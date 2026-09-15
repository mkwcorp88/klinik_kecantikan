-- Migration 005: Sistem Afiliasi terintegrasi terinspirasi dari DRW Prime
-- Menambahkan field afiliasi pada user, order, serta tabel penarikan dan mutasi komisi.

-- 1) Tambah kolom afiliasi pada tabel user
ALTER TABLE `user`
  ADD COLUMN `affiliate_code` varchar(20) DEFAULT NULL COMMENT 'Kode afiliasi unik' AFTER `id_cabang`,
  ADD COLUMN `affiliate_code_updated_at` datetime DEFAULT NULL COMMENT 'Terakhir kali ubah kode afiliasi' AFTER `affiliate_code`,
  ADD COLUMN `total_komisi` int(11) NOT NULL DEFAULT 0 COMMENT 'Saldo komisi aktif/siap ditarik' AFTER `affiliate_code_updated_at`,
  ADD COLUMN `total_ditarik` int(11) NOT NULL DEFAULT 0 COMMENT 'Total komisi yang telah dicairkan' AFTER `total_komisi`,
  ADD COLUMN `total_referral` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah pasien referral berhasil' AFTER `total_ditarik`,
  ADD UNIQUE KEY `uq_user_affiliate_code` (`affiliate_code`);

-- 2) Tambah kolom afiliasi pada tabel order
ALTER TABLE `order`
  ADD COLUMN `referred_by` varchar(20) DEFAULT NULL COMMENT 'Kode afiliasi saat booking' AFTER `catatan_tambahan`,
  ADD COLUMN `referrer_id` int(11) DEFAULT NULL COMMENT 'ID user yang mereferensikan' AFTER `referred_by`,
  ADD COLUMN `komisi_nominal` int(11) NOT NULL DEFAULT 0 COMMENT 'Nominal komisi afiliasi' AFTER `referrer_id`,
  ADD COLUMN `komisi_status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'Status pembayaran komisi' AFTER `komisi_nominal`,
  ADD COLUMN `total_bayar` int(11) DEFAULT NULL COMMENT 'Total aktual pembayaran transaksi' AFTER `komisi_status`,
  ADD KEY `idx_order_referrer` (`referrer_id`),
  ADD KEY `idx_order_referred_by` (`referred_by`),
  ADD CONSTRAINT `fk_order_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `user` (`id_user`) ON DELETE SET NULL ON UPDATE CASCADE;

-- 3) Buat tabel penarikan komisi (affiliate_withdrawal)
CREATE TABLE IF NOT EXISTS `affiliate_withdrawal` (
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

-- 4) Buat tabel log mutasi komisi (affiliate_log)
CREATE TABLE IF NOT EXISTS `affiliate_log` (
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
