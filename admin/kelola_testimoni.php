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
if(isset($_GET['search_content']) && !empty($_GET['search_content'])) $current_query_params['search_content'] = $_GET['search_content'];
$query_string_params = http_build_query($current_query_params);
$action_url = "kelola_testimoni.php" . ($query_string_params ? "?".$query_string_params : "");

// Handle Update Status Testimoni
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status_testimoni'])) {
    $testimoni_id = intval($_POST['testimoni_id']);
    $new_status = $conn->real_escape_string($_POST['status_testimoni']);
    $allowed_statuses = ['approved', 'pending', 'rejected'];

    if (in_array($new_status, $allowed_statuses)) {
        $stmt_update = $conn->prepare("UPDATE testimoni SET status_testimoni = ? WHERE id_testimoni = ?");
        $stmt_update->bind_param("si", $new_status, $testimoni_id);
        if ($stmt_update->execute()) {
            $_SESSION['flash_message'] = "Status testimoni ID #$testimoni_id berhasil diperbarui menjadi '" . ucfirst($new_status) . "'.";
            $_SESSION['flash_message_type'] = "success";
        } else {
            $_SESSION['flash_message'] = "Gagal memperbarui status testimoni: " . $stmt_update->error;
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

// Handle Hapus Testimoni Permanen
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_testimoni'])) {
    $testimoni_id_delete = intval($_POST['testimoni_id_delete']);
    $stmt_delete = $conn->prepare("DELETE FROM testimoni WHERE id_testimoni = ?");
    $stmt_delete->bind_param("i", $testimoni_id_delete);
    if ($stmt_delete->execute()) {
        $_SESSION['flash_message'] = "Testimoni ID #$testimoni_id_delete berhasil dihapus permanen.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal menghapus testimoni: " . $stmt_delete->error;
        $_SESSION['flash_message_type'] = "danger";
    }
    $stmt_delete->close();
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
$search_content_get = isset($_GET['search_content']) ? $conn->real_escape_string(trim($_GET['search_content'])) : '';

$testimonies = [];
$where_clauses = [];

if (!empty($filter_status_get)) {
    $where_clauses[] = "t.status_testimoni = '$filter_status_get'";
}
if (!empty($search_content_get)) {
     $where_clauses[] = "(t.isi_testimoni LIKE '%$search_content_get%' OR u.nama_lengkap LIKE '%$search_content_get%' OR u.username LIKE '%$search_content_get%')";
}

$sql_testimonies = "SELECT t.id_testimoni, u.nama_lengkap AS nama_user, u.username AS username_user, t.isi_testimoni, t.tanggal_testimoni, t.status_testimoni
                    FROM testimoni t
                    JOIN user u ON t.id_user = u.id_user";
if (!empty($where_clauses)) {
    $sql_testimonies .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql_testimonies .= " ORDER BY t.status_testimoni = 'pending' DESC, t.tanggal_testimoni DESC"; // Pending testimonials first

$result_testimonies = $conn->query($sql_testimonies);
if ($result_testimonies && $result_testimonies->num_rows > 0) {
    while ($row = $result_testimonies->fetch_assoc()) {
        $testimonies[] = $row;
    }
}

// Data untuk badge di sidebar (konsisten dengan admin/index.php)
$pending_orders_query = $conn->query("SELECT COUNT(*) as total FROM `order` WHERE status_order = 'pending'");
$pending_orders = ($pending_orders_query && $pending_orders_query->num_rows > 0) ? $pending_orders_query->fetch_assoc()['total'] : 0;
if($pending_orders_query) $pending_orders_query->close();

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;
if($pending_testimoni_query) $pending_testimoni_query->close();

$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Testimoni - Admin <?php echo NAMA_KLINIK; ?></title>
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
                <li class="nav-item"><a class="nav-link" href="kelola_layanan.php"><i class="fas fa-concierge-bell"></i> Kelola Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order <?php if ($pending_orders > 0) echo "<span class='badge bg-danger ms-1'>$pending_orders</span>"; ?></a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_testimoni.php"><i class="fas fa-comment-dots"></i> Kelola Testimoni <?php if ($pending_testimoni > 0) echo "<span class='badge bg-warning ms-1'>$pending_testimoni</span>"; ?></a></li>
            </ul>
            <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="logout_admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
        </nav>

        <div class="admin-main-content">
            <header class="admin-header">
                <h1 class="h4 mb-0 text-gray-800">Kelola Testimoni Pelanggan</h1>
                <div class="user-info">
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
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter me-2"></i>Filter dan Pencarian Testimoni</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="kelola_testimoni.php" class="row g-3 align-items-end">
                            <div class="col-md-5">
                                <label for="search_content" class="form-label">Cari Konten/Member</label>
                                <input type="text" name="search_content" id="search_content" class="form-control form-control-sm" placeholder="Isi testimoni atau nama/username member..." value="<?php echo htmlspecialchars($search_content_get); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="filter_status" class="form-label">Filter Status</label>
                                <select name="filter_status" id="filter_status" class="form-select form-select-sm">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php if ($filter_status_get == 'pending') echo 'selected'; ?>>Pending</option>
                                    <option value="approved" <?php if ($filter_status_get == 'approved') echo 'selected'; ?>>Approved</option>
                                    <option value="rejected" <?php if ($filter_status_get == 'rejected') echo 'selected'; ?>>Rejected</option>
                                </select>
                            </div>
                             <div class="col-md-3 mt-auto">
                                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fas fa-search me-1"></i> Terapkan</button>
                            </div>
                             <?php if (!empty($filter_status_get) || !empty($search_content_get)): ?>
                            <div class="col-12 mt-2">
                                <a href="kelola_testimoni.php" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i> Reset Filter</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header py-3 bg-light border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul me-2"></i>Daftar Testimoni</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTableTestimoni" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Member</th>
                                        <th scope="col">Isi Testimoni</th>
                                        <th scope="col">Tgl Kirim</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($testimonies)): ?>
                                        <?php foreach ($testimonies as $testi): ?>
                                        <tr>
                                            <td>#<?php echo $testi['id_testimoni']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($testi['nama_user']); ?><br>
                                                <small class="text-muted">@<?php echo htmlspecialchars($testi['username_user']); ?></small>
                                            </td>
                                            <td><small><?php echo nl2br(htmlspecialchars(substr($testi['isi_testimoni'], 0, 200) . (strlen($testi['isi_testimoni']) > 200 ? '...' : ''))); ?></small></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($testi['tanggal_testimoni'])); ?></td>
                                            <td class="text-center">
                                                 <span class="badge bg-<?php
                                                    switch ($testi['status_testimoni']) {
                                                        case 'pending': echo 'warning text-dark'; break;
                                                        case 'approved': echo 'success'; break;
                                                        case 'rejected': echo 'danger'; break;
                                                        default: echo 'secondary';
                                                    }
                                                ?>"><?php echo ucfirst(htmlspecialchars($testi['status_testimoni'])); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group action-dropdown">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Kelola Testimoni">
                                                        <i class="fas fa-cog"></i> <span class="d-none d-lg-inline">Aksi</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                                <input type="hidden" name="testimoni_id" value="<?php echo $testi['id_testimoni']; ?>">
                                                                <input type="hidden" name="status_testimoni" value="approved">
                                                                <button type="submit" name="update_status_testimoni" class="dropdown-item <?php if ($testi['status_testimoni'] == 'approved') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-check-circle text-success"></i>Setujui
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                                <input type="hidden" name="testimoni_id" value="<?php echo $testi['id_testimoni']; ?>">
                                                                <input type="hidden" name="status_testimoni" value="rejected">
                                                                <button type="submit" name="update_status_testimoni" class="dropdown-item <?php if ($testi['status_testimoni'] == 'rejected') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-times-circle text-danger"></i>Tolak
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $action_url; ?>" class="d-inline">
                                                                <input type="hidden" name="testimoni_id" value="<?php echo $testi['id_testimoni']; ?>">
                                                                <input type="hidden" name="status_testimoni" value="pending">
                                                                <button type="submit" name="update_status_testimoni" class="dropdown-item <?php if ($testi['status_testimoni'] == 'pending') echo 'active disabled fw-bold'; ?>">
                                                                    <i class="fas fa-clock text-warning"></i>Jadikan Pending
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" action="<?php echo $action_url; ?>" class="d-inline" onsubmit="return confirm('Anda YAKIN ingin menghapus permanen testimoni ini? Tindakan ini tidak dapat diurungkan.');">
                                                                <input type="hidden" name="testimoni_id_delete" value="<?php echo $testi['id_testimoni']; ?>">
                                                                <button type="submit" name="delete_testimoni" class="dropdown-item">
                                                                    <i class="fas fa-trash-alt text-danger"></i>Hapus Permanen
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
                                            <td colspan="6" class="text-center">
                                                <?php if(!empty($filter_status_get) || !empty($search_content_get)): ?>
                                                    Tidak ada testimoni ditemukan dengan filter/pencarian saat ini.
                                                <?php else: ?>
                                                    Belum ada data testimoni.
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