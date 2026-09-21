-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 16 Agu 2026 pada 13.51
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `penyewaan_tari`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `detail_penyewaan`
--

CREATE TABLE `detail_penyewaan` (
  `id` int(11) NOT NULL,
  `penyewaan_id` int(11) NOT NULL,
  `peralatan_id` int(11) NOT NULL,
  `jumlah_pinjam` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `detail_penyewaan`
--

INSERT INTO `detail_penyewaan` (`id`, `penyewaan_id`, `peralatan_id`, `jumlah_pinjam`) VALUES
(1, 1, 3, 1),
(2, 1, 2, 1),
(3, 2, 5, 1),
(4, 3, 3, 1),
(5, 4, 4, 1),
(6, 4, 5, 1),
(7, 5, 5, 6),
(8, 6, 4, 2),
(9, 7, 4, 2),
(10, 8, 2, 1),
(11, 9, 4, 2),
(12, 10, 3, 1),
(13, 11, 5, 1),
(14, 12, 2, 1),
(15, 12, 4, 1),
(16, 13, 7, 1),
(17, 13, 11, 2),
(18, 14, 13, 3),
(19, 14, 19, 2),
(20, 15, 21, 3),
(21, 15, 29, 3),
(22, 16, 20, 2),
(23, 16, 27, 2),
(24, 17, 17, 3),
(25, 17, 28, 3),
(26, 18, 14, 3),
(27, 18, 22, 3),
(28, 18, 28, 1),
(29, 19, 28, 3),
(30, 20, 14, 2),
(31, 20, 16, 1),
(32, 20, 21, 2),
(33, 21, 12, 1),
(34, 21, 21, 2),
(35, 21, 24, 3),
(36, 22, 2, 3),
(37, 22, 24, 1),
(38, 23, 4, 2),
(39, 23, 19, 3),
(40, 23, 31, 3),
(41, 24, 4, 2),
(42, 24, 13, 1),
(43, 25, 29, 4),
(44, 25, 14, 2),
(45, 26, 24, 1),
(46, 26, 3, 2),
(47, 27, 4, 1),
(48, 27, 20, 1),
(49, 28, 9, 1),
(50, 28, 27, 3),
(51, 28, 31, 2),
(52, 29, 8, 3),
(53, 30, 13, 3),
(54, 30, 18, 2),
(55, 30, 22, 3),
(56, 31, 13, 3),
(57, 31, 31, 2),
(58, 32, 28, 2),
(59, 33, 12, 1),
(60, 34, 27, 1);

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id` int(11) NOT NULL,
  `penyewaan_id` int(11) NOT NULL,
  `tanggal_dikembalikan` date NOT NULL,
  `denda_keterlambatan` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status_denda` enum('belum_bayar','lunas') NOT NULL DEFAULT 'belum_bayar',
  `bukti_bayar_denda` varchar(255) DEFAULT NULL,
  `kondisi_peralatan` text DEFAULT NULL,
  `dicatat_oleh` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pengembalian`
--

INSERT INTO `pengembalian` (`id`, `penyewaan_id`, `tanggal_dikembalikan`, `denda_keterlambatan`, `status_denda`, `bukti_bayar_denda`, `kondisi_peralatan`, `dicatat_oleh`, `created_at`) VALUES
(1, 7, '2026-06-16', 0.00, 'belum_bayar', NULL, 'selesai, semua barang sudah kembali dengan sempurna.', 3, '2026-06-16 08:34:30'),
(2, 9, '2026-06-16', 0.00, 'belum_bayar', NULL, 'Berhasil dikembalikan dengan kondisi baik.', 3, '2026-06-16 08:56:04'),
(3, 3, '2026-06-19', 50000.00, 'belum_bayar', NULL, 'Lecet', 3, '2026-06-16 08:59:49'),
(4, 6, '2026-06-18', 0.00, 'belum_bayar', NULL, NULL, 3, '2026-06-16 16:32:01'),
(5, 8, '2026-07-01', 50000.00, 'belum_bayar', NULL, NULL, 3, '2026-07-01 13:03:37'),
(6, 13, '2026-06-16', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(7, 14, '2026-06-21', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(8, 15, '2026-06-29', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(9, 16, '2026-06-27', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(10, 19, '2026-06-08', 50000.00, 'belum_bayar', NULL, 'Barang terlambat dikembalikan, kondisi baik', 3, '2026-07-01 13:34:30'),
(11, 20, '2026-06-22', 100000.00, 'belum_bayar', NULL, 'Barang terlambat dikembalikan, kondisi baik', 3, '2026-07-01 13:34:30'),
(12, 21, '2026-06-29', 45000.00, 'belum_bayar', NULL, 'Barang terlambat dikembalikan, kondisi baik', 3, '2026-07-01 13:34:30'),
(13, 22, '2026-06-29', 60000.00, 'belum_bayar', NULL, 'Barang terlambat dikembalikan, kondisi baik', 3, '2026-07-01 13:34:30'),
(14, 23, '2026-07-03', 30000.00, 'belum_bayar', NULL, 'Barang terlambat dikembalikan, kondisi baik', 3, '2026-07-01 13:34:30'),
(15, 24, '2026-06-16', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(16, 25, '2026-06-28', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30'),
(17, 32, '2026-06-25', 0.00, 'belum_bayar', NULL, 'Selesai, semua barang kembali tepat waktu', 3, '2026-07-01 13:34:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penyewaan`
--

CREATE TABLE `penyewaan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tanggal_sewa` date NOT NULL,
  `tanggal_kembali` date NOT NULL,
  `total_biaya` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('menunggu','aktif','selesai','terlambat') NOT NULL DEFAULT 'menunggu',
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status_pembayaran` enum('belum_bayar','menunggu_verifikasi','lunas') NOT NULL DEFAULT 'belum_bayar',
  `catatan` text DEFAULT NULL,
  `foto_konfirmasi_pelanggan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penyewaan`
--

INSERT INTO `penyewaan` (`id`, `user_id`, `tanggal_sewa`, `tanggal_kembali`, `total_biaya`, `status`, `bukti_pembayaran`, `status_pembayaran`, `catatan`, `foto_konfirmasi_pelanggan`, `created_at`, `updated_at`) VALUES
(1, 4, '2026-06-12', '2026-06-14', 400000.00, 'terlambat', 'uploads/bukti/bukti_6a2be1f39d01a8.87562416.jpg', 'lunas', 'Tolong disiapkan jam 7 pagi.', NULL, '2026-06-11 16:16:03', '2026-06-15 14:26:50'),
(2, 5, '2026-06-13', '2026-06-16', 150000.00, 'menunggu', NULL, 'belum_bayar', NULL, NULL, '2026-06-12 11:02:33', '2026-06-12 11:02:33'),
(3, 4, '2026-06-15', '2026-06-18', 200000.00, 'terlambat', 'uploads/bukti/bukti_6a2e7f909c3053.10577850.jpg', 'lunas', NULL, NULL, '2026-06-14 10:15:44', '2026-06-16 08:59:49'),
(4, 6, '2026-06-16', '2026-06-19', 250000.00, 'menunggu', 'uploads/bukti/bukti_6a2fdc27ac2876.75347957.jpg', 'menunggu_verifikasi', 'Saya Bakal Ambil Barangnya Jam 07 pagi.', NULL, '2026-06-15 11:03:05', '2026-06-15 11:04:07'),
(5, 8, '2026-06-16', '2026-06-19', 900000.00, 'menunggu', NULL, 'belum_bayar', 'Ukuran M=4, Ukuran L=2', NULL, '2026-06-15 14:17:42', '2026-06-15 14:17:42'),
(6, 9, '2026-06-17', '2026-06-20', 200000.00, 'selesai', 'uploads/bukti/bukti_6a300afc6fbe28.77284952.jpg', 'lunas', 'Ukurann M&L', NULL, '2026-06-15 14:23:13', '2026-06-16 16:32:01'),
(7, 4, '2026-06-16', '2026-06-19', 200000.00, 'selesai', 'uploads/bukti/bukti_6a300e6c5b9a38.94950205.jpg', 'lunas', 'Ukuran L & M', NULL, '2026-06-15 14:37:50', '2026-06-16 08:34:30'),
(8, 12, '2026-06-17', '2026-06-20', 200000.00, 'terlambat', 'uploads/bukti/bukti_6a3107ced37758.65272343.jpg', 'lunas', 'Siapkan barangnya di jam 07 pagi.', NULL, '2026-06-16 08:21:35', '2026-07-01 13:03:37'),
(9, 13, '2026-06-16', '2026-06-19', 200000.00, 'selesai', 'uploads/bukti/bukti_6a310eb81ba075.80615057.jpg', 'lunas', 'Request ukuran L & XL', NULL, '2026-06-16 08:51:11', '2026-06-16 08:56:04'),
(10, 8, '2026-06-16', '2026-06-19', 200000.00, 'menunggu', 'uploads/bukti/bukti_6a314f1c1b61c9.61610087.jpg', 'menunggu_verifikasi', NULL, NULL, '2026-06-16 13:26:37', '2026-06-16 13:26:52'),
(11, 4, '2026-06-16', '2026-06-19', 150000.00, 'menunggu', NULL, 'belum_bayar', NULL, NULL, '2026-06-16 15:44:00', '2026-06-16 15:44:00'),
(12, 4, '2026-07-01', '2026-07-04', 300000.00, 'terlambat', 'uploads/bukti/bukti_6a450dbd2aafa1.11132432.jpg', 'lunas', NULL, NULL, '2026-07-01 12:53:05', '2026-07-01 12:54:26'),
(13, 14, '2026-06-10', '2026-06-13', 1200000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 1', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(14, 14, '2026-06-18', '2026-06-21', 1050000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 2', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(15, 18, '2026-06-23', '2026-06-26', 750000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 3', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(16, 17, '2026-06-24', '2026-06-27', 800000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 4', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(17, 16, '2026-07-01', '2026-07-04', 600000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 5', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(18, 16, '2026-07-01', '2026-07-04', 540000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 6', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(19, 15, '2026-06-05', '2026-06-08', 450000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 7', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(20, 18, '2026-06-15', '2026-06-18', 360000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 8', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(21, 17, '2026-06-23', '2026-06-26', 700000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 9', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(22, 16, '2026-06-23', '2026-06-26', 700000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 10', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(23, 17, '2026-06-30', '2026-07-03', 575000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 11', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(24, 14, '2026-06-10', '2026-06-13', 500000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 12', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(25, 17, '2026-06-25', '2026-06-28', 660000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 13', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(26, 18, '2026-07-01', '2026-07-04', 500000.00, 'selesai', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 14', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(27, 18, '2026-06-30', '2026-07-03', 350000.00, 'aktif', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 15', NULL, '2026-07-01 13:34:30', '2026-08-14 14:45:17'),
(28, 16, '2026-07-01', '2026-07-04', 750000.00, 'menunggu', NULL, 'belum_bayar', 'Catatan simulasi transaksi 16', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(29, 15, '2026-07-01', '2026-07-04', 600000.00, 'menunggu', 'uploads/bukti/dummy_bukti.jpg', 'menunggu_verifikasi', 'Catatan simulasi transaksi 17', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(30, 18, '2026-06-30', '2026-07-03', 1700000.00, 'aktif', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 18', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(31, 14, '2026-07-02', '2026-07-05', 1000000.00, 'aktif', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 19', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(32, 17, '2026-06-22', '2026-06-25', 300000.00, 'terlambat', 'uploads/bukti/dummy_bukti.jpg', 'lunas', 'Catatan simulasi transaksi 20', NULL, '2026-07-01 13:34:30', '2026-07-01 13:34:30'),
(33, 4, '2026-08-14', '2026-08-15', 200000.00, 'selesai', 'uploads/bukti/bukti_6a7f2bd6d9fdc8.54037324.jpg', 'lunas', NULL, 'uploads/foto_pengembalian/kembali_6a7f2c6b799159.14181116.jpg', '2026-08-14 14:52:19', '2026-08-14 14:56:44'),
(34, 4, '2026-08-14', '2026-08-17', 150000.00, 'aktif', 'uploads/bukti/bukti_6a7f2d2d17f942.62538745.jpg', 'lunas', NULL, NULL, '2026-08-14 14:58:42', '2026-08-14 14:59:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `peralatan`
--

CREATE TABLE `peralatan` (
  `id` int(11) NOT NULL,
  `nama_alat` varchar(100) NOT NULL,
  `kategori_alat` enum('kostum','aksesoris','alat_musik') NOT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `harga_sewa` decimal(10,2) NOT NULL,
  `kondisi` varchar(50) NOT NULL DEFAULT 'baik',
  `foto` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `peralatan`
--

INSERT INTO `peralatan` (`id`, `nama_alat`, `kategori_alat`, `stok`, `harga_sewa`, `kondisi`, `foto`, `deskripsi`, `created_at`, `updated_at`) VALUES
(2, 'Kendang Rampak', 'alat_musik', 2, 200000.00, 'baik', 'foto_6a2adb48568564.42208677.jpg', NULL, '2026-06-11 15:59:04', '2026-07-01 13:03:37'),
(3, 'Bonang', 'alat_musik', 4, 200000.00, 'baik', 'foto_6a2adc584b6089.63770684.jpg', 'Sudah include dengan alat pemukul bonang.', '2026-06-11 16:03:36', '2026-06-16 08:59:49'),
(4, 'Tari bendrong lesung', 'kostum', 23, 100000.00, 'baik', 'foto_6a2adcf5c7c5e4.65158946.jpg', 'Set Lengkap.', '2026-06-11 16:06:13', '2026-07-01 12:54:08'),
(5, 'Tari panah', 'kostum', 24, 150000.00, 'baik', 'foto_6a2ade734bba29.59755862.jpg', 'Set Lengkap.', '2026-06-11 16:12:35', '2026-06-11 16:12:35'),
(6, 'Gedor', 'alat_musik', 25, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(7, 'Gong', 'alat_musik', 5, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(8, 'Saron diatonis', 'alat_musik', 6, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(9, 'Rebana', 'alat_musik', 19, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(10, 'Bendrong lesung', 'alat_musik', 20, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(11, 'Rampak bedug', 'alat_musik', 6, 500000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(12, 'Angklung', 'alat_musik', 9, 200000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-08-14 14:53:45'),
(13, 'Gambang', 'alat_musik', 19, 300000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(14, 'Kentongan', 'alat_musik', 19, 30000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(15, 'Kecapi', 'alat_musik', 20, 250000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(16, 'Suling', 'alat_musik', 12, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(17, 'Tamborin', 'alat_musik', 18, 50000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(18, 'Gamelan degung', 'alat_musik', 15, 250000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(19, 'Simbal', 'alat_musik', 25, 75000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(20, 'Saron degung', 'alat_musik', 25, 250000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(21, 'Tari prajurit', 'kostum', 19, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(22, 'Tari rampak bedug pria', 'kostum', 7, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(23, 'Tari rampak gendang', 'kostum', 5, 150000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(24, 'Tari jawari medal', 'kostum', 24, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(25, 'Tari Rampak bedug wanita berhijab', 'kostum', 12, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(26, 'Tari rampak bedug wanita', 'kostum', 18, 150000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(27, 'Tari penyambutan 1', 'kostum', 17, 150000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-08-14 14:59:11'),
(28, 'Tari penyambutan 2', 'kostum', 12, 150000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(29, 'Tari penyambutan pengantin', 'kostum', 5, 150000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(30, 'Tari dzikir saman Wanita', 'kostum', 22, 100000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08'),
(31, 'Tari bedug kerok', 'kostum', 15, 50000.00, 'baik', NULL, NULL, '2026-07-01 13:29:08', '2026-07-01 13:29:08');

-- --------------------------------------------------------

--
-- Struktur dari tabel `riwayat_stok`
--

CREATE TABLE `riwayat_stok` (
  `id` int(11) NOT NULL,
  `peralatan_id` int(11) NOT NULL,
  `penyewaan_id` int(11) NOT NULL,
  `jenis` enum('keluar','masuk') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `dicatat_pada` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `riwayat_stok`
--

INSERT INTO `riwayat_stok` (`id`, `peralatan_id`, `penyewaan_id`, `jenis`, `jumlah`, `keterangan`, `dicatat_pada`) VALUES
(1, 3, 1, 'keluar', 1, 'Disewa', '2026-06-12 11:21:24'),
(2, 2, 1, 'keluar', 1, 'Disewa', '2026-06-12 11:21:24'),
(3, 4, 6, 'keluar', 2, 'Disewa', '2026-06-15 14:25:57'),
(4, 4, 7, 'keluar', 2, 'Disewa', '2026-06-15 14:40:14'),
(5, 2, 8, 'keluar', 1, 'Disewa', '2026-06-16 08:32:34'),
(6, 4, 7, 'masuk', 2, 'Dikembalikan', '2026-06-16 08:34:30'),
(7, 3, 3, 'keluar', 1, 'Disewa', '2026-06-16 08:53:18'),
(8, 4, 9, 'keluar', 2, 'Disewa', '2026-06-16 08:54:25'),
(9, 4, 9, 'masuk', 2, 'Dikembalikan', '2026-06-16 08:56:04'),
(10, 3, 3, 'masuk', 1, 'Dikembalikan', '2026-06-16 08:59:49'),
(11, 4, 6, 'masuk', 2, 'Dikembalikan', '2026-06-16 16:32:01'),
(12, 2, 12, 'keluar', 1, 'Disewa', '2026-07-01 12:54:08'),
(13, 4, 12, 'keluar', 1, 'Disewa', '2026-07-01 12:54:08'),
(14, 2, 8, 'masuk', 1, 'Dikembalikan', '2026-07-01 13:03:37'),
(15, 12, 33, 'keluar', 1, 'Disewa', '2026-08-14 14:53:45'),
(16, 27, 34, 'keluar', 1, 'Disewa', '2026-08-14 14:59:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('pelanggan','admin_pengelola','admin_gudang','ketua_sanggar') NOT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `no_telp`, `alamat`, `created_at`) VALUES
(1, 'Ketua Sanggar', 'ketua', '$2y$10$RjknmM2QEOV0QzoVRf23tud.1wSpw52O5EZzVHWIqXhBOtUI2D0UC', 'ketua_sanggar', NULL, NULL, '2026-06-10 08:54:55'),
(2, 'Admin Pengelola', 'admin_pengelola', '$2y$10$WDPl9kJPKegmmUnCV.rSAeJAeJ9jeiy9Mhq7biyL5BeUaVvoRm6iK', 'admin_pengelola', NULL, NULL, '2026-06-10 08:54:55'),
(3, 'Admin Gudang', 'admin_gudang', '$2y$10$VQhYrgWimFKDc79/7CymVuYEqSqe10x60TM/VY2p3hitBEckYUd7y', 'admin_gudang', NULL, NULL, '2026-06-10 08:54:55'),
(4, 'Mimin', 'mimin', '$2y$10$lMjgk./eFCLOhG4dCBbQ4uN1mR5TabT8jZgfiout74S3YmopG3VfC', 'pelanggan', '08123654789', 'jl. jendral sudirman, No. 99', '2026-06-11 09:32:40'),
(5, 'Mimin Customer 2', 'mimin2', '$2y$10$O96qfpoBZpiufUqv7Uxxt.QVh7kyGrvHMZ4phCGWB0xxIizD4mYxO', 'pelanggan', '08123456789', 'Jl. Tari No. 2', '2026-06-11 15:51:39'),
(6, 'M. Budi', 'budi', '$2y$10$QE7eeNAwZpS4xMOCfYJUw.E4f2ASG8Dz8zwe1ytYhf7vNY7/A4VPu', 'pelanggan', '0812345678910', 'Jl. Gatot Subroto, No.05, Jakarta Pusat', '2026-06-15 11:00:58'),
(7, 'udin', 'udin', '$2y$10$82NSXE8BmEeGwLHH.3D3MeZtIcViP7iSyzbLGEVkfr.PsjQ5CSrRu', 'pelanggan', '08777123123', 'Jl. Jendral Soedirman, No.1', '2026-06-15 13:41:47'),
(8, 'asep', 'asep', '$2y$10$2pIttUE6eLxigOg5/mZU9eWeZ7f95lW.NmCroyAnksu3xuxl9i0/W', 'pelanggan', '0852113344', 'jl. Jendral Gatot Subroto, No.05, Jakarta Pusat', '2026-06-15 14:15:51'),
(9, 'Dani', 'dani', '$2y$10$XA58HZC15uE8i4Sdr2fNjOBGWb0JWE/UgYima8xWuyht/ZefCQA3a', 'pelanggan', '08123321123', 'Jl. Byangkara, No.1', '2026-06-15 14:21:22'),
(10, 'Test Ketua', 'ketua_sanggar', '$2y$10$1T7EcSNbGJEL0nfc876Fx.6a/swCMuez.WH42ZtZMQXeyUtqqH6Sq', 'pelanggan', '08123456789', 'Test Alamat', '2026-06-16 05:19:46'),
(11, 'Attacker', 'attacker', '$2y$10$8KoyCjcfHpSDMkDz3pi/A.GQ4ke1.vY6NymDV98xRlulxwHwG0LB6', 'pelanggan', '08123456789', 'Attacker Address', '2026-06-16 06:35:37'),
(12, 'Gunawan', 'gunawan', '$2y$10$5ENcsJVcM1/W.cg9Ld6YMO3firstRUVd0ctSjPC5ByjgSNa47osqS', 'pelanggan', '08987654321', 'Jl. Jendral Soedirman, No.77, GG. Satria', '2026-06-16 08:19:27'),
(13, 'Aceng', 'aceng', '$2y$10$OUMDR3fKxRyRjbkkHk4ws.hf.VHfYCcwAa3JW6cv/r0dKgcdzp2..', 'pelanggan', '08777123', 'Jl. Kebun Raya, No.04', '2026-06-16 08:48:57'),
(14, 'Eko Prasetyo', 'ekoprasetyo', '$2y$10$2hthMHRbkiF3DoIYozOTiudg3AgXNRSSHQBxjVxkgisIi6X8K.xOm', 'pelanggan', '081211111111', 'Alamat Dummy 1', '2026-07-01 13:34:30'),
(15, 'Putri Indah', 'putriindah', '$2y$10$2hthMHRbkiF3DoIYozOTiudg3AgXNRSSHQBxjVxkgisIi6X8K.xOm', 'pelanggan', '081222222222', 'Alamat Dummy 2', '2026-07-01 13:34:30'),
(16, 'Aditya Nugraha', 'adityanugraha', '$2y$10$2hthMHRbkiF3DoIYozOTiudg3AgXNRSSHQBxjVxkgisIi6X8K.xOm', 'pelanggan', '081233333333', 'Alamat Dummy 3', '2026-07-01 13:34:30'),
(17, 'Hendra Wijaya', 'hendrawijaya', '$2y$10$2hthMHRbkiF3DoIYozOTiudg3AgXNRSSHQBxjVxkgisIi6X8K.xOm', 'pelanggan', '081244444444', 'Alamat Dummy 4', '2026-07-01 13:34:30'),
(18, 'Ayu Lestari', 'ayulestari', '$2y$10$2hthMHRbkiF3DoIYozOTiudg3AgXNRSSHQBxjVxkgisIi6X8K.xOm', 'pelanggan', '081255555555', 'Alamat Dummy 5', '2026-07-01 13:34:30');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `detail_penyewaan`
--
ALTER TABLE `detail_penyewaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penyewaan_id` (`penyewaan_id`),
  ADD KEY `peralatan_id` (`peralatan_id`);

--
-- Indeks untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `penyewaan_id` (`penyewaan_id`),
  ADD KEY `dicatat_oleh` (`dicatat_oleh`);

--
-- Indeks untuk tabel `penyewaan`
--
ALTER TABLE `penyewaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `peralatan`
--
ALTER TABLE `peralatan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `riwayat_stok`
--
ALTER TABLE `riwayat_stok`
  ADD PRIMARY KEY (`id`),
  ADD KEY `peralatan_id` (`peralatan_id`),
  ADD KEY `penyewaan_id` (`penyewaan_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `detail_penyewaan`
--
ALTER TABLE `detail_penyewaan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `penyewaan`
--
ALTER TABLE `penyewaan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT untuk tabel `peralatan`
--
ALTER TABLE `peralatan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT untuk tabel `riwayat_stok`
--
ALTER TABLE `riwayat_stok`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `detail_penyewaan`
--
ALTER TABLE `detail_penyewaan`
  ADD CONSTRAINT `detail_penyewaan_ibfk_1` FOREIGN KEY (`penyewaan_id`) REFERENCES `penyewaan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_penyewaan_ibfk_2` FOREIGN KEY (`peralatan_id`) REFERENCES `peralatan` (`id`);

--
-- Ketidakleluasaan untuk tabel `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`penyewaan_id`) REFERENCES `penyewaan` (`id`),
  ADD CONSTRAINT `pengembalian_ibfk_2` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `penyewaan`
--
ALTER TABLE `penyewaan`
  ADD CONSTRAINT `penyewaan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `riwayat_stok`
--
ALTER TABLE `riwayat_stok`
  ADD CONSTRAINT `riwayat_stok_ibfk_1` FOREIGN KEY (`peralatan_id`) REFERENCES `peralatan` (`id`),
  ADD CONSTRAINT `riwayat_stok_ibfk_2` FOREIGN KEY (`penyewaan_id`) REFERENCES `penyewaan` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
