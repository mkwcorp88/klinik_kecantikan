<?php
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin");
    exit();
}

$search_query = "";
$users = [];

if (isset($_GET['search'])) {
    $search_term = $conn->real_escape_string(trim($_GET['search']));
    $search_query = " WHERE nama_lengkap LIKE '%$search_term%' OR username LIKE '%$search_term%' OR email LIKE '%$search_term%'";
}

$sql_users = "SELECT id_user, nama_lengkap, username, email, no_telepon, alamat, tanggal_daftar FROM user" . $search_query . " ORDER BY tanggal_daftar DESC";
$result_users = $conn->query($sql_users);

if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Member - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { display: flex; min-height: 100vh; flex-direction: column; }
        .admin-sidebar { background-color: #343a40; color: white; padding-top: 1rem; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1rem; }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { color: white; background-color: #495057; }
        .admin-sidebar .nav-link .fas { margin-right: 0.5rem; }
        .admin-content { flex-grow: 1; padding: 1rem 2rem; } /* Adjusted padding */
        .admin-header { background-color: #f8f9fa; padding: 1rem 1.5rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .table-responsive { margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="d-flex">
        <nav class="admin-sidebar d-none d-md-block col-md-3 col-lg-2">
            <div class="sidebar-sticky">
                <h5 class="px-3 py-2 text-white"><?php echo NAMA_KLINIK; ?></h5>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_user.php"><i class="fas fa-users"></i> Kelola Member</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_layanan.php"><i class="fas fa-concierge-bell"></i> Kelola Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_testimoni.php"><i class="fas fa-comment-dots"></i> Kelola Testimoni <?php if ($pending_testimoni > 0) echo "<span class='badge bg-warning ms-1'>$pending_testimoni</span>"; ?></a></li>
                </ul>
                <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
            </div>
        </nav>

        <div class="admin-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <header class="admin-header">
                <h1 class="h4 mb-0">Kelola Member</h1>
                <span class="text-muted">Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </header>

            <main class="pt-3">
                <form method="GET" action="kelola_user.php" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, username, atau email..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Cari</button>
                         <?php if (isset($_GET['search'])): ?>
                            <a href="kelola_user.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
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
                                    <td><?php echo nl2br(htmlspecialchars($user['alamat'])); ?></td>
                                    <td><?php echo date('d M Y H:i', strtotime($user['tanggal_daftar'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">Tidak ada data member ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>