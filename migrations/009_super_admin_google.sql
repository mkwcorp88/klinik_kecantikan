-- Akun pemilik masuk lewat Google terverifikasi; id_cabang NULL = semua klinik.
-- Jalankan sekali setelah migrations/002_admin_per_cabang.sql.

ALTER TABLE `admin`
  MODIFY COLUMN `password_hash` varchar(255) DEFAULT NULL,
  ADD COLUMN `google_email` varchar(100) DEFAULT NULL AFTER `username`,
  ADD COLUMN `google_sub` varchar(255) DEFAULT NULL AFTER `google_email`,
  ADD UNIQUE KEY `admin_google_email_unique` (`google_email`),
  ADD UNIQUE KEY `admin_google_sub_unique` (`google_sub`);

INSERT INTO `admin` (`username`, `password_hash`, `google_email`, `google_sub`, `id_cabang`, `nama_lengkap`, `status_admin`)
VALUES ('drwcorpora@gmail.com', NULL, 'drwcorpora@gmail.com', NULL, NULL, 'DRW Corpora', 'aktif');
