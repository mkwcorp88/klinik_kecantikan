<?php
require_once 'config.php';

$errors = [];
$csrfToken = drw_csrf_token();

if (drw_is_logged_in()) {
    header('Location: ' . drw_app_url(drw_consume_post_login_redirect()));
    exit();
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ' . drw_app_url('admin/index.php'));
    exit();
}

$displayMessage = '';
$displayMessageType = '';

if ($flash = drw_consume_flash()) {
    $displayMessage = $flash['message'];
    $displayMessageType = $flash['type'];
} elseif (isset($_SESSION['error_message_redirect'])) {
    $displayMessage = $_SESSION['error_message_redirect'];
    $displayMessageType = 'warning';
    unset($_SESSION['error_message_redirect']);
} elseif (isset($_SESSION['success_message'])) {
    $displayMessage = $_SESSION['success_message'];
    $displayMessageType = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_GET['pesan'])) {
    $pesan = (string) $_GET['pesan'];
    if ($pesan === 'belum_login') {
        $displayMessage = 'Anda harus login terlebih dahulu untuk mengakses halaman tersebut.';
        $displayMessageType = 'warning';
    } elseif ($pesan === 'belum_terdaftar_order') {
        $displayMessage = 'Silakan masuk dengan Google untuk melanjutkan booking.';
        $displayMessageType = 'info';
    } elseif ($pesan === 'belum_terdaftar_testimoni') {
        $displayMessage = 'Silakan masuk dengan Google untuk memberi testimoni.';
        $displayMessageType = 'info';
    } elseif ($pesan === 'logout' || $pesan === 'logout_admin_success') {
        $displayMessage = 'Anda telah berhasil logout.';
        $displayMessageType = 'success';
    } elseif ($pesan === 'khusus_admin') {
        $displayMessage = 'Halaman tersebut khusus untuk Admin. Silakan login sebagai Admin.';
        $displayMessageType = 'danger';
    } elseif ($pesan === 'error_sesi') {
        $displayMessage = 'Sesi tidak valid atau pengguna tidak ditemukan. Silakan login kembali.';
        $displayMessageType = 'danger';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '') {
            $errors[] = 'Username wajib diisi.';
        }
        if ($password === '') {
            $errors[] = 'Password wajib diisi.';
        }

        if ($errors === []) {
            $stmt = $conn->prepare('SELECT id_user, username, nama_lengkap, password, auth_provider FROM user WHERE username = ? LIMIT 1');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if ($user['password'] !== null && password_verify($password, $user['password'])) {
                    drw_login_user($user);
                    header('Location: ' . drw_app_url(drw_consume_post_login_redirect()));
                    exit();
                }

                if ($user['auth_provider'] === 'google' && $user['password'] === null) {
                    $errors[] = 'Akun ini menggunakan Login Google. Silakan lanjutkan dengan Google.';
                } else {
                    $errors[] = 'Username atau password salah.';
                }
            } else {
                $errors[] = 'Username atau password salah.';
            }
            $stmt->close();
        }
    }
}

$noticeType = in_array($displayMessageType, ['success', 'warning', 'info', 'danger'], true) ? $displayMessageType : 'info';
$pageTitle = 'Masuk | ' . NAMA_KLINIK;
$pageDescription = 'Masuk ke akun Klinik Pratama DRW Estetika untuk mengelola janji konsultasi Anda.';
$activePage = 'login';
$bodyClass = 'home-page auth-page';

$conn->close();
require 'partials/site_header.php';
?>

<main class="drw-auth-page-main">
    <section class="drw-auth-shell">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="drw-auth-card">
                        <aside class="drw-auth-aside">
                            <span class="drw-hero-label">AREA PASIEN</span>
                            <h1>Perawatan yang dimulai dari <em>cerita Anda.</em></h1>
                            <p>Masuk untuk membuat janji konsultasi dan memantau riwayat booking Anda.</p>
                            <ul class="drw-auth-benefits">
                                <li><i class="fa-regular fa-calendar-check"></i> Buat janji konsultasi online</li>
                                <li><i class="fa-solid fa-location-dot"></i> Pilih cabang yang paling nyaman</li>
                                <li><i class="fa-regular fa-clock"></i> Pantau status booking Anda</li>
                            </ul>
                        </aside>

                        <section class="drw-auth-form-panel" aria-labelledby="login-title">
                            <span class="drw-hero-label">MASUK</span>
                            <h2 id="login-title">Selamat datang kembali.</h2>
                            <p class="drw-auth-intro">Masuk untuk melanjutkan ke konsultasi dan perawatan Anda.</p>

                            <?php if ($displayMessage !== ''): ?>
                                <div class="drw-auth-alert is-<?= $noticeType ?>" role="alert">
                                    <span><?= htmlspecialchars($displayMessage, ENT_QUOTES, 'UTF-8') ?></span>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($errors !== []): ?>
                                <div class="drw-auth-alert is-danger" role="alert">
                                    <div>
                                        <?php foreach ($errors as $error): ?>
                                            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (GOOGLE_OAUTH_CONFIGURED): ?>
                                <a class="drw-google-button" href="auth/google_start.php"><i class="fa-brands fa-google"></i> Lanjutkan dengan Google</a>
                            <?php else: ?>
                                <div class="drw-auth-alert is-info"><i class="fa-solid fa-circle-info"></i><span>Login Google sedang disiapkan. Akun lama tetap dapat digunakan di bawah ini.</span></div>
                            <?php endif; ?>

                            <?php if (GOOGLE_OAUTH_CONFIGURED): ?>
                                <details class="drw-legacy-login">
                                    <summary>Login dengan akun lama</summary>
                                    <p>Gunakan ini hanya jika Anda sudah memiliki akun lokal sebelumnya.</p>
                            <?php endif; ?>

                            <form class="drw-auth-form" action="login.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required value="<?= isset($_POST['username']) ? htmlspecialchars((string) $_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>" autofocus>
                                </div>
                                <div>
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <button type="submit" class="btn drw-auth-submit w-100">Masuk</button>
                            </form>

                            <?php if (GOOGLE_OAUTH_CONFIGURED): ?>
                                </details>
                            <?php endif; ?>

                            <p class="drw-auth-admin"><a href="admin/login_admin.php">Masuk sebagai Admin <i class="fa-solid fa-arrow-right"></i></a></p>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require 'partials/site_footer.php'; ?>
