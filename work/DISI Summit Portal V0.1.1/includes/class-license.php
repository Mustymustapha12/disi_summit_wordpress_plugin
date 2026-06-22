<?php

if (!defined('ABSPATH')) {
    exit;
}

class DISI_License {

    const OPTION_KEY = 'disi_portal_license_key';
    const INSTALLATION_KEY = 'disi_portal_installation_id';
    const REQUEST_PREFIX = 'DISI-REQ-';
    const LICENSE_PREFIX = 'DISI-LIC-';

    private const PUBLIC_KEY = <<<'KEY'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA9uDmMNiTFJohd+5Yb/O0
dcp3AzqhyfkpDL/UvIBARu9ykSZZ3cpEuOip1xP8obFOxN1dDM7iyu6LPx/aVT7W
7QkgHQwRJjEVhczEjzGiTiDMzzGuYk44a6HNWvJlUpeA+vTagjxBfylJK1YMd2OX
AI1inD88Dg0l5sv/1ZUuqIoKdWI1RDYiWrmXBMmp9yk7cl/IakgFCaRpVrLSSXh9
XZioMBZHqXRUw2m55SDmg2QmZIRaSdPb2daZm+ReymXqBoVoukmbKvXbIJ2NNLE+
vf7R8mlCfFOOY5g5aUcNWVOUxP+ZwygtIi2iG3jL8BlQ1Zxc5WX+tKqCTfLLH92K
gQIDAQAB
-----END PUBLIC KEY-----
KEY;

    public static function is_active() {

        $key = trim((string) get_option(self::OPTION_KEY, ''));

        return self::verify($key) === true;
    }

    public static function activate($key) {

        $key = preg_replace('/\s+/', '', (string) $key);
        $result = self::verify($key);

        if ($result !== true) {
            return new WP_Error('invalid_disi_license', $result);
        }

        update_option(self::OPTION_KEY, $key, false);

        return true;
    }

    public static function request_code() {

        $payload = wp_json_encode(
            [
                'site' => self::site_identity(),
                'installation' => self::installation_id()
            ],
            JSON_UNESCAPED_SLASHES
        );

        return self::REQUEST_PREFIX . self::base64url_encode($payload);
    }

    public static function site_identity() {

        $url = untrailingslashit(home_url('/'));
        $parts = wp_parse_url($url);

        if (!is_array($parts) || empty($parts['host'])) {
            return strtolower($url);
        }

        $identity = strtolower(
            ($parts['scheme'] ?? 'https') . '://' . $parts['host']
        );

        if (!empty($parts['port'])) {
            $identity .= ':' . intval($parts['port']);
        }

        if (!empty($parts['path'])) {
            $identity .= '/' . trim($parts['path'], '/');
        }

        return untrailingslashit($identity);
    }

    public static function status_message() {

        $key = trim((string) get_option(self::OPTION_KEY, ''));

        if (empty($key)) {
            return 'This WordPress installation has not been approved yet.';
        }

        $result = self::verify($key);

        return $result === true
            ? 'This WordPress installation is approved.'
            : $result;
    }

    private static function verify($key) {

        if (empty($key)) {
            return 'Enter an activation key approved for this installation.';
        }

        if (!function_exists('openssl_verify')) {
            return 'The server OpenSSL extension is required for license verification.';
        }

        if (strpos($key, self::LICENSE_PREFIX) !== 0) {
            return 'The activation key format is invalid.';
        }

        $value = substr($key, strlen(self::LICENSE_PREFIX));
        $parts = explode('.', $value, 2);

        if (count($parts) !== 2) {
            return 'The activation key is incomplete.';
        }

        $payload = self::base64url_decode($parts[0]);
        $signature = self::base64url_decode($parts[1]);
        $data = json_decode($payload, true);

        if (
            self::base64url_encode($payload) !== $parts[0] ||
            self::base64url_encode($signature) !== $parts[1]
        ) {
            return 'The activation key encoding is invalid.';
        }

        if (
            !is_array($data) ||
            empty($data['site']) ||
            empty($data['installation'])
        ) {
            return 'The activation key contains invalid site information.';
        }

        $verified = openssl_verify(
            $payload,
            $signature,
            self::PUBLIC_KEY,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1) {
            return 'The activation key signature is not valid.';
        }

        if (!hash_equals(self::site_identity(), (string) $data['site'])) {
            return 'This activation key was approved for a different site URL.';
        }

        if (
            !hash_equals(
                self::installation_id(),
                (string) $data['installation']
            )
        ) {
            return 'This activation key was approved for a different installation.';
        }

        return true;
    }

    private static function installation_id() {

        $id = (string) get_option(self::INSTALLATION_KEY, '');

        if (!empty($id)) {
            return $id;
        }

        $id = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : bin2hex(random_bytes(16));

        add_option(self::INSTALLATION_KEY, $id, '', false);

        return $id;
    }

    private static function base64url_encode($value) {

        return rtrim(
            strtr(base64_encode((string) $value), '+/', '-_'),
            '='
        );
    }

    private static function base64url_decode($value) {

        $value = strtr((string) $value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? '' : $decoded;
    }
}
