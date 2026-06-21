<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Form_Provider {

    public static function get_available_providers() {

        $providers = [];

        if (self::fluentforms_installed()) {
            $providers['fluentforms'] = 'Fluent Forms';
        }

        if (self::forminator_installed()) {
            $providers['forminator'] = 'Forminator';
        }

        if (self::contact_form_7_installed()) {
            $providers['contactform7'] = 'Contact Form 7';
        }

        if (self::gravityforms_installed()) {
            $providers['gravityforms'] = 'Gravity Forms';
        }

        if (self::wpforms_installed()) {
            $providers['wpforms'] = 'WPForms';
        }

        return $providers;
    }

    public static function get_forms($provider = '') {

        global $wpdb;

        if ($provider === 'fluentforms') {

            $table =
                $wpdb->prefix .
                'fluentform_forms';

            if (!self::table_exists($table)) {
                return [];
            }

            return $wpdb->get_results(
                "SELECT id,title
                 FROM {$table}
                 ORDER BY title ASC"
            );
        }

        if ($provider === 'forminator') {

            if (!self::forminator_installed()) {
                return [];
            }

            try {

                $forms = Forminator_API::get_forms();

                return self::normalize_forminator_forms($forms);

            } catch (Exception $e) {

                return [];
            }
        }

        if ($provider === 'contactform7') {

            return get_posts([
                'post_type' => 'wpcf7_contact_form',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
        }

        if ($provider === 'gravityforms') {

            if (!class_exists('GFAPI')) {
                return [];
            }

            return GFAPI::get_forms();
        }

        if ($provider === 'wpforms') {

            return get_posts([
                'post_type' => 'wpforms',
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
        }

        return [];
    }

    public static function get_form_title($form) {

        if (is_object($form)) {
            return $form->title ?? $form->name ?? $form->post_title ?? '';
        }

        if (is_array($form)) {
            return $form['title'] ?? $form['name'] ?? '';
        }

        return '';
    }

    public static function get_form_id($form) {

        if (is_object($form)) {
            return $form->id ?? $form->ID ?? '';
        }

        if (is_array($form)) {
            return $form['id'] ?? $form['ID'] ?? '';
        }

        return '';
    }

    private static function table_exists($table) {

        global $wpdb;

        return $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table
            )
        ) === $table;
    }

    private static function fluentforms_installed() {

        global $wpdb;

        return defined('FLUENTFORM') ||
            self::table_exists($wpdb->prefix . 'fluentform_forms');
    }

    private static function forminator_installed() {

        return class_exists('Forminator_API');
    }

    private static function contact_form_7_installed() {

        return defined('WPCF7_VERSION') ||
            post_type_exists('wpcf7_contact_form');
    }

    private static function gravityforms_installed() {

        return class_exists('GFAPI');
    }

    private static function wpforms_installed() {

        return defined('WPFORMS_VERSION') ||
            post_type_exists('wpforms');
    }

    private static function normalize_forminator_forms($forms) {

        if (!is_array($forms)) {
            return [];
        }

        return array_map(
            function ($form) {
                if (is_object($form)) {
                    return $form;
                }

                return (object) $form;
            },
            $forms
        );
    }
}
