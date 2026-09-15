<?php
require_once 'config.php';

$pageTitle = 'Tentang Kami | ' . NAMA_KLINIK;
$pageDescription = 'Mengenal Klinik Pratama DRW Estetika, perjalanan kami, dan pendekatan perawatan yang personal.';
$activePage = 'tentang';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <span class="drw-hero-label">TENTANG KAMI</span>
                    <h1>Kecantikan yang berawal dari <em>rasa nyaman.</em></h1>
                    <p>Klinik Pratama DRW Estetika hadir untuk membantu setiap orang merasa lebih percaya diri melalui perawatan yang aman, nyaman, dan sesuai kebutuhan.</p>
                </div>
                <div class="col-lg-5">
                    <div class="drw-inner-hero-art"><img src="images/konsultasi_dokter.jpg" alt="Konsultasi di Klinik Pratama DRW Estetika" onerror="this.src='images/default_layanan.jpg'"></div>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5"><img class="drw-content-photo" src="images/body_spa.jpg" alt="Suasana perawatan yang nyaman" onerror="this.src='images/default_layanan.jpg'"></div>
                <div class="col-lg-6 offset-lg-1">
                    <div class="drw-section-kicker">KLINIK PRATAMA DRW ESTETIKA</div>
                    <h2>Merawat kulit dengan pendekatan yang <em>lebih personal.</em></h2>
                    <p class="drw-copy-lead mt-4">Berawal dari keinginan sederhana untuk membantu orang merasa lebih nyaman dengan dirinya sendiri, DRW Estetika tumbuh menjadi tempat perawatan kulit yang mengedepankan kualitas, kenyamanan, dan kepercayaan.</p>
                    <p>Setiap orang memiliki kebutuhan yang berbeda. Karena itu, kami tidak hanya fokus pada hasil perawatan, tetapi juga pada pengalaman selama menjalani prosesnya.</p>
                    <div class="drw-fact-card"><strong>Sejak 2016</strong><span>Mendampingi berbagai kebutuhan perawatan kulit dan estetika secara lebih terarah.</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-page-section drw-section-soft">
        <div class="container">
            <div class="row gy-5 align-items-start">
                <div class="col-lg-5">
                    <div class="drw-section-intro mb-0">
                        <div class="drw-section-kicker">PERJALANAN KAMI</div>
                        <h2>Dari konsultasi sederhana menjadi tempat untuk <em>bercerita.</em></h2>
                        <p>DRW Estetika berkembang dari keinginan untuk menghadirkan layanan kecantikan yang lebih dekat dan lebih personal. Kami ingin setiap orang merasa didengar sebelum memulai perawatan.</p>
                    </div>
                    <div class="drw-founder-card">
                        <span class="drw-founder-initials">WT</span>
                        <div><small>FOUNDER</small><strong>dr. Wahyu Triasmara</strong><p>Pendekatan yang hangat, terarah, dan disesuaikan dengan kebutuhan setiap pasien.</p></div>
                    </div>
                </div>
                <div class="col-lg-6 offset-lg-1">
                    <div class="row g-3">
                        <div class="col-sm-6"><article class="drw-value-card"><i class="fa-solid fa-comments"></i><h3>Dimulai dari Mendengar</h3><p>Setiap perawatan diawali dengan konsultasi untuk memahami kondisi kulit dan kebutuhan Anda.</p></article></div>
                        <div class="col-sm-6"><article class="drw-value-card"><i class="fa-solid fa-user-doctor"></i><h3>Ditangani Profesional</h3><p>Didukung dokter dan tenaga terlatih untuk membantu memilih langkah perawatan yang tepat.</p></article></div>
                        <div class="col-sm-6"><article class="drw-value-card"><i class="fa-solid fa-shield-heart"></i><h3>Fokus pada Kenyamanan</h3><p>Kami menciptakan suasana yang aman, nyaman, dan tidak terburu-buru.</p></article></div>
                        <div class="col-sm-6"><article class="drw-value-card"><i class="fa-solid fa-sparkles"></i><h3>Hasil yang Terarah</h3><p>Perawatan disesuaikan dengan tujuan dan kondisi kulit masing-masing pasien.</p></article></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-cta-section">
        <div class="container"><div class="drw-cta-card"><div><span class="drw-section-kicker">KENALI KEBUTUHAN ANDA</span><h2>Mulai dari cerita, lanjut ke <em>konsultasi.</em></h2><p>Tim kami siap membantu Anda menentukan langkah awal perawatan.</p></div><a class="btn drw-btn-light" href="order.php"><i class="fa-regular fa-calendar-check"></i> Buat Janji Konsultasi</a></div></div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
