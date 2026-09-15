<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak diizinkan.');
}

if (!drw_is_valid_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Sesi export telah berakhir. Muat ulang halaman Member lalu coba lagi.');
}

function drw_export_date(?string $value): ?string
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
}

function drw_export_csv_value(mixed $value): string
{
    $value = trim((string) ($value ?? ''));
    if (preg_match('/^[\s]*[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}

function drw_export_value(string $column, array $row): string
{
    return match ($column) {
        'name' => (string) ($row['nama_lengkap'] ?? ''),
        'source' => !empty($row['aido_mr']) ? 'AIDO' : 'Website/manual',
        'aido_mr' => (string) ($row['aido_mr'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['no_telepon'] ?? ''),
        'address' => (string) ($row['alamat'] ?? ''),
        'member_branch' => (string) ($row['member_cabang'] ?? ''),
        'registered_at' => (string) ($row['tanggal_daftar'] ?? ''),
        'transaction_id' => !empty($row['aido_trx_id'])
            ? 'AIDO-' . $row['aido_trx_id']
            : (!empty($row['id_order']) ? 'WEB-' . $row['id_order'] : ''),
        'transaction_date' => (string) ($row['tanggal_treatment'] ?? ''),
        'service' => (string) ($row['nama_layanan'] ?? ''),
        'transaction_status' => (string) ($row['status_order'] ?? ''),
        'transaction_branch' => (string) ($row['transaction_cabang'] ?? ''),
        'notes' => (string) ($row['catatan_tambahan'] ?? ''),
        default => '',
    };
}

$adminCabangId = drw_admin_cabang_id();
$branches = drw_admin_fetch_branches($conn);
$allowedBranchIds = array_column($branches, 'id_cabang');

$exportType = ($_POST['export_type'] ?? '') === 'transactions' ? 'transactions' : 'members';
$source = is_string($_POST['source'] ?? null) ? $_POST['source'] : 'all';
$source = in_array($source, ['all', 'aido', 'website'], true) ? $source : 'all';
$memberState = is_string($_POST['member_state'] ?? null) ? $_POST['member_state'] : 'all';
$memberState = in_array($memberState, ['all', 'with_order', 'without_order'], true) ? $memberState : 'all';
$orderStatus = is_string($_POST['order_status'] ?? null) ? $_POST['order_status'] : 'all';
$orderStatus = in_array($orderStatus, ['all', 'pending', 'confirmed', 'completed', 'cancelled'], true) ? $orderStatus : 'all';
$searchTerm = isset($_POST['search']) ? mb_substr(trim((string) $_POST['search']), 0, 100) : '';
$memberDateFrom = drw_export_date(is_string($_POST['member_date_from'] ?? null) ? $_POST['member_date_from'] : null);
$memberDateTo = drw_export_date(is_string($_POST['member_date_to'] ?? null) ? $_POST['member_date_to'] : null);
$transactionDateFrom = drw_export_date(is_string($_POST['transaction_date_from'] ?? null) ? $_POST['transaction_date_from'] : null);
$transactionDateTo = drw_export_date(is_string($_POST['transaction_date_to'] ?? null) ? $_POST['transaction_date_to'] : null);

$selectedCabangId = null;
$failClosed = false;
if ($adminCabangId !== null && $adminCabangId !== -1) {
    $selectedCabangId = $adminCabangId;
} elseif ($adminCabangId === -1) {
    $failClosed = true;
} elseif (isset($_POST['id_cabang']) && ctype_digit((string) $_POST['id_cabang'])) {
    $candidateCabangId = (int) $_POST['id_cabang'];
    if (in_array($candidateCabangId, $allowedBranchIds, true)) {
        $selectedCabangId = $candidateCabangId;
    } else {
        // Reject invalid branch IDs for superadmin rather than defaulting to all clinics.
        http_response_code(400);
        exit('Klinik tidak valid atau tidak aktif.');
    }
}

$memberColumns = [
    'name' => 'Nama Member',
    'source' => 'Sumber',
    'aido_mr' => 'MR AIDO',
    'email' => 'Email',
    'phone' => 'No. Telepon',
    'address' => 'Alamat',
    'member_branch' => 'Klinik Member',
    'registered_at' => 'Tanggal Daftar',
];
$transactionColumns = [
    'transaction_id' => 'ID Transaksi',
    'transaction_date' => 'Tanggal Transaksi',
    'service' => 'Layanan',
    'transaction_status' => 'Status Transaksi',
    'transaction_branch' => 'Klinik Transaksi',
    'notes' => 'Catatan Transaksi',
];
$allowedColumns = $exportType === 'transactions'
    ? array_merge($memberColumns, $transactionColumns)
    : $memberColumns;
$requestedColumns = is_array($_POST['columns'] ?? null) ? $_POST['columns'] : [];
$selectedColumns = [];
foreach ($requestedColumns as $column) {
    if (is_string($column) && isset($allowedColumns[$column])) {
        $selectedColumns[$column] = $allowedColumns[$column];
    }
}
if ($selectedColumns === []) {
    $selectedColumns = $exportType === 'transactions'
        ? array_intersect_key($allowedColumns, array_flip(['name', 'source', 'aido_mr', 'email', 'phone', 'transaction_id', 'transaction_date', 'service', 'transaction_status']))
        : array_intersect_key($allowedColumns, array_flip(['name', 'source', 'aido_mr', 'email', 'phone', 'member_branch', 'registered_at']));
}

$whereClauses = [];
$whereTypes = '';
$whereParams = [];
if ($failClosed) {
    $whereClauses[] = '1=0';
} elseif ($selectedCabangId !== null) {
    $whereClauses[] = '(u.id_cabang = ? OR EXISTS (SELECT 1 FROM `order` o_scope WHERE o_scope.id_user = u.id_user AND o_scope.id_cabang = ?))';
    $whereTypes .= 'ii';
    $whereParams[] = $selectedCabangId;
    $whereParams[] = $selectedCabangId;
}
if ($source === 'aido') {
    $whereClauses[] = 'u.aido_mr IS NOT NULL';
} elseif ($source === 'website') {
    $whereClauses[] = 'u.aido_mr IS NULL';
}
if ($memberState === 'with_order') {
    $whereClauses[] = $selectedCabangId === null
        ? 'EXISTS (SELECT 1 FROM `order` o_member WHERE o_member.id_user = u.id_user)'
        : 'EXISTS (SELECT 1 FROM `order` o_member WHERE o_member.id_user = u.id_user AND o_member.id_cabang = ?)';
    if ($selectedCabangId !== null) {
        $whereTypes .= 'i';
        $whereParams[] = $selectedCabangId;
    }
} elseif ($memberState === 'without_order') {
    $whereClauses[] = $selectedCabangId === null
        ? 'NOT EXISTS (SELECT 1 FROM `order` o_member WHERE o_member.id_user = u.id_user)'
        : 'NOT EXISTS (SELECT 1 FROM `order` o_member WHERE o_member.id_user = u.id_user AND o_member.id_cabang = ?)';
    if ($selectedCabangId !== null) {
        $whereTypes .= 'i';
        $whereParams[] = $selectedCabangId;
    }
}
if ($searchTerm !== '') {
    $searchLike = '%' . $searchTerm . '%';
    $whereClauses[] = '(u.nama_lengkap LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.no_telepon LIKE ? OR u.aido_mr LIKE ?)';
    $whereTypes .= 'sssss';
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
}
if ($memberDateFrom !== null) {
    $whereClauses[] = 'u.tanggal_daftar >= ?';
    $whereTypes .= 's';
    $whereParams[] = $memberDateFrom . ' 00:00:00';
}
if ($memberDateTo !== null) {
    $whereClauses[] = 'u.tanggal_daftar < ?';
    $whereTypes .= 's';
    $whereParams[] = (new DateTimeImmutable($memberDateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
}

$select = 'SELECT u.nama_lengkap, u.aido_mr, u.email, u.no_telepon, u.alamat, u.tanggal_daftar, member_cabang.nama_cabang AS member_cabang';
$from = ' FROM user u LEFT JOIN cabang member_cabang ON member_cabang.id_cabang = u.id_cabang';
$joinTypes = '';
$joinParams = [];
if ($exportType === 'transactions' && !$failClosed) {
    $select .= ', o.id_order, o.aido_trx_id, o.tanggal_treatment, o.status_order, o.catatan_tambahan, layanan.nama_layanan, transaction_cabang.nama_cabang AS transaction_cabang';
    $from .= ' LEFT JOIN `order` o ON o.id_user = u.id_user';
    if ($selectedCabangId !== null) {
        $from .= ' AND o.id_cabang = ?';
        $joinTypes .= 'i';
        $joinParams[] = $selectedCabangId;
    }
    $from .= ' LEFT JOIN layanan ON layanan.id_layanan = o.id_layanan LEFT JOIN cabang transaction_cabang ON transaction_cabang.id_cabang = o.id_cabang';
    if ($orderStatus !== 'all') {
        $whereClauses[] = 'o.status_order = ?';
        $whereTypes .= 's';
        $whereParams[] = $orderStatus;
    }
    if ($transactionDateFrom !== null) {
        $whereClauses[] = 'o.tanggal_treatment >= ?';
        $whereTypes .= 's';
        $whereParams[] = $transactionDateFrom . ' 00:00:00';
    }
    if ($transactionDateTo !== null) {
        $whereClauses[] = 'o.tanggal_treatment < ?';
        $whereTypes .= 's';
        $whereParams[] = (new DateTimeImmutable($transactionDateTo))->modify('+1 day')->format('Y-m-d 00:00:00');
    }
}

$sql = $select . $from;
if ($whereClauses !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
}
$sql .= $exportType === 'transactions'
    ? ' ORDER BY u.nama_lengkap ASC, o.tanggal_treatment DESC, o.id_order DESC'
    : ' ORDER BY u.nama_lengkap ASC, u.id_user ASC';

try {
    $stmt = $conn->prepare($sql);
    $types = $joinTypes . $whereTypes;
    $params = array_merge($joinParams, $whereParams);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
} catch (mysqli_sql_exception $exception) {
    error_log('Member export failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Export gagal diproses. Silakan coba lagi.');
}

session_write_close();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="member-export-' . date('Ymd-His') . '.csv"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, array_values($selectedColumns));
while ($row = $result->fetch_assoc()) {
    $values = [];
    foreach (array_keys($selectedColumns) as $column) {
        $values[] = drw_export_csv_value(drw_export_value($column, $row));
    }
    fputcsv($output, $values);
}
fclose($output);
$stmt->close();
$conn->close();
exit();
