<?php
$canTambah = hasAccess($role, 'inventaris', 'tambah');
$canKelola = hasAccess($role, 'inventaris', 'kelola');
$canVerifikasi = hasAccess($role, 'inventaris', 'verifikasi');
$canApprove = hasAccess($role, 'inventaris', 'approve');
$canMonitoring = hasAccess($role, 'inventaris', 'monitoring');

$barangList = $conn->query("SELECT * FROM barang ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$stokTotal = array_sum(array_column($barangList, 'stok'));
$pinjamAktif = $conn->query("SELECT COUNT(*) n FROM peminjaman WHERE status='disetujui'")->fetch_assoc()['n'];
$menunggu = $conn->query("SELECT COUNT(*) n FROM peminjaman WHERE status IN ('diajukan','diverifikasi')")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="package"></i></div><h3><?= count($barangList) ?></h3><p>Jenis Barang</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#2f6690"><i data-lucide="layers"></i></div><h3><?= $stokTotal ?></h3><p>Total Stok</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#a97c22"><i data-lucide="clock"></i></div><h3><?= $menunggu ?></h3><p>Menunggu Verifikasi/Approval</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#6a4fa0"><i data-lucide="handshake"></i></div><h3><?= $pinjamAktif ?></h3><p>Peminjaman Aktif</p></div>
</div>

<div class="tabs" id="invTabs">
    <button class="tab-btn active" data-tab="barang">Data Barang</button>
    <button class="tab-btn" data-tab="pinjam">Peminjaman & Persetujuan</button>
    <?php if ($canKelola || $canVerifikasi): ?><button class="tab-btn" data-tab="pengembalian">Pengembalian</button><?php endif; ?>
</div>

<div class="tab-panel" id="panel-barang">
    <div class="card">
        <div class="card-head">
            <div><h2>Daftar Barang Inventaris</h2><p>Kode, kategori, lokasi, stok, dan status barang.</p></div>
            <div class="hgroup">
                <div class="search-bar"><i data-lucide="search"></i><input id="searchBarang" placeholder="Cari barang..."></div>
                <div id="toolbarBarang"></div>
                <?php if ($canTambah): ?><button class="btn btn-primary" onclick="openModal('modalBarang')"><i data-lucide="plus"></i> Tambah Barang</button><?php endif; ?>
            </div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblBarang">
                <thead><tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Lokasi</th><th>Stok</th><th>Status</th><?php if ($canKelola): ?><th>Aksi</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if (!$barangList): ?><tr><td colspan="7" class="empty-row">Belum ada data barang.</td></tr><?php endif; ?>
                <?php foreach ($barangList as $b):
                    $statusBadge = ['tersedia' => 'b-green', 'habis' => 'b-yellow', 'rusak' => 'b-red'][$b['status']] ?? 'b-gray'; ?>
                    <tr>
                        <td><b><?= htmlspecialchars($b['kode_barang']) ?></b></td>
                        <td><?= htmlspecialchars($b['nama_barang']) ?></td>
                        <td><?= htmlspecialchars($b['kategori']) ?></td>
                        <td><?= htmlspecialchars($b['lokasi']) ?></td>
                        <td><?= $b['stok'] ?></td>
                        <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($b['status']) ?></span></td>
                        <?php if ($canKelola): ?>
                        <td class="actions-cell"><button class="btn btn-outline btn-sm btn-icon" onclick='editBarang(<?= json_encode($b) ?>)'><i data-lucide="pencil"></i></button></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-panel" id="panel-pinjam" style="display:none">
    <div class="card">
        <div class="card-head">
            <div><h2>Pengajuan & Persetujuan Peminjaman</h2><p>Alur: Ajukan → Verifikasi (Staff Inventaris) → Approve (SPV Inventaris).</p></div>
            <div class="hgroup"><div id="toolbarPinjam"></div><button class="btn btn-primary" onclick="openModal('modalPinjam')"><i data-lucide="plus"></i> Ajukan Peminjaman</button></div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblPinjam">
                <thead><tr><th>Barang</th><th>Jml</th><th>Peminjam</th><th>Tgl Pinjam</th><th>Rencana Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody id="pinjamBody"><tr><td colspan="7" class="empty-row">Memuat data...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canKelola || $canVerifikasi): ?>
<div class="tab-panel" id="panel-pengembalian" style="display:none">
    <div class="card">
        <div class="card-head"><div><h2>Konfirmasi Pengembalian</h2><p>Peminjaman berstatus "disetujui" siap dikembalikan.</p></div><div id="toolbarKembali"></div></div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblKembali">
                <thead><tr><th>Barang</th><th>Jml</th><th>Peminjam</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody id="kembaliBody"><tr><td colspan="5" class="empty-row">Memuat data...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL TAMBAH/EDIT BARANG -->
<?php if ($canTambah || $canKelola): ?>
<div class="modal-overlay" id="modalBarang">
    <div class="modal">
        <div class="modal-head"><h3 id="modalBarangTitle">Tambah Barang</h3><button class="modal-close" onclick="closeModal('modalBarang')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="fBarangId">
            <div class="form-grid">
                <div class="fld"><label>Nama Barang</label><input id="fNamaBarang"></div>
                <div class="fld"><label>Kategori</label><input id="fKategori"></div>
                <div class="fld"><label>Lokasi</label><input id="fLokasi"></div>
                <div class="fld"><label>Stok</label><input type="number" id="fStok" min="0"></div>
                <div class="fld" id="fStatusWrap" style="display:none"><label>Status</label>
                    <select id="fStatus"><option value="tersedia">Tersedia</option><option value="habis">Habis</option><option value="rusak">Rusak</option></select>
                </div>
                <div class="fld"><label>Keterangan</label><input id="fKeterangan"></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalBarang')">Batal</button><button class="btn btn-primary" onclick="simpanBarang()">Simpan</button></div>
    </div>
</div>
<?php endif; ?>

<!-- MODAL AJUKAN PEMINJAMAN -->
<div class="modal-overlay" id="modalPinjam">
    <div class="modal">
        <div class="modal-head"><h3>Ajukan Peminjaman Barang</h3><button class="modal-close" onclick="closeModal('modalPinjam')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Barang</label>
                <select id="pBarang"><?php foreach ($barangList as $b) echo '<option value="' . $b['id'] . '">' . htmlspecialchars($b['nama_barang']) . ' (stok: ' . $b['stok'] . ')</option>'; ?></select>
            </div>
            <div class="form-grid">
                <div class="fld"><label>Jumlah</label><input type="number" id="pJumlah" value="1" min="1"></div>
                <div class="fld"><label>Tanggal Pinjam</label><input type="date" id="pTglPinjam" value="<?= date('Y-m-d') ?>"></div>
                <div class="fld"><label>Rencana Kembali</label><input type="date" id="pRencana"></div>
            </div>
            <div class="fld"><label>Keperluan</label><textarea id="pKeperluan"></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalPinjam')">Batal</button><button class="btn btn-primary" onclick="ajukanPinjam()">Ajukan</button></div>
    </div>
</div>

<script>
const CAN_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
const CAN_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const CAN_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;

document.querySelectorAll('#invTabs .tab-btn').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('#invTabs .tab-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.getElementById('panel-' + b.dataset.tab).style.display = 'block';
    if (b.dataset.tab === 'pinjam') loadPinjaman();
    if (b.dataset.tab === 'pengembalian') loadPinjaman();
}));

document.getElementById('searchBarang')?.addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#tblBarang tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
});

function editBarang(b) {
    document.getElementById('modalBarangTitle').textContent = 'Edit Barang';
    document.getElementById('fBarangId').value = b.id;
    document.getElementById('fNamaBarang').value = b.nama_barang;
    document.getElementById('fKategori').value = b.kategori || '';
    document.getElementById('fLokasi').value = b.lokasi || '';
    document.getElementById('fStok').value = b.stok;
    document.getElementById('fKeterangan').value = b.keterangan || '';
    document.getElementById('fStatusWrap').style.display = 'block';
    document.getElementById('fStatus').value = b.status;
    openModal('modalBarang');
}
async function simpanBarang() {
    const id = document.getElementById('fBarangId').value;
    const payload = {
        nama_barang: document.getElementById('fNamaBarang').value,
        kategori: document.getElementById('fKategori').value,
        lokasi: document.getElementById('fLokasi').value,
        stok: document.getElementById('fStok').value,
        keterangan: document.getElementById('fKeterangan').value,
    };
    const action = id ? 'update_barang' : 'tambah_barang';
    if (id) { payload.id = id; payload.status = document.getElementById('fStatus').value; }
    const r = await apiFetch('inventaris', action, payload);
    if (r.ok) { toast('Data barang tersimpan.', 'success'); closeModal('modalBarang'); location.reload(); }
    else toast(r.msg, 'error');
}

async function ajukanPinjam() {
    const r = await apiFetch('inventaris', 'ajukan_pinjam', {
        barang_id: document.getElementById('pBarang').value,
        jumlah: document.getElementById('pJumlah').value,
        tanggal_pinjam: document.getElementById('pTglPinjam').value,
        rencana_kembali: document.getElementById('pRencana').value,
        keperluan: document.getElementById('pKeperluan').value,
    });
    if (r.ok) { toast('Pengajuan peminjaman terkirim.', 'success'); closeModal('modalPinjam'); loadPinjaman(); }
    else toast(r.msg, 'error');
}

const statusBadge = { diajukan: 'b-gray', diverifikasi: 'b-blue', disetujui: 'b-green', ditolak: 'b-red', dikembalikan: 'b-purple' };
const statusLabel = { diajukan: 'Diajukan', diverifikasi: 'Diverifikasi', disetujui: 'Disetujui', ditolak: 'Ditolak', dikembalikan: 'Dikembalikan' };

async function loadPinjaman() {
    const r = await apiFetch('inventaris', 'list_peminjaman', {}, 'GET');
    if (!r.ok) return;
    const body = document.getElementById('pinjamBody');
    body.innerHTML = r.data.length ? r.data.map(p => {
        let aksi = '';
        if (p.status === 'diajukan' && CAN_VERIFIKASI) aksi = btnPair('verifikasi_pinjam', p.id);
        else if (p.status === 'diverifikasi' && CAN_APPROVE) aksi = btnPair('approve_pinjam', p.id);
        else aksi = '<span style="color:#9ca3af;font-size:12px">—</span>';
        return `<tr><td>${p.nama_barang}</td><td>${p.jumlah}</td><td>${p.peminjam_nama}</td><td>${p.tanggal_pinjam}</td><td>${p.rencana_kembali||'-'}</td>
        <td><span class="badge ${statusBadge[p.status]}">${statusLabel[p.status]}</span></td><td class="actions-cell">${aksi}</td></tr>`;
    }).join('') : '<tr><td colspan="7" class="empty-row">Belum ada pengajuan peminjaman.</td></tr>';

    const kembaliBody = document.getElementById('kembaliBody');
    if (kembaliBody) {
        const siap = r.data.filter(p => p.status === 'disetujui');
        kembaliBody.innerHTML = siap.length ? siap.map(p => `<tr><td>${p.nama_barang}</td><td>${p.jumlah}</td><td>${p.peminjam_nama}</td>
            <td><span class="badge b-green">Dipinjam</span></td>
            <td><button class="btn btn-outline btn-sm" onclick="kembalikan(${p.id})"><i data-lucide="corner-down-left"></i> Konfirmasi Kembali</button></td></tr>`).join('')
            : '<tr><td colspan="5" class="empty-row">Tidak ada barang yang sedang dipinjam.</td></tr>';
    }
    lucide.createIcons();
}
function btnPair(action, id) {
    return `<button class="btn btn-primary btn-sm" onclick="prosesPinjam('${action}',${id},1)"><i data-lucide="check"></i></button>
            <button class="btn btn-danger btn-sm" onclick="prosesPinjam('${action}',${id},0)"><i data-lucide="x"></i></button>`;
}
async function prosesPinjam(action, id, setuju) {
    const r = await apiFetch('inventaris', action, { id, setuju });
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadPinjaman(); } else toast(r.msg, 'error');
}
async function kembalikan(id) {
    const r = await apiFetch('inventaris', 'kembalikan', { id, kondisi_barang: 'baik' });
    if (r.ok) { toast('Pengembalian dikonfirmasi.', 'success'); loadPinjaman(); } else toast(r.msg, 'error');
}
loadPinjaman();
renderExportToolbar('#toolbarBarang', 'tblBarang', 'data_barang', 'Daftar Barang Inventaris');
renderExportToolbar('#toolbarPinjam', 'tblPinjam', 'peminjaman', 'Peminjaman & Persetujuan');
renderExportToolbar('#toolbarKembali', 'tblKembali', 'pengembalian', 'Konfirmasi Pengembalian');
</script>
