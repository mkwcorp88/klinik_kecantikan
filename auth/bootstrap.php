<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function drw_google_client(): Google\Client
{
    if (!GOOGLE_OAUTH_CONFIGURED) {
        throw new RuntimeException('Login Google belum dikonfigurasi.');
    }

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoloadPath)) {
        throw new RuntimeException('Dependensi Login Google belum dipasang. Jalankan composer install terlebih dahulu.');
    }

    require_once $autoloadPath;

    $client = new Google\Client();
    $client->setClientId(GOOGLE_CLIENT_ID);
    $client->setClientSecret(GOOGLE_CLIENT_SECRET);
    $client->setRedirectUri(GOOGLE_REDIRECT_URI);

    return $client;
}

function drw_google_base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function drw_google_flash_and_redirect(string $type, string $message, string $path = 'login.php'): never
{
    drw_flash($type, $message);
    header('Location: ' . drw_app_url($path));
    exit();
}

function drw_google_fetch_token(string $authorizationCode, string $codeVerifier): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Ekstensi cURL PHP diperlukan untuk Login Google.');
    }

    $request = curl_init('https://oauth2.googleapis.com/token');
    if ($request === false) {
        throw new RuntimeException('Tidak dapat memulai koneksi ke Google.');
    }

    $body = http_build_query([
        'code' => $authorizationCode,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
        'code_verifier' => $codeVerifier,
    ], '', '&', PHP_QUERY_RFC3986);

    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($request);
    $statusCode = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($request);
    curl_close($request);

    if ($response === false) {
        throw new RuntimeException('Gagal terhubung ke Google: ' . $curlError);
    }

    $token = json_decode($response, true);
    if (!is_array($token) || $statusCode !== 200 || isset($token['error'])) {
        throw new RuntimeException('Google tidak dapat menyelesaikan proses login.');
    }

    return $token;
}

function drw_google_available_username(mysqli $conn, string $googleSub): string
{
    $baseUsername = 'google_' . substr(hash('sha256', $googleSub), 0, 20);
    $candidate = $baseUsername;

    for ($suffix = 1; $suffix <= 20; $suffix++) {
        $statement = $conn->prepare('SELECT id_user FROM user WHERE username = ? LIMIT 1');
        $statement->bind_param('s', $candidate);
        $statement->execute();
        $exists = $statement->get_result()->num_rows > 0;
        $statement->close();

        if (!$exists) {
            return $candidate;
        }

        $candidate = $baseUsername . '_' . $suffix;
    }

    throw new RuntimeException('Tidak dapat menyiapkan ID akun pasien.');
}
