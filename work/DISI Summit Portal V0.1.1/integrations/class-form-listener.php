<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Form_Listener {

    public function __construct() {

        add_action(
            'fluentform/submission_inserted',
            [$this, 'capture_fluentforms'],
            10,
            3
        );

        add_action(
            'fluentform/submission_deleted',
            [$this, 'delete_fluentforms'],
            10,
            2
        );

        add_action(
            'forminator_custom_form_submit_before_set_fields',
            [$this, 'capture_forminator'],
            10,
            3
        );

        add_action(
            'forminator_form_entry_delete',
            [$this, 'delete_forminator'],
            10,
            2
        );

        add_action(
            'wpcf7_mail_sent',
            [$this, 'capture_contact_form_7']
        );

        add_action(
            'gform_after_submission',
            [$this, 'capture_gravityforms'],
            10,
            2
        );

        add_action(
            'gform_delete_entry',
            [$this, 'delete_gravityforms']
        );

        add_action(
            'wpforms_process_complete',
            [$this, 'capture_wpforms'],
            10,
            4
        );

        add_action(
            'wpforms_entry_delete',
            [$this, 'delete_wpforms'],
            10,
            2
        );
    }

    public function capture_fluentforms(
        $entry_id,
        $form_data,
        $form
    ) {

        $form_id = $this->extract_id($form, ['id', 'form_id']);
        $entry_id = $this->extract_id(
            $entry_id,
            ['id', 'entry_id', 'submission_id']
        );

        $this->capture(
            'fluentforms',
            'Fluent Forms',
            $form_id,
            $entry_id,
            $form_data
        );
    }

    public function delete_fluentforms(
        $entry_id,
        $form_id = 0
    ) {

        $entry_id = $this->extract_id(
            $entry_id,
            ['id', 'entry_id', 'submission_id']
        );
        $form_id = $this->extract_id($form_id, ['id', 'form_id']);

        DISI_Registration_Manager::delete_by_source_entry(
            'Fluent Forms',
            $form_id,
            $entry_id
        );
    }

    public function capture_forminator(
        $entry = null,
        $form_id = 0,
        $field_data = []
    ) {

        $data = [];

        if (is_array($field_data)) {

            foreach ($field_data as $field) {

                if (!is_array($field)) {
                    continue;
                }

                $key =
                $field['name']
                ?? $field['field_name']
                ?? $field['slug']
                ?? '';

                if (empty($key)) {
                    continue;
                }

                $data[$key] =
                $field['value']
                ?? '';
            }
        }

        $entry_id =
        is_object($entry)
        ? ($entry->entry_id ?? $entry->id ?? '')
        : '';

        $this->capture(
            'forminator',
            'Forminator',
            $form_id,
            $entry_id,
            $data
        );
    }

    public function delete_forminator(
        $entry_id,
        $form_id = 0
    ) {

        DISI_Registration_Manager::delete_by_source_entry(
            'Forminator',
            $form_id,
            $entry_id
        );
    }

    public function capture_contact_form_7($contact_form) {

        if (!class_exists('WPCF7_Submission')) {
            return;
        }

        $submission =
        WPCF7_Submission::get_instance();

        if (!$submission) {
            return;
        }

        $this->capture(
            'contactform7',
            'Contact Form 7',
            $contact_form->id(),
            md5(wp_json_encode($submission->get_posted_data())),
            $submission->get_posted_data()
        );
    }

    public function capture_gravityforms(
        $entry,
        $form
    ) {

        $data = [];

        foreach ($entry as $key => $value) {
            $data[$key] = $value;
        }

        $this->capture(
            'gravityforms',
            'Gravity Forms',
            $form['id'] ?? 0,
            $entry['id'] ?? '',
            $data
        );
    }

    public function delete_gravityforms($entry_id) {

        global $wpdb;

        $form_id = '';

        if (class_exists('GFAPI')) {

            $entry = GFAPI::get_entry($entry_id);

            if (!is_wp_error($entry)) {
                $form_id = $entry['form_id'] ?? '';
            }
        }

        if (empty($form_id)) {
            return;
        }

        DISI_Registration_Manager::delete_by_source_entry(
            'Gravity Forms',
            $form_id,
            $entry_id
        );
    }

    public function capture_wpforms(
        $fields,
        $entry,
        $form_data,
        $entry_id
    ) {

        $data = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key =
            $field['name']
            ?? $field['id']
            ?? '';

            if (empty($key)) {
                continue;
            }

            $data[$key] =
            $field['value']
            ?? '';
        }

        $this->capture(
            'wpforms',
            'WPForms',
            $form_data['id'] ?? 0,
            $entry_id,
            $data
        );
    }

    public function delete_wpforms(
        $entry_id,
        $entry = null
    ) {

        $form_id =
        is_object($entry)
        ? ($entry->form_id ?? '')
        : '';

        DISI_Registration_Manager::delete_by_source_entry(
            'WPForms',
            $form_id,
            $entry_id
        );
    }

    private function capture(
        $provider,
        $source_plugin,
        $form_id,
        $entry_id,
        $form_data
    ) {

        $config =
        DISI_Settings::get_configuration();

        if (
            ($config['provider'] ?? '') !== $provider ||
            intval($config['participant_form'] ?? 0) !== intval($form_id)
        ) {
            return;
        }

        $form_data =
        $this->sanitize_form_data($form_data);

        $email =
        $this->find_email($form_data);

        if (empty($email)) {
            return;
        }

        $registration_type =
        $this->find_registration_type($form_data);

        $group_count =
        $this->find_group_count($form_data);

        $workshop_selected =
        $this->has_workshop($form_data);

        $registration_amount =
        $this->registration_amount(
            $registration_type,
            $group_count,
            $config
        );

        $workshop_amount =
        $workshop_selected
        ? DISI_Registration_Manager::normalize_amount(
            $config['workshop_amount'] ?? 0
        )
        : 0;

        $total_amount =
        $registration_amount +
        $workshop_amount;

        $full_name =
        $this->find_name($form_data);

        $result =
        DISI_Registration_Manager::create([

            'registration_type' => $registration_type,
            'source_plugin' => $source_plugin,
            'form_id' => $form_id,
            'source_entry_id' => $entry_id,
            'email' => $email,
            'phone' => $this->find_phone($form_data),
            'first_name' => $full_name['first_name'],
            'last_name' => $full_name['last_name'],
            'business_name' => '',
            'registration_amount' => $registration_amount,
            'workshop_amount' => $workshop_amount,
            'total_amount' => $total_amount,
            'submitted_data' => $form_data

        ]);

        if (is_wp_error($result)) {

            error_log(
                'DISI REGISTRATION ERROR: ' .
                $result->get_error_message()
            );
        }
    }

    private function sanitize_form_data($form_data) {

        if (!is_array($form_data)) {
            return [];
        }

        $clean = [];

        foreach ($form_data as $key => $value) {

            if (is_array($value)) {
                $value = implode(', ', array_map('sanitize_text_field', $value));
            }

            $clean[sanitize_text_field($key)] =
            sanitize_text_field((string) $value);
        }

        return $clean;
    }

    private function find_email($data) {

        foreach ($data as $value) {
            if (is_email($value)) {
                return sanitize_email($value);
            }
        }

        return '';
    }

    private function find_phone($data) {

        foreach ($data as $key => $value) {
            if (stripos($key, 'phone') !== false) {
                return $value;
            }
        }

        return '';
    }

    private function find_name($data) {

        $first = '';
        $last = '';
        $full = '';

        foreach ($data as $key => $value) {
            $key = strtolower($key);

            if (strpos($key, 'first') !== false) {
                $first = $value;
            }

            if (strpos($key, 'last') !== false) {
                $last = $value;
            }

            if (
                empty($full) &&
                strpos($key, 'name') !== false
            ) {
                $full = $value;
            }
        }

        if (
            empty($first) &&
            empty($last) &&
            !empty($full)
        ) {

            $parts = preg_split('/\s+/', trim($full));
            $first = $parts[0] ?? '';
            $last = trim(str_replace($first, '', $full));
        }

        return [
            'first_name' => $first,
            'last_name' => $last
        ];
    }

    private function find_registration_type($data) {

        $haystack =
        strtolower(
            implode(' ', array_merge(array_keys($data), array_values($data)))
        );

        if (strpos($haystack, 'academic') !== false ||
            strpos($haystack, 'researcher') !== false) {
            return 'academic_researcher';
        }

        if (strpos($haystack, 'student') !== false) {
            return 'student';
        }

        if (strpos($haystack, 'group') !== false) {
            return 'group_booking';
        }

        return 'professional';
    }

    private function find_group_count($data) {

        foreach ($data as $key => $value) {
            $key = strtolower($key);

            if (
                strpos($key, 'group') !== false ||
                strpos($key, 'number') !== false ||
                strpos($key, 'participants') !== false
            ) {

                $count = intval($value);

                if ($count > 0) {
                    return $count;
                }
            }
        }

        return 1;
    }

    private function has_workshop($data) {

        foreach ($data as $key => $value) {
            $text = strtolower($key . ' ' . $value);

            if (
                strpos($text, 'workshop') !== false &&
                strpos($text, 'no') === false
            ) {
                return true;
            }
        }

        return false;
    }

    private function registration_amount(
        $registration_type,
        $group_count,
        $config
    ) {

        $map = [
            'professional' => 'professional_amount',
            'academic_researcher' => 'academic_amount',
            'student' => 'student_amount',
            'group_booking' => 'group_booking_amount'
        ];

        $amount =
        DISI_Registration_Manager::normalize_amount(
            $config[$map[$registration_type] ?? 'professional_amount'] ?? 0
        );

        if ($registration_type === 'group_booking') {
            return $amount * max(1, intval($group_count));
        }

        return $amount;
    }

    private function extract_id($value, $keys = []) {

        if (is_scalar($value)) {
            return $value;
        }

        foreach ($keys as $key) {
            if (is_object($value) && isset($value->{$key})) {
                return $value->{$key};
            }

            if (is_array($value) && isset($value[$key])) {
                return $value[$key];
            }
        }

        return 0;
    }
}
