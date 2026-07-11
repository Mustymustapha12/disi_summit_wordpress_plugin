<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_Paystack {

    const API_BASE = 'https://api.paystack.co';

    public function __construct() {

        add_action(
            'admin_post_disi_paystack_callback',
            [$this, 'handle_callback']
        );

        add_action(
            'admin_post_nopriv_disi_paystack_callback',
            [$this, 'handle_callback']
        );

        add_action(
            'admin_post_disi_paystack_webhook',
            [$this, 'handle_webhook']
        );

        add_action(
            'admin_post_nopriv_disi_paystack_webhook',
            [$this, 'handle_webhook']
        );
    }

    public static function initialize_transaction($registration) {

        $config = DISI_Settings::get_configuration();
        $secret_key = trim($config['paystack_secret_key'] ?? '');
        $public_key = trim($config['paystack_public_key'] ?? '');
        $mode = self::mode($config);

        if (empty($secret_key) || empty($public_key)) {
            return new WP_Error(
                'paystack_missing_key',
                'Add both Paystack API keys in DISI Portal Integrations.'
            );
        }

        if (
            strpos($secret_key, 'sk_' . $mode . '_') !== 0 ||
            strpos($public_key, 'pk_' . $mode . '_') !== 0
        ) {
            return new WP_Error(
                'paystack_key_mode_mismatch',
                'The Paystack API keys do not match the selected mode.'
            );
        }

        $amount = DISI_Registration_Manager::normalize_amount(
            $registration->total_amount ?? 0
        );

        if ($amount <= 0) {
            return new WP_Error(
                'paystack_invalid_amount',
                'The payment amount must be greater than zero.'
            );
        }

        $reference = sprintf(
            'DISI-%d-%d-%d',
            intval($registration->id),
            time(),
            wp_rand(100000, 999999)
        );

        $metadata = [
            'name' => trim(
                ($registration->first_name ?? '') . ' ' .
                ($registration->last_name ?? '')
            ),
            'phone_number' => $registration->phone ?? '',
            'registration_type' => $registration->registration_type ?? '',
            'workshop_option' =>
                floatval($registration->workshop_amount ?? 0) > 0
                    ? 'Yes'
                    : 'No',
            'exhibition_option' =>
                floatval($registration->exhibition_amount ?? 0) > 0
                    ? 'Yes'
                    : 'No',
            'registration_id' => intval($registration->id)
        ];

        $response = wp_remote_post(
            self::API_BASE . '/transaction/initialize',
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret_key,
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode([
                    'email' => sanitize_email($registration->email),
                    'amount' => (string) round($amount * 100),
                    'currency' => 'NGN',
                    'callback_url' => self::callback_url(),
                    'reference' => $reference,
                    'metadata' => wp_json_encode($metadata)
                ])
            ]
        );

        $body = self::response_body($response);

        if (is_wp_error($body)) {
            return $body;
        }

        if (
            empty($body['status']) ||
            empty($body['data']['authorization_url']) ||
            empty($body['data']['reference'])
        ) {
            return new WP_Error(
                'paystack_initialize_failed',
                sanitize_text_field(
                    $body['message'] ?? 'Paystack could not initialize the transaction.'
                )
            );
        }

        return [
            'authorization_url' => esc_url_raw(
                $body['data']['authorization_url']
            ),
            'reference' => sanitize_text_field(
                $body['data']['reference']
            ),
            'mode' => $mode
        ];
    }

    public static function verify_transaction($reference) {

        $config = DISI_Settings::get_configuration();
        $secret_key = trim($config['paystack_secret_key'] ?? '');

        if (empty($secret_key)) {
            return new WP_Error(
                'paystack_missing_key',
                'Paystack verification is not configured.'
            );
        }

        $response = wp_remote_get(
            self::API_BASE . '/transaction/verify/' .
            rawurlencode($reference),
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret_key
                ]
            ]
        );

        $body = self::response_body($response);

        if (is_wp_error($body)) {
            return $body;
        }

        if (empty($body['status']) || empty($body['data'])) {
            return new WP_Error(
                'paystack_verify_failed',
                sanitize_text_field(
                    $body['message'] ?? 'Paystack could not verify the transaction.'
                )
            );
        }

        return $body['data'];
    }

    public function handle_callback() {

        $reference = sanitize_text_field(
            wp_unslash($_GET['reference'] ?? $_GET['trxref'] ?? '')
        );

        $result = DISI_Registration_Manager::verify_payment($reference);
        $config = DISI_Settings::get_configuration();
        $thank_you_url = esc_url_raw(
            $config['paystack_callback_url'] ?? ''
        );

        if (empty($thank_you_url)) {
            $thank_you_url = home_url('/');
        }

        $thank_you_url = add_query_arg(
            'disi_payment',
            is_wp_error($result) ? 'failed' : 'success',
            $thank_you_url
        );

        wp_redirect($thank_you_url);
        exit;
    }

    public function handle_webhook() {

        $payload = file_get_contents('php://input');
        $signature = sanitize_text_field(
            wp_unslash($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '')
        );
        $config = DISI_Settings::get_configuration();
        $secret_key = trim($config['paystack_secret_key'] ?? '');

        if (
            empty($payload) ||
            empty($signature) ||
            empty($secret_key)
        ) {
            status_header(400);
            exit;
        }

        $expected_signature = hash_hmac(
            'sha512',
            $payload,
            $secret_key
        );

        if (!hash_equals($expected_signature, $signature)) {
            status_header(401);
            exit;
        }

        $event = json_decode($payload, true);

        if (!is_array($event)) {
            status_header(400);
            exit;
        }

        if (($event['event'] ?? '') === 'charge.success') {
            $reference = sanitize_text_field(
                $event['data']['reference'] ?? ''
            );

            if (!empty($reference)) {
                DISI_Registration_Manager::verify_payment($reference);
            }
        }

        status_header(200);
        exit;
    }

    public static function callback_url() {

        return add_query_arg(
            'action',
            'disi_paystack_callback',
            admin_url('admin-post.php')
        );
    }

    public static function webhook_url() {

        return add_query_arg(
            'action',
            'disi_paystack_webhook',
            admin_url('admin-post.php')
        );
    }

    private static function mode($config) {

        return ($config['paystack_mode'] ?? 'test') === 'live'
            ? 'live'
            : 'test';
    }

    private static function response_body($response) {

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(
            wp_remote_retrieve_body($response),
            true
        );

        if (!is_array($body)) {
            return new WP_Error(
                'paystack_invalid_response',
                'Paystack returned an invalid response.'
            );
        }

        if ($status_code < 200 || $status_code >= 300) {
            return new WP_Error(
                'paystack_http_error',
                sanitize_text_field(
                    $body['message'] ?? 'Paystack request failed.'
                )
            );
        }

        return $body;
    }
}
