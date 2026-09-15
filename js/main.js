/* AL-SYUKROSMART OPS — main.js */
'use strict';

/* TOAST */
function toast(msg, type = 'info', duration = 4000) {
    const c = document.getElementById('toastContainer');
    if (!c) { console.log(`[${type}] ${msg}`); return; }
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    const ic = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    el.innerHTML = `<span style="font-size:16px;">${ic[type] || 'ℹ️'}</span><span style="flex:1;line-height:1.4;">${msg}</span>`;
    el.onclick = () => el.remove();
    c.appendChild(el);
    setTimeout(() => { el.style.transition = 'opacity .4s,transform .4s'; el.style.opacity = '0'; el.style.transform = 'translateX(120%)'; setTimeout(() => el.remove(), 400); }, duration);
}

/* API FETCH — dipakai oleh semua halaman modul (api/<resource>.php) */
async function apiFetch(resource, action, data = {}, method = 'POST') {
    try {
        let url, opts = {};
        if (method === 'GET') {
            url = `api/${resource}.php?${new URLSearchParams({ ...data, action, _t: Date.now() })}`;
            opts = { method: 'GET', cache: 'no-store' };
        } else {
            url = `api/${resource}.php`;
            const b = new FormData();
            b.append('action', action);
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) b.append('csrf_token', csrfMeta.content);
            Object.entries(data).forEach(([k, v]) => b.append(k, v));
            opts = { method: 'POST', body: b };
        }
        const res = await fetch(url, opts);
        return await res.json();
    } catch (err) { return { ok: false, msg: 'Koneksi gagal: ' + err.message }; }
}

/* MODAL */
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

function customConfirm(msg, btnText = 'Ya', btnClass = 'btn-primary', icon = '⚠️') {
    return new Promise(resolve => {
        const id = 'confirm_' + Date.now();
        const html = `
        <div class="modal-overlay open" id="${id}" style="z-index:9999">
            <div class="modal" style="max-width:400px;text-align:center;padding:28px">
                <div style="font-size:42px;margin-bottom:14px">${icon}</div>
                <h3 style="font-family:var(--font-display);font-size:18px;font-weight:600;margin-bottom:8px">Konfirmasi</h3>
                <p style="color:var(--muted);margin-bottom:22px;font-size:13.5px">${msg}</p>
                <div style="display:flex;gap:10px;justify-content:center">
                    <button class="btn btn-outline" style="flex:1" id="btnCancel_${id}">Batal</button>
                    <button class="btn ${btnClass}" style="flex:1" id="btnOk_${id}">${btnText}</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        const overlay = document.getElementById(id);
        const close = (result) => { overlay.remove(); resolve(result); };
        document.getElementById(`btnCancel_${id}`).onclick = () => close(false);
        document.getElementById(`btnOk_${id}`).onclick = () => close(true);
    });
}

/* Klik di luar modal / tombol close menutup modal */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.modal-overlay').forEach(ov => ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); }));
    document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', () => btn.closest('.modal-overlay')?.classList.remove('open')));

    /* Sidebar mobile (jika elemen toggle tersedia) */
    const menuBtn = document.getElementById('menuToggle');
    const sb = document.querySelector('.sidebar');
    if (menuBtn && sb) menuBtn.addEventListener('click', () => sb.classList.toggle('open'));

    /* Tutup panel notifikasi saat klik di luar */
    document.addEventListener('click', (e) => {
        const panel = document.getElementById('notifPanel');
        const btn = document.getElementById('notifBtn');
        if (panel && btn && panel.style.display === 'block' && !panel.contains(e.target) && !btn.contains(e.target)) {
            panel.style.display = 'none';
        }
    });
});
