<?php
// Mencegah error reporting untuk notice dan warning (opsional, baik untuk development)
// error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Mulai session jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// =========================================
// PENGATURAN DATABASE
// =========================================
define('DB_HOST', 'localhost');         // Biasanya 'localhost' untuk XAMPP
define('DB_USER', 'root');              // User default MySQL di XAMPP
define('DB_PASS', '');                  // Password default MySQL di XAMPP adalah kosong
define('DB_NAME', 'klinik_kecantikan'); // Nama database Anda

// =========================================
// KONEKSI KE DATABASE
// =========================================
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Periksa koneksi
if ($conn->connect_error) {
    // Hentikan eksekusi dan tampilkan pesan error jika koneksi gagal
    // Sebaiknya ini dimatikan atau diganti dengan logging di lingkungan produksi
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// (Opsional) Atur charset koneksi ke utf8mb4 untuk mendukung berbagai karakter
if (!$conn->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
    // exit();
}

// =========================================
// PENGATURAN UMUM APLIKASI (Opsional)
// =========================================
define('BASE_URL', 'http://localhost/klinik_kecantikan/'); // Sesuaikan jika path XAMPP Anda berbeda
define('NAMA_KLINIK', 'Serenity Aesthetic Center');

// =========================================
// KREDENSIAL ADMIN (Hardcoded)
// =========================================
// Sebaiknya password admin juga di-hash dan disimpan sebagai hash.
// Namun, untuk perbandingan langsung seperti permintaan:
define('ADMIN_USERNAME', 'mik22');
define('ADMIN_PASSWORD_PLAIN', 'rekmed'); // Ini adalah password plain text untuk perbandingan langsung.
                                         // Pertimbangkan keamanan jika ini digunakan di lingkungan produksi.
                                         // Idealnya, saat login, hash input password dan bandingkan dengan hash yang tersimpan.

// =========================================
// FUNGSI BANTUAN (Opsional, bisa ditambahkan di sini)
// =========================================

/*
Contoh fungsi bantuan sederhana:

// Fungsi untuk membersihkan input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Fungsi untuk mengecek apakah member sudah login
function is_member_logged_in() {
    return isset($_SESSION['user_id']); // Asumsi 'user_id' disimpan di session saat member login
}

// Fungsi untuk mengecek apakah admin sudah login
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Di setiap halaman admin, panggil ini di awal:
// if (!is_admin_logged_in()) {
//     redirect(BASE_URL . 'login.php?pesan=belum_login_admin');
// }

// Di setiap halaman yang memerlukan login member:
// if (!is_member_logged_in()) {
//     redirect(BASE_URL . 'login.php?pesan=belum_login');
// }
*/

?>