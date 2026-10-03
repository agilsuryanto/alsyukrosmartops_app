/* AL-SYUKROSMART OPS — main.js */
'use strict';

/* TOAST / POPUP */
function toast(msg, type = 'info', duration = 4000) {
    if (typeof Swal !== 'undefined') {
        const titleMap = { success: 'Berhasil!', error: 'Terjadi Kesalahan!', warning: 'Peringatan!', info: 'Informasi' };
        Swal.fire({
            title: titleMap[type] || 'Informasi',
            text: msg,
            icon: type,
            showConfirmButton: true,
            confirmButtonText: 'OK',
            confirmButtonColor: 'var(--bs-primary, #145c3f)'
        });
    } else {
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
function showModalError(modalId, msg) {
    const modalBody = document.querySelector(`#${modalId} .modal-body`);
    if (!modalBody) { toast(msg, 'error'); return; }
    let errBox = modalBody.querySelector('.modal-error-box');
    if (!errBox) {
        errBox = document.createElement('div');
        errBox.className = 'modal-error-box';
        errBox.style.cssText = 'margin-bottom:14px;padding:10px 14px;border-radius:var(--bs-border-radius, .6rem);background:var(--danger-tint, #f8e6e2);color:var(--danger, #b3392b);font-size:13px;font-weight:500;display:flex;align-items:center;gap:8px;border:1px solid rgba(179,57,43,0.2);';
        modalBody.insertBefore(errBox, modalBody.firstChild);
    }
    errBox.innerHTML = `<svg style="width:16px;height:16px;flex-shrink:0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg><span style="flex:1;line-height:1.4">${msg}</span>`;
    errBox.style.display = 'flex';
    errBox.scrollIntoView({ behavior: 'smooth', block: 'end' });
}

function clearModalError(modalId) {
    const errBox = document.querySelector(`#${modalId} .modal-error-box`);
    if (errBox) errBox.style.display = 'none';
}

function openModal(id) { 
    clearModalError(id);
    document.getElementById(id)?.classList.add('open'); 
}
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

function customConfirm(msg, btnText = 'Ya', btnClass = 'btn-primary', icon = '⚠️') {
    return new Promise(resolve => {
        const id = 'confirm_' + Date.now();
        const html = `
        <div class="modal-overlay open" id="${id}" style="z-index:9999">
            <div class="modal" style="max-width:400px;text-align:center;padding:28px;margin:auto">
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
        if (window.lucide) lucide.createIcons();
        const overlay = document.getElementById(id);
        const handleKey = (e) => {
            if (e.key === 'Enter') { e.preventDefault(); close(true); }
            if (e.key === 'Escape') { e.preventDefault(); close(false); }
        };
        const close = (result) => { 
            document.removeEventListener('keydown', handleKey);
            overlay.remove(); 
            resolve(result); 
        };
        document.addEventListener('keydown', handleKey);
        const btnOk = document.getElementById(`btnOk_${id}`);
        document.getElementById(`btnCancel_${id}`).onclick = () => close(false);
        btnOk.onclick = () => close(true);
        btnOk.focus();
    });
}

function customPrompt(msg, defaultValue = '') {
    return new Promise(resolve => {
        const id = 'prompt_' + Date.now();
        const html = `
        <div class="modal-overlay open" id="${id}" style="z-index:9999;backdrop-filter:blur(3px)">
            <div class="modal" style="max-width:400px;padding:24px;margin:auto">
                <h3 style="font-family:var(--font-display);font-size:16px;font-weight:600;margin-bottom:12px">${msg}</h3>
                <div class="fld" style="margin-bottom:20px;width:100%">
                    <input type="text" id="input_${id}" value="${defaultValue}" style="width:100%" placeholder="Ketik di sini...">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end">
                    <button class="btn btn-outline" id="btnCancel_${id}">Batal</button>
                    <button class="btn btn-primary" id="btnOk_${id}">OK</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', html);
        const overlay = document.getElementById(id);
        const input = document.getElementById(`input_${id}`);
        const handleKey = (e) => {
            if (e.key === 'Enter') { e.preventDefault(); close(input.value); }
            if (e.key === 'Escape') { e.preventDefault(); close(null); }
        };
        const close = (result) => {
            document.removeEventListener('keydown', handleKey);
            overlay.remove();
            resolve(result);
        };
        document.addEventListener('keydown', handleKey);
        const btnOk = document.getElementById(`btnOk_${id}`);
        document.getElementById(`btnCancel_${id}`).onclick = () => close(null);
        btnOk.onclick = () => close(input.value);
        input.focus();
    });
}

const _lastRender = {};
function updateDOM(id, newHTML) {
    if (_lastRender[id] !== newHTML) {
        const el = document.getElementById(id);
        if (el) {
            el.innerHTML = newHTML;
            _lastRender[id] = newHTML;
            if (window.lucide) lucide.createIcons({ nameAttr: 'data-lucide' }); // re-render icons if changed
        }
    }
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
