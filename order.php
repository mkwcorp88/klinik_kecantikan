<?php
declare(strict_types=1);

require_once 'config.php';

$returnQuery = [];
if (isset($_GET['id_layanan']) && ctype_digit((string) $_GET['id_layanan'])) {
    $returnQuery['id_layanan'] = (int) $_GET['id_layanan'];
}
if (isset($_GET['cabang']) && preg_match('/^[a-z0-9-]{2,50}$/', (string) $_GET['cabang'])) {
    $returnQuery['cabang'] = (string) $_GET['cabang'];
}
$returnPath = 'order.php' . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
drw_require_member('Silakan masuk dengan Google untuk mengajukan booking konsultasi.', $returnPath);

$userId = (int) $_SESSION['user_id'];
$errors = [];
$flash = drw_consume_flash();
$csrfToken = drw_csrf_token();
if (!isset($_SESSION['booking_form_token']) || !is_string($_SESSION['booking_form_token'])) {
    $_SESSION['booking_form_token'] = bin2hex(random_bytes(32));
}
$bookingFormToken = $_SESSION['booking_form_token'];

$statement = $conn->prepare('SELECT nama_lengkap, no_telepon FROM user WHERE id_user = ? LIMIT 1');
$statement->bind_param('i', $userId);
$statement->execute();
$patient = $statement->get_result()->fetch_assoc();
$statement->close();

if (!$patient) {
    $_SESSION = [];
    session_destroy();
    drw_flash('danger', 'Sesi pasien tidak valid. Silakan masuk kembali.');
    header('Location: ' . drw_app_url('login.php'));
    exit();
}

$services = [];
$resultServices = $conn->query("SELECT id_layanan, nama_layanan FROM layanan WHERE status_layanan = 'aktif' ORDER BY nama_layanan ASC");
while ($service = $resultServices->fetch_assoc()) {
    $services[(int) $service['id_layanan']] = $service;
}
$resultServices->close();

$branches = [];
$branchesBySlug = [];
$resultBranches = $conn->query("SELECT id_cabang, slug, nama_cabang FROM cabang WHERE status_cabang = 'aktif' ORDER BY nama_cabang ASC");
while ($branch = $resultBranches->fetch_assoc()) {
    $branch['id_cabang'] = (int) $branch['id_cabang'];
    $branches[$branch['id_cabang']] = $branch;
    $branchesBySlug[$branch['slug']] = $branch;
}
$resultBranches->close();

$selectedServiceId = isset($_POST['id_layanan'])
    ? (int) $_POST['id_layanan']
    : (int) ($returnQuery['id_layanan'] ?? 0);
$selectedBranchId = isset($_POST['id_cabang']) ? (int) $_POST['id_cabang'] : 0;

if ($selectedBranchId === 0 && isset($returnQuery['cabang'], $branchesBySlug[$returnQuery['cabang']])) {
    $selectedBranchId = $branchesBySlug[$returnQuery['cabang']]['id_cabang'];
}

$bookingDate = trim((string) ($_POST['tanggal_treatment'] ?? ''));
$bookingDateForDatabase = null;
$notes = trim((string) ($_POST['catatan_tambahan'] ?? ''));
$phone = trim((string) ($_POST['no_telepon'] ?? ($patient['no_telepon'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sesi formulir telah berakhir. Silakan muat ulang halaman dan coba lagi.';
    } elseif (!is_string($_POST['booking_form_token'] ?? null) || !hash_equals($bookingFormToken, $_POST['booking_form_token'])) {
        $errors[] = 'Booking ini sudah diproses atau formulir telah kedaluwarsa. Silakan coba lagi dari formulir terbaru.';
    } else {
        unset($_SESSION['booking_form_token']);
    }
    if (!isset($services[$selectedServiceId])) {
        $errors[] = 'Silakan pilih perawatan yang tersedia.';
    }
    if (!isset($branches[$selectedBranchId])) {
        $errors[] = 'Silakan pilih cabang tujuan.';
    }
    if (!preg_match('/^\+?[0-9]{8,15}$/', preg_replace('/[\s()-]/', '', $phone))) {
        $errors[] = 'Masukkan nomor WhatsApp aktif dengan format yang benar.';
    }
    if ($bookingDate === '') {
        $errors[] = 'Silakan pilih tanggal dan waktu kunjungan.';
    } else {
        $timezone = new DateTimeZone('Asia/Jakarta');
        $selectedDateTime = DateTime::createFromFormat('!Y-m-d\TH:i', $bookingDate, $timezone);
        $dateErrors = DateTime::getLastErrors();
        if ($selectedDateTime === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
            $errors[] = 'Format tanggal dan waktu kunjungan tidak valid.';
        } elseif ($selectedDateTime < new DateTime('now', $timezone)) {
            $errors[] = 'Tanggal dan waktu kunjungan tidak boleh di masa lalu.';
        } else {
            $bookingDateForDatabase = $selectedDateTime->format('Y-m-d H:i:s');
        }
    }

    if ($errors === []) {
        $statement = $conn->prepare("SELECT id_order FROM `order` WHERE id_user = ? AND id_layanan = ? AND id_cabang = ? AND tanggal_treatment = ? AND status_order IN ('pending', 'confirmed') LIMIT 1");
        $statement->bind_param('iiis', $userId, $selectedServiceId, $selectedBranchId, $bookingDateForDatabase);
        $statement->execute();
        $existingBooking = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($existingBooking) {
            $errors[] = 'Anda sudah memiliki booking aktif untuk cabang, layanan, dan jadwal tersebut.';
        }
    }

    if ($errors === []) {
        $normalizedPhone = preg_replace('/[\s()-]/', '', $phone);
        if ($normalizedPhone !== ($patient['no_telepon'] ?? '')) {
            $statement = $conn->prepare('UPDATE user SET no_telepon = ? WHERE id_user = ?');
            $statement->bind_param('si', $normalizedPhone, $userId);
            $statement->execute();
            $statement->close();
        }

        $statement = $conn->prepare("INSERT INTO `order` (id_user, id_layanan, id_cabang, tanggal_treatment, catatan_tambahan, status_order) VALUES (?, ?, ?, ?, ?, 'pending')");
        $statement->bind_param('iiiss', $userId, $selectedServiceId, $selectedBranchId, $bookingDateForDatabase, $notes);
        $statement->execute();
        $statement->close();

        drw_flash('success', 'Booking konsultasi berhasil diajukan. Tim cabang akan mengonfirmasi jadwal Anda.');
        header('Location: ' . drw_app_url('riwayat_order.php'));
        exit();
    }
}

$bookingFormToken = $_SESSION['booking_form_token'] ?? bin2hex(random_bytes(32));
$_SESSION['booking_form_token'] = $bookingFormToken;
$minimumDateTime = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d\TH:i');
$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Konsultasi - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php"><?php echo NAMA_KLINIK; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Buka navigasi"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php#perawatan">Perawatan</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="order.php">Booking</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?></a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                            <li><a class="dropdown-item" href="riwayat_order.php">Riwayat Booking</a></li>
                            <li><a class="dropdown-item" href="profil.php">Profil Saya</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container my-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-4">
                    <p class="text-uppercase text-danger fw-bold small mb-2">Konsultasi Gratis</p>
                    <h1 class="h2 fw-bold">Ajukan Booking Kunjungan</h1>
                    <p class="text-muted mb-0">Pilih cabang, perawatan, dan waktu yang Anda inginkan. Tim kami akan mengonfirmasi ketersediaannya.</p>
                </div>

                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php foreach ($errors as $error): ?><p class="mb-0"><?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0">
                    <div class="card-body p-4 p-md-5">
                        <?php if ($services === [] || $branches === []): ?>
                            <div class="alert alert-warning mb-0">Data perawatan atau cabang belum tersedia. Hubungi admin untuk melengkapi data awal.</div>
                        <?php else: ?>
                            <form action="order.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <input type="hidden" name="booking_form_token" value="<?php echo htmlspecialchars($bookingFormToken); ?>">
                                <div class="mb-3">
                                    <label for="id_cabang" class="form-label">Pilih Cabang <span class="text-danger">*</span></label>
                                    <select class="form-select" id="id_cabang" name="id_cabang" required>
                                        <option value="">-- Pilih Cabang --</option>
                                        <?php foreach ($branches as $branch): ?>
                                            <option value="<?php echo $branch['id_cabang']; ?>" <?php echo $branch['id_cabang'] === $selectedBranchId ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['nama_cabang']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="id_layanan" class="form-label">Pilih Perawatan <span class="text-danger">*</span></label>
                                    <select class="form-select" id="id_layanan" name="id_layanan" required>
                                        <option value="">-- Pilih Perawatan --</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service['id_layanan']; ?>" <?php echo (int) $service['id_layanan'] === $selectedServiceId ? 'selected' : ''; ?>><?php echo htmlspecialchars($service['nama_layanan']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Rekomendasi perawatan mengikuti hasil konsultasi dokter.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="no_telepon" class="form-label">Nomor WhatsApp <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="no_telepon" name="no_telepon" inputmode="tel" required value="<?php echo htmlspecialchars($phone); ?>" placeholder="Contoh: 081234567890">
                                    <div class="form-text">Nomor ini dipakai cabang untuk konfirmasi jadwal Anda.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="tanggal_treatment" class="form-label">Tanggal dan Waktu yang Diinginkan <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="tanggal_treatment" name="tanggal_treatment" required min="<?php echo $minimumDateTime; ?>" value="<?php echo htmlspecialchars($bookingDate); ?>">
                                </div>
                                <div class="mb-4">
                                    <label for="catatan_tambahan" class="form-label">Keluhan atau Catatan Tambahan</label>
                                    <textarea class="form-control" id="catatan_tambahan" name="catatan_tambahan" rows="4" maxlength="1000" placeholder="Ceritakan kebutuhan kulit atau waktu kunjungan yang Anda harapkan."><?php echo htmlspecialchars($notes); ?></textarea>
                                </div>
                                <div class="d-grid"><button type="submit" class="btn btn-danger btn-lg">Ajukan Booking Konsultasi</button></div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white text-center py-4"><div class="container"><p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo NAMA_KLINIK; ?>.</p></div></footer>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>
