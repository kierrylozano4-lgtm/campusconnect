<nav class="admin-nav" aria-label="Admin control center">
    <a href="{{ route('admin.index') }}" @if($active === 'home') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">▤</span> Reports &amp; overview
    </a>
    <a href="{{ route('admin.users.index') }}" @if($active === 'users') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">♟</span> Manage campus users
    </a>
    <a href="{{ route('admin.services.index') }}" @if($active === 'services') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">▣</span> Manage services
    </a>
    <a href="{{ route('admin.weather.index') }}" @if($active === 'weather') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">☼</span> Weather report
    </a>
    <a href="{{ route('admin.index') }}#admin-announcement-heading">
        <span aria-hidden="true">◉</span> Broadcast announcement
    </a>
</nav>
