<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Settings {

    public static function get_configuration() {

        $defaults = [
            'provider' => '',
            'participant_form' => '',
            'paystack_secret_key' => '',
            'paystack_public_key' => '',
            'paystack_callback_url' => '',
            'paystack_mode' => 'test',
            'professional_amount' => '',
            'academic_amount' => '',
            'student_amount' => '',
            'group_booking_amount' => '',
            'workshop_amount' => ''
        ];

        return wp_parse_args(
            get_option(
            'disi_form_configuration',
                []
            ),
            $defaults
        );
    }

    public static function save_configuration($data) {

        update_option(
            'disi_form_configuration',
            $data
        );
    }
}
