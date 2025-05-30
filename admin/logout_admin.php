<?php
// Memulai atau melanjutkan sesi yang ada untuk bisa dihancurkan
// config.php biasanya sudah memanggil session_start()
require_once '../config.php';

// 1. Hapus semua variabel session.
$_SESSION = array();

// 2. Jika ingin menghancurkan session sepenuhnya, hapus juga session cookie.
// Catatan: Ini akan menghancurkan session, bukan hanya data session!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Akhirnya, hancurkan session.
session_destroy();

// 4. Arahkan ke halaman login admin
// Pesan logout bisa juga disimpan di session sebelum destroy jika login_admin.php bisa menampilkannya
// Tapi untuk kasus ini, GET parameter cukup
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0"); // Tambahan header untuk redirect
header("Pragma: no-cache");
header("Location: login_admin.php?pesan=logout_admin_success");
exit();
?>