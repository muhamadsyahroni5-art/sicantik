<?php
require_once 'koneksi.php';
$currentUser = requireRole(['superadmin', 'admin']);
$activePage = 'unit_kerja';

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentUser['role'] === 'superadmin') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $namaUnit = trim($_POST['nama_unit'] ?? '');
        $pimpinan = trim($_POST['pimpinan'] ?? '');
        $nipPimpinan = trim($_POST['nip_pimpinan'] ?? '');
        $jabatanPimpinan = trim($_POST['jabatan_pimpinan'] ?? '');

        if (!$namaUnit || !$pimpinan) {
            $error = 'Nama Unit Kerja dan Pimpinan wajib diisi!';
        } elseif ($id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE unit_kerja SET nama_unit=?, pimpinan=?, nip_pimpinan=?, jabatan_pimpinan=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $namaUnit, $pimpinan, $nipPimpinan, $jabatanPimpinan, $id);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Data unit kerja berhasil diperbarui.';
            } else {
                $error = 'Gagal memperbarui data unit kerja.';
            }
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO unit_kerja (nama_unit, pimpinan, nip_pimpinan, jabatan_pimpinan) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $namaUnit, $pimpinan, $nipPimpinan, $jabatanPimpinan);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Unit kerja baru berhasil ditambahkan.';
            } else {
                $error = 'Gagal menambahkan unit kerja baru.';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM unit_kerja WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Unit kerja berhasil dihapus.';
            } else {
                $error = 'Gagal menghapus unit kerja.';
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$editData = null;
if (isset($_GET['edit']) && $currentUser['role'] === 'superadmin') {
    $editId = (int)$_GET['edit'];
    $resEdit = mysqli_query($conn, "SELECT * FROM unit_kerja WHERE id = $editId");
    $editData = mysqli_fetch_assoc($resEdit);
}

$sql = "SELECT * FROM unit_kerja";
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $sql .= " WHERE nama_unit LIKE '%$esc%' OR pimpinan LIKE '%$esc%' OR jabatan_pimpinan LIKE '%$esc%' OR CAST(id AS CHAR) = '$esc'";
}
$sql .= " ORDER BY id ASC";
$unitsRes = mysqli_query($conn, $sql);
$unitRows = [];
while ($row = mysqli_fetch_assoc($unitsRes)) {
    $unitRows[] = $row;
}
$kepalaKantor = getKepalaKantor($conn);

$isModalOpen = ($currentUser['role'] === 'superadmin' && $editData !== null);

include 'layout_header.php';
?>
<style>
@media print {
    @page { size: A4 landscape; margin: 1cm; }
    body { background: #fff !important; }
}
</style>

<div class="space-y-6">
    <!-- Header Halaman -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-[#006838] shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 12v.01M9 15v.01M9 18v.01" />
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Manajemen Unit Kerja &amp; Pimpinan</h2>
                <p class="text-sm text-gray-600 mt-1">Pengelolaan data satuan kerja, ID unit kerja (MySQL), dan pejabat atasan / pimpinan di lingkungan Kantor Kementerian Agama Kabupaten Lebak.</p>
            </div>
        </div>
        <div class="flex items-center gap-2.5 shrink-0">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Data Unit Kerja
            </button>
            <?php if ($currentUser['role'] === 'superadmin'): ?>
                <button type="button" onclick="openUnitModal()" class="inline-flex items-center gap-1.5 bg-[#006838] hover:bg-emerald-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition">
                    <span>+ Tambah Unit Kerja</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium print:hidden"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium print:hidden"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Pencarian -->
    <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 flex items-center gap-3 print:hidden">
        <div class="relative flex-1">
            <svg class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari ID unit, nama unit kerja, atau pimpinan..." class="w-full pl-10 pr-4 py-2 rounded-xl border border-gray-300 text-sm focus:outline-none focus:border-[#006838]">
        </div>
        <button type="submit" class="px-4 py-2 bg-[#006838] hover:bg-emerald-900 text-white rounded-xl text-xs font-bold transition">Cari</button>
    </form>

    <!-- Tabel Interaktif Layar -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden print:hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-emerald-50/80 text-[#006838] uppercase text-xs font-bold">
                    <tr>
                        <th class="px-4 py-3.5 text-center">No</th>
                        <th class="px-4 py-3.5 text-center">ID Unit Kerja</th>
                        <th class="px-4 py-3.5">Nama Unit Kerja</th>
                        <th class="px-4 py-3.5">Nama Pimpinan / Pejabat</th>
                        <th class="px-4 py-3.5">NIP Pimpinan</th>
                        <th class="px-4 py-3.5">Jabatan Pimpinan</th>
                        <?php if ($currentUser['role'] === 'superadmin'): ?>
                            <th class="px-4 py-3.5 text-center">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($unitRows) === 0): ?>
                        <tr>
                            <td colspan="<?= $currentUser['role'] === 'superadmin' ? 7 : 6 ?>" class="px-4 py-8 text-center text-gray-500">
                                Tidak ada data unit kerja yang ditemukan.
                            </td>
                        </tr>
                    <?php else: $no = 1; foreach ($unitRows as $uk): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-center"><?= $no++ ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="inline-flex flex-col items-center justify-center">
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-50 border border-emerald-200 font-mono text-xs font-extrabold text-[#006838]">
                                        <?= (int)$uk['id'] ?>
                                    </span>
                                    <span class="text-[10px] text-gray-400 font-mono mt-0.5">uk-<?= (int)$uk['id'] ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-bold text-gray-900"><?= e($uk['nama_unit']) ?></td>
                            <td class="px-4 py-3"><?= e($uk['pimpinan']) ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= e($uk['nip_pimpinan']) ?></td>
                            <td class="px-4 py-3"><?= e($uk['jabatan_pimpinan']) ?></td>
                            <?php if ($currentUser['role'] === 'superadmin'): ?>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <button
                                        type="button"
                                        onclick='openUnitModal(<?= json_encode([
                                            "id" => (int)$uk["id"],
                                            "nama_unit" => $uk["nama_unit"],
                                            "pimpinan" => $uk["pimpinan"],
                                            "nip_pimpinan" => $uk["nip_pimpinan"],
                                            "jabatan_pimpinan" => $uk["jabatan_pimpinan"],
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                        title="Edit"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 transition mr-1"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Hapus unit kerja ini?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int)$uk['id'] ?>">
                                        <button type="submit" title="Hapus" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah / Edit Unit Kerja -->
    <?php if ($currentUser['role'] === 'superadmin'): ?>
    <div id="unitModal" class="fixed inset-0 z-50 <?= $isModalOpen ? 'flex' : 'hidden' ?> items-center justify-center bg-black/50 backdrop-blur-[1px] p-4 print:hidden" onclick="if(event.target === this) closeUnitModal()">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden animate-fadeIn">
            <!-- Header Modal Hijau dengan Aksen Garis Bawah Kuning -->
            <div class="bg-[#006838] px-6 py-4 flex items-center justify-between border-b-2 border-amber-400">
                <h3 id="unitModalTitle" class="text-white font-bold text-base">
                    <?= $editData ? 'Edit Unit Kerja' : 'Tambah Unit Kerja Baru' ?>
                </h3>
                <button type="button" onclick="closeUnitModal()" aria-label="Tutup" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body Form Modal -->
            <form method="POST" action="unit_kerja.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="unit_id" value="<?= (int)($editData['id'] ?? 0) ?>">

                <div>
                    <label for="nama_unit" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NAMA UNIT KERJA</label>
                    <input
                        type="text"
                        id="nama_unit"
                        name="nama_unit"
                        required
                        value="<?= e($editData['nama_unit'] ?? '') ?>"
                        placeholder="Contoh: KUA Kecamatan Rangkasbitung"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <div>
                    <label for="pimpinan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NAMA PIMPINAN / PEJABAT</label>
                    <input
                        type="text"
                        id="pimpinan"
                        name="pimpinan"
                        required
                        value="<?= e($editData['pimpinan'] ?? '') ?>"
                        placeholder="Contoh: Drs. H. Dede Sujana"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <div>
                    <label for="nip_pimpinan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NIP PIMPINAN</label>
                    <input
                        type="text"
                        id="nip_pimpinan"
                        name="nip_pimpinan"
                        required
                        value="<?= e($editData['nip_pimpinan'] ?? '') ?>"
                        placeholder="Contoh: 196811101994031005"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm font-mono text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <div>
                    <label for="jabatan_pimpinan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">JABATAN PIMPINAN</label>
                    <input
                        type="text"
                        id="jabatan_pimpinan"
                        name="jabatan_pimpinan"
                        required
                        value="<?= e($editData['jabatan_pimpinan'] ?? '') ?>"
                        placeholder="Contoh: Kepala KUA / Penghulu Madya"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <!-- Footer Tombol Batal & Simpan -->
                <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onclick="closeUnitModal()"
                        class="px-4 py-2 rounded-xl border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-bold transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#006838] hover:bg-emerald-900 text-white text-xs font-bold shadow-sm transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        <span id="unitSubmitLabel"><?= $editData ? 'Simpan Perubahan' : 'Simpan' ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openUnitModal(data = null) {
        const modal = document.getElementById('unitModal');
        const title = document.getElementById('unitModalTitle');
        const submitLabel = document.getElementById('unitSubmitLabel');

        if (data) {
            title.textContent = 'Edit Unit Kerja';
            submitLabel.textContent = 'Simpan Perubahan';
            document.getElementById('unit_id').value = data.id || 0;
            document.getElementById('nama_unit').value = data.nama_unit || '';
            document.getElementById('pimpinan').value = data.pimpinan || '';
            document.getElementById('nip_pimpinan').value = data.nip_pimpinan || '';
            document.getElementById('jabatan_pimpinan').value = data.jabatan_pimpinan || '';
        } else {
            title.textContent = 'Tambah Unit Kerja Baru';
            submitLabel.textContent = 'Simpan';
            document.getElementById('unit_id').value = 0;
            document.getElementById('nama_unit').value = '';
            document.getElementById('pimpinan').value = '';
            document.getElementById('nip_pimpinan').value = '';
            document.getElementById('jabatan_pimpinan').value = '';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => document.getElementById('nama_unit').focus(), 50);
    }

    function closeUnitModal() {
        const modal = document.getElementById('unitModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (window.location.search.includes('edit=')) {
            window.history.replaceState({}, '', 'unit_kerja.php');
        }
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeUnitModal();
        }
    });
    </script>
    <?php endif; ?>

    <!-- Format Cetak Resmi Kop Surat Kemenag Lebak -->
    <div class="hidden print:block text-black bg-white">
        <div class="text-center border-b-4 border-double border-black pb-3 mb-4" style="font-family: Arial, sans-serif;">
            <div class="font-bold uppercase" style="font-size: 14pt;">KEMENTERIAN AGAMA REPUBLIK INDONESIA</div>
            <div class="font-bold uppercase" style="font-size: 12pt;">KANTOR KEMENTERIAN AGAMA KABUPATEN LEBAK</div>
            <div style="font-size: 9pt;">Jl. Siliwangi No. 2, Kel. Muara Ciujung Timur Kec. Rangkasbitung, Kabupaten Lebak, Banten 42314</div>
            <div style="font-size: 9pt;">Telp: (0252) 201319 • E-mail: sekjen_416610@kemenag.go.id • site: https://kemenaglebak.com</div>
        </div>

        <div class="text-center mb-4">
            <h3 class="font-bold text-sm uppercase underline">DAFTAR UNIT KERJA DAN PEJABAT PIMPINAN</h3>
            <div class="text-xs mt-1">Referensi ID Unit Kerja Database SICANTIK Kemenag Kabupaten Lebak</div>
        </div>

        <table class="w-full border-collapse border border-black text-xs text-left">
            <thead>
                <tr class="bg-gray-100 text-center font-bold">
                    <th class="border border-black px-2 py-2 w-10">No</th>
                    <th class="border border-black px-2 py-2 w-24">ID Unit Kerja</th>
                    <th class="border border-black px-3 py-2">Nama Unit Kerja</th>
                    <th class="border border-black px-3 py-2">Nama Pimpinan / Pejabat</th>
                    <th class="border border-black px-3 py-2">NIP Pimpinan</th>
                    <th class="border border-black px-3 py-2">Jabatan Pimpinan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($unitRows) === 0): ?>
                    <tr><td colspan="6" class="border border-black text-center py-6">Tidak ada data unit kerja.</td></tr>
                <?php else: $no = 1; foreach ($unitRows as $uk): ?>
                    <tr>
                        <td class="border border-black px-2 py-1.5 text-center"><?= $no++ ?></td>
                        <td class="border border-black px-2 py-1.5 text-center font-mono font-bold"><?= (int)$uk['id'] ?></td>
                        <td class="border border-black px-3 py-1.5 font-bold"><?= e($uk['nama_unit']) ?></td>
                        <td class="border border-black px-3 py-1.5"><?= e($uk['pimpinan']) ?></td>
                        <td class="border border-black px-3 py-1.5 font-mono"><?= e($uk['nip_pimpinan']) ?></td>
                        <td class="border border-black px-3 py-1.5"><?= e($uk['jabatan_pimpinan']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="flex justify-between items-start mt-8 text-xs">
            <div>
                <div>Total Unit Kerja: <b><?= count($unitRows) ?></b> unit</div>
                <div>Dicetak pada: <?= formatDateIndo(date('Y-m-d')) ?></div>
            </div>
            <div class="text-center w-80">
                <div>Rangkasbitung, <?= formatDateIndo(date('Y-m-d')) ?></div>
                <div class="font-bold mt-1 uppercase leading-snug"><?= e($kepalaKantor['jabatan_pimpinan'] ?? 'KEPALA KANTOR KEMENTERIAN AGAMA KABUPATEN LEBAK') ?></div>
                <div class="h-16"></div>
                <div class="font-bold underline"><?= e($kepalaKantor['pimpinan'] ?? 'H. Badrusalam, S.Ag') ?></div>
                <div class="font-mono">NIP. <?= e($kepalaKantor['nip_pimpinan'] ?? '196901031991031005') ?></div>
            </div>
        </div>
    </div>
</div>
<?php include 'layout_footer.php'; ?>