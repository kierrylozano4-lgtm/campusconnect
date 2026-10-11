@extends('layouts.app')
@section('content')
<div class="service-detail-top"><a class="text-link" href="{{ route('dashboard') }}">← Back to marketplace</a></div>
<article class="service-detail">
    <div class="service-detail-heading">
        <div><p class="eyebrow">{{ $service->category }} service</p><h1>{{ $service->title }}</h1></div>
        <a class="button button-small" href="{{ route('profile.show', $service->user) }}">View provider profile</a>
    </div>
    <div class="service-detail-grid">
        <section class="panel service-detail-copy"><h2>About this service</h2><p>{{ $service->description }}</p></section>
        <aside class="panel service-pricing-panel">
            <p class="eyebrow">Pricing</p>
            @if($service->price !== null)
                <strong class="service-detail-price">₱{{ number_format((float) $service->price, 2) }}</strong>
                @if($service->price_negotiable)<span class="negotiable-label">Negotiable</span>@endif
            @elseif($service->price_negotiable)
                <strong class="service-detail-price">Negotiable</strong>
            @else
                <strong class="service-detail-price">Price not listed</strong>
            @endif
            @if($service->price_note)<p class="pricing-note">{{ $service->price_note }}</p>@endif
        </aside>
    </div>
    <footer class="service-detail-footer">
        <a class="provider-detail" href="{{ route('profile.show', $service->user) }}"><span class="provider-mark">{{ $service->user->initials() }}</span><span><small>Provided by</small><strong>{{ $service->user->full_name }}</strong></span></a>
        @if($service->user_id === auth()->id())
            <span class="muted">This is your service.</span>
        @else
            <div class="service-detail-actions">
                <form method="POST" action="{{ route('services.request', $service) }}">@csrf<input type="hidden" name="start_chat" value="1"><button class="button" type="submit">Chat with provider</button></form>
                <form method="POST" action="{{ route('services.request', $service) }}">@csrf<button class="button button-outline" type="submit">Request help</button></form>
            </div>
        @endif
    </footer>
</article>
@endsection