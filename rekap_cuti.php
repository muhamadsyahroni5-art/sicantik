<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'rekap_cuti';

$isRestricted = in_array($currentUser['role'], ['operator', 'admin'], true);
$userUnitId = (int)$currentUser['unit_kerja_id'];

$search = trim($_GET['q'] ?? '');
$filterUnit = $isRestricted ? (string)$userUnitId : ($_GET['unit'] ?? 'ALL');
$filterYear = $_GET['tahun'] ?? '2026';
$filterStatus = trim($_GET['status'] ?? 'ALL');
$dateFrom = trim($_GET['tanggal_mulai'] ?? '');
$dateTo = trim($_GET['tanggal_akhir'] ?? '');
$dateFilterError = '';

$isValidDate = static function (string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
};

if (($dateFrom !== '' && !$isValidDate($dateFrom)) || ($dateTo !== '' && !$isValidDate($dateTo))) {
    $dateFilterError = 'Format rentang tanggal pengajuan tidak valid.';
    $dateFrom = '';
    $dateTo = '';
} elseif ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $dateFilterError = 'Tanggal mulai tidak boleh melewati tanggal akhir.';
    $dateFrom = '';
    $dateTo = '';
}

$allowedStatuses = ['ALL', 'Disetujui', 'Ditolak', 'Diproses'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = 'ALL';
}

$where = [];
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $where[] = "(p.nama_lengkap LIKE '%$esc%' OR p.nip LIKE '%$esc%')";
}
if ($filterUnit !== 'ALL') {
    $where[] = "p.unit_kerja_id = " . (int)$filterUnit;
}
if ($filterYear !== 'ALL') {
    $escYr = mysqli_real_escape_string($conn, $filterYear);
    $where[] = "c.tanggal_mulai LIKE '$escYr%'";
}
if ($dateFrom !== '') {
    $where[] = "c.tanggal_pengajuan >= '" . mysqli_real_escape_string($conn, $dateFrom) . "'";
}
if ($dateTo !== '') {
    $where[] = "c.tanggal_pengajuan <= '" . mysqli_real_escape_string($conn, $dateTo) . "'";
}
if ($filterStatus === 'Disetujui' || $filterStatus === 'Ditolak') {
    $escSt = mysqli_real_escape_string($conn, $filterStatus);
    $where[] = "c.status_akhir = '$escSt'";
} elseif ($filterStatus === 'Diproses') {
    $where[] = "(c.status_akhir IS NULL OR c.status_akhir NOT IN ('Disetujui', 'Ditolak'))";
}
$whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$resRekap = mysqli_query($conn, "SELECT c.*, p.nama_lengkap, p.nip, p.jabatan, p.sctn2, p.sctn1, p.sctn, u.nama_unit
    FROM cuti_requests c
    JOIN pegawai p ON c.pegawai_id = p.id
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    $whereSql
    ORDER BY p.nama_lengkap ASC, c.tanggal_pengajuan ASC, c.id ASC");

$rows = [];
$groupedRows = [];
while ($r = mysqli_fetch_assoc($resRekap)) {
    $rows[] = $r;
    $pidKey = (string)$r['pegawai_id'];
    if (!isset($groupedRows[$pidKey])) {
        $groupedRows[$pidKey] = [
            'pegawai' => $r,
            'items' => [],
        ];
    }
    $groupedRows[$pidKey]['items'][] = $r;
}

$unitsRes = mysqli_query($conn, "SELECT * FROM unit_kerja ORDER BY id ASC");
$unitList = [];
while ($u = mysqli_fetch_assoc($unitsRes)) $unitList[] = $u;

$selectedUnitName = 'Seluruh Unit Kerja';
if ($filterUnit !== 'ALL') {
    foreach ($unitList as $u) {
        if ((string)$u['id'] === (string)$filterUnit) {
            $selectedUnitName = $u['nama_unit'];
            break;
        }
    }
}

$statusLabelMap = [
    'ALL' => 'Semua Status',
    'Disetujui' => 'Disetujui',
    'Ditolak' => 'Ditolak',
    'Diproses' => 'Menunggu / Diproses',
];
$selectedStatusLabel = $statusLabelMap[$filterStatus] ?? 'Semua Status';

$logoKemenagSrc = file_exists(__DIR__ . '/assets/logo-kemenag.jpg')
    ? 'assets/logo-kemenag.jpg'
    : (file_exists(__DIR__ . '/logo-kemenag.jpg')
        ? 'logo-kemenag.jpg'
        : (file_exists(__DIR__ . '/assets/logo-kemenag.png') ? 'assets/logo-kemenag.png' : 'logo-kemenag.png'));

$penandatangan = getKepalaKantor($conn);
if ($currentUser['role'] === 'operator') {
    foreach ($unitList as $u) {
        if ((int)$u['id'] === $userUnitId) {
            $penandatangan = $u;
            break;
        }
    }
}
include 'layout_header.php';
?>
<style>
@media print {
    @page { size: A4 landscape; margin: 1cm; }
    body { background: #fff !important; }
}
</style>

<div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Rekapitulasi Data Usulan Cuti Pegawai</h2>
            <p class="text-sm text-gray-600 mt-1">
                <?= $isRestricted ? 'Rekapitulasi khusus unit kerja: ' . e($selectedUnitName) : 'Filter berdasarkan nama/NIP, unit kerja, tahun, status, dan rentang tanggal pengajuan.' ?>
            </p>
        </div>
        <button onclick="window.print()" class="bg-[#006838] hover:bg-emerald-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow">
            Cetak Laporan Resmi (Kop Surat)
        </button>
    </div>

    <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 flex flex-wrap gap-3 items-center print:hidden">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari Nama Pegawai / NIP..." class="flex-1 min-w-[200px] px-4 py-2 rounded-xl border text-sm">
        <?php if (!$isRestricted): ?>
            <select name="unit" class="px-3 py-2 rounded-xl border text-sm bg-white">
                <option value="ALL">Semua Unit Kerja</option>
                <?php foreach ($unitList as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= ((string)$filterUnit === (string)$u['id']) ? 'selected' : '' ?>><?= e($u['nama_unit']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div class="px-3 py-2 rounded-xl bg-emerald-50 border border-emerald-200 text-xs font-bold text-[#006838]"><?= e($selectedUnitName) ?></div>
        <?php endif; ?>
        <select name="tahun" class="px-3 py-2 rounded-xl border text-sm bg-white">
            <option value="ALL" <?= $filterYear === 'ALL' ? 'selected' : '' ?>>Semua Tahun</option>
            <option value="2026" <?= $filterYear === '2026' ? 'selected' : '' ?>>2026</option>
            <option value="2025" <?= $filterYear === '2025' ? 'selected' : '' ?>>2025</option>
            <option value="2024" <?= $filterYear === '2024' ? 'selected' : '' ?>>2024</option>
        </select>
        <select name="status" class="px-3 py-2 rounded-xl border text-sm bg-white">
            <option value="ALL" <?= $filterStatus === 'ALL' ? 'selected' : '' ?>>Semua Status</option>
            <option value="Disetujui" <?= $filterStatus === 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
            <option value="Ditolak" <?= $filterStatus === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
            <option value="Diproses" <?= $filterStatus === 'Diproses' ? 'selected' : '' ?>>Menunggu / Diproses</option>
        </select>
        <div class="flex flex-wrap items-center gap-2">
            <label for="tanggal_mulai" class="text-xs font-semibold text-gray-600">Tanggal Pengajuan:</label>
            <input id="tanggal_mulai" type="date" name="tanggal_mulai" value="<?= e($dateFrom) ?>" aria-label="Tanggal pengajuan mulai" class="px-3 py-2 rounded-xl border text-sm bg-white">
            <span class="text-xs text-gray-500">s.d.</span>
            <input type="date" name="tanggal_akhir" value="<?= e($dateTo) ?>" aria-label="Tanggal pengajuan akhir" class="px-3 py-2 rounded-xl border text-sm bg-white">
        </div>
        <button type="submit" class="px-4 py-2 bg-[#006838] text-white rounded-xl text-xs font-bold">Filter</button>
    </form>
    <?php if ($dateFilterError !== ''): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium print:hidden"><?= e($dateFilterError) ?></div>
    <?php endif; ?>

    <!-- Area Laporan Resmi (Tampil di layar & mode cetak) -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 print:p-0 print:border-0 print:shadow-none text-black">
<div class="flex items-center gap-4 border-b-4 border-double border-black pb-3 mb-4" style="font-family: Arial, sans-serif;">
    <img
        src="<?= e($logoKemenagSrc) ?>"
        alt="Logo Kementerian Agama"
        class="w-18 h-18 object-contain object-center shrink-0 ml-14"
        style="width: 68px; height: 68px;"
    >
    <div class="flex-1 text-center pr-16">
        <div class="font-bold uppercase" style="font-size: 14pt;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
        <div class="font-bold uppercase" style="font-size: 12pt;">KANTOR KEMENTERIAN AGAMA KABUPATEN LEBAK</div>
        <div style="font-size: 9pt;">Jl. Siliwangi No. 2, Kel. Muara Ciujung Timur Kec. Rangkasbitung, Kabupaten Lebak, Banten 42314</div>
        <div style="font-size: 9pt;">Telp: (0252) 201319 • E-mail: sekjen_416610@kemenag.go.id • site: https://kemenaglebak.com</div>
    </div>
</div>
        <div class="text-center mb-4">
            <h3 class="font-bold text-sm uppercase underline">LAPORAN REKAPITULASI DATA USULAN CUTI PEGAWAI</h3>
            <div class="text-xs mt-1">
                Unit Kerja: <b><?= e($selectedUnitName) ?></b>
                | Tahun: <b><?= e($filterYear === 'ALL' ? 'Semua Tahun' : $filterYear) ?></b>
                | Status: <b><?= e($selectedStatusLabel) ?></b>
                | Tanggal Pengajuan: <b><?= e($dateFrom !== '' ? formatDateIndo($dateFrom) : 'Awal') ?> s.d. <?= e($dateTo !== '' ? formatDateIndo($dateTo) : 'Akhir') ?></b>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-black text-xs text-left">
                <thead>
                    <tr class="bg-gray-100 text-center font-bold">
                        <th class="border border-black px-2 py-2">No</th>
                        <th class="border border-black px-2 py-2">Nama Pegawai & NIP</th>
                        <th class="border border-black px-2 py-2">Jabatan</th>
                        <th class="border border-black px-2 py-2">Unit Kerja</th>
                        <th class="border border-black px-2 py-2">Tgl Pengajuan</th>
                        <th class="border border-black px-2 py-2">Tanggal Cuti</th>
                        <th class="border border-black px-2 py-2">Lama</th>
                        <th class="border border-black px-2 py-2">Sisa SCT</th>
                        <th class="border border-black px-2 py-2">Status</th>
                    </tr>
                </thead>
                <?php if (count($groupedRows) === 0): ?>
                    <tbody><tr><td colspan="9" class="border border-black text-center py-6 text-gray-500">Tidak ada data rekap cuti.</td></tr></tbody>
                <?php else: $no = 1; foreach ($groupedRows as $group): $peg = $group['pegawai']; $items = $group['items']; $rowCount = count($items); $totalSct = (int)$peg['sctn2'] + (int)$peg['sctn1'] + (int)$peg['sctn']; ?>
                    <tbody class="break-inside-avoid">
                    <?php foreach ($items as $idx => $r): ?>
                        <tr>
                            <?php if ($idx === 0): ?>
                                <td rowspan="<?= $rowCount ?>" class="border border-black px-2 py-1.5 text-center align-top"><?= $no++ ?></td>
                                <td rowspan="<?= $rowCount ?>" class="border border-black px-2 py-1.5 align-top">
                                    <div class="font-bold"><?= e($peg['nama_lengkap']) ?></div>
                                    <div class="font-mono text-[10px]">NIP. <?= e($peg['nip']) ?></div>
                                </td>
                                <td rowspan="<?= $rowCount ?>" class="border border-black px-2 py-1.5 align-top"><?= e($peg['jabatan']) ?></td>
                                <td rowspan="<?= $rowCount ?>" class="border border-black px-2 py-1.5 align-top"><?= e($peg['nama_unit']) ?></td>
                            <?php endif; ?>
                            <td class="border border-black px-2 py-1.5 whitespace-nowrap"><?= formatDateIndo($r['tanggal_pengajuan']) ?></td>
                            <td class="border border-black px-2 py-1.5 whitespace-nowrap"><?= formatDateIndo($r['tanggal_mulai']) ?> s/d <?= formatDateIndo($r['tanggal_selesai']) ?></td>
                            <td class="border border-black px-2 py-1.5 text-center font-bold"><?= (int)$r['lama_hari_cuti'] ?> Hari</td>
                            <?php if ($idx === 0): ?>
                                <td rowspan="<?= $rowCount ?>" class="border border-black px-2 py-1.5 text-center font-mono align-top"><?= $totalSct ?> Hari</td>
                            <?php endif; ?>
                            <td class="border border-black px-2 py-1.5 text-center font-semibold"><?= e($r['status_akhir']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                <?php endforeach; endif; ?>
            </table>
        </div>

        <div class="flex justify-between items-start mt-8 text-xs">
            <div>
                <div>Total Data: <b><?= count($rows) ?></b> usulan cuti (<b><?= count($groupedRows) ?></b> pegawai)</div>
                <div>Dicetak pada: <?= formatDateIndo(date('Y-m-d')) ?></div>
            </div>
            <div class="text-center w-80">
                <div>Rangkasbitung, <?= formatDateIndo(date('Y-m-d')) ?></div>
                <div class="font-bold mt-1 uppercase leading-snug"><?= e($penandatangan['jabatan_pimpinan']) ?></div>
                <div class="h-16"></div>
                <div class="font-bold underline"><?= e($penandatangan['pimpinan']) ?></div>
                <div class="font-mono">NIP. <?= e($penandatangan['nip_pimpinan']) ?></div>
            </div>
        </div>
    </div>
</div>
<?php include 'layout_footer.php'; ?>