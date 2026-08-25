<?php

declare(strict_types=1);

function meta_config(): array
{
    $configFile = __DIR__ . '/meta-config.php';

    if (!file_exists($configFile)) {
        return [];
    }

    $config = require $configFile;
    return is_array($config) ? $config : [];
}

function meta_lower(string $value): string
{
    $value = trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function meta_hash(string $value): string
{
    return hash('sha256', $value);
}

function meta_normalize_email(string $email): string
{
    return meta_lower(trim($email));
}

function meta_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return '';
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }

    return $digits;
}

function meta_normalize_name(string $name): string
{
    $value = meta_lower(trim($name));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    return $value;
}

function meta_client_ip(): string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') continue;
        $ip = trim(explode(',', $candidate)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }

    return '';
}

function meta_split_name(string $name): array
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) return ['', ''];

    $first = array_shift($parts) ?: '';
    $last = implode(' ', $parts);

    return [$first, $last];
}

function meta_send_event(
    string $eventName,
    string $eventId,
    string $sourceUrl,
    array $rawUserData = [],
    array $tracking = [],
    array $customData = []
): array {
    $config = meta_config();

    $pixelId = trim((string)($config['pixel_id'] ?? '1385672982045076'));
    $token = trim((string)($config['access_token'] ?? ''));
    $apiVersion = trim((string)($config['api_version'] ?? 'v26.0'));
    $testEventCode = trim((string)($config['test_event_code'] ?? ''));

    if ($pixelId === '' || $token === '') {
        return ['ok' => false, 'skipped' => true, 'reason' => 'meta_config_missing'];
    }

    $userData = [];

    $email = meta_normalize_email((string)($rawUserData['email'] ?? ''));
    if ($email !== '') $userData['em'] = [meta_hash($email)];

    $phone = meta_normalize_phone((string)($rawUserData['telefone'] ?? $rawUserData['phone'] ?? ''));
    if ($phone !== '') $userData['ph'] = [meta_hash($phone)];

    [$firstName, $lastName] = meta_split_name((string)($rawUserData['nome'] ?? $rawUserData['name'] ?? ''));

    $firstName = meta_normalize_name($firstName);
    if ($firstName !== '') $userData['fn'] = [meta_hash($firstName)];

    $lastName = meta_normalize_name($lastName);
    if ($lastName !== '') $userData['ln'] = [meta_hash($lastName)];

    $fbp = trim((string)($tracking['fbp'] ?? ''));
    $fbc = trim((string)($tracking['fbc'] ?? ''));
    if ($fbp !== '') $userData['fbp'] = $fbp;
    if ($fbc !== '') $userData['fbc'] = $fbc;

    $ip = meta_client_ip();
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ip !== '') $userData['client_ip_address'] = $ip;
    if ($userAgent !== '') $userData['client_user_agent'] = $userAgent;

    $event = [
        'event_name' => $eventName,
        'event_time' => time(),
        'event_id' => $eventId,
        'event_source_url' => $sourceUrl,
        'action_source' => 'website',
        'user_data' => $userData,
    ];

    if ($customData) {
        $event['custom_data'] = $customData;
    }

    $payload = ['data' => [$event]];
    if ($testEventCode !== '') {
        $payload['test_event_code'] = $testEventCode;
    }

    $endpoint = sprintf(
        'https://graph.facebook.com/%s/%s/events',
        rawurlencode($apiVersion),
        rawurlencode($pixelId)
    );

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false) {
        return [
            'ok' => false,
            'status' => $httpCode,
            'error' => $curlError ?: 'curl_failed',
        ];
    }

    $decoded = json_decode($responseBody, true);
    $ok = $httpCode >= 200 && $httpCode < 300;

    return [
        'ok' => $ok,
        'status' => $httpCode,
        'response' => is_array($decoded) ? $decoded : $responseBody,
    ];
}
