<?php
require_once 'config.php'; // Session sudah dimulai di config.php

// Variabel untuk menyimpan query string jika ada id_layanan dari GET
$current_page_query_params = '';
if (isset($_GET['id_layanan'])) {
    $current_page_query_params = '?id_layanan=' . intval($_GET['id_layanan']);
}

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message_redirect'] = "Anda harus login terlebih dahulu untuk membuat pesanan.";
    // Simpan halaman tujuan dan parameter layanan jika ada
    $redirect_params = 'redirect_to_order=true';
    if (isset($_GET['id_layanan'])) {
        $redirect_params .= '&id_layanan_after_login=' . intval($_GET['id_layanan']);
    }
    header("Location: login.php?" . $redirect_params . "&pesan=belum_login"); // Menggunakan pesan=belum_login
    exit();
}

$errors = [];
$id_user = $_SESSION['user_id'];

// Ambil id_layanan dari GET, POST, atau session (setelah login redirect)
$selected_layanan_id = '';
if (isset($_GET['id_layanan'])) {
    $selected_layanan_id = intval($_GET['id_layanan']);
} elseif (isset($_POST['id_layanan'])) {
    $selected_layanan_id = intval($_POST['id_layanan']);
} elseif (isset($_SESSION['id_layanan_after_login'])) {
    $selected_layanan_id = intval($_SESSION['id_layanan_after_login']);
    unset($_SESSION['id_layanan_after_login']); // Hapus setelah digunakan
}

$tanggal_treatment = isset($_POST['tanggal_treatment']) ? $_POST['tanggal_treatment'] : '';
$catatan_tambahan = isset($_POST['catatan_tambahan']) ? trim($_POST['catatan_tambahan']) : '';

// Ambil daftar layanan yang aktif untuk dropdown
$query_all_layanan = "SELECT id_layanan, nama_layanan, harga FROM layanan WHERE status_layanan = 'aktif' ORDER BY nama_layanan ASC";
$result_all_layanan = $conn->query($query_all_layanan);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Repopulate selected_layanan_id from POST jika belum diset dari GET/session
    if (empty($selected_layanan_id) && isset($_POST['id_layanan'])) {
        $selected_layanan_id = intval($_POST['id_layanan']);
    }

    if (empty($selected_layanan_id)) {
        $errors[] = "Silakan pilih layanan.";
    }
    if (empty($tanggal_treatment)) {
        $errors[] = "Silakan pilih tanggal dan waktu treatment.";
    } else {
        $timezone = new DateTimeZone('Asia/Jakarta'); // Sesuaikan dengan zona waktu Anda
        $current_datetime = new DateTime("now", $timezone);
        try {
            $selected_datetime = new DateTime($tanggal_treatment, $timezone);
            if ($selected_datetime < $current_datetime) {
                $errors[] = "Tanggal dan waktu treatment tidak boleh di masa lalu.";
            }
        } catch (Exception $e) {
             $errors[] = "Format tanggal dan waktu treatment tidak valid.";
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO `order` (id_user, id_layanan, tanggal_treatment, catatan_tambahan, status_order) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->bind_param("iiss", $id_user, $selected_layanan_id, $tanggal_treatment, $catatan_tambahan);

        if ($stmt->execute()) {
            $_SESSION['order_success'] = "Pesanan Anda berhasil dibuat dan sedang menunggu konfirmasi. Cek status pesanan Anda di Riwayat Pesanan.";
            header("Location: riwayat_order.php");
            exit();
        } else {
            $errors[] = "Gagal membuat pesanan: Terjadi kesalahan pada server.";
        }
        $stmt->close();
    }
}

// Ambil pesan error dari redirect login jika ada
if (isset($_SESSION['error_message_redirect'])) {
    $error_message_redirect = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buat Pesanan Layanan - <?php echo NAMA_KLINIK; ?></title>
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
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" href="layanan_tampil.php">Layanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" href="testimoni_tampil.php">Testimoni Publik</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'order.php') echo 'active'; ?>" aria-current="page" href="order.php">Order Sekarang</a>
                    </li>
                     <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php
                                $user_pages = ['riwayat_order.php', 'testimoni_buat.php', 'profil.php'];
                                if (in_array(basename($_SERVER['PHP_SELF']), $user_pages)) echo 'active';
                            ?>" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                    <?php else: // Pengguna belum login, seharusnya sudah diredirect jika mengakses order.php ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-primary ms-lg-2" href="daftar.php">Daftar Member</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 mb-5 flex-grow-1"> <?php /* STICKY FOOTER */ ?>
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Form Pemesanan Layanan</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                         <?php if (isset($error_message_redirect)): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error_message_redirect); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>


                        <form action="order.php<?php echo htmlspecialchars($current_page_query_params); ?>" method="post">
                            <div class="mb-3">
                                <label for="id_layanan" class="form-label">Pilih Layanan <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_layanan" name="id_layanan" required>
                                    <option value="">-- Pilih Layanan --</option>
                                    <?php
                                    if ($result_all_layanan && $result_all_layanan->num_rows > 0) {
                                        while ($layanan_item = $result_all_layanan->fetch_assoc()) {
                                            $is_selected = ($layanan_item['id_layanan'] == $selected_layanan_id) ? 'selected' : '';
                                            echo "<option value='" . $layanan_item['id_layanan'] . "' " . $is_selected . ">" . htmlspecialchars($layanan_item['nama_layanan']) . " (Rp " . number_format($layanan_item['harga'], 0, ',', '.') . ")</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="tanggal_treatment" class="form-label">Tanggal & Waktu Treatment <span class="text-danger">*</span></label>
                                <?php
                                    // Set default DateTime ke zona waktu Jakarta untuk min attribute
                                    $now_jakarta_min = new DateTime("now", new DateTimeZone('Asia/Jakarta'));
                                    $min_datetime = $now_jakarta_min->format('Y-m-d\TH:i');
                                ?>
                                <input type="datetime-local" class="form-control" id="tanggal_treatment" name="tanggal_treatment" value="<?php echo htmlspecialchars($tanggal_treatment); ?>" required min="<?php echo $min_datetime; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="catatan_tambahan" class="form-label">Catatan Tambahan (Opsional)</label>
                                <textarea class="form-control" id="catatan_tambahan" name="catatan_tambahan" rows="3"><?php echo htmlspecialchars($catatan_tambahan); ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Buat Pesanan</button>
                            </div>
                        </form>
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
<?php
if(isset($result_all_layanan) && $result_all_layanan) $result_all_layanan->close();
$conn->close();
?>