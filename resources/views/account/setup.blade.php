@extends('layouts.app')
@section('content')
<section class="panel account-setup-panel">
	<div class="section-title"><div><p class="eyebrow">Student identity</p><h1>Campus account setup</h1><p class="muted">Confirm the details classmates use to recognize and collaborate with you.</p></div><a class="text-link" href="{{ route('profile.edit', ['tab' => 'settings']) }}">Back to settings</a></div>
	<form method="POST" action="{{ route('account.setup.save') }}" class="form-stack">@csrf @method('PUT')
		<label>Campus / dorm address<input name="campus_address" value="{{ old('campus_address', $profile?->campus_address) }}" required></label>
		<div class="two-col"><label>Grade / year<input name="grade_level" value="{{ old('grade_level', $profile?->grade_level) }}" required></label><label>Course / major<input name="course_major" value="{{ old('course_major', $profile?->course_major) }}" required></label></div>
		<label>Facebook<input name="facebook" value="{{ old('facebook', $profile?->facebook) }}"></label>
		<label>Instagram<input name="instagram" value="{{ old('instagram', $profile?->instagram) }}"></label>
		<label>TikTok<input name="tiktok" value="{{ old('tiktok', $profile?->tiktok) }}"></label>
		<button class="button" type="submit">Save campus details</button>
	</form>
</section>
@endsection
