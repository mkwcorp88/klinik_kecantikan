# Proyek Website Klinik Kecantikan

Aplikasi web sederhana untuk sebuah klinik kecantikan, dibangun dengan PHP native dan MySQL. Memungkinkan member untuk melihat layanan, order, memberi testimoni, dan mengelola profil. Admin memiliki dashboard untuk manajemen data.

## Fitur Utama

* **Member:** Registrasi, login, lihat layanan, order, riwayat order, buat & kelola testimoni pribadi, edit profil & password.
* **Admin:** Login khusus, dashboard statistik, kelola member, kelola layanan (CRUD dengan soft delete), kelola order (ubah status), kelola testimoni (approve/reject/delete).
* **Frontend:** Halaman utama dengan slider layanan unggulan (SwiperJS), testimoni promo, CTA, footer informatif.
* **Lainnya:** Desain responsif dengan Bootstrap 5, navigasi & tema konsisten.

## Teknologi

* PHP (Native)
* MySQL (via XAMPP)
* HTML5, CSS3, JavaScript
* Bootstrap 5
* SwiperJS
* Font Awesome

## Cara Menjalankan (Lokal)

1.  **Prasyarat:** XAMPP (Apache, MySQL, PHP) terinstal.
2.  **Clone/Unduh Proyek:** Letakkan folder proyek `klinik_kecantikan` di dalam `htdocs` XAMPP Anda.
3.  **Database:**
    * Buat database baru bernama `klinik_kecantikan` di phpMyAdmin.
    * Impor file `database_schema.sql` (Anda perlu membuat file ini dari query DDL `CREATE TABLE` yang telah disediakan sebelumnya) ke database `klinik_kecantikan` tersebut.
    * (Opsional) Jalankan query `INSERT INTO` untuk data contoh layanan dan user jika ada.
4.  **Konfigurasi:** Pastikan detail koneksi database di `config.php` sudah benar (biasanya default XAMPP: user `root`, password kosong). Sesuaikan juga `BASE_URL`.
5.  **Akses:** Buka browser dan kunjungi `http://localhost/klinik_kecantikan/`.

## Kredensial Admin

* **Username:** `admin`
* **Password:** `rekammedis`
    *(Kredensial ini ada di `config.php`)*

## Screenshots Aplikasi

Tambahkan screenshot tampilan website Anda di sini untuk memberikan gambaran visual.

**Cara Menambahkan Screenshot di Markdown:**

1.  Buat folder baru di repositori Anda, misalnya `screenshots/` atau `docs/images/`.
2.  Letakkan file gambar screenshot Anda (misalnya, `halaman_utama.png`, `admin_dashboard.jpg`) di dalam folder tersebut.
3.  Gunakan sintaks Markdown berikut untuk menampilkan gambar di README ini:

    ```markdown
    ![Deskripsi Gambar 1](preview.png)
    *Tampilan Halaman Utama*

    ![Deskripsi Gambar 2](preview.png)
    *Tampilan Dashboard Admin*
    ```

    Ganti `Deskripsi Gambar` dan `nama_file_gambar.png` sesuai dengan milik Anda.

---

Semoga ringkasan ini membantu!
