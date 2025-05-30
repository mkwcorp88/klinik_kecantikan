<?php
// Memulai session jika belum dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php'; // Untuk koneksi database dan variabel global

// Ambil layanan unggulan (misalnya 9 layanan untuk SwiperJS, yang aktif)
$query_layanan_promo = "SELECT id_layanan, nama_layanan, deskripsi_singkat, gambar_layanan, harga FROM layanan WHERE status_layanan = 'aktif' ORDER BY id_layanan DESC LIMIT 9";
$result_layanan_promo = $conn->query($query_layanan_promo);

$layanan_unggulan_items = [];
if ($result_layanan_promo && $result_layanan_promo->num_rows > 0) {
    while($row = $result_layanan_promo->fetch_assoc()){
        $layanan_unggulan_items[] = $row;
    }
}

// Ambil beberapa testimoni untuk ditampilkan (yang sudah diapprove)
$query_testimoni_promo = "SELECT u.nama_lengkap, t.isi_testimoni, t.id_testimoni 
                          FROM testimoni t
                          JOIN user u ON t.id_user = u.id_user
                          WHERE t.status_testimoni = 'approved'
                          ORDER BY t.tanggal_testimoni DESC LIMIT 3";
$result_testimoni_promo = $conn->query($query_testimoni_promo);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Selamat Datang di <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya minimal yang sangat spesifik untuk index.php jika ada. */
        /* Sebagian besar gaya visual (hero, cta-enhanced, swiper) sebaiknya ada di style.css */
        body {
            background-color: #fdfdfe; /* Warna latar sedikit off-white */
        }
        .hero-section {
            background: url('images/banner_promo.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
        }
        .hero-section::before {
          content: "";
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background: rgba(0, 0, 0, 0.35);
          z-index: 1;
        }
        .hero-section .container {
          position: relative;
          z-index: 2;
        }
        .hero-section h1 {
            font-size: 3.8rem;
            font-weight: 700;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.6);
            margin-bottom: 0.75rem;
        }
        .hero-section p.lead {
            font-size: 1.3rem;
            font-weight: 300;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            margin-bottom: 2rem;
        }
        .card-img-top-custom {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        /* Swiper Layanan Unggulan */
        .swiper-container-layanan {
            padding-top: 10px;
            padding-bottom: 40px; /* Ruang untuk pagination jika masih ada dan di bawah */
            overflow: hidden;
        }
        .swiper-slide {
            display: flex;
            justify-content: center;
            align-items: stretch;
        }
        .swiper-slide .card {
            width: 100%;
        }
        .swiper-pagination {
            position: relative; /* Agar margin-top bisa diterapkan */
            bottom: auto; /* Hapus posisi absolut default jika ada */
            margin-top: 25px; /* Jarak antara kartu dan bullets pagination */
        }
        .swiper-pagination-bullet-active {
            background: #0d6efd;
        }

        /* Testimoni Pelanggan Ditingkatkan */
        #testimoni-promo {
            background-color: #ffffff; /* Latar putih bersih untuk section testimoni */
        }
        .testimonial-card-enhanced {
          border: none;
          box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); /* Shadow lebih menyebar */
          transition: transform 0.3s ease, box-shadow 0.3s ease;
          border-radius: 15px; /* Lebih rounded */
          background-color: #fff;
          overflow: hidden; /* Untuk quote icon */
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
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 3rem;
            color: #0d6efd; /* Warna primary */
            opacity: 0.15;
            position: absolute;
            top: 15px;
            left: 20px;
            z-index: -1;
        }
        .testimonial-avatar { /* Jika Anda menggunakannya */
          width: 70px;
          height: 70px;
          object-fit: cover;
          border: 3px solid #fff;
          box-shadow: 0 2px 8px rgba(0,0,0,0.15);
          margin-top: -35px;
          position: relative;
          z-index: 3; /* Di atas card body agar bisa keluar */
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
        #testimoni-promo .lead-testimonial { /* Subheading untuk section testimoni */
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }


        /* Footer (Gaya sudah ada di style.css, ini hanya jika perlu override) */
        .footer-enhanced {
            background-color: #2c3e50;
            color: #bdc3c7;
            padding-top: 4rem;
            padding-bottom: 2rem;
        }
        .footer-enhanced h5 {
            color: #fff;
            margin-bottom: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .footer-enhanced p, .footer-enhanced ul li {
            color: #bdc3c7;
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
        }
         .footer-enhanced ul {
            list-style: none;
            padding-left: 0;
        }
        .footer-enhanced ul li a:hover {
            color: #fff;
            text-decoration: underline;
        }
        .footer-social-icons a {
            color: #bdc3c7;
            font-size: 1.5rem;
            margin-right: 1rem;
            transition: color 0.3s ease;
        }
        .footer-social-icons a:last-child {
            margin-right: 0;
        }
        .footer-social-icons a:hover {
            color: #fff;
        }
        .footer-bottom {
            border-top: 1px solid #3a506b;
            padding-top: 1.5rem;
            margin-top: 2rem;
        }
        .footer-info .logo span {
            font-size: 1.5rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

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
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>" aria-current="page" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" href="layanan_tampil.php">Layanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" href="testimoni_tampil.php">Testimoni Publik</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'order.php') echo 'active'; ?>" href="order.php">Order Sekarang</a>
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
                        <li class="nav-item">
                            <a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'login.php') echo 'active'; ?>" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary ms-lg-2 <?php if(basename($_SERVER['PHP_SELF']) == 'daftar.php') echo 'active'; ?>" href="daftar.php">Daftar Member</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container">
            <h1 class="text-white">Temukan Kecantikan Sejatimu Bersama Kami</h1>
            <p class="lead mb-4">Dapatkan perawatan eksklusif dari para ahli kami untuk kulit sehat, bercahaya, dan penampilan yang memukau.</p>
            <a href="layanan_tampil.php" class="btn btn-lg btn-light me-md-2 mb-2 mb-md-0 px-4 py-3 shadow-sm"><i class="fas fa-list me-2"></i> Lihat Semua Layanan</a>
            <a href="order.php" class="btn btn-lg btn-primary px-4 py-3 shadow-sm"><i class="fas fa-calendar-check me-2"></i> Buat Janji Sekarang</a>
        </div>
    </header>

    <main class="container mt-5 mb-5 flex-grow-1">

        <section id="tentang" class="my-5 py-4">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 class="section-title text-start mb-3">Selamat Datang di <span class="text-primary"><?php echo NAMA_KLINIK; ?></span></h2>
                    <p class="text-secondary">Kami adalah klinik kecantikan terpercaya yang didedikasikan untuk memberikan Anda pengalaman perawatan diri yang luar biasa dan hasil yang memuaskan. Dengan teknologi terkini dan tim profesional yang berpengalaman, kami siap membantu Anda mencapai penampilan terbaik.</p>
                    <p class="text-secondary">Visi kami adalah menjadi pilihan utama untuk solusi kecantikan dan kesehatan kulit, dengan selalu mengedepankan kualitas layanan, keamanan prosedur, dan kepuasan setiap pelanggan.</p>
                    <a href="#layanan-unggulan" class="btn btn-outline-primary mt-3 px-4"><i class="fas fa-arrow-down me-2"></i> Jelajahi Layanan Unggulan</a>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="images/gambar_klinik_interior.jpg" class="img-fluid rounded shadow-lg" alt="Interior Klinik Kecantikan <?php echo NAMA_KLINIK; ?>">
                </div>
            </div>
        </section>
        <hr class="my-5">

        <section id="layanan-unggulan" class="my-5 py-4">
            <h2 class="section-title">Layanan Unggulan Pilihan Kami</h2>
            <?php if (count($layanan_unggulan_items) > 0): ?>
                <div class="swiper swiper-container-layanan">
                    <div class="swiper-wrapper">
                        <?php foreach ($layanan_unggulan_items as $layanan): ?>
                        <div class="swiper-slide px-2 h-auto">
                            <div class="card h-100 shadow-sm">
                                <img src="images/<?php echo htmlspecialchars($layanan['gambar_layanan'] ? $layanan['gambar_layanan'] : 'default_layanan.jpg'); ?>" class="card-img-top-custom" alt="<?php echo htmlspecialchars($layanan['nama_layanan']); ?>">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title text-primary"><?php echo htmlspecialchars($layanan['nama_layanan']); ?></h5>
                                    <p class="card-text small text-secondary flex-grow-1"><?php echo htmlspecialchars($layanan['deskripsi_singkat']); ?></p>
                                    <p class="card-text fs-5 fw-bold text-success">Rp <?php echo number_format($layanan['harga'], 0, ',', '.'); ?></p>
                                    <a href="order.php?id_layanan=<?php echo $layanan['id_layanan']; ?>" class="btn btn-primary mt-auto w-100"><i class="fas fa-shopping-cart me-2"></i> Pesan Sekarang</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            <?php else: ?>
                <div class="col">
                    <p class="text-center alert alert-light">Belum ada layanan unggulan yang ditampilkan.</p>
                </div>
            <?php endif; ?>
            <div class="text-center mt-2">
                <a href="layanan_tampil.php" class="btn btn-outline-secondary btn-lg px-5">Lihat Semua Layanan</a>
            </div>
        </section>
        <hr class="my-5">

        
        <section id="testimoni-promo" class="mt-3 pt-3 mb-5 pb-5">
            <div class="container">
                <h2 class="section-title">Apa Kata Pelanggan Setia Kami?</h2>
                <p class="text-center lead-testimonial">Dengarkan langsung pengalaman mereka yang telah mempercayakan perawatannya kepada kami.</p>
                <div class="row">
                    <?php if ($result_testimoni_promo && $result_testimoni_promo->num_rows > 0): ?>
                        <?php $delay_anim = 0; ?>
                        <?php while($testi = $result_testimoni_promo->fetch_assoc()): ?>
                        <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="<?php echo $delay_anim; $delay_anim+=100; ?>">
                            <div class="card testimonial-card-enhanced w-100">
                                <div class="card-body text-center">
                                    <?php /* Placeholder untuk avatar
                                    <img src="images/avatars/default_avatar.png" alt="Foto <?php echo htmlspecialchars($testi['nama_lengkap']); ?>" class="testimonial-avatar rounded-circle mb-3 img-thumbnail">
                                    */ ?>
                                    <p class="card-text fst-italic text-secondary mt-3">"<?php echo nl2br(htmlspecialchars($testi['isi_testimoni'])); ?>"</p>
                                </div>
                                <div class="card-footer text-center bg-transparent border-top-0 pt-0"> <?php /* bg-transparent dan border-top-0 */ ?>
                                    <h6 class="testimonial-name mb-0 text-primary"><?php echo htmlspecialchars($testi['nama_lengkap']); ?></h6>
                                    <small class="text-muted">Pelanggan Terverifikasi</small>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col">
                            <p class="text-center alert alert-light">Belum ada testimoni yang bisa ditampilkan.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="testimoni_tampil.php" class="btn btn-outline-primary btn-lg px-5">Lihat Semua Testimoni</a>
                </div>
            </div>
        </section>

        <section id="cta-enhanced" class="py-5 text-white" style="background: radial-gradient(circle, rgba(101,78,163,1) 0%, rgb(151, 160, 243) 100%);">
            <div class="container text-center">
                <i class="fas fa-spa fa-3x mb-3 text-white opacity-75"></i>
                <h2 class="display-5 fw-bold mb-3">Siap Tampil Memukau?</h2>
                <p class="lead mb-4 px-lg-5">Jangan tunda lagi! Kami di <?php echo NAMA_KLINIK; ?> siap membantu Anda meraih versi terbaik diri Anda. <br class="d-none d-md-block">Daftar sebagai member untuk penawaran eksklusif atau buat janji treatment Anda hari ini.</p>
                
                <div class="row justify-content-center mb-5 gy-3">
                    <div class="col-md-auto why-us-item">
                        <div class="icon mb-2"><i class="fas fa-user-md fa-2x text-white-50"></i></div>
                        <h6 class="text-white fw-bold">Tenaga Ahli Profesional</h6>
                    </div>
                    <div class="col-md-auto why-us-item">
                        <div class="icon mb-2"><i class="fas fa-award fa-2x text-white-50"></i></div>
                        <h6 class="text-white fw-bold">Kualitas Terjamin</h6>
                    </div>
                    <div class="col-md-auto why-us-item">
                        <div class="icon mb-2"><i class="fas fa-hand-holding-heart fa-2x text-white-50"></i></div>
                        <h6 class="text-white fw-bold">Pelayanan Prima</h6>
                    </div>
                </div>

                <a href="daftar.php" class="btn btn-light btn-lg me-md-3 mb-3 mb-md-0 px-5 py-3 shadow-sm"><i class="fas fa-user-plus me-2"></i> Daftar Member</a>
                <a href="order.php" class="btn btn-outline-light btn-lg px-5 py-3 shadow-sm"><i class="fas fa-calendar-check me-2"></i> Buat Janji</a>
                <p class="mt-4 small opacity-75">Dapatkan konsultasi gratis untuk member baru!</p>
            </div>
        </section>
    </main>

    <footer class="footer-enhanced">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-5 col-md-12 footer-info mb-4 mb-lg-0 text-center text-lg-start">
                    <a href="index.php" class="logo d-flex align-items-center justify-content-center justify-content-lg-start mb-3">
                        <img src="images/logo.png" alt="Logo Klinik <?php echo NAMA_KLINIK; ?>" height="45">
                        <span class="h4 ms-2 text-white fw-bold"><?php echo NAMA_KLINIK; ?></span>
                    </a>
                    <p class="pe-lg-3 small">Solusi terpercaya untuk kecantikan dan kesehatan kulit Anda.</p>
                    <div class="footer-social-icons mt-2">
                        <a href="#" title="Facebook <?php echo NAMA_KLINIK; ?>" class="me-2"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Instagram <?php echo NAMA_KLINIK; ?>" class="me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="Twitter <?php echo NAMA_KLINIK; ?>" class="me-2"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="WhatsApp <?php echo NAMA_KLINIK; ?>"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 footer-contact mb-4 mb-lg-0 text-center text-md-start">
                    <h5>Hubungi Kami</h5>
                    <p>
                        <i class="fas fa-map-marker-alt me-2 text-white-50"></i>Jl. Cantik Sejahtera No. 123<br>
                        <span class="ms-4">Yogyakarta, Indonesia 55281</span><br>
                        <i class="fas fa-phone me-2 text-white-50"></i>+62 274 123456<br>
                        <i class="fas fa-envelope me-2 text-white-50"></i>info@klinikkecantikan.com<br>
                    </p>
                </div>

                <div class="col-lg-4 col-md-6 footer-hours text-center text-md-start">
                    <h5>Jam Layanan</h5>
                    <ul class="list-unstyled">
                        <li><strong>Senin - Jumat :</strong> 09:00 - 20:00 WIB</li>
                        <li><strong>Sabtu :</strong> 09:00 - 18:00 WIB</li>
                        <li><strong>Minggu & Libur Nasional :</strong> Tutup</li>
                    </ul>
                </div>

            </div>
        </div>
        <div class="container footer-bottom text-center">
            <p class="mb-0 small">&copy; Hak Cipta <?php echo date("Y"); ?> <strong><span><?php echo NAMA_KLINIK; ?></span></strong>. All Rights Reserved.</p>
            <p class="mb-0 small">
                <a href="#" class="text-white-50 me-2">Kebijakan Privasi</a> |
                <a href="#" class="text-white-50 ms-2">Syarat & Ketentuan</a>
            </p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="js/script.js"></script>
    <?php /*
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
      if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
        });
      }
    </script>
    */ ?>
</body>
</html>
<?php
if(isset($result_layanan_promo) && $result_layanan_promo) $result_layanan_promo->close();
if(isset($result_testimoni_promo) && $result_testimoni_promo) $result_testimoni_promo->close();
$conn->close();
?>