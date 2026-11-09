<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Invoices') - {{ config('app.brand') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
    <script>
        // Apply the saved theme before the page paints, so there is no light/dark flash.
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    @auth
    <script>
        // Extra guard: if the browser restores this page from its back/forward cache
        // (e.g. Back after signing out), reload it so the server can check the session.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) window.location.reload();
        });
    </script>
    @endauth
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<header class="top">
    <div class="wrap">
        <a class="brand" href="{{ auth()->check() ? route('invoices.index') : route('login') }}"><span class="tk">৳</span>{{ config('app.brand') }}</a>
        <nav class="main" aria-label="Main">
            @auth
                <a href="{{ route('invoices.create') }}" class="{{ request()->routeIs('invoices.create') ? 'on' : '' }}">New invoice</a>
                <a href="{{ route('invoices.index') }}" class="{{ request()->routeIs('invoices.index', 'invoices.show', 'invoices.edit') ? 'on' : '' }}">Invoices</a>
                <a href="{{ route('vendors.index') }}" class="{{ request()->routeIs('vendors.*') ? 'on' : '' }}">Vendors</a>
                <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.*') ? 'on' : '' }}">My business</a>
                <a href="{{ route('account.edit') }}" class="{{ request()->routeIs('account.*') ? 'on' : '' }}" title="Name, email and password">Account</a>
                @if (auth()->user()->isSuperAdmin())
                    <a href="{{ route('admin.index') }}" class="nav-admin {{ request()->routeIs('admin.*') ? 'on' : '' }}">Super admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Sign out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="{{ request()->routeIs('login') ? 'on' : '' }}">Sign in</a>
                <a href="{{ route('register') }}" class="{{ request()->routeIs('register') ? 'on' : '' }}">Create account</a>
            @endauth
            <div class="theme-switch" role="group" aria-label="Colour theme">
                <button type="button" data-theme-choice="light" aria-pressed="false" title="Light">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <span class="lbl">Light</span>
                </button>
                <button type="button" data-theme-choice="dark" aria-pressed="false" title="Dark">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    <span class="lbl">Dark</span>
                </button>
                <button type="button" data-theme-choice="auto" aria-pressed="false" title="Follow device setting">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                    <span class="lbl">Auto</span>
                </button>
            </div>
        </nav>
    </div>
</header>

<main class="wrap stack">
    @if (session('status'))
        <div class="flash no-print" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="errors no-print" role="alert">
            <strong>Please fix the following:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<script>
    // Light / Dark / Auto switch. The choice is remembered in this browser.
    (function () {
        var root = document.documentElement;
        var buttons = document.querySelectorAll('[data-theme-choice]');

        function current() {
            return root.getAttribute('data-theme') || 'auto';
        }
        function mark() {
            buttons.forEach(function (b) {
                b.setAttribute('aria-pressed', b.dataset.themeChoice === current() ? 'true' : 'false');
            });
        }
        buttons.forEach(function (b) {
            b.addEventListener('click', function () {
                var choice = b.dataset.themeChoice;
                if (choice === 'auto') root.removeAttribute('data-theme');
                else root.setAttribute('data-theme', choice);
                try {
                    if (choice === 'auto') localStorage.removeItem('theme');
                    else localStorage.setItem('theme', choice);
                } catch (e) {}
                mark();
            });
        });
        mark();
    })();
</script>
@stack('scripts')
</body>
</html>
