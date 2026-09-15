<?php
require_once 'config.php';

$pageTitle = 'Kontak & Reservasi | ' . NAMA_KLINIK;
$pageDescription = 'Hubungi Klinik Pratama DRW Estetika Purworejo atau buat janji konsultasi di cabang pilihan Anda.';
$activePage = 'kontak';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <span class="drw-hero-label">KONTAK &amp; RESERVASI</span>
                    <h1>Siap mendengar cerita <em>kulit Anda.</em></h1>
                    <p>Hubungi tim Klinik Pratama DRW Estetika untuk konsultasi, informasi layanan, atau bantuan membuat janji.</p>
                </div>
                <div class="col-lg-5"><div class="drw-inner-hero-art"><img src="images/konsultasi_dokter.jpg" alt="Tim konsultasi Klinik Pratama DRW Estetika" onerror="this.src='images/default_layanan.jpg'"></div></div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="drw-section-intro">
                <div class="drw-section-kicker">KLINIK PURWOREJO</div>
                <h2>Hubungi kami di <em>Purworejo.</em></h2>
                <p>Informasi kontak dan jam operasional berikut berlaku untuk cabang Purworejo.</p>
            </div>
            <div class="drw-contact-grid">
                <div class="drw-contact-panel">
                    <h2>Kontak Klinik</h2>
                    <div class="drw-contact-list">
                        <div><i class="fa-solid fa-location-dot"></i><div><strong>Alamat</strong><span>Jl. Jend. Sudirman No.1, Purworejo</span></div></div>
                        <div><i class="fa-brands fa-whatsapp"></i><div><strong>WhatsApp</strong><a href="https://wa.me/6282110859908" target="_blank" rel="noopener noreferrer">0821-1085-9908</a></div></div>
                        <div><i class="fa-solid fa-phone"></i><div><strong>Telepon</strong><a href="tel:+62275321843">0275-321843</a></div></div>
                        <div><i class="fa-brands fa-instagram"></i><div><strong>Instagram</strong><a href="https://instagram.com/drwskincare.purworejo" target="_blank" rel="noopener noreferrer">@drwskincare.purworejo</a></div></div>
                        <div><i class="fa-brands fa-tiktok"></i><div><strong>TikTok</strong><a href="https://www.tiktok.com/@drwskincare.purworejo" target="_blank" rel="noopener noreferrer">@drwskincare.purworejo</a></div></div>
                        <div><i class="fa-brands fa-youtube"></i><div><strong>YouTube</strong><a href="https://www.youtube.com/@drwskincare" target="_blank" rel="noopener noreferrer">@drwskincare</a></div></div>
                    </div>
                </div>
                <div class="drw-contact-panel is-dark">
                    <h2>Jam Operasional</h2>
                    <dl class="drw-hours-list">
                        <div><dt>Senin</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Selasa</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Rabu</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Kamis</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Jumat</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Sabtu</dt><dd>08.00-18.00 WIB</dd></div>
                        <div><dt>Minggu</dt><dd>08.00-18.00 WIB</dd></div>
                    </dl>
                    <p class="drw-small-note">Untuk perubahan jadwal atau ketersediaan perawatan, silakan konfirmasi terlebih dahulu kepada tim klinik.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-page-section drw-section-soft">
        <div class="container">
            <div class="row gy-4 align-items-center">
                <div class="col-lg-6"><div class="drw-section-intro mb-0"><div class="drw-section-kicker">BUAT JANJI</div><h2>Pilih cabang tujuan <em>Anda.</em></h2><p>Masuk ke formulir booking untuk memilih cabang, layanan konsultasi, dan tanggal yang Anda inginkan.</p></div></div>
                <div class="col-lg-5 offset-lg-1"><div class="drw-contact-branch-list"><a href="order.php?cabang=purworejo"><span>Purworejo<small>Jl. Jend. Sudirman No.1</small></span><i class="fa-solid fa-arrow-right"></i></a><a href="order.php?cabang=kutoarjo"><span>Kutoarjo<small>Pilih cabang untuk membuat janji</small></span><i class="fa-solid fa-arrow-right"></i></a><a href="order.php?cabang=magelang"><span>Magelang<small>Pilih cabang untuk membuat janji</small></span><i class="fa-solid fa-arrow-right"></i></a></div></div>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
