<?php
declare(strict_types=1);

require_once 'config.php';

$pageTitle = 'Dashboard Afiliator | ' . NAMA_KLINIK;
$pageDescription = 'Program kemitraan afiliasi Klinik Pratama DRW Estetika. Dapatkan komisi 10% dari setiap referral perawatan.';
$activePage = 'afiliasi';
$bodyClass = 'affiliate-page site-page';

$csrfToken = drw_csrf_token();
$isLoggedIn = drw_is_logged_in();
$currentUser = null;
$affiliateCode = '';
$referralLink = '';
$stats = [
    'total_komisi' => 0,
    'total_ditarik' => 0,
    'total_referral' => 0,
    'pending_orders' => 0,
];
$referrals = [];
$withdrawals = [];
$mutations = [];

if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];

    // Handle POST Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
            drw_flash('danger', 'Sesi formulir telah berakhir. Silakan muat ulang halaman.');
            header('Location: ' . drw_app_url('afiliasi.php'));
            exit();
        }

        $action = (string) ($_POST['action'] ?? '');

        // 1) Ubah Kode Afiliasi Kustom
        if ($action === 'update_code') {
            $rawCode = trim((string) ($_POST['new_affiliate_code'] ?? ''));
            $validation = drw_validate_affiliate_code($rawCode);

            if (!$validation['valid']) {
                drw_flash('danger', $validation['error']);
            } else {
                $cleanCode = $validation['code'];

                // Cek apakah kode sudah digunakan user lain
                $stmtCheck = $conn->prepare('SELECT id_user FROM user WHERE affiliate_code = ? AND id_user != ? LIMIT 1');
                $stmtCheck->bind_param('si', $cleanCode, $userId);
                $stmtCheck->execute();
                $exists = $stmtCheck->get_result()->fetch_assoc();
                $stmtCheck->close();

                if ($exists) {
                    drw_flash('danger', 'Kode afiliasi "' . htmlspecialchars($cleanCode) . '" sudah digunakan. Silakan pilih kode lain.');
                } else {
                    $stmtUpd = $conn->prepare('UPDATE user SET affiliate_code = ?, affiliate_code_updated_at = NOW() WHERE id_user = ?');
                    $stmtUpd->bind_param('si', $cleanCode, $userId);
                    $stmtUpd->execute();
                    $stmtUpd->close();

                    drw_flash('success', 'Kode afiliasi berhasil diperbarui menjadi: ' . htmlspecialchars($cleanCode));
                }
            }
            header('Location: ' . drw_app_url('afiliasi.php'));
            exit();
        }

        // 2) Pengajuan Penarikan Komisi (Withdrawal)
        if ($action === 'request_withdrawal') {
            $amount = (int) ($_POST['amount'] ?? 0);
            $accountType = in_array($_POST['account_type'] ?? '', ['bank', 'ewallet'], true) ? (string) $_POST['account_type'] : 'bank';
            $bankName = trim((string) ($_POST['bank_name'] ?? ''));
            $accountNumber = trim((string) ($_POST['account_number'] ?? ''));
            $accountName = trim((string) ($_POST['account_name'] ?? ''));

            // Ambil saldo saat ini
            $stmtUser = $conn->prepare('SELECT total_komisi FROM user WHERE id_user = ? LIMIT 1');
            $stmtUser->bind_param('i', $userId);
            $stmtUser->execute();
            $userBalance = (int) ($stmtUser->get_result()->fetch_assoc()['total_komisi'] ?? 0);
            $stmtUser->close();

            $minWithdrawal = 50000;

            if ($amount < $minWithdrawal) {
                drw_flash('danger', 'Minimal penarikan komisi adalah Rp ' . number_format($minWithdrawal, 0, ',', '.') . '.');
            } elseif ($amount > $userBalance) {
                drw_flash('danger', 'Saldo komisi Anda tidak mencukupi (Tersedia: Rp ' . number_format($userBalance, 0, ',', '.') . ').');
            } elseif ($bankName === '' || $accountNumber === '' || $accountName === '') {
                drw_flash('danger', 'Semua informasi rekening tujuan harus diisi dengan lengkap.');
            } else {
                $conn->begin_transaction();
                try {
                    // Potong saldo user
                    $stmtDeduct = $conn->prepare('UPDATE user SET total_komisi = total_komisi - ? WHERE id_user = ?');
                    $stmtDeduct->bind_param('ii', $amount, $userId);
                    $stmtDeduct->execute();
                    $stmtDeduct->close();

                    // Simpan permohonan penarikan
                    $stmtWd = $conn->prepare('
                        INSERT INTO affiliate_withdrawal (id_user, nominal, tipe_tujuan, nama_bank, nomor_rekening, nama_pemilik, status)
                        VALUES (?, ?, ?, ?, ?, ?, "pending")
                    ');
                    $stmtWd->bind_param('iissss', $userId, $amount, $accountType, $bankName, $accountNumber, $accountName);
                    $stmtWd->execute();
                    $withdrawalId = (int) $stmtWd->insert_id;
                    $stmtWd->close();

                    // Catat ke affiliate log
                    $logDesc = sprintf(
                        'Pengajuan penarikan komisi ke %s %s an %s',
                        $bankName,
                        $accountNumber,
                        $accountName
                    );
                    $negAmount = -$amount;
                    $stmtLog = $conn->prepare('
                        INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan, id_withdrawal)
                        VALUES (?, "penarikan", ?, ?, ?)
                    ');
                    $stmtLog->bind_param('iisi', $userId, $negAmount, $logDesc, $withdrawalId);
                    $stmtLog->execute();
                    $stmtLog->close();

                    $conn->commit();
                    drw_flash('success', 'Pengajuan penarikan komisi sebesar Rp ' . number_format($amount, 0, ',', '.') . ' berhasil dikirim. Tim admin akan memproses dalam 1-3 hari kerja.');
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Gagal mengajukan withdrawal: ' . $e->getMessage());
                    drw_flash('danger', 'Terjadi kesalahan sistem saat mengajukan penarikan. Silakan coba kembali.');
                }
            }
            header('Location: ' . drw_app_url('afiliasi.php'));
            exit();
        }
    }

    // Ambil data profil user
    $stmt = $conn->prepare('SELECT id_user, nama_lengkap, email, no_telepon, affiliate_code, total_komisi, total_ditarik, total_referral FROM user WHERE id_user = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $currentUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($currentUser) {
        $affiliateCode = drw_get_user_affiliate_code($conn, $userId, (string) $currentUser['nama_lengkap']);
        $referralLink = drw_app_url('order.php?ref=' . urlencode($affiliateCode));

        $stats['total_komisi'] = (int) ($currentUser['total_komisi'] ?? 0);
        $stats['total_ditarik'] = (int) ($currentUser['total_ditarik'] ?? 0);
        $stats['total_referral'] = (int) ($currentUser['total_referral'] ?? 0);

        // Hitung pending orders dari referral
        $stmtPending = $conn->prepare("SELECT COUNT(*) AS total FROM `order` WHERE referrer_id = ? AND status_order IN ('pending', 'confirmed')");
        $stmtPending->bind_param('i', $userId);
        $stmtPending->execute();
        $stats['pending_orders'] = (int) ($stmtPending->get_result()->fetch_assoc()['total'] ?? 0);
        $stmtPending->close();

        // Riwayat Pasien Referral
        $stmtRef = $conn->prepare("
            SELECT o.id_order, o.tanggal_treatment, o.status_order, o.komisi_nominal, o.komisi_status,
                   l.nama_layanan, c.nama_cabang, u_pat.nama_lengkap AS patient_name
            FROM `order` o
            JOIN layanan l ON l.id_layanan = o.id_layanan
            LEFT JOIN cabang c ON c.id_cabang = o.id_cabang
            JOIN user u_pat ON u_pat.id_user = o.id_user
            WHERE o.referrer_id = ?
            ORDER BY o.id_order DESC
            LIMIT 50
        ");
        $stmtRef->bind_param('i', $userId);
        $stmtRef->execute();
        $resRef = $stmtRef->get_result();
        while ($r = $resRef->fetch_assoc()) {
            $referrals[] = $r;
        }
        $stmtRef->close();

        // Riwayat Penarikan Komisi
        $stmtWdList = $conn->prepare('
            SELECT id_withdrawal, nominal, tipe_tujuan, nama_bank, nomor_rekening, nama_pemilik,
                   status, catatan_admin, tanggal_pengajuan, tanggal_diproses
            FROM affiliate_withdrawal
            WHERE id_user = ?
            ORDER BY id_withdrawal DESC
            LIMIT 50
        ');
        $stmtWdList->bind_param('i', $userId);
        $stmtWdList->execute();
        $resWd = $stmtWdList->get_result();
        while ($w = $resWd->fetch_assoc()) {
            $withdrawals[] = $w;
        }
        $stmtWdList->close();

        // Mutasi Saldo
        $stmtMut = $conn->prepare('
            SELECT id_log, tipe, nominal, keterangan, created_at
            FROM affiliate_log
            WHERE id_user = ?
            ORDER BY id_log DESC
            LIMIT 50
        ');
        $stmtMut->bind_param('i', $userId);
        $stmtMut->execute();
        $resMut = $stmtMut->get_result();
        while ($m = $resMut->fetch_assoc()) {
            $mutations[] = $m;
        }
        $stmtMut->close();
    }
}

$flash = drw_consume_flash();
require 'partials/site_header.php';
?>

<main class="py-4 py-md-5">
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
            </div>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>
            <!-- PUBLIC VIEW: INFORMASI PROGRAM AFILIASI -->
            <section class="drw-inner-hero text-center mb-5">
                <div class="row justify-content-center">
                    <div class="col-lg-9">
                        <span class="drw-hero-label text-uppercase"><i class="fa-solid fa-handshake me-1"></i> PROGRAM AFILIASI KLINIK</span>
                        <h1 class="mt-2">Raih Komisi <em>10%</em> dari Setiap Pasien yang Anda Ajak.</h1>
                        <p class="lead text-muted mt-3">Bagikan link referral Anda kepada teman, keluarga, atau media sosial. Dapatkan penghasilan tambahan otomatis setiap kali mereka melakukan perawatan di Klinik DRW Estetika.</p>
                        <div class="mt-4 d-flex flex-wrap gap-2 justify-content-center">
                            <a class="btn drw-btn-primary px-4 py-2" href="login.php?redirect=afiliasi.php"><i class="fa-regular fa-user me-1"></i> Masuk ke Dashboard Afiliator</a>
                            <a class="btn btn-outline-secondary px-4 py-2" href="daftar.php"><i class="fa-solid fa-user-plus me-1"></i> Daftar Akun Pasien</a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4 LANGKAH CARA KERJA -->
            <section class="mb-5">
                <div class="drw-section-intro text-center mb-4">
                    <div class="drw-section-kicker">CARA KERJA MUDAH</div>
                    <h2>Langkah Menjadi <em>Afiliator.</em></h2>
                    <p>Sistem otomatis, transparan, dan terintegrasi langsung dengan reservasi klinik.</p>
                </div>
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="card h-100 shadow-sm border-0 text-center p-4">
                            <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
                                <i class="fa-solid fa-id-card"></i>
                            </div>
                            <h5 class="fw-bold">1. Dapatkan Kode</h5>
                            <p class="text-muted small mb-0">Setiap member otomatis memperoleh kode afiliasi unik dan link referral pribadi.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 shadow-sm border-0 text-center p-4">
                            <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
                                <i class="fa-solid fa-share-nodes"></i>
                            </div>
                            <h5 class="fw-bold">2. Bagikan Link</h5>
                            <p class="text-muted small mb-0">Sebarkan link referral atau QR Code Anda ke WhatsApp, Instagram, TikTok, atau kontak Anda.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 shadow-sm border-0 text-center p-4">
                            <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
                                <i class="fa-solid fa-calendar-check"></i>
                            </div>
                            <h5 class="fw-bold">3. Pasien Treatment</h5>
                            <p class="text-muted small mb-0">Pasien booking melalui link Anda dan menyelesaikan perawatan di cabang klinik pilihan.</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card h-100 shadow-sm border-0 text-center p-4">
                            <div class="rounded-circle bg-light text-primary mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:64px;height:64px;font-size:1.5rem;">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                            <h5 class="fw-bold">4. Tarik Komisi</h5>
                            <p class="text-muted small mb-0">Komisi 10% langsung masuk ke saldo akun Anda dan dapat dicairkan kapan saja ke Bank atau E-Wallet.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- KEUNGGULAN -->
            <section class="card border-0 shadow-sm p-4 p-md-5 bg-light mb-5">
                <div class="row align-items-center gy-4">
                    <div class="col-lg-6">
                        <span class="drw-section-kicker">KEUNTUNGAN MITRA</span>
                        <h2 class="fw-bold mb-3">Mengapa Bergabung dengan Afiliasi DRW?</h2>
                        <ul class="list-unstyled mb-0 d-grid gap-3">
                            <li class="d-flex align-items-start">
                                <i class="fa-solid fa-circle-check text-success me-3 mt-1 fs-5"></i>
                                <div><strong>Komisi 10% Nyata:</strong> Dihitung langsung dari biaya perawatan pasien yang berhasil diselesaikan.</div>
                            </li>
                            <li class="d-flex align-items-start">
                                <i class="fa-solid fa-circle-check text-success me-3 mt-1 fs-5"></i>
                                <div><strong>Tracking Otomatis 30 Hari:</strong> Sistem mengingat referral pasien hingga 30 hari melalui cookie & session.</div>
                            </li>
                            <li class="d-flex align-items-start">
                                <i class="fa-solid fa-circle-check text-success me-3 mt-1 fs-5"></i>
                                <div><strong>Pencairan Fleksibel:</strong> Transfer dana langsung ke semua bank nasional (BCA, Mandiri, BRI, BNI, BSI) & E-Wallet (DANA, GoPay, OVO, ShopeePay).</div>
                            </li>
                            <li class="d-flex align-items-start">
                                <i class="fa-solid fa-circle-check text-success me-3 mt-1 fs-5"></i>
                                <div><strong>Layanan Terpercaya:</strong> Didukung oleh dokter & beautician profesional di Purworejo, Kutoarjo, dan Magelang.</div>
                            </li>
                        </ul>
                    </div>
                    <div class="col-lg-6 text-center">
                        <div class="p-4 bg-white rounded-3 shadow-sm border">
                            <h4 class="fw-bold mb-2">Mulai Sekarang</h4>
                            <p class="text-muted small mb-4">Sudah punya akun pasien? Masuk sekarang untuk melihat kode unik Anda.</p>
                            <a class="btn drw-btn-primary w-100 mb-2 py-2" href="login.php?redirect=afiliasi.php">Masuk Akun Pasien</a>
                            <a class="btn btn-outline-secondary w-100 py-2" href="daftar.php">Daftar Akun Baru</a>
                        </div>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- MEMBER VIEW: DASHBOARD AFILIATOR TERPADU -->
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div>
                    <span class="badge bg-primary text-dark px-3 py-1 mb-1 font-monospace fw-semibold"><i class="fa-solid fa-certificate me-1"></i> Afiliator Resmi</span>
                    <h1 class="h3 fw-bold mb-0">Dashboard Afiliator</h1>
                    <p class="text-muted small mb-0">Hai <strong><?= htmlspecialchars((string) ($currentUser['nama_lengkap'] ?? $_SESSION['username']), ENT_QUOTES, 'UTF-8') ?></strong>, kelola referral dan penghasilan komisi Anda di sini.</p>
                </div>
                <div>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#withdrawModal" <?= $stats['total_komisi'] < 50000 ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-money-bill-wave me-1"></i> Tarik Komisi
                    </button>
                </div>
            </div>

            <!-- CARD KODE AFILIASI & LINK REFERRAL -->
            <div class="card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #2b2520 0%, #1a1614 100%); color: #fff;">
                <div class="card-body p-4 p-md-5">
                    <div class="row align-items-center gy-4">
                        <div class="col-lg-6">
                            <span class="text-warning text-uppercase small fw-bold tracking-wider mb-2 d-block"><i class="fa-solid fa-key me-1"></i> Kode Afiliasi Anda</span>
                            <div class="d-flex align-items-baseline gap-3 mb-2">
                                <span class="display-5 font-monospace fw-bold text-warning" id="affiliateCodeDisplay"><?= htmlspecialchars($affiliateCode, ENT_QUOTES, 'UTF-8') ?></span>
                                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editCodeModal" title="Ubah Kode Afiliasi">
                                    <i class="fa-solid fa-pen-to-square"></i> Ubah
                                </button>
                            </div>
                            <p class="text-white-50 small mb-0">Komisi 10% otomatis tercatat setiap kali pasien menyelesaikan reservasi menggunakan kode Anda.</p>
                        </div>
                        <div class="col-lg-6">
                            <label class="form-label text-white-50 small mb-1">Tautan Referral Siap Bagikan:</label>
                            <div class="input-group mb-3">
                                <input type="text" class="form-control form-control-sm font-monospace bg-dark text-white border-secondary" id="referralLinkInput" value="<?= htmlspecialchars($referralLink, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                <button class="btn btn-warning btn-sm px-3 fw-bold" type="button" id="btnCopyLink">
                                    <i class="fa-solid fa-copy me-1"></i> Salin Link
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#qrModal">
                                    <i class="fa-solid fa-qrcode me-1"></i> Tampilkan QR Code
                                </button>
                                <a class="btn btn-sm btn-success" href="https://api.whatsapp.com/send?text=<?= urlencode('Yuk konsultasi dan treatment di Klinik DRW Estetika menggunakan link referral saya: ' . $referralLink) ?>" target="_blank" rel="noopener noreferrer">
                                    <i class="fa-brands fa-whatsapp me-1"></i> Share ke WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4 STATS CARDS -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Saldo Komisi</span>
                            <span class="badge bg-success-subtle text-success p-2 rounded-circle"><i class="fa-solid fa-wallet"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark">Rp <?= number_format($stats['total_komisi'], 0, ',', '.') ?></h3>
                        <span class="text-muted small">Siap dicairkan (Min Rp 50.000)</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Total Ditarik</span>
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-circle"><i class="fa-solid fa-money-bill-transfer"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark">Rp <?= number_format($stats['total_ditarik'], 0, ',', '.') ?></h3>
                        <span class="text-muted small">Telah berhasil dicairkan</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Pasien Berhasil</span>
                            <span class="badge bg-info-subtle text-info p-2 rounded-circle"><i class="fa-solid fa-user-check"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?= number_format($stats['total_referral'], 0, ',', '.') ?></h3>
                        <span class="text-muted small">Treatment selesai</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 p-3 bg-white">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small fw-semibold">Booking Pending</span>
                            <span class="badge bg-warning-subtle text-warning p-2 rounded-circle"><i class="fa-solid fa-clock"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-dark"><?= number_format($stats['pending_orders'], 0, ',', '.') ?></h3>
                        <span class="text-muted small">Menunggu kunjungan</span>
                    </div>
                </div>
            </div>

            <!-- TABS RIWAYAT: REFERRAL, WITHDRAWAL, MUTASI -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pt-3">
                    <ul class="nav nav-tabs card-header-tabs" id="affiliateTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-semibold" id="referrals-tab" data-bs-toggle="tab" data-bs-target="#referrals" type="button" role="tab" aria-controls="referrals" aria-selected="true">
                                <i class="fa-solid fa-users me-1"></i> Pasien Referral (<?= count($referrals) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="withdrawals-tab" data-bs-toggle="tab" data-bs-target="#withdrawals" type="button" role="tab" aria-controls="withdrawals" aria-selected="false">
                                <i class="fa-solid fa-receipt me-1"></i> Riwayat Penarikan (<?= count($withdrawals) ?>)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-semibold" id="mutations-tab" data-bs-toggle="tab" data-bs-target="#mutations" type="button" role="tab" aria-controls="mutations" aria-selected="false">
                                <i class="fa-solid fa-clock-rotate-left me-1"></i> Mutasi Saldo (<?= count($mutations) ?>)
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="affiliateTabContent">
                        <!-- TAB 1: REFERRALS -->
                        <div class="tab-pane fade show active p-3 p-md-4" id="referrals" role="tabpanel" aria-labelledby="referrals-tab">
                            <?php if (empty($referrals)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-user-group fs-1 text-black-50 mb-3 d-block"></i>
                                    <p class="mb-2">Belum ada pasien yang booking menggunakan kode referral Anda.</p>
                                    <p class="small text-muted mb-0">Bagikan link atau kode referral Anda sekarang untuk mulai mendapatkan komisi.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Pasien</th>
                                                <th>Layanan</th>
                                                <th>Cabang</th>
                                                <th>Status Booking</th>
                                                <th>Komisi (10%)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($referrals as $ref): ?>
                                                <?php
                                                // Sensor nama pasien untuk privasi (contoh: "Siti Rahma" -> "Siti R****")
                                                $pName = trim((string) $ref['patient_name']);
                                                $parts = explode(' ', $pName);
                                                if (count($parts) > 1) {
                                                    $maskedName = $parts[0] . ' ' . mb_substr($parts[1], 0, 1) . '****';
                                                } else {
                                                    $maskedName = mb_substr($pName, 0, 3) . '****';
                                                }
                                                ?>
                                                <tr>
                                                    <td><small><?= date('d M Y', strtotime((string) $ref['tanggal_treatment'])) ?></small></td>
                                                    <td><strong><?= htmlspecialchars($maskedName, ENT_QUOTES, 'UTF-8') ?></strong></td>
                                                    <td><?= htmlspecialchars((string) $ref['nama_layanan'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><small class="text-muted"><?= htmlspecialchars((string) ($ref['nama_cabang'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small></td>
                                                    <td>
                                                        <?php if ($ref['status_order'] === 'completed'): ?>
                                                            <span class="badge bg-success">Selesai</span>
                                                        <?php elseif ($ref['status_order'] === 'confirmed'): ?>
                                                            <span class="badge bg-primary">Terkonfirmasi</span>
                                                        <?php elseif ($ref['status_order'] === 'cancelled'): ?>
                                                            <span class="badge bg-danger">Batal</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($ref['komisi_status'] === 'paid'): ?>
                                                            <span class="fw-bold text-success">+Rp <?= number_format((int) $ref['komisi_nominal'], 0, ',', '.') ?></span>
                                                        <?php elseif ($ref['komisi_status'] === 'cancelled'): ?>
                                                            <span class="text-muted text-decoration-line-through">Rp <?= number_format((int) $ref['komisi_nominal'], 0, ',', '.') ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted small">Est. Rp <?= number_format((int) $ref['komisi_nominal'], 0, ',', '.') ?> (Pending)</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 2: WITHDRAWALS -->
                        <div class="tab-pane fade p-3 p-md-4" id="withdrawals" role="tabpanel" aria-labelledby="withdrawals-tab">
                            <?php if (empty($withdrawals)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-receipt fs-1 text-black-50 mb-3 d-block"></i>
                                    <p class="mb-0">Belum ada riwayat penarikan komisi.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Nominal</th>
                                                <th>Tujuan Transfer</th>
                                                <th>Status</th>
                                                <th>Catatan Admin</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawals as $w): ?>
                                                <tr>
                                                    <td><small><?= date('d M Y, H:i', strtotime((string) $w['tanggal_pengajuan'])) ?></small></td>
                                                    <td class="fw-bold text-dark">Rp <?= number_format((int) $w['nominal'], 0, ',', '.') ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border text-uppercase me-1"><?= htmlspecialchars((string) $w['tipe_tujuan'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <strong><?= htmlspecialchars((string) $w['nama_bank'], ENT_QUOTES, 'UTF-8') ?></strong>: <?= htmlspecialchars((string) $w['nomor_rekening'], ENT_QUOTES, 'UTF-8') ?>
                                                        <div class="small text-muted">an <?= htmlspecialchars((string) $w['nama_pemilik'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    </td>
                                                    <td>
                                                        <?php if ($w['status'] === 'completed'): ?>
                                                            <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Selesai Transfer</span>
                                                        <?php elseif ($w['status'] === 'approved'): ?>
                                                            <span class="badge bg-info text-dark"><i class="fa-solid fa-spinner me-1"></i> Diproses</span>
                                                        <?php elseif ($w['status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Ditolak</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-clock me-1"></i> Menunggu Verifikasi</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small class="text-muted"><?= htmlspecialchars((string) ($w['catatan_admin'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- TAB 3: MUTASI -->
                        <div class="tab-pane fade p-3 p-md-4" id="mutations" role="tabpanel" aria-labelledby="mutations-tab">
                            <?php if (empty($mutations)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-list-ol fs-1 text-black-50 mb-3 d-block"></i>
                                    <p class="mb-0">Belum ada catatan mutasi saldo.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Waktu</th>
                                                <th>Tipe</th>
                                                <th>Keterangan</th>
                                                <th>Nominal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mutations as $m): ?>
                                                <tr>
                                                    <td><small><?= date('d M Y, H:i', strtotime((string) $m['created_at'])) ?></small></td>
                                                    <td>
                                                        <?php if ($m['tipe'] === 'komisi_masuk'): ?>
                                                            <span class="badge bg-success-subtle text-success">Komisi Masuk</span>
                                                        <?php elseif ($m['tipe'] === 'penarikan'): ?>
                                                            <span class="badge bg-primary-subtle text-primary">Penarikan Dana</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary-subtle text-secondary">Koreksi</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small><?= htmlspecialchars((string) $m['keterangan'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                                    <td>
                                                        <?php if ((int) $m['nominal'] >= 0): ?>
                                                            <span class="fw-bold text-success">+Rp <?= number_format((int) $m['nominal'], 0, ',', '.') ?></span>
                                                        <?php else: ?>
                                                            <span class="fw-bold text-danger">-Rp <?= number_format(abs((int) $m['nominal']), 0, ',', '.') ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL TARIK KOMISI -->
            <div class="modal fade" id="withdrawModal" tabindex="-1" aria-labelledby="withdrawModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="afiliasi.php" class="modal-content border-0 shadow">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="request_withdrawal">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="withdrawModalLabel"><i class="fa-solid fa-money-bill-wave text-success me-2"></i>Tarik Komisi Afiliasi</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-light border mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="small text-muted">Saldo Tersedia:</span>
                                    <span class="fw-bold text-success fs-5">Rp <?= number_format($stats['total_komisi'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="withdrawAmount" class="form-label fw-semibold">Nominal Penarikan (Rp) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="withdrawAmount" name="amount" min="50000" max="<?= $stats['total_komisi'] ?>" step="1000" placeholder="Minimal 50.000" required>
                                <div class="form-text">Minimal penarikan adalah Rp 50.000.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipe Rekening Tujuan <span class="text-danger">*</span></label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="account_type" id="typeBank" value="bank" checked>
                                        <label class="form-check-label" for="typeBank">Transfer Bank</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="account_type" id="typeEwallet" value="ewallet">
                                        <label class="form-check-label" for="typeEwallet">E-Wallet</label>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="bankName" class="form-label fw-semibold">Nama Bank / E-Wallet <span class="text-danger">*</span></label>
                                <input class="form-control" list="bankOptions" id="bankName" name="bank_name" placeholder="Pilih atau ketik (contoh: BCA, BRI, Mandiri, DANA, GoPay)" required>
                                <datalist id="bankOptions">
                                    <option value="BCA">
                                    <option value="BRI">
                                    <option value="Mandiri">
                                    <option value="BNI">
                                    <option value="BSI (Bank Syariah Indonesia)">
                                    <option value="CIMB Niaga">
                                    <option value="Bank Jateng">
                                    <option value="Permata">
                                    <option value="DANA">
                                    <option value="GoPay">
                                    <option value="OVO">
                                    <option value="ShopeePay">
                                </datalist>
                            </div>
                            <div class="mb-3">
                                <label for="accountNumber" class="form-label fw-semibold">Nomor Rekening / No. E-Wallet <span class="text-danger">*</span></label>
                                <input type="text" class="form-control font-monospace" id="accountNumber" name="account_number" placeholder="Contoh: 1234567890 atau 08123456789" required>
                            </div>
                            <div class="mb-3">
                                <label for="accountName" class="form-label fw-semibold">Nama Pemilik Rekening <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="accountName" name="account_name" value="<?= htmlspecialchars((string) ($currentUser['nama_lengkap'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Sesuai buku tabungan atau akun e-wallet" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success fw-semibold"><i class="fa-solid fa-paper-plane me-1"></i> Ajukan Penarikan</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL UBAH KODE AFILIASI -->
            <div class="modal fade" id="editCodeModal" tabindex="-1" aria-labelledby="editCodeModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="afiliasi.php" class="modal-content border-0 shadow">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="update_code">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="editCodeModalLabel"><i class="fa-solid fa-pen-to-square text-primary me-2"></i>Ubah Kode Afiliasi</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="newAffiliateCode" class="form-label fw-semibold">Kode Afiliasi Baru</label>
                                <input type="text" class="form-control font-monospace text-uppercase" id="newAffiliateCode" name="new_affiliate_code" value="<?= htmlspecialchars($affiliateCode, ENT_QUOTES, 'UTF-8') ?>" maxlength="15" minlength="4" required>
                                <div class="form-text">Gunakan 4-15 karakter huruf dan angka (A-Z, 0-9). Contoh: <code><?= htmlspecialchars(mb_strtoupper(mb_substr(preg_replace('/[^A-Za-z]/', '', (string)$currentUser['nama_lengkap']), 0, 4) . '25'), ENT_QUOTES, 'UTF-8') ?></code></div>
                            </div>
                            <div class="alert alert-warning small mb-0">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Link referral lama yang telah Anda bagikan akan otomatis mengarah ke kode baru ini.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary fw-semibold"><i class="fa-solid fa-check me-1"></i> Simpan Kode Baru</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL QR CODE REFERRAL -->
            <div class="modal fade" id="qrModal" tabindex="-1" aria-labelledby="qrModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered text-center">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="qrModalLabel"><i class="fa-solid fa-qrcode text-primary me-2"></i>QR Code Referral</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body py-4">
                            <div id="qrcodeCanvas" class="d-flex justify-content-center mb-3"></div>
                            <p class="font-monospace fw-bold text-dark mb-1 fs-5"><?= htmlspecialchars($affiliateCode, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="small text-muted mb-3">Tunjukkan QR code ini kepada calon pasien untuk langsung membuka form booking dengan kode referral Anda.</p>
                            <button class="btn btn-outline-primary btn-sm" id="btnDownloadQr">
                                <i class="fa-solid fa-download me-1"></i> Unduh Gambar QR Code
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Copy referral link button
    var copyBtn = document.getElementById('btnCopyLink');
    var linkInput = document.getElementById('referralLinkInput');
    if (copyBtn && linkInput) {
        copyBtn.addEventListener('click', function () {
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(linkInput.value).then(function () {
                var originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Tersalin!';
                copyBtn.classList.remove('btn-warning');
                copyBtn.classList.add('btn-success');
                setTimeout(function () {
                    copyBtn.innerHTML = originalHtml;
                    copyBtn.classList.remove('btn-success');
                    copyBtn.classList.add('btn-warning');
                }, 2000);
            });
        });
    }

    // Generate QR Code if logged in
    var qrContainer = document.getElementById('qrcodeCanvas');
    var qrModalEl = document.getElementById('qrModal');
    var qrInstance = null;
    if (qrContainer && linkInput) {
        qrModalEl.addEventListener('shown.bs.modal', function () {
            if (!qrInstance) {
                qrInstance = new QRCode(qrContainer, {
                    text: linkInput.value,
                    width: 200,
                    height: 200,
                    colorDark: "#1a1614",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            }
        });

        var downloadBtn = document.getElementById('btnDownloadQr');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function () {
                var img = qrContainer.querySelector('img');
                var canvas = qrContainer.querySelector('canvas');
                var dataUrl = '';
                if (img && img.src) {
                    dataUrl = img.src;
                } else if (canvas) {
                    dataUrl = canvas.toDataURL('image/png');
                }
                if (dataUrl) {
                    var a = document.createElement('a');
                    a.href = dataUrl;
                    a.download = 'QR-DRW-Afiliasi-' + '<?= htmlspecialchars($affiliateCode, ENT_QUOTES, 'UTF-8') ?>' + '.png';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                }
            });
        }
    }
});
</script>

<?php
require 'partials/site_footer.php';
$conn->close();
