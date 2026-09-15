<?php
declare(strict_types=1);

// CLI: impor member + order Purworejo dari hasil tarikan AIDO API (JSONL).
// Idempoten: aman dijalankan ulang (kunci aido_mr / aido_trx_id).
//
// Contoh:
//   php tools/import_aido_purworejo.php \
//     --patients=/opt/klinikdrwestetika/.import/aido-purworejo-patients.jsonl \
//     --visits=/opt/klinikdrwestetika/.import/aido-purworejo-visits.jsonl
//
// NIK AIDO tidak diimpor (tidak ada kolomnya dan tidak dibutuhkan operasional web).

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya via CLI.');
}

$options = getopt('', ['patients:', 'visits:']);
$patientsPath = (string) ($options['patients'] ?? '');
$visitsPath = (string) ($options['visits'] ?? '');
if ($patientsPath === '' || !is_file($patientsPath)) {
    fwrite(STDERR, "File patients JSONL tidak ditemukan.\n");
    exit(1);
}
if ($visitsPath === '' || !is_file($visitsPath)) {
    fwrite(STDERR, "File visits JSONL tidak ditemukan.\n");
    exit(1);
}

require __DIR__ . '/../config.php';

function clean_name(?string $name): string
{
    $name = trim((string) $name);
    $name = preg_replace('/\s*\(.*$/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function norm_name(?string $name): string
{
    return mb_strtolower(clean_name($name));
}

function to_wib_datetime(?string $settleIso, ?string $consultationDmy): string
{
    if ($settleIso) {
        try {
            $dt = new DateTime($settleIso);
            $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // fall through to consultation date
        }
    }
    if ($consultationDmy && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $consultationDmy, $m)) {
        return sprintf('%04d-%02d-%02d 08:00:00', (int) $m[3], (int) $m[2], (int) $m[1]);
    }
    return date('Y-m-d H:i:s');
}

function rupiah(int $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Cabang Purworejo.
$stmt = $conn->prepare("SELECT id_cabang FROM cabang WHERE slug = 'purworejo' LIMIT 1");
$stmt->execute();
$cabang = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cabang) {
    fwrite(STDERR, "Cabang purworejo tidak ditemukan.\n");
    exit(1);
}
$cabangId = (int) $cabang['id_cabang'];

// Layanan generik untuk kategori AIDO (tidak aktif agar tidak muncul di form booking).
$layananDefs = [
    'obat' => 'Obat / Farmasi (AIDO)',
    'tindakan' => 'Tindakan (AIDO)',
    'lab' => 'Laboratorium (AIDO)',
    'radio' => 'Radiologi (AIDO)',
    'lainnya' => 'Lainnya (AIDO)',
    'konsultasi' => 'Konsultasi (AIDO)',
];
$layananIds = [];
foreach ($layananDefs as $key => $nama) {
    $stmt = $conn->prepare('SELECT id_layanan FROM layanan WHERE nama_layanan = ? LIMIT 1');
    $stmt->bind_param('s', $nama);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $layananIds[$key] = (int) $row['id_layanan'];
        continue;
    }
    $desc = 'Kategori impor AIDO, tidak untuk booking.';
    $harga = 0;
    $status = 'tidak_aktif';
    $stmt = $conn->prepare('INSERT INTO layanan (nama_layanan, deskripsi_singkat, harga, status_layanan) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssis', $nama, $desc, $harga, $status);
    $stmt->execute();
    $layananIds[$key] = (int) $stmt->insert_id;
    $stmt->close();
}

// 1) Impor member.
$memberByMr = [];   // mr => id_user
$memberByName = []; // norm name => [id_user,...]
$fh = fopen($patientsPath, 'r');
$nPat = 0;
$stmtSel = $conn->prepare('SELECT id_user FROM user WHERE aido_mr = ? LIMIT 1');
$stmtIns = $conn->prepare("INSERT INTO user (nama_lengkap, username, password, email, no_telepon, alamat, aido_mr, auth_provider) VALUES (?, ?, NULL, ?, ?, ?, ?, 'local') ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), email = VALUES(email), no_telepon = VALUES(no_telepon), alamat = VALUES(alamat), aido_mr = VALUES(aido_mr)");
$conn->begin_transaction();
try {
    while (($line = fgets($fh)) !== false) {
        $p = json_decode(trim($line), true);
        if (!is_array($p)) {
            continue;
        }
        $mr = trim((string) ($p['mrNumber'] ?? ''));
        if ($mr === '') {
            continue;
        }
        $nama = clean_name(trim((string) ($p['firstName'] ?? '')) . ' ' . trim((string) ($p['lastName'] ?? '')));
        if ($nama === '') {
            $nama = $mr;
        }
        $username = mb_strtolower('aido_pwj_' . preg_replace('/[^a-z0-9]/i', '', $mr));
        $phone = trim((string) ($p['waNumber'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($p['phoneNumber'] ?? ''));
        }
        $emailV = trim((string) ($p['email'] ?? ''));
        if ($emailV === '') {
            $emailV = null;
        }
        $phoneV = $phone !== '' ? $phone : null;
        $alamatV = trim((string) ($p['address'] ?? ''));
        if ($alamatV === '') {
            $alamatV = null;
        }
        $stmtIns->bind_param('ssssss', $nama, $username, $emailV, $phoneV, $alamatV, $mr);
        $stmtIns->execute();
        $idUser = (int) $stmtIns->insert_id;
        if ($idUser === 0) {
            $stmtSel->bind_param('s', $mr);
            $stmtSel->execute();
            $row = $stmtSel->get_result()->fetch_assoc();
            $idUser = $row ? (int) $row['id_user'] : 0;
        }
        if ($idUser > 0) {
            $memberByMr[$mr] = $idUser;
            $memberByName[norm_name($nama)][] = $idUser;
            $nPat++;
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Impor member gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
fclose($fh);
$stmtSel->close();
$stmtIns->close();
fwrite(STDOUT, "Member AIDO diproses: {$nPat}\n");

// Indeks nama tambahan dari DB (agar rerun / nama ganda konsisten).
$res = $conn->query('SELECT id_user, nama_lengkap FROM user WHERE aido_mr IS NOT NULL');
while ($res && ($row = $res->fetch_assoc())) {
    $memberByName[norm_name($row['nama_lengkap'])][] = (int) $row['id_user'];
}
if ($res) {
    $res->close();
}
foreach ($memberByName as $k => $ids) {
    $memberByName[$k] = array_values(array_unique($ids));
}

$stmtStubSel = $conn->prepare('SELECT id_user FROM user WHERE username = ? LIMIT 1');
$stmtStubIns = $conn->prepare("INSERT INTO user (nama_lengkap, username, password, auth_provider) VALUES (?, ?, NULL, 'local') ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap)");

// 2) Impor order (dedup pasangan registration+trx).
$fh = fopen($visitsPath, 'r');
$seen = [];
$nOrd = 0;
$nSkip = 0;
$stmtOrd = $conn->prepare('INSERT INTO `order` (id_user, id_layanan, id_cabang, aido_trx_id, tanggal_treatment, catatan_tambahan, status_order, tanggal_order_dibuat) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE id_user = VALUES(id_user), status_order = VALUES(status_order), catatan_tambahan = VALUES(catatan_tambahan)');
$conn->begin_transaction();
try {
    while (($line = fgets($fh)) !== false) {
        $v = json_decode(trim($line), true);
        if (!is_array($v)) {
            continue;
        }
        $pair = (string) ($v['registrationId'] ?? '') . ':' . (string) ($v['trxId'] ?? '');
        if (isset($seen[$pair])) {
            $nSkip++;
            continue;
        }
        $seen[$pair] = true;

        $namaVisit = trim((string) ($v['patientName'] ?? $v['firstNameDecoded'] ?? ''));
        $key = norm_name($namaVisit);
        if (isset($memberByName[$key]) && count($memberByName[$key]) > 0) {
            $idUser = $memberByName[$key][0];
        } else {
            $stubUsername = 'aido_pwj_trx_' . preg_replace('/\D/', '', (string) ($v['trxId'] ?? '0'));
            $stmtStubSel->bind_param('s', $stubUsername);
            $stmtStubSel->execute();
            $row = $stmtStubSel->get_result()->fetch_assoc();
            if ($row) {
                $idUser = (int) $row['id_user'];
            } else {
                $stubNama = clean_name($namaVisit);
                if ($stubNama === '') {
                    $stubNama = $stubUsername;
                }
                $stmtStubIns->bind_param('ss', $stubNama, $stubUsername);
                $stmtStubIns->execute();
                $idUser = (int) $stmtStubIns->insert_id;
                $memberByName[$key][] = $idUser;
            }
        }

        $vals = [
            'obat' => (float) ($v['totalObat'] ?? 0),
            'tindakan' => (float) ($v['totalTindakan'] ?? 0),
            'lab' => (float) ($v['totalLaboratorium'] ?? 0),
            'radio' => (float) ($v['totalRadiologi'] ?? 0),
            'lainnya' => (float) ($v['totalLainnya'] ?? 0) + (float) ($v['totalConsumable'] ?? 0),
        ];
        $dom = 'konsultasi';
        $max = 0.0;
        foreach ($vals as $k => $amt) {
            if ($amt > $max) {
                $max = $amt;
                $dom = $k;
            }
        }
        $idLayanan = $layananIds[$dom];
        $total = (int) round((float) ($v['totalBill'] ?? 0));
        $status = (($v['paidStatus'] ?? '') === 'PAID') ? 'completed' : 'pending';
        $tgl = to_wib_datetime($v['settleDate'] ?? null, $v['consultationDate'] ?? null);

        $payParts = [];
        foreach ((array) ($v['payment'] ?? []) as $pay) {
            $payParts[] = trim((string) ($pay['paymentOption'] ?? '')) . ' ' . rupiah((int) round((float) ($pay['totalAmount'] ?? 0)));
        }
        $catatan = sprintf(
            'AIDO %s %s | Total %s | %s | Dokter: %s | %s%s',
            (string) ($v['trxId'] ?? ''),
            trim((string) (($v['payment'][0]['trxNumber'] ?? '') ?? '')),
            rupiah($total),
            implode(', ', array_filter($payParts)) !== '' ? implode(', ', array_filter($payParts)) : '-',
            trim((string) ($v['doctorName'] ?? '-')),
            trim((string) ($v['visitType'] ?? '')),
            !empty($v['diagnoseName']) ? ' | Dx: ' . trim((string) $v['diagnoseName']) : ''
        );
        $trxId = (int) ($v['trxId'] ?? 0);
        if ($trxId <= 0) {
            $nSkip++;
            continue;
        }
        $stmtOrd->bind_param('iiiissss', $idUser, $idLayanan, $cabangId, $trxId, $tgl, $catatan, $status, $tgl);
        $stmtOrd->execute();
        $nOrd++;
        if ($nOrd % 2000 === 0) {
            fwrite(STDOUT, "Order diproses: {$nOrd} (skip duplikat: {$nSkip})\n");
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, 'Impor order gagal: ' . $e->getMessage() . "\n");
    exit(1);
}
fclose($fh);
$stmtOrd->close();
$stmtStubSel->close();
$stmtStubIns->close();
fwrite(STDOUT, "Order AIDO diproses: {$nOrd} (duplikat dilewati: {$nSkip})\n");
$conn->close();
