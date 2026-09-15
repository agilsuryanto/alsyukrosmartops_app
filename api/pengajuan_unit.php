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

if (!hasAccess($role, 'pengajuan_unit', 'lihat')) fail('Anda tidak memiliki hak akses ke halaman ini.');

switch ($action) {

    case 'list':
        $sql = "SELECT pu.*, su.nama_unit, u1.name AS pemohon_nama, r1.nama_role AS pemohon_role,
                       u2.name AS approver_nama
                FROM pengajuan_unit pu
                LEFT JOIN struktur_unit su ON su.id = pu.unit_id
                JOIN users u1 ON u1.id = pu.pemohon_id
                JOIN roles r1 ON r1.id = u1.role_id
                LEFT JOIN users u2 ON u2.id = pu.approver_id";
        if (hasAccess($role, 'pengajuan_unit', 'approve')) {
            // Atasan hanya melihat pengajuan yang memang menjadi tanggung jawabnya untuk di-approve.
            $sql .= " WHERE pu.approver_role='" . $conn->real_escape_string($role) . "'";
            if ($role === 'kepala_sekolah') $sql .= " AND pu.unit_id=" . (int)$user['unit_id'];
        } else {
            // Pengaju hanya melihat pengajuannya sendiri.
            $sql .= " WHERE pu.pemohon_id=" . (int)$user['id'];
        }
        $sql .= " ORDER BY pu.id DESC";
        ok($conn->query($sql)->fetch_all(MYSQLI_ASSOC));

    case 'ajukan':
        if (!hasAccess($role, 'pengajuan_unit', 'ajukan')) fail('Akses ditolak.');
        $approverRole = getPengajuanApproverRole($role);
        if (!$approverRole) fail('Peran Anda tidak memiliki alur pengajuan lintas divisi.');
        $allowed = getPengajuanAllowedModules($role);
        $jenis = $_POST['jenis_modul'] ?? '';
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if (!in_array($jenis, $allowed, true)) fail('Jenis pengajuan tidak diizinkan untuk peran Anda.');
        if (!$deskripsi) fail('Deskripsi kebutuhan wajib diisi.');
        if ($approverRole === 'kepala_sekolah' && !$user['unit_id']) fail('Akun Anda belum terdaftar pada unit pendidikan manapun.');

        $stmt = $conn->prepare("INSERT INTO pengajuan_unit (unit_id, pemohon_id, approver_role, jenis_modul, deskripsi, status, tanggal_pengajuan) VALUES (?,?,?,?,?, 'diajukan', CURDATE())");
        $unitId = $user['unit_id'] ?: null;
        $stmt->bind_param("iisss", $unitId, $user['id'], $approverRole, $jenis, $deskripsi);
        $stmt->execute();
        logAktivitas($user['id'], 'pengajuan_unit', "Mengajukan kebutuhan ($jenis), menunggu approval atasan");
        notifyRole($approverRole, 'pengajuan_unit', $user['name'] . ' mengajukan kebutuhan baru, mohon di-approve.', $approverRole === 'kepala_sekolah' ? $user['unit_id'] : null);
        ok(['id' => $stmt->insert_id]);

    case 'approve':
        if (!hasAccess($role, 'pengajuan_unit', 'approve')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $catatan = trim($_POST['catatan'] ?? '');
        $pu = $conn->query("SELECT * FROM pengajuan_unit WHERE id=$id")->fetch_assoc();
        if (!$pu || $pu['approver_role'] !== $role) fail('Data tidak ditemukan.');
        if ($role === 'kepala_sekolah' && (int)$pu['unit_id'] !== (int)$user['unit_id']) fail('Data tidak ditemukan.');

        if (!$setuju) {
            $stmt = $conn->prepare("UPDATE pengajuan_unit SET status='ditolak', approver_id=?, catatan_atasan=? WHERE id=?");
            $stmt->bind_param("isi", $user['id'], $catatan, $id);
            $stmt->execute();
            kirimNotifikasi($pu['pemohon_id'], 'pengajuan_unit', 'Pengajuan Anda ditolak oleh atasan.');
            logAktivitas($user['id'], 'pengajuan_unit', "Menolak pengajuan lintas divisi #$id");
            ok();
        }

        // Diteruskan ke modul terkait sesuai alur modul masing-masing
        $refId = null;
        if ($pu['jenis_modul'] === 'maintenance') {
            $fasilitas = $conn->query("SELECT id FROM fasilitas ORDER BY id LIMIT 1")->fetch_assoc();
            $stmt = $conn->prepare("INSERT INTO pengajuan_maintenance (fasilitas_id, pemohon_id, deskripsi_kerusakan, status, tanggal_pengajuan) VALUES (?,?,?, 'diajukan', CURDATE())");
            $fid = $fasilitas['id'] ?? 1;
            $stmt->bind_param("iis", $fid, $pu['pemohon_id'], $pu['deskripsi']);
            $stmt->execute();
            $refId = $stmt->insert_id;
            notifyRole('team_leader', 'maintenance', 'Pengajuan maintenance dari divisi lain diteruskan, mohon diperiksa.');
        } elseif ($pu['jenis_modul'] === 'event') {
            $stmt = $conn->prepare("INSERT INTO event (nama_event, pemohon_id, deskripsi, status) VALUES (?,?,?, 'diajukan')");
            $nama = 'Kegiatan/Event Divisi';
            $stmt->bind_param("sis", $nama, $pu['pemohon_id'], $pu['deskripsi']);
            $stmt->execute();
            $refId = $stmt->insert_id;
            notifyRole('spv_crm', 'event', 'Pengajuan event dari divisi lain diteruskan, mohon diperiksa.');
        } elseif ($pu['jenis_modul'] === 'inventaris') {
            // Diarahkan ke pengelola inventaris untuk ditindaklanjuti sebagai pengajuan peminjaman
            notifyRole('staff_inventaris', 'inventaris', 'Ada kebutuhan barang dari divisi lain (' . $pu['deskripsi'] . '), mohon ditindaklanjuti.');
        }

        $stmt = $conn->prepare("UPDATE pengajuan_unit SET status='diteruskan', approver_id=?, catatan_atasan=?, ref_id=? WHERE id=?");
        $stmt->bind_param("isii", $user['id'], $catatan, $refId, $id);
        $stmt->execute();
        kirimNotifikasi($pu['pemohon_id'], 'pengajuan_unit', 'Pengajuan Anda disetujui atasan dan diteruskan ke pengelola modul.');
        logAktivitas($user['id'], 'pengajuan_unit', "Menyetujui & meneruskan pengajuan lintas divisi #$id");
        ok();

    default:
        fail('Aksi tidak dikenal.');
}
