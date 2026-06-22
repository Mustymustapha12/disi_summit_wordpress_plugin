<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
}

if (!class_exists('chillerlan\\QRCode\\QRCode')) {
    require_once __DIR__ . '/vendor/autoload.php';
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

    public static function qr_svg($value) {

        $matrix = self::qr_matrix($value);

        if (empty($matrix)) {
            return '';
        }

        $rectangles = '';

        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $is_dark) {
                if ($is_dark) {
                    $rectangles .= sprintf(
                        '<rect x="%d" y="%d" width="1" height="1"/>',
                        $x,
                        $y
                    );
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" shape-rendering="crispEdges" role="img" aria-label="Ticket QR code"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#172b3b">%2$s</g></svg>',
            count($matrix),
            $rectangles
        );
    }

    public static function draw_pdf_qr(
        $pdf,
        $value,
        $x,
        $y,
        $size
    ) {

        $matrix = self::qr_matrix($value);

        if (empty($matrix)) {
            return;
        }

        $module_size = $size / count($matrix);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $size, $size, 'F');
        $pdf->SetFillColor(23, 43, 59);

        foreach ($matrix as $row => $modules) {
            foreach ($modules as $column => $is_dark) {
                if ($is_dark) {
                    $pdf->Rect(
                        $x + ($column * $module_size),
                        $y + ($row * $module_size),
                        $module_size,
                        $module_size,
                        'F'
                    );
                }
            }
        }
    }

    private static function qr_matrix($value) {

        if (empty($value)) {
            return [];
        }

        $options = new \chillerlan\QRCode\QROptions([
            'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M,
            'addQuietzone' => true,
            'quietzoneSize' => 4
        ]);

        return (new \chillerlan\QRCode\QRCode($options))
            ->getMatrix((string) $value)
            ->matrix(true);
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
        $qr_code = self::qr_svg($ticket_url);

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
                    <div style="margin:28px auto;background:#fff;padding:15px;border:1px solid #dbe5e2;max-width:220px;">
                        ' . $qr_code . '
                    </div>
                    <p style="text-align:center;">
                        <a href="' . esc_url($ticket_url) . '" style="display:inline-block;background:#ffc801;color:#172b3b;padding:13px 24px;text-decoration:none;font-weight:bold;">View E-ticket</a>
                    </p>
                    <p style="font-size:13px;color:#64706e;">The QR code opens the secure electronic copy of this ticket. Keep the ticket private.</p>
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
        $qr_code = self::qr_svg($url);
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
        .qr-code{border:1px solid #dbe5e2;padding:18px;background:#fff}.qr-code svg{display:block;width:min(100%,240px);height:auto;margin:auto}
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
        '</div><div class="qr-code">' . $qr_code . '</div>
        <p class="note">Present this QR code at the event entrance. This ticket is personal and should not be shared.</p>
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

}

class DISI_Ticket_PDF extends FPDF {

    private $logo_path;

    public function __construct($logo_path) {

        parent::__construct('L', 'mm', [100, 240]);
        $this->logo_path = $logo_path;
        $this->SetMargins(0, 0, 0);
        $this->SetAutoPageBreak(false);
    }

    public function ticket($registration, $ticket_url) {

        $name = trim(
            ($registration->first_name ?? '') . ' ' .
            ($registration->last_name ?? '')
        );
        $name = $name ?: $registration->email;
        $registration_number =
            DISI_Registration_Manager::get_registration_number(
                $registration
            );
        $registration_type =
            DISI_Registration_Manager::label_registration_type(
                $registration->registration_type
            );
        $ticket_number = DISI_Ticketing::ticket_number($registration);

        $this->SetFillColor(244, 248, 247);
        $this->Rect(0, 0, 240, 100, 'F');

        $this->SetFillColor(23, 43, 59);
        $this->Rect(0, 0, 240, 22, 'F');

        $this->SetFillColor(255, 200, 1);
        $this->Rect(0, 22, 240, 2.5, 'F');

        if (is_readable($this->logo_path)) {
            $this->Image($this->logo_path, 7, 1.5, 34, 20);
        }

        $this->SetXY(47, 4.5);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(104, 7, 'DISI SUMMIT 2026', 0, 1);
        $this->SetX(47);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(213, 224, 221);
        $this->Cell(104, 5, 'OFFICIAL ADMISSION TICKET', 0, 1);

        $this->SetXY(172, 5);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(255, 200, 1);
        $this->Cell(61, 5, 'ADMIT ONE', 0, 1, 'R');
        $this->SetX(172);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(61, 5, $ticket_number, 0, 1, 'R');

        $this->SetXY(8, 30);
        $this->SetFillColor(220, 252, 231);
        $this->SetTextColor(22, 101, 52);
        $this->SetFont('Helvetica', 'B', 8);
        $this->Cell(28, 7, 'VALID - PAID', 0, 0, 'C', true);

        $this->SetXY(8, 42);
        $this->SetTextColor(100, 112, 110);
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->Cell(100, 4, 'PARTICIPANT', 0, 1);
        $this->SetX(8);
        $this->SetTextColor(23, 43, 59);
        $this->fit_font(
            DISI_Exporter::pdf_text($name),
            150,
            16,
            10,
            'B'
        );
        $this->Cell(
            150,
            9,
            DISI_Exporter::pdf_text($name),
            0,
            1
        );

        $this->detail(
            8,
            59,
            48,
            'REGISTRATION NO.',
            $registration_number
        );
        $this->detail(
            58,
            59,
            48,
            'REGISTRATION TYPE',
            $registration_type
        );
        $this->detail(
            108,
            59,
            51,
            'PHONE',
            $registration->phone
        );
        $this->detail(
            8,
            75,
            98,
            'EMAIL',
            $registration->email
        );
        $this->detail(
            108,
            75,
            51,
            'ISSUED',
            $registration->ticket_issued_at
        );

        $this->SetFillColor(83, 150, 92);
        $this->Rect(0, 94, 165, 6, 'F');
        $this->SetXY(8, 95);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 6.5);
        $this->Cell(
            148,
            4,
            'PRESENT THIS TICKET AT THE EVENT ENTRANCE',
            0,
            0
        );

        $this->SetDrawColor(177, 190, 187);
        for ($y = 27; $y < 96; $y += 4) {
            $this->Line(165, $y, 165, min($y + 2, 96));
        }

        $this->SetXY(171, 30);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', 'B', 9);
        $this->Cell(61, 5, 'SCAN TO VERIFY', 0, 1, 'C');

        DISI_Ticketing::draw_pdf_qr(
            $this,
            $ticket_url,
            178,
            38,
            47
        );

        $this->SetXY(171, 87);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->Cell(
            61,
            4,
            DISI_Exporter::pdf_text($ticket_number),
            0,
            1,
            'C'
        );
        $this->SetX(171);
        $this->SetFont('Helvetica', '', 5.8);
        $this->SetTextColor(100, 112, 110);
        $this->Cell(
            61,
            4,
            'Secure electronic ticket',
            0,
            0,
            'C'
        );
    }

    private function detail($x, $y, $width, $label, $value) {

        $this->SetXY($x, $y);
        $this->SetTextColor(100, 112, 110);
        $this->SetFont('Helvetica', 'B', 6);
        $this->Cell($width, 4, $label, 0, 1);
        $this->SetX($x);
        $this->SetTextColor(23, 43, 59);
        $this->fit_font(
            DISI_Exporter::pdf_text($value ?: '-'),
            $width,
            8.5,
            6.5,
            'B'
        );
        $this->Cell(
            $width,
            6,
            DISI_Exporter::pdf_text($value ?: '-'),
            0,
            0
        );
    }

    private function fit_font(
        $text,
        $width,
        $maximum,
        $minimum,
        $style = ''
    ) {

        $size = $maximum;
        $this->SetFont('Helvetica', $style, $size);

        while (
            $size > $minimum &&
            $this->GetStringWidth($text) > $width
        ) {
            $size -= 0.5;
            $this->SetFont('Helvetica', $style, $size);
        }
    }
}
