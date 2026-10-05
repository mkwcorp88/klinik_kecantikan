<?php
declare(strict_types=1);

// CLI: izinkan akun Google untuk admin yang sudah terdaftar.
// Contoh: php tools/set_admin_google.php --username=admin_purworejo --email=admin@example.com
// Cabut akses: php tools/set_admin_google.php --username=admin_purworejo --revoke

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya via CLI.');
}

$options = getopt('', ['username:', 'email:', 'revoke']);
$username = trim((string) ($options['username'] ?? ''));
$revoke = array_key_exists('revoke', $options);
$email = strtolower(trim((string) ($options['email'] ?? '')));

if ($username === '' || ($revoke && $email !== '') || (!$revoke && $email === '')) {
    fwrite(STDERR, "Gunakan --username=... bersama --email=... atau --revoke.\n");
    exit(1);
}
if (!$revoke && (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
    fwrite(STDERR, "Alamat email tidak valid.\n");
    exit(1);
}

require __DIR__ . '/../config.php';

$statement = $conn->prepare('SELECT a.id_admin, a.google_email, a.id_cabang, a.status_admin, c.status_cabang FROM admin a LEFT JOIN cabang c ON c.id_cabang = a.id_cabang WHERE a.username = ? LIMIT 1');
$statement->bind_param('s', $username);
$statement->execute();
$admin = $statement->get_result()->fetch_assoc();
$statement->close();

if (!is_array($admin)) {
    fwrite(STDERR, "Akun admin tidak ditemukan.\n");
    exit(1);
}
if (!$revoke && $admin['status_admin'] !== 'aktif') {
    fwrite(STDERR, "Akun admin harus aktif untuk diberi akses Google.\n");
    exit(1);
}

$isSuper = $admin['id_cabang'] === null;
if ($isSuper && ($username !== 'drwcorpora@gmail.com' || $revoke || $email !== 'drwcorpora@gmail.com')) {
    fwrite(STDERR, "Akses Google superadmin hanya untuk drwcorpora@gmail.com dan tidak dapat dicabut lewat alat ini.\n");
    exit(1);
}
if (!$isSuper && !$revoke && ($admin['status_cabang'] !== 'aktif' || $email === 'drwcorpora@gmail.com')) {
    fwrite(STDERR, "Cabang harus aktif dan email pemilik tidak boleh dipakai admin cabang.\n");
    exit(1);
}

$newEmail = $revoke ? null : $email;
if ($admin['google_email'] === $newEmail) {
    fwrite(STDOUT, "Akses Google admin tidak berubah.\n");
    exit(0);
}

$statement = $conn->prepare('UPDATE admin SET google_email = ?, google_sub = NULL WHERE id_admin = ?');
$adminId = (int) $admin['id_admin'];
$statement->bind_param('si', $newEmail, $adminId);
$statement->execute();
$statement->close();

fwrite(STDOUT, $revoke ? "Akses Google admin dicabut.\n" : "Akses Google admin diperbarui. Login pertama akan mengikat identitas Google.\n");
