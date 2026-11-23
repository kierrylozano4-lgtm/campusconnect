@extends('layouts.app')
@section('content')
<section class="panel marketplace-feed" aria-labelledby="marketplace-heading">
    <header class="marketplace-feed-header">
        <div>
            <p class="eyebrow">{{ now()->format('l, F j') }} · campus network</p>
            <h1 id="marketplace-heading">Campus services feed</h1>
            <p class="muted">Find a skill, share yours, and make campus life better together.</p>
        </div>
        <div class="marketplace-feed-tools">
            <span class="marketplace-result-count" data-service-result-count aria-live="polite" aria-atomic="true">Showing {{ $services->count() }} service{{ $services->count() === 1 ? '' : 's' }}</span>
            <div class="marketplace-layout-switch" role="group" aria-label="Service layout">
                <button class="marketplace-layout-button" type="button" data-service-layout="cards" aria-pressed="true">Cards</button>
                <button class="marketplace-layout-button" type="button" data-service-layout="list" aria-pressed="false">List</button>
            </div>
        </div>
    </header>

    <form class="searchbar" method="GET" action="{{ route('dashboard') }}" data-service-search-form>
        <span class="search-icon" aria-hidden="true">⌕</span>
        <input name="search" value="{{ $search }}" placeholder="Search services, skills, or people" aria-label="Search campus services" autocomplete="off" data-service-search-input>
        <select name="category" aria-label="Filter by category" data-service-category-filter>
            <option value="">All categories</option>
            @foreach($categories as $item)
                <option value="{{ $item }}" @selected($category === $item)>{{ $item }}</option>
            @endforeach
        </select>
        <button class="button button-small" type="submit">Filter</button>
    </form>

    <div class="service-grid" data-service-grid data-service-layout="cards">
        @forelse($services as $service)
            <article class="service-card" data-service-card data-service-title="{{ \Illuminate\Support\Str::lower($service->title) }}" data-service-category="{{ $service->category }}">
                <a class="service-card-main" href="{{ route('services.show', $service) }}" aria-label="View details for {{ $service->title }}">
                    <div class="service-card-top">
                        <span class="tag">{{ $service->category }}</span>
                        <span class="service-pulse" aria-label="Active service"></span>
                    </div>
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
                    <h2>{{ $service->title }}</h2>
                    <p>{{ $service->description }}</p>
                    <span class="service-card-link-hint">View details <span aria-hidden="true">→</span></span>
                </a>
                <footer>
                    @if($service->user)
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
                    @else
                        <span class="provider-mark">?</span>
                        <span class="provider-name muted">Unknown provider</span>
                    @endif
                </footer>
            </article>
        @empty
            <div class="marketplace-empty">
                <span aria-hidden="true">⌕</span>
                <strong>No services found</strong>
                <p>Try another search or category, or check back later for new listings.</p>
            </div>
        @endforelse
        @if($services->isNotEmpty())
            <div class="marketplace-empty" data-service-search-empty hidden>
                <span aria-hidden="true">⌕</span>
                <strong>No services found</strong>
                <p>Try another search or category, or check back later for new listings.</p>
            </div>
        @endif
    </div>
</section>

<section id="service-post" class="panel post-panel marketplace-post-panel" aria-labelledby="post-service-heading">
    <div class="section-title">
        <div>
            <p class="eyebrow">Share your skills</p>
            <h2 id="post-service-heading">Post a new service</h2>
        </div>
        <span class="panel-glyph" aria-hidden="true">+</span>
    </div>
    <p class="muted">Offer something useful to other students on campus.</p>
    <form method="POST" action="{{ route('services.store') }}" class="form-stack">
        @csrf
        <label>Title<input name="title" value="{{ old('title') }}" required maxlength="150" placeholder="What can you help with?"></label>
        <label>Category
            <select name="category" required>
                <option value="Academic" @selected(old('category') === 'Academic')>Academic</option>
                <option value="Personal" @selected(old('category') === 'Personal')>Personal</option>
                <option value="Tech" @selected(old('category') === 'Tech')>Tech</option>
            </select>
        </label>
        <label>Description<textarea name="description" rows="4" required maxlength="5000" placeholder="Give students a clear starting point.">{{ old('description') }}</textarea></label>
        <label>Price (₱)<input name="price" type="number" min="0" max="99999999.99" step="0.01" value="{{ old('price') }}" placeholder="Enter a price or choose negotiable"></label>
        <label class="check"><input name="price_negotiable" type="checkbox" value="1" @checked(old('price_negotiable'))> Price is negotiable (or discuss the price)</label>
        <label>Pricing note <span class="muted">Optional</span><input name="price_note" value="{{ old('price_note') }}" maxlength="500" placeholder="e.g. Materials included"></label>
        <button class="button" type="submit">Publish service <span aria-hidden="true">↗</span></button>
    </form>
</section>

<div class="request-grid">
    <section id="incoming-requests" class="panel">
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
            @if($item->status === 'Completed')
                @include('dashboard._review-form', ['serviceRequest' => $item])
            @endif
        @empty
            <p class="muted">No incoming requests.</p>
        @endforelse
    </section>
    <section id="my-requests" class="panel">
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
            @if($item->status === 'Completed')
                @include('dashboard._review-form', ['serviceRequest' => $item])
            @endif
        @empty
            <p class="muted">You have not requested a service.</p>
        @endforelse
    </section>
</div>
@endsection