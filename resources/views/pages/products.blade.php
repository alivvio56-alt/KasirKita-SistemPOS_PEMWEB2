@extends('layouts.app')
@section('title', 'Produk')
@section('page', 'products')

@section('content')
<div class="page-head">
    <div><h1>Produk &amp; stok</h1><p>Katalog menu, harga, dan ketersediaan stok.</p></div>
    <button class="btn btn-primary hidden" id="add" data-admin-only>+ Tambah produk</button>
</div>
<section class="card">
    <form class="toolbar" id="filters" onsubmit="return false">
        <input class="input grow" type="search" name="search" placeholder="Cari nama atau SKU…" aria-label="Cari produk">
        <select class="input" name="category_id" id="catFilter" aria-label="Kategori"><option value="">Semua kategori</option></select>
        <select class="input" name="sort" aria-label="Urutkan">
            <option value="name">Nama A–Z</option><option value="-price">Harga tertinggi</option><option value="price">Harga terendah</option>
            <option value="stock">Stok paling sedikit</option><option value="-created_at">Terbaru</option>
        </select>
        <label class="check"><input type="checkbox" name="low_stock"> Stok menipis</label>
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Produk</th><th class="hide-sm">Kategori</th><th class="right">Harga</th><th class="right">Stok</th><th class="hide-sm">Status</th><th></th></tr></thead>
        <tbody id="rows"><tr><td colspan="6" class="empty"><span class="spinner"></span></td></tr></tbody>
    </table></div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, rupiah, dt, esc, badge, toast, fail, modal, confirmBox, formData, formErrors, submitting, pager, debounce } = App;
    const user = await boot(); if (!user) return;
    const admin = user.role === 'admin';
    const filters = document.getElementById('filters');
    if (new URLSearchParams(location.search).get('low_stock')) filters.low_stock.checked = true;
    let page = 1, cats = [], cache = {};

    try {
        cats = (await api('GET', '/categories?per_page=100')).data;
        document.getElementById('catFilter').insertAdjacentHTML('beforeend', cats.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join(''));
    } catch (e) { fail(e); }

    async function load() {
        try {
            const r = await api('GET', '/products' + qs({ ...formData(filters), page }));
            cache = Object.fromEntries(r.data.map((p) => [p.id, p]));
            document.getElementById('rows').innerHTML = r.data.length ? r.data.map((p) => `
                <tr>
                    <td><b>${esc(p.name)}</b><div class="small muted mono">${esc(p.sku)}</div></td>
                    <td class="hide-sm">${esc(p.category ? p.category.name : '-')}</td>
                    <td class="right num">${rupiah(p.price)}</td>
                    <td class="right num"><b>${p.stock}</b> <span class="muted small">${esc(p.unit)}</span>${p.is_low_stock ? `<div>${badge('low', 'Menipis (min ' + p.min_stock + ')')}</div>` : ''}</td>
                    <td class="hide-sm">${p.is_active ? badge('completed', 'Dijual') : badge('unpaid', 'Nonaktif')}</td>
                    <td class="right" style="white-space:nowrap"><button class="btn btn-sm" data-act="hist" data-id="${p.id}">Riwayat</button>
                    ${admin ? `<button class="btn btn-sm btn-dark" data-act="stock" data-id="${p.id}">Stok</button>
                    <button class="btn btn-sm" data-act="edit" data-id="${p.id}">Ubah</button>
                    <button class="btn btn-sm btn-danger" data-act="del" data-id="${p.id}">Hapus</button>` : ''}</td>
                </tr>`).join('') : '<tr><td colspan="6" class="empty">Tidak ada produk.</td></tr>';
            pager(document.getElementById('pager'), r, (p) => { page = p; load(); });
        } catch (e) { fail(e); }
    }

    function productForm(p) {
        const m = modal({
            title: p ? 'Ubah produk' : 'Produk baru',
            body: `<form id="f" novalidate>
                <div class="form-row"><label class="field"><span>SKU</span><input class="input" name="sku" value="${esc(p?.sku)}" placeholder="KOP-010"></label>
                <label class="field"><span>Kategori</span><select class="input" name="category_id">${cats.map((c) => `<option value="${c.id}" ${p?.category_id == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}</select></label></div>
                <label class="field"><span>Nama produk</span><input class="input" name="name" value="${esc(p?.name)}"></label>
                <div class="form-row"><label class="field"><span>Harga (Rp)</span><input class="input" type="number" min="0" name="price" value="${p ? p.price : ''}"></label>
                <label class="field"><span>Satuan</span><input class="input" name="unit" value="${esc(p?.unit || 'pcs')}"></label></div>
                <div class="form-row">${p ? '' : `<label class="field"><span>Stok awal</span><input class="input" type="number" min="0" name="stock" value="0"></label>`}
                <label class="field"><span>Stok minimum (peringatan)</span><input class="input" type="number" min="0" name="min_stock" value="${p ? p.min_stock : 5}"></label></div>
                <label class="field"><span>Deskripsi</span><textarea class="input" name="description" rows="2">${esc(p?.description)}</textarea></label>
                <label class="check"><input type="checkbox" name="is_active" ${!p || p.is_active ? 'checked' : ''}> Tampilkan &amp; jual di kasir</label>
                ${p ? '<p class="small muted">Stok diubah melalui tombol <b>Stok</b> agar setiap perubahan tercatat di kartu stok.</p>' : ''}</form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="s">Simpan</button>`,
        });
        const f = m.querySelector('#f');
        m.querySelector('#s').onclick = (e) => submitting(e.currentTarget, async () => {
            const d = formData(f);
            ['price', 'stock', 'min_stock', 'category_id'].forEach((k) => { if (d[k] !== null && d[k] !== undefined) d[k] = Number(d[k]); });
            try {
                const r = p ? await api('PUT', '/products/' + p.id, d) : await api('POST', '/products', d);
                toast(r.message); m.close(); load();
            } catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    function stockForm(p) {
        const m = modal({
            title: 'Stok: ' + p.name,
            body: `<p style="margin-top:0">Stok saat ini <b class="num">${p.stock} ${esc(p.unit)}</b></p>
                <form id="f" novalidate>
                <label class="field"><span>Jenis</span><select class="input" name="type"><option value="in">Restock (tambah stok)</option><option value="adjustment">Stock opname (set stok sesuai hitungan fisik)</option></select></label>
                <label class="field"><span id="ql">Jumlah ditambahkan</span><input class="input" type="number" min="0" name="quantity"></label>
                <label class="field"><span>Catatan</span><input class="input" name="note" placeholder="mis. Produksi pagi / barang rusak"></label></form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="s">Simpan</button>`,
        });
        const f = m.querySelector('#f');
        f.type.onchange = () => m.querySelector('#ql').textContent = f.type.value === 'in' ? 'Jumlah ditambahkan' : 'Stok sebenarnya (hasil hitung)';
        m.querySelector('#s').onclick = (e) => submitting(e.currentTarget, async () => {
            const d = formData(f); if (d.quantity !== null) d.quantity = Number(d.quantity);
            try { const r = await api('POST', `/products/${p.id}/stock-movements`, d); toast(`${r.message} Stok ${p.name}: ${r.data.product.stock}`); m.close(); load(); }
            catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    async function history(p) {
        try {
            const r = await api('GET', `/products/${p.id}/stock-movements?per_page=30`);
            modal({
                title: 'Kartu stok: ' + p.name, wide: true,
                body: `<div class="table-wrap" style="border:1px solid var(--line);border-radius:10px"><table class="table">
                    <thead><tr><th>Waktu</th><th>Jenis</th><th class="right">Qty</th><th class="right">Stok</th><th>Keterangan</th></tr></thead><tbody>
                    ${r.data.map((s) => `<tr><td class="small">${dt(s.created_at)}</td><td>${badge(s.type, s.type_label)}</td>
                        <td class="right num">${s.quantity > 0 ? '+' : ''}${s.quantity}</td><td class="right num small">${s.stock_before} → <b>${s.stock_after}</b></td>
                        <td class="small">${esc(s.note || '')}${s.user ? `<div class="muted">${esc(s.user.name)}</div>` : ''}</td></tr>`).join('') || '<tr><td colspan="5" class="empty">Belum ada pergerakan stok.</td></tr>'}
                    </tbody></table></div>`,
            });
        } catch (e) { fail(e); }
    }

    document.getElementById('rows').onclick = async (e) => {
        const b = e.target.closest('[data-act]'); if (!b) return;
        const p = cache[b.dataset.id];
        if (b.dataset.act === 'edit') productForm(p);
        if (b.dataset.act === 'stock') stockForm(p);
        if (b.dataset.act === 'hist') history(p);
        if (b.dataset.act === 'del') {
            if (!(await confirmBox(`Hapus produk ${p.name}? Riwayat transaksi lama tetap tersimpan.`, { danger: true, okText: 'Hapus' }))) return;
            try { const r = await api('DELETE', '/products/' + p.id); toast(r.message); load(); } catch (err) { fail(err); }
        }
    };
    document.getElementById('add').onclick = () => productForm(null);
    filters.oninput = debounce(() => { page = 1; load(); });
    load();
})();
</script>
@endpush
