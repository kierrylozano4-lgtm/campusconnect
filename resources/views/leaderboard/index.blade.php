@extends('layouts.app')
@section('content')
<section class="section-heading"><div><p class="eyebrow">Campus reputation</p><h1>Leaderboard</h1><p class="muted">Recognizing students who consistently show up for the campus community.</p></div><a class="button button-small" href="{{ route('dashboard') }}">Back to marketplace</a></section>
<div class="profile-grid">
    <section class="panel"><div class="section-title"><h2>Top rated providers</h2><span>Quality focus</span></div>
        @forelse($topRated as $index => $entry)<div class="leader-row"><strong class="rank">{{ $index + 1 }}</strong><div><a class="text-link" href="{{ route('profile.show', $entry->reviewedUser) }}">{{ $entry->reviewedUser->full_name }}</a><span>{{ $entry->reviewedUser->profile?->course_major ?? 'Student' }}</span></div><b>{{ number_format($entry->average_rating, 1) }} / 5<small>{{ $entry->review_count }} reviews</small></b></div>@empty<p class="empty muted">No ratings recorded yet.</p>@endforelse
    </section>
    <section class="panel"><div class="section-title"><h2>Most completed services</h2><span>Activity focus</span></div>
        @forelse($topCompleted as $index => $entry)<div class="leader-row"><strong class="rank">{{ $index + 1 }}</strong><div><a class="text-link" href="{{ route('profile.show', $entry->provider) }}">{{ $entry->provider->full_name }}</a><span>{{ $entry->provider->profile?->course_major ?? 'Student' }}</span></div><b>{{ $entry->completed_count }}<small>completed</small></b></div>@empty<p class="empty muted">No completed services yet.</p>@endforelse
    </section>
</div>
@endsection
