-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 06 Jun 2026 pada 21.13
-- Versi server: 10.11.16-MariaDB-cll-lve
-- Versi PHP: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fdngbjxi_ujiansmkbhg`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_login` int(1) DEFAULT 0,
  `nama_lengkap` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`, `is_login`, `nama_lengkap`) VALUES
(1, 'AdminGuru1', 'd9a81b8eab843c318c320f5ef34cc599', 0, 'Siti Puji Pertiwi'),
(2, 'AdminGuru2', '1291461192ccf8c070e81f37dc3ed8b2', 1, 'Dicky'),
(3, 'AdminGuru3', '874d253cb476d208cf566b85f261bc0f', 0, 'Lena Septiana'),
(4, 'AdminSMK', '$2y$12$q8LJCdjXrOs8br7ZGrNDU.vGLEbld4Vk/lZ51PO43BFpD20NtDflS', 0, 'Administrator SMK ISLAM BAHAGIA');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jawaban_siswa`
--

CREATE TABLE `jawaban_siswa` (
  `id` int(11) NOT NULL,
  `ujian_id` int(11) NOT NULL,
  `soal_id` int(11) NOT NULL,
  `jawaban_dipilih` enum('A','B','C','D','E') DEFAULT NULL,
  `status_benar` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `jawaban_siswa`
--

INSERT INTO `jawaban_siswa` (`id`, `ujian_id`, `soal_id`, `jawaban_dipilih`, `status_benar`) VALUES
(84, 3, 10, NULL, 0),
(85, 3, 11, NULL, 0),
(86, 3, 12, NULL, 0),
(87, 3, 13, NULL, 0),
(88, 3, 14, NULL, 0),
(89, 3, 15, NULL, 0),
(90, 3, 16, NULL, 0),
(91, 3, 17, NULL, 0),
(92, 3, 18, NULL, 0),
(93, 3, 19, NULL, 0),
(94, 3, 20, NULL, 0),
(95, 3, 21, NULL, 0),
(96, 3, 22, NULL, 0),
(97, 3, 23, NULL, 0),
(98, 3, 24, NULL, 0),
(99, 3, 25, NULL, 0),
(100, 3, 26, NULL, 0),
(101, 3, 27, NULL, 0),
(102, 3, 28, NULL, 0),
(103, 3, 29, NULL, 0),
(104, 3, 30, NULL, 0),
(105, 3, 31, NULL, 0),
(106, 3, 32, NULL, 0),
(107, 3, 33, NULL, 0),
(108, 3, 34, NULL, 0),
(109, 3, 35, NULL, 0),
(110, 3, 36, NULL, 0),
(111, 3, 37, NULL, 0),
(112, 3, 38, NULL, 0),
(113, 3, 39, NULL, 0),
(114, 3, 40, NULL, 0),
(115, 3, 41, NULL, 0),
(116, 3, 42, NULL, 0),
(117, 3, 43, NULL, 0),
(118, 3, 44, NULL, 0),
(119, 3, 45, NULL, 0),
(120, 3, 46, NULL, 0),
(121, 3, 47, NULL, 0),
(122, 3, 48, NULL, 0),
(123, 3, 49, NULL, 0),
(124, 3, 103, 'A', 0),
(125, 3, 104, NULL, 0),
(126, 3, 105, NULL, 0),
(127, 3, 106, NULL, 0),
(128, 3, 107, NULL, 0),
(129, 3, 108, NULL, 0),
(130, 3, 109, NULL, 0),
(131, 3, 110, NULL, 0),
(132, 3, 111, NULL, 0),
(133, 3, 113, NULL, 0),
(134, 3, 115, 'C', 0),
(135, 3, 118, NULL, 0),
(136, 3, 121, NULL, 0),
(137, 3, 124, NULL, 0),
(138, 3, 126, NULL, 0),
(139, 3, 127, NULL, 0),
(140, 3, 128, NULL, 0),
(141, 3, 129, NULL, 0),
(142, 3, 130, NULL, 0),
(143, 3, 131, NULL, 0),
(144, 3, 132, NULL, 0),
(145, 3, 133, NULL, 0),
(146, 3, 134, NULL, 0),
(147, 3, 135, 'B', 0),
(148, 3, 136, NULL, 0),
(149, 3, 138, NULL, 0),
(150, 3, 140, NULL, 0),
(151, 3, 142, NULL, 0),
(152, 3, 143, NULL, 0),
(153, 3, 144, NULL, 0),
(154, 3, 146, NULL, 0),
(155, 3, 147, NULL, 0),
(156, 3, 152, NULL, 0),
(157, 3, 155, NULL, 0),
(158, 3, 157, NULL, 0),
(159, 3, 159, NULL, 0),
(160, 3, 161, NULL, 0),
(161, 3, 162, NULL, 0),
(162, 3, 163, NULL, 0),
(163, 3, 164, NULL, 0),
(244, 5, 10, 'B', 1),
(245, 5, 11, 'B', 0),
(246, 5, 12, 'D', 1),
(247, 5, 13, 'A', 0),
(248, 5, 14, 'E', 0),
(249, 5, 15, 'E', 1),
(250, 5, 16, 'E', 0),
(251, 5, 17, 'C', 0),
(252, 5, 18, 'A', 0),
(253, 5, 19, 'E', 0),
(254, 5, 20, 'D', 0),
(255, 5, 21, 'C', 1),
(256, 5, 22, 'C', 0),
(257, 5, 23, 'A', 0),
(258, 5, 24, 'E', 0),
(259, 5, 25, 'E', 0),
(260, 5, 26, 'E', 0),
(261, 5, 27, 'E', 1),
(262, 5, 28, 'C', 0),
(263, 5, 29, 'C', 0),
(264, 5, 30, 'E', 1),
(265, 5, 31, 'A', 0),
(266, 5, 32, 'D', 0),
(267, 5, 33, 'C', 0),
(268, 5, 34, 'C', 0),
(269, 5, 35, 'A', 0),
(270, 5, 36, 'C', 0),
(271, 5, 37, 'D', 1),
(272, 5, 38, 'D', 0),
(273, 5, 39, 'D', 0),
(274, 5, 40, 'B', 0),
(275, 5, 41, 'A', 0),
(276, 5, 42, 'D', 0),
(277, 5, 43, 'C', 0),
(278, 5, 44, 'A', 0),
(279, 5, 45, 'D', 0),
(280, 5, 46, 'B', 1),
(281, 5, 47, 'A', 0),
(282, 5, 48, 'E', 1),
(283, 5, 49, 'B', 0),
(284, 5, 103, NULL, 0),
(285, 5, 104, NULL, 0),
(286, 5, 105, NULL, 0),
(287, 5, 106, NULL, 0),
(288, 5, 107, NULL, 0),
(289, 5, 108, NULL, 0),
(290, 5, 109, NULL, 0),
(291, 5, 110, NULL, 0),
(292, 5, 111, NULL, 0),
(293, 5, 113, NULL, 0),
(294, 5, 115, NULL, 0),
(295, 5, 118, NULL, 0),
(296, 5, 121, NULL, 0),
(297, 5, 124, NULL, 0),
(298, 5, 126, NULL, 0),
(299, 5, 127, NULL, 0),
(300, 5, 128, NULL, 0),
(301, 5, 129, NULL, 0),
(302, 5, 130, NULL, 0),
(303, 5, 131, NULL, 0),
(304, 5, 132, NULL, 0),
(305, 5, 133, NULL, 0),
(306, 5, 134, NULL, 0),
(307, 5, 135, NULL, 0),
(308, 5, 136, NULL, 0),
(309, 5, 138, NULL, 0),
(310, 5, 140, NULL, 0),
(311, 5, 142, NULL, 0),
(312, 5, 143, NULL, 0),
(313, 5, 144, NULL, 0),
(314, 5, 146, NULL, 0),
(315, 5, 147, NULL, 0),
(316, 5, 152, NULL, 0),
(317, 5, 155, NULL, 0),
(318, 5, 157, NULL, 0),
(319, 5, 159, NULL, 0),
(320, 5, 161, NULL, 0),
(321, 5, 162, NULL, 0),
(322, 5, 163, NULL, 0),
(323, 5, 164, NULL, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `aktivitas` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `admin_id`, `aktivitas`, `created_at`) VALUES
(1, 0, 'Login ke Portal Admin CBT', '2026-06-05 01:14:54'),
(2, 2, 'Login ke Portal Admin CBT', '2026-06-05 01:29:10'),
(3, 3, 'Login ke Portal Admin CBT', '2026-06-05 01:37:01'),
(4, 1, 'Login ke Portal Admin CBT', '2026-06-05 03:21:57'),
(5, 3, 'Login ke Portal Admin CBT', '2026-06-05 05:11:45'),
(6, 1, 'Login ke Portal Admin CBT', '2026-06-05 07:36:39'),
(7, 3, 'Login ke Portal Admin CBT', '2026-06-05 07:38:48'),
(8, 2, 'Login ke Portal Admin CBT', '2026-06-05 08:32:44'),
(9, 3, 'Login ke Portal Admin CBT', '2026-06-05 10:42:25'),
(10, 2, 'Login ke Portal Admin CBT', '2026-06-05 11:19:07'),
(11, 1, 'Login ke Portal Admin CBT', '2026-06-05 13:41:42'),
(12, 3, 'Login ke Portal Admin CBT', '2026-06-05 16:30:28'),
(13, 1, 'Login ke Portal Admin CBT', '2026-06-06 00:50:45'),
(14, 1, 'Logout dari Portal Admin CBT', '2026-06-06 01:38:18'),
(15, 3, 'Login ke Portal Admin CBT', '2026-06-06 03:58:12'),
(16, 3, 'Logout dari Portal Admin CBT', '2026-06-06 04:11:51'),
(17, 2, 'Login ke Portal Admin CBT', '2026-06-06 06:36:01'),
(18, 3, 'Login ke Portal Admin CBT', '2026-06-06 07:26:01'),
(19, 1, 'Login ke Portal Admin CBT', '2026-06-06 09:10:17'),
(20, 1, 'Logout dari Portal Admin CBT', '2026-06-06 10:12:52'),
(21, 2, 'Login ke Portal Admin CBT', '2026-06-06 13:15:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_ujian`
--

CREATE TABLE `pengaturan_ujian` (
  `id` int(11) NOT NULL,
  `nama_ujian` varchar(100) DEFAULT NULL,
  `mata_pelajaran` varchar(100) DEFAULT NULL,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `pengaturan_ujian`
--

INSERT INTO `pengaturan_ujian` (`id`, `nama_ujian`, `mata_pelajaran`, `waktu_mulai`, `waktu_selesai`) VALUES
(1, 'UUB Semester Genap', 'MTK', '2026-06-04 06:30:00', '2026-06-08 09:00:00'),
(2, 'UUB Semester Genap', 'PAI', '2026-06-08 09:30:00', '2026-06-08 11:00:00'),
(3, 'UUB Semester Genap', 'Bahasa Inggris', '2026-06-09 06:30:00', '2026-06-09 09:00:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `kartu_peserta` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `kelas` varchar(50) DEFAULT NULL,
  `status_ujian` enum('belum','sedang_mengerjakan','selesai') DEFAULT 'belum',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `siswa`
--

INSERT INTO `siswa` (`id`, `kartu_peserta`, `password`, `nama_siswa`, `kelas`, `status_ujian`, `created_at`, `updated_at`) VALUES
(1, '01.03.0059.001.9-XI', '01.03.0059.001.9-XI', 'Ahmad Mukhid Munzadi', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(2, '01.03.0059.001.9-X', '01.03.0059.001.9-X', 'Ahmad Rendy Saputra', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(3, '01.03.0059.002.8-X', '01.03.0059.002.8-X', 'ALFA MONIKA', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(4, '01.03.0059.003.7-X', '01.03.0059.003.7-X', 'Andara Putra', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(5, '01.03.0059.004.6-X', '01.03.0059.004.6-X', 'Bintang Rafi', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(6, '01.03.0059.005.5-X', '01.03.0059.005.5-X', 'Elang Setiawan', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(7, '01.03.0059.006.4-X', '01.03.0059.006.4-X', 'FAIZ RIFAT ARDIANSYAH', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(8, '01.03.0059.007.3-X', '01.03.0059.007.3-X', 'Inayah Nafisah', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(9, '01.03.0059.008.2-X', '01.03.0059.008.2-X', 'Kanaya', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(10, '01.03.0059.009.9-X', '01.03.0059.009.9-X', 'Muhamad Fahtir', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(11, '01.03.0059.010.8-X', '01.03.0059.010.8-X', 'MUHAMAD FIRMANSYAH', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(12, '01.03.0059.011.7-X', '01.03.0059.011.7-X', 'Muhamad Rafka Ibrahim', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(13, '01.03.0059.012.6-X', '01.03.0059.012.6-X', 'Muhammad Haikal Rizqi', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(14, '01.03.0059.013.5-X', '01.03.0059.013.5-X', 'Muhammad Nurfajar Aryadillah', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(15, '01.03.0059.014.4-X', '01.03.0059.014.4-X', 'Nadira Shiren Arsifa', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(16, '01.03.0059.015.3-X', '01.03.0059.015.3-X', 'Nikita Nabila Hartandi', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(17, '01.03.0059.016.2-X', '01.03.0059.016.2-X', 'Raihan Noval Safei', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(18, '01.03.0059.017.9-X', '01.03.0059.017.9-X', 'Ridwan Al Hakim', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(19, '01.03.0059.018.8-X', '01.03.0059.018.8-X', 'Rival Firdaus', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(20, '01.03.0059.019.7-X', '01.03.0059.019.7-X', 'SAFA RISMAWATI', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(21, '01.03.0059.020.6-X', '01.03.0059.020.6-X', 'Salsa Novita', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(22, '01.03.0059.021.5-X', '01.03.0059.021.5-X', 'Saskia Yulianti', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(23, '01.03.0059.022.4-X', '01.03.0059.022.4-X', 'SHABYLA ALWAHYUDI', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(24, '01.03.0059.023.3-X', '01.03.0059.023.3-X', 'SYAHVIRA AZZAHRA ZEIN', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(25, '01.03.0059.024.2-X', '01.03.0059.024.2-X', 'Vina Oktavia', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(26, '01.03.0059.025.9-X', '01.03.0059.025.9-X', 'Zahrotul Sita', 'X AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(27, '01.03.0059.026.8-X', '01.03.0059.026.8-X', 'AIRA', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(28, '01.03.0059.027.7-X', '01.03.0059.027.7-X', 'AMANDA MAHARANI', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(29, '01.03.0059.028.6-X', '01.03.0059.028.6-X', 'Andini Rahmah Wati', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(30, '01.03.0059.029.5-X', '01.03.0059.029.5-X', 'Arif Rafi Fardhu Rahman', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(31, '01.03.0059.030.4-X', '01.03.0059.030.4-X', 'BULAN LESTARI', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(32, '01.03.0059.031.3-X', '01.03.0059.031.3-X', 'Dinda Kirana', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(33, '01.03.0059.032.2-X', '01.03.0059.032.2-X', 'Dwi Adianto', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(34, '01.03.0059.033.9-X', '01.03.0059.033.9-X', 'Gilang Permana', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(35, '01.03.0059.034.8-X', '01.03.0059.034.8-X', 'Hendry Pratama', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(36, '01.03.0059.035.7-X', '01.03.0059.035.7-X', 'Keysha Az`zhura', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(37, '01.03.0059.036.6-X', '01.03.0059.036.6-X', 'Liana Devi', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(38, '01.03.0059.037.5-X', '01.03.0059.037.5-X', 'MARSHANDA AZAHRA', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(39, '01.03.0059.038.4-X', '01.03.0059.038.4-X', 'Mochamad Farhan', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(40, '01.03.0059.039.3-X', '01.03.0059.039.3-X', 'Muhamad Rifki Ibrahim', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(41, '01.03.0059.040.2-X', '01.03.0059.040.2-X', 'Muhamad Wafa Asqolani', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(42, '01.03.0059.041.9-X', '01.03.0059.041.9-X', 'Muhammad Fahri', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(43, '01.03.0059.042.8-X', '01.03.0059.042.8-X', 'Muhammad Ilham Malid', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(44, '01.03.0059.043.7-X', '01.03.0059.043.7-X', 'MUHAMMAD TEGAR RIFAI', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(45, '01.03.0059.044.6-X', '01.03.0059.044.6-X', 'Nayla Nurdini', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(46, '01.03.0059.045.5-X', '01.03.0059.045.5-X', 'PUTRI RIZKA APRILIYA', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(47, '01.03.0059.046.4-X', '01.03.0059.046.4-X', 'Rangga Syakiil Maulana', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(48, '01.03.0059.047.3-X', '01.03.0059.047.3-X', 'Rifat Ridho Hisyam', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(49, '01.03.0059.048.2-X', '01.03.0059.048.2-X', 'SARTIKA MAHARANI', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(50, '01.03.0059.049.9-X', '01.03.0059.049.9-X', 'Sekar Ayu Pamungkas', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(51, '01.03.0059.050.8-X', '01.03.0059.050.8-X', 'SIAUPEN', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(52, '01.03.0059.051.7-X', '01.03.0059.051.7-X', 'Syifa Fauziah', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(53, '01.03.0059.052.6-X', '01.03.0059.052.6-X', 'Widiya Wati Tria Ningsih', 'X AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(54, '01.03.0059.053.5-X', '01.03.0059.053.5-X', 'Ahmad Danu Muwardi', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(55, '01.03.0059.054.4-X', '01.03.0059.054.4-X', 'Akhma Maulana', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(56, '01.03.0059.055.3-X', '01.03.0059.055.3-X', 'Bagas Putra Baskoro', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(57, '01.03.0059.056.2-X', '01.03.0059.056.2-X', 'Chilla Aulia', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(58, '01.03.0059.057.9-X', '01.03.0059.057.9-X', 'DAFA RAMADAN', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(59, '01.03.0059.058.8-X', '01.03.0059.058.8-X', 'ELSA SAFIRA', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(60, '01.03.0059.059.7-X', '01.03.0059.059.7-X', 'Farhan Gunawan', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(61, '01.03.0059.060.6-X', '01.03.0059.060.6-X', 'Langit Satria Purnomo', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(62, '01.03.0059.061.5-X', '01.03.0059.061.5-X', 'Maelisakh', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(63, '01.03.0059.062.4-X', '01.03.0059.062.4-X', 'Mohammad Fahri', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(64, '01.03.0059.063.3-X', '01.03.0059.063.3-X', 'Muhamad Fatur Rahman', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(65, '01.03.0059.064.2-X', '01.03.0059.064.2-X', 'Muhamad Soleh', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(66, '01.03.0059.065.9-X', '01.03.0059.065.9-X', 'Muhammad Hafiz', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(67, '01.03.0059.066.8-X', '01.03.0059.066.8-X', 'Muhammad Rasya Saepudin', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(68, '01.03.0059.067.7-X', '01.03.0059.067.7-X', 'Myla Saputri', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(69, '01.03.0059.068.6-X', '01.03.0059.068.6-X', 'NAURAH SAHDA', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(70, '01.03.0059.069.5-X', '01.03.0059.069.5-X', 'Rafli Amar Fadilah', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(71, '01.03.0059.070.4-X', '01.03.0059.070.4-X', 'REGAN AUROLA', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(72, '01.03.0059.071.3-X', '01.03.0059.071.3-X', 'Reno Febriano', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(73, '01.03.0059.072.2-X', '01.03.0059.072.2-X', 'Sahdan Futuh Nurohman', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(74, '01.03.0059.073.9-X', '01.03.0059.073.9-X', 'SARNAH', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(75, '01.03.0059.074.8-X', '01.03.0059.074.8-X', 'SEFON', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(76, '01.03.0059.075.7-X', '01.03.0059.075.7-X', 'Silvia Khairunnissa', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(77, '01.03.0059.076.6-X', '01.03.0059.076.6-X', 'SYIFA JULIANTI', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(78, '01.03.0059.077.5-X', '01.03.0059.077.5-X', 'Yuliyana Ramadani', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(79, '01.03.0059.078.4-X', '01.03.0059.078.4-X', 'Zeriko Mohammet', 'X AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(80, '01.03.0059.002.8-XI', '01.03.0059.002.8-XI', 'Alfan Maulana', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(81, '01.03.0059.003.7-XI', '01.03.0059.003.7-XI', 'Alfi Syarifatul Febrian', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(82, '01.03.0059.004.6-XI', '01.03.0059.004.6-XI', 'Alissa Febrianti', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(83, '01.03.0059.005.5-XI', '01.03.0059.005.5-XI', 'Allinda Anggraeni', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(84, '01.03.0059.006.4-XI', '01.03.0059.006.4-XI', 'An Naafi', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(85, '01.03.0059.007.3-XI', '01.03.0059.007.3-XI', 'Atha Rasyid Rizqi', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(86, '01.03.0059.008.2-XI', '01.03.0059.008.2-XI', 'Claudia Ramadhani', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(87, '01.03.0059.009.9-XI', '01.03.0059.009.9-XI', 'Daniella Moy Vica', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(88, '01.03.0059.010.8-XI', '01.03.0059.010.8-XI', 'Deby Anggraeni', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(89, '01.03.0059.011.7-XI', '01.03.0059.011.7-XI', 'Dinda Nawang Wulan', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(90, '01.03.0059.012.6-XI', '01.03.0059.012.6-XI', 'Elvirly Nanda Alqajaya', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(91, '01.03.0059.013.5-XI', '01.03.0059.013.5-XI', 'Fauziah Setiawan', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(92, '01.03.0059.014.4-XI', '01.03.0059.014.4-XI', 'Juana Wangsa Putri', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(93, '01.03.0059.015.3-XI', '01.03.0059.015.3-XI', 'Muhamad Farel', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(94, '01.03.0059.016.2-XI', '01.03.0059.016.2-XI', 'Muhammad Daffa Eryadi', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(95, '01.03.0059.017.9-XI', '01.03.0059.017.9-XI', 'Nurhalimah Ramadhani', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(96, '01.03.0059.018.8-XI', '01.03.0059.018.8-XI', 'Priyanti Kuwat', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(97, '01.03.0059.019.7-XI', '01.03.0059.019.7-XI', 'Sandi Prayoga', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(98, '01.03.0059.020.6-XI', '01.03.0059.020.6-XI', 'Siti Hartini', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(99, '01.03.0059.021.5-XI', '01.03.0059.021.5-XI', 'Surya Anggara', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(100, '01.03.0059.022.4-XI', '01.03.0059.022.4-XI', 'Syahrul Ramdhan Hasbullah', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(101, '01.03.0059.023.3-XI', '01.03.0059.023.3-XI', 'Syifa Khairunisa', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(102, '01.03.0059.024.2-XI', '01.03.0059.024.2-XI', 'Taufiqqurahman', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(103, '01.03.0059.025.9-XI', '01.03.0059.025.9-XI', 'Vincent Ary Prasetyo', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(104, '01.03.0059.026.8-XI', '01.03.0059.026.8-XI', 'Zahir AliFahman', 'XI AK 1', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(105, '01.03.0059.051.7-XI', '01.03.0059.051.7-XI', 'Abianansyah', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(106, '01.03.0059.028.6-XI', '01.03.0059.028.6-XI', 'Agesta Salsabila', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(107, '01.03.0059.029.5-XI', '01.03.0059.029.5-XI', 'Alfridho Tri Gustomo', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(108, '01.03.0059.030.4-XI', '01.03.0059.030.4-XI', 'Allisa Silvariani', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(109, '01.03.0059.031.3-XI', '01.03.0059.031.3-XI', 'Anggita Rizqi Aulia', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(110, '01.03.0059.032.2-XI', '01.03.0059.032.2-XI', 'Arfah Maritza', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(111, '01.03.0059.027.7-XI', '01.03.0059.027.7-XI', 'Arya Alfarizky Laksono', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(112, '01.03.0059.033.9-XI', '01.03.0059.033.9-XI', 'Bayu Nugroho', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(113, '01.03.0059.034.8-XI', '01.03.0059.034.8-XI', 'Dichardo Febrio', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(114, '01.03.0059.035.7-XI', '01.03.0059.035.7-XI', 'Dwi Alyssa Putri', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(115, '01.03.0059.036.6-XI', '01.03.0059.036.6-XI', 'Ervirnia Cinta Aulya', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(116, '01.03.0059.037.5-XI', '01.03.0059.037.5-XI', 'Khairul Rofiko Yasri', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(117, '01.03.0059.038.4-XI', '01.03.0059.038.4-XI', 'Lista Rosdianti', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(118, '01.03.0059.039.3-XI', '01.03.0059.039.3-XI', 'Lukman Nul Hakim', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(119, '01.03.0059.040.2-XI', '01.03.0059.040.2-XI', 'Melvia Satifani', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(120, '01.03.0059.041.9-XI', '01.03.0059.041.9-XI', 'Mhd Ardiansyah', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(121, '01.03.0059.042.8-XI', '01.03.0059.042.8-XI', 'Muhamad Roihan Nashirudin', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(122, '01.03.0059.043.7-XI', '01.03.0059.043.7-XI', 'Muhammad Fahri Rizky Fadilah', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(123, '01.03.0059.044.6-XI', '01.03.0059.044.6-XI', 'Qiana Amaradhina', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(124, '01.03.0059.045.5-XI', '01.03.0059.045.5-XI', 'Safira', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(125, '01.03.0059.046.4-XI', '01.03.0059.046.4-XI', 'Sri Fatimah', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(126, '01.03.0059.047.3-XI', '01.03.0059.047.3-XI', 'Syifa Aulia', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(127, '01.03.0059.048.2-XI', '01.03.0059.048.2-XI', 'Vina Yuniastuti', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(128, '01.03.0059.049.9-XI', '01.03.0059.049.9-XI', 'Zahratu Sita', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(129, '01.03.0059.050.8-XI', '01.03.0059.050.8-XI', 'Zibran Asidik', 'XI AK 2', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(130, '01.03.0059.052.6-XI', '01.03.0059.052.6-XI', 'Adel Melya Saputri', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(131, '01.03.0059.053.7-XI', '01.03.0059.053.7-XI', 'Agustina Safitri', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(132, '01.03.0059.054.6-XI', '01.03.0059.054.6-XI', 'Aisya Safitri', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(133, '01.03.0059.055.5-XI', '01.03.0059.055.5-XI', 'Ardy Tri Ansyah', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(134, '01.03.0059.056.4-XI', '01.03.0059.056.4-XI', 'Arya Sena Nugraha', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(135, '01.03.0059.057.3-XI', '01.03.0059.057.3-XI', 'Axzara Rahmawati', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(136, '01.03.0059.058.2-XI', '01.03.0059.058.2-XI', 'Devi Nurhayati', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(137, '01.03.0059.059.9-XI', '01.03.0059.059.9-XI', 'Gilang Al Farit Ramadhan', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(138, '01.03.0059.060.8-XI', '01.03.0059.060.8-XI', 'Habibie Shyafwan', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(139, '01.03.0059.061.7-XI', '01.03.0059.061.7-XI', 'Khopifah', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(140, '01.03.0059.062.6-XI', '01.03.0059.062.6-XI', 'Marcelino Andriansyah', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(141, '01.03.0059.063.5-XI', '01.03.0059.063.5-XI', 'Maulida Ulfatun Nisa', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(142, '01.03.0059.064.4-XI', '01.03.0059.064.4-XI', 'Muhamad Fahri', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(143, '01.03.0059.065.3-XI', '01.03.0059.065.3-XI', 'Muhammad Daffa Pratama', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(144, '01.03.0059.066.2-XI', '01.03.0059.066.2-XI', 'Nila Nadila', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(145, '01.03.0059.067.9-XI', '01.03.0059.067.9-XI', 'Radit Budiansyah', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(146, '01.03.0059.068.8-XI', '01.03.0059.068.8-XI', 'Riska Maulida', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(147, '01.03.0059.069.7-XI', '01.03.0059.069.7-XI', 'Sarifah', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(148, '01.03.0059.070.6-XI', '01.03.0059.070.6-XI', 'Shaskia Driscika Halmaidha', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(149, '01.03.0059.071.5-XI', '01.03.0059.071.5-XI', 'Shifa Aulia Putri', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(150, '01.03.0059.072.4-XI', '01.03.0059.072.4-XI', 'Sindy', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(151, '01.03.0059.073.3-XI', '01.03.0059.073.3-XI', 'Yoga Maulana Jaya', 'XI AK 3', 'belum', '2026-06-04 18:28:14', '2026-06-04 18:28:14'),
(152, '12001', 'siswa123', 'Testing Siswa', 'XI-TEST-1', 'selesai', '2026-06-04 18:34:57', '2026-06-05 08:49:21'),
(153, '12002', 'siswa123', 'Lenong', 'X-AK-2', 'selesai', '2026-06-05 11:24:32', '2026-06-05 11:34:54');

-- --------------------------------------------------------

--
-- Struktur dari tabel `soal`
--

CREATE TABLE `soal` (
  `id` int(11) NOT NULL,
  `mata_pelajaran` varchar(100) DEFAULT NULL,
  `kelas` enum('X','XI') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  `pertanyaan` text NOT NULL,
  `opsi_a` text NOT NULL,
  `gambar_a` varchar(255) DEFAULT NULL,
  `opsi_b` text NOT NULL,
  `gambar_b` varchar(255) DEFAULT NULL,
  `opsi_c` text NOT NULL,
  `gambar_c` varchar(255) DEFAULT NULL,
  `opsi_d` text NOT NULL,
  `gambar_d` varchar(255) DEFAULT NULL,
  `opsi_e` text NOT NULL,
  `gambar_e` varchar(255) DEFAULT NULL,
  `kunci_jawaban` enum('A','B','C','D','E') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `soal`
--

INSERT INTO `soal` (`id`, `mata_pelajaran`, `kelas`, `deskripsi`, `gambar`, `pertanyaan`, `opsi_a`, `gambar_a`, `opsi_b`, `gambar_b`, `opsi_c`, `gambar_c`, `opsi_d`, `gambar_d`, `opsi_e`, `gambar_e`, `kunci_jawaban`) VALUES
(10, 'MTK', 'X', '', '', 'Diketahui sebuah barisan bilangan: 20, 14, 8, 2, −4, … . Nilai suku pertama dan beda barisan tersebut adalah ….', '𝑎 = 20 dan 𝑏 = 6', '', '𝑎 = 20 dan 𝑏 = −6', '', '𝑎 = −4 dan 𝑏 = 6', '', '𝑎 = 2 dan 𝑏 = −6', '', '𝑎 = −6 dan 𝑏 = 20', '', 'B'),
(11, 'MTK', 'X', '', '', 'Diketahui suku ke-3 dari sebuah barisan aritmatika adalah 11, sedangkan suku ke-5 dari barisan tersebut adalah 19. Nilai dari suku ke-6 barisan tersebut adalah ….', '21', '', '22', '', '23', '', '24', '', '25', '', 'C'),
(12, 'MTK', 'X', '', '', 'Diberikan sebuah barisan aritmatika: 3, 7, 11, 15, …, 83. Diketahui banyak suku pada barisan tersebut adalah ganjil. Suku tengah (𝑈𝑡) dari barisan tersebut adalah ….', '40', '', '41', '', '42', '', '43', '', '44', '', 'D'),
(13, 'MTK', 'X', '', '', 'Sebuah gedung pertemuan di sekolah sedang ditata untuk acara kelulusan. Panitia mengatur kursi dengan pola aritmatika: baris pertama (kursi depan) berisi 12 kursi, baris kedua berisi 15 kursi, baris ketiga berisi 18 kursi, dan seterusnya selalu bertambah secara konstan. Karena keterbatasan lebar gedung, panitia menetapkan aturan jumlah kursi pada baris terakhir (paling belakang) tidak boleh lebih dari 45 kursi agar tidak mengganggu jalur evakuasi di sisi kanan dan kiri gedung. Berdasarkan analisis aturan tersebut, berapa kapasitas maksimal total kursi yang dapat ditampung di dalam gedung tersebut?', '312 kursi', '', '342 kursi', '', '370 kursi', '', '624 kursi', '', '685 kursi', '', 'B'),
(14, 'MTK', 'X', '', '', 'Sebuah aula pertemuan sedang ditata untuk acara seminar. Panitia menetapkan bahwa baris paling depan berisi 15 kursi. Karena keterbatasan ruang, penataan dihentikan tepat pada baris ke-12 yang berisi 70 kursi. Jika perubahan jumlah kursi dari baris depan ke belakang selalu konstan. Maka penambahan jumlah kursi pada setiap baris di belakangnya adalah ….', '2 baris', '', '3 baris', '', '4 baris', '', '5 baris', '', '6 baris', '', 'D'),
(15, 'MTK', 'X', '', '', 'Diketahui suku pertama dari suatu barisan geometri adalah 4 dan suku ke-4 adalah 32. Suku ke- 6 dari barisan geometri tersebut adalah ….', '64', '', '80', '', '88', '', '110', '', '128', '', 'E'),
(16, 'MTK', 'X', '', 'soal_utama_1780669028.png', ' ', '210', '', '220', '', '230', '', '420', '', '560', '', 'B'),
(17, 'MTK', 'X', '', '', 'Seorang lulusan SMK membuka usaha bengkel sepeda motor. Pada bulan pertama, ia berhasil mengumpulkan keuntungan bersih sebesar Rp. 1.200.000,00. Seiring bertambahnya pelanggan, keuntungan bersihnya meningkat secara konstan setiap bulan sebesar Rp. 150.000,00. Usaha tersebut berjalan lancar, namun pada bulan ke-10, pemilik usaha harus membayar biaya sewa tempat tahunan sebesar Rp. 12.000.000,00 yang diambil langsung dari total seluruh tabungan keuntungan bersihnya dari bulan pertama. Maka sisa uang tabungan keuntungan dari total pendapatan 10 bulan pertama setelah digunakan untuk membayar sewa tempat adalah ….', 'Rp. 1.000.000,00', '', 'Rp. 1.750.000,00', '', 'Rp. 2.000.000,00', '', 'Rp. 3.000.000,00', '', 'Rp. 3.750.000,00', '', 'E'),
(18, 'MTK', 'X', '', '', 'Diberikan sebuah deret geometri tak hingga sebagai berikut: 16 + 8 + 4 + 2 + … . Jumlah tak hingga dari deret angka tersebut adalah ….', '29', '', '30', '', '31', '', '32', '', '33', '', 'C'),
(19, 'MTK', 'X', '', '', 'Seorang murid SMK jurusan Bisnis Daring dan Pemasaran (BDP) sedang mempraktikkan strategi affiliate marketing di media sosial untuk mempromosikan sebuah produk lokal. Pada minggu pertama, ia berhasil mengajak 6 orang untuk membeli produk tersebut. Karena konten promosi yang ia buat menjadi viral, jumlah pembeli baru meningkat secara eksponensial dengan pola geometri yang konstan. Pada minggu ketiga, tercatat ada 24 orang pembeli baru. Sesuai kontrak kerja sama dengan pemilik produk, murid tersebut akan mendapatkan bonus khusus jika total akumulasi penjualan dari minggu pertama hingga minggu keenam mencapai minimal 350 produk. Berdasarkan wacana tersebut, lakukanlah analisis terhadap perkembangan target penjualan murid tersebut! Pernayataan yang tepat untuk menggambarkan kondisi pencapaian targetnya pada akhir minggu keenam adalah ….', 'Target tidak tercapai karena total akumulasi penjualan hanya mencapai 186 produk.', '', 'Target tidak tercapai karena total akumulasi penjualan hanya mencapai 252 produk.', '', 'Target tepat sasaran karena total akumulasi penjualan mencapai tepat 350 produk.', '', 'Target berhasil melampaui sasaran karena total akumulasi penjualan mencapai 378 produk.', '', 'Target berhasil melampaui sasaran karena total akumulasi penjualan mencapai 756 produk.', '', 'D'),
(20, 'MTK', 'X', '', '', 'Untuk mendukung kelancaran usaha, seorang lulusan SMK jurusan Desain Komunikasi Visual (DKV) memutuskan membeli sebuah komputer grafis profesional seharga Rp15.000.000,00 melalui sistem kredit. Ia membayar uang muka (down payment) sebesar Rp3.000.000,00, dan sisanya akan dicicil selama 2 tahun. Pihak penyedia kredit menerapkan sistem bunga tunggal sebesar 10% per tahun untuk sisa pembayaran tersebut. Besar cicilan yang harus dibayarkan oleh desainer tersebut setiap bulannya adalah ….', 'Rp. 600.000,00', '', 'Rp. 640.000,00', '', 'Rp. 700.000,00', '', 'Rp. 710.000,00', '', 'Rp. 780.000,00', '', 'A'),
(21, 'MTK', 'X', '', '', 'Seorang lulusan SMK mendapatkan bonus kelulusan dari orang tuanya sebesar Rp10.000.000,00. Ia berencana menyimpan uang tersebut untuk modal usaha di masa depan. Ia memiliki dua pilihan tempat menyimpan uang dengan sistem bunga tunggal:<br />\r\n-	Bank A menawarkan bunga tunggal sebesar 6% per tahun.<br />\r\n-	Koperasi B menawarkan bunga tunggal sebesar 8 % per tahun<br />\r\nJika ia memutuskan untuk menyimpan uang tersebut selama tepat 3 tahun, selisih total bunga uang yang akan ia terima antara Bank A dan Koperasi B adalah ….<br />\r\n', 'Rp. 200.000,00', '', 'Rp. 500.000,00', '', 'Rp. 600.000,00', '', 'Rp. 850.000,00', '', 'Rp. 2.400.000,00', '', 'C'),
(22, 'MTK', 'X', '', '', 'Seorang lulusan SMK jurusan Tata Boga berencana membuka usaha katering kecil-kecilan. Ia membutuhkan modal sebesar Rp10.000.000,00. Saat ini, ia baru memiliki uang sebesar Rp8.000.000,00. Untuk menambah kekurangannya, ia menyimpan uang tersebut di sebuah bank yang menawarkan sistem bunga tunggal sebesar 5% per tahun. Berapa lama (dalam tahun) lulusan SMK tersebut harus menabung di bank agar total uang tabungannya menjadi tepat Rp. 10.000.000,00 ?', '8 Tahun', '', '7 Tahun', '', '6 Tahun', '', '5 Tahun', '', '4 tahun', '', 'E'),
(23, 'MTK', 'X', '', '', 'Seorang siswa menabung modal awal sebesar Rp1.000.000,00 di sebuah bank yang menerapkan sistem bunga tunggal. Setelah tepat 1 tahun, ia mengambil seluruh uangnya dan mendapati modal akhirnya telah bertambah menjadi Rp1.080.000,00. Suku bunga per tahun yang diberikan oleh bank tersebut adalah .…', '5%', '', '6%', '', '7%', '', '8%', '', '9%', '', 'D'),
(24, 'MTK', 'X', '', 'soal_utama_1780669628.png', ' ', 'Rp. 7.320.500,00', '', 'Rp. 6.655.000,00', '', 'Rp. 6.600.000,00', '', 'Rp. 6.500.000,00', '', 'Rp. 5.500.000,00', '', 'B'),
(25, 'MTK', 'X', '', 'soal_utama_1780669726.png', ' ', 'Rp. 8.400.000,00', '', 'Rp. 8.800.000,00', '', 'Rp. 8.820.000,00', '', 'Rp. 9.261.000,00', '', 'Rp. 9.720.000,00', '', 'C'),
(26, 'MTK', 'X', '', '', 'Seorang ingin menginvestasikan uangnya dengan modal awal sebesar Rp. 2.000.000,00 di sebuah platform pendanaan UMKM resmi. Investasi tersebut menerapkan sistem bunga majemuk tahunan. Setelah waktu 2 tahun, investasi tersebut dicairkan seluruhnya dan modalnya telah berkembang menjadi sebesar Rp2.420.000,00. Persentase suku bunga majemuk pertahun yang telah ditetapkan oleh platform pendanaan tersebut adalah …. (petunjuk pengerjaan:<br />\r\n√1,21 = 1,1)', '10%', '', '11%', '', '20%', '', '27%', '', '30%', '', 'A'),
(27, 'MTK', 'X', '', 'soal_utama_1780670251.png', ' ', 'Rp. 8.500.000,00', '', 'Rp. 9.000.000,00', '', 'Rp. 9.200.000,00', '', 'Rp. 9.500.000,00', '', 'Rp. 9.600.000,00', '', 'E'),
(28, 'MTK', 'X', '', '', 'Seseorang akan segera melunasi pinjamannya dengan menerapkan sistem Anuitas bulanan. Jika besar angsuran Rp. 120.000,00 dan bunganya sebesar Rp. 300.000,00, maka besar anuitas pinjaman tersebut sebesar ….', 'Rp. 360.000,00', '', 'RP. 420.000,00', '', 'Rp. 450.000,00', '', 'Rp. 500.000,00', '', 'Rp. 510.000,00', '', 'B'),
(29, 'MTK', 'X', 'Seorang pengrajin sepatu kulit mengajukan pinjaman modal ke koperasi simpan pinjam untuk membeli mesin jahit otomatis. Pinjaman tersebut dilunasi dengan sistem anuitas bulanan dengan suku bunga 1% per bulan. Berikut adalah tabel rencana pelunasannya yang datanya belum lengkap :', 'soal_utama_1780668679.png', 'Besarnya anuitas adalah ...', 'Rp. 1.330.000,00', '', 'Rp. 1.450.000,00', '', 'Rp. 1.540.000,00', '', 'Rp. 1.660.000,00', '', 'Rp. 2.000.000,00', '', 'A'),
(30, 'MTK', 'X', 'Perhatikan grafik fungsi kuadrat di bawah ini.', 'soal_utama_1780498754.png', 'Nilai 𝑎 dari grafik fungsi kuadrat di atas adalah ….', '-1', '', '1', '', '2', '', '3', '', '5', '', 'E'),
(31, 'MTK', 'X', 'Perhatikan grafik fungsi kuadrat di bawah ini.', 'soal_utama_1780499216.png', 'Sebuah parabola yang terbuka ke bawah, memotong sumbu X di titik (-1, 0) dan (5,0), serta memiliki titik puncak/titik balik maksimum pada koordinat (2,9). Berdasarkan grafik fungsi kuadrat di atas, nilai maksimum dari fungsi tersebut adalah ….', '-1', '', '3', '', '9', '', '10', '', '15', '', 'C'),
(32, 'MTK', 'X', 'Perhatikan grafik fungsi kuadrat di bawah ini.', 'soal_utama_1780500448.jpg', 'Nilai maksimum dari fungsi kuadrat 16 − 6𝑥 − 𝑥² di atas adalah ….', '25', '', '20', '', '19', '', '18', '', '-20', '', 'A'),
(33, 'MTK', 'X', '', '', 'Titik puncak dari 𝑓(𝑥) = 𝑥² + 3𝑥 + 2 adalah ….', '', 'opsi_a_1780500981_68.png', '', 'opsi_b_1780500981_25.png', '', 'opsi_c_1780500981_28.png', '', 'opsi_d_1780500981_90.png', '', 'opsi_e_1780670576_43.png', 'D'),
(34, 'MTK', 'X', '', '', 'Persamaan garis sumbu simetri dari fungsi kuadrat 𝑓(𝑥) = 𝑥² + 6𝑥 + 8 adalah .…', '-3', '', '-2', '', '2', '', '3', '', '6', '', 'A'),
(35, 'MTK', 'X', 'Perhatikan grafik fungsi kuadrat di bawah ini.', 'soal_utama_1780670752.png', 'Persamaan grafik fungsi kuadrat pada gambar di atas adalah ….', '𝑦 = −2² + 4𝑥 + 3', '', '𝑦 = −2² + 4𝑥 + 2', '', '𝑦 = −2² + 2𝑥 + 3', '', '𝑦 = −2² + 4𝑥 − 6', '', '𝑦 = −2² + 2𝑥 − 5', '', 'C'),
(36, 'MTK', 'X', '', '', 'Akar-akar persamaan kuadrat 𝑥² − 5𝑥 + 6 = 0 adalah ....', '1 atau 6', '', '2 atau 3', '', '-2 atau -3', '', '3 atau 5', '', '-1 atau -6', '', 'B'),
(37, 'MTK', 'X', '', '', 'Akar-akar persamaan kuadrat 4𝑥² + 4𝑥 − 3 = 0 adalah ....', '', 'opsi_a_1780633148_58.png', '', 'opsi_b_1780633148_19.png', '', 'opsi_c_1780633148_17.png', '', 'opsi_d_1780633148_86.png', '', 'opsi_e_1780633148_70.png', 'D'),
(38, 'MTK', 'X', '', '', 'Diketahui persamaan kuadrat 𝑥² − 2𝑥 − 2𝑎 + 5 = 0 mempunyai dua akar sama (kembar). Nilai a yang memenuhi adalah ....', '-2', '', '-1', '', '0', '', '1', '', '2', '', 'E'),
(39, 'MTK', 'X', '', 'soal_utama_1780671462.png', ' ', '', 'opsi_a_1780501342_47.png', '', 'opsi_b_1780501342_92.png', '', 'opsi_c_1780501342_13.png', '', 'opsi_d_1780501342_25.png', '', 'opsi_e_1780501342_45.png', 'A'),
(40, 'MTK', 'X', 'Perhatikan gambar berikut.', 'soal_utama_1780501428.jpg', 'Berdasarkan gambar diatas, nilai Sin A adalah ....', '', 'opsi_a_1780633391_58.png', '', 'opsi_b_1780633391_66.png', '', 'opsi_c_1780633391_24.png', '', 'opsi_d_1780633391_77.png', '', 'opsi_e_1780633391_40.png', 'A'),
(41, 'MTK', 'X', '', '', 'Diketahui segitiga siku-siku PQR dengan sudut siku-siku di Q. Jika ditinjau dari sudut P, maka sisi QR adalah ….', 'sisi miring', '', 'sisi samping', '', 'sisi depan', '', 'sisi alas', '', 'sisi tegak', '', 'C'),
(42, 'MTK', 'X', '', '', 'Sudut 220° jika diubah ke dalam satuan radian adalah ....', '', 'opsi_a_1780633599_69.png', '', 'opsi_b_1780633599_89.png', '', 'opsi_c_1780633599_12.png', '', 'opsi_d_1780633599_30.png', '', 'opsi_e_1780633599_51.png', 'C'),
(43, 'MTK', 'X', 'Diketahui segitiga siku-siku ABC seperti pada gambar berikut.', 'soal_utama_1780501638.jpg', 'Panjang sisi AC adalah ....', '4√2 𝑐𝑚', '', '4√3 𝑐𝑚', '', '6√2 𝑐𝑚', '', '6√3 𝑐𝑚', '', '16 𝑐𝑚', '', 'D'),
(44, 'MTK', 'X', '', '', 'Seorang pekerja bangunan akan mengecet dinding bagian atas rumah dengan ketinggian 6 meter. Pekerja tersebut menggunakan tangga yang disandarkan tepat pada ujung tembok dan kemiringan tangga 60°. Tinggi tangga yang digunakan adalah ....', '4√2 𝑚', '', '4√3 𝑚', '', '6√2 𝑚', '', '6√3 𝑚', '', '8√3 𝑚', '', 'B'),
(45, 'MTK', 'X', '', 'soal_utama_1780671650.png', ' ', '', 'opsi_a_1780634156_88.png', '', 'opsi_b_1780634156_46.png', '', 'opsi_c_1780634156_78.png', '', 'opsi_d_1780634156_32.png', '', 'opsi_e_1780634156_97.png', 'C'),
(46, 'MTK', 'X', '', 'soal_utama_1780671844.png', ' ', '', 'opsi_a_1780634215_77.png', '', 'opsi_b_1780634215_84.png', '', 'opsi_c_1780634215_78.png', '', 'opsi_d_1780634215_41.png', '', 'opsi_e_1780634215_11.png', 'B'),
(47, 'MTK', 'X', '', '', 'Diketahui segitiga siku-siku di B dengan panjang AC = 12 cm dan 𝐵𝐶 = 6√2 cm. Besar sudut<i> ACB </i> adalah ....', '30°', '', '37°', '', '45°', '', '60°', '', '90°', '', 'C'),
(48, 'MTK', 'X', '', '', 'Siswa yang sedang berlibur ke suatu pantai melihat sebuah menara. Jarak menara dengan dirinya adalah 120 m. Ujung menara terlihat dengan sudut elevasi 60° dari titik pengamatan. Tinggi menara tersebut adalah ....', '60√2 𝑚', '', '60√3 𝑚', '', '120 𝑚', '', '120√2 𝑚', '', '120√2 𝑚', '', 'E'),
(49, 'MTK', 'X', '', '', 'Seorang anak yang berdiri di lantai tiga suatu sekolah melihat satu mobil yang sedang berparkir di halaman sekolah. Jarak mobil ke gedung sekolah tepat di bawah anak berdiri sekitar 5√3 𝑚. Jika anak tersebut melihat mobil dengan sudut depresi 60°, perkiraan tinggi setiap lantai sekolah tersebut adalah ....', '5 m', '', '6 m', '', '8 m', '', '9 m ', '', '15 m', '', 'A'),
(50, 'PAI', 'X', '', 'soal_utama_1780556743.png', '.', 'A.	Alif Lam Syamsiyah, Mad Thabi&#039;i, dan Ikhfa  ', '', 'Alif Lam Qomariyah, Mad Badal, dan Idgham', '', 'Alif Lam Qomariyah, Mad Wajib Muttasil, dan Idzhar', '', 'Alif Lam Syamsiyah, Mad Jaiz Munfasil, dan Ghunnah ', '', 'Alif Lam Syamsiyah, Mad Arid Lissukun, dan Qalqalah', '', 'D'),
(51, 'PAI', 'X', '', 'soal_utama_1780557541.png', 'Berdasarkan ayat di atas, isi kandungan yang tepat tentang larangan perbuatan zina adalah... .', 'Zina adalah perbuatan yang diperbolehkan asal dilakukan atas dasar suka sama suka   ', '', 'Siksaan bagi pezina adalah dicambuk sebanyak seratus kali di hadapan orang mukmin', '', 'Larangan dalam ayat tersebut hanya berlaku bagi orang yang sudah menikah (zina muhsan)', '', 'Zina merupakan perbuatan keji, namun masih ada jalan keluar yang baik selain menjauhinya', '', 'Menghindari segala bentuk pergaulan yang mendekati zina lebih utama daripada mengobati dampaknya.', '', 'E'),
(52, 'PAI', 'X', '', 'soal_utama_1780557824.png', 'Berdasarkan tabel di atas, contoh perilaku yang paling tepat dalam upaya &quot;tidak mendekati zina&quot; dalam kehidupan sehari-hari adalah... .', 'menasihati teman agar segera bertaubat setelah melakukan kemaksiatan.', '', 'memberikan bantuan materi kepada fakir miskin di lingkungan sekitar', '', 'melakukan mediasi terhadap teman yang sedang berselisih paham', '', 'mempelajari tata cara pernikahan yang sah menurut syariat Islam', '', 'menghindari berduaan (khalwat) dengan lawan jenis di tempat sepi.', '', 'E'),
(53, 'PAI', 'X', '', 'soal_utama_1780557994.png', '.', 'seratus kali dera ', '', 'tujuh puluh kali dera', '', ' delapan puluh kali dera', '', ' seratus delapan puluh kali dera ', '', 'pezina perempuan dan pezina laki-laki', '', 'A'),
(54, 'PAI', 'X', '', 'soal_utama_1780558252.png', 'Ayat di atas menegaskan bahwa zina adalah perbuatan keji dan jalan yang buruk. Salah satu hikmah terbesar bagi seseorang yang menjauhi segala pintu menuju zina sesuai ayat tersebut adalah... .', 'Menjaga kemurnian nasab (keturunan) dan kehormatan diri. ', '', 'Meningkatkan status sosial di mata masyarakat luas.', '', 'Mempercepat datangnya rezeki yang bersifat materi. ', '', 'Mendapatkan pujian sebagai orang yang paling suci. ', '', 'Terhindar dari beban tugas belajar yang berat.', '', 'A'),
(55, 'PAI', 'X', '', '', 'Di era digital saat ini, penggunaan aplikasi kencan dan media sosial sering kali memfasilitasi interaksi tanpa batas antara laki-laki dan perempuan. Banyak remaja menganggap bahwa saling mengirim pesan romantis atau bertemu berduaan di tempat sepi adalah hal yang lumrah selama tidak melakukan hubungan seksual.<br />\r\nJika dianalisis berdasarkan Q.S Al-Isra/17:32, perilaku tersebut termasuk kategori... <br />\r\n', 'diperbolehkan karena belum terjadi perbuatan zina secara fisik. ', '', 'perilaku yang harus didukung sebagai bentuk modernisasi pergaulan. ', '', 'perbuatan yang dimaafkan selama pelakunya masih dalam usia remaja. ', '', 'pelanggaran terhadap larangan &quot;Wala taqrabu&quot; karena mendekati zina. ', '', 'Hal yang wajar selama kedua belah pihak mampu menjaga kehormatan.', '', 'D'),
(56, 'PAI', 'X', '', 'soal_utama_1780559040.png', 'Perintah agar hukuman zina disaksikan oleh sebagian orang-orang mukmin menunjukkan bahwa salah satu akibat besar bagi pelaku zina adalah... .', 'pelaku akan mendapatkan perlindungan hukum dari negara lain. ', '', 'menghilangkan hak waris pelaku secara permanen dari keluarganya.', '', 'memudahkan pelaku untuk segera menikah dengan pasangan zinanya.', '', 'terjadinya proses pembersihan dosa secara otomatis tanpa perlu bertaubat.', '', ' dampak psikologis berupa rasa malu sebagai bentuk pelajaran bagi orang lain.', '', 'E'),
(57, 'PAI', 'X', '', 'soal_utama_1780559200.png', 'Arti kata yang dicetak berwarna merah secara berurutan pada ayat di atas adalah... .', 'Maka dera-lah; seratus ', '', 'Maka penjara-lah; seratus', '', ' Maka asingkan-lah; seratus ', '', 'Maka hukum-lah; lima puluh ', '', 'Maka cambuk-lah; delapan puluh', '', 'A'),
(58, 'PAI', 'X', '', 'soal_utama_1780559339.png', 'Kata yang dicetak berwarna merah dalam konteks pergaulan bebas memiliki arti... .', 'Perbuatan yang biasa saja ', '', '  Perbuatan yang keji/buruk 			', '', 'Sesuatu yang nampak keren ', '', 'Jalan yang penuh tantangan', '', 'Kebebasan dalam berekspresi ', '', 'B'),
(59, 'PAI', 'X', '', '', 'Berdasarkan Q.S. an-Nūr/24: 2 memberikan penjelasan tentang hukuman bagi pelaku zina (ghoiru muhshan) yaitu didera 100 kali. Jika kita kaitkan dengan gaya hidup  anak muda saat ini, isi kandungan ayat tersebut adalah... .', 'pentingnya menjaga kehormatan diri karena ada konsekuensi setiap tindakan', '', 'perintah untuk menghakimi orang lain yang berbuat salah secara sepihak  ', '', 'anjuran untuk menikah muda tanpa persiapan mental dan ekonomi  ', '', 'larangan untuk bersosialisasi dengan lawan jenis secara total', '', ' perintah untuk menjauhkan diri dari teknologi informasi', '', 'A'),
(60, 'PAI', 'X', '', 'soal_utama_1780559712.png', 'Pasangan yang paling tepat agar kita tidak salah memahami  isi Al-Qur\'an adalah... .', '1-Z, 2-X, 3-Y 				  				', '', '1-X, 2-Z, 3-Y ', '', '1-Y, 2-X, 3-Z ', '', '1-X, 2-Y, 3-Z', '', '1-Z, 2-Y, 3-X C.	', '', 'B'),
(61, 'PAI', 'X', '', 'soal_utama_1780559940.png', '.', 'A.	Mad Thabi&#039;i 				', '', ' Mad Jaiz Munfasil ', '', 'Idgham Bilagunnah ', '', 'Mad Wajib Muttasil  ', '', 'Alif Lam Qomariyah', '', 'A'),
(62, 'PAI', 'X', '', '', 'Media sosial sekarang penuh dengan flexing gaya hidup bebas yang menjurus ke zina. Sebagai generasi yang cerdas, upaya paling efektif agar tidak terjebak dalam  pergaulan bebas sesuai nilai Al-Qur\'an adalah... .', 'menutup semua akun media sosial selamanya  ', '', 'membatasi diri dalam mencari ilmu pengetahuan umum ', '', ' selalu overthinking terhadap masa depan tanpa melakukan aksi nyata', '', ' mengikuti semua tren yang ada supaya dianggap tidak ketinggalan zaman ', '', ' memperkuat pertemanan positif dan  menyibukkan diri yang bermanfaat ', '', 'E'),
(63, 'PAI', 'X', '', 'soal_utama_1780560293.png', ' Berdasarkan kutipan tersebut, lafaz yang ditulis dengan warna merah mengandung hukum tajwid … .', 'Mad Thabi&#039;i 					 ', '', 'Mad Jaiz Munfasil 				', '', 'Idgham Bilagunnah', '', 'Mad Wajib Muttasil  ', '', 'Alif Lam Qomariyah ', '', 'D'),
(64, 'PAI', 'X', '', 'soal_utama_1780560443.png', '.', 'sedangkan ia dalam keadaan beriman ', '', 'dan dia adalah orang yang bertaubat ', '', 'kecuali jika ia beriman kepada Allah ', '', 'maka imannya akan tetap terjaga', '', ' karena ia adalah seorang muslim', '', 'A'),
(65, 'PAI', 'X', '', '', 'Dalam materi tentang hakikat mencintai Allah subhanahu wata’ala., ada istilah Mahabbah. Secara mendalam, Mahabbah kepada Allah itu bukan sekadar \"kata-kata manis\" di lisan. Makna Mahabbah yang sesungguhnya adalah... .', 'perasaan takut yang membuat kita menjauh dari Allah  ', '', 'harapan untuk mendapatkan pujian dari manusia lewat ibadah   ', '', 'penyerahan diri secara total karena merasa tidak punya pilihan ', '', 'rasa sombong karena sudah merasa paling religius dibanding teman lain ', '', 'cinta yang mendalam kepada Allah yang menimbulakn rasa tenang dan ketaatan', '', 'E'),
(66, 'PAI', 'X', '', '', 'Aisyah adalah seorang siswi yang selalu berusaha meningkatkan keimanannya. Ia mencintai Allah dengan sepenuh hati, mencintai Rasul-Nya, serta mencintai sesama karena Allah. Ia juga berusaha melakukan segala sesuatu yang diridhai oleh Allah sebagai wujud rasa cinta-Nya (Mahabbah).<br />\r\n<br />\r\nBerdasarkan narasi tersebut, yang merupakan macam-macam mahabbah yang benar adalah … .', 'cinta kepada Allah, cinta kepada sesama manusia, dan cinta kepada diri sendiri ', '', 'cinta kepada Allah, cinta karena Allah, dan cinta kepada apa yang dicintai Allah ', '', 'cinta kepada jabatan, cinta kepada kekuasaan, dan cinta kepada popularitas ', '', 'cinta kepada keluarga, cinta kepada teman, dan cinta kepada lingkungan', '', ' cinta kepada Allah, cinta kepada dunia, dan cinta kepada harta', '', 'B'),
(67, 'PAI', 'X', '', '', '18.	Rizki adalah siswa yang aktif dalam kegiatan keagamaan di sekolah. Ia melaksanakan salat tepat waktu, membantu teman tanpa mengharap imbalan, dan menjauhi perbuatan yang dilarang Allah. Ia melakukan semua itu karena rasa cintanya kepada Allah (mahabbah).<br />\r\nBerdasarkan narasi tersebut, manakah contoh perilaku yang mencerminkan mahabbah dengan tepat… .', 'berdoa agar mendapatkan banyak pahala dan surga ', '', 'melaksanakan ibadah karena takut akan siksa Allah ', '', 'menolong teman agar mendapatkan pujian dari orang lain', '', ' beribadah dan berbuat baik karena rasa cinta kepada Allah ', '', 'pasrah tanpa berusaha karena yakin semua sudah ditentukan', '', 'D'),
(68, 'PAI', 'X', '', '', '19.	Nabila adalah seorang siswi yang selalu berusaha mendekatkan diri kepada Allah. Ia melaksanakan salat tepat waktu, membaca Al-Qur’an setiap hari, dan membantu teman-temannya dengan ikhlas. Nabila melakukan semua itu bukan karena ingin dipuji, tetapi karena rasa cintanya kepada Allah (mahabbah).<br />\r\nBerdasarkan narasi tersebut, yang merupakan  hikmah dari sikap mahabbah yang tepat Adalah … .', 'menjadikan hati tenang, ikhlas dan meningkatkan kesabaran', '', 'menjadikan seseorang lebih mencintai dunia daripada akhirat ', '', 'mendorong seseorang hanya mengharapkan imbalan berupa pahala ', '', 'Cmembuat seseorang pasrah tanpa usaha dalam menjalani kehidupan ', '', 'menjadikan seseorang selalu merasa takut berlebihan terhadap azab Allah', '', 'A'),
(69, 'PAI', 'X', '', '', '20.	Khauf adalah rasa takut kepada Allah yang mendorong seseorang untuk menjauhi larangan-Nya dan melaksanakan perintah-Nya. Rasa takut ini bukan membuat putus asa, tetapi justru menjadikan seseorang lebih taat dan berhati-hati dalam bertindak.<br />\r\nBerdasarkan deskripsi tersebut, manakah yang termasuk macam-macam khauf yang benar… .', 'takut terhadap ujian, takut terhadap penyakit, dan takut terhadap masa depan', '', ' takut kepada Allah, takut kepada manusia, dan takut kepada kegagalannya ', '', 'takut kepada Allah, takut akan siksa-Nya, dan takut amal tidak diterima ', '', 'takut kepada orang tua, takut kepada guru, dan takut kepada teman ', '', 'takut kehilangan harta, takut miskin, dan takut tidak terkenal', '', 'C'),
(70, 'PAI', 'X', '', '', '21.	Raja’ adalah sikap berharap kepada rahmat dan ampunan Allah. Seorang muslim yang memiliki sikap raja’ akan tetap optimis dalam menjalani kehidupan, tidak mudah putus asa, serta selalu berusaha dan berdoa kepada Allah meskipun menghadapi berbagai kesulitan.<br />\r\nBerdasarkan narasi tersebut, manakah yang merupakan keutamaan dari perilaku raja’... .', 'membuat seseorang takut berlebihan terhadap azab Allah ', '', 'menumbuhkan sikap putus asa ketika mengalami kegagalan ', '', 'menjadikan seseorang optimis dan tidak mudah berputus asa ', '', 'menjadikan seseorang bergantung sepenuhnya pada manusia ', '', 'menjadikan seseorang malas berusaha karena hanya berharap', '', 'C'),
(71, 'PAI', 'X', '', '', 'Perhatikan pernyataan berikut!<br />\r\n1.	Ramdhan berusaha dengan sungguh-sungguh, kemudian menyerahkan hasilnya kepada Allah.<br />\r\n2.	Maulana hanya pasrah tanpa melakukan usaha apa pun.<br />\r\n3.	Hamid berusaha, tetapi masih ragu dan tidak yakin kepada ketentuan Allah.<br />\r\n4.	Latif berusaha maksimal, yakin sepenuhnya kepada Allah, dan hatinya tenang menerima apa pun hasilnya.<br />\r\n5.	Ali  berusaha hanya untuk mendapatkan pujian dari orang lain.<br />\r\nBerdasarkan pernyataan tersebut, tingkatan tawakkal yang paling tinggi ditunjukan oleh… .', '1. Ramdhan', '', ' 2. Maulana			', '', '3. Hamid', '', '4. Latif', '', '5. Ali ', '', 'D'),
(72, 'PAI', 'X', '', '', 'Perhatikan pernyataan berikut!<br />\r\n1.	Saya berhenti berusaha ketika gagal dan menyalahkan takdir.<br />\r\n2.	Saya belajar dengan sungguh-sungguh, berdoa, lalu menyerahkan hasil ujian kepada Allah.<br />\r\n3.	Setelah berusaha maksimal dalam bekerja, saya menerima hasilnya dengan ikhlas karena yakin itu ketentuan Allah.<br />\r\n4.	Saya hanya berdoa tanpa berusaha karena yakin Allah akan memberikan hasil terbaik.<br />\r\n5.	Saya tetap berikhtiar dan berdoa ketika menghadapi masalah, lalu bertawakkal kepada Allah.<br />\r\nBerdasarkan pernyataan tersebut, contoh perilaku tawakkal yang benar ditunjukan oleh nomor … .<br />\r\n', '1, 2 dan 3', '', '1, 3 dan 5', '', '2, 3 dan 4', '', '2, 4 dan 5', '', '2, 3 dan 5', '', 'E'),
(73, 'PAI', 'X', '', '', '24.	Andi sering marah ketika keinginannya tidak terpenuhi. Ia mudah tersinggung, berkata kasar, bahkan kadang melampiaskan emosinya kepada teman-temannya. Sikap tersebut termasuk akhlak mażmūmah yang harus dihindari oleh setiap muslim.<br />\r\nBerdasarkan narasi tersebut, makna ghadab yang paling tepat adalah… .', 'perasaan marah yang berlebihan dan sulit dikendalikan ', '', 'perasaan senang terhadap keberhasilan orang lain', '', ' sikap sabar dalam menghadapi masalah ', '', 'sikap rendah hati kepada sesama ', '', 'perasaan takut kepada Allah', '', 'A'),
(74, 'PAI', 'X', '', '', '25.	Farhan punya reputasi sebagai siswa yang sumbu pendek atau mudah tersulut emosi. Suatu hari, bukunya basah kuyup karena ketidaksengajaan temannya. Meski sempat naik pitam dan nyaris memukul, Farhan justru memilih pergi berwudu untuk menenangkan diri.<br />\r\nBerdasarkan kisah tersebut, kita bisa belajar bahwa sifat gadab (marah) sangat penting untuk diredam karena... .', 'Bisa bikin teman-teman merasa segan.', '', 'Menandakan sikap tegas saat menghadapi sebuah masalah.', '', 'Membuat seseorang lebih nekat dalam mengambil keputusan.', '', 'Membuat orang lain merasa terintimidasi sehingga kita dihormati.', '', 'Berpotensi memicu keretakan pertemanan dan penyesalan di kemudian hari.', '', 'E'),
(75, 'PAI', 'X', '', '', 'Perhatikan pernyataan berikut!<br />\r\n<br />\r\n1.	Selalu berusaha husnuzan (berpikir positif) kepada orang lain.<br />\r\n<br />\r\n2.	Membalas ejekan dengan kalimat yang jauh lebih pedas.<br />\r\n<br />\r\n3.	Mengambil waktu sejenak untuk mendinginkan kepala saat emosi memuncak.<br />\r\n<br />\r\n4.	Menghindari diskusi atau musyawarah saat hati sedang panas.<br />\r\n<br />\r\n5.	Menenangkan batin dengan cara berzikir dan berwudu<br />\r\n<br />\r\nBerdasarkan pernyataan tersebut, langkah yang paling efektif untuk mencegah sifat ghadab ditunjukkan oleh poin... . <br />\r\n<br />\r\n', '1, 2, dan 3', '', '1, 3, dan 5', '', '2, 3, dan 4', '', '2, 4, dan 5', '', '3, 4, dan 5', '', 'B'),
(76, 'PAI', 'X', '', '', '27.	Reno punya kebiasaan begadang demi main gim. Efeknya, ia jadi sensitif; saat orang tuanya memberi nasihat, ia malah membentak bahkan sampai membanting barang. Namun, setelah emosinya surut, ia merasa sangat bersalah.<br />\r\nAkar permasalahan dari perilaku Reno tersebut sebenarnya adalah… .', 'Dampak buruk dari pergaulan di sekolahnya.  ', '', 'Terlalu sibuk mengurusi berbagai organisasi.', '', ' Nilai akademiknya yang mungkin sedang menurun. ', '', 'Kurangnya interaksi dengan teman sebaya di rumah. ', '', 'Lemahnya kemampuan dalam mengontrol hawa nafsu.', '', 'E'),
(77, 'PAI', 'X', '', 'soal_utama_1780564068.png', 'Berdasarkan tabel tersebut, makna dari mujahadah an-nafs (kontrol diri) yang paling tepat adalah... ', '1, 2, dan 3.', '', '2, 3, dan 4', '', '3, 2, dan 5.', '', '4, 5, dan 1.', '', '1, 2, dan 4', '', 'E'),
(78, 'PAI', 'X', '', '', 'Dina adalah siswa cerdas. Saat ujian, temannya yang kesulitan terus-menerus memohon jawaban padanya. Walaupun Dina tahu jawabannya, ia tetap diam dan memilih jujur demi prinsipnya.<br />\r\nTindakan Dina ini adalah contoh nyata dari sifat syaja’ah, yakni... .<br />\r\n', 'berani berkonfrontasi dengan guru. ', '', 'melakukan sesuatu demi mendapatkan pujian. ', '', 'tekad kuat untuk selalu menang dalam persaingan.', '', ' keberanian dalam memegang teguh nilai kebenaran.', '', 'keberanian mengambil risiko tanpa memikirkan akibatnya', '', 'D'),
(79, 'PAI', 'X', '', '', '\"Seorang Ketua OSIS tidak ragu menyuarakan pendapat yang benar dalam rapat, meskipun suaranya berbeda sendiri dibanding peserta lain.\"<br />\r\nBerdasarkan narasi tersebut, manfaat yang bisa dipetik dari sifat syaja’ah berikut adalah... .', 'memberikan kebebasan untuk berbuat semaunya. ', '', 'membuat keinginan pribadi jadi lebih cepat terwujud.', '', ' agar selalu memenangkan debat di setiap kesempatan. ', '', 'menumbuhkan keberanian untuk menabrak aturan yang ada. ', '', 'membentuk rasa percaya diri yang kuat dalam membela kebenaran.', '', 'E'),
(80, 'PAI', 'X', '', '', '31.	Pemerintah dan warga bahu-membahu memberantas narkoba sekaligus memberikan edukasi bagi kaum pemuda. Hal ini dilakukan agar generasi masa depan tidak rusak secara fisik maupun mental.<br />\r\nBerdasarkan narasi tersebut, bentuk nyata dari al-kulliyat al-khamsah dalam hal menjaga keturunan (hifz an-nasl) adalah... .', 'fokus pada keselamatan nyawa secara umum. ', '', 'berupaya meningkatkan taraf ekonomi keluarga. ', '', 'menjamin keamanan aset dan harta benda masyarakat. ', '', 'memberikan kebebasan mutlak bagi manusia untuk bertindak. ', '', 'upaya menjaga martabat serta kualitas generasi penerus bangsa.', '', 'E'),
(81, 'PAI', 'X', '', '', 'Sebuah sekolah melakukan kebijakan yang sangat tegas melarang aksi bullying, pergaulan bebas, dan penyalahgunaan medsos agar siswanya memiliki akhlak mulia dan masa depan cerah. <br />\r\nBerdasarkan narasi tersebut, kebijakan sekolah yang paling tepat adalah... .<br />\r\n', 'Menjaga keturunan (hifz an-nasl) ', '', 'Menjaga agama (hifz ad-dīn) ', '', 'Menjaga harta (hifz al-māl) ', '', 'Menjaga jiwa (hifz an-nafs) ', '', 'Menjaga akal (hifz al-‘aql)', '', 'A'),
(82, 'PAI', 'X', '', '', 'Istilah al-kulliyatul al-khamsah dalam Islam terdiri dari dua kata yaitu al-kulliyatu dan al-khamsah. Al-kulliyatu artinya prinsip dasar, sedangkan al-khamsah berarti lima, jadi al-kulliyatu al-khamsah berarti lima prinsip dasar hukum Islam.<br />\r\nJadi dapat disimpulkan bahwa al-kulliyatu al khamsah berarti lima prinsip dasar hukum Islam yang bertujuan mewujudkan kemaslahatan (al-maslahat), dan apabila hal ini tidak ada maka akan muncul kerusakan (mafsadat). <br />\r\nBerikut ini merupakan macam al Kulliyyatu al khamsah, kecuali… .', 'menjaga jiwa (hifzhu al-nafs)', '', 'menjaga akal (hifzhu al-‘Aql)', '', 'menjaga agama (hifzhu al-din)', '', 'menjaga negara (hifzhu al-bilad).', '', 'menjaga keturunan (hifzhu al-nasl)', '', 'D'),
(83, 'PAI', 'X', '', '', 'Perhatikan pernyataan berikut!<br />\r\n1.	Menolak ajakan mengonsumsi minuman keras atau narkoba karena sadar hal tersebut dapat merusak fungsi otak, memicu perilaku kriminal, dan menghilangkan kesadaran diri.<br />\r\n2.	Memutuskan untuk menjaga batas pergaulan dengan lawan jenis dan menghindari perzinaan guna menjaga kehormatan keluarga serta memastikan nasab (garis keturunan) yang jelas di masa depan.<br />\r\nBerdasarkan pernyataan tersebut, perbedaan mendasar hifz al-aql (memelihara akal) dan hifz an-nasl (memelihara keturunan) adalah... .<br />\r\n', 'Hifz al-aql berfokus pada pencegahan segala sesuatu yang merusak daya pikir manusia, sedangkan hifz an-nasl berfokus pada upaya menjaga keberlangsungan hidup dan martabat melalui institusi pernikahan yang sah. ', '', 'Hifz al-aql bertujuan untuk meningkatkan kecerdasan akademik di sekolah, sementara hifz an-nasl bertujuan untuk memperbanyak jumlah anggota keluarga dalam satu garis keturunan. ', '', 'Hifz al-aql dilakukan dengan cara menjauhi narkoba agar terhindar dari sanksi hukum pidana, sedangkan hifz an-nasl dilakukan dengan cara menjauhi zina agar tidak terkena penyakit menular seksual. ', '', 'Perbedaan keduanya terletak pada objeknya; hifz al-aql melindungi aspek jasmani berupa organ otak, sedangkan hifz an-nasl melindungi aspek rohani berupa rasa cinta kasih antar sesama manusia. ', '', 'Hifz al-aql merupakan kewajiban bagi setiap individu yang masih bersekolah, sementara hifz an-nasl merupakan kewajiban yang hanya berlaku bagi orang dewasa yang sudah bekerja di Jakarta.', '', 'A'),
(84, 'PAI', 'X', '', '', '35.	Andi adalah seorang siswa SMK di Jakarta yang aktif dalam komunitas e-sport. Suatu hari, menjelang turnamen besar, teman-teman satu timnya mengajak Andi untuk mengonsumsi minuman berenergi yang dicampur dengan obat-obatan terlarang dengan alasan agar tetap terjaga (tidak tidur) dan fokus berlatih sepanjang malam. Teman-temannya berdalih bahwa hal ini dilakukan demi solidaritas tim dan kemenangan sekolah. Namun, Andi mengetahui bahwa tindakan tersebut dapat merusak fungsi saraf pusat dan menyebabkan ketergantungan yang merusak masa depannya.<br />\r\nBerdasarkan narasi tersebut, hifz al-aql (memelihara akal) dalam kehidupan sehari-hari, tindakan yang paling tepat dilakukan Andi adalah... .<br />\r\n', 'menolak dengan tegas dan menjelaskan bahwa menjaga kesehatan akal dan kesadaran lebih utama daripada kemenangan turnamen. ', '', 'mengikuti ajakan teman-temannya hanya satu kali saja demi menjaga perasaan dan solidaritas tim agar tidak terjadi perpecahan. ', '', 'menerima ajakan tersebut asalkan dosisnya sedikit, karena yang terpenting adalah tujuan memenangkan lomba untuk nama baik sekolah. ', '', 'mengusulkan untuk mengganti obat terlarang tersebut dengan kopi hitam dalam jumlah banyak agar tetap bisa begadang hingga pagi hari.', '', 'diam saja dan membiarkan teman-temannya mengonsumsi obat tersebut, sementara Andi sendiri tidak ikut mengonsumsinya agar tetap aman. ', '', 'A'),
(85, 'PAI', 'X', '', '', 'Penyebaran Islam di Pulau Jawa tidak lepas dari peran besar sembilan ulama yang dikenal dengan sebutan Wali Songo. Mereka menggunakan berbagai pendekatan budaya dan sosial sehingga Islam dapat diterima dengan damai oleh masyarakat. Berdasarkan narasi tersebut, tokoh yang seluruhnya termasuk dalam anggota Wali Songo?', 'Sunan Gresik, Sunan Ampel, dan Sunan Kalijaga  ', '', 'Sunan Giri, Sunan Drajat, dan Tuanku Imam Bonjol  ', '', 'Sunan Ampel, Sunan Kalijaga, dan Pangeran Diponegoro ', '', 'Sunan Muria, Sunan Kudus, dan Syekh Yusuf al-Makassari ', '', 'Sunan Bonang, Sunan Gunung Jati, dan Sultan Hasanuddin', '', 'A'),
(86, 'PAI', 'X', '', '', 'Wali Songo dalam menyebarkan ajaran Islam di Nusantara dikenal menggunakan pendekatan yang sangat bijaksana. Mereka tidak menghapus tradisi lama secara drastis, melainkan memasukkan nilai-nilai tauhid ke dalam seni, budaya, dan tatanan sosial yang sudah ada. Sebagai contoh, Sunan Kalijaga menggunakan media wayang kulit untuk menyisipkan ajaran Islam, sementara Sunan Kudus membangun menara masjid yang menyerupai bentuk candi sebagai bentuk penghormatan terhadap masyarakat setempat yang masih memegang teguh tradisi Hindu-Buddha.<br />\r\n<br />\r\nBerdasarkan deskripsi tersebut, metode utama yang digunakan oleh Wali Songo dalam penyebaran Islam di Indonesia adalah... .', 'debat keagamaan secara terbuka', '', ' penaklukan wilayah secara militer dan politik  ', '', 'asimilasi dan akulturasi budaya yang harmonis  ', '', 'isolasi diri dari masyarakat untuk menjaga kemurnian ajaran  ', '', 'mewajibkan seluruh rakyat untuk meninggalkan tradisi nenek moyang', '', 'C'),
(87, 'PAI', 'X', '', '', 'Bayangkan kamu sedang scrolling sejarah penyebaran Islam di Indonesia. Kamu akan menemukan fakta bahwa Wali Songo itu nggak cuma jago dakwah di masjid, tapi juga jago \"baca algoritma\" masyarakat saat itu. Contohnya, Sunan Muria yang lebih memilih tinggal di daerah terpencil buat bantu rakyat kecil, atau Sunan Bonang yang mengubah instrumen gamelan menjadi media dakwah yang syahdu. <br />\r\nBerdasarkan narasi tersebut, sikap keteladanan paling menonjol dari Wali Songo yang bisa kita terapkan sebagai kehidupan  di era modern adalah... .', 'menjauhkan diri dari pergaulan agar tetap fokus dan tenang dalam ibadah  B.	selalu merasa paling benar dalam menyampaikan pendapat di media sosial  C.	memaksakan standar moral kepada orang lain agar terlihat lebih religius D.	mengutamakan kepentingan golongan sendiri di atas masyarakat luas  E.	bersikap inklusif dan adaptif terhadap budaya lokal yang ada ', '', 'selalu merasa paling benar dalam menyampaikan pendapat di media sosial  ', '', 'memaksakan standar moral kepada orang lain agar terlihat lebih religius ', '', 'mengutamakan kepentingan golongan sendiri di atas masyarakat luas  ', '', 'bersikap inklusif dan adaptif terhadap budaya lokal yang ada ', '', 'E'),
(88, 'PAI', 'X', '', '', '39.	Wali Songo sebagai content creator dan community developer paling sukses sepanjang sejarah. Tanpa kekuatan militer atau paksaan, mereka berhasil menjadikan Islam sebagai agama mayoritas yang diterima dengan baik oleh warga lokal. <br />\r\nBerdasarkan narasi tersebut, analisis yang tepat Wali Songo berhasil gemilang adalah... .', 'Wali Songo tidak fleksibel dalam menerapkan aturan agama sehingga masyarakat takut.', '', 'menghapus semua tradisi lokal secara instan karena dianggap tidak relevan dengan ajaran baru. ', '', 'mereka hanya fokus berdakwah kepada kaum elit dan bangsawan agar rakyat bawah otomatis ikut.   ', '', 'mereka punya dukungan yang besar dari kerajaan luar negeri untuk membangun fasilitas mewah.  ', '', 'adanya strategi cultural branding yang kuat, di mana Islam dikemas secara damai tanpa merusak tatanan sosial yang sudah ada.', '', 'E'),
(89, 'PAI', 'X', '', '', 'Sunan Kudus saat berdakwah kepada masyarakat saat itu mayoritas masih sangat menghormati sapi sebagai hewan suci dalam tradisi mereka. Sunan Kudus justru melarang pengikutnya menyembelih sapi untuk menghargai perasaan warga lokal. Beliau bahkan membangun menara masjid dengan arsitektur yang mirip candi Hindu agar warga merasa tidak  asing dengan kehadiran Islam.<br />\r\nBerdasarkan narasai tersebut, kesimpulan yang paling tepat mengenai keteladanan Sunan Kudus adalah... .', 'terlalu kompromi sehingga ajaran Islam yang disampaikan menjadi tidak murni lagi.  ', '', 'langkah beliau hanyalah pencitraan agar cepat mendapatkan kekuasaan politik di wilayah Kudus.  ', '', 'sangat cerdas dalam menerapkan strategi diplomasi budaya untuk menciptakan harmoni tanpa mengorbankan akidah Islam. ', '', 'kurang tegas dalam berdakwah karena membiarkan tradisi agama lain tetap hidup di lingkungan masjid. ', '', ' strategi beliau hanya efektif di masa lalu dan sudah tidak relevan lagi untuk diterapkan di zaman modern yang serba cepat.', '', 'C'),
(90, 'Bahasa Inggris', 'X', 'PART I - PICTURES<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your test book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  ', 'soal_utama_1780567846.png', '.', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'C'),
(91, 'Bahasa Inggris', 'X', 'PART I - PICTURES<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your test book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  ', 'soal_utama_1780568306.png', '.', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'B'),
(92, 'Bahasa Inggris', 'X', 'PART I - PICTURES<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your test book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  ', 'soal_utama_1780568318.png', '.', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'A'),
(93, 'Bahasa Inggris', 'X', 'PART II – QUESTIONS RESPONSES<br />\r\n<br />\r\nDirections:  You will hear a question or statement and three responses spoken in English.<br />\r\nThey will not be printed in your test book and will be spoken only one time. Select<br />\r\nthe best response to the question or statement and mark the letter (A), (B), or (C)<br />\r\non your answer sheet.<br />\r\n', '', 'Mark your answer on your answer sheet', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'B'),
(94, 'Bahasa Inggris', 'X', 'PART II – QUESTIONS RESPONSES<br />\r\n<br />\r\nDirections:  You will hear a question or statement and three responses spoken in English.<br />\r\nThey will not be printed in your test book and will be spoken only one time. Select<br />\r\nthe best response to the question or statement and mark the letter (A), (B), or (C)<br />\r\non your answer sheet.<br />\r\n', '', 'Mark your answer on your answer sheet', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'A'),
(95, 'Bahasa Inggris', 'X', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\nspoken only one time.', '', 'What will the man do if he feels better tonight?', 'He will stay at home. ', '', 'He will go to the hospital anyway. ', '', 'He will call the woman for help. ', '', 'He will buy some medicine. ', '', 'He will see the doctor tomorrow morning', '', 'A'),
(96, 'Bahasa Inggris', 'X', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\nspoken only one time.', '', 'What should Bella do if she wants to go to the concert?', 'Ask Lusi for some money. ', '', 'Buy the ticket immediately. ', '', 'Look for a cheaper concert. ', '', 'Start managing her expenses better.', '', 'Wait for next month to get a free ticket.', '', 'D'),
(97, 'Bahasa Inggris', 'X', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\nspoken only one time.', '', 'What does Leo usually do on Sundays?<br />\r\n<br />\r\n<br />\r\n<br />\r\n', 'He finishes his school projects. ', '', 'He spends his time relaxing. ', '', 'He goes to school early.', '', 'He stays up late to study. ', '', 'He meets Maya for a discussion.', '', 'A'),
(98, 'Bahasa Inggris', 'X', 'PART IV – SHORT TALKS<br />\r\n<br />\r\n<br />\r\n<br />\r\nDirections: You will hear some talks given by a single speaker. You will be asked to answer two questions about what the speaker says in each talk. Select the best response to each question and mark the letter (A), (B), (C), or (D) on your answer sheet. The talks will not be printed in your test book and will be spoken only one time.  <br />\r\n<br />\r\n<br />\r\n<br />\r\nNow let’s begin with the following short talk.', '', 'What is the purpose of the announcement?<br />\r\n<br />\r\n<br />\r\n<br />\r\n', 'To inform about the school&#039;s anniversary date.', '', 'To invite students to join a storytelling competition.', '', 'To announce a holiday on October 20th.', '', 'To tell the students to meet Ms. Indah.', '', 'To ask students to clean the school hall.', '', 'B'),
(99, 'Bahasa Inggris', 'X', 'PART IV – SHORT TALKS<br />\r\n<br />\r\n<br />\r\nDirections: You will hear some talks given by a single speaker. You will be asked to answer two questions about what the speaker says in each talk. Select the best response to each question and mark the letter (A), (B), (C), or (D) on your answer sheet. The talks will not be printed in your test book and will be spoken only one time.  <br />\r\n<br />\r\nNow let’s begin with the following short talk.', '', 'Where will the competition take place?', 'In the classroom', '', 'In the teacher&#039;s room. 	', '', 'In the library. ', '', 'In the school hall. ', '', 'At the English Club&#039;s office.', '', 'D'),
(100, 'Bahasa Inggris', 'X', 'B. READING SECTION<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\n<br />\r\nPART V. Directions: <br />\r\n<br />\r\nFrom questions 11 – 19, four clauses/sentences, marked (A), (B), (C), (D) or (E), are given beneath each incomplete dialog. Choose the one clause/sentence that best completes the dialog. Then, on your answer sheet, find the number of the question and mark your answer<br />\r\n<br />\r\n', '', 'Jane	: “Have you met the new manager, Mr. Henderson?”<br />\r\nLeo	: “Not yet. What does he look like?”<br />\r\nJane	: “Well, he is quite ….... and has a very professional demeanour.”<br />\r\nLeo	: “Is he friendly?”<br />\r\nJane	: “To be honest, he seems a bit reserved at first, but he is actually very helpful once you get to know him.”', 'short and stout', '', 'tall and athletic', '', 'messy and loud', '', 'young and reckless', '', 'old and grumpy', '', 'B'),
(101, 'Bahasa Inggris', 'X', '', '', 'Sarah	: “I am not sure if I can finish the report on time.”<br />\r\nTom	: “Don\'t worry. If you need some help, I will assist you after lunch.”<br />\r\nSarah	: “That is kind of you. What about the presentation tomorrow?”<br />\r\nTom	: “The meeting will be a disaster ……... ready by 8 AM.”', 'if we do not get the slides', '', 'we did not get the slides', '', 'if won&#039;t get the slide', '', 'if we do haven&#039;t got', '', 'if wouldn&#039;t get the slides', '', 'A'),
(102, 'Bahasa Inggris', 'X', '', '', 'Sarah	: “I usually wake up at 5 AM.”<br />\r\nTom	: “That\'s very early! What do you do next?” <br />\r\nSarah	: “I always drink a glass of water before having my breakfast to stay hydrated.” <br />\r\nTom	: “How do you go to the office? “<br />\r\nSarah	: “My office is quite far, so I ... to avoid traffic jams.”', 'take the commuter train	', '', 'drive my own car', '', 'walk slowly', '', 'buy a new vehicle', '', 'stay at home', '', 'A'),
(103, 'MTK', 'XI', '', '', 'Pada pelajaran matematika, Pak Rahman menggambar sebuah lingkaran di papan tulis. Titik O berada tepat di tengah lingkaran. Pak Rahman kemudian menarik garis dari titik O menuju titik A yang berada pada keliling lingkaran. Setelah itu, Pak Rahman menjelaskan bahwa garis tersebut merupakan salah satu elemen penting pada lingkaran. Berdasarkan wacana tersebut, garis OA adalah ....', 'Diameter', '', 'Jari-jari', '', 'Busur', '', 'Tali Busur', '', 'Apotema', '', 'B'),
(104, 'MTK', 'XI', 'Perhatikan gambar berikut :', 'soal_utama_1780623139.jpg', 'Sebuah bioskop memiliki tata letak kursi berbentuk busur lingkaran dengan titik pusat gedung di O. Layar utama bioskop diwakili oleh busur AB. Jika besar sudut pusat ∠AOB<br />\r\n= 80° maka besar sudut pandang (sudut keliling) penonton yang duduk di titik C (∠ACB) adalah ….', '40°', '', '80°', '', '100°', '', '120°', '', '200°', '', 'A');
INSERT INTO `soal` (`id`, `mata_pelajaran`, `kelas`, `deskripsi`, `gambar`, `pertanyaan`, `opsi_a`, `gambar_a`, `opsi_b`, `gambar_b`, `opsi_c`, `gambar_c`, `opsi_d`, `gambar_d`, `opsi_e`, `gambar_e`, `kunci_jawaban`) VALUES
(105, 'MTK', 'XI', '', '', '3.	Sebuah taman hiburan memamerkan bianglala raksasa baru. Demi keamanan, struktur roda bianglala diperkuat dengan tiang-tiang penyangga baja yang saling bersilang di bagian dalamnya, menghubungkan titik-titik tempat duduk (kabin). Seorang arsitek menggambarkan salah satu struktur penyangga bagian dalam yang membentuk segi empat tali busur ABCD pada lingkaran roda bianglala. Diketahui bahwa tiang-tiang tersebut membentuk segi empat tali busur dengan sudut-sudut yang saling berhadapan memiliki hubungan khusus. Berdasarkan cetak biru pembangunan, besar sudut yang terbentuk pada kabin A (∠DAB) adalah 105o dan besar sudut pada kabin B (∠ABC) adalah 88o. Jika mekanik ingin mengukur ketepatan sudut pada kabin C (∠BCD) untuk memastikan keseimbangan struktur roda maka besar sudut pada kabin C adalah ….', '75°', '', '88°', '', '92°', '', '105°', '', '115°', '', 'A'),
(106, 'MTK', 'XI', 'Perhatikan gambar di bawah ini!', 'soal_utama_1780623770.jpg', 'Panjang garis singgung persekutuan luar AB adalah ....', '24 cm', '', '22 cm', '', '18 cm', '', '16 cm', '', '12 cm', '', 'E'),
(107, 'MTK', 'XI', '', '', 'Jarak titik pusat dua buah lingkaran adalah 25 cm. Jika jari-jari salah satu lingkaran adalah 12 cm dan jari-jari lingkaran lain adalah 8 cm. Panjang garis singgung persekutuan dalam antara kedua lingkaran tersebut adalah ....', '15 cm', '', '17 cm', '', '19 cm', '', '21 cm', '', '23 cm', '', 'A'),
(108, 'MTK', 'XI', 'Perhatikan gambar berikut.', 'soal_utama_1780624224.jpg', 'Jika sebuah lingkaran memiliki jari-jari 7 cm dengan sudut pusat 120°, maka luas juring adalah ....', '51,23 𝑐𝑚²', '', '51,33 𝑐𝑚²', '', '52,33 𝑐𝑚²', '', '52,67 𝑐𝑚²', '', '53,33 𝑐𝑚²', '', 'B'),
(109, 'MTK', 'XI', 'Perhatikan lingkaran berikut.', 'soal_utama_1780624472.png', 'Jika panjang PA = 21 cm dan besar ∠𝐴𝑃𝐵 = 120°, maka panjang busur AB adalah ....', '40 cm', '', '41 cm', '', '42 cm', '', '43 cm', '', '44 cm', '', 'E'),
(110, 'MTK', 'XI', 'Perhatikan gambar berikut.', 'soal_utama_1780624733.jpg', '8.	Seorang arsitek mendesain sebuah jendela rumah dengan bentuk setengah lingkaran seperti pada ilustrasi di bawah ini. Untuk memperkuat struktur kaca, arsitek menambahkan sebuah teralis besi horizontal yang berfungsi sebagai tali busur jendela (garis AB).Titik O merupakan titik pusat diameter jendela. Jari-jari jendela tersebut OB adalah 25 cm. Jarak tegak lurus dari titik pusat O ke teralis besi AB adalah 7 cm. Berdasarkan ukuran padA gambar struktur di samping, panjang total tali busur jendela AB adalah ….', '24 cm', '', '32 cm', '', '36 cm', '', '48 cm', '', '50 cm', '', 'D'),
(111, 'MTK', 'XI', 'Perhatikan gambar berikut :', 'soal_utama_1780624897.jpg', 'Sebuah jembatan beton memiliki struktur lengkungan bawah yang membentuk busur lingkaran. Bagian atas lengkungan dibatasi oleh badan jalan jembatan yang mendatar (garis lurus AB) sehingga membentuk tembereng. Luas tembereng tersebut adalah ….', '20,5 m²', '', '22,5 m²', '', '28,5 m²', '', '50,0 m²', '', '50,5 m²', '', 'C'),
(112, 'Bahasa Inggris', 'X', '', '', 'Mr. Henry		: “Good morning, team. Before we begin the briefing, I would like to  Introduce our newest project manager, Sarah Jenkins”.<br />\r\nTeam		: “Good morning, Sarah.”<br />\r\nSarah		: “Good morning, everyone.…… I look forward to working with you all.”<br />\r\nMr. Henry			: “Sarah has extensive experience in software development. Mark, could <br />\r\n                                	You introduce yourself to her ?”<br />\r\nMark		: “Of course, sir. I\'m Mark, the lead designer. Nice to meet you, Sarah.”', 'I am very busy today.', '', 'It is a pleasure to be here.', '', 'I have to go to the kitchen.', '', 'What is everyone doing here?', '', 'See you later at the park.', '', 'B'),
(113, 'MTK', 'XI', '', 'soal_utama_1780625192.jpg', 'Sebuah stadion olahraga memiliki lintasan lari luar yang berbentuk lingkaran sempurna dengan titik pusat di O. Di dalam area stadion tersebut, pengelola membuat sebuah pembatas lurus AB sepanjang 80 m yang memotong area dalam lingkaran. Seorang pelatih ingin mengukur jarak terpendek (d) untuk menempatkan kamera pemantau. Diketahui jarak OB = 50 m. Berdasarkan gambar denah struktur di samping, jarak terpendek (apotema) dari O ke AB adalah ….', '20 m', '', '25 m', '', '30 m', '', '40 m', '', '50 m', '', 'C'),
(114, 'Bahasa Inggris', 'X', '', '', 'Sarah	: “I watched your science presentation earlier, Alex. It was truly informative and well structured.”<br />\r\nAlex	: “Thank you, Sarah. I spent a lot of time on the research.”<br />\r\nSarah	: “You did a marvelous job!”<br />\r\nAlex	: “By the way, did you see the visual slides I made?”<br />\r\nSarah	: “Yes, they were very creative. I have never seen such high-quality visuals before.”<br />\r\nAlex	: “…...”', 'I know, I am the best.', '', 'That is very kind of you to say.', '', 'You are lying to me.', '', 'I do not want to talk about it.', '', 'Please do not look at them.', '', 'B'),
(115, 'MTK', 'XI', '', '', 'Persamaan lingkaran dengan pusat O (0,0) dan jari-jari 3 adalah ....', '𝑥² + 𝑦² = −9', '', '𝑥² + 𝑦² = −3', '', '𝑥² + 𝑦² = 0', '', '𝑥² + 𝑦² = 3', '', '𝑥² + 𝑦² = 9', '', 'E'),
(116, 'Bahasa Inggris', 'X', '', '', 'Mark	: “I\'ve been thinking about the upcoming marketing campaign.”<br />\r\nSarah	: “Oh? Do you have something specific in mind?”<br />\r\nMark	: “Yes ……….”<br />\r\nSarah	: “That sounds like a great way to reach younger demographics.”<br />\r\nMark	: “Exactly. I want to ensure we stay relevant.”', 'I might see it later if I have time', '', 'I am going to launch a social media contest', '', 'I was thinking about what we did last year', '', 'I have already finished the monthly report', '', 'I don&#039;t think we need any more advertising', '', 'B'),
(117, 'Bahasa Inggris', 'X', '', '', 'Mr. Andrew	: “The project report is due tomorrow morning, but I\'m still struggling with the data analysis.”<br />\r\nMs. Clara	: “Don\'t worry, sir. I …… you finish it after the lunch break.”<br />\r\nMr. Andrew	: “Thank you, Clara. By the way, do we have any official updates?”<br />\r\nMs. Clara	: “Yes, we are meeting the board of directors at 3 PM today to discuss the results.”', 'I will help you finish it', '', 'I am helping you	', '', 'I help you to finish', '', 'I will helped you finish it', '', 'I am going to help', '', 'A'),
(118, 'MTK', 'XI', '', '', 'Persamaan lingkaran dengan pusat (-1, 4) dan melalui titik (3, -6) adalah ....', '𝑥² + 𝑦²− 𝑥 + 4𝑦 + 116 = 0', '', '𝑥² + 𝑦² + 𝑥 + 4𝑦 − 116 = 0', '', '𝑥² + 𝑦² − 2𝑥 − 8𝑦 + 99 = 0', '', '𝑥² + 𝑦² + 2𝑥 − 8𝑦 − 99 = 0', '', '𝑥² + 𝑦² − 2𝑥 + 8𝑦 − 99 = 0', '', 'D'),
(119, 'Bahasa Inggris', 'X', '', '', 'Rudi	: “Your score was so good in the last test. What was your preparation?”<br />\r\nLea	: “……. And I tried to understand the item test by myself.”<br />\r\nRudi	: “so, have asked something to your sister?”<br />\r\nLea	: “Yes, I asked her to take me to her favorite library.”', 'I join the course	', '', 'My sister was good in solving the problem', '', 'My school gives the extra lesson in the afternoon', '', 'I think it was my lucky day', '', 'I was studying the night before', '', 'E'),
(120, 'Bahasa Inggris', 'X', '', '', 'Anna	: “I saw your brother at the clinic yesterday. What exactly does he do there?”<br />\r\nBen	: “Oh, he is a Paediatrician. He specializes in treating children and infants.”<br />\r\nAnna	: “That sounds like a tough job. Does he work alone?”<br />\r\nBen	: “No, he works with a team of nurses who ...”<br />\r\n', 'sell medicine to the parents outside the clinic. ', '', 'clean the floors and windows of the clinic daily. ', '', 'assist him in providing medical care to young patients. ', '', 'write news articles about the clinic&#039;s achievements. ', '', 'drive the ambulance to pick up elder people.', '', 'C'),
(121, 'MTK', 'XI', '', '', 'Diketahui persamaan umum sebuah lingkaran adalah x2 + y2 + 8x + 10y + 5 = 0. Titik pusat dan jari-jari lingkaran tersebut adalah….', 'Pusat (4,5) dan jari-jari 6', '', 'Pusat (-4,-5) dan jari-jari 6', '', 'Pusat (-4,-5) dan jari-jari 16', '', 'Pusat (8,10) dan jari-jari 36', '', 'Pusat (-8,-10) dan jari-jari 6', '', 'B'),
(122, 'Bahasa Inggris', 'X', 'PART VI. Directions: <br />\r\nQuestions 20 – 40 are based on a selection of reading materials. Choose the one best answer, (A), (B), (C), (D) or (E), to each question. Answer all the questions following each reading selection on the basis of what is stated or implied in the selection. Then, on your answer sheet, find the number of the question and mark your answer.Question number 20-22 are based on the following text. <br />\r\n', 'soal_utama_1780626339.png', 'What is the text mainly about?', 'Traditional food in Yogyakarta', '', 'The myths of Indonesia', '', 'Parangtritis Beach as a tourist attraction', '', 'Activities in the city center', '', 'Horse riding competitions', '', 'C'),
(123, 'Bahasa Inggris', 'X', '', 'soal_utama_1780626459.png', 'What is the main idea of the third paragraph?', 'The beach is famous for shopping', '', 'The beach has many restaurants', '', 'Visitors enjoy the sunset', '', 'The beach is crowded every day', '', 'The beach is connected to local myths and legends', '', 'E'),
(124, 'MTK', 'XI', 'Perhatikan perubahan grafik fungsi kuadrat berikut :', 'soal_utama_1780626547.png', 'Berdasarkan perubahan posisi titik puncak tersebut, besar translasi (pergeseran) yang terjadi adalah...', '', 'opsi_a_1780626547_18.png', '', 'opsi_b_1780626547_75.png', '', 'opsi_c_1780626547_28.png', '', 'opsi_d_1780626547_43.png', '', 'opsi_e_1780626547_64.png', 'A'),
(125, 'Bahasa Inggris', 'X', '', 'soal_utama_1780626590.png', 'How far is Parangtritis Beach from the city center?', '10 kilometers					 					', '', '15 kilometers', '', '20 kilometers', '', '27 kilometers', '', '35 kilometers ', '', 'D'),
(126, 'MTK', 'XI', '', '', 'Diketahui fungsi linier f(x) = 3x + 5. Jika fungsi tersebut ditranslasikan sejauh satuan ke kanan dan 5 satuan ke bawah makan persamaan bayangan hasil translasi f’(x) adalah ….', 'f’(x) = 3x + 14', '', 'f’(x) = 3x + 1', '', 'f’(x) = 3x – 1', '', 'f’(x) = 3x – 4', '', 'f’(x) = 3x – 9', '', 'E'),
(127, 'MTK', 'XI', '', '', 'Bayangan dari titik P(5,-2) jika dicerminkan terhadap sumbu x adalah ….', 'P’(-5, -2)', '', 'P’(-5, 2)', '', 'P’(2, 5)', '', 'P’(5, 2)', '', 'P’(-2, 5)', '', 'D'),
(128, 'MTK', 'XI', '', '', 'Diketahui fungsi kuadrat f(x) = x2 + 6x + 8. Jika grafik fungsi tersebut direfleksikan terhadap sumbu y, maka persamaan bayangan hasil refleksi adalah ….', 'f’(x) = x² + 6x – 8', '', 'f’(x) = x² – 6x – 8', '', 'f’(x) = x² – 6x + 8', '', 'f’(x) = - x² + 6x – 8', '', 'f’(x) = - x² – 6x – 8', '', 'C'),
(129, 'MTK', 'XI', '', '', 'bayangan dari titik A(3,5) jika dirotasikan sejauh 90o berlawanan arah jarum jam dengan pusat O (0,0) adalah ….', 'A’ (3,-5)', '', 'A’ (-3,5)', '', 'A’ (5,3)', '', 'A’ (-5,-3)', '', 'A’ (-5,3)', '', 'E'),
(130, 'MTK', 'XI', '', '', 'Bayangan titik A(6,4) oleh dilatasi dengan pusat P92,3) dan factor skala k = 2 adalah ….', 'A’(10,5)', '', 'A’(12,8)', '', 'A’(8,6)', '', 'A’(10,8)', '', 'A’(12,5)', '', 'A'),
(131, 'MTK', 'XI', '', '', 'Diketahui fungsi linier f(x) = 3x + 2. Jika fungsi tersebut didilatasikan dengan pusat O(0,0) dan factor skala k = 2, maka persamaan bayangan fungsinya adalah ….', 'f’(x) = 6x + 4', '', 'f’(x) = 3x + 2', '', 'f’(x) = 3x + 4', '', '', 'opsi_d_1780627747_88.png', 'f’(x) = 6x + 2', '', 'C'),
(132, 'MTK', 'XI', '', 'soal_utama_1780628926.png', ' ', 'A’’ (4,3)', '', 'A’’ (-4,-3)', '', 'A’’ (10,-3)', '', 'A’’ (4,-3)', '', 'A’’ (4,7)', '', 'D'),
(133, 'MTK', 'XI', '', 'soal_utama_1780629164.png', ' ', '2x + 3y + 10 = 0', '', '2x – 3y + 10 = 0', '', '-2x – 3y + 10 = 0', '', '2x – 3y -10 = 0', '', '-2x + 3y -10 = 0', '', 'B'),
(134, 'MTK', 'XI', '', '', 'Diketahui segitiga ABC dengan koordinat titik A(1,2), B(3,1) dan C(2,4). Segitiga tersebut direfleksikan terhadap sumbu y, kemudian hasilnya didilatasikan dengan pusat P(-1,1) dan factor skala k = 2. Koordinat bayangan akhir segitiga A”B’’C’’ adalah ….', 'A” (-3,3), B”(-7,1) dan C”(-5,7)', '', 'A” (3,3), B”(-7,1) dan C”(-5,7)', '', 'A” (-3,3), B”(-7,-1) dan C”(-5,7)', '', 'A” (-3,3), B”(-7,1) dan C”(5,7)', '', 'A” (3,3), B”(7,1) dan C”(5,7)', '', 'A'),
(135, 'MTK', 'XI', '', '', 'Budi adalah seorang siswa SMK yang sedang merencanakan rute pulang sekolah. Untuk sampai ke rumahnya, Budi memiliki beberapa pilihan jenis transportasi. Ia melihat di aplikasi transportasi online terdapat 3 pilihan layanan ojek motor dan 2 pilihan layanan taksi mobil. Budi hanya bisa memilih salah satu dari layanan tersebut untuk sekali perjalanan pulang. Berdasarkan wacana di atas, banyak cara yang dapat dipilih Budi untuk menentukan layanan transportasi pulang ke rumah adalah ….', '1 cara', '', '3 cara', '', '5 cara', '', '7 cara', '', '9 cara', '', 'C'),
(136, 'MTK', 'XI', '', '', 'Diberikan sekumpulan angka unik {1, 2, 3, 4, 5, 6}. Dari angka-angka tersebut, akan disusun bilangan ratusan yang terdiri dari angka-angka berbeda (tidak boleh berulang). Banyaknya bilangan ganjil yang nilainya lebih besar dari 400 adalah...', '24 cara', '', '32 cara', '', '36 cara', '', '40 cara', '', '48 cara', '', 'B'),
(137, 'Bahasa Inggris', 'X', '', 'soal_utama_1780629874.png', 'How did the writer practice the speech?', 'By writing essays every day						', '', 'By practicing in front of a mirror and family', '', 'By recording videos only', '', 'By reading books in the library', '', 'By practicing with classmates at school only ', '', 'B'),
(138, 'MTK', 'XI', '', '', 'Sebuah perusahaan teknologi mewajibkan karyawannya membuat kata sandi (password) yang terdiri dari 3 huruf berbeda diikuti oleh 2 angka berbeda. Jika aturan pembuatannya adalah huruf pertama harus huruf vokal (A, I, U, E, O) dan angka terakhir harus angka prima (2, 3, 5, 7), maka banyaknya variasi kata sandi yang dapat dibuat adalah...', '36000', '', '72000', '', '96000', '', '108000', '', '120000', '', 'D'),
(139, 'Bahasa Inggris', 'XI', 'In this section of the test, you will have the chance to show how well you understand spoken English. There are four parts to this section with special directions for each. part.<br />\r\n<br />\r\n<br />\r\nPART I - PICTURES<br />\r\n<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your<br />\r\ntest book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  <br />\r\n', 'soal_utama_1780707442.jpg', 'Look at the picture marked number 1 in your text book.  ', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'B'),
(140, 'MTK', 'XI', '', '', 'Dalam sebuah acara diskusi, terdapat 3 siswa laki-laki dan 4 siswi perempuan yang akan duduk berdampingan dalam satu baris kursi. Jika ketiga siswa laki-laki tersebut harus selalu duduk berdampingan, maka banyaknya susunan tempat duduk yang mungkin adalah....', '144', '', '360', '', '720', '', '1260', '', '5040', '', 'C'),
(141, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630097.png', 'What is the main idea of the second paragraph?', 'The writer wanted to quit the competition ', '', 'The writer discussed the competition with judges', '', ' The writer practiced with classmates only', '', ' The write prepared and practiced the speech seriosly ', '', ' The writer was not interested in the competition', '', 'D'),
(142, 'MTK', 'XI', '', '', 'Banyaknya susunan kata baru yang dapat dibentuk dari huruf-huruf pada kata \"AGUSTUS\" adalah....', '360', '', '540', '', '720', '', '1020', '', '1260', '', 'E'),
(143, 'MTK', 'XI', '', '', 'Sebuah kedai minuman \"Segar Ceria\" memiliki persediaan bahan baku sebagai berikut:<br />\r\n•	Basis Minuman: Teh, Susu, dan Kopi.<br />\r\n•	Rasa Tambahan: Vanila, Cokelat, Karamel, dan Matcha.<br />\r\n•	Topping: Boba, Jelly, dan Keju.<br />\r\nSetiap menu baru diciptakan dengan memilih satu jenis basis minuman, satu rasa tambahan, dan satu jenis topping. Banyaknya variasi menu minuman baru yang dapat diciptakan oleh kedai tersebut adalah ....<br />\r\n', '10', '', '12', '', '24', '', '36', '', '48', '', 'D'),
(144, 'MTK', 'XI', '', '', 'Dalam sebuah ujian matematika, terdapat 10 soal yang tersedia. Setiap siswa diminta untuk memilih dan mengerjakan 7 soal saja. Namun, soal nomor 1 sampai 3 wajib dikerjakan oleh seluruh siswa. Banyaknya pilihan variasi soal yang dapat dikerjakan siswa tersebut adalah....', '120', '', '35', '', '21', '', '7', '', '4', '', 'B'),
(145, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630361.png', 'What is the purpose of the text?', 'To explain football rules', '', 'To compare football clubs', '', 'To retell the life and achievements of Lionel Messi', '', 'To entertain readers with a fictional story', '', 'To advertise football academies', '', 'C'),
(146, 'MTK', 'XI', '', '', 'Di dalam sebuah kantong terdapat 5 kelereng merah, 4 kelereng biru, dan 3 kelereng kuning. Dari kantong tersebut, akan diambil 3 kelereng sekaligus secara acak. Banyaknya kejadian terambilnya paling sedikit 2 kelereng merah adalah….', '35', '', '45', '', '70', '', '80', '', '90', '', 'D'),
(147, 'MTK', 'XI', '', '', 'Sebuah restoran sedang mengadakan promo paket hemat makan siang. Setiap pembeli dapat menyusun paket makanannya sendiri yang terdiri dari 1 jenis makanan utama dan 1 jenis minuman. Menu makanan utama yang tersedia adalah Ayam Bakar, Bebek Goreng, dan Nasi Goreng. Sementara itu, pilihan minuman yang tersedia adalah Es Teh, Jus Jeruk, Es Dawet, dan Air Mineral. Berdasarkan wacana tersebut, banyaknya seluruh anggota ruang sampel dari kombinasi paket makanan dan minuman yang dapat dipilih oleh pembeli adalah….', '12', '', '10', '', '8', '', '7', '', '4', '', 'A'),
(148, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630471.png', 'What medical condition did Messi have when he was 11 years old?', 'Heart disease	', '', 'Asthma', '', 'Eye disorder', '', 'Lung onfection', '', 'Growth hormone deficiency ', '', 'E'),
(149, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630569.png', 'The word “outstanding” in the third paragraph has a similar meaning to ...', ' Excellent ', '', 'Weak	', '', 'Average', '', 'Unlucky', '', 'Ordinary ', '', 'A'),
(150, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630736.png', 'What is the purpose of the announcement?', 'To describe English learning activities ', '', 'To inform students about an English Speech Contest ', '', ' To explain how to make speeches ', '', 'To invite students to a music competition ', '', 'To announce examination results', '', 'B'),
(151, 'Bahasa Inggris', 'X', '', 'soal_utama_1780630846.png', 'What can we conclude from the text?', 'Students do not need preparation for the contest ', '', 'Only teachers can join the event ', '', 'The competition aims to improve students’ speaking skills ', '', 'The contest will be held online ', '', 'Students can register after August 15', '', 'C'),
(152, 'MTK', 'XI', '', '', 'Di akhir sebuah seminar kesehatan, panitia membagikan doorprize menarik untuk para peserta yang beruntung. Panitia mengumpulkan nomor undian dari seluruh peserta ke dalam sebuah kotak. Diketahui total peserta yang hadir adalah 100 orang, yang terdiri dari 45 peserta pria dan 55 peserta wanita. Panitia akan mengambil satu nomor undian secara acak untuk menentukan pemenang hadiah utama. Berdasarkan wacana tersebut, peluang terambilnya nomor undian milik peserta pria adalah….', '', 'opsi_a_1780631033_17.png', '', 'opsi_b_1780631033_75.png', '', 'opsi_c_1780631033_15.png', '', 'opsi_d_1780631033_67.png', '', 'opsi_e_1780631033_77.png', 'A'),
(153, 'Bahasa Inggris', 'X', '', 'soal_utama_1780631045.png', 'What is the text mainly about?', 'School subjects', '', 'Family activities', '', 'The writer’s daily activities', '', ' Activities in the library ', '', ' Weekend activities', '', 'C'),
(154, 'Bahasa Inggris', 'X', '', 'soal_utama_1780631145.png', 'What is the main idea of the first paragraph?', 'The writer’s morning activities before school		', '', ' The writer’s favorite breakfast', '', ' The writer’s hobbies at home', '', 'The writer’s school subjects', '', 'The writer’s transportation problems', '', 'A'),
(155, 'MTK', 'XI', '', '', 'Dalam rangka memperingati Hari Kemerdekaan, OSIS sebuah SMA membuka seleksi untuk posisi pengibar bendera. Dari hasil seleksi tahap akhir, terpilih 6 siswa laki-laki dan 4 siswa perempuan yang memiliki kemampuan sama baiknya. Karena kuota yang terbatas, pembina OSIS akan memilih 3 siswa secara acak sekaligus dari 10 siswa tersebut untuk menjadi tim inti pengibar bendera. Berdasarkan wacana tersebut, peluang terpilihnya tim inti yang terdiri dari 2 siswa laki-laki dan 1 siswa perempuan adalah ….', '', 'opsi_a_1780631307_49.png', '', 'opsi_b_1780631307_31.png', '', 'opsi_c_1780631307_66.png', '', 'opsi_d_1780631307_17.png', '', 'opsi_e_1780631307_57.png', 'E'),
(156, 'Bahasa Inggris', 'X', '', 'soal_utama_1780631435.png', 'What does the writer do during break time?', 'Sleeps in the classroom				', '', 'Eats snacks and talks with friends		', '', 'Goes home', '', 'Cleans the school yard', '', 'Studies Mathematics only ', '', 'A'),
(157, 'MTK', 'XI', '', 'soal_utama_1780631560.png', ' ', '25 lampu', '', '50 lampu', '', '125 lampu', '', '1250 lampu', '', '2450 lampu', '', 'B'),
(158, 'Bahasa Inggris', 'X', '', 'soal_utama_1780631683.png', 'Look at the graph above. What is the purpose of the graph?', 'To compare students’ favorite subjects		', '', 'To explain how to study mathematics	', '', 'To describe a teacher’s daily activities', '', ' To advertise a new school program ', '', ' To tell students to join competitions', '', 'A'),
(159, 'MTK', 'XI', '', '', 'Sebuah perusahaan logistik nasional membuka lowongan kerja untuk posisi staf administrasi. Setelah melalui seleksi administrasi, para pelamar yang lolos dikelompokkan berdasarkan wilayah domisili mereka untuk mempermudah penempatan kerja. Data persentase pelamar yang lolos adalah sebagai berikut: 40% pelamar dari Wilayah A, 35% pelamar dari Wilayah B, dan sisanya dari Wilayah C. Tim HRD akan memilih satu berkas pelamar secara acak untuk melakukan wawancara pertama. Peluang terpilihnya pelamar yang berdomisili dari Wilayah B atau Wilayah C adalah….', '', 'opsi_a_1780631799_97.png', '', 'opsi_b_1780631799_66.png', '', 'opsi_c_1780631799_44.png', '', 'opsi_d_1780631799_67.png', '', 'opsi_e_1780631799_54.png', 'C'),
(160, 'Bahasa Inggris', 'X', '', 'soal_utama_1780631924.png', 'According to the graph, which of the following statements is TRUE?', 'Art is more popular than History.		', '', 'More students prefer Science than English.			', '', ' A total of 100 students were surveyed.', '', 'Mathematics is the least favorite subject.', '', 'Fewer students chose Art than any other subject.', '', 'E'),
(161, 'MTK', 'XI', '', '', 'Di sebuah SMK, setiap siswa kelas XI diwajibkan mengikuti setidaknya satu kegiatan ekstrakurikuler berbasis olahraga atau seni untuk mengembangkan bakat mereka. Berdasarkan data dari wali kelas XI-A yang memiliki total 40 siswa, tercatat sebanyak 22 siswa mengikuti ekstrakurikuler Basket, 18 siswa mengikuti ekstrakurikuler Musik, dan 6 siswa mengikuti kedua ekstrakurikuler tersebut. Pada saat jam istirahat, wali kelas akan memanggil satu siswa secara acak untuk menjadi perwakilan rapat kelas. Peluang terpilihnya siswa yang mengikuti ekstrakurikuler Basket atau Musik adalah ….', '', 'opsi_a_1780632046_10.png', '', 'opsi_b_1780632046_98.png', '', 'opsi_c_1780632046_52.png', '', 'opsi_d_1780632046_93.png', '', 'opsi_e_1780632046_13.png', 'B'),
(162, 'MTK', 'XI', '', '', 'Dua orang sahabat, Andi dan Budi, mengikuti ujian praktik berkendara untuk mendapatkan Surat Izin Mengemudi (SIM) C di Satpas Ditlantas. Ujian tersebut terdiri dari lintasan berbentuk huruf \'U\' dan angka \'8\'. Berdasarkan data statistik kelulusan di Satpas tersebut, peluang seorang peserta lulus ujian praktik pada kesempatan pertama adalah 0,8 untuk Andi dan 0,7 untuk Budi. peluang kejadian Andi lulus ujian praktik tetapi Budi tidak lulus pada kesempatan pertama adalah….', '0,56', '', '0,38', '', '0,24', '', '0,14', '', '0,12', '', 'C'),
(163, 'MTK', 'XI', '', '', 'Terdapat sebuah kotak yang berisi 6 bola merah dan 4 bola putih. Seorang siswa melakukan sebuah eksperimen dengan cara mengambil dua buah bola satu per satu secara acak tanpa pengembalian. Pada pengambilan pertama, siswa tersebut berhasil mengambil sebuah bola dan langsung mencatat warnanya tanpa memasukkannya kembali ke dalam kotak. Setelah itu, siswa tersebut melanjutkan pengambilan bola kedua. jika pada pengambilan pertama diketahui bola yang terambil adalah bola merah, peluang terambilnya bola putih pada pengambilan kedua adalah….', '', 'opsi_a_1780632451_39.png', '', 'opsi_b_1780632451_79.png', '', 'opsi_c_1780632451_57.png', '', 'opsi_d_1780632451_67.png', '', 'opsi_e_1780632451_33.png', 'D'),
(164, 'MTK', 'XI', '', '', 'Sebuah bank internasional menerapkan sistem keamanan ganda untuk memproteksi ruang penyimpanan brankas utamanya dari potensi pembobolan cyber. Sistem tersebut dilengkapi oleh dua modul pemindai independen yang bekerja secara bersamaan, yaitu Modul Pemindai Retina (Modul A) dan Modul Pemindai Sidik Jari (Modul B). Berdasarkan cetak biru teknologi tersebut, peluang Modul A gagal mendeteksi penyusup adalah 0,1, sedangkan peluang Modul B gagal mendeteksi penyusup adalah 0,15. Kegagalan salah satu modul tidak memengaruhi kinerja modul lainnya. Alarm utama bangunan hanya akan berbunyi jika setidaknya salah satu dari kedua modul tersebut berhasil mendeteksi adanya penyusup. Untuk menguji keandalan sistem ini secara berkala, tim IT bank melakukan simulasi peretasan dan penyusupan sebanyak 400 kali percobaan. Frekuensi harapan alarm utama berhasil berbunyi selama seluruh uji coba simulasi tersebut dilakukan adalah….', '6 kali', '', '60 kali', '', '260 kali', '', '340 kali', '', '394 kali', '', 'E'),
(165, 'Bahasa Inggris', 'X', '', 'soal_utama_1780633197.png', '\"The sales rose significantly in June.\"<br />\r\nWhich sentence has the closest meaning?', 'The sales stayed the same in June			', '', 'The sales dropped sharply in June			', '', ' The sales were unpredictable in June', '', 'The sales increased greatly in June', '', 'The sales ended in June', '', 'D'),
(166, 'Bahasa Inggris', 'X', '', 'soal_utama_1780633350.png', 'What is the main idea of the second paragraph?', 'Clara played with her cousins near the river', '', 'Clara’s grandmother explained the importance of the necklace ', '', 'Clara lost the necklace in the garden ', '', 'Clara’s parents talked in the living room ', '', 'Clara bought a necklace for her grandmother', '', 'B'),
(167, 'Bahasa Inggris', 'X', '', 'soal_utama_1780633480.png', 'Why did Clara feel nervous?', 'Because she fell into the river ', '', 'Because her parents were angry with her', '', 'Because she was the last person who saw the necklace', '', 'Because she wanted to go home early', '', 'Because her cousins laughed at her', '', 'C'),
(168, 'Bahasa Inggris', 'X', '', 'soal_utama_1780633728.png', 'What is the topic of the announcement?', 'A company sports competition				', '', ' A monthly office meeting ', '', 'A new office building', '', 'An employee farewell party ', '', ' A customer service training', '', 'B'),
(169, 'Bahasa Inggris', 'X', '', 'soal_utama_1780633865.png', 'What additional information will be shared after the meeting?', 'New office rules	', '', 'Company holiday schedules', '', 'New employee salaries', '', 'The new health insurance program', '', 'Office renovation plans ', '', 'D'),
(170, 'PAI', 'XI', '', '', 'Perhatikan arti Q.S. Al-Mā\'idah/5: 32 berikut !<br />\r\n “Barangsiapa membunuh seorang manusia tanpa alasan yang benar (bukan karena qisas   atau berbuat kerusakan, maka seakan - akan dia telah membunuh ... “. Berdasarkan kutipan arti yang tepat untuk melengkapi terjemahan tersebut adalah… . <br />\r\n', 'keluarganya sendiri', '', 'orang-orang kafir', '', 'seluruh manusia ', '', 'dirinya sendiri', '', 'para nabi', '', 'C'),
(171, 'PAI', 'XI', '', 'soal_utama_1780645583.png', 'Berdasarkan potongan ayat tersebut, perilaku yang paling tepat dalam kehidupan masyarakat yang majemuk adalah… .', 'mengikuti seluruh ritual ibadah umat lain demi menjaga tali persaudaraan.  ', '', 'menjauhi pergaulan dengan orang yang berbeda keyakinan agar iman tetap terjaga. ', '', 'menghargai keberadaan kelompok yang berbeda keyakinan tanpa harus mencela atau memaksakan kehendak. ', '', 'berdebat kusir dengan orang yang tidak seiman agar mereka segera mengubah keyakinannya. ', '', 'mengikuti  seluruh  kegiatan semua agama  yang ada demi menunjukan nilai toleransi', '', 'C'),
(172, 'PAI', 'XI', '', '', 'Di sebuah desa yang majemuk, terjadi perdebatan panas. Sehingga beberapa warga ingin melakukan tindak kekerasan fisik terhadap warga lain yang berbeda pendapat atau tidak sepaham. <br />\r\nBerdasarkan narasi tersebut, perilaku yang tepat yang sesuai dengan Q.S. Al-Maidah / 5:32, adalah ... .<br />\r\n', 'melaporkan kepada pihak berwajib dan menolak kekerasan.', '', 'menyarankan untuk membunuh saja agar cepat selesai ', '', 'mendukung salah satu pihak yang lebih kuat. ', '', 'diam saja karena takut terlibat konflik. ', '', 'ikut serta agar dianggap setia kawan', '', 'A'),
(173, 'PAI', 'XI', '', 'soal_utama_1780645991.png', '.', 'orang-orang yang berbuat kerusakan  ', '', 'orang-orang yang melampaui batas   ', '', 'orang-orang yang bertoleransin', '', 'orang-orang yang bertakwa', '', 'orang-orang yang beriman', '', 'A'),
(174, 'PAI', 'XI', '', 'soal_utama_1780646247.png', 'Berdasarkan kutipan ayat tersebut, hikmah utama dari adanya toleransi sesama muslim adalah... .', 'Menyerahkan seluruh urusan duniawi kepada kelompok yang mayoritas ', '', 'Menganggap semua agama sama benarnya agar tidak terjadi konflik sosial  ', '', 'Kesadaran untuk saling menghormati keyakinan dan bertanggung jawab masing-masing ', '', 'Kewajiban untuk memaksa orang lain agar memiliki keyakinan yang sama demi kedamaian ', '', 'Keharusan untuk menjauhi dan tidak berinteraksi dengan kelompok yang berbeda keyakinan', '', 'C'),
(175, 'PAI', 'XI', '', '', 'Di sebuah desa, hidup masyarakat yang majemuk. Pak Amir berkeyakinan Islam, sedangkan Pak Stefanus berkeyakinan Kristen. Suatu hari, Pak Amir diundang dalam perayaan hari besar Pak Stefanus. Meskipun pak Amir berteman baik dan menghormati, namun tidak mengikuti ritual ibadahnya. <br />\r\n<br />\r\nBerdasarkan narasi tersebut Sikap Pak Amir yang mencerminkan pengamalan  Q.S. Yunus ayat 41, yaitu ... ', 'menjauhi Pak Stefanus agar tidak terpengaruh', '', 'bagiku pekerjaanku dan bagimu pekerjaanmu  ', '', 'mengikuti ritual agar dianggap toleran', '', 'menganggap semua agama sama saja ', '', 'memaksa Pak Stefanus masuk Islam', '', 'B'),
(176, 'PAI', 'XI', '', '', '\"Akibat rasa egois (tidak mau mengalah) dan tidak adanya toleransi, sebuah komunitas mengalami perpecahan. Perkelahian terjadi dan menyebabkan hilangnya nyawa manusia serta kerusakan fasilitas umum.”<br />\r\n  Berdasarkan narasi tersebut, perilaku yang bertentangan dengan ajaran Q.S. Yunus / 10 : 40 - 41 dan Q.S. Al – Maidah / 5 : 32 adalah ... .', 'melakukan aksi kekerasan dan menolak perbedaan ', '', 'mengadakan kegiatan dialog antarumat beragama', '', ' bertanggung jawab atas setiap amal perbuatan', '', 'menghargai setiap keyakinan orang lain ', '', 'saling membantu dalam kebaikan', '', 'B'),
(177, 'PAI', 'XI', '', 'soal_utama_1780646589.png', '.', 'dan jika mereka mendustakanmu', '', 'bagi mereka pekerjaanya', '', 'jika mereka beriman', '', 'pekerjaanku', '', 'katakanlah', '', 'A'),
(178, 'PAI', 'XI', '', 'soal_utama_1780646717.png', '.', 'membunuh hewan', '', 'membunuh manusia', '', 'membunuh orang lain ', '', 'membunuh tumbuhan', '', 'membunuh diri sendiri', '', 'B'),
(180, 'PAI', 'XI', '', 'soal_utama_1780648440.png', ' Berdasarkan kutipan ayat tersebut, kesimpulan yang tepat adalah ... .', 'larangan membunuh orang tanpa alasan yang benar ', '', 'anjuran berbuat baik  terhadap  sesama muslim ', '', 'perintah untuk meningkatkan persaudaraan ', '', 'larangan  membuka aib orang lain  ', '', 'larangan berbuat zina muhshon', '', 'A'),
(181, 'PAI', 'XI', '', 'soal_utama_1780648601.png', 'Berdasarkan tabel tersebut arti pasangan yang tepat adalah ... .', '1=a, 2=b, 3=c, 4=d, 5=e	', '', '1=b, 2=e, 3=a, 4=c, 5=d', '', '1=c, 2=a, 3=b, 4=e, 5=d', '', '1=e, 2=a, 3=d, 4=b, 5=c', '', '1=c, 4=e, 3=b, 2=a, 5=d', '', 'E'),
(182, 'PAI', 'XI', '', 'soal_utama_1780648730.png', 'Berdasarkan tabel tersebut, hukum tajwid yang benar adalah ... .', '1, 2, dan 3', '', '1, 3, dan 4', '', '1, 3 dan 5', '', '2, 3 dan 5', '', '2, 3 dan 4', '', 'B'),
(183, 'PAI', 'XI', '', 'soal_utama_1780651270.png', 'Berdasarkan kutipan ayat tersebut, bila ada non-muslim yang sedang berdoa sesuai agamanya didalam kelas maka sikap seorang muslim yang tepat adalah… .<br />\r\n', 'melarang karena berbeda dengan Islam', '', 'mengajaknya ikut shalat agar masuk Islam', '', ' islam melarang berteman dengan non muslim', '', ' mengejek do’a nya karena tidak ada yang dikabulkan ', '', 'membiarkannya berdoa dan menghormati keyakinannya', '', 'E'),
(184, 'PAI', 'XI', '', 'soal_utama_1780651444.png', 'Berdasarkan kutipan ayat tersebut, hukum bacaan tajwid yang benar pada kata yang digarisbawahi adalah ... .', 'Mad thobi’i, gunnah, mad wajib munfasil dan alif qomariah ', '', 'Mad thobi’i, gunnah, alif qomariah dam mad iwad ', '', 'Mad thobi’i, gunnah, alif syamsiah dan mad iwad ', '', 'Ikhfa, mad wajib munfasil, dan mad thobi’i ', '', 'Izhar, mad thobi’i, gunnah dan mad thobi;i', '', 'C'),
(185, 'PAI', 'XI', '', 'soal_utama_1780651905.png', 'Berdasarkan hadis tersebut,  arti lafaz hadits untuk melengkapi hadit tersebut  yang tepat adalah ... .    ', 'Selama tidak menumpahkan darah tanpa hak', '', 'Selama tidak menyakiti kepada fakir miskin', '', 'Selama tidakberpuasa bulan Ramadan  ', '', 'Selama tidak shalat lima waktu', '', 'membunuh binatang dilarang', '', 'A'),
(186, 'PAI', 'XI', '', '', 'Menjaga kehormatan adalah sikap menahan diri dari segala perbuatan yang dapat menjatuhkan harga diri, martabat, dan kemuliaan diri sebagai manusia dan sebagai muslim. Menjaga kehormatan dapat dilakukan dengan menjauhi perbuatan zina, perkataan kotor, meminta-minta, dan perbuatan tercela lainnya. <br />\r\n Berdasarkan narasi tersebut, arti  iffah dalam Islam yang tepat adalah… . <br />\r\n', 'menahan diri dari suatu yang merusak harga diri ', '', 'menjaga harta agar tidak dicuri orang ', '', 'menjaga kesehatan dengan olahraga ', '', 'menjaga makanan agar tidak basi ', '', 'menjaga dari keselamatan', '', 'A'),
(187, 'PAI', 'XI', '', '', 'Menjaga kehormatan adalah akhlak yang harus dimilik bagi setiap pribadi orang yang beriman, terdapat tiga istilah dalam Islam yang berkaitan dengan  menjaga kehormatan yaitu ... .', 'istikhfaf, ikram, dan hasanah  ', '', 'muru’ah, dan mudawamah', '', 'iffah, ibtibra, dan istihsan   ', '', 'ihsan,’izzah dan muru’ah       ', '', 'iffah, ‘izzah dan muru’ah', '', 'E'),
(188, 'PAI', 'XI', '', '', 'Di grup whatsapp  kelas XI SMK, ada yang mengirim foto teman perempuan yang sedang tidak pakai jilbab di rumahnya. Jika  anda sebagai ketua kelas  sikap yang tepat untuk menjaga kehormatan teman tersebut adalah... .', 'ikut memviralkan fotonya', '', 'ikut menyimpan karena lucu ', '', 'membiarkan saja karena bukan foto kita ', '', 'meneruskan ke grup lain agar pelaku jera ', '', 'menegur dan mengingatkan menyebar aurat orang lain', '', 'E'),
(189, 'PAI', 'XI', '', '', 'Rasulullah Saw bersabda   yang artinya: “Barangsiapa menjaga kemaluannya dan lisannya, aku jamin surga baginya.” (H.R. Bukhari), <br />\r\nBerdasarkan narasi tersebut, manfaat menjaga kehormatan bagi diri sendiri adalah... .<br />\r\n', 'terjaga harga diri, martabat, dan terhindar dari penyakit ', '', 'disukai semua orang meski berbuat salah ', '', 'bebas dari semua masalah hidup', '', ' menjadi kaya raya dan terkenal ', '', 'hidupnya tidak terarah', '', 'A'),
(190, 'PAI', 'XI', '', '', 'Orang yang hatinya ikhlas akan merasakan  ketenangan  karena semua perbuatan cukup hanya Allah yang tahu. <br />\r\nBerdasarkan deskripsi tersebut, keutamaan orang yang ikhlas adalah... .<br />\r\n', 'tidak berharap pujian dan balasan manusia', '', 'selalu mendapat hadiah dari orang lain', '', 'tidak pernah dimarahi guru', '', 'nilainya selalu bagus', '', 'selalu damai', '', 'A'),
(191, 'PAI', 'XI', '', '', 'Di era digital, banyak siswa yang  membuat konten “spill gaji” dan flexing barang branded agar FYP. Dina memilih tidak ikut tren itu karena malu jika kontennya menimbulkan riya dan membuat orang lain iri. Berdasarkan narasi tersebut, keutamaan sifat malu yang dimiliki Dina adalah... .', 'membuat Dina jadi kuper dan ketinggalan zaman', '', ' menghambat personal branding di dunia kerja', '', 'menjaga hati dari penyakit riya dan hasad', '', 'agar dimarahi guru BK dan guru agama.', '', 'agar tidak dimarahi guru BK', '', 'A'),
(192, 'PAI', 'XI', '', '', 'Siti adalah siswi  SMK jurusan Tata Boga yang memiliki  usaha kue yang sukses. Namun  Ia tetap hidup sederhana, dan sering bersedekah,  dan ia  berkata: \"Saya berbisnis agar bisa lebih banyak bersedekah. Berdasarkan narasi tersebut, tingkatan zuhud  yang tepat untuk Siti adalah... .', 'zuhud khawasul khawas karena hatinya kosong dari dunia ', '', 'zuhud khawas karena dunia jadi sarana akhirat ', '', 'zuhud awam karena masih cinta dunia', '', ' tidak zuhud karena masih kaya', '', 'zuhud karena masih kaya', '', 'C'),
(194, 'PAI', 'XI', '', '', 'Bu Ani sedang melaksanakan pembelajaran didalam kelas, akan tetapi ada salah satu  siswa  yang asik bermain HP. Kemudian bu Ani menegur siswa tersebut, setelah ditegur, dia menjawab: \"Saya tetap paham kok, Bu\". Berdasarkan narasi tersebut, sikap siswa yang berkaitan dengan makna adab yang tepat adalah… . ', 'Melanggar tata tertib sekolah', '', 'Main HP membuat nilai turun', '', 'Guru jadi tidak semangat mengajar', '', 'Tidak melanggar tata tertib sekolah.', '', 'Adab lebih utama dari pada  ilmu itu sendiri', '', 'A'),
(195, 'PAI', 'XI', '', '', 'Seorang siswa SMK jurusan DKV ingin membuat design  portofolio karena ingin mengenalkan fortofolionya kepada  klien. Berdasarkan narasi tersebut, jenis media sosial yang tepat adalah... . ', 'LinkedIn &amp; Instagram, karena untuk pamer karya visual', '', 'Facebook, karena penggunanya paling banyak ', '', 'WhatsApp, karena mudah kirim file ke klien', '', ' Twitter/X, karena bisa viral dengan cepat ', '', 'Facebook, karena penggunanya sedikit.', '', 'A'),
(196, 'PAI', 'XI', '', '', 'Tim LKS sekolahmu menang dalam mengikuti LKS, lalu kalian live TikTok euforia sambil joget dan membuka aurat, dan menggunakan akun sekolah. Berdasarkan narasi tersebu, sikap  yang melanggar akhlak dalam bermedsos itu dilarang karena... .', 'Tidak seru, harusnya joget lebih heboh', '', 'Mencoreng nama baik sekolah.', '', 'Live di luar jam sekolah.', '', 'Live saat jam sekolah', '', 'Lupa pakai filter', '', 'B'),
(197, 'PAI', 'XI', '', '', 'Guru BK menemukan banyak siswa SMK yang depresi karena mereka telah membanding-bandingkan hidupnya di medsos kepada orang lain. Salah satu penyebab paling mendasar dari  perilaku tersebut karena \"mengkonsumsi medsos tanpa filter\".Berdasarkan narasi tersebut, penyebab bermedsos yang salah adalah... .', 'medsos tidak ada tombol &quot;unfollow&quot;', '', 'hilangnya sifat zuhud &amp; qana’ah ', '', 'wifi sekolah terlalu kencang', '', 'medsos ada tombol follow.', '', 'influencer terlalu kaya', '', 'B'),
(198, 'PAI', 'XI', '', '', 'Rani siswi SMK jurusan  RPL, ia  ingin mengomentari  sikap toxic di postingan teman, tapi urung dilakukan karena ingat bahwa Allah Maha melihat chat meski akun ini anonim. Berdasakan narasi tersebut, makna  muraqabah dalam bermedsos yaitu... .', 'takut tidak dapat like', '', 'menghindari UU ITE', '', 'takut ketahuan admin grup ', '', 'selalu mendapat like dari siapapun.', '', 'kesadaran bahwa Allah Maha Melihat', '', 'E'),
(199, 'PAI', 'XI', '', '', 'Pada saat musim hujan, terdapat  beberapa siswa yang terkena musibah banjir. Sebagian pengurus OSIS melakukan penggalangan  donasi via Instagram dengan laporan transparan pakai Google Sheet. Berdasarkan narasi tersebut, contoh bermedsos yang tepat, adalah...  .', 'cari panggung', '', 'biar sekolah viral', '', 'tugas warga sekolah yang aktif.', '', 'tugas OSIS yang seharusnya begitu.', '', 'sikap saling tolong-menolong &amp; amanah ', '', 'E'),
(200, 'PAI', 'XI', '', '', 'Diantara penyebab  maraknya  judi online karena adanya  iklan yang muncul di medsos sehingga mereka tertarik ingin mencobanya. <br />\r\nBerdasarkan narasi tersebut, solusi yang  tepat jika ditinjau dari prinsip saddudz dzaro’ah menutup jalan maksiat adalah... .<br />\r\n', 'uninstall semua aplikasi medsos  ', '', 'lapor ke keluarga saja supaya tertutup. ', '', 'lapor polisi saja kalau sudah kecanduan', '', 'pinjam uang teman untuk modal judi lagi', '', 'aktifkan &quot;filter konten sensitif&quot;, unfollow akun judi,', '', 'E'),
(201, 'PAI', 'XI', '', '', 'Pernikahan bukan sekadar ikatan cinta, melainkan komitmen sakral, kepercayaan, dan kesetiaan untuk membangun kehidupan bersama serta menghadapi tantangan hidup.<br />\r\nBerdasarkan narasi tersebut yang merupakan makna pernikahan menurut UU No. 1 tahun 1974 adalah… .<br />\r\n', 'Perkawinan adalah ikatan lahir batin antara seorang pria dan seorang wanita sebagai suami istri dengan tujuan membentuk keluarga atau rumah tangga yang bahagia dan kekal berdasarkan Ketuhanan Yang Maha Esa. ', '', 'Perkawinan adalah penyatuan antara laki-laki dan perempuan atas dasar suka sama suka', '', 'Perkawinan adalah ikatan lahir batin antara seorang pria dan wanita untuk hidup Bersama dan memperoleh keturunan ', '', 'Perkawinan adalah ikatan lahir batin antara pria dan wanita sebagai suami dan istri yang dihadiri oleh keluarga kedua mempelai.', '', 'Perkawinan adalah sarana menyatukan pria dan wanita yang sudah saling mencintai dan direstui oleh kedua orang tua. ', '', 'A'),
(202, 'PAI', 'XI', '', '', 'Pernikahan bukan sekadar penyatuan dua orang, melainkan perjalanan panjang yang membutuhkan fondasi kokoh untuk tetap tegak. Islam telah mengajarkan kepada kita gar tidak salah dalam memilih pasangan agar sebuah keluarga dapat menjadi keluarga yang samawa. <br />\r\nBerdasarkan narasi tersebut, yang bukan faktor penting dalam memilih pasangan adalah… .<br />\r\n', 'memilih pasangan yang baik keturunannya', '', 'memilih pasangan yang cantik wajahnya', '', ' memilih pasangan yang banyak relasinya ', '', 'memilih pasangan yang baik agamanya ', '', 'memilih pasangan yang baik hartanya', '', 'C'),
(203, 'PAI', 'XI', '', '', 'Tono adalah seorang pemuda yang tidak memiliki pekerjaan, selain itu emosi yang dimilikinya juga terlihat belum stabil, tetapi Ia ingin sekali menikah karena melihat teman-teman sebayanya sudah menikah. <br />\r\nBerdasarkan narasi tersebut, hukum pernikah bagi Tono adalah… . ', 'Wajib ', '', 'Haram', '', 'Makruh', '', 'Sunnah', '', 'Mubah', '', 'C'),
(204, 'PAI', 'XI', '', '', '34.	Perhatikan pernyataan berikut ini !<br />\r\n1.	Kekerasan dalam rumah tangga (KDRT)<br />\r\n2.	Ketidaksiapan dalam hal finansial (ekonomi)<br />\r\n3.	Adanya rasa saling percaya dan menghormati<br />\r\n4.	Komunikasi yang baik <br />\r\n5.	Judi online dan penelantaran anak <br />\r\nBerdasarkan pernyataan tesebut, penyebab terjadinya perceraian ditunjukan oleh nomor… .', '1, 2 dan 3', '', '2, 4 dan 5', '', '1, 4 dan 5', '', '1, 2 dan 5', '', '3, 4 dan 5 ', '', 'D'),
(205, 'PAI', 'XI', '', '', 'Pak Rodi adalah seorang pedagang kecil dengan penghasilan yang tidak menentu, berbeda dengan saudaranya Toni yang merupakan pengusaha besar. meskipun penghasilannya tidak menentu ia dan istrinya tetap hidup rukun dan harmonis tanpa mengeluh dengan meributkan kondisi ekonomi rumah tangga yang dapat merusak hubungan rumah tangga mereka. Berdasarkan narasi tersebut, yang bukan merupakan nilai yang dapat diambil adalah… .', 'Rasa Syukur (Qana’ah) ', '', 'Kematangan Emosional', '', 'Kesetiaan dalam Suka dan Duka.', '', 'Kesederhanaan (Hidup Bersahaja) ', '', 'Mengeluh dan menyalahkan keadaan atas ketidakpastian penghasilan', '', 'E'),
(206, 'PAI', 'XI', '', '', 'Sejarah perkembangan Islam secara umum dibagi menjadi tiga periode besar, yaitu periode klasik, pertengahan, dan modern. <br />\r\nBerdasarkan narasi tersebut, periode perkembangan Islam pada masa modern dimulai pada tahun... .<br />\r\n', '650 M – 1250 M	', '', '1250 M – 1500 M', '', '1250 M – 1800 M', '', '1500 M – 1800 M', '', '1800 M – sekarang', '', 'E'),
(207, 'PAI', 'XI', '', '', 'Jamaluddin al-afghani adalah salah satu tokoh pembaharu islam dari afganistan. Diantara ide pemikiran beliau adalah paham yang bertujuan mempersatukan seluruh umat Islam di dunia. Berdasarkan deskripsi tersebut, paham mempersatukan seluruh umat Islam di dunia dikenal dengan… .', 'Nasionalisme', '', 'pan Islamisme', '', 'ukhuwah Islamiyah ', '', 'ukhuwah wathoniyah', '', 'ukhuwah nasionalisme', '', 'B'),
(208, 'PAI', 'XI', '', '', 'Tokoh pembaharu Islam di Indonesia yang mempertahankan ajaran ahlus sunnah wal jamaah dan  dalam menerapkan konsep pendekatan kemasyarakatan yang dijadikan landasan dalam bersikap ketika berinteraksi antar sesamanya dan merupakan pendiri organisasi Nahdhatul Ulama.<br />\r\nBerdasarkan narasi tersebut, tokoh pendiri organisasi Nahdhatul Ulama adalah… .<br />\r\n', 'KH. Moh. Nasir', '', 'KH. Soleh Darat ', '', 'KH. Ahmad Dahlan ', '', 'KH. Hasyim Asy’ari', '', 'Pangeran Diponegoro', '', 'D'),
(209, 'PAI', 'XI', '', '', 'Perkembangan islam pada masa modern khususnya di Indonesia tidak terlepas dari peran para ulama dalam menyampaikan ide pemikirannya. <br />\r\nBerdasarkan narasi tersebut yang bukan ide pemikiran KH. Hasyim Asy’ari dalam pembaharuan islam di Indonesia adalah… .', 'mengeluarkan resolusi jihad ', '', 'menolak campur tangan kolonial ', '', 'mendirikan organisasi kemasyarakatan ', '', ' memperkuat pendidikan islam melalui pesantren ', '', 'pemisahan antara agama dan urusan kebangsaan/negara', '', 'E'),
(210, 'PAI', 'XI', '', '', 'Salah satu tokoh pembaru Islam dari India, Sayyid Ahmad Khan, melakukan modernisasi dengan mendirikan Aligarh Muslim University. Pokok pemikiran utama beliau dalam memajukan umat Islam di India.<br />\r\nBerdasarkan narasi tersebut, pokok pemikiran utama Sayyid Ahmad Khan dalam memajukan umat Islam di India. adalah... .', 'Mengajak umat Islam untuk memisahkan urusan agama dari urusan politik (Sekularisme) ', '', 'Menolak segala bentuk penafsiran Al-Qur’an yang menggunakan pendekatan akal atau rasio ', '', 'Menyerukan jihad fisik secara terus-menerus untuk mengusir penjajah Inggris dari tanah India   ', '', 'Melarang umat Islam mempelajari bahasa Inggris karena dianggap sebagai bahasa kaum kafir  ', '', 'Meyakini kemajuan Islam dicapai dengan menguasai ilmu pengetahuan dan teknologi Barat', '', 'E'),
(211, 'PAI', 'XI', '', '', 'Salah satu tokoh pembaru Islam dari India, Sayyid Ahmad Khan, melakukan modernisasi dengan mendirikan Aligarh Muslim University. Pokok pemikiran utama beliau dalam memajukan umat Islam di India.<br />\r\nBerdasarkan narasi tersebut, pokok pemikiran utama Sayyid Ahmad Khan dalam memajukan umat Islam di India. adalah... .', '', '', '', '', '', '', '', '', '', '', 'E'),
(212, 'Bahasa Inggris', 'X', '', 'soal_utama_1780678064.png', 'The word “valuable” in the last paragraph has a similar meaning to ...', 'Useless', '', 'Expensive', '', 'Important', '', 'Difficult', '', 'Strange', '', 'C'),
(213, 'Bahasa Inggris', 'XI', 'In this section of the test, you will have the chance to show how well you understand spoken English. There are four parts to this section with special directions for each. part.<br />\r\n<br />\r\n<br />\r\n<br />\r\nPART I - PICTURES<br />\r\n<br />\r\n<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your<br />\r\n<br />\r\ntest book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  <br />\r\n<br />\r\n', 'soal_utama_1780707581.jpg', 'Look at the picture marked number 2 in your textbook.  ', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'B'),
(214, 'Bahasa Inggris', 'XI', 'In this section of the test, you will have the chance to show how well you understand spoken English. There are four parts to this section with special directions for each. part.<br />\r\n<br />\r\n<br />\r\nPART I - PICTURES<br />\r\n<br />\r\n<br />\r\nDirections: For each question in this part, you will hear four statements about a picture in your<br />\r\n<br />\r\ntest book. When you hear the statements, you must select the one statement that best describes what you see in the picture. Then find the number of the question on your answer sheet and mark your answer. The statements will not be printed in your test book and will be spoken only one time.  ', 'soal_utama_1780707679.jpg', 'Look at the picture marked number 3 in your textbook.', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'A'),
(215, 'Bahasa Inggris', 'XI', 'PART II – QUESTIONS RESPONSES<br />\r\n<br />\r\nDirections:  You will hear a question or statement and three responses spoken in English.<br />\r\nThey will not be printed in your test book and will be spoken only one time. Select<br />\r\nthe best response to the question or statement and mark the letter (A), (B), or (C)<br />\r\non your answer sheet.<br />\r\n<br />\r\nNow let’s begin with question number four (4) on your test paper.<br />\r\n', '', 'Mark your answer on your answer sheet', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'A');
INSERT INTO `soal` (`id`, `mata_pelajaran`, `kelas`, `deskripsi`, `gambar`, `pertanyaan`, `opsi_a`, `gambar_a`, `opsi_b`, `gambar_b`, `opsi_c`, `gambar_c`, `opsi_d`, `gambar_d`, `opsi_e`, `gambar_e`, `kunci_jawaban`) VALUES
(216, 'Bahasa Inggris', 'XI', 'PART II – QUESTIONS RESPONSES<br />\r\n<br />\r\n<br />\r\n<br />\r\nDirections:  You will hear a question or statement and three responses spoken in English.<br />\r\n<br />\r\nThey will not be printed in your test book and will be spoken only one time. Select<br />\r\n<br />\r\nthe best response to the question or statement and mark the letter (A), (B), or (C)<br />\r\n<br />\r\non your answer sheet.<br />\r\n<br />\r\n<br />\r\n<br />\r\nNow let’s begin with question number four (4) on your test paper.', '', 'Mark your answer on your answer sheet', 'A', '', 'B', '', 'C', '', 'D', '', 'E', '', 'C'),
(217, 'Bahasa Inggris', 'XI', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\n<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\n<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\n<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\n<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\n<br />\r\nspoken only one time.<br />\r\n<br />\r\n<br />\r\n<br />\r\nNow, let’s begin with question number seven (6) on your test paper.', '', 'What is Sarah\'s definite plan for next month according to the dialogue?', 'She is going to rent a scooter for a month.', '', 'She will move to Bali permanently.', '', 'She plans to work in a travel agency in Bali', '', 'She is going to explore Bali by foot.', '', 'She is going to visit her grandmother', '', 'E'),
(218, 'Bahasa Inggris', 'XI', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\n<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\n<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\n<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\n<br />\r\nspoken only one time.<br />\r\n<br />\r\n<br />\r\n<br />\r\nNow, let’s begin with question number seven (6) on your test paper.<br />\r\n<br />\r\n', '', 'What can be inferred about Riko’s opinion?', 'He thinks the color is too bright', '', 'He believes the dress is too large', '', 'He thinks the dress is uncomfortable to wear.', '', 'He wants Anna to buy a different color', '', 'He dislikes the style of the dress.', '', 'C'),
(219, 'Bahasa Inggris', 'XI', 'PART III – SHORT CONVERSATION<br />\r\n<br />\r\nDirections: You will hear some conversations between two or more people. You will be asked<br />\r\n<br />\r\nto answer one questions about what the speakers say in each conversation. Select<br />\r\n<br />\r\nthe best response to each question and mark the letter (A), (B), (C), or (D) on your<br />\r\n<br />\r\nanswer sheet. The conversations will not be printed in your test book and will be<br />\r\n<br />\r\nspoken only one time.<br />\r\n<br />\r\n<br />\r\n<br />\r\nNow, let’s begin with question number seven (6) on your test paper.<br />\r\n<br />\r\n', '', 'How were the invitation letters sent?', 'Through email', '', 'By a courier service.', '', 'Through the post office.', '', 'Delivered by hand.', '', 'By the manager himself.', '', 'C'),
(220, 'Bahasa Inggris', 'XI', 'PART IV – SHORT TALKS<br />\r\n<br />\r\nDirections: You will hear some talks given by a single speaker. You will be asked to answer two questions about what the speaker says in each talk. Select the best response to each question and mark the letter (A), (B), (C), or (D) on your answer sheet. The talks will not be printed in your test book and will be spoken only one time.  <br />\r\n<br />\r\nNow let’s begin with the following short talk.<br />\r\n<br />\r\n', '', 'According to the instructions, what must be done before turning the camera on?', 'Looking through the viewfinder.', '', 'Choosing the &#039;Auto&#039; mode.', '', 'Pressing the shutter button halfway.', '', 'Inserting the battery and the SD card.', '', 'Finding an object to photograph.', '', 'D'),
(221, 'Bahasa Inggris', 'XI', 'PART IV – SHORT TALKS<br />\r\n<br />\r\nDirections: You will hear some talks given by a single speaker. You will be asked to answer two questions about what the speaker says in each talk. Select the best response to each question and mark the letter (A), (B), (C), or (D) on your answer sheet. The talks will not be printed in your test book and will be spoken only one time.  <br />\r\n<br />\r\n<br />\r\n<br />\r\nNow let’s begin with the following short talk.<br />\r\n<br />\r\n', '', 'Why does the user need to press the shutter button halfway before taking the picture?', 'To save the battery power.', '', 'To turn off the LCD screen', '', 'To ensure the SD card is working', '', 'To change the camera mode to &#039;Auto&#039;', '', 'To allow the camera to focus on the object.', '', 'E'),
(222, 'Bahasa Inggris', 'XI', 'B. READING SECTION<br />\r\n<br />\r\n<br />\r\nIn this part of the test you will have the chance to show how well you understand written English. There are four parts to this section, with special directions for each part.<br />\r\n<br />\r\n<br />\r\nPART V. Directions: <br />\r\n<br />\r\n<br />\r\nFrom questions 11 – 19, four clauses/sentences, marked (A), (B), (C), (D) or (E), are given beneath each incomplete dialog. Choose the one clause/sentence that best completes the dialog. Then, on your answer sheet, find the number of the question and mark your answer', '', 'Rina	: \"Have you tried the food at the new Italian restaurant on Mawar Street?\"<br />\r\nDodi	: \"Yes, I went there with my family last night.\"<br />\r\nRina    : \"What do you think about the taste of the pasta?\"<br />\r\nDodi	: “….... It was the best spaghetti I’ve ever had in this city.\"<br />\r\nRina	: \"I agree with you. The sauce was very fresh and authentic.\"<br />\r\nDodi	: \"We should go there together sometime next week.\"', 'I don&#039;t like Italian food', '', 'I think the price was too expensive.', '', 'I&#039;m not sure about the location', '', 'I don&#039;t think I will go back there.', '', 'In my opinion, it was delicious', '', 'E'),
(223, 'Bahasa Inggris', 'XI', 'B. READING SECTION<br />\r\n<br />\r\nIn this part of the test you will have the chance to show how well you understand written English. There are four parts to this section, with special directions for each part.<br />\r\n<br />\r\nPART V. Directions: <br />\r\n<br />\r\nFrom questions 11 – 19, four clauses/sentences, marked (A), (B), (C), (D) or (E), are given beneath each incomplete dialog. Choose the one clause/sentence that best completes the dialog. Then, on your answer sheet, find the number of the question and mark your answer', '', 'Receptionist	: \"Good morning, Pratama Corporation. How may I help you?\"<br />\r\nCaller	        : \"Hello, I’m Mr. Hadi from Global Tech. May I speak to Mr. Anwar, please\"<br />\r\nReceptionist	: \"I’m sorry, Sir. Mr. Anwar is in a meeting at the moment.\"<br />\r\nCaller	        : \"Oh, I see. .......\"<br />\r\nReceptionist	: \"Of course, Sir. I will write it down for him. May I have your phone number?\"<br />\r\nCaller	: \"Yes, it’s 0812-3456-789. Please tell him to call me back as soon as possible.\"', 'Can I leave a message, please?', '', 'Would you like to call him later?', '', 'Should I put you on hold?', '', 'Could you tell me where he is?', '', 'May I know who is calling?', '', 'A'),
(224, 'Bahasa Inggris', 'XI', 'B. READING SECTION<br />\r\n<br />\r\nIn this part of the test you will have the chance to show how well you understand written English. There are four parts to this section, with special directions for each part.<br />\r\n<br />\r\nPART V. Directions: <br />\r\n<br />\r\nFrom questions 11 – 19, four clauses/sentences, marked (A), (B), (C), (D) or (E), are given beneath each incomplete dialog. Choose the one clause/sentence that best completes the dialog. Then, on your answer sheet, find the number of the question and mark your answer', '', 'Mr. Andre: \"We’re having a dinner party at our place tonight to celebrate my promotion.\"<br />\r\nMr. Ian 	: \"Congratulations on your promotion! That’s wonderful news.\"<br />\r\nMr. Andre: \"Thank you. …....\"<br />\r\nMr. Ian	: \"I’d be delighted to attend. What time should we arrive?\"', 'Would you like to join us?', '', 'Do you want to work tonight?', '', 'Can you help me cook?', '', 'Why are you so happy?', '', 'I don&#039;t think you should come.', '', 'A'),
(225, 'Bahasa Inggris', 'XI', 'PART V. Directions: <br />\r\nFrom questions 11 – 19, four clauses/sentences, marked (A), (B), (C), (D) or (E), are given beneath each incomplete dialog. Choose the one clause/sentence that best completes the dialog. Then, on your answer sheet, find the number of the question and mark your answer', '', '14.	Tono	: \"Do you have any special routine for your Sunday mornings?\"<br />\r\nLia	: \"Not really. I just use that time to relax and stay away from school books.\"<br />\r\nTono: \"Do you usually stay at home all day?\"<br />\r\nLia	: \"….... I need to move my body and get some fresh air.\"<br />\r\nTono: \"That’s a healthy habit! I usually join the car-free day too.\"<br />\r\nLia	: \"Maybe we can meet and run together next Sunday morning.\"<br />\r\n', 'I never leave my bedroom.', '', 'I rarely exercise on weekends.', '', 'I often sleep until the afternoon.', '', 'I always go jogging in the park.', '', 'I sometimes clean the entire house.', '', 'D'),
(226, 'Bahasa Inggris', 'XI', '', '', 'Budi	: \"Do you think the Math test was difficult yesterday?\"<br />\r\nAni	: \"Yes, it was quite hard for me. How about the English test?\"<br />\r\nBudi	: \"For me, the Math test was ….... than the English test.\"<br />\r\nAni	: \"I agree. I got a better score in English than in Math.\"<br />\r\nBudi	: \"Me too. Let\'s study harder for the next Math exam.\"<br />\r\n', 'difficult', '', 'difficultest', '', 'as difficult as', '', 'more difficult', '', 'most difficult', '', 'D'),
(227, 'Bahasa Inggris', 'XI', '', '', 'Tono: \"I have a terrible headache, but I have so much work to do.\"<br />\r\nLia	: \"If I were you, .... a doctor and take a rest.\"<br />\r\nTono: \"I want to, but the deadline is this afternoon.\"<br />\r\nLia	: \"Health is more important than work, Tono.\"<br />\r\n', 'I visit', '', 'I will visit', '', 'I visited', '', 'I would visit', '', 'I would have visited', '', 'D'),
(228, 'Bahasa Inggris', 'XI', '', '', 'Maya: \"We have a big English test next Monday, right?\"<br />\r\nDina	: \"Yes, and I haven\'t studied at all because I was busy with my music club.\"<br />\r\nMaya: \"I have some great notes. .... together at the library tomorrow?\"<br />\r\nDina	: \"That would be very helpful! I\'ll bring some snacks for us.\"<br />\r\nMaya: \"Deal. See you at the library at 10 AM.\"<br />\r\n', 'Will we study', '', 'Did we study', '', 'Do we study', '', 'Have we studied', '', 'Are we going to study', '', 'E'),
(229, 'Bahasa Inggris', 'XI', '', '', 'Sarah	: “I called you several times last night around 8 PM, but you didn\'t answer.”<br />\r\nMark	: “Oh, I\'m sorry. I left my phone in the car when I arrived home.”<br />\r\nSarah	: “What were you doing at that time then?”<br />\r\n', 'I cooked dinner for my family.', '', 'I have cooked dinner for my family.', '', 'I was cooking dinner for my family.', '', 'I had cooked dinner for my family.', '', 'I will be cooking dinner for my family.', '', 'C'),
(230, 'Bahasa Inggris', 'XI', '', '', 'Budi	: “Look at that window! The glass is broken into pieces.”<br />\r\nSiti	: “Oh no! When did that happen?”<br />\r\nBudi	: “I think it …….. by a ball during the break time earlier.”<br />\r\nSiti	: “Who was playing football near the classroom?”<br />\r\nBudi	: “I don\'t know, but the teacher has been informed about it.”<br />\r\nSiti	: “I hope it will be fixed before the rain starts.”<br />\r\n', 'is hit', '', 'was hit', '', 'was hitting', '', 'is being hit', '', 'has hit', '', 'A'),
(231, 'Bahasa Inggris', 'XI', '', '', 'Lia	: “The food at \"Soto Makmur\" is very delicious and the price is cheap.”<br />\r\nRio	: “……... I ate there yesterday and I want to go back again tomorrow.”<br />\r\nLia	: “I know, right? The broth is so savory and rich.”<br />\r\nRio	: “And the service is also very fast even though it\'s crowded.”<br />\r\nLia	: “We should definitely go there together sometime.”', 'I disagree', '', 'I don&#039;t think ', '', 'it&#039;s good', '', 'I&#039;m afraid I can&#039;t agree', '', 'I&#039;m not sure about that', '', 'E'),
(232, 'Bahasa Inggris', 'XI', 'PART VI. Directions', 'soal_utama_1780737150.png', 'What is the main purpose of the invitation?', 'To invite parents to a graduation ceremony', '', 'To announce a new school building', '', 'To promote extracurricular competitions', '', 'To inform students about examination schedules', '', 'To invite parents to attend the Annual Parent-Teacher Conference', '', 'E'),
(233, 'Bahasa Inggris', 'XI', 'PART VI. Directions: ', 'soal_utama_1780737243.png', 'What should parents do before June 10th, 2026?', 'Submit students’ assignments', '', 'Buy tickets for the conference', '', 'Confirm their attendance ', '', 'Meet the principal personally', '', 'Prepare workshop materials', '', 'C'),
(234, 'Bahasa Inggris', 'XI', '', 'soal_utama_1780737364.png', 'What activities will be available for parents during the event?', 'Music concerts and dancing competitions', '', 'Swimming lessons and sports training', '', 'Cooking competitions', '', 'Workshop and question-answer sessions', '', 'Movie screenings', '', 'D'),
(236, 'Bahasa Inggris', 'XI', 'PART VI. Directions<br />\r\n<br />\r\n<br />\r\n<br />\r\nQuestion number 24 – 27 based on the following personal letter', 'soal_utama_1780737547.png', 'What is the purpose of the letter?', 'To complain about school activities', '', 'To invite Sarah to join a photography club', '', 'To share Amanda’s experiences and invite Sarah to a festival', '', 'To explain how to make cakes and cookies', '', 'To ask Sarah to move to Amanda’s school', '', 'C'),
(237, 'Bahasa Inggris', 'XI', 'PART VI. Directions<br />\r\n<br />\r\nQuestion number 24 – 27 based on the following personal letter', 'soal_utama_1780737681.png', 'What should Sarah do after reading the letter?', 'Buy tickets for the festival', '', 'Reply to Amanda’s letter', '', 'Join Amanda’s school immediately', '', 'Visit the bakery every weekend', '', 'Send photographs to Amanda', '', 'B'),
(238, 'Bahasa Inggris', 'XI', 'PART VI. Directions<br />\r\n<br />\r\nQuestion number 24 – 27 based on the following personal letter', 'soal_utama_1780738090.png', 'Which activity is mentioned as part of the cultural festival?', 'Swimming competition', '', 'Football tournament', '', 'Traditional dances', '', 'Camping activities', '', 'Debate competition', '', 'C'),
(239, 'Bahasa Inggris', 'XI', 'Question number 27 – 28 based on the following procedure text', 'soal_utama_1780738284.png', 'What is the main idea of the second paragraph?', 'The steps to serve hot chocolate', '', 'The ingredients and tools needed to make hot chocolate', '', 'The benefits of drinking hot chocolate', '', 'The way to boil water correctly', '', 'The history of hot chocolate', '', 'B'),
(240, 'Bahasa Inggris', 'XI', '', 'soal_utama_1780738376.png', 'Why should the milk be stirred slowly?', 'To make it sweeter', '', 'To change the color', '', 'To prevent it from burning', '', 'To cool the milk', '', 'To reduce the amount of milk', '', 'C'),
(241, 'Bahasa Inggris', 'XI', 'Question number 29 – 31 based on the following daily text', 'soal_utama_1780738515.png', 'What is the purpose of the text?', 'To explain how to study English', '', 'To describe the writer’s daily activities on Monday', '', 'To invite readers to join an English course', '', 'To tell readers about a holiday trip', '', 'To compare school subjects', '', 'B'),
(242, 'Bahasa Inggris', 'XI', 'Question number 29 – 31 based on the following daily text', 'soal_utama_1780738778.png', 'What is the main idea of the second paragraph?', 'The writer’s activities after school', '', 'The writer’s favorite television programs', '', 'The writer’s family dinner activities', '', 'The writer’s gardening hobby', '', 'The writer’s activities and lessons at school', '', 'E'),
(243, 'Bahasa Inggris', 'XI', 'Question number 29 – 31 based on the following daily text', 'soal_utama_1780738989.png', 'What time does the writer leave home for school?', '5 a.m', '', '6 a.m', '', '6.30 a.m', '', '7 a.m', '', '7.30 a.m', '', 'C'),
(244, 'Bahasa Inggris', 'XI', 'Question number 32 – 34 based on the following text', 'soal_utama_1780739243.png', 'What is the main purpose of the text?', 'To explain different kinds of sports', '', 'To entertain readers with a story about athletes', '', 'To persuade readers to exercise regularly', '', 'To compare indoor and outdoor activities', '', 'To describe the history of exercise', '', 'C'),
(245, 'Bahasa Inggris', 'XI', 'Question number 32 – 34 based on the following text', 'soal_utama_1780739326.png', 'What is implied about exercise and stress?', 'Exercise can help reduce stress', '', 'Exercise increases emotional problems', '', 'Exercise causes students to feel tired all day', '', 'Exercise makes students avoid school', '', 'Exercise has no effect on emotions', '', 'A'),
(246, 'Bahasa Inggris', 'XI', 'Question number 32 – 34 based on the following text', 'soal_utama_1780739446.png', 'What can exercise improve besides physical health?', 'Internet connection', '', 'Fashion style', '', 'Driving ability', '', 'Cooking skills', '', 'Mental health', '', 'E'),
(247, 'Bahasa Inggris', 'XI', 'Question number 35 – 37 based on the following recount text', 'soal_utama_1780739613.png', 'What is the main idea of the last paragraph?', 'The writer regretted attending the school', '', 'The writer had a memorable and positive first day', '', 'The writer disliked extracurricular activities', '', 'The school day ended too quickly', '', 'The writer felt lonely at school', '', 'B'),
(248, 'Bahasa Inggris', 'XI', 'Question number 35 – 37 based on the following recount text', 'soal_utama_1780739746.png', 'Who helped the writer find the classroom?', 'Parents', '', 'Teachers', '', 'Junior students', '', 'Senior students', '', 'Security guards', '', 'D'),
(249, 'Bahasa Inggris', 'XI', 'Question number 35 – 37 based on the following recount text', 'soal_utama_1780739848.png', 'What can readers infer about the senior students?', 'They were helpful and friendly', '', 'They ignored new students', '', 'They were strict and rude', '', 'They disliked the writer', '', 'They were afraid of teachers', '', 'A'),
(250, 'Bahasa Inggris', 'XI', 'Question number 38 – 40 based on the following narrative text', 'soal_utama_1780739993.png', 'What is the main purpose of the text?', 'To explain how villagers raise sheep', '', 'To explain the behaviour of wolves', '', 'To describe life in a village', '', 'To persuade readers to become shepherds', '', 'To entertain readers with a story containing a moral lesson', '', 'E'),
(251, 'Bahasa Inggris', 'XI', 'Question number 38 – 40 based on the following narrative text', 'soal_utama_1780740118.png', 'Why did the shepherd boy shout “Wolf!” for the first time?', 'Because he saw a real wolf', '', 'Because he wanted to amuse himself', '', 'Because the sheep ran away', '', 'Because the villagers asked him to shout', '', 'Because he was afraid of the forest', '', 'B'),
(252, 'Bahasa Inggris', 'XI', 'Question number 38 – 40 based on the following narrative text', 'soal_utama_1780740231.png', 'What did the villagers do when they heard the boy’s cries?', 'They ignored him completely', '', 'They laughed at him', '', 'They came quickly to help him', '', 'They called the police', '', 'They sent hunters to the hill', '', 'C');

-- --------------------------------------------------------

--
-- Struktur dari tabel `ujian_siswa`
--

CREATE TABLE `ujian_siswa` (
  `id` int(11) NOT NULL,
  `siswa_id` int(11) NOT NULL,
  `mata_pelajaran` varchar(100) DEFAULT NULL,
  `foto_selfie` varchar(255) DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT 0.00,
  `waktu_mulai` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `jumlah_pelanggaran` int(11) DEFAULT 0,
  `last_ping` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `ujian_siswa`
--

INSERT INTO `ujian_siswa` (`id`, `siswa_id`, `mata_pelajaran`, `foto_selfie`, `nilai`, `waktu_mulai`, `waktu_selesai`, `jumlah_pelanggaran`, `last_ping`) VALUES
(3, 152, 'MTK', 'selfie_152_1780649310.jpg', 0.00, '2026-06-05 15:48:30', '2026-06-05 15:49:21', 2, '2026-06-05 15:49:16'),
(5, 153, 'MTK', 'selfie_153_1780658993.jpg', 11.25, '2026-06-05 18:29:53', '2026-06-05 18:34:54', 1, '2026-06-05 18:34:49');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ujian_id` (`ujian_id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indeks untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `pengaturan_ujian`
--
ALTER TABLE `pengaturan_ujian`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kartu_peserta` (`kartu_peserta`);

--
-- Indeks untuk tabel `soal`
--
ALTER TABLE `soal`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `ujian_siswa`
--
ALTER TABLE `ujian_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT untuk tabel `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT untuk tabel `pengaturan_ujian`
--
ALTER TABLE `pengaturan_ujian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=154;

--
-- AUTO_INCREMENT untuk tabel `soal`
--
ALTER TABLE `soal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT untuk tabel `ujian_siswa`
--
ALTER TABLE `ujian_siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `jawaban_siswa`
--
ALTER TABLE `jawaban_siswa`
  ADD CONSTRAINT `jawaban_siswa_ibfk_1` FOREIGN KEY (`ujian_id`) REFERENCES `ujian_siswa` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jawaban_siswa_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ujian_siswa`
--
ALTER TABLE `ujian_siswa`
  ADD CONSTRAINT `ujian_siswa_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
