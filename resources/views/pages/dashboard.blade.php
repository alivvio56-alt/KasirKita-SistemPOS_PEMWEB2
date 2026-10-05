@extends('layouts.app')
@section('title', 'Dashboard')
@section('page', 'dashboard')

@section('content')
<div class="page-head">
    <div><h1>Halo, <span data-user-name>…</span> 👋</h1><p id="today"></p></div>
    <a class="btn btn-primary" href="/pos">+ Pesanan baru</a>
</div>

<div class="grid grid-4" id="stats">
    @foreach (['Menunggu diproses', 'Sedang diproses', 'Siap diambil', 'Pendapatan hari ini'] as $l)
        <div class="card stat"><div class="label">{{ $l }}</div><div class="value">—</div></div>
    @endforeach
</div>

<div class="grid grid-2" style="margin-top:16px">
    <section class="card">
        <div class="card-head"><h2>Antrian pesanan aktif</h2><a href="/orders?status=pending,processing,ready" class="small">Lihat semua →</a></div>
        <div class="table-wrap"><table class="table"><tbody id="queue"><tr><td class="empty"><span class="spinner"></span></td></tr></tbody></table></div>
    </section>
    <section class="card">
        <div class="card-head"><h2>Preorder mendatang</h2><span class="badge b-ready" id="lowStock" hidden></span></div>
        <div class="table-wrap"><table class="table"><tbody id="preorders"><tr><td class="empty"><span class="spinner"></span></td></tr></tbody></table></div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, boot, rupiah, dt, esc, badge, fail } = App;
    const user = await boot(); if (!user) return;
    document.getElementById('today').textContent = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    try {
        const [dash, queue] = await Promise.all([
            api('GET', '/dashboard'),
            api('GET', '/orders?status=pending,processing,ready&per_page=8'),
        ]);
        const d = dash.data;
        const vals = [
            [d.queue.pending, 'pesanan'], [d.queue.processing, 'pesanan'], [d.queue.ready, 'pesanan'],
            [rupiah(d.today.revenue), d.today.orders_completed + ' transaksi selesai'],
        ];
        document.querySelectorAll('#stats .stat').forEach((el, i) => {
            el.querySelector('.value').textContent = vals[i][0];
            el.insertAdjacentHTML('beforeend', `<div class="hint">${esc(vals[i][1])}</div>`);
        });
        if (d.low_stock_count > 0) {
            const b = document.getElementById('lowStock'); b.hidden = false;
            b.className = 'badge b-low'; b.textContent = d.low_stock_count + ' produk stok menipis';
            b.style.cursor = 'pointer'; b.onclick = () => location.href = '/products?low_stock=1';
        }

        document.getElementById('queue').innerHTML = queue.data.length ? queue.data.map((o) => `
            <tr class="clickable" onclick="location.href='/orders?open=${o.id}'">
                <td><b class="mono">${esc(o.code)}</b><div class="small muted">${esc(o.customer ? o.customer.name : 'Umum')} · ${esc(o.type_label)}</div></td>
                <td>${badge(o.status, o.status_label)}</td>
                <td class="right num"><b>${rupiah(o.total)}</b><div class="small">${badge(o.payment_status, o.payment_status_label)}</div></td>
            </tr>`).join('') : '<tr><td class="empty">Tidak ada pesanan aktif. ☕</td></tr>';

        document.getElementById('preorders').innerHTML = d.upcoming_preorders.length ? d.upcoming_preorders.map((o) => `
            <tr class="clickable" onclick="location.href='/orders?open=${o.id}'">
                <td><b>${esc(o.customer ? o.customer.name : '-')}</b><div class="small muted mono">${esc(o.code)}</div></td>
                <td class="small">Ambil:<br><b>${dt(o.pickup_at)}</b></td>
                <td>${badge(o.status, o.status_label)}</td>
            </tr>`).join('') : '<tr><td class="empty">Belum ada preorder.</td></tr>';
    } catch (e) { fail(e); }
})();
</script>
@endpush
