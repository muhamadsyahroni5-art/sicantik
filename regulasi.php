<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'regulasi';
include 'layout_header.php';
?>
<div class="space-y-6">
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Aturan & Regulasi Cuti PNS dan PPPK</h2>
        <p class="text-sm text-gray-600 mt-1">Panduan pelaksanaan cuti ASN berdasarkan Perka BKN No. 24 Tahun 2017 (PNS) & PP No. 49 Tahun 2018 (PPPK).</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-3 text-sm text-gray-700">
            <h3 class="font-bold text-base text-[#006838] border-b pb-2">Ketentuan Cuti PNS (Perka BKN No. 24/2017)</h3>
            <ul class="list-disc list-inside space-y-1.5">
                <li><b>Cuti Tahunan:</b> Hak 12 hari kerja/tahun setelah bekerja minimal 1 tahun secara terus-menerus.</li>
                <li><b>Akumulasi Sisa Cuti:</b> Sisa cuti tahun N dapat dibawa ke N-1 maksimal 6 hari kerja, dan N-1 ke N-2 sehingga total maksimal hingga 24 hari kerja.</li>
                <li><b>Pemotongan FIFO:</b> Pengambilan cuti tahunan memotong kuota terlama dahulu (SCTN-2 &rarr; SCTN-1 &rarr; SCTN).</li>
                <li><b>Jenis Cuti Lain:</b> Cuti Besar, Cuti Sakit, Cuti Melahirkan, Cuti Alasan Penting, dan CLTN.</li>
            </ul>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 space-y-3 text-sm text-gray-700">
            <h3 class="font-bold text-base text-amber-700 border-b pb-2">Ketentuan Cuti PPPK (PP No. 49 Tahun 2018)</h3>
            <ul class="list-disc list-inside space-y-1.5">
                <li><b>Cuti Tahunan:</b> Hak 12 hari kerja/tahun setelah masa perjanjian kerja minimal 1 tahun.</li>
                <li><b>Cuti Sakit:</b> Diberikan sesuai surat keterangan dokter sesuai masa perjanjian kerja.</li>
                <li><b>Cuti Melahirkan:</b> Maksimal 3 bulan untuk kelahiran anak pertama sampai dengan ketiga selama menjadi PPPK.</li>
                <li><b>Cuti Bersama:</b> Mengikuti ketetapan Pemerintah tanpa mengurangi hak cuti tahunan.</li>
            </ul>
        </div>
    </div>
</div>
<?php include 'layout_footer.php'; ?>
