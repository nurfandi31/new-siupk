-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 103.177.95.91:3306
-- Waktu pembuatan: 25 Jul 2026 pada 11.26
-- Versi server: 8.0.46
-- Versi PHP: 8.3.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Basis data: `siupk`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `akun_level_1`
--

CREATE TABLE `akun_level_1` (
  `id` int NOT NULL,
  `lev1` int DEFAULT '0',
  `lev2` int DEFAULT '0',
  `lev3` int DEFAULT '0',
  `lev4` int DEFAULT '0',
  `kode_akun` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `nama_akun` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `jenis_mutasi` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `akun_level_1`
--

INSERT INTO `akun_level_1` (`id`, `lev1`, `lev2`, `lev3`, `lev4`, `kode_akun`, `nama_akun`, `jenis_mutasi`) VALUES
(1, 1, 0, 0, 0, '1.0.00.00', 'Aset', 'Debet'),
(2, 2, 0, 0, 0, '2.0.00.00', 'Utang', 'Kredit'),
(3, 3, 0, 0, 0, '3.0.00.00', 'Modal', 'Kredit'),
(4, 4, 0, 0, 0, '4.0.00.00', 'Pendapatan', 'Kredit'),
(5, 5, 0, 0, 0, '5.0.00.00', 'Beban', 'Debet');

-- --------------------------------------------------------

--
-- Struktur dari tabel `akun_level_2`
--

CREATE TABLE `akun_level_2` (
  `id` int NOT NULL,
  `parent_id` int NOT NULL,
  `lev1` int DEFAULT '0',
  `lev2` int DEFAULT '0',
  `lev3` int DEFAULT '0',
  `lev4` int DEFAULT '0',
  `kode_akun` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `nama_akun` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `jenis_mutasi` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `akun_level_2`
--

INSERT INTO `akun_level_2` (`id`, `parent_id`, `lev1`, `lev2`, `lev3`, `lev4`, `kode_akun`, `nama_akun`, `jenis_mutasi`) VALUES
(11, 1, 1, 1, 0, 0, '1.1.00.00', 'Aset Lancar', 'Debet'),
(12, 1, 1, 2, 0, 0, '1.2.00.00', 'Aset Tidak Lancar', 'Debet'),
(13, 1, 1, 3, 0, 0, '1.3.00.00', 'Aset Lain-lain', 'Debet'),
(21, 2, 2, 1, 0, 0, '2.1.00.00', 'Utang Jangka Pendek', 'Kredit'),
(22, 2, 2, 2, 0, 0, '2.2.00.00', 'Utang Jangka Panjang', 'Kredit'),
(31, 3, 3, 1, 0, 0, '3.1.00.00', 'Modal Disetor', 'Kredit'),
(32, 3, 3, 2, 0, 0, '3.2.00.00', 'Laba Rugi', 'Kredit'),
(41, 4, 4, 1, 0, 0, '4.1.00.00', 'Pendapatan Usaha', 'Kredit'),
(42, 4, 4, 2, 0, 0, '4.2.00.00', 'Pendapatan Non Usaha', 'Kredit'),
(43, 4, 4, 3, 0, 0, '4.3.00.00', 'Pendapatan Luar biasa', 'Kredit'),
(51, 5, 5, 1, 0, 0, '5.1.00.00', 'Beban Usaha', 'Debet'),
(52, 5, 5, 2, 0, 0, '5.2.00.00', 'Beban Pemasaran', 'Debet'),
(53, 5, 5, 3, 0, 0, '5.3.00.00', 'Beban Non Usaha', 'Debet'),
(54, 5, 5, 4, 0, 0, '5.4.00.00', 'Beban Pajak', 'Debet');

-- --------------------------------------------------------

--
-- Struktur dari tabel `akun_level_3`
--

CREATE TABLE `akun_level_3` (
  `id` int NOT NULL,
  `parent_id` int NOT NULL,
  `lev1` int DEFAULT '0',
  `lev2` int DEFAULT '0',
  `lev3` int DEFAULT '0',
  `lev4` int DEFAULT '0',
  `kode_akun` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `nama_akun` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `posisi` int DEFAULT '0',
  `jenis_mutasi` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `akun_level_3`
--

INSERT INTO `akun_level_3` (`id`, `parent_id`, `lev1`, `lev2`, `lev3`, `lev4`, `kode_akun`, `nama_akun`, `posisi`, `jenis_mutasi`) VALUES
(111, 11, 1, 1, 1, 0, '1.1.01.00', 'Kas', 1, 'Debet'),
(112, 11, 1, 1, 2, 0, '1.1.02.00', 'Kas Setara Kas', 1, 'Debet'),
(113, 11, 1, 1, 3, 0, '1.1.03.00', 'Piutang', 1, 'Debet'),
(114, 11, 1, 1, 4, 0, '1.1.04.00', 'Cadangan Kerugian Piutang', 1, 'Debet'),
(115, 11, 1, 1, 5, 0, '1.1.05.00', 'Rekening antar Kantor ', 1, 'Debet'),
(116, 11, 1, 1, 6, 0, '1.1.06.00', 'Investasi', 1, 'Debet'),
(121, 12, 1, 2, 1, 0, '1.2.01.00', 'Aktiva Tetap dan Inventaris', 1, 'Debet'),
(122, 12, 1, 2, 2, 0, '1.2.02.00', 'Akumulasi Penyusutan Aktiva Tetap dan Inventaris', 1, 'Debet'),
(123, 12, 1, 2, 3, 0, '1.2.03.00', 'Aset Tak Berwujud', 1, 'Debet'),
(124, 12, 1, 2, 4, 0, '1.2.04.00', 'Akumulasi Amortisasi Aset Tak Berwujud', 1, 'Debet'),
(125, 12, 1, 2, 5, 0, '1.2.05.00', 'Konstruksi Dalam Pengerjaan', 1, 'Debet'),
(131, 13, 1, 3, 1, 0, '1.3.01.00', 'Aset Lain-lain', 1, 'Debet'),
(211, 21, 2, 1, 1, 0, '2.1.01.00', 'Utang Bank', 1, 'Kredit'),
(212, 21, 2, 1, 2, 0, '2.1.02.00', 'Utang Biaya Operasional', 1, 'Kredit'),
(213, 21, 2, 1, 3, 0, '2.1.03.00', 'Utang Pajak', 1, 'Kredit'),
(214, 21, 2, 1, 4, 0, '2.1.04.00', 'Utang Pembagian Laba', 1, 'Kredit'),
(215, 21, 2, 1, 5, 0, '2.1.05.00', 'Utang Jangka Pendek Lainnya', 1, 'Kredit'),
(221, 22, 2, 2, 1, 0, '2.2.01.00', 'Utang Bank', 1, 'Kredit'),
(222, 22, 2, 2, 2, 0, '2.2.02.00', 'Utang Jangka Panjang Lainnya', 1, 'Kredit'),
(311, 31, 3, 1, 1, 0, '3.1.01.00', 'Modal Masyarakat dan Desa', 1, 'Kredit'),
(312, 31, 3, 1, 2, 0, '3.1.02.00', 'Modal Lain-lain', 1, 'Kredit'),
(321, 32, 3, 2, 1, 0, '3.2.01.00', 'Laba Ditahan', 1, 'Kredit'),
(322, 32, 3, 2, 2, 0, '3.2.02.00', 'Laba Rugi Berjalan', 1, 'Kredit'),
(411, 41, 4, 1, 1, 0, '4.1.01.00', 'Pendapatan Usaha Utama', 1, 'Kredit'),
(412, 41, 4, 1, 2, 0, '4.1.02.00', 'Pendapatan Usaha Lain', 1, 'Kredit'),
(421, 42, 4, 2, 1, 0, '4.2.01.00', 'Pendapatan Non Usaha', 1, 'Kredit'),
(431, 43, 4, 3, 1, 0, '4.3.01.00', 'Pendapatan Luar biasa', 1, 'Kredit'),
(511, 51, 5, 1, 1, 0, '5.1.01.00', 'Beban Gaji dan Honor', 1, 'Kredit'),
(512, 51, 5, 1, 2, 0, '5.1.02.00', 'Beban Tunjangan dan Bonus', 1, 'Kredit'),
(513, 51, 5, 1, 3, 0, '5.1.03.00', 'Beban ATK dan Umum', 1, 'Kredit'),
(514, 51, 5, 1, 4, 0, '5.1.04.00', 'Beban Administarsi dan Umum Lainnya', 1, 'Kredit'),
(515, 51, 5, 1, 5, 0, '5.1.05.00', 'Beban Rapat, Peningkatan Kapasitas', 1, 'Kredit'),
(516, 51, 5, 1, 6, 0, '5.1.06.00', 'Transportasi dan Perjalanan Dinas', 1, 'Kredit'),
(517, 51, 5, 1, 7, 0, '5.1.07.00', 'Beban Penyisihan, Penyusutan dan Amortisasi', 1, 'Kredit'),
(518, 51, 5, 1, 8, 0, '5.1.08.00', 'Beban Bunga Utang', 1, 'Kredit'),
(519, 51, 5, 1, 9, 0, '5.1.09.00', 'Beban Usaha Lainnya', 1, 'Kredit'),
(521, 52, 5, 2, 1, 0, '5.2.01.00', 'Beban Pemasaran', 1, 'Kredit'),
(531, 53, 5, 3, 1, 0, '5.3.01.00', 'Beban Pajak bunga dan Administrasi Bank', 1, 'Kredit'),
(532, 53, 5, 3, 2, 0, '5.3.02.00', 'Beban Penghapusan Aset Tetap', 1, 'Kredit'),
(533, 53, 5, 3, 3, 0, '5.3.03.00', 'Beban Kegiatan Sosial dan Masyarakat', 1, 'Kredit'),
(534, 53, 5, 3, 4, 0, '5.3.04.00', 'Beban Non Usaha Lainnya', 1, 'Kredit'),
(541, 54, 5, 4, 1, 0, '5.4.01.00', 'Beban Pajak', 1, 'Kredit');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rekening_1`
--

CREATE TABLE `rekening_1` (
  `parent_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lev1` int DEFAULT '0',
  `lev2` int DEFAULT '0',
  `lev3` int DEFAULT '0',
  `lev4` int DEFAULT '0',
  `kode_akun` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `nama_akun` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `jenis_mutasi` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tgl_nonaktif` date DEFAULT NULL,
  `saldo_awal` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tb2022` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tbk2022` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tb2021` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tbk2021` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '0',
  `tb2020` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tbk2020` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tb2019` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tbk2019` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tb2018` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tbk2018` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tb2017` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tbk2017` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tb2016` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tbk2016` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '0',
  `tb2015` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '''0''',
  `tbk2015` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '''0'''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data untuk tabel `rekening_1`
--

INSERT INTO `rekening_1` (`parent_id`, `lev1`, `lev2`, `lev3`, `lev4`, `kode_akun`, `nama_akun`, `jenis_mutasi`, `tgl_nonaktif`, `saldo_awal`, `tb2022`, `tbk2022`, `tb2021`, `tbk2021`, `tb2020`, `tbk2020`, `tb2019`, `tbk2019`, `tb2018`, `tbk2018`, `tb2017`, `tbk2017`, `tb2016`, `tbk2016`, `tb2015`, `tbk2015`) VALUES
('111', 1, 1, 1, 1, '1.1.01.01', 'Kas Tunai', 'debet', NULL, '28302510600', '174802525130', '174802502208', '157819541830', '157815320411', '141367034830', '141366921566', '125685286830', '125685250670', '110857806830', '110856787531', '95448263530', '95442593381', '0', '0', '0', '0'),
('111', 1, 1, 1, 2, '1.1.01.02', 'Kas Kecil', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('111', 1, 1, 1, 3, '1.1.01.03', 'Kas di Bank Ops', 'debet', NULL, '1394386053', '5645559670', '5643880913', '5065294757', '5063894931', '4535086933', '4496295865', '3994965757', '3990693629', '3529603512', '3523543180', '2815291833', '2793448570', '0', '0', '0', '0'),
('111', 1, 1, 1, 4, '1.1.01.04', 'Kas di Bank SPP', 'debet', NULL, '12837711476', '82611444876', '81325396504', '74469978047', '73600459798', '66838666908', '65864423870', '59374543866', '58668666461', '52299734829', '51837174455', '45335510871', '44752799123', '0', '0', '0', '0'),
('111', 1, 1, 1, 5, '1.1.01.05', 'Kas di Bank Bumkalma', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('112', 1, 1, 2, 1, '1.1.02.01', 'Deposito', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('112', 1, 1, 2, 2, '1.1.02.02', 'Saham', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('112', 1, 1, 2, 3, '1.1.02.03', 'Obligasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 1, '1.1.03.01', 'Piutang Masyarakat SPP (Pokok)', 'debet', NULL, '0', '32410800000', '28805398000', '25359800000', '21767140500', '18243300000', '15149196000', '11682500000', '8677372500', '5399500000', '2535991500', '4773500000', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 2, '1.1.03.02', 'Piutang Masyarakat UEP (Pokok)', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 3, '1.1.03.03', 'Piutang Lembaga Lain (Pokok) ', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 4, '1.1.03.04', 'Piutang Jasa SPP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 5, '1.1.03.05', 'Piutang Jasa UEP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 6, '1.1.03.06', 'Piutang Jasa Lembaga Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 7, '1.1.03.07', 'Piutang Dividen', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('113', 1, 1, 3, 8, '1.1.03.08', 'Piutang lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 1, '1.1.04.01', 'Cadangan Kerugian Piutang Pokok SPP', 'debet', NULL, '0', '0', '374007160', '0', '374007160', '0', '318522810', '0', '263477200', '0', '210747200', '0', '164034166', '0', '0', '0', '0'),
('114', 1, 1, 4, 2, '1.1.04.02', 'Cadangan Kerugian Piutang Pokok UEP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 3, '1.1.04.03', 'Cadangan Kerugian Piutang Pokok Lembaga Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 4, '1.1.04.04', 'Cadangan Kerugian Piutang Jasa SPP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 5, '1.1.04.05', 'Cadangan Kerugian Piutang Jasa UEP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 6, '1.1.04.06', 'Cadangan Kerugian Piutang Jasa Lembaga Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('114', 1, 1, 4, 7, '1.1.04.07', 'Cadangan Kerugian Piutang Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('115', 1, 1, 5, 1, '1.1.05.01', 'Rekening antar Kantor (RK unit Usaha 1)', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('115', 1, 1, 5, 2, '1.1.05.02', 'Rekening antar Kantor (RK unit Usaha 2)', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('115', 1, 1, 5, 3, '1.1.05.03', 'Rekening antar Kantor (RK unit Usaha 3)', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('116', 1, 1, 6, 1, '1.1.06.01', 'Investasi unit Usaha 1', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('116', 1, 1, 6, 2, '1.1.06.02', 'Investasi unit Usaha 2', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('116', 1, 1, 6, 3, '1.1.06.03', 'Investasi unit Usaha 3', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('121', 1, 2, 1, 1, '1.2.01.01', 'Tanah', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('121', 1, 2, 1, 2, '1.2.01.02', 'Gedung & Bangunan', 'debet', NULL, '0', '309518000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('121', 1, 2, 1, 3, '1.2.01.03', 'Kendaraan dan Mesin', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('121', 1, 2, 1, 4, '1.2.01.04', 'Inventaris/Peralatan', 'debet', NULL, '0', '161430338', '0', '148984838', '133366057', '141984838', '116891521', '129994838', '100001053', '109248738', '83270125', '105873738', '81818451', '0', '0', '0', '0'),
('122', 1, 2, 2, 1, '1.2.02.01', 'Akumulasi penyusutan Gedung dan Bangunan', 'debet', NULL, '0', '0', '88606137.50', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('122', 1, 2, 2, 2, '1.2.02.02', 'Akumulasi penyusutan Kendaraan dan Mesin', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('122', 1, 2, 2, 3, '1.2.02.03', 'Akumulasi penyusutan Inventaris/Peralatan', 'debet', NULL, '3050000', '0', '132025075.00', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('123', 1, 2, 3, 1, '1.2.03.01', 'Biaya Pendirian Organisasi', 'debet', NULL, '0', '0', '0', '280523000', '44416110', '280523000', '30389970', '280523000', '16363830', '280523000', '2337690', '280523000', '1168846', '0', '0', '0', '0'),
('123', 1, 2, 3, 2, '1.2.03.02', 'Lisensi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('123', 1, 2, 3, 3, '1.2.03.03', 'Sewa dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('123', 1, 2, 3, 4, '1.2.03.04', 'Asuransi dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('124', 1, 2, 4, 1, '1.2.04.01', 'Akumulasi Amortisasi Biaya Pendirian Organisasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('124', 1, 2, 4, 2, '1.2.04.02', 'Akumulasi Amortisasi Lisensi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('124', 1, 2, 4, 3, '1.2.04.03', 'Akumulasi Amortisasi Sewa dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('124', 1, 2, 4, 4, '1.2.04.04', 'Akumulasi Amortisasi Asuransi dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('125', 1, 2, 5, 1, '1.2.05.01', 'Konstruksi Dalam Pengerjaan dan Uang Muka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('131', 1, 3, 1, 1, '1.3.01.01', 'Aset Lain-lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('211', 2, 1, 1, 1, '2.1.01.01', 'Utang Bank 1', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('211', 2, 1, 1, 2, '2.1.01.02', 'Utang Bank 2', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('212', 2, 1, 2, 1, '2.1.02.01', 'Utang Gaji', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('212', 2, 1, 2, 2, '2.1.02.02', 'Utang Honor', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('212', 2, 1, 2, 3, '2.1.02.03', 'Utang Tunjangan', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('212', 2, 1, 2, 4, '2.1.02.04', 'Utang Bonus Prestasi Kerja', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('212', 2, 1, 2, 5, '2.1.02.05', 'Utang Biaya Operasional lainnya', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('213', 2, 1, 3, 1, '2.1.03.01', 'Utang Pajak', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('214', 2, 1, 4, 1, '2.1.04.01', 'Utang Laba Bagian Masyarakat', 'kredit', NULL, '0', '0', '155630000.00', '0', '154400000', '0', '138529500', '0', '133000000', '0', '129060000', '0', '3500575', '0', '0', '0', '0'),
('214', 2, 1, 4, 2, '2.1.04.02', 'Utang Laba Bagian Desa', 'kredit', NULL, '0', '0', '67634000.00', '0', '134850000', '0', '160000000', '0', '155000000', '0', '146000000', '0', '0', '0', '0', '0', '0'),
('214', 2, 1, 4, 3, '2.1.04.03', 'Utang Laba Bagian Penyerta Modal', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('215', 2, 1, 5, 1, '2.1.05.01', 'Utang Jangka Pendek Lainnya', 'kredit', NULL, '0', '0', '117057000', '0', '31000000', '0', '0', '0', '0', '0', '0', '0', '250000', '0', '0', '0', '0'),
('221', 2, 2, 1, 1, '2.2.01.01', 'Utang Bank 1', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('221', 2, 2, 1, 2, '2.2.01.02', 'Dana Pensiuan', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('222', 2, 2, 2, 1, '2.2.02.01', 'Utang Jangka Panjang Lainnya', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('311', 3, 1, 1, 1, '3.1.01.01', 'Modal Masyarakat Desa (Eks. PNPM)', 'kredit', NULL, '0', '0', '642756350', '0', '642756350', '0', '642756350', '0', '642756350', '0', '642756350', '0', '642756350', '0', '0', '0', '0'),
('311', 3, 1, 1, 2, '3.1.01.02', 'Modal Desa Pendiri', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('311', 3, 1, 1, 3, '3.1.01.03', 'Modal Masyarakat', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('312', 3, 1, 2, 1, '3.1.02.01', 'Modal Lain-lain', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('321', 3, 2, 1, 1, '3.2.01.01', 'Laba Ditahan s/d Tahun lalu', 'kredit', NULL, '0', '0', '3786384666.50', '0', '3382511155', '0', '3122669057', '0', '2815232598', '0', '2508748878', '0', '2200214670', '0', '0', '0', '0'),
('322', 3, 2, 2, 1, '3.2.02.01', 'Laba/Rugi Tahun Berjalan', 'kredit', NULL, '0', '0', '', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 1, '4.1.01.01', 'Pendapatan Jasa Piutang SPP', 'kredit', NULL, '815437500', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 2, '4.1.01.02', 'Pendapatan Jasa Piutang UEP', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 3, '4.1.01.03', 'Pendapatan Jasa Piutang Lembaga Lain', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 4, '4.1.01.04', 'Pendapatan Denda Piutang SPP', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 5, '4.1.01.05', 'Pendapatan Denda Piutang UEP', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('411', 4, 1, 1, 6, '4.1.01.06', 'Pendapatan Denda Piutang Lembaga Lain', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('412', 4, 1, 2, 1, '4.1.02.01', 'Pendapatan Dividen Unit Usaha 1', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('412', 4, 1, 2, 2, '4.1.02.02', 'Pendapatan Dividen Unit Usaha 2', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('412', 4, 1, 2, 3, '4.1.02.03', 'Pendapatan Dividen Unit Usaha 3', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('412', 4, 1, 2, 99, '4.1.02.99', 'Pendapatan Usaha Lainnya', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 1, '4.2.01.01', 'Pendapatan Bunga Bank', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 2, '4.2.01.02', 'Pendapatan Bunga Deposito', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 3, '4.2.01.03', 'Pendapatan Surat Berharga', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 4, '4.2.01.04', 'Pertambahan Nilai Penjualan Aset', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 5, '4.2.01.05', 'Pendapatan Hadiah', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 6, '4.2.01.06', 'Pendapatan Hibah', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('421', 4, 2, 1, 7, '4.2.01.07', 'Pendapatan Non Usaha Lainnya', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('431', 4, 3, 1, 1, '4.3.01.01', 'Pendapatan revaluasi Aset', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('431', 4, 3, 1, 2, '4.3.01.02', 'Pendapatan revaluasi Saham', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('431', 4, 3, 1, 3, '4.3.01.03', 'Pendapatan lain-lain Lainnya', 'kredit', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 1, '5.1.01.01', 'Beban Gaji PO ', 'debet', NULL, '110400000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 2, '5.1.01.02', 'Beban Gaji Pegawai ', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 3, '5.1.01.03', 'Beban Honor Verifikator', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 4, '5.1.01.04', 'Beban Honor Pengawas', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 5, '5.1.01.05', 'Beban Honor Penasihat', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 6, '5.1.01.06', 'Beban Honor Tim Penanganan Masalah', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 7, '5.1.01.07', 'Beban Honor Tim Pendanaan', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('511', 5, 1, 1, 8, '5.1.01.08', 'Beban Honor Petugas Keamanan dan Kebersihan', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 1, '5.1.02.01', 'Beban Tunjangan Jabatan', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 2, '5.1.02.02', 'Beban Tunjangan Komunikasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 3, '5.1.02.03', 'Beban Tunjangan Hari Raya', 'debet', NULL, '3050000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 4, '5.1.02.04', 'Beban Tunjangan Asuransi/BPJS', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 5, '5.1.02.05', 'Bonus Prestasi Kerja', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('512', 5, 1, 2, 6, '5.1.02.06', 'Tunjangan Pensiun', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('513', 5, 1, 3, 1, '5.1.03.01', 'Beban Administrasi dan Umum', 'debet', NULL, '6108750', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('513', 5, 1, 3, 2, '5.1.03.02', 'Beban Listrik', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('513', 5, 1, 3, 3, '5.1.03.03', 'Beban Internet', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('513', 5, 1, 3, 4, '5.1.03.04', 'Beban Pemeliharaan & Perbaikan Aset', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('514', 5, 1, 4, 1, '5.1.04.01', 'Konsumsi Kantor dan Tamu', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('514', 5, 1, 4, 2, '5.1.04.02', 'Beban Iuran Organisasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('514', 5, 1, 4, 3, '5.1.04.03', 'Beban Biaya Audit', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('515', 5, 1, 5, 1, '5.1.05.01', 'Beban Rapat / MAD', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('515', 5, 1, 5, 2, '5.1.05.02', 'Beban Peningkatan Kapasitas', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('515', 5, 1, 5, 3, '5.1.05.03', 'Beban Pembinaan Kelompok Bermasalah', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('516', 5, 1, 6, 1, '5.1.06.01', 'Beban Perjalanan Dinas', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('516', 5, 1, 6, 2, '5.1.06.02', 'Beban Transportasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 1, '5.1.07.01', 'Beban Penyisihan Kerugian Piutang SPP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 2, '5.1.07.02', 'Beban Penyisihan Kerugian Piutang UEP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 3, '5.1.07.03', 'Beban Penyisihan Kerugian Piutang Lembaga Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 4, '5.1.07.04', 'Beban Penyisihan Kerugian Piutang Jasa SPP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 5, '5.1.07.05', 'Beban Penyisihan Kerugian Piutang Jasa UEP', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 6, '5.1.07.06', 'Beban Penyisihan Kerugian Piutang Jasa Lembaga Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 7, '5.1.07.07', 'Beban Penyisihan Kerugian Piutang Lain', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 8, '5.1.07.08', 'Beban Penyusutan Gedung dan Bangunan', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 9, '5.1.07.09', 'Beban Penyusutan Kendaraan & Mesin', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 10, '5.1.07.10', 'Beban Penyusutan Inventaris', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 11, '5.1.07.11', 'Beban Amortisasi Biaya Pendirian Organisasi', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 12, '5.1.07.12', 'Beban Amortisasi Lisensi', 'debet', NULL, '3585096', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 13, '5.1.07.13', 'Beban Amortisasi Sewa dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('517', 5, 1, 7, 14, '5.1.07.14', 'Beban Amortisasi Asuransi dibayar dimuka', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('518', 5, 1, 8, 1, '5.1.08.01', 'Beban Bunga Utang Bank', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('519', 5, 1, 9, 1, '5.1.09.01', 'Beban Usaha Lainnya', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('521', 5, 2, 1, 1, '5.2.01.01', 'Beban IPTW', 'debet', NULL, '76368000', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('521', 5, 2, 1, 2, '5.2.01.02', 'Beban Seragam PO dan Pegawai', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('521', 5, 2, 1, 3, '5.2.01.03', 'Beban Spanduk/Papan Nama', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('521', 5, 2, 1, 4, '5.2.01.04', 'Beban Pemasaran lainnya', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('531', 5, 3, 1, 1, '5.3.01.01', 'Beban Pajak Bank', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('531', 5, 3, 1, 2, '5.3.01.02', 'Beban Administrasi Bank', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('532', 5, 3, 2, 1, '5.3.02.01', 'Beban Penghapusan Aset Tetap', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('533', 5, 3, 3, 1, '5.3.03.01', 'Beban Sumbangan Kegiatan Kemasyarakatan', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('533', 5, 3, 3, 2, '5.3.03.02', 'Beban Kegiatan Sosial', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('534', 5, 3, 4, 1, '5.3.04.01', 'Beban Non Usaha Lainnya', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0'),
('541', 5, 4, 1, 1, '5.4.01.01', 'Taksiran PPh', 'debet', NULL, '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0');

--
-- Indeks untuk tabel yang dibuang
--

--
-- Indeks untuk tabel `akun_level_1`
--
ALTER TABLE `akun_level_1`
  ADD PRIMARY KEY (`kode_akun`,`id`);

--
-- Indeks untuk tabel `akun_level_2`
--
ALTER TABLE `akun_level_2`
  ADD PRIMARY KEY (`kode_akun`,`id`);

--
-- Indeks untuk tabel `akun_level_3`
--
ALTER TABLE `akun_level_3`
  ADD PRIMARY KEY (`kode_akun`,`id`);

--
-- Indeks untuk tabel `rekening_1`
--
ALTER TABLE `rekening_1`
  ADD PRIMARY KEY (`kode_akun`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
