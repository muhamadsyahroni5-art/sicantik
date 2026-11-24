<?php
require_once 'koneksi.php';
$currentUser = requireRole(['superadmin', 'admin']);
$activePage = 'users';

$notice = '';
$error = '';
$isAdmin = ($currentUser['role'] === 'admin');
$userUnitId = (int)$currentUser['unit_kerja_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_wa') {
        $template = trim($_POST['wa_template'] ?? '');
        if (!$template) {
            $error = 'Template pesan WhatsApp tidak boleh kosong.';
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO app_settings (setting_key, setting_value) VALUES ('wa_template', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            mysqli_stmt_bind_param($stmt, 's', $template);
            mysqli_stmt_execute($stmt);
            $notice = 'Pengaturan isi pesan notifikasi WhatsApp berhasil disimpan.';
        }
    } elseif ($action === 'save_user') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_lengkap'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $nip = trim($_POST['nip'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $noWa = trim($_POST['no_wa'] ?? '');
        $unitIdRaw = (int)($_POST['unit_kerja_id'] ?? $userUnitId);
        $unitId = $unitIdRaw > 0 ? $unitIdRaw : null;
        $role = $_POST['role'] ?? 'operator';

        if (!$nama || !$username || !$nip || !$password) {
            $error = 'Nama, Username, NIP, dan Password wajib diisi.';
        } elseif ($id > 0) {
            if ($isAdmin) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, nip=?, password=?, jabatan=?, no_wa=? WHERE id=? AND unit_kerja_id=?");
                mysqli_stmt_bind_param($stmt, 'ssssssii', $nama, $username, $nip, $password, $jabatan, $noWa, $id, $userUnitId);
                if (mysqli_stmt_execute($stmt)) {
                    $notice = 'Data user unit kerja Anda berhasil diperbarui.';
                } else {
                    $error = 'Gagal memperbarui user (pastikan Username dan NIP tidak duplikat).';
                }
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET nama_lengkap=?, username=?, nip=?, password=?, jabatan=?, unit_kerja_id=?, no_wa=?, role=? WHERE id=?");
                mysqli_stmt_bind_param($stmt, 'sssssissi', $nama, $username, $nip, $password, $jabatan, $unitId, $noWa, $role, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $notice = 'Data user berhasil diperbarui.';
                } else {
                    $error = 'Gagal memperbarui user (pastikan Username dan NIP tidak duplikat).';
                }
            }
        } else {
            if ($isAdmin) {
                $error = 'Admin tidak diizinkan menambah user baru.';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, nip, password, jabatan, unit_kerja_id, no_wa, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'sssssiss', $nama, $username, $nip, $password, $jabatan, $unitId, $noWa, $role);
                if (mysqli_stmt_execute($stmt)) {
                    $notice = 'User baru berhasil ditambahkan.';
                } else {
                    $error = 'Gagal menambah user (username atau NIP sudah terdaftar).';
                }
            }
        }
    } elseif ($action === 'delete_user') {
        $id = (int)($_POST['id'] ?? 0);
        if ($isAdmin) {
            $error = 'Admin tidak memiliki izin menghapus user.';
        } elseif ($id === (int)$currentUser['id']) {
            $error = 'Akun yang sedang aktif login tidak dapat dihapus.';
        } elseif ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $notice = 'Akun user berhasil dihapus.';
            } else {
                $error = 'Gagal menghapus akun user.';
            }
        }
    }
}

$waTemplate = getWaTemplate($conn);
$editUser = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $sqlEdit = $isAdmin
        ? "SELECT * FROM users WHERE id=$eid AND unit_kerja_id=$userUnitId"
        : "SELECT * FROM users WHERE id=$eid";
    $editUser = mysqli_fetch_assoc(mysqli_query($conn, $sqlEdit));
}

$unitsRes = mysqli_query($conn, "SELECT * FROM unit_kerja ORDER BY id ASC");
$unitList = [];
while ($u = mysqli_fetch_assoc($unitsRes)) $unitList[] = $u;

$pegRes = mysqli_query($conn, "SELECT p.id, p.nama_lengkap, p.nip, p.jabatan, p.unit_kerja_id, p.no_wa, u.nama_unit
    FROM pegawai p
    LEFT JOIN unit_kerja u ON p.unit_kerja_id = u.id
    ORDER BY p.nama_lengkap ASC");
$pegawaiOptions = [];
if ($pegRes) {
    while ($pr = mysqli_fetch_assoc($pegRes)) {
        $pegawaiOptions[] = $pr;
    }
}

$usersSql = "SELECT usr.*, u.nama_unit FROM users usr LEFT JOIN unit_kerja u ON usr.unit_kerja_id = u.id";
if ($isAdmin) {
    $usersSql .= " WHERE usr.unit_kerja_id = $userUnitId";
}
$usersSql .= " ORDER BY usr.id ASC";
$usersRes = mysqli_query($conn, $usersSql);

$isUserModalOpen = ($editUser !== null);

include 'layout_header.php';
?>
<div class="space-y-6">
    <!-- Header Halaman -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-start gap-3">
            <div class="mt-0.5 text-[#006838] shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
            <div>
                <h2 class="text-xl font-bold text-gray-900">Manajemen User &amp; Hak Akses Aplikasi</h2>
                <p class="text-sm text-gray-600 mt-1">
                    <?= $isAdmin ? 'Menampilkan daftar user pada unit kerja Anda.' : 'Pengelolaan akun login untuk seluruh role Super Admin, Admin, Operator, serta pengaturan isi pesan WhatsApp.' ?>
                </p>
            </div>
        </div>
        <?php if (!$isAdmin): ?>
            <button type="button" onclick="openUserModal()" class="inline-flex items-center gap-1.5 bg-[#006838] hover:bg-emerald-900 text-white px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm transition shrink-0">
                <span>+ Tambah User Baru</span>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($notice): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($notice) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-medium"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Tabel User -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-700">
                <thead class="bg-emerald-50/80 text-[#006838] uppercase text-xs font-bold">
                    <tr>
                        <th class="px-4 py-3.5 text-center">No</th>
                        <th class="px-4 py-3.5">Nama &amp; Username</th>
                        <th class="px-4 py-3.5">NIP</th>
                        <th class="px-4 py-3.5">Jabatan / Status</th>
                        <th class="px-4 py-3.5">Unit Kerja</th>
                        <th class="px-4 py-3.5">No. WA</th>
                        <th class="px-4 py-3.5 text-center">Role Akses</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php $no = 1; while ($usr = mysqli_fetch_assoc($usersRes)):
                        $roleVal = strtolower($usr['role'] ?? 'operator');
                        $roleBadgeClass = match ($roleVal) {
                            'superadmin' => 'bg-amber-500 text-white',
                            'admin' => 'bg-blue-600 text-white',
                            default => 'bg-emerald-600 text-white',
                        };
                        $isCurrentLogin = ((int)$usr['id'] === (int)$currentUser['id']);
                    ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-center font-medium"><?= $no++ ?></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-gray-900"><?= e($usr['nama_lengkap']) ?></span>
                                    <?php if ($isCurrentLogin): ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Aktif</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-emerald-700 font-semibold">@<?= e($usr['username']) ?></div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs"><?= e($usr['nip']) ?></td>
                            <td class="px-4 py-3">
                                <div class="text-xs sm:text-sm text-gray-800"><?= e($usr['jabatan']) ?></div>
                                <div class="text-[10px] text-gray-400 uppercase mt-0.5">PNS</div>
                            </td>
                            <td class="px-4 py-3 text-xs"><?= e($usr['nama_unit']) ?></td>
                            <td class="px-4 py-3 font-mono text-xs"><?= e($usr['no_wa']) ?></td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wide <?= $roleBadgeClass ?>">
                                    <?= e($usr['role']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    onclick='openUserModal(<?= json_encode([
                                        "id" => (int)$usr["id"],
                                        "nama_lengkap" => $usr["nama_lengkap"],
                                        "username" => $usr["username"],
                                        "nip" => $usr["nip"],
                                        "password" => $usr["password"],
                                        "jabatan" => $usr["jabatan"],
                                        "unit_kerja_id" => (int)($usr["unit_kerja_id"] ?? $userUnitId),
                                        "no_wa" => $usr["no_wa"],
                                        "role" => $usr["role"],
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                    title="Edit"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-600 transition mr-1"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <?php if (!$isAdmin && !$isCurrentLogin): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Hapus user ini?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                                        <button type="submit" title="Hapus" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span title="Tidak dapat dihapus" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 text-gray-300 cursor-not-allowed">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah / Edit Akun User -->
    <div id="userModal" class="fixed inset-0 z-50 <?= $isUserModalOpen ? 'flex' : 'hidden' ?> items-center justify-center bg-black/50 backdrop-blur-[1px] p-4 overflow-y-auto" onclick="if(event.target === this) closeUserModal()">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden my-auto">
            <!-- Header Hijau dengan Aksen Garis Bawah Kuning -->
            <div class="bg-[#006838] px-6 py-4 flex items-center justify-between border-b-2 border-amber-400">
                <h3 id="userModalTitle" class="text-white font-bold text-base">
                    <?= $editUser ? 'Edit Akun User' : 'Tambah Akun User Baru' ?>
                </h3>
                <button type="button" onclick="closeUserModal()" aria-label="Tutup" class="text-white/80 hover:text-white transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Body Form Modal -->
            <form method="POST" action="users.php" class="p-6 space-y-4">
                <input type="hidden" name="action" value="save_user">
                <input type="hidden" name="id" id="user_id" value="<?= (int)($editUser['id'] ?? 0) ?>">

                <!-- Filter Search Data Pegawai (Otomatis Mengisi Nama, NIP, Jabatan, Unit Kerja, No. WA) -->
                <div id="pegawaiSearchSection" class="relative bg-emerald-50/70 border border-emerald-200 rounded-xl p-3.5">
                    <label for="pegawaiSearchInput" class="block text-[11px] font-bold uppercase tracking-wider text-[#006838] mb-1.5">
                        CARI DATA PEGAWAI (NAMA / NIP)
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            id="pegawaiSearchInput"
                            autocomplete="off"
                            placeholder="Ketik nama atau NIP pegawai untuk mengisi otomatis..."
                            class="w-full px-3.5 py-2 pr-8 rounded-xl border border-emerald-300 bg-white text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                        <button
                            type="button"
                            id="clearPegawaiSearchBtn"
                            onclick="clearPegawaiSearch()"
                            title="Bersihkan pencarian"
                            class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-sm font-bold px-1"
                        >&times;</button>
                    </div>
                    <div
                        id="pegawaiSearchResults"
                        class="hidden absolute left-3.5 right-3.5 z-30 mt-1 max-h-52 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl divide-y divide-gray-100"
                    ></div>
                </div>

                <div>
                    <label for="user_nama" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NAMA LENGKAP &amp; GELAR</label>
                    <input
                        type="text"
                        id="user_nama"
                        name="nama_lengkap"
                        required
                        value="<?= e($editUser['nama_lengkap'] ?? '') ?>"
                        placeholder="Contoh: Siti Aminah, S.Kom."
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="user_username" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">USERNAME LOGIN</label>
                        <input
                            type="text"
                            id="user_username"
                            name="username"
                            required
                            value="<?= e($editUser['username'] ?? '') ?>"
                            placeholder="Contoh: sitiaminah"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                    </div>
                    <div>
                        <label for="user_password" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">PASSWORD</label>
                        <input
                            type="text"
                            id="user_password"
                            name="password"
                            required
                            value="<?= e($editUser['password'] ?? 'password123') ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="user_nip" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NIP</label>
                        <input
                            type="text"
                            id="user_nip"
                            name="nip"
                            required
                            value="<?= e($editUser['nip'] ?? '') ?>"
                            placeholder="Contoh: 198505122010012003"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm font-mono text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                    </div>
                    <div>
                        <label for="user_jabatan" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">JABATAN</label>
                        <input
                            type="text"
                            id="user_jabatan"
                            name="jabatan"
                            required
                            value="<?= e($editUser['jabatan'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="user_unit" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">UNIT KERJA</label>
                        <select
                            id="user_unit"
                            name="unit_kerja_id"
                            <?= $isAdmin ? 'disabled' : '' ?>
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 bg-white focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <?php foreach ($unitList as $u): ?>
                                <option value="<?= (int)$u['id'] ?>" <?= ((int)($editUser['unit_kerja_id'] ?? $userUnitId) === (int)$u['id']) ? 'selected' : '' ?>><?= e($u['nama_unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="user_role" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">ROLE USER</label>
                        <select
                            id="user_role"
                            name="role"
                            <?= $isAdmin ? 'disabled' : '' ?>
                            class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 bg-white font-bold focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="operator" <?= (($editUser['role'] ?? 'operator') === 'operator') ? 'selected' : '' ?>>Operator</option>
                            <option value="admin" <?= (($editUser['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="superadmin" <?= (($editUser['role'] ?? '') === 'superadmin') ? 'selected' : '' ?>>Super Admin</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="user_wa" class="block text-[11px] font-bold uppercase tracking-wider text-gray-600 mb-1.5">NO. HP / WA</label>
                    <input
                        type="text"
                        id="user_wa"
                        name="no_wa"
                        value="<?= e($editUser['no_wa'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-300 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:border-[#006838] focus:ring-2 focus:ring-emerald-500/20"
                    >
                </div>

                <!-- Footer Tombol Batal & Simpan -->
                <div class="pt-3 border-t border-gray-200 flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onclick="closeUserModal()"
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
                        <span id="userSubmitLabel"><?= $editUser ? 'Simpan Perubahan' : 'Simpan Perubahan' ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pengaturan Isi Pesan WA -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-emerald-50 px-6 py-4 border-b border-emerald-100">
            <h3 class="font-bold text-base text-gray-900">Pengaturan Isi Pesan Notifikasi WhatsApp</h3>
            <p class="text-xs text-gray-600">Variabel tersedia: <code class="bg-white px-1 rounded">{nama}</code>, <code class="bg-white px-1 rounded">{jenis}</code>, <code class="bg-white px-1 rounded">{status}</code>, <code class="bg-white px-1 rounded">{tanggal}</code>, <code class="bg-white px-1 rounded">{lama}</code>, <code class="bg-white px-1 rounded">{nomor_surat}</code></p>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="save_wa">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-600 mb-1.5">FORMAT ISI PESAN WHATSAPP</label>
                    <textarea id="waTemplateInput" name="wa_template" rows="4" required oninput="updateWaPreview()" class="w-full px-4 py-3 rounded-xl border border-gray-300 text-sm focus:outline-none focus:border-[#006838]"><?= e($waTemplate) ?></textarea>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase text-gray-600 mb-1.5">PRATINJAU PESAN WHATSAPP (CONTOH OUTPUT):</label>
                    <div id="waPreviewBox" class="w-full min-h-[108px] px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-xs text-gray-700 whitespace-pre-line"></div>
                </div>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 bg-[#006838] hover:bg-emerald-900 text-white text-xs font-bold rounded-xl transition">Simpan Pengaturan Pesan WA</button>
            </div>
        </form>
    </div>

    <script>
    const defaultUserUnitId = <?= (int)($userUnitId ?: ($unitList[0]['id'] ?? 0)) ?>;
    const pegawaiListForUser = <?= json_encode($pegawaiOptions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const pegSearchInput = document.getElementById('pegawaiSearchInput');
    const pegSearchResults = document.getElementById('pegawaiSearchResults');
    const clearPegSearchBtn = document.getElementById('clearPegawaiSearchBtn');

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[ch]));
    }

    function clearPegawaiSearch() {
        if (pegSearchInput) pegSearchInput.value = '';
        if (clearPegSearchBtn) clearPegSearchBtn.classList.add('hidden');
        if (pegSearchResults) pegSearchResults.classList.add('hidden');
    }

    function renderPegawaiSearch(query = '') {
        if (!pegSearchResults) return;
        const q = String(query || '').trim().toLowerCase();
        const matches = q === ''
            ? pegawaiListForUser.slice(0, 20)
            : pegawaiListForUser.filter(p =>
                String(p.nama_lengkap || '').toLowerCase().includes(q) ||
                String(p.nip || '').toLowerCase().includes(q) ||
                String(p.nama_unit || '').toLowerCase().includes(q)
            );

        if (clearPegSearchBtn) clearPegSearchBtn.classList.toggle('hidden', q === '');

        if (matches.length === 0) {
            pegSearchResults.innerHTML = `<div class="px-3.5 py-2.5 text-xs text-gray-500">Pegawai "${escapeHtml(query)}" tidak ditemukan.</div>`;
            pegSearchResults.classList.remove('hidden');
            return;
        }

        pegSearchResults.innerHTML = matches.map(p => `
            <button
                type="button"
                data-peg-id="${escapeHtml(p.id)}"
                class="w-full text-left px-3.5 py-2 hover:bg-emerald-50 focus:bg-emerald-50 transition flex flex-col gap-0.5"
            >
                <div class="text-xs font-bold text-gray-900">${escapeHtml(p.nama_lengkap)}</div>
                <div class="text-[11px] font-mono text-gray-600">NIP. ${escapeHtml(p.nip)}</div>
                <div class="text-[11px] text-gray-500">${escapeHtml(p.jabatan || '-')} • ${escapeHtml(p.nama_unit || '-')}</div>
            </button>
        `).join('');

        pegSearchResults.classList.remove('hidden');
    }

    function fillUserFromPegawai(pegId) {
        const p = pegawaiListForUser.find(item => String(item.id) === String(pegId));
        if (!p) return;

        document.getElementById('user_nama').value = p.nama_lengkap || '';
        document.getElementById('user_nip').value = p.nip || '';
        document.getElementById('user_jabatan').value = p.jabatan || '';
        document.getElementById('user_wa').value = p.no_wa || '';

        const unitSelect = document.getElementById('user_unit');
        if (unitSelect && p.unit_kerja_id) {
            unitSelect.value = String(p.unit_kerja_id);
        }

        const usernameInput = document.getElementById('user_username');
        if (usernameInput && !usernameInput.value.trim() && p.nip) {
            usernameInput.value = String(p.nip).trim();
        }

        if (pegSearchInput) {
            pegSearchInput.value = `${p.nama_lengkap} - ${p.nip}`;
        }
        if (clearPegSearchBtn) clearPegSearchBtn.classList.remove('hidden');
        if (pegSearchResults) pegSearchResults.classList.add('hidden');
    }

    if (pegSearchInput) {
        pegSearchInput.addEventListener('focus', () => renderPegawaiSearch(pegSearchInput.value));
        pegSearchInput.addEventListener('input', () => renderPegawaiSearch(pegSearchInput.value));
    }

    if (pegSearchResults) {
        pegSearchResults.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-peg-id]');
            if (!btn) return;
            fillUserFromPegawai(btn.getAttribute('data-peg-id'));
        });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#pegawaiSearchInput') && !e.target.closest('#pegawaiSearchResults') && !e.target.closest('#clearPegawaiSearchBtn')) {
            if (pegSearchResults) pegSearchResults.classList.add('hidden');
        }
    });

    function openUserModal(data = null) {
        const modal = document.getElementById('userModal');
        const title = document.getElementById('userModalTitle');
        const submitLabel = document.getElementById('userSubmitLabel');
        const searchSection = document.getElementById('pegawaiSearchSection');

        if (data) {
            title.textContent = 'Edit Akun User';
            submitLabel.textContent = 'Simpan Perubahan';
            if (searchSection) searchSection.classList.add('hidden');
            document.getElementById('user_id').value = data.id || 0;
            document.getElementById('user_nama').value = data.nama_lengkap || '';
            document.getElementById('user_username').value = data.username || '';
            document.getElementById('user_password').value = data.password || 'password123';
            document.getElementById('user_nip').value = data.nip || '';
            document.getElementById('user_jabatan').value = data.jabatan || '';
            document.getElementById('user_unit').value = data.unit_kerja_id || defaultUserUnitId;
            document.getElementById('user_role').value = (data.role || 'operator').toLowerCase();
            document.getElementById('user_wa').value = data.no_wa || '';
        } else {
            title.textContent = 'Tambah Akun User Baru';
            submitLabel.textContent = 'Simpan Perubahan';
            if (searchSection) searchSection.classList.remove('hidden');
            clearPegawaiSearch();
            document.getElementById('user_id').value = 0;
            document.getElementById('user_nama').value = '';
            document.getElementById('user_username').value = '';
            document.getElementById('user_password').value = 'password123';
            document.getElementById('user_nip').value = '';
            document.getElementById('user_jabatan').value = '';
            document.getElementById('user_unit').value = defaultUserUnitId;
            document.getElementById('user_role').value = 'operator';
            document.getElementById('user_wa').value = '';
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            if (!data && pegSearchInput) pegSearchInput.focus();
            else document.getElementById('user_nama').focus();
        }, 50);
    }

    function closeUserModal() {
        const modal = document.getElementById('userModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (pegSearchResults) pegSearchResults.classList.add('hidden');
        if (window.location.search.includes('edit=')) {
            window.history.replaceState({}, '', 'users.php');
        }
    }

    function updateWaPreview() {
        const tpl = document.getElementById('waTemplateInput')?.value || '';
        const preview = tpl
            .replaceAll('{nama}', 'Siti Aminah, S.Kom.')
            .replaceAll('{jenis}', 'Cuti Tahunan')
            .replaceAll('{status}', 'Disetujui')
            .replaceAll('{tanggal}', '24 November 2026')
            .replaceAll('{lama}', '3 Hari')
            .replaceAll('{nomor_surat}', '800/123/Kemenag.28.02/KP.07.1/11/2026');
        const box = document.getElementById('waPreviewBox');
        if (box) box.textContent = preview;
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeUserModal();
        }
    });

    updateWaPreview();
    </script>
</div>
<?php include 'layout_footer.php'; ?>