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

$payload = [
    'cda_id' => null,
    'email' => trim((string)($input['email'] ?? '')),
    'idade' => trim((string)($input['idade'] ?? '')),
    'nome' => trim((string)($input['nome'] ?? '')),
    'parceria_id' => 37061,
    'status_id' => 1,
    'telefone' => trim((string)($input['telefone'] ?? '')),
];

if ($payload['nome'] === '' || $payload['email'] === '' || $payload['idade'] === '' || $payload['telefone'] === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Campos obrigatórios ausentes']);
    exit;
}

$endpoint = 'https://drive.knnidiomas.com.br/api/v1/parceria-cupons/landingpage-cupom/';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/json;charset=UTF-8',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);

$responseBody = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Falha ao comunicar com o DRIVE',
        'detail' => $curlError,
    ]);
    exit;
}

$decodedDrive = json_decode($responseBody, true);
$driveSuccess = $httpCode >= 200 && $httpCode < 300 && ($decodedDrive === true || trim($responseBody) === 'true');
$metaDebug = 'not_attempted';

// A Meta só recebe o evento Lead quando o DRIVE confirma o cadastro.
if ($driveSuccess) {
    $eventId = trim((string)($input['event_id'] ?? ''));
    $sourceUrl = trim((string)($input['event_source_url'] ?? ''));

    if ($eventId !== '' && $sourceUrl !== '') {
        require_once __DIR__ . '/meta-capi.php';

        $metaResult = meta_send_event(
            'Lead',
            $eventId,
            $sourceUrl,
            [
                'email' => $payload['email'],
                'telefone' => $payload['telefone'],
                'nome' => $payload['nome'],
            ],
            [
                'fbp' => (string)($input['fbp'] ?? ''),
                'fbc' => (string)($input['fbc'] ?? ''),
            ],
            [
                'content_name' => 'Formulario KNN Barretos',
                'content_category' => 'Lead',
                'lead_type' => 'formulario',
            ]
        );

        if ($metaResult['ok'] ?? false) {
            $metaDebug = 'ok';
        } elseif ($metaResult['skipped'] ?? false) {
            $metaDebug = 'skipped_config';
        } else {
            $metaDebug = 'error_' . (string)($metaResult['status'] ?? 'unknown');
            error_log('[KNN Meta CAPI] Lead não enviado: ' . json_encode($metaResult, JSON_UNESCAPED_UNICODE));
        }
    } else {
        $metaDebug = 'missing_event_id';
    }
}

header('X-Meta-CAPI: ' . $metaDebug);

// Mantém exatamente a resposta do DRIVE para não quebrar o front-end.
http_response_code($httpCode ?: 502);
echo $responseBody;
