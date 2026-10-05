@extends('layouts.app')
@section('title', 'Kasir')
@section('page', 'pos')

@section('content')
<div class="page-head">
    <div><h1>Pesanan baru</h1><p>Pilih produk, tentukan jenis pesanan, lalu simpan.</p></div>
</div>

<div class="pos">
    <section>
        <div class="card card-pad" style="margin-bottom:12px">
            <input class="input" id="search" type="search" placeholder="Cari produk atau SKU…" aria-label="Cari produk">
            <div class="chips" id="cats" style="margin-top:12px"></div>
        </div>
        <div class="products-grid" id="grid"><div class="empty"><span class="spinner"></span></div></div>
        <button type="button" class="cart-jump" id="jump"><span id="jumpCount">0 item</span><span id="jumpTotal">Rp 0 →</span></button>
    </section>

    <aside class="card cart" aria-label="Keranjang">
        <div class="card-head"><h2>Keranjang</h2><button class="btn btn-sm" id="clear" type="button">Kosongkan</button></div>
        <div class="card-pad" style="border-bottom:1px solid var(--line)">
            <div class="seg" id="type" role="group" aria-label="Jenis pesanan">
                <button type="button" data-v="dine_in" class="active">Makan di tempat</button>
                <button type="button" data-v="take_away">Bawa pulang</button>
                <button type="button" data-v="preorder">Preorder</button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
                <div style="flex:1;min-width:0"><div class="small muted">Pelanggan</div><b id="custName">Umum (tanpa data)</b></div>
                <button class="btn btn-sm" type="button" id="pickCust">Pilih</button>
                <button class="btn btn-sm hidden" type="button" id="clearCust" aria-label="Hapus pelanggan">✕</button>
            </div>
            <label class="field hidden" id="pickupWrap" style="margin:12px 0 0"><span>Jadwal ambil</span><input class="input" type="datetime-local" id="pickup"></label>
        </div>
        <div class="cart-items" id="lines"></div>
        <div class="totals">
            <div class="row"><span class="muted">Subtotal</span><b class="num" id="sub">Rp 0</b></div>
            <div class="row" style="align-items:center"><span class="muted">Diskon (Rp)</span><input class="input num" id="disc" type="number" min="0" step="500" value="0" style="width:130px;text-align:right"></div>
            <div class="row grand"><span>Total</span><span class="num" id="total">Rp 0</span></div>
            <textarea class="input" id="notes" rows="2" placeholder="Catatan (mis. meja 3, less sugar)"></textarea>
            <label class="check" id="autoWrap"><input type="checkbox" id="auto" checked> Langsung proses (stok dipotong)</label>
            <button class="btn btn-primary btn-block" id="save" type="button" style="padding:12px">Simpan pesanan</button>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(async () => {
    const { api, qs, boot, rupiah, esc, toast, fail, modal, formData, formErrors, submitting, debounce } = App;
    if (!(await boot())) return;

    const state = { products: [], cat: '', search: '', cart: new Map(), type: 'dine_in', customer: null };
    const $ = (id) => document.getElementById(id);

    async function loadCats() {
        const r = await api('GET', '/categories?per_page=100');
        $('cats').innerHTML = `<button class="chip active" data-c="">Semua</button>` + r.data.map((c) => `<button class="chip" data-c="${c.id}">${esc(c.name)}</button>`).join('');
        $('cats').onclick = (e) => {
            const b = e.target.closest('.chip'); if (!b) return;
            $('cats').querySelectorAll('.chip').forEach((x) => x.classList.toggle('active', x === b));
            state.cat = b.dataset.c; loadProducts();
        };
    }
    async function loadProducts() {
        try {
            const r = await api('GET', '/products' + qs({ is_active: 1, per_page: 100, category_id: state.cat, search: state.search }));
            state.products = r.data; renderGrid();
        } catch (e) { fail(e); }
    }
    function renderGrid() {
        const pre = state.type === 'preorder';
        $('grid').innerHTML = state.products.length ? state.products.map((p) => {
            const out = p.stock <= 0 && !pre;
            return `<button class="product-tile" data-id="${p.id}" ${out ? 'disabled' : ''}>
                <span class="small muted">${esc(p.category ? p.category.name : '')}</span>
                <span class="name">${esc(p.name)}</span>
                <span class="stock ${p.is_low_stock ? 'low' : ''}">${out ? 'Habis' : 'Stok ' + p.stock + ' ' + esc(p.unit)}</span>
                <span class="price num">${rupiah(p.price)}</span></button>`;
        }).join('') : '<div class="empty card">Produk tidak ditemukan.</div>';
    }
    $('grid').onclick = (e) => {
        const t = e.target.closest('.product-tile'); if (!t || t.disabled) return;
        const p = state.products.find((x) => x.id == t.dataset.id);
        const line = state.cart.get(p.id) || { product: p, quantity: 0 };
        line.quantity++;
        if (state.type !== 'preorder' && line.quantity > p.stock) { toast(`Stok ${p.name} hanya ${p.stock}`, 'error'); return; }
        state.cart.set(p.id, line); renderCart();
    };
    $('search').oninput = debounce((e) => { state.search = e.target.value; loadProducts(); });

    function renderCart() {
        const lines = [...state.cart.values()];
        $('lines').innerHTML = lines.length ? lines.map((l) => `
            <div class="cart-line" data-id="${l.product.id}">
                <div><div class="nm">${esc(l.product.name)}</div><div class="small muted num">${rupiah(l.product.price)}</div></div>
                <b class="num" style="text-align:right">${rupiah(l.product.price * l.quantity)}</b>
                <div class="qty"><button type="button" data-d="-1" aria-label="Kurangi">−</button><input type="number" min="1" value="${l.quantity}" aria-label="Jumlah"><button type="button" data-d="1" aria-label="Tambah">+</button></div>
                <button class="btn btn-sm btn-danger" type="button" data-rm style="justify-self:end">Hapus</button>
            </div>`).join('') : '<div class="empty">Belum ada item. Ketuk produk untuk menambahkan.</div>';
        const sub = lines.reduce((s, l) => s + l.product.price * l.quantity, 0);
        const disc = Math.min(Number($('disc').value || 0), sub);
        $('sub').textContent = rupiah(sub); $('total').textContent = rupiah(sub - disc);
        $('jumpCount').textContent = lines.reduce((s, l) => s + l.quantity, 0) + ' item · Lihat keranjang';
        $('jumpTotal').textContent = rupiah(sub - disc) + ' →';
    }
    $('lines').onclick = (e) => {
        const row = e.target.closest('.cart-line'); if (!row) return;
        const id = Number(row.dataset.id); const l = state.cart.get(id);
        if (e.target.closest('[data-rm]')) state.cart.delete(id);
        else if (e.target.dataset.d) { l.quantity += Number(e.target.dataset.d); if (l.quantity < 1) state.cart.delete(id); }
        else return;
        renderCart();
    };
    $('lines').onchange = (e) => {
        const row = e.target.closest('.cart-line'); if (!row) return;
        const l = state.cart.get(Number(row.dataset.id)); l.quantity = Math.max(1, parseInt(e.target.value) || 1); renderCart();
    };
    $('disc').oninput = renderCart;
    $('jump').onclick = () => document.querySelector('.cart').scrollIntoView({ behavior: 'smooth' });
    $('clear').onclick = () => { state.cart.clear(); renderCart(); };

    $('type').onclick = (e) => {
        const b = e.target.closest('button'); if (!b) return;
        state.type = b.dataset.v;
        $('type').querySelectorAll('button').forEach((x) => x.classList.toggle('active', x === b));
        const pre = state.type === 'preorder';
        $('pickupWrap').classList.toggle('hidden', !pre);
        $('autoWrap').classList.toggle('hidden', pre);
        if (pre && !$('pickup').value) {
            const d = new Date(Date.now() + 86400000); d.setHours(10, 0, 0, 0);
            $('pickup').value = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        }
        renderGrid();
    };

    function setCustomer(c) {
        state.customer = c;
        $('custName').textContent = c ? `${c.name}${c.phone ? ' · ' + c.phone : ''}` : 'Umum (tanpa data)';
        $('clearCust').classList.toggle('hidden', !c);
    }
    $('clearCust').onclick = () => setCustomer(null);
    $('pickCust').onclick = () => {
        const m = modal({
            title: 'Pilih pelanggan',
            body: `<input class="input" id="cs" type="search" placeholder="Cari nama / no. HP…">
                <div id="cl" style="margin:12px 0;max-height:280px;overflow:auto"></div>
                <details><summary style="cursor:pointer;font-weight:700">+ Pelanggan baru</summary>
                <form id="cf" style="margin-top:12px" novalidate>
                    <div class="form-row"><label class="field"><span>Nama</span><input class="input" name="name"></label>
                    <label class="field"><span>No. HP</span><input class="input" name="phone" inputmode="tel"></label></div>
                    <button class="btn btn-dark" type="submit">Simpan & pilih</button></form></details>`,
        });
        const list = m.querySelector('#cl');
        const search = async (term = '') => {
            const r = await api('GET', '/customers' + qs({ search: term, per_page: 8 }));
            list.innerHTML = r.data.map((c) => `<button type="button" class="btn btn-block" style="justify-content:space-between;margin-bottom:6px" data-c='${esc(JSON.stringify({ id: c.id, name: c.name, phone: c.phone }))}'><span>${esc(c.name)}</span><span class="muted small">${esc(c.phone || '')}</span></button>`).join('') || '<p class="muted">Tidak ditemukan.</p>';
        };
        search().catch(fail);
        m.querySelector('#cs').oninput = debounce((e) => search(e.target.value).catch(fail));
        list.onclick = (e) => { const b = e.target.closest('[data-c]'); if (b) { setCustomer(JSON.parse(b.dataset.c)); m.close(); } };
        const f = m.querySelector('#cf');
        f.onsubmit = (e) => {
            e.preventDefault();
            submitting(f.querySelector('button'), async () => {
                try { const r = await api('POST', '/customers', formData(f)); toast(r.message); setCustomer(r.data); m.close(); }
                catch (err) { formErrors(f, err.errors); fail(err); }
            });
        };
    };

    $('save').onclick = () => submitting($('save'), async () => {
        if (!state.cart.size) { toast('Keranjang masih kosong.', 'error'); return; }
        const body = {
            type: state.type,
            customer_id: state.customer ? state.customer.id : null,
            discount: Number($('disc').value || 0),
            notes: $('notes').value.trim() || null,
            items: [...state.cart.values()].map((l) => ({ product_id: l.product.id, quantity: l.quantity })),
        };
        if (state.type === 'preorder') body.pickup_at = $('pickup').value ? $('pickup').value.replace('T', ' ') + ':00' : null;
        try {
            const r = await api('POST', '/orders', body);
            let order = r.data;
            toast(r.message);
            if (state.type !== 'preorder' && $('auto').checked) {
                try { order = (await api('PATCH', `/orders/${order.id}/status`, { status: 'processing' })).data; toast('Pesanan diproses, stok sudah dipotong.'); }
                catch (err) { fail(err); }
            }
            state.cart.clear(); $('disc').value = 0; $('notes').value = ''; setCustomer(null); renderCart(); loadProducts();
            setTimeout(() => location.href = '/orders?open=' + order.id, 600);
        } catch (err) { fail(err); }
    });

    await loadCats().catch(fail);
    await loadProducts();
    renderCart();
})();
</script>
@endpush
