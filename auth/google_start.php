<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    drw_google_client();
} catch (RuntimeException $exception) {
    drw_google_flash_and_redirect('danger', $exception->getMessage());
}

$mode = 'login';
if (($_GET['mode'] ?? '') === 'link') {
    if (!isset($_SESSION['user_id'])) {
        drw_google_flash_and_redirect('warning', 'Silakan login terlebih dahulu untuk menghubungkan akun Google.');
    }
    $mode = 'link';
}

$state = drw_google_base64url(random_bytes(32));
$nonce = drw_google_base64url(random_bytes(32));
$codeVerifier = drw_google_base64url(random_bytes(64));
$codeChallenge = drw_google_base64url(hash('sha256', $codeVerifier, true));

$_SESSION['google_oauth'] = [
    'state' => $state,
    'nonce' => $nonce,
    'code_verifier' => $codeVerifier,
    'mode' => $mode,
    'expires_at' => time() + 600,
];

$authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'nonce' => $nonce,
    'code_challenge' => $codeChallenge,
    'code_challenge_method' => 'S256',
    'access_type' => 'online',
    'prompt' => 'select_account',
], '', '&', PHP_QUERY_RFC3986);

header('Location: ' . $authorizationUrl);
exit();
