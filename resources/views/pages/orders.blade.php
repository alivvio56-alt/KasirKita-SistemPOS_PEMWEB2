@extends('layouts.app')
@section('title', 'Pesanan')
@section('page', 'orders')

@section('content')
<div class="page-head">
    <div><h1>Pesanan &amp; riwayat transaksi</h1><p>Pantau status produksi dan selesaikan pembayaran.</p></div>
    <a class="btn btn-primary" href="/pos">+ Pesanan baru</a>
</div>

<section class="card">
    <form class="toolbar" id="filters">
        <input class="input grow" type="search" name="search" placeholder="Cari kode / nama / HP pelanggan…" aria-label="Cari">
        <select class="input" name="status" aria-label="Status">
            <option value="">Semua status</option>
            <option value="pending,processing,ready">Aktif (belum selesai)</option>
            <option value="pending">Menunggu</option><option value="processing">Diproses</option>
            <option value="ready">Siap diambil</option><option value="completed">Selesai</option><option value="cancelled">Dibatalkan</option>
        </select>
        <select class="input" name="type" aria-label="Jenis">
            <option value="">Semua jenis</option><option value="dine_in">Makan di tempat</option><option value="take_away">Bawa pulang</option><option value="preorder">Preorder</option>
        </select>
        <select class="input" name="payment_status" aria-label="Pembayaran">
            <option value="">Semua pembayaran</option><option value="unpaid">Belum bayar</option><option value="paid">Lunas</option><option value="refunded">Dikembalikan</option>
        </select>
        <input class="input" type="date" name="date_from" aria-label="Dari tanggal">
        <input class="input" type="date" name="date_to" aria-label="Sampai tanggal">
        <button class="btn" type="reset">Reset</button>
    </form>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Kode</th><th class="hide-sm">Pelanggan</th><th class="hide-sm">Jenis</th><th>Status</th><th class="hide-sm">Pembayaran</th><th class="right">Total</th><th class="hide-sm">Dibuat</th></tr></thead>
            <tbody id="rows"><tr><td colspan="7" class="empty"><span class="spinner"></span></td></tr></tbody>
        </table>
    </div>
    <div id="pager"></div>
</section>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, rupiah, dt, esc, badge, toast, fail, modal, confirmBox, formData, formErrors, submitting, pager, debounce, auth } = App;
    const user = await boot(); if (!user) return;

    const form = document.getElementById('filters');
    const params = new URLSearchParams(location.search);
    ['status', 'type', 'search', 'payment_status'].forEach((k) => { if (params.get(k)) form[k].value = params.get(k); });
    let page = 1;

    async function load() {
        const f = formData(form);
        try {
            const r = await api('GET', '/orders' + qs({ ...f, page, per_page: 15 }));
            document.getElementById('rows').innerHTML = r.data.length ? r.data.map((o) => `
                <tr class="clickable" data-id="${o.id}">
                    <td><b class="mono">${esc(o.code)}</b><div class="small muted">${o.items_count} item · ${esc(o.cashier ? o.cashier.name : '')}</div><div class="small hide-md">${esc(o.customer ? o.customer.name : 'Umum')} · ${esc(o.type_label)}</div></td>
                    <td class="hide-sm">${esc(o.customer ? o.customer.name : 'Umum')}</td>
                    <td class="hide-sm">${esc(o.type_label)}${o.pickup_at ? `<div class="small muted">Ambil ${dt(o.pickup_at)}</div>` : ''}</td>
                    <td>${badge(o.status, o.status_label)}</td>
                    <td class="hide-sm">${badge(o.payment_status, o.payment_status_label)}</td>
                    <td class="right num"><b>${rupiah(o.total)}</b></td>
                    <td class="small hide-sm">${dt(o.created_at)}</td>
                </tr>`).join('') : '<tr><td colspan="7" class="empty">Tidak ada pesanan yang cocok dengan filter.</td></tr>';
            pager(document.getElementById('pager'), r, (p) => { page = p; load(); });
        } catch (e) { fail(e); }
    }
    form.oninput = debounce(() => { page = 1; load(); });
    form.onreset = () => setTimeout(() => { page = 1; load(); });
    form.onsubmit = (e) => e.preventDefault();
    document.getElementById('rows').onclick = (e) => { const tr = e.target.closest('tr[data-id]'); if (tr) openOrder(tr.dataset.id); };

    // ------------------------------------------------------------------
    const FLOW = [['pending', 'Menunggu'], ['processing', 'Diproses'], ['ready', 'Siap'], ['completed', 'Selesai']];
    const ACTIONS = {
        processing: ['Proses & potong stok', 'btn-dark'],
        ready: ['Tandai siap diambil', 'btn-dark'],
        completed: ['Selesaikan pesanan', 'btn-primary'],
    };

    async function openOrder(id) {
        let o;
        try { o = (await api('GET', '/orders/' + id)).data; } catch (e) { return fail(e); }
        const idx = FLOW.findIndex((f) => f[0] === o.status);
        const flow = o.status === 'cancelled'
            ? `<div class="flow"><span class="step" style="background:var(--bad-soft);color:var(--bad)">Dibatalkan${o.cancel_reason ? ': ' + esc(o.cancel_reason) : ''}</span></div>`
            : '<div class="flow">' + FLOW.map((f, i) => `<span class="step ${i < idx ? 'done' : i === idx ? 'now' : ''}">${f[1]}</span>`).join('<span class="arrow">→</span>') + '</div>';

        const canEdit = o.status === 'pending' && o.payment_status !== 'paid' && (user.role === 'admin' || (o.cashier && o.cashier.id === user.id));
        const canCancel = o.allowed_transitions.includes('cancelled') && (o.status === 'pending' || user.role === 'admin');
        const canPay = o.payment_status === 'unpaid' && o.status !== 'cancelled';
        const canDelete = user.role === 'admin' && ['pending', 'cancelled'].includes(o.status);

        const next = o.allowed_transitions.filter((s) => ACTIONS[s]);
        const buttons = [
            canDelete ? `<button class="btn btn-danger" data-act="delete" style="margin-right:auto">Hapus</button>` : '',
            `<button class="btn" data-act="print">Cetak struk</button>`,
            canEdit ? `<button class="btn" data-act="edit">Ubah</button>` : '',
            canCancel ? `<button class="btn btn-danger" data-act="cancel">Batalkan</button>` : '',
            canPay ? `<button class="btn btn-dark" data-act="pay">Bayar</button>` : '',
            ...next.map((s) => `<button class="btn ${ACTIONS[s][1]}" data-act="status" data-s="${s}">${ACTIONS[s][0]}</button>`),
        ].join('');

        const m = modal({
            title: o.code, wide: true,
            body: `${flow}
                <dl class="kv">
                    <dt>Pelanggan</dt><dd>${esc(o.customer ? o.customer.name + (o.customer.phone ? ' · ' + o.customer.phone : '') : 'Umum')}</dd>
                    <dt>Jenis</dt><dd>${esc(o.type_label)}${o.pickup_at ? ' — ambil ' + dt(o.pickup_at) : ''}</dd>
                    <dt>Kasir</dt><dd>${esc(o.cashier ? o.cashier.name : '-')}</dd>
                    <dt>Dibuat</dt><dd>${dt(o.created_at)}</dd>
                    <dt>Pembayaran</dt><dd>${badge(o.payment_status, o.payment_status_label)} ${o.payment_method ? esc(o.payment_method_label) + ' · dibayar ' + rupiah(o.paid_amount) + (o.change_amount > 0 ? ' · kembali ' + rupiah(o.change_amount) : '') : ''}</dd>
                    ${o.notes ? `<dt>Catatan</dt><dd>${esc(o.notes)}</dd>` : ''}
                </dl>
                <div class="table-wrap" style="margin-top:14px;border:1px solid var(--line);border-radius:10px">
                <table class="table"><thead><tr><th>Item</th><th class="right">Harga</th><th class="right">Qty</th><th class="right">Subtotal</th></tr></thead><tbody>
                ${o.items.map((i) => `<tr><td>${esc(i.product_name)}</td><td class="right num">${rupiah(i.price)}</td><td class="right num">${i.quantity}</td><td class="right num">${rupiah(i.subtotal)}</td></tr>`).join('')}
                <tr><td colspan="3" class="right muted">Subtotal</td><td class="right num">${rupiah(o.subtotal)}</td></tr>
                ${o.discount > 0 ? `<tr><td colspan="3" class="right muted">Diskon</td><td class="right num">− ${rupiah(o.discount)}</td></tr>` : ''}
                <tr><td colspan="3" class="right"><b>Total</b></td><td class="right num"><b>${rupiah(o.total)}</b></td></tr>
                </tbody></table></div>
                ${o.status === 'pending' && o.type !== 'preorder' ? '<p class="small muted" style="margin-bottom:0">Stok akan dicek ulang dan dipotong otomatis saat pesanan diproses.</p>' : ''}
                ${o.status === 'pending' && o.type === 'preorder' ? '<p class="small muted" style="margin-bottom:0">Preorder: stok dicek &amp; dipotong saat produksi dimulai (Proses). Pastikan stok sudah di-restock admin.</p>' : ''}`,
            foot: buttons,
        });

        const done = (msg, data) => { toast(msg); m.close(); load(); if (data) openOrder(data.id); };
        m.querySelector('.modal-foot').onclick = async (e) => {
            const b = e.target.closest('[data-act]'); if (!b) return;
            const act = b.dataset.act;
            if (act === 'status') {
                submitting(b, async () => {
                    try { const r = await api('PATCH', `/orders/${o.id}/status`, { status: b.dataset.s }); done(r.message, r.data); }
                    catch (err) { fail(err); }
                });
            } else if (act === 'pay') { m.close(); payModal(o); }
            else if (act === 'cancel') { m.close(); cancelModal(o); }
            else if (act === 'edit') { m.close(); editModal(o); }
            else if (act === 'print') printReceipt(o);
            else if (act === 'delete') {
                if (!(await confirmBox(`Hapus pesanan ${o.code}? Tindakan ini tidak dapat dibatalkan.`, { danger: true, okText: 'Hapus' }))) return;
                try { const r = await api('DELETE', '/orders/' + o.id); toast(r.message); m.close(); load(); } catch (err) { fail(err); }
            }
        };
    }

    function payModal(o) {
        const quick = [o.total, Math.ceil(o.total / 10000) * 10000, Math.ceil(o.total / 50000) * 50000, Math.ceil(o.total / 100000) * 100000]
            .filter((v, i, a) => a.indexOf(v) === i);
        const m = modal({
            title: 'Pembayaran ' + o.code,
            body: `<p style="margin-top:0">Total tagihan <b class="num" style="font-size:1.3rem">${rupiah(o.total)}</b></p>
                <form id="pf" novalidate>
                <label class="field"><span>Metode</span><select class="input" name="payment_method"><option value="cash">Tunai</option><option value="qris">QRIS</option><option value="transfer">Transfer bank</option></select></label>
                <label class="field"><span>Jumlah dibayar</span><input class="input num" type="number" name="paid_amount" min="0" value="${o.total}"></label>
                <div class="btn-group" id="quick">${quick.map((v) => `<button type="button" class="btn btn-sm" data-v="${v}">${rupiah(v)}</button>`).join('')}</div>
                <p>Kembalian: <b class="num" id="chg">Rp 0</b></p></form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="payBtn">Simpan pembayaran</button>`,
        });
        const f = m.querySelector('#pf');
        const upd = () => {
            const cash = f.payment_method.value === 'cash';
            if (!cash) f.paid_amount.value = o.total;
            f.paid_amount.readOnly = !cash;
            m.querySelector('#quick').classList.toggle('hidden', !cash);
            m.querySelector('#chg').textContent = rupiah(Math.max(0, Number(f.paid_amount.value) - o.total));
        };
        f.oninput = upd; f.onchange = upd; upd();
        m.querySelector('#quick').onclick = (e) => { const b = e.target.closest('[data-v]'); if (b) { f.paid_amount.value = b.dataset.v; upd(); } };
        m.querySelector('#payBtn').onclick = (e) => submitting(e.currentTarget, async () => {
            try {
                const d = formData(f); d.paid_amount = Number(d.paid_amount);
                const r = await api('POST', `/orders/${o.id}/payment`, d);
                toast(r.message + (r.data.change_amount > 0 ? ' Kembalian ' + rupiah(r.data.change_amount) : ''));
                m.close(); load(); openOrder(o.id);
            } catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    function cancelModal(o) {
        const m = modal({
            title: 'Batalkan ' + o.code,
            body: `${o.stock_deducted ? '<div class="alert alert-info">Stok yang sudah dipotong akan dikembalikan otomatis.</div>' : ''}
                ${o.payment_status === 'paid' ? '<div class="alert alert-error">Pesanan sudah dibayar — status pembayaran akan menjadi <b>Dikembalikan</b>. Kembalikan uang pelanggan.</div>' : ''}
                <form id="cf" novalidate><label class="field"><span>Alasan pembatalan</span><textarea class="input" name="reason" rows="3" placeholder="mis. Pelanggan membatalkan pesanan"></textarea></label></form>`,
            foot: `<button class="btn" data-close>Kembali</button><button class="btn btn-danger" id="cb">Batalkan pesanan</button>`,
        });
        const f = m.querySelector('#cf');
        m.querySelector('#cb').onclick = (e) => submitting(e.currentTarget, async () => {
            try { const r = await api('PATCH', `/orders/${o.id}/status`, { status: 'cancelled', ...formData(f) }); toast(r.message); m.close(); load(); openOrder(o.id); }
            catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    function editModal(o) {
        const items = o.items.map((i) => ({ product_id: i.product_id, name: i.product_name, quantity: i.quantity }));
        const m = modal({
            title: 'Ubah ' + o.code,
            body: `<form id="ef" novalidate>
                <div id="ei"></div>
                <div class="form-row" style="margin-top:12px">
                    <label class="field"><span>Diskon (Rp)</span><input class="input" type="number" min="0" name="discount" value="${o.discount}"></label>
                    ${o.type === 'preorder' ? `<label class="field"><span>Jadwal ambil</span><input class="input" type="datetime-local" name="pickup_at" value="${o.pickup_at ? new Date(new Date(o.pickup_at) - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16) : ''}"></label>` : ''}
                </div>
                <label class="field"><span>Catatan</span><textarea class="input" name="notes" rows="2">${esc(o.notes || '')}</textarea></label>
                <p class="small muted">Untuk menambah produk lain, batalkan lalu buat pesanan baru dari halaman Kasir.</p></form>`,
            foot: `<button class="btn" data-close>Batal</button><button class="btn btn-primary" id="eb">Simpan perubahan</button>`,
        });
        const draw = () => m.querySelector('#ei').innerHTML = items.map((it, i) => `
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><span style="flex:1">${esc(it.name)}</span>
            <input class="input" type="number" min="1" value="${it.quantity}" data-i="${i}" style="width:90px" aria-label="Jumlah">
            <button type="button" class="btn btn-sm btn-danger" data-rm="${i}" ${items.length < 2 ? 'disabled' : ''}>Hapus</button></div>`).join('');
        draw();
        m.querySelector('#ei').oninput = (e) => { if (e.target.dataset.i) items[e.target.dataset.i].quantity = Number(e.target.value); };
        m.querySelector('#ei').onclick = (e) => { if (e.target.dataset.rm) { items.splice(Number(e.target.dataset.rm), 1); draw(); } };
        const f = m.querySelector('#ef');
        m.querySelector('#eb').onclick = (e) => submitting(e.currentTarget, async () => {
            const d = formData(f);
            const body = { items: items.map(({ product_id, quantity }) => ({ product_id, quantity })), discount: Number(d.discount || 0), notes: d.notes };
            if (d.pickup_at) body.pickup_at = d.pickup_at.replace('T', ' ') + ':00';
            try { const r = await api('PUT', '/orders/' + o.id, body); toast(r.message); m.close(); load(); openOrder(o.id); }
            catch (err) { formErrors(f, err.errors); fail(err); }
        });
    }

    function printReceipt(o) {
        const w = window.open('', '_blank', 'width=380,height=600');
        if (!w) return toast('Izinkan pop-up untuk mencetak struk.', 'error');
        w.document.write(`<html><head><title>${esc(o.code)}</title><style>body{font-family:monospace;font-size:12px;width:280px;margin:10px auto}h3{text-align:center;margin:4px}table{width:100%}td.r{text-align:right}hr{border:0;border-top:1px dashed #000}</style></head><body>
            <h3>KasirKita</h3><div style="text-align:center">${esc(o.code)}<br>${dt(o.created_at)}</div><hr>
            <table>${o.items.map((i) => `<tr><td colspan="2">${esc(i.product_name)}</td></tr><tr><td>${i.quantity} x ${rupiah(i.price)}</td><td class="r">${rupiah(i.subtotal)}</td></tr>`).join('')}</table><hr>
            <table><tr><td>Subtotal</td><td class="r">${rupiah(o.subtotal)}</td></tr>${o.discount > 0 ? `<tr><td>Diskon</td><td class="r">-${rupiah(o.discount)}</td></tr>` : ''}
            <tr><td><b>TOTAL</b></td><td class="r"><b>${rupiah(o.total)}</b></td></tr>
            ${o.payment_method ? `<tr><td>${esc(o.payment_method_label)}</td><td class="r">${rupiah(o.paid_amount)}</td></tr><tr><td>Kembali</td><td class="r">${rupiah(o.change_amount)}</td></tr>` : '<tr><td colspan="2">BELUM DIBAYAR</td></tr>'}</table><hr>
            <div style="text-align:center">Kasir: ${esc(o.cashier ? o.cashier.name : '-')}<br>Terima kasih!</div><script>window.print()<\/script></body></html>`);
        w.document.close();
    }

    await load();
    if (params.get('open')) openOrder(params.get('open'));
})();
</script>
@endpush
