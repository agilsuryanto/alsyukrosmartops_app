<?php
$canTambah = hasAccess($role, 'karyawan', 'tambah');
$canKelola = hasAccess($role, 'karyawan', 'kelola');
$canPenilaian = hasAccess($role, 'karyawan', 'penilaian');
$isPelaksana = in_array($role, ['cso', 'gardener']);

$karyawanList = $conn->query("SELECT k.*, su.nama_unit, a.nama AS atasan_nama FROM karyawan k
                               LEFT JOIN struktur_unit su ON su.id=k.divisi_id
                               LEFT JOIN karyawan a ON a.id=k.atasan_id ORDER BY k.id")->fetch_all(MYSQLI_ASSOC);
$unitList = $conn->query("SELECT * FROM struktur_unit ORDER BY nama_unit")->fetch_all(MYSQLI_ASSOC);
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="users"></i></div><h3><?= count($karyawanList) ?></h3><p>Total Karyawan</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#2f6690"><i data-lucide="clipboard-list"></i></div><h3 id="statKegiatanAktif">-</h3><p>Kegiatan Berjalan</p></div>
</div>

<div class="tabs" id="karTabs">
    <?php if ($canTambah || $canKelola): ?><button class="tab-btn active" data-tab="data">Data Karyawan</button><?php endif; ?>
    <button class="tab-btn <?= !($canTambah || $canKelola) ? 'active' : '' ?>" data-tab="kegiatan"><?= $isPelaksana ? 'Kegiatan Saya' : 'Kegiatan & Monitoring' ?></button>
    <?php if ($canPenilaian): ?><button class="tab-btn" data-tab="penilaian">Penilaian Kinerja</button><?php endif; ?>
</div>

<?php if ($canTambah || $canKelola): ?>
<div class="tab-panel" id="panel-data">
    <div class="card">
        <div class="card-head">
            <div><h2>Data Karyawan</h2><p>Struktur organisasi &amp; data dasar karyawan.</p></div>
            <div class="hgroup"><div id="toolbarKaryawan"></div><?php if ($canTambah): ?><button class="btn btn-primary" onclick="openModal('modalKaryawan')"><i data-lucide="plus"></i> Tambah Karyawan</button><?php endif; ?></div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblKaryawan">
                <thead><tr><th>NIP</th><th>Nama</th><th>Jabatan</th><th>Unit/Divisi</th><th>Atasan</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (!$karyawanList): ?><tr><td colspan="6" class="empty-row">Belum ada data karyawan.</td></tr><?php endif; ?>
                <?php foreach ($karyawanList as $k): ?>
                    <tr>
                        <td><b><?= htmlspecialchars($k['nip']) ?></b></td>
                        <td><?= htmlspecialchars($k['nama']) ?></td>
                        <td><?= htmlspecialchars($k['jabatan']) ?></td>
                        <td><?= htmlspecialchars($k['nama_unit'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($k['atasan_nama'] ?? '-') ?></td>
                        <td><span class="badge <?= $k['status'] === 'aktif' ? 'b-green' : 'b-gray' ?>"><?= ucfirst($k['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="tab-panel" id="panel-kegiatan" style="display:<?= ($canTambah || $canKelola) ? 'none' : 'block' ?>">
    <div class="card">
        <div class="card-head">
            <div><h2><?= $isPelaksana ? 'Kegiatan yang Diberikan' : 'Kegiatan Karyawan' ?></h2><p><?= $isPelaksana ? 'Lihat kegiatan dari atasan & input hasil pekerjaan.' : 'Monitoring kegiatan rutin & non-rutin karyawan.' ?></p></div>
            <div class="hgroup"><div id="toolbarKegiatan"></div><?php if ($canKelola): ?><button class="btn btn-primary" onclick="openModal('modalKegiatan')"><i data-lucide="plus"></i> Berikan Kegiatan</button><?php endif; ?></div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblKegiatan">
                <thead><tr><th>Karyawan</th><th>Jenis</th><th>Deskripsi</th><th>Tanggal</th><th>Status</th><th>Hasil</th><?php if ($isPelaksana): ?><th>Aksi</th><?php endif; ?></tr></thead>
                <tbody id="kegiatanBody"><tr><td colspan="7" class="empty-row">Memuat data...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($canPenilaian): ?>
<div class="tab-panel" id="panel-penilaian" style="display:none">
    <div class="card">
        <div class="card-head"><div><h2>Penilaian Kinerja</h2><p>Penilaian oleh atasan sesuai kewenangan jabatan.</p></div>
            <div class="hgroup"><div id="toolbarPenilaian"></div><button class="btn btn-primary" onclick="openModal('modalNilai')"><i data-lucide="plus"></i> Beri Penilaian</button></div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblPenilaian">
                <thead><tr><th>Karyawan</th><th>Periode</th><th>Nilai</th><th>Penilai</th><th>Catatan</th></tr></thead>
                <tbody id="penilaianBody"><tr><td colspan="5" class="empty-row">Memuat data...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- MODALS -->
<?php if ($canTambah): ?>
<div class="modal-overlay" id="modalKaryawan">
    <div class="modal">
        <div class="modal-head"><h3>Tambah Karyawan</h3><button class="modal-close" onclick="closeModal('modalKaryawan')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="fld"><label>NIP</label><input id="kNip"></div>
                <div class="fld"><label>Nama</label><input id="kNama"></div>
                <div class="fld"><label>Jabatan</label><input id="kJabatan"></div>
                <div class="fld"><label>Unit/Divisi</label><select id="kDivisi"><?php foreach ($unitList as $u) echo '<option value="' . $u['id'] . '">' . htmlspecialchars($u['nama_unit']) . '</option>'; ?></select></div>
                <div class="fld"><label>Atasan (opsional)</label><select id="kAtasan"><option value="">-- Tidak ada --</option><?php foreach ($karyawanList as $k) echo '<option value="' . $k['id'] . '">' . htmlspecialchars($k['nama'] . ' — ' . $k['jabatan']) . '</option>'; ?></select></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalKaryawan')">Batal</button><button class="btn btn-primary" onclick="simpanKaryawan()">Simpan</button></div>
    </div>
</div>
<?php endif; ?>

<?php if ($canKelola): ?>
<div class="modal-overlay" id="modalKegiatan">
    <div class="modal">
        <div class="modal-head"><h3>Berikan Kegiatan Karyawan</h3><button class="modal-close" onclick="closeModal('modalKegiatan')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Karyawan</label><select id="gKaryawan"><?php foreach ($karyawanList as $k) if (in_array($k['jabatan'], ['CSO', 'Gardener'])) echo '<option value="' . $k['id'] . '">' . htmlspecialchars($k['nama'] . ' (' . $k['jabatan'] . ')') . '</option>'; ?></select></div>
            <div class="form-grid">
                <div class="fld"><label>Jenis</label><select id="gJenis"><option value="rutin">Rutin</option><option value="non-rutin">Non-Rutin</option></select></div>
                <div class="fld"><label>Tanggal</label><input type="date" id="gTanggal" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <div class="fld"><label>Deskripsi Kegiatan</label><textarea id="gDeskripsi"></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalKegiatan')">Batal</button><button class="btn btn-primary" onclick="simpanKegiatan()">Berikan</button></div>
    </div>
</div>
<?php endif; ?>

<?php if ($isPelaksana): ?>
<div class="modal-overlay" id="modalHasil">
    <div class="modal">
        <div class="modal-head"><h3>Input Hasil Kegiatan</h3><button class="modal-close" onclick="closeModal('modalHasil')"><i data-lucide="x"></i></button></div>
        <div class="modal-body"><input type="hidden" id="hId"><div class="fld"><label>Hasil Pekerjaan</label><textarea id="hHasil"></textarea></div></div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalHasil')">Batal</button><button class="btn btn-primary" onclick="simpanHasil()">Kirim</button></div>
    </div>
</div>
<?php endif; ?>

<?php if ($canPenilaian): ?>
<div class="modal-overlay" id="modalNilai">
    <div class="modal">
        <div class="modal-head"><h3>Beri Penilaian Kinerja</h3><button class="modal-close" onclick="closeModal('modalNilai')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Karyawan</label><select id="nKaryawan"><?php foreach ($karyawanList as $k) echo '<option value="' . $k['id'] . '">' . htmlspecialchars($k['nama']) . '</option>'; ?></select></div>
            <div class="form-grid">
                <div class="fld"><label>Periode</label><input type="month" id="nPeriode" value="<?= date('Y-m') ?>"></div>
                <div class="fld"><label>Nilai (0-100)</label><input type="number" id="nNilai" min="0" max="100"></div>
            </div>
            <div class="fld"><label>Catatan</label><textarea id="nCatatan"></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalNilai')">Batal</button><button class="btn btn-primary" onclick="simpanNilai()">Simpan</button></div>
    </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('#karTabs .tab-btn').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('#karTabs .tab-btn').forEach(x => x.classList.remove('active'));
    b.classList.add('active');
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    document.getElementById('panel-' + b.dataset.tab).style.display = 'block';
    if (b.dataset.tab === 'penilaian') loadPenilaian();
}));

async function simpanKaryawan() {
    const r = await apiFetch('karyawan', 'tambah', {
        nip: document.getElementById('kNip').value, nama: document.getElementById('kNama').value,
        jabatan: document.getElementById('kJabatan').value, divisi_id: document.getElementById('kDivisi').value,
        atasan_id: document.getElementById('kAtasan').value,
    });
    if (r.ok) { toast('Data karyawan tersimpan.', 'success'); location.reload(); } else toast(r.msg, 'error');
}
async function simpanKegiatan() {
    const r = await apiFetch('karyawan', 'assign_kegiatan', {
        karyawan_id: document.getElementById('gKaryawan').value, jenis: document.getElementById('gJenis').value,
        tanggal: document.getElementById('gTanggal').value, deskripsi: document.getElementById('gDeskripsi').value,
    });
    if (r.ok) { toast('Kegiatan diberikan.', 'success'); closeModal('modalKegiatan'); loadKegiatan(); } else toast(r.msg, 'error');
}
function bukaHasil(id) { document.getElementById('hId').value = id; document.getElementById('hHasil').value = ''; openModal('modalHasil'); }
async function simpanHasil() {
    const r = await apiFetch('karyawan', 'input_hasil', { id: document.getElementById('hId').value, hasil_laporan: document.getElementById('hHasil').value });
    if (r.ok) { toast('Hasil kegiatan terkirim.', 'success'); closeModal('modalHasil'); loadKegiatan(); } else toast(r.msg, 'error');
}
async function simpanNilai() {
    const r = await apiFetch('karyawan', 'nilai_kinerja', {
        karyawan_id: document.getElementById('nKaryawan').value, periode: document.getElementById('nPeriode').value,
        nilai: document.getElementById('nNilai').value, catatan: document.getElementById('nCatatan').value,
    });
    if (r.ok) { toast('Penilaian tersimpan.', 'success'); closeModal('modalNilai'); loadPenilaian(); } else toast(r.msg, 'error');
}
const kStatusBadge = { ditugaskan: 'b-gray', dikerjakan: 'b-blue', selesai: 'b-green' };
const kStatusLabel = { ditugaskan: 'Ditugaskan', dikerjakan: 'Dikerjakan', selesai: 'Selesai' };
async function loadKegiatan() {
    const r = await apiFetch('karyawan', 'list_kegiatan', {}, 'GET');
    if (!r.ok) return;
    document.getElementById('statKegiatanAktif').textContent = r.data.filter(k => k.status !== 'selesai').length;
    const isPelaksana = <?= $isPelaksana ? 'true' : 'false' ?>;
    document.getElementById('kegiatanBody').innerHTML = r.data.length ? r.data.map(k => `<tr><td>${k.karyawan_nama}</td><td>${k.jenis}</td><td>${k.deskripsi}</td><td>${k.tanggal}</td>
        <td><span class="badge ${kStatusBadge[k.status]}">${kStatusLabel[k.status]}</span></td><td>${k.hasil_laporan||'-'}</td>
        ${isPelaksana ? `<td>${k.status!=='selesai' ? `<button class="btn btn-outline btn-sm" onclick="bukaHasil(${k.id})"><i data-lucide="send"></i> Input Hasil</button>` : '-'}</td>` : ''}
        </tr>`).join('') : `<tr><td colspan="7" class="empty-row">Belum ada kegiatan.</td></tr>`;
    lucide.createIcons();
}
async function loadPenilaian() {
    const r = await apiFetch('karyawan', 'list_penilaian', {}, 'GET');
    if (!r.ok) return;
    document.getElementById('penilaianBody').innerHTML = r.data.length ? r.data.map(p => `<tr><td>${p.karyawan_nama}</td><td>${p.periode}</td><td><b>${p.nilai}</b></td><td>${p.penilai_nama}</td><td>${p.catatan||'-'}</td></tr>`).join('')
        : '<tr><td colspan="5" class="empty-row">Belum ada penilaian.</td></tr>';
}
loadKegiatan();
renderExportToolbar('#toolbarKaryawan', 'tblKaryawan', 'data_karyawan', 'Data Karyawan');
renderExportToolbar('#toolbarKegiatan', 'tblKegiatan', 'kegiatan_karyawan', 'Kegiatan Karyawan');
renderExportToolbar('#toolbarPenilaian', 'tblPenilaian', 'penilaian_kinerja', 'Penilaian Kinerja');
</script>
