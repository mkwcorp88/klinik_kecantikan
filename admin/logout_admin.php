<?php
require_once '../config.php'; // Untuk session_start()

// Hapus spesifik admin session variables
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_username']);

// Jika Anda ingin menghancurkan seluruh session (termasuk jika ada member login di browser yang sama)
// $_SESSION = array();
// if (ini_get("session.use_cookies")) {
//     $params = session_get_cookie_params();
//     setcookie(session_name(), '', time() - 42000,
//         $params["path"], $params["domain"],
//         $params["secure"], $params["httponly"]
//     );
// }
// session_destroy();

// Arahkan ke halaman login admin dengan pesan logout berhasil
header("Location: login_admin.php?pesan=logout_admin_success");
exit();
?>