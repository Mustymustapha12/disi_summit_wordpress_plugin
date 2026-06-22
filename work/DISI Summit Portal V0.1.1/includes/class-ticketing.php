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

        add_action(
            'admin_post_disi_export_tickets',
            [$this, 'handle_export']
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

    public static function get_eligible(
        $search = '',
        $type = '',
        $ticket_status = '',
        $scan_status = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();
        $where = self::eligible_where(
            $search,
            $type,
            $ticket_status,
            $scan_status
        );

        return $wpdb->get_results(
            "SELECT *
             FROM {$table}
             {$where}
             ORDER BY paid_at DESC, id DESC"
        );
    }

    public static function get_eligible_paginated(
        $page,
        $per_page,
        $search = '',
        $type = '',
        $ticket_status = '',
        $scan_status = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();
        $where = self::eligible_where(
            $search,
            $type,
            $ticket_status,
            $scan_status
        );
        $offset = (max(1, intval($page)) - 1) * intval($per_page);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 {$where}
                 ORDER BY paid_at DESC, id DESC
                 LIMIT %d OFFSET %d",
                intval($per_page),
                $offset
            )
        );
    }

    public static function eligible_count(
        $search = '',
        $type = '',
        $ticket_status = '',
        $scan_status = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();
        $where = self::eligible_where(
            $search,
            $type,
            $ticket_status,
            $scan_status
        );

        return intval(
            $wpdb->get_var(
                "SELECT COUNT(*)
                 FROM {$table}
                 {$where}"
            )
        );
    }

    private static function eligible_where(
        $search,
        $type,
        $ticket_status,
        $scan_status
    ) {

        global $wpdb;

        $where = "WHERE status = 'approved'
                  AND payment_status = 'paid'";

        if (!empty($type)) {
            $where .= $wpdb->prepare(
                ' AND registration_type = %s',
                $type
            );
        }

        if ($ticket_status === 'issued') {
            $where .= " AND ticket_token IS NOT NULL
                        AND ticket_token != ''";
        } elseif ($ticket_status === 'not_issued') {
            $where .= " AND (
                ticket_token IS NULL
                OR ticket_token = ''
            )";
        }

        if ($scan_status === 'scanned') {
            $where .= ' AND ticket_scan_count > 0';
        } elseif ($scan_status === 'not_scanned') {
            $where .= ' AND ticket_scan_count = 0';
        }

        if (!empty($search)) {
            $term = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (
                    email LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
                    OR business_name LIKE %s
                    OR phone LIKE %s
                )",
                $term,
                $term,
                $term,
                $term,
                $term
            );
        }

        return $where;
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

    public function handle_export() {

        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to export E-tickets.');
        }

        check_admin_referer('disi_export_tickets');

        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $filters = [
            'search' => sanitize_text_field($_GET['s'] ?? ''),
            'type' => sanitize_text_field($_GET['type'] ?? ''),
            'ticket_status' => sanitize_text_field(
                $_GET['ticket_status'] ?? ''
            ),
            'scan_status' => sanitize_text_field(
                $_GET['scan_status'] ?? ''
            )
        ];
        $rows = self::get_eligible(
            $filters['search'],
            $filters['type'],
            $filters['ticket_status'],
            $filters['scan_status']
        );

        if ($format === 'pdf') {
            self::export_pdf($rows, $filters);
        }

        self::export_csv($rows);
    }

    private static function export_csv($rows) {

        $filename = 'disi-etickets-' . gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' .
            $filename . '"'
        );

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv(
            $output,
            [
                'Ticket Number',
                'Registration Number',
                'Participant',
                'Business Name',
                'Email',
                'Phone',
                'Registration Type',
                'Amount Paid',
                'Paid At',
                'Ticket Status',
                'E-ticket URL',
                'Ticket Issued At',
                'Ticket Email Sent At',
                'Scan Count',
                'Last Scanned At'
            ]
        );

        foreach ($rows as $row) {
            $name = trim(
                ($row->first_name ?? '') . ' ' .
                ($row->last_name ?? '')
            );
            $issued = !empty($row->ticket_token);
            $values = [
                $issued ? self::ticket_number($row) : '',
                DISI_Registration_Manager::get_registration_number($row),
                $name ?: $row->email,
                $row->business_name ?? '',
                $row->email,
                $row->phone,
                DISI_Registration_Manager::label_registration_type(
                    $row->registration_type
                ),
                number_format(
                    floatval($row->total_amount ?? 0),
                    2,
                    '.',
                    ''
                ),
                $row->paid_at ?? '',
                $issued ? 'Issued' : 'Not Issued',
                $issued ? self::ticket_url($row) : '',
                $row->ticket_issued_at ?? '',
                $row->ticket_email_sent_at ?? '',
                intval($row->ticket_scan_count ?? 0),
                $row->ticket_last_scanned_at ?? ''
            ];

            fputcsv(
                $output,
                array_map([__CLASS__, 'csv_value'], $values)
            );
        }

        fclose($output);
        exit;
    }

    private static function csv_value($value) {

        $value = (string) $value;

        if (preg_match('/^[=\-+@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private static function export_pdf($rows, $filters) {

        $pdf = new DISI_Tickets_Report_PDF(
            DISI_PLUGIN_DIR . 'assets/images/disi-logo.png'
        );
        $pdf->SetTitle('DISI Summit E-ticketing Report');
        $pdf->SetAuthor('DISI Summit Portal');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->report_summary(
            current_time('mysql'),
            self::ticket_filter_summary($filters),
            count($rows)
        );
        $pdf->start_table();

        foreach ($rows as $index => $row) {
            $pdf->ticket_row($index + 1, $row);
        }

        $pdf->finish_table(empty($rows));
        $pdf->Output(
            'D',
            'disi-etickets-' . gmdate('Y-m-d-His') . '.pdf'
        );
        exit;
    }

    private static function ticket_filter_summary($filters) {

        $labels = [];

        if (!empty($filters['type'])) {
            $labels[] = 'Type: ' .
                DISI_Registration_Manager::label_registration_type(
                    $filters['type']
                );
        }

        if (!empty($filters['ticket_status'])) {
            $labels[] = 'Ticket: ' . ucwords(
                str_replace('_', ' ', $filters['ticket_status'])
            );
        }

        if (!empty($filters['scan_status'])) {
            $labels[] = 'Scan: ' . ucwords(
                str_replace('_', ' ', $filters['scan_status'])
            );
        }

        if (!empty($filters['search'])) {
            $labels[] = 'Search: ' . $filters['search'];
        }

        return empty($labels)
            ? 'All approved and paid participants'
            : implode('; ', $labels);
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

class DISI_Tickets_Report_PDF extends FPDF {

    private $logo_path;
    private $table_started = false;
    private $row_index = 0;
    private $widths = [7, 24, 24, 40, 52, 27, 30, 29, 10, 34];
    private $headers = [
        '#',
        'Ticket',
        'Registration',
        'Participant',
        'Email',
        'Phone',
        'Type',
        'Issued',
        'Scans',
        'Last Scan'
    ];

    public function __construct($logo_path) {

        parent::__construct('L', 'mm', 'A4');
        $this->logo_path = $logo_path;
    }

    public function Header() {

        $this->SetFillColor(23, 43, 59);
        $this->Rect(0, 0, 297, 25, 'F');

        if (is_readable($this->logo_path)) {
            $this->Image($this->logo_path, 3, -5, 50, 35);
        }

        $this->SetXY(47, 6);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 7, 'DISI Summit 2026 E-ticketing Report', 0, 1);
        $this->SetX(47);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(222, 231, 235);
        $this->Cell(
            0,
            5,
            'Approved and verified paid participant tickets',
            0,
            1
        );
        $this->SetY(29);

        if ($this->table_started) {
            $this->table_header();
        }
    }

    public function Footer() {

        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(
            0,
            6,
            'DISI Summit Portal | E-ticketing | Page ' . $this->PageNo(),
            0,
            0,
            'C'
        );
    }

    public function report_summary($exported_at, $filters, $record_count) {

        $this->SetFillColor(243, 247, 246);
        $this->SetDrawColor(205, 219, 215);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', '', 8);
        $this->MultiCell(
            0,
            5,
            DISI_Exporter::pdf_text(
                'Exported: ' . $exported_at .
                '    |    Records: ' . number_format($record_count) .
                '    |    Filters: ' . $filters
            ),
            1,
            'L',
            true
        );
        $this->Ln(3);
    }

    public function start_table() {

        $this->table_started = true;
        $this->table_header();
    }

    public function finish_table($empty) {

        if (!$empty) {
            return;
        }

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(
            0,
            10,
            'No E-tickets matched the selected filters.',
            1,
            1,
            'C'
        );
    }

    public function ticket_row($number, $row) {

        $name = trim(
            ($row->first_name ?? '') . ' ' .
            ($row->last_name ?? '')
        );
        $issued = !empty($row->ticket_token);

        $this->table_row([
            $number,
            $issued ? DISI_Ticketing::ticket_number($row) : 'Not issued',
            DISI_Registration_Manager::get_registration_number($row),
            $name ?: $row->email,
            $row->email,
            $row->phone,
            DISI_Registration_Manager::label_registration_type(
                $row->registration_type
            ),
            $row->ticket_issued_at ?: '-',
            intval($row->ticket_scan_count ?? 0),
            $row->ticket_last_scanned_at ?: 'Not scanned'
        ]);
    }

    private function table_header() {

        $this->SetFillColor(21, 118, 100);
        $this->SetDrawColor(255, 255, 255);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 6.5);

        foreach ($this->headers as $index => $header) {
            $this->Cell(
                $this->widths[$index],
                8,
                $header,
                1,
                0,
                'C',
                true
            );
        }

        $this->Ln();
    }

    private function table_row($data) {

        $this->SetFont('Helvetica', '', 6.5);
        $line_counts = [];

        foreach ($data as $index => $value) {
            $line_counts[] = $this->line_count(
                $this->widths[$index],
                DISI_Exporter::pdf_text($value)
            );
        }

        $height = max(8, max($line_counts) * 3.4 + 2);

        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }

        $fill = $this->row_index % 2 === 0;
        $this->SetFillColor(246, 249, 248);
        $this->SetDrawColor(207, 218, 215);
        $this->SetTextColor(35, 45, 48);

        foreach ($data as $index => $value) {
            $width = $this->widths[$index];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect(
                $x,
                $y,
                $width,
                $height,
                $fill ? 'DF' : 'D'
            );
            $this->SetXY($x + 1, $y + 1);
            $this->MultiCell(
                $width - 2,
                3.4,
                DISI_Exporter::pdf_text($value),
                0,
                in_array($index, [0, 8], true) ? 'C' : 'L',
                false
            );
            $this->SetXY($x + $width, $y);
        }

        $this->SetY($this->GetY() + $height);
        $this->row_index++;
    }

    private function line_count($width, $text) {

        $cw = &$this->CurrentFont['cw'];
        $max_width = ($width - 2) * 1000 / $this->FontSize;
        $text = str_replace("\r", '', $text);
        $length = strlen($text);

        if ($length > 0 && $text[$length - 1] === "\n") {
            $length--;
        }

        $separator = -1;
        $index = 0;
        $line_start = 0;
        $line_width = 0;
        $lines = 1;

        while ($index < $length) {
            $character = $text[$index];

            if ($character === "\n") {
                $index++;
                $separator = -1;
                $line_start = $index;
                $line_width = 0;
                $lines++;
                continue;
            }

            if ($character === ' ') {
                $separator = $index;
            }

            $line_width += $cw[$character] ?? 0;

            if ($line_width > $max_width) {
                if ($separator === -1 || $separator < $line_start) {
                    if ($index === $line_start) {
                        $index++;
                    }
                } else {
                    $index = $separator + 1;
                }

                $separator = -1;
                $line_start = $index;
                $line_width = 0;
                $lines++;
            } else {
                $index++;
            }
        }

        return $lines;
    }
}
