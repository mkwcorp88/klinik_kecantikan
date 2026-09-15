<?php
require_once 'config.php'; // Session sudah dimulai di config.php

drw_require_member('Silakan masuk untuk memberikan atau mengelola testimoni.', 'testimoni_buat.php');

$errors_create = []; // Errors untuk form pembuatan testimoni
$message_manage = ''; // Pesan untuk manajemen testimoni (hapus)
$message_manage_type = ''; // 'success' atau 'danger' untuk pesan manajemen

$id_user = $_SESSION['user_id'];
$isi_testimoni_form = ''; // Untuk repopulate form jika ada error
$csrfToken = drw_csrf_token();

// Handle Penghapusan Testimoni Milik User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_my_testimoni') {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $_SESSION['message_manage_error'] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    } elseif (isset($_POST['id_testimoni_to_delete'])) {
        $id_testimoni_delete = intval($_POST['id_testimoni_to_delete']);

        $stmt_delete_my = $conn->prepare("DELETE FROM testimoni WHERE id_testimoni = ? AND id_user = ?");
        $stmt_delete_my->bind_param("ii", $id_testimoni_delete, $id_user);
        if ($stmt_delete_my->execute()) {
            if ($stmt_delete_my->affected_rows > 0) {
                $_SESSION['message_manage_success'] = "Testimoni Anda berhasil dihapus.";
            } else {
                $_SESSION['message_manage_error'] = "Gagal menghapus testimoni. Testimoni tidak ditemukan atau bukan milik Anda.";
            }
        } else {
            $_SESSION['message_manage_error'] = "Terjadi kesalahan saat menghapus testimoni.";
        }
        $stmt_delete_my->close();
    }
    header("Location: testimoni_buat.php"); // Redirect untuk refresh dan menghindari resubmit
    exit();
}

// Handle Pembuatan Testimoni Baru
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_new_testimoni'])) {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors_create[] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    }
    $isi_testimoni_form = trim((string) ($_POST['isi_testimoni'] ?? ''));

    if (empty($isi_testimoni_form)) {
        $errors_create[] = "Testimoni tidak boleh kosong.";
    } elseif (strlen($isi_testimoni_form) < 10) {
        $errors_create[] = "Testimoni terlalu pendek, minimal 10 karakter.";
    }

    if (empty($errors_create)) {
        $stmt_create = $conn->prepare("INSERT INTO testimoni (id_user, isi_testimoni, status_testimoni) VALUES (?, ?, 'pending')");
        $stmt_create->bind_param("is", $id_user, $isi_testimoni_form);

        if ($stmt_create->execute()) {
            $_SESSION['message_create_success'] = "Terima kasih! Testimoni baru Anda telah dikirim dan akan ditinjau oleh admin.";
            header("Location: testimoni_buat.php"); // Redirect untuk refresh
            exit();
        } else {
            $errors_create[] = "Terjadi kesalahan saat mengirim testimoni. Silakan coba lagi.";
        }
        $stmt_create->close();
    }
}

// Ambil semua testimoni milik pengguna yang sedang login
$my_testimonials = [];
$stmt_my_testi = $conn->prepare("SELECT id_testimoni, isi_testimoni, tanggal_testimoni, status_testimoni FROM testimoni WHERE id_user = ? ORDER BY tanggal_testimoni DESC");
$stmt_my_testi->bind_param("i", $id_user);
$stmt_my_testi->execute();
$result_my_testi = $stmt_my_testi->get_result();
while ($row = $result_my_testi->fetch_assoc()) {
    $my_testimonials[] = $row;
}
$stmt_my_testi->close();

// Ambil pesan dari session dan hapus agar tidak tampil lagi
if (isset($_SESSION['message_create_success'])) {
    $message_create_success = $_SESSION['message_create_success'];
    unset($_SESSION['message_create_success']);
}
if (isset($_SESSION['message_manage_success'])) {
    $message_manage = $_SESSION['message_manage_success'];
    $message_manage_type = 'success';
    unset($_SESSION['message_manage_success']);
}
if (isset($_SESSION['message_manage_error'])) {
    $message_manage = $_SESSION['message_manage_error'];
    $message_manage_type = 'danger';
    unset($_SESSION['message_manage_error']);
}
if (isset($_SESSION['error_message_redirect'])) { // Pesan error dari redirect login
    $message_redirect_error = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}

$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat & Kelola Testimoni - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Raleway:wght@400;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Anda bisa memindahkan gaya ini ke style.css jika belum */
        .card-testimonial-item .card-footer {
            background-color: #f8f9fa;
        }
    </style>
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
                                <i class="fas fa-user-circle"></i> Halo, <?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?>
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
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <?php if (isset($message_redirect_error)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message_redirect_error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-5">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-plus-circle"></i> Bagikan Pengalaman Anda</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors_create)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors_create as $error): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($message_create_success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($message_create_success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form action="testimoni_buat.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <div class="mb-3">
                                <label for="isi_testimoni" class="form-label">Testimoni Anda <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="isi_testimoni" name="isi_testimoni" rows="5" required minlength="10" placeholder="Ceritakan pengalaman Anda setelah menggunakan layanan kami..."><?php echo htmlspecialchars($isi_testimoni_form); ?></textarea>
                                <div class="form-text">Minimal 10 karakter. Testimoni Anda akan ditinjau admin sebelum ditampilkan untuk publik.</div>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="submit_new_testimoni" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Testimoni</button>
                            </div>
                        </form>
                    </div>
                </div>

                <hr class="my-4">

                <h4 class="mb-3"><i class="fas fa-list-alt"></i> Riwayat Testimoni Saya</h4>
                <?php if (!empty($message_manage)): ?>
                    <div class="alert alert-<?php echo $message_manage_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message_manage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($my_testimonials)): ?>
                    <?php foreach ($my_testimonials as $testi): ?>
                        <div class="card mb-3 shadow-sm card-testimonial-item">
                            <div class="card-body">
                                <p class="card-text fst-italic">"<?php echo nl2br(htmlspecialchars($testi['isi_testimoni'])); ?>"</p>
                                <small class="text-muted">
                                    Dikirim pada: <?php echo date('d M Y, H:i', strtotime($testi['tanggal_testimoni'])); ?>
                                </small>
                            </div>
                            <div class="card-footer d-flex justify-content-between align-items-center py-2">
                                <span>Status: <span class="badge bg-<?php
                                        switch ($testi['status_testimoni']) {
                                            case 'approved': echo 'success'; break;
                                            case 'pending': echo 'warning text-dark'; break;
                                            case 'rejected': echo 'danger'; break;
                                            default: echo 'secondary';
                                        }
                                    ?>">
                                        <?php echo ucfirst(htmlspecialchars($testi['status_testimoni'])); ?>
                                    </span>
                                </span>
                                <form method="POST" action="testimoni_buat.php" onsubmit="return confirm('Anda yakin ingin menghapus testimoni ini?');" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete_my_testimoni">
                                    <input type="hidden" name="id_testimoni_to_delete" value="<?php echo $testi['id_testimoni']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-light text-center" role="alert">
                        Anda belum pernah mengirimkan testimoni.
                    </div>
                <?php endif; ?>
                <p class="mt-4 text-center"><a href="testimoni_tampil.php"><i class="fas fa-comments"></i> Lihat Semua Testimoni Publik</a></p>
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
