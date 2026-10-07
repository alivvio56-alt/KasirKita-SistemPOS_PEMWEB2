@extends('layouts.base')
@section('title', 'Masuk')
@section('page', 'login')

@section('body')
<div class="auth">
    @include('auth._side')
    <main class="auth-main">
        <div class="auth-card">
            <h1>Masuk</h1>
            <p class="muted" style="margin:6px 0 22px">Gunakan akun kasir atau admin Anda.</p>
            <div id="notice"></div>
            <form id="loginForm" novalidate>
                <label class="field"><span>Email</span><input class="input" type="email" name="email" autocomplete="username" required></label>
                <label class="field"><span>Password</span><input class="input" type="password" name="password" autocomplete="current-password" required></label>
                <button class="btn btn-primary btn-block" type="submit">Masuk</button>
            </form>
            <p class="small muted" style="margin-top:16px">Belum punya akun kasir? <a href="/register">Daftar di sini</a></p>
        </div>
    </main>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const { api, auth, formErrors, formData, submitting, toast, esc } = App;
    if (auth.token()) location.href = '/dashboard';
    const form = document.getElementById('loginForm');
    const notice = document.getElementById('notice');
    if (new URLSearchParams(location.search).has('expired')) notice.innerHTML = '<div class="alert alert-info">Sesi Anda telah berakhir. Silakan masuk kembali.</div>';


    form.addEventListener('submit', (e) => {
        e.preventDefault();
        notice.innerHTML = '';
        submitting(form.querySelector('button[type=submit]'), async () => {
            try {
                const res = await api('POST', '/login', { ...formData(form), device_name: 'web' });
                auth.save(res.data.token, res.data.user);
                toast(res.message);
                location.href = '/dashboard';
            } catch (err) {
                formErrors(form, err.status === 401 ? {} : err.errors);
                notice.innerHTML = `<div class="alert alert-error">${esc(err.message)}</div>`;
            }
        });
    });
})();
</script>
@endpush
