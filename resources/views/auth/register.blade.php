@extends('layouts.base')
@section('title', 'Daftar')
@section('page', 'register')

@section('body')
<div class="auth">
    @include('auth._side')
    <main class="auth-main">
        <div class="auth-card">
            <h1>Daftar akun kasir</h1>
            <p class="muted" style="margin:6px 0 22px">Akun baru otomatis berperan sebagai <b>Kasir</b>. Hak akses admin diberikan oleh pemilik.</p>
            <div id="notice"></div>
            <form id="regForm" novalidate>
                <label class="field"><span>Nama lengkap</span><input class="input" name="name" autocomplete="name" required></label>
                <label class="field"><span>Email</span><input class="input" type="email" name="email" autocomplete="email" required></label>
                <label class="field"><span>Nomor HP (opsional)</span><input class="input" name="phone" inputmode="tel" placeholder="08xxxxxxxxxx"></label>
                <div class="form-row">
                    <label class="field"><span>Password</span><input class="input" type="password" name="password" autocomplete="new-password" required></label>
                    <label class="field"><span>Ulangi password</span><input class="input" type="password" name="password_confirmation" autocomplete="new-password" required></label>
                </div>
                <p class="small muted" style="margin-top:-6px">Minimal 8 karakter, mengandung huruf dan angka.</p>
                <button class="btn btn-primary btn-block" type="submit">Buat akun</button>
            </form>
            <p class="small muted" style="margin-top:16px">Sudah punya akun? <a href="/login">Masuk</a></p>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const { api, auth, formErrors, formData, submitting, toast, esc } = App;
    const form = document.getElementById('regForm');
    const notice = document.getElementById('notice');
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        notice.innerHTML = '';
        submitting(form.querySelector('button[type=submit]'), async () => {
            try {
                const res = await api('POST', '/register', { ...formData(form), device_name: 'web' });
                auth.save(res.data.token, res.data.user);
                toast(res.message);
                location.href = '/dashboard';
            } catch (err) {
                formErrors(form, err.errors);
                notice.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
            }
        });
    });
})();
</script>
@endpush
