<?php
/**
 * CS & User Management Dashboard - An-Namiroh & Alhidayah
 * Host: http://an.alhidayah.id/cs-admin/
 * Database: MySQL Live (u2314635_laravel8_xero)
 */

session_start();

$dbHost = "127.0.0.1";
$dbName = "u2314635_laravel8_xero";
$dbUser = "u2314635_isal";
$dbPass = "Mojokerto123";

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . htmlspecialchars($e->getMessage()));
}

// 1. Inisialisasi Tabel Otomatis
$pdo->exec("
CREATE TABLE IF NOT EXISTS bot_users_cs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_user_id BIGINT UNIQUE NOT NULL,
    username VARCHAR(100) NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('cs', 'supervisor', 'admin', 'jamaah') DEFAULT 'cs',
    is_active TINYINT DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS bot_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_code VARCHAR(50) UNIQUE NOT NULL,
    jamaah_user_id BIGINT NOT NULL,
    jamaah_name VARCHAR(150) NOT NULL,
    cs_user_id BIGINT NULL,
    cs_name VARCHAR(150) NULL,
    category VARCHAR(50) NOT NULL,
    package_name VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'WAITING_CLAIM',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    claimed_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Pre-populate data awal jika kosong (Dr Yasin & Isal)
$checkCount = $pdo->query("SELECT COUNT(*) FROM bot_users_cs")->fetchColumn();
if ($checkCount == 0) {
    $pdo->exec("
        INSERT INTO bot_users_cs (telegram_user_id, username, full_name, role, is_active) VALUES
        (53762504, 'dryasin', 'Dr. Moch Yasin', 'admin', 1),
        (800112233, 'isal48', 'Faishal Firman Hakim', 'cs', 1);
    ");
}

// 2. Endpoint API JSON untuk Bot Telegram
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $auth2Fa = $_SERVER['HTTP_X_2FA_CODE'] ?? $_GET['key'] ?? '';
    if ($auth2Fa !== 'wwwfacebookcom1A') {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized 2FA']);
        exit;
    }

    if ($_GET['action'] === 'get_cs_list') {
        $stmt = $pdo->query("SELECT telegram_user_id, username, full_name, role, is_active FROM bot_users_cs WHERE role IN ('cs', 'supervisor', 'admin') AND is_active = 1");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($_GET['action'] === 'save_ticket' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data) {
            $stmt = $pdo->prepare("
                INSERT INTO bot_tickets (ticket_code, jamaah_user_id, jamaah_name, cs_user_id, cs_name, category, package_name, status)
                VALUES (:code, :jid, :jname, :csid, :csname, :cat, :pkg, :status)
                ON DUPLICATE KEY UPDATE status = VALUES(status), cs_user_id = VALUES(cs_user_id), cs_name = VALUES(cs_name)
            ");
            $stmt->execute([
                ':code' => $data['ticket_code'] ?? '',
                ':jid' => $data['jamaah_user_id'] ?? 0,
                ':jname' => $data['jamaah_name'] ?? '',
                ':csid' => $data['cs_user_id'] ?? null,
                ':csname' => $data['cs_name'] ?? null,
                ':cat' => $data['category'] ?? 'GENERAL',
                ':pkg' => $data['package_name'] ?? '',
                ':status' => $data['status'] ?? 'WAITING_CLAIM'
            ]);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
}

// 3. Autentikasi Web Sederhana
$AUTH_PASS = "wwwfacebookcom1A";
if (isset($_POST['login_pass'])) {
    if ($_POST['login_pass'] === $AUTH_PASS) {
        $_SESSION['cs_admin_auth'] = true;
    } else {
        $loginError = "Password Salah!";
    }
}
if (isset($_GET['logout'])) {
    unset($_SESSION['cs_admin_auth']);
    header("Location: index.php");
    exit;
}

$isAuth = !empty($_SESSION['cs_admin_auth']);

// 4. Proses Form CRUD User jika Auth
$msg = '';
if ($isAuth && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_add'])) {
        $tgId = trim($_POST['telegram_user_id']);
        $uname = ltrim(trim($_POST['username']), '@');
        $name = trim($_POST['full_name']);
        $role = $_POST['role'] ?? 'cs';
        if ($tgId && $name) {
            $stmt = $pdo->prepare("
                INSERT INTO bot_users_cs (telegram_user_id, username, full_name, role, is_active)
                VALUES (:id, :uname, :name, :role, 1)
                ON DUPLICATE KEY UPDATE username = VALUES(username), full_name = VALUES(full_name), role = VALUES(role), is_active = 1
            ");
            $stmt->execute([':id' => $tgId, ':uname' => $uname, ':name' => $name, ':role' => $role]);
            $msg = "✅ Pengguna <b>{$name}</b> berhasil disimpan!";
        }
    } elseif (isset($_POST['action_toggle'])) {
        $id = (int)$_POST['user_id'];
        $cur = (int)$_POST['current_status'];
        $new = $cur === 1 ? 0 : 1;
        $pdo->prepare("UPDATE bot_users_cs SET is_active = ? WHERE id = ?")->execute([$new, $id]);
        $msg = "Status user berhasil diubah!";
    } elseif (isset($_POST['action_role'])) {
        $id = (int)$_POST['user_id'];
        $newRole = $_POST['new_role'];
        $pdo->prepare("UPDATE bot_users_cs SET role = ? WHERE id = ?")->execute([$newRole, $id]);
        $msg = "Peran user berhasil diperbarui menjadi {$newRole}!";
    } elseif (isset($_POST['action_delete'])) {
        $id = (int)$_POST['user_id'];
        $pdo->prepare("DELETE FROM bot_users_cs WHERE id = ?")->execute([$id]);
        $msg = "User berhasil dihapus!";
    }
}

$users = $pdo->query("SELECT * FROM bot_users_cs ORDER BY role ASC, created_at DESC")->fetchAll();
$tickets = $pdo->query("SELECT * FROM bot_tickets ORDER BY id DESC LIMIT 30")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CS & User Management Dashboard - An-Namiroh</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen font-sans">

<?php if (!$isAuth): ?>
<div class="flex items-center justify-center min-h-screen p-4">
    <div class="bg-slate-800 p-8 rounded-2xl shadow-2xl max-w-md w-full border border-slate-700">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                <i class="fa-solid fa-headset text-2xl text-white"></i>
            </div>
            <h1 class="text-2xl font-bold text-emerald-400">CS Control Center</h1>
            <p class="text-xs text-slate-400 mt-1">An-Namiroh & Alhidayah Telegram Bot</p>
        </div>
        <?php if (!empty($loginError)): ?>
            <div class="bg-rose-900/50 border border-rose-500 text-rose-300 text-xs p-3 rounded-lg mb-4 text-center">
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <label class="block text-xs text-slate-300 mb-1">Passkey Akses:</label>
            <input type="password" name="login_pass" required placeholder="Masukkan kata sandi..." class="w-full bg-slate-950 border border-slate-700 rounded-lg px-4 py-2.5 text-sm text-white mb-4 focus:outline-none focus:border-emerald-500">
            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-semibold py-2.5 rounded-lg text-sm shadow transition">Masuk ke Dashboard</button>
        </form>
    </div>
</div>
<?php exit; endif; ?>

<!-- Navbar -->
<nav class="bg-slate-800/80 backdrop-blur border-b border-slate-700 sticky top-0 z-50 px-6 py-3.5 flex justify-between items-center">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-emerald-600 rounded-xl flex items-center justify-center shadow">
            <i class="fa-solid fa-kaaba text-lg text-white"></i>
        </div>
        <div>
            <h1 class="text-base font-bold text-white leading-tight">An-Namiroh CS Control Center</h1>
            <p class="text-[11px] text-emerald-400">Database: MySQL Live (an.alhidayah.id)</p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <a href="?logout=1" class="text-xs bg-slate-700 hover:bg-rose-600 text-slate-200 px-3 py-1.5 rounded-lg transition"><i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Logout</a>
    </div>
</nav>

<div class="max-w-7xl mx-auto p-6 space-y-6">

    <?php if ($msg): ?>
    <div class="bg-emerald-950/60 border border-emerald-500 text-emerald-300 p-3.5 rounded-xl text-sm flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-emerald-400"></i> <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- Form Tambah CS / User Baru -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 shadow-lg">
        <h2 class="text-sm font-bold text-emerald-400 uppercase tracking-wider mb-4 flex items-center gap-2">
            <i class="fa-solid fa-user-plus"></i> Tambah / Daftarkan Pengguna (CS / Jamaah / Supervisor)
        </h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <input type="hidden" name="action_add" value="1">
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Telegram User ID *</label>
                <input type="number" name="telegram_user_id" required placeholder="Contoh: 53762504" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Username Telegram</label>
                <input type="text" name="username" placeholder="@isal48 (tanpa @)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Nama Lengkap *</label>
                <input type="text" name="full_name" required placeholder="Faishal Firman Hakim" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-[11px] text-slate-400 mb-1">Peran (Role) *</label>
                <select name="role" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
                    <option value="cs" selected>Staf CS (Melayani Tiket)</option>
                    <option value="supervisor">Supervisor</option>
                    <option value="admin">Administrator</option>
                    <option value="jamaah">Jamaah Biasa</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-2 rounded-lg text-xs transition shadow flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-save"></i> Simpan User
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel Daftar Pengguna & CS -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 shadow-lg">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-emerald-400"></i> Direktori Pengguna & Staf CS Terdaftar
                <span class="text-xs bg-slate-700 text-slate-300 px-2 py-0.5 rounded-full"><?= count($users) ?> Orang</span>
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 uppercase text-[10px] tracking-wider bg-slate-900/50">
                        <th class="p-3">Pengguna</th>
                        <th class="p-3">Telegram ID</th>
                        <th class="p-3">Peran (Role)</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Aksi Manajemen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="p-4 text-center text-slate-500">Belum ada user di database. Silakan tambahkan di atas.</td></tr>
                    <?php else: foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-750 transition">
                        <td class="p-3">
                            <div class="font-semibold text-white"><?= htmlspecialchars($u['full_name']) ?></div>
                            <div class="text-[11px] text-slate-400">@<?= htmlspecialchars($u['username'] ?: 'no_username') ?></div>
                        </td>
                        <td class="p-3 font-mono text-emerald-400"><?= $u['telegram_user_id'] ?></td>
                        <td class="p-3">
                            <form method="POST" class="inline-flex items-center gap-1">
                                <input type="hidden" name="action_role" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <select name="new_role" onchange="this.form.submit()" class="bg-slate-900 text-[11px] border border-slate-700 rounded px-2 py-1 text-slate-200">
                                    <option value="cs" <?= $u['role'] === 'cs' ? 'selected' : '' ?>>🎧 Staf CS</option>
                                    <option value="supervisor" <?= $u['role'] === 'supervisor' ? 'selected' : '' ?>>⭐ Supervisor</option>
                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>👑 Admin</option>
                                    <option value="jamaah" <?= $u['role'] === 'jamaah' ? 'selected' : '' ?>>👤 Jamaah</option>
                                </select>
                            </form>
                        </td>
                        <td class="p-3">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action_toggle" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $u['is_active'] ?>">
                                <button type="submit" class="px-2.5 py-1 rounded text-[11px] font-semibold transition <?= $u['is_active'] ? 'bg-emerald-900/60 text-emerald-300 border border-emerald-600 hover:bg-emerald-800' : 'bg-rose-900/60 text-rose-300 border border-rose-600 hover:bg-rose-800' ?>">
                                    <?= $u['is_active'] ? '● Aktif' : '○ Non-Aktif' ?>
                                </button>
                            </form>
                        </td>
                        <td class="p-3 text-right">
                            <form method="POST" class="inline" onsubmit="return confirm('Hapus user ini?')">
                                <input type="hidden" name="action_delete" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="text-rose-400 hover:text-rose-300 p-1 rounded transition"><i class="fa-solid fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel Monitoring Tiket Masuk Live -->
    <div class="bg-slate-800 border border-slate-700 rounded-2xl p-5 shadow-lg">
        <h2 class="text-sm font-bold text-white flex items-center gap-2 mb-4">
            <i class="fa-solid fa-ticket text-emerald-400"></i> Monitoring Tiket & SLA Live (Terakhir 30 Tiket)
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 uppercase text-[10px] tracking-wider bg-slate-900/50">
                        <th class="p-3">Kode Tiket</th>
                        <th class="p-3">Jamaah</th>
                        <th class="p-3">Kategori & Paket</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">CS Penanggung Jawab</th>
                        <th class="p-3">Waktu Masuk</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/60">
                    <?php if (empty($tickets)): ?>
                    <tr><td colspan="6" class="p-4 text-center text-slate-500">Belum ada tiket transaksi aktif di server MySQL.</td></tr>
                    <?php else: foreach ($tickets as $t): ?>
                    <tr class="hover:bg-slate-750 transition">
                        <td class="p-3 font-mono text-emerald-400 font-bold"><?= htmlspecialchars($t['ticket_code']) ?></td>
                        <td class="p-3"><?= htmlspecialchars($t['jamaah_name']) ?></td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] bg-slate-700 text-slate-300"><?= htmlspecialchars($t['category']) ?></span>
                            <div class="text-[11px] text-slate-400 mt-0.5"><?= htmlspecialchars($t['package_name'] ?: '-') ?></div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $t['status'] === 'CLAIMED' ? 'bg-blue-900/60 text-blue-300 border border-blue-600' : ($t['status'] === 'CLOSED' ? 'bg-slate-700 text-slate-300' : 'bg-amber-900/60 text-amber-300 border border-amber-600') ?>">
                                <?= htmlspecialchars($t['status']) ?>
                            </span>
                        </td>
                        <td class="p-3 text-slate-300"><?= htmlspecialchars($t['cs_name'] ?: 'Menunggu Klaim') ?></td>
                        <td class="p-3 text-slate-400"><?= htmlspecialchars($t['created_at']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
