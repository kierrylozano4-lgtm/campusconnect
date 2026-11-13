@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Restricted workspace</p>
        <h1>Management &amp; reports</h1>
        <p class="muted">Monitor campus activity, manage the marketplace, and keep students informed.</p>
    </div>
    <a class="button button-small" href="{{ route('dashboard') }}">Back to marketplace</a>
</section>

@include('admin._navigation', ['active' => 'home'])

        <div class="admin-stats">
            @foreach(['users' => 'Total campus users', 'services' => 'Active services', 'requests' => 'Total requests made', 'completed' => 'Completed engagements'] as $key => $label)
                <div class="panel admin-stat-card admin-stat-{{ $key }}">
                    <span class="eyebrow">{{ $label }}</span>
                    <strong>{{ $stats[$key] }}</strong>
                </div>
            @endforeach
        </div>

        <section class="panel admin-summary" aria-labelledby="admin-summary-heading">
            <div>
                <p class="eyebrow">Platform overview</p>
                <h2 id="admin-summary-heading">System activity summary</h2>
            </div>
            <p class="muted">Welcome to the administrative control center. Use the navigation above to manage campus accounts, moderate service listings, and broadcast important updates.</p>
        </section>

        <section class="admin-quick-links" aria-label="Management areas">
            <a class="panel admin-quick-link" href="{{ route('admin.users.index') }}">
                <span class="admin-quick-link-icon" aria-hidden="true">01</span>
                <span class="admin-quick-link-copy">
                    <span class="eyebrow">Account access</span>
                    <strong>Manage users</strong>
                    <span>Review campus accounts and administrator access.</span>
                </span>
                <span class="admin-quick-link-arrow" aria-hidden="true">→</span>
            </a>
            <a class="panel admin-quick-link" href="{{ route('admin.services.index') }}">
                <span class="admin-quick-link-icon" aria-hidden="true">02</span>
                <span class="admin-quick-link-copy">
                    <span class="eyebrow">Marketplace moderation</span>
                    <strong>Manage services</strong>
                    <span>Moderate listings and update their availability.</span>
                </span>
                <span class="admin-quick-link-arrow" aria-hidden="true">→</span>
            </a>
        </section>

        <section class="panel admin-section admin-announcement-section" aria-labelledby="admin-announcement-heading">
            <div class="admin-section-heading">
                <div>
                    <p class="eyebrow">Campus updates</p>
                    <h2 id="admin-announcement-heading">Broadcast an announcement</h2>
                    <p class="muted">Share an update with the campus community.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="form-stack">
                @csrf
                <label>Title<input name="title" required maxlength="150"></label>
                <label>Message<textarea name="message" rows="3" required></textarea></label>
                <button class="button" type="submit">Publish announcement</button>
            </form>
        </section>
@endsection
