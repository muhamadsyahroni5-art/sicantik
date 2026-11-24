<?php
/**
 * API Sinkronisasi MySQL Otomatis untuk SICANTIK Kemenag Lebak
 * Kompatibel dengan Laragon & XAMPP (PHP 7.4 - 8.3 & MySQL 5.7/8.0 / MariaDB)
 * Otomatis membuat database, tabel, dan data awal saat dijalankan di server Laragon/XAMPP.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

mysqli_report(MYSQLI_REPORT_OFF);

$dbHost = getenv('SICANTIK_DB_HOST') ?: 'localhost';
$dbUser = getenv('SICANTIK_DB_USER') ?: 'sicantik_user';
$dbPass = getenv('SICANTIK_DB_PASS') ?: 'Prakom123*';
$dbName = getenv('SICANTIK_DB_NAME') ?: 'sicantik_kemenag_lebak';
$dbPort = (int)(getenv('SICANTIK_DB_PORT') ?: 3306);

$candidates = [
    [$dbHost, $dbPort],
    ['127.0.0.1', $dbPort],
    ['localhost', 3306],
    ['127.0.0.1', 3306],
    ['localhost', 3307],
    ['127.0.0.1', 3307],
];

$conn = null;
$lastErr = '';
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
    $lastErr = mysqli_connect_error();
}

if (!$conn) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'driver' => 'mysql',
        'error' => 'Koneksi ke MySQL gagal. Pastikan service MySQL/MariaDB di Laragon atau XAMPP berjalan. (' . $lastErr . ')'
    ]);
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
@mysqli_query($conn, "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

if (!@mysqli_select_db($conn, $dbName)) {
    mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if (!@mysqli_select_db($conn, $dbName)) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'driver' => 'mysql',
            'error' => 'Gagal membuat atau memilih database ' . $dbName . ': ' . mysqli_error($conn)
        ]);
        exit;
    }
}

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

$colCheck = @mysqli_query($conn, "SHOW COLUMNS FROM `cuti_requests` LIKE 'dokumen_pdf_data'");
if ($colCheck && mysqli_num_rows($colCheck) === 0) {
    @mysqli_query($conn, "ALTER TABLE `cuti_requests` ADD COLUMN `dokumen_pdf_data` MEDIUMTEXT NULL AFTER `dokumen_pdf`");
}

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

$action = $_GET['action'] ?? 'bootstrap';

if ($action === 'bootstrap') {
    $unitList = [];
    if ($res = mysqli_query($conn, "SELECT * FROM `unit_kerja` ORDER BY id ASC")) {
        while ($r = mysqli_fetch_assoc($res)) {
            $unitList[] = [
                'id' => (string)$r['id'],
                'namaUnit' => $r['nama_unit'],
                'pimpinan' => $r['pimpinan'],
                'nipPimpinan' => $r['nip_pimpinan'],
                'jabatanPimpinan' => $r['jabatan_pimpinan'],
            ];
        }
    }

    $pegawaiList = [];
    if ($res = mysqli_query($conn, "SELECT * FROM `pegawai` ORDER BY id ASC")) {
        while ($r = mysqli_fetch_assoc($res)) {
            $pegawaiList[] = [
                'id' => (string)$r['id'],
                'namaLengkap' => $r['nama_lengkap'],
                'nip' => $r['nip'],
                'jabatan' => $r['jabatan'],
                'unitKerjaId' => $r['unit_kerja_id'] !== null ? (string)$r['unit_kerja_id'] : '',
                'statusPegawai' => $r['status_pegawai'],
                'bulanMulaiKerja' => (int)$r['bulan_mulai_kerja'],
                'tahunMulaiKerja' => (int)$r['tahun_mulai_kerja'],
                'noWa' => $r['no_wa'],
                'sctn2' => (int)$r['sctn2'],
                'sctn1' => (int)$r['sctn1'],
                'sctn' => (int)$r['sctn'],
            ];
        }
    }

    $userList = [];
    if ($res = mysqli_query($conn, "SELECT * FROM `users` ORDER BY id ASC")) {
        while ($r = mysqli_fetch_assoc($res)) {
            $userList[] = [
                'id' => (string)$r['id'],
                'namaLengkap' => $r['nama_lengkap'],
                'username' => $r['username'],
                'nip' => $r['nip'],
                'password' => $r['password'],
                'jabatan' => $r['jabatan'],
                'statusPegawai' => $r['status_pegawai'],
                'unitKerjaId' => $r['unit_kerja_id'] !== null ? (string)$r['unit_kerja_id'] : '',
                'noWa' => $r['no_wa'],
                'role' => $r['role'],
            ];
        }
    }

    $holidays = [];
    if ($res = mysqli_query($conn, "SELECT * FROM `hari_libur` ORDER BY tanggal ASC, id ASC")) {
        while ($r = mysqli_fetch_assoc($res)) {
            $holidays[] = [
                'id' => (string)$r['id'],
                'tanggal' => $r['tanggal'],
                'keterangan' => $r['keterangan'],
            ];
        }
    }

    $cutiList = [];
    if ($res = mysqli_query($conn, "SELECT * FROM `cuti_requests` ORDER BY id DESC")) {
        while ($r = mysqli_fetch_assoc($res)) {
            $item = [
                'id' => (string)$r['id'],
                'pegawaiId' => (string)$r['pegawai_id'],
                'jenisCuti' => $r['jenis_cuti'],
                'tanggalMulai' => $r['tanggal_mulai'],
                'tanggalSelesai' => $r['tanggal_selesai'],
                'lamaHariCuti' => (int)$r['lama_hari_cuti'],
                'alasan' => $r['alasan'],
                'alamatSelamaCuti' => $r['alamat_selama_cuti'],
                'dokumenPdf' => $r['dokumen_pdf'] ?: 'Surat_Permohonan_Cuti.pdf',
                'statusAtasan' => $r['status_atasan'],
                'statusKepala' => $r['status_kepala'],
                'statusAkhir' => $r['status_akhir'],
                'tanggalPengajuan' => $r['tanggal_pengajuan'],
            ];
            if (!empty($r['dokumen_pdf_data'])) {
                $item['dokumenPdfData'] = $r['dokumen_pdf_data'];
            }
            if ($r['catatan_atasan'] !== null && $r['catatan_atasan'] !== '') {
                $item['catatanAtasan'] = $r['catatan_atasan'];
            }
            if ($r['catatan_kepala'] !== null && $r['catatan_kepala'] !== '') {
                $item['catatanKepala'] = $r['catatan_kepala'];
            }
            if ($r['nomor_surat'] !== null && $r['nomor_surat'] !== '') {
                $item['nomorSurat'] = $r['nomor_surat'];
            }
            $cutiList[] = $item;
        }
    }

    $waTemplate = 'Halo {nama}, pemberitahuan dari SICANTIK Kemenag Kab. Lebak: Usulan {jenis} Anda ({tanggal}, {lama} hari kerja) saat ini berstatus *{status}*. Terima kasih.';
    if ($res = mysqli_query($conn, "SELECT setting_value FROM `app_settings` WHERE setting_key='wa_template' LIMIT 1")) {
        if ($row = mysqli_fetch_assoc($res)) {
            $waTemplate = $row['setting_value'] ?: $waTemplate;
        }
    }

    echo json_encode([
        'ok' => true,
        'driver' => 'mysql',
        'database' => $dbName,
        'data' => [
            'unitList' => $unitList,
            'pegawaiList' => $pegawaiList,
            'userList' => $userList,
            'holidays' => $holidays,
            'cutiList' => $cutiList,
            'waTemplate' => $waTemplate,
        ],
    ]);
    exit;
}

if ($action === 'sync' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Payload JSON tidak valid.']);
        exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $unitMap = [];
        $pegMap = [];
        $userMap = [];
        $holidayMap = [];
        $cutiMap = [];

        $keptUnits = [];
        $keptPegawai = [];
        $keptUsers = [];
        $keptHolidays = [];
        $keptCuti = [];

        // 1. Sync unit_kerja
        $unitsIn = is_array($payload['unitList'] ?? null) ? $payload['unitList'] : [];
        foreach ($unitsIn as $u) {
            $rawId = (string)($u['id'] ?? '');
            $nama = (string)($u['namaUnit'] ?? '');
            $pimpinan = (string)($u['pimpinan'] ?? '');
            $nip = (string)($u['nipPimpinan'] ?? '');
            $jabatan = (string)($u['jabatanPimpinan'] ?? '');
            if ($nama === '') continue;

            if (ctype_digit($rawId) && (int)$rawId > 0) {
                $id = (int)$rawId;
                $stmt = mysqli_prepare($conn, "INSERT INTO `unit_kerja` (`id`, `nama_unit`, `pimpinan`, `nip_pimpinan`, `jabatan_pimpinan`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `nama_unit`=VALUES(`nama_unit`), `pimpinan`=VALUES(`pimpinan`), `nip_pimpinan`=VALUES(`nip_pimpinan`), `jabatan_pimpinan`=VALUES(`jabatan_pimpinan`)");
                mysqli_stmt_bind_param($stmt, 'issss', $id, $nama, $pimpinan, $nip, $jabatan);
                mysqli_stmt_execute($stmt);
                $keptUnits[] = $id;
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO `unit_kerja` (`nama_unit`, `pimpinan`, `nip_pimpinan`, `jabatan_pimpinan`) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'ssss', $nama, $pimpinan, $nip, $jabatan);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    if ($newId > 0) {
                        $unitMap[$rawId] = (string)$newId;
                        $keptUnits[] = $newId;
                    }
                }
            }
        }

        // 2. Sync pegawai
        $pegIn = is_array($payload['pegawaiList'] ?? null) ? $payload['pegawaiList'] : [];
        foreach ($pegIn as $p) {
            $rawId = (string)($p['id'] ?? '');
            $nama = (string)($p['namaLengkap'] ?? '');
            $nip = (string)($p['nip'] ?? '');
            $jabatan = (string)($p['jabatan'] ?? '');
            $rawUnit = (string)($p['unitKerjaId'] ?? '');
            $mappedUnit = $unitMap[$rawUnit] ?? $rawUnit;
            $unitId = (ctype_digit($mappedUnit) && (int)$mappedUnit > 0) ? (int)$mappedUnit : null;
            $status = (($p['statusPegawai'] ?? 'PNS') === 'PPPK') ? 'PPPK' : 'PNS';
            $bulan = (int)($p['bulanMulaiKerja'] ?? 1);
            $tahun = (int)($p['tahunMulaiKerja'] ?? 2020);
            $noWa = (string)($p['noWa'] ?? '');
            $sctn2 = (int)($p['sctn2'] ?? 0);
            $sctn1 = (int)($p['sctn1'] ?? 0);
            $sctn = (int)($p['sctn'] ?? 12);

            if ($nama === '' || $nip === '') continue;

            if (ctype_digit($rawId) && (int)$rawId > 0) {
                $id = (int)$rawId;
                $stmt = mysqli_prepare($conn, "INSERT INTO `pegawai` (`id`, `nama_lengkap`, `nip`, `jabatan`, `unit_kerja_id`, `status_pegawai`, `bulan_mulai_kerja`, `tahun_mulai_kerja`, `no_wa`, `sctn2`, `sctn1`, `sctn`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`), `nip`=VALUES(`nip`), `jabatan`=VALUES(`jabatan`), `unit_kerja_id`=VALUES(`unit_kerja_id`), `status_pegawai`=VALUES(`status_pegawai`), `bulan_mulai_kerja`=VALUES(`bulan_mulai_kerja`), `tahun_mulai_kerja`=VALUES(`tahun_mulai_kerja`), `no_wa`=VALUES(`no_wa`), `sctn2`=VALUES(`sctn2`), `sctn1`=VALUES(`sctn1`), `sctn`=VALUES(`sctn`)");
                mysqli_stmt_bind_param($stmt, 'isssisiisiii', $id, $nama, $nip, $jabatan, $unitId, $status, $bulan, $tahun, $noWa, $sctn2, $sctn1, $sctn);
                if (mysqli_stmt_execute($stmt)) {
                    $keptPegawai[] = $id;
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO `pegawai` (`nama_lengkap`, `nip`, `jabatan`, `unit_kerja_id`, `status_pegawai`, `bulan_mulai_kerja`, `tahun_mulai_kerja`, `no_wa`, `sctn2`, `sctn1`, `sctn`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`), `jabatan`=VALUES(`jabatan`), `unit_kerja_id`=VALUES(`unit_kerja_id`), `status_pegawai`=VALUES(`status_pegawai`), `bulan_mulai_kerja`=VALUES(`bulan_mulai_kerja`), `tahun_mulai_kerja`=VALUES(`tahun_mulai_kerja`), `no_wa`=VALUES(`no_wa`), `sctn2`=VALUES(`sctn2`), `sctn1`=VALUES(`sctn1`), `sctn`=VALUES(`sctn`)");
                mysqli_stmt_bind_param($stmt, 'sssisisiiii', $nama, $nip, $jabatan, $unitId, $status, $bulan, $tahun, $noWa, $sctn2, $sctn1, $sctn);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    if ($newId === 0) {
                        $lookup = mysqli_prepare($conn, "SELECT id FROM `pegawai` WHERE nip=? LIMIT 1");
                        mysqli_stmt_bind_param($lookup, 's', $nip);
                        mysqli_stmt_execute($lookup);
                        $lr = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
                        $newId = (int)($lr['id'] ?? 0);
                    }
                    if ($newId > 0) {
                        $pegMap[$rawId] = (string)$newId;
                        $keptPegawai[] = $newId;
                    }
                }
            }
        }

        // 3. Sync users
        $usersIn = is_array($payload['userList'] ?? null) ? $payload['userList'] : [];
        foreach ($usersIn as $usr) {
            $rawId = (string)($usr['id'] ?? '');
            $nama = (string)($usr['namaLengkap'] ?? '');
            $username = (string)($usr['username'] ?? '');
            $nip = (string)($usr['nip'] ?? '');
            $password = (string)($usr['password'] ?? 'password123');
            $jabatan = (string)($usr['jabatan'] ?? '');
            $status = (($usr['statusPegawai'] ?? 'PNS') === 'PPPK') ? 'PPPK' : 'PNS';
            $rawUnit = (string)($usr['unitKerjaId'] ?? '');
            $mappedUnit = $unitMap[$rawUnit] ?? $rawUnit;
            $unitId = (ctype_digit($mappedUnit) && (int)$mappedUnit > 0) ? (int)$mappedUnit : null;
            $noWa = (string)($usr['noWa'] ?? '');
            $role = in_array($usr['role'] ?? 'operator', ['superadmin', 'admin', 'operator'], true) ? $usr['role'] : 'operator';

            if ($nama === '' || $username === '' || $nip === '') continue;

            if (ctype_digit($rawId) && (int)$rawId > 0) {
                $id = (int)$rawId;
                $stmt = mysqli_prepare($conn, "INSERT INTO `users` (`id`, `nama_lengkap`, `username`, `nip`, `password`, `jabatan`, `status_pegawai`, `unit_kerja_id`, `no_wa`, `role`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`), `username`=VALUES(`username`), `nip`=VALUES(`nip`), `password`=VALUES(`password`), `jabatan`=VALUES(`jabatan`), `status_pegawai`=VALUES(`status_pegawai`), `unit_kerja_id`=VALUES(`unit_kerja_id`), `no_wa`=VALUES(`no_wa`), `role`=VALUES(`role`)");
                mysqli_stmt_bind_param($stmt, 'issssssiss', $id, $nama, $username, $nip, $password, $jabatan, $status, $unitId, $noWa, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $keptUsers[] = $id;
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO `users` (`nama_lengkap`, `username`, `nip`, `password`, `jabatan`, `status_pegawai`, `unit_kerja_id`, `no_wa`, `role`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `nama_lengkap`=VALUES(`nama_lengkap`), `password`=VALUES(`password`), `jabatan`=VALUES(`jabatan`), `status_pegawai`=VALUES(`status_pegawai`), `unit_kerja_id`=VALUES(`unit_kerja_id`), `no_wa`=VALUES(`no_wa`), `role`=VALUES(`role`)");
                mysqli_stmt_bind_param($stmt, 'ssssssiss', $nama, $username, $nip, $password, $jabatan, $status, $unitId, $noWa, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    if ($newId === 0) {
                        $lookup = mysqli_prepare($conn, "SELECT id FROM `users` WHERE username=? OR nip=? LIMIT 1");
                        mysqli_stmt_bind_param($lookup, 'ss', $username, $nip);
                        mysqli_stmt_execute($lookup);
                        $lr = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
                        $newId = (int)($lr['id'] ?? 0);
                    }
                    if ($newId > 0) {
                        $userMap[$rawId] = (string)$newId;
                        $keptUsers[] = $newId;
                    }
                }
            }
        }

        // 4. Sync hari_libur
        $holIn = is_array($payload['holidays'] ?? null) ? $payload['holidays'] : [];
        foreach ($holIn as $h) {
            $rawId = (string)($h['id'] ?? '');
            $tanggal = (string)($h['tanggal'] ?? '');
            $ket = (string)($h['keterangan'] ?? '');
            if ($tanggal === '' || $ket === '') continue;

            if (ctype_digit($rawId) && (int)$rawId > 0) {
                $id = (int)$rawId;
                $stmt = mysqli_prepare($conn, "INSERT INTO `hari_libur` (`id`, `tanggal`, `keterangan`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `tanggal`=VALUES(`tanggal`), `keterangan`=VALUES(`keterangan`)");
                mysqli_stmt_bind_param($stmt, 'iss', $id, $tanggal, $ket);
                if (mysqli_stmt_execute($stmt)) {
                    $keptHolidays[] = $id;
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO `hari_libur` (`tanggal`, `keterangan`) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt, 'ss', $tanggal, $ket);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    if ($newId > 0) {
                        $holidayMap[$rawId] = (string)$newId;
                        $keptHolidays[] = $newId;
                    }
                }
            }
        }

        // 5. Sync cuti_requests
        $cutiIn = is_array($payload['cutiList'] ?? null) ? $payload['cutiList'] : [];
        $validStatuses = ['Pending', 'Disetujui', 'Perubahan', 'Ditolak', 'Ditangguhkan'];
        foreach ($cutiIn as $c) {
            $rawId = (string)($c['id'] ?? '');
            $rawPeg = (string)($c['pegawaiId'] ?? '');
            $mappedPeg = $pegMap[$rawPeg] ?? $rawPeg;
            if (!ctype_digit($mappedPeg) || (int)$mappedPeg <= 0) continue;
            $pegId = (int)$mappedPeg;

            $jenis = (string)($c['jenisCuti'] ?? 'Cuti Tahunan');
            $tglMulai = (string)($c['tanggalMulai'] ?? date('Y-m-d'));
            $tglSelesai = (string)($c['tanggalSelesai'] ?? $tglMulai);
            $lama = (int)($c['lamaHariCuti'] ?? 1);
            $alasan = (string)($c['alasan'] ?? '');
            $alamat = (string)($c['alamatSelamaCuti'] ?? '');
            $dokPdf = (string)($c['dokumenPdf'] ?? 'Surat_Permohonan_Cuti.pdf');
            $dokPdfData = isset($c['dokumenPdfData']) ? (string)$c['dokumenPdfData'] : '';
            $stAtasan = in_array($c['statusAtasan'] ?? 'Pending', $validStatuses, true) ? $c['statusAtasan'] : 'Pending';
            $stKepala = in_array($c['statusKepala'] ?? 'Pending', $validStatuses, true) ? $c['statusKepala'] : 'Pending';
            $stAkhir = (string)($c['statusAkhir'] ?? 'Menunggu Verifikasi Atasan');
            $catAtasan = (string)($c['catatanAtasan'] ?? '');
            $catKepala = (string)($c['catatanKepala'] ?? '');
            $noSurat = (string)($c['nomorSurat'] ?? '');
            $tglPengajuan = (string)($c['tanggalPengajuan'] ?? date('Y-m-d'));

            if (ctype_digit($rawId) && (int)$rawId > 0) {
                $id = (int)$rawId;
                $stmt = mysqli_prepare($conn, "INSERT INTO `cuti_requests` (`id`, `pegawai_id`, `jenis_cuti`, `tanggal_mulai`, `tanggal_selesai`, `lama_hari_cuti`, `alasan`, `alamat_selama_cuti`, `dokumen_pdf`, `dokumen_pdf_data`, `status_atasan`, `status_kepala`, `status_akhir`, `catatan_atasan`, `catatan_kepala`, `nomor_surat`, `tanggal_pengajuan`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?) ON DUPLICATE KEY UPDATE `pegawai_id`=VALUES(`pegawai_id`), `jenis_cuti`=VALUES(`jenis_cuti`), `tanggal_mulai`=VALUES(`tanggal_mulai`), `tanggal_selesai`=VALUES(`tanggal_selesai`), `lama_hari_cuti`=VALUES(`lama_hari_cuti`), `alasan`=VALUES(`alasan`), `alamat_selama_cuti`=VALUES(`alamat_selama_cuti`), `dokumen_pdf`=VALUES(`dokumen_pdf`), `dokumen_pdf_data`=VALUES(`dokumen_pdf_data`), `status_atasan`=VALUES(`status_atasan`), `status_kepala`=VALUES(`status_kepala`), `status_akhir`=VALUES(`status_akhir`), `catatan_atasan`=VALUES(`catatan_atasan`), `catatan_kepala`=VALUES(`catatan_kepala`), `nomor_surat`=VALUES(`nomor_surat`), `tanggal_pengajuan`=VALUES(`tanggal_pengajuan`)");
                mysqli_stmt_bind_param($stmt, 'iisssisssssssssss', $id, $pegId, $jenis, $tglMulai, $tglSelesai, $lama, $alasan, $alamat, $dokPdf, $dokPdfData, $stAtasan, $stKepala, $stAkhir, $catAtasan, $catKepala, $noSurat, $tglPengajuan);
                if (mysqli_stmt_execute($stmt)) {
                    $keptCuti[] = $id;
                }
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO `cuti_requests` (`pegawai_id`, `jenis_cuti`, `tanggal_mulai`, `tanggal_selesai`, `lama_hari_cuti`, `alasan`, `alamat_selama_cuti`, `dokumen_pdf`, `dokumen_pdf_data`, `status_atasan`, `status_kepala`, `status_akhir`, `catatan_atasan`, `catatan_kepala`, `nomor_surat`, `tanggal_pengajuan`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");
                mysqli_stmt_bind_param($stmt, 'isssisssssssssss', $pegId, $jenis, $tglMulai, $tglSelesai, $lama, $alasan, $alamat, $dokPdf, $dokPdfData, $stAtasan, $stKepala, $stAkhir, $catAtasan, $catKepala, $noSurat, $tglPengajuan);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    if ($newId > 0) {
                        $cutiMap[$rawId] = (string)$newId;
                        $keptCuti[] = $newId;
                    }
                }
            }
        }

        // 6. Hapus data yang sudah dihapus dari aplikasi (urutan FK aman)
        $deleteUnused = function(string $table, array $keptIds) use ($conn) {
            $cleanIds = array_values(array_filter(array_map('intval', $keptIds), fn($v) => $v > 0));
            if (count($cleanIds) > 0) {
                $inList = implode(',', $cleanIds);
                mysqli_query($conn, "DELETE FROM `$table` WHERE `id` NOT IN ($inList)");
            } else {
                mysqli_query($conn, "DELETE FROM `$table`");
            }
        };

        $deleteUnused('cuti_requests', $keptCuti);
        $deleteUnused('pegawai', $keptPegawai);
        $deleteUnused('users', $keptUsers);
        $deleteUnused('unit_kerja', $keptUnits);
        $deleteUnused('hari_libur', $keptHolidays);

        // 7. Sync wa_template
        if (isset($payload['waTemplate'])) {
            $waTpl = (string)$payload['waTemplate'];
            $stmt = mysqli_prepare($conn, "INSERT INTO `app_settings` (`setting_key`, `setting_value`) VALUES ('wa_template', ?) ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`)");
            mysqli_stmt_bind_param($stmt, 's', $waTpl);
            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);

        echo json_encode([
            'ok' => true,
            'driver' => 'mysql',
            'idMap' => [
                'units' => (object)$unitMap,
                'pegawai' => (object)$pegMap,
                'users' => (object)$userMap,
                'holidays' => (object)$holidayMap,
                'cuti' => (object)$cutiMap,
            ],
        ]);
        exit;
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Aksi tidak dikenal.']);
