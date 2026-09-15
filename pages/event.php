<?php
$canVerifikasi = hasAccess($role, 'event', 'verifikasi');
$canApprove = hasAccess($role, 'event', 'approve');
$canKelola = hasAccess($role, 'event', 'kelola');
$totalBerjalan = $conn->query("SELECT COUNT(*) n FROM event WHERE status IN ('disetujui','berlangsung')")->fetch_assoc()['n'];
$totalMenunggu = $conn->query("SELECT COUNT(*) n FROM event WHERE status IN ('diajukan','diverifikasi')")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#c79a3f"><i data-lucide="calendar-clock"></i></div><h3><?= $totalMenunggu ?></h3><p>Menunggu Persetujuan</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="calendar-check-2"></i></div><h3><?= $totalBerjalan ?></h3><p>Disetujui / Berlangsung</p></div>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Event Management System</h2><p>Alur: Pengajuan → Verifikasi (Staff/SPV CRM) → Approval (Manajer MARKOM). Sistem mencegah benturan jadwal otomatis.</p></div>
        <div class="hgroup"><div id="toolbarEvent"></div><button class="btn btn-primary" onclick="openModal('modalEvent')"><i data-lucide="plus"></i> Ajukan Event</button></div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblEvent">
            <thead><tr><th>Nama Event</th><th>Jadwal</th><th>Lokasi</th><th>Pemohon</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="eventBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalEvent">
    <div class="modal">
        <div class="modal-head"><h3>Ajukan Kegiatan / Event</h3><button class="modal-close" onclick="closeModal('modalEvent')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Nama Event</label><input id="eNama"></div>
            <div class="fld"><label>Deskripsi</label><textarea id="eDeskripsi"></textarea></div>
            <div class="form-grid">
                <div class="fld"><label>Tanggal</label><input type="date" id="eTanggal"></div>
                <div class="fld"><label>Lokasi</label><input id="eLokasi" placeholder="Aula, Lapangan, dsb."></div>
                <div class="fld"><label>Jam Mulai</label><input type="time" id="eJamMulai" value="08:00"></div>
                <div class="fld"><label>Jam Selesai</label><input type="time" id="eJamSelesai" value="10:00"></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalEvent')">Batal</button><button class="btn btn-primary" onclick="ajukanEvent()">Ajukan</button></div>
    </div>
</div>

<script>
const E_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
const E_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const E_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
const eBadge = { diajukan:'b-gray', diverifikasi:'b-blue', disetujui:'b-green', ditolak:'b-red', berlangsung:'b-purple', selesai:'b-green' };
const eLabel = { diajukan:'Diajukan', diverifikasi:'Diverifikasi', disetujui:'Disetujui', ditolak:'Ditolak', berlangsung:'Berlangsung', selesai:'Selesai' };

async function ajukanEvent() {
    const r = await apiFetch('event', 'ajukan', {
        nama_event: document.getElementById('eNama').value,
        deskripsi: document.getElementById('eDeskripsi').value,
        tanggal: document.getElementById('eTanggal').value,
        lokasi: document.getElementById('eLokasi').value,
        jam_mulai: document.getElementById('eJamMulai').value,
        jam_selesai: document.getElementById('eJamSelesai').value,
    });
    if (r.ok) { toast('Pengajuan event terkirim.', 'success'); closeModal('modalEvent'); loadEvent(); } else toast(r.msg, 'error');
}
async function loadEvent() {
    const r = await apiFetch('event', 'list', {}, 'GET');
    if (!r.ok) return;
    document.getElementById('eventBody').innerHTML = r.data.length ? r.data.map(e => {
        let aksi = '<span style="color:#9ca3af;font-size:12px">—</span>';
        if (e.status === 'diajukan' && E_VERIFIKASI) aksi = pairE('verifikasi', e.id, 'valid');
        else if (e.status === 'diverifikasi' && E_APPROVE) aksi = pairE('approve', e.id, 'setuju');
        else if (e.status === 'disetujui' && (E_KELOLA || E_VERIFIKASI)) aksi = `<button class="btn btn-outline btn-sm" onclick="setStatus(${e.id},'berlangsung')">Mulai</button>`;
        else if (e.status === 'berlangsung' && (E_KELOLA || E_VERIFIKASI)) aksi = `<button class="btn btn-primary btn-sm" onclick="setStatus(${e.id},'selesai')">Selesaikan</button>`;
        return `<tr><td>${e.nama_event}</td><td>${e.tanggal||'-'} ${e.jam_mulai||''}-${e.jam_selesai||''}</td><td>${e.lokasi||'-'}</td><td>${e.pemohon_nama}</td>
        <td><span class="badge ${eBadge[e.status]}">${eLabel[e.status]}</span></td><td class="actions-cell">${aksi}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="empty-row">Belum ada pengajuan event.</td></tr>';
    lucide.createIcons();
}
function pairE(action, id, field) {
    return `<button class="btn btn-primary btn-sm" onclick="prosesE('${action}',${id},'${field}',1)"><i data-lucide="check"></i></button>
            <button class="btn btn-danger btn-sm" onclick="prosesE('${action}',${id},'${field}',0)"><i data-lucide="x"></i></button>`;
}
async function prosesE(action, id, field, val) {
    const payload = { id }; payload[field] = val;
    const r = await apiFetch('event', action, payload);
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadEvent(); } else toast(r.msg, 'error');
}
async function setStatus(id, status) {
    const r = await apiFetch('event', 'update_status', { id, status });
    if (r.ok) { toast('Status event diperbarui.', 'success'); loadEvent(); } else toast(r.msg, 'error');
}
loadEvent();
renderExportToolbar('#toolbarEvent', 'tblEvent', 'data_event', 'Event Management');
</script>
