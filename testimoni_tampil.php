<?php
require_once 'config.php'; // Session sudah dimulai di config.php

$query_testimoni = "SELECT t.isi_testimoni, t.tanggal_testimoni, u.nama_lengkap
                    FROM testimoni t
                    JOIN user u ON t.id_user = u.id_user
                    WHERE t.status_testimoni = 'approved'
                    ORDER BY t.tanggal_testimoni DESC";
$result_testimoni = $conn->query($query_testimoni);

// Ambil pesan dari session jika ada
if (isset($_SESSION['testimoni_success'])) { // Dari testimoni_buat.php
    $success_message = $_SESSION['testimoni_success'];
    unset($_SESSION['testimoni_success']);
}
if (isset($_SESSION['testimoni_error'])) { // Dari testimoni_buat.php
    $error_message = $_SESSION['testimoni_error'];
    unset($_SESSION['testimoni_error']);
}
if (isset($_SESSION['error_message_redirect'])) { // Pesan error dari redirect login
    $error_message_redirect = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Testimoni Pelanggan - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya tambahan atau override spesifik untuk halaman ini jika perlu */
        /* Sebagian besar gaya visual (section-title, testimonial-card-enhanced, footer-enhanced) */
        /* diharapkan sudah ada di style.css */

        /* Contoh: Jika ingin section testimoni di halaman ini punya background berbeda */
        /*
        #testimoni-utama {
            background-color: #f8f9fa; // bg-light
            padding-top: 4rem;
            padding-bottom: 4rem;
        }
        */
        .lead-page-description { /* Untuk deskripsi di bawah judul halaman */
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
         /* Pastikan gaya untuk .testimonial-card-enhanced ada di style.css */
        /* Saya tambahkan di sini sebagai referensi jika belum dipindah ke style.css dari index.php */
        .testimonial-card-enhanced {
          border: none;
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
          transition: transform 0.3s ease, box-shadow 0.3s ease;
          border-radius: 15px;
          background-color: #fff;
          overflow: hidden;
          position: relative;
        }
        .testimonial-card-enhanced:hover {
          transform: translateY(-10px) scale(1.02);
          box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1);
        }
        .testimonial-card-enhanced .card-body {
          padding: 2rem 1.8rem 1.5rem 1.8rem;
          position: relative;
          z-index: 2;
        }
        .testimonial-card-enhanced .card-body::before { /* Quote icon */
            content: "\f10d"; /* FontAwesome ikon kutip kiri */
            font-family: "Font Awesome 6 Free"; /* Pastikan merujuk ke versi FA yang benar */
            font-weight: 900;
            font-size: 3rem;
            color: #0d6efd; /* Warna primary */
            opacity: 0.10; /* Lebih subtle */
            position: absolute;
            top: 15px;
            left: 20px;
            z-index: -1;
        }
        .testimonial-card-enhanced .card-footer {
          background-color: #f8f9fa;
          border-top: 1px solid #e9ecef;
          padding-top: 1rem;
          padding-bottom: 1rem;
        }
        .testimonial-name {
          font-weight: 600;
          color: #0d6efd;
        }

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
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" href="layanan_tampil.php">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" aria-current="page" href="testimoni_tampil.php">Testimoni Publik</a></li>
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

    <main class="container mt-5 mb-5 flex-grow-1" id="testimoni-utama"> <?php /* STICKY FOOTER & ID untuk styling section jika perlu */ ?>
        <div class="text-center">
            <h1 class="section-title">Testimoni Pelanggan Kami</h1>
            <p class="lead-page-description">Kami bangga dapat berbagi pengalaman positif dari para pelanggan yang telah mempercayakan perawatannya kepada <?php echo NAMA_KLINIK; ?>.</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show col-md-8 mx-auto" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show col-md-8 mx-auto" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message_redirect)): ?>
            <div class="alert alert-warning alert-dismissible fade show col-md-8 mx-auto" role="alert">
                <?php echo htmlspecialchars($error_message_redirect); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="text-center my-5">
            <a href="testimoni_buat.php" class="btn btn-lg btn-outline-primary px-5 py-3"><i class="fas fa-plus-circle me-2"></i> Bagikan atau Kelola Testimoni Anda</a>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center col-md-8 mx-auto my-5">
            Untuk memberikan testimoni, silakan <a href="testimoni_buat.php" class="alert-link">masuk dengan Google</a> terlebih dahulu.
        </div>
        <?php endif; ?>


        <?php if ($result_testimoni && $result_testimoni->num_rows > 0): ?>
            <div class="row">
                <?php $delay_anim = 0; ?>
                <?php while($testi = $result_testimoni->fetch_assoc()): ?>
                <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="<?php echo $delay_anim; $delay_anim+=100; ?>">
                    <div class="card testimonial-card-enhanced w-100">
                        <div class="card-body text-center">
                            <?php /* Jika ingin avatar
                            <img src="images/avatars/default_avatar.png" alt="Foto <?php echo htmlspecialchars($testi['nama_lengkap']); ?>" class="testimonial-avatar rounded-circle mb-3 img-thumbnail mx-auto d-block">
                            */ ?>
                            <p class="card-text fst-italic text-secondary mt-3">"<?php echo nl2br(htmlspecialchars($testi['isi_testimoni'])); ?>"</p>
                        </div>
                        <div class="card-footer text-center bg-transparent border-top-0 pt-0">
                            <h6 class="testimonial-name mb-1 text-primary"><?php echo htmlspecialchars($testi['nama_lengkap']); ?></h6>
                            <small class="text-muted">Pada <?php echo date('d F Y', strtotime($testi['tanggal_testimoni'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-light text-center mt-4" role="alert">
              Belum ada testimoni yang dapat ditampilkan saat ini.
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white text-center py-4"> <?php // mt-auto bisa dihapus jika body sudah d-flex flex-column min-vh-100 ?>
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
    <?php /* Jika Anda menggunakan AOS (Animate On Scroll)
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800, // values from 0 to 3000, with step 50ms
            once: true,    // whether animation should happen only once - while scrolling down
        });
      }
    </script>
    */ ?>
</body>
</html>
<?php
if(isset($result_testimoni) && $result_testimoni) $result_testimoni->close();
$conn->close();
?>
