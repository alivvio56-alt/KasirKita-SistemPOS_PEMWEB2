/* KasirKita — klien kecil untuk REST API (Bearer token Sanctum). */
(function () {
  'use strict';
  const BASE = document.querySelector('meta[name="api-base"]').content.replace(/\/$/, '');
  const TOKEN_KEY = 'kasirkita_token';
  const USER_KEY = 'kasirkita_user';

  const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch {} },
    del(k) { try { localStorage.removeItem(k); } catch {} },
  };

  class ApiError extends Error {
    constructor(message, status, errors) { super(message); this.status = status; this.errors = errors || {}; }
  }

  // ---------------- API ----------------
  async function api(method, path, body) {
    const headers = { Accept: 'application/json' };
    const token = store.get(TOKEN_KEY);
    if (token) headers.Authorization = 'Bearer ' + token;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    let res;
    try {
      res = await fetch(BASE + path, { method, headers, body: body !== undefined ? JSON.stringify(body) : undefined });
    } catch (e) {
      throw new ApiError('Tidak dapat terhubung ke server. Periksa koneksi internet.', 0);
    }
    let data = null;
    try { data = await res.json(); } catch {}

    if (res.status === 401 && !path.startsWith('/login')) {
      auth.clear();
      location.href = '/login?expired=1';
      throw new ApiError('Sesi berakhir', 401);
    }
    if (!res.ok) throw new ApiError((data && data.message) || 'Terjadi kesalahan (' + res.status + ')', res.status, data && data.errors);
    return data;
  }
  const qs = (o) => {
    const p = new URLSearchParams();
    Object.entries(o || {}).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined && v !== false) p.set(k, v === true ? 1 : v); });
    const s = p.toString(); return s ? '?' + s : '';
  };

  // ---------------- Auth ----------------
  const auth = {
    token: () => store.get(TOKEN_KEY),
    user() { try { return JSON.parse(store.get(USER_KEY)); } catch { return null; } },
    save(token, user) { store.set(TOKEN_KEY, token); store.set(USER_KEY, JSON.stringify(user)); },
    setUser(user) { store.set(USER_KEY, JSON.stringify(user)); },
    clear() { store.del(TOKEN_KEY); store.del(USER_KEY); },
    isAdmin() { const u = auth.user(); return u && u.role === 'admin'; },
    async logout() {
      try { await api('POST', '/logout'); } catch {}
      auth.clear(); location.href = '/login';
    },
  };

  // ---------------- Format ----------------
  const rupiah = (n) => 'Rp ' + Math.round(Number(n || 0)).toLocaleString('id-ID');
  const dt = (iso, withTime = true) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) +
      (withTime ? ' ' + d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '');
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const badge = (key, label) => `<span class="badge b-${esc(key)}">${esc(label)}</span>`;

  // ---------------- UI ----------------
  function toast(message, type = 'success', errors) {
    let box = document.querySelector('.toasts');
    if (!box) { box = document.createElement('div'); box.className = 'toasts'; document.body.appendChild(box); }
    const el = document.createElement('div');
    el.className = 'toast ' + (type === 'error' ? 'error' : '');
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    let html = esc(message);
    const list = errors ? Object.values(errors).flat() : [];
    if (list.length) html += '<ul>' + list.slice(0, 5).map((e) => '<li>' + esc(e) + '</li>').join('') + '</ul>';
    el.innerHTML = html;
    box.appendChild(el);
    setTimeout(() => el.remove(), type === 'error' ? 6500 : 3500);
  }
  function fail(err) {
    if (err && err.status === 401) return;
    toast(err.message || 'Terjadi kesalahan', 'error', err.errors);
  }

  /** Tampilkan error validasi di bawah input yang namanya cocok. */
  function formErrors(form, errors) {
    form.querySelectorAll('.field-error').forEach((e) => e.remove());
    form.querySelectorAll('.is-invalid').forEach((e) => e.classList.remove('is-invalid'));
    Object.entries(errors || {}).forEach(([name, msgs]) => {
      const input = form.querySelector(`[name="${name}"]`);
      if (!input) return;
      input.classList.add('is-invalid');
      const div = document.createElement('div');
      div.className = 'field-error'; div.textContent = msgs[0];
      input.insertAdjacentElement('afterend', div);
    });
  }
  function formData(form) {
    const out = {};
    new FormData(form).forEach((v, k) => { out[k] = typeof v === 'string' ? v.trim() : v; });
    form.querySelectorAll('input[type=checkbox][name]').forEach((c) => { out[c.name] = c.checked; });
    Object.keys(out).forEach((k) => { if (out[k] === '') out[k] = null; });
    return out;
  }
  async function submitting(btn, fn) {
    const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
    try { return await fn(); } finally { btn.disabled = false; btn.innerHTML = old; }
  }

  /** Modal sederhana. Mengembalikan elemen modal; tutup dengan modal.close(). */
  function modal({ title, body, foot = '', wide = false, onOpen }) {
    const wrap = document.createElement('div');
    wrap.className = 'modal-backdrop';
    wrap.innerHTML = `<div class="modal ${wide ? 'wide' : ''}" role="dialog" aria-modal="true" aria-label="${esc(title)}">
      <div class="modal-head"><h2>${esc(title)}</h2><button class="icon-btn" data-close aria-label="Tutup">&times;</button></div>
      <div class="modal-body">${body}</div>${foot ? `<div class="modal-foot">${foot}</div>` : ''}</div>`;
    const close = () => { wrap.remove(); document.removeEventListener('keydown', onKey); };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    wrap.addEventListener('click', (e) => { if (e.target === wrap || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(wrap);
    wrap.close = close;
    const first = wrap.querySelector('input:not([type=hidden]),select,textarea');
    if (first) setTimeout(() => first.focus(), 30);
    if (onOpen) onOpen(wrap);
    return wrap;
  }
  function confirmBox(message, { okText = 'Ya, lanjutkan', danger = false } = {}) {
    return new Promise((resolve) => {
      const m = modal({
        title: 'Konfirmasi',
        body: `<p style="margin:0">${esc(message)}</p>`,
        foot: `<button class="btn" data-close>Batal</button><button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-ok>${esc(okText)}</button>`,
      });
      m.querySelector('[data-ok]').onclick = () => { m.close(); resolve(true); };
      m.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => resolve(false)));
    });
  }

  function pager(el, res, onPage) {
    if (!res || !res.meta) { el.innerHTML = ''; return; }
    const m = res.meta;
    el.className = 'pager';
    el.innerHTML = `<span>Menampilkan ${m.from || 0}–${m.to || 0} dari ${m.total} data</span>
      <span class="btn-group"><button class="btn btn-sm" data-p="${m.current_page - 1}" ${m.current_page <= 1 ? 'disabled' : ''}>‹ Sebelumnya</button>
      <span style="align-self:center">Hal. ${m.current_page} / ${m.last_page}</span>
      <button class="btn btn-sm" data-p="${m.current_page + 1}" ${m.current_page >= m.last_page ? 'disabled' : ''}>Berikutnya ›</button></span>`;
    el.querySelectorAll('[data-p]').forEach((b) => b.onclick = () => onPage(Number(b.dataset.p)));
  }
  const debounce = (fn, ms = 300) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  // ---------------- Halaman terproteksi ----------------
  async function boot() {
    const page = document.body.dataset.page;
    const needsAdmin = document.body.dataset.admin === '1';
    if (!auth.token()) { location.href = '/login'; return false; }
    let user = auth.user();
    try { const r = await api('GET', '/me'); user = r.data; auth.setUser(user); } catch (e) { if (e.status === 401) return false; }
    if (needsAdmin && user.role !== 'admin') {
      document.querySelector('.content').innerHTML = '<div class="card card-pad"><h2>Akses ditolak</h2><p class="muted">Halaman ini khusus admin / pemilik.</p><a class="btn" href="/dashboard">Kembali ke Dashboard</a></div>';
      return false;
    }
    document.querySelectorAll('[data-admin-only]').forEach((el) => el.classList.toggle('hidden', user.role !== 'admin'));
    document.querySelectorAll('[data-user-name]').forEach((el) => el.textContent = user.name);
    document.querySelectorAll('[data-user-role]').forEach((el) => el.textContent = user.role_label);
    document.querySelectorAll('.nav a').forEach((a) => a.classList.toggle('active', a.dataset.page === page));
    document.querySelectorAll('[data-logout]').forEach((b) => b.onclick = (e) => { e.preventDefault(); auth.logout(); });
    const shell = document.querySelector('.shell');
    document.querySelectorAll('[data-nav-toggle]').forEach((b) => b.onclick = () => shell.classList.toggle('nav-open'));
    shell && shell.addEventListener('click', (e) => { if (shell.classList.contains('nav-open') && e.target === shell) shell.classList.remove('nav-open'); });
    return user;
  }

  window.App = { api, qs, auth, rupiah, dt, esc, badge, toast, fail, formErrors, formData, submitting, modal, confirmBox, pager, debounce, boot, ApiError };
})();
