@extends('layouts.app')
@section('title', 'Pelanggan')
@section('page', 'customers')

@section('content')
<div class="page-head">
    <div><h1>Pelanggan</h1><p>Data pelanggan tetap beserta riwayat transaksinya.</p></div>
    <button class="btn btn-primary" id="add">+ Tambah pelanggan</button>
</div>
<section class="card">
    <div class="toolbar"><input class="input grow" id="search" type="search" placeholder="Cari nama, no. HP, atau email…" aria-label="Cari pelanggan"></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama</th><th>Kontak</th><th class="right">Pesanan</th><th class="right">Total belanja</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="5" class="empty"><span class="spinner"></span></td></tr></tbody>
    </table></div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, rupiah, dt, esc, badge, toast, fail, modal, confirmBox, formData, formErrors, submitting, pager, debounce } = App;
    const user = await boot(); if (!user) return;
    let page = 1, search = '';

    async function load() {
        try {
            const r = await api('GET', '/customers' + qs({ search, page }));
            document.getElementById('rows').innerHTML = r.data.length ? r.data.map((c) => `
                <tr class="clickable" data-id="${c.id}">
                    <td><b>${esc(c.name)}</b>${c.notes ? `<div class="small muted">${esc(c.notes)}</div>` : ''}</td>
                    <td class="small">${esc(c.phone || '-')}<div class="muted">${esc(c.email || '')}</div></td>
                    <td class="right num">${c.orders_count}</td>
                    <td class="right num">${rupiah(c.total_spent)}</td>
                    <td class="right"><button class="btn btn-sm" data-edit="${c.id}">Ubah</button>
                        ${user.role === 'admin' ? `<button class="btn btn-sm btn-danger" data-del="${c.id}">Hapus</button>` : ''}</td>
                </tr>`).join('') : '<tr><td colspan="5" class="empty">Belum ada pelanggan.</td></tr>';
            pager(document.getElementById('pager'), r, (p) => { page = p; load(); });
            window._c = Object.fromEntries(r.data.map((c) => [c.id, c]));
        } catch (e) { fail(e); }
    }

    function form(c) {
        const m = modal({
            title: c ? 'Ubah pelanggan' : 'Pelanggan baru',
            body: `<form id="f" novalidate>
                <label class="field"><span>Nama</span><input class="input" name="name" value="${esc(c?.name)}"></label>
                <div class="form-row"><label class="field"><span>No. HP</span><input class="input" name="phone" inputmode="tel" value="${esc(c?.phone)}"></label>
                <label class="field"><span>Email</span><input class="input" type="email" name="email" value="${esc(c?.email)}"></label></div>
                <label class="field"><span>Alamat</span><textarea class="input" name="address" rows="2">${esc(c?.address)}</textarea></label>
                <label class="field"><span>Catatan</span><input class="input" name="notes" value="${esc(c?.notes)}" placeholder="mis. alergi kacang, langganan bolu"></label></form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="s">Simpan</button>`,
        });
        const f = m.querySelector('#f');
        m.querySelector('#s').onclick = (e) => submitting(e.currentTarget, async () => {
            try {
                const r = c ? await api('PUT', '/customers/' + c.id, formData(f)) : await api('POST', '/customers', formData(f));
                toast(r.message); m.close(); load();
            } catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    async function detail(id) {
        try {
            const [c, o] = await Promise.all([api('GET', '/customers/' + id), api('GET', `/customers/${id}/orders?per_page=20`)]);
            modal({
                title: c.data.name, wide: true,
                body: `<dl class="kv"><dt>No. HP</dt><dd>${esc(c.data.phone || '-')}</dd><dt>Email</dt><dd>${esc(c.data.email || '-')}</dd>
                    <dt>Alamat</dt><dd>${esc(c.data.address || '-')}</dd><dt>Total belanja</dt><dd>${rupiah(c.data.total_spent)} (${c.data.orders_count} pesanan)</dd></dl>
                    <h3 style="margin:18px 0 8px">Riwayat transaksi</h3>
                    <div class="table-wrap" style="border:1px solid var(--line);border-radius:10px"><table class="table"><tbody>
                    ${o.data.length ? o.data.map((x) => `<tr class="clickable" onclick="location.href='/orders?open=${x.id}'"><td class="mono">${esc(x.code)}</td><td class="small">${dt(x.created_at)}</td><td>${badge(x.status, x.status_label)}</td><td class="right num">${rupiah(x.total)}</td></tr>`).join('') : '<tr><td class="empty">Belum ada transaksi.</td></tr>'}
                    </tbody></table></div>`,
            });
        } catch (e) { fail(e); }
    }

    document.getElementById('rows').onclick = async (e) => {
        const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
        if (ed) return form(window._c[ed.dataset.edit]);
        if (del) {
            const c = window._c[del.dataset.del];
            if (!(await confirmBox(`Hapus pelanggan ${c.name}? Riwayat pesanannya tetap tersimpan tanpa nama pelanggan.`, { danger: true, okText: 'Hapus' }))) return;
            try { const r = await api('DELETE', '/customers/' + c.id); toast(r.message); load(); } catch (err) { fail(err); }
            return;
        }
        const tr = e.target.closest('tr[data-id]'); if (tr) detail(tr.dataset.id);
    };
    document.getElementById('add').onclick = () => form(null);
    document.getElementById('search').oninput = debounce((e) => { search = e.target.value; page = 1; load(); });
    load();
})();
</script>
@endpush
