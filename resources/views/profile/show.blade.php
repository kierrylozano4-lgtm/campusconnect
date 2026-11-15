@extends('layouts.app')
@section('content')
<section class="section-heading profile-heading">
	<div>
		<p class="eyebrow">{{ $isOwnProfile ? 'Your profile' : 'Campus member' }}</p>
		<h1>{{ $profileUser->full_name }}</h1>
		<p class="muted">{{ $profileUser->email }} · Student ID {{ $profileUser->student_id }}</p>
	</div>
	<div class="profile-heading-actions">
		<div class="avatar" aria-hidden="true">{{ $profileUser->initials() }}</div>
		@if($isOwnProfile)
			<a class="button button-small" href="{{ route('profile.edit', ['tab' => 'settings']) }}"><span aria-hidden="true">⚙</span> Settings</a>
		@endif
	</div>
</section>
@unless($isOwnProfile)<section class="profile-chat-action panel"><div><h2>Start a conversation</h2><p class="muted">Send {{ $profileUser->full_name }} a direct message.</p></div><form method="POST" action="{{ route('conversations.start', $profileUser) }}">@csrf<button class="button" type="submit">Chat with {{ $profileUser->full_name }}</button></form></section>@endunless
<nav class="tabs" aria-label="Profile navigation">
	<a class="{{ $tab === 'profile' ? 'active' : '' }}" href="{{ $isOwnProfile ? route('profile.edit', ['tab' => 'profile']) : route('profile.show', ['user' => $profileUser, 'tab' => 'profile']) }}">Profile</a>
	<a class="{{ $tab === 'services' ? 'active' : '' }}" href="{{ $isOwnProfile ? route('profile.edit', ['tab' => 'services']) : route('profile.show', ['user' => $profileUser, 'tab' => 'services']) }}">Services ({{ $profileUser->services->count() }})</a>
	<a class="{{ $tab === 'reviews' ? 'active' : '' }}" href="{{ $isOwnProfile ? route('profile.edit', ['tab' => 'reviews']) : route('profile.show', ['user' => $profileUser, 'tab' => 'reviews']) }}">Feedback ({{ $profileUser->reviewsReceived->count() }})</a>
	@if($isOwnProfile)
		<a class="{{ in_array($tab, ['settings', 'edit', 'notifications'], true) ? 'active' : '' }}" href="{{ route('profile.edit', ['tab' => 'settings']) }}">Settings</a>
	@endif
</nav>

@if($tab === 'profile')
	<div class="profile-overview-grid">
		<section class="panel profile-about-panel">
			<div class="section-title"><h2>About</h2><span>{{ $profileUser->profile?->verification_status ?? 'Unverified' }}</span></div>
			<p class="profile-copy">{{ $profileUser->profile?->bio ?: 'This student has not added a bio yet.' }}</p>
			<dl class="facts">
				<dt>Course / major</dt><dd>{{ $profileUser->profile?->course_major ?? 'Not provided' }}</dd>
				<dt>Grade / year</dt><dd>{{ $profileUser->profile?->grade_level ?? 'Not provided' }}</dd>
				<dt>Campus address</dt><dd>{{ $profileUser->profile?->campus_address ?? 'Not provided' }}</dd>
			</dl>
			<div class="profile-social-links">
				<h3>Social links</h3>
				<dl class="facts">
					@foreach(['facebook', 'instagram', 'tiktok', 'github'] as $social)
						<dt>{{ ucfirst($social) }}</dt><dd>{{ $profileUser->profile?->{$social} ?: 'Not linked' }}</dd>
					@endforeach
				</dl>
			</div>
		</section>
		<section class="panel profile-feedback-panel" aria-labelledby="profile-feedback-heading">
			<div class="section-title">
				<div><p class="eyebrow">Community feedback</p><h2 id="profile-feedback-heading">Recent feedback</h2></div>
				<a class="text-link" href="{{ $isOwnProfile ? route('profile.edit', ['tab' => 'reviews']) : route('profile.show', ['user' => $profileUser, 'tab' => 'reviews']) }}">View all</a>
			</div>
			<div class="profile-rating-summary">
				<strong>{{ $averageRating !== null ? number_format((float) $averageRating, 1) : '—' }}</strong>
				<span><span class="profile-rating-stars" aria-label="{{ $averageRating !== null ? number_format((float) $averageRating, 1).' out of 5' : 'No ratings yet' }}">{{ $averageRating !== null ? '★' : '☆' }}</span> {{ $profileUser->reviewsReceived->count() }} review{{ $profileUser->reviewsReceived->count() === 1 ? '' : 's' }}</span>
			</div>
			@forelse($profileUser->reviewsReceived->sortByDesc('created_at')->take(3) as $review)
				<article class="profile-feedback-item">
					<div class="profile-feedback-item-heading"><strong>{{ $review->reviewer->full_name }}</strong><span>{{ $review->created_at->format('M j, Y') }}</span></div>
					<div class="profile-rating-stars" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
					<p>{{ $review->comment ?: 'No written comment.' }}</p>
				</article>
			@empty
				<p class="empty muted">No feedback yet. Completed service requests can leave feedback on this profile.</p>
			@endforelse
		</section>
	</div>
@elseif($tab === 'services')
	<section class="service-grid">
		@forelse($profileUser->services as $service)
			<article class="service-card"><span class="tag">{{ $service->category }}</span><h3>{{ $service->title }}</h3><p>{{ $service->description }}</p><span class="status {{ strtolower($service->status) }}">{{ $service->status }}</span></article>
		@empty
			<p class="empty muted">No services posted yet.</p>
		@endforelse
	</section>
@elseif($tab === 'reviews')
	<section class="panel profile-reviews-panel">
		<div class="section-title"><div><p class="eyebrow">Service history</p><h2>Feedback from the campus community</h2></div><span>{{ $profileUser->reviewsReceived->count() }} total</span></div>
		@forelse($profileUser->reviewsReceived->sortByDesc('created_at') as $review)
			<article class="review-row"><strong>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</strong><p>{{ $review->comment ?: 'No written comment.' }}</p><span>by {{ $review->reviewer->full_name }} · {{ $review->created_at->format('M j, Y') }}</span></article>
		@empty
			<p class="empty muted">No feedback yet.</p>
		@endforelse
	</section>
@elseif($tab === 'settings')
	<section class="settings-page">
		<header class="settings-page-heading">
			<div><p class="eyebrow">Account center</p><h2>Profile settings</h2><p class="muted">Manage your campus profile, account details, and personal activity.</p></div>
			<a class="button button-small" href="{{ route('profile.show', $profileUser) }}">Preview profile <span aria-hidden="true">↗</span></a>
		</header>
		<div class="settings-grid">
			<section class="panel settings-panel" aria-labelledby="profile-details-settings-heading">
				<div class="settings-panel-heading"><span class="settings-index">01</span><div><h3 id="profile-details-settings-heading">Profile details</h3><p class="muted">Keep your campus identity clear and up to date.</p></div></div>
				<div class="settings-completion"><div><span>Profile completion</span><strong>{{ $profileCompletion }}%</strong></div><progress max="100" value="{{ $profileCompletion }}">{{ $profileCompletion }}%</progress></div>
				<a class="settings-action" href="{{ route('profile.edit', ['tab' => 'edit']) }}"><span><strong>Edit profile</strong><small>Update your bio, course, year, and social links.</small></span><span aria-hidden="true">↗</span></a>
				<a class="settings-action" href="{{ route('account.setup') }}"><span><strong>Campus account setup</strong><small>Review your campus address and student details.</small></span><span aria-hidden="true">↗</span></a>
			</section>
			<section class="panel settings-panel" aria-labelledby="account-settings-heading">
				<div class="settings-panel-heading"><span class="settings-index">02</span><div><h3 id="account-settings-heading">Account and activity</h3><p class="muted">Your sign-in identity and profile activity.</p></div></div>
				<div class="settings-account-email"><span>Sign-in email</span><strong>{{ $profileUser->email }}</strong></div>
				<a class="settings-action" href="{{ route('profile.edit', ['tab' => 'notifications']) }}"><span><strong>Notifications</strong><small>{{ $profileUser->notifications->where('is_read', false)->count() }} unread notification{{ $profileUser->notifications->where('is_read', false)->count() === 1 ? '' : 's' }}</small></span><span aria-hidden="true">↗</span></a>
				<form method="POST" action="{{ route('logout') }}" class="settings-logout-form">@csrf<button class="settings-action settings-logout" type="submit"><span><strong>Sign out</strong><small>End your CampusConnect session on this device.</small></span><span aria-hidden="true">↗</span></button></form>
			</section>
		</div>
	</section>
@elseif($tab === 'edit')
	<section class="panel profile-edit-panel">
		<div class="section-title"><div><p class="eyebrow">Your public information</p><h2>Edit profile</h2></div><a class="text-link" href="{{ route('profile.edit', ['tab' => 'settings']) }}">Back to settings</a></div>
		<form method="POST" action="{{ route('profile.update') }}" class="form-stack">@csrf @method('PUT')
			<label>Campus / dorm address<input name="campus_address" value="{{ old('campus_address', $profileUser->profile?->campus_address) }}" required></label>
			<div class="two-col"><label>Grade / year<input name="grade_level" value="{{ old('grade_level', $profileUser->profile?->grade_level) }}" required></label><label>Course / major<input name="course_major" value="{{ old('course_major', $profileUser->profile?->course_major) }}" required></label></div>
			<label>Bio<textarea name="bio" rows="5" maxlength="2000">{{ old('bio', $profileUser->profile?->bio) }}</textarea></label>
			@foreach(['facebook', 'instagram', 'tiktok', 'github'] as $social)<label>{{ ucfirst($social) }}<input name="{{ $social }}" value="{{ old($social, $profileUser->profile?->{$social}) }}"></label>@endforeach
			<button class="button" type="submit">Save profile</button>
		</form>
	</section>
@else
	<section class="panel profile-notifications-panel">
		<div class="section-title"><div><p class="eyebrow">Account activity</p><h2>Notifications</h2></div><a class="text-link" href="{{ route('profile.edit', ['tab' => 'settings']) }}">Back to settings</a></div>
		@forelse($profileUser->notifications->sortByDesc('created_at') as $notification)
			<div class="review-row"><div><strong>{{ $notification->message }}</strong><span>{{ $notification->created_at->format('M j, Y g:i A') }}</span></div>@if($notification->is_read === false)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button class="button button-small" type="submit">Mark read</button></form>@endif</div>
		@empty
			<p class="empty muted">No notifications yet.</p>
		@endforelse
	</section>
@endif
@endsection