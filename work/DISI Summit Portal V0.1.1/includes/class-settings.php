<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Settings {

    public static function get_configuration() {

        return get_option(
            'disi_form_configuration',
            [
                'provider' => '',
                'participant_form' => '',
                'paystack_link' => '',
                'professional_amount' => '',
                'academic_amount' => '',
                'student_amount' => '',
                'group_booking_amount' => '',
                'workshop_amount' => ''
            ]
        );
    }

    public static function save_configuration($data) {

        update_option(
            'disi_form_configuration',
            $data
        );
    }
}
