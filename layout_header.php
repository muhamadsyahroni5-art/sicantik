<?php
$currentUser = $currentUser ?? requireAuth();
$activePage = $activePage ?? 'dashboard';
$role = $currentUser['role'];

$menuItems = [];
if ($role === 'superadmin') {
    $menuItems = [
        ['id' => 'dashboard', 'href' => 'index.php', 'label' => 'Dashboard Utama'],
        ['id' => 'profile', 'href' => 'profile.php', 'label' => 'Profile User'],
        ['id' => 'unit_kerja', 'href' => 'unit_kerja.php', 'label' => 'Manajemen Unit Kerja'],
        ['id' => 'pegawai', 'href' => 'pegawai.php', 'label' => 'Data Pegawai & Sisa Cuti'],
        ['id' => 'usulan_cuti', 'href' => 'usulan_cuti.php', 'label' => 'Manajemen Usulan Cuti'],
        ['id' => 'form_cuti', 'href' => 'form_cuti.php', 'label' => 'Formulir Pengajuan Cuti'],
        ['id' => 'rekap_cuti', 'href' => 'rekap_cuti.php', 'label' => 'Rekapitulasi & Laporan'],
        ['id' => 'users', 'href' => 'users.php', 'label' => 'Manajemen User & Hak Akses'],
        ['id' => 'hari_libur', 'href' => 'hari_libur.php', 'label' => 'Hari Libur Nasional'],
        ['id' => 'regulasi', 'href' => 'regulasi.php', 'label' => 'Aturan & Regulasi Cuti'],
        ['id' => 'panduan', 'href' => 'panduan.php', 'label' => 'Panduan Penggunaan Aplikasi'],
    ];
} elseif ($role === 'admin') {
    $menuItems = [
        ['id' => 'dashboard', 'href' => 'index.php', 'label' => 'Dashboard Admin'],
        ['id' => 'profile', 'href' => 'profile.php', 'label' => 'Profile User'],
        ['id' => 'unit_kerja', 'href' => 'unit_kerja.php', 'label' => 'Data Unit Kerja'],
        ['id' => 'form_cuti', 'href' => 'form_cuti.php', 'label' => 'Input Usulan Cuti'],
        ['id' => 'usulan_cuti', 'href' => 'usulan_cuti.php', 'label' => 'Verifikasi Usulan Cuti'],
        ['id' => 'rekap_cuti', 'href' => 'rekap_cuti.php', 'label' => 'Rekapitulasi Usulan Cuti'],
        ['id' => 'users', 'href' => 'users.php', 'label' => 'Manajemen User & WA'],
        ['id' => 'regulasi', 'href' => 'regulasi.php', 'label' => 'Aturan & Regulasi Cuti'],
        ['id' => 'panduan', 'href' => 'panduan.php', 'label' => 'Panduan Penggunaan Aplikasi'],
    ];
} else {
    $menuItems = [
        ['id' => 'dashboard', 'href' => 'index.php', 'label' => 'Dashboard Operator'],
        ['id' => 'profile', 'href' => 'profile.php', 'label' => 'Profile User'],
        ['id' => 'form_cuti', 'href' => 'form_cuti.php', 'label' => 'Input Usulan Cuti'],
        ['id' => 'usulan_cuti', 'href' => 'usulan_cuti.php', 'label' => 'Monitoring Usulan Cuti'],
        ['id' => 'rekap_cuti', 'href' => 'rekap_cuti.php', 'label' => 'Rekapitulasi Cuti Pegawai'],
        ['id' => 'regulasi', 'href' => 'regulasi.php', 'label' => 'Aturan & Regulasi Cuti'],
        ['id' => 'panduan', 'href' => 'panduan.php', 'label' => 'Panduan Penggunaan Aplikasi'],
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SICANTIK Lebak</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="shortcut icon" type="image/png" href="assets/icon-logo-cuti.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body.app-background {
            background-color: #eef4fb;
            background-image:
                linear-gradient(rgba(238, 244, 251, 0.82), rgba(238, 244, 251, 0.82)),
                url('assets/login_pegawai_kinerja_1795138447817.jpg');
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
        }

        @media print {
            body.app-background {
                background: #fff !important;
            }
        }
    </style>
</head>
<body class="app-background min-h-screen flex flex-col font-sans text-gray-900">
<header class="relative overflow-hidden bg-gradient-to-r from-[#005a30] via-[#006838] to-[#004f2a] text-white shadow-lg sticky top-0 z-40 border-b-4 border-[#D4AF37] print:hidden">
    <div class="relative w-full px-4 sm:px-6 h-20 flex justify-between items-center gap-4">
        <div class="flex items-center space-x-3 shrink-0">
            <div class="bg-white/95 p-1 rounded-full shadow-md flex items-center justify-center w-12 h-12 border border-[#D4AF37]/60 shrink-0 overflow-hidden">
                <img
                    src="<?= file_exists(__DIR__ . '/assets/logo-kemenag.jpg') ? 'assets/logo-kemenag.jpg' : (file_exists(__DIR__ . '/logo-kemenag.jpg') ? 'logo-kemenag.jpg' : 'assets/logo-kemenag.png') ?>"
                    alt="Logo Kementerian Agama"
                    class="w-full h-full object-contain"
                >
            </div>
            <div class="leading-tight">
                <div class="text-[11px] uppercase tracking-wider text-[#D4AF37] font-semibold">Kementerian Agama Republik Indonesia</div>
                <h1 class="text-base sm:text-lg font-bold tracking-tight">Kantor Kementerian Agama Kabupaten Lebak</h1>
                <div class="text-xs text-emerald-100 italic">SICANTIK - Sistem Informasi Cuti ASN Terpadu Kemenag Lebak</div>
            </div>
        </div>

        <div class="hidden lg:flex items-center justify-center flex-1 max-w-xs">
            <div class="h-14 w-full rounded-xl border border-[#D4AF37]/45 bg-[#004a27]/85 px-4 flex items-center justify-between shadow-md">
                <div class="text-[11px] text-emerald-100 font-semibold leading-snug">
                    <div class="text-[#D4AF37] font-bold">Analisa Cuti</div>
                    <div>Monitoring Real-time</div>
                </div>
                <svg class="w-20 h-11" viewBox="0 0 80 44" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <polygon points="8,36 16,32 16,22 8,26" fill="#34D399"/>
                    <polygon points="16,32 22,35 22,25 16,22" fill="#059669"/>
                    <polygon points="8,26 16,22 22,25 14,29" fill="#6EE7B7"/>
                    <polygon points="28,36 36,32 36,15 28,19" fill="#FBBF24"/>
                    <polygon points="36,32 42,35 42,18 36,15" fill="#B45309"/>
                    <polygon points="28,19 36,15 42,18 34,22" fill="#FDE68A"/>
                    <polygon points="48,36 56,32 56,8 48,12" fill="#34D399"/>
                    <polygon points="56,32 62,35 62,11 56,8" fill="#047857"/>
                    <polygon points="48,12 56,8 62,11 54,15" fill="#A7F3D0"/>
                    <path d="M8 24 L32 15 L56 7 L72 4" stroke="#D4AF37" stroke-width="2.2" stroke-linecap="round"/>
                </svg>
            </div>
        </div>

        <div class="flex items-center space-x-3 shrink-0">
            <div class="hidden sm:block text-right">
                <div class="text-xs font-bold"><?= e($currentUser['nama_lengkap']) ?></div>
                <div class="text-[11px] text-amber-300 uppercase font-semibold"><?= e($role) ?></div>
            </div>
            <a href="logout.php" class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition">
                Keluar
            </a>
        </div>
    </div>
</header>

    <div class="flex-1 flex flex-col md:flex-row">
        <aside class="w-full md:w-64 bg-white shadow-md md:min-h-[calc(100vh-5rem)] flex flex-col border-r border-gray-200 print:hidden">
            <div class="p-4 bg-emerald-50 border-b border-emerald-100">
                <div class="text-xs text-emerald-800 font-semibold uppercase tracking-wider">
                    Menu Navigasi (<?= strtoupper(e($role)) ?>)
                </div>
            </div>
            <nav class="flex-1 p-3 space-y-1">
                <?php foreach ($menuItems as $item): ?>
                    <?php $isActive = ($activePage === $item['id']); ?>
                    <?php if (in_array($item['id'], ['unit_kerja', 'users', 'regulasi'], true)): ?>
                        <div class="my-2 border-t border-gray-200"></div>
                    <?php endif; ?>
                    <a href="<?= e($item['href']) ?>"
                       class="block w-full px-3.5 py-2.5 rounded-xl text-sm font-medium transition <?= $isActive ? 'bg-[#006838] text-white font-semibold shadow-sm' : 'text-gray-700 hover:bg-emerald-50 hover:text-[#006838]' ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
                <div class="pt-2 mt-2 border-t border-gray-100">
                    <button type="button" onclick="openHelpdeskModal()" class="block w-full text-left px-3.5 py-2.5 rounded-xl text-sm font-semibold bg-gradient-to-r from-emerald-50 to-amber-50 hover:from-emerald-100 hover:to-amber-100 text-[#006838] border border-emerald-200 transition">
                        Layanan Kontak Helpdesk
                    </button>
                </div>
            </nav>
            <div class="p-4 m-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900">
                <div class="font-bold text-amber-950 mb-1">Dasar Hukum Cuti:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>PNS: Perka BKN No. 24/2017</li>
                    <li>PPPK: PP No. 49 Tahun 2018</li>
                </ul>
            </div>
        </aside>

        <main class="flex-1 p-4 sm:p-8 overflow-y-auto print:p-0">
