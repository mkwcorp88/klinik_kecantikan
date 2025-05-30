<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin"); // Arahkan ke login admin jika belum
    exit();
}

// Contoh data untuk dashboard (bisa diganti dengan query database)
$total_users_query = $conn->query("SELECT COUNT(*) as total FROM user");
$total_users = ($total_users_query && $total_users_query->num_rows > 0) ? $total_users_query->fetch_assoc()['total'] : 0;

$total_orders_query = $conn->query("SELECT COUNT(*) as total FROM `order`");
$total_orders = ($total_orders_query && $total_orders_query->num_rows > 0) ? $total_orders_query->fetch_assoc()['total'] : 0;

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;

$total_layanan_query = $conn->query("SELECT COUNT(*) as total FROM layanan");
$total_layanan = ($total_layanan_query && $total_layanan_query->num_rows > 0) ? $total_layanan_query->fetch_assoc()['total'] : 0;

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin - <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }
        .admin-sidebar {
            background-color: #343a40; /* Dark background */
            color: white;
            padding-top: 1rem;
            min-height: 100vh; /* Full height sidebar */
        }
        .admin-sidebar .nav-link {
            color: #adb5bd; /* Lighter text for links */
            padding: 0.75rem 1rem;
        }
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: white;
            background-color: #495057; /* Slightly lighter on hover/active */
        }
        .admin-sidebar .nav-link .fas {
            margin-right: 0.5rem;
        }
        .admin-content {
            flex-grow: 1;
            padding: 2rem;
        }
        .admin-header {
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-card {
            border-left-width: 5px;
            border-radius: .35rem;
        }
        .border-left-primary { border-left-color: #0d6efd !important; }
        .border-left-success { border-left-color: #198754 !important; }
        .border-left-info    { border-left-color: #0dcaf0 !important; }
        .border-left-warning { border-left-color: #ffc107 !important; }
    </style>
</head>
<body>
    <div class="d-flex">
        <nav class="admin-sidebar d-none d-md-block col-md-3 col-lg-2">
            <div class="sidebar-sticky">
                <h5 class="px-3 py-2 text-white"><?php echo NAMA_KLINIK; ?></h5>
                <ul class="nav flex-column">
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
                        <a class="nav-link" href="../logout.php">
                           <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="admin-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <header class="admin-header">
                <h1 class="h4 mb-0">Dashboard Admin</h1>
                <span class="text-muted">Selamat datang, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</span>
            </header>

            <main class="pt-3">
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Member</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Order</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_orders; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                     <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Layanan</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_layanan; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-concierge-bell fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Testimoni Pending</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_testimoni; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-comments fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    Selamat datang di halaman administrasi <?php echo NAMA_KLINIK; ?>.
                    Silakan gunakan menu di samping untuk mengelola konten website.
                </div>

                </main>
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>