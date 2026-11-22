@extends('layouts.app')
@section('content')
<div class="profile-cover-wrap">
	@if($profileUser->profile?->cover_photo_path)
		<button class="profile-cover profile-photo-preview" type="button" data-profile-photo-preview data-photo-url="{{ asset('storage/'.$profileUser->profile->cover_photo_path) }}" data-photo-alt="{{ $profileUser->full_name }}'s cover photo" aria-label="Preview {{ $profileUser->full_name }}'s cover photo">
			<img src="{{ asset('storage/'.$profileUser->profile->cover_photo_path) }}" alt="">
		</button>
	@else
		<div class="profile-cover" aria-label="{{ $profileUser->full_name }} has no cover photo"><span aria-hidden="true">CampusConnect</span></div>
	@endif
	@if($isOwnProfile)
		<a class="profile-cover-update" href="{{ route('profile.edit', ['tab' => 'edit']) }}#profile-photo-settings">Update cover photo</a>
	@endif
</div>
<section class="section-heading profile-heading">
	<div>
		<p class="eyebrow">{{ $isOwnProfile ? 'Your profile' : 'Campus member' }}</p>
		<h1>{{ $profileUser->full_name }}</h1>
		<p class="muted">{{ $profileUser->email }} · Student ID {{ $profileUser->student_id }}</p>
	</div>
	<div class="profile-heading-actions">
		<div class="profile-avatar-wrap">
			@if($profileUser->profile?->profile_photo_path)
				<button class="avatar profile-avatar profile-photo-preview" type="button" data-profile-photo-preview data-photo-url="{{ asset('storage/'.$profileUser->profile->profile_photo_path) }}" data-photo-alt="{{ $profileUser->full_name }}'s profile picture" aria-label="Preview {{ $profileUser->full_name }}'s profile picture"><img src="{{ asset('storage/'.$profileUser->profile->profile_photo_path) }}" alt=""></button>
			@else
				<div class="avatar profile-avatar"><span aria-hidden="true">{{ $profileUser->initials() }}</span></div>
			@endif
			@if($isOwnProfile)
				<a class="profile-avatar-update" href="{{ route('profile.edit', ['tab' => 'edit']) }}#profile-photo-settings">Update profile picture</a>
			@endif
		</div>
		@if($isOwnProfile)
			<a class="button button-small" href="{{ route('profile.edit', ['tab' => 'settings']) }}"><span aria-hidden="true">⚙</span> Settings</a>
		@endif
	</div>
</section>
<dialog class="profile-photo-dialog" data-profile-photo-dialog aria-label="Photo preview">
	<form method="dialog"><button class="profile-photo-dialog-close" type="submit" aria-label="Close photo preview">Close <span aria-hidden="true">×</span></button></form>
	<img data-profile-photo-dialog-image src="" alt="">
</dialog>
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
		<section class="panel settings-panel settings-appearance-panel" aria-labelledby="appearance-settings-heading">
			<div class="settings-panel-heading"><span class="settings-index">03</span><div><h3 id="appearance-settings-heading">Website appearance</h3><p class="muted">Choose a visual style and color family for your campus workspace.</p></div></div>
			<form method="POST" action="{{ route('profile.preferences') }}" class="settings-appearance-form" data-appearance-form>
				@csrf
				@method('PUT')
				<label>Design style
					<select name="design_theme" data-theme-select>
						<option value="modern" @selected($profileUser->design_theme === 'modern')>Modern</option>
						<option value="glass" @selected($profileUser->design_theme === 'glass')>Glassmorphism</option>
						<option value="skeuomorphic" @selected($profileUser->design_theme === 'skeuomorphic')>Skeuomorphic</option>
						<option value="brutalist" @selected($profileUser->design_theme === 'brutalist')>Brutalist</option>
						<option value="editorial" @selected($profileUser->design_theme === 'editorial')>Editorial</option>
					</select>
				</label>
				<fieldset class="palette-fieldset">
					<legend>Color combination</legend>
					<div class="palette-options">
						@foreach(['campus' => 'Campus', 'ocean' => 'Ocean', 'botanical' => 'Botanical', 'sunset' => 'Sunset', 'monochrome' => 'Monochrome'] as $palette => $label)
							<label class="palette-choice"><input type="radio" name="design_palette" value="{{ $palette }}" @checked($profileUser->design_palette === $palette)><span class="palette-swatch palette-swatch-{{ $palette }}" aria-hidden="true"></span><span>{{ $label }}</span></label>
						@endforeach
					</div>
				</fieldset>
				<button class="button button-small" type="submit">Apply design</button>
			</form>
		</section>
		<section class="settings-support-grid">
			<section class="panel settings-panel" aria-labelledby="send-feedback-heading">
				<div class="settings-panel-heading"><span class="settings-index">04</span><div><h3 id="send-feedback-heading">Send feedback</h3><p class="muted">Share a concern, report a problem, or suggest an improvement.</p></div></div>
				<form method="POST" action="{{ route('profile.feedback.store') }}" class="settings-feedback-form">@csrf
					<label>Send to<select name="recipient" required><option value="admin">Campus admin team</option><option value="developer">Development team</option></select></label>
					<label>Subject<input name="subject" maxlength="150" required placeholder="What is this about?"></label>
					<label>Message<textarea name="message" rows="5" maxlength="5000" required placeholder="Tell us what happened or what could work better."></textarea></label>
					<button class="button button-small" type="submit">Send feedback</button>
				</form>
			</section>
			<section class="panel settings-panel" aria-labelledby="feedback-history-heading">
				<div class="settings-panel-heading"><span class="settings-index">05</span><div><h3 id="feedback-history-heading">Your feedback</h3><p class="muted">Messages you have sent to the campus team.</p></div></div>
				@forelse($profileUser->feedbackMessages->take(5) as $feedback)
					<article class="settings-feedback-history-item"><div><strong>{{ $feedback->subject }}</strong><span>{{ $feedback->recipient === 'developer' ? 'Development team' : 'Admin team' }} · {{ $feedback->created_at->format('M j, Y') }}</span></div><span class="feedback-status feedback-status-{{ \Illuminate\Support\Str::slug($feedback->status) }}">{{ $feedback->status }}</span></article>
				@empty
					<p class="empty muted">You have not sent any feedback yet.</p>
				@endforelse
			</section>
		</section>
	</section>
@elseif($tab === 'edit')
	<section class="panel profile-edit-panel">
		<div class="section-title"><div><p class="eyebrow">Your public information</p><h2>Edit profile</h2></div><a class="text-link" href="{{ route('profile.edit', ['tab' => 'settings']) }}">Back to settings</a></div>
		<form method="POST" action="{{ route('profile.update') }}" class="form-stack" enctype="multipart/form-data">@csrf @method('PUT')
			<div class="profile-media-fields" id="profile-photo-settings">
				<label>Profile picture<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input data-max-dimension="1200"><small>JPEG, PNG, or WebP up to 2 MB.</small></label>
				@if($profileUser->profile?->profile_photo_path)<label class="check profile-remove-check"><input type="checkbox" name="remove_profile_photo" value="1"> Remove current profile picture</label>@endif
				<label>Cover photo<input type="file" name="cover_photo" accept="image/jpeg,image/png,image/webp" data-profile-photo-input data-max-dimension="1920"><small>Wide images work best. Maximum 2 MB.</small></label>
				@if($profileUser->profile?->cover_photo_path)<label class="check profile-remove-check"><input type="checkbox" name="remove_cover_photo" value="1"> Remove current cover photo</label>@endif
			</div>
			<p class="profile-upload-status" data-profile-upload-status role="status" aria-live="polite" hidden></p>
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