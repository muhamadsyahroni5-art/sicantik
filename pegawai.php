<?php
require_once 'koneksi.php';
$currentUser = requireRole(['superadmin']);
$activePage = 'pegawai';

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_pegawai_mysql.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'nama_lengkap',
        'nip',
        'jabatan',
        'unit_kerja_id',
        'status_pegawai',
        'bulan_mulai_kerja',
        'tahun_mulai_kerja',
        'no_wa',
        'sctn2',
        'sctn1',
        'sctn'
    ], ';');
    fputcsv($out, [
        'Hj. Ratu Masnah, M.Pd.I.',
        '198104122006042001',
        'Guru Ahli Madya',
        3,
        'PNS',
        4,
        2006,
        '081299998888',
        0,
        6,
        12
    ], ';');
    fputcsv($out, [
        'Ahmad Fauzi, S.H.',
        '199208142023211003',
        'Analis Hukum',
        2,
        'PPPK',
        3,
        2023,
        '085611112222',
        0,
        0,
        12
    ], ';');
    fclose($out);
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $nip = preg_replace('/\s+/', '', trim($_POST['nip'] ?? ''));
        $jabatan = trim($_POST['jabatan'] ?? '');
        $unitIdRaw = (int)($_POST['unit_kerja_id'] ?? 0);
        $unitId = $unitIdRaw > 0 ? $unitIdRaw : null;
        $status = ($_POST['status_pegawai'] ?? 'PNS') === 'PPPK' ? 'PPPK' : 'PNS';
        $bulan = max(1, min(12, (int)($_POST['bulan_mulai_kerja'] ?? 1)));
        $tahun = (int)($_POST['tahun_mulai_kerja'] ?? 2020);
        $noWa = trim($_POST['no_wa'] ?? '');
        $sctn2 = (int)($_POST['sctn2'] ?? 0);
        $sctn1 = (int)($_POST['sctn1'] ?? 0);
        $sctn = (int)($_POST['sctn'] ?? 12);

        if (!$nama || !$nip) {
            $error = 'Nama lengkap dan NIP wajib diisi!';
        } elseif ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE pegawai SET nama_lengkap=?, nip=?, jabatan=?, unit_kerja_id=?, status_pegawai=?, bulan_mulai_kerja=?, tahun_mulai_kerja=?, no_wa=?, sctn2=?, sctn1=?, sctn=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'sssisisiiiii', $nama, $nip, $jabatan, $unitId, $status, $bulan, $tahun, $noWa, $sctn2, $sctn1, $sctn, $id);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Data pegawai berhasil diperbarui.';
            } else {
                $error = 'Gagal memperbarui data pegawai (pastikan NIP tidak duplikat).';
            }
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO pegawai (nama_lengkap, nip, jabatan, unit_kerja_id, status_pegawai, bulan_mulai_kerja, tahun_mulai_kerja, no_wa, sctn2, sctn1, sctn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssisisiiii', $nama, $nip, $jabatan, $unitId, $status, $bulan, $tahun, $noWa, $sctn2, $sctn1, $sctn);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Pegawai baru berhasil ditambahkan.';
            } else {
                $error = 'Gagal menambah pegawai (pastikan NIP belum terdaftar).';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($conn, "DELETE FROM pegawai WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $notice = 'Data pegawai berhasil dihapus.';
    } elseif ($action === 'reset_pns' || $action === 'reset_pppk') {
        $targetStatus = $action === 'reset_pns' ? 'PNS' : 'PPPK';
        $stmt = mysqli_prepare($conn, "UPDATE pegawai SET sctn2 = sctn1, sctn1 = IF(sctn > 6, 6, sctn), sctn = 12 WHERE status_pegawai = ?");
        mysqli_stmt_bind_param($stmt, 's', $targetStatus);
        mysqli_stmt_execute($stmt);
        $notice = "Berhasil mereset kuota cuti seluruh pegawai berstatus $targetStatus!";
    } elseif ($action === 'import_csv') {
        $rawRows = [];
        $hasUpload = isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK;
        $pasteText = trim((string)($_POST['paste_data'] ?? ''));

        if (!$hasUpload && $pasteText === '') {
            $error = 'Silakan pilih file CSV/XLSX atau tempel data dari Excel terlebih dahulu.';
        } else {
            if ($hasUpload) {
                $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
                if ($ext === 'xlsx') {
                    if (!class_exists('ZipArchive')) {
                        $error = 'Ekstensi PHP ZipArchive tidak aktif. Silakan simpan Excel sebagai .csv (CSV UTF-8) lalu unggah kembali.';
                    } else {
                        $zip = new ZipArchive();
                        if ($zip->open($_FILES['csv_file']['tmp_name']) === true) {
                            $sharedStrings = [];
                            if (($ssXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                                $ss = @simplexml_load_string($ssXml);
                                if ($ss) {
                                    foreach ($ss->si as $si) {
                                        $txt = '';
                                        if (isset($si->t)) {
                                            $txt = (string)$si->t;
                                        } elseif (isset($si->r)) {
                                            foreach ($si->r as $r) $txt .= (string)$r->t;
                                        }
                                        $sharedStrings[] = $txt;
                                    }
                                }
                            }
                            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                            if ($sheetXml !== false) {
                                $sx = @simplexml_load_string($sheetXml);
                                if ($sx && isset($sx->sheetData->row)) {
                                    foreach ($sx->sheetData->row as $rNode) {
                                        $rowArr = [];
                                        $fallbackIdx = 0;
                                        foreach ($rNode->c as $cNode) {
                                            $ref = (string)$cNode['r'];
                                            $letters = preg_replace('/[^A-Za-z]/', '', $ref);
                                            $colIdx = $fallbackIdx;
                                            if ($letters !== '') {
                                                $colIdx = 0;
                                                $letters = strtoupper($letters);
                                                for ($li = 0; $li < strlen($letters); $li++) {
                                                    $colIdx = $colIdx * 26 + (ord($letters[$li]) - 64);
                                                }
                                                $colIdx--;
                                            }
                                            while (count($rowArr) <= $colIdx) $rowArr[] = '';
                                            $tAttr = (string)$cNode['t'];
                                            if ($tAttr === 's') {
                                                $siIdx = (int)$cNode->v;
                                                $rowArr[$colIdx] = trim((string)($sharedStrings[$siIdx] ?? ''));
                                            } elseif ($tAttr === 'inlineStr') {
                                                $rowArr[$colIdx] = trim((string)($cNode->is->t ?? ''));
                                            } else {
                                                $rowArr[$colIdx] = trim((string)($cNode->v ?? ''));
                                            }
                                            $fallbackIdx++;
                                        }
                                        if (implode('', $rowArr) !== '') $rawRows[] = $rowArr;
                                    }
                                }
                            }
                            $zip->close();
                        } else {
                            $error = 'Gagal membuka file .xlsx.';
                        }
                    }
                } elseif ($ext === 'csv' || $ext === 'txt') {
                    $rawBytes = file_get_contents($_FILES['csv_file']['tmp_name']);
                    if ($rawBytes !== false) {
                        if (substr($rawBytes, 0, 2) === "\xFF\xFE") {
                            $pasteText = function_exists('mb_convert_encoding')
                                ? mb_convert_encoding(substr($rawBytes, 2), 'UTF-8', 'UTF-16LE')
                                : str_replace("\0", '', substr($rawBytes, 2));
                        } elseif (substr($rawBytes, 0, 2) === "\xFE\xFF") {
                            $pasteText = function_exists('mb_convert_encoding')
                                ? mb_convert_encoding(substr($rawBytes, 2), 'UTF-8', 'UTF-16BE')
                                : str_replace("\0", '', substr($rawBytes, 2));
                        } else {
                            $pasteText = preg_replace('/^\xEF\xBB\xBF/', '', $rawBytes);
                        }
                    }
                } else {
                    $error = 'Format file harus .csv atau .xlsx.';
                }
            }

            if ($error === '' && count($rawRows) === 0 && $pasteText !== '') {
                $normalizedText = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $pasteText);
                $lines = array_values(array_filter(explode("\n", $normalizedText), fn($l) => trim($l) !== ''));
                $explicitDelim = null;
                if (count($lines) > 0 && preg_match('/^"?sep\s*=\s*(.|\\\\t)"?$/i', trim($lines[0]), $m)) {
                    $explicitDelim = strtolower($m[1]) === '\\t' ? "\t" : $m[1];
                    array_shift($lines);
                }
                if (count($lines) > 0) {
                    $delimiter = $explicitDelim;
                    if ($delimiter === null) {
                        $bestDelim = ',';
                        $bestScore = -1;
                        foreach ([';', ',', "\t", '|'] as $cand) {
                            $score = 0;
                            foreach (array_slice($lines, 0, 10) as $sl) {
                                $score += substr_count($sl, $cand);
                            }
                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestDelim = $cand;
                            }
                        }
                        $delimiter = $bestScore > 0 ? $bestDelim : ',';
                    }
                    foreach ($lines as $ln) {
                        $cols = str_getcsv($ln, $delimiter);
                        if (count($cols) > 0 && trim(implode('', $cols)) !== '') {
                            $rawRows[] = $cols;
                        }
                    }
                }
            }

            if ($error === '') {
                $aliases = [
                    'id' => 'id', 'no' => 'id', 'nomor' => 'id',
                    'nama_lengkap' => 'nama_lengkap', 'nama' => 'nama_lengkap', 'nama_pegawai' => 'nama_lengkap', 'nama_asn' => 'nama_lengkap',
                    'nip' => 'nip', 'nip_pegawai' => 'nip', 'nomor_induk_pegawai' => 'nip',
                    'jabatan' => 'jabatan', 'nama_jabatan' => 'jabatan',
                    'unit_kerja_id' => 'unit_kerja_id', 'id_unit_kerja' => 'unit_kerja_id', 'id_unit' => 'unit_kerja_id', 'unit_kerja' => 'unit_kerja_id', 'unit' => 'unit_kerja_id',
                    'status_pegawai' => 'status_pegawai', 'status' => 'status_pegawai', 'status_asn' => 'status_pegawai',
                    'bulan_mulai_kerja' => 'bulan_mulai_kerja', 'bulan_mulai' => 'bulan_mulai_kerja', 'bulan_tmt' => 'bulan_mulai_kerja', 'bulan' => 'bulan_mulai_kerja',
                    'tahun_mulai_kerja' => 'tahun_mulai_kerja', 'tahun_mulai' => 'tahun_mulai_kerja', 'tahun_tmt' => 'tahun_mulai_kerja', 'tahun' => 'tahun_mulai_kerja',
                    'no_wa' => 'no_wa', 'wa' => 'no_wa', 'no_hp' => 'no_wa', 'hp' => 'no_wa', 'telepon' => 'no_wa',
                    'sctn2' => 'sctn2', 'sctn_2' => 'sctn2',
                    'sctn1' => 'sctn1', 'sctn_1' => 'sctn1',
                    'sctn' => 'sctn', 'sisa_cuti' => 'sctn'
                ];

                $headerRowIdx = -1;
                $headerMap = [];
                for ($hi = 0; $hi < min(5, count($rawRows)); $hi++) {
                    $tempMap = [];
                    $matches = 0;
                    foreach ($rawRows[$hi] as $ci => $cellVal) {
                        $tok = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string)$cellVal)), '_');
                        if (isset($aliases[$tok]) && !isset($tempMap[$aliases[$tok]])) {
                            $tempMap[$aliases[$tok]] = $ci;
                            $matches++;
                        }
                    }
                    if ((isset($tempMap['nama_lengkap']) && isset($tempMap['nip'])) || $matches >= 3) {
                        $headerRowIdx = $hi;
                        $headerMap = $tempMap;
                        break;
                    }
                }

                $dataRows = $headerRowIdx !== -1 ? array_slice($rawRows, $headerRowIdx + 1) : $rawRows;
                $hasLeadingHeaderId = ($headerRowIdx !== -1 && ($headerMap['id'] ?? -1) === 0);

                $validUnits = [];
                $unitByName = [];
                $uRes = mysqli_query($conn, "SELECT id, nama_unit FROM unit_kerja ORDER BY id ASC");
                while ($ur = mysqli_fetch_assoc($uRes)) {
                    $validUnits[(int)$ur['id']] = true;
                    $unitByName[(int)$ur['id']] = strtolower($ur['nama_unit']);
                }

                $sql = "INSERT INTO pegawai (nama_lengkap, nip, jabatan, unit_kerja_id, status_pegawai, bulan_mulai_kerja, tahun_mulai_kerja, no_wa, sctn2, sctn1, sctn)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        nama_lengkap = VALUES(nama_lengkap),
                        jabatan = VALUES(jabatan),
                        unit_kerja_id = VALUES(unit_kerja_id),
                        status_pegawai = VALUES(status_pegawai),
                        bulan_mulai_kerja = VALUES(bulan_mulai_kerja),
                        tahun_mulai_kerja = VALUES(tahun_mulai_kerja),
                        no_wa = VALUES(no_wa),
                        sctn2 = VALUES(sctn2),
                        sctn1 = VALUES(sctn1),
                        sctn = VALUES(sctn)";
                $stmt = mysqli_prepare($conn, $sql);

                $inserted = 0;
                $updated = 0;
                foreach ($dataRows as $row) {
                    $c0 = trim((string)($row[0] ?? ''));
                    $c1 = trim((string)($row[1] ?? ''));
                    $autoLeadingId = ($headerRowIdx === -1 && count($row) >= 3 && preg_match('/^\d{1,6}$/', $c0) && preg_match('/[a-zA-Z]/', $c1));
                    $shift = ($hasLeadingHeaderId || $autoLeadingId) ? 1 : 0;

                    $getCol = function(string $name, int $pos) use ($row, $headerRowIdx, $headerMap, $shift): string {
                        if ($headerRowIdx !== -1 && isset($headerMap[$name])) {
                            return (string)($row[$headerMap[$name]] ?? '');
                        }
                        return (string)($row[$pos + $shift] ?? '');
                    };

                    $nama = trim($getCol('nama_lengkap', 0));
                    $nip = preg_replace('/[\s.]+/', '', ltrim(trim($getCol('nip', 1)), "'"));
                    if ($nama === '' || $nip === '') continue;

                    $jabatan = trim($getCol('jabatan', 2)) ?: 'Pelaksana';
                    $rawUnitStr = trim($getCol('unit_kerja_id', 3));
                    $unitId = null;
                    if ($rawUnitStr !== '' && ctype_digit($rawUnitStr) && isset($validUnits[(int)$rawUnitStr])) {
                        $unitId = (int)$rawUnitStr;
                    } elseif ($rawUnitStr !== '') {
                        $needle = strtolower($rawUnitStr);
                        foreach ($unitByName as $uid => $uname) {
                            if (strpos($uname, $needle) !== false) {
                                $unitId = $uid;
                                break;
                            }
                        }
                    }

                    $rawStatus = strtoupper(trim($getCol('status_pegawai', 4)));
                    $status = (strpos($rawStatus, 'PPPK') !== false || strpos($rawStatus, 'P3K') !== false) ? 'PPPK' : 'PNS';
                    $bulan = max(1, min(12, (int)(trim($getCol('bulan_mulai_kerja', 5)) ?: 1)));
                    $tahunRaw = trim($getCol('tahun_mulai_kerja', 6));
                    $tahun = $tahunRaw !== '' ? (int)$tahunRaw : 2020;
                    $noWa = ltrim(trim($getCol('no_wa', 7)), "'");
                    $sctn2 = (int)(trim($getCol('sctn2', 8)) ?: 0);
                    $sctn1 = (int)(trim($getCol('sctn1', 9)) ?: 0);
                    $sctnRaw = trim($getCol('sctn', 10));
                    $sctn = $sctnRaw !== '' ? (int)$sctnRaw : 12;

                    mysqli_stmt_bind_param($stmt, 'sssisisiiii', $nama, $nip, $jabatan, $unitId, $status, $bulan, $tahun, $noWa, $sctn2, $sctn1, $sctn);
                    if (mysqli_stmt_execute($stmt)) {
                        $aff = mysqli_affected_rows($conn);
                        if ($aff === 1) $inserted++;
                        else $updated++;
                    }
                }

                if ($inserted === 0 && $updated === 0) {
                    $error = 'Tidak ada baris data pegawai valid yang ditemukan. Pastikan minimal Nama Lengkap dan NIP terisi.';
                } else {
                    $notice = "Import selesai ke tabel MySQL pegawai: $inserted pegawai baru ditambahkan, $updated data pegawai diperbarui.";
                }
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterUnit = $_GET['unit'] ?? 'ALL';
$ttdMode = ($_GET['ttd'] ?? 'kepala') === 'kasubbag' ? 'kasubbag' : 'kepala';
$allowedPerPage = [50, 100, 200];
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, $allowedPerPage, true)) $perPage = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editData = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pegawai WHERE id=$editId"));
}

$historyPegawai = null;
$historyRows = [];
if (isset($_GET['history_id'])) {
    $hid = (int)$_GET['history_id'];
    $historyPegawai = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.*, u.nama_unit FROM pegawai p LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id WHERE p.id=$hid"));
    if ($historyPegawai) {
        $resH = mysqli_query($conn, "SELECT * FROM cuti_requests WHERE pegawai_id=$hid ORDER BY tanggal_pengajuan DESC, id DESC");
        while ($hr = mysqli_fetch_assoc($resH)) $historyRows[] = $hr;
    }
}

$unitsRes = mysqli_query($conn, "SELECT * FROM unit_kerja ORDER BY id ASC");
$unitOptions = [];
while ($u = mysqli_fetch_assoc($unitsRes)) $unitOptions[] = $u;

$kepalaKantor = getKepalaKantor($conn);
$kasubbagTu = [
    'pimpinan' => 'Ajrum Firdaus, S.Ag., M.M.',
    'nip_pimpinan' => '197010051991031004',
    'jabatan_pimpinan' => 'Kepala Sub Bagian Tata Usaha'
];
foreach ($unitOptions as $uo) {
    $nm = strtolower($uo['nama_unit']);
    $jb = strtolower($uo['jabatan_pimpinan']);
    if (strpos($nm, 'tata usaha') !== false || strpos($jb, 'tata usaha') !== false || strpos($jb, 'kasubbag') !== false) {
        $kasubbagTu = $uo;
        break;
    }
}

$selectedUnitLabel = 'Semua Unit Kerja';
if ($filterUnit !== 'ALL') {
    foreach ($unitOptions as $uo) {
        if ((string)$uo['id'] === (string)$filterUnit) {
            $selectedUnitLabel = $uo['nama_unit'];
            break;
        }
    }
}

$where = [];
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(p.nama_lengkap LIKE '%$esc%' OR p.nip LIKE '%$esc%' OR p.jabatan LIKE '%$esc%')";
}
if ($filterUnit !== 'ALL') {
    $where[] = "p.unit_kerja_id = " . (int)$filterUnit;
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$pegawaiRes = mysqli_query($conn, "SELECT p.*, u.nama_unit,
    COALESCE((SELECT SUM(c.lama_hari_cuti) FROM cuti_requests c WHERE c.pegawai_id = p.id AND c.jenis_cuti = 'Cuti Tahunan' AND c.status_akhir NOT IN ('Disetujui', 'Ditolak')), 0) AS diusulkan
    FROM pegawai p
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    $whereSql
    ORDER BY p.nama_lengkap ASC, p.id ASC");

$pegawaiRows = [];
$pnsCount = 0;
$pppkCount = 0;
if ($pegawaiRes) {
    while ($pr = mysqli_fetch_assoc($pegawaiRes)) {
        $pegawaiRows[] = $pr;
        if (($pr['status_pegawai'] ?? 'PNS') === 'PPPK') $pppkCount++;
        else $pnsCount++;
    }
}

$totalPegawai = count($pegawaiRows);
$totalPages = max(1, (int)ceil($totalPegawai / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;
$pegawaiPageRows = array_slice($pegawaiRows, $offset, $perPage);

$logoKemenagSrc = file_exists(__DIR__ . '/assets/logo-kemenag.jpg')
    ? 'assets/logo-kemenag.jpg'
    : (file_exists(__DIR__ . '/logo-kemenag.jpg')
        ? 'logo-kemenag.jpg'
        : (file_exists(__DIR__ . '/assets/logo-kemenag.png') ? 'assets/logo-kemenag.png' : 'logo-kemenag.png'));

$isPegawaiModalOpen = ($editData !== null);

include 'layout_header.php';
?>
<style>
@media print {
    @page { size: A4 portrait; margin: 1.2cm; }
    body { background: #fff !important; }
}
</style>

<div class="space-y-5">
    <!-- Header Halaman & Tombol Aksi -->
    <div class="bg-white px-6 py-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 print:hidden">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-[#006838] shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900 tracking-tight">Manajemen Data Pegawai &amp; Sisa Cuti</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Manajemen data PNS dan PPPK, kuota sisa cuti tahunan (SCTN-2, SCTN-1, SCTN), dan pengurangan Total SCT dengan usulan cuti.</p>
            </div>
        </div>

        <div class="flex flex-col items-stretch sm:items-end gap-2 shrink-0">
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 bg-[#D4AF37] hover:bg-amber-500 text-gray-900 px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak Data Pegawai
                </button>
                <form method="POST" onsubmit="return confirm('Reset kuota cuti seluruh pegawai PPPK?')">
                    <input type="hidden" name="action" value="reset_pppk">
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset Cuti PPPK
                    </button>
                </form>
                <form method="POST" onsubmit="return confirm('Reset kuota cuti seluruh pegawai PNS?')">
                    <input type="hidden" name="action" value="reset_pns">
                    <button type="submit" class="inline-flex items-center gap-1.5 bg-[#006838] hover:bg-emerald-900 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Reset Cuti PNS
                    </button>
                </form>
                <button type="button" onclick="openImportModal()" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Import CSV/XLSX
                </button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="openPegawaiModal()" class="inline-flex items-center gap-1.5 bg-[#006838] hover:bg-emerald-900 text-white px-3.5 py-2 rounded-xl text-xs font-bold shadow-sm transition">
                    <span>+ Tambah Pegawai</span>
                </button>
            </div>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium print:hidden"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium print:hidden"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Baris Pencarian & Filter Unit Kerja -->
    <form method="GET" class="bg-white px-5 py-3.5 rounded-2xl shadow-sm border border-gray-200 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <input type="hidden" name="page" value="1">
        <div class="relative flex-1 min-w-[240px] max-w-md">
            <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama, NIP, atau jabatan..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838]">
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="filter_unit" class="text-[11px] font-bold uppercase tracking-wider text-gray-500">UNIT KERJA:</label>
                <select id="filter_unit" name="unit" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-gray-300 text-xs sm:text-sm text-gray-800 bg-white focus:outline-none focus:border-[#006838]">
                    <option value="ALL">Semua Unit Kerja</option>
                    <?php foreach ($unitOptions as $uo): ?>
                        <option value="<?= (int)$uo['id'] ?>" <?= ((string)$filterUnit === (string)$uo['id']) ? 'selected' : '' ?>><?= e($uo['nama_unit']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <select name="ttd" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-gray-300 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#006838]" title="Pejabat Penandatangan Cetak">
                <option value="kepala" <?= $ttdMode === 'kepala' ? 'selected' : '' ?>>TTD: Kepala Kantor</option>
                <option value="kasubbag" <?= $ttdMode === 'kasubbag' ? 'selected' : '' ?>>TTD: Kasubbag TU</option>
            </select>
            <div class="flex items-center gap-2">
                <label for="per_page" class="text-[11px] font-bold uppercase tracking-wider text-gray-500">BARIS:</label>
                <select id="per_page" name="per_page" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-gray-300 text-xs text-gray-700 bg-white focus:outline-none focus:border-[#006838]">
                    <?php foreach ([50, 100, 200] as $optionPerPage): ?>
                        <option value="<?= $optionPerPage ?>" <?= $perPage === $optionPerPage ? 'selected' : '' ?>><?= $optionPerPage ?> Baris</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php if ($historyPegawai): ?>
        <div class="bg-white p-6 rounded-2xl shadow-md border-2 border-[#006838] space-y-4 print:hidden">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-lg text-[#006838]">History Usulan Cuti: <?= e($historyPegawai['nama_lengkap']) ?></h3>
                    <div class="text-xs text-gray-600">NIP. <?= e($historyPegawai['nip']) ?> | <?= e($historyPegawai['nama_unit']) ?></div>
                </div>
                <a href="pegawai.php" class="px-3 py-1.5 bg-gray-200 rounded-lg text-xs font-bold">Tutup History</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border">
                    <thead class="bg-emerald-50 text-[#006838]">
                        <tr>
                            <th class="p-2 border">No</th>
                            <th class="p-2 border">Tgl Pengajuan</th>
                            <th class="p-2 border">Jenis Cuti</th>
                            <th class="p-2 border">Tanggal Cuti</th>
                            <th class="p-2 border">Lama</th>
                            <th class="p-2 border">Status Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historyRows) === 0): ?>
                            <tr><td colspan="6" class="p-4 text-center text-gray-500">Belum ada riwayat usulan cuti.</td></tr>
                        <?php else: $hi = 1; foreach ($historyRows as $hr): ?>
                            <tr>
                                <td class="p-2 border"><?= $hi++ ?></td>
                                <td class="p-2 border"><?= formatDateIndo($hr['tanggal_pengajuan']) ?></td>
                                <td class="p-2 border font-bold"><?= e($hr['jenis_cuti']) ?></td>
                                <td class="p-2 border"><?= formatDateIndo($hr['tanggal_mulai']) ?> s/d <?= formatDateIndo($hr['tanggal_selesai']) ?></td>
                                <td class="p-2 border font-bold"><?= (int)$hr['lama_hari_cuti'] ?> Hari</td>
                                <td class="p-2 border font-bold"><?= e($hr['status_akhir']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabel Data Pegawai -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden print:hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-emerald-50/75 text-[#006838] uppercase text-[11px] font-bold tracking-wider border-b border-emerald-100">
                    <tr>
                        <th class="px-4 py-3.5 text-center w-12">NO</th>
                        <th class="px-4 py-3.5">NAMA PEGAWAI &amp; NIP</th>
                        <th class="px-4 py-3.5">JABATAN / STATUS</th>
                        <th class="px-4 py-3.5">UNIT KERJA</th>
                        <th class="px-4 py-3.5">MASA KERJA</th>
                        <th class="px-3 py-3.5 text-center leading-tight">SCTN-<br>2</th>
                        <th class="px-3 py-3.5 text-center leading-tight">SCTN-<br>1</th>
                        <th class="px-3 py-3.5 text-center">SCTN</th>
                        <th class="px-4 py-3.5 text-center bg-amber-50/60 leading-tight">TOTAL SCT<br>(SISA)</th>
                        <th class="px-4 py-3.5 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($pegawaiPageRows) === 0): ?>
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500">Tidak ada data pegawai yang ditemukan.</td>
                        </tr>
                    <?php else: $no = $offset + 1; foreach ($pegawaiPageRows as $p):
                        $exp = hitungMasaKerja((int)$p['bulan_mulai_kerja'], (int)$p['tahun_mulai_kerja']);
                        $diusulkan = (int)$p['diusulkan'];
                        $totalSct = max(0, ((int)$p['sctn2'] + (int)$p['sctn1'] + (int)$p['sctn']) - $diusulkan);
                    ?>
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="px-4 py-3.5 text-center font-medium text-gray-700"><?= $no++ ?></td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-gray-900"><?= e($p['nama_lengkap']) ?></div>
                                <div class="text-xs font-mono text-gray-500"><?= e($p['nip']) ?></div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="text-xs sm:text-sm text-gray-800 leading-snug"><?= e($p['jabatan']) ?></div>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold <?= $p['status_pegawai'] === 'PNS' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>"><?= e($p['status_pegawai']) ?></span>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-gray-700"><?= e($p['nama_unit']) ?></td>
                            <td class="px-4 py-3.5 text-xs font-bold text-gray-800 whitespace-nowrap"><?= $exp['years'] ?> Tahun <?= $exp['months'] ?> Bln</td>
                            <td class="px-3 py-3.5 text-center font-mono text-gray-700"><?= (int)$p['sctn2'] ?></td>
                            <td class="px-3 py-3.5 text-center font-mono text-gray-700"><?= (int)$p['sctn1'] ?></td>
                            <td class="px-3 py-3.5 text-center font-mono font-bold text-[#006838]"><?= (int)$p['sctn'] ?></td>
                            <td class="px-4 py-3.5 text-center font-mono font-bold text-amber-700 bg-amber-50/40 whitespace-nowrap">
                                <?= $totalSct ?> Hari
                                <?php if ($diusulkan > 0): ?>
                                    <div class="text-[10px] text-rose-600">(-<?= $diusulkan ?> diusulkan)</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3.5 text-center whitespace-nowrap space-x-1">
                                <a href="pegawai.php?history_id=<?= (int)$p['id'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 hover:bg-amber-100 border border-amber-300 text-amber-800 rounded-lg text-xs font-bold transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    History
                                </a>
                                <button
                                    type="button"
                                    onclick='openPegawaiModal(<?= json_encode([
                                        "id" => (int)$p["id"],
                                        "nama_lengkap" => $p["nama_lengkap"],
                                        "nip" => $p["nip"],
                                        "jabatan" => $p["jabatan"],
                                        "unit_kerja_id" => (int)($p["unit_kerja_id"] ?? 0),
                                        "status_pegawai" => $p["status_pegawai"],
                                        "bulan_mulai_kerja" => (int)$p["bulan_mulai_kerja"],
                                        "tahun_mulai_kerja" => (int)$p["tahun_mulai_kerja"],
                                        "no_wa" => $p["no_wa"],
                                        "sctn2" => (int)$p["sctn2"],
                                        "sctn1" => (int)$p["sctn1"],
                                        "sctn" => (int)$p["sctn"],
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                    title="Edit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 transition"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <form method="POST" class="inline" onsubmit="return confirm('Hapus data pegawai ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <button type="submit" title="Hapus" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
            $paginationParams = [
                'q' => $search,
                'unit' => $filterUnit,
                'ttd' => $ttdMode,
                'per_page' => $perPage,
            ];
            $pageUrl = function (int $page) use ($paginationParams): string {
                $params = array_merge($paginationParams, ['page' => $page]);
                return 'pegawai.php?' . http_build_query($params);
            };
            $rangeStart = $totalPegawai > 0 ? $offset + 1 : 0;
            $rangeEnd = min($offset + $perPage, $totalPegawai);
        ?>
        <div class="px-5 py-3.5 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3 print:hidden">
            <div class="text-xs text-gray-600">
                Menampilkan <b class="text-gray-900"><?= $rangeStart ?>-<?= $rangeEnd ?></b> dari <b class="text-gray-900"><?= $totalPegawai ?></b> pegawai
            </div>
            <div class="flex items-center gap-2">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= e($pageUrl($currentPage - 1)) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Prev page
                    </a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 bg-gray-100 text-xs font-bold text-gray-400 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        Prev page
                    </span>
                <?php endif; ?>
                <span class="px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-bold text-[#006838]">
                    Halaman <?= $currentPage ?> / <?= $totalPages ?>
                </span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= e($pageUrl($currentPage + 1)) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 transition">
                        Next page
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 bg-gray-100 text-xs font-bold text-gray-400 cursor-not-allowed">
                        Next page
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Tambah / Edit Pegawai Baru -->
    <div id="pegawaiModal" class="fixed inset-0 z-50 <?= $isPegawaiModalOpen ? 'flex' : 'hidden' ?> items-center justify-center bg-black/50 backdrop-blur-[1px] p-4 overflow-y-auto print:hidden" onclick="if(event.target === this) closePegawaiModal()">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden my-auto">
            <div class="bg-[#006838] px-6 py-4 flex items-center justify-between border-b-2 border-amber-400">
                <h3 id="pegawaiModalTitle" class="text-white font-bold text-base">
                    <?= $editData ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru' ?>
                </h3>
                <button type="button" onclick="closePegawaiModal()" aria-label="Tutup" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="pegawai.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="pegawai_id" value="<?= (int)($editData['id'] ?? 0) ?>">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="nama_lengkap" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NAMA LENGKAP DENGAN GELAR</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" required value="<?= e($editData['nama_lengkap'] ?? '') ?>" placeholder="Contoh: Drs. H. Ahmad Fauzi, M.Pd." class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838]">
                    </div>
                    <div>
                        <label for="nip" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NIP (NOMOR INDUK PEGAWAI)</label>
                        <input type="text" id="nip" name="nip" required value="<?= e($editData['nip'] ?? '') ?>" placeholder="Contoh: 198001012005011001" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm font-mono text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="jabatan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">JABATAN</label>
                        <input type="text" id="jabatan" name="jabatan" required value="<?= e($editData['jabatan'] ?? '') ?>" placeholder="Contoh: Analis Kepegawaian Ahli Muda" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838]">
                    </div>
                    <div>
                        <label for="status_pegawai" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">STATUS KEPEGAWAIAN</label>
                        <select id="status_pegawai" name="status_pegawai" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 bg-white focus:outline-none focus:border-[#006838]">
                            <option value="PNS" <?= (($editData['status_pegawai'] ?? 'PNS') === 'PNS') ? 'selected' : '' ?>>PNS (Pegawai Negeri Sipil)</option>
                            <option value="PPPK" <?= (($editData['status_pegawai'] ?? '') === 'PPPK') ? 'selected' : '' ?>>PPPK (Pegawai Pemerintah dengan Perjanjian Kerja)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="unit_kerja_id" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">UNIT KERJA</label>
                        <select id="unit_kerja_id" name="unit_kerja_id" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 bg-white focus:outline-none focus:border-[#006838]">
                            <?php foreach ($unitOptions as $uo): ?>
                                <option value="<?= (int)$uo['id'] ?>" <?= ((int)($editData['unit_kerja_id'] ?? ($unitOptions[0]['id'] ?? 0)) === (int)$uo['id']) ? 'selected' : '' ?>><?= e($uo['nama_unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="no_wa" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NO. HP / WHATSAPP</label>
                        <input type="text" id="no_wa" name="no_wa" value="<?= e($editData['no_wa'] ?? '0812') ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838]">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="bulan_mulai_kerja" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">BULAN MULAI KERJA (1-12)</label>
                        <input type="number" id="bulan_mulai_kerja" min="1" max="12" name="bulan_mulai_kerja" value="<?= (int)($editData['bulan_mulai_kerja'] ?? 1) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838]">
                    </div>
                    <div>
                        <label for="tahun_mulai_kerja" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">TAHUN MULAI KERJA</label>
                        <input type="number" id="tahun_mulai_kerja" name="tahun_mulai_kerja" value="<?= (int)($editData['tahun_mulai_kerja'] ?? 2020) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838]">
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="sctn2" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">SCTN-2 (2 THN LALU)</label>
                        <input type="number" id="sctn2" name="sctn2" value="<?= (int)($editData['sctn2'] ?? 0) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838]">
                    </div>
                    <div>
                        <label for="sctn1" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">SCTN-1 (1 THN LALU)</label>
                        <input type="number" id="sctn1" name="sctn1" value="<?= (int)($editData['sctn1'] ?? 6) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838]">
                    </div>
                    <div>
                        <label for="sctn" class="block text-[11px] font-bold uppercase tracking-wider text-[#006838] mb-1.5">SCTN (TAHUN BERJALAN)</label>
                        <input type="number" id="sctn" name="sctn" value="<?= (int)($editData['sctn'] ?? 12) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838]">
                    </div>
                </div>

                <div class="pt-3 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closePegawaiModal()" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold transition">Batal</button>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#006838] hover:bg-emerald-900 text-white text-xs font-bold shadow-sm transition">
                        <span id="pegawaiSubmitLabel"><?= $editData ? 'Simpan Perubahan' : 'Simpan Pegawai' ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Import Data Pegawai (CSV / XLSX / Paste Excel) -->
    <div id="importModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-[1px] p-3 sm:p-5 overflow-y-auto print:hidden" onclick="if(event.target === this) closeImportModal()">
        <form id="importDataForm" method="POST" enctype="multipart/form-data" class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl overflow-hidden my-auto">
            <input type="hidden" name="action" value="import_csv">

            <div class="bg-[#006838] px-5 py-3.5 flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-white">
                    <svg class="w-5 h-5 text-amber-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="font-bold text-sm sm:text-base">Import Data Pegawai (CSV / XLSX / Paste Excel)</h3>
                </div>
                <button type="button" onclick="closeImportModal()" aria-label="Tutup" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-4 sm:p-5 space-y-4 max-h-[78vh] overflow-y-auto">
                <div class="bg-emerald-50/50 border border-emerald-200 rounded-xl p-4 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-1.5 text-sm font-bold text-[#006838]">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Urutan Kolom Sesuai Tabel <code class="bg-emerald-100 text-[#006838] px-1.5 py-0.5 rounded font-mono text-xs">pegawai</code> di MySQL</span>
                            </div>
                            <p class="text-[11px] text-gray-600 mt-1">
                                Wajib minimal <b>nama_lengkap</b> dan <b>nip</b>. Mendukung file <b>.csv</b> (koma, titik koma, tab), <b>.xlsx</b> langsung, tanpa baris header sekalipun, maupun copy-paste dari Excel.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" onclick="copyCsvHeader()" id="btnSalinHeader" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold shadow-2xs transition">
                                <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span>Salin Header</span>
                            </button>
                            <a href="pegawai.php?download_template=1" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-[#006838] hover:bg-emerald-900 text-white text-xs font-bold shadow-2xs transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                <span>Download Template CSV</span>
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100/90 text-gray-600 uppercase text-[10px] font-bold border-b border-gray-200">
                                <tr>
                                    <th class="px-3 py-2 text-center w-16">URUTAN</th>
                                    <th class="px-3 py-2">NAMA KOLOM MYSQL</th>
                                    <th class="px-3 py-2">TIPE &amp; KETENTUAN</th>
                                    <th class="px-3 py-2">CONTOH ISI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700">
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">1</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">nama_lengkap</td><td class="px-3 py-1.5 text-[11px] text-gray-600">VARCHAR(150) — Wajib diisi lengkap dengan gelar</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">Hj. Ratu Masnah, M.Pd.I.</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">2</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">nip</td><td class="px-3 py-1.5 text-[11px] text-gray-600">VARCHAR(30) UNIQUE — Wajib diisi (18 digit)</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">198104122006042001</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">3</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">jabatan</td><td class="px-3 py-1.5 text-[11px] text-gray-600">VARCHAR(120) — Nama jabatan (default: Pelaksana)</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">Guru Ahli Madya</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">4</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">unit_kerja_id</td><td class="px-3 py-1.5 text-[11px] text-gray-600">INT / Nama Unit — Lihat daftar ID unit di bawah</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">3</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">5</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">status_pegawai</td><td class="px-3 py-1.5 text-[11px] text-gray-600">ENUM('PNS', 'PPPK') — Default PNS</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">PNS</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">6</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">bulan_mulai_kerja</td><td class="px-3 py-1.5 text-[11px] text-gray-600">TINYINT (1 s.d 12) — Default 1</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">4</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">7</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">tahun_mulai_kerja</td><td class="px-3 py-1.5 text-[11px] text-gray-600">SMALLINT (4 digit) — Default 2020</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">2006</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">8</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">no_wa</td><td class="px-3 py-1.5 text-[11px] text-gray-600">VARCHAR(25) — Nomor WhatsApp aktif</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">081299998888</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">9</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">sctn2</td><td class="px-3 py-1.5 text-[11px] text-gray-600">INT — Sisa Cuti N-2 (default 0)</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">0</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">10</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">sctn1</td><td class="px-3 py-1.5 text-[11px] text-gray-600">INT — Sisa Cuti N-1 (default 0)</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">6</td></tr>
                                <tr><td class="px-3 py-1.5 text-center font-bold text-[#006838]">11</td><td class="px-3 py-1.5 font-mono font-semibold text-gray-900">sctn</td><td class="px-3 py-1.5 text-[11px] text-gray-600">INT — Sisa Cuti Tahun Berjalan (default 12)</td><td class="px-3 py-1.5 font-mono text-[11px] text-emerald-700">12</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="pt-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 mb-2">REFERENSI <code class="font-mono lowercase">unit_kerja_id</code> PADA DATABASE MYSQL:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($unitOptions as $uo): ?>
                                <span class="inline-flex items-center gap-1.5 bg-white border border-gray-200 rounded-md px-2 py-1 text-[11px] text-gray-700 shadow-2xs">
                                    <span class="font-mono font-bold text-[#006838] bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded text-[10px]"><?= (int)$uo['id'] ?></span>
                                    <span class="truncate max-w-[180px]" title="<?= e($uo['nama_unit']) ?>"><?= e($uo['nama_unit']) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex border-b border-gray-200 text-xs font-semibold">
                        <button type="button" id="tabUploadBtn" onclick="switchImportTab('upload')" class="px-4 py-2 border-b-2 border-[#006838] text-[#006838] font-bold transition">
                            1. Upload File (.CSV / .XLSX)
                        </button>
                        <button type="button" id="tabPasteBtn" onclick="switchImportTab('paste')" class="px-4 py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition">
                            2. Copy-Paste Langsung dari Excel
                        </button>
                    </div>

                    <div id="panelUpload" class="mt-3 border-2 border-dashed border-gray-200 rounded-xl p-6 text-center bg-gray-50/40 space-y-2.5">
                        <div class="mx-auto w-8 h-8 text-gray-400">
                            <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                        </div>
                        <div class="text-xs font-bold text-gray-800">Pilih File CSV atau Excel (.csv / .xlsx)</div>
                        <p class="text-[11px] text-gray-500">Data otomatis terbaca dan tampil pada pratinjau di bawah sebelum disimpan.</p>
                        <div class="pt-1 flex items-center justify-center gap-2">
                            <label class="cursor-pointer inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-semibold text-gray-700 shadow-2xs">
                                <span>Choose File</span>
                                <input type="file" id="importFileInput" name="csv_file" accept=".csv,.xlsx,.txt" class="sr-only" onchange="handleImportFileChange(this)">
                            </label>
                            <span id="importFileNameLabel" class="text-xs text-gray-500">No file chosen</span>
                        </div>
                    </div>

                    <div id="panelPaste" class="mt-3 hidden space-y-2">
                        <label for="pasteDataTextarea" class="block text-xs font-semibold text-gray-700">Tempel baris sel dari Excel / Spreadsheet (urutan kolom 1–11):</label>
                        <textarea
                            id="pasteDataTextarea"
                            name="paste_data"
                            rows="4"
                            oninput="updateImportPreviewFromPaste()"
                            placeholder="Hj. Ratu Masnah, M.Pd.I.&#9;198104122006042001&#9;Guru Ahli Madya&#9;3&#9;PNS&#9;4&#9;2006&#9;081299998888&#9;0&#9;6&#9;12"
                            class="w-full px-3 py-2 rounded-xl border border-gray-300 font-mono text-xs text-gray-800 focus:outline-none focus:border-[#006838]"
                        ></textarea>
                    </div>

                    <div id="importPreviewBox" class="mt-3 hidden bg-white border border-gray-200 rounded-xl p-3 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-[#006838]">Pratinjau Data Terbaca (<span id="importPreviewCount">0</span> baris)</span>
                            <button type="button" onclick="clearImportPreview()" class="text-[11px] text-rose-600 hover:underline font-semibold">Bersihkan</button>
                        </div>
                        <div class="overflow-x-auto max-h-40">
                            <table class="w-full text-[11px] text-left border-collapse">
                                <thead class="bg-gray-100 text-gray-700">
                                    <tr>
                                        <th class="border px-2 py-1">Nama Lengkap</th>
                                        <th class="border px-2 py-1">NIP</th>
                                        <th class="border px-2 py-1">Jabatan</th>
                                        <th class="border px-2 py-1">Unit ID</th>
                                        <th class="border px-2 py-1">Status</th>
                                        <th class="border px-2 py-1">SCTN-2/1/N</th>
                                    </tr>
                                </thead>
                                <tbody id="importPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
                <button type="button" onclick="fillSampleImportRows()" class="px-3 py-1.5 rounded-lg border border-amber-300 bg-amber-50/70 hover:bg-amber-100 text-amber-800 text-xs font-semibold transition">
                    Simulasi Import Contoh (2 Data)
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeImportModal()" class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-100 text-gray-700 text-xs font-bold transition">
                        Batal
                    </button>
                    <button id="btnProsesImportData" type="submit" disabled class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gray-300 text-gray-500 text-xs font-bold cursor-not-allowed transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Proses Import Data</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Format Cetak Resmi A4 Portrait Kop Surat Kemenag Lebak dengan Logo -->
    <div class="hidden print:block text-black bg-white">
        <div class="flex items-center gap-4 border-b-4 border-double border-black pb-3 mb-4" style="font-family: Arial, sans-serif;">
            <img src="<?= e($logoKemenagSrc) ?>" alt="Logo Kementerian Agama" class="w-16 h-16 object-contain shrink-0">
            <div class="flex-1 text-center">
                <div class="font-bold uppercase" style="font-size: 13pt;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
                <div class="font-bold uppercase" style="font-size: 11.5pt;">KANTOR KEMENTERIAN AGAMA KABUPATEN LEBAK</div>
                <div style="font-size: 8.5pt;">Jl. Siliwangi No. 2, Kel. Muara Ciujung Timur Kec. Rangkasbitung, Kabupaten Lebak, Banten 42314</div>
                <div style="font-size: 8.5pt;">Telp: (0252) 201319 &bull; E-mail: sekjen_416610@kemenag.go.id &bull; site: https://kemenaglebak.com</div>
            </div>
        </div>

        <div class="text-center mb-4">
            <h3 class="font-bold text-xs uppercase underline tracking-wide">DAFTAR DATA PEGAWAI DAN SISA CUTI TAHUNAN (SCT)</h3>
            <div class="text-[11px] mt-1">
                Unit Kerja: <b><?= e($selectedUnitLabel) ?></b>
                <?php if ($search !== ''): ?> | Pencarian: <b>&ldquo;<?= e($search) ?>&rdquo;</b><?php endif; ?>
            </div>
        </div>

        <table class="w-full border-collapse border border-black text-[10px] text-left">
            <thead>
                <tr class="bg-gray-100 text-center font-bold">
                    <th class="border border-black px-1.5 py-1.5 w-7">No</th>
                    <th class="border border-black px-2 py-1.5">Nama Pegawai &amp; NIP</th>
                    <th class="border border-black px-2 py-1.5">Jabatan / Status</th>
                    <th class="border border-black px-2 py-1.5">Unit Kerja</th>
                    <th class="border border-black px-1.5 py-1.5 text-center w-16">Masa Kerja</th>
                    <th class="border border-black px-1 py-1.5 text-center w-9">N-2</th>
                    <th class="border border-black px-1 py-1.5 text-center w-9">N-1</th>
                    <th class="border border-black px-1 py-1.5 text-center w-8">N</th>
                    <th class="border border-black px-1.5 py-1.5 text-center w-14">Sisa SCT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($pegawaiRows) === 0): ?>
                    <tr><td colspan="9" class="border border-black text-center py-6">Tidak ada data pegawai pada unit kerja yang dipilih.</td></tr>
                <?php else: $pNo = 1; foreach ($pegawaiRows as $pr):
                    $exp = hitungMasaKerja((int)$pr['bulan_mulai_kerja'], (int)$pr['tahun_mulai_kerja']);
                    $diusulkan = (int)$pr['diusulkan'];
                    $totalSct = max(0, ((int)$pr['sctn2'] + (int)$pr['sctn1'] + (int)$pr['sctn']) - $diusulkan);
                ?>
                    <tr class="break-inside-avoid">
                        <td class="border border-black px-1.5 py-1 text-center align-top"><?= $pNo++ ?></td>
                        <td class="border border-black px-2 py-1 align-top">
                            <div class="font-bold leading-tight"><?= e($pr['nama_lengkap']) ?></div>
                            <div class="font-mono text-[9px]"><?= e($pr['nip']) ?></div>
                        </td>
                        <td class="border border-black px-2 py-1 align-top">
                            <div class="leading-tight"><?= e($pr['jabatan']) ?></div>
                            <div class="text-[9px] font-bold">(<?= e($pr['status_pegawai']) ?>)</div>
                        </td>
                        <td class="border border-black px-2 py-1 align-top leading-tight"><?= e($pr['nama_unit'] ?: '-') ?></td>
                        <td class="border border-black px-1.5 py-1 text-center align-top whitespace-nowrap"><?= $exp['years'] ?> Th <?= $exp['months'] ?> Bl</td>
                        <td class="border border-black px-1 py-1 text-center font-mono align-top"><?= (int)$pr['sctn2'] ?></td>
                        <td class="border border-black px-1 py-1 text-center font-mono align-top"><?= (int)$pr['sctn1'] ?></td>
                        <td class="border border-black px-1 py-1 text-center font-mono font-bold align-top"><?= (int)$pr['sctn'] ?></td>
                        <td class="border border-black px-1.5 py-1 text-center font-mono font-bold align-top"><?= $totalSct ?> Hr</td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="flex justify-between items-start mt-7 text-[11px] break-inside-avoid">
            <div class="space-y-0.5">
                <div>Total Pegawai: <b><?= count($pegawaiRows) ?></b> orang</div>
                <div>PNS: <b><?= $pnsCount ?></b> orang | PPPK: <b><?= $pppkCount ?></b> orang</div>
                <div>Dicetak pada: <?= formatDateIndo(date('Y-m-d')) ?></div>
            </div>
            <div class="text-center w-72">
                <div>Rangkasbitung, <?= formatDateIndo(date('Y-m-d')) ?></div>
                <?php if ($ttdMode === 'kepala'): ?>
                    <div class="font-bold mt-1 leading-snug"><?= e($kepalaKantor['jabatan_pimpinan'] ?: 'Kepala Kantor Kementerian Agama Kabupaten Lebak') ?></div>
                    <div class="h-16"></div>
                    <div class="font-bold underline"><?= e($kepalaKantor['pimpinan']) ?></div>
                    <div class="font-mono text-[10px]">NIP. <?= e($kepalaKantor['nip_pimpinan']) ?></div>
                <?php else: ?>
                    <div class="font-bold mt-1 leading-snug">
                        a.n. Kepala Kantor Kementerian Agama Kab. Lebak<br>
                        <?= e($kasubbagTu['jabatan_pimpinan'] ?: 'Kepala Sub Bagian Tata Usaha') ?>
                    </div>
                    <div class="h-16"></div>
                    <div class="font-bold underline"><?= e($kasubbagTu['pimpinan']) ?></div>
                    <div class="font-mono text-[10px]">NIP. <?= e($kasubbagTu['nip_pimpinan']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    const defaultUnitId = <?= (int)($unitOptions[0]['id'] ?? 0) ?>;
    const csvHeaderString = 'nama_lengkap,nip,jabatan,unit_kerja_id,status_pegawai,bulan_mulai_kerja,tahun_mulai_kerja,no_wa,sctn2,sctn1,sctn';

    function openPegawaiModal(data = null) {
        const modal = document.getElementById('pegawaiModal');
        const title = document.getElementById('pegawaiModalTitle');
        const submitLabel = document.getElementById('pegawaiSubmitLabel');

        if (data) {
            title.textContent = 'Edit Data Pegawai';
            submitLabel.textContent = 'Simpan Perubahan';
            document.getElementById('pegawai_id').value = data.id || 0;
            document.getElementById('nama_lengkap').value = data.nama_lengkap || '';
            document.getElementById('nip').value = data.nip || '';
            document.getElementById('jabatan').value = data.jabatan || '';
            document.getElementById('status_pegawai').value = data.status_pegawai || 'PNS';
            document.getElementById('unit_kerja_id').value = data.unit_kerja_id || defaultUnitId;
            document.getElementById('no_wa').value = data.no_wa || '0812';
            document.getElementById('bulan_mulai_kerja').value = data.bulan_mulai_kerja || 1;
            document.getElementById('tahun_mulai_kerja').value = data.tahun_mulai_kerja || 2020;
            document.getElementById('sctn2').value = data.sctn2 ?? 0;
            document.getElementById('sctn1').value = data.sctn1 ?? 6;
            document.getElementById('sctn').value = data.sctn ?? 12;
        } else {
            title.textContent = 'Tambah Pegawai Baru';
            submitLabel.textContent = 'Simpan Pegawai';
            document.getElementById('pegawai_id').value = 0;
            document.getElementById('nama_lengkap').value = '';
            document.getElementById('nip').value = '';
            document.getElementById('jabatan').value = '';
            document.getElementById('status_pegawai').value = 'PNS';
            document.getElementById('unit_kerja_id').value = defaultUnitId;
            document.getElementById('no_wa').value = '0812';
            document.getElementById('bulan_mulai_kerja').value = 1;
            document.getElementById('tahun_mulai_kerja').value = 2020;
            document.getElementById('sctn2').value = 0;
            document.getElementById('sctn1').value = 6;
            document.getElementById('sctn').value = 12;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => document.getElementById('nama_lengkap').focus(), 50);
    }

    function closePegawaiModal() {
        const modal = document.getElementById('pegawaiModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (window.location.search.includes('edit=')) {
            window.history.replaceState({}, '', 'pegawai.php');
        }
    }

    function openImportModal() {
        const modal = document.getElementById('importModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeImportModal() {
        const modal = document.getElementById('importModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function switchImportTab(tab) {
        const tabUpload = document.getElementById('tabUploadBtn');
        const tabPaste = document.getElementById('tabPasteBtn');
        const panelUpload = document.getElementById('panelUpload');
        const panelPaste = document.getElementById('panelPaste');

        if (tab === 'paste') {
            tabPaste.classList.add('border-[#006838]', 'text-[#006838]', 'font-bold');
            tabPaste.classList.remove('border-transparent', 'text-gray-500');
            tabUpload.classList.remove('border-[#006838]', 'text-[#006838]', 'font-bold');
            tabUpload.classList.add('border-transparent', 'text-gray-500');
            panelPaste.classList.remove('hidden');
            panelUpload.classList.add('hidden');
            updateImportPreviewFromPaste();
        } else {
            tabUpload.classList.add('border-[#006838]', 'text-[#006838]', 'font-bold');
            tabUpload.classList.remove('border-transparent', 'text-gray-500');
            tabPaste.classList.remove('border-[#006838]', 'text-[#006838]', 'font-bold');
            tabPaste.classList.add('border-transparent', 'text-gray-500');
            panelUpload.classList.remove('hidden');
            panelPaste.classList.add('hidden');
            const fileInput = document.getElementById('importFileInput');
            setImportReadyState(fileInput.files && fileInput.files.length > 0);
        }
    }

    function setImportReadyState(ready) {
        const btn = document.getElementById('btnProsesImportData');
        if (!btn) return;
        btn.disabled = !ready;
        if (ready) {
            btn.className = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#006838] hover:bg-emerald-900 text-white text-xs font-bold shadow-sm cursor-pointer transition';
        } else {
            btn.className = 'inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-gray-300 text-gray-500 text-xs font-bold cursor-not-allowed transition';
        }
    }

    function copyCsvHeader() {
        const btnSpan = document.querySelector('#btnSalinHeader span');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(csvHeaderString).catch(() => {});
        }
        if (btnSpan) {
            const oldText = btnSpan.textContent;
            btnSpan.textContent = 'Tersalin!';
            setTimeout(() => { btnSpan.textContent = oldText; }, 1500);
        }
    }

    function parseDelimitedLine(line, delim) {
        const result = [];
        let cur = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') {
                if (inQuotes && line[i + 1] === '"') {
                    cur += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (ch === delim && !inQuotes) {
                result.push(cur.trim());
                cur = '';
            } else {
                cur += ch;
            }
        }
        result.push(cur.trim());
        return result;
    }

    function renderPreviewRows(rows) {
        const box = document.getElementById('importPreviewBox');
        const body = document.getElementById('importPreviewBody');
        const countEl = document.getElementById('importPreviewCount');
        if (!rows.length) {
            box.classList.add('hidden');
            body.innerHTML = '';
            countEl.textContent = '0';
            setImportReadyState(false);
            return;
        }
        countEl.textContent = String(rows.length);
        body.innerHTML = rows.slice(0, 20).map(r => {
            const nama = r[0] || '';
            const nip = r[1] || '';
            const jab = r[2] || 'Pelaksana';
            const unit = r[3] || '';
            const status = r[4] || 'PNS';
            const sctnStr = `${r[8] ?? 0}/${r[9] ?? 0}/${r[10] ?? 12}`;
            return `<tr>
                <td class="border px-2 py-1 font-semibold">${escapeHtml(nama)}</td>
                <td class="border px-2 py-1 font-mono">${escapeHtml(nip)}</td>
                <td class="border px-2 py-1">${escapeHtml(jab)}</td>
                <td class="border px-2 py-1 font-mono">${escapeHtml(unit)}</td>
                <td class="border px-2 py-1">${escapeHtml(status)}</td>
                <td class="border px-2 py-1 font-mono">${escapeHtml(sctnStr)}</td>
            </tr>`;
        }).join('');
        box.classList.remove('hidden');
        setImportReadyState(true);
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
    }

    function updateImportPreviewFromPaste() {
        const raw = document.getElementById('pasteDataTextarea').value || '';
        const lines = raw.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
        const parsed = [];
        for (const ln of lines) {
            const delim = ln.includes('\t') ? '\t' : (ln.includes(';') ? ';' : ',');
            const cols = parseDelimitedLine(ln, delim);
            const c0Lower = (cols[0] || '').toLowerCase();
            if (c0Lower === 'nama_lengkap' || c0Lower === 'nama' || c0Lower === 'id') continue;
            if (cols[0] || cols[1]) parsed.push(cols);
        }
        renderPreviewRows(parsed);
    }

    function handleImportFileChange(input) {
        const label = document.getElementById('importFileNameLabel');
        if (!input.files || !input.files.length) {
            label.textContent = 'No file chosen';
            setImportReadyState(false);
            return;
        }
        const file = input.files[0];
        label.textContent = file.name;
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext === 'csv' || ext === 'txt') {
            const reader = new FileReader();
            reader.onload = function (ev) {
                const text = String(ev.target.result || '').replace(/^\uFEFF/, '');
                const lines = text.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
                const parsed = [];
                for (const ln of lines) {
                    const delim = ln.includes(';') ? ';' : (ln.includes('\t') ? '\t' : ',');
                    const cols = parseDelimitedLine(ln, delim);
                    const c0Lower = (cols[0] || '').toLowerCase();
                    if (c0Lower === 'nama_lengkap' || c0Lower === 'nama' || c0Lower === 'id') continue;
                    if (cols[0] || cols[1]) parsed.push(cols);
                }
                renderPreviewRows(parsed);
            };
            reader.readAsText(file);
        } else {
            document.getElementById('importPreviewBox').classList.add('hidden');
            setImportReadyState(true);
        }
    }

    function fillSampleImportRows() {
        switchImportTab('paste');
        const sample = [
            'H. Fadli Kurnia Dwi Cahyono, S.Kom.\t198107142011011006\tJF Prakom Ahli Muda\t2\tPNS\t1\t2011\t082298023535\t0\t6\t12',
            'Ahmad Fauzi, S.H.\t199208142023211003\tAnalis Hukum\t2\tPPPK\t3\t2023\t085611112222\t0\t0\t12'
        ].join('\n');
        document.getElementById('pasteDataTextarea').value = sample;
        updateImportPreviewFromPaste();
    }

    function clearImportPreview() {
        const fileInput = document.getElementById('importFileInput');
        if (fileInput) fileInput.value = '';
        document.getElementById('importFileNameLabel').textContent = 'No file chosen';
        document.getElementById('pasteDataTextarea').value = '';
        renderPreviewRows([]);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePegawaiModal();
            closeImportModal();
        }
    });
    </script>
</div>
<?php include 'layout_footer.php'; ?>