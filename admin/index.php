<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();

// Ambil data dashboard. Semua query dipaksa mengikuti scope cabang admin.
$countQuery = static function (mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0);
};

$orderStatusMeta = [
    'pending' => ['label' => 'Pending', 'class' => 'warning', 'icon' => 'hourglass-half'],
    'confirmed' => ['label' => 'Dikonfirmasi', 'class' => 'info', 'icon' => 'calendar-check'],
    'completed' => ['label' => 'Selesai', 'class' => 'success', 'icon' => 'circle-check'],
    'cancelled' => ['label' => 'Batal', 'class' => 'danger', 'icon' => 'ban'],
];

$orderStatusCounts = array_fill_keys(array_keys($orderStatusMeta), 0);
$recentOrders = [];

if ($adminCabangId === null) {
    $total_users = $countQuery($conn, 'SELECT COUNT(*) AS total FROM user');
    $today_orders = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM `order`
         WHERE DATE(tanggal_treatment) = CURDATE()
           AND status_order IN ('pending', 'confirmed')"
    );
    $pending_affiliates = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM user
         WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'pending'"
    );
    $active_affiliates = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM user
         WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'aktif'"
    );
    $pending_withdrawals = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM affiliate_withdrawal WHERE status = 'pending'"
    );

    $stmt = $conn->prepare("SELECT status_order, COUNT(*) AS total FROM `order` GROUP BY status_order");
    $stmtRecent = $conn->prepare(
        "SELECT o.id_order, o.tanggal_treatment, o.status_order, o.tanggal_order_dibuat,
                u.nama_lengkap AS nama_user, l.nama_layanan, c.nama_cabang
         FROM `order` o
         JOIN user u ON u.id_user = o.id_user
         JOIN layanan l ON l.id_layanan = o.id_layanan
         LEFT JOIN cabang c ON c.id_cabang = o.id_cabang
         ORDER BY o.tanggal_order_dibuat DESC
         LIMIT 6"
    );
} else {
    $total_users = $countQuery(
        $conn,
        "SELECT COUNT(DISTINCT u.id_user) AS total
         FROM user u
         JOIN `order` o ON o.id_user = u.id_user
         WHERE o.id_cabang = ?",
        'i',
        [$adminCabangId]
    );
    $today_orders = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM `order`
         WHERE id_cabang = ?
           AND DATE(tanggal_treatment) = CURDATE()
           AND status_order IN ('pending', 'confirmed')",
        'i',
        [$adminCabangId]
    );
    $pending_affiliates = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM user
         WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'pending' AND id_cabang = ?",
        'i',
        [$adminCabangId]
    );
    $active_affiliates = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total FROM user
         WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'aktif' AND id_cabang = ?",
        'i',
        [$adminCabangId]
    );
    $pending_withdrawals = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM affiliate_withdrawal w
         JOIN user u ON u.id_user = w.id_user
         WHERE w.status = 'pending' AND u.id_cabang = ?",
        'i',
        [$adminCabangId]
    );

    $stmt = $conn->prepare("SELECT status_order, COUNT(*) AS total FROM `order` WHERE id_cabang = ? GROUP BY status_order");
    $stmt->bind_param('i', $adminCabangId);
    $stmtRecent = $conn->prepare(
        "SELECT o.id_order, o.tanggal_treatment, o.status_order, o.tanggal_order_dibuat,
                u.nama_lengkap AS nama_user, l.nama_layanan, c.nama_cabang
         FROM `order` o
         JOIN user u ON u.id_user = o.id_user
         JOIN layanan l ON l.id_layanan = o.id_layanan
         LEFT JOIN cabang c ON c.id_cabang = o.id_cabang
         WHERE o.id_cabang = ?
         ORDER BY o.tanggal_order_dibuat DESC
         LIMIT 6"
    );
    $stmtRecent->bind_param('i', $adminCabangId);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $status = (string) ($row['status_order'] ?? '');
    if (array_key_exists($status, $orderStatusCounts)) {
        $orderStatusCounts[$status] = (int) $row['total'];
    }
}
$stmt->close();

$total_orders = array_sum($orderStatusCounts);
$pending_orders = $orderStatusCounts['pending'];

$stmtRecent->execute();
$resultRecent = $stmtRecent->get_result();
while ($row = $resultRecent->fetch_assoc()) {
    $recentOrders[] = $row;
}
$stmtRecent->close();

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
    <link href="../css/admin-theme.css" rel="stylesheet">
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
                    <a class="nav-link" href="kelola_order.php">
                        <i class="fas fa-shopping-cart"></i> Kelola Order
                         <?php if ($pending_orders > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $pending_orders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kelola_afiliasi.php">
                        <i class="fas fa-handshake"></i> Afiliator
                        <?php if ($pending_affiliates > 0): ?>
                            <span class="badge bg-warning text-dark ms-1" title="Menunggu approval"><?php echo $pending_affiliates; ?></span>
                        <?php endif; ?>
                        <?php if ($pending_withdrawals > 0): ?>
                            <span class="badge bg-info text-dark ms-1" title="Penarikan pending"><?php echo $pending_withdrawals; ?></span>
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
                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-clinic-medical me-1"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <div class="dashboard-hero mb-4">
                    <div>
                        <div class="dashboard-eyebrow">Dashboard Operasional</div>
                        <h2 class="h3 mb-2">Halo, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></h2>
                        <p class="mb-0">Pantau booking, member, dan afiliasi untuk <?php echo htmlspecialchars($adminCabangNama); ?>.</p>
                    </div>
                    <div class="dashboard-hero-meta">
                        <span><i class="fas fa-calendar-day me-2"></i><?php echo date('d M Y'); ?></span>
                        <span><i class="fas fa-clinic-medical me-2"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-primary">
                            <div class="card-body">
                                <div class="text-xs text-primary">Total Member</div>
                                <div class="h3 text-gray-800"><?php echo number_format((int) $total_users, 0, ',', '.'); ?></div>
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
                                <div class="h3 text-gray-800"><?php echo number_format((int) $total_orders, 0, ',', '.'); ?></div>
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
                                <div class="text-xs text-info">Jadwal Hari Ini</div>
                                <div class="h3 text-gray-800"><?php echo number_format((int) $today_orders, 0, ',', '.'); ?></div>
                                <i class="fas fa-calendar-day stat-icon"></i>
                            </div>
                             <a href="kelola_order.php" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Jadwal</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card-enhanced stat-card-warning">
                            <div class="card-body">
                                <div class="text-xs text-warning">Afiliator Menunggu Approval</div>
                                <div class="h3 text-gray-800"><?php echo number_format((int) $pending_affiliates, 0, ',', '.'); ?></div>
                                <i class="fas fa-handshake stat-icon"></i>
                            </div>
                             <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=pending" class="card-footer d-flex align-items-center justify-content-between">
                                <span>Lihat Detail</span> <i class="fas fa-arrow-circle-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-xl-5">
                        <div class="card dashboard-panel h-100">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="h6 mb-0"><i class="fas fa-chart-pie me-2"></i>Status Booking</h3>
                                <span class="badge bg-light text-dark border"><?php echo number_format((int) $total_orders, 0, ',', '.'); ?> total</span>
                            </div>
                            <div class="card-body">
                                <?php foreach ($orderStatusMeta as $statusKey => $meta): ?>
                                    <?php $statusCount = (int) $orderStatusCounts[$statusKey]; ?>
                                    <?php $statusPercent = $total_orders > 0 ? min(100, round(($statusCount / $total_orders) * 100)) : 0; ?>
                                    <a class="dashboard-status-row" href="kelola_order.php?filter_status=<?php echo urlencode($statusKey); ?>">
                                        <span class="dashboard-status-label">
                                            <span class="status-icon status-<?php echo htmlspecialchars($meta['class']); ?>">
                                                <i class="fas fa-<?php echo htmlspecialchars($meta['icon']); ?>"></i>
                                            </span>
                                            <?php echo htmlspecialchars($meta['label']); ?>
                                        </span>
                                        <span class="dashboard-status-count"><?php echo number_format($statusCount, 0, ',', '.'); ?></span>
                                        <span class="dashboard-status-track">
                                            <span style="width: <?php echo $statusPercent; ?>%"></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-7">
                        <div class="card dashboard-panel h-100">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h3 class="h6 mb-0"><i class="fas fa-clock me-2"></i>Booking Terbaru</h3>
                                <a href="kelola_order.php" class="small fw-semibold">Lihat semua</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Pasien</th>
                                            <th>Layanan</th>
                                            <th>Jadwal</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($recentOrders === []): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">Belum ada booking untuk ditampilkan.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <?php $status = (string) ($order['status_order'] ?? 'pending'); ?>
                                                <?php $meta = $orderStatusMeta[$status] ?? $orderStatusMeta['pending']; ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo htmlspecialchars((string) $order['nama_user']); ?></div>
                                                        <small class="text-muted">#<?php echo (int) $order['id_order']; ?> &bull; <?php echo htmlspecialchars((string) ($order['nama_cabang'] ?? '-')); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string) $order['nama_layanan']); ?></td>
                                                    <td>
                                                        <span class="fw-semibold"><?php echo date('d M Y', strtotime((string) $order['tanggal_treatment'])); ?></span><br>
                                                        <small class="text-muted"><?php echo date('H:i', strtotime((string) $order['tanggal_treatment'])); ?> WIB</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge text-bg-<?php echo htmlspecialchars($meta['class']); ?>">
                                                            <?php echo htmlspecialchars($meta['label']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <h3 class="h5 mb-3 text-gray-700"><i class="fas fa-rocket me-2"></i>Aksi Cepat</h3>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_order.php?filter_status=pending" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-hourglass-half text-warning"></i>
                                <h6 class="card-title">Order Pending</h6>
                                <span class="dashboard-action-count"><?php echo number_format((int) $pending_orders, 0, ',', '.'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_afiliasi.php?tab=withdrawals&amp;wd_status=pending" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-money-check-alt text-success"></i>
                                <h6 class="card-title">Penarikan Afiliasi</h6>
                                <span class="dashboard-action-count"><?php echo number_format((int) $pending_withdrawals, 0, ',', '.'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=aktif" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-handshake text-info"></i>
                                <h6 class="card-title">Afiliator Aktif</h6>
                                <span class="dashboard-action-count"><?php echo number_format((int) $active_affiliates, 0, ',', '.'); ?></span>
                            </div>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <a href="kelola_user.php" class="card quick-action-card text-decoration-none text-dark shadow-sm">
                            <div class="card-body">
                                <i class="fas fa-users-cog text-secondary"></i>
                                <h6 class="card-title">Lihat Member</h6>
                                <span class="dashboard-action-count"><?php echo number_format((int) $total_users, 0, ',', '.'); ?></span>
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
