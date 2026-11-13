<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CampusConnect' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ auth()->check() ? 'workspace-page '.(request()->routeIs('dashboard') ? 'dashboard-page' : 'app-page') : (request()->routeIs('login') ? 'login-page' : 'app-page') }}">
    @auth
        <div class="workspace-shell">
            <aside class="workspace-sidebar" aria-label="CampusConnect workspace">
                <a class="workspace-brand" href="{{ route('dashboard') }}">
                    <span class="workspace-brand-mark">CC</span>
                    <span class="workspace-brand-name">CampusConnect</span>
                    <small>v1.2</small>
                </a>

                <section class="workspace-broadcasts" aria-labelledby="workspace-broadcasts-heading">
                    <h2 id="workspace-broadcasts-heading"><span aria-hidden="true">◉</span> Campus broadcasts</h2>
                    @forelse($sidebarAnnouncements as $announcement)
                        <article class="workspace-broadcast">
                            <div>
                                <strong>{{ $announcement->title }}</strong>
                                <time datetime="{{ \Illuminate\Support\Carbon::parse($announcement->created_at)->toDateString() }}">{{ \Illuminate\Support\Carbon::parse($announcement->created_at)->format('M j') }}</time>
                            </div>
                            <p>{{ \Illuminate\Support\Str::limit($announcement->message, 110) }}</p>
                        </article>
                    @empty
                        <p class="workspace-broadcast-empty">No campus broadcasts yet.</p>
                    @endforelse
                </section>

                <nav class="workspace-nav" aria-label="Primary navigation">
                    <h2>Navigate</h2>
                    <a class="{{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <span aria-hidden="true">▦</span><span>Marketplace</span>
                    </a>
                    <a href="{{ route('dashboard') }}#incoming-requests">
                        <span aria-hidden="true">◌</span><span>Requests &amp; chat</span>
                        @if($sidebarStats['requests'] > 0)<small>{{ $sidebarStats['requests'] }}</small>@endif
                    </a>
                    <a class="{{ request()->routeIs('leaderboard') ? 'is-active' : '' }}" href="{{ route('leaderboard') }}" @if(request()->routeIs('leaderboard')) aria-current="page" @endif>
                        <span aria-hidden="true">♛</span><span>Leaderboard</span>
                    </a>
                    <a class="{{ request()->routeIs('profile.show') || (request()->routeIs('profile.edit') && request()->query('tab') !== 'notifications') ? 'is-active' : '' }}" href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.show') || (request()->routeIs('profile.edit') && request()->query('tab') !== 'notifications')) aria-current="page" @endif>
                        <span aria-hidden="true">◉</span><span>My profile</span>
                    </a>
                    <a class="{{ request()->routeIs('profile.edit') && request()->query('tab') === 'notifications' ? 'is-active' : '' }}" href="{{ route('profile.edit', ['tab' => 'notifications']) }}" @if(request()->routeIs('profile.edit') && request()->query('tab') === 'notifications') aria-current="page" @endif>
                        <span aria-hidden="true">◈</span><span>Notifications</span>
                        @if($unreadNotificationCount > 0)<small>{{ $unreadNotificationCount }}</small>@endif
                    </a>
                    @if(auth()->user()->is_admin)
                        <a class="{{ request()->routeIs('admin.*') ? 'is-active' : '' }}" href="{{ route('admin.index') }}" @if(request()->routeIs('admin.*')) aria-current="page" @endif>
                            <span aria-hidden="true">ϟ</span><span>Admin control</span><small>Admin</small>
                        </a>
                    @endif
                    <a class="{{ request()->routeIs('account.setup*') ? 'is-active' : '' }}" href="{{ route('account.setup') }}" @if(request()->routeIs('account.setup*')) aria-current="page" @endif>
                        <span aria-hidden="true">⚙</span><span>Account setup</span>
                    </a>
                </nav>

                <section class="workspace-quick-stats" aria-labelledby="workspace-stats-heading">
                    <h2 id="workspace-stats-heading">Quick stats</h2>
                    <div><span>Campus users</span><strong>{{ $sidebarStats['users'] }}</strong></div>
                    <div><span>Active services</span><strong class="stat-active">{{ $sidebarStats['active_services'] }}</strong></div>
                    <div><span>Inactive services</span><strong class="stat-inactive">{{ $sidebarStats['inactive_services'] }}</strong></div>
                </section>

                <div class="workspace-account">
                    <a href="{{ route('profile.edit') }}" class="workspace-account-link">
                        <span class="workspace-account-avatar">{{ auth()->user()->initials() }}</span>
                        <span><strong>{{ auth()->user()->full_name }}</strong><small>{{ auth()->user()->email }}</small></span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="workspace-logout" type="submit">Log out</button>
                    </form>
                </div>
            </aside>

            <div class="workspace-main">
                <header class="workspace-topbar">
                    <div class="workspace-greeting">
                        <span class="workspace-breadcrumb-badge"><span aria-hidden="true">ϟ</span> Workspace</span>
                        <span class="workspace-breadcrumb-divider">/</span>
                        <span class="workspace-breadcrumb-label">{{ request()->routeIs('admin.*') ? 'Admin control center' : (request()->routeIs('dashboard') ? 'Campus marketplace' : 'CampusConnect') }}</span>
                        <span class="workspace-welcome">Welcome back, <strong>{{ \Illuminate\Support\Str::before(auth()->user()->full_name, ' ') }}</strong></span>
                    </div>
                    <div class="workspace-topbar-actions">
                        @if(auth()->user()->is_admin)
                            <a class="workspace-role-badge" href="{{ route('admin.index') }}">Administrator</a>
                        @endif
                        <a class="button button-small workspace-post-link" href="{{ route('dashboard') }}#service-post"><span aria-hidden="true">＋</span> Post new service</a>
                    </div>
                </header>
                <main class="page-shell">
                    @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
                    @if ($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
                    @yield('content')
                </main>
            </div>
        </div>
    @else
        <header class="topbar">
            <a class="brand" href="{{ route('home') }}"><span>CC</span> CampusConnect</a>
            <nav class="nav-links nav-rail" aria-label="Primary navigation">
                <a class="nav-station {{ request()->routeIs('login') ? 'is-active' : '' }}" href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Log in</span></a>
                <a class="nav-station {{ request()->routeIs('register') ? 'is-active' : '' }}" href="{{ route('register') }}" @if(request()->routeIs('register')) aria-current="page" @endif><span class="nav-station-marker" aria-hidden="true"></span><span class="nav-station-label">Join campus</span></a>
            </nav>
        </header>
        <main class="page-shell">
            @if (session('status'))<div class="alert success">{{ session('status') }}</div>@endif
            @if ($errors->any())<div class="alert error">{{ $errors->first() }}</div>@endif
            @yield('content')
        </main>
    @endauth
</body>
</html>
