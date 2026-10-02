<?php
require_once 'config.php';

$pageTitle = NAMA_KLINIK . ' | Perawatan Kulit & Estetika';
$pageDescription = 'Klinik Pratama DRW Estetika hadir untuk konsultasi dan perawatan kulit yang lebih terarah.';
$activePage = 'home';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-hero">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6">
                    <div class="drw-eyebrow"><span></span> KLINIK PRATAMA DRW ESTETIKA</div>
                    <h1>Merawat Kulit, <em>Merawat Kepercayaan Diri.</em></h1>
                    <p class="drw-hero-copy">Setiap kulit punya cerita. Kami hadir untuk mendengarkan, memahami, dan merawatnya dengan pendekatan yang sesuai kebutuhan Anda.</p>
                    <div class="drw-hero-actions">
                        <a class="btn drw-btn-primary" href="order.php"><i class="fa-regular fa-calendar-check"></i> Buat Janji Konsultasi</a>
                        <a class="drw-text-link" href="tentang.php">Kenali klinik kami <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="drw-trust-row">
                        <span class="drw-trust-icon" aria-hidden="true"><i class="fa-solid fa-heart-pulse"></i></span>
                        <div><strong>Sejak 2016</strong><span>Merawat berbagai kebutuhan kulit</span></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="drw-hero-visual">
                        <div class="drw-arch-shape"></div>
                        <img src="images/clinic/beranda-treatment-portrait.webp" alt="Tenaga klinik melakukan perawatan kulit pada pasien" width="901" height="1600" fetchpriority="high" decoding="async">
                        <div class="drw-floating-card drw-card-top"><i class="fa-solid fa-user-doctor"></i><span>Dokter &amp; Terapis<br><strong>Profesional</strong></span></div>
                        <div class="drw-floating-card drw-card-bottom"><i class="fa-solid fa-heart-pulse"></i><span>Perawatan yang<br><strong>Personal</strong></span></div>
                        <span class="drw-orbit drw-orbit-one"></span>
                        <span class="drw-orbit drw-orbit-two"></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-intro-section">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5">
                    <div class="drw-image-collage">
                        <img class="drw-image-main drw-photo-about" src="images/clinic/tentang-kami-klinik.webp" alt="Staf melayani pasien di Klinik Pratama DRW Estetika" width="1600" height="901" loading="lazy" decoding="async">
                        <div class="drw-image-note"><i class="fa-solid fa-quote-left"></i><span>Nyaman, aman, dan didampingi.</span></div>
                    </div>
                </div>
                <div class="col-lg-6 offset-lg-1">
                    <div class="drw-section-kicker">MENGENAL KAMI</div>
                    <h2>Tempat untuk <em>bercerita</em> tentang kulit Anda.</h2>
                    <p>Klinik Pratama DRW Estetika adalah klinik kecantikan yang menghadirkan perawatan kulit secara lebih personal. Kami memahami bahwa kebutuhan setiap orang berbeda, sehingga setiap langkah perawatan diawali dengan konsultasi.</p>
                    <p>Dengan dukungan dokter dan tenaga profesional, kami membantu Anda merawat kulit dengan cara yang lebih terarah dan nyaman.</p>
                    <a class="drw-text-link" href="tentang.php">Cerita kami selengkapnya <i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-services" aria-labelledby="layanan-preview-title">
        <div class="container">
            <div class="drw-section-heading">
                <div>
                    <div class="drw-section-kicker">LAYANAN PURWOREJO</div>
                    <h2 id="layanan-preview-title">Perawatan untuk setiap <em>kebutuhan.</em></h2>
                </div>
                <a class="drw-text-link d-none d-md-inline-flex" href="layanan.php">Lihat semua layanan <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="row g-4">
                <div class="col-sm-6 col-lg-3"><a class="drw-service-card" href="layanan.php#facial"><img class="drw-photo-facial" src="images/clinic/layanan-facial.webp" alt="Sesi perawatan facial" width="1600" height="900" loading="lazy" decoding="async"><span class="drw-service-overlay"></span><span class="drw-service-content"><small>01</small><strong>Facial</strong><em>Untuk kulit lebih bersih dan segar <i class="fa-solid fa-arrow-right"></i></em></span></a></div>
                <div class="col-sm-6 col-lg-3"><a class="drw-service-card" href="layanan.php#dpl"><img src="images/clinic/layanan-dpl.webp" alt="Sesi perawatan DPL" width="901" height="1600" loading="lazy" decoding="async"><span class="drw-service-overlay"></span><span class="drw-service-content"><small>02</small><strong>DPL</strong><em>Solusi untuk flek dan pigmentasi <i class="fa-solid fa-arrow-right"></i></em></span></a></div>
                <div class="col-sm-6 col-lg-3"><a class="drw-service-card" href="layanan.php#skin-booster"><img class="drw-photo-skinbooster" src="images/clinic/layanan-skinbooster.webp" alt="Sesi perawatan skin booster" width="1600" height="901" loading="lazy" decoding="async"><span class="drw-service-overlay"></span><span class="drw-service-content"><small>03</small><strong>Skin Booster</strong><em>Untuk kulit lebih terhidrasi <i class="fa-solid fa-arrow-right"></i></em></span></a></div>
                <div class="col-sm-6 col-lg-3"><a class="drw-service-card" href="layanan.php#tindakan-dokter"><img class="drw-photo-doctor" src="images/clinic/layanan-tindakan-dokter.webp" alt="Tenaga klinik melakukan tindakan perawatan" width="1600" height="901" loading="lazy" decoding="async"><span class="drw-service-overlay"></span><span class="drw-service-content"><small>04</small><strong>Tindakan Dokter</strong><em>Dengan konsultasi terlebih dahulu <i class="fa-solid fa-arrow-right"></i></em></span></a></div>
            </div>
            <a class="drw-text-link d-md-none mt-4" href="layanan.php">Lihat semua layanan <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </section>

    <section class="drw-page-links" aria-labelledby="jelajahi-title">
        <div class="container">
            <div class="drw-section-heading mb-4">
                <div>
                    <div class="drw-section-kicker">JELAJAHI INFORMASI</div>
                    <h2 id="jelajahi-title">Temukan yang Anda <em>butuhkan.</em></h2>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4"><a class="drw-link-panel" href="afiliasi.php"><span class="drw-link-panel-icon"><i class="fa-solid fa-handshake"></i></span><strong>Dashboard Afiliator</strong><span>Program kemitraan &amp; komisi referral pasien.</span><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="col-md-4"><a class="drw-link-panel" href="cabang.php"><span class="drw-link-panel-icon"><i class="fa-solid fa-location-dot"></i></span><strong>Cabang Klinik</strong><span>Pilih lokasi yang paling nyaman untuk Anda kunjungi.</span><i class="fa-solid fa-arrow-right"></i></a></div>
                <div class="col-md-4"><a class="drw-link-panel" href="kontak.php"><span class="drw-link-panel-icon"><i class="fa-solid fa-comments"></i></span><strong>Kontak &amp; Reservasi</strong><span>Hubungi tim kami untuk menanyakan kebutuhan Anda.</span><i class="fa-solid fa-arrow-right"></i></a></div>
            </div>
        </div>
    </section>

    <section class="drw-cta-section">
        <div class="container">
            <div class="drw-cta-card">
                <div><span class="drw-section-kicker">MULAI DARI KONSULTASI</span><h2>Yuk, dengarkan dulu <em>cerita kulit Anda.</em></h2><p>Konsultasi adalah langkah awal untuk menemukan perawatan yang paling sesuai.</p></div>
                <a class="btn drw-btn-light" href="order.php"><i class="fa-regular fa-calendar-check"></i> Buat Janji Konsultasi</a>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
