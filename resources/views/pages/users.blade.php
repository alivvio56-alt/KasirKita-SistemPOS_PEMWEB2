@extends('layouts.app')
@section('title', 'Pengguna')
@section('page', 'users')
@section('admin', '1')

@section('content')
<div class="page-head">
    <div><h1>Pengguna &amp; hak akses</h1><p><b>Admin</b>: kelola katalog, stok, pengguna, laporan, batalkan pesanan yang sudah diproses. <b>Kasir</b>: catat &amp; proses pesanan, pembayaran, pelanggan.</p></div>
    <button class="btn btn-primary" id="add">+ Tambah pengguna</button>
</div>
<section class="card">
    <div class="toolbar">
        <input class="input grow" id="search" type="search" placeholder="Cari nama atau email…" aria-label="Cari pengguna">
        <select class="input" id="role" aria-label="Role"><option value="">Semua role</option><option value="admin">Admin</option><option value="kasir">Kasir</option></select>
    </div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama</th><th>Role</th><th>Status</th><th class="right">Pesanan dicatat</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="5" class="empty"><span class="spinner"></span></td></tr></tbody>
    </table></div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, esc, badge, toast, fail, modal, confirmBox, formData, formErrors, submitting, pager, debounce } = App;
    const me = await boot(); if (!me) return;
    let page = 1, cache = {};
    const $ = (id) => document.getElementById(id);

    async function load() {
        try {
            const r = await api('GET', '/users' + qs({ search: $('search').value, role: $('role').value, page }));
            cache = Object.fromEntries(r.data.map((u) => [u.id, u]));
            $('rows').innerHTML = r.data.map((u) => `
                <tr><td><b>${esc(u.name)}</b>${u.id === me.id ? ' <span class="badge">Anda</span>' : ''}<div class="small muted">${esc(u.email)}</div></td>
                <td>${badge(u.role === 'admin' ? 'ready' : 'processing', u.role_label)}</td>
                <td>${u.is_active ? badge('completed', 'Aktif') : badge('cancelled', 'Nonaktif')}</td>
                <td class="right num">${u.orders_count}</td>
                <td class="right" style="white-space:nowrap"><button class="btn btn-sm" data-edit="${u.id}">Ubah</button>
                ${u.id !== me.id ? `<button class="btn btn-sm btn-danger" data-del="${u.id}">Hapus</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="5" class="empty">Tidak ada pengguna.</td></tr>';
            pager($('pager'), r, (p) => { page = p; load(); });
        } catch (e) { fail(e); }
    }
    function form(u) {
        const self = u && u.id === me.id;
        const m = modal({
            title: u ? 'Ubah pengguna' : 'Pengguna baru',
            body: `<form id="f" novalidate>
                <label class="field"><span>Nama</span><input class="input" name="name" value="${esc(u?.name)}"></label>
                <div class="form-row"><label class="field"><span>Email</span><input class="input" type="email" name="email" value="${esc(u?.email)}"></label>
                <label class="field"><span>No. HP</span><input class="input" name="phone" value="${esc(u?.phone)}"></label></div>
                <div class="form-row"><label class="field"><span>Role</span><select class="input" name="role" ${self ? 'disabled' : ''}>
                    <option value="kasir" ${u?.role === 'kasir' ? 'selected' : ''}>Kasir</option><option value="admin" ${u?.role === 'admin' ? 'selected' : ''}>Admin</option></select></label>
                <label class="field"><span>${u ? 'Password baru (opsional)' : 'Password'}</span><input class="input" type="password" name="password" autocomplete="new-password"></label></div>
                ${self ? '' : `<label class="check"><input type="checkbox" name="is_active" ${!u || u.is_active ? 'checked' : ''}> Akun aktif (bisa login)</label>`}</form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="s">Simpan</button>`,
        });
        const f = m.querySelector('#f');
        m.querySelector('#s').onclick = (e) => submitting(e.currentTarget, async () => {
            const d = formData(f);
            if (u && !d.password) delete d.password;
            try { const r = u ? await api('PUT', '/users/' + u.id, d) : await api('POST', '/users', d); toast(r.message); m.close(); load(); }
            catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }
    $('rows').onclick = async (e) => {
        const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
        if (ed) form(cache[ed.dataset.edit]);
        if (del) {
            const u = cache[del.dataset.del];
            if (!(await confirmBox(`Hapus ${u.name}? Jika sudah pernah mencatat transaksi, akun hanya dinonaktifkan.`, { danger: true, okText: 'Hapus' }))) return;
            try { const r = await api('DELETE', '/users/' + u.id); toast(r.message); load(); } catch (err) { fail(err); }
        }
    };
    $('add').onclick = () => form(null);
    $('search').oninput = debounce(() => { page = 1; load(); });
    $('role').onchange = () => { page = 1; load(); };
    load();
})();
</script>
@endpush
