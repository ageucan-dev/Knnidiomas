<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$eventName = trim((string)($input['event_name'] ?? ''));
$eventId = trim((string)($input['event_id'] ?? ''));
$sourceUrl = trim((string)($input['event_source_url'] ?? ''));

$allowedEvents = ['Contact', 'ViewContent', 'PageView'];
if (!in_array($eventName, $allowedEvents, true) || $eventId === '' || $sourceUrl === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Evento inválido']);
    exit;
}

require_once __DIR__ . '/meta-capi.php';

$result = meta_send_event(
    $eventName,
    $eventId,
    $sourceUrl,
    [],
    [
        'fbp' => (string)($input['fbp'] ?? ''),
        'fbc' => (string)($input['fbc'] ?? ''),
    ],
    is_array($input['custom_data'] ?? null) ? $input['custom_data'] : []
);

// O clique no WhatsApp não deve ser bloqueado caso a Meta esteja indisponível.
http_response_code(200);
echo json_encode([
    'ok' => (bool)($result['ok'] ?? false),
    'meta_status' => $result['status'] ?? null,
    'skipped' => (bool)($result['skipped'] ?? false),
]);
