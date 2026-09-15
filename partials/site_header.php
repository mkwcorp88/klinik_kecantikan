<?php
$pageTitle = $pageTitle ?? NAMA_KLINIK;
$pageDescription = $pageDescription ?? 'Klinik Pratama DRW Estetika untuk kebutuhan perawatan kulit dan konsultasi Anda.';
$activePage = $activePage ?? '';
$bodyClass = $bodyClass ?? 'home-page site-page';
$navItems = [
    ['key' => 'home', 'href' => 'index.php', 'label' => 'Beranda'],
    ['key' => 'tentang', 'href' => 'tentang.php', 'label' => 'Tentang'],
    ['key' => 'layanan', 'href' => 'layanan.php', 'label' => 'Layanan'],
    ['key' => 'fasilitas', 'href' => 'fasilitas.php', 'label' => 'Fasilitas'],
    ['key' => 'cabang', 'href' => 'cabang.php', 'label' => 'Cabang'],
    ['key' => 'kontak', 'href' => 'kontak.php', 'label' => 'Kontak'],
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=3">
    <link rel="stylesheet" href="css/home.css?v=4">
    <link rel="stylesheet" href="css/site.css?v=8">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
    <nav class="navbar navbar-expand-xl navbar-light drw-navbar sticky-top">
        <div class="container">
            <a class="navbar-brand drw-brand" href="index.php" aria-label="<?= htmlspecialchars(NAMA_KLINIK, ENT_QUOTES, 'UTF-8') ?>">
                <img class="drw-logo-image" src="images/klinik-pratama-drw-estetika-logo.png" alt="Klinik Pratama DRW Estetika" width="1500" height="415">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#drwMainNav" aria-controls="drwMainNav" aria-expanded="false" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="drwMainNav">
                <ul class="navbar-nav mx-xl-auto drw-nav-links">
                    <?php foreach ($navItems as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activePage === $item['key'] ? ' active' : '' ?>" href="<?= $item['href'] ?>"<?= $activePage === $item['key'] ? ' aria-current="page"' : '' ?>><?= $item['label'] ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="drw-nav-actions">
                    <?php if (drw_is_logged_in()): ?>
                        <a class="drw-login" href="profil.php"><i class="fa-regular fa-user"></i><span>Akun Saya</span></a>
                    <?php else: ?>
                        <a class="drw-login<?= $activePage === 'login' ? ' active' : '' ?>" href="login.php"><i class="fa-regular fa-user"></i><span>Masuk</span></a>
                    <?php endif; ?>
                    <a class="btn drw-btn-primary drw-btn-nav" href="order.php"><i class="fa-regular fa-calendar-check"></i> Buat Janji</a>
                </div>
            </div>
        </div>
    </nav>
