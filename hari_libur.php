<?php
require_once 'koneksi.php';
$currentUser = requireRole(['superadmin']);
$activePage = 'hari_libur';

$notice = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $tgl = $_POST['tanggal'] ?? '';
        $ket = trim($_POST['keterangan'] ?? '');
        if ($tgl && $ket) {
            $stmt = mysqli_prepare($conn, "INSERT INTO hari_libur (tanggal, keterangan) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $tgl, $ket);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Hari libur berhasil ditambahkan.';
            } else {
                $error = 'Gagal menambahkan hari libur.';
            }
        } else {
            $error = 'Tanggal dan keterangan hari besar wajib diisi.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM hari_libur WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Hari libur berhasil dihapus.';
            } else {
                $error = 'Gagal menghapus hari libur.';
            }
        }
    }
}

$holidays = mysqli_query($conn, "SELECT * FROM hari_libur ORDER BY tanggal ASC");
include 'layout_header.php';
?>
<div class="space-y-6">
    <!-- Header Halaman -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-[#006838] shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Manajemen Tanggal Hari Libur Nasional &amp; Hari Besar</h2>
                <p class="text-sm text-gray-600 mt-1">Hari libur nasional tidak dihitung dalam akumulasi hari kerja aktif pengajuan cuti.</p>
            </div>
        </div>
        <button type="button" onclick="openHolidayModal()" class="inline-flex items-center gap-1.5 bg-[#006838] hover:bg-emerald-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition shrink-0">
            <span>+ Tambah Hari Libur</span>
        </button>
    </div>

    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Tabel Hari Libur -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-emerald-50/80 text-[#006838] uppercase text-xs font-bold">
                    <tr>
                        <th class="px-5 py-3.5 text-center w-16">NO</th>
                        <th class="px-5 py-3.5 w-48">TANGGAL</th>
                        <th class="px-5 py-3.5">KETERANGAN HARI LIBUR / HARI BESAR</th>
                        <th class="px-5 py-3.5 text-center w-24">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $no = 1; while ($h = mysqli_fetch_assoc($holidays)): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3.5 text-center font-medium"><?= $no++ ?></td>
                            <td class="px-5 py-3.5 font-mono text-xs sm:text-sm font-medium text-gray-800 whitespace-nowrap"><?= formatDateIndo($h['tanggal']) ?></td>
                            <td class="px-5 py-3.5 font-semibold text-[#006838]"><?= e($h['keterangan']) ?></td>
                            <td class="px-5 py-3.5 text-center">
                                <form method="POST" class="inline" onsubmit="return confirm('Hapus hari libur ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
                                    <button type="submit" title="Hapus" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Hari Libur Nasional -->
    <div id="holidayModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-[1px] p-4" onclick="if(event.target === this) closeHolidayModal()">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl overflow-hidden">
            <!-- Header Hijau dengan Aksen Garis Bawah Kuning -->
            <div class="bg-[#006838] px-6 py-4 flex items-center justify-between border-b-2 border-amber-400">
                <h3 class="text-white font-bold text-base">Tambah Hari Libur Nasional</h3>
                <button type="button" onclick="closeHolidayModal()" aria-label="Tutup" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body Form Modal -->
            <form method="POST" action="hari_libur.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="add">

                <div>
                    <label for="holiday_tanggal" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">TANGGAL</label>
                    <input
                        type="date"
                        id="holiday_tanggal"
                        name="tanggal"
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <div>
                    <label for="holiday_keterangan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">KETERANGAN HARI BESAR</label>
                    <input
                        type="text"
                        id="holiday_keterangan"
                        name="keterangan"
                        required
                        placeholder="Contoh: Hari Raya Idul Fitri"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <!-- Footer Tombol Batal & Simpan -->
                <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onclick="closeHolidayModal()"
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
                        <span>Simpan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openHolidayModal() {
        const modal = document.getElementById('holidayModal');
        document.getElementById('holiday_tanggal').value = '';
        document.getElementById('holiday_keterangan').value = '';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => document.getElementById('holiday_tanggal').focus(), 50);
    }

    function closeHolidayModal() {
        const modal = document.getElementById('holidayModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeHolidayModal();
        }
    });
    </script>
</div>
<?php include 'layout_footer.php'; ?>