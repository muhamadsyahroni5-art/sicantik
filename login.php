<?php
require_once 'koneksi.php';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ? OR nip = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'ss', $loginInput, $loginInput);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);

    if ($user && ($password === $user['password'] || password_verify($password, $user['password']))) {
        $_SESSION['user'] = $user;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Username / NIP atau Password salah!';
    }
}

$loginBgCandidates = [
    'assets/login_pegawai_kinerja.jpg',
    'assets/login_pegawai_kinerja_1795138447817_2.jpg',
    'assets/login_pegawai_kinerja_1795138447817.jpg',
    'assets/login_pegawai_kinerja.png',
];
$loginBgSrc = 'assets/login_pegawai_kinerja.jpg';
foreach ($loginBgCandidates as $cand) {
    if (file_exists(__DIR__ . '/' . $cand)) {
        $loginBgSrc = $cand;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SICANTIK Kemenag Lebak</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
    <link rel="shortcut icon" type="image/png" href="assets/icon-logo-cuti.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="relative overflow-x-hidden bg-[#071a3d] min-h-screen flex flex-col items-center justify-center p-4">
    <!-- Gambar Background dari Folder assets -->
    <div
        class="pointer-events-none select-none fixed inset-0 z-0 bg-cover bg-center bg-no-repeat"
        style="background-image: url('<?= e($loginBgSrc) ?>');"
        aria-hidden="true"
    ></div>
    <!-- Overlay Gelap Transparan agar Kontras Form Login Tetap Jelas -->
    <div
        class="pointer-events-none select-none fixed inset-0 z-0 bg-gradient-to-br from-[#071a3d]/75 via-[#0b3168]/65 to-[#071a3d]/80"
        aria-hidden="true"
    ></div>

    <div class="relative z-10 bg-white/95 backdrop-blur-md w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border-t-8 border-[#D4AF37]">
        <div class="p-8 text-center bg-emerald-50/70 border-b">
            <img src="assets/logo-kemenag.jpg" alt="Logo Kementerian Agama" class="w-16 h-16 mx-auto mb-3 rounded-full bg-white object-contain border-2 border-[#D4AF37] shadow-sm">
            <div class="text-xs uppercase font-bold text-[#D4AF37] tracking-wider">Kantor Kementerian Agama Kabupaten Lebak</div>
            <h2 class="text-2xl font-extrabold text-[#006838] mt-1 tracking-wide">SICANTIK</h2>
            <p class="text-xs text-gray-700 font-medium mt-1">Sistem Informasi Cuti ASN Terintegrasi Kemenag Lebak</p>
        </div>

        <form method="POST" class="p-8 space-y-4">
            <?php if ($error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs p-3 rounded-xl font-medium">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Username atau NIP</label>
                <input type="text" name="login_input" required placeholder="Contoh: superadmin / 197001011995031001"
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#006838]">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 rounded-xl border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-[#006838]">
            </div>

            <button type="submit" class="w-full bg-[#006838] hover:bg-emerald-900 text-white font-bold py-3 rounded-xl text-sm transition shadow-lg">
                MASUK APLIKASI
            </button>            
        </form>
    </div>
    <footer class="relative z-10 mt-6 px-5 py-2 rounded-full bg-[#006838]/90 border border-[#D4AF37]/60 text-center text-xs text-white font-medium shadow-lg">
        Copyright &copy; <?= date('Y') ?> Prakom Kemenag Lebak
    </footer>
</body>
</html>