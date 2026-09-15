<?php
require_once 'config.php';

$pageTitle = 'Cabang Klinik | ' . NAMA_KLINIK;
$pageDescription = 'Pilih cabang Klinik Pratama DRW Estetika yang paling nyaman untuk Anda kunjungi.';
$activePage = 'cabang';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <span class="drw-hero-label">CABANG KLINIK</span>
                    <h1>Lebih dekat dengan <em>Anda.</em></h1>
                    <p>Pilih cabang Klinik Pratama DRW Estetika yang paling nyaman untuk konsultasi dan perawatan Anda.</p>
                </div>
                <div class="col-lg-5"><div class="drw-inner-hero-art"><img src="images/konsultasi_dokter.jpg" alt="Pelayanan Klinik Pratama DRW Estetika" onerror="this.src='images/default_layanan.jpg'"></div></div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="drw-section-intro">
                <div class="drw-section-kicker">PILIH LOKASI</div>
                <h2>Temukan cabang yang <em>terdekat.</em></h2>
                <p>Anda dapat memilih cabang tujuan saat membuat janji. Informasi kontak dan jam operasional lengkap yang tersedia saat ini ditampilkan untuk cabang Purworejo.</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-5" id="purworejo">
                    <article class="drw-branch-card is-featured">
                        <span class="drw-branch-status">CABANG PURWOREJO</span>
                        <h2>Purworejo</h2>
                        <p>Klinik dengan fasilitas konsultasi dan area perawatan yang siap mendampingi kebutuhan Anda.</p>
                        <div class="drw-branch-meta">
                            <span><i class="fa-solid fa-location-dot"></i>Jl. Jend. Sudirman No.1, Purworejo</span>
                            <span><i class="fa-regular fa-clock"></i>Setiap hari, 08.00-18.00 WIB</span>
                            <span><i class="fa-brands fa-whatsapp"></i>0821-1085-9908</span>
                        </div>
                        <div class="drw-branch-actions"><a class="drw-button-outline" href="kontak.php">Lihat Kontak</a><a class="drw-button-outline" href="order.php?cabang=purworejo">Buat Janji</a></div>
                    </article>
                </div>
                <div class="col-lg-7"><div class="row g-4 h-100">
                    <div class="col-md-6"><article class="drw-branch-card"><span class="drw-branch-status">CABANG KLINIK</span><h3>Kutoarjo</h3><p>Pilih Kutoarjo sebagai tujuan Anda saat membuat janji konsultasi.</p><div class="drw-branch-meta"><span><i class="fa-solid fa-calendar-check"></i>Reservasi tersedia melalui website</span></div><div class="drw-branch-actions"><a class="drw-button-outline" href="order.php?cabang=kutoarjo">Pilih Cabang</a></div></article></div>
                    <div class="col-md-6"><article class="drw-branch-card"><span class="drw-branch-status">CABANG KLINIK</span><h3>Magelang</h3><p>Pilih Magelang sebagai tujuan Anda saat membuat janji konsultasi.</p><div class="drw-branch-meta"><span><i class="fa-solid fa-calendar-check"></i>Reservasi tersedia melalui website</span></div><div class="drw-branch-actions"><a class="drw-button-outline" href="order.php?cabang=magelang">Pilih Cabang</a></div></article></div>
                    <div class="col-12"><article class="drw-branch-card"><span class="drw-branch-status">BUTUH BANTUAN MEMILIH?</span><h3>Mulai dari konsultasi.</h3><p>Jika Anda belum yakin dengan cabang atau perawatan yang dibutuhkan, tim kami dapat membantu mengarahkan langkah awal Anda.</p><div class="drw-branch-actions"><a class="drw-button-outline" href="kontak.php">Hubungi Kami</a><a class="drw-button-outline" href="layanan.php">Lihat Layanan Purworejo</a></div></article></div>
                </div></div>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
