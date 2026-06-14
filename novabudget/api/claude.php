<?php
// api/claude.php — Claude AI proxy (keeps API key server-side)
require_once __DIR__ . '/../config/auth.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required']);
    exit;
}

$apiKey = getenv('ANTHROPIC_API_KEY');
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI service not configured. Set ANTHROPIC_API_KEY environment variable.']);
    exit;
}

$payload = [
    'model'      => 'claude-sonnet-4-20250514',
    'max_tokens' => min((int)($body['max_tokens'] ?? 800), 2000),
    'messages'   => [['role' => 'user', 'content' => $body['message']]],
];
if (!empty($body['system'])) {
    $payload['system'] = (string)$body['system'];
}

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(503);
    echo json_encode(['error' => 'Network error: ' . $curlErr]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || empty($data['content'][0]['text'])) {
    $msg = $data['error']['message'] ?? 'AI request failed';
    http_response_code($httpCode ?: 503);
    echo json_encode(['error' => $msg]);
    exit;
}

echo json_encode(['text' => $data['content'][0]['text']]);
