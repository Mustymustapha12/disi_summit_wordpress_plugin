<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/vendor/fpdf/fpdf.php';
}

class DISI_Exporter {

    public function __construct() {

        add_action(
            'admin_post_disi_export_registrations',
            [$this, 'export']
        );
    }

    public function export() {

        if (!current_user_can('manage_options')) {
            wp_die('You are not allowed to export registrations.');
        }

        check_admin_referer('disi_export_registrations');

        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $filters = [
            'type' => sanitize_text_field($_GET['type'] ?? ''),
            'status' => sanitize_text_field($_GET['status'] ?? ''),
            'payment_status' => sanitize_text_field(
                $_GET['payment_status'] ?? ''
            ),
            'search' => sanitize_text_field($_GET['s'] ?? '')
        ];

        $rows = DISI_Registration_Manager::get_filtered(
            $filters['type'],
            $filters['status'],
            $filters['payment_status'],
            $filters['search']
        );

        if ($format === 'pdf') {
            $this->export_pdf($rows, $filters);
        }

        $this->export_csv($rows);
    }

    private function export_csv($rows) {

        $submitted_keys = $this->submitted_keys($rows);
        $filename = 'disi-registrations-' .
            gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header(
            'Content-Disposition: attachment; filename="' .
            $filename . '"'
        );

        $output = fopen('php://output', 'w');

        fwrite($output, "\xEF\xBB\xBF");

        $headers = array_merge(
            [
                'Registration ID',
                'Registration Number',
                'Registration Type',
                'First Name',
                'Last Name',
                'Business Name',
                'Email',
                'Phone',
                'Source Plugin',
                'Source Form ID',
                'Source Entry ID',
                'Registration Amount',
                'Workshop Amount',
                'Total Amount',
                'Registration Status',
                'Payment Status',
                'Paystack Reference',
                'Paystack Transaction ID',
                'Payment Mode',
                'Payment Link',
                'Ticket Number',
                'E-ticket URL',
                'Ticket Issued At',
                'Ticket Email Sent At',
                'Ticket Scan Count',
                'Ticket Last Scanned At',
                'Rejection Reason',
                'Approved By',
                'Approved At',
                'Paid At',
                'Created At',
                'Updated At'
            ],
            array_map(
                function ($key) {
                    return 'Submitted: ' . self::field_label($key);
                },
                $submitted_keys
            )
        );

        fputcsv($output, $headers);

        foreach ($rows as $row) {
            $submitted = $this->submitted_data($row);
            $values = [
                $row->id,
                DISI_Registration_Manager::get_registration_number($row),
                DISI_Registration_Manager::label_registration_type(
                    $row->registration_type
                ),
                $row->first_name,
                $row->last_name,
                $row->business_name,
                $row->email,
                $row->phone,
                $row->source_plugin,
                $row->form_id,
                $row->source_entry_id,
                number_format(floatval($row->registration_amount), 2, '.', ''),
                number_format(floatval($row->workshop_amount), 2, '.', ''),
                number_format(floatval($row->total_amount), 2, '.', ''),
                ucfirst($row->status),
                ucfirst($row->payment_status ?? 'unpaid'),
                $row->paystack_reference,
                $row->paystack_transaction_id,
                $row->paystack_mode,
                $row->paystack_authorization_url,
                !empty($row->ticket_token)
                    ? DISI_Ticketing::ticket_number($row)
                    : '',
                !empty($row->ticket_token)
                    ? DISI_Ticketing::ticket_url($row)
                    : '',
                $row->ticket_issued_at ?? '',
                $row->ticket_email_sent_at ?? '',
                intval($row->ticket_scan_count ?? 0),
                $row->ticket_last_scanned_at ?? '',
                $row->rejection_reason,
                $row->approved_by,
                $row->approved_at,
                $row->paid_at,
                $row->created_at,
                $row->updated_at
            ];

            foreach ($submitted_keys as $key) {
                $values[] = $submitted[$key] ?? '';
            }

            fputcsv(
                $output,
                array_map([$this, 'csv_value'], $values)
            );
        }

        fclose($output);
        exit;
    }

    private function export_pdf($rows, $filters) {

        $expected_amount = 0;
        $paid_amount = 0;

        foreach ($rows as $row) {
            $row_amount = floatval($row->total_amount ?? 0);
            $expected_amount += $row_amount;

            if (($row->payment_status ?? 'unpaid') === 'paid') {
                $paid_amount += $row_amount;
            }
        }

        $pdf = new DISI_Registrations_PDF(
            DISI_PLUGIN_DIR . 'assets/images/disi-logo.png'
        );
        $pdf->SetTitle('DISI Summit Registrations');
        $pdf->SetAuthor('DISI Summit Portal');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->report_summary(
            current_time('mysql'),
            $this->filter_summary($filters),
            count($rows),
            $expected_amount,
            $paid_amount
        );
        $pdf->start_table();

        foreach ($rows as $index => $row) {
            $pdf->registration_row(
                $index + 1,
                $row,
                $this->submitted_data($row)
            );
        }

        $pdf->finish_table(empty($rows));

        $pdf->Output(
            'D',
            'disi-registrations-' . gmdate('Y-m-d-His') . '.pdf'
        );
        exit;
    }

    private function submitted_keys($rows) {

        $keys = [];

        foreach ($rows as $row) {
            foreach ($this->submitted_data($row) as $key => $value) {
                if (!in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    private function submitted_data($row) {

        $data = json_decode($row->submitted_data ?? '', true);

        if (!is_array($data)) {
            return [];
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = implode(', ', $value);
            }
        }

        return $data;
    }

    private function csv_value($value) {

        $value = (string) $value;

        if (preg_match('/^[=\-+@]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }

    private function filter_summary($filters) {

        $labels = [];

        if (!empty($filters['type'])) {
            $labels[] = 'Type: ' .
                DISI_Registration_Manager::label_registration_type(
                    $filters['type']
                );
        }

        if (!empty($filters['status'])) {
            $labels[] = 'Status: ' . ucfirst($filters['status']);
        }

        if (!empty($filters['payment_status'])) {
            $labels[] = 'Payment: ' .
                ucfirst($filters['payment_status']);
        }

        if (!empty($filters['search'])) {
            $labels[] = 'Search: ' . $filters['search'];
        }

        return empty($labels) ? 'All registrations' : implode('; ', $labels);
    }

    public static function field_label($key) {

        return DISI_Registration_Manager::label_submission_field(
            $key
        );
    }

    public static function pdf_text($text) {

        $text = wp_strip_all_tags((string) $text);
        $converted = iconv(
            'UTF-8',
            'windows-1252//TRANSLIT//IGNORE',
            $text
        );

        return $converted !== false ? $converted : $text;
    }
}

class DISI_Registrations_PDF extends FPDF {

    private $logo_path;
    private $table_started = false;
    private $row_index = 0;
    private $widths = [8, 25, 29, 39, 24, 16, 18, 24, 21, 73];
    private $headers = [
        '#',
        'Registration',
        'Name',
        'Contact',
        'Type',
        'Status',
        'Payment',
        'Amount',
        'Submitted',
        'Full Details'
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
        $this->Cell(
            0,
            7,
            'DISI Summit 2026 Registration Report',
            0,
            1
        );

        $this->SetX(47);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(222, 231, 235);
        $this->Cell(
            0,
            5,
            'Registration, approval and payment records',
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
            'DISI Summit Portal | Confidential | Page ' . $this->PageNo(),
            0,
            0,
            'C'
        );
    }

    public function report_summary(
        $exported_at,
        $filters,
        $record_count,
        $expected_amount,
        $paid_amount
    ) {

        $this->SetFillColor(243, 247, 246);
        $this->SetDrawColor(205, 219, 215);
        $this->SetTextColor(23, 43, 59);
        $this->SetFont('Helvetica', '', 8);
        $this->MultiCell(
            0,
            5,
            DISI_Exporter::pdf_text(
                'Exported: ' . $exported_at .
                '    |    Filters: ' . $filters
            ),
            1,
            'L',
            true
        );
        $this->Ln(2);

        $card_width = 67.75;
        $cards = [
            ['Records', number_format($record_count)],
            ['Expected Amount', 'NGN ' . number_format($expected_amount, 2)],
            ['Verified Paid', 'NGN ' . number_format($paid_amount, 2)],
            [
                'Outstanding',
                'NGN ' . number_format(
                    max(0, $expected_amount - $paid_amount),
                    2
                )
            ]
        ];

        foreach ($cards as $index => $card) {
            if ($index > 0) {
                $this->SetX($this->GetX() + 2);
            }

            $x = $this->GetX();
            $y = $this->GetY();
            $this->SetFillColor(255, 255, 255);
            $this->Rect($x, $y, $card_width, 15, 'DF');
            $this->SetXY($x + 3, $y + 2);
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(90, 105, 110);
            $this->Cell($card_width - 6, 4, $card[0], 0, 2);
            $this->SetFont('Helvetica', 'B', 10);
            $this->SetTextColor(21, 118, 100);
            $this->Cell(
                $card_width - 6,
                6,
                DISI_Exporter::pdf_text($card[1]),
                0,
                0
            );
            $this->SetXY($x + $card_width, $y);
        }

        $this->Ln(18);
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
            'No registrations matched the selected filters.',
            1,
            1,
            'C'
        );
    }

    public function registration_row($number, $row, $submitted) {

        $name = trim(
            ($row->first_name ?? '') . ' ' .
            ($row->last_name ?? '')
        );

        $contact = trim(
            ($row->email ?? '') . "\n" .
            ($row->phone ?? '')
        );

        $details = [
            'Business: ' . ($row->business_name ?: '-'),
            'Registration fee: NGN ' .
                number_format(floatval($row->registration_amount), 2),
            'Workshop: NGN ' .
                number_format(floatval($row->workshop_amount), 2),
            'Source: ' . $row->source_plugin .
                ' / Form ' . $row->form_id .
                ' / Entry ' . $row->source_entry_id
        ];

        if (!empty($row->paystack_reference)) {
            $details[] = 'Reference: ' . $row->paystack_reference;
        }

        if (!empty($row->paid_at)) {
            $details[] = 'Paid: ' . $row->paid_at;
        }

        if (!empty($row->ticket_token)) {
            $details[] = 'Ticket: ' .
                DISI_Ticketing::ticket_number($row);
            $details[] = 'Ticket scans: ' .
                intval($row->ticket_scan_count ?? 0);

            if (!empty($row->ticket_last_scanned_at)) {
                $details[] = 'Last ticket scan: ' .
                    $row->ticket_last_scanned_at;
            }
        }

        foreach ($submitted as $key => $value) {
            $details[] = DISI_Exporter::field_label($key) .
                ': ' . $value;
        }

        $this->table_row([
            $number,
            DISI_Registration_Manager::get_registration_number($row),
            $name ?: $row->email,
            $contact,
            DISI_Registration_Manager::label_registration_type(
                $row->registration_type
            ),
            ucfirst($row->status),
            ucfirst($row->payment_status ?? 'unpaid'),
            'NGN ' . number_format(floatval($row->total_amount), 2),
            $row->created_at ?? '',
            implode("\n", $details)
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

            if ($index === 5) {
                $this->SetTextColor(
                    strtolower($value) === 'approved' ? 22 : 146,
                    strtolower($value) === 'approved' ? 101 : 64,
                    strtolower($value) === 'approved' ? 52 : 14
                );
            } elseif ($index === 6) {
                $paid = strtolower($value) === 'paid';
                $this->SetTextColor(
                    $paid ? 22 : 154,
                    $paid ? 101 : 52,
                    $paid ? 52 : 18
                );
            } else {
                $this->SetTextColor(35, 45, 48);
            }

            $this->SetXY($x + 1, $y + 1);
            $this->MultiCell(
                $width - 2,
                3.4,
                DISI_Exporter::pdf_text($value),
                0,
                $index === 0 ? 'C' : 'L',
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
        $line_start = 0;
        $position = 0;
        $line_width = 0;
        $lines = 1;

        while ($position < $length) {
            $character = $text[$position];

            if ($character === "\n") {
                $position++;
                $separator = -1;
                $line_start = $position;
                $line_width = 0;
                $lines++;
                continue;
            }

            if ($character === ' ') {
                $separator = $position;
            }

            $line_width += $cw[$character] ?? 0;

            if ($line_width > $max_width) {
                if ($separator === -1) {
                    if ($position === $line_start) {
                        $position++;
                    }
                } else {
                    $position = $separator + 1;
                }

                $separator = -1;
                $line_start = $position;
                $line_width = 0;
                $lines++;
            } else {
                $position++;
            }
        }

        return $lines;
    }
}
