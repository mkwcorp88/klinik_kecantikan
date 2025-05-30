<?php
require_once 'config.php'; // Session sudah dimulai di config.php
$errors = [];

// Jika pengguna atau admin sudah login, arahkan mereka
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['redirect_url'])) {
        $redirect_url = $_SESSION['redirect_url'];
        unset($_SESSION['redirect_url']);
        if (isset($_GET['id_layanan_after_login'])) { // Khusus untuk redirect dari order.php
            $_SESSION['id_layanan_after_login'] = $_GET['id_layanan_after_login'];
        }
        header("Location: " . $redirect_url);
        exit();
    }
    header("Location: index.php"); // Default redirect untuk member
    exit();
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ) {
    header("Location: admin/index.php"); // Default redirect untuk admin
    exit();
}

// Logika untuk menangani pesan notifikasi secara terpusat
$display_message = '';
$display_message_type = ''; // 'success', 'warning', 'info', 'danger'

// Prioritaskan pesan dari session (biasanya lebih spesifik atau hasil aksi sebelumnya)
if (isset($_SESSION['error_message_redirect'])) { // Pesan dari halaman yang mengarahkan karena belum login
    $display_message = $_SESSION['error_message_redirect'];
    $display_message_type = 'warning'; // Atau 'info' tergantung konteks asalnya
    unset($_SESSION['error_message_redirect']);
} elseif (isset($_SESSION['success_message'])) { // Pesan sukses, misal setelah registrasi
    $display_message = $_SESSION['success_message'];
    $display_message_type = 'success';
    unset($_SESSION['success_message']);
} elseif (isset($_GET['pesan'])) { // Jika tidak ada pesan session, cek GET parameter
    $pesan = $_GET['pesan'];
    if ($pesan == 'belum_login') {
        $display_message = "Anda harus login terlebih dahulu untuk mengakses halaman tersebut.";
        $display_message_type = 'warning';
    } elseif ($pesan == 'belum_terdaftar_order') {
        // Pesan ini spesifik, bisa jadi berasal dari redirect order.php jika tidak menggunakan session message
        $display_message = 'Silakan login atau <a href="daftar.php" class="alert-link">daftar akun</a> untuk melanjutkan pesanan.';
        $display_message_type = 'info';
    } elseif ($pesan == 'belum_terdaftar_testimoni') {
        $display_message = 'Silakan login atau <a href="daftar.php" class="alert-link">daftar akun</a> untuk memberi testimoni.';
        $display_message_type = 'info';
    } elseif ($pesan == 'logout' || $pesan == 'logout_admin_success') {
        $display_message = "Anda telah berhasil logout.";
        $display_message_type = 'success';
    } elseif ($pesan == 'khusus_admin') {
        $display_message = "Halaman tersebut khusus untuk Admin. Silakan login sebagai Admin.";
        $display_message_type = 'danger';
    } elseif ($pesan == 'error_sesi') {
        $display_message = "Sesi tidak valid atau pengguna tidak ditemukan. Silakan login kembali.";
        $display_message_type = 'danger';
    }
    // Anda bisa menambahkan penanganan untuk nilai 'pesan' lainnya di sini
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    if (empty($username)) $errors[] = "Username wajib diisi.";
    if (empty($password)) $errors[] = "Password wajib diisi.";

    if (empty($errors)) {
        // Cek login admin
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD_PLAIN) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header("Location: admin/index.php");
            exit();
        } else {
            // Cek login member
            $stmt = $conn->prepare("SELECT id_user, username, password, nama_lengkap FROM user WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id_user'];
                    $_SESSION['username'] = $user['username'];

                    // Cek apakah ada URL redirect dari halaman sebelumnya (disimpan di session)
                    if (isset($_SESSION['redirect_url'])) {
                        $redirect_url = $_SESSION['redirect_url'];
                        unset($_SESSION['redirect_url']);
                        // Periksa apakah ada parameter tambahan yang perlu dibawa (misal dari order.php)
                        if (isset($_GET['id_layanan_after_login'])) {
                             $_SESSION['id_layanan_after_login'] = $_GET['id_layanan_after_login'];
                        }
                        header("Location: " . $redirect_url . (strpos($redirect_url, '?') === false ? '?' : '&') . 'login_success=1' );
                        exit();
                    }

                    header("Location: index.php?login_success=1"); // Default redirect setelah login member
                    exit();
                } else {
                    $errors[] = "Username atau password salah.";
                }
            } else {
                $errors[] = "Username atau password salah.";
            }
            $stmt->close();
        }
    }
}

// Untuk meneruskan parameter redirect ke action form jika ada
$redirect_query_params = '';
if (isset($_GET['redirect_to_order']) && $_GET['redirect_to_order'] == 'true') {
    $redirect_query_params .= '?redirect_to_order=true';
    if(isset($_GET['id_layanan_after_login'])) {
        $redirect_query_params .= '&id_layanan_after_login=' . intval($_GET['id_layanan_after_login']);
    }
} elseif (isset($_GET['redirect_to_testimoni']) && $_GET['redirect_to_testimoni'] == 'true') {
    $redirect_query_params .= '?redirect_to_testimoni=true';
} elseif (isset($_SESSION['redirect_url'])) { // Jika redirect_url ada di session, prioritaskan itu
    // Tidak perlu menambahkan redirect_url ke query string form jika sudah ditangani oleh session saat login sukses
}


$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="d-flex flex-column min-vh-100"> <?php /* STICKY FOOTER */ ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Logo Klinik" height="40">
                <?php echo NAMA_KLINIK; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" href="layanan_tampil.php">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" href="testimoni_tampil.php">Testimoni Publik</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'login.php') echo 'active'; ?>" aria-current="page" href="login.php">Login</a></li>
                    <li class="nav-item"><a class="btn btn-primary ms-lg-2 <?php if(basename($_SERVER['PHP_SELF']) == 'daftar.php') echo 'active'; ?>" href="daftar.php">Daftar Member</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 mb-5 flex-grow-1"> <?php /* STICKY FOOTER */ ?>
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h4 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login Akun</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php // Blok tunggal untuk menampilkan pesan ?>
                        <?php if (!empty($display_message)): ?>
                            <div class="alert alert-<?php echo $display_message_type; ?> alert-dismissible fade show" role="alert">
                                <?php echo $display_message; // Pesan ini bisa mengandung HTML, pastikan aman ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php<?php echo htmlspecialchars($redirect_query_params); ?>" method="post">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg">Login</button>
                            </div>
                        </form>
                        <p class="mt-3 text-center mb-0">Belum punya akun? <a href="daftar.php">Daftar di sini</a></p>
                        <p class="mt-2 text-center small"><a href="admin/login_admin.php">Login sebagai Admin</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main> <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>