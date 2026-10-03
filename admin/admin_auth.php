<?php
declare(strict_types=1);

// Helper auth admin per klinik. Dipakai semua halaman di folder admin.
// Session yang dipakai:
// - admin_logged_in (bool)
// - admin_id (int|null, null untuk akun env legacy)
// - admin_username (string)
// - admin_cabang_id (int|null, null = semua klinik)
// - admin_cabang_nama (string)
// - admin_is_super (bool)
// - admin_auth_method (password|google|legacy)
// - admin_google_sub (string|null)

function drw_admin_clear_session(): void
{
    unset(
        $_SESSION['admin_logged_in'],
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_cabang_id'],
        $_SESSION['admin_cabang_nama'],
        $_SESSION['admin_is_super'],
        $_SESSION['admin_auth_method'],
        $_SESSION['admin_google_sub']
    );
}

function drw_admin_session_valid(mysqli $conn): bool
{
    if (($_SESSION['admin_logged_in'] ?? false) !== true) {
        return false;
    }

    $adminId = $_SESSION['admin_id'] ?? null;
    // Sesi sebelum migrasi belum menyimpan metode login.
    $method = $_SESSION['admin_auth_method'] ?? ($adminId === null ? 'legacy' : 'password');
    if ($method === 'legacy') {
        return $adminId === null
            && ADMIN_USERNAME !== ''
            && hash_equals(ADMIN_USERNAME, (string) ($_SESSION['admin_username'] ?? ''))
            && (ADMIN_PASSWORD_HASH !== '' || (APP_ENV === 'local' && ADMIN_PASSWORD_PLAIN !== ''))
            && ($_SESSION['admin_is_super'] ?? false) === true;
    }

    if (!is_int($adminId) || $adminId <= 0 || !in_array($method, ['password', 'google'], true)) {
        return false;
    }

    $statement = $conn->prepare('SELECT username, password_hash, google_email, google_sub, id_cabang, status_admin FROM admin WHERE id_admin = ? LIMIT 1');
    $statement->bind_param('i', $adminId);
    $statement->execute();
    $admin = $statement->get_result()->fetch_assoc();
    $statement->close();
    if (!is_array($admin)
        || $admin['status_admin'] !== 'aktif'
        || !hash_equals((string) $admin['username'], (string) ($_SESSION['admin_username'] ?? ''))) {
        return false;
    }

    if ($method === 'google') {
        $sessionSub = $_SESSION['admin_google_sub'] ?? null;
        if (!is_string($sessionSub)
            || $sessionSub === ''
            || !is_string($admin['google_sub'])
            || !hash_equals($admin['google_sub'], $sessionSub)
            || !hash_equals('drwcorpora@gmail.com', strtolower((string) $admin['google_email']))) {
            return false;
        }
    } elseif (!is_string($admin['password_hash']) || $admin['password_hash'] === '') {
        return false;
    }

    if ($admin['id_cabang'] === null) {
        return ($_SESSION['admin_is_super'] ?? false) === true;
    }

    return ($_SESSION['admin_is_super'] ?? false) === false
        && isset($_SESSION['admin_cabang_id'])
        && (int) $_SESSION['admin_cabang_id'] === (int) $admin['id_cabang'];
}

function drw_require_admin(): void
{
    global $conn;
    if ($conn instanceof mysqli && drw_admin_session_valid($conn)) {
        return;
    }

    drw_admin_clear_session();
    header('Location: login_admin.php?pesan=belum_login_admin');
    exit();
}

function drw_admin_cabang_id(): ?int
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return null;
    }
    if (!isset($_SESSION['admin_cabang_id']) || $_SESSION['admin_cabang_id'] === null || $_SESSION['admin_cabang_id'] === '') {
        // Fail closed for non-super admins; require explicit session scope.
        return drw_admin_is_super() ? null : -1;
    }

    return (int) $_SESSION['admin_cabang_id'];
}

function drw_admin_is_super(): bool
{
    return ($_SESSION['admin_logged_in'] ?? false) === true
        && ($_SESSION['admin_is_super'] ?? false) === true;
}

function drw_admin_display_cabang(): string
{
    return (string) ($_SESSION['admin_cabang_nama'] ?? 'Semua Klinik');
}

function drw_admin_fetch_branches(mysqli $conn): array
{
    $branches = [];
    $result = $conn->query("SELECT id_cabang, slug, nama_cabang FROM cabang WHERE status_cabang = 'aktif' ORDER BY nama_cabang ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['id_cabang'] = (int) $row['id_cabang'];
            $branches[] = $row;
        }
        $result->close();
    }

    return $branches;
}

function drw_admin_login_db_account(mysqli $conn, string $username): ?array
{
    $stmt = $conn->prepare('SELECT id_admin, username, password_hash, id_cabang, status_admin FROM admin WHERE username = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

function drw_admin_google_account(mysqli $conn, string $email): ?array
{
    $statement = $conn->prepare('SELECT id_admin, username, google_email, google_sub, id_cabang, status_admin FROM admin WHERE google_email = ? LIMIT 1');
    $statement->bind_param('s', $email);
    $statement->execute();
    $admin = $statement->get_result()->fetch_assoc();
    $statement->close();

    return is_array($admin) ? $admin : null;
}

function drw_admin_set_session(?int $adminId, string $username, ?int $cabangId, string $cabangNama, bool $isSuper, ?string $googleSub = null): void
{
    drw_admin_clear_session();
    unset(
        $_SESSION['csrf_token'],
        $_SESSION['user_id'],
        $_SESSION['username'],
        $_SESSION['display_name'],
        $_SESSION['user_auth_provider'],
        $_SESSION['post_login_redirect']
    );
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_cabang_id'] = $cabangId;
    $_SESSION['admin_cabang_nama'] = $cabangNama;
    $_SESSION['admin_is_super'] = $isSuper;
    $_SESSION['admin_auth_method'] = $adminId === null ? 'legacy' : ($googleSub === null ? 'password' : 'google');
    $_SESSION['admin_google_sub'] = $googleSub;
}
