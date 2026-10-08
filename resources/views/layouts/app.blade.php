@extends('layouts.base')

@php
    $icons = [
        'dashboard' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
        'pos' => '<path d="M3 3h18v4H3zM5 7v14h14V7M9 11h6M9 15h6"/>',
        'orders' => '<path d="M9 5h11M9 12h11M9 19h11M4 5h.01M4 12h.01M4 19h.01"/>',
        'customers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'products' => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
        'categories' => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
        'stock' => '<path d="M3 3v18h18M7 15l4-4 3 3 5-6"/>',
        'reports' => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
        'users' => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
    ];
    $svg = fn ($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$k].'</svg>';
    $menu = [
        ['Operasional', null],
        ['dashboard', 'Dashboard'], ['pos', 'Kasir (Pesanan Baru)'], ['orders', 'Pesanan'], ['customers', 'Pelanggan'],
        ['Katalog & Stok', null],
        ['products', 'Produk'], ['categories', 'Kategori', true], ['stock', 'Kartu Stok', true],
        ['Pemilik', 'admin'],
        ['reports', 'Laporan', true], ['users', 'Pengguna', true],
    ];
@endphp

@section('body')
<div class="shell">
    <aside class="sidebar" aria-label="Navigasi utama">
        <div class="brand"><span class="brand-mark">☕</span> KasirKita</div>
        <nav class="nav">
            @foreach ($menu as $m)
                @if ($m[1] === null || $m[1] === 'admin')
                    <div class="sep" @if($m[1] === 'admin') data-admin-only @endif>{{ $m[0] }}</div>
                @else
                    <a href="/{{ $m[0] }}" data-page="{{ $m[0] }}" @if(!empty($m[2])) data-admin-only @endif>{!! $svg($m[0]) !!} {{ $m[1] }}</a>
                @endif
            @endforeach
        </nav>
        <div class="me">
            <b data-user-name>…</b>
            <span class="muted small" style="color:rgba(255,248,240,.7)" data-user-role></span>
            <div style="margin-top:8px"><a href="#" data-logout style="color:#ffd9bf;font-weight:700">Keluar</a></div>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button data-nav-toggle aria-label="Buka menu">☰</button>
            <b>KasirKita</b>
            <span class="small" data-user-name></span>
        </header>
        <main class="content">
            @yield('content')
        </main>
    </div>
</div>
@endsection
