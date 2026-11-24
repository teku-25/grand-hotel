<?php
declare(strict_types=1);

// Credentials stay on the PHP server. Never send the secret key to a browser.
$envPath = dirname(__DIR__) . '/backend/.env';
if (!is_file($envPath) || !is_readable($envPath)) {
    throw new RuntimeException('The backend/.env configuration file was not found.');
}

$config = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2);
    $config[trim($name)] = trim(trim($value), "\"'");
}

$supabaseUrl = rtrim($config['SUPABASE_URL'] ?? '', '/');
$supabaseKey = $config['SUPABASE_PUBLISHABLE_KEY'] ?? '';
$supabaseSecretKey = $config['SUPABASE_SECRET_KEY'] ?? '';
$adminEmail = strtolower(trim($config['ADMIN_EMAIL'] ?? ''));
$adminPasswordHash = $config['ADMIN_PASSWORD_HASH'] ?? '';
if ($supabaseUrl === '' || $supabaseKey === '') {
    throw new RuntimeException('Set SUPABASE_URL and SUPABASE_PUBLISHABLE_KEY in backend/.env.');
}

function supabaseRequest(string $path, string $method = 'GET', ?array $body = null, ?string $accessToken = null): array
{
    global $supabaseUrl, $supabaseKey;
    return sendSupabaseRequest($supabaseUrl, $supabaseKey, $path, $method, $body, $accessToken);
}

function supabaseAdminRequest(string $path, string $method = 'GET', ?array $body = null): array
{
    global $supabaseUrl, $supabaseSecretKey;
    if ($supabaseSecretKey === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'The server admin key is not configured.'];
    }
    return sendSupabaseRequest($supabaseUrl, $supabaseSecretKey, $path, $method, $body);
}

function sendSupabaseRequest(string $url, string $apiKey, string $path, string $method, ?array $body, ?string $accessToken = null): array
{
    $headers = [
        'apikey: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    if ($accessToken !== null) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    } elseif (str_starts_with($apiKey, 'eyJ')) {
        // Older anon keys are JWTs; newer sb_publishable keys are not.
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    }
    $curl = curl_init($url . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($response === false) {
        error_log('Supabase request failed: ' . $curlError);
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Unable to connect to the booking service.'];
    }
    $data = json_decode($response, true);
    $ok = $status >= 200 && $status < 300;
    $error = $ok ? null : (is_array($data) ? ($data['msg'] ?? $data['message'] ?? $data['error_description'] ?? $data['error'] ?? null) : null);
    return ['ok' => $ok, 'status' => $status, 'data' => $data, 'error' => $error ?: 'The booking service could not complete the request.'];
}

function h(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bookingCsrfToken(): string
{
    if (empty($_SESSION['booking_csrf'])) {
        $_SESSION['booking_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['booking_csrf'];
}

function bookingCsrfValid(): bool
{
    return isset($_POST['csrf'], $_SESSION['booking_csrf'])
        && hash_equals($_SESSION['booking_csrf'], (string) $_POST['csrf']);
}
