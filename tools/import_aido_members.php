<?php
declare(strict_types=1);

// CLI Tool: Impor member AIDO dari JSON snapshot (aido-members.json atau aido-members-*.json)
// Idempoten: aman dijalankan berulang kali (pencocokan berdasarkan aido_mr)
//
// Contoh penggunaan:
//   php tools/import_aido_members.php --file=../KlinikPratamadrwestetika/data/aido-members.json
//   php tools/import_aido_members.php --file=../KlinikPratamadrwestetika/data/aido-members-kutoarjo.json

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Hanya dapat dijalankan via CLI.');
}

$options = getopt('', ['file:']);
$filePath = (string) ($options['file'] ?? '');

if ($filePath === '' || !is_file($filePath)) {
    fwrite(STDERR, "Penggunaan: php tools/import_aido_members.php --file=/path/ke/aido-members.json\n");
    exit(1);
}

require_once __DIR__ . '/../config.php';

function clean_patient_name(?string $name): string
{
    $name = trim((string) $name);
    $name = preg_replace('/\s*\(.*$/', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

// 1) Load pemetaan cabang dari database
$cabangMap = []; // 'kutoarjo' => id_cabang
$res = $conn->query("SELECT id_cabang, slug, nama_cabang FROM cabang");
while ($row = $res->fetch_assoc()) {
    $cabangMap[mb_strtolower($row['slug'])] = (int) $row['id_cabang'];
}
$res->close();

if (empty($cabangMap)) {
    fwrite(STDERR, "Tabel cabang kosong. Pastikan migrasi database sudah dijalankan.\n");
    exit(1);
}

// 2) Baca file JSON
$raw = file_get_contents($filePath);
$data = json_decode($raw, true);

if (!is_array($data) || !isset($data['members']) || !is_array($data['members'])) {
    fwrite(STDERR, "Format JSON tidak valid. Pastikan berisi array 'members'.\n");
    exit(1);
}

$members = $data['members'];
$total = count($members);
fwrite(STDOUT, "Membaca {$total} member dari file: {$filePath}\n");

// Prepared statements for idempotent upsert
$stmtSel = $conn->prepare('SELECT id_user FROM user WHERE aido_mr = ? LIMIT 1');
$stmtUpd = $conn->prepare('UPDATE user SET nama_lengkap = ?, email = ?, no_telepon = ?, alamat = ?, id_cabang = ? WHERE aido_mr = ?');
$stmtIns = $conn->prepare('INSERT INTO user (nama_lengkap, username, password, email, no_telepon, alamat, aido_mr, id_cabang, auth_provider) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, \'local\')');

$inserted = 0;
$updated = 0;
$skipped = 0;

$conn->begin_transaction();

try {
    foreach ($members as $index => $m) {
        $mr = trim((string) ($m['mrNumber'] ?? ''));
        if ($mr === '') {
            $skipped++;
            continue;
        }

        $nama = clean_patient_name($m['name'] ?? '');
        if ($nama === '') {
            $nama = $mr;
        }

        $branchKey = mb_strtolower((string) ($m['branchKey'] ?? ''));
        $idCabang = $cabangMap[$branchKey] ?? null;

        // Fallback pencocokan cabang dari nama
        if ($idCabang === null) {
            $branchName = mb_strtolower((string) ($m['branchName'] ?? ''));
            foreach ($cabangMap as $slug => $id) {
                if (str_contains($branchName, $slug)) {
                    $idCabang = $id;
                    break;
                }
            }
        }

        $wa = trim((string) ($m['waNumber'] ?? ''));
        $phone = trim((string) ($m['phoneNumber'] ?? ''));
        $noTelp = $wa !== '' ? $wa : ($phone !== '' ? $phone : null);

        $email = trim((string) ($m['email'] ?? ''));
        $emailV = $email !== '' ? $email : null;

        $address = trim((string) ($m['address'] ?? ''));
        $alamatV = $address !== '' ? $address : null;

        $prefix = $branchKey !== '' ? $branchKey : 'aido';
        $username = mb_strtolower('aido_' . $prefix . '_' . preg_replace('/[^a-z0-9]/i', '', $mr));

        // Check existing
        $stmtSel->bind_param('s', $mr);
        $stmtSel->execute();
        $row = $stmtSel->get_result()->fetch_assoc();

        if ($row) {
            $stmtUpd->bind_param('sssssi', $nama, $emailV, $noTelp, $alamatV, $idCabang, $mr);
            $stmtUpd->execute();
            $updated++;
        } else {
            $stmtIns->bind_param('ssssssi', $nama, $username, $emailV, $noTelp, $alamatV, $mr, $idCabang);
            $stmtIns->execute();
            $inserted++;
        }

        if (($index + 1) % 1000 === 0) {
            fwrite(STDOUT, "Diproses: " . ($index + 1) . " / {$total}...\n");
        }
    }

    $conn->commit();
    fwrite(STDOUT, "\n=== IMPOR SELESAI ===\n");
    fwrite(STDOUT, "Ditambahkan : {$inserted}\n");
    fwrite(STDOUT, "Diperbarui   : {$updated}\n");
    fwrite(STDOUT, "Dilewati     : {$skipped}\n");
    fwrite(STDOUT, "Total        : {$total}\n");

} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Impor gagal: " . $e->getMessage() . "\n");
    exit(1);
}

$stmtSel->close();
$stmtUpd->close();
$stmtIns->close();
$conn->close();
