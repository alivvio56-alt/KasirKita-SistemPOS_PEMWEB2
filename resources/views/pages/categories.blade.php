@extends('layouts.app')
@section('title', 'Kategori')
@section('page', 'categories')
@section('admin', '1')

@section('content')
<div class="page-head">
    <div><h1>Kategori produk</h1><p>Pengelompokan menu di layar kasir.</p></div>
    <button class="btn btn-primary" id="add">+ Tambah kategori</button>
</div>
<section class="card">
    <div class="toolbar"><input class="input grow" id="search" type="search" placeholder="Cari kategori…" aria-label="Cari kategori"></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Nama</th><th>Deskripsi</th><th class="right">Jumlah produk</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="4" class="empty"><span class="spinner"></span></td></tr></tbody>
    </table></div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, esc, toast, fail, modal, confirmBox, formData, formErrors, submitting, pager, debounce } = App;
    if (!(await boot())) return;
    let page = 1, search = '', cache = {};

    async function load() {
        try {
            const r = await api('GET', '/categories' + qs({ search, page }));
            cache = Object.fromEntries(r.data.map((c) => [c.id, c]));
            document.getElementById('rows').innerHTML = r.data.length ? r.data.map((c) => `
                <tr><td><b>${esc(c.name)}</b><div class="small muted mono">${esc(c.slug)}</div></td>
                <td class="small">${esc(c.description || '-')}</td><td class="right num">${c.products_count}</td>
                <td class="right" style="white-space:nowrap"><button class="btn btn-sm" data-edit="${c.id}">Ubah</button> <button class="btn btn-sm btn-danger" data-del="${c.id}">Hapus</button></td></tr>`).join('')
                : '<tr><td colspan="4" class="empty">Belum ada kategori.</td></tr>';
            pager(document.getElementById('pager'), r, (p) => { page = p; load(); });
        } catch (e) { fail(e); }
    }
    function form(c) {
        const m = modal({
            title: c ? 'Ubah kategori' : 'Kategori baru',
            body: `<form id="f" novalidate><label class="field"><span>Nama</span><input class="input" name="name" value="${esc(c?.name)}"></label>
                <label class="field"><span>Deskripsi</span><textarea class="input" name="description" rows="3">${esc(c?.description)}</textarea></label></form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="s">Simpan</button>`,
        });
        const f = m.querySelector('#f');
        m.querySelector('#s').onclick = (e) => submitting(e.currentTarget, async () => {
            try { const r = c ? await api('PUT', '/categories/' + c.id, formData(f)) : await api('POST', '/categories', formData(f)); toast(r.message); m.close(); load(); }
            catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }
    document.getElementById('rows').onclick = async (e) => {
        const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
        if (ed) form(cache[ed.dataset.edit]);
        if (del) {
            const c = cache[del.dataset.del];
            if (!(await confirmBox(`Hapus kategori ${c.name}?`, { danger: true, okText: 'Hapus' }))) return;
            try { const r = await api('DELETE', '/categories/' + c.id); toast(r.message); load(); } catch (err) { fail(err); }
        }
    };
    document.getElementById('add').onclick = () => form(null);
    document.getElementById('search').oninput = debounce((e) => { search = e.target.value; page = 1; load(); });
    load();
})();
</script>
@endpush
