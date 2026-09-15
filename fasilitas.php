<?php
require_once 'config.php';

$pageTitle = 'Fasilitas & Alur Pelayanan Purworejo | ' . NAMA_KLINIK;
$pageDescription = 'Fasilitas dan alur pelayanan Klinik Pratama DRW Estetika Purworejo.';
$activePage = 'fasilitas';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <span class="drw-hero-label">FASILITAS PURWOREJO</span>
                    <h1>Ruang perawatan yang mendukung rasa <em>nyaman.</em></h1>
                    <p>Setiap area kami siapkan untuk mendukung pengalaman perawatan yang lebih tenang, rapi, dan menyenangkan.</p>
                </div>
                <div class="col-lg-5"><div class="drw-inner-hero-art"><img src="images/body_spa.jpg" alt="Ruang perawatan Klinik Pratama DRW Estetika Purworejo" onerror="this.src='images/default_layanan.jpg'"></div></div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="drw-section-intro">
                <div class="drw-section-kicker">AREA KLINIK</div>
                <h2>Fasilitas Klinik <em>Purworejo.</em></h2>
                <p>Setiap lantai memiliki fungsi yang dirancang untuk mendukung kebutuhan pasien dan aktivitas tim klinik.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4"><article class="drw-facility-card"><img src="images/konsultasi_dokter.jpg" alt="Area konsultasi Klinik Purworejo" onerror="this.src='images/default_layanan.jpg'"><div><h3>Lantai 1</h3><p>Area Fashion Muslim, penjualan produk, serta lobby dan ruang konsultasi.</p></div></article></div>
                <div class="col-md-4"><article class="drw-facility-card"><img src="images/facial_dasar.jpg" alt="Area perawatan facial" onerror="this.src='images/default_layanan.jpg'"><div><h3>Lantai 2</h3><p>Ruang facial, spa center, ruang injection, nail art, dan hair treatment.</p></div></article></div>
                <div class="col-md-4"><article class="drw-facility-card"><img src="images/body_spa.jpg" alt="Area pendukung klinik" onerror="this.src='images/default_layanan.jpg'"><div><h3>Lantai 3</h3><p>Meeting room, area laundry, dan ruang staff untuk mendukung operasional klinik.</p></div></article></div>
            </div>
        </div>
    </section>

    <section class="drw-page-section drw-section-soft">
        <div class="container">
            <div class="drw-section-intro">
                <div class="drw-section-kicker">ALUR PELAYANAN</div>
                <h2>Langkah perawatan yang <em>lebih terarah.</em></h2>
                <p>Kami ingin Anda memahami prosesnya sejak awal, sehingga setiap kunjungan terasa lebih nyaman.</p>
            </div>
            <div class="drw-process">
                <article class="drw-process-step"><h3>Konsultasi Awal</h3><p>Pasien berkonsultasi mengenai keluhan dan kebutuhan perawatan.</p></article>
                <article class="drw-process-step"><h3>Analisa Kondisi</h3><p>Tim membantu memahami kondisi kulit dan pilihan layanan yang relevan.</p></article>
                <article class="drw-process-step"><h3>Rekomendasi</h3><p>Perawatan disesuaikan dengan kebutuhan dan tujuan pasien.</p></article>
                <article class="drw-process-step"><h3>Perawatan</h3><p>Proses dilakukan dengan pendampingan tenaga profesional.</p></article>
            </div>
        </div>
    </section>

    <section class="drw-cta-section">
        <div class="container"><div class="drw-cta-card"><div><span class="drw-section-kicker">SIAP MEMULAI</span><h2>Awali dengan konsultasi yang <em>nyaman.</em></h2><p>Kunjungi Klinik Purworejo atau buat janji konsultasi melalui website.</p></div><a class="btn drw-btn-light" href="order.php?cabang=purworejo"><i class="fa-regular fa-calendar-check"></i> Buat Janji Konsultasi</a></div></div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
