<?php
require_once 'koneksi.php';
$currentUser = requireAuth();
$activePage = 'profile';

$notice = '';
$error = '';
$role = $currentUser['role'];
$userId = (int)$currentUser['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if ($role !== 'admin') {
        $error = 'Hanya Admin yang dapat mengubah username dan password dari halaman profile.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['password_confirmation'] ?? '';

        if ($username === '' || strlen($username) > 60) {
            $error = 'Username wajib diisi dan maksimal 60 karakter.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Konfirmasi password tidak sama.';
        } else {
            $passwordStmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id=? LIMIT 1");
            if (!$passwordStmt) {
                $error = 'Tidak dapat memverifikasi password saat ini.';
            } else {
                mysqli_stmt_bind_param($passwordStmt, 'i', $userId);
                mysqli_stmt_execute($passwordStmt);
                mysqli_stmt_bind_result($passwordStmt, $storedPassword);
                $hasAccount = mysqli_stmt_fetch($passwordStmt);
                $passwordMatches = $currentPassword !== '' && $hasAccount && (
                    hash_equals((string)$storedPassword, $currentPassword) ||
                    password_verify($currentPassword, (string)$storedPassword)
                );

                if (!$passwordMatches) {
                    $error = 'Password lama tidak sesuai.';
                } else {
                    $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username=? AND id<>? LIMIT 1");
                    if (!$checkStmt) {
                        $error = 'Tidak dapat memeriksa ketersediaan username.';
                    } else {
                        mysqli_stmt_bind_param($checkStmt, 'si', $username, $userId);
                        mysqli_stmt_execute($checkStmt);
                        $duplicateUser = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));

                        if ($duplicateUser) {
                            $error = 'Username sudah digunakan oleh akun lain.';
                        } else {
                            if ($newPassword !== '') {
                                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                                $updateStmt = mysqli_prepare($conn, "UPDATE users SET username=?, password=? WHERE id=?");
                                if ($updateStmt) {
                                    mysqli_stmt_bind_param($updateStmt, 'ssi', $username, $passwordHash, $userId);
                                }
                            } else {
                                $passwordHash = null;
                                $updateStmt = mysqli_prepare($conn, "UPDATE users SET username=? WHERE id=?");
                                if ($updateStmt) {
                                    mysqli_stmt_bind_param($updateStmt, 'si', $username, $userId);
                                }
                            }

                            if (!$updateStmt) {
                                $error = 'Tidak dapat menyiapkan perubahan profile.';
                            } elseif (mysqli_stmt_execute($updateStmt)) {
                                $_SESSION['user']['username'] = $username;
                                if ($passwordHash !== null) {
                                    $_SESSION['user']['password'] = $passwordHash;
                                }
                                $notice = $newPassword !== ''
                                    ? 'Username dan password profile berhasil diperbarui.'
                                    : 'Username profile berhasil diperbarui.';
                            } else {
                                $error = 'Gagal menyimpan perubahan profile.';
                            }
                        }
                    }
                }
            }
        }
    }
}

$profile = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT u.*, uk.nama_unit
     FROM users u
     LEFT JOIN unit_kerja uk ON u.unit_kerja_id = uk.id
     WHERE u.id=$userId
     LIMIT 1"
));

if (!$profile) {
    die('Data profile user tidak ditemukan.');
}

$roleLabels = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'operator' => 'Operator',
];

include 'layout_header.php';
?>
<div class="space-y-6">
    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Profile User</h2>
            <p class="text-sm text-gray-600 mt-1">Informasi akun yang sedang digunakan untuk login.</p>
        </div>
        <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-[#006838] hover:bg-emerald-900 text-white rounded-xl text-xs font-bold shadow-sm transition shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
            </svg>
            Kembali ke Dashboard
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-[#006838] to-emerald-700 px-6 py-5 text-white flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-white/95 text-[#006838] flex items-center justify-center text-2xl font-black border-2 border-[#D4AF37]">
                    <?= e(strtoupper(substr($profile['nama_lengkap'], 0, 1))) ?>
                </div>
                <div>
                    <h3 class="text-lg font-bold"><?= e($profile['nama_lengkap']) ?></h3>
                    <p class="text-sm text-emerald-100 mt-0.5"><?= e($roleLabels[$profile['role']] ?? ucfirst($profile['role'])) ?></p>
                </div>
            </div>
            <?php if ($role === 'admin'): ?>
                <button type="button" onclick="openProfileEditModal()" class="px-4 py-2 bg-white text-[#006838] hover:bg-emerald-50 rounded-xl text-xs font-bold shadow-sm shrink-0">Edit Username / Password</button>
            <?php endif; ?>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5">
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Nama Lengkap</div>
                <div class="mt-1 text-sm font-semibold text-gray-900"><?= e($profile['nama_lengkap']) ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Username</div>
                <div class="mt-1 text-sm font-semibold text-gray-900">@<?= e($profile['username']) ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">NIP</div>
                <div class="mt-1 text-sm font-mono font-semibold text-gray-900"><?= e($profile['nip']) ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Jabatan</div>
                <div class="mt-1 text-sm font-semibold text-gray-900"><?= e($profile['jabatan']) ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Status Pegawai</div>
                <div class="mt-1 text-sm font-semibold text-gray-900"><?= e($profile['status_pegawai']) ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Unit Kerja</div>
                <div class="mt-1 text-sm font-semibold text-gray-900"><?= e($profile['nama_unit'] ?: '-') ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">No. WhatsApp</div>
                <div class="mt-1 text-sm font-semibold text-gray-900"><?= e($profile['no_wa'] ?: '-') ?></div>
            </div>
            <div class="border-b border-gray-100 pb-3">
                <div class="text-xs font-bold uppercase text-gray-500">Hak Akses</div>
                <div class="mt-1">
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                        <?= e($roleLabels[$profile['role']] ?? ucfirst($profile['role'])) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($role === 'admin'): ?>
<div id="profileEditModal" class="hidden fixed inset-0 z-50 bg-black/60 items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <div class="bg-[#006838] text-white px-6 py-4 flex items-center justify-between border-b-4 border-[#D4AF37]">
            <h3 class="font-bold text-base">Edit Akun Admin</h3>
            <button type="button" onclick="closeProfileEditModal()" class="text-white font-bold text-xl" aria-label="Tutup">&times;</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label for="profileUsername" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Username</label>
                <input id="profileUsername" type="text" name="username" required maxlength="60" value="<?= e($profile['username']) ?>" autocomplete="username" class="w-full px-3 py-2.5 rounded-xl border text-sm">
            </div>
            <div>
                <label for="profileCurrentPassword" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Password Lama (Verifikasi)</label>
                <input id="profileCurrentPassword" type="password" name="current_password" required autocomplete="current-password" class="w-full px-3 py-2.5 rounded-xl border text-sm">
            </div>
            <div>
                <label for="profilePassword" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Password Baru</label>
                <input id="profilePassword" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Kosongkan jika tidak ingin mengubah" class="w-full px-3 py-2.5 rounded-xl border text-sm">
                <p class="text-[11px] text-gray-500 mt-1">Minimal 8 karakter. Password disimpan secara terenkripsi.</p>
            </div>
            <div>
                <label for="profilePasswordConfirmation" class="block text-xs font-semibold uppercase text-gray-600 mb-1">Konfirmasi Password Baru</label>
                <input id="profilePasswordConfirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" class="w-full px-3 py-2.5 rounded-xl border text-sm">
            </div>
            <div class="flex justify-end gap-2 pt-3 border-t">
                <button type="button" onclick="closeProfileEditModal()" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-xl text-xs font-bold">Batal</button>
                <button type="submit" class="px-5 py-2 bg-[#006838] hover:bg-emerald-900 text-white rounded-xl text-xs font-bold">Simpan</button>
            </div>
        </form>
    </div>
</div>
<script>
function openProfileEditModal() {
    const modal = document.getElementById('profileEditModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeProfileEditModal() {
    const modal = document.getElementById('profileEditModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
<?php endif; ?>
<?php include 'layout_footer.php'; ?>
