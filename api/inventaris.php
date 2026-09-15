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

if (!hasAccess($role, 'inventaris', 'lihat')) fail('Anda tidak memiliki hak akses ke modul Inventaris.');

switch ($action) {

    case 'list_barang':
        $res = $conn->query("SELECT * FROM barang ORDER BY id DESC");
        ok($res->fetch_all(MYSQLI_ASSOC));

    case 'list_peminjaman':
        $sql = "SELECT p.*, b.nama_barang, b.kode_barang, u1.name AS peminjam_nama,
                       u2.name AS verifikator_nama, u3.name AS approval_nama
                FROM peminjaman p
                JOIN barang b ON b.id = p.barang_id
                JOIN users u1 ON u1.id = p.peminjam_id
                LEFT JOIN users u2 ON u2.id = p.verifikator_id
                LEFT JOIN users u3 ON u3.id = p.approval_id
                ORDER BY p.id DESC";
        $res = $conn->query($sql);
        ok($res->fetch_all(MYSQLI_ASSOC));

    case 'tambah_barang':
        if (!hasAccess($role, 'inventaris', 'tambah')) fail('Akses ditolak.');
        $nama = trim($_POST['nama_barang'] ?? '');
        if (!$nama) fail('Nama barang wajib diisi.');
        $kategori = trim($_POST['kategori'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $stok = (int)($_POST['stok'] ?? 0);
        $ket = trim($_POST['keterangan'] ?? '');
        $row = $conn->query("SELECT COUNT(*)+1 n FROM barang")->fetch_assoc();
        $kode = 'BRG-' . str_pad($row['n'], 3, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori, lokasi, stok, status, keterangan) VALUES (?,?,?,?,?, 'tersedia', ?)");
        $stmt->bind_param("sssiss", $kode, $nama, $kategori, $lokasi, $stok, $ket);
        $stmt->execute();
        logAktivitas($user['id'], 'inventaris', "Menambahkan barang $nama ($kode)");
        ok(['id' => $stmt->insert_id]);

    case 'update_barang':
        if (!hasAccess($role, 'inventaris', 'kelola')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama_barang'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $stok = (int)($_POST['stok'] ?? 0);
        $status = $_POST['status'] ?? 'tersedia';
        $stmt = $conn->prepare("UPDATE barang SET nama_barang=?, kategori=?, lokasi=?, stok=?, status=? WHERE id=?");
        $stmt->bind_param("sssisi", $nama, $kategori, $lokasi, $stok, $status, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'inventaris', "Memperbarui data barang #$id");
        ok();

    case 'ajukan_pinjam':
        $barangId = (int)($_POST['barang_id'] ?? 0);
        $jumlah = max(1, (int)($_POST['jumlah'] ?? 1));
        $tglPinjam = $_POST['tanggal_pinjam'] ?? date('Y-m-d');
        $rencana = $_POST['rencana_kembali'] ?? null;
        $keperluan = trim($_POST['keperluan'] ?? '');
        if (!$barangId) fail('Barang wajib dipilih.');
        $b = $conn->query("SELECT stok FROM barang WHERE id=" . $barangId)->fetch_assoc();
        if (!$b || $b['stok'] < $jumlah) fail('Stok barang tidak mencukupi.');
        $stmt = $conn->prepare("INSERT INTO peminjaman (barang_id, jumlah, peminjam_id, tanggal_pinjam, rencana_kembali, keperluan, status) VALUES (?,?,?,?,?,?, 'diajukan')");
        $stmt->bind_param("iiisss", $barangId, $jumlah, $user['id'], $tglPinjam, $rencana, $keperluan);
        $stmt->execute();
        logAktivitas($user['id'], 'inventaris', 'Mengajukan peminjaman barang');
        notifyRole('staff_inventaris', 'inventaris', $user['name'] . ' mengajukan peminjaman barang, mohon diverifikasi.');
        ok(['id' => $stmt->insert_id]);

    case 'verifikasi_pinjam':
        if (!hasAccess($role, 'inventaris', 'verifikasi')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $status = $setuju ? 'diverifikasi' : 'ditolak';
        $stmt = $conn->prepare("UPDATE peminjaman SET status=?, verifikator_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $user['id'], $id);
        $stmt->execute();
        logAktivitas($user['id'], 'inventaris', "Verifikasi pengajuan peminjaman #$id: $status");
        if ($setuju) notifyRole('spv_inventaris', 'inventaris', "Pengajuan peminjaman #$id telah diverifikasi, menunggu approval.");
        ok();

    case 'approve_pinjam':
        if (!hasAccess($role, 'inventaris', 'approve')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $setuju = ($_POST['setuju'] ?? '1') === '1';
        $catatan = trim($_POST['catatan'] ?? '');
        $pj = $conn->query("SELECT * FROM peminjaman WHERE id=$id")->fetch_assoc();
        if (!$pj) fail('Data tidak ditemukan.');
        if ($setuju) {
            $conn->query("UPDATE barang SET stok = stok - {$pj['jumlah']} WHERE id={$pj['barang_id']}");
            $stmt = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'keluar', ?, CURDATE(), 'Peminjaman disetujui', ?)");
            $stmt->bind_param("iii", $pj['barang_id'], $pj['jumlah'], $user['id']);
            $stmt->execute();
            $status = 'disetujui';
        } else {
            $status = 'ditolak';
        }
        $stmt = $conn->prepare("UPDATE peminjaman SET status=?, approval_id=?, catatan=? WHERE id=?");
        $stmt->bind_param("sisi", $status, $user['id'], $catatan, $id);
        $stmt->execute();
        logAktivitas($user['id'], 'inventaris', "Approval peminjaman #$id: $status");
        kirimNotifikasi($pj['peminjam_id'], 'inventaris', "Pengajuan peminjaman Anda telah $status.");
        ok();

    case 'kembalikan':
        if (!hasAccess($role, 'inventaris', 'verifikasi') && !hasAccess($role, 'inventaris', 'kelola')) fail('Akses ditolak.');
        $id = (int)($_POST['id'] ?? 0);
        $kondisi = $_POST['kondisi_barang'] ?? 'baik';
        $catatan = trim($_POST['catatan'] ?? '');
        $pj = $conn->query("SELECT * FROM peminjaman WHERE id=$id")->fetch_assoc();
        if (!$pj || $pj['status'] !== 'disetujui') fail('Peminjaman tidak valid untuk pengembalian.');
        $stmt = $conn->prepare("INSERT INTO pengembalian (peminjaman_id, tanggal_kembali, kondisi_barang, petugas_id, catatan) VALUES (?, CURDATE(), ?, ?, ?)");
        $stmt->bind_param("isis", $id, $kondisi, $user['id'], $catatan);
        $stmt->execute();
        $conn->query("UPDATE peminjaman SET status='dikembalikan' WHERE id=$id");
        if ($kondisi === 'baik') {
            $conn->query("UPDATE barang SET stok = stok + {$pj['jumlah']} WHERE id={$pj['barang_id']}");
            $stmt2 = $conn->prepare("INSERT INTO transaksi_barang (barang_id, jenis, jumlah, tanggal, keterangan, user_id) VALUES (?, 'masuk', ?, CURDATE(), 'Pengembalian barang', ?)");
            $stmt2->bind_param("iii", $pj['barang_id'], $pj['jumlah'], $user['id']);
            $stmt2->execute();
        }
        logAktivitas($user['id'], 'inventaris', "Konfirmasi pengembalian peminjaman #$id ($kondisi)");
        ok();

    default:
        fail('Aksi tidak dikenal.');
}
