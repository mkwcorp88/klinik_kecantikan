-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 26, 2025 at 06:04 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `klinik_kecantikan`
--

-- --------------------------------------------------------

--
-- Table structure for table `layanan`
--

CREATE TABLE `layanan` (
  `id_layanan` int(11) NOT NULL,
  `nama_layanan` varchar(100) NOT NULL,
  `deskripsi_singkat` varchar(255) DEFAULT NULL,
  `harga` int(11) NOT NULL,
  `gambar_layanan` varchar(255) DEFAULT NULL COMMENT 'Nama file gambar, contoh: facial.jpg',
  `status_layanan` enum('aktif','tidak_aktif') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `layanan`
--

INSERT INTO `layanan` (`id_layanan`, `nama_layanan`, `deskripsi_singkat`, `harga`, `gambar_layanan`, `status_layanan`) VALUES
(2, 'Facial Dasar', 'Perawatan wajah dasar untuk membersihkan, melembapkan, dan menyegarkan kulit wajah Anda.', 250000, 'facial_dasar.jpg', 'aktif'),
(3, 'Facial Brightening Glow', 'Facial intensif untuk mencerahkan kulit kusam, meratakan warna kulit, dan memberikan efek glowing seketika.', 450000, 'facial_brightening.jpg', 'aktif'),
(4, 'Chemical Peeling', 'Prosedur pengelupasan kulit dengan larutan kimia untuk mengangkat sel kulit mati, mengurangi noda hitam, dan meremajakan kulit.', 600000, 'chemical_peeling.jpg', 'aktif'),
(5, 'Mikrodermabrasi Diamond', 'Perawatan untuk menghaluskan tekstur kulit, menyamarkan bekas jerawat, dan merangsang regenerasi kolagen dengan kristal diamond.', 550000, 'mikrodermabrasi.jpg', 'aktif'),
(6, 'Laser Rejuvenation (Wajah)', 'Perawatan laser untuk meremajakan kulit, mengurangi kerutan halus, mengecilkan pori-pori, dan meningkatkan elastisitas kulit.', 1200000, 'laser_rejuve.jpg', 'aktif'),
(7, 'Treatment Acne Basic', 'Solusi untuk mengatasi jerawat aktif, mengurangi peradangan, dan mencegah timbulnya jerawat baru.', 350000, 'treatment_acne.jpg', 'aktif'),
(8, 'Infus Whitening Premium', 'Perawatan infus untuk mencerahkan kulit secara keseluruhan, meningkatkan daya tahan tubuh, dan memberikan nutrisi bagi kulit.', 950000, 'infus_whitening.jpg', 'aktif'),
(9, 'Body Spa & Massage Relaksasi', 'Paket perawatan tubuh lengkap dengan pijat relaksasi, lulur, dan masker tubuh untuk memanjakan diri dan meredakan stres.', 500000, 'body_spa.jpg', 'aktif'),
(10, 'Laser Hair Removal (Ketiak)', 'Menghilangkan bulu ketiak secara permanen dan aman dengan teknologi laser terkini yang tentunya nyaman.', 400000, 'laser_hairremoval_ketiak.jpg', 'aktif'),
(11, 'Konsultasi Dokter Kecantikan', 'Sesi konsultasi mendalam dengan dokter ahli kami untuk mendapatkan rekomendasi perawatan yang paling sesuai dengan kulit Anda.', 150000, 'konsultasi_dokter.jpg', 'aktif'),
(12, 'dddd', 'dssds', 2147483647, '', 'tidak_aktif');

-- --------------------------------------------------------

--
-- Table structure for table `order`
--

CREATE TABLE `order` (
  `id_order` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_layanan` int(11) NOT NULL,
  `tanggal_treatment` datetime NOT NULL,
  `catatan_tambahan` text DEFAULT NULL,
  `status_order` enum('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  `tanggal_order_dibuat` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order`
--

INSERT INTO `order` (`id_order`, `id_user`, `id_layanan`, `tanggal_treatment`, `catatan_tambahan`, `status_order`, `tanggal_order_dibuat`) VALUES
(4, 2, 9, '2025-05-31 00:03:00', 'oke', 'completed', '2025-05-25 17:03:18'),
(5, 2, 12, '2025-05-29 03:53:00', 'dwwdwd', 'confirmed', '2025-05-25 18:53:29'),
(6, 2, 8, '2025-05-26 07:35:00', '', 'pending', '2025-05-25 19:16:24');

-- --------------------------------------------------------

--
-- Table structure for table `testimoni`
--

CREATE TABLE `testimoni` (
  `id_testimoni` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `isi_testimoni` text NOT NULL,
  `tanggal_testimoni` timestamp NOT NULL DEFAULT current_timestamp(),
  `status_testimoni` enum('pending','approved') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimoni`
--

INSERT INTO `testimoni` (`id_testimoni`, `id_user`, `isi_testimoni`, `tanggal_testimoni`, `status_testimoni`) VALUES
(4, 1, 'Pelayanannya sangat memuaskan! Saya mencoba Facial Brightening Glow dan hasilnya langsung terlihat. Kulit jadi lebih cerah dan glowing. Staffnya juga ramah dan profesional. Pasti akan kembali lagi!', '2025-05-22 17:26:01', 'approved'),
(5, 2, 'Tempatnya bersih dan nyaman sekali. Dokter dan terapisnya sangat informatif dalam menjelaskan setiap prosedur. Saya merasa aman dan puas dengan treatment acne yang saya jalani. Jerawat mulai berkurang signifikan. Terima kasih!', '2025-05-23 17:26:01', 'approved'),
(6, 3, 'Suka banget sama suasana kliniknya, tenang dan bikin rileks. Baru pertama kali coba mikrodermabrasi di sini, sejauh ini kulit terasa lebih halus. Semoga hasilnya maksimal. Recommended!', '2025-05-24 17:26:01', 'pending'),
(7, 4, 'Setelah beberapa kali treatment Laser Rejuvenation, kerutan halus di wajah saya berkurang drastis dan kulit terasa lebih kencang. Benar-benar investasi yang sepadan untuk penampilan. Sangat merekomendasikan klinik ini!', '2025-05-25 12:26:01', 'approved'),
(8, 5, 'Infus whiteningnya oke banget! Badan jadi terasa lebih segar dan kulit terlihat lebih cerah merata. Prosesnya juga nyaman dan cepat. Good service!', '2025-05-25 17:26:01', 'approved'),
(9, 6, 'Baru pertama kali konsultasi di sini, dokternya sangat detail menjelaskan kondisi kulitku. Diberikan rekomendasi treatment yang sesuai budget juga. Next mau coba treatmentnya!', '2025-05-25 07:28:36', 'approved'),
(10, 7, 'Suasana kliniknya benar-benar nyaman dan estetik. Membuat sesi perawatan jadi lebih menyenangkan. Staff di resepsionis juga sangat membantu dan ramah.', '2025-05-25 08:28:36', 'approved'),
(11, 1, 'Saya mencoba treatment baru untuk menghilangkan flek hitam. Sudah 2x sesi, mulai terlihat perubahannya walau belum signifikan. Mungkin butuh beberapa sesi lagi ya? Terapisnya sabar.', '2025-05-25 09:28:36', 'pending'),
(12, 8, 'Dokter Linda (kebetulan namanya sama hehe) sangat ahli! Pengerjaan fillernya rapi dan hasilnya natural. Wajah jadi terlihat lebih fresh. Terima kasih banyak!', '2025-05-25 10:28:36', 'approved'),
(13, 2, 'Kliniknya sangat bersih dan steril. Peralatan yang digunakan juga terlihat modern dan terawat. Ini jadi poin plus buat saya. Pasti jadi langganan.', '2025-05-25 11:28:36', 'approved'),
(14, 9, 'Apakah ada paket treatment khusus untuk mahasiswa? Overall tempatnya bagus, tapi harganya lumayan juga ya untuk beberapa treatment. Semoga ada promo pelajar.', '2025-05-25 12:28:36', 'pending'),
(15, 10, 'Happy banget dengan hasil laser hair removal di sini! Bulu jadi jauh berkurang dan kulit ketiak jadi lebih halus. Bakal rekomendasiin ke teman-teman kantor.', '2025-05-25 13:28:36', 'approved'),
(16, 2, 'ydsagdya dsaudbSG fdusggd auua 8yauha cauh ac\\r\\nsdhu ugud suhd\\r\\n\\r\\nasudg gddhish siahdih', '2025-05-26 06:45:05', '');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_telepon` varchar(15) NOT NULL,
  `alamat` text NOT NULL,
  `tanggal_daftar` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `nama_lengkap`, `username`, `password`, `email`, `no_telepon`, `alamat`, `tanggal_daftar`) VALUES
(1, 'asa', 'asa', '$2y$10$j6UE/g5lBDWf6s2kXZPZa.1o3KQS2JFNsUUzw5BbU0Nf6JvitavLC', 'wdq@efef.com', '00000232323', 'wdwdwdecw fve erweth', '2025-05-25 16:28:09'),
(2, 'arenc wsq sswd', 'aren', '$2y$10$58qlxdiolNheAcEY5tDTuOy40dUuXXjk3mTAmPWFxSQRGxT25GOx.', 'add@sd.id', '087774588123132', 'sfwif madura', '2025-05-25 16:57:52'),
(3, 'Budi Santoso', 'budi_s', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'budi.santoso@example.com', '081234567890', 'Jl. Merdeka No. 10, Jakarta Pusat', '2025-05-25 17:23:38'),
(4, 'Siti Aminah', 'sitiaminah', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'siti.aminah@example.net', '081345678901', 'Jl. Pahlawan No. 25, Bandung', '2025-05-24 17:23:38'),
(5, 'Dewi Lestari', 'dewi_lestari', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', NULL, '081567890123', 'Jl. Kenanga Blok C1 No. 5, Surabaya', '2025-05-23 17:23:38'),
(6, 'Agus Wijaya', 'agusw', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'agus.wijaya@example.org', '081789012345', 'Jl. Sudirman Kav. 12, Medan', '2025-05-22 17:23:38'),
(7, 'Rina Permata', 'rina_prmt', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'rina.permata@example.com', '081901234567', 'Jl. Gajah Mada No. 101, Semarang', '2025-05-21 17:23:38'),
(8, 'Fitriani Indah', 'fitri_indah', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'fitri.indah@example.com', '082112345678', 'Jl. Cendrawasih No. 12, Yogyakarta', '2025-05-20 17:28:25'),
(9, 'Muhammad Iqbal', 'iqbal_keren', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'muh.iqbal@example.net', '082234567890', 'Perumahan Griya Asri Blok B No. 3, Sleman', '2025-05-19 17:28:25'),
(10, 'Linda Kusuma Wardani', 'linda_kw', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', NULL, '082345678901', 'Jl. Kaliurang KM 7, Gg. Mawar No. 1, Depok', '2025-05-18 17:28:25'),
(11, 'Rizky Pratama Putra', 'rizky_pratama', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'rizky.p@example.org', '085712345678', 'Jl. Parangtritis No. 99, Bantul', '2025-05-17 17:28:25'),
(12, 'Eka Putri Handayani', 'eka_putri', '$2y$10$E1yGfX.l7q.hVnZ5U8M73uLwG0YyT.R6h7.tP8d.C/wP8sK.Z0i.G', 'eka.putri@example.com', '085823456789', 'Jl. Magelang No. 45, Mlati', '2025-05-16 17:28:25');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `layanan`
--
ALTER TABLE `layanan`
  ADD PRIMARY KEY (`id_layanan`);

--
-- Indexes for table `order`
--
ALTER TABLE `order`
  ADD PRIMARY KEY (`id_order`),
  ADD KEY `fk_order_user` (`id_user`),
  ADD KEY `fk_order_layanan` (`id_layanan`);

--
-- Indexes for table `testimoni`
--
ALTER TABLE `testimoni`
  ADD PRIMARY KEY (`id_testimoni`),
  ADD KEY `fk_testimoni_user` (`id_user`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username_unique` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `layanan`
--
ALTER TABLE `layanan`
  MODIFY `id_layanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `order`
--
ALTER TABLE `order`
  MODIFY `id_order` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `testimoni`
--
ALTER TABLE `testimoni`
  MODIFY `id_testimoni` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order`
--
ALTER TABLE `order`
  ADD CONSTRAINT `fk_order_layanan` FOREIGN KEY (`id_layanan`) REFERENCES `layanan` (`id_layanan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `testimoni`
--
ALTER TABLE `testimoni`
  ADD CONSTRAINT `fk_testimoni_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
