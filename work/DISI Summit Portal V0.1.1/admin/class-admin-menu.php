<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Admin_Menu {

    public function __construct() {

        add_action(
            'admin_menu',
            [$this, 'register_menu']
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue_assets']
        );
    }

    public function enqueue_assets() {

        wp_enqueue_style(
            'disi-admin-css',
            DISI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            time()
        );
    }

    public function register_menu() {

        $licensed = (
            class_exists('DISI_License') &&
            DISI_License::is_active()
        );

        add_menu_page(
            $licensed
                ? 'DISI Summit Portal V0.5.0'
                : 'DISI Portal Approval Required',
            'DISI Portal',
            'manage_options',
            'disi-dashboard',
            $licensed ? [$this, 'dashboard'] : [$this, 'license'],
            'dashicons-groups',
            25
        );

        if (!$licensed) {
            add_submenu_page(
                'disi-dashboard',
                'License',
                'License',
                'manage_options',
                'disi-dashboard',
                [$this, 'license']
            );

            return;
        }

        add_submenu_page(
            'disi-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'disi-dashboard',
            [$this, 'dashboard']
        );

        add_submenu_page(
            'disi-dashboard',
            'Registrations',
            'Registrations',
            'manage_options',
            'disi-registrations',
            [$this, 'registrations']
        );

        add_submenu_page(
            'disi-dashboard',
            'Integrations',
            'Integrations',
            'manage_options',
            'disi-integrations',
            [$this, 'integrations']
        );

        add_submenu_page(
            'disi-dashboard',
            'E-ticketing',
            'E-ticketing',
            'manage_options',
            'disi-eticketing',
            [$this, 'eticketing']
        );

        add_submenu_page(
            'disi-dashboard',
            'License',
            'License',
            'manage_options',
            'disi-license',
            [$this, 'license']
        );

        add_submenu_page(
            null,
            'Registration Details',
            'Registration Details',
            'manage_options',
            'disi-registration-view',
            [$this, 'registration_view']
        );
    }

    public function registration_view() {

        if (!$this->licensed()) {
            $this->license();
            return;
        }

        include DISI_PLUGIN_DIR .
        'admin/views/registration-details.php';
    }

    public function dashboard() {

        if (!$this->licensed()) {
            $this->license();
            return;
        }

        include DISI_PLUGIN_DIR .
        'admin/views/dashboard.php';
    }

    public function registrations() {

        if (!$this->licensed()) {
            $this->license();
            return;
        }

        include DISI_PLUGIN_DIR .
        'admin/views/registrations.php';
    }

    public function integrations() {

        if (!$this->licensed()) {
            $this->license();
            return;
        }

        include DISI_PLUGIN_DIR .
        'admin/views/integrations.php';
    }

    public function eticketing() {

        if (!$this->licensed()) {
            $this->license();
            return;
        }

        include DISI_PLUGIN_DIR .
        'admin/views/eticketing.php';
    }

    public function license() {

        include DISI_PLUGIN_DIR . 'admin/views/license.php';
    }

    private function licensed() {

        return (
            class_exists('DISI_License') &&
            DISI_License::is_active()
        );
    }
}
