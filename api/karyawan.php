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

if (!hasAccess($role, 'karyawan', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Karyawan.');

switch ($action) {

    case 'list':
        $sql = "SELECT k.*, su.nama_unit, a.nama AS atasan_nama, u.username
                FROM karyawan k
                LEFT JOIN struktur_unit su ON su.id = k.divisi_id
                LEFT JOIN karyawan a ON a.id = k.atasan_id
                LEFT JOIN users u ON u.id = k.user_id
                ORDER BY k.id";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'tambah':
        if (!hasAccess($role, 'karyawan', 'tambah')) fail('Akses ditolak.');
        $nip = trim($_POST['nip'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $divisiId = (int)($_POST['divisi_id'] ?? 0) ?: null;
        $atasanId = (int)($_POST['atasan_id'] ?? 0) ?: null;
        if (!$nip || !$nama) fail('NIP dan nama wajib diisi.');
        $stmt = $conn->prepare("INSERT INTO karyawan (nip, nama, jabatan, divisi_id, atasan_id, status) VALUES (?,?,?,?,?, 'aktif')");
        $stmt->bind_param("sssii", $nip, $nama, $jabatan, $divisiId, $atasanId);
        $stmt->execute();
        logAktivitas($user['id'], 'karyawan', "Menambahkan data karyawan $nama");
        ok(['id' => $stmt->insert_id]);

    case 'update':
        if (!hasAccess($role, 'karyawan', 'kelola')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        $status = $_POST['status'] ?? 'aktif';
        $stmt = $conn->prepare("UPDATE karyawan SET nama=?, jabatan=?, status=? WHERE id=?");
        $stmt->bind_param("sssi", $nama, $jabatan, $status, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'karyawan', "Memperbarui data karyawan #$id");
        ok();

    case 'list_kegiatan':
        $karyawanId = (int)($_GET['karyawan_id'] ?? 0);
        $sql = "SELECT kk.*, k.nama AS karyawan_nama, u.name AS pemberi_nama
                FROM kegiatan_karyawan kk
                JOIN karyawan k ON k.id = kk.karyawan_id
                LEFT JOIN users u ON u.id = kk.assigned_by";
        if ($karyawanId) $sql .= " WHERE kk.karyawan_id=$karyawanId";
        elseif (in_array($role, ['cso', 'gardener'])) {
            $mine = $conn->query("SELECT id FROM karyawan WHERE user_id=" . $user['id'])->fetch_assoc();
            $sql .= " WHERE kk.karyawan_id=" . ($mine['id'] ?? 0);
        }
        $sql .= " ORDER BY kk.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'assign_kegiatan':
        if (!hasAccess($role, 'karyawan', 'kelola')) fail('Akses ditolak.');
        $karyawanId = (int)($_POST['karyawan_id'] ?? 0);
        $jenis = $_POST['jenis'] ?? 'rutin';
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        if (!$karyawanId || !$deskripsi) fail('Karyawan dan deskripsi kegiatan wajib diisi.');
        $stmt = $conn->prepare("INSERT INTO kegiatan_karyawan (karyawan_id, assigned_by, jenis, deskripsi, tanggal, status) VALUES (?,?,?,?,?, 'ditugaskan')");
        $stmt->bind_param("iisss", $karyawanId, $user['id'], $jenis, $deskripsi, $tanggal);
        $stmt->execute();
        $kar = $conn->query("SELECT user_id FROM karyawan WHERE id=$karyawanId")->fetch_assoc();
        if ($kar && $kar['user_id']) kirimNotifikasi($kar['user_id'], 'karyawan', 'Anda mendapat kegiatan baru dari atasan.');
        logAktivitas($user['id'], 'karyawan', "Memberikan kegiatan kepada karyawan #$karyawanId");
        ok(['id' => $stmt->insert_id]);

    case 'input_hasil':
        $id = (int)($_POST['id'] ?? 0);
        $hasil = trim($_POST['hasil_laporan'] ?? '');
        $kk = $conn->query("SELECT kk.*, k.user_id FROM kegiatan_karyawan kk JOIN karyawan k ON k.id=kk.karyawan_id WHERE kk.id=$id")->fetch_assoc();
        if (!$kk) fail('Kegiatan tidak ditemukan.');
        if ($kk['user_id'] != $user['id'] && !hasAccess($role, 'karyawan', 'kelola')) fail('Akses ditolak.');
        $stmt = $conn->prepare("UPDATE kegiatan_karyawan SET hasil_laporan=?, status='selesai' WHERE id=?");
        $stmt->bind_param("si", $hasil, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'karyawan', "Menginput hasil kegiatan #$id");
        ok();

    case 'nilai_kinerja':
        if (!hasAccess($role, 'karyawan', 'penilaian')) fail('Akses ditolak.');
        $karyawanId = (int)($_POST['karyawan_id'] ?? 0);
        $periode = $_POST['periode'] ?? date('Y-m');
        $nilai = (int)($_POST['nilai'] ?? 0);
        $catatan = trim($_POST['catatan'] ?? '');
        if (!$karyawanId || $nilai < 0 || $nilai > 100) fail('Data penilaian tidak valid.');
        $stmt = $conn->prepare("INSERT INTO penilaian_kinerja (karyawan_id, penilai_id, periode, nilai, catatan) VALUES (?,?,?,?,?)");
        $stmt->bind_param("iisis", $karyawanId, $user['id'], $periode, $nilai, $catatan);
        $stmt->execute();
        logAktivitas($user['id'], 'karyawan', "Memberikan penilaian kinerja karyawan #$karyawanId");
        ok();

    case 'list_penilaian':
        $karyawanId = (int)($_GET['karyawan_id'] ?? 0);
        $sql = "SELECT pk.*, k.nama AS karyawan_nama, u.name AS penilai_nama
                FROM penilaian_kinerja pk JOIN karyawan k ON k.id=pk.karyawan_id JOIN users u ON u.id=pk.penilai_id";
        if ($karyawanId) $sql .= " WHERE pk.karyawan_id=$karyawanId";
        $sql .= " ORDER BY pk.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    default:
        fail('Aksi tidak dikenal.');
}
