-- Migration 006: Menambahkan status_afiliasi pada tabel user untuk kontrol approval dan manajemen admin
ALTER TABLE `user`
  ADD COLUMN `status_afiliasi` enum('pending','aktif','nonaktif') NOT NULL DEFAULT 'pending' COMMENT 'Status approval dan keaktifan afiliator' AFTER `affiliate_code`;

-- Kode afiliasi yang sudah ada dianggap telah aktif agar link lama tidak terputus.
UPDATE `user`
SET `status_afiliasi` = 'aktif'
WHERE `affiliate_code` IS NOT NULL;
