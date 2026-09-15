-- Menandai cabang utama member agar admin cabang dapat melihat pasien tanpa order.
-- Jalankan sekali pada database yang sudah ada. Backup sebelum menjalankan DDL ini.

ALTER TABLE `user`
  ADD COLUMN `id_cabang` int(11) DEFAULT NULL COMMENT 'Cabang utama member' AFTER `aido_mr`,
  ADD KEY `idx_user_cabang` (`id_cabang`),
  ADD CONSTRAINT `fk_user_cabang` FOREIGN KEY (`id_cabang`) REFERENCES `cabang` (`id_cabang`) ON UPDATE CASCADE ON DELETE SET NULL;

UPDATE `user` u
JOIN `cabang` c ON c.slug = 'purworejo'
SET u.id_cabang = c.id_cabang
WHERE u.aido_mr IS NOT NULL AND u.id_cabang IS NULL;
