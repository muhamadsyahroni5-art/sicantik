<?php
require_once 'koneksi.php';
$currentUser = requireAuth();

$id = (int)($_GET['id'] ?? 0);
$cuti = mysqli_fetch_assoc(mysqli_query($conn, "SELECT c.*, p.nama_lengkap, p.nip, p.jabatan, p.no_wa, p.bulan_mulai_kerja, p.tahun_mulai_kerja, p.sctn2, p.sctn1, p.sctn, u.nama_unit, u.pimpinan, u.nip_pimpinan, u.jabatan_pimpinan
    FROM cuti_requests c
    JOIN pegawai p ON c.pegawai_id = p.id
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    WHERE c.id = $id LIMIT 1"));

if (!$cuti) {
    die('Data usulan cuti tidak ditemukan.');
}

$statusAkhirKey = strtolower(trim(preg_replace('/\s+/', ' ', (string)$cuti['status_akhir'])));
if (in_array($statusAkhirKey, [
    'menunggu verifikasi atasan',
    'menunggu verifikasi atasan langsung',
    'menunggu verifikasi kepala'
], true)) {
    header('Location: usulan_cuti.php?cetak_blocked=1');
    exit;
}

$kepala = getKepalaKantor($conn);
$exp = hitungMasaKerja((int)$cuti['bulan_mulai_kerja'], (int)$cuti['tahun_mulai_kerja']);
$yr = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Formulir Cuti - <?= e($cuti['nama_lengkap']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { size: A4 portrait; margin: 0.6cm 0.8cm; }
            body { background: #fff !important; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .print-sheet { font-size: 7.5pt !important; line-height: 1.15 !important; box-shadow: none !important; margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .print-sheet table td, .print-sheet table th { padding: 2px 5px !important; line-height: 1.15 !important; }
        }
    </style>
</head>
<body class="bg-gray-200 py-6 print:py-0 print:bg-white text-black font-sans">
    <div class="no-print max-w-4xl mx-auto mb-4 flex justify-between items-center bg-[#006838] text-white px-6 py-3 rounded-xl shadow">
        <span class="font-bold text-sm">Pratinjau Cetak Formulir Cuti Resmi (1 Halaman A4)</span>
        <div class="space-x-2">
            <button onclick="window.print()" class="bg-[#D4AF37] text-gray-900 font-bold px-4 py-1.5 rounded-lg text-xs">Cetak A4 Sekarang</button>
            <a href="usulan_cuti.php" class="bg-emerald-900 text-white px-4 py-1.5 rounded-lg text-xs font-bold">Kembali</a>
        </div>
    </div>

    <div class="print-sheet bg-white max-w-4xl mx-auto p-6 shadow-xl text-[8pt] space-y-1.5">
        <div class="text-right text-[7pt] leading-tight font-serif">
            <div>ANAK LAMPIRAN 1.b</div>
            <div>PERATURAN BADAN KEPEGAWAIAN NEGARA</div>
            <div>REPUBLIK INDONESIA NOMOR 24 TAHUN 2017</div>
            <div>TENTANG TATA CARA PEMBERIAN CUTI PEGAWAI NEGERI SIPIL</div>
        </div>

        <div class="text-right pr-2 leading-tight">
            <div>Rangkasbitung, <?= formatDateIndo($cuti['tanggal_pengajuan']) ?></div>
            <div class="font-bold">Kepada</div>
            <div>Yth. Kepala Kantor Kementerian Agama Kabupaten Lebak</div>
            <div><b>di</b> Rangkasbitung</div>
        </div>

        <div class="text-center font-bold underline text-[10pt] py-0.5">FORMULIR PERMINTAAN DAN PEMBERIAN CUTI</div>

        <table class="w-full border-collapse border border-black">
            <tr><td colspan="4" class="bg-gray-200 font-bold border border-black px-2 py-0.5">I. DATA PEGAWAI</td></tr>
            <tr>
                <td class="w-1/4 border border-black px-2 py-0.5 font-semibold">Nama</td>
                <td class="w-1/4 border border-black px-2 py-0.5"><?= e($cuti['nama_lengkap']) ?></td>
                <td class="w-1/4 border border-black px-2 py-0.5 font-semibold">NIP</td>
                <td class="w-1/4 border border-black px-2 py-0.5 font-mono"><?= e($cuti['nip']) ?></td>
            </tr>
            <tr>
                <td class="border border-black px-2 py-0.5 font-semibold">Jabatan</td>
                <td class="border border-black px-2 py-0.5"><?= e($cuti['jabatan']) ?></td>
                <td class="border border-black px-2 py-0.5 font-semibold">Masa Kerja</td>
                <td class="border border-black px-2 py-0.5"><?= $exp['years'] ?> Tahun <?= $exp['months'] ?> Bulan</td>
            </tr>
            <tr>
                <td class="border border-black px-2 py-0.5 font-semibold">Unit Kerja</td>
                <td colspan="3" class="border border-black px-2 py-0.5"><?= e($cuti['nama_unit']) ?></td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black">
            <tr><td colspan="2" class="bg-gray-200 font-bold border border-black px-2 py-0.5">II. JENIS CUTI YANG DIAMBIL **</td></tr>
            <tr>
                <td class="w-1/2 border border-black px-2 py-0.5">1. Cuti Tahunan <span class="float-right font-bold"><?= $cuti['jenis_cuti'] === 'Cuti Tahunan' ? '( V )' : '(   )' ?></span></td>
                <td class="w-1/2 border border-black px-2 py-0.5">2. Cuti Besar <span class="float-right font-bold"><?= $cuti['jenis_cuti'] === 'Cuti Besar' ? '( V )' : '(   )' ?></span></td>
            </tr>
            <tr>
                <td class="border border-black px-2 py-0.5">3. Cuti Sakit <span class="float-right font-bold"><?= $cuti['jenis_cuti'] === 'Cuti Sakit' ? '( V )' : '(   )' ?></span></td>
                <td class="border border-black px-2 py-0.5">4. Cuti Melahirkan <span class="float-right font-bold"><?= $cuti['jenis_cuti'] === 'Cuti Melahirkan' ? '( V )' : '(   )' ?></span></td>
            </tr>
            <tr>
                <td class="border border-black px-2 py-0.5">5. Cuti Karena Alasan Penting <span class="float-right font-bold"><?= $cuti['jenis_cuti'] === 'Cuti Karena Alasan Penting' ? '( V )' : '(   )' ?></span></td>
                <td class="border border-black px-2 py-0.5">6. Cuti di Luar Tanggungan Negara <span class="float-right font-bold"><?= strpos($cuti['jenis_cuti'], 'CLTN') !== false ? '( V )' : '(   )' ?></span></td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black">
            <tr><td class="bg-gray-200 font-bold border border-black px-2 py-0.5">III. ALASAN CUTI</td></tr>
            <tr><td class="border border-black px-2 py-1"><?= e($cuti['alasan']) ?></td></tr>
        </table>

        <table class="w-full border-collapse border border-black">
            <tr><td colspan="3" class="bg-gray-200 font-bold border border-black px-2 py-0.5">IV. LAMANYA CUTI</td></tr>
            <tr>
                <td class="w-1/3 border border-black px-2 py-0.5">Selama: <b><?= (int)$cuti['lama_hari_cuti'] ?> hari kerja</b></td>
                <td class="w-1/3 border border-black px-2 py-0.5">Mulai Tanggal: <b><?= formatDateIndo($cuti['tanggal_mulai']) ?></b></td>
                <td class="w-1/3 border border-black px-2 py-0.5">s/d: <b><?= formatDateIndo($cuti['tanggal_selesai']) ?></b></td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black">
            <tr><td colspan="2" class="bg-gray-200 font-bold border border-black px-2 py-0.5">V. CATATAN CUTI ***</td></tr>
            <tr>
                <td class="w-1/2 border border-black p-0">
                    <table class="w-full border-collapse text-center">
                        <tr class="bg-gray-100"><th class="border border-black p-1">1. Cuti Tahunan<?= $cuti['jenis_cuti'] === 'Cuti Tahunan' ? '( V )' : '(   )' ?></th><th class="border border-black px-1">Tahun</th><th class="border border-black p-1">Sisa</th><th class="border border-black p-1">Ket</th></tr>
                        <tr><td class="border border-black p-1 text-left">N-2</td><td class="border border-black p-1"><?= $yr - 2 ?></td><td class="border border-black p-1 font-mono"><?= (int)$cuti['sctn2'] ?></td><td class="border border-black p-1"></td></tr>
                        <tr><td class="border border-black p-1 text-left">N-1</td><td class="border border-black p-1"><?= $yr - 1 ?></td><td class="border border-black p-1 font-mono"><?= (int)$cuti['sctn1'] ?></td><td class="border border-black p-1"></td></tr>
                        <tr><td class="border border-black p-1 text-left">N</td><td class="border border-black p-1"><?= $yr ?></td><td class="border border-black p-1 font-mono"><?= (int)$cuti['sctn'] ?></td><td class="border border-black p-1"></td></tr>
                    </table>
                </td>
                <td class="w-1/2 border border-black p-1 align-top text-[7pt]">
                    <div>2. CUTI BESAR <?= $cuti['jenis_cuti'] === 'Cuti Besar' ? '( V )' : '(   )' ?> | 3. CUTI SAKIT <?= $cuti['jenis_cuti'] === 'Cuti Sakit' ? '( V )' : '(   )' ?></div>
                    <div>4. CUTI MELAHIRKAN <?= $cuti['jenis_cuti'] === 'Cuti Melahirkan' ? '( V )' : '(   )' ?>    | 5. CUTI ALASAN PENTING <?= $cuti['jenis_cuti'] === 'Cuti Karena Alasan Penting' ? '( V )' : '(   )' ?></div>
                    <div>6. CUTI DI LUAR TANGGUNGAN NEGARA <?= strpos($cuti['jenis_cuti'], 'CLTN') !== false ? '( V )' : '(   )' ?></div>
                </td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black">
            <tr><td colspan="2" class="bg-gray-200 font-bold border border-black px-2 py-0.5">VI. ALAMAT SELAMA MENJALANKAN CUTI</td></tr>
            <tr>
                <td class="w-2/3 border border-black px-2 py-1 align-top"><?= e($cuti['alamat_selama_cuti']) ?><div class="mt-1">TELP: <?= e($cuti['no_wa']) ?></div></td>
                <td class="w-1/3 border border-black px-2 py-1 text-center">
                    <div>Hormat Saya,</div>
                    <div class="h-12"></div>
                    <div class="font-bold underline"><?= e($cuti['nama_lengkap']) ?></div>
                    <div class="font-mono">NIP. <?= e($cuti['nip']) ?></div>
                </td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black text-center">
            <tr><td colspan="4" class="bg-gray-200 font-bold border border-black px-2 py-0.5 text-left">VII. PERTIMBANGAN ATASAN LANGSUNG **</td></tr>
            <tr class="bg-gray-50 font-semibold">
                <td class="border border-black w-1/4">DISETUJUI</td><td class="border border-black w-1/4">PERUBAHAN ****</td><td class="border border-black w-1/4">DITANGGUHKAN ****</td><td class="border border-black w-1/4">TIDAK DISETUJUI ****</td>
            </tr>
            <tr>
                <td class="border border-black font-bold"><?= $cuti['status_atasan'] === 'Disetujui' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_atasan'] === 'Perubahan' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_atasan'] === 'Ditangguhkan' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_atasan'] === 'Ditolak' ? '( V )' : '(   )' ?></td>
            </tr>
            <tr>
                <td colspan="4" class="border border-black p-1 text-right">
                    <div class="inline-block text-center w-72">
                        <div class="font-bold"><?= e($cuti['jabatan_pimpinan'] ?: 'Atasan Langsung') ?></div>
                        <div class="h-12"></div>
                        <div class="font-bold underline"><?= e($cuti['pimpinan']) ?></div>
                        <div class="font-mono">NIP. <?= e($cuti['nip_pimpinan']) ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="w-full border-collapse border border-black text-center">
            <tr><td colspan="4" class="bg-gray-200 font-bold border border-black px-2 py-0.5 text-left">VIII. KEPUTUSAN PEJABAT YANG BERWENANG MEMBERIKAN CUTI **</td></tr>
            <tr class="bg-gray-50 font-semibold">
                <td class="border border-black w-1/4">DISETUJUI</td><td class="border border-black w-1/4">PERUBAHAN ****</td><td class="border border-black w-1/4">DITANGGUHKAN ****</td><td class="border border-black w-1/4">TIDAK DISETUJUI ****</td>
            </tr>
            <tr>
                <td class="border border-black font-bold"><?= $cuti['status_kepala'] === 'Disetujui' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_kepala'] === 'Perubahan' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_kepala'] === 'Ditangguhkan' ? '( V )' : '(   )' ?></td>
                <td class="border border-black font-bold"><?= $cuti['status_kepala'] === 'Ditolak' ? '( V )' : '(   )' ?></td>
            </tr>
            <tr>
                <td colspan="4" class="border border-black p-1 text-right">
                    <div class="inline-block text-center w-80">
                        <div class="font-bold uppercase"><?= e($kepala['jabatan_pimpinan']) ?></div>
                        <div class="h-12"></div>
                        <div class="font-bold underline"><?= e($kepala['pimpinan']) ?></div>
                        <div class="font-mono">NIP. <?= e($kepala['nip_pimpinan']) ?></div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="text-[6.5pt] text-gray-700 pt-0.5 border-t border-black">
            <div><b>Catatan :</b> * Coret yang tidak perlu | ** Pilih salah satu dengan memberi tanda centang ( V ) | *** Diisi pejabat kepegawaian | N = Cuti berjalan</div>
        </div>
    </div>
</body>
</html>
