<?php
declare(strict_types=1);

// CLI: buat / reset password admin per klinik.
// Contoh:
//   php tools/create_admin.php --username=admin_purworejo --cabang=purworejo
//   php tools/create_admin.php --username=admin_kutoarjo --cabang=kutoarjo --nama="Admin Kutoarjo"
//   php tools/create_admin.php --username=superadmin --super
// Password diminta interaktif (tidak tampil di shell history).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya via CLI.');
}

$options = getopt('', ['username:', 'cabang::', 'nama::', 'super', 'nonaktif']);
$username = trim((string) ($options['username'] ?? ''));
$cabangSlug = trim((string) ($options['cabang'] ?? ''));
$nama = trim((string) ($options['nama'] ?? ''));
$isSuper = isset($options['super']);
$makeInactive = isset($options['nonaktif']);

if ($username === '' || !preg_match('/^[a-z0-9_.-]{3,50}$/i', $username)) {
    fwrite(STDERR, "Username wajib: 3-50 karakter huruf/angka/._-\n");
    exit(1);
}
if (!$isSuper && $cabangSlug === '') {
    fwrite(STDERR, "Untuk admin klinik wajib --cabang=purworejo|kutoarjo|magelang. Untuk superadmin pakai --super.\n");
    exit(1);
}

fwrite(STDOUT, "Password baru untuk {$username}: ");
$password = '';
if (function_exists('readline')) {
    // Fallback sederhana; untuk keamanan maksimal jalankan via terminal terpercaya.
    $password = trim((string) fgets(STDIN));
} else {
    $password = trim((string) fgets(STDIN));
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password minimal 8 karakter.\n");
    exit(1);
}

require __DIR__ . '/../config.php';

$cabangId = null;
$cabangNama = 'Semua Klinik';
if (!$isSuper) {
    $stmt = $conn->prepare("SELECT id_cabang, nama_cabang FROM cabang WHERE slug = ? AND status_cabang = 'aktif' LIMIT 1");
    $stmt->bind_param('s', $cabangSlug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        fwrite(STDERR, "Cabang '{$cabangSlug}' tidak ditemukan / tidak aktif.\n");
        exit(1);
    }
    $cabangId = (int) $row['id_cabang'];
    $cabangNama = (string) $row['nama_cabang'];
}

$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Gagal membuat hash password.\n");
    exit(1);
}

$status = $makeInactive ? 'nonaktif' : 'aktif';
$namaDb = $nama !== '' ? $nama : null;

// Upsert berdasarkan username.
$stmt = $conn->prepare('SELECT id_admin FROM admin WHERE username = ? LIMIT 1');
$stmt->bind_param('s', $username);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    if ($cabangId === null) {
        $stmt = $conn->prepare('UPDATE admin SET password_hash = ?, id_cabang = NULL, nama_lengkap = ?, status_admin = ? WHERE username = ?');
        $stmt->bind_param('ssss', $hash, $namaDb, $status, $username);
    } else {
        $stmt = $conn->prepare('UPDATE admin SET password_hash = ?, id_cabang = ?, nama_lengkap = ?, status_admin = ? WHERE username = ?');
        $stmt->bind_param('sisss', $hash, $cabangId, $namaDb, $status, $username);
    }
    $stmt->execute();
    $stmt->close();
    fwrite(STDOUT, "Admin '{$username}' diperbarui ({$cabangNama}, {$status}).\n");
} else {
    if ($cabangId === null) {
        $stmt = $conn->prepare('INSERT INTO admin (username, password_hash, id_cabang, nama_lengkap, status_admin) VALUES (?, ?, NULL, ?, ?)');
        $stmt->bind_param('ssss', $username, $hash, $namaDb, $status);
    } else {
        $stmt = $conn->prepare('INSERT INTO admin (username, password_hash, id_cabang, nama_lengkap, status_admin) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiss', $username, $hash, $cabangId, $namaDb, $status);
    }
    $stmt->execute();
    $stmt->close();
    fwrite(STDOUT, "Admin '{$username}' dibuat ({$cabangNama}, {$status}).\n");
}

$conn->close();
