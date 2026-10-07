<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="api-base" content="{{ url('/api') }}">
    <meta name="theme-color" content="#5b3a24">
    <title>@yield('title', 'KasirKita') · {{ config('app.name', 'KasirKita') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}?v=1">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='8' fill='%23c8642c'/><text x='16' y='22' font-size='16' text-anchor='middle' fill='white' font-family='sans-serif' font-weight='bold'>K</text></svg>">
</head>
<body data-page="@yield('page')" data-admin="@yield('admin', '0')">
    @yield('body')
    <div class="toasts" aria-live="polite"></div>
    <script src="{{ asset('assets/app.js') }}?v=1"></script>
    @stack('scripts')
</body>
</html>
