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
            <nav class="nav-links"><a class="nav-active" href="{{ route('dashboard') }}">Marketplace</a><a href="{{ route('leaderboard') }}">Leaderboard</a><a href="{{ route('profile.edit') }}">Profile</a><a href="{{ route('profile.edit', ['tab' => 'notifications']) }}">Notifications ({{ auth()->user()->notifications()->where('is_read', false)->count() }})</a>@if(auth()->user()->is_admin)<a href="{{ route('admin.index') }}">Admin</a>@endif<a href="{{ route('account.setup') }}">Setup</a><form method="POST" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Log out</button></form></nav>
        @else
            <nav class="nav-links"><a href="{{ route('login') }}">Log in</a><a class="button button-small" href="{{ route('register') }}">Join campus</a></nav>
        @endauth
    </header>
    <main class="page-shell">
        @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</body>
</html>