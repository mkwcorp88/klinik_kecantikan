-- Run once on an existing Klinik DRW Estetika database.
-- Existing bookings remain intact and have id_cabang = NULL until updated manually.
-- MariaDB/MySQL commits DDL implicitly, so create a backup before running this migration.

ALTER TABLE `user`
  MODIFY `password` varchar(255) DEFAULT NULL,
  MODIFY `no_telepon` varchar(20) DEFAULT NULL,
  MODIFY `alamat` text DEFAULT NULL,
  ADD COLUMN `google_sub` varchar(255) DEFAULT NULL AFTER `password`,
  ADD COLUMN `google_email` varchar(100) DEFAULT NULL AFTER `email`,
  ADD COLUMN `auth_provider` enum('local','google') NOT NULL DEFAULT 'local' AFTER `google_email`,
  ADD UNIQUE KEY `google_sub_unique` (`google_sub`),
  ADD UNIQUE KEY `google_email_unique` (`google_email`);

CREATE TABLE `cabang` (
  `id_cabang` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `nama_cabang` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `status_cabang` enum('aktif','tidak_aktif') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id_cabang`),
  UNIQUE KEY `cabang_slug_unique` (`slug`),
  UNIQUE KEY `cabang_nama_unique` (`nama_cabang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cabang` (`slug`, `nama_cabang`, `status_cabang`) VALUES
  ('purworejo', 'Klinik DRW Estetika Purworejo', 'aktif'),
  ('kutoarjo', 'Klinik DRW Estetika Kutoarjo', 'aktif'),
  ('magelang', 'Klinik DRW Estetika Magelang', 'aktif');

ALTER TABLE `order`
  ADD COLUMN `id_cabang` int(11) DEFAULT NULL AFTER `id_layanan`,
  ADD KEY `idx_order_cabang_tanggal` (`id_cabang`, `tanggal_treatment`),
  ADD KEY `idx_order_active_booking` (`id_user`, `id_layanan`, `id_cabang`, `tanggal_treatment`, `status_order`),
  ADD CONSTRAINT `fk_order_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE `testimoni`
  MODIFY `status_testimoni` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending';
