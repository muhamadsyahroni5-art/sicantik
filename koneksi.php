<?php
/**
 * Koneksi Database MySQL & Auto-Bootstrap untuk SICANTIK Kemenag Lebak
 * Kompatibel dengan Laragon & XAMPP (PHP 7.4 - 8.3 & MySQL 5.7/8.0 / MariaDB)
 * Otomatis membuat database, tabel, dan data awal jika belum tersedia.
 */
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = getenv('SICANTIK_DB_HOST') ?: 'localhost';
$dbUser = getenv('SICANTIK_DB_USER') ?: 'sicantik_user';
$dbPass = getenv('SICANTIK_DB_PASS') !== false ? getenv('SICANTIK_DB_PASS') : 'Prakom123*';
$dbName = getenv('SICANTIK_DB_NAME') ?: 'sicantik_kemenag_lebak';
$dbPort = (int)(getenv('SICANTIK_DB_PORT') ?: 3306);

// Coba beberapa kombinasi host/port umum Laragon & XAMPP agar langsung terkoneksi
$candidates = [
    [$dbHost, $dbPort],
    ['127.0.0.1', $dbPort],
    ['localhost', 3306],
    ['127.0.0.1', 3306],
    ['localhost', 3307],
    ['127.0.0.1', 3307],
];

$conn = null;
$lastConnectError = '';
$tried = [];
foreach ($candidates as [$tryHost, $tryPort]) {
    $key = $tryHost . ':' . $tryPort;
    if (isset($tried[$key])) continue;
    $tried[$key] = true;

    $attempt = @mysqli_connect($tryHost, $dbUser, $dbPass, '', (int)$tryPort);
    if ($attempt) {
        $conn = $attempt;
        break;
    }
    $lastConnectError = mysqli_connect_error();
}

if (!$conn) {
    die(
        '<div style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:24px;border:1px solid #fecdd3;background:#fff1f2;color:#9f1239;border-radius:12px;">'
        . '<h2 style="margin-top:0;">Koneksi MySQL Gagal</h2>'
        . '<p>Pastikan service <b>MySQL / MariaDB</b> di <b>Laragon</b> atau <b>XAMPP</b> sudah dijalankan.</p>'
        . '<p><b>Detail Error:</b> ' . htmlspecialchars((string)$lastConnectError, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
    );
}

mysqli_set_charset($conn, 'utf8mb4');
// Pastikan query GROUP BY tetap kompatibel di MySQL 8.0 (Laragon)
@mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Auto-create database jika belum ada
if (!@mysqli_select_db($conn, $dbName)) {
    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if (!@mysqli_select_db($conn, $dbName)) {
        die(
            '<div style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:24px;border:1px solid #fecdd3;background:#fff1f2;color:#9f1239;border-radius:12px;">'
            . '<h2 style="margin-top:0;">Database Tidak Dapat Dipilih</h2>'
            . '<p>Gagal membuat atau membuka database <code>' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code>.</p>'
            . '<p><b>Detail Error:</b> ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8') . '</p>'
            . '</div>'
        );
    }
}

// Pastikan seluruh tabel tersedia (aman dijalankan berulang via IF NOT EXISTS)
$schemaQueries = [
    "CREATE TABLE IF NOT EXISTS `unit_kerja` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_unit` VARCHAR(150) NOT NULL,
        `pimpinan` VARCHAR(150) NOT NULL,
        `nip_pimpinan` VARCHAR(30) NOT NULL,
        `jabatan_pimpinan` VARCHAR(150) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `pegawai` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_lengkap` VARCHAR(150) NOT NULL,
        `nip` VARCHAR(30) NOT NULL UNIQUE,
        `jabatan` VARCHAR(120) NOT NULL,
        `unit_kerja_id` INT NULL,
        `status_pegawai` ENUM('PNS', 'PPPK') NOT NULL DEFAULT 'PNS',
        `bulan_mulai_kerja` TINYINT NOT NULL DEFAULT 1,
        `tahun_mulai_kerja` SMALLINT NOT NULL DEFAULT 2020,
        `no_wa` VARCHAR(25) NOT NULL DEFAULT '',
        `sctn2` INT NOT NULL DEFAULT 0,
        `sctn1` INT NOT NULL DEFAULT 0,
        `sctn` INT NOT NULL DEFAULT 12,
        CONSTRAINT `fk_pegawai_unit` FOREIGN KEY (`unit_kerja_id`) REFERENCES `unit_kerja`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_lengkap` VARCHAR(150) NOT NULL,
        `username` VARCHAR(60) NOT NULL UNIQUE,
        `nip` VARCHAR(30) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `jabatan` VARCHAR(120) NOT NULL,
        `status_pegawai` ENUM('PNS', 'PPPK') NOT NULL DEFAULT 'PNS',
        `unit_kerja_id` INT NULL,
        `no_wa` VARCHAR(25) NOT NULL DEFAULT '',
        `role` ENUM('superadmin', 'admin', 'operator') NOT NULL DEFAULT 'operator',
        CONSTRAINT `fk_users_unit` FOREIGN KEY (`unit_kerja_id`) REFERENCES `unit_kerja`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `cuti_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `pegawai_id` INT NOT NULL,
        `jenis_cuti` VARCHAR(100) NOT NULL,
        `tanggal_mulai` DATE NOT NULL,
        `tanggal_selesai` DATE NOT NULL,
        `lama_hari_cuti` INT NOT NULL,
        `alasan` TEXT NOT NULL,
        `alamat_selama_cuti` TEXT NOT NULL,
        `dokumen_pdf` VARCHAR(255) DEFAULT 'Surat_Permohonan_Cuti.pdf',
        `dokumen_pdf_data` MEDIUMTEXT NULL,
        `status_atasan` ENUM('Pending', 'Disetujui', 'Perubahan', 'Ditolak', 'Ditangguhkan') NOT NULL DEFAULT 'Pending',
        `status_kepala` ENUM('Pending', 'Disetujui', 'Perubahan', 'Ditolak', 'Ditangguhkan') NOT NULL DEFAULT 'Pending',
        `status_akhir` VARCHAR(60) NOT NULL DEFAULT 'Menunggu Verifikasi Atasan',
        `catatan_atasan` TEXT NULL,
        `catatan_kepala` TEXT NULL,
        `nomor_surat` VARCHAR(100) NULL,
        `tanggal_pengajuan` DATE NOT NULL,
        CONSTRAINT `fk_cuti_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pegawai`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `hari_libur` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `tanggal` DATE NOT NULL,
        `keterangan` VARCHAR(200) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS `app_settings` (
        `setting_key` VARCHAR(100) PRIMARY KEY,
        `setting_value` TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($schemaQueries as $q) {
    @mysqli_query($conn, $q);
}

// Migrasi ringan jika tabel cuti_requests lama belum punya kolom dokumen_pdf_data
$colCheck = @mysqli_query($conn, "SHOW COLUMNS FROM `cuti_requests` LIKE 'dokumen_pdf_data'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
    @mysqli_query($conn, "ALTER TABLE `cuti_requests` ADD COLUMN `dokumen_pdf_data` MEDIUMTEXT NULL AFTER `dokumen_pdf`");
}

// Seed data awal otomatis hanya jika database masih kosong (belum ada user & unit)
$unitCountRes = @mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `unit_kerja`");
$unitCount = $unitCountRes ? (int)(mysqli_fetch_assoc($unitCountRes)['cnt'] ?? 0) : 0;
if ($unitCount === 0) {
    @mysqli_query($conn, "INSERT IGNORE INTO `unit_kerja` (`id`, `nama_unit`, `pimpinan`, `nip_pimpinan`, `jabatan_pimpinan`) VALUES
        (1, 'Kepala Kantor', 'Dr. H. Encep Saepudin, M.Ag.', '197001011995031001', 'Kepala Kantor Kementerian Agama Kabupaten Lebak'),
        (2, 'Sub Bagian Tata Usaha', 'H. Moh. Yasin, S.Ag., M.Pd.', '197205121998031002', 'Kepala Sub Bagian Tata Usaha'),
        (3, 'Seksi Pendidikan Madrasah (Penmad)', 'H. Ahmad Otong, S.Ag., M.Si.', '197508202000031004', 'Kepala Seksi Pendidikan Madrasah'),
        (4, 'Seksi Pendidikan Agama Islam (PAIS)', 'Dr. Hj. Siti Rohmah, M.Pd.', '197812152003122001', 'Kepala Seksi PAIS'),
        (5, 'Seksi Penyelenggaraan Haji dan Umrah (PHU)', 'H. Encep Saepudin, S.Ag.', '198004102005011003', 'Kepala Seksi PHU'),
        (6, 'Seksi Bimbingan Masyarakat Islam', 'H. Hidayatullah, M.Sy.', '198201152009011005', 'Kepala Seksi Bimas Islam'),
        (7, 'KUA Kecamatan Rangkasbitung', 'Drs. H. Dede Sujana', '196811101994031005', 'Kepala KUA / Penghulu Madya'),
        (8, 'KUA Kecamatan Cibadak', 'H. Ade Supriatna, S.Hi.', '198305202009101001', 'Kepala KUA')");

    @mysqli_query($conn, "INSERT IGNORE INTO `pegawai` (`id`, `nama_lengkap`, `nip`, `jabatan`, `unit_kerja_id`, `status_pegawai`, `bulan_mulai_kerja`, `tahun_mulai_kerja`, `no_wa`, `sctn2`, `sctn1`, `sctn`) VALUES
        (1, 'Drs. H. Nanang Saparudin, M.Pd.', '196901011994031001', 'Analis Kepegawaian Ahli Muda', 2, 'PNS', 3, 1994, '081288881111', 0, 6, 12),
        (2, 'Siti Aminah, S.Kom.', '198505122010012003', 'Pranata Komputer Ahli Pertama', 2, 'PNS', 1, 2010, '081399992222', 2, 6, 10),
        (3, 'Rian Hidayat, S.Pd.I.', '199003202019031002', 'Guru Ahli Pertama', 3, 'PNS', 3, 2019, '081911113333', 0, 4, 12),
        (4, 'Dewi Lestari, S.E.', '199307152023212001', 'Pengelola Administrasi Kepegawaian', 2, 'PPPK', 2, 2023, '085277774444', 0, 0, 12),
        (5, 'Asep Saepullah, M.Pd.', '198801102014121001', 'Pengembang Teknologi Pembelajaran', 3, 'PNS', 12, 2014, '087755556666', 1, 6, 12),
        (6, 'Nurul Hidayah, S.Ag.', '199109052024212002', 'Penyuluh Agama Islam', 7, 'PPPK', 4, 2024, '081233339999', 0, 0, 12)");

    @mysqli_query($conn, "INSERT IGNORE INTO `hari_libur` (`id`, `tanggal`, `keterangan`) VALUES
        (1, '2026-01-01', 'Tahun Baru Masehi'),
        (2, '2026-03-31', 'Hari Raya Idul Fitri 1447 H'),
        (3, '2026-04-01', 'Hari Raya Idul Fitri 1447 H (Cuti Bersama)'),
        (4, '2026-05-27', 'Hari Raya Idul Adha 1447 H'),
        (5, '2026-08-17', 'HUT Kemerdekaan RI ke-81'),
        (6, '2026-12-25', 'Hari Raya Natal')");

    @mysqli_query($conn, "INSERT IGNORE INTO `cuti_requests` (`id`, `pegawai_id`, `jenis_cuti`, `tanggal_mulai`, `tanggal_selesai`, `lama_hari_cuti`, `alasan`, `alamat_selama_cuti`, `dokumen_pdf`, `status_atasan`, `status_kepala`, `status_akhir`, `catatan_atasan`, `catatan_kepala`, `nomor_surat`, `tanggal_pengajuan`) VALUES
        (1, 2, 'Cuti Tahunan', '2026-06-10', '2026-06-12', 3, 'Keperluan keluarga dan silaturahmi mudik lebaran', 'Jl. Raya Rangkasbitung - Pandeglang Km. 5 Lebak', 'Surat_Permohonan_Cuti.pdf', 'Disetujui', 'Disetujui', 'Disetujui', 'Silakan laksanakan cuti dengan baik dan kembali tepat waktu.', 'Disetujui sesuai ketentuan Perka BKN No. 24 Tahun 2017.', 'B-123/Kemenag.Lb/Kp.02.3/06/2026', '2026-05-20')");
}

$userCountRes = @mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `users`");
$userCount = $userCountRes ? (int)(mysqli_fetch_assoc($userCountRes)['cnt'] ?? 0) : 0;
if ($userCount === 0) {
    @mysqli_query($conn, "INSERT IGNORE INTO `users` (`id`, `nama_lengkap`, `username`, `nip`, `password`, `jabatan`, `status_pegawai`, `unit_kerja_id`, `no_wa`, `role`) VALUES
        (1, 'Dr. H. Encep Saepudin, M.Ag. (Super Admin)', 'superadmin', '197001011995031001', 'password123', 'Kepala Kantor / Super Admin', 'PNS', 2, '081112345678', 'superadmin'),
        (2, 'H. Moh. Yasin, S.Ag., M.Pd. (Admin)', 'admin', '197205121998031002', 'password123', 'Kasubbag TU / Admin', 'PNS', 2, '081234567890', 'admin'),
        (3, 'Siti Aminah, S.Kom. (Operator)', 'operator', '198505122010012003', 'password123', 'Pranata Komputer / Operator TU', 'PNS', 2, '081399992222', 'operator'),
        (4, 'Rian Hidayat, S.Pd.I. (Operator Penmad)', 'operator_penmad', '199003202019031002', 'password123', 'Operator Seksi Penmad', 'PNS', 3, '081911113333', 'operator')");
}

@mysqli_query($conn, "INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
    ('wa_template', 'Halo {nama}, pemberitahuan dari SICANTIK Kemenag Kab. Lebak: Usulan {jenis} Anda ({tanggal}, {lama} hari kerja) saat ini berstatus *{status}*. Terima kasih.')");

// Pastikan folder uploads tersedia
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

// Helper Functions
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatDateIndo(?string $dateStr): string {
    if (!$dateStr) return '-';
    $ts = strtotime($dateStr);
    if ($ts === false) return e($dateStr);
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $d = (int)date('j', $ts);
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return "$d {$bulan[$m]} $y";
}

function hitungMasaKerja(int $bulanMulai, int $tahunMulai): array {
    $nowYear = (int)date('Y');
    $nowMonth = (int)date('n');
    $totalMonths = ($nowYear - $tahunMulai) * 12 + ($nowMonth - $bulanMulai);
    if ($totalMonths < 0) $totalMonths = 0;
    $years = intdiv($totalMonths, 12);
    $months = $totalMonths % 12;
    return [
        'years' => $years,
        'months' => $months,
        'isValidForLeave' => $years >= 1
    ];
}

function hitungHariKerja(string $startDate, string $endDate, mysqli $conn): int {
    if (!$startDate || !$endDate) return 0;
    $startTs = strtotime($startDate);
    $endTs = strtotime($endDate);
    if ($startTs === false || $endTs === false || $endTs < $startTs) return 0;

    $holidayDates = [];
    $res = mysqli_query($conn, "SELECT tanggal FROM hari_libur");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $holidayDates[$row['tanggal']] = true;
        }
    }

    $count = 0;
    for ($cur = $startTs; $cur <= $endTs; $cur += 86400) {
        $dayOfWeek = (int)date('N', $cur); // 6=Sabtu, 7=Minggu
        $ymd = date('Y-m-d', $cur);
        if ($dayOfWeek < 6 && !isset($holidayDates[$ymd])) {
            $count++;
        }
    }
    return $count;
}

function getWaTemplate(mysqli $conn): string {
    $default = 'Halo {nama}, pemberitahuan dari SICANTIK Kemenag Kab. Lebak: Usulan {jenis} Anda ({tanggal}, {lama} hari kerja) saat ini berstatus *{status}*. Terima kasih.';
    $res = mysqli_query($conn, "SELECT setting_value FROM app_settings WHERE setting_key = 'wa_template' LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row['setting_value'] ?: $default;
    }
    return $default;
}

function getKepalaKantor(mysqli $conn): array {
    $res = mysqli_query($conn, "SELECT * FROM unit_kerja WHERE LOWER(nama_unit) LIKE '%kepala kantor%' OR LOWER(jabatan_pimpinan) LIKE '%kepala kantor%' LIMIT 1");
    if ($res && ($row = mysqli_fetch_assoc($res))) {
        return $row;
    }
    return [
        'nama_unit' => 'Kepala Kantor',
        'pimpinan' => 'Dr. H. Encep Saepudin, M.Ag.',
        'nip_pimpinan' => '197001011995031001',
        'jabatan_pimpinan' => 'Kepala Kantor Kementerian Agama Kabupaten Lebak'
    ];
}

function requireAuth(): array {
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
    $user = $_SESSION['user'];
    $user['unit_kerja_id'] = $user['unit_kerja_id'] ?? null;
    return $user;
}

function requireRole(array $allowedRoles): array {
    $user = requireAuth();
    if (!in_array($user['role'], $allowedRoles, true)) {
        header('Location: index.php');
        exit;
    }
    return $user;
}
