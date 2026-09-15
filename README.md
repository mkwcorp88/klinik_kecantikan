# Klinik DRW Estetika

Website PHP native untuk Klinik DRW Estetika. Pasien membuat akun melalui Google, memilih cabang dan jadwal konsultasi, lalu memantau status booking. Admin menggunakan akses lokal terpisah untuk mengelola booking, layanan, pasien, dan testimoni.

## Alur Pasien

1. Pilih cabang Purworejo, Kutoarjo, atau Magelang.
2. Masuk dengan Google.
3. Isi nomor WhatsApp, konsultasi, dan jadwal yang diinginkan.
4. Booking masuk sebagai `pending` sampai dikonfirmasi admin.
5. Pasien melihat statusnya di riwayat booking.

## Prasyarat

- PHP 8.2 atau lebih baru dengan ekstensi `mysqli` dan `curl`.
- MariaDB atau MySQL.
- Composer.
- Akun Google Cloud untuk Login Google.

## Menjalankan Lokal

1. Salin konfigurasi lokal dan isi kredensial database:

   ```sh
   cp config.local.php.example config.local.php
   ```

2. Buat database baru dengan mengimpor bootstrap bersih. File ini tidak berisi pasien, booking, testimoni, tarif, atau data klinik yang tidak terverifikasi:

   ```sh
   mariadb -u USER_DATABASE -p < klinik_drw_estetika.sql
   ```

3. Pasang dependensi OAuth:

   ```sh
   composer install
   ```

4. Jalankan server dari root proyek:

   ```sh
   php -S 127.0.0.1:8000
   ```

5. Buka `http://127.0.0.1:8000`.

`config.local.php` dan `vendor/` sengaja tidak dilacak Git. Jangan menyimpan OAuth client secret atau kredensial produksi di `config.php`.

Jika aplikasi berjalan di belakang reverse proxy yang mengakhiri HTTPS, set `TRUSTED_PROXY` ke `true` hanya bila proxy tersebut tepercaya.

## Migrasi Database Lama

Untuk database lama, buat backup lalu jalankan migrasi berikut **berurutan**:

1. `migrations/001_google_oauth_and_cabang.sql` — menambahkan kolom Login Google, tabel cabang, hubungan booking-cabang, dan status testimoni `rejected`.
2. `migrations/002_admin_per_cabang.sql` — admin per klinik.
3. `migrations/003_aido_import.sql` — kolom `aido_mr` dan indeks untuk impor AIDO Purworejo.
4. `migrations/004_member_cabang.sql` — kolom `id_cabang` pada tabel `user`, foreign key, dan pengisian cabang utama untuk member AIDO yang sudah ada.
5. `migrations/005_affiliate_system.sql` — sistem afiliasi (kode referral, komisi, penarikan/withdrawal, dan log mutasi komisi).

Jalankan masing-masing **sekali saja**. Jangan menjalankan migrasi 001–005 setelah mengimpor `klinik_drw_estetika.sql` (bootstrap sudah lengkap).

## Konfigurasi Login Google

1. Buat project di Google Cloud Console.
2. Konfigurasikan branding dan consent screen OAuth.
3. Buat OAuth Client ID bertipe **Web application**.
4. Tambahkan redirect URI berikut secara persis untuk lokal:

   ```text
   http://127.0.0.1:8000/auth/google_callback.php
   ```

5. Tambahkan email penguji saat aplikasi masih berstatus Testing.
6. Salin Client ID dan Client Secret ke `config.local.php`:

   ```php
   'GOOGLE_CLIENT_ID' => '...apps.googleusercontent.com',
   'GOOGLE_CLIENT_SECRET' => '...',
   'GOOGLE_REDIRECT_URI' => 'http://127.0.0.1:8000/auth/google_callback.php',
   ```

Untuk deployment, gunakan HTTPS dan ubah `BASE_URL` serta redirect URI Google ke domain produksi yang sama persis.

## Admin Lokal

Isi `ADMIN_USERNAME` dan `ADMIN_PASSWORD_HASH` di `config.local.php`. Buat hash password menggunakan PHP:

```sh
php -r "echo password_hash('GantiDenganPasswordAman', PASSWORD_DEFAULT), PHP_EOL;"
```

## Data Klinik

Konten publik hanya menggunakan informasi yang telah dikonfirmasi: bagian dari DRW Skincare, konsultasi dokter, dan cabang Purworejo, Kutoarjo, serta Magelang. Tambahkan alamat, nomor WhatsApp, tarif, atau layanan detail hanya setelah data resmi tersedia.
