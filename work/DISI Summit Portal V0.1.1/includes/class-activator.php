<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Activator {

    public static function activate() {

        if (class_exists('DISI_Database')) {

            DISI_Database::maybe_upgrade();
        }

        flush_rewrite_rules();
    }
}
