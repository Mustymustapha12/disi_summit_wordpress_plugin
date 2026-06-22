<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Database {

    /**
     * Create plugin tables
     */
    public static function create_tables() {

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $table = $wpdb->prefix . 'disi_registrations';

        $sql = "CREATE TABLE {$table} (

            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

            registration_type VARCHAR(50) NULL,

            source_plugin VARCHAR(50) NULL,

            form_id BIGINT NULL,

            source_entry_id VARCHAR(100) NULL,

            email VARCHAR(255) NULL,

            phone VARCHAR(50) NULL,

            first_name VARCHAR(255) NULL,

            last_name VARCHAR(255) NULL,

            business_name VARCHAR(255) NULL,

            registration_amount DECIMAL(12,2) NULL,

            workshop_amount DECIMAL(12,2) NULL,

            total_amount DECIMAL(12,2) NULL,

            rejection_reason TEXT NULL,

            status VARCHAR(20) NOT NULL DEFAULT 'pending',

            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',

            paystack_authorization_url TEXT NULL,

            paystack_reference VARCHAR(100) NULL,

            paystack_transaction_id VARCHAR(100) NULL,

            paystack_mode VARCHAR(10) NULL,

            paid_at DATETIME NULL,

            ticket_token VARCHAR(64) NULL,

            ticket_issued_at DATETIME NULL,

            ticket_email_sent_at DATETIME NULL,

            ticket_scan_count BIGINT UNSIGNED NOT NULL DEFAULT 0,

            ticket_last_scanned_at DATETIME NULL,

            submitted_data LONGTEXT NULL,

            wp_user_id BIGINT NULL,

            approved_by BIGINT NULL,

            approved_at DATETIME NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            PRIMARY KEY  (id),

            KEY email_idx (email),

            KEY source_entry_idx (source_plugin, form_id, source_entry_id),

            KEY status_idx (status),

            KEY payment_status_idx (payment_status),

            KEY paystack_reference_idx (paystack_reference),

            UNIQUE KEY ticket_token_idx (ticket_token),

            KEY registration_type_idx (registration_type)

        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Upgrade existing installations
     */
    public static function maybe_upgrade() {

        global $wpdb;

        $table = $wpdb->prefix . 'disi_registrations';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        if (!$table_exists) {

            self::create_tables();

            return;
        }

        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        );

        if (!$table_exists) {
            self::create_tables();
            return;
        }

        $columns = $wpdb->get_col(
            "SHOW COLUMNS FROM {$table}",
            0
        );

        $required_columns = [

            'first_name' =>
                "ALTER TABLE {$table}
                 ADD first_name VARCHAR(255) NULL",

            'last_name' =>
                "ALTER TABLE {$table}
                 ADD last_name VARCHAR(255) NULL",

            'business_name' =>
                "ALTER TABLE {$table}
                 ADD business_name VARCHAR(255) NULL",

            'source_plugin' =>
                "ALTER TABLE {$table}
                 ADD source_plugin VARCHAR(50) NULL",

            'form_id' =>
                "ALTER TABLE {$table}
                 ADD form_id BIGINT NULL",

            'source_entry_id' =>
                "ALTER TABLE {$table}
                 ADD source_entry_id VARCHAR(100) NULL",

            'registration_amount' =>
                "ALTER TABLE {$table}
                 ADD registration_amount DECIMAL(12,2) NULL",

            'workshop_amount' =>
                "ALTER TABLE {$table}
                 ADD workshop_amount DECIMAL(12,2) NULL",

            'total_amount' =>
                "ALTER TABLE {$table}
                 ADD total_amount DECIMAL(12,2) NULL",

            'rejection_reason' =>
                "ALTER TABLE {$table}
                 ADD rejection_reason TEXT NULL",

            'payment_status' =>
                "ALTER TABLE {$table}
                 ADD payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid'",

            'paystack_authorization_url' =>
                "ALTER TABLE {$table}
                 ADD paystack_authorization_url TEXT NULL",

            'paystack_reference' =>
                "ALTER TABLE {$table}
                 ADD paystack_reference VARCHAR(100) NULL",

            'paystack_transaction_id' =>
                "ALTER TABLE {$table}
                 ADD paystack_transaction_id VARCHAR(100) NULL",

            'paystack_mode' =>
                "ALTER TABLE {$table}
                 ADD paystack_mode VARCHAR(10) NULL",

            'paid_at' =>
                "ALTER TABLE {$table}
                 ADD paid_at DATETIME NULL",

            'ticket_token' =>
                "ALTER TABLE {$table}
                 ADD ticket_token VARCHAR(64) NULL",

            'ticket_issued_at' =>
                "ALTER TABLE {$table}
                 ADD ticket_issued_at DATETIME NULL",

            'ticket_email_sent_at' =>
                "ALTER TABLE {$table}
                 ADD ticket_email_sent_at DATETIME NULL",

            'ticket_scan_count' =>
                "ALTER TABLE {$table}
                 ADD ticket_scan_count BIGINT UNSIGNED NOT NULL DEFAULT 0",

            'ticket_last_scanned_at' =>
                "ALTER TABLE {$table}
                 ADD ticket_last_scanned_at DATETIME NULL"
        ];

        foreach ($required_columns as $column => $query) {

            if (!in_array($column, $columns)) {

                $wpdb->query($query);
            }
        }

        $ticket_index = $wpdb->get_var(
            "SHOW INDEX FROM {$table}
             WHERE Key_name = 'ticket_token_idx'"
        );

        if (!$ticket_index) {
            $wpdb->query(
                "ALTER TABLE {$table}
                 ADD UNIQUE KEY ticket_token_idx (ticket_token)"
            );
        }

        $wpdb->query(
            "UPDATE {$table}
             SET registration_amount = 0,
                 total_amount = 0
             WHERE registration_type = 'group_booking'
             AND status = 'pending'"
        );
    }

    /**
     * Get registrations table name
     */
    public static function get_table() {

        global $wpdb;

        return $wpdb->prefix . 'disi_registrations';
    }
}
