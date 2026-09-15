<?php
require_once 'config.php'; // Session sudah dimulai di config.php

$query_layanan = "SELECT id_layanan, nama_layanan, deskripsi_singkat, gambar_layanan, harga FROM layanan WHERE status_layanan = 'aktif' ORDER BY nama_layanan ASC";
$result_layanan = $conn->query($query_layanan);

// Ambil pesan sukses dari session jika ada (misalnya setelah user berhasil order)
if (isset($_SESSION['order_success'])) {
    $order_success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar Layanan - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya tambahan atau override spesifik untuk halaman ini jika perlu */
        /* Sebagian besar gaya visual (section-title, card, card-img-top-custom, footer-enhanced) */
        /* diharapkan sudah ada di style.css */

        .lead-page-description { /* Untuk deskripsi di bawah judul halaman */
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Card styling (pastikan konsisten dengan index.php, atau lebih baik di style.css) */
        .card-img-top-custom {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        /* .card {
            border: none;
            border-radius: 10px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: #fff;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        } */

        /* Footer (Jika belum ada di style.css) */
        .footer-enhanced {
            background-color: #2c3e50; color: #bdc3c7; padding-top: 4rem; padding-bottom: 2rem;
        }
        .footer-enhanced h5 {
            color: #fff; margin-bottom: 1.5rem; font-weight: 600; font-size: 1.1rem;
        }
        .footer-enhanced p, .footer-enhanced ul li {
            color: #bdc3c7; font-size: 0.9rem; margin-bottom: 0.6rem;
        }
        .footer-enhanced ul { list-style: none; padding-left: 0; }
        .footer-enhanced ul li a:hover { color: #fff; text-decoration: underline; }
        .footer-social-icons a {
            color: #bdc3c7; font-size: 1.5rem; margin-right: 1rem; transition: color 0.3s ease;
        }
        .footer-social-icons a:last-child { margin-right: 0; }
        .footer-social-icons a:hover { color: #fff; }
        .footer-bottom {
            border-top: 1px solid #3a506b; padding-top: 1.5rem; margin-top: 2rem;
        }
        .footer-info .logo span { font-size: 1.5rem; }
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
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" aria-current="page" href="layanan_tampil.php">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" href="testimoni_tampil.php">Testimoni Publik</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'order.php') echo 'active'; ?>" href="order.php">Order Sekarang</a></li>
                     <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php
                                $user_pages = ['riwayat_order.php', 'testimoni_buat.php', 'profil.php'];
                                if (in_array(basename($_SERVER['PHP_SELF']), $user_pages)) echo 'active';
                            ?>" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'login.php') echo 'active'; ?>" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 mb-5 flex-grow-1"> <?php /* STICKY FOOTER */ ?>
        <div class="text-center">
            <h1 class="section-title">Pilihan Layanan Terbaik Kami</h1>
            <p class="lead-page-description">Temukan berbagai perawatan kecantikan dan kesehatan kulit yang dirancang khusus untuk memenuhi kebutuhan Anda, ditangani oleh para profesional berpengalaman.</p>
        </div>

        <?php if (isset($order_success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show col-md-8 mx-auto" role="alert">
                <?php echo htmlspecialchars($order_success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($result_layanan && $result_layanan->num_rows > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
                <?php while($layanan = $result_layanan->fetch_assoc()): ?>
                <div class="col d-flex align-items-stretch">
                    <div class="card h-100 shadow-sm"> <?php /* Menggunakan gaya kartu umum */ ?>
                        <img src="images/<?php echo htmlspecialchars($layanan['gambar_layanan'] ? $layanan['gambar_layanan'] : 'default_layanan.jpg'); ?>" class="card-img-top-custom" alt="<?php echo htmlspecialchars($layanan['nama_layanan']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title text-primary"><?php echo htmlspecialchars($layanan['nama_layanan']); ?></h5>
                            <p class="card-text small text-secondary flex-grow-1"><?php echo nl2br(htmlspecialchars($layanan['deskripsi_singkat'])); ?></p>
                            <p class="card-text fs-5 fw-bold text-success"><?php echo (int) $layanan['harga'] > 0 ? 'Rp ' . number_format($layanan['harga'], 0, ',', '.') : 'Konsultasi gratis'; ?></p>
                            <a href="order.php?id_layanan=<?php echo $layanan['id_layanan']; ?>" class="btn btn-primary mt-auto w-100"><i class="fas fa-shopping-cart me-2"></i> Pesan Sekarang</a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-4">Belum ada layanan yang tersedia saat ini. Silakan kembali lagi nanti.</div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white text-center py-4"> <?php // mt-auto bisa dihapus jika body sudah d-flex flex-column min-vh-100 ?>
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php
if(isset($result_layanan) && $result_layanan) $result_layanan->close();
$conn->close();
?>
