@extends('layouts.app')
@section('title', 'Laporan')
@section('page', 'reports')
@section('admin', '1')

@section('content')
<div class="page-head">
    <div><h1>Laporan penjualan</h1><p>Dihitung dari pesanan berstatus <b>Selesai</b>.</p></div>
    <form class="btn-group" id="range" onsubmit="return false">
        <input class="input" type="date" name="date_from" aria-label="Dari tanggal" style="width:auto">
        <input class="input" type="date" name="date_to" aria-label="Sampai tanggal" style="width:auto">
    </form>
</div>
<div class="grid grid-4" id="sum"></div>
<div class="grid grid-2" style="margin-top:16px">
    <section class="card"><div class="card-head"><h2>Pendapatan harian</h2></div><div class="card-pad" id="daily"></div></section>
    <section class="card"><div class="card-head"><h2>Produk terlaris</h2></div><div class="table-wrap"><table class="table"><tbody id="top"></tbody></table></div></section>
</div>
<section class="card" style="margin-top:16px"><div class="card-head"><h2>Metode pembayaran</h2></div><div class="table-wrap"><table class="table"><tbody id="pay"></tbody></table></div></section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, rupiah, esc, fail, formData } = App;
    if (!(await boot())) return;
    const range = document.getElementById('range');
    const iso = (d) => new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    range.date_to.value = iso(new Date());
    range.date_from.value = iso(new Date(Date.now() - 6 * 86400000));

    async function load() {
        try {
            const r = (await api('GET', '/reports/sales' + qs(formData(range)))).data;
            const s = r.summary;
            document.getElementById('sum').innerHTML = [
                ['Pendapatan', rupiah(s.revenue), `${r.period.from} s/d ${r.period.to}`],
                ['Transaksi selesai', s.orders_completed, 'rata-rata ' + rupiah(s.orders_completed ? s.revenue / s.orders_completed : 0)],
                ['Diskon diberikan', rupiah(s.discount_given), ''],
                ['Pesanan dibatalkan', s.orders_cancelled, s.unpaid_active + ' pesanan aktif belum dibayar'],
            ].map(([l, v, h]) => `<div class="card stat"><div class="label">${l}</div><div class="value">${v}</div><div class="hint">${esc(h)}</div></div>`).join('');

            const max = Math.max(1, ...r.daily.map((d) => d.revenue));
            document.getElementById('daily').innerHTML = r.daily.length ? r.daily.map((d) => `
                <div style="display:grid;grid-template-columns:90px 1fr 110px;gap:10px;align-items:center;margin-bottom:10px">
                    <span class="small">${new Date(d.date + 'T00:00').toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' })}</span>
                    <div class="bar"><i style="width:${(d.revenue / max) * 100}%"></i></div>
                    <span class="num small" style="text-align:right"><b>${rupiah(d.revenue)}</b><br><span class="muted">${d.orders} trx</span></span>
                </div>`).join('') : '<p class="muted">Belum ada penjualan pada periode ini.</p>';

            document.getElementById('top').innerHTML = r.top_products.length ? r.top_products.map((p, i) => `
                <tr><td class="num muted">${i + 1}</td><td><b>${esc(p.product_name)}</b></td><td class="right num">${p.qty} terjual</td><td class="right num">${rupiah(p.revenue)}</td></tr>`).join('')
                : '<tr><td class="empty">Belum ada data.</td></tr>';
            document.getElementById('pay').innerHTML = r.by_payment_method.length ? r.by_payment_method.map((p) => `
                <tr><td><b>${esc(p.label || '-')}</b></td><td class="right num">${p.orders} transaksi</td><td class="right num">${rupiah(p.revenue)}</td></tr>`).join('')
                : '<tr><td class="empty">Belum ada data.</td></tr>';
        } catch (e) { fail(e); }
    }
    range.onchange = load;
    load();
})();
</script>
@endpush
