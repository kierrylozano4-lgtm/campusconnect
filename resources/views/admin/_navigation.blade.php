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
    <a href="{{ route('admin.transactions.index') }}" @if($active === 'transactions') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">↗</span> Manage transactions
    </a>
    <a href="{{ route('admin.financials.index') }}" @if($active === 'financials') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">₱</span> Payouts &amp; refunds
    </a>
    <a href="{{ route('admin.disputes.index') }}" @if($active === 'disputes') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">△</span> Transaction disputes
    </a>
    <a href="{{ route('admin.feedback.index') }}" @if($active === 'feedback') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">✉</span> User feedback
    </a>
    <a href="{{ route('admin.weather.index') }}" @if($active === 'weather') class="is-active" aria-current="page" @endif>
        <span aria-hidden="true">☼</span> Weather report
    </a>
    <a href="{{ route('admin.index') }}#admin-announcement-heading">
        <span aria-hidden="true">◉</span> Broadcast announcement
    </a>
</nav>
