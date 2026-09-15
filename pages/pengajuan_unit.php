<?php
$canAjukan = hasAccess($role, 'pengajuan_unit', 'ajukan');
$canApprove = hasAccess($role, 'pengajuan_unit', 'approve');
$jenisLabel = ['inventaris' => 'Peminjaman Barang', 'maintenance' => 'Maintenance Fasilitas', 'event' => 'Event/Kegiatan'];
$allowedModules = $canAjukan ? getPengajuanAllowedModules($role) : [];
$approverRole = $canAjukan ? getPengajuanApproverRole($role) : null;
$approverLabel = $approverRole ? getRoleName($approverRole) : '';
?>
<div class="card">
    <div class="card-head">
        <div><h2>Pengajuan Lintas Divisi</h2>
            <p>
                <?php if ($canAjukan): ?>
                    Alur: Anda membuat pengajuan → <strong><?= htmlspecialchars($approverLabel) ?></strong> menyetujui → sistem meneruskan ke pengelola modul terkait.
                <?php elseif ($canApprove): ?>
                    Alur: staff mengajukan kebutuhan → Anda menyetujui sebelum diteruskan ke pengelola modul terkait.
                <?php else: ?>
                    Alur: pengajuan kebutuhan divisi disetujui atasan sebelum diteruskan ke pengelola modul terkait.
                <?php endif; ?>
            </p>
        </div>
        <div class="hgroup"><div id="toolbarPengajuanUnit"></div><?php if ($canAjukan): ?><button class="btn btn-primary" onclick="openModal('modalAjukanUnit')"><i data-lucide="plus"></i> Buat Pengajuan</button><?php endif; ?></div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblPengajuanUnit">
            <thead><tr><th>Unit/Divisi</th><th>Jenis</th><th>Deskripsi</th><th>Pemohon</th><th>Status</th><th>Disetujui Oleh</th><?php if ($canApprove): ?><th>Aksi</th><?php endif; ?></tr></thead>
            <tbody id="puBody"><tr><td colspan="7" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<?php if ($canAjukan): ?>
<div class="modal-overlay" id="modalAjukanUnit">
    <div class="modal">
        <div class="modal-head"><h3>Buat Pengajuan</h3><button class="modal-close" onclick="closeModal('modalAjukanUnit')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Jenis Kebutuhan</label>
                <select id="puJenis"><?php foreach ($allowedModules as $m): ?><option value="<?= $m ?>"><?= $jenisLabel[$m] ?></option><?php endforeach; ?></select>
            </div>
            <div class="fld"><label>Deskripsi Kebutuhan</label><textarea id="puDeskripsi" placeholder="Jelaskan kebutuhan Anda..."></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalAjukanUnit')">Batal</button><button class="btn btn-primary" onclick="ajukanUnit()">Ajukan</button></div>
    </div>
</div>
<?php endif; ?>

<script>
const PU_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const puJenisLabel = <?= json_encode($jenisLabel) ?>;
const puBadge = { diajukan: 'b-gray', disetujui: 'b-green', ditolak: 'b-red', diteruskan: 'b-blue' };
const puLabel = { diajukan: 'Menunggu Approval', disetujui: 'Disetujui', ditolak: 'Ditolak', diteruskan: 'Diteruskan ke Modul' };

async function ajukanUnit() {
    const r = await apiFetch('pengajuan_unit', 'ajukan', { jenis_modul: document.getElementById('puJenis').value, deskripsi: document.getElementById('puDeskripsi').value });
    if (r.ok) { toast('Pengajuan terkirim, menunggu approval atasan.', 'success'); closeModal('modalAjukanUnit'); loadPU(); } else toast(r.msg, 'error');
}
async function loadPU() {
    const r = await apiFetch('pengajuan_unit', 'list', {}, 'GET');
    if (!r.ok) return;
    document.getElementById('puBody').innerHTML = r.data.length ? r.data.map(p => {
        let aksi = '<span style="color:#9ca3af;font-size:12px">—</span>';
        if (p.status === 'diajukan' && PU_APPROVE) {
            aksi = `<button class="btn btn-primary btn-sm" onclick="approvePU(${p.id},1)"><i data-lucide="check"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="approvePU(${p.id},0)"><i data-lucide="x"></i></button>`;
        }
        return `<tr><td>${p.nama_unit||'-'}</td><td>${puJenisLabel[p.jenis_modul]}</td><td>${p.deskripsi}</td><td>${p.pemohon_nama} <span style="color:#9ca3af;font-size:11px">(${p.pemohon_role})</span></td>
        <td><span class="badge ${puBadge[p.status]}">${puLabel[p.status]}</span></td><td>${p.approver_nama||'-'}</td><td class="actions-cell">${aksi}</td></tr>`;
    }).join('') : '<tr><td colspan="7" class="empty-row">Belum ada pengajuan.</td></tr>';
    lucide.createIcons();
}
async function approvePU(id, setuju) {
    const r = await apiFetch('pengajuan_unit', 'approve', { id, setuju });
    if (r.ok) { toast('Pengajuan berhasil diproses.', 'success'); loadPU(); } else toast(r.msg, 'error');
}
loadPU();
renderExportToolbar('#toolbarPengajuanUnit', 'tblPengajuanUnit', 'pengajuan_lintas_divisi', 'Pengajuan Lintas Divisi');
</script>
