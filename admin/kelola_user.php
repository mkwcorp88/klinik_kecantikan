<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin");
    exit();
}

$search_query_sql = "";
$users = [];
$search_term_get = ''; // Untuk repopulate search box

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search_term_get = trim($_GET['search']);
    $search_term_sql = $conn->real_escape_string($search_term_get);
    $search_query_sql = " WHERE nama_lengkap LIKE '%$search_term_sql%' OR username LIKE '%$search_term_sql%' OR email LIKE '%$search_term_sql%' OR no_telepon LIKE '%$search_term_sql%'";
}

$sql_users = "SELECT id_user, nama_lengkap, username, email, no_telepon, alamat, tanggal_daftar FROM user" . $search_query_sql . " ORDER BY tanggal_daftar DESC";
$result_users = $conn->query($sql_users);

if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
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
    <title>Kelola Member - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        /* Gaya Admin Dashboard Spesifik (Sama seperti di admin/index.php) */
        /* Sebaiknya ini dipindahkan ke file CSS admin terpisah */
        body {
            background-color: #f4f7f6;
        }
        .admin-sidebar {
            background-color: #2c3e50;
            color: white;
            padding-top: 0;
            min-height: 100vh;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            width: 250px;
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
            color: #bdc3c7;
            padding: 0.9rem 1.5rem;
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .admin-sidebar .nav-link:hover {
            color: #fff;
            background-color: #34495e;
            border-left-color: #1abc9c;
        }
        .admin-sidebar .nav-link.active {
            color: #fff;
            background-color: #1abc9c;
            border-left-color: #fff;
        }
        .admin-sidebar .nav-link .fas {
            margin-right: 0.8rem;
            width: 20px;
            text-align: center;
        }
         .admin-sidebar hr.text-secondary {
            border-top: 1px solid #3a506b;
        }

        .admin-main-content {
            margin-left: 250px;
            padding: 0;
            width: calc(100% - 250px);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .admin-header {
            background-color: #fff;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 99;
        }
         .admin-header .user-info span {
            font-size: 0.9rem;
        }

        .admin-content-area {
            padding: 2rem;
            flex-grow: 1; /* Membuat konten mengisi ruang yang tersedia */
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .admin-footer {
            background-color: #e9ecef; /* Warna footer admin lebih terang */
            padding: 1rem 2rem;
            font-size: 0.875rem;
            border-top: 1px solid #dee2e6;
        }
        
        @media (max-width: 767px) {
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
                position: static;
            }
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
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="kelola_user.php">
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
                <h1 class="h4 mb-0 text-gray-800">Kelola Data Member</h1>
                <div class="user-info">
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-search me-2"></i>Pencarian Member</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="kelola_user.php" class="row gx-3 gy-2 align-items-center">
                            <div class="col-sm-9">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari berdasarkan nama, username, email, atau no. telepon..." value="<?php echo htmlspecialchars($search_term_get); ?>">
                            </div>
                            <div class="col-sm-3">
                                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fas fa-search"></i> Cari</button>
                            </div>
                             <?php if (!empty($search_term_get)): ?>
                            <div class="col-12 mt-2">
                                <a href="kelola_user.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset Pencarian</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul me-2"></i>Daftar Member Terdaftar</h6>
                        </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTableUsers" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Lengkap</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>No. Telepon</th>
                                        <th>Alamat</th>
                                        <th>Tgl Daftar</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($users)): ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id_user']; ?></td>
                                            <td><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ? $user['email'] : '-'); ?></td>
                                            <td><?php echo htmlspecialchars($user['no_telepon']); ?></td>
                                            <td><small><?php echo nl2br(htmlspecialchars($user['alamat'])); ?></small></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($user['tanggal_daftar'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">
                                                <?php if(!empty($search_term_get)): ?>
                                                    Tidak ada member ditemukan untuk kata kunci "<?php echo htmlspecialchars($search_term_get); ?>".
                                                <?php else: ?>
                                                    Belum ada data member terdaftar.
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