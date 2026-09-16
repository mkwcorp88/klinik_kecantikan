<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();

$message = '';
$message_type = '';
$upload_dir = '../images/'; // Directory untuk upload gambar layanan

// Variabel untuk form (baik tambah maupun edit)
$form_mode = 'tambah'; // 'tambah' atau 'edit'
$form_data = [
    'id_layanan' => '',
    'nama_layanan' => '',
    'deskripsi_singkat' => '',
    'harga' => '',
    'gambar_layanan' => '',
    'status_layanan' => 'aktif'
];

// Handle Toggle Status Layanan (Nonaktifkan/Aktifkan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status_layanan'])) {
    $id_layanan_toggle = intval($_POST['id_layanan_toggle']);
    $current_status_toggle = $_POST['current_status_toggle'];
    $new_status_toggle = ($current_status_toggle == 'aktif') ? 'tidak_aktif' : 'aktif';

    $stmt_toggle = $conn->prepare("UPDATE layanan SET status_layanan = ? WHERE id_layanan = ?");
    $stmt_toggle->bind_param("si", $new_status_toggle, $id_layanan_toggle);
    if ($stmt_toggle->execute()) {
        $_SESSION['flash_message'] = "Status layanan ID #$id_layanan_toggle berhasil diubah menjadi '" . ucfirst($new_status_toggle) . "'.";
        $_SESSION['flash_message_type'] = "success";
    } else {
        $_SESSION['flash_message'] = "Gagal mengubah status layanan: " . $stmt_toggle->error;
        $_SESSION['flash_message_type'] = "danger";
    }
    $stmt_toggle->close();
    header("Location: kelola_layanan.php" . (isset($_GET['edit_id']) ? '?edit_id='.intval($_GET['edit_id']) : '' ) ); // Redirect untuk refresh
    exit();
}

// Handle Tambah atau Edit Layanan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_data['nama_layanan'] = trim($_POST['nama_layanan'] ?? '');
    $form_data['deskripsi_singkat'] = trim($_POST['deskripsi_singkat'] ?? '');
    $form_data['harga'] = trim($_POST['harga'] ?? '');
    $form_data['status_layanan'] = $_POST['status_layanan'] ?? 'aktif';
    $form_data['gambar_layanan'] = $_POST['gambar_lama'] ?? '';
    $form_data['id_layanan'] = $_POST['id_layanan_edit'] ?? '';

    if (isset($_POST['tambah_layanan']) || isset($_POST['edit_layanan_submit'])) {
        $nama_layanan_input = $conn->real_escape_string($form_data['nama_layanan']);
        $deskripsi_singkat_input = $conn->real_escape_string($form_data['deskripsi_singkat']);
        $harga_input = intval($form_data['harga']);
        $status_layanan_input = $form_data['status_layanan'];
        $gambar_lama_input = $form_data['gambar_layanan'];
        $id_layanan_edit_input = intval($form_data['id_layanan']);
        $gambar_layanan_nama_db = $gambar_lama_input;

        if (isset($_FILES['gambar_layanan']) && $_FILES['gambar_layanan']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['gambar_layanan']['tmp_name'];
            $file_name_original = basename($_FILES['gambar_layanan']['name']);
            $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            $file_name_new = uniqid('layanan_', true) . '.' . $file_ext;
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_ext, $allowed_types)) {
                if (move_uploaded_file($file_tmp, $upload_dir . $file_name_new)) {
                    $gambar_layanan_nama_db = $file_name_new;
                    if ($id_layanan_edit_input > 0 && !empty($gambar_lama_input) && $gambar_lama_input != $gambar_layanan_nama_db && file_exists($upload_dir . $gambar_lama_input)) {
                        unlink($upload_dir . $gambar_lama_input);
                    }
                } else {
                    $message = "Gagal mengupload gambar baru.";
                    $message_type = "danger";
                }
            } else {
                $message = "Tipe file gambar tidak diizinkan (hanya JPG, JPEG, PNG, GIF, WEBP).";
                $message_type = "danger";
            }
        } elseif ($id_layanan_edit_input == 0 && empty($_FILES['gambar_layanan']['name'])) { // Tambah baru dan tidak ada file
             $gambar_layanan_nama_db = NULL;
        }


        if (empty($nama_layanan_input) || $harga_input <= 0) {
            $message = "Nama layanan dan harga (lebih dari 0) wajib diisi.";
            $message_type = "danger";
        } elseif (empty($message)) {
            if ($id_layanan_edit_input > 0) {
                $stmt = $conn->prepare("UPDATE layanan SET nama_layanan = ?, deskripsi_singkat = ?, harga = ?, gambar_layanan = ?, status_layanan = ? WHERE id_layanan = ?");
                $stmt->bind_param("ssissi", $nama_layanan_input, $deskripsi_singkat_input, $harga_input, $gambar_layanan_nama_db, $status_layanan_input, $id_layanan_edit_input);
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "Layanan berhasil diperbarui.";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    $_SESSION['flash_message'] = "Gagal memperbarui layanan: " . $stmt->error;
                    $_SESSION['flash_message_type'] = "danger";
                }
                $stmt->close();
                header("Location: kelola_layanan.php?edit_id=".$id_layanan_edit_input); // Kembali ke mode edit dengan pesan
                exit();
            } else {
                $stmt = $conn->prepare("INSERT INTO layanan (nama_layanan, deskripsi_singkat, harga, gambar_layanan, status_layanan) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiss", $nama_layanan_input, $deskripsi_singkat_input, $harga_input, $gambar_layanan_nama_db, $status_layanan_input);
                if ($stmt->execute()) {
                    $_SESSION['flash_message'] = "Layanan baru berhasil ditambahkan.";
                    $_SESSION['flash_message_type'] = "success";
                } else {
                    $_SESSION['flash_message'] = "Gagal menambahkan layanan: " . $stmt->error;
                    $_SESSION['flash_message_type'] = "danger";
                }
                $stmt->close();
                header("Location: kelola_layanan.php"); // Kembali ke halaman kelola layanan
                exit();
            }
        }
    }
}


// Ambil pesan flash dari session
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_message_type'];
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_type']);
}

// Handle mode Edit (mengisi form jika ada GET parameter)
// Harus dijalankan setelah handle POST untuk memastikan data terbaru ditampilkan jika ada error POST saat edit
if (isset($_GET['edit_id'])) {
    $id_to_edit = intval($_GET['edit_id']);
    // Hanya isi $form_data dari DB jika tidak ada error POST sebelumnya (yang berarti user mencoba submit perubahan)
    if (!($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_layanan_submit']) && !empty($message) && $message_type == "danger" && $form_data['id_layanan'] == $id_to_edit )) {
        $stmt_edit_data = $conn->prepare("SELECT id_layanan, nama_layanan, deskripsi_singkat, harga, gambar_layanan, status_layanan FROM layanan WHERE id_layanan = ?");
        $stmt_edit_data->bind_param("i", $id_to_edit);
        $stmt_edit_data->execute();
        $result_edit = $stmt_edit_data->get_result();
        if ($result_edit->num_rows == 1) {
            $form_data = $result_edit->fetch_assoc();
            $form_mode = 'edit';
        } else {
            // Jika id_layanan untuk edit tidak ditemukan, tampilkan pesan dan reset ke mode tambah
            if (empty($message)) { // Hindari menimpa pesan error dari POST
                 $_SESSION['flash_message'] = "Layanan dengan ID $id_to_edit tidak ditemukan.";
                 $_SESSION['flash_message_type'] = "warning";
            }
            header("Location: kelola_layanan.php");
            exit();
        }
        $stmt_edit_data->close();
    } else {
        // Jika ada error POST saat edit, $form_data sudah diisi dari POST di atas
        $form_mode = 'edit';
    }
}


// Ambil semua layanan untuk ditampilkan (termasuk yang tidak aktif untuk admin)
$layanans = [];
$sql_layanans = "SELECT id_layanan, nama_layanan, deskripsi_singkat, harga, gambar_layanan, status_layanan FROM layanan ORDER BY nama_layanan ASC";
$result_layanans = $conn->query($sql_layanans);
if ($result_layanans && $result_layanans->num_rows > 0) {
    while ($row = $result_layanans->fetch_assoc()) {
        $layanans[] = $row;
    }
}

// Data untuk badge di sidebar (order difilter klinik aktif)
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

// $conn->close(); // Ditutup di akhir file
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Layanan - Admin <?php echo NAMA_KLINIK; ?></title>
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
        .img-preview { max-width: 70px; max-height: 70px; margin-top: 5px; border:1px solid #ddd; padding:2px; border-radius: .25rem; }
        .action-dropdown .dropdown-item { display: flex; align-items: center; font-size: 0.9rem; }
        .action-dropdown .dropdown-item i.fas { width: 20px; margin-right: 0.5rem; }
        .admin-footer { background-color: #e9ecef; padding: 1rem 2rem; font-size: 0.875rem; border-top: 1px solid #dee2e6; }
        @media (max-width: 767px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; box-shadow: none; }
            .admin-main-content { margin-left: 0; width: 100%; }
            .admin-header { position: static; }
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
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_user.php"><i class="fas fa-users"></i> Kelola Member</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_layanan.php"><i class="fas fa-concierge-bell"></i> Kelola Layanan</a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order <?php if ($pending_orders > 0) echo "<span class='badge bg-danger ms-1'>$pending_orders</span>"; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="kelola_afiliasi.php"><i class="fas fa-handshake"></i> Afiliator</a></li>
            </ul>
            <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="logout_admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
        </nav>

        <div class="admin-main-content">
            <header class="admin-header">
                <h1 class="h4 mb-0 text-gray-800">Kelola Data Layanan</h1>
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
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas <?php echo ($form_mode == 'edit') ? 'fa-edit' : 'fa-plus-circle'; ?> me-2"></i><?php echo ($form_mode == 'edit') ? 'Edit Layanan (ID: '.htmlspecialchars($form_data['id_layanan']).')' : 'Tambah Layanan Baru'; ?></h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="kelola_layanan.php<?php if ($form_mode == 'edit') echo '?edit_id='.htmlspecialchars($form_data['id_layanan']); ?>" enctype="multipart/form-data">
                            <?php if ($form_mode == 'edit'): ?>
                                <input type="hidden" name="id_layanan_edit" value="<?php echo htmlspecialchars($form_data['id_layanan']); ?>">
                                <input type="hidden" name="gambar_lama" value="<?php echo htmlspecialchars($form_data['gambar_layanan']); ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nama_layanan" class="form-label">Nama Layanan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="nama_layanan" name="nama_layanan" value="<?php echo htmlspecialchars($form_data['nama_layanan']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="harga" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="harga" name="harga" value="<?php echo htmlspecialchars($form_data['harga']); ?>" required min="1">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="deskripsi_singkat" class="form-label">Deskripsi Singkat</label>
                                <textarea class="form-control form-control-sm" id="deskripsi_singkat" name="deskripsi_singkat" rows="3"><?php echo htmlspecialchars($form_data['deskripsi_singkat']); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gambar_layanan" class="form-label">Gambar Layanan <?php echo ($form_mode == 'edit') ? '(Kosongkan jika tidak ganti)' : ''; ?></label>
                                    <input type="file" class="form-control form-control-sm" id="gambar_layanan" name="gambar_layanan" accept="image/jpeg, image/png, image/gif, image/webp">
                                    <?php if ($form_mode == 'edit' && !empty($form_data['gambar_layanan']) && file_exists($upload_dir . $form_data['gambar_layanan'])): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo $upload_dir . htmlspecialchars($form_data['gambar_layanan']); ?>?t=<?php echo time(); // Cache buster ?>" alt="Gambar layanan saat ini" class="img-preview img-thumbnail">
                                        </div>
                                    <?php elseif($form_mode == 'edit' && !empty($form_data['gambar_layanan'])): ?>
                                         <div class="mt-2 text-danger small">Gambar saat ini (<?php echo htmlspecialchars($form_data['gambar_layanan']); ?>) tidak ditemukan.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status_layanan" class="form-label">Status Layanan</label>
                                    <select class="form-select form-select-sm" id="status_layanan" name="status_layanan">
                                        <option value="aktif" <?php if ($form_data['status_layanan'] == 'aktif') echo 'selected'; ?>>Aktif</option>
                                        <option value="tidak_aktif" <?php if ($form_data['status_layanan'] == 'tidak_aktif') echo 'selected'; ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <?php if ($form_mode == 'edit'): ?>
                                <button type="submit" name="edit_layanan_submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i> Simpan Perubahan</button>
                                <a href="kelola_layanan.php" class="btn btn-secondary btn-sm"><i class="fas fa-times me-1"></i> Batal Edit</a>
                            <?php else: ?>
                                <button type="submit" name="tambah_layanan" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i> Tambah Layanan</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header py-3 bg-light border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list-ul me-2"></i>Daftar Layanan Tersedia</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="dataTableLayanan" width="100%" cellspacing="0">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Gambar</th>
                                        <th>Nama Layanan</th>
                                        <th>Deskripsi</th>
                                        <th>Harga (Rp)</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($layanans)): ?>
                                        <?php foreach ($layanans as $layanan): ?>
                                        <tr>
                                            <td><?php echo $layanan['id_layanan']; ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($layanan['gambar_layanan']) && file_exists($upload_dir . $layanan['gambar_layanan'])): ?>
                                                    <img src="<?php echo $upload_dir . htmlspecialchars($layanan['gambar_layanan']); ?>?t=<?php echo time(); // Cache buster ?>" alt="<?php echo htmlspecialchars($layanan['nama_layanan']); ?>" class="img-preview img-thumbnail">
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($layanan['nama_layanan']); ?></td>
                                            <td><small><?php echo nl2br(htmlspecialchars($layanan['deskripsi_singkat'] ? $layanan['deskripsi_singkat'] : '-')); ?></small></td>
                                            <td class="text-end"><?php echo number_format($layanan['harga'], 0, ',', '.'); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo ($layanan['status_layanan'] == 'aktif') ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($layanan['status_layanan']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <a href="kelola_layanan.php?edit_id=<?php echo $layanan['id_layanan']; ?>" class="btn btn-sm btn-outline-warning mb-1" title="Edit Layanan">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" action="kelola_layanan.php" class="d-inline" onsubmit="return confirm('Anda yakin ingin mengubah status layanan \'<?php echo htmlspecialchars(addslashes($layanan['nama_layanan'])); ?>\' menjadi \'<?php echo ($layanan['status_layanan'] == 'aktif') ? 'Tidak Aktif' : 'Aktif'; ?>\'?');">
                                                    <input type="hidden" name="id_layanan_toggle" value="<?php echo $layanan['id_layanan']; ?>">
                                                    <input type="hidden" name="current_status_toggle" value="<?php echo $layanan['status_layanan']; ?>">
                                                    <button type="submit" name="toggle_status_layanan" class="btn btn-sm <?php echo ($layanan['status_layanan'] == 'aktif') ? 'btn-outline-danger' : 'btn-outline-success'; ?> mb-1" title="<?php echo ($layanan['status_layanan'] == 'aktif') ? 'Nonaktifkan' : 'Aktifkan'; ?>">
                                                        <i class="fas <?php echo ($layanan['status_layanan'] == 'aktif') ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">Belum ada data layanan. Silakan tambahkan layanan baru.</td>
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
<?php
if(isset($result_layanans) && $result_layanans) $result_layanans->close();
if(isset($result_edit) && $result_edit) $result_edit->close(); // Jika ada result_edit
// $conn->close(); // Sudah ditutup di awal setelah semua query selesai
?>
