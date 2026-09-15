-- Bootstrap database for Klinik DRW Estetika.
-- This file intentionally contains no sample patients, bookings, testimonials, or unverified prices.

CREATE DATABASE IF NOT EXISTS `klinik_drw_estetika`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `klinik_drw_estetika`;

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `google_sub` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `google_email` varchar(100) DEFAULT NULL,
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local',
  `no_telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `aido_mr` varchar(50) DEFAULT NULL COMMENT 'Nomor MR dari AIDO, unik per pasien',
  `id_cabang` int(11) DEFAULT NULL COMMENT 'Cabang utama member',
  `affiliate_code` varchar(20) DEFAULT NULL COMMENT 'Kode afiliasi unik',
  `affiliate_code_updated_at` datetime DEFAULT NULL COMMENT 'Terakhir kali ubah kode afiliasi',
  `total_komisi` int(11) NOT NULL DEFAULT 0 COMMENT 'Saldo komisi aktif/siap ditarik',
  `total_ditarik` int(11) NOT NULL DEFAULT 0 COMMENT 'Total komisi yang telah dicairkan',
  `total_referral` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah pasien referral berhasil',
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `google_sub_unique` (`google_sub`),
  UNIQUE KEY `google_email_unique` (`google_email`),
  UNIQUE KEY `uq_user_aido_mr` (`aido_mr`),
  UNIQUE KEY `uq_user_affiliate_code` (`affiliate_code`),
  KEY `idx_user_cabang` (`id_cabang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE `layanan` (
  `id_layanan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_layanan` varchar(100) NOT NULL,
  `deskripsi_singkat` varchar(255) DEFAULT NULL,
  `harga` int(11) NOT NULL DEFAULT 0,
  `gambar_layanan` varchar(255) DEFAULT NULL COMMENT 'Nama file gambar layanan',
  `status_layanan` enum('aktif','tidak_aktif') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id_layanan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `layanan` (`nama_layanan`, `deskripsi_singkat`, `harga`, `gambar_layanan`, `status_layanan`) VALUES
  ('Konsultasi Dokter', 'Diskusikan keluhan dan kebutuhan kulitmu untuk memahami pilihan perawatan yang sesuai.', 0, 'konsultasi_dokter.jpg', 'aktif');

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_cabang` int(11) DEFAULT NULL COMMENT 'NULL = superadmin semua klinik',
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `status_admin` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `admin_username_unique` (`username`),
  KEY `idx_admin_cabang` (`id_cabang`),
  CONSTRAINT `fk_admin_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `order` (
  `id_order` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_layanan` int(11) NOT NULL,
  `id_cabang` int(11) DEFAULT NULL,
  `aido_trx_id` bigint(20) DEFAULT NULL COMMENT 'ID transaksi AIDO untuk dedup impor',
  `tanggal_treatment` datetime NOT NULL,
  `catatan_tambahan` text DEFAULT NULL,
  `referred_by` varchar(20) DEFAULT NULL COMMENT 'Kode afiliasi saat booking',
  `referrer_id` int(11) DEFAULT NULL COMMENT 'ID user yang mereferensikan',
  `komisi_nominal` int(11) NOT NULL DEFAULT 0 COMMENT 'Nominal komisi afiliasi',
  `komisi_status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Status pembayaran komisi',
  `total_bayar` int(11) DEFAULT NULL COMMENT 'Total aktual pembayaran transaksi',
  `status_order` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `tanggal_order_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_order`),
  KEY `fk_order_user` (`id_user`),
  KEY `fk_order_layanan` (`id_layanan`),
  KEY `idx_order_referrer` (`referrer_id`),
  KEY `idx_order_referred_by` (`referred_by`),
  UNIQUE KEY `uq_order_aido_trx` (`aido_trx_id`),
  KEY `idx_order_cabang_tanggal` (`id_cabang`, `tanggal_treatment`),
  KEY `idx_order_active_booking` (`id_user`, `id_layanan`, `id_cabang`, `tanggal_treatment`, `status_order`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_layanan` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id_layanan`) ON UPDATE CASCADE,
  CONSTRAINT `fk_order_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_order_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `affiliate_withdrawal` (
  `id_withdrawal` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `nominal` int(11) NOT NULL,
  `tipe_tujuan` enum('bank','ewallet') NOT NULL DEFAULT 'bank',
  `nama_bank` varchar(50) NOT NULL COMMENT 'Nama Bank / E-Wallet',
  `nomor_rekening` varchar(50) NOT NULL,
  `nama_pemilik` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected','completed') NOT NULL DEFAULT 'pending',
  `catatan_admin` text DEFAULT NULL,
  `tanggal_pengajuan` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_diproses` datetime DEFAULT NULL,
  PRIMARY KEY (`id_withdrawal`),
  KEY `idx_withdrawal_user` (`id_user`),
  KEY `idx_withdrawal_status` (`status`),
  CONSTRAINT `fk_withdrawal_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `affiliate_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `tipe` enum('komisi_masuk','penarikan','koreksi') NOT NULL,
  `nominal` int(11) NOT NULL COMMENT 'Positif untuk komisi masuk, negatif untuk penarikan/koreksi',
  `keterangan` varchar(255) NOT NULL,
  `id_order` int(11) DEFAULT NULL,
  `id_withdrawal` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_log_user` (`id_user`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_log_order` FOREIGN KEY (`id_order`) REFERENCES `order` (`id_order`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_log_withdrawal` FOREIGN KEY (`id_withdrawal`) REFERENCES `affiliate_withdrawal` (`id_withdrawal`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `testimoni` (
  `id_testimoni` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `isi_testimoni` text NOT NULL,
  `tanggal_testimoni` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_testimoni` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id_testimoni`),
  KEY `fk_testimoni_user` (`id_user`),
  CONSTRAINT `fk_testimoni_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
