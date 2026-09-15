-- Kunci idempotensi untuk impor data AIDO (Purworejo).
-- Aman dijalankan ulang: IF NOT EXISTS tidak didukung untuk ADD COLUMN di semua versi,
-- jadi cek manual dulu bila migrasi pernah dijalankan sebagian.

ALTER TABLE `user`
  ADD COLUMN `aido_mr` varchar(50) DEFAULT NULL COMMENT 'Nomor MR dari AIDO, unik per pasien' AFTER `alamat`;

ALTER TABLE `user`
  ADD UNIQUE KEY `uq_user_aido_mr` (`aido_mr`);

ALTER TABLE `order`
  ADD COLUMN `aido_trx_id` bigint(20) DEFAULT NULL COMMENT 'ID transaksi AIDO untuk dedup impor' AFTER `id_cabang`;

ALTER TABLE `order`
  ADD UNIQUE KEY `uq_order_aido_trx` (`aido_trx_id`);
