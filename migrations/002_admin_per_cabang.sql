-- Memisahkan admin per klinik.
-- id_cabang NULL = superadmin (semua klinik).
-- Jalankan sekali pada database yang sudah ada. Backup dulu sebelum migrasi.

CREATE TABLE IF NOT EXISTS `admin` (
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
