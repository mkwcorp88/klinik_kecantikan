<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();
$csrfToken = drw_csrf_token();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        drw_flash('danger', 'Sesi formulir telah berakhir. Silakan muat ulang halaman.');
        header('Location: kelola_afiliasi.php');
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    // 1) Update Status Penarikan Dana (Withdrawal)
    if ($action === 'update_withdrawal_status') {
        $withdrawalId = (int) ($_POST['id_withdrawal'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? '');
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
        $allowedStatus = ['pending', 'approved', 'completed', 'rejected'];

        if ($withdrawalId <= 0 || !in_array($newStatus, $allowedStatus, true)) {
            drw_flash('danger', 'Parameter penarikan tidak valid.');
        } else {
            $stmtWd = $conn->prepare('SELECT id_withdrawal, id_user, nominal, status FROM affiliate_withdrawal WHERE id_withdrawal = ? LIMIT 1');
            $stmtWd->bind_param('i', $withdrawalId);
            $stmtWd->execute();
            $wd = $stmtWd->get_result()->fetch_assoc();
            $stmtWd->close();

            if (!$wd) {
                drw_flash('danger', 'Data penarikan tidak ditemukan.');
            } else {
                $oldStatus = (string) $wd['status'];
                $amount = (int) $wd['nominal'];
                $userId = (int) $wd['id_user'];

                $conn->begin_transaction();
                try {
                    // Update status penarikan
                    $stmtUpd = $conn->prepare('
                        UPDATE affiliate_withdrawal
                        SET status = ?, catatan_admin = ?, tanggal_diproses = NOW()
                        WHERE id_withdrawal = ?
                    ');
                    $stmtUpd->bind_param('ssi', $newStatus, $adminNotes, $withdrawalId);
                    $stmtUpd->execute();
                    $stmtUpd->close();

                    // Logika saldo:
                    // Jika ditolak dari pending/approved: kembalikan saldo komisi ke user
                    if ($newStatus === 'rejected' && $oldStatus !== 'rejected') {
                        $stmtRefund = $conn->prepare('UPDATE user SET total_komisi = total_komisi + ? WHERE id_user = ?');
                        $stmtRefund->bind_param('ii', $amount, $userId);
                        $stmtRefund->execute();
                        $stmtRefund->close();

                        $logDesc = 'Pengembalian saldo: Penarikan #' . $withdrawalId . ' ditolak. Catatan: ' . ($adminNotes ?: '-');
                        $stmtLog = $conn->prepare('INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan, id_withdrawal) VALUES (?, "koreksi", ?, ?, ?)');
                        $stmtLog->bind_param('iisi', $userId, $amount, $logDesc, $withdrawalId);
                        $stmtLog->execute();
                        $stmtLog->close();
                    }
                    // Jika selesai ditransfer (completed): catat ke total_ditarik
                    elseif ($newStatus === 'completed' && $oldStatus !== 'completed') {
                        $stmtCompleted = $conn->prepare('UPDATE user SET total_ditarik = total_ditarik + ? WHERE id_user = ?');
                        $stmtCompleted->bind_param('ii', $amount, $userId);
                        $stmtCompleted->execute();
                        $stmtCompleted->close();
                    }
                    // Jika sebelumnya rejected tapi diubah ke pending/approved/completed: potong kembali saldo
                    elseif ($oldStatus === 'rejected' && $newStatus !== 'rejected') {
                        $stmtReDeduct = $conn->prepare('UPDATE user SET total_komisi = GREATEST(0, total_komisi - ?) WHERE id_user = ?');
                        $stmtReDeduct->bind_param('ii', $amount, $userId);
                        $stmtReDeduct->execute();
                        $stmtReDeduct->close();

                        if ($newStatus === 'completed') {
                            $stmtCompleted = $conn->prepare('UPDATE user SET total_ditarik = total_ditarik + ? WHERE id_user = ?');
                            $stmtCompleted->bind_param('ii', $amount, $userId);
                            $stmtCompleted->execute();
                            $stmtCompleted->close();
                        }
                    }

                    $conn->commit();
                    drw_flash('success', 'Status penarikan #' . $withdrawalId . ' berhasil diperbarui menjadi: ' . ucfirst($newStatus));
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Error update withdrawal: ' . $e->getMessage());
                    drw_flash('danger', 'Gagal memperbarui status penarikan. Terjadi kesalahan database.');
                }
            }
        }
        header('Location: kelola_afiliasi.php');
        exit();
    }

    // 2) Atur / Reset Kode Afiliasi Member
    if ($action === 'set_affiliate_code') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $newCodeRaw = trim((string) ($_POST['affiliate_code'] ?? ''));
        $val = drw_validate_affiliate_code($newCodeRaw);

        if (!$val['valid']) {
            drw_flash('danger', $val['error']);
        } else {
            $code = $val['code'];
            $stmtCheck = $conn->prepare('SELECT id_user FROM user WHERE affiliate_code = ? AND id_user != ? LIMIT 1');
            $stmtCheck->bind_param('si', $code, $targetUserId);
            $stmtCheck->execute();
            $exists = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if ($exists) {
                drw_flash('danger', 'Kode ' . htmlspecialchars($code) . ' sudah digunakan oleh member lain.');
            } else {
                $stmtSet = $conn->prepare('UPDATE user SET affiliate_code = ?, affiliate_code_updated_at = NOW() WHERE id_user = ?');
                $stmtSet->bind_param('si', $code, $targetUserId);
                $stmtSet->execute();
                $stmtSet->close();
                drw_flash('success', 'Kode afiliasi user #' . $targetUserId . ' berhasil diubah menjadi: ' . htmlspecialchars($code));
            }
        }
        header('Location: kelola_afiliasi.php?tab=affiliates');
        exit();
    }
}

// Data Ringkasan Dashboard
$stats = [
    'pending_wd_count' => 0,
    'pending_wd_amount' => 0,
    'total_paid_commissions' => 0,
    'total_affiliates' => 0,
    'total_referral_orders' => 0,
];

// Hitung pending withdrawals
$res = $conn->query("SELECT COUNT(*) AS count, COALESCE(SUM(nominal), 0) AS total FROM affiliate_withdrawal WHERE status = 'pending'");
if ($row = $res->fetch_assoc()) {
    $stats['pending_wd_count'] = (int) $row['count'];
    $stats['pending_wd_amount'] = (int) $row['total'];
}
$res->close();

// Total komisi dibayarkan
$res = $conn->query("SELECT COALESCE(SUM(komisi_nominal), 0) AS total FROM `order` WHERE komisi_status = 'paid'");
if ($row = $res->fetch_assoc()) {
    $stats['total_paid_commissions'] = (int) $row['total'];
}
$res->close();

// Total member yang memiliki kode afiliasi
$res = $conn->query("SELECT COUNT(*) AS total FROM user WHERE affiliate_code IS NOT NULL");
if ($row = $res->fetch_assoc()) {
    $stats['total_affiliates'] = (int) $row['total'];
}
$res->close();

// Total booking referral
$res = $conn->query("SELECT COUNT(*) AS total FROM `order` WHERE referred_by IS NOT NULL");
if ($row = $res->fetch_assoc()) {
    $stats['total_referral_orders'] = (int) $row['total'];
}
$res->close();

// Tab aktif dari URL
$activeTab = $_GET['tab'] ?? 'withdrawals';

// 1) List Permintaan Penarikan (Withdrawals)
$filterWdStatus = $_GET['wd_status'] ?? 'all';
$wdWhere = [];
$wdParams = [];
$wdTypes = '';
if (in_array($filterWdStatus, ['pending', 'approved', 'completed', 'rejected'], true)) {
    $wdWhere[] = 'w.status = ?';
    $wdTypes .= 's';
    $wdParams[] = $filterWdStatus;
}
$wdSql = '
    SELECT w.id_withdrawal, w.id_user, w.nominal, w.tipe_tujuan, w.nama_bank, w.nomor_rekening,
           w.nama_pemilik, w.status, w.catatan_admin, w.tanggal_pengajuan, w.tanggal_diproses,
           u.nama_lengkap, u.username, u.no_telepon, u.affiliate_code
    FROM affiliate_withdrawal w
    JOIN user u ON u.id_user = w.id_user
';
if ($wdWhere !== []) {
    $wdSql .= ' WHERE ' . implode(' AND ', $wdWhere);
}
$wdSql .= ' ORDER BY w.id_withdrawal DESC LIMIT 100';

$stmtWdList = $conn->prepare($wdSql);
if ($wdTypes !== '') {
    $stmtWdList->bind_param($wdTypes, ...$wdParams);
}
$stmtWdList->execute();
$withdrawals = $stmtWdList->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtWdList->close();

// 2) List Afiliator
$searchAff = trim((string) ($_GET['search_aff'] ?? ''));
$affWhere = ['u.affiliate_code IS NOT NULL'];
$affParams = [];
$affTypes = '';
if ($searchAff !== '') {
    $affLike = '%' . $searchAff . '%';
    $affWhere[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.affiliate_code LIKE ? OR u.no_telepon LIKE ?)';
    $affTypes .= 'ssss';
    $affParams = [$affLike, $affLike, $affLike, $affLike];
}
$affSql = '
    SELECT u.id_user, u.nama_lengkap, u.username, u.no_telepon, u.affiliate_code,
           u.total_komisi, u.total_ditarik, u.total_referral, u.tanggal_daftar,
           c.nama_cabang
    FROM user u
    LEFT JOIN cabang c ON c.id_cabang = u.id_cabang
    WHERE ' . implode(' AND ', $affWhere) . '
    ORDER BY u.total_referral DESC, u.total_komisi DESC, u.id_user DESC
    LIMIT 100
';
$stmtAff = $conn->prepare($affSql);
if ($affTypes !== '') {
    $stmtAff->bind_param($affTypes, ...$affParams);
}
$stmtAff->execute();
$affiliates = $stmtAff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAff->close();

// 3) List Booking Berkomisi
$orderCommSql = '
    SELECT o.id_order, o.referred_by, o.komisi_nominal, o.komisi_status, o.total_bayar,
           o.tanggal_treatment, o.status_order,
           l.nama_layanan, l.harga,
           c.nama_cabang,
           u_pat.nama_lengkap AS patient_name,
           u_ref.nama_lengkap AS referrer_name, u_ref.id_user AS referrer_id
    FROM `order` o
    JOIN layanan l ON l.id_layanan = o.id_layanan
    LEFT JOIN cabang c ON c.id_cabang = o.id_cabang
    JOIN user u_pat ON u_pat.id_user = o.id_user
    LEFT JOIN user u_ref ON u_ref.id_user = o.referrer_id
    WHERE o.referred_by IS NOT NULL
    ORDER BY o.id_order DESC
    LIMIT 100
';
$resComm = $conn->query($orderCommSql);
$commissionOrders = $resComm ? $resComm->fetch_all(MYSQLI_ASSOC) : [];
if ($resComm) $resComm->close();

// Sidebar badges
if ($adminCabangId === null) {
    $pendingOrdersQuery = $conn->query("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending'");
    $pending_orders = (int) ($pendingOrdersQuery->fetch_assoc()['total'] ?? 0);
    $pendingOrdersQuery->close();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending' AND id_cabang = ?");
    $stmt->bind_param('i', $adminCabangId);
    $stmt->execute();
    $pending_orders = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? (int) $pending_testimoni_query->fetch_assoc()['total'] : 0;
if ($pending_testimoni_query) $pending_testimoni_query->close();

$flash = drw_consume_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Afiliasi - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="../css/admin-theme.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .admin-sidebar { background-color: #2c3e50; color: white; min-height: 100vh; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; width: 250px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .admin-sidebar .sidebar-brand { padding: 1rem 1.5rem; font-size: 1.5rem; font-weight: bold; color: #fff; text-align: center; border-bottom: 1px solid #3a506b; }
        .admin-sidebar .sidebar-brand img { max-height: 35px; margin-right: 10px; }
        .admin-sidebar .nav-link { color: #bdc3c7; padding: 0.9rem 1.5rem; font-weight: 500; border-left: 3px solid transparent; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #34495e; border-left-color: #1abc9c; }
        .admin-sidebar .nav-link.active { color: #fff; background-color: #1abc9c; border-left-color: #fff; }
        .admin-sidebar .nav-link .fas { margin-right: 0.8rem; width: 20px; text-align: center; }
        .admin-sidebar hr.text-secondary { border-top: 1px solid #3a506b; }
        .admin-main-content { margin-left: 250px; padding: 20px; width: calc(100% - 250px); }
        .admin-header { background-color: #fff; padding: 15px 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 991.98px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; box-shadow: none; }
            .admin-main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="d-flex flex-column flex-lg-row">
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <img src="../images/logo.png" alt="Logo"> <?php echo NAMA_KLINIK; ?>
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
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
                    <a class="nav-link active" href="kelola_afiliasi.php">
                        <i class="fas fa-handshake"></i> Kelola Afiliasi
                        <?php if ($stats['pending_wd_count'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-1"><?php echo $stats['pending_wd_count']; ?></span>
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
                <h1 class="h4 mb-0 text-gray-800">Kelola Program Afiliasi & Penarikan</h1>
                <div class="user-info">
                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-clinic-medical me-1"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <!-- STATS CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-warning border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">PENARIKAN PENDING</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1">
                                <h4 class="fw-bold mb-0 text-dark"><?php echo $stats['pending_wd_count']; ?> permintaan</h4>
                                <span class="badge bg-warning text-dark">Rp <?php echo number_format($stats['pending_wd_amount'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-success border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">TOTAL KOMISI DIBAYAR</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1">Rp <?php echo number_format($stats['total_paid_commissions'], 0, ',', '.'); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-primary border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">TOTAL AFILIATOR AKTIF</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['total_affiliates'], 0, ',', '.'); ?> member</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-info border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">BOOKING DARI REFERRAL</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['total_referral_orders'], 0, ',', '.'); ?> order</h4>
                        </div>
                    </div>
                </div>

                <!-- TABS CONTAINER -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom pt-3">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'withdrawals' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=withdrawals">
                                    <i class="fas fa-money-check-alt me-1"></i> Permintaan Penarikan
                                    <?php if ($stats['pending_wd_count'] > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $stats['pending_wd_count']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'affiliates' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=affiliates">
                                    <i class="fas fa-users me-1"></i> Daftar Afiliator
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'orders' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=orders">
                                    <i class="fas fa-receipt me-1"></i> Riwayat Komisi Booking
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <?php if ($activeTab === 'withdrawals'): ?>
                            <!-- TAB 1: PERMINTAAN PENARIKAN -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-list me-1"></i> Data Penarikan Dana Afiliasi</h6>
                                <div class="btn-group btn-group-sm">
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=all" class="btn btn-outline-secondary <?php echo $filterWdStatus === 'all' ? 'active' : ''; ?>">Semua</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=pending" class="btn btn-outline-warning <?php echo $filterWdStatus === 'pending' ? 'active' : ''; ?>">Pending</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=approved" class="btn btn-outline-info <?php echo $filterWdStatus === 'approved' ? 'active' : ''; ?>">Diproses</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=completed" class="btn btn-outline-success <?php echo $filterWdStatus === 'completed' ? 'active' : ''; ?>">Selesai</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=rejected" class="btn btn-outline-danger <?php echo $filterWdStatus === 'rejected' ? 'active' : ''; ?>">Ditolak</a>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Tanggal</th>
                                            <th>Afiliator</th>
                                            <th>Nominal</th>
                                            <th>Rekening Tujuan</th>
                                            <th>Status</th>
                                            <th>Catatan Admin</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($withdrawals)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Tidak ada data penarikan untuk filter ini.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($withdrawals as $w): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $w['id_withdrawal']; ?></td>
                                                    <td><small><?php echo date('d M Y, H:i', strtotime((string) $w['tanggal_pengajuan'])); ?></small></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars((string) $w['nama_lengkap']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars((string) $w['username']); ?> &bull; Kode: <code><?php echo htmlspecialchars((string) $w['affiliate_code']); ?></code></small>
                                                        <?php if (!empty($w['no_telepon'])): ?>
                                                            <br><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars((string) $w['no_telepon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-success fs-6">Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border text-uppercase me-1"><?php echo htmlspecialchars((string) $w['tipe_tujuan']); ?></span>
                                                        <strong><?php echo htmlspecialchars((string) $w['nama_bank']); ?></strong>
                                                        <br><span class="font-monospace fw-bold"><?php echo htmlspecialchars((string) $w['nomor_rekening']); ?></span>
                                                        <br><small class="text-muted">an <?php echo htmlspecialchars((string) $w['nama_pemilik']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($w['status'] === 'completed'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Selesai Transfer</span>
                                                        <?php elseif ($w['status'] === 'approved'): ?>
                                                            <span class="badge bg-info text-dark"><i class="fas fa-spinner fa-spin me-1"></i> Diproses</span>
                                                        <?php elseif ($w['status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Ditolak</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars((string) ($w['catatan_admin'] ?? '-')); ?></small></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProcessWd<?php echo (int) $w['id_withdrawal']; ?>">
                                                            <i class="fas fa-edit me-1"></i> Proses
                                                        </button>

                                                        <!-- Modal Proses Penarikan -->
                                                        <div class="modal fade" id="modalProcessWd<?php echo (int) $w['id_withdrawal']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog text-start">
                                                                <form method="POST" action="kelola_afiliasi.php" class="modal-content">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                    <input type="hidden" name="action" value="update_withdrawal_status">
                                                                    <input type="hidden" name="id_withdrawal" value="<?php echo (int) $w['id_withdrawal']; ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold">Proses Penarikan #<?php echo (int) $w['id_withdrawal']; ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="p-3 bg-light rounded mb-3">
                                                                            <div><strong>Afiliator:</strong> <?php echo htmlspecialchars((string) $w['nama_lengkap']); ?></div>
                                                                            <div><strong>Nominal:</strong> <span class="text-success fw-bold">Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?></span></div>
                                                                            <div><strong>Transfer ke:</strong> <?php echo htmlspecialchars((string) $w['nama_bank'] . ' ' . $w['nomor_rekening'] . ' an ' . $w['nama_pemilik']); ?></div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Pilih Status Baru</label>
                                                                            <select name="status" class="form-select" required>
                                                                                <option value="pending" <?php echo $w['status'] === 'pending' ? 'selected' : ''; ?>>Pending (Menunggu)</option>
                                                                                <option value="approved" <?php echo $w['status'] === 'approved' ? 'selected' : ''; ?>>Approved (Sedang Ditransfer)</option>
                                                                                <option value="completed" <?php echo $w['status'] === 'completed' ? 'selected' : ''; ?>>Completed (Selesai Ditransfer)</option>
                                                                                <option value="rejected" <?php echo $w['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected (Tolak & Refund Saldo)</option>
                                                                            </select>
                                                                            <div class="form-text">Jika memilih <em>Rejected</em>, saldo sebesar Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?> akan otomatis dikembalikan ke akun member.</div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Catatan Admin (Opsional)</label>
                                                                            <textarea name="admin_notes" class="form-control" rows="2" placeholder="Contoh: Transfer via BCA ref 9281729 / Nomor rekening tidak valid"><?php echo htmlspecialchars((string) ($w['catatan_admin'] ?? '')); ?></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($activeTab === 'affiliates'): ?>
                            <!-- TAB 2: DAFTAR AFILIATOR -->
                            <div class="row gx-3 gy-2 align-items-center mb-3">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-users me-1"></i> Data Afiliator Terdaftar</h6>
                                </div>
                                <div class="col-md-6">
                                    <form method="GET" action="kelola_afiliasi.php" class="d-flex gap-2">
                                        <input type="hidden" name="tab" value="affiliates">
                                        <input type="text" name="search_aff" class="form-control form-control-sm" placeholder="Cari nama, username, kode..." value="<?php echo htmlspecialchars($searchAff); ?>">
                                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if ($searchAff !== ''): ?>
                                            <a href="kelola_afiliasi.php?tab=affiliates" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Afiliator</th>
                                            <th>Kode Afiliasi</th>
                                            <th>Klinik</th>
                                            <th>Saldo Komisi</th>
                                            <th>Total Ditarik</th>
                                            <th>Pasien Berhasil</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($affiliates)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Belum ada afiliator ditemukan.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($affiliates as $aff): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $aff['id_user']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars((string) $aff['nama_lengkap']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars((string) $aff['username']); ?></small>
                                                        <?php if (!empty($aff['no_telepon'])): ?>
                                                            <br><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars((string) $aff['no_telepon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark font-monospace fs-6 px-2 py-1"><?php echo htmlspecialchars((string) $aff['affiliate_code']); ?></span>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars((string) ($aff['nama_cabang'] ?? '-')); ?></small></td>
                                                    <td class="fw-bold text-success">Rp <?php echo number_format((int) $aff['total_komisi'], 0, ',', '.'); ?></td>
                                                    <td>Rp <?php echo number_format((int) $aff['total_ditarik'], 0, ',', '.'); ?></td>
                                                    <td><span class="badge bg-info text-dark"><?php echo number_format((int) $aff['total_referral'], 0, ',', '.'); ?> pasien</span></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditCode<?php echo (int) $aff['id_user']; ?>" title="Ubah Kode Afiliasi">
                                                            <i class="fas fa-tag me-1"></i> Edit Kode
                                                        </button>

                                                        <!-- Modal Edit Kode Afiliasi -->
                                                        <div class="modal fade" id="modalEditCode<?php echo (int) $aff['id_user']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog text-start">
                                                                <form method="POST" action="kelola_afiliasi.php" class="modal-content">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                    <input type="hidden" name="action" value="set_affiliate_code">
                                                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $aff['id_user']; ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold">Ubah Kode Afiliasi</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p class="small text-muted mb-2">Member: <strong><?php echo htmlspecialchars((string) $aff['nama_lengkap']); ?></strong></p>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Kode Afiliasi Baru</label>
                                                                            <input type="text" name="affiliate_code" class="form-control font-monospace text-uppercase" value="<?php echo htmlspecialchars((string) $aff['affiliate_code']); ?>" required minlength="4" maxlength="15">
                                                                            <div class="form-text">4-15 karakter huruf dan angka (A-Z, 0-9).</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Kode</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($activeTab === 'orders'): ?>
                            <!-- TAB 3: RIWAYAT KOMISI BOOKING -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-receipt me-1"></i> Transaksi Menggunakan Referral</h6>
                                <span class="badge bg-light text-dark border"><?php echo count($commissionOrders); ?> transaksi terbaru</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID Order</th>
                                            <th>Tgl Kunjungan</th>
                                            <th>Pasien</th>
                                            <th>Layanan</th>
                                            <th>Klinik</th>
                                            <th>Afiliator (Referrer)</th>
                                            <th>Status Booking</th>
                                            <th>Komisi Afiliasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($commissionOrders)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Belum ada booking yang menggunakan kode referral.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($commissionOrders as $co): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $co['id_order']; ?></td>
                                                    <td><small><?php echo date('d M Y, H:i', strtotime((string) $co['tanggal_treatment'])); ?></small></td>
                                                    <td><strong><?php echo htmlspecialchars((string) $co['patient_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars((string) $co['nama_layanan']); ?></td>
                                                    <td><small><?php echo htmlspecialchars((string) ($co['nama_cabang'] ?? '-')); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-primary text-white font-monospace"><?php echo htmlspecialchars((string) $co['referred_by']); ?></span>
                                                        <?php if (!empty($co['referrer_name'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars((string) $co['referrer_name']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($co['status_order'] === 'completed'): ?>
                                                            <span class="badge bg-success">Selesai</span>
                                                        <?php elseif ($co['status_order'] === 'confirmed'): ?>
                                                            <span class="badge bg-primary">Terkonfirmasi</span>
                                                        <?php elseif ($co['status_order'] === 'cancelled'): ?>
                                                            <span class="badge bg-danger">Batal</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($co['komisi_status'] === 'paid'): ?>
                                                            <span class="fw-bold text-success">+Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-success-subtle text-success">Sudah Dibayar</small>
                                                        <?php elseif ($co['komisi_status'] === 'cancelled'): ?>
                                                            <span class="text-muted text-decoration-line-through">Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-danger-subtle text-danger">Dibatalkan</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Est. Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-warning-subtle text-dark">Menunggu Treatment</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <footer class="admin-footer mt-auto">
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
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>
<?php
$conn->close();
