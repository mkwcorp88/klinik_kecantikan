<?php
require_once 'config.php';

$pageTitle = 'Layanan Purworejo | ' . NAMA_KLINIK;
$pageDescription = 'Daftar kategori layanan perawatan di Klinik Pratama DRW Estetika Purworejo.';
$activePage = 'layanan';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <span class="drw-hero-label">LAYANAN PURWOREJO</span>
                    <h1>Perawatan yang disesuaikan dengan <em>kebutuhan Anda.</em></h1>
                    <p>Mulai dari perawatan dasar hingga tindakan dokter, tim kami membantu Anda memilih langkah yang lebih sesuai melalui konsultasi terlebih dahulu.</p>
                </div>
                <div class="col-lg-5"><div class="drw-inner-hero-art"><img src="images/facial_brightening.jpg" alt="Perawatan facial di Klinik Pratama DRW Estetika" onerror="this.src='images/default_layanan.jpg'"></div></div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="drw-section-intro">
                <div class="drw-section-kicker">PILIHAN PERAWATAN</div>
                <h2>Daftar layanan di <em>Purworejo.</em></h2>
                <p>Setiap kulit memiliki kondisi dan tujuan yang berbeda. Konsultasikan kebutuhan Anda dengan tim kami untuk mengetahui pilihan perawatan yang tersedia.</p>
            </div>
            <div class="drw-service-notice"><i class="fa-solid fa-circle-info"></i><p>Daftar ini merupakan informasi layanan Klinik Pratama DRW Estetika Purworejo. Ketersediaan tindakan dan jadwal konsultasi dapat dikonfirmasi langsung kepada tim klinik.</p></div>
            <div class="drw-service-directory">
                <article class="drw-service-group" id="facial"><h3>Facial</h3><ul><li>Facial Brightening</li><li>Facial Acne</li><li>Facial White</li><li>Facial Acne V-Shape</li><li>Facial Purification</li><li>Facial Korean Massage</li></ul></article>
                <article class="drw-service-group"><h3>Radio Frequency</h3><ul><li>Facial RF</li><li>Neck RF</li><li>Eye RF</li><li>Tummy RF</li><li>Arm RF</li></ul></article>
                <article class="drw-service-group" id="dpl"><h3>DPL</h3><ul><li>DPL Rejuvenation</li><li>DPL Acne</li><li>DPL Hair Removal</li><li>DPL Vagina</li></ul></article>
                <article class="drw-service-group"><h3>Mesotherapy</h3><ul><li>Meso Brightening</li><li>Meso Acne</li><li>Meso Whitening</li><li>Meso Vagina</li></ul></article>
                <article class="drw-service-group" id="skin-booster"><h3>Skin Booster</h3><ul><li>Salmon DNA</li><li>Meso Botox</li><li>Scarlet Reju</li><li>Glowing Peel</li></ul></article>
                <article class="drw-service-group" id="tindakan-dokter"><h3>Tindakan Dokter</h3><ul><li>Mole Removal</li><li>Scar Removal</li><li>Infus Beauty, White, Healthy, dan Burn Fat</li><li>Injection Slimming, Whitening, Immun, Beauty, Antiaging, Glowing, dan lainnya</li></ul></article>
                <article class="drw-service-group"><h3>Perawatan Rambut</h3><ul><li>Hair Growth</li><li>PRP Hair</li></ul></article>
                <article class="drw-service-group"><h3>Perawatan Tubuh</h3><ul><li>Cavitation</li><li>Slimming</li><li>Fat Injection</li></ul></article>
                <article class="drw-service-group"><h3>Layanan Lainnya</h3><ul><li>Thread Lift</li><li>HIFU</li><li>Botox</li><li>Filler</li><li>PRP</li><li>Chemical Peeling</li><li>Gips Mask</li><li>Vagina Ozone</li></ul></article>
            </div>
        </div>
    </section>

    <section class="drw-page-section drw-section-sage">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7"><div class="drw-section-intro mb-0"><div class="drw-section-kicker">LANGKAH AWAL</div><h2>Belum yakin memilih <em>perawatan?</em></h2><p>Jangan khawatir. Konsultasi membantu tim kami memahami kondisi kulit, tujuan, dan pilihan perawatan yang paling relevan untuk Anda.</p></div></div>
                <div class="col-lg-4 offset-lg-1"><a class="btn drw-btn-primary w-100" href="order.php?cabang=purworejo"><i class="fa-regular fa-calendar-check"></i> Buat Janji Konsultasi</a><a class="drw-text-link mt-3" href="kontak.php">Tanyakan pilihan perawatan <i class="fa-solid fa-arrow-right"></i></a></div>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
