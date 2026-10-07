@extends('layouts.app')
@section('title', 'Kartu Stok')
@section('page', 'stock')
@section('admin', '1')

@section('content')
<div class="page-head">
    <div><h1>Kartu stok</h1><p>Semua pergerakan stok: stok awal, restock, penjualan, pembatalan, dan stock opname.</p></div>
</div>
<section class="card">
    <form class="toolbar" id="filters" onsubmit="return false">
        <select class="input grow" name="product_id" id="prod" aria-label="Produk"><option value="">Semua produk</option></select>
        <select class="input" name="type" aria-label="Jenis">
            <option value="">Semua jenis</option><option value="in">Stok masuk</option><option value="out">Penjualan</option>
            <option value="return">Pengembalian</option><option value="adjustment">Penyesuaian</option>
        </select>
        <input class="input" type="date" name="date_from" aria-label="Dari tanggal">
        <input class="input" type="date" name="date_to" aria-label="Sampai tanggal">
    </form>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Waktu</th><th>Produk</th><th>Jenis</th><th class="right">Qty</th><th class="right">Stok</th><th>Referensi</th></tr></thead>
        <tbody id="rows"><tr><td colspan="6" class="empty"><span class="spinner"></span></td></tr></tbody>
    </table></div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, dt, esc, badge, fail, formData, pager } = App;
    if (!(await boot())) return;
    const filters = document.getElementById('filters');
    let page = 1;
    try {
        const p = await api('GET', '/products?per_page=100');
        document.getElementById('prod').insertAdjacentHTML('beforeend', p.data.map((x) => `<option value="${x.id}">${esc(x.name)} (${esc(x.sku)})</option>`).join(''));
    } catch (e) { fail(e); }

    async function load() {
        try {
            const r = await api('GET', '/stock-movements' + qs({ ...formData(filters), page }));
            document.getElementById('rows').innerHTML = r.data.length ? r.data.map((s) => `
                <tr><td class="small">${dt(s.created_at)}</td><td><b>${esc(s.product.name)}</b><div class="small muted mono">${esc(s.product.sku)}</div></td>
                <td>${badge(s.type, s.type_label)}</td><td class="right num"><b>${s.quantity > 0 ? '+' : ''}${s.quantity}</b></td>
                <td class="right num small">${s.stock_before} → <b>${s.stock_after}</b></td>
                <td class="small">${s.order ? `<a class="mono" href="/orders?open=${s.order.id}">${esc(s.order.code)}</a>` : esc(s.note || '-')}<div class="muted">${esc(s.user ? s.user.name : '')}</div></td></tr>`).join('')
                : '<tr><td colspan="6" class="empty">Tidak ada data.</td></tr>';
            pager(document.getElementById('pager'), r, (p) => { page = p; load(); });
        } catch (e) { fail(e); }
    }
    filters.oninput = () => { page = 1; load(); };
    load();
})();
</script>
@endpush
