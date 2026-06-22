<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this tool from the command line.\n");
    exit(1);
}

$request = preg_replace('/\s+/', '', (string) ($argv[1] ?? ''));
$prefix = 'DISI-REQ-';

if (strpos($request, $prefix) !== 0) {
    fwrite(
        STDERR,
        "Usage: php owner-tools/generate-license.php 'DISI-REQ-...'\n"
    );
    exit(1);
}

$encoded_payload = substr($request, strlen($prefix));
$payload = base64url_decode($encoded_payload);
$data = json_decode($payload, true);

if (
    !is_array($data) ||
    empty($data['site']) ||
    empty($data['installation'])
) {
    fwrite(STDERR, "The request code is invalid.\n");
    exit(1);
}

$private_key_path = __DIR__ . '/disi-license-private.pem';
$private_key = is_readable($private_key_path)
    ? file_get_contents($private_key_path)
    : false;

if (!$private_key) {
    fwrite(
        STDERR,
        "Private key not found at {$private_key_path}.\n"
    );
    exit(1);
}

$signature = '';
$signed = openssl_sign(
    $payload,
    $signature,
    $private_key,
    OPENSSL_ALGO_SHA256
);

if (!$signed) {
    fwrite(STDERR, "The activation key could not be signed.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Approved site: {$data['site']}\n\n" .
    'DISI-LIC-' . $encoded_payload . '.' .
    base64url_encode($signature) . "\n"
);

function base64url_encode($value) {

    return rtrim(
        strtr(base64_encode((string) $value), '+/', '-_'),
        '='
    );
}

function base64url_decode($value) {

    $value = strtr((string) $value, '-_', '+/');
    $padding = strlen($value) % 4;

    if ($padding) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($value, true);

    return $decoded === false ? '' : $decoded;
}
