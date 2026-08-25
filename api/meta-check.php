<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$configFile = __DIR__ . '/meta-config.php';

$result = [
    'config_path' => $configFile,
    'config_exists' => file_exists($configFile),
    'config_readable' => is_readable($configFile),
    'pixel_id' => null,
    'token_present' => false,
    'token_length' => 0,
    'api_version' => null,
    'test_event_code_present' => false,
];

if ($result['config_exists'] && $result['config_readable']) {
    $config = require $configFile;

    if (is_array($config)) {
        $pixelId = trim((string)($config['pixel_id'] ?? ''));
        $token = trim((string)($config['access_token'] ?? ''));
        $apiVersion = trim((string)($config['api_version'] ?? ''));
        $testEventCode = trim((string)($config['test_event_code'] ?? ''));

        $result['pixel_id'] = $pixelId !== '' ? $pixelId : null;
        $result['token_present'] = $token !== '';
        $result['token_length'] = strlen($token);
        $result['api_version'] = $apiVersion !== '' ? $apiVersion : null;
        $result['test_event_code_present'] = $testEventCode !== '';
    } else {
        $result['config_invalid'] = true;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
