<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 * SESSION HARDENING (dipertahankan dari sistem sebelumnya)
 * ═══════════════════════════════════════════════════════════════════
 */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';

/* ───────────────────────── CSRF PROTECTION ───────────────────────── */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_verify() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!(!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent))) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'msg' => 'Sesi tidak valid atau kedaluwarsa (CSRF). Silakan muat ulang halaman.']);
        exit();
    }
}
function csrf_valid_form() {
    $sent = $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $sent);
}

/* ───────────────────── LOGIN RATE LIMITING / LOCKOUT ─────────────── */
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

function isAccountLocked($user) {
    if (empty($user['locked_until'])) return false;
    return strtotime($user['locked_until']) > time();
}
function registerFailedLogin($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT failed_attempts FROM users WHERE id=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $attempts = (int)($row['failed_attempts'] ?? 0) + 1;
    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
        $stmt = $conn->prepare("UPDATE users SET failed_attempts=?, locked_until=? WHERE id=?");
        $stmt->bind_param("isi", $attempts, $lockedUntil, $userId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET failed_attempts=? WHERE id=?");
        $stmt->bind_param("ii", $attempts, $userId);
    }
    $stmt->execute();
    return $attempts;
}
function resetFailedLogin($userId) {
    global $conn;
    $stmt = $conn->prepare("UPDATE users SET failed_attempts=0, locked_until=NULL WHERE id=?");
    $stmt->bind_param("i", $userId); $stmt->execute();
}

/* ───────────────────────── SESI / IDENTITAS ───────────────────────── */
function isLoggedIn() { return isset($_SESSION['user_id']); }

function requireLogin() {
    if (!isLoggedIn()) { header('Location: index.php'); exit(); }
}

/** Ambil data user + nama & kode peran (role) dalam satu query (JOIN roles). */
function getCurrentUser() {
    global $conn;
    if (!isLoggedIn()) return null;
    static $cached = null;
    if ($cached !== null) return $cached;
    $stmt = $conn->prepare("SELECT u.*, r.kode AS role, r.nama_role, su.nama_unit, su.tipe AS unit_tipe
                             FROM users u
                             JOIN roles r ON r.id = u.role_id
                             LEFT JOIN struktur_unit su ON su.id = u.unit_id
                             WHERE u.id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $cached = $stmt->get_result()->fetch_assoc();
    return $cached;
}

/* ═══════════════════════════════════════════════════════════════════
 * RBAC — MATRIKS HAK AKSES (role x modul x aksi) PERSIS SESUAI STRUKTUR
 * ORGANISASI PADA KETIGA DOKUMEN (Model 1, Model 2, KAK Project Work).
 * Sumber kebenaran: tabel `hak_akses` (role_id, modul, aksi).
 * ═══════════════════════════════════════════════════════════════════ */

/** Cache hak akses per-role dalam satu request supaya hemat query. */
function getRoleAccessMatrix($role) {
    global $conn;
    static $cache = [];
    if (isset($cache[$role])) return $cache[$role];
    $stmt = $conn->prepare("SELECT ha.modul, ha.aksi FROM hak_akses ha
                             JOIN roles r ON r.id = ha.role_id WHERE r.kode=?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $res = $stmt->get_result();
    $matrix = [];
    while ($row = $res->fetch_assoc()) {
        $matrix[$row['modul']][] = $row['aksi'];
    }
    $cache[$role] = $matrix;
    return $matrix;
}

/** Apakah role memiliki aksi tertentu pada modul tertentu? */
function hasAccess($role, $modul, $aksi) {
    $matrix = getRoleAccessMatrix($role);
    return isset($matrix[$modul]) && in_array($aksi, $matrix[$modul], true);
}

/** Wajibkan hak akses tertentu — hentikan eksekusi (403) jika tidak punya. */
function requireAccess($modul, $aksi) {
    $user = getCurrentUser();
    if (!$user || !hasAccess($user['role'], $modul, $aksi)) {
        http_response_code(403);
        if (php_sapi_name() !== 'cli' && stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'msg' => 'Anda tidak memiliki hak akses untuk aksi ini.']);
        } else {
            echo '<div style="padding:40px;font-family:sans-serif">Anda tidak memiliki hak akses ke halaman ini.</div>';
        }
        exit();
    }
}

/** Daftar modul yang BISA DILIHAT oleh sebuah role (untuk sidebar & dashboard). */
function getRoleModules($role) {
    $matrix = getRoleAccessMatrix($role);
    return array_keys($matrix);
}

/** Label modul untuk tampilan UI. */
function getModuleLabel($modul) {
    $labels = [
        'inventaris'     => 'Inventaris & Peminjaman Barang',
        'karyawan'       => 'Manajemen Karyawan',
        'event'          => 'Event Management System',
        'maintenance'    => 'Maintenance Fasilitas',
        'arsip'          => 'Arsip Digital & Pelaporan Analitik',
        'laporan'        => 'Laporan & Monitoring',
        'pengajuan_unit' => 'Pengajuan Lintas Divisi',
    ];
    return $labels[$modul] ?? ucfirst($modul);
}
function getModuleIcon($modul) {
    $icons = [
        'inventaris' => 'package', 'karyawan' => 'users', 'event' => 'calendar',
        'maintenance' => 'wrench', 'arsip' => 'archive', 'laporan' => 'bar-chart-2',
        'pengajuan_unit' => 'file-text',
    ];
    return $icons[$modul] ?? 'circle';
}
function getModulePage($modul) {
    $pages = [
        'inventaris' => 'inventory', 'karyawan' => 'karyawan', 'event' => 'event',
        'maintenance' => 'maintenance', 'arsip' => 'arsip', 'laporan' => 'laporan',
        'pengajuan_unit' => 'pengajuan_unit',
    ];
    return $pages[$modul] ?? $modul;
}

/** Nama peran (untuk badge / tampilan) — diambil dari tabel roles. */
function getRoleName($role) {
    global $conn;
    static $cache = [];
    if (isset($cache[$role])) return $cache[$role];
    $stmt = $conn->prepare("SELECT nama_role FROM roles WHERE kode=?");
    $stmt->bind_param("s", $role); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $cache[$role] = $row['nama_role'] ?? $role;
}

/** Warna badge per kelompok peran (untuk konsistensi visual saja). */
function getRoleColor($role) {
    $groups = [
        'admin' => '#111827',
        'manajer_ga' => '#059669', 'spv_inventaris' => '#10b981', 'staff_inventaris' => '#34d399',
        'spv_sapras' => '#0d9488', 'team_leader' => '#14b8a6', 'engineering' => '#2dd4bf',
        'cso' => '#5eead4', 'gardener' => '#5eead4',
        'manajer_hrd' => '#9b59b6', 'staff_hrd' => '#c39bd3',
        'manajer_markom' => '#f39c12', 'spv_crm' => '#f59e0b', 'staff_crm' => '#fbbf24',
        'manajer_qms' => '#7f8c8d', 'staff_qms' => '#94a3b8',
        'tu' => '#3498db', 'kepala_sekolah' => '#c0392b',
        'pengguna_unit' => '#64748b',
    ];
    return $groups[$role] ?? '#95a5a6';
}

/* ═══════════════════════════════════════════════════════════════════
 * JEMBATAN PENGAJUAN LINTAS DIVISI (modul `pengajuan_unit`)
 * Setiap divisi yang BUKAN pengelola langsung modul Inventaris/
 * Maintenance/EMS mengajukan kebutuhan lewat sini; atasan langsungnya
 * (approver_role) wajib approve SEBELUM pengajuan diteruskan ke
 * pengelola modul terkait — persis pola yang berulang di seluruh
 * dokumen rancangan hak akses (bukan cuma TU -> Kepala Sekolah).
 * ═══════════════════════════════════════════════════════════════════ */

/** Role atasan yang wajib approve pengajuan sebelum diteruskan. */
function getPengajuanApproverRole($submitterRole) {
    $map = [
        'tu'               => 'kepala_sekolah',
        'staff_hrd'        => 'manajer_hrd',
        'staff_qms'        => 'manajer_qms',
        'spv_crm'          => 'manajer_markom',
        'team_leader'      => 'spv_sapras',
        'staff_inventaris' => 'spv_inventaris',
    ];
    return $map[$submitterRole] ?? null;
}

/** Jenis kebutuhan (modul) yang boleh diajukan role tsb lewat jembatan ini. */
function getPengajuanAllowedModules($submitterRole) {
    $map = [
        'tu'               => ['inventaris', 'maintenance', 'event'],
        'staff_hrd'        => ['inventaris', 'maintenance', 'event'],
        'staff_qms'        => ['inventaris', 'maintenance'],
        'spv_crm'          => ['inventaris', 'maintenance'],
        'team_leader'      => ['inventaris'],
        'staff_inventaris' => ['maintenance'],
    ];
    return $map[$submitterRole] ?? [];
}

/** Semua daftar role (untuk form registrasi / kelola akun oleh admin). */
function getAllRoles() {
    global $conn;
    $res = $conn->query("SELECT id, kode, nama_role FROM roles ORDER BY id");
    return $res->fetch_all(MYSQLI_ASSOC);
}

/* ───────────────────────── AKTIVITAS & NOTIFIKASI ───────────────────────── */
function logAktivitas($userId, $modul, $aktivitas) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO log_aktivitas (user_id, modul, aktivitas) VALUES (?,?,?)");
    $stmt->bind_param("iss", $userId, $modul, $aktivitas);
    $stmt->execute();
}
function kirimNotifikasi($userId, $modulAsal, $pesan) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO notifikasi (user_id, modul_asal, pesan) VALUES (?,?,?)");
    $stmt->bind_param("iss", $userId, $modulAsal, $pesan);
    $stmt->execute();
}

/** Cari user_id pemegang suatu role (dipakai untuk auto-notify approver berikutnya).
 *  Jika role terikat unit (mis. kepala_sekolah per unit), filter juga by unit_id. */
function getUserIdsByRole($role, $unitId = null) {
    global $conn;
    if ($unitId) {
        $stmt = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.kode=? AND u.unit_id=? AND u.status='aktif'");
        $stmt->bind_param("si", $role, $unitId);
    } else {
        $stmt = $conn->prepare("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE r.kode=? AND u.status='aktif'");
        $stmt->bind_param("s", $role);
    }
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
}
function notifyRole($role, $modulAsal, $pesan, $unitId = null) {
    foreach (getUserIdsByRole($role, $unitId) as $uid) kirimNotifikasi($uid, $modulAsal, $pesan);
}
