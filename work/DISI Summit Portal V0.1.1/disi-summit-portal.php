<?php
/**
 * Plugin Name: DISI Summit Portal
 * Plugin URI: https://layer6.ng
 * Description: DISI Summit 2026 registration approval, pricing, payment notification, and form integration portal.
 * Version: 0.2.1
 * Author: Mustapha Mustapha
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/

define(
    'DISI_PLUGIN_DIR',
    plugin_dir_path(__FILE__)
);

define(
    'DISI_PLUGIN_URL',
    plugin_dir_url(__FILE__)
);

/*
|--------------------------------------------------------------------------
| Includes
|--------------------------------------------------------------------------
*/

require_once DISI_PLUGIN_DIR . 'includes/class-database.php';
require_once DISI_PLUGIN_DIR . 'includes/class-activator.php';
require_once DISI_PLUGIN_DIR . 'includes/class-registration-manager.php';
require_once DISI_PLUGIN_DIR . 'includes/class-email.php';
require_once DISI_PLUGIN_DIR . 'includes/class-settings.php';
require_once DISI_PLUGIN_DIR . 'includes/class-form-provider.php';

require_once DISI_PLUGIN_DIR . 'admin/class-admin-menu.php';

require_once DISI_PLUGIN_DIR . 'integrations/class-form-listener.php';
require_once DISI_PLUGIN_DIR . 'includes/class-login-branding.php';

/*
|--------------------------------------------------------------------------
| Activation
|--------------------------------------------------------------------------
*/

register_activation_hook(
    __FILE__,
    ['DISI_Activator', 'activate']
);

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/

add_action(
    'plugins_loaded',
    function () {

        if (class_exists('DISI_Database')) {

            DISI_Database::maybe_upgrade();
        }

        if (class_exists('DISI_Admin_Menu')) {

            new DISI_Admin_Menu();
        }

        if (class_exists('DISI_Form_Listener')) {

            new DISI_Form_Listener();
        }

        if (class_exists('DISI_Login_Branding')) {

            new DISI_Login_Branding();
        }
    }
);
