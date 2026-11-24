<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'form_cuti';

$error = '';
$isOperator = ($currentUser['role'] === 'operator');
$userUnitId = (int)$currentUser['unit_kerja_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pegawaiId = (int)($_POST['pegawai_id'] ?? 0);
    $noWa = trim($_POST['no_wa'] ?? '');
    $jenisCuti = trim($_POST['jenis_cuti'] ?? '');
    $tglMulai = $_POST['tanggal_mulai'] ?? '';
    $tglSelesai = $_POST['tanggal_selesai'] ?? '';
    $alasan = trim($_POST['alasan'] ?? '');
    $alamat = trim($_POST['alamat_selama_cuti'] ?? '');

    $peg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM pegawai WHERE id = $pegawaiId"));
    if ($pegawaiId <= 0 || !$peg) {
        $error = 'Silakan pilih data pegawai terlebih dahulu.';
    } elseif ($isOperator && (int)$peg['unit_kerja_id'] !== $userUnitId) {
        $error = 'Anda hanya dapat mengajukan cuti untuk pegawai di unit kerja Anda.';
    } else {
        $exp = hitungMasaKerja((int)$peg['bulan_mulai_kerja'], (int)$peg['tahun_mulai_kerja']);
        $lamaHari = hitungHariKerja($tglMulai, $tglSelesai, $conn);
        $totalSct = (int)$peg['sctn2'] + (int)$peg['sctn1'] + (int)$peg['sctn'];

        if (!$exp['isValidForLeave']) {
            $error = 'Pegawai dengan masa kerja kurang dari 1 tahun belum berhak mengajukan cuti.';
        } elseif ($lamaHari <= 0) {
            $error = 'Tanggal cuti tidak valid atau lama hari kerja = 0.';
        } elseif ($jenisCuti === 'Cuti Tahunan' && $lamaHari > $totalSct) {
            $error = "Sisa Cuti Tahunan tidak mencukupi ($totalSct hari tersedia, usulan $lamaHari hari).";
        } else {
            if ($noWa !== '') {
                $stmtWa = mysqli_prepare($conn, "UPDATE pegawai SET no_wa = ? WHERE id = ?");
                if ($stmtWa) {
                    mysqli_stmt_bind_param($stmtWa, 'si', $noWa, $pegawaiId);
                    mysqli_stmt_execute($stmtWa);
                }
            }

            $dokumenName = 'Surat_Permohonan_Cuti.pdf';
            if (isset($_FILES['dokumen_pdf']) && $_FILES['dokumen_pdf']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['dokumen_pdf']['name'], PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    $safeName = 'permohonan_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['dokumen_pdf']['name']);
                    if (move_uploaded_file($_FILES['dokumen_pdf']['tmp_name'], __DIR__ . '/uploads/' . $safeName)) {
                        $dokumenName = $safeName;
                    }
                }
            }

            $tglPengajuan = date('Y-m-d');
            $stmt = mysqli_prepare($conn, "INSERT INTO cuti_requests (pegawai_id, jenis_cuti, tanggal_mulai, tanggal_selesai, lama_hari_cuti, alasan, alamat_selama_cuti, dokumen_pdf, tanggal_pengajuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isssissss', $pegawaiId, $jenisCuti, $tglMulai, $tglSelesai, $lamaHari, $alasan, $alamat, $dokumenName, $tglPengajuan);
            if (mysqli_stmt_execute($stmt)) {
                header('Location: usulan_cuti.php?submitted=1');
                exit;
            } else {
                $error = 'Gagal menyimpan usulan cuti ke database MySQL.';
            }
        }
    }
}

$pegQuery = "SELECT p.*, u.nama_unit, u.pimpinan, u.nip_pimpinan, u.jabatan_pimpinan FROM pegawai p LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id";
if ($isOperator) {
    $pegQuery .= " WHERE p.unit_kerja_id = $userUnitId";
}
$pegQuery .= " ORDER BY p.nama_lengkap ASC";
$pegRes = mysqli_query($conn, $pegQuery);
$pegawaiData = [];
while ($r = mysqli_fetch_assoc($pegRes)) {
    $r['exp'] = hitungMasaKerja((int)$r['bulan_mulai_kerja'], (int)$r['tahun_mulai_kerja']);
    $r['no_wa_auto'] = $r['no_wa'] ?? ($r['no_hp'] ?? ($r['telepon'] ?? ($r['hp'] ?? '')));
    $pegawaiData[] = $r;
}

$holRes = mysqli_query($conn, "SELECT tanggal FROM hari_libur");
$holidayDates = [];
while ($h = mysqli_fetch_assoc($holRes)) $holidayDates[] = $h['tanggal'];

include 'layout_header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Formulir Pengajuan Usulan Cuti Pegawai</h2>
        <p class="text-sm text-gray-600 mt-1">Pengajuan cuti PNS (Perka BKN No. 24 Tahun 2017) & PPPK (PP No. 49 Tahun 2018).</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-6">
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="relative">
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Cari Pegawai (Nama / NIP)</label>
                <input type="hidden" name="pegawai_id" id="pegawaiIdInput" value="">
                <div class="relative">
                    <input
                        type="text"
                        id="pegawaiSearchInput"
                        autocomplete="off"
                        value=""
                        placeholder="Ketik nama atau NIP pegawai..."
                        class="w-full px-4 py-2.5 pr-9 rounded-xl border border-gray-300 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#006838] focus:border-[#006838]"
                    >
                    <button
                        type="button"
                        id="clearPegawaiSearch"
                        title="Bersihkan pencarian"
                        class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-xs font-bold px-1.5 py-0.5"
                    >&times;</button>
                </div>

                <!-- List Hasil Pencarian -->
                <div
                    id="pegawaiSearchResults"
                    class="hidden absolute z-30 mt-1 w-full max-h-60 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl divide-y divide-gray-100"
                ></div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">No. WA</label>
                <input
                    type="text"
                    name="no_wa"
                    id="noWaView"
                    value=""
                    placeholder="Otomatis dari data pegawai..."
                    class="w-full px-4 py-2.5 rounded-xl border border-gray-300 bg-gray-50 text-sm font-mono text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#006838]"
                >
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Status Kepegawaian &amp; Masa Kerja</label>
                <input type="text" id="statusMasaKerja" value="" readonly class="w-full px-4 py-2.5 rounded-xl border bg-gray-50 text-sm font-bold text-emerald-800">
            </div>
        </div>

        <!-- II. Unit Kerja & Atasan Langsung (Otomatis) -->
        <div class="border-t pt-4">
            <h3 class="text-xs font-bold uppercase text-[#006838] mb-3">II. Unit Kerja &amp; Atasan Langsung (Otomatis)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Unit Kerja</label>
                    <input type="text" id="unitKerjaView" value="" readonly class="w-full px-3 py-2 rounded-xl border bg-gray-50 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Nama &amp; NIP Atasan</label>
                    <input type="text" id="atasanNamaView" value="" readonly class="w-full px-3 py-2 rounded-xl border bg-gray-50 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Jabatan Atasan Langsung</label>
                    <input type="text" id="atasanJabatanView" value="" readonly class="w-full px-3 py-2 rounded-xl border bg-gray-50 text-sm">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t pt-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Jenis Cuti</label>
                <select name="jenis_cuti" id="jenisCutiSelect" required class="w-full px-3 py-2.5 rounded-xl border text-sm bg-white">
                    <option value="">-- Pilih Jenis Cuti --</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" id="tglMulai" required class="w-full px-3 py-2 rounded-xl border text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" id="tglSelesai" required class="w-full px-3 py-2 rounded-xl border text-sm">
            </div>
        </div>

        <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-200 flex flex-wrap justify-between items-center text-xs">
            <div>Lama Hari Kerja (Senin-Jumat, di luar hari libur nasional): <b id="lamaHariView" class="text-sm text-[#006838]">0 Hari Kerja</b></div>
            <div>Total Sisa Cuti Tahunan (SCT): <b id="sisaSctView" class="text-sm text-amber-800"></b></div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Alasan Mengajukan Cuti</label>
            <textarea name="alasan" rows="3" required class="w-full px-4 py-2.5 rounded-xl border text-sm"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Alamat Selama Cuti</label>
                <input type="text" name="alamat_selama_cuti" required class="w-full px-4 py-2.5 rounded-xl border text-sm">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Upload Surat Permohonan (.PDF)</label>
                <input type="file" name="dokumen_pdf" accept=".pdf,application/pdf" class="w-full text-xs">
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t">
            <button type="submit" class="px-6 py-3 bg-[#006838] hover:bg-emerald-900 text-white font-bold rounded-xl text-sm shadow">
                Ajukan Usulan Cuti
            </button>
        </div>
    </form>
</div>

<script>
const pegawaiList = <?= json_encode($pegawaiData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const holidayDates = new Set(<?= json_encode($holidayDates) ?>);
const pnsJenis = ['Cuti Tahunan', 'Cuti Besar', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Karena Alasan Penting', 'CLTN (Cuti Luar Tanggungan Negara)'];
const pppkJenis = ['Cuti Tahunan', 'Cuti Sakit', 'Cuti Melahirkan', 'Cuti Bersama'];

const pegIdInput = document.getElementById('pegawaiIdInput');
const noWaView = document.getElementById('noWaView');
const jenisSelect = document.getElementById('jenisCutiSelect');
const searchInput = document.getElementById('pegawaiSearchInput');
const searchResults = document.getElementById('pegawaiSearchResults');
const clearSearchBtn = document.getElementById('clearPegawaiSearch');

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[ch]));
}

function clearPegawaiFields(clearSearch = false) {
    if (clearSearch) {
        searchInput.value = '';
        clearSearchBtn.classList.add('hidden');
    }
    pegIdInput.value = '';
    noWaView.value = '';
    document.getElementById('statusMasaKerja').value = '';
    document.getElementById('unitKerjaView').value = '';
    document.getElementById('atasanNamaView').value = '';
    document.getElementById('atasanJabatanView').value = '';
    document.getElementById('sisaSctView').textContent = '';
    jenisSelect.innerHTML = '<option value="">-- Pilih Jenis Cuti --</option>';
}

function renderSearchResults(query = '') {
    const q = query.trim().toLowerCase();
    const matches = q === ''
        ? pegawaiList.slice(0, 25)
        : pegawaiList.filter(p =>
            String(p.nama_lengkap || '').toLowerCase().includes(q) ||
            String(p.nip || '').toLowerCase().includes(q) ||
            String(p.nama_unit || '').toLowerCase().includes(q)
        );

    clearSearchBtn.classList.toggle('hidden', q === '');

    if (matches.length === 0) {
        searchResults.innerHTML = `<div class="px-4 py-3 text-xs text-gray-500">Pegawai dengan kata kunci "<b>${escapeHtml(query)}</b>" tidak ditemukan.</div>`;
        searchResults.classList.remove('hidden');
        return;
    }

    searchResults.innerHTML = matches.map(p => `
        <button
            type="button"
            data-pegawai-id="${escapeHtml(p.id)}"
            class="w-full text-left px-4 py-2.5 hover:bg-emerald-50 focus:bg-emerald-50 transition flex flex-col gap-0.5"
        >
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-bold text-gray-900">${escapeHtml(p.nama_lengkap)}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${p.status_pegawai === 'PPPK' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">${escapeHtml(p.status_pegawai)}</span>
            </div>
            <div class="text-[11px] font-mono text-gray-600">NIP. ${escapeHtml(p.nip)}</div>
            <div class="text-[11px] text-gray-500">${escapeHtml(p.nama_unit || '-')}</div>
        </button>
    `).join('');

    searchResults.classList.remove('hidden');
}

function selectPegawaiById(id) {
    const p = pegawaiList.find(item => String(item.id) === String(id));
    if (!p) {
        clearPegawaiFields(false);
        return;
    }

    pegIdInput.value = String(p.id);
    searchInput.value = `${p.nama_lengkap} - ${p.nip}`;
    noWaView.value = p.no_wa_auto || '';
    clearSearchBtn.classList.remove('hidden');
    searchResults.classList.add('hidden');

    document.getElementById('statusMasaKerja').value = `${p.status_pegawai} (${p.exp.years} Thn ${p.exp.months} Bln)`;
    document.getElementById('unitKerjaView').value = p.nama_unit || '-';
    document.getElementById('atasanNamaView').value = p.pimpinan ? `${p.pimpinan} (NIP. ${p.nip_pimpinan})` : '-';
    document.getElementById('atasanJabatanView').value = p.jabatan_pimpinan || '-';
    const total = Number(p.sctn2) + Number(p.sctn1) + Number(p.sctn);
    document.getElementById('sisaSctView').textContent = `${total} Hari (N-2:${p.sctn2} | N-1:${p.sctn1} | N:${p.sctn})`;

    const list = p.status_pegawai === 'PPPK' ? pppkJenis : pnsJenis;
    jenisSelect.innerHTML = '<option value="">-- Pilih Jenis Cuti --</option>' + list.map(j => `<option value="${escapeHtml(j)}">${escapeHtml(j)}</option>`).join('');
}

function hitungKerja() {
    const s = document.getElementById('tglMulai').value;
    const e = document.getElementById('tglSelesai').value;
    if (!s || !e) { document.getElementById('lamaHariView').textContent = '0 Hari Kerja'; return; }
    const start = new Date(s + 'T00:00:00');
    const end = new Date(e + 'T00:00:00');
    if (end < start) { document.getElementById('lamaHariView').textContent = '0 Hari Kerja'; return; }
    let count = 0;
    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const day = d.getDay();
        const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        if (day !== 0 && day !== 6 && !holidayDates.has(ymd)) count++;
    }
    document.getElementById('lamaHariView').textContent = `${count} Hari Kerja`;
}

searchInput.addEventListener('focus', () => renderSearchResults(searchInput.value));
searchInput.addEventListener('input', () => {
    pegIdInput.value = '';
    renderSearchResults(searchInput.value);
});

searchResults.addEventListener('click', (e) => {
    const itemBtn = e.target.closest('[data-pegawai-id]');
    if (!itemBtn) return;
    selectPegawaiById(itemBtn.getAttribute('data-pegawai-id'));
});

clearSearchBtn.addEventListener('click', () => {
    clearPegawaiFields(true);
    renderSearchResults('');
    searchInput.focus();
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('#pegawaiSearchInput') && !e.target.closest('#pegawaiSearchResults') && !e.target.closest('#clearPegawaiSearch')) {
        searchResults.classList.add('hidden');
    }
});

document.getElementById('tglMulai').addEventListener('change', hitungKerja);
document.getElementById('tglSelesai').addEventListener('change', hitungKerja);

clearPegawaiFields(true);
</script>
<?php include 'layout_footer.php'; ?>