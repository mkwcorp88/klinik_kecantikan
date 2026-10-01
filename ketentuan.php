<?php
require_once 'config.php';

$pageTitle = 'Syarat dan Ketentuan | ' . NAMA_KLINIK;
$pageDescription = 'Syarat dan ketentuan penggunaan website Klinik Pratama DRW Estetika.';
$bodyClass = 'home-page site-page';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="drw-hero-label">PENGGUNAAN WEBSITE</span>
                    <h1>Syarat dan <em>Ketentuan.</em></h1>
                    <p>Ketentuan ini mengatur penggunaan website, akun pasien, Login Google, dan pengajuan booking di Klinik Pratama DRW Estetika.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="drw-section-intro">
                        <div class="drw-section-kicker">SYARAT PENGGUNAAN</div>
                        <h2>Gunakan akun dan layanan kami secara <em>aman dan bertanggung jawab.</em></h2>
                    </div>

                    <article class="mb-5">
                        <h3>Akun dan Login Google</h3>
                        <p>Anda dapat menggunakan Login Google atau akun lokal yang tersedia untuk mengakses fitur tertentu. Anda bertanggung jawab menjaga keamanan akun dan perangkat yang digunakan untuk masuk.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Informasi yang diberikan</h3>
                        <p>Pastikan nama, nomor WhatsApp, dan data booking yang dikirimkan akurat serta terbaru. Kesalahan data dapat memengaruhi proses konfirmasi atau penjadwalan konsultasi.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Booking dan konsultasi</h3>
                        <p>Pengajuan booking melalui website merupakan permintaan jadwal dan tetap bergantung pada ketersediaan serta konfirmasi dari pihak klinik. Informasi pada website tidak menggantikan konsultasi langsung dengan tenaga profesional.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Penggunaan yang dilarang</h3>
                        <p>Anda tidak boleh menggunakan website untuk mengirim data palsu, mengganggu sistem, mencoba mengakses akun orang lain, atau melakukan aktivitas lain yang melanggar hukum maupun hak pihak lain.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Perubahan layanan</h3>
                        <p>Klinik Pratama DRW Estetika dapat memperbarui fitur website, proses booking, atau ketentuan ini dari waktu ke waktu. Perubahan berlaku setelah dipublikasikan pada website.</p>
                    </article>

                    <article>
                        <h3>Hubungi kami</h3>
                        <p>Untuk pertanyaan terkait penggunaan website atau akun, silakan kunjungi halaman <a href="kontak.php">Kontak</a>. Penggunaan data pribadi diatur lebih lanjut dalam <a href="privasi.php">Kebijakan Privasi</a>.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
