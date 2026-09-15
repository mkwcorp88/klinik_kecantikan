<?php
require_once 'config.php'; // Session sudah dimulai di config.php

drw_require_member('Silakan masuk untuk melihat riwayat booking.', 'riwayat_order.php');

$id_user = $_SESSION['user_id'];
$orders = [];
$flash = drw_consume_flash();

// Ambil data booking pengguna dari database.
$stmt = $conn->prepare("SELECT o.id_order, l.nama_layanan, c.nama_cabang, o.tanggal_treatment, o.catatan_tambahan, o.status_order, o.tanggal_order_dibuat
                        FROM `order` o
                        JOIN layanan l ON o.id_layanan = l.id_layanan
                        LEFT JOIN cabang c ON o.id_cabang = c.id_cabang
                        WHERE o.id_user = ?
                        ORDER BY o.tanggal_order_dibuat DESC");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();

// Ambil pesan dari session jika ada (misalnya setelah berhasil order)
if (isset($_SESSION['order_success'])) {
    $order_success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
}
if (isset($_SESSION['error_message_redirect'])) { // Pesan error dari redirect login
    $error_message_redirect = $_SESSION['error_message_redirect'];
    unset($_SESSION['error_message_redirect']);
}

$conn->close();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Pesanan Saya - <?php echo NAMA_KLINIK; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700&family=Raleway:wght@400;700&display=swap" rel="stylesheet">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Anda bisa memindahkan gaya ini ke style.css jika belum */
        .table-responsive {
            margin-top: 1rem;
        }
        .status-badge {
            font-size: 0.9em;
            padding: 0.4em 0.7em;
        }
        .card-order-item {
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }
        .card-order-item .card-header {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100"> <?php /* STICKY FOOTER */ ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Logo Klinik" height="40">
                <?php echo NAMA_KLINIK; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'index.php') echo 'active'; ?>" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'layanan_tampil.php') echo 'active'; ?>" href="layanan_tampil.php">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_tampil.php') echo 'active'; ?>" href="testimoni_tampil.php">Testimoni Publik</a></li>
                    <li class="nav-item"><a class="nav-link <?php if(basename($_SERVER['PHP_SELF']) == 'order.php') echo 'active'; ?>" href="order.php">Order Sekarang</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php
                                $user_pages = ['riwayat_order.php', 'testimoni_buat.php', 'profil.php'];
                                if (in_array(basename($_SERVER['PHP_SELF']), $user_pages)) echo 'active';
                            ?>" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-current="page">
                                <i class="fas fa-user-circle"></i> Halo, <?php echo htmlspecialchars($_SESSION['display_name'] ?? $_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUser">
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'riwayat_order.php') echo 'active'; ?>" href="riwayat_order.php"><i class="fas fa-history"></i> Riwayat Pesanan</a></li>
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'testimoni_buat.php') echo 'active'; ?>" href="testimoni_buat.php"><i class="fas fa-comment-medical"></i> Buat Review / Testimoni Saya</a></li>
                                <li><a class="dropdown-item <?php if(basename($_SERVER['PHP_SELF']) == 'profil.php') echo 'active'; ?>" href="profil.php"><i class="fas fa-user-edit"></i> Profil Saya</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php elseif (isset($_SESSION['admin_logged_in'])): ?>
                         <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-shield"></i> Admin: <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownAdmin">
                                <li><a class="dropdown-item" href="admin/index.php"><i class="fas fa-tachometer-alt"></i> Dashboard Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: // Pengguna belum login, seharusnya sudah diredirect jika mengakses halaman ini ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-5 mb-5 flex-grow-1"> <?php /* STICKY FOOTER */ ?>
        <h1 class="text-center mb-4 section-title">Riwayat Booking Saya</h1>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message_redirect)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message_redirect); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($order_success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($order_success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>


        <?php if (!empty($orders)): ?>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID Order</th>
                            <th scope="col">Layanan</th>
                            <th scope="col">Cabang</th>
                            <th scope="col">Jadwal Kunjungan</th>
                            <th scope="col">Diajukan</th>
                            <th scope="col">Catatan</th>
                            <th scope="col" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($order['id_order']); ?></td>
                            <td><?php echo htmlspecialchars($order['nama_layanan']); ?></td>
                            <td><?php echo htmlspecialchars($order['nama_cabang'] ?? 'Belum dicatat'); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_treatment'])); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($order['tanggal_order_dibuat'])); ?></td>
                            <td><?php echo !empty($order['catatan_tambahan']) ? nl2br(htmlspecialchars($order['catatan_tambahan'])) : '-'; ?></td>
                            <td class="text-center">
                                <span class="badge status-badge bg-<?php
                                    switch ($order['status_order']) {
                                        case 'pending': echo 'warning text-dark'; break;
                                        case 'confirmed': echo 'info text-dark'; break;
                                        case 'completed': echo 'success'; break;
                                        case 'cancelled': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst(htmlspecialchars($order['status_order'])); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-block d-md-none">
                <?php foreach ($orders as $order): ?>
                <div class="card card-order-item shadow-sm">
                    <div class="card-header py-2">
                        <strong>ID Order: #<?php echo htmlspecialchars($order['id_order']); ?></strong>
                        <span class="float-end badge status-badge bg-<?php
                            switch ($order['status_order']) {
                                case 'pending': echo 'warning text-dark'; break;
                                case 'confirmed': echo 'info text-dark'; break;
                                case 'completed': echo 'success'; break;
                                case 'cancelled': echo 'danger'; break;
                                default: echo 'secondary';
                            }
                        ?>">
                            <?php echo ucfirst(htmlspecialchars($order['status_order'])); ?>
                        </span>
                    </div>
                    <div class="card-body small">
                        <p class="card-text mb-1"><strong>Layanan:</strong> <?php echo htmlspecialchars($order['nama_layanan']); ?></p>
                        <p class="card-text mb-1"><strong>Cabang:</strong> <?php echo htmlspecialchars($order['nama_cabang'] ?? 'Belum dicatat'); ?></p>
                        <p class="card-text mb-1"><strong>Jadwal:</strong> <?php echo date('d M Y, H:i', strtotime($order['tanggal_treatment'])); ?></p>
                        <p class="card-text mb-1"><strong>Dipesan:</strong> <?php echo date('d M Y, H:i', strtotime($order['tanggal_order_dibuat'])); ?></p>
                        <?php if (!empty($order['catatan_tambahan'])): ?>
                        <p class="card-text mb-0"><strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($order['catatan_tambahan'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center mt-4" role="alert">
                Anda belum memiliki riwayat booking. <a href="order.php" class="alert-link">Ajukan booking konsultasi.</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> <?php echo NAMA_KLINIK; ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
<?php
if(isset($result) && $result) $result->close();
// $conn->close(); // Koneksi sudah ditutup di awal setelah query jika tidak ada lagi query setelahnya
?>
