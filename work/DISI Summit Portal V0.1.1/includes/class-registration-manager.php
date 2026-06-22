<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Registration_Manager {

    /**
     * Create registration
     */
    public static function create($data = []) {

        global $wpdb;

        $table = DISI_Database::get_table();

        if (empty($data['email'])) {
            return false;
        }

        $email = sanitize_email($data['email']);

        /*
        |--------------------------------------------------------------------------
        | Duplicate Email Check
        |--------------------------------------------------------------------------
        */

        if (
            !empty($data['source_plugin']) &&
            !empty($data['form_id']) &&
            !empty($data['source_entry_id']) &&
            self::source_entry_exists(
                $data['source_plugin'],
                $data['form_id'],
                $data['source_entry_id']
            )
        ) {

            return new WP_Error(
                'duplicate_source_entry',
                'This source form entry is already registered.'
            );
        }

        if (
            self::email_exists($email)
        ) {

            return new WP_Error(
                'duplicate_email',
                'This email is already registered.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Phone Check
        |--------------------------------------------------------------------------
        */

        if (
            !empty($data['phone']) &&
            self::phone_exists(
                $data['phone']
            )
        ) {

            return new WP_Error(
                'duplicate_phone',
                'This phone number is already registered.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Insert registration
        |--------------------------------------------------------------------------
        */

        $inserted = $wpdb->insert(
            $table,
            [

                'registration_type' =>
                    sanitize_text_field(
                        $data['registration_type'] ?? ''
                    ),

                'source_plugin' =>
                    sanitize_text_field(
                        $data['source_plugin'] ?? ''
                    ),

                'form_id' =>
                    intval(
                        $data['form_id'] ?? 0
                    ),

                'source_entry_id' =>
                    sanitize_text_field(
                        $data['source_entry_id'] ?? ''
                    ),

                'email' => $email,

                'phone' =>
                    sanitize_text_field(
                        $data['phone'] ?? ''
                    ),

                'first_name' =>
                    sanitize_text_field(
                        $data['first_name'] ?? ''
                    ),

                'last_name' =>
                    sanitize_text_field(
                        $data['last_name'] ?? ''
                    ),

                'business_name' =>
                    sanitize_text_field(
                        $data['business_name'] ?? ''
                    ),

                'registration_amount' =>
                    self::normalize_amount(
                        $data['registration_amount'] ?? 0
                    ),

                'workshop_amount' =>
                    self::normalize_amount(
                        $data['workshop_amount'] ?? 0
                    ),

                'total_amount' =>
                    self::normalize_amount(
                        $data['total_amount'] ?? 0
                    ),

                'status' => 'pending',

                'payment_status' => 'unpaid',

                'submitted_data' =>
                    wp_json_encode(
                        $data['submitted_data'] ?? []
                    ),

                'created_at' =>
                    current_time('mysql'),

                'updated_at' =>
                    current_time('mysql')

            ]
        );

        if ($inserted === false) {
            return new WP_Error(
                'registration_insert_failed',
                !empty($wpdb->last_error)
                    ? $wpdb->last_error
                    : 'The registration could not be saved.'
            );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get all registrations
     */
    public static function get_all() {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_results(

            "SELECT *
             FROM {$table}
             ORDER BY id DESC"

        );
    }

    /**
     * Get registration by ID
     */
    public static function get($id) {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_row(

            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE id = %d",
                $id
            )

        );
    }

    public static function delete_by_source_entry(
        $source_plugin,
        $form_id,
        $source_entry_id
    ) {

        global $wpdb;

        if (empty($source_plugin) || empty($source_entry_id)) {
            return false;
        }

        $table = DISI_Database::get_table();

        $where = [
            'source_plugin' => sanitize_text_field($source_plugin),
            'source_entry_id' => sanitize_text_field($source_entry_id)
        ];

        if (!empty($form_id)) {
            $where['form_id'] = intval($form_id);
        }

        return $wpdb->delete(
            $table,
            $where
        );
    }

    /**
     * Count registrations
     */
    public static function count($status = null) {

        global $wpdb;

        $table = DISI_Database::get_table();

        if ($status) {

            return $wpdb->get_var(

                $wpdb->prepare(
                    "SELECT COUNT(*)
                     FROM {$table}
                     WHERE status = %s",
                    $status
                )

            );
        }

        return $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$table}"
        );
    }
    /**
     * Get registrations with filters and pagination
     */
    public static function get_paginated(
        $page = 1,
        $per_page = 20,
        $type = '',
        $status = '',
        $payment_status = '',
        $search = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();

        $where = "WHERE 1=1";

        if (!empty($type)) {

            $where .= $wpdb->prepare(
                " AND registration_type = %s",
                $type
            );
        }

        if (!empty($status)) {

            $where .= $wpdb->prepare(
                " AND status = %s",
                $status
            );
        }

        if (!empty($payment_status)) {

            $where .= $wpdb->prepare(
                " AND payment_status = %s",
                $payment_status
            );
        }

        if (!empty($search)) {

            $search_term = '%' .
            $wpdb->esc_like($search) .
            '%';

            $where .= $wpdb->prepare(
                " AND (
                    email LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
                    OR business_name LIKE %s
                )",
                $search_term,
                $search_term,
                $search_term,
                $search_term
            );
        }

        $offset =
        ($page - 1) * $per_page;

        return $wpdb->get_results(

            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 {$where}
                 ORDER BY id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )

        );
    }
    public static function total_count(
        $type = '',
        $status = '',
        $payment_status = '',
        $search = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();

        $where = "WHERE 1=1";

        if (!empty($type)) {

            $where .= $wpdb->prepare(
                " AND registration_type=%s",
                $type
            );
        }

        if (!empty($status)) {

            $where .= $wpdb->prepare(
                " AND status=%s",
                $status
            );
        }

        if (!empty($payment_status)) {

            $where .= $wpdb->prepare(
                " AND payment_status=%s",
                $payment_status
            );
        }

        if (!empty($search)) {

            $term =
            '%' .
            $wpdb->esc_like($search) .
            '%';

            $where .= $wpdb->prepare(
                " AND (
                    email LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
                    OR business_name LIKE %s
                )",
                $term,
                $term,
                $term,
                $term
            );
        }

        return $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$table}
             {$where}"
        );
    }

    public static function get_filtered(
        $type = '',
        $status = '',
        $payment_status = '',
        $search = ''
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();
        $where = self::filtered_where(
            $type,
            $status,
            $payment_status,
            $search
        );

        return $wpdb->get_results(
            "SELECT *
             FROM {$table}
             {$where}
             ORDER BY id DESC"
        );
    }

    public static function amount_totals() {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_row(
            "SELECT
                COALESCE(SUM(
                    CASE
                        WHEN status != 'rejected'
                        THEN registration_amount
                        ELSE 0
                    END
                ), 0)
                    AS registration_amount,
                COALESCE(SUM(
                    CASE
                        WHEN status != 'rejected'
                        THEN workshop_amount
                        ELSE 0
                    END
                ), 0)
                    AS workshop_amount,
                COALESCE(SUM(
                    CASE
                        WHEN status != 'rejected'
                        THEN total_amount
                        ELSE 0
                    END
                ), 0)
                    AS total_amount,
                COALESCE(SUM(
                    CASE
                        WHEN status = 'rejected'
                        THEN total_amount
                        ELSE 0
                    END
                ), 0) AS rejected_amount,
                COALESCE(SUM(
                    CASE
                        WHEN payment_status = 'paid'
                        THEN total_amount
                        ELSE 0
                    END
                ), 0) AS paid_amount
             FROM {$table}"
        );
    }

    private static function filtered_where(
        $type,
        $status,
        $payment_status,
        $search
    ) {

        global $wpdb;

        $where = "WHERE 1=1";

        if (!empty($type)) {
            $where .= $wpdb->prepare(
                " AND registration_type = %s",
                $type
            );
        }

        if (!empty($status)) {
            $where .= $wpdb->prepare(
                " AND status = %s",
                $status
            );
        }

        if (!empty($payment_status)) {
            $where .= $wpdb->prepare(
                " AND payment_status = %s",
                $payment_status
            );
        }

        if (!empty($search)) {
            $term = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (
                    email LIKE %s
                    OR first_name LIKE %s
                    OR last_name LIKE %s
                    OR business_name LIKE %s
                )",
                $term,
                $term,
                $term,
                $term
            );
        }

        return $where;
    }
    /**
     * Approve registration
     */
    public static function approve(
        $registration_id,
        $group_custom_amount = 0
    ) {

        global $wpdb;

        $registration = self::get($registration_id);

        if (
            !$registration ||
            $registration->status !== 'pending'
        ) {
            return new WP_Error(
                'invalid_registration_status',
                'Only pending registrations can be approved.'
            );
        }

        if (
            $registration->registration_type === 'group_booking' &&
            self::normalize_amount($group_custom_amount) > 0
        ) {
            $registration_amount = self::normalize_amount(
                $group_custom_amount
            );
            $total_amount = $registration_amount +
                self::normalize_amount($registration->workshop_amount ?? 0);

            $amount_updated = $wpdb->update(
                DISI_Database::get_table(),
                [
                    'registration_amount' => $registration_amount,
                    'total_amount' => $total_amount,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => intval($registration_id)]
            );

            if ($amount_updated === false) {
                return new WP_Error(
                    'group_amount_update_failed',
                    'The custom group amount could not be saved.'
                );
            }

            $registration = self::get($registration_id);
        }

        $transaction = DISI_Paystack::initialize_transaction(
            $registration
        );

        if (is_wp_error($transaction)) {
            return $transaction;
        }

        $table = DISI_Database::get_table();

        $updated = $wpdb->update(
            $table,
            [
                'status' => 'approved',
                'payment_status' => 'unpaid',
                'paystack_authorization_url' =>
                    $transaction['authorization_url'],
                'paystack_reference' => $transaction['reference'],
                'paystack_mode' => $transaction['mode'],
                'approved_by' => get_current_user_id(),
                'approved_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => intval($registration_id)]
        );

        if ($updated === false) {
            return new WP_Error(
                'approval_update_failed',
                !empty($wpdb->last_error)
                    ? $wpdb->last_error
                    : 'The registration could not be approved.'
            );
        }

        $registration = self::get($registration_id);

        if (class_exists('DISI_Email')) {
            DISI_Email::send_approval_email($registration);
        }

        return true;
    }

    public static function verify_payment($reference) {

        global $wpdb;

        if (empty($reference)) {
            return new WP_Error(
                'missing_payment_reference',
                'The payment reference is missing.'
            );
        }

        $registration = self::get_by_paystack_reference($reference);

        if (!$registration) {
            return new WP_Error(
                'unknown_payment_reference',
                'No registration matches this payment reference.'
            );
        }

        if (($registration->payment_status ?? '') === 'paid') {
            return $registration;
        }

        $transaction = DISI_Paystack::verify_transaction($reference);

        if (is_wp_error($transaction)) {
            return $transaction;
        }

        $transaction_status = sanitize_text_field(
            $transaction['status'] ?? ''
        );

        if ($transaction_status !== 'success') {
            return new WP_Error(
                'payment_not_paid',
                'This transaction is not paid yet. Paystack status: ' .
                ($transaction_status ?: 'unknown') .
                '.'
            );
        }

        $expected_amount = (int) round(
            self::normalize_amount($registration->total_amount ?? 0) * 100
        );
        $metadata = $transaction['metadata'] ?? [];

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        $valid = (
            ($transaction['reference'] ?? '') === $reference &&
            intval($transaction['amount'] ?? 0) === $expected_amount &&
            ($transaction['currency'] ?? '') === 'NGN' &&
            sanitize_email(
                $transaction['customer']['email'] ?? ''
            ) === sanitize_email($registration->email) &&
            ($transaction['domain'] ?? '') ===
                ($registration->paystack_mode ?? 'test') &&
            intval($metadata['registration_id'] ?? 0) ===
                intval($registration->id)
        );

        if (!$valid) {
            return new WP_Error(
                'payment_verification_mismatch',
                'The Paystack transaction does not match this registration.'
            );
        }

        $paid_at = !empty($transaction['paid_at'])
            ? get_date_from_gmt(
                gmdate(
                    'Y-m-d H:i:s',
                    strtotime($transaction['paid_at'])
                )
            )
            : current_time('mysql');

        $updated = $wpdb->update(
            DISI_Database::get_table(),
            [
                'payment_status' => 'paid',
                'paystack_transaction_id' => sanitize_text_field(
                    (string) ($transaction['id'] ?? '')
                ),
                'paid_at' => $paid_at,
                'updated_at' => current_time('mysql')
            ],
            ['id' => intval($registration->id)]
        );

        if ($updated === false) {
            return new WP_Error(
                'payment_update_failed',
                'The verified payment could not be saved.'
            );
        }

        return self::get($registration->id);
    }

    public static function resend_approval_email($registration_id) {

        $registration = self::get($registration_id);

        if (
            !$registration ||
            $registration->status !== 'approved' ||
            empty($registration->paystack_authorization_url)
        ) {
            return new WP_Error(
                'payment_email_unavailable',
                'A payment email can only be sent to an approved registration with a payment link.'
            );
        }

        if (!class_exists('DISI_Email')) {
            return new WP_Error(
                'email_service_unavailable',
                'The email service is unavailable.'
            );
        }

        if (!DISI_Email::send_approval_email($registration)) {
            return new WP_Error(
                'payment_email_failed',
                'WordPress could not send the payment email.'
            );
        }

        return true;
    }

    public static function get_by_paystack_reference($reference) {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT *
                 FROM {$table}
                 WHERE paystack_reference = %s
                 LIMIT 1",
                sanitize_text_field($reference)
            )
        );
    }

    public static function payment_count($payment_status) {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE payment_status = %s",
                sanitize_text_field($payment_status)
            )
        );
    }


    /**
     * Reject registration
     */
    public static function reject(
        $registration_id,
        $reason = ''
    ) {

        $registration = self::get(
            $registration_id
        );

        if (
            !$registration ||
            $registration->status !== 'pending'
        ) {
            return false;
        }

        global $wpdb;

        $table = DISI_Database::get_table();

        $updated = $wpdb->update(

            $table,

            [

                'status' => 'rejected',

                'rejection_reason' =>
                    sanitize_textarea_field($reason),

                'updated_at' => current_time('mysql')

            ],

            [

                'id' => intval(
                    $registration_id
                )

            ]

        );

        if ($updated !== false) {

            $registration = self::get(
                $registration_id
            );

            if (
                class_exists(
                    'DISI_Email'
                )
            ) {

                DISI_Email::send_rejection_email(
                    $registration
                );
            }
        }

        return $updated;
    }
    /**
     * Check if email exists
     */
    public static function email_exists($email)
    {
        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE email = %s
                 LIMIT 1",
                sanitize_email($email)
            )
        );
    }

    /**
     * Check if phone exists
     */
    public static function phone_exists($phone)
    {
        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE phone = %s
                 LIMIT 1",
                sanitize_text_field($phone)
            )
        );
    }

    public static function source_entry_exists(
        $source_plugin,
        $form_id,
        $source_entry_id
    ) {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE source_plugin = %s
                 AND form_id = %d
                 AND source_entry_id = %s
                 LIMIT 1",
                sanitize_text_field($source_plugin),
                intval($form_id),
                sanitize_text_field($source_entry_id)
            )
        );
    }

    public static function normalize_amount($amount) {

        $amount = preg_replace(
            '/[^0-9.]/',
            '',
            (string) $amount
        );

        return round(
            floatval($amount),
            2
        );
    }
    /**
     * Generate Registration Number
     */
    public static function get_registration_number(
        $registration
    ) {

        return sprintf(

            'DISI-%s-%06d',

            date(
                'Y',
                strtotime(
                    $registration->created_at
                )
            ),

            intval(
                $registration->id
            )

        );
    }

    public static function label_registration_type($type) {

        $labels = [
            'professional' => 'Professional',
            'academic_researcher' => 'Academic/Researcher',
            'student' => 'Student',
            'group_booking' => 'Group Booking',
            'workshop_only' => 'Workshop Only',
            'participant' => 'Participant'
        ];

        return $labels[$type] ??
            ucwords(str_replace('_', ' ', (string) $type));
    }

    public static function label_submission_field($key) {

        $key = preg_replace(
            '/(?:-|_)\d+$/',
            '',
            (string) $key
        );

        return ucwords(
            trim(
                preg_replace(
                    '/[\s_-]+/',
                    ' ',
                    $key
                )
            )
        );
    }

    public static function is_hidden_submission_field($key) {

        $key = strtolower((string) $key);

        $hidden_fragments = [
            'fluentform',
            'fluent form',
            'fluent_form',
            'fluentformnonce',
            '_wp_http_referer',
            'wp_http_referer',
            'wp http referer',
            'embedded_post_id',
            'embedded post',
            'embded post',
            'embed_post_id',
            'form_id',
            '_wpnonce',
            'nonce'
        ];

        foreach ($hidden_fragments as $fragment) {
            if (strpos($key, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Count by registration type
     */
    public static function count_by_type(
        $type
    ) {

        global $wpdb;

        $table =
        DISI_Database::get_table();

        return $wpdb->get_var(

            $wpdb->prepare(

                "SELECT COUNT(*)
                 FROM {$table}
                 WHERE registration_type = %s",

                $type

            )

        );
    }




}
