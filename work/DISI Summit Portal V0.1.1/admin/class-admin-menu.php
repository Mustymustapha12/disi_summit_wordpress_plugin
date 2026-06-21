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

        add_menu_page(
            'DISI Summit Portal V0.3.2',
            'DISI Portal',
            'manage_options',
            'disi-dashboard',
            [$this, 'dashboard'],
            'dashicons-groups',
            25
        );

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
            null,
            'Registration Details',
            'Registration Details',
            'manage_options',
            'disi-registration-view',
            [$this, 'registration_view']
        );
    }

    public function registration_view() {

        include DISI_PLUGIN_DIR .
        'admin/views/registration-details.php';
    }

    public function dashboard() {

        include DISI_PLUGIN_DIR .
        'admin/views/dashboard.php';
    }

    public function registrations() {

        include DISI_PLUGIN_DIR .
        'admin/views/registrations.php';
    }

    public function integrations() {

        include DISI_PLUGIN_DIR .
        'admin/views/integrations.php';
    }
}
