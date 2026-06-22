<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
}

class DISI_Ticketing {

    public function __construct() {

        add_action(
            'template_redirect',
            [$this, 'handle_public_ticket']
        );

        add_action(
            'admin_post_disi_send_ticket',
            [$this, 'handle_admin_send']
        );
    }

    public static function issue_and_send($registration_id) {

        $registration = DISI_Registration_Manager::get($registration_id);

        if (!self::is_eligible($registration)) {
            return new WP_Error(
                'ticket_not_eligible',
                'Tickets are available only for approved and paid registrations.'
            );
        }

        if (empty($registration->ticket_token)) {
            $registration = self::issue($registration);

            if (is_wp_error($registration)) {
                return $registration;
            }
        }

        return self::send_email($registration);
    }

    public static function get_eligible($search = '') {

        global $wpdb;

        $table = DISI_Database::get_table();
        $where = "WHERE status = 'approved'
                  AND payment_status = 'paid'";

        if (!empty($search)) {
            $term = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (
                    email LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
                    OR phone LIKE %s
                )",
                $term,
                $term,
                $term,
                $term
            );
        }

        return $wpdb->get_results(
            "SELECT *
             FROM {$table}
             {$where}
             ORDER BY paid_at DESC, id DESC"
        );
    }

    public static function ticket_url($registration, $admin_preview = false) {

        if (empty($registration->ticket_token)) {
            return '';
        }

        $args = [
            'disi_ticket' => $registration->ticket_token
        ];

        if ($admin_preview) {
            $args['disi_preview'] = 1;
            $args['_wpnonce'] = wp_create_nonce(
                'disi_ticket_preview_' . intval($registration->id)
            );
        }

        return add_query_arg($args, home_url('/'));
    }

    public static function ticket_number($registration) {

        return sprintf(
            'DISI-TKT-%06d',
            intval($registration->id)
        );
    }

    public function handle_admin_send() {

        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to send tickets.');
        }

        $registration_id = intval($_GET['registration_id'] ?? 0);

        check_admin_referer(
            'disi_send_ticket_' . $registration_id
        );

        $result = self::issue_and_send($registration_id);

        wp_safe_redirect(
            add_query_arg(
                [
                    'page' => 'disi-eticketing',
                    'ticket_notice' => is_wp_error($result)
                        ? 'error'
                        : 'sent',
                    'ticket_message' => is_wp_error($result)
                        ? $result->get_error_message()
                        : 'Ticket email sent successfully.'
                ],
                admin_url('admin.php')
            )
        );
        exit;
    }

    public function handle_public_ticket() {

        $token = sanitize_text_field(
            wp_unslash($_GET['disi_ticket'] ?? '')
        );

        if (empty($token)) {
            return;
        }

        $registration = self::get_by_token($token);

        if (!self::is_eligible($registration)) {
            status_header(404);
            nocache_headers();
            echo self::ticket_error_page();
            exit;
        }

        $is_admin_preview = (
            current_user_can('manage_options') &&
            !empty($_GET['disi_preview']) &&
            wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash($_GET['_wpnonce'] ?? '')
                ),
                'disi_ticket_preview_' . intval($registration->id)
            )
        );

        if (!$is_admin_preview) {
            self::record_scan($registration->id);
        }
        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        echo self::ticket_page($registration);
        exit;
    }

    public static function barcode_svg($value, $height = 72) {

        $bars = self::code128_values($value);

        if (empty($bars)) {
            return '';
        }

        $modules = 0;

        foreach ($bars as $pattern) {
            $modules += array_sum(str_split($pattern));
        }

        $quiet = 10;
        $width = $modules + ($quiet * 2);
        $x = $quiet;
        $rectangles = '';

        foreach ($bars as $pattern) {
            $parts = str_split($pattern);

            foreach ($parts as $index => $part) {
                $bar_width = intval($part);

                if ($index % 2 === 0) {
                    $rectangles .= sprintf(
                        '<rect x="%d" y="0" width="%d" height="%d"/>',
                        $x,
                        $bar_width,
                        $height
                    );
                }

                $x += $bar_width;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" role="img" aria-label="Ticket barcode"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#172b3b">%s</g></svg>',
            $width,
            $height,
            $rectangles
        );
    }

    public static function draw_pdf_barcode(
        $pdf,
        $value,
        $x,
        $y,
        $width,
        $height
    ) {

        $bars = self::code128_values($value);

        if (empty($bars)) {
            return;
        }

        $modules = 20;

        foreach ($bars as $pattern) {
            $modules += array_sum(str_split($pattern));
        }

        $module_width = $width / $modules;
        $current_x = $x + (10 * $module_width);
        $pdf->SetFillColor(23, 43, 59);

        foreach ($bars as $pattern) {
            foreach (str_split($pattern) as $index => $part) {
                $bar_width = intval($part) * $module_width;

                if ($index % 2 === 0) {
                    $pdf->Rect(
                        $current_x,
                        $y,
                        $bar_width,
                        $height,
                        'F'
                    );
                }

                $current_x += $bar_width;
            }
        }
    }

    private static function issue($registration) {

        global $wpdb;

        try {
            $token = rtrim(
                strtr(
                    base64_encode(random_bytes(24)),
                    '+/',
                    '-_'
                ),
                '='
            );
        } catch (Exception $exception) {
            $token = wp_generate_password(32, false, false);
        }

        $issued_at = current_time('mysql');
        $updated = $wpdb->update(
            DISI_Database::get_table(),
            [
                'ticket_token' => $token,
                'ticket_issued_at' => $issued_at,
                'updated_at' => $issued_at
            ],
            ['id' => intval($registration->id)]
        );

        if ($updated === false) {
            return new WP_Error(
                'ticket_issue_failed',
                'The ticket could not be issued.'
            );
        }

        return DISI_Registration_Manager::get($registration->id);
    }

    private static function send_email($registration) {

        global $wpdb;

        $ticket_url = self::ticket_url($registration);
        $attachment = self::create_pdf($registration);

        if (is_wp_error($attachment)) {
            return $attachment;
        }

        $name = trim(
            ($registration->first_name ?? '') . ' ' .
            ($registration->last_name ?? '')
        );
        $name = $name ?: 'Participant';
        $logo = DISI_PLUGIN_URL . 'assets/images/disi-logo.png';
        $barcode = self::barcode_svg($ticket_url, 58);

        $message = '
        <html>
        <body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#172b3b;">
            <div style="max-width:720px;margin:30px auto;background:#fff;border-top:8px solid #ffc801;">
                <div style="background:#172b3b;padding:24px;text-align:center;">
                    <img src="' . esc_url($logo) . '" alt="DISI" style="max-width:190px;height:auto;">
                </div>
                <div style="padding:34px;">
                    <h2 style="color:#157664;margin-top:0;">Your DISI Summit 2026 E-ticket</h2>
                    <p>Dear ' . esc_html($name) . ',</p>
                    <p>Your registration and payment have been confirmed. Present the attached ticket at the event entrance.</p>
                    <table cellpadding="8" style="width:100%;border-collapse:collapse;background:#f8faf9;">
                        <tr><td>Ticket Number</td><td><strong>' . esc_html(self::ticket_number($registration)) . '</strong></td></tr>
                        <tr><td>Registration Number</td><td><strong>' . esc_html(DISI_Registration_Manager::get_registration_number($registration)) . '</strong></td></tr>
                        <tr><td>Participant</td><td><strong>' . esc_html($name) . '</strong></td></tr>
                        <tr><td>Registration Type</td><td><strong>' . esc_html(DISI_Registration_Manager::label_registration_type($registration->registration_type)) . '</strong></td></tr>
                    </table>
                    <div style="margin:28px 0;background:#fff;padding:15px;border:1px solid #dbe5e2;">
                        ' . $barcode . '
                    </div>
                    <p style="text-align:center;">
                        <a href="' . esc_url($ticket_url) . '" style="display:inline-block;background:#ffc801;color:#172b3b;padding:13px 24px;text-decoration:none;font-weight:bold;">View E-ticket</a>
                    </p>
                    <p style="font-size:13px;color:#64706e;">The barcode opens the secure electronic copy of this ticket. Keep the ticket private.</p>
                </div>
            </div>
        </body>
        </html>';

        $sent = wp_mail(
            $registration->email,
            'Your DISI Summit 2026 E-ticket',
            $message,
            [
                'Content-Type: text/html; charset=UTF-8',
                'From: DISI Summit 2026 <noreply@disisummit.org>'
            ],
            [$attachment]
        );

        @unlink($attachment);

        if (!$sent) {
            return new WP_Error(
                'ticket_email_failed',
                'WordPress could not send the ticket email.'
            );
        }

        $wpdb->update(
            DISI_Database::get_table(),
            [
                'ticket_email_sent_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => intval($registration->id)]
        );

        return true;
    }

    private static function create_pdf($registration) {

        $temporary_path = wp_tempnam(
            'disi-ticket-' . intval($registration->id)
        );

        if (empty($temporary_path)) {
            return new WP_Error(
                'ticket_pdf_path_failed',
                'A temporary ticket file could not be created.'
            );
        }

        $path = $temporary_path . '.pdf';

        if (!rename($temporary_path, $path)) {
            $path = $temporary_path;
        }

        $pdf = new DISI_Ticket_PDF(
            DISI_PLUGIN_DIR . 'assets/images/disi-logo.png'
        );
        $pdf->SetTitle(self::ticket_number($registration));
        $pdf->SetAuthor('DISI Summit Portal');
        $pdf->AddPage();
        $pdf->ticket($registration, self::ticket_url($registration));
        $pdf->Output('F', $path);

        return $path;
    }

    private static function get_by_token($token) {

        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM " . DISI_Database::get_table() . "
                 WHERE ticket_token = %s
                 LIMIT 1",
                $token
            )
        );
    }

    private static function record_scan($registration_id) {

        global $wpdb;

        $table = DISI_Database::get_table();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET ticket_scan_count = ticket_scan_count + 1,
                     ticket_last_scanned_at = %s,
                     updated_at = %s
                 WHERE id = %d",
                current_time('mysql'),
                current_time('mysql'),
                intval($registration_id)
            )
        );
    }

    private static function is_eligible($registration) {

        return (
            is_object($registration) &&
            ($registration->status ?? '') === 'approved' &&
            ($registration->payment_status ?? '') === 'paid'
        );
    }

    private static function ticket_page($registration) {

        $name = trim(
            ($registration->first_name ?? '') . ' ' .
            ($registration->last_name ?? '')
        );
        $url = self::ticket_url($registration);
        $barcode = self::barcode_svg($url, 80);
        $logo = DISI_PLUGIN_URL . 'assets/images/disi-logo.png';

        return '<!doctype html>
        <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc_html(self::ticket_number($registration)) . '</title>
        <style>
        body{margin:0;background:#eef2f1;font-family:Arial,sans-serif;color:#172b3b}
        .ticket{max-width:760px;margin:28px auto;background:#fff;border-top:8px solid #ffc801;box-shadow:0 10px 30px rgba(23,43,59,.12)}
        .head{background:#172b3b;padding:24px;text-align:center}.head img{max-width:190px;height:auto}
        .body{padding:32px}.valid{display:inline-block;background:#dcfce7;color:#166534;padding:7px 12px;font-weight:700;border-radius:6px}
        .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:24px 0}
        .field{border:1px solid #dbe5e2;padding:13px}.label{font-size:12px;color:#687673;margin-bottom:6px}.value{font-weight:700;overflow-wrap:anywhere}
        .barcode{border:1px solid #dbe5e2;padding:18px;background:#fff}.barcode svg{width:100%;height:100px}
        .note{font-size:13px;color:#687673;text-align:center;margin-top:16px}
        @media(max-width:600px){.ticket{margin:0}.body{padding:20px}.grid{grid-template-columns:1fr}}
        </style></head><body>
        <main class="ticket"><div class="head"><img src="' . esc_url($logo) . '" alt="DISI"></div>
        <div class="body"><span class="valid">VALID E-TICKET</span>
        <h1>DISI Summit 2026</h1>
        <div class="grid">
        ' . self::ticket_field('Ticket Number', self::ticket_number($registration)) .
        self::ticket_field('Registration Number', DISI_Registration_Manager::get_registration_number($registration)) .
        self::ticket_field('Participant', $name ?: $registration->email) .
        self::ticket_field('Email', $registration->email) .
        self::ticket_field('Phone', $registration->phone) .
        self::ticket_field('Registration Type', DISI_Registration_Manager::label_registration_type($registration->registration_type)) .
        self::ticket_field('Payment Status', 'Paid') .
        self::ticket_field('Issued At', $registration->ticket_issued_at) .
        '</div><div class="barcode">' . $barcode . '</div>
        <p class="note">Present this barcode at the event entrance. This ticket is personal and should not be shared.</p>
        </div></main></body></html>';
    }

    private static function ticket_error_page() {

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ticket Not Found</title></head><body style="font-family:Arial,sans-serif;background:#f3f4f6;color:#172b3b;padding:40px;text-align:center"><h1>Ticket not found</h1><p>This ticket is invalid or no longer eligible.</p></body></html>';
    }

    private static function ticket_field($label, $value) {

        return '<div class="field"><div class="label">' .
            esc_html($label) .
            '</div><div class="value">' .
            esc_html($value ?: '-') .
            '</div></div>';
    }

    private static function code128_values($value) {

        $patterns = self::code128_patterns();
        $codes = [104];
        $checksum = 104;
        $position = 1;

        foreach (str_split((string) $value) as $character) {
            $ascii = ord($character);

            if ($ascii < 32 || $ascii > 126) {
                continue;
            }

            $code = $ascii - 32;
            $codes[] = $code;
            $checksum += $code * $position;
            $position++;
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        return array_map(
            function ($code) use ($patterns) {
                return $patterns[$code];
            },
            $codes
        );
    }

    private static function code128_patterns() {

        return [
            '212222','222122','222221','121223','121322','131222',
            '122213','122312','132212','221213','221312','231212',
            '112232','122132','122231','113222','123122','123221',
            '223211','221132','221231','213212','223112','312131',
            '311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321',
            '112313','132113','132311','211313','231113','231311',
            '112133','112331','132131','113123','113321','133121',
            '313121','211331','231131','213113','213311','213131',
            '311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124',
            '121421','141122','141221','112214','112412','122114',
            '122411','142112','142211','241211','221114','413111',
            '241112','134111','111242','121142','121241','114212',
            '124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113',
            '114311','411113','411311','113141','114131','311141',
            '411131','211412','211214','211232','2331112'
        ];
    }
}

class DISI_Ticket_PDF extends FPDF {

    private $logo_path;

    public function __construct($logo_path) {

        parent::__construct('P', 'mm', 'A4');
        $this->logo_path = $logo_path;
        $this->SetMargins(14, 14, 14);
        $this->SetAutoPageBreak(false);
    }

    public function ticket($registration, $ticket_url) {

        $name = trim(
            ($registration->first_name ?? '') . ' ' .
            ($registration->last_name ?? '')
        );

        $this->SetFillColor(23, 43, 59);
        $this->Rect(0, 0, 210, 48, 'F');

        if (is_readable($this->logo_path)) {
            $this->Image($this->logo_path, 12, -3, 57, 40);
        }

        $this->SetXY(72, 12);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 19);
        $this->Cell(0, 9, 'DISI SUMMIT 2026', 0, 1);
        $this->SetX(72);
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 6, 'Official E-ticket', 0, 1);

        $this->SetXY(14, 59);
        $this->SetFillColor(220, 252, 231);
        $this->SetTextColor(22, 101, 52);
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(42, 9, 'VALID - PAID', 0, 1, 'C', true);

        $this->Ln(8);
        $this->field('Ticket Number', DISI_Ticketing::ticket_number($registration));
        $this->field(
            'Registration Number',
            DISI_Registration_Manager::get_registration_number($registration)
        );
        $this->field('Participant', $name ?: $registration->email);
        $this->field('Email', $registration->email);
        $this->field('Phone', $registration->phone);
        $this->field(
            'Registration Type',
            DISI_Registration_Manager::label_registration_type(
                $registration->registration_type
            )
        );
        $this->field('Issued At', $registration->ticket_issued_at);

        $this->SetY(184);
        $this->SetDrawColor(219, 229, 226);
        $this->Rect(14, 180, 182, 72, 'D');
        DISI_Ticketing::draw_pdf_barcode(
            $this,
            $ticket_url,
            20,
            192,
            170,
            38
        );

        $this->SetY(234);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(
            0,
            6,
            DISI_Exporter::pdf_text(
                DISI_Ticketing::ticket_number($registration)
            ),
            0,
            1,
            'C'
        );
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 112, 110);
        $this->MultiCell(
            0,
            5,
            'Scan the barcode to open the secure electronic copy. Keep this ticket private.',
            0,
            'C'
        );
    }

    private function field($label, $value) {

        $this->SetTextColor(100, 112, 110);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(45, 7, $label, 0, 0);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', 'B', 10);
        $this->MultiCell(
            0,
            7,
            DISI_Exporter::pdf_text($value ?: '-'),
            0,
            'L'
        );
    }
}
