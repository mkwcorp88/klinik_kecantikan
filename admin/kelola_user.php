<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();
$csrfToken = drw_csrf_token();

$users = [];
$searchTerm = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$page = isset($_GET['page']) && ctype_digit((string) $_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 50;
$totalUsers = 0;
$totalPages = 1;
$branches = drw_admin_fetch_branches($conn);

$whereClauses = [];
$queryTypes = '';
$queryParams = [];

if ($adminCabangId !== null && $adminCabangId !== -1) {
    $whereClauses[] = '(u.id_cabang = ? OR EXISTS (SELECT 1 FROM `order` o_cabang WHERE o_cabang.id_user = u.id_user AND o_cabang.id_cabang = ?))';
    $queryTypes .= 'ii';
    $queryParams[] = $adminCabangId;
    $queryParams[] = $adminCabangId;
} elseif ($adminCabangId === -1) {
    // Fail closed: branch admin with missing scope sees nothing.
    $whereClauses[] = '1=0';
}

if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $whereClauses[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.no_telepon LIKE ? OR u.aido_mr LIKE ?)';
    $queryTypes .= 'sssss';
    $queryParams[] = $searchLike;
    $queryParams[] = $searchLike;
    $queryParams[] = $searchLike;
    $queryParams[] = $searchLike;
    $queryParams[] = $searchLike;
}

$whereSql = $whereClauses === [] ? '' : ' WHERE ' . implode(' AND ', $whereClauses);
$stmt = $conn->prepare('SELECT COUNT(*) AS total FROM user u' . $whereSql);
if ($queryTypes !== '') {
    $stmt->bind_param($queryTypes, ...$queryParams);
}
$stmt->execute();
$totalUsers = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int) ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare('SELECT u.id_user, u.nama_lengkap, u.username, u.email, u.no_telepon, u.alamat, u.aido_mr, u.tanggal_daftar, c.nama_cabang AS member_cabang FROM user u LEFT JOIN cabang c ON c.id_cabang = u.id_cabang' . $whereSql . ' ORDER BY u.tanggal_daftar DESC, u.id_user DESC LIMIT ? OFFSET ?');
$listTypes = $queryTypes . 'ii';
$listParams = array_merge($queryParams, [$perPage, $offset]);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$resultUsers = $stmt->get_result();
while ($row = $resultUsers->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

if ($adminCabangId === null) {
    $pendingOrdersQuery = $conn->query("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending'");
    $pending_orders = (int) ($pendingOrdersQuery->fetch_assoc()['total'] ?? 0);
    $pendingOrdersQuery->close();
} elseif ($adminCabangId === -1) {
    $pending_orders = 0;
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending' AND id_cabang = ?");
    $stmt->bind_param('i', $adminCabangId);
    $stmt->execute();
    $pending_orders = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

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
                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-clinic-medical me-1"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
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
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari nama, MR AIDO, username, email, atau no. telepon..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            <div class="col-sm-3">
                                <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fas fa-search"></i> Cari</button>
                            </div>
                             <?php if ($searchTerm !== ''): ?>
                            <div class="col-12 mt-2">
                                <a href="kelola_user.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Reset Pencarian</a>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-export me-2"></i>Export Member</h6>
                        <span class="badge bg-light text-dark border">CSV UTF-8 (Excel)</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="export_member.php" target="_blank" class="row gx-3 gy-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                            <div class="col-md-3">
                                <label for="export_type" class="form-label">Data yang diexport</label>
                                <select class="form-select form-select-sm" id="export_type" name="export_type">
                                    <option value="members">Member saja</option>
                                    <option value="transactions">Member + riwayat transaksi</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="source" class="form-label">Sumber member</label>
                                <select class="form-select form-select-sm" id="source" name="source">
                                    <option value="all">Semua sumber</option>
                                    <option value="aido">Import AIDO</option>
                                    <option value="website">Website/manual</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="member_state" class="form-label">Riwayat transaksi</label>
                                <select class="form-select form-select-sm" id="member_state" name="member_state">
                                    <option value="all">Semua member</option>
                                    <option value="with_order">Punya transaksi</option>
                                    <option value="without_order">Belum punya transaksi</option>
                                </select>
                            </div>
                            <div class="col-md-3 js-tx-field">
                                <label for="order_status" class="form-label">Status transaksi</label>
                                <select class="form-select form-select-sm" id="order_status" name="order_status">
                                    <option value="all">Semua status</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <?php if ($adminCabangId === null): ?>
                                <div class="col-md-3">
                                    <label for="id_cabang" class="form-label">Klinik</label>
                                    <select class="form-select form-select-sm" id="id_cabang" name="id_cabang">
                                        <option value="">Semua klinik</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo (int) $branch['id_cabang']; ?>"><?php echo htmlspecialchars($branch['nama_cabang']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="id_cabang" value="<?php echo (int) $adminCabangId; ?>">
                                <div class="col-md-3">
                                    <label class="form-label">Klinik</label>
                                    <div class="form-control form-control-sm bg-light"><?php echo htmlspecialchars($adminCabangNama); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label for="member_date_from" class="form-label">Daftar member dari</label>
                                <input type="date" class="form-control form-control-sm" id="member_date_from" name="member_date_from">
                            </div>
                            <div class="col-md-3">
                                <label for="member_date_to" class="form-label">Daftar member sampai</label>
                                <input type="date" class="form-control form-control-sm" id="member_date_to" name="member_date_to">
                            </div>
                            <div class="col-md-3 js-tx-field">
                                <label for="transaction_date_from" class="form-label">Transaksi dari</label>
                                <input type="date" class="form-control form-control-sm" id="transaction_date_from" name="transaction_date_from">
                            </div>
                            <div class="col-md-3 js-tx-field">
                                <label for="transaction_date_to" class="form-label">Transaksi sampai</label>
                                <input type="date" class="form-control form-control-sm" id="transaction_date_to" name="transaction_date_to">
                            </div>
                            <fieldset class="col-12">
                                <legend class="fs-6 mb-2">Kolom yang diexport</legend>
                                <div class="row row-cols-2 row-cols-md-4 g-2">
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="name" checked> <span class="form-check-label">Nama</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="source" checked> <span class="form-check-label">Sumber</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="aido_mr" checked> <span class="form-check-label">MR AIDO</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="email" checked> <span class="form-check-label">Email</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="phone" checked> <span class="form-check-label">No. telepon</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="address"> <span class="form-check-label">Alamat</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="member_branch" checked> <span class="form-check-label">Klinik member</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="registered_at" checked> <span class="form-check-label">Tgl daftar</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="transaction_id" checked> <span class="form-check-label">ID transaksi</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="transaction_date" checked> <span class="form-check-label">Tgl transaksi</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="service" checked> <span class="form-check-label">Layanan</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="transaction_status" checked> <span class="form-check-label">Status transaksi</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="transaction_branch"> <span class="form-check-label">Klinik transaksi</span></label></div>
                                    <div class="col"><label class="form-check"><input class="form-check-input js-tx-col" type="checkbox" name="columns[]" value="notes"> <span class="form-check-label">Catatan transaksi</span></label></div>
                                </div>
                                <div class="form-text mt-2">Kolom transaksi hanya diisi saat memilih “Member + riwayat transaksi”. Catatan transaksi dapat memuat informasi sensitif.</div>
                            </fieldset>
                            <div class="col-12 d-flex justify-content-end">
                                <button class="btn btn-success" type="submit"><i class="fas fa-download me-1"></i> Unduh CSV</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul me-2"></i>Daftar Member Terdaftar</h6>
                        <span class="badge bg-light text-dark border"><?php echo number_format($totalUsers, 0, ',', '.'); ?> member</span>
                        </div>
                    <div class="card-body">
                        <p class="small text-muted">Menampilkan <?php echo $totalUsers === 0 ? 0 : $offset + 1; ?>-<?php echo min($offset + $perPage, $totalUsers); ?> dari <?php echo number_format($totalUsers, 0, ',', '.'); ?> member.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTableUsers" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama Lengkap</th>
                                        <th>Sumber</th>
                                        <th>MR AIDO</th>
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
                                            <td><?php echo $user['aido_mr'] !== null ? '<span class="badge bg-info text-dark">AIDO</span>' : '<span class="badge bg-secondary">Website</span>'; ?></td>
                                            <td><?php echo htmlspecialchars($user['aido_mr'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ? $user['email'] : '-'); ?></td>
                                            <td><?php echo htmlspecialchars($user['no_telepon']); ?></td>
                                            <td><small><?php echo nl2br(htmlspecialchars($user['alamat'])); ?></small></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($user['tanggal_daftar'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">
                                                <?php if ($searchTerm !== ''): ?>
                                                    Tidak ada member ditemukan untuk kata kunci "<?php echo htmlspecialchars($searchTerm); ?>".
                                                <?php else: ?>
                                                    Belum ada data member terdaftar.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-3" aria-label="Halaman member">
                                <ul class="pagination pagination-sm mb-0 justify-content-end">
                                    <?php
                                    $firstPage = max(1, $page - 2);
                                    $lastPage = min($totalPages, $page + 2);
                                    $pageUrl = static function (int $targetPage) use ($searchTerm): string {
                                        $query = ['page' => $targetPage];
                                        if ($searchTerm !== '') {
                                            $query['search'] = $searchTerm;
                                        }
                                        return 'kelola_user.php?' . http_build_query($query);
                                    };
                                    ?>
                                    <li class="page-item <?php echo $page === 1 ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pageUrl(max(1, $page - 1))); ?>">Sebelumnya</a></li>
                                    <?php for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++): ?>
                                        <li class="page-item <?php echo $pageNumber === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pageUrl($pageNumber)); ?>"><?php echo $pageNumber; ?></a></li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page === $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo htmlspecialchars($pageUrl(min($totalPages, $page + 1))); ?>">Berikutnya</a></li>
                                </ul>
                            </nav>
                        <?php endif; ?>
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

        </div> </div>     <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var exportTypeSelect = document.getElementById('export_type');
        if (!exportTypeSelect) return;
        var txFields = document.querySelectorAll('.js-tx-field');
        var txCols = document.querySelectorAll('.js-tx-col');

        function updateExportForm() {
            var isTx = exportTypeSelect.value === 'transactions';
            txFields.forEach(function (el) {
                el.style.display = isTx ? '' : 'none';
                var input = el.querySelector('input, select');
                if (input) input.disabled = !isTx;
            });
            txCols.forEach(function (el) {
                el.disabled = !isTx;
                var parent = el.closest('.col');
                if (parent) {
                    parent.style.opacity = isTx ? '1' : '0.4';
                }
            });
        }

        exportTypeSelect.addEventListener('change', updateExportForm);
        updateExportForm();
    });
    </script>
</body>
</html>
