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

        $pdf = new DISI_Registrations_PDF();
        $pdf->SetTitle('DISI Summit Registrations');
        $pdf->SetAuthor('DISI Summit Portal');
        $pdf->SetMargins(12, 14, 12);
        $pdf->SetAutoPageBreak(true, 16);
        $pdf->AddPage();

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor(21, 118, 100);
        $pdf->Cell(
            0,
            9,
            self::pdf_text('DISI Summit 2026 Registrations'),
            0,
            1
        );

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(
            0,
            5,
            self::pdf_text(
                'Exported: ' . current_time('mysql') . "\n" .
                'Filters: ' . $this->filter_summary($filters) . "\n" .
                'Records: ' . count($rows)
            )
        );
        $pdf->Ln(3);

        foreach ($rows as $index => $row) {
            if ($index > 0) {
                $pdf->AddPage();
            }

            $pdf->registration($row, $this->submitted_data($row));
        }

        if (empty($rows)) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Cell(0, 10, 'No registrations matched the filters.', 0, 1);
        }

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

        return ucwords(
            str_replace(['_', '-'], ' ', (string) $key)
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

    public function Footer() {

        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(
            0,
            6,
            'DISI Summit Portal - Page ' . $this->PageNo(),
            0,
            0,
            'C'
        );
    }

    public function registration($row, $submitted) {

        $name = trim(
            ($row->first_name ?? '') . ' ' .
            ($row->last_name ?? '')
        );

        $this->SetFillColor(23, 43, 59);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 12);
        $this->Cell(
            0,
            9,
            DISI_Exporter::pdf_text(
                DISI_Registration_Manager::get_registration_number($row) .
                ' - ' . ($name ?: $row->email)
            ),
            0,
            1,
            'L',
            true
        );
        $this->Ln(2);

        $fields = [
            'Registration Type' =>
                DISI_Registration_Manager::label_registration_type(
                    $row->registration_type
                ),
            'Email' => $row->email,
            'Phone' => $row->phone,
            'Business Name' => $row->business_name,
            'Registration Status' => ucfirst($row->status),
            'Payment Status' => ucfirst($row->payment_status ?? 'unpaid'),
            'Registration Amount' =>
                'NGN ' . number_format(floatval($row->registration_amount), 2),
            'Workshop Amount' =>
                'NGN ' . number_format(floatval($row->workshop_amount), 2),
            'Total Amount' =>
                'NGN ' . number_format(floatval($row->total_amount), 2),
            'Source' =>
                $row->source_plugin . ' / Form ' . $row->form_id .
                ' / Entry ' . $row->source_entry_id,
            'Paystack Reference' => $row->paystack_reference,
            'Paystack Transaction ID' => $row->paystack_transaction_id,
            'Payment Mode' => $row->paystack_mode,
            'Payment Link' => $row->paystack_authorization_url,
            'Rejection Reason' => $row->rejection_reason,
            'Approved At' => $row->approved_at,
            'Paid At' => $row->paid_at,
            'Created At' => $row->created_at,
            'Updated At' => $row->updated_at
        ];

        foreach ($fields as $label => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $this->field($label, $value);
        }

        if (!empty($submitted)) {
            $this->Ln(2);
            $this->SetFont('Helvetica', 'B', 11);
            $this->SetTextColor(21, 118, 100);
            $this->Cell(0, 7, 'Submitted Information', 0, 1);

            foreach ($submitted as $key => $value) {
                $this->field(
                    DISI_Exporter::field_label($key),
                    $value
                );
            }
        }
    }

    private function field($label, $value) {

        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(90, 90, 90);
        $this->MultiCell(
            0,
            4,
            DISI_Exporter::pdf_text($label)
        );
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(20, 20, 20);
        $this->MultiCell(
            0,
            5,
            DISI_Exporter::pdf_text($value),
            0,
            'L'
        );
        $this->Ln(1);
    }
}
