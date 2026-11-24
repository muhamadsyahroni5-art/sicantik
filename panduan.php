<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'panduan';
$role = $currentUser['role'];

include 'layout_header.php';
?>
<div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Panduan Penggunaan Aplikasi SICANTIK</h2>
            <p class="text-sm text-gray-600 mt-1">Petunjuk teknis penggunaan Sistem Informasi Cuti ASN Terpadu Kantor Kementerian Agama Kabupaten Lebak.</p>
        </div>
        <button type="button" onclick="openHelpdeskModal()" class="bg-[#006838] hover:bg-emerald-900 text-white font-semibold px-4 py-2.5 rounded-xl text-xs shadow transition shrink-0">
            Layanan Kontak Helpdesk
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <h3 class="text-base font-bold text-[#006838] mb-4">1. Alur Proses Pengajuan &amp; Verifikasi Cuti</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-lg bg-[#006838] text-[#D4AF37] font-mono font-bold">LANGKAH 01</span>
                <div class="font-bold text-gray-900 text-sm">Input Usulan &amp; Upload PDF</div>
                <p class="text-gray-600 leading-relaxed">Pilih pegawai, jenis cuti, tanggal mulai–selesai (hari kerja otomatis melewati akhir pekan &amp; hari libur), serta unggah surat permohonan PDF.</p>
            </div>
            <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-lg bg-[#006838] text-[#D4AF37] font-mono font-bold">LANGKAH 02</span>
                <div class="font-bold text-gray-900 text-sm">Verifikasi Atasan Langsung</div>
                <p class="text-gray-600 leading-relaxed">Admin Unit Kerja / Kasubbag TU memeriksa usulan dan menentukan pertimbangan atasan langsung.</p>
            </div>
            <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-lg bg-[#006838] text-[#D4AF37] font-mono font-bold">LANGKAH 03</span>
                <div class="font-bold text-gray-900 text-sm">Keputusan Kepala Kantor</div>
                <p class="text-gray-600 leading-relaxed">Super Admin memproses persetujuan akhir Kepala Kantor &amp; nomor surat. Bila disetujui, kuota cuti tahunan terpotong otomatis.</p>
            </div>
            <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 space-y-2">
                <span class="inline-block px-2.5 py-0.5 rounded-lg bg-[#006838] text-[#D4AF37] font-mono font-bold">LANGKAH 04</span>
                <div class="font-bold text-gray-900 text-sm">Cetak Form &amp; Kirim WA</div>
                <p class="text-gray-600 leading-relaxed">Setelah berstatus Disetujui, cetak Form Anak Lampiran 1.b (1 halaman A4) dan kirim notifikasi WhatsApp.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 text-xs">
        <div class="bg-white p-5 rounded-2xl border <?= $role === 'operator' ? 'border-[#006838] ring-2 ring-[#006838]/20' : 'border-gray-200' ?>">
            <div class="font-bold text-sm text-[#006838] mb-2">Role: Operator Unit Kerja</div>
            <ul class="list-disc list-inside space-y-1.5 text-gray-700 leading-relaxed">
                <li>Input usulan cuti pegawai pada unit kerja masing-masing.</li>
                <li>Monitoring usulan cuti &amp; Preview PDF surat permohonan.</li>
                <li>Cetak Form Resmi hanya aktif ketika status akhir <b>Disetujui</b>.</li>
                <li>Rekapitulasi unit kerja ditandatangani oleh Pimpinan Unit Kerja masing-masing.</li>
            </ul>
        </div>
        <div class="bg-white p-5 rounded-2xl border <?= $role === 'admin' ? 'border-blue-600 ring-2 ring-blue-600/20' : 'border-gray-200' ?>">
            <div class="font-bold text-sm text-blue-800 mb-2">Role: Admin / Kasubbag TU</div>
            <ul class="list-disc list-inside space-y-1.5 text-gray-700 leading-relaxed">
                <li>Verifikasi Atasan Langsung terhadap usulan cuti masuk.</li>
                <li>Kelola data Unit Kerja, User, dan template pesan WhatsApp.</li>
                <li>Cetak Rekapitulasi Usulan Cuti unit kerjanya lengkap dengan Kop Surat Resmi.</li>
            </ul>
        </div>
        <div class="bg-white p-5 rounded-2xl border <?= $role === 'superadmin' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-gray-200' ?>">
            <div class="font-bold text-sm text-amber-800 mb-2">Role: Super Admin / Kepegawaian</div>
            <ul class="list-disc list-inside space-y-1.5 text-gray-700 leading-relaxed">
                <li>Kelola seluruh Unit Kerja, Pegawai &amp; Sisa Cuti, serta Hari Libur Nasional.</li>
                <li>Eksekusi Reset Cuti Tahunan PNS &amp; PPPK awal tahun dengan rumus seragam.</li>
                <li>Verifikasi Kepala Kantor, penomoran surat, dan manajemen seluruh user.</li>
            </ul>
        </div>
    </div>

    <div class="bg-gradient-to-r from-[#005a30] via-[#006838] to-[#004f2a] text-white p-6 rounded-2xl shadow border-b-4 border-[#D4AF37] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="font-bold text-base">Butuh Bantuan Teknis atau Kendala Akun?</h3>
            <p class="text-xs text-emerald-100 mt-0.5">Hubungi Tim Pranata Komputer &amp; Statistisi Kantor Kementerian Agama Kabupaten Lebak.</p>
        </div>
        <button type="button" onclick="openHelpdeskModal()" class="px-4 py-2.5 bg-[#D4AF37] hover:bg-amber-500 text-gray-900 font-bold text-xs rounded-xl shadow transition shrink-0">
            Buka Helpdesk &amp; Credit
        </button>
    </div>
</div>
<?php include 'layout_footer.php'; ?>
