<?php
require_once '../config.php'; // Path ke config.php dari dalam folder admin

// Cek apakah admin sudah login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php?pesan=belum_login_admin");
    exit();
}

$message = '';
$message_type = '';
$upload_dir = '../images/'; // Directory untuk upload gambar layanan

// Variabel untuk form (baik tambah maupun edit)
// Inisialisasi untuk mode tambah
$form_mode = 'tambah'; // 'tambah' atau 'edit'
$form_data = [
    'id_layanan' => '',
    'nama_layanan' => '',
    'deskripsi_singkat' => '',
    'harga' => '',
    'gambar_layanan' => '', // Nama file gambar yang sudah ada (untuk edit)
    'status_layanan' => 'aktif' // Default untuk layanan baru
];

// Handle Toggle Status Layanan (Nonaktifkan/Aktifkan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['toggle_status_layanan'])) {
    $id_layanan_toggle = intval($_POST['id_layanan_toggle']);
    $current_status_toggle = $_POST['current_status_toggle'];

    $new_status_toggle = ($current_status_toggle == 'aktif') ? 'tidak_aktif' : 'aktif';

    $stmt_toggle = $conn->prepare("UPDATE layanan SET status_layanan = ? WHERE id_layanan = ?");
    $stmt_toggle->bind_param("si", $new_status_toggle, $id_layanan_toggle);
    if ($stmt_toggle->execute()) {
        $message = "Status layanan ID #$id_layanan_toggle berhasil diubah menjadi '" . ucfirst($new_status_toggle) . "'.";
        $message_type = "success";
    } else {
        $message = "Gagal mengubah status layanan: " . $stmt_toggle->error;
        $message_type = "danger";
    }
    $stmt_toggle->close();
}

// Handle Tambah atau Edit Layanan
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Mengisi $form_data dengan data POST untuk repopulate jika ada error
    $form_data['nama_layanan'] = trim($_POST['nama_layanan'] ?? '');
    $form_data['deskripsi_singkat'] = trim($_POST['deskripsi_singkat'] ?? '');
    $form_data['harga'] = trim($_POST['harga'] ?? '');
    $form_data['status_layanan'] = $_POST['status_layanan'] ?? 'aktif';
    $form_data['gambar_layanan'] = $_POST['gambar_lama'] ?? ''; // Untuk mode edit
    $form_data['id_layanan'] = $_POST['id_layanan_edit'] ?? ''; // Untuk mode edit

    if (isset($_POST['tambah_layanan']) || isset($_POST['edit_layanan_submit'])) {
        $nama_layanan_input = $conn->real_escape_string($form_data['nama_layanan']);
        $deskripsi_singkat_input = $conn->real_escape_string($form_data['deskripsi_singkat']);
        $harga_input = intval($form_data['harga']);
        $status_layanan_input = $form_data['status_layanan'];
        $gambar_lama_input = $form_data['gambar_layanan'];
        $id_layanan_edit_input = intval($form_data['id_layanan']);

        $gambar_layanan_nama_db = $gambar_lama_input; // Default ke gambar lama jika edit

        if (isset($_FILES['gambar_layanan']) && $_FILES['gambar_layanan']['error'] == UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['gambar_layanan']['tmp_name'];
            $file_name_original = basename($_FILES['gambar_layanan']['name']);
            $file_ext = strtolower(pathinfo($file_name_original, PATHINFO_EXTENSION));
            $file_name_new = uniqid('layanan_', true) . '.' . $file_ext; // Nama file unik
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_ext, $allowed_types)) {
                if (move_uploaded_file($file_tmp, $upload_dir . $file_name_new)) {
                    $gambar_layanan_nama_db = $file_name_new;
                    // Hapus gambar lama jika ini adalah proses edit dan gambar baru diupload BERHASIL
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
        } elseif ($id_layanan_edit_input > 0) { // Jika edit dan tidak ada file baru diupload, gunakan nama gambar lama
            $gambar_layanan_nama_db = $gambar_lama_input;
        } elseif (!isset($_POST['edit_layanan_submit'])) { // Jika tambah baru dan tidak ada file
            $gambar_layanan_nama_db = NULL;
        }

        if (empty($nama_layanan_input) || $harga_input <= 0) {
            $message = "Nama layanan dan harga (lebih dari 0) wajib diisi.";
            $message_type = "danger";
        } elseif (empty($message)) { // Lanjutkan jika tidak ada error dari validasi atau upload
            if ($id_layanan_edit_input > 0) { // Proses Edit
                $stmt = $conn->prepare("UPDATE layanan SET nama_layanan = ?, deskripsi_singkat = ?, harga = ?, gambar_layanan = ?, status_layanan = ? WHERE id_layanan = ?");
                $stmt->bind_param("ssisssi", $nama_layanan_input, $deskripsi_singkat_input, $harga_input, $gambar_layanan_nama_db, $status_layanan_input, $id_layanan_edit_input);
                if ($stmt->execute()) {
                    $message = "Layanan berhasil diperbarui.";
                    $message_type = "success";
                    $form_mode = 'tambah'; // Kembali ke mode tambah setelah edit sukses
                    $form_data = ['id_layanan' => '', 'nama_layanan' => '', 'deskripsi_singkat' => '', 'harga' => '', 'gambar_layanan' => '', 'status_layanan' => 'aktif']; // Reset form
                } else {
                    $message = "Gagal memperbarui layanan: " . $stmt->error;
                    $message_type = "danger";
                    $form_mode = 'edit'; // Tetap di mode edit jika gagal
                }
            } else { // Proses Tambah
                $stmt = $conn->prepare("INSERT INTO layanan (nama_layanan, deskripsi_singkat, harga, gambar_layanan, status_layanan) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiss", $nama_layanan_input, $deskripsi_singkat_input, $harga_input, $gambar_layanan_nama_db, $status_layanan_input);
                if ($stmt->execute()) {
                    $message = "Layanan baru berhasil ditambahkan.";
                    $message_type = "success";
                    $form_data = ['id_layanan' => '', 'nama_layanan' => '', 'deskripsi_singkat' => '', 'harga' => '', 'gambar_layanan' => '', 'status_layanan' => 'aktif']; // Reset form
                } else {
                    $message = "Gagal menambahkan layanan: " . $stmt->error;
                    $message_type = "danger";
                }
            }
            $stmt->close();
        }
    }
}


// Handle mode Edit (mengisi form jika ada GET parameter dan belum ada POST error)
if (isset($_GET['edit_id']) && $_SERVER["REQUEST_METHOD"] != "POST" ) {
    $form_mode = 'edit';
    $id_to_edit = intval($_GET['edit_id']);
    $stmt_edit_data = $conn->prepare("SELECT id_layanan, nama_layanan, deskripsi_singkat, harga, gambar_layanan, status_layanan FROM layanan WHERE id_layanan = ?");
    $stmt_edit_data->bind_param("i", $id_to_edit);
    $stmt_edit_data->execute();
    $result_edit = $stmt_edit_data->get_result();
    if ($result_edit->num_rows == 1) {
        $form_data = $result_edit->fetch_assoc();
    } else {
        $message = "Layanan tidak ditemukan untuk diedit.";
        $message_type = "warning";
        $form_mode = 'tambah'; // Kembali ke mode tambah jika ID edit tidak valid
    }
    $stmt_edit_data->close();
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

$pending_testimoni_query = $conn->query("SELECT COUNT(*) as total FROM testimoni WHERE status_testimoni = 'pending'");
$pending_testimoni = ($pending_testimoni_query && $pending_testimoni_query->num_rows > 0) ? $pending_testimoni_query->fetch_assoc()['total'] : 0;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Layanan - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { display: flex; min-height: 100vh; flex-direction: column; }
        .admin-sidebar { background-color: #343a40; color: white; padding-top: 1rem; min-height: 100vh; }
        .admin-sidebar .nav-link { color: #adb5bd; padding: 0.75rem 1rem; }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active { color: white; background-color: #495057; }
        .admin-sidebar .nav-link .fas { margin-right: 0.5rem; }
        .admin-content { flex-grow: 1; padding: 1rem 2rem; }
        .admin-header { background-color: #f8f9fa; padding: 1rem 1.5rem; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .table-responsive { margin-top: 1rem; }
        .img-preview { max-width: 80px; max-height: 80px; margin-top: 5px; border:1px solid #ddd; padding:2px;}
    </style>
</head>
<body>
    <div class="d-flex">
        <nav class="admin-sidebar d-none d-md-block col-md-3 col-lg-2">
            <div class="sidebar-sticky">
                <h5 class="px-3 py-2 text-white"><?php echo NAMA_KLINIK; ?></h5>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_user.php"><i class="fas fa-users"></i> Kelola Member</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="kelola_layanan.php"><i class="fas fa-concierge-bell"></i> Kelola Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_order.php"><i class="fas fa-shopping-cart"></i> Kelola Order</a></li>
                    <li class="nav-item"><a class="nav-link" href="kelola_testimoni.php"><i class="fas fa-comment-dots"></i> Kelola Testimoni <?php if ($pending_testimoni > 0) echo "<span class='badge bg-warning ms-1'>$pending_testimoni</span>"; ?></a></li>
                </ul>
                <hr class="text-secondary"><ul class="nav flex-column"><li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-globe"></i> Lihat Website</a></li><li class="nav-item"><a class="nav-link" href="logout_admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul>
            </div>
        </nav>

        <div class="admin-content col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <header class="admin-header">
                <h1 class="h4 mb-0">Kelola Layanan</h1>
                <span class="text-muted">Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            </header>

            <main class="pt-3">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><?php echo ($form_mode == 'edit') ? 'Edit Layanan' : 'Tambah Layanan Baru'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="kelola_layanan.php" enctype="multipart/form-data">
                            <?php if ($form_mode == 'edit'): ?>
                                <input type="hidden" name="id_layanan_edit" value="<?php echo htmlspecialchars($form_data['id_layanan']); ?>">
                                <input type="hidden" name="gambar_lama" value="<?php echo htmlspecialchars($form_data['gambar_layanan']); ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nama_layanan" class="form-label">Nama Layanan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_layanan" name="nama_layanan" value="<?php echo htmlspecialchars($form_data['nama_layanan']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="harga" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="harga" name="harga" value="<?php echo htmlspecialchars($form_data['harga']); ?>" required min="1">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="deskripsi_singkat" class="form-label">Deskripsi Singkat</label>
                                <textarea class="form-control" id="deskripsi_singkat" name="deskripsi_singkat" rows="3"><?php echo htmlspecialchars($form_data['deskripsi_singkat']); ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="gambar_layanan" class="form-label">Gambar Layanan <?php echo ($form_mode == 'edit') ? '(Kosongkan jika tidak ingin ganti)' : ''; ?></label>
                                    <input type="file" class="form-control" id="gambar_layanan" name="gambar_layanan" accept="image/jpeg, image/png, image/gif, image/webp">
                                    <?php if ($form_mode == 'edit' && !empty($form_data['gambar_layanan'])): ?>
                                        <div class="mt-2">
                                            <small>Gambar saat ini:</small><br>
                                            <img src="<?php echo $upload_dir . htmlspecialchars($form_data['gambar_layanan']); ?>" alt="Gambar layanan" class="img-preview img-thumbnail">
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="status_layanan" class="form-label">Status Layanan</label>
                                    <select class="form-select" id="status_layanan" name="status_layanan">
                                        <option value="aktif" <?php if ($form_data['status_layanan'] == 'aktif') echo 'selected'; ?>>Aktif</option>
                                        <option value="tidak_aktif" <?php if ($form_data['status_layanan'] == 'tidak_aktif') echo 'selected'; ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                            </div>
                            <?php if ($form_mode == 'edit'): ?>
                                <button type="submit" name="edit_layanan_submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan Perubahan</button>
                                <a href="kelola_layanan.php" class="btn btn-secondary">Batal Edit</a>
                            <?php else: ?>
                                <button type="submit" name="tambah_layanan" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Layanan</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <h5 class="mt-4">Daftar Layanan Tersedia</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Gambar</th>
                                <th>Nama Layanan</th>
                                <th>Deskripsi</th>
                                <th>Harga (Rp)</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($layanans)): ?>
                                <?php foreach ($layanans as $layanan): ?>
                                <tr>
                                    <td><?php echo $layanan['id_layanan']; ?></td>
                                    <td>
                                        <?php if (!empty($layanan['gambar_layanan'])): ?>
                                            <img src="<?php echo $upload_dir . htmlspecialchars($layanan['gambar_layanan']); ?>" alt="<?php echo htmlspecialchars($layanan['nama_layanan']); ?>" class="img-preview img-thumbnail">
                                        <?php else: ?>
                                            <small>-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($layanan['nama_layanan']); ?></td>
                                    <td><small><?php echo nl2br(htmlspecialchars($layanan['deskripsi_singkat'] ? $layanan['deskripsi_singkat'] : '-')); ?></small></td>
                                    <td><?php echo number_format($layanan['harga'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ($layanan['status_layanan'] == 'aktif') ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($layanan['status_layanan']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="kelola_layanan.php?edit_id=<?php echo $layanan['id_layanan']; ?>" class="btn btn-sm btn-warning mb-1" title="Edit"><i class="fas fa-edit"></i></a>
                                        <form method="POST" action="kelola_layanan.php" class="d-inline" onsubmit="return confirm('Anda yakin ingin mengubah status layanan ini menjadi \'<?php echo ($layanan['status_layanan'] == 'aktif') ? 'Tidak Aktif' : 'Aktif'; ?>\'?');">
                                            <input type="hidden" name="id_layanan_toggle" value="<?php echo $layanan['id_layanan']; ?>">
                                            <input type="hidden" name="current_status_toggle" value="<?php echo $layanan['status_layanan']; ?>">
                                            <button type="submit" name="toggle_status_layanan" class="btn btn-sm <?php echo ($layanan['status_layanan'] == 'aktif') ? 'btn-danger' : 'btn-success'; ?> mb-1" title="<?php echo ($layanan['status_layanan'] == 'aktif') ? 'Nonaktifkan' : 'Aktifkan'; ?>">
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
            </main>
        </div>
    </div>
    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script> <?php /* Pastikan script.js ada jika diperlukan untuk fungsi lain */ ?>
</body>
</html>
<?php
if(isset($result_layanans)) $result_layanans->close();
if(isset($result_edit)) $result_edit->close();
if(isset($pending_testimoni_query)) $pending_testimoni_query->close();
$conn->close();
?>