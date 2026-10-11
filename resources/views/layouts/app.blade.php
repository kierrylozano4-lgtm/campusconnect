<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CampusConnect' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ request()->routeIs('dashboard') ? 'dashboard-page' : (request()->routeIs('login') ? 'login-page' : 'app-page') }}">
    <header class="topbar">
        <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('home') }}"><span>CC</span> CampusConnect</a>
        @auth
            <nav class="nav-links nav-rail" aria-label="Primary navigation">
                <a class="nav-station {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Marketplace</span></a>
                <a class="nav-station {{ request()->routeIs('leaderboard') ? 'is-active' : '' }}" href="{{ route('leaderboard') }}" @if(request()->routeIs('leaderboard')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Leaderboard</span></a>
                <a class="nav-station {{ request()->routeIs('profile.show') || (request()->routeIs('profile.edit') && request()->query('tab') !== 'notifications') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.show') || (request()->routeIs('profile.edit') && request()->query('tab') !== 'notifications')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Profile</span></a>
                <a class="nav-station {{ request()->routeIs('profile.edit') && request()->query('tab') === 'notifications' ? 'is-active' : '' }}" href="{{ route('profile.edit', ['tab' => 'notifications']) }}" @if(request()->routeIs('profile.edit') && request()->query('tab') === 'notifications') aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Notifications ({{ auth()->user()->notifications()->where('is_read', false)->count() }})</span></a>
                @if(auth()->user()->is_admin)
                    <a class="nav-station {{ request()->routeIs('admin.*') ? 'is-active' : '' }}" href="{{ route('admin.index') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Admin</span></a>
                @endif
                <a class="nav-station {{ request()->routeIs('account.setup*') ? 'is-active' : '' }}" href="{{ route('account.setup') }}" @if(request()->routeIs('account.setup*')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Setup</span></a>
                <form class="nav-station-form" method="POST" action="{{ route('logout') }}">@csrf<button class="link-button nav-station" type="submit"><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Log out</span></button></form>
            </nav>
        @else
            <nav class="nav-links nav-rail" aria-label="Primary navigation">
                <a class="nav-station {{ request()->routeIs('login') ? 'is-active' : '' }}" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Log in</span></a>
                <a class="nav-station {{ request()->routeIs('register') ? 'is-active' : '' }}" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Join campus</span></a>
            </nav>
        @endauth
    </header>
    <main class="page-shell">
        @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</body>
</html>