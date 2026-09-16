<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();

$message = '';
$message_type = '';
$csrfToken = drw_csrf_token();
$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

// Filter dan Search Logic (cabang admin dipaksa bila bukan superadmin semua klinik)
$filter_status_get = isset($_GET['filter_status']) && in_array($_GET['filter_status'], $allowed_statuses, true) ? $_GET['filter_status'] : '';
$search_user_get = isset($_GET['search_user']) && is_string($_GET['search_user']) ? trim($_GET['search_user']) : '';
$filter_cabang_get = isset($_GET['filter_cabang']) && ctype_digit((string) $_GET['filter_cabang']) ? (int) $_GET['filter_cabang'] : 0;
if ($adminCabangId !== null) {
    $filter_cabang_get = $adminCabangId;
}

// Parameter untuk filter dan search, agar tetap ada setelah aksi
$current_query_params = [];
if ($filter_status_get !== '') $current_query_params['filter_status'] = $filter_status_get;
if ($search_user_get !== '') $current_query_params['search_user'] = $search_user_get;
if ($adminCabangId === null && $filter_cabang_get > 0) $current_query_params['filter_cabang'] = $filter_cabang_get;
$query_string_params = http_build_query($current_query_params);
$action_url = "kelola_order.php" . ($query_string_params ? "?".$query_string_params : "");

// Handle Update Status Order (wajib milik klinik admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status_order'])) {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        drw_flash('danger', 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.');
    } else {
        $order_id = intval($_POST['order_id']);
        $new_status = is_string($_POST['status_order'] ?? null) ? $_POST['status_order'] : '';

        if (!in_array($new_status, $allowed_statuses, true)) {
            drw_flash('danger', 'Status booking tidak valid.');
        } else {
            $allowed = true;
            if ($adminCabangId !== null) {
                $stmt_check = $conn->prepare("SELECT id_cabang FROM `order` WHERE id_order = ? LIMIT 1");
                $stmt_check->bind_param('i', $order_id);
                $stmt_check->execute();
                $orderRow = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();
                if (!$orderRow || (int) ($orderRow['id_cabang'] ?? 0) !== $adminCabangId) {
                    $allowed = false;
                    drw_flash('danger', 'Order tersebut bukan milik klinik Anda.');
                }
            }
            if ($allowed) {
                $stmt_update = $conn->prepare("UPDATE `order` SET status_order = ? WHERE id_order = ?");
                $stmt_update->bind_param("si", $new_status, $order_id);
                if ($stmt_update->execute()) {
                    if ($new_status === 'completed') {
                        drw_process_order_commission($conn, $order_id);
                    } elseif ($new_status === 'cancelled') {
                        drw_revert_order_commission($conn, $order_id);
                    }
                    drw_flash('success', "Status booking ID #$order_id berhasil diperbarui menjadi '" . ucfirst($new_status) . "'.");
                } else {
                    drw_flash('danger', 'Gagal memperbarui status booking. Terjadi kesalahan internal.');
                }
                $stmt_update->close();
            }
        }
    }
    header("Location: " . $action_url); // Redirect dengan parameter filter/search
    exit();
}

// Ambil pesan flash dari session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_message_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}

$branches = [];
$result_branches = $conn->query("SELECT id_cabang, nama_cabang FROM cabang WHERE status_cabang = 'aktif' ORDER BY nama_cabang ASC");
if ($result_branches) {
    while ($branch = $result_branches->fetch_assoc()) {
        $branch['id_cabang'] = (int) $branch['id_cabang'];
        if ($adminCabangId !== null && $branch['id_cabang'] !== $adminCabangId) {
            continue;
        }
        $branches[] = $branch;
    }
    $result_branches->close();
}

$orders = [];
$queryTypes = '';
$queryParams = [];
$where_clauses = [];

if (!empty($filter_status_get)) {
    $where_clauses[] = "o.status_order = ?";
    $queryTypes .= 's';
    $queryParams[] = $filter_status_get;
}
if ($filter_cabang_get > 0) {
    $where_clauses[] = "o.id_cabang = ?";
    $queryTypes .= 'i';
    $queryParams[] = $filter_cabang_get;
} elseif ($adminCabangId !== null) {
    $where_clauses[] = "o.id_cabang = ?";
    $queryTypes .= 'i';
    $queryParams[] = $adminCabangId;
}
$search_like = null;
if (!empty($search_user_get)) {
     $where_clauses[] = "(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.no_telepon LIKE ?)";
     $search_like = '%' . $search_user_get . '%';
     $queryTypes .= 'ssss';
     $queryParams[] = $search_like;
     $queryParams[] = $search_like;
     $queryParams[] = $search_like;
     $queryParams[] = $search_like;
}

$sql_orders = "SELECT o.id_order, u.nama_lengkap AS nama_user, u.no_telepon AS telepon_user, l.nama_layanan, c.nama_cabang, o.tanggal_treatment, o.status_order, o.tanggal_order_dibuat, o.catatan_tambahan, o.referred_by, o.komisi_nominal, o.komisi_status, ref_u.nama_lengkap AS referrer_nama
               FROM `order` o
               JOIN user u ON o.id_user = u.id_user
               JOIN layanan l ON o.id_layanan = l.id_layanan
               LEFT JOIN cabang c ON o.id_cabang = c.id_cabang
               LEFT JOIN user ref_u ON ref_u.id_user = o.referrer_id";
if (!empty($where_clauses)) {
    $sql_orders .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_orders .= " ORDER BY o.tanggal_order_dibuat DESC";

$stmt_orders = $conn->prepare($sql_orders);
if ($stmt_orders) {
    if ($queryTypes !== '') {
        $stmt_orders->bind_param($queryTypes, ...$queryParams);
    }
    $stmt_orders->execute();
    $result_orders = $stmt_orders->get_result();
    while ($row = $result_orders->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt_orders->close();
}

// Data untuk badge di sidebar (difilter klinik aktif)
if ($adminCabangId === null) {
    $pending_orders_query = $conn->query("SELECT COUNT(*) as total FROM `order` WHERE status_order = 'pending'");
    $pending_orders = ($pending_orders_query && $pending_orders_query->num_rows > 0) ? $pending_orders_query->fetch_assoc()['total'] : 0;
    if($pending_orders_query) $pending_orders_query->close();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM `order` WHERE status_order = 'pending' AND id_cabang = ?");
    $stmt->bind_param('i', $adminCabangId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $pending_orders = (int) ($row['total'] ?? 0);
    $stmt->close();
}

$conn->close();
$actionUrlHtml = htmlspecialchars($action_url, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Order - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya Admin Dashboard Spesifik (Sama seperti di admin/index.php) */
        /* Sebaiknya ini dipindahkan ke file CSS admin terpisah */
        body { background-color: #f4f7f6; }
        .admin-sidebar { background-color: #2c3e50; color: white; padding-top: 0; min-height: 100vh; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; width: 250px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .admin-sidebar .sidebar-brand { padding: 1rem 1.5rem; font-size: 1.5rem; font-weight: bold; color: #fff; text-align: center; border-bottom: 1px solid #3a506b; }
        .admin-sidebar .sidebar-brand img { max-height: 35px; margin-right: 10px; }
        .admin-sidebar .nav-link { color: #bdc3c7; padding: 0.9rem 1.5rem; font-weight: 500; border-left: 3px solid transparent; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #34495e; border-left-color: #1abc9c; }
        .admin-sidebar .nav-link.active { color: #fff; background-color: #1abc9c; border-left-color: #fff; }
        .admin-sidebar .nav-link .fas { margin-right: 0.8rem; width: 20px; text-align: center; }
        .admin-sidebar hr.text-secondary { border-top: 1px solid #3a506b; }
        .admin-main-content { margin-left: 250px; padding: 0; width: calc(100% - 250px); display: flex; flex-direction: column; min-height: 100vh; }
        .admin-header { background-color: #fff; padding: 1rem 2rem; border-bottom: 1px solid #e3e6f0; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99; }
        .admin-header .user-info span { font-size: 0.9rem; }
        .admin-content-area { padding: 2rem; flex-grow: 1; }
        .table th, .table td { vertical-align: middle; }
        .action-dropdown .dropdown-item { display: flex; align-items: center; font-size: 0.9rem; }
        .action-dropdown .dropdown-item i.fas { width: 20px; margin-right: 0.5rem; }
        .action-dropdown .dropdown-item.disabled { color: #6c757d; pointer-events: none; background-color: transparent; }
        .badge { font-size: 0.8em; padding: 0.4em 0.6em; }
        .admin-footer { background-color: #e9ecef; padding: 1rem 2rem; font-size: 0.875rem; border-top: 1px solid #dee2e6;}
        @media (max-width: 767px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; box-shadow: none; }
            .admin-main-content { margin-left: 0; width: 100%; }
            .admin-header { position: static; }
        }
    </style>
    <link href="../css/admin-theme.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
    <div class="d-flex">
        <nav class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-brand">
                <img src="../images/logo.png" alt="Logo"> <?php echo NAMA_KLINIK; ?>
            </div>
            <ul class="nav flex-column mt-3">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_user.php"><i class="fas fa-users"></i> Kelola Member</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order <?php if ($pending_orders > 0) echo "<span class='badge bg-danger ms-1'>$pending_orders</span>"; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_afiliasi.php"><i class="fas fa-handshake"></i> Afiliator</a></li>
            </ul>
            <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="logout_admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
        </nav>

        <div class="admin-main-content">
            <header class="admin-header">
                <h1 class="h4 mb-0 text-gray-800">Kelola Order Pelanggan</h1>
                <div class="user-info">
                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-clinic-medical me-1"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 bg-light border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>Filter dan Pencarian Order</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="kelola_order.php" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="search_user" class="form-label">Cari Member</label>
                                <input type="text" name="search_user" id="search_user" class="form-control form-control-sm" placeholder="Nama atau username member..." value="<?php echo htmlspecialchars($search_user_get); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="filter_status" class="form-label">Filter Status</label>
                                <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php if ($filter_status_get == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="confirmed" <?php if ($filter_status_get == 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                    <option value="completed" <?php if ($filter_status_get == 'completed') echo 'selected'; ?>>Completed</option>
                                    <option value="cancelled" <?php if ($filter_status_get == 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter_cabang" class="form-label">Cabang</label>
                                <?php if ($adminCabangId === null): ?>
                                <select name="filter_cabang" id="filter_cabang" class="form-select form-select-sm">
                                    <option value="">Semua Cabang</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo (int) $branch['id_cabang']; ?>" <?php if ($filter_cabang_get === (int) $branch['id_cabang']) echo 'selected'; ?>><?php echo htmlspecialchars($branch['nama_cabang']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($adminCabangNama); ?>" disabled>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2 mt-auto">
                                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fas fa-search me-1"></i> Terapkan</button>
                            </div>
                              <?php if (!empty($filter_status_get) || !empty($search_user_get) || $filter_cabang_get > 0): ?>
                            <div class="col-12 mt-2">
                                <a href="kelola_order.php" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i> Reset Filter</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                     <div class="card-header py-3 bg-light border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul me-2"></i>Daftar Order</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTableOrders" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">ID</th>
                                         <th scope="col">Member (Telp)</th>
                                         <th scope="col">Cabang</th>
                                         <th scope="col">Layanan</th>
                                        <th scope="col">Afiliasi</th>
                                        <th scope="col">Jadwal Treatment</th>
                                        <th scope="col">Tgl Order</th>
                                        <th scope="col">Catatan</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($orders)): ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id_order']; ?></td>
                                             <td>
                                                 <?php echo htmlspecialchars($order['nama_user']); ?><br>
                                                 <small class="text-muted"><i class="fas fa-phone-alt me-1"></i><?php echo htmlspecialchars($order['telepon_user']); ?></small>
                                             </td>
                                             <td><?php echo htmlspecialchars($order['nama_cabang'] ?? 'Belum dicatat'); ?></td>
                                             <td><?php echo htmlspecialchars($order['nama_layanan']); ?></td>
                                             <td>
                                                 <?php if (!empty($order['referred_by'])): ?>
                                                     <span class="badge bg-primary text-white"><i class="fas fa-handshake me-1"></i><?php echo htmlspecialchars((string) $order['referred_by']); ?></span>
                                                     <?php if (!empty($order['referrer_nama'])): ?>
                                                         <br><small class="text-muted"><?php echo htmlspecialchars((string) $order['referrer_nama']); ?></small>
                                                     <?php endif; ?>
                                                     <?php if ($order['komisi_status'] === 'paid'): ?>
                                                         <br><small class="text-success fw-bold">Komisi: Rp <?php echo number_format((int) $order['komisi_nominal'], 0, ',', '.'); ?></small>
                                                     <?php endif; ?>
                                                 <?php else: ?>
                                                     <span class="text-muted small">-</span>
                                                 <?php endif; ?>
                                             </td>
                                             <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_treatment'])); ?></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_order_dibuat'])); ?></td>
                                            <td><small><?php echo nl2br(htmlspecialchars($order['catatan_tambahan'] ? $order['catatan_tambahan'] : '-')); ?></small></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php
                                                    switch ($order['status_order']) {
                                                        case 'pending': echo 'warning text-dark'; break;
                                                        case 'confirmed': echo 'info text-dark'; break;
                                                        case 'completed': echo 'success'; break;
                                                        case 'cancelled': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>"><?php echo ucfirst(htmlspecialchars($order['status_order'])); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group action-dropdown">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Ubah Status Order">
                                                        <i class="fas fa-edit"></i> <span class="d-none d-lg-inline">Status</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <form method="POST" action="<?php echo $actionUrlHtml; ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                                <input type="hidden" name="status_order" value="pending">
                                                                <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'pending') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-clock text-warning"></i>Jadikan Pending
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $actionUrlHtml; ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                                <input type="hidden" name="status_order" value="confirmed">
                                                                <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'confirmed') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-check-circle text-info"></i>Konfirmasi Pesanan
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $actionUrlHtml; ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                                <input type="hidden" name="status_order" value="completed">
                                                                <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'completed') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-thumbs-up text-success"></i>Tandai Selesai
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $actionUrlHtml; ?>" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                                <input type="hidden" name="status_order" value="cancelled">
                                                                <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'cancelled') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-times-circle text-danger"></i>Batalkan Pesanan
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <?php if(!empty($filter_status_get) || !empty($search_user_get) || $filter_cabang_get > 0): ?>
                                                    Tidak ada order ditemukan dengan filter/pencarian saat ini.
                                                <?php else: ?>
                                                    Belum ada data order.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div> <footer class="admin-footer mt-auto">
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
</body>
</html>
