<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/admin_auth.php';

drw_require_admin();
if (!drw_admin_is_super()) {
    http_response_code(403);
    exit('Halaman ini khusus super admin.');
}

header('Cache-Control: private, no-store, max-age=0');

function drw_aido_date(?string $value, DateTimeZone $timezone): ?DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    return $date !== false
        && $date->format('Y-m-d') === $value
        && (int) $date->format('Y') >= 1000
        && (int) $date->format('Y') <= 9998
        ? $date
        : null;
}

function drw_aido_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $statement = $conn->prepare($sql);
    if ($types !== '') {
        $statement->bind_param($types, ...$params);
    }
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $statement->close();

    return $rows;
}

function drw_aido_branch_key(mixed $branchId): string
{
    return $branchId === null ? 'unmapped' : 'branch:' . (int) $branchId;
}

function drw_aido_format_number(int $value): string
{
    return number_format($value, 0, ',', '.');
}

function drw_aido_month_name(int $month): string
{
    $names = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return $names[$month] ?? '';
}

function drw_aido_format_date(DateTimeImmutable $date): string
{
    return $date->format('j') . ' ' . drw_aido_month_name((int) $date->format('n')) . ' ' . $date->format('Y');
}

function drw_aido_empty_branch(string $name): array
{
    return [
        'name' => $name,
        'members' => 0,
        'all_time_transactions' => 0,
        'transactions' => 0,
        'unique_patients' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'completed' => 0,
        'cancelled' => 0,
    ];
}

$timezone = new DateTimeZone('Asia/Jakarta');
$today = new DateTimeImmutable('today', $timezone);
$defaultFrom = $today->modify('first day of this month')->modify('-11 months');
$from = $defaultFrom;
$to = $today;
$filterError = null;

if (isset($_GET['from']) || isset($_GET['to'])) {
    $requestedFrom = drw_aido_date(is_string($_GET['from'] ?? null) ? $_GET['from'] : null, $timezone);
    $requestedTo = drw_aido_date(is_string($_GET['to'] ?? null) ? $_GET['to'] : null, $timezone);
    if ($requestedFrom === null || $requestedTo === null || $requestedFrom > $requestedTo) {
        $filterError = 'Rentang tanggal tidak valid. Data ditampilkan untuk 12 bulan terakhir.';
    } elseif ($requestedTo > $requestedFrom->modify('+120 months')->modify('-1 day')) {
        $filterError = 'Rentang tanggal maksimal 10 tahun. Data ditampilkan untuk 12 bulan terakhir.';
    } else {
        $from = $requestedFrom;
        $to = $requestedTo;
    }
}

$fromSql = $from->format('Y-m-d 00:00:00');
$toExclusiveSql = $to->modify('+1 day')->format('Y-m-d 00:00:00');
$periodParams = [$fromSql, $toExclusiveSql];

$branches = [];
foreach (drw_aido_rows($conn, 'SELECT id_cabang, nama_cabang FROM cabang ORDER BY nama_cabang ASC') as $row) {
    $branches[drw_aido_branch_key($row['id_cabang'])] = drw_aido_empty_branch((string) $row['nama_cabang']);
}

$ensureBranch = static function (array &$allBranches, string $key, ?int $id): void {
    if (!isset($allBranches[$key])) {
        $name = $id === null ? 'Cabang belum dipetakan' : 'Cabang #' . $id;
        $allBranches[$key] = drw_aido_empty_branch($name);
    }
};

$totalMembers = 0;
foreach (drw_aido_rows($conn, 'SELECT id_cabang, COUNT(*) AS total FROM user WHERE aido_mr IS NOT NULL GROUP BY id_cabang') as $row) {
    $id = $row['id_cabang'] === null ? null : (int) $row['id_cabang'];
    $key = drw_aido_branch_key($id);
    $ensureBranch($branches, $key, $id);
    $branches[$key]['members'] = (int) $row['total'];
    $totalMembers += (int) $row['total'];
}

$latestImportedEvent = null;
foreach (drw_aido_rows($conn, 'SELECT id_cabang, COUNT(*) AS total, MAX(tanggal_treatment) AS latest FROM `order` WHERE aido_trx_id IS NOT NULL GROUP BY id_cabang') as $row) {
    $id = $row['id_cabang'] === null ? null : (int) $row['id_cabang'];
    $key = drw_aido_branch_key($id);
    $ensureBranch($branches, $key, $id);
    $branches[$key]['all_time_transactions'] = (int) $row['total'];
    if ($row['latest'] !== null && ($latestImportedEvent === null || $row['latest'] > $latestImportedEvent)) {
        $latestImportedEvent = (string) $row['latest'];
    }
}

$statusLabels = [
    'pending' => 'Belum tercatat lunas',
    'confirmed' => 'Dikonfirmasi',
    'completed' => 'Lunas (AIDO)',
    'cancelled' => 'Batal',
];
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
$totalTransactions = 0;

$branchPeriodRows = drw_aido_rows(
    $conn,
    "SELECT id_cabang, COUNT(*) AS transactions, COUNT(DISTINCT id_user) AS unique_patients,
            SUM(status_order = 'pending') AS pending,
            SUM(status_order = 'confirmed') AS confirmed,
            SUM(status_order = 'completed') AS completed,
            SUM(status_order = 'cancelled') AS cancelled
     FROM `order`
     WHERE aido_trx_id IS NOT NULL AND tanggal_treatment >= ? AND tanggal_treatment < ?
     GROUP BY id_cabang",
    'ss',
    $periodParams
);
foreach ($branchPeriodRows as $row) {
    $id = $row['id_cabang'] === null ? null : (int) $row['id_cabang'];
    $key = drw_aido_branch_key($id);
    $ensureBranch($branches, $key, $id);
    $branches[$key]['transactions'] = (int) $row['transactions'];
    $branches[$key]['unique_patients'] = (int) $row['unique_patients'];
    $totalTransactions += (int) $row['transactions'];
    foreach ($statusLabels as $status => $_label) {
        $branches[$key][$status] = (int) $row[$status];
        $statusCounts[$status] += (int) $row[$status];
    }
}

$uniquePatientsRows = drw_aido_rows(
    $conn,
    'SELECT COUNT(DISTINCT id_user) AS total FROM `order` WHERE aido_trx_id IS NOT NULL AND tanggal_treatment >= ? AND tanggal_treatment < ?',
    'ss',
    $periodParams
);
$uniquePatients = (int) ($uniquePatientsRows[0]['total'] ?? 0);

$monthlyRows = drw_aido_rows(
    $conn,
    "SELECT DATE_FORMAT(tanggal_treatment, '%Y-%m') AS month_key,
            COUNT(*) AS transactions,
            COUNT(DISTINCT id_user) AS unique_patients,
            SUM(status_order = 'completed') AS completed
     FROM `order`
     WHERE aido_trx_id IS NOT NULL AND tanggal_treatment >= ? AND tanggal_treatment < ?
     GROUP BY month_key
     ORDER BY month_key",
    'ss',
    $periodParams
);
$monthTotals = [];
foreach ($monthlyRows as $row) {
    $monthTotals[(string) $row['month_key']] = [
        'transactions' => (int) $row['transactions'],
        'unique_patients' => (int) $row['unique_patients'],
        'completed' => (int) $row['completed'],
    ];
}

$months = [];
$maxMonthTransactions = 0;
$lastMonth = $to->modify('first day of this month');
for ($cursor = $from->modify('first day of this month'); $cursor <= $lastMonth; $cursor = $cursor->modify('+1 month')) {
    $key = $cursor->format('Y-m');
    $monthly = $monthTotals[$key] ?? ['transactions' => 0, 'unique_patients' => 0, 'completed' => 0];
    $months[] = ['label' => drw_aido_month_name((int) $cursor->format('n')) . ' ' . $cursor->format('Y')] + $monthly;
    $maxMonthTransactions = max($maxMonthTransactions, $monthly['transactions']);
}

$periodLabel = drw_aido_format_date($from) . ' – ' . drw_aido_format_date($to);
$branchCountWithTransactions = count(array_filter(
    $branches,
    static fn (array $branch, string $key): bool => $key !== 'unmapped' && $branch['all_time_transactions'] > 0,
    ARRAY_FILTER_USE_BOTH
));
$transactionBranchNames = [];
foreach ($branches as $key => $branch) {
    if ($key !== 'unmapped' && $branch['all_time_transactions'] > 0) {
        $transactionBranchNames[] = $branch['name'];
    }
}
$unmappedTransactions = $branches['unmapped']['all_time_transactions'] ?? 0;
$coverageSummary = match (count($transactionBranchNames)) {
    0 => $unmappedTransactions > 0
        ? 'Transaksi AIDO yang tersimpan belum memiliki cabang yang terpetakan.'
        : 'Belum ada transaksi AIDO yang tersimpan.',
    1 => 'Transaksi AIDO yang tersimpan baru mencakup ' . $transactionBranchNames[0] . '.',
    default => 'Transaksi AIDO yang tersimpan mencakup ' . count($transactionBranchNames) . ' cabang.',
};
if ($unmappedTransactions > 0 && $transactionBranchNames !== []) {
    $coverageSummary .= ' Sebagian transaksi belum memiliki cabang yang terpetakan.';
}
$latestEventDate = $latestImportedEvent === null ? null : drw_aido_date(substr($latestImportedEvent, 0, 10), $timezone);
$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Analitik AIDO - <?php echo htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/admin-theme.css" rel="stylesheet">
    <link href="../css/admin-aido-dashboard.css" rel="stylesheet">
</head>
<body class="aido-page">
    <header class="aido-topbar">
        <div class="aido-shell aido-topbar-inner">
            <a class="aido-brand" href="index.php" aria-label="Kembali ke dashboard admin">
                <img src="../images/logo.png" alt="" width="42" height="42">
                <span>DRW <small>Owner Analytics</small></span>
            </a>
            <nav class="aido-nav" aria-label="Navigasi admin">
                <a href="index.php">Dashboard</a>
                <a href="kelola_user.php">Member</a>
                <a href="kelola_order.php">Order</a>
                <a class="is-active" href="aido_dashboard.php" aria-current="page">Analitik AIDO</a>
                <a href="logout_admin.php">Keluar</a>
            </nav>
        </div>
    </header>

    <main class="aido-shell aido-main">
        <section class="aido-hero" aria-labelledby="aido-page-title">
            <div>
                <span class="aido-eyebrow">Akses super admin · Semua cabang</span>
                <h1 id="aido-page-title">Analitik AIDO</h1>
                <p>Ringkasan member dan transaksi yang sudah masuk ke website klinik.</p>
            </div>
            <div class="aido-period">
                <span>Periode transaksi</span>
                <strong><?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                <small>Waktu Indonesia Barat (WIB)</small>
            </div>
        </section>

        <form class="aido-filter" method="get" action="aido_dashboard.php" aria-label="Filter periode transaksi">
            <div>
                <label for="from">Dari tanggal</label>
                <input class="form-control" type="date" id="from" name="from" value="<?php echo htmlspecialchars($from->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div>
                <label for="to">Sampai tanggal</label>
                <input class="form-control" type="date" id="to" name="to" value="<?php echo htmlspecialchars($to->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <button class="btn btn-primary" type="submit">Terapkan</button>
        </form>
        <?php if ($filterError !== null): ?>
            <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($filterError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <aside class="aido-data-notice" aria-label="Keterangan sumber data">
            <strong>Data historis hasil impor AIDO · bukan data langsung</strong>
            <p><?php echo htmlspecialchars($coverageSummary, ENT_QUOTES, 'UTF-8'); ?> Tanggal transaksi terbaru yang tersimpan: <?php echo $latestEventDate === null ? 'belum ada' : htmlspecialchars(drw_aido_format_date($latestEventDate), ENT_QUOTES, 'UTF-8'); ?>. Database belum menyimpan waktu sinkronisasi; filter memakai tanggal peristiwa AIDO, bukan tanggal pembaruan data.</p>
        </aside>

        <section class="aido-kpis" aria-label="Ringkasan AIDO">
            <article class="aido-kpi">
                <span>Member AIDO</span>
                <strong><?php echo drw_aido_format_number($totalMembers); ?></strong>
                <small>Seluruh waktu · berdasarkan nomor MR</small>
            </article>
            <article class="aido-kpi">
                <span>Transaksi AIDO</span>
                <strong><?php echo drw_aido_format_number($totalTransactions); ?></strong>
                <small>Dalam periode yang dipilih</small>
            </article>
            <article class="aido-kpi">
                <span>Pasien unik</span>
                <strong><?php echo drw_aido_format_number($uniquePatients); ?></strong>
                <small>Dalam periode · dihitung lintas cabang</small>
            </article>
            <article class="aido-kpi">
                <span>Transaksi lunas</span>
                <strong><?php echo drw_aido_format_number($statusCounts['completed']); ?></strong>
                <small>Dalam periode yang dipilih</small>
            </article>
        </section>

        <section class="aido-grid" aria-label="Status dan cakupan data">
            <article class="aido-panel">
                <div class="aido-panel-heading">
                    <div>
                        <span class="aido-section-label">Periode terpilih</span>
                        <h2>Status transaksi</h2>
                    </div>
                    <strong><?php echo drw_aido_format_number($totalTransactions); ?> total</strong>
                </div>
                <?php foreach ($statusLabels as $status => $label): ?>
                    <?php $percentage = $totalTransactions > 0 ? round($statusCounts[$status] / $totalTransactions * 100) : 0; ?>
                    <div class="aido-status-row">
                        <div><span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo drw_aido_format_number($statusCounts[$status]); ?></strong></div>
                        <div class="aido-track"><span class="aido-track-fill is-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo (int) $percentage; ?>%"></span></div>
                    </div>
                <?php endforeach; ?>
                <p class="aido-panel-note">Saat impor, status PAID dari AIDO dicatat sebagai lunas; status lainnya dicatat sebagai belum lunas.</p>
            </article>

            <article class="aido-panel aido-coverage">
                <span class="aido-section-label">Cakupan impor</span>
                <h2>Data transaksi per cabang</h2>
                <div class="aido-coverage-count"><strong><?php echo drw_aido_format_number($branchCountWithTransactions); ?></strong><span>cabang memiliki transaksi AIDO dalam database</span></div>
                <p>Jumlah member dapat tersedia meski transaksi cabang tersebut belum masuk. Angka nol menunjukkan belum ada data transaksi AIDO pada database, bukan aktivitas klinik nol.</p>
                <div class="aido-coverage-note">Omzet tidak ditampilkan karena jumlah pembayaran AIDO belum tersimpan lengkap di kolom terstruktur.</div>
            </article>
        </section>

        <section class="aido-panel aido-trend" aria-labelledby="aido-trend-title">
            <div class="aido-panel-heading">
                <div>
                    <span class="aido-section-label">Periode terpilih</span>
                    <h2 id="aido-trend-title">Tren bulanan</h2>
                </div>
                <span class="aido-legend"><i></i> Semua transaksi <i></i> Lunas</span>
            </div>
            <div class="aido-trend-scroll">
                <div class="aido-trend-list">
                    <?php foreach ($months as $month): ?>
                        <?php $totalWidth = $maxMonthTransactions > 0 ? round($month['transactions'] / $maxMonthTransactions * 100) : 0; ?>
                        <?php $completedWidth = $maxMonthTransactions > 0 ? round($month['completed'] / $maxMonthTransactions * 100) : 0; ?>
                        <div class="aido-trend-row">
                            <span class="aido-trend-month"><?php echo htmlspecialchars($month['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="aido-trend-bars" role="img" aria-label="<?php echo htmlspecialchars($month['label'] . ': ' . $month['transactions'] . ' transaksi, ' . $month['completed'] . ' lunas, ' . $month['unique_patients'] . ' pasien unik', ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="aido-trend-bar" style="width: <?php echo (int) $totalWidth; ?>%"></span>
                                <span class="aido-trend-bar is-completed" style="width: <?php echo (int) $completedWidth; ?>%"></span>
                            </div>
                            <strong><?php echo drw_aido_format_number($month['transactions']); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="aido-panel-note">Bulan tanpa transaksi tetap ditampilkan. Tanggal transaksi mengikuti <code>tanggal_treatment</code> yang tersimpan dalam WIB.</p>
        </section>

        <section class="aido-panel" aria-labelledby="aido-branches-title">
            <div class="aido-panel-heading">
                <div>
                    <span class="aido-section-label">Semua cabang</span>
                    <h2 id="aido-branches-title">Cakupan cabang</h2>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table aido-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Cabang</th>
                            <th scope="col" class="text-end">Member AIDO<br><small>Seluruh waktu</small></th>
                            <th scope="col" class="text-end">Transaksi AIDO<br><small>Seluruh waktu</small></th>
                            <th scope="col" class="text-end">Transaksi<br><small>Periode terpilih</small></th>
                            <th scope="col" class="text-end">Lunas<br><small>Periode terpilih</small></th>
                            <th scope="col" class="text-end">Pasien unik<br><small>Periode terpilih</small></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                            <tr>
                                <th scope="row">
                                    <?php echo htmlspecialchars($branch['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <small class="aido-table-coverage"><?php echo $branch['all_time_transactions'] > 0 ? 'Ada data transaksi AIDO' : 'Belum ada data transaksi AIDO'; ?></small>
                                </th>
                                <td class="text-end"><?php echo drw_aido_format_number($branch['members']); ?></td>
                                <td class="text-end"><?php echo drw_aido_format_number($branch['all_time_transactions']); ?></td>
                                <td class="text-end"><?php echo drw_aido_format_number($branch['transactions']); ?></td>
                                <td class="text-end"><?php echo drw_aido_format_number($branch['completed']); ?></td>
                                <td class="text-end"><?php echo drw_aido_format_number($branch['unique_patients']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="aido-panel-note">Pasien yang datang ke lebih dari satu cabang dihitung sekali pada ringkasan lintas cabang, tetapi muncul di tiap cabang yang dikunjungi. Pencocokan pasien impor masih dapat memengaruhi jumlah pasien unik.</p>
        </section>
    </main>
</body>
</html>
