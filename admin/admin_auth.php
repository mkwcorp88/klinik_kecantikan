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

function drw_require_admin(): void
{
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return;
    }

    header('Location: login_admin.php?pesan=belum_login_admin');
    exit();
}

function drw_admin_cabang_id(): ?int
{
    if (!isset($_SESSION['admin_cabang_id']) || $_SESSION['admin_cabang_id'] === null || $_SESSION['admin_cabang_id'] === '') {
        return null;
    }

    return (int) $_SESSION['admin_cabang_id'];
}

function drw_admin_is_super(): bool
{
    return ($_SESSION['admin_is_super'] ?? false) === true;
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

function drw_admin_set_session(?int $adminId, string $username, ?int $cabangId, string $cabangNama, bool $isSuper): void
{
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
}
