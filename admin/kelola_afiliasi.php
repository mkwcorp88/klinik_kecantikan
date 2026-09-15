<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
$adminCabangId = drw_admin_cabang_id();
$adminCabangNama = drw_admin_display_cabang();
$csrfToken = drw_csrf_token();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
        drw_flash('danger', 'Sesi formulir telah berakhir. Silakan muat ulang halaman.');
        header('Location: kelola_afiliasi.php');
        exit();
    }

    $action = (string) ($_POST['action'] ?? '');

    // 1) Update Status Penarikan Dana (Withdrawal)
    if ($action === 'update_withdrawal_status') {
        $withdrawalId = (int) ($_POST['id_withdrawal'] ?? 0);
        $newStatus = (string) ($_POST['status'] ?? '');
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
        $allowedStatus = ['pending', 'approved', 'completed', 'rejected'];
        $allowedTransitions = [
            'pending' => ['pending', 'approved', 'rejected'],
            'approved' => ['approved', 'completed', 'rejected'],
            'completed' => ['completed'],
            'rejected' => ['rejected'],
        ];

        if ($withdrawalId <= 0 || !in_array($newStatus, $allowedStatus, true)) {
            drw_flash('danger', 'Parameter penarikan tidak valid.');
        } else {
            $conn->begin_transaction();
            try {
                $wdSql = '
                    SELECT w.id_withdrawal, w.id_user, w.nominal, w.status
                    FROM affiliate_withdrawal w
                    JOIN user u ON u.id_user = w.id_user
                    WHERE w.id_withdrawal = ?
                ';
                if ($adminCabangId !== null) {
                    $wdSql .= ' AND u.id_cabang = ?';
                }
                $wdSql .= ' LIMIT 1 FOR UPDATE';
                $stmtWd = $conn->prepare($wdSql);
                if ($adminCabangId === null) {
                    $stmtWd->bind_param('i', $withdrawalId);
                } else {
                    $stmtWd->bind_param('ii', $withdrawalId, $adminCabangId);
                }
                $stmtWd->execute();
                $wd = $stmtWd->get_result()->fetch_assoc();
                $stmtWd->close();

                if (!$wd) {
                    throw new DomainException('Data penarikan tidak ditemukan atau bukan bagian dari cabang Anda.');
                }

                $oldStatus = (string) $wd['status'];
                $amount = (int) $wd['nominal'];
                $userId = (int) $wd['id_user'];
                if (!in_array($newStatus, $allowedTransitions[$oldStatus] ?? [], true)) {
                    throw new DomainException('Status penarikan yang sudah selesai atau ditolak tidak dapat diubah kembali.');
                }

                // Saldo sudah ditahan saat member mengajukan penarikan.
                if ($newStatus === 'rejected' && $oldStatus !== 'rejected') {
                    $stmtRefund = $conn->prepare('UPDATE user SET total_komisi = total_komisi + ? WHERE id_user = ?');
                    $stmtRefund->bind_param('ii', $amount, $userId);
                    $stmtRefund->execute();
                    $stmtRefund->close();

                    $logDesc = 'Pengembalian saldo: Penarikan #' . $withdrawalId . ' ditolak. Catatan: ' . ($adminNotes ?: '-');
                    $stmtLog = $conn->prepare('INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan, id_withdrawal) VALUES (?, "koreksi", ?, ?, ?)');
                    $stmtLog->bind_param('iisi', $userId, $amount, $logDesc, $withdrawalId);
                    $stmtLog->execute();
                    $stmtLog->close();
                } elseif ($newStatus === 'completed' && $oldStatus !== 'completed') {
                    $stmtCompleted = $conn->prepare('UPDATE user SET total_ditarik = total_ditarik + ? WHERE id_user = ?');
                    $stmtCompleted->bind_param('ii', $amount, $userId);
                    $stmtCompleted->execute();
                    $stmtCompleted->close();
                }

                $stmtUpd = $conn->prepare('
                    UPDATE affiliate_withdrawal
                    SET status = ?, catatan_admin = ?, tanggal_diproses = CASE WHEN ? = "pending" THEN NULL ELSE NOW() END
                    WHERE id_withdrawal = ?
                ');
                $stmtUpd->bind_param('sssi', $newStatus, $adminNotes, $newStatus, $withdrawalId);
                $stmtUpd->execute();
                $stmtUpd->close();

                $conn->commit();
                drw_flash('success', 'Status penarikan #' . $withdrawalId . ' berhasil diperbarui menjadi: ' . ucfirst($newStatus));
            } catch (DomainException $e) {
                $conn->rollback();
                drw_flash('danger', $e->getMessage());
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Error update withdrawal: ' . $e->getMessage());
                drw_flash('danger', 'Gagal memperbarui status penarikan. Terjadi kesalahan database.');
            }
        }
        header('Location: kelola_afiliasi.php?tab=withdrawals');
        exit();
    }

    // 2) Atur / Reset Kode Afiliasi Member
    if ($action === 'set_affiliate_code') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $newCodeRaw = trim((string) ($_POST['affiliate_code'] ?? ''));
        $val = drw_validate_affiliate_code($newCodeRaw);

        if (!$val['valid']) {
            drw_flash('danger', $val['error']);
        } else {
            $code = $val['code'];
            $stmtCheck = $conn->prepare('SELECT id_user FROM user WHERE affiliate_code = ? AND id_user != ? LIMIT 1');
            $stmtCheck->bind_param('si', $code, $targetUserId);
            $stmtCheck->execute();
            $exists = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if ($exists) {
                drw_flash('danger', 'Kode ' . $code . ' sudah digunakan oleh member lain.');
            } else {
                $targetSql = 'SELECT id_user FROM user WHERE id_user = ?';
                if ($adminCabangId !== null) {
                    $targetSql .= ' AND id_cabang = ?';
                }
                $targetSql .= ' LIMIT 1';
                $stmtTarget = $conn->prepare($targetSql);
                if ($adminCabangId === null) {
                    $stmtTarget->bind_param('i', $targetUserId);
                } else {
                    $stmtTarget->bind_param('ii', $targetUserId, $adminCabangId);
                }
                $stmtTarget->execute();
                $targetExists = (bool) $stmtTarget->get_result()->fetch_assoc();
                $stmtTarget->close();

                if (!$targetExists) {
                    drw_flash('danger', 'Member tidak ditemukan atau bukan bagian dari cabang Anda.');
                } else {
                    $setCodeSql = 'UPDATE user SET affiliate_code = ?, affiliate_code_updated_at = NOW() WHERE id_user = ?';
                    if ($adminCabangId !== null) {
                        $setCodeSql .= ' AND id_cabang = ?';
                    }
                    $stmtSet = $conn->prepare($setCodeSql);
                    if ($adminCabangId === null) {
                        $stmtSet->bind_param('si', $code, $targetUserId);
                    } else {
                        $stmtSet->bind_param('sii', $code, $targetUserId, $adminCabangId);
                    }
                    $stmtSet->execute();
                    $stmtSet->close();
                    drw_flash('success', 'Kode afiliasi user #' . $targetUserId . ' berhasil diubah menjadi: ' . $code);
                }
            }
        }
        header('Location: kelola_afiliasi.php?tab=affiliates');
        exit();
    }

    // 3) Approve, aktifkan, atau nonaktifkan afiliator
    if ($action === 'update_affiliate_status') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $newStatus = (string) ($_POST['status_afiliasi'] ?? '');
        $allowedAffiliateStatuses = ['pending', 'aktif', 'nonaktif'];

        if ($targetUserId <= 0 || !in_array($newStatus, $allowedAffiliateStatuses, true)) {
            drw_flash('danger', 'Status afiliator tidak valid.');
        } else {
            $targetSql = 'SELECT id_user, affiliate_code FROM user WHERE id_user = ?';
            if ($adminCabangId !== null) {
                $targetSql .= ' AND id_cabang = ?';
            }
            $targetSql .= ' LIMIT 1';
            $stmtTarget = $conn->prepare($targetSql);
            if ($adminCabangId === null) {
                $stmtTarget->bind_param('i', $targetUserId);
            } else {
                $stmtTarget->bind_param('ii', $targetUserId, $adminCabangId);
            }
            $stmtTarget->execute();
            $target = $stmtTarget->get_result()->fetch_assoc();
            $stmtTarget->close();

            if (!$target || empty($target['affiliate_code'])) {
                drw_flash('danger', 'Afiliator tidak ditemukan atau belum memiliki kode afiliasi.');
            } else {
                $statusSql = 'UPDATE user SET status_afiliasi = ? WHERE id_user = ?';
                if ($adminCabangId !== null) {
                    $statusSql .= ' AND id_cabang = ?';
                }
                $stmtStatus = $conn->prepare($statusSql);
                if ($adminCabangId === null) {
                    $stmtStatus->bind_param('si', $newStatus, $targetUserId);
                } else {
                    $stmtStatus->bind_param('sii', $newStatus, $targetUserId, $adminCabangId);
                }
                $stmtStatus->execute();
                $stmtStatus->close();
                $statusLabels = ['pending' => 'Pending', 'aktif' => 'Aktif', 'nonaktif' => 'Nonaktif'];
                drw_flash('success', 'Status afiliator user #' . $targetUserId . ' berhasil diubah menjadi ' . ($statusLabels[$newStatus] ?? $newStatus) . '.');
            }
        }
        header('Location: kelola_afiliasi.php?tab=affiliates');
        exit();
    }

    // 4) Koreksi saldo komisi secara manual dengan audit log
    if ($action === 'adjust_commission') {
        $targetUserId = (int) ($_POST['target_user_id'] ?? 0);
        $adjustmentType = (string) ($_POST['adjustment_type'] ?? '');
        $amount = (int) ($_POST['adjustment_amount'] ?? 0);
        $note = trim((string) ($_POST['adjustment_note'] ?? ''));

        if ($targetUserId <= 0 || !in_array($adjustmentType, ['credit', 'debit'], true) || $amount < 1 || $amount > 1000000000 || $note === '' || mb_strlen($note) > 255) {
            drw_flash('danger', 'Data koreksi saldo tidak valid. Nominal dan alasan wajib diisi.');
        } else {
            $signedAmount = $adjustmentType === 'debit' ? -$amount : $amount;
            $conn->begin_transaction();
            try {
                $targetSql = 'SELECT id_user FROM user WHERE id_user = ? AND affiliate_code IS NOT NULL';
                if ($adminCabangId !== null) {
                    $targetSql .= ' AND id_cabang = ?';
                }
                $targetSql .= ' LIMIT 1 FOR UPDATE';
                $stmtTarget = $conn->prepare($targetSql);
                if ($adminCabangId === null) {
                    $stmtTarget->bind_param('i', $targetUserId);
                } else {
                    $stmtTarget->bind_param('ii', $targetUserId, $adminCabangId);
                }
                $stmtTarget->execute();
                $targetExists = (bool) $stmtTarget->get_result()->fetch_assoc();
                $stmtTarget->close();

                if (!$targetExists) {
                    throw new DomainException('Afiliator tidak ditemukan atau bukan bagian dari cabang Anda.');
                }

                $updateSql = 'UPDATE user SET total_komisi = total_komisi + ? WHERE id_user = ? AND total_komisi + ? >= 0';
                if ($adminCabangId !== null) {
                    $updateSql .= ' AND id_cabang = ?';
                }
                $stmtBalance = $conn->prepare($updateSql);
                if ($adminCabangId === null) {
                    $stmtBalance->bind_param('iii', $signedAmount, $targetUserId, $signedAmount);
                } else {
                    $stmtBalance->bind_param('iiii', $signedAmount, $targetUserId, $signedAmount, $adminCabangId);
                }
                $stmtBalance->execute();
                $changed = $stmtBalance->affected_rows;
                $stmtBalance->close();

                if ($changed !== 1) {
                    throw new DomainException('Saldo tidak mencukupi untuk pengurangan tersebut.');
                }

                $logDesc = ($signedAmount > 0 ? 'Penambahan' : 'Pengurangan') . ' saldo manual oleh admin: ' . $note;
                $stmtLog = $conn->prepare('INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan) VALUES (?, "koreksi", ?, ?)');
                $stmtLog->bind_param('iis', $targetUserId, $signedAmount, $logDesc);
                $stmtLog->execute();
                $stmtLog->close();
                $conn->commit();
                drw_flash('success', 'Saldo komisi user #' . $targetUserId . ' berhasil dikoreksi.');
            } catch (DomainException $e) {
                $conn->rollback();
                drw_flash('danger', $e->getMessage());
            } catch (Throwable $e) {
                $conn->rollback();
                error_log('Error adjust commission: ' . $e->getMessage());
                drw_flash('danger', 'Gagal mengoreksi saldo komisi. Terjadi kesalahan database.');
            }
        }
        header('Location: kelola_afiliasi.php?tab=affiliates');
        exit();
    }
}

// Data Ringkasan Dashboard
$stats = [
    'pending_wd_count' => 0,
    'pending_wd_amount' => 0,
    'pending_affiliate_count' => 0,
    'total_paid_commissions' => 0,
    'total_affiliates' => 0,
    'total_referral_orders' => 0,
];

// Hitung pending withdrawals
$pendingWdSql = "SELECT COUNT(*) AS count, COALESCE(SUM(w.nominal), 0) AS total
                 FROM affiliate_withdrawal w
                 JOIN user u ON u.id_user = w.id_user
                 WHERE w.status = 'pending'";
if ($adminCabangId !== null) {
    $pendingWdSql .= ' AND u.id_cabang = ?';
}
$stmtPendingWd = $conn->prepare($pendingWdSql);
if ($adminCabangId === null) {
    $stmtPendingWd->execute();
} else {
    $stmtPendingWd->bind_param('i', $adminCabangId);
    $stmtPendingWd->execute();
}
if ($row = $stmtPendingWd->get_result()->fetch_assoc()) {
    $stats['pending_wd_count'] = (int) $row['count'];
    $stats['pending_wd_amount'] = (int) $row['total'];
}
$stmtPendingWd->close();

// Hitung afiliator yang menunggu approval
$pendingAffiliateSql = "SELECT COUNT(*) AS total FROM user WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'pending'";
if ($adminCabangId !== null) {
    $pendingAffiliateSql .= ' AND id_cabang = ?';
}
$stmtPendingAffiliate = $conn->prepare($pendingAffiliateSql);
if ($adminCabangId === null) {
    $stmtPendingAffiliate->execute();
} else {
    $stmtPendingAffiliate->bind_param('i', $adminCabangId);
    $stmtPendingAffiliate->execute();
}
if ($row = $stmtPendingAffiliate->get_result()->fetch_assoc()) {
    $stats['pending_affiliate_count'] = (int) $row['total'];
}
$stmtPendingAffiliate->close();

// Total komisi dibayarkan
$paidCommissionSql = "SELECT COALESCE(SUM(o.komisi_nominal), 0) AS total
                      FROM `order` o
                      JOIN user u_ref ON u_ref.id_user = o.referrer_id
                      WHERE o.komisi_status = 'paid'";
if ($adminCabangId !== null) {
    $paidCommissionSql .= ' AND u_ref.id_cabang = ?';
}
$stmtPaidCommission = $conn->prepare($paidCommissionSql);
if ($adminCabangId === null) {
    $stmtPaidCommission->execute();
} else {
    $stmtPaidCommission->bind_param('i', $adminCabangId);
    $stmtPaidCommission->execute();
}
if ($row = $stmtPaidCommission->get_result()->fetch_assoc()) {
    $stats['total_paid_commissions'] = (int) $row['total'];
}
$stmtPaidCommission->close();

// Total afiliator yang sudah disetujui
$affiliateCountSql = "SELECT COUNT(*) AS total FROM user WHERE affiliate_code IS NOT NULL AND status_afiliasi = 'aktif'";
if ($adminCabangId !== null) {
    $affiliateCountSql .= ' AND id_cabang = ?';
}
$stmtAffiliateCount = $conn->prepare($affiliateCountSql);
if ($adminCabangId === null) {
    $stmtAffiliateCount->execute();
} else {
    $stmtAffiliateCount->bind_param('i', $adminCabangId);
    $stmtAffiliateCount->execute();
}
if ($row = $stmtAffiliateCount->get_result()->fetch_assoc()) {
    $stats['total_affiliates'] = (int) $row['total'];
}
$stmtAffiliateCount->close();

// Total booking referral
$referralOrderCountSql = "SELECT COUNT(*) AS total
                          FROM `order` o
                          JOIN user u_ref ON u_ref.id_user = o.referrer_id
                          WHERE o.referred_by IS NOT NULL";
if ($adminCabangId !== null) {
    $referralOrderCountSql .= ' AND u_ref.id_cabang = ?';
}
$stmtReferralOrderCount = $conn->prepare($referralOrderCountSql);
if ($adminCabangId === null) {
    $stmtReferralOrderCount->execute();
} else {
    $stmtReferralOrderCount->bind_param('i', $adminCabangId);
    $stmtReferralOrderCount->execute();
}
if ($row = $stmtReferralOrderCount->get_result()->fetch_assoc()) {
    $stats['total_referral_orders'] = (int) $row['total'];
}
$stmtReferralOrderCount->close();

// Tab aktif dari URL
$activeTab = (string) ($_GET['tab'] ?? 'withdrawals');
if (!in_array($activeTab, ['withdrawals', 'affiliates', 'orders'], true)) {
    $activeTab = 'withdrawals';
}

// 1) List Permintaan Penarikan (Withdrawals)
$filterWdStatus = (string) ($_GET['wd_status'] ?? 'all');
$wdWhere = ['u.affiliate_code IS NOT NULL'];
$wdParams = [];
$wdTypes = '';
if ($adminCabangId !== null) {
    $wdWhere[] = 'u.id_cabang = ?';
    $wdTypes .= 'i';
    $wdParams[] = $adminCabangId;
}
if (in_array($filterWdStatus, ['pending', 'approved', 'completed', 'rejected'], true)) {
    $wdWhere[] = 'w.status = ?';
    $wdTypes .= 's';
    $wdParams[] = $filterWdStatus;
}
$wdSql = '
    SELECT w.id_withdrawal, w.id_user, w.nominal, w.tipe_tujuan, w.nama_bank, w.nomor_rekening,
           w.nama_pemilik, w.status, w.catatan_admin, w.tanggal_pengajuan, w.tanggal_diproses,
           u.nama_lengkap, u.username, u.no_telepon, u.affiliate_code, u.status_afiliasi
    FROM affiliate_withdrawal w
    JOIN user u ON u.id_user = w.id_user
';
if ($wdWhere !== []) {
    $wdSql .= ' WHERE ' . implode(' AND ', $wdWhere);
}
$wdSql .= ' ORDER BY w.id_withdrawal DESC LIMIT 100';

$stmtWdList = $conn->prepare($wdSql);
if ($wdTypes !== '') {
    $stmtWdList->bind_param($wdTypes, ...$wdParams);
}
$stmtWdList->execute();
$withdrawals = $stmtWdList->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtWdList->close();

// 2) List Afiliator
$searchAff = trim((string) ($_GET['search_aff'] ?? ''));
$affWhere = ['u.affiliate_code IS NOT NULL'];
$affParams = [];
$affTypes = '';
if ($adminCabangId !== null) {
    $affWhere[] = 'u.id_cabang = ?';
    $affTypes .= 'i';
    $affParams[] = $adminCabangId;
}
$filterAffStatus = (string) ($_GET['aff_status'] ?? 'all');
if (in_array($filterAffStatus, ['pending', 'aktif', 'nonaktif'], true)) {
    $affWhere[] = 'u.status_afiliasi = ?';
    $affTypes .= 's';
    $affParams[] = $filterAffStatus;
}
if ($searchAff !== '') {
    $affLike = '%' . $searchAff . '%';
    $affWhere[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.affiliate_code LIKE ? OR u.no_telepon LIKE ?)';
    $affTypes .= 'ssss';
    $affParams = array_merge($affParams, [$affLike, $affLike, $affLike, $affLike]);
}
$affSql = '
    SELECT u.id_user, u.nama_lengkap, u.username, u.no_telepon, u.affiliate_code, u.status_afiliasi,
           u.total_komisi, u.total_ditarik, u.total_referral, u.tanggal_daftar,
           c.nama_cabang
    FROM user u
    LEFT JOIN cabang c ON c.id_cabang = u.id_cabang
    WHERE ' . implode(' AND ', $affWhere) . '
    ORDER BY u.total_referral DESC, u.total_komisi DESC, u.id_user DESC
    LIMIT 100
';
$stmtAff = $conn->prepare($affSql);
if ($affTypes !== '') {
    $stmtAff->bind_param($affTypes, ...$affParams);
}
$stmtAff->execute();
$affiliates = $stmtAff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAff->close();

// 3) List Booking Berkomisi
$orderCommWhere = ['o.referred_by IS NOT NULL'];
$orderCommParams = [];
$orderCommTypes = '';
if ($adminCabangId !== null) {
    $orderCommWhere[] = 'u_ref.id_cabang = ?';
    $orderCommTypes = 'i';
    $orderCommParams[] = $adminCabangId;
}
$orderCommSql = '
    SELECT o.id_order, o.referred_by, o.komisi_nominal, o.komisi_status, o.total_bayar,
           o.tanggal_treatment, o.status_order,
           l.nama_layanan, l.harga,
           c.nama_cabang,
           u_pat.nama_lengkap AS patient_name,
            u_ref.nama_lengkap AS referrer_name, u_ref.id_user AS referrer_id
    FROM `order` o
    JOIN layanan l ON l.id_layanan = o.id_layanan
    LEFT JOIN cabang c ON c.id_cabang = o.id_cabang
    JOIN user u_pat ON u_pat.id_user = o.id_user
    LEFT JOIN user u_ref ON u_ref.id_user = o.referrer_id
    WHERE ' . implode(' AND ', $orderCommWhere) . '
    ORDER BY o.id_order DESC
    LIMIT 100
';
$stmtComm = $conn->prepare($orderCommSql);
if ($orderCommTypes !== '') {
    $stmtComm->bind_param($orderCommTypes, ...$orderCommParams);
}
$stmtComm->execute();
$commissionOrders = $stmtComm->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtComm->close();

// Sidebar badges
if ($adminCabangId === null) {
    $pendingOrdersQuery = $conn->query("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending'");
    $pending_orders = (int) ($pendingOrdersQuery->fetch_assoc()['total'] ?? 0);
    $pendingOrdersQuery->close();
} else {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `order` WHERE status_order = 'pending' AND id_cabang = ?");
    $stmt->bind_param('i', $adminCabangId);
    $stmt->execute();
    $pending_orders = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}
$pending_withdrawals = (int) $stats['pending_wd_count'];

$flash = drw_consume_flash();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Afiliator - Admin <?php echo NAMA_KLINIK; ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link href="../css/admin-theme.css" rel="stylesheet">
    <style>
        body { background-color: #f4f7f6; }
        .admin-sidebar { background-color: #2c3e50; color: white; min-height: 100vh; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; width: 250px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .admin-sidebar .sidebar-brand { padding: 1rem 1.5rem; font-size: 1.5rem; font-weight: bold; color: #fff; text-align: center; border-bottom: 1px solid #3a506b; }
        .admin-sidebar .sidebar-brand img { max-height: 35px; margin-right: 10px; }
        .admin-sidebar .nav-link { color: #bdc3c7; padding: 0.9rem 1.5rem; font-weight: 500; border-left: 3px solid transparent; }
        .admin-sidebar .nav-link:hover { color: #fff; background-color: #34495e; border-left-color: #1abc9c; }
        .admin-sidebar .nav-link.active { color: #fff; background-color: #1abc9c; border-left-color: #fff; }
        .admin-sidebar .nav-link .fas { margin-right: 0.8rem; width: 20px; text-align: center; }
        .admin-sidebar hr.text-secondary { border-top: 1px solid #3a506b; }
        .admin-main-content { margin-left: 250px; padding: 20px; width: calc(100% - 250px); }
        .admin-header { background-color: #fff; padding: 15px 25px; margin-bottom: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        @media (max-width: 991.98px) {
            .admin-sidebar { width: 100%; height: auto; position: relative; box-shadow: none; }
            .admin-main-content { margin-left: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="d-flex flex-column flex-lg-row">
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
                    <a class="nav-link" href="kelola_user.php">
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
                    <a class="nav-link active" href="kelola_afiliasi.php">
                        <i class="fas fa-handshake"></i> Afiliator
                        <?php if ($stats['pending_affiliate_count'] > 0): ?>
                            <span class="badge bg-warning text-dark ms-1" title="Menunggu approval"><?php echo $stats['pending_affiliate_count']; ?></span>
                        <?php endif; ?>
                        <?php if ($stats['pending_wd_count'] > 0): ?>
                            <span class="badge bg-info text-dark ms-1" title="Penarikan pending"><?php echo $stats['pending_wd_count']; ?></span>
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
                <h1 class="h4 mb-0 text-gray-800">Manajemen Afiliator & Penarikan</h1>
                <div class="user-info">
                    <span class="badge bg-light text-dark border me-2"><i class="fas fa-clinic-medical me-1"></i><?php echo htmlspecialchars($adminCabangNama); ?></span>
                    <span class="text-muted me-2">Admin:</span>
                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                </div>
            </header>

            <div class="admin-content-area">
                <?php if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                <?php endif; ?>

                <!-- STATS CARDS -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-warning border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">PENARIKAN PENDING</span>
                            <div class="d-flex align-items-baseline justify-content-between mt-1">
                                <h4 class="fw-bold mb-0 text-dark"><?php echo $stats['pending_wd_count']; ?> permintaan</h4>
                                <span class="badge bg-warning text-dark">Rp <?php echo number_format($stats['pending_wd_amount'], 0, ',', '.'); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-success border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">TOTAL KOMISI DIBAYAR</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1">Rp <?php echo number_format($stats['total_paid_commissions'], 0, ',', '.'); ?></h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-primary border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">TOTAL AFILIATOR AKTIF</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['total_affiliates'], 0, ',', '.'); ?> member</h4>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card shadow-sm border-0 border-start border-info border-4 p-3 bg-white">
                            <span class="text-muted small fw-bold">BOOKING DARI REFERRAL</span>
                            <h4 class="fw-bold mb-0 text-dark mt-1"><?php echo number_format($stats['total_referral_orders'], 0, ',', '.'); ?> order</h4>
                        </div>
                    </div>
                </div>

                <!-- TABS CONTAINER -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom pt-3">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'withdrawals' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=withdrawals">
                                    <i class="fas fa-money-check-alt me-1"></i> Permintaan Penarikan
                                    <?php if ($stats['pending_wd_count'] > 0): ?>
                                        <span class="badge bg-danger ms-1"><?php echo $stats['pending_wd_count']; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'affiliates' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=affiliates">
                                    <i class="fas fa-users me-1"></i> Daftar Afiliator
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $activeTab === 'orders' ? 'active fw-bold' : ''; ?>" href="kelola_afiliasi.php?tab=orders">
                                    <i class="fas fa-receipt me-1"></i> Riwayat Komisi Booking
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <?php if ($activeTab === 'withdrawals'): ?>
                            <!-- TAB 1: PERMINTAAN PENARIKAN -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-list me-1"></i> Data Penarikan Dana Afiliasi</h6>
                                <div class="btn-group btn-group-sm">
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=all" class="btn btn-outline-secondary <?php echo $filterWdStatus === 'all' ? 'active' : ''; ?>">Semua</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=pending" class="btn btn-outline-warning <?php echo $filterWdStatus === 'pending' ? 'active' : ''; ?>">Pending</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=approved" class="btn btn-outline-info <?php echo $filterWdStatus === 'approved' ? 'active' : ''; ?>">Diproses</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=completed" class="btn btn-outline-success <?php echo $filterWdStatus === 'completed' ? 'active' : ''; ?>">Selesai</a>
                                    <a href="kelola_afiliasi.php?tab=withdrawals&wd_status=rejected" class="btn btn-outline-danger <?php echo $filterWdStatus === 'rejected' ? 'active' : ''; ?>">Ditolak</a>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Tanggal</th>
                                            <th>Afiliator</th>
                                            <th>Nominal</th>
                                            <th>Rekening Tujuan</th>
                                            <th>Status</th>
                                            <th>Catatan Admin</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($withdrawals)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Tidak ada data penarikan untuk filter ini.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($withdrawals as $w): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $w['id_withdrawal']; ?></td>
                                                    <td><small><?php echo date('d M Y, H:i', strtotime((string) $w['tanggal_pengajuan'])); ?></small></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars((string) $w['nama_lengkap']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars((string) $w['username']); ?> &bull; Kode: <code><?php echo htmlspecialchars((string) $w['affiliate_code']); ?></code></small>
                                                        <?php if (!empty($w['no_telepon'])): ?>
                                                            <br><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars((string) $w['no_telepon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="fw-bold text-success fs-6">Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border text-uppercase me-1"><?php echo htmlspecialchars((string) $w['tipe_tujuan']); ?></span>
                                                        <strong><?php echo htmlspecialchars((string) $w['nama_bank']); ?></strong>
                                                        <br><span class="font-monospace fw-bold"><?php echo htmlspecialchars((string) $w['nomor_rekening']); ?></span>
                                                        <br><small class="text-muted">an <?php echo htmlspecialchars((string) $w['nama_pemilik']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($w['status'] === 'completed'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i> Selesai Transfer</span>
                                                        <?php elseif ($w['status'] === 'approved'): ?>
                                                            <span class="badge bg-info text-dark"><i class="fas fa-spinner fa-spin me-1"></i> Diproses</span>
                                                        <?php elseif ($w['status'] === 'rejected'): ?>
                                                            <span class="badge bg-danger"><i class="fas fa-times me-1"></i> Ditolak</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo htmlspecialchars((string) ($w['catatan_admin'] ?? '-')); ?></small></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalProcessWd<?php echo (int) $w['id_withdrawal']; ?>">
                                                            <i class="fas fa-edit me-1"></i> Proses
                                                        </button>

                                                        <!-- Modal Proses Penarikan -->
                                                        <div class="modal fade" id="modalProcessWd<?php echo (int) $w['id_withdrawal']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog text-start">
                                                                <form method="POST" action="kelola_afiliasi.php" class="modal-content">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                    <input type="hidden" name="action" value="update_withdrawal_status">
                                                                    <input type="hidden" name="id_withdrawal" value="<?php echo (int) $w['id_withdrawal']; ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold">Proses Penarikan #<?php echo (int) $w['id_withdrawal']; ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <div class="p-3 bg-light rounded mb-3">
                                                                            <div><strong>Afiliator:</strong> <?php echo htmlspecialchars((string) $w['nama_lengkap']); ?></div>
                                                                            <div><strong>Nominal:</strong> <span class="text-success fw-bold">Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?></span></div>
                                                                            <div><strong>Transfer ke:</strong> <?php echo htmlspecialchars((string) $w['nama_bank'] . ' ' . $w['nomor_rekening'] . ' an ' . $w['nama_pemilik']); ?></div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Pilih Status Baru</label>
                                                                             <?php $nextStatuses = ['pending' => ['pending', 'approved', 'rejected'], 'approved' => ['approved', 'completed', 'rejected'], 'completed' => ['completed'], 'rejected' => ['rejected']][(string) $w['status']] ?? []; ?>
                                                                             <select name="status" class="form-select" required>
                                                                                 <?php foreach ($nextStatuses as $nextStatus): ?>
                                                                                     <option value="<?php echo htmlspecialchars($nextStatus); ?>" <?php echo $w['status'] === $nextStatus ? 'selected' : ''; ?>><?php echo $nextStatus === 'pending' ? 'Pending (Menunggu)' : ($nextStatus === 'approved' ? 'Approved (Sedang Ditransfer)' : ($nextStatus === 'completed' ? 'Completed (Selesai Ditransfer)' : 'Rejected (Tolak & Refund Saldo)')); ?></option>
                                                                                 <?php endforeach; ?>
                                                                             </select>
                                                                            <div class="form-text">Jika memilih <em>Rejected</em>, saldo sebesar Rp <?php echo number_format((int) $w['nominal'], 0, ',', '.'); ?> akan otomatis dikembalikan ke akun member.</div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Catatan Admin (Opsional)</label>
                                                                            <textarea name="admin_notes" class="form-control" rows="2" placeholder="Contoh: Transfer via BCA ref 9281729 / Nomor rekening tidak valid"><?php echo htmlspecialchars((string) ($w['catatan_admin'] ?? '')); ?></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($activeTab === 'affiliates'): ?>
                            <!-- TAB 2: DAFTAR AFILIATOR -->
                            <div class="row gx-3 gy-2 align-items-center mb-3">
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-users me-1"></i> Data Afiliator Terdaftar</h6>
                                </div>
                                <div class="col-md-6">
                                    <form method="GET" action="kelola_afiliasi.php" class="d-flex gap-2">
                                        <input type="hidden" name="tab" value="affiliates">
                                        <input type="text" name="search_aff" class="form-control form-control-sm" placeholder="Cari nama, username, kode..." value="<?php echo htmlspecialchars($searchAff); ?>">
                                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-search"></i></button>
                                        <?php if ($searchAff !== ''): ?>
                                            <a href="kelola_afiliasi.php?tab=affiliates" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm mb-3" role="group" aria-label="Filter status afiliator">
                                <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=all" class="btn btn-outline-secondary <?php echo $filterAffStatus === 'all' ? 'active' : ''; ?>">Semua</a>
                                <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=pending" class="btn btn-outline-warning <?php echo $filterAffStatus === 'pending' ? 'active' : ''; ?>">Pending<?php if ($stats['pending_affiliate_count'] > 0): ?> (<?php echo $stats['pending_affiliate_count']; ?>)<?php endif; ?></a>
                                <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=aktif" class="btn btn-outline-success <?php echo $filterAffStatus === 'aktif' ? 'active' : ''; ?>">Aktif</a>
                                <a href="kelola_afiliasi.php?tab=affiliates&amp;aff_status=nonaktif" class="btn btn-outline-danger <?php echo $filterAffStatus === 'nonaktif' ? 'active' : ''; ?>">Nonaktif</a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Afiliator</th>
                                            <th>Status</th>
                                            <th>Kode Afiliasi</th>
                                            <th>Klinik</th>
                                            <th>Saldo Komisi</th>
                                            <th>Total Ditarik</th>
                                            <th>Pasien Berhasil</th>
                                            <th class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($affiliates)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center py-4 text-muted">Belum ada afiliator ditemukan.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($affiliates as $aff): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $aff['id_user']; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars((string) $aff['nama_lengkap']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars((string) $aff['username']); ?></small>
                                                        <?php if (!empty($aff['no_telepon'])): ?>
                                                            <br><small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars((string) $aff['no_telepon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($aff['status_afiliasi'] === 'aktif'): ?>
                                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>Aktif</span>
                                                        <?php elseif ($aff['status_afiliasi'] === 'nonaktif'): ?>
                                                            <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Nonaktif</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark font-monospace fs-6 px-2 py-1"><?php echo htmlspecialchars((string) $aff['affiliate_code']); ?></span>
                                                    </td>
                                                    <td><small><?php echo htmlspecialchars((string) ($aff['nama_cabang'] ?? '-')); ?></small></td>
                                                    <td class="fw-bold text-success">Rp <?php echo number_format((int) $aff['total_komisi'], 0, ',', '.'); ?></td>
                                                    <td>Rp <?php echo number_format((int) $aff['total_ditarik'], 0, ',', '.'); ?></td>
                                                    <td><span class="badge bg-info text-dark"><?php echo number_format((int) $aff['total_referral'], 0, ',', '.'); ?> pasien</span></td>
                                                    <td class="text-center">
                                                        <form method="POST" action="kelola_afiliasi.php?tab=affiliates" class="d-flex gap-1 mb-2">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                            <input type="hidden" name="action" value="update_affiliate_status">
                                                            <input type="hidden" name="target_user_id" value="<?php echo (int) $aff['id_user']; ?>">
                                                            <select name="status_afiliasi" class="form-select form-select-sm" aria-label="Status afiliator">
                                                                <option value="pending" <?php echo $aff['status_afiliasi'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="aktif" <?php echo $aff['status_afiliasi'] === 'aktif' ? 'selected' : ''; ?>>Approve / Aktif</option>
                                                                <option value="nonaktif" <?php echo $aff['status_afiliasi'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Simpan status"><i class="fas fa-check"></i></button>
                                                        </form>
                                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEditCode<?php echo (int) $aff['id_user']; ?>" title="Ubah Kode Afiliasi">
                                                            <i class="fas fa-tag me-1"></i> Edit Kode
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAdjustCommission<?php echo (int) $aff['id_user']; ?>" title="Koreksi Saldo Komisi">
                                                            <i class="fas fa-wallet me-1"></i> Saldo
                                                        </button>

                                                        <!-- Modal Edit Kode Afiliasi -->
                                                        <div class="modal fade" id="modalEditCode<?php echo (int) $aff['id_user']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog text-start">
                                                                <form method="POST" action="kelola_afiliasi.php" class="modal-content">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                    <input type="hidden" name="action" value="set_affiliate_code">
                                                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $aff['id_user']; ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold">Ubah Kode Afiliasi</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p class="small text-muted mb-2">Member: <strong><?php echo htmlspecialchars((string) $aff['nama_lengkap']); ?></strong></p>
                                                                        <div class="mb-3">
                                                                            <label class="form-label fw-semibold">Kode Afiliasi Baru</label>
                                                                            <input type="text" name="affiliate_code" class="form-control font-monospace text-uppercase" value="<?php echo htmlspecialchars((string) $aff['affiliate_code']); ?>" required minlength="4" maxlength="15">
                                                                            <div class="form-text">4-15 karakter huruf dan angka (A-Z, 0-9).</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Kode</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>

                                                        <!-- Modal Koreksi Saldo Komisi -->
                                                        <div class="modal fade" id="modalAdjustCommission<?php echo (int) $aff['id_user']; ?>" tabindex="-1" aria-hidden="true">
                                                            <div class="modal-dialog text-start">
                                                                <form method="POST" action="kelola_afiliasi.php?tab=affiliates" class="modal-content">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                                    <input type="hidden" name="action" value="adjust_commission">
                                                                    <input type="hidden" name="target_user_id" value="<?php echo (int) $aff['id_user']; ?>">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title fw-bold">Koreksi Saldo Komisi</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p class="small text-muted mb-2">Afiliator: <strong><?php echo htmlspecialchars((string) $aff['nama_lengkap']); ?></strong></p>
                                                                        <p class="small mb-3">Saldo saat ini: <strong class="text-success">Rp <?php echo number_format((int) $aff['total_komisi'], 0, ',', '.'); ?></strong></p>
                                                                        <div class="row g-2 mb-3">
                                                                            <div class="col-md-5">
                                                                                <label class="form-label fw-semibold">Jenis</label>
                                                                                <select name="adjustment_type" class="form-select" required>
                                                                                    <option value="credit">Tambah Saldo</option>
                                                                                    <option value="debit">Kurangi Saldo</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-md-7">
                                                                                <label class="form-label fw-semibold">Nominal (Rp)</label>
                                                                                <input type="number" name="adjustment_amount" class="form-control" min="1" max="1000000000" step="1" required>
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <label class="form-label fw-semibold">Alasan</label>
                                                                            <textarea name="adjustment_note" class="form-control" rows="3" maxlength="255" placeholder="Contoh: Koreksi komisi transaksi #123" required></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Koreksi</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($activeTab === 'orders'): ?>
                            <!-- TAB 3: RIWAYAT KOMISI BOOKING -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-receipt me-1"></i> Transaksi Menggunakan Referral</h6>
                                <span class="badge bg-light text-dark border"><?php echo count($commissionOrders); ?> transaksi terbaru</span>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID Order</th>
                                            <th>Tgl Kunjungan</th>
                                            <th>Pasien</th>
                                            <th>Layanan</th>
                                            <th>Klinik</th>
                                            <th>Afiliator (Referrer)</th>
                                            <th>Status Booking</th>
                                            <th>Komisi Afiliasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($commissionOrders)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">Belum ada booking yang menggunakan kode referral.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($commissionOrders as $co): ?>
                                                <tr>
                                                    <td>#<?php echo (int) $co['id_order']; ?></td>
                                                    <td><small><?php echo date('d M Y, H:i', strtotime((string) $co['tanggal_treatment'])); ?></small></td>
                                                    <td><strong><?php echo htmlspecialchars((string) $co['patient_name']); ?></strong></td>
                                                    <td><?php echo htmlspecialchars((string) $co['nama_layanan']); ?></td>
                                                    <td><small><?php echo htmlspecialchars((string) ($co['nama_cabang'] ?? '-')); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-primary text-white font-monospace"><?php echo htmlspecialchars((string) $co['referred_by']); ?></span>
                                                        <?php if (!empty($co['referrer_name'])): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars((string) $co['referrer_name']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($co['status_order'] === 'completed'): ?>
                                                            <span class="badge bg-success">Selesai</span>
                                                        <?php elseif ($co['status_order'] === 'confirmed'): ?>
                                                            <span class="badge bg-primary">Terkonfirmasi</span>
                                                        <?php elseif ($co['status_order'] === 'cancelled'): ?>
                                                            <span class="badge bg-danger">Batal</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Pending</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($co['komisi_status'] === 'paid'): ?>
                                                            <span class="fw-bold text-success">+Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-success-subtle text-success">Sudah Dibayar</small>
                                                        <?php elseif ($co['komisi_status'] === 'cancelled'): ?>
                                                            <span class="text-muted text-decoration-line-through">Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-danger-subtle text-danger">Dibatalkan</small>
                                                        <?php else: ?>
                                                            <span class="text-muted">Est. Rp <?php echo number_format((int) $co['komisi_nominal'], 0, ',', '.'); ?></span>
                                                            <br><small class="badge bg-warning-subtle text-dark">Menunggu Treatment</small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <footer class="admin-footer mt-auto">
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
        </div>
    </div>

    <script src="../js/bootstrap.bundle.min.js"></script>
    <script src="../js/script.js"></script>
</body>
</html>
<?php
$conn->close();
