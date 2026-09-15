<?php
require_once '../config/auth.php';
requireLogin();
csrf_verify();
header('Content-Type: application/json');

$user = getCurrentUser();
$role = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function ok($data = []) { echo json_encode(['ok' => true, 'data' => $data]); exit(); }
function fail($msg) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $msg]); exit(); }

if (!hasAccess($role, 'arsip', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Arsip.');

// Pengguna dengan tingkat akses terbatas hanya melihat dokumen 'umum' + miliknya sendiri
function visibleWhere($role, $userId) {
    if (hasAccess($role, 'arsip', 'kelola')) return "1=1";
    return "(tingkat_akses='umum' OR uploader_id=$userId)";
}

switch ($action) {

    case 'list_kategori':
        ok($conn->query("SELECT * FROM kategori_arsip ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC));

    case 'list':
        $where = visibleWhere($role, $user['id']);
        $sql = "SELECT a.*, ka.nama_kategori, u.name AS uploader_nama FROM arsip a
                LEFT JOIN kategori_arsip ka ON ka.id=a.kategori_id
                JOIN users u ON u.id=a.uploader_id
                WHERE $where ORDER BY a.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'tambah_kategori':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $nama = trim($_POST['nama_kategori'] ?? '');
        if (!$nama) fail('Nama kategori wajib diisi.');
        $desk = trim($_POST['deskripsi'] ?? '');
        $stmt = $conn->prepare("INSERT INTO kategori_arsip (nama_kategori, deskripsi) VALUES (?,?)");
        $stmt->bind_param("ss", $nama, $desk);
        $stmt->execute();
        ok(['id' => $stmt->insert_id]);

    case 'upload':
        if (!hasAccess($role, 'arsip', 'tambah') && !hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $judul = trim($_POST['judul_dokumen'] ?? '');
        $kategoriId = (int)($_POST['kategori_id'] ?? 0) ?: null;
        $tingkat = $_POST['tingkat_akses'] ?? 'umum';
        if (!$judul) fail('Judul dokumen wajib diisi.');

        $filePath = null;
        if (!empty($_FILES['file']['name'])) {
            $dir = __DIR__ . '/../uploads/arsip/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            $safeName = uniqid('arsip_') . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $safeName)) {
                $filePath = 'uploads/arsip/' . $safeName;
            }
        }
        if (!$filePath) $filePath = 'uploads/arsip/(belum ada file)';

        $stmt = $conn->prepare("INSERT INTO arsip (judul_dokumen, kategori_id, file_path, uploader_id, tingkat_akses) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sisis", $judul, $kategoriId, $filePath, $user['id'], $tingkat);
        $stmt->execute();
        logAktivitas($user['id'], 'arsip', "Mengunggah dokumen: $judul");
        ok(['id' => $stmt->insert_id]);

    case 'backup':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $arsipId = (int)($_POST['arsip_id'] ?? 0);
        $a = $conn->query("SELECT * FROM arsip WHERE id=$arsipId")->fetch_assoc();
        if (!$a) fail('Dokumen tidak ditemukan.');
        $lokasi = 'backups/arsip_' . $arsipId . '_' . date('Ymd_His') . '.bak';
        $stmt = $conn->prepare("INSERT INTO backup_arsip (arsip_id, lokasi_backup) VALUES (?,?)");
        $stmt->bind_param("is", $arsipId, $lokasi);
        $stmt->execute();
        logAktivitas($user['id'], 'arsip', "Backup dokumen #$arsipId");
        ok(['lokasi' => $lokasi]);

    case 'list_backup':
        if (!hasAccess($role, 'arsip', 'kelola')) fail('Akses ditolak.');
        $sql = "SELECT b.*, a.judul_dokumen FROM backup_arsip b LEFT JOIN arsip a ON a.id=b.arsip_id ORDER BY b.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    default:
        fail('Aksi tidak dikenal.');
}
