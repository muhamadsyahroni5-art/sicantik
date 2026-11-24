-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versi server:                 8.0.30 - MySQL Community Server - GPL
-- OS Server:                    Win64
-- HeidiSQL Versi:               12.1.0.6537
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Membuang struktur basisdata untuk sicantik_kemenag_lebak
CREATE DATABASE IF NOT EXISTS `sicantik_kemenag_lebak` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `sicantik_kemenag_lebak`;

-- membuang struktur untuk table sicantik_kemenag_lebak.app_settings
CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.app_settings: ~0 rows (lebih kurang)
INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES
	('wa_template', 'Halo {nama}, pemberitahuan dari SICANTIK Kemenag Kab. Lebak: Usulan {jenis} Anda ({tanggal}, {lama} hari kerja) saat ini berstatus *{status}*. Terima kasih.');

-- membuang struktur untuk table sicantik_kemenag_lebak.cuti_requests
CREATE TABLE IF NOT EXISTS `cuti_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pegawai_id` int NOT NULL,
  `jenis_cuti` varchar(100) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `lama_hari_cuti` int NOT NULL,
  `alasan` text NOT NULL,
  `alamat_selama_cuti` text NOT NULL,
  `dokumen_pdf` varchar(255) DEFAULT 'Surat_Permohonan_Cuti.pdf',
  `dokumen_pdf_data` mediumtext,
  `status_atasan` enum('Pending','Disetujui','Perubahan','Ditolak','Ditangguhkan') NOT NULL DEFAULT 'Pending',
  `status_kepala` enum('Pending','Disetujui','Perubahan','Ditolak','Ditangguhkan') NOT NULL DEFAULT 'Pending',
  `status_akhir` varchar(60) NOT NULL DEFAULT 'Menunggu Verifikasi Atasan',
  `catatan_atasan` text,
  `catatan_kepala` text,
  `nomor_surat` varchar(100) DEFAULT NULL,
  `tanggal_pengajuan` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_cuti_pegawai` (`pegawai_id`),
  CONSTRAINT `fk_cuti_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.cuti_requests: ~9 rows (lebih kurang)

-- membuang struktur untuk table sicantik_kemenag_lebak.hari_libur
CREATE TABLE IF NOT EXISTS `hari_libur` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `keterangan` varchar(200) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.hari_libur: ~6 rows (lebih kurang)
INSERT INTO `hari_libur` (`id`, `tanggal`, `keterangan`) VALUES
	(1, '2026-01-01', 'Tahun Baru Masehi'),
	(2, '2026-03-31', 'Hari Raya Idul Fitri 1447 H'),
	(3, '2026-04-01', 'Hari Raya Idul Fitri 1447 H (Cuti Bersama)'),
	(4, '2026-05-27', 'Hari Raya Idul Adha 1447 H'),
	(5, '2026-08-17', 'HUT Kemerdekaan RI ke-81'),
	(6, '2026-12-25', 'Hari Raya Natal'),
	(7, '2026-05-01', 'Hari buruh'),
	(8, '2026-12-24', 'cuti bersama hari natal');

-- membuang struktur untuk table sicantik_kemenag_lebak.pegawai
CREATE TABLE IF NOT EXISTS `pegawai` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(150) NOT NULL,
  `nip` varchar(30) NOT NULL,
  `jabatan` varchar(120) NOT NULL,
  `unit_kerja_id` int DEFAULT NULL,
  `status_pegawai` enum('PNS','PPPK') NOT NULL DEFAULT 'PNS',
  `bulan_mulai_kerja` tinyint NOT NULL DEFAULT '1',
  `tahun_mulai_kerja` smallint NOT NULL DEFAULT '2020',
  `no_wa` varchar(25) NOT NULL DEFAULT '',
  `sctn2` int NOT NULL DEFAULT '0',
  `sctn1` int NOT NULL DEFAULT '0',
  `sctn` int NOT NULL DEFAULT '12',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nip` (`nip`),
  KEY `fk_pegawai_unit` (`unit_kerja_id`),
  CONSTRAINT `fk_pegawai_unit` FOREIGN KEY (`unit_kerja_id`) REFERENCES `unit_kerja` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.pegawai: ~171 rows (lebih kurang)

-- membuang struktur untuk table sicantik_kemenag_lebak.unit_kerja
CREATE TABLE IF NOT EXISTS `unit_kerja` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_unit` varchar(150) NOT NULL,
  `pimpinan` varchar(150) NOT NULL,
  `nip_pimpinan` varchar(30) NOT NULL,
  `jabatan_pimpinan` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.unit_kerja: ~8 rows (lebih kurang)
INSERT INTO `unit_kerja` (`id`, `nama_unit`, `pimpinan`, `nip_pimpinan`, `jabatan_pimpinan`) VALUES
	(1, 'Kepala Kantor', 'H. Badrusalam, S.Ag', '198107142011011006', 'Kepala Kantor Kementerian Agama Kab. Lebak'),
	(2, 'Sub Bagian Tata Usaha', 'H. Moh. Yasin, S.Ag., M.Pd.', '197205121998031002', 'Kepala Sub Bagian Tata Usaha'),
	(3, 'Seksi Pendidikan Madrasah (Penmad)', 'H. Ahmad Otong, S.Ag., M.Si.', '197508202000031004', 'Kepala Seksi Pendidikan Madrasah'),
	(4, 'Seksi Pendidikan Agama Islam (PAIS)', 'Dr. Hj. Siti Rohmah, M.Pd.', '197812152003122001', 'Kepala Seksi PAIS'),
	(5, 'Seksi Pend. Diniyah & Pondok Pesantren', 'Hj. Neng Eulis', '198004102005011003', 'Kepala Seksi Pend. Diniyah & Pontren'),
	(6, 'Seksi Bimbingan Masyarakat Islam', 'H. Sudirman, S.Ag. M.Pd', '198201152009011005', 'Kepala Seksi Bimas Islam'),
	(7, 'Penyelenggara Zakat & Wakaf', 'H. Abdul Basit', '197311101994031005', 'Kepala Peny. Zakat & Wakaf'),
	(8, 'KUA Kecamatan Rangkasbitung', 'H. Aminin Nurdin', '198305202009101001', 'Kepala KUA Kec. Rangkasbitung');

-- membuang struktur untuk table sicantik_kemenag_lebak.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL,
  `nip` varchar(30) NOT NULL,
  `password` varchar(255) NOT NULL,
  `jabatan` varchar(120) NOT NULL,
  `status_pegawai` enum('PNS','PPPK') NOT NULL DEFAULT 'PNS',
  `unit_kerja_id` int DEFAULT NULL,
  `no_wa` varchar(25) NOT NULL DEFAULT '',
  `role` enum('superadmin','admin','operator') NOT NULL DEFAULT 'operator',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `nip` (`nip`),
  KEY `fk_users_unit` (`unit_kerja_id`),
  CONSTRAINT `fk_users_unit` FOREIGN KEY (`unit_kerja_id`) REFERENCES `unit_kerja` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Membuang data untuk tabel sicantik_kemenag_lebak.users: ~8 rows (lebih kurang)
INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `nip`, `password`, `jabatan`, `status_pegawai`, `unit_kerja_id`, `no_wa`, `role`) VALUES
	(1, 'H. Badrusalam, S.Ag (Super Admin)', 'superadmin', '197001011995031001', 'password123', 'Kepala Kantor / Super Admin', 'PNS', 1, '081112345678', 'superadmin'),
	(2, 'H. Moh. Yasin, S.Ag., M.Pd. (Admin)', 'admin', '197205121998031002', 'password123', 'Kasubbag TU / Admin', 'PNS', 2, '081234567890', 'admin'),
	(3, 'Fadli Kurnia DC', 'super', '198107142011011006', 'prakom123', 'JF Prakom Ahli Muda', 'PNS', 1, '087772600662', 'superadmin');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
