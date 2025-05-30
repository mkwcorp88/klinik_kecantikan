<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin");
    exit();
}

$message = '';
$message_type = '';

// Parameter untuk filter dan search, agar tetap ada setelah aksi
$current_query_params = [];
if(isset($_GET['filter_status']) && !empty($_GET['filter_status'])) $current_query_params['filter_status'] = $_GET['filter_status'];
if(isset($_GET['search_user']) && !empty($_GET['search_user'])) $current_query_params['search_user'] = $_GET['search_user'];
$query_string_params = http_build_query($current_query_params);
$action_url = "kelola_order.php" . ($query_string_params ? "?".$query_string_params : "");

// Handle Update Status Order
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status_order'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $conn->real_escape_string($_POST['status_order']);
    $allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];

    if (in_array($new_status, $allowed_statuses)) {
        $stmt_update = $conn->prepare("UPDATE `order` SET status_order = ? WHERE id_order = ?");
        $stmt_update->bind_param("si", $new_status, $order_id);
        if ($stmt_update->execute()) {
            $_SESSION['flash_message'] = "Status order ID #$order_id berhasil diperbarui menjadi '" . ucfirst($new_status) . "'.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal memperbarui status order: " . $stmt_update->error;
            $_SESSION['flash_message_type'] = "danger";
        }
        $stmt_update->close();
    } else {
        $_SESSION['flash_message'] = "Status tidak valid.";
        $_SESSION['flash_message_type'] = "danger";
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

// Filter dan Search Logic
$filter_status_get = isset($_GET['filter_status']) ? $conn->real_escape_string($_GET['filter_status']) : '';
$search_user_get = isset($_GET['search_user']) ? $conn->real_escape_string(trim($_GET['search_user'])) : '';

$orders = [];
$where_clauses = [];

if (!empty($filter_status_get)) {
    $where_clauses[] = "o.status_order = '$filter_status_get'";
}
if (!empty($search_user_get)) {
     $where_clauses[] = "(u.nama_lengkap LIKE '%$search_user_get%' OR u.username LIKE '%$search_user_get%')";
}

$sql_orders = "SELECT o.id_order, u.nama_lengkap AS nama_user, u.no_telepon AS telepon_user, l.nama_layanan, o.tanggal_treatment, o.status_order, o.tanggal_order_dibuat, o.catatan_tambahan
               FROM `order` o
               JOIN user u ON o.id_user = u.id_user
               JOIN layanan l ON o.id_layanan = l.id_layanan";
if (!empty($where_clauses)) {
    $sql_orders .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_orders .= " ORDER BY o.tanggal_order_dibuat DESC";

$result_orders = $conn->query($sql_orders);
if ($result_orders && $result_orders->num_rows > 0) {
    while ($row = $result_orders->fetch_assoc()) {
        $orders[] = $row;
    }
}

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;

$conn->close();
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
        body { display: flex; min-height: 100vh; flex-direction: column; }
        .admin-sidebar { background-color: #343a40; color: white; padding-top: 1rem; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1rem; }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { color: white; background-color: #495057; }
        .admin-sidebar .nav-link .fas { margin-right: 0.5rem; }
        .admin-content { flex-grow: 1; padding: 1rem 2rem; }
        .admin-header { background-color: #f8f9fa; padding: 1rem 1.5rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .table-responsive { margin-top: 1rem; }
        .table th, .table td { vertical-align: middle; }
        .action-dropdown .dropdown-item {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        .action-dropdown .dropdown-item i.fas {
            width: 20px; /* Agar ikon sejajar */
            margin-right: 0.5rem;
        }
        .action-dropdown .dropdown-item.disabled {
            color: #6c757d;
            pointer-events: none;
            background-color: transparent;
        }
        .badge { font-size: 0.8em; padding: 0.4em 0.6em; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <div class="d-flex">
        <nav class="admin-sidebar d-none d-md-block col-md-3 col-lg-2">
            <div class="sidebar-sticky">
                <h5 class="px-3 py-2 text-white"><?php echo NAMA_KLINIK; ?></h5>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_user.php"><i class="fas fa-users"></i> Kelola Member</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_layanan.php"><i class="fas fa-concierge-bell"></i> Kelola Layanan</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_testimoni.php"><i class="fas fa-comment-dots"></i> Kelola Testimoni <?php if ($pending_testimoni > 0) echo "<span class='badge bg-warning ms-1'>$pending_testimoni</span>"; ?></a></li>
                </ul>
                <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="logout_admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
            </div>
        </nav>

        <div class="admin-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <header class="admin-header">
                <h1 class="h4 mb-0">Kelola Order Pelanggan</h1>
                <span class="text-muted">Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </header>

            <main class="pt-3">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form method="GET" action="kelola_order.php" class="row g-3 mb-4 align-items-end">
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
                    <div class="col-md-auto">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-filter me-1"></i> Filter</button>
                         <?php if (!empty($filter_status_get) || !empty($search_user_get)): ?>
                            <a href="kelola_order.php" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i> Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Member (Telp)</th>
                                <th scope="col">Layanan</th>
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
                                    <td><?php echo htmlspecialchars($order['nama_layanan']); ?></td>
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
                                                <i class="fas fa-edit"></i> <span class="d-none d-lg-inline">Ubah Status</span>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li>
                                                    <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                        <input type="hidden" name="status_order" value="pending">
                                                        <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'pending') echo 'disabled fw-bold'; ?>">
                                                            <i class="fas fa-clock text-warning"></i>Jadikan Pending
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                        <input type="hidden" name="status_order" value="confirmed">
                                                        <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'confirmed') echo 'disabled fw-bold'; ?>">
                                                            <i class="fas fa-check-circle text-info"></i>Konfirmasi Pesanan
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                        <input type="hidden" name="status_order" value="completed">
                                                        <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'completed') echo 'disabled fw-bold'; ?>">
                                                            <i class="fas fa-thumbs-up text-success"></i>Tandai Selesai
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id_order']; ?>">
                                                        <input type="hidden" name="status_order" value="cancelled">
                                                        <button type="submit" name="update_status_order" class="dropdown-item <?php if ($order['status_order'] == 'cancelled') echo 'disabled fw-bold'; ?>">
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
                                    <td colspan="8" class="text-center">Tidak ada data order ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script> <?php /* Pastikan script.js ada jika diperlukan */ ?>
</body>
</html>