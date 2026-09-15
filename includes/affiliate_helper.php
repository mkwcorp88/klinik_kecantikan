<?php
declare(strict_types=1);

/**
 * Helper Sistem Afiliasi terintegrasi untuk Klinik DRW Estetika
 * Mengadaptasi skema dan alur komisi dari DRW Prime (10% komisi, tracking URL ?ref=, withdrawal, dan audit log).
 */

/**
 * Generate kode afiliasi unik berbasis nama pengguna atau prefix acak
 * Format: 2-3 huruf inisial + 3 karakter alphanumeric (contoh: "SR7K9", "DRW8X2")
 */
function drw_generate_affiliate_code(mysqli $conn, string $name = '', ?int $userId = null): string
{
    $cleanName = preg_replace('/[^A-Za-z]/', '', $name);
    $prefix = mb_strtoupper(mb_substr($cleanName !== '' ? $cleanName : 'DRW', 0, 2));
    if (mb_strlen($prefix) < 2) {
        $prefix = 'DR';
    }

    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Tanpa karakter ambigu (0, O, 1, I)
    $maxAttempts = 12;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $randomPart = '';
        for ($i = 0; $i < 3; $i++) {
            $randomPart .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $candidate = $prefix . $randomPart;

        if ($userId !== null) {
            $stmt = $conn->prepare('SELECT id_user FROM user WHERE affiliate_code = ? AND id_user != ? LIMIT 1');
            $stmt->bind_param('si', $candidate, $userId);
        } else {
            $stmt = $conn->prepare('SELECT id_user FROM user WHERE affiliate_code = ? LIMIT 1');
            $stmt->bind_param('s', $candidate);
        }
        $stmt->execute();
        $isTaken = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$isTaken) {
            return $candidate;
        }
    }

    // Fallback: prefix AF + 4 random characters
    $fallback = 'AF' . mb_strtoupper(bin2hex(random_bytes(2)));
    return $fallback;
}

/**
 * Validasi format dan isi kode afiliasi kustom
 */
function drw_validate_affiliate_code(string $code): array
{
    $code = mb_strtoupper(trim($code));

    if (mb_strlen($code) < 4 || mb_strlen($code) > 15) {
        return ['valid' => false, 'error' => 'Kode afiliasi harus terdiri dari 4 sampai 15 karakter.'];
    }

    if (!preg_match('/^[A-Z0-9]+$/', $code)) {
        return ['valid' => false, 'error' => 'Kode hanya boleh berisi huruf dan angka (A-Z, 0-9) tanpa spasi.'];
    }

    $forbidden = [
        'ADMIN', 'STAFF', 'SYSTEM', 'NULL', 'UNDEFINED', 'ROOT', 'SUPERADMIN',
        'ANJING', 'BANGSAT', 'KONTOL', 'MEMEK', 'BAJINGAN', 'TOLOL', 'GOBLOK', 'JEMBUT'
    ];

    foreach ($forbidden as $word) {
        if (str_contains($code, $word)) {
            return ['valid' => false, 'error' => 'Kode mengandung kata yang tidak diperbolehkan.'];
        }
    }

    return ['valid' => true, 'code' => $code];
}

/**
 * Ambil atau buat otomatis kode afiliasi untuk user yang sedang login
 */
function drw_get_user_affiliate_code(mysqli $conn, int $userId, string $name = ''): string
{
    $stmt = $conn->prepare('SELECT affiliate_code FROM user WHERE id_user = ? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['affiliate_code'])) {
        return (string) $row['affiliate_code'];
    }

    $newCode = drw_generate_affiliate_code($conn, $name, $userId);
    $stmt = $conn->prepare('UPDATE user SET affiliate_code = ?, affiliate_code_updated_at = NOW() WHERE id_user = ?');
    $stmt->bind_param('si', $newCode, $userId);
    $stmt->execute();
    $stmt->close();

    return $newCode;
}

/**
 * Menangkap dan menyimpan kode referral dari URL (?ref=KODE) ke sesi dan cookie 30 hari
 */
function drw_capture_referral(?string $paramRef = null): ?string
{
    $candidate = $paramRef ?? ($_GET['ref'] ?? null);
    if (is_string($candidate)) {
        $clean = mb_strtoupper(trim($candidate));
        if (preg_match('/^[A-Z0-9]{3,20}$/', $clean)) {
            $_SESSION['affiliate_ref'] = $clean;
            $secure = drw_is_https();
            setcookie('drw_affiliate_ref', $clean, [
                'expires' => time() + (30 * 86400),
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            return $clean;
        }
    }

    return $_SESSION['affiliate_ref'] ?? $_COOKIE['drw_affiliate_ref'] ?? null;
}

/**
 * Mendapatkan data referrer aktif (mencegah self-referral)
 */
function drw_get_active_referral(mysqli $conn, ?int $currentUserId = null): ?array
{
    $code = drw_capture_referral();
    if ($code === null || $code === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT id_user, nama_lengkap, affiliate_code, status_afiliasi FROM user WHERE affiliate_code = ? LIMIT 1');
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || ($row['status_afiliasi'] ?? 'nonaktif') !== 'aktif') {
        return null;
    }

    // Blokir self-referral: pasien tidak bisa mereferensikan dirinya sendiri
    if ($currentUserId !== null && (int) $row['id_user'] === $currentUserId) {
        return null;
    }

    return [
        'id_user' => (int) $row['id_user'],
        'nama_lengkap' => (string) $row['nama_lengkap'],
        'affiliate_code' => (string) $row['affiliate_code'],
    ];
}

/**
 * Menghitung dan memberikan komisi afiliasi saat order ditandai 'completed'
 * Skema: Komisi 10% dari total pembayaran atau harga layanan
 */
function drw_process_order_commission(mysqli $conn, int $orderId, ?int $actualPayment = null): bool
{
    $stmt = $conn->prepare('
        SELECT o.id_order, o.referrer_id, o.komisi_nominal, o.komisi_status, o.total_bayar,
               l.harga, l.nama_layanan, u.nama_lengkap AS patient_name,
               u_ref.status_afiliasi AS referrer_status
        FROM `order` o
        JOIN layanan l ON l.id_layanan = o.id_layanan
        JOIN user u ON u.id_user = o.id_user
        LEFT JOIN user u_ref ON u_ref.id_user = o.referrer_id
        WHERE o.id_order = ? LIMIT 1
    ');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || empty($order['referrer_id']) || ($order['referrer_status'] ?? 'nonaktif') !== 'aktif') {
        return false;
    }

    // Hanya proses jika belum pernah dibayar
    if ($order['komisi_status'] === 'paid') {
        return false;
    }

    $referrerId = (int) $order['referrer_id'];
    $basePrice = $actualPayment ?? ($order['total_bayar'] ? (int) $order['total_bayar'] : (int) $order['harga']);
    $commissionRate = 0.10; // 10% komisi afiliasi DRW
    $commissionAmount = (int) round($basePrice * $commissionRate);

    $conn->begin_transaction();
    try {
        // 1) Update order
        $stmtUpd = $conn->prepare('
            UPDATE `order`
            SET komisi_nominal = ?, komisi_status = "paid", total_bayar = ?
            WHERE id_order = ?
        ');
        $stmtUpd->bind_param('iii', $commissionAmount, $basePrice, $orderId);
        $stmtUpd->execute();
        $stmtUpd->close();

        if ($commissionAmount > 0) {
            // 2) Tambah saldo komisi & total referral ke user referrer
            $stmtUser = $conn->prepare('
                UPDATE user
                SET total_komisi = total_komisi + ?, total_referral = total_referral + 1
                WHERE id_user = ?
            ');
            $stmtUser->bind_param('ii', $commissionAmount, $referrerId);
            $stmtUser->execute();
            $stmtUser->close();

            // 3) Catat ke affiliate_log
            $desc = sprintf(
                'Komisi 10%% booking #%d (%s) - Pasien: %s',
                $orderId,
                $order['nama_layanan'],
                $order['patient_name']
            );
            $stmtLog = $conn->prepare('
                INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan, id_order)
                VALUES (?, "komisi_masuk", ?, ?, ?)
            ');
            $stmtLog->bind_param('iisi', $referrerId, $commissionAmount, $desc, $orderId);
            $stmtLog->execute();
            $stmtLog->close();
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Gagal memproses komisi order #' . $orderId . ': ' . $e->getMessage());
        return false;
    }
}

/**
 * Batalkan komisi afiliasi jika order dibatalkan setelah sebelumnya selesai
 */
function drw_revert_order_commission(mysqli $conn, int $orderId): bool
{
    $stmt = $conn->prepare('SELECT referrer_id, komisi_nominal, komisi_status FROM `order` WHERE id_order = ? LIMIT 1');
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || empty($order['referrer_id'])) {
        return false;
    }

    $referrerId = (int) $order['referrer_id'];
    $commission = (int) ($order['komisi_nominal'] ?? 0);

    // Jika belum paid (masih pending), cukup ubah status ke cancelled
    if ($order['komisi_status'] !== 'paid') {
        $stmt = $conn->prepare('UPDATE `order` SET komisi_status = "cancelled" WHERE id_order = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    $conn->begin_transaction();
    try {
        if ($commission > 0) {
            $stmtUser = $conn->prepare('
                UPDATE user
                SET total_komisi = GREATEST(0, total_komisi - ?),
                    total_referral = GREATEST(0, total_referral - 1)
                WHERE id_user = ?
            ');
            $stmtUser->bind_param('ii', $commission, $referrerId);
            $stmtUser->execute();
            $stmtUser->close();

            $desc = 'Koreksi pembatalan komisi booking #' . $orderId;
            $negativeNominal = -$commission;
            $stmtLog = $conn->prepare('
                INSERT INTO affiliate_log (id_user, tipe, nominal, keterangan, id_order)
                VALUES (?, "koreksi", ?, ?, ?)
            ');
            $stmtLog->bind_param('iisi', $referrerId, $negativeNominal, $desc, $orderId);
            $stmtLog->execute();
            $stmtLog->close();
        }

        $stmt = $conn->prepare('UPDATE `order` SET komisi_status = "cancelled" WHERE id_order = ?');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Gagal membatalkan komisi order #' . $orderId . ': ' . $e->getMessage());
        return false;
    }
}
