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
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `google_sub_unique` (`google_sub`),
  UNIQUE KEY `google_email_unique` (`google_email`)
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
  `tanggal_treatment` datetime NOT NULL,
  `catatan_tambahan` text DEFAULT NULL,
  `status_order` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `tanggal_order_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_order`),
  KEY `fk_order_user` (`id_user`),
  KEY `fk_order_layanan` (`id_layanan`),
  KEY `idx_order_cabang_tanggal` (`id_cabang`, `tanggal_treatment`),
  KEY `idx_order_active_booking` (`id_user`, `id_layanan`, `id_cabang`, `tanggal_treatment`, `status_order`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_layanan` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id_layanan`) ON UPDATE CASCADE,
  CONSTRAINT `fk_order_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE RESTRICT
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
