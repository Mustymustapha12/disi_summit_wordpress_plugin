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
                 ADD rejection_reason TEXT NULL"
        ];

        foreach ($required_columns as $column => $query) {

            if (!in_array($column, $columns)) {

                $wpdb->query($query);
            }
        }
    }

    /**
     * Get registrations table name
     */
    public static function get_table() {

        global $wpdb;

        return $wpdb->prefix . 'disi_registrations';
    }
}
