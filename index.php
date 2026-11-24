<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'dashboard';

$isRestricted = in_array($currentUser['role'], ['operator', 'admin'], true);
$unitId = (int)($currentUser['unit_kerja_id'] ?? 0);

$selectedYear = $_GET['tahun'] ?? '2026';
$yearsRes = mysqli_query($conn, "SELECT DISTINCT LEFT(tanggal_mulai, 4) AS yr FROM cuti_requests WHERE tanggal_mulai <> '' ORDER BY yr DESC");
$availableYears = ['2026', '2025', '2024'];
if ($yearsRes) {
    while ($yrRow = mysqli_fetch_assoc($yearsRes)) {
        if (!in_array($yrRow['yr'], $availableYears, true) && strlen($yrRow['yr']) === 4) {
            $availableYears[] = $yrRow['yr'];
        }
    }
}
rsort($availableYears);

$pegWhere = $isRestricted ? "WHERE unit_kerja_id = $unitId" : "";
$resPns = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pegawai " . ($pegWhere ? "$pegWhere AND status_pegawai='PNS'" : "WHERE status_pegawai='PNS'"));
$resPppk = mysqli_query($conn, "SELECT COUNT(*) AS total FROM pegawai " . ($pegWhere ? "$pegWhere AND status_pegawai='PPPK'" : "WHERE status_pegawai='PPPK'"));
$totalPns = (int)(mysqli_fetch_assoc($resPns)['total'] ?? 0);
$totalPppk = (int)(mysqli_fetch_assoc($resPppk)['total'] ?? 0);
$totalPegawai = $totalPns + $totalPppk;

$cutiConditions = [];
if ($isRestricted) $cutiConditions[] = "p.unit_kerja_id = $unitId";
if ($selectedYear !== 'ALL') {
    $escYr = mysqli_real_escape_string($conn, $selectedYear);
    $cutiConditions[] = "c.tanggal_mulai LIKE '$escYr%'";
}
$cutiWhere = count($cutiConditions) ? ('WHERE ' . implode(' AND ', $cutiConditions)) : '';

$resStats = mysqli_query($conn, "SELECT
    COUNT(*) AS total_masuk,
    SUM(CASE WHEN c.status_akhir = 'Disetujui' THEN 1 ELSE 0 END) AS disetujui,
    SUM(CASE WHEN c.status_akhir LIKE 'Menunggu%' THEN 1 ELSE 0 END) AS menunggu,
    SUM(CASE WHEN c.status_akhir = 'Ditolak' THEN 1 ELSE 0 END) AS ditolak,
    SUM(CASE WHEN c.status_akhir IN ('Ditangguhkan', 'Perubahan') THEN 1 ELSE 0 END) AS ditangguhkan,
    SUM(CASE WHEN c.status_akhir = 'Disetujui' THEN c.lama_hari_cuti ELSE 0 END) AS hari_disetujui
    FROM cuti_requests c
    JOIN pegawai p ON c.pegawai_id = p.id
    $cutiWhere");
$statRow = mysqli_fetch_assoc($resStats);
$totalMasuk = (int)($statRow['total_masuk'] ?? 0);
$totalApproved = (int)($statRow['disetujui'] ?? 0);
$totalWaiting = (int)($statRow['menunggu'] ?? 0);
$totalRejected = (int)($statRow['ditolak'] ?? 0);
$totalDeferred = (int)($statRow['ditangguhkan'] ?? 0);
$totalRejectedDeferred = $totalRejected + $totalDeferred;
$totalHariDisetujui = (int)($statRow['hari_disetujui'] ?? 0);

// Unit Kerja Stats
$unitCond = $isRestricted ? "WHERE u.id = $unitId" : "WHERE LOWER(u.nama_unit) NOT LIKE '%kepala kantor%'";
$yearJoinCond = $selectedYear !== 'ALL' ? " AND c.tanggal_mulai LIKE '" . mysqli_real_escape_string($conn, $selectedYear) . "%'" : "";
$resUnitStats = mysqli_query($conn, "SELECT u.id, u.nama_unit, u.pimpinan, u.nip_pimpinan,
    COUNT(DISTINCT p.id) AS jml_pegawai,
    COUNT(c.id) AS jml_usulan,
    SUM(CASE WHEN c.status_akhir = 'Disetujui' THEN 1 ELSE 0 END) AS jml_disetujui,
    SUM(CASE WHEN c.status_akhir IN ('Ditolak','Ditangguhkan','Perubahan') THEN 1 ELSE 0 END) AS jml_ditolak
    FROM unit_kerja u
    LEFT JOIN pegawai p ON p.unit_kerja_id = u.id
    LEFT JOIN cuti_requests c ON c.pegawai_id = p.id $yearJoinCond
    $unitCond
    GROUP BY u.id, u.nama_unit, u.pimpinan, u.nip_pimpinan
    ORDER BY u.id ASC");
$unitStats = [];
$maxUnitTotal = 1;
if ($resUnitStats) {
    while ($uRow = mysqli_fetch_assoc($resUnitStats)) {
        $unitStats[] = $uRow;
        if ((int)$uRow['jml_usulan'] > $maxUnitTotal) $maxUnitTotal = (int)$uRow['jml_usulan'];
    }
}

// Annual Stats
$annualStats = [];
$maxAnnual = 1;
foreach (array_reverse($availableYears) as $yr) {
    $escYr = mysqli_real_escape_string($conn, $yr);
    $yrWhere = "WHERE c.tanggal_mulai LIKE '$escYr%'" . ($isRestricted ? " AND p.unit_kerja_id = $unitId" : "");
    $rYr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        COUNT(*) AS masuk,
        SUM(CASE WHEN c.status_akhir='Disetujui' THEN 1 ELSE 0 END) AS disetujui,
        SUM(CASE WHEN c.status_akhir IN ('Ditolak','Ditangguhkan','Perubahan') THEN 1 ELSE 0 END) AS ditolak
        FROM cuti_requests c JOIN pegawai p ON c.pegawai_id = p.id $yrWhere"));
    $masuk = (int)($rYr['masuk'] ?? 0);
    if ($masuk > $maxAnnual) $maxAnnual = $masuk;
    $annualStats[] = ['year' => $yr, 'masuk' => $masuk, 'disetujui' => (int)($rYr['disetujui'] ?? 0), 'ditolak' => (int)($rYr['ditolak'] ?? 0)];
}

// Jenis Cuti Stats
$jenisMaster = ['Cuti Tahunan', 'Cuti Besar', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Karena Alasan Penting', 'CLTN (Cuti Luar Tanggungan Negara)', 'Cuti Bersama'];
$jenisMap = [];
foreach ($jenisMaster as $jm) $jenisMap[$jm] = ['jumlah' => 0, 'hari' => 0];
$resJenis = mysqli_query($conn, "SELECT c.jenis_cuti, COUNT(*) AS jml, SUM(c.lama_hari_cuti) AS hari
    FROM cuti_requests c JOIN pegawai p ON c.pegawai_id = p.id $cutiWhere GROUP BY c.jenis_cuti");
$maxJenis = 1;
if ($resJenis) {
    while ($jr = mysqli_fetch_assoc($resJenis)) {
        $jenisMap[$jr['jenis_cuti']] = ['jumlah' => (int)$jr['jml'], 'hari' => (int)$jr['hari']];
        if ((int)$jr['jml'] > $maxJenis) $maxJenis = (int)$jr['jml'];
    }
}

$yearLabel = $selectedYear === 'ALL' ? 'Semua Tahun' : 'Tahun ' . $selectedYear;
include 'layout_header.php';
?>
<div class="space-y-6">
    <div class="bg-gradient-to-r from-[#005a30] via-[#006838] to-emerald-800 text-white p-6 rounded-2xl shadow-lg border-b-4 border-[#D4AF37]">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h2 class="text-2xl font-extrabold">Sistem Informasi Cuti ASN Terintegrasi Kemenag Lebak (SICANTIK)</h2>
                <p class="text-xs text-emerald-100 mt-1">Selamat datang, <b><?= e($currentUser['nama_lengkap']) ?></b> (Role: <span class="uppercase font-bold text-[#D4AF37]"><?= e($currentUser['role']) ?></span>)</p>
            </div>
            <a href="form_cuti.php" class="bg-[#D4AF37] hover:bg-amber-500 text-gray-900 px-4 py-2.5 rounded-xl text-sm font-bold shadow shrink-0">+ Ajukan Cuti Baru</a>
        </div>
        <div class="mt-4 pt-4 border-t border-emerald-600/50 flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="font-semibold text-emerald-100">Filter Tahun Rekapitulasi: <b class="text-[#D4AF37]"><?= e($yearLabel) ?></b></span>
            <div class="flex flex-wrap gap-1.5">
                <a href="index.php?tahun=ALL" class="px-3 py-1.5 rounded-lg font-bold <?= $selectedYear === 'ALL' ? 'bg-[#D4AF37] text-gray-900' : 'bg-emerald-900/70 text-emerald-100' ?>">Semua Tahun</a>
                <?php foreach ($availableYears as $yr): ?>
                    <a href="index.php?tahun=<?= e($yr) ?>" class="px-3 py-1.5 rounded-lg font-bold <?= $selectedYear === $yr ? 'bg-[#D4AF37] text-gray-900' : 'bg-emerald-900/70 text-emerald-100' ?>"><?= e($yr) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 border-l-4 border-l-[#006838]">
            <div class="text-xs font-bold uppercase text-gray-500">Rekap Pegawai ASN</div>
            <div class="text-3xl font-extrabold text-[#006838] mt-1"><?= $totalPegawai ?></div>
            <div class="text-xs text-gray-600 mt-1">PNS: <b><?= $totalPns ?></b> | PPPK: <b><?= $totalPppk ?></b></div>
        </div>
        <div id="kpiUsulanMasuk" class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 border-l-4 border-l-blue-600<?= $totalWaiting > 0 && in_array($currentUser['role'], ['superadmin', 'admin'], true) ? ' waiting-alert' : '' ?>">
            <div class="text-xs font-bold uppercase text-gray-500">Usulan Masuk (<?= e($selectedYear) ?>)</div>
            <div class="text-3xl font-extrabold text-blue-600 mt-1"><?= $totalMasuk ?></div>
            <div class="text-xs text-amber-700 mt-1"><?= $totalWaiting ?> menunggu proses</div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 border-l-4 border-l-emerald-600">
            <div class="text-xs font-bold uppercase text-gray-500">Cuti Disetujui</div>
            <div class="text-3xl font-extrabold text-emerald-700 mt-1"><?= $totalApproved ?></div>
            <div class="text-xs text-emerald-700 mt-1">Total <?= $totalHariDisetujui ?> hari kerja</div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 border-l-4 border-l-rose-600">
            <div class="text-xs font-bold uppercase text-gray-500">Ditolak / Ditangguhkan</div>
            <div class="text-3xl font-extrabold text-rose-600 mt-1"><?= $totalRejectedDeferred ?></div>
            <div class="text-xs text-rose-700 mt-1">Ditolak: <?= $totalRejected ?> | Tangguh: <?= $totalDeferred ?></div>
        </div>
    </div>

    <!-- Grafik Donut Proporsi -->
    <?php
        $r = 52; $circ = 2 * M_PI * $r;
        $pnsLen = $totalPegawai > 0 ? ($totalPns / $totalPegawai) * $circ : 0;
        $pppkLen = $totalPegawai > 0 ? ($totalPppk / $totalPegawai) * $circ : 0;

        $appLen = $totalMasuk > 0 ? ($totalApproved / $totalMasuk) * $circ : 0;
        $waitLen = $totalMasuk > 0 ? ($totalWaiting / $totalMasuk) * $circ : 0;
        $rejLen = $totalMasuk > 0 ? ($totalRejectedDeferred / $totalMasuk) * $circ : 0;
    ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="font-bold text-gray-900 mb-4">Grafik Donut Proporsi Pegawai (PNS vs PPPK)</h3>
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative w-36 h-36 shrink-0 flex items-center justify-center">
                    <svg width="144" height="144" viewBox="0 0 144 144" class="-rotate-90">
                        <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#F3F4F6" stroke-width="20"/>
                        <?php if ($totalPegawai > 0): ?>
                            <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#006838" stroke-width="20" stroke-dasharray="<?= $pnsLen ?> <?= $circ - $pnsLen ?>" stroke-dashoffset="0"/>
                            <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#D4AF37" stroke-width="20" stroke-dasharray="<?= $pppkLen ?> <?= $circ - $pppkLen ?>" stroke-dashoffset="-<?= $pnsLen ?>"/>
                        <?php endif; ?>
                    </svg>
                    <div class="absolute text-center"><div class="text-2xl font-extrabold"><?= $totalPegawai ?></div><div class="text-[10px] text-gray-500 uppercase">ASN</div></div>
                </div>
                <div class="flex-1 w-full space-y-2 text-xs">
                    <div class="flex justify-between p-2.5 bg-emerald-50 rounded-xl"><span>PNS</span><b><?= $totalPns ?> (<?= $totalPegawai > 0 ? round(($totalPns/$totalPegawai)*100) : 0 ?>%)</b></div>
                    <div class="flex justify-between p-2.5 bg-amber-50 rounded-xl"><span>PPPK</span><b><?= $totalPppk ?> (<?= $totalPegawai > 0 ? round(($totalPppk/$totalPegawai)*100) : 0 ?>%)</b></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
            <h3 class="font-bold text-gray-900 mb-4">Grafik Donut Proporsi Status Usulan (<?= e($yearLabel) ?>)</h3>
            <div class="flex flex-col sm:flex-row items-center gap-6">
                <div class="relative w-36 h-36 shrink-0 flex items-center justify-center">
                    <svg width="144" height="144" viewBox="0 0 144 144" class="-rotate-90">
                        <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#F3F4F6" stroke-width="20"/>
                        <?php if ($totalMasuk > 0): ?>
                            <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#059669" stroke-width="20" stroke-dasharray="<?= $appLen ?> <?= $circ - $appLen ?>" stroke-dashoffset="0"/>
                            <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#2563EB" stroke-width="20" stroke-dasharray="<?= $waitLen ?> <?= $circ - $waitLen ?>" stroke-dashoffset="-<?= $appLen ?>"/>
                            <circle cx="72" cy="72" r="<?= $r ?>" fill="transparent" stroke="#E11D48" stroke-width="20" stroke-dasharray="<?= $rejLen ?> <?= $circ - $rejLen ?>" stroke-dashoffset="-<?= ($appLen + $waitLen) ?>"/>
                        <?php endif; ?>
                    </svg>
                    <div class="absolute text-center"><div class="text-2xl font-extrabold"><?= $totalMasuk ?></div><div class="text-[10px] text-gray-500 uppercase">Usulan</div></div>
                </div>
                <div class="flex-1 w-full space-y-2 text-xs">
                    <div class="flex justify-between p-2 bg-emerald-50 rounded-xl"><span>Disetujui</span><b><?= $totalApproved ?></b></div>
                    <div class="flex justify-between p-2 bg-blue-50 rounded-xl"><span>Menunggu Proses</span><b><?= $totalWaiting ?></b></div>
                    <div class="flex justify-between p-2 bg-rose-50 rounded-xl"><span>Ditolak / Ditangguhkan</span><b><?= $totalRejectedDeferred ?></b></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagram Batang Unit Kerja & Rekap Tahunan -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-3">
            <h3 class="font-bold text-gray-900">Diagram Batang Usulan Per Unit Kerja (<?= e($yearLabel) ?>)</h3>
            <?php foreach ($unitStats as $us): $pct = $us['jml_usulan'] > 0 ? max(8, round(((int)$us['jml_usulan'] / $maxUnitTotal) * 100)) : 0; ?>
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between"><span class="font-semibold"><?= e($us['nama_unit']) ?></span><b class="text-[#006838]"><?= (int)$us['jml_usulan'] ?> usulan (Disetujui: <?= (int)$us['jml_disetujui'] ?>)</b></div>
                    <div class="w-full h-3.5 bg-gray-100 rounded-full overflow-hidden">
                        <?php if ($pct > 0): ?><div class="h-full bg-[#006838] rounded-full" style="width: <?= $pct ?>%"></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-4">
            <h3 class="font-bold text-gray-900">Rekapitulasi Usulan Cuti Per Tahun</h3>
            <table class="w-full text-xs text-left">
                <thead class="bg-emerald-50 text-[#006838] uppercase">
                    <tr><th class="px-3 py-2">Tahun</th><th class="px-3 py-2 text-center">Masuk</th><th class="px-3 py-2 text-center">Disetujui</th><th class="px-3 py-2 text-center">Ditolak/Tangguh</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($annualStats as $as): ?>
                        <tr>
                            <td class="px-3 py-2 font-bold"><?= e($as['year']) ?></td>
                            <td class="px-3 py-2 text-center font-bold text-blue-700"><?= $as['masuk'] ?></td>
                            <td class="px-3 py-2 text-center font-bold text-emerald-700"><?= $as['disetujui'] ?></td>
                            <td class="px-3 py-2 text-center font-bold text-rose-700"><?= $as['ditolak'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Informasi Jumlah Cuti Per Jenis Cuti -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h3 class="font-bold text-gray-900 mb-4">Informasi &amp; Diagram Batang Jumlah Cuti Per Jenis Cuti (<?= e($yearLabel) ?>)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($jenisMap as $jName => $jData): $pct = $jData['jumlah'] > 0 ? max(8, round(($jData['jumlah'] / $maxJenis) * 100)) : 0; ?>
                <div class="p-3 rounded-xl border border-gray-200 bg-gray-50/50 space-y-1.5 text-xs">
                    <div class="flex justify-between font-bold"><span><?= e($jName) ?></span><span class="text-[#006838]"><?= $jData['jumlah'] ?> usulan (<?= $jData['hari'] ?> hari)</span></div>
                    <div class="w-full h-2.5 bg-gray-200 rounded-full overflow-hidden">
                        <?php if ($pct > 0): ?><div class="h-full bg-emerald-600 rounded-full" style="width: <?= $pct ?>%"></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    @keyframes waitingBlink {
        0%, 100% {
            background-color: #ffffff;
            border-color: #d1d5db;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        50% {
            background-color: #fee2e2;
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.18), 0 10px 20px rgba(185, 28, 28, 0.18);
        }
    }

    #kpiUsulanMasuk.waiting-alert {
        border-width: 2px;
        border-left-width: 4px;
        animation: waitingBlink 1s ease-in-out infinite;
    }
</style>

<?php if ($totalWaiting > 0 && in_array($currentUser['role'], ['superadmin', 'admin'], true)): ?>
<button type="button" id="enableWaitingAlarm" class="fixed bottom-5 right-5 z-[60] hidden items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-xl">
    <span class="text-base" aria-hidden="true">&#128276;</span>
    Aktifkan Bunyi Alarm
</button>

<script>
(function () {
    let audioContext = null;
    let alarmTimer = null;
    let alarmStarted = false;
    const alarmButton = document.getElementById('enableWaitingAlarm');

    function playAlarmBeep() {
        if (!audioContext || audioContext.state !== 'running') return;

        const now = audioContext.currentTime;
        [0, 0.22].forEach(function (offset) {
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();

            oscillator.type = 'square';
            oscillator.frequency.setValueAtTime(880, now + offset);
            oscillator.frequency.setValueAtTime(660, now + offset + 0.12);

            gain.gain.setValueAtTime(0.0001, now + offset);
            gain.gain.exponentialRampToValueAtTime(0.12, now + offset + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + offset + 0.18);

            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start(now + offset);
            oscillator.stop(now + offset + 0.19);
        });
    }

    async function startWaitingAlarm() {
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;

            if (!audioContext) audioContext = new AudioContextClass();
            if (audioContext.state === 'suspended') await audioContext.resume();
            if (audioContext.state !== 'running') throw new Error('AudioContext blocked');

            if (!alarmStarted) {
                alarmStarted = true;
                playAlarmBeep();
                alarmTimer = window.setInterval(playAlarmBeep, 1800);
            }

            alarmButton.classList.add('hidden');
            alarmButton.classList.remove('flex');
        } catch (error) {
            alarmButton.classList.remove('hidden');
            alarmButton.classList.add('flex');
        }
    }

    alarmButton.addEventListener('click', function (event) {
        event.stopPropagation();
        startWaitingAlarm();
    });

    ['pointerdown', 'keydown'].forEach(function (eventName) {
        window.addEventListener(eventName, function () {
            if (!alarmStarted) startWaitingAlarm();
        }, { once: true });
    });

    startWaitingAlarm();
})();
</script>
<?php endif; ?>
<?php include 'layout_footer.php'; ?>
