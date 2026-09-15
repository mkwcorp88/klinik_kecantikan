<?php
declare(strict_types=1);

$drwLocalConfig = [];
$drwLocalConfigPath = __DIR__ . '/config.local.php';

if (is_file($drwLocalConfigPath)) {
    $loadedConfig = require $drwLocalConfigPath;
    if (!is_array($loadedConfig)) {
        throw new RuntimeException('config.local.php harus mengembalikan array konfigurasi.');
    }
    $drwLocalConfig = $loadedConfig;
}

function drw_config(string $key, ?string $default = null): ?string
{
    global $drwLocalConfig;

    $environmentValue = getenv($key);
    if ($environmentValue !== false && $environmentValue !== '') {
        return $environmentValue;
    }

    if (array_key_exists($key, $drwLocalConfig) && $drwLocalConfig[$key] !== '') {
        return (string) $drwLocalConfig[$key];
    }

    return $default;
}

function drw_is_https(): bool
{
    $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
    $trustedProxy = filter_var(drw_config('TRUSTED_PROXY', 'false'), FILTER_VALIDATE_BOOL);

    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443')
        || ($trustedProxy && strtolower(trim(explode(',', $forwardedProto)[0])) === 'https');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => drw_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_ENV', drw_config('APP_ENV', 'local'));
define('DB_HOST', drw_config('DB_HOST', '127.0.0.1'));
define('DB_USER', drw_config('DB_USER', 'root'));
define('DB_PASS', drw_config('DB_PASS', ''));
define('DB_NAME', drw_config('DB_NAME', 'klinik_drw_estetika'));
define('BASE_URL', rtrim(drw_config('BASE_URL', 'http://127.0.0.1:8000'), '/') . '/');
define('NAMA_KLINIK', 'Klinik Pratama DRW Estetika');
define('GOOGLE_CLIENT_ID', drw_config('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', drw_config('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', drw_config('GOOGLE_REDIRECT_URI', BASE_URL . 'auth/google_callback.php'));
define('GOOGLE_OAUTH_CONFIGURED', GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '');

// Admin credentials are only read from the untracked local configuration or environment.
define('ADMIN_USERNAME', drw_config('ADMIN_USERNAME', ''));
define('ADMIN_PASSWORD_HASH', drw_config('ADMIN_PASSWORD_HASH', ''));
define('ADMIN_PASSWORD_PLAIN', drw_config('ADMIN_PASSWORD_PLAIN', ''));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit(APP_ENV === 'local'
        ? 'Database belum siap. Periksa DB_HOST, DB_USER, DB_PASS, dan DB_NAME di config.local.php.'
        : 'Terjadi gangguan sementara. Silakan coba kembali.');
}

function drw_app_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function drw_flash(string $type, string $message): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
}

function drw_consume_flash(): ?array
{
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }

    $flash = [
        'message' => (string) $_SESSION['flash_message'],
        'type' => (string) ($_SESSION['flash_message_type'] ?? 'info'),
    ];
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);

    return $flash;
}

function drw_csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function drw_is_valid_csrf_token(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function drw_sanitize_return_path(?string $returnPath, string $fallback = 'index.php'): string
{
    if ($returnPath === null || $returnPath === '') {
        return $fallback;
    }

    $parts = parse_url($returnPath);
    if ($parts === false || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'])) {
        return $fallback;
    }

    $allowedPaths = ['index.php', 'order.php', 'profil.php', 'riwayat_order.php', 'testimoni_buat.php'];
    $path = ltrim($parts['path'] ?? '', '/');
    if (!in_array($path, $allowedPaths, true)) {
        return $fallback;
    }

    parse_str($parts['query'] ?? '', $query);
    $safeQuery = [];
    if ($path === 'order.php') {
        if (isset($query['id_layanan']) && ctype_digit((string) $query['id_layanan'])) {
            $safeQuery['id_layanan'] = (int) $query['id_layanan'];
        }
        if (isset($query['cabang']) && preg_match('/^[a-z0-9-]{2,50}$/', (string) $query['cabang'])) {
            $safeQuery['cabang'] = (string) $query['cabang'];
        }
    }

    return $path . ($safeQuery === [] ? '' : '?' . http_build_query($safeQuery));
}

function drw_remember_post_login_redirect(string $returnPath): void
{
    $_SESSION['post_login_redirect'] = drw_sanitize_return_path($returnPath);
}

function drw_consume_post_login_redirect(string $fallback = 'index.php'): string
{
    $returnPath = drw_sanitize_return_path($_SESSION['post_login_redirect'] ?? null, $fallback);
    unset($_SESSION['post_login_redirect']);

    return $returnPath;
}

function drw_login_user(array $user): void
{
    unset($_SESSION['csrf_token'], $_SESSION['admin_logged_in'], $_SESSION['admin_username']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id_user'];
    $_SESSION['username'] = (string) $user['username'];
    $_SESSION['display_name'] = (string) ($user['nama_lengkap'] ?? $user['username']);
    $_SESSION['user_auth_provider'] = (string) ($user['auth_provider'] ?? 'local');
}

function drw_is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function drw_require_member(string $message, string $returnPath): void
{
    if (drw_is_logged_in()) {
        return;
    }

    drw_remember_post_login_redirect($returnPath);
    drw_flash('warning', $message);
    header('Location: ' . drw_app_url('login.php'));
    exit();
}
