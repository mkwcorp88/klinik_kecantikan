<?php
require_once 'config.php'; // Session sudah dimulai di config.php

// 1. Autentikasi: Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message_redirect'] = "Anda harus login untuk mengakses halaman profil.";
    $_SESSION['redirect_url'] = 'profil.php'; // Simpan halaman tujuan
    header("Location: login.php?pesan=belum_login");
    exit();
}

$id_user = $_SESSION['user_id'];
$user_data = null;
$errors_detail = [];
$success_message_detail = '';
$errors_password = [];
$success_message_password = '';

// 2. Ambil data pengguna saat ini
$stmt_user = $conn->prepare("SELECT username, nama_lengkap, email, no_telepon, alamat, tanggal_daftar FROM user WHERE id_user = ?");
$stmt_user->bind_param("i", $id_user);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows === 1) {
    $user_data = $result_user->fetch_assoc();
} else {
    // Seharusnya tidak terjadi jika session valid, tapi sebagai fallback:
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    $_SESSION['error_message_redirect'] = "Sesi tidak valid atau pengguna tidak ditemukan.";
    header("Location: login.php?pesan=error_sesi");
    exit();
}
$stmt_user->close();

// 3. Handle Update Profil Detail
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profil_detail'])) {
    $nama_lengkap = $conn->real_escape_string(trim($_POST['nama_lengkap']));
    $email_post = $conn->real_escape_string(trim($_POST['email'])); // Email dari form
    $no_telepon = $conn->real_escape_string(trim($_POST['no_telepon']));
    $alamat = $conn->real_escape_string(trim($_POST['alamat']));

    // Validasi dasar
    if (empty($nama_lengkap)) $errors_detail[] = "Nama lengkap wajib diisi.";
    if (empty($no_telepon)) $errors_detail[] = "Nomor telepon wajib diisi.";
    if (empty($alamat)) $errors_detail[] = "Alamat wajib diisi.";
    if (!empty($email_post) && !filter_var($email_post, FILTER_VALIDATE_EMAIL)) {
        $errors_detail[] = "Format email tidak valid.";
    }

    // Cek keunikan email jika diubah dan tidak kosong
    if (empty($errors_detail) && !empty($email_post) && strtolower($email_post) !== strtolower($user_data['email'] ?? '')) {
        $stmt_check_email = $conn->prepare("SELECT id_user FROM user WHERE email = ? AND id_user != ?");
        $stmt_check_email->bind_param("si", $email_post, $id_user);
        $stmt_check_email->execute();
        $result_check_email = $stmt_check_email->get_result();
        if ($result_check_email->num_rows > 0) {
            $errors_detail[] = "Email sudah terdaftar oleh pengguna lain.";
        }
        $stmt_check_email->close();
    }

    if (empty($errors_detail)) {
        $email_val_db = !empty($email_post) ? $email_post : NULL;
        $stmt_update = $conn->prepare("UPDATE user SET nama_lengkap = ?, email = ?, no_telepon = ?, alamat = ? WHERE id_user = ?");
        $stmt_update->bind_param("ssssi", $nama_lengkap, $email_val_db, $no_telepon, $alamat, $id_user);

        if ($stmt_update->execute()) {
            $_SESSION['success_message_profil'] = "Profil berhasil diperbarui.";
            // Jika nama lengkap adalah bagian dari session username, mungkin perlu update session
            // Namun, username (login) tidak diubah di sini.
            header("Location: profil.php"); // Redirect untuk refresh data dan tampilkan pesan
            exit();
        } else {
            $errors_detail[] = "Gagal memperbarui profil: Terjadi kesalahan server.";
        }
        $stmt_update->close();
    }
}

// 4. Handle Ganti Password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ganti_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password_baru = $_POST['konfirmasi_password_baru'];

    if (empty($password_lama) || empty($password_baru) || empty($konfirmasi_password_baru)) {
        $errors_password[] = "Semua field password wajib diisi.";
    } elseif ($password_baru !== $konfirmasi_password_baru) {
        $errors_password[] = "Password baru dan konfirmasi password tidak cocok.";
    } elseif (strlen($password_baru) < 6) {
        $errors_password[] = "Password baru minimal 6 karakter.";
    }

    if (empty($errors_password)) {
        $stmt_pass = $conn->prepare("SELECT password FROM user WHERE id_user = ?");
        $stmt_pass->bind_param("i", $id_user);
        $stmt_pass->execute();
        $result_pass = $stmt_pass->get_result();
        $user_pass_data = $result_pass->fetch_assoc();
        $stmt_pass->close();

        if ($user_pass_data && password_verify($password_lama, $user_pass_data['password'])) {
            $hashed_password_baru = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt_update_pass = $conn->prepare("UPDATE user SET password = ? WHERE id_user = ?");
            $stmt_update_pass->bind_param("si", $hashed_password_baru, $id_user);

            if ($stmt_update_pass->execute()) {
                $_SESSION['success_message_password'] = "Password berhasil diganti.";
                header("Location: profil.php");
                exit();
            } else {
                $errors_password[] = "Gagal mengganti password: Terjadi kesalahan server.";
            }
            $stmt_update_pass->close();
        } else {
            $errors_password[] = "Password lama yang Anda masukkan salah.";
        }
    }
}

// Ambil pesan sukses/error dari session jika ada (setelah redirect)
if (isset($_SESSION['success_message_profil'])) {
    $success_message_detail = $_SESSION['success_message_profil'];
    unset($_SESSION['success_message_profil']);
}
if (isset($_SESSION['success_message_password'])) {
    $success_message_password = $_SESSION['success_message_password'];
    unset($_SESSION['success_message_password']);
}
if (isset($_SESSION['error_message_redirect'])) { // Pesan error dari redirect login
    $message_redirect_error = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}

// Data untuk repopulate form jika ada error pada update detail, atau data awal dari DB
$form_nama_lengkap = (!empty($errors_detail) && isset($_POST['nama_lengkap'])) ? htmlspecialchars($_POST['nama_lengkap']) : htmlspecialchars($user_data['nama_lengkap']);
$form_email = (!empty($errors_detail) && isset($_POST['email'])) ? htmlspecialchars($_POST['email']) : htmlspecialchars($user_data['email'] ?? '');
$form_no_telepon = (!empty($errors_detail) && isset($_POST['no_telepon'])) ? htmlspecialchars($_POST['no_telepon']) : htmlspecialchars($user_data['no_telepon']);
$form_alamat = (!empty($errors_detail) && isset($_POST['alamat'])) ? htmlspecialchars($_POST['alamat']) : htmlspecialchars($user_data['alamat']);

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Saya - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Raleway:wght@400;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'order.php') echo 'active'; ?>" href="order.php">Order Sekarang</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php
                                $user_pages = ['riwayat_order.php', 'testimoni_buat.php', 'profil.php'];
                                if (in_array(basename($_SERVER['PHP_SELF']), $user_pages)) echo 'active';
                            ?>" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-current="page">
                                <i class="fas fa-user-circle"></i> Halo, <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'riwayat_order.php') echo 'active'; ?>" href="riwayat_order.php"><i class="fas fa-history"></i> Riwayat Pesanan</a></li>
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_buat.php') echo 'active'; ?>" href="testimoni_buat.php"><i class="fas fa-comment-medical"></i> Buat Review / Testimoni Saya</a></li>
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'profil.php') echo 'active'; ?>" href="profil.php"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php elseif (isset($_SESSION['admin_logged_in'])): ?>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-shield"></i> Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownAdmin">
                                <li><a class="dropdown-item" href="admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php endif; // Pengguna belum login, seharusnya sudah diredirect jika mengakses halaman ini ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 mb-5 flex-grow-1"> <?php /* STICKY FOOTER */ ?>
        <h1 class="text-center mb-5 section-title">Profil Saya</h1>

        <?php if (isset($message_redirect_error)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message_redirect_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-id-card"></i> Informasi Akun</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message_detail): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success_message_detail); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($errors_detail)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors_detail as $error): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form action="profil.php" method="post">
                            <div class="mb-3">
                                <label for="profil_username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="profil_username" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly disabled>
                                <div class="form-text">Username tidak dapat diubah.</div>
                            </div>
                            <div class="mb-3">
                                <label for="profil_nama_lengkap" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="profil_nama_lengkap" name="nama_lengkap" value="<?php echo $form_nama_lengkap; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="profil_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="profil_email" name="email" value="<?php echo $form_email; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="profil_no_telepon" class="form-label">Nomor Telepon <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="profil_no_telepon" name="no_telepon" value="<?php echo $form_no_telepon; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="profil_alamat" class="form-label">Alamat <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="profil_alamat" name="alamat" rows="3" required><?php echo $form_alamat; ?></textarea>
                            </div>
                             <div class="mb-3">
                                <label class="form-label">Tanggal Daftar</label>
                                <input type="text" class="form-control" value="<?php echo date('d F Y, H:i', strtotime($user_data['tanggal_daftar'])); ?>" readonly disabled>
                            </div>
                            <button type="submit" name="update_profil_detail" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan Profil</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-secondary text-white">
                         <h5 class="mb-0"><i class="fas fa-key"></i> Ganti Password</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message_password): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($success_message_password); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($errors_password)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors_password as $error): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form action="profil.php" method="post">
                            <div class="mb-3">
                                <label for="password_lama" class="form-label">Password Lama <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_lama" name="password_lama" required>
                            </div>
                            <div class="mb-3">
                                <label for="password_baru" class="form-label">Password Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password_baru" name="password_baru" required minlength="6">
                                <div class="form-text">Minimal 6 karakter.</div>
                            </div>
                            <div class="mb-3">
                                <label for="konfirmasi_password_baru" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="konfirmasi_password_baru" name="konfirmasi_password_baru" required>
                            </div>
                            <button type="submit" name="ganti_password" class="btn btn-danger"><i class="fas fa-lock"></i> Ganti Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php $conn->close(); ?>