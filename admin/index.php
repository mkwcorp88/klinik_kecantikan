<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin");
    exit();
}

// Ambil data untuk statistik dashboard
$total_users_query = $conn->query("SELECT COUNT(*) as total FROM user");
$total_users = ($total_users_query && $total_users_query->num_rows > 0) ? $total_users_query->fetch_assoc()['total'] : 0;
if($total_users_query) $total_users_query->close();

$total_orders_query = $conn->query("SELECT COUNT(*) as total FROM `order`");
$total_orders = ($total_orders_query && $total_orders_query->num_rows > 0) ? $total_orders_query->fetch_assoc()['total'] : 0;
if($total_orders_query) $total_orders_query->close();

$pending_orders_query = $conn->query("SELECT COUNT(*) as total FROM `order` WHERE status_order = 'pending'");
$pending_orders = ($pending_orders_query && $pending_orders_query->num_rows > 0) ? $pending_orders_query->fetch_assoc()['total'] : 0;
if($pending_orders_query) $pending_orders_query->close();

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;
// $pending_testimoni_query->close(); // Sudah ditutup di file sidebar/navigasi sebelumnya jika ini duplikat

$total_layanan_query = $conn->query("SELECT COUNT(*) as total FROM layanan WHERE status_layanan = 'aktif'");
$total_layanan_aktif = ($total_layanan_query && $total_layanan_query->num_rows > 0) ? $total_layanan_query->fetch_assoc()['total'] : 0;
if($total_layanan_query) $total_layanan_query->close();

$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin - <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya Admin Dashboard Spesifik */
        body {
            background-color: #f4f7f6; /* Latar belakang admin area sedikit berbeda */
        }
        .admin-sidebar {
            background-color: #2c3e50; /* Warna sidebar lebih gelap */
            color: white;
            padding-top: 0; /* Hapus padding atas agar logo sejajar */
            min-height: 100vh;
            position: fixed; /* Sidebar tetap */
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            width: 250px; /* Lebar sidebar */
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .admin-sidebar .sidebar-brand {
            padding: 1rem 1.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
            text-align: center;
            border-bottom: 1px solid #3a506b;
        }
        .admin-sidebar .sidebar-brand img {
            max-height: 35px;
            margin-right: 10px;
        }
        .admin-sidebar .nav-link {
            color: #bdc3c7; /* Warna link sidebar */
            padding: 0.9rem 1.5rem;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .admin-sidebar .nav-link:hover {
            color: #fff;
            background-color: #34495e;
            border-left-color: #1abc9c; /* Aksen warna hover */
        }
        .admin-sidebar .nav-link.active {
            color: #fff;
            background-color: #1abc9c; /* Warna aktif lebih menonjol */
            border-left-color: #fff;
        }
        .admin-sidebar .nav-link .fas {
            margin-right: 0.8rem;
            width: 20px; /* Agar ikon rapi */
            text-align: center;
        }
        .admin-sidebar .sidebar-heading { /* Jika ada heading di sidebar */
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: .05rem;
            margin-top: 1rem;
        }
        .admin-sidebar hr.text-secondary {
            border-top: 1px solid #3a506b;
        }

        .admin-main-content {
            margin-left: 250px; /* Sesuaikan dengan lebar sidebar */
            padding: 0; /* Header akan menangani padding */
            width: calc(100% - 250px);
        }

        .admin-header {
            background-color: #fff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky; /* Header tetap di atas saat scroll konten */
            top: 0;
            z-index: 99;
        }
        .admin-header .breadcrumb {
            margin-bottom: 0;
        }
        .admin-header .user-info img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .admin-content-area {
            padding: 2rem; /* Padding untuk konten di bawah header */
        }

        /* Kartu Statistik yang Ditingkatkan */
        .stat-card-enhanced {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden; /* Untuk efek background ikon */
            position: relative;
        }
        .stat-card-enhanced:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        .stat-card-enhanced .card-body {
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }
        .stat-card-enhanced .stat-icon {
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 4rem; /* Ukuran ikon besar */
            opacity: 0.15; /* Ikon transparan di latar */
            z-index: 1;
            transform: rotate(-15deg);
        }
        .stat-card-enhanced .text-xs {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        .stat-card-enhanced .h3 { /* Ukuran angka statistik */
            font-weight: 700;
            margin-bottom: 0;
        }
        .stat-card-enhanced .card-footer {
            background-color: rgba(0,0,0,0.03);
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85rem;
        }
        .stat-card-enhanced .card-footer a {
            text-decoration: none;
            color: inherit;
            opacity: 0.8;
        }
        .stat-card-enhanced .card-footer a:hover {
            opacity: 1;
        }

        /* Warna aksen untuk kartu statistik */
        .stat-card-primary { border-left: 5px solid #4e73df; }
        .stat-card-success { border-left: 5px solid #1cc88a; }
        .stat-card-info    { border-left: 5px solid #36b9cc; }
        .stat-card-warning { border-left: 5px solid #f6c23e; }

        /* Quick Actions Section */
        .quick-action-card {
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .quick-action-card .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }
        .quick-action-card i.fas {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }
        .quick-action-card .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        .quick-action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            text-decoration: none;
        }
        
        @media (max-width: 767px) { /* Mobile adjustments */
            .admin-sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: none;
            }
            .admin-main-content {
                margin-left: 0;
                width: 100%;
            }
            .admin-header {
                position: static; /* Header tidak sticky di mobile jika sidebar di atas */
            }
            /* Navbar toggler untuk sidebar di mobile jika diperlukan */
        }

    </style>
</head>
<body>
    <div class="d-flex">
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <img src="../images/logo.png" alt="Logo"> <?php echo NAMA_KLINIK; ?>
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola_user.php">
                        <i class="fas fa-users"></i> Kelola Member
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola_layanan.php">
                        <i class="fas fa-concierge-bell"></i> Kelola Layanan
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola_order.php">
                        <i class="fas fa-shopping-cart"></i> Kelola Order
                         <?php if ($pending_orders > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $pending_orders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola_testimoni.php">
                        <i class="fas fa-comment-dots"></i> Kelola Testimoni
                        <?php if ($pending_testimoni > 0): ?>
                            <span class="badge bg-warning ms-1"><?php echo $pending_testimoni; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <hr class="text-secondary">
            <ul class="nav flex-column">
                 <li class="nav-item">
                    <a class="nav-link" href="../index.php" target="_blank">
                        <i class="fas fa-globe"></i> Lihat Website
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout_admin.php">
                       <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </nav>

        <div class="admin-main-content">
            <header class="admin-header">
                <div>
                    <h1 class="h4 mb-0 text-gray-800">Dashboard</h1>
                    <?php /* Breadcrumb opsional
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                        </ol>
                    </nav> */ ?>
                </div>
                <div class="user-info d-flex align-items-center">
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <div class="alert alert-primary shadow-sm" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-mug-hot me-2"></i>Selamat Datang, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</h4>
                    <p>Ini adalah pusat kendali untuk website <?php echo NAMA_KLINIK; ?> Anda. Gunakan menu navigasi di samping untuk mengelola berbagai aspek website.</p>
                    <hr>
                    <p class="mb-0">Semoga hari Anda menyenangkan dalam mengelola klinik!</p>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-primary">
                            <div class="card-body">
                                <div class="text-xs text-primary">Total Member</div>
                                <div class="h3 text-gray-800"><?php echo $total_users; ?></div>
                                <i class="fas fa-users stat-icon"></i>
                            </div>
                            <a href="kelola_user.php" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Detail</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-success">
                            <div class="card-body">
                                <div class="text-xs text-success">Total Order</div>
                                <div class="h3 text-gray-800"><?php echo $total_orders; ?></div>
                                <i class="fas fa-shopping-cart stat-icon"></i>
                            </div>
                             <a href="kelola_order.php" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Detail</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                     <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-info">
                            <div class="card-body">
                                <div class="text-xs text-info">Layanan Aktif</div>
                                <div class="h3 text-gray-800"><?php echo $total_layanan_aktif; ?></div>
                                <i class="fas fa-concierge-bell stat-icon"></i>
                            </div>
                            <a href="kelola_layanan.php" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Detail</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-warning">
                            <div class="card-body">
                                <div class="text-xs text-warning">Testimoni Pending</div>
                                <div class="h3 text-gray-800"><?php echo $pending_testimoni; ?></div>
                                <i class="fas fa-comments stat-icon"></i>
                            </div>
                             <a href="kelola_testimoni.php?filter_status=pending" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Detail</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <h3 class="h5 mb-3 text-gray-700"><i class="fas fa-rocket me-2"></i>Aksi Cepat</h3>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_layanan.php#formLayanan" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-plus-circle text-primary"></i>
                                <h6 class="card-title">Tambah Layanan</h6>
                            </div>
                        </a>
                    </div>
                     <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_order.php?filter_status=pending" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-hourglass-half text-warning"></i>
                                <h6 class="card-title">Order Pending (<?php echo $pending_orders; ?>)</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_testimoni.php?filter_status=pending" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-comment-medical text-info"></i>
                                <h6 class="card-title">Moderasi Testimoni (<?php echo $pending_testimoni; ?>)</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_user.php" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-users-cog text-secondary"></i>
                                <h6 class="card-title">Lihat Member</h6>
                            </div>
                        </a>
                    </div>
                </div>
                
                </div> <footer class="py-4 bg-light mt-auto">
                <div class="container-fluid px-4">
                    <div class="d-flex align-items-center justify-content-between small">
                        <div class="text-muted">Hak Cipta &copy; <?php echo NAMA_KLINIK; ?> <?php echo date("Y"); ?></div>
                        <div>
                            <a href="#">Kebijakan Privasi</a>
                            &middot;
                            <a href="#">Syarat &amp; Ketentuan</a>
                        </div>
                    </div>
                </div>
            </footer>

        </div> </div> <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
    <?php /* Jika ada JS spesifik admin
    <script src="js/admin-scripts.js"></script>
    */ ?>
</body>
</html>