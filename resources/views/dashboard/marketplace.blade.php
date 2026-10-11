@extends('layouts.app')
@section('content')
<section class="cockpit">
    <div class="cockpit-metrics">
        <p class="eyebrow">{{ now()->format('l, F j') }} · campus network</p>
        <h1>Good to see you, {{ Str::before(auth()->user()->full_name, ' ') }}.</h1>
        <div class="metric"><strong>{{ $stats['services'] }}</strong><span>Active Services</span></div>
        <div class="metric metric-secondary"><strong>{{ $stats['requests'] }}</strong><span>Open Requests</span></div>
    </div>
</section>
<div class="cockpit-modules">
    <a class="cockpit-module" href="{{ route('dashboard') }}"><span class="module-icon">◌</span><span><strong>Services</strong><small>{{ $services->count() }} signals in view</small></span><b>↗</b></a>
    <a class="cockpit-module cockpit-module-alert" href="{{ route('leaderboard') }}"><span class="module-icon">!</span><span><strong>Network pulse</strong><small>{{ $stats['requests'] }} requests need attention</small></span><b>↗</b></a>
    <div class="cockpit-status"><span class="signal-live"><i></i> Network live</span><small>Updated {{ now()->format('g:i A') }}</small></div>
</div>
<div class="dashboard-grid">
    <section class="content-column">
        <div class="section-title section-title-large">
            <div><p class="eyebrow">Explore the network</p><h2>Services on campus</h2></div>
            <span>{{ $services->count() }} results</span>
        </div>
        <form class="searchbar" method="GET" action="{{ route('dashboard') }}">
            <span class="search-icon">⌕</span>
            <input name="search" value="{{ $search }}" placeholder="Search skills, topics, or people">
            <select name="category">
                <option value="">All categories</option>
                @foreach($categories as $item)
                    <option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>
                @endforeach
            </select>
            <button class="button button-small" type="submit">Search</button>
        </form>
        <div class="service-grid">
            @forelse($services as $service)
                <article class="service-card">
                    <a class="service-card-main" href="{{ route('services.show', $service) }}" aria-label="View details for {{ $service->title }}">
                        <div class="service-card-top"><span class="tag">{{ $service->category }}</span><span class="service-pulse"></span></div>
                        <div class="service-card-price">
                            @if($service->price !== null)
                                <strong>₱{{ number_format((float) $service->price, 2) }}</strong>
                                @if($service->price_negotiable)<span>Negotiable</span>@endif
                            @elseif($service->price_negotiable)
                                <strong>Negotiable</strong>
                            @else
                                <strong>Price not listed</strong>
                            @endif
                        </div>
                        <h3>{{ $service->title }}</h3>
                        <p>{{ $service->description }}</p>
                        <span class="service-card-link-hint">View details <span aria-hidden="true">→</span></span>
                    </a>
                    <footer>
                        <span class="provider-mark">{{ $service->user->initials() }}</span>
                        <span class="provider-name">{{ $service->user->full_name }}</span>
                        <a class="provider-profile-link" href="{{ route('profile.show', $service->user) }}" aria-label="View {{ $service->user->full_name }}'s profile">Profile</a>
                        @if($service->user_id === auth()->id())
                            <span class="muted service-owner">Your service</span>
                        @else
                            <form method="POST" action="{{ route('services.request', $service) }}">
                                @csrf
                                <button class="button button-small" type="submit">Request help</button>
                            </form>
                        @endif
                    </footer>
                </article>
            @empty
                <div class="empty">No services match that search yet.</div>
            @endforelse
        </div>
    </section>
    <aside class="sidebar-column">
        <section class="panel post-panel">
            <div class="section-title"><div><p class="eyebrow">Add a signal</p><h2>Post a service</h2></div><span class="panel-glyph">+</span></div>
            <p class="muted">Share something useful with the network.</p>
            <form method="POST" action="{{ route('services.store') }}" class="form-stack">
                @csrf
                <label>Title<input name="title" value="{{ old('title') }}" required maxlength="150" placeholder="What can you help with?"></label>
                <label>Category<select name="category" required><option value="Academic" @selected(old('category') === 'Academic')>Academic</option><option value="Personal" @selected(old('category') === 'Personal')>Personal</option><option value="Tech" @selected(old('category') === 'Tech')>Tech</option></select></label>
                <label>Description<textarea name="description" rows="4" required maxlength="5000" placeholder="Give students a clear starting point.">{{ old('description') }}</textarea></label>
                <label>Price (₱)<input name="price" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('price') }}" placeholder="Enter a price or choose negotiable"></label>
                <label class="check"><input name="price_negotiable" type="checkbox" value="1" @checked(old('price_negotiable'))> Price is negotiable (or discuss the price)</label>
                <label>Pricing note <span class="muted">Optional</span><input name="price_note" value="{{ old('price_note') }}" maxlength="500" placeholder="e.g. Materials included"></label>
                <button class="button" type="submit">Publish service <span>↗</span></button>
            </form>
        </section>
    </aside>
</div>
<div class="request-grid">
    <section class="panel">
        <div class="section-title"><h2>Incoming requests</h2><span>{{ $incoming->count() }}</span></div>
        @forelse($incoming as $item)
            <div class="request-row">
                <div><strong>{{ $item->requester->full_name }}</strong><span>{{ $item->service->title }}</span><small class="status {{ strtolower($item->status) }}">{{ $item->status }}</small></div>
                <div class="row-actions">
                    @if($item->status === 'Pending')
                        <form method="POST" action="{{ route('requests.update', $item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Ongoing"><button class="button button-small" type="submit">Accept</button></form>
                    @elseif(in_array($item->status, ['Accepted', 'Ongoing'], true))
                        <form method="POST" action="{{ route('requests.update', $item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Completed"><button class="button button-small" type="submit">Complete</button></form>
                    @endif
                    <a class="text-link" href="{{ route('chat.show', $item) }}">Chat</a>
                </div>
            </div>
        @empty
            <p class="muted">No incoming requests.</p>
        @endforelse
    </section>
    <section class="panel">
        <div class="section-title"><h2>My requests</h2><span>{{ $outgoing->count() }}</span></div>
        @forelse($outgoing as $item)
            <div class="request-row">
                <div><strong>{{ $item->service->title }}</strong><span>with {{ $item->provider->full_name }}</span><small class="status {{ strtolower($item->status) }}">{{ $item->status }}</small></div>
                <div class="row-actions">
                    @if($item->status === 'Pending')
                        <form method="POST" action="{{ route('requests.update', $item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Cancelled"><button class="button button-small" type="submit">Cancel request</button></form>
                    @elseif(in_array($item->status, ['Accepted', 'Ongoing'], true))
                        <form method="POST" action="{{ route('requests.update', $item) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="Completed"><button class="button button-small" type="submit">Complete</button></form>
                    @endif
                    <a class="text-link" href="{{ route('chat.show', $item) }}">Chat</a>
                </div>
            </div>
        @empty
            <p class="muted">You have not requested a service.</p>
        @endforelse
    </section>
</div>
@endsection