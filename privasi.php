<?php
require_once 'config.php';

$pageTitle = 'Kebijakan Privasi | ' . NAMA_KLINIK;
$pageDescription = 'Kebijakan privasi penggunaan website dan Login Google Klinik Pratama DRW Estetika.';
$bodyClass = 'home-page site-page';
require 'partials/site_header.php';
?>

<main>
    <section class="drw-inner-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <span class="drw-hero-label">PRIVASI ANDA</span>
                    <h1>Kebijakan <em>Privasi.</em></h1>
                    <p>Kami menjelaskan data yang digunakan saat Anda membuat akun, masuk, atau mengajukan booking melalui website Klinik Pratama DRW Estetika.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="drw-page-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="drw-section-intro">
                        <div class="drw-section-kicker">KEBIJAKAN PRIVASI</div>
                        <h2>Data dipakai untuk membantu proses <em>konsultasi dan booking.</em></h2>
                        <p class="drw-copy-lead">Dengan menggunakan website ini, Anda menyetujui pengolahan data sesuai kebijakan berikut.</p>
                    </div>

                    <article class="mb-5">
                        <h3>Data yang kami kumpulkan</h3>
                        <p>Kami dapat menyimpan nama, alamat email, nomor WhatsApp, pilihan cabang, informasi booking, dan informasi lain yang Anda isi sendiri melalui website.</p>
                        <p>Saat memilih Login dengan Google, kami menerima nama, alamat email, dan ID akun Google unik yang diperlukan untuk membuat atau menghubungkan akun. Kami tidak menerima maupun menyimpan kata sandi Google Anda.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Tujuan penggunaan data</h3>
                        <ul>
                            <li>Membuat dan mengelola akun pasien.</li>
                            <li>Memproses permintaan konsultasi dan booking.</li>
                            <li>Menghubungi Anda terkait status booking atau layanan yang diminta.</li>
                            <li>Menjaga keamanan akun, sesi login, dan operasional website.</li>
                        </ul>
                    </article>

                    <article class="mb-5">
                        <h3>Penyimpanan dan perlindungan</h3>
                        <p>Data disimpan pada sistem yang digunakan untuk menjalankan website dan operasional Klinik Pratama DRW Estetika. Kami menerapkan langkah teknis yang wajar untuk membatasi akses tidak sah, termasuk sesi login yang aman dan pembatasan akses admin.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Pembagian data</h3>
                        <p>Kami tidak menjual data pribadi Anda. Data hanya dapat diakses oleh pihak yang memerlukan data tersebut untuk menjalankan layanan, serta dapat dibagikan bila diwajibkan oleh peraturan yang berlaku.</p>
                    </article>

                    <article class="mb-5">
                        <h3>Hak dan pertanyaan Anda</h3>
                        <p>Anda dapat meminta pembaruan atau penghapusan data akun sesuai kebutuhan dan ketentuan yang berlaku. Untuk pertanyaan tentang privasi atau akun, hubungi kami melalui halaman <a href="kontak.php">Kontak</a>.</p>
                    </article>

                    <article>
                        <h3>Perubahan kebijakan</h3>
                        <p>Kebijakan ini dapat diperbarui bila ada perubahan pada layanan, sistem, atau ketentuan yang berlaku. Versi terbaru selalu tersedia pada halaman ini.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
require 'partials/site_footer.php';
$conn->close();
