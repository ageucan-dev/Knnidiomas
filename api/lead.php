<?php
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
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
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

http_response_code($httpCode ?: 502);
echo $responseBody;
