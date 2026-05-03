<?php

function loadEnv(string $projectRoot): void
{
    $envPath = $projectRoot . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, "\xEF\xBB\xBF")) {
            $trimmed = trim(substr($trimmed, 3));
        }
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $eqPos = strpos($trimmed, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $eqPos));
        $value = trim(substr($trimmed, $eqPos + 1));

        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $value = trim($value);

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function requestResend(string $apiKey, string $method, string $endpoint, ?array $payload = null): array
{
    $url = 'https://api.resend.com' . $endpoint;

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL extension is required for this CLI tool.'];
    }

    $ch = curl_init($url);
    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['ok' => false, 'error' => 'cURL error: ' . $error];
    }

    $decoded = json_decode((string) $response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $message = is_array($decoded) ? json_encode($decoded) : (string) $response;
        return ['ok' => false, 'error' => 'Resend API error (' . $httpCode . '): ' . $message];
    }

    return ['ok' => true, 'data' => $decoded];
}

$projectRoot = dirname(__DIR__);
loadEnv($projectRoot);

$apiKey = trim((string) (getenv('RESEND_API_KEY') ?: ''));
if ($apiKey === '' || str_starts_with($apiKey, 're_your_')) {
    fwrite(STDERR, "RESEND_API_KEY is missing or still placeholder in .env\n");
    exit(1);
}
if (!preg_match('/^re_[A-Za-z0-9_-]{20,}$/', $apiKey)) {
    fwrite(STDERR, "RESEND_API_KEY format looks wrong after loading .env — check for spaces, quotes, or copy/paste issues.\n");
    exit(1);
}

$action = $argv[1] ?? '';

if ($action === 'create') {
    $name = $argv[2] ?? 'Production';
    $result = requestResend($apiKey, 'POST', '/api-keys', ['name' => $name]);
} elseif ($action === 'list') {
    $result = requestResend($apiKey, 'GET', '/api-keys');
} elseif ($action === 'delete') {
    $id = $argv[2] ?? '';
    if ($id === '') {
        fwrite(STDERR, "Usage: php helpers/resend_api_keys.php delete <api_key_id>\n");
        exit(1);
    }
    $result = requestResend($apiKey, 'DELETE', '/api-keys/' . rawurlencode($id));
} else {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php helpers/resend_api_keys.php create \"Production\"\n");
    fwrite(STDERR, "  php helpers/resend_api_keys.php list\n");
    fwrite(STDERR, "  php helpers/resend_api_keys.php delete <api_key_id>\n");
    exit(1);
}

if (!$result['ok']) {
    fwrite(STDERR, $result['error'] . "\n");
    exit(1);
}

echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);

