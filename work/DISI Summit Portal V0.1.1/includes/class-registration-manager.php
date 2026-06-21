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

    /**
     * Delete registration
     */
    public static function delete($id) {

        global $wpdb;

        $table = DISI_Database::get_table();

        return $wpdb->delete(
            $table,
            [
                'id' => intval($id)
            ]
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
    /**
     * Approve registration
     */
    public static function approve(
        $registration_id
        ) {

        global $wpdb;

        $registration =
        self::get(
            $registration_id
        );

        if (
            !$registration ||
            $registration->status !== 'pending'
        ) {
            return false;
        }

        $table =
        DISI_Database::get_table();

        $updated = $wpdb->update(

            $table,

            [

                'status' => 'approved',

                'approved_by' =>
                    get_current_user_id(),

                'approved_at' =>
                    current_time('mysql'),

                'updated_at' =>
                    current_time('mysql')

            ],

            [

                'id' =>
                    intval(
                        $registration_id
                    )

            ]

        );

        if ($updated !== false) {

            $registration =
            self::get(
                $registration_id
            );

            error_log(
                'DISI APPROVAL EMAIL FUNCTION CALLED'
            );

            if (
                class_exists(
                    'DISI_Email'
                )
            ) {

                DISI_Email::send_approval_email(
                    $registration
                );
            }
        }

        return $updated;
        

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
            'participant' => 'Participant'
        ];

        return $labels[$type] ??
            ucwords(str_replace('_', ' ', (string) $type));
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
