<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'usulan_cuti';

$role = $currentUser['role'];
$isRestricted = in_array($role, ['operator', 'admin'], true);
$userUnitId = (int)$currentUser['unit_kerja_id'];

$notice = isset($_GET['submitted']) ? 'Usulan cuti berhasil diajukan dan dikirim untuk verifikasi Atasan Langsung!' : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $cutiId = (int)($_POST['cuti_id'] ?? 0);

    if ($action === 'verify_atasan' && in_array($role, ['superadmin', 'admin'], true)) {
        $keputusan = $_POST['status_keputusan'] ?? 'Disetujui';
        $catatan = trim($_POST['catatan'] ?? '');
        $statusAkhir = ($keputusan === 'Disetujui') ? 'Menunggu Verifikasi Kepala' : $keputusan;

        $stmt = mysqli_prepare($conn, "UPDATE cuti_requests SET status_atasan=?, catatan_atasan=?, status_akhir=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssi', $keputusan, $catatan, $statusAkhir, $cutiId);
        mysqli_stmt_execute($stmt);
        $notice = 'Verifikasi Atasan Langsung berhasil disimpan.';
    } elseif ($action === 'edit_request' && $role === 'admin') {
        $jenisCuti = trim($_POST['jenis_cuti'] ?? '');
        $tglMulai = trim($_POST['tanggal_mulai'] ?? '');
        $tglSelesai = trim($_POST['tanggal_selesai'] ?? '');
        $alasan = trim($_POST['alasan'] ?? '');
        $alamat = trim($_POST['alamat_selama_cuti'] ?? '');
        $allowedLeaveTypes = [
            'Cuti Tahunan',
            'Cuti Besar',
            'Cuti Sakit',
            'Cuti Melahirkan',
            'Cuti Karena Alasan Penting',
            'CLTN (Cuti Luar Tanggungan Negara)',
            'Cuti Bersama',
        ];
        $cutiSql = "SELECT c.*, p.sctn2, p.sctn1, p.sctn FROM cuti_requests c
            JOIN pegawai p ON c.pegawai_id = p.id
            WHERE c.id = $cutiId" . ($isRestricted ? " AND p.unit_kerja_id = $userUnitId" : '') . " LIMIT 1";
        $cutiRow = $cutiId > 0 ? mysqli_fetch_assoc(mysqli_query($conn, $cutiSql)) : null;

        if (!$cutiRow) {
            $error = 'Usulan cuti tidak ditemukan atau bukan bagian dari unit kerja Anda.';
        } elseif ($cutiRow['status_akhir'] === 'Disetujui') {
            $error = 'Usulan yang telah disetujui Kepala Kantor tidak dapat diubah.';
        } elseif (!in_array($jenisCuti, $allowedLeaveTypes, true) || $tglMulai === '' || $tglSelesai === '' || $alasan === '' || $alamat === '') {
            $error = 'Lengkapi semua data usulan dengan nilai yang valid.';
        } elseif (strtotime($tglMulai) === false || date('Y-m-d', strtotime($tglMulai)) !== $tglMulai || strtotime($tglSelesai) === false || date('Y-m-d', strtotime($tglSelesai)) !== $tglSelesai || $tglMulai > $tglSelesai) {
            $error = 'Tanggal mulai dan tanggal selesai tidak valid.';
        } else {
            $lamaHari = hitungHariKerja($tglMulai, $tglSelesai, $conn);
            if ($lamaHari <= 0) {
                $error = 'Rentang tanggal cuti harus memiliki minimal satu hari kerja.';
            } elseif ($jenisCuti === 'Cuti Tahunan' && $lamaHari > ((int)$cutiRow['sctn2'] + (int)$cutiRow['sctn1'] + (int)$cutiRow['sctn'])) {
                $sisaCuti = (int)$cutiRow['sctn2'] + (int)$cutiRow['sctn1'] + (int)$cutiRow['sctn'];
                $error = "Sisa cuti tahunan tidak mencukupi ($sisaCuti hari tersedia, usulan $lamaHari hari).";
            } else {
                $dokumenName = (string)($cutiRow['dokumen_pdf'] ?? '');
                $uploadedFile = $_FILES['dokumen_pdf'] ?? null;
                if ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Unggah PDF gagal. Silakan coba kembali.';
                    } elseif (strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                        $error = 'Dokumen yang diunggah harus berformat PDF.';
                    } else {
                        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                        if ($fileInfo->file($uploadedFile['tmp_name']) !== 'application/pdf') {
                            $error = 'File yang dipilih bukan dokumen PDF yang valid.';
                        } else {
                            $newFileName = 'permohonan_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.pdf';
                            if (!move_uploaded_file($uploadedFile['tmp_name'], __DIR__ . '/uploads/' . $newFileName)) {
                                $error = 'File PDF gagal disimpan. Periksa folder uploads.';
                            } else {
                                $dokumenName = $newFileName;
                            }
                        }
                    }
                }

                if ($error === '') {
                    $stmt = mysqli_prepare($conn, "UPDATE cuti_requests
                        SET jenis_cuti=?, tanggal_mulai=?, tanggal_selesai=?, lama_hari_cuti=?,
                            alasan=?, alamat_selama_cuti=?, dokumen_pdf=?,
                            status_atasan='Pending', status_kepala='Pending',
                            status_akhir='Menunggu Verifikasi Atasan Langsung',
                            catatan_atasan=NULL, catatan_kepala=NULL
                        WHERE id=?");
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'sssisssi', $jenisCuti, $tglMulai, $tglSelesai, $lamaHari, $alasan, $alamat, $dokumenName, $cutiId);
                        if (mysqli_stmt_execute($stmt)) {
                            $notice = 'Usulan cuti berhasil diperbarui dan dikirim kembali untuk verifikasi.';
                        } else {
                            $error = 'Gagal memperbarui usulan cuti.';
                        }
                    } else {
                        $error = 'Form perubahan usulan cuti tidak dapat diproses.';
                    }
                }
            }
        }
    } elseif ($action === 'verify_kepala' && $role === 'superadmin') {
        $keputusan = $_POST['status_keputusan'] ?? 'Disetujui';
        $catatan = trim($_POST['catatan'] ?? '');

        mysqli_begin_transaction($conn);
        try {
            $cutiRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM cuti_requests WHERE id=$cutiId FOR UPDATE"));
            if ($cutiRow) {
                $shouldDeduct = ($keputusan === 'Disetujui' && $cutiRow['status_akhir'] !== 'Disetujui' && $cutiRow['jenis_cuti'] === 'Cuti Tahunan');
                $nomorSurat = $cutiRow['nomor_surat'];
                if ($keputusan === 'Disetujui' && !$nomorSurat) {
                    $nomorSurat = 'B-' . rand(100, 999) . '/Kemenag.Lb/Kp.02.3/' . date('m') . '/' . date('Y');
                }

                $stmt = mysqli_prepare($conn, "UPDATE cuti_requests SET status_kepala=?, catatan_kepala=?, status_akhir=?, nomor_surat=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'ssssi', $keputusan, $catatan, $keputusan, $nomorSurat, $cutiId);
                mysqli_stmt_execute($stmt);

                if ($shouldDeduct) {
                    $pegId = (int)$cutiRow['pegawai_id'];
                    $peg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT sctn2, sctn1, sctn FROM pegawai WHERE id=$pegId FOR UPDATE"));
                    if ($peg) {
                        $sisa = (int)$cutiRow['lama_hari_cuti'];
                        $ambil2 = min((int)$peg['sctn2'], $sisa);
                        $new2 = (int)$peg['sctn2'] - $ambil2;
                        $sisa -= $ambil2;

                        $ambil1 = min((int)$peg['sctn1'], $sisa);
                        $new1 = (int)$peg['sctn1'] - $ambil1;
                        $sisa -= $ambil1;

                        $ambil0 = min((int)$peg['sctn'], $sisa);
                        $new0 = (int)$peg['sctn'] - $ambil0;

                        $uStmt = mysqli_prepare($conn, "UPDATE pegawai SET sctn2=?, sctn1=?, sctn=? WHERE id=?");
                        mysqli_stmt_bind_param($uStmt, 'iiii', $new2, $new1, $new0, $pegId);
                        mysqli_stmt_execute($uStmt);
                    }
                }
            }
            mysqli_commit($conn);
            $notice = 'Verifikasi Kepala Kantor berhasil disimpan dan sisa cuti diperbarui otomatis.';
        } catch (Throwable $ex) {
            mysqli_rollback($conn);
            $error = 'Terjadi kesalahan saat menyimpan verifikasi.';
        }
    } elseif ($action === 'delete' && $role !== 'operator') {
        $cutiRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status_akhir FROM cuti_requests WHERE id=$cutiId"));
        if ($cutiRow && $cutiRow['status_akhir'] === 'Disetujui') {
            $error = 'Usulan cuti yang sudah disetujui Kepala Kantor tidak dapat dihapus.';
        } else {
            mysqli_query($conn, "DELETE FROM cuti_requests WHERE id=$cutiId");
            $notice = 'Usulan cuti berhasil dihapus.';
        }
    }
}

$search = trim($_GET['q'] ?? '');
$allowedPerPage = [30, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 30);
if (!in_array($perPage, $allowedPerPage, true)) $perPage = 30;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$where = [];
if ($isRestricted) {
    $where[] = "p.unit_kerja_id = $userUnitId";
}
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(p.nama_lengkap LIKE '%$esc%' OR p.nip LIKE '%$esc%' OR c.jenis_cuti LIKE '%$esc%')";
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countRes = mysqli_query($conn, "SELECT COUNT(*) AS total
    FROM cuti_requests c
    JOIN pegawai p ON c.pegawai_id = p.id
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    $whereSql");
$totalCuti = (int)(mysqli_fetch_assoc($countRes)['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalCuti / $perPage));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;

$resCuti = mysqli_query($conn, "SELECT c.*, p.nama_lengkap, p.nip, p.jabatan, p.status_pegawai, p.no_wa, u.nama_unit
    FROM cuti_requests c
    JOIN pegawai p ON c.pegawai_id = p.id
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    $whereSql
    ORDER BY c.id DESC
    LIMIT $offset, $perPage");

$waTemplate = getWaTemplate($conn);
$pdfPreview = null;
if (isset($_GET['preview_pdf'])) {
    $pid = (int)$_GET['preview_pdf'];
    $previewUnitCond = $isRestricted ? " AND p.unit_kerja_id = $userUnitId" : "";
    $pdfPreview = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.*, p.nama_lengkap, p.nip, p.jabatan, p.status_pegawai, p.no_wa, u.nama_unit
        FROM cuti_requests c
        JOIN pegawai p ON c.pegawai_id = p.id
        LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
        WHERE c.id=$pid $previewUnitCond"));
}

include 'layout_header.php';
?>
<div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Monitoring & Verifikasi Usulan Cuti Pegawai</h2>
        <p class="text-sm text-gray-600 mt-1">Verifikasi 2 tahap (Atasan Langsung & Kepala Kantor), Preview PDF Surat Permohonan, Cetak Form Resmi, dan Notifikasi WhatsApp.</p>
    </div>

    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($pdfPreview): ?>
        <?php
            $safePdfFile = basename((string)$pdfPreview['dokumen_pdf']);
            $filePath = __DIR__ . '/uploads/' . $safePdfFile;
            $hasFilePdf = ($safePdfFile !== '' && file_exists($filePath));
            $pdfDataUri = trim((string)($pdfPreview['dokumen_pdf_data'] ?? ''));
            $hasDbPdf = (strpos($pdfDataUri, 'data:application/pdf') === 0);
            $hasRealPdf = ($hasFilePdf || $hasDbPdf);
            $pdfDownloadHref = $hasFilePdf ? ('uploads/' . rawurlencode($safePdfFile)) : $pdfDataUri;
        ?>
        <div class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4 overflow-y-auto print:hidden">
            <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden my-6">
                <div class="bg-[#006838] text-white px-6 py-4 flex justify-between items-center border-b-4 border-[#D4AF37]">
                    <div>
                        <h3 class="font-bold text-base">Preview Dokumen PDF Surat Permohonan Cuti</h3>
                        <div class="text-xs text-emerald-100 font-mono"><?= e($pdfPreview['dokumen_pdf'] ?: 'Surat_Permohonan_Cuti.pdf') ?></div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if ($hasRealPdf): ?>
                            <button type="button" onclick='printPdfDocument(<?= json_encode($pdfDownloadHref, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' class="px-3.5 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold">Cetak Dokumen</button>
                            <a href="<?= e($pdfDownloadHref) ?>" download="<?= e($safePdfFile ?: 'Surat_Permohonan_Cuti.pdf') ?>" class="px-3.5 py-1.5 bg-[#D4AF37] hover:bg-amber-500 text-gray-900 rounded-xl text-xs font-bold">Unduh PDF</a>
                        <?php endif; ?>
                        <a href="usulan_cuti.php" class="px-3.5 py-1.5 bg-white text-[#006838] rounded-xl text-xs font-bold">Tutup</a>
                    </div>
                </div>
                <div class="p-6 bg-gray-100 max-h-[80vh] overflow-y-auto">
                    <?php if ($hasRealPdf): ?>
                        <object data="<?= e($pdfDownloadHref) ?>" type="application/pdf" class="w-full h-[68vh] rounded-xl border bg-white">
                            <iframe src="<?= e($pdfDownloadHref) ?>" class="w-full h-[68vh] rounded-xl border bg-white"></iframe>
                        </object>
                    <?php else: ?>
                        <div class="bg-white max-w-2xl mx-auto p-8 rounded-xl shadow border text-xs space-y-4 font-serif">
                            <div class="text-right">Rangkasbitung, <?= formatDateIndo($pdfPreview['tanggal_pengajuan']) ?></div>
                            <div>Kepada Yth.<br><b>Kepala Kantor Kementerian Agama Kabupaten Lebak</b><br>di Rangkasbitung</div>
                            <div class="text-center font-bold underline text-sm">SURAT PERMOHONAN <?= strtoupper(e($pdfPreview['jenis_cuti'])) ?></div>
                            <p>Yang bertanda tangan di bawah ini: <b><?= e($pdfPreview['nama_lengkap']) ?></b> (NIP. <?= e($pdfPreview['nip']) ?>), Jabatan: <?= e($pdfPreview['jabatan']) ?>, Unit Kerja: <?= e($pdfPreview['nama_unit']) ?>.</p>
                            <p>Dengan ini mengajukan permohonan <b><?= e($pdfPreview['jenis_cuti']) ?></b> selama <b><?= (int)$pdfPreview['lama_hari_cuti'] ?> hari kerja</b> mulai <?= formatDateIndo($pdfPreview['tanggal_mulai']) ?> s/d <?= formatDateIndo($pdfPreview['tanggal_selesai']) ?> dengan alasan: <i><?= e($pdfPreview['alasan']) ?></i>.</p>
                            <p>Alamat selama cuti: <?= e($pdfPreview['alamat_selama_cuti']) ?> (Telp/WA: <?= e($pdfPreview['no_wa']) ?>).</p>
                            <div class="text-right pt-6">
                                <div class="inline-block text-center">
                                    Hormat saya,<br><br><br>
                                    <b class="underline"><?= e($pdfPreview['nama_lengkap']) ?></b><br>NIP. <?= e($pdfPreview['nip']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 flex gap-3">
        <input type="hidden" name="page" value="1">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari berdasarkan nama pegawai, NIP, atau jenis cuti..." class="flex-1 px-4 py-2 rounded-xl border text-sm">
        <select name="per_page" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-gray-300 text-xs text-gray-700 bg-white" title="Jumlah baris per halaman">
            <?php foreach ([30, 50, 100] as $optionPerPage): ?>
                <option value="<?= $optionPerPage ?>" <?= $perPage === $optionPerPage ? 'selected' : '' ?>><?= $optionPerPage ?> Baris</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="px-4 py-2 bg-[#006838] text-white rounded-xl text-xs font-bold">Cari</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-emerald-50 text-[#006838] uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Pegawai & Unit</th>
                        <th class="px-4 py-3">Jenis Cuti</th>
                        <th class="px-4 py-3">Jangka Waktu</th>
                        <th class="px-4 py-3 text-center">Verif. Atasan</th>
                        <th class="px-4 py-3 text-center">Catatan Atasan Langsung</th>
                        <th class="px-4 py-3 text-center">Verif. Kepala</th>
                        <th class="px-4 py-3 text-center">Catatan Kepala Kantor</th>
                        <th class="px-4 py-3 text-center">Status Akhir</th>
                        <th class="px-4 py-3 text-center">Aksi / Dokumen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($totalCuti === 0): ?>
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-gray-500">Tidak ada data usulan cuti yang ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                    <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($resCuti)):
                        $waMsg = str_replace(
                            ['{nama}', '{jenis}', '{status}', '{tanggal}', '{lama}', '{nomor_surat}'],
                            [$row['nama_lengkap'], $row['jenis_cuti'], $row['status_akhir'], formatDateIndo($row['tanggal_mulai']) . ' s/d ' . formatDateIndo($row['tanggal_selesai']), (string)$row['lama_hari_cuti'], $row['nomor_surat'] ?: '-'],
                            $waTemplate
                        );
                        $waPhone = preg_replace('/^0/', '62', preg_replace('/\D/', '', $row['no_wa']));
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3"><?= $no++ ?></td>
                            <td class="px-4 py-3">
                                <div class="font-bold text-gray-900"><?= e($row['nama_lengkap']) ?></div>
                                <div class="text-xs font-mono text-gray-500"><?= e($row['nip']) ?></div>
                                <div class="text-[11px] text-emerald-700 font-medium"><?= e($row['nama_unit']) ?></div>
                            </td>
                            <td class="px-4 py-3 font-semibold"><?= e($row['jenis_cuti']) ?></td>
                            <td class="px-4 py-3 text-xs">
                                <div><?= formatDateIndo($row['tanggal_mulai']) ?> s/d <?= formatDateIndo($row['tanggal_selesai']) ?></div>
                                <b class="text-[#006838]">(<?= (int)$row['lama_hari_cuti'] ?> Hari Kerja)</b>
                            </td>
                            <td class="px-4 py-3 text-center text-xs font-bold"><?= e($row['status_atasan']) ?></td>
                            <td class="px-4 py-3 text-xs text-gray-700 min-w-[180px]"><?= e($row['catatan_atasan'] ?: '-') ?></td>
                            <td class="px-4 py-3 text-center text-xs font-bold"><?= e($row['status_kepala']) ?></td>
                            <td class="px-4 py-3 text-xs text-gray-700 min-w-[180px]"><?= e($row['catatan_kepala'] ?: '-') ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $row['status_akhir'] === 'Disetujui' ? 'bg-emerald-600 text-white' : ($row['status_akhir'] === 'Ditolak' ? 'bg-rose-600 text-white' : 'bg-blue-100 text-blue-800') ?>">
                                    <?= e($row['status_akhir']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center space-y-1.5">
                                <?php if (in_array($role, ['superadmin', 'admin'], true)): ?>
                                    <div class="flex flex-wrap justify-center gap-1">
                                        <button type="button" onclick="openVerifyModal(<?= (int)$row['id'] ?>, 'verify_atasan', 'Verifikasi Atasan Langsung')" class="px-2 py-1 bg-emerald-50 text-[#006838] rounded text-[11px] font-bold">Verif Atasan</button>
                                        <?php if ($role === 'superadmin'): ?>
                                            <button type="button" onclick="openVerifyModal(<?= (int)$row['id'] ?>, 'verify_kepala', 'Verifikasi Kepala Kantor')" class="px-2 py-1 bg-amber-50 text-amber-800 rounded text-[11px] font-bold">Verif Kepala</button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="flex flex-wrap justify-center gap-1">
                                    <a href="usulan_cuti.php?preview_pdf=<?= (int)$row['id'] ?>" class="px-2 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded text-[11px] font-bold">Preview PDF</a>
                                    <?php if ($role === 'admin' && $row['status_akhir'] !== 'Disetujui'): ?>
                                        <button type="button" onclick='openEditRequestModal(<?= json_encode([
                                            "id" => (int)$row["id"],
                                            "jenis_cuti" => $row["jenis_cuti"],
                                            "tanggal_mulai" => $row["tanggal_mulai"],
                                            "tanggal_selesai" => $row["tanggal_selesai"],
                                            "alasan" => $row["alasan"],
                                            "alamat_selama_cuti" => $row["alamat_selama_cuti"],
                                            "dokumen_pdf" => $row["dokumen_pdf"],
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)' class="px-2 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded text-[11px] font-bold">Edit Usulan</button>
                                    <?php endif; ?>

                                    <?php
                                        $statusAkhirKey = strtolower(trim(preg_replace('/\s+/', ' ', (string)$row['status_akhir'])));
                                        $cetakDiblokir = in_array($statusAkhirKey, [
                                            'menunggu verifikasi atasan',
                                            'menunggu verifikasi atasan langsung',
                                            'menunggu verifikasi kepala'
                                        ], true);
                                    ?>
                                    <?php if ($role !== 'operator' || $row['status_akhir'] === 'Disetujui'): ?>
                                        <?php if ($cetakDiblokir): ?>
                                            <button type="button" onclick="openCetakBlockedModal()" class="px-2 py-1 bg-emerald-600 text-white rounded text-[11px] font-bold">Cetak Form</button>
                                        <?php else: ?>
                                            <a href="cetak_form_cuti.php?id=<?= (int)$row['id'] ?>" target="_blank" class="px-2 py-1 bg-emerald-600 text-white rounded text-[11px] font-bold">Cetak Form</a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <a href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($waMsg) ?>" target="_blank" class="px-2 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded text-[11px] font-bold">WA</a>

                                    <?php if ($role !== 'operator' && $row['status_akhir'] !== 'Disetujui'): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Hapus usulan cuti ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="cuti_id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="px-2 py-1 bg-rose-50 text-rose-700 rounded text-[11px] font-bold">Hapus</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
            $paginationParams = [
                'q' => $search,
                'per_page' => $perPage,
            ];
            $pageUrl = function (int $page) use ($paginationParams): string {
                $params = array_merge($paginationParams, ['page' => $page]);
                return 'usulan_cuti.php?' . http_build_query($params);
            };
            $rangeStart = $totalCuti > 0 ? $offset + 1 : 0;
            $rangeEnd = min($offset + $perPage, $totalCuti);
        ?>
        <div class="px-5 py-3.5 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-gray-600">
                Menampilkan <b class="text-gray-900"><?= $rangeStart ?>-<?= $rangeEnd ?></b> dari <b class="text-gray-900"><?= $totalCuti ?></b> usulan cuti
            </div>
            <div class="flex items-center gap-2">
                <?php if ($currentPage > 1): ?>
                    <a href="<?= e($pageUrl($currentPage - 1)) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Prev page
                    </a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 bg-gray-100 text-xs font-bold text-gray-400 cursor-not-allowed">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Prev page
                    </span>
                <?php endif; ?>

                <span class="px-3 py-2 rounded-lg bg-emerald-50 border border-emerald-200 text-xs font-bold text-[#006838]">
                    Halaman <?= $currentPage ?> / <?= $totalPages ?>
                </span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?= e($pageUrl($currentPage + 1)) ?>" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-xs font-bold text-gray-700 transition">
                        Next page
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 bg-gray-100 text-xs font-bold text-gray-400 cursor-not-allowed">
                        Next page
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Verifikasi -->
<div id="cetakBlockedModal" class="<?= isset($_GET['cetak_blocked']) ? 'flex' : 'hidden' ?> fixed inset-0 z-50 bg-black/50 items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-[#006838] text-white px-6 py-4 border-b-4 border-[#D4AF37]">
            <h3 class="font-bold text-base">Cetak Form Tidak Dapat Dilakukan</h3>
        </div>
        <div class="p-6 space-y-5">
            <p class="text-sm text-gray-700">Usulan cuti belum mendapat persetujuan Atasan/Kepala Kantor.</p>
            <div class="flex justify-end border-t pt-4">
                <a href="usulan_cuti.php" class="px-5 py-2 bg-[#006838] text-white rounded-xl text-xs font-bold">Kembali ke Usulan Cuti</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Verifikasi -->
<div id="verifyModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-[#006838] text-white px-6 py-4 flex justify-between items-center border-b-4 border-[#D4AF37]">
            <h3 id="verifyModalTitle" class="font-bold text-base">Verifikasi</h3>
            <button type="button" onclick="document.getElementById('verifyModal').classList.add('hidden')" class="text-white font-bold">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="verifyAction">
            <input type="hidden" name="cuti_id" id="verifyCutiId">
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Keputusan</label>
                <select name="status_keputusan" class="w-full px-3 py-2 rounded-xl border text-sm font-bold bg-white">
                    <option value="Disetujui">Disetujui</option>
                    <option value="Perubahan">Perubahan</option>
                    <option value="Ditangguhkan">Ditangguhkan</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Catatan</label>
                <textarea name="catatan" rows="3" class="w-full px-3 py-2 rounded-xl border text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2 pt-2 border-t">
                <button type="button" onclick="document.getElementById('verifyModal').classList.add('hidden')" class="px-4 py-2 bg-gray-100 rounded-xl text-xs font-bold">Batal</button>
                <button type="submit" class="px-5 py-2 bg-[#006838] text-white rounded-xl text-xs font-bold">Simpan Verifikasi</button>
            </div>
        </form>
    </div>
</div>

<?php if ($role === 'admin'): ?>
<div id="editRequestModal" class="hidden fixed inset-0 z-50 bg-black/60 items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden my-6">
        <div class="bg-[#006838] text-white px-6 py-4 flex justify-between items-center border-b-4 border-[#D4AF37]">
            <div>
                <h3 class="font-bold text-base">Edit Usulan Cuti</h3>
                <p class="text-xs text-emerald-100 mt-1">Perubahan akan dikirim ulang untuk verifikasi.</p>
            </div>
            <button type="button" onclick="closeEditRequestModal()" class="text-white font-bold text-xl" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit_request">
            <input type="hidden" name="cuti_id" id="editRequestId">
            <div>
                <label for="editJenisCuti" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Jenis Cuti</label>
                <select id="editJenisCuti" name="jenis_cuti" required class="w-full px-3 py-2.5 rounded-xl border text-sm bg-white">
                    <option value="Cuti Tahunan">Cuti Tahunan</option>
                    <option value="Cuti Besar">Cuti Besar</option>
                    <option value="Cuti Sakit">Cuti Sakit</option>
                    <option value="Cuti Melahirkan">Cuti Melahirkan</option>
                    <option value="Cuti Karena Alasan Penting">Cuti Karena Alasan Penting</option>
                    <option value="CLTN (Cuti Luar Tanggungan Negara)">CLTN (Cuti Luar Tanggungan Negara)</option>
                    <option value="Cuti Bersama">Cuti Bersama</option>
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="editTanggalMulai" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Tanggal Mulai</label>
                    <input id="editTanggalMulai" type="date" name="tanggal_mulai" required class="w-full px-3 py-2.5 rounded-xl border text-sm">
                </div>
                <div>
                    <label for="editTanggalSelesai" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Tanggal Selesai</label>
                    <input id="editTanggalSelesai" type="date" name="tanggal_selesai" required class="w-full px-3 py-2.5 rounded-xl border text-sm">
                </div>
            </div>
            <div>
                <label for="editAlasan" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Alasan Cuti</label>
                <textarea id="editAlasan" name="alasan" rows="3" required class="w-full px-3 py-2.5 rounded-xl border text-sm"></textarea>
            </div>
            <div>
                <label for="editAlamat" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Alamat Selama Cuti</label>
                <textarea id="editAlamat" name="alamat_selama_cuti" rows="2" required class="w-full px-3 py-2.5 rounded-xl border text-sm"></textarea>
            </div>
            <div>
                <label for="editDokumenPdf" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Upload Ulang Surat Permohonan (PDF)</label>
                <div id="editExistingPdf" class="text-xs text-gray-500 mb-2"></div>
                <input id="editDokumenPdf" type="file" name="dokumen_pdf" accept=".pdf,application/pdf" class="w-full text-xs">
                <p class="text-[11px] text-gray-500 mt-1">Kosongkan jika dokumen lama tetap digunakan.</p>
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" onclick="closeEditRequestModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-xs font-bold">Batal</button>
                <button type="submit" class="px-5 py-2 bg-[#006838] hover:bg-emerald-900 text-white rounded-xl text-xs font-bold">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function printPdfDocument(url) {
    const printWindow = window.open(url, '_blank');
    if (!printWindow) {
        alert('Jendela cetak diblokir browser. Izinkan pop-up untuk mencetak dokumen.');
        return;
    }

    let printStarted = false;
    const startPrint = function () {
        if (printStarted || printWindow.closed) return;
        printStarted = true;
        printWindow.focus();
        printWindow.print();
    };
    printWindow.addEventListener('load', startPrint, { once: true });
    window.setTimeout(startPrint, 1500);
}

function openCetakBlockedModal() {
    document.getElementById('cetakBlockedModal').classList.remove('hidden');
    document.getElementById('cetakBlockedModal').classList.add('flex');
}

function openVerifyModal(cutiId, actionType, title) {
    document.getElementById('verifyCutiId').value = cutiId;
    document.getElementById('verifyAction').value = actionType;
    document.getElementById('verifyModalTitle').textContent = title;
    document.getElementById('verifyModal').classList.remove('hidden');
}

function openEditRequestModal(request) {
    document.getElementById('editRequestId').value = request.id;
    document.getElementById('editJenisCuti').value = request.jenis_cuti;
    document.getElementById('editTanggalMulai').value = request.tanggal_mulai;
    document.getElementById('editTanggalSelesai').value = request.tanggal_selesai;
    document.getElementById('editAlasan').value = request.alasan;
    document.getElementById('editAlamat').value = request.alamat_selama_cuti;
    document.getElementById('editDokumenPdf').value = '';
    document.getElementById('editExistingPdf').textContent = 'Dokumen saat ini: ' + (request.dokumen_pdf || 'Belum ada dokumen');

    const modal = document.getElementById('editRequestModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeEditRequestModal() {
    const modal = document.getElementById('editRequestModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
<?php include 'layout_footer.php'; ?>
