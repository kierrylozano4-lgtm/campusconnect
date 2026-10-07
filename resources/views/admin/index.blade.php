@extends('layouts.app')
@section('content')
<section class="section-heading"><div><p class="eyebrow">Restricted workspace</p><h1>Admin control center</h1><p class="muted">Manage campus accounts, service listings, and announcements.</p></div><a class="button button-small" href="{{ route('dashboard') }}">Back to marketplace</a></section>
<div class="admin-stats">@foreach(['users'=>'Campus users','services'=>'Services','requests'=>'Requests','completed'=>'Completed'] as $key => $label)<div class="panel"><span class="eyebrow">{{ $label }}</span><strong>{{ $stats[$key] }}</strong></div>@endforeach</div>
<div class="profile-grid admin-columns"><section class="panel">
    <div class="section-title"><h2>User controls</h2><span>{{ $users->count() }} total</span></div>
    @foreach($users as $user)
        <div class="admin-row">
            <div><strong>{{ $user->full_name }}</strong><span>{{ $user->email }} · {{ $user->profile?->course_major ?? 'No course' }}</span></div>
            <div class="row-actions">
                <span class="status {{ $user->is_admin ? 'ongoing' : 'pending' }}">{{ $user->is_admin ? 'Admin' : 'Member' }}</span>
                @if(!$user->is(auth()->user()))
                    <form method="POST" action="{{ route('admin.users.role', $user) }}">@csrf @method('PATCH')<button class="button button-small" type="submit">{{ $user->is_admin ? 'Demote' : 'Make admin' }}</button></form>
                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}">@csrf @method('DELETE')<button class="text-link" type="submit">Delete</button></form>
                @endif
            </div>
        </div>
    @endforeach
</section>
<section class="panel">
    <div class="section-title"><h2>Service controls</h2><span>{{ $services->count() }} total</span></div>
    <form id="bulk-service-form" method="POST" action="{{ route('admin.services.bulk') }}" class="form-stack admin-bulk-form">@csrf @method('PATCH')
        <div class="bulk-panel">
            <label>Status<select name="status"><option value="Active">Activate selected</option><option value="Inactive">Deactivate selected</option></select></label>
            <label>Reason<textarea name="inactive_reason" rows="2" placeholder="Optional moderation reason..."></textarea></label>
            <label class="check"><input type="checkbox" name="hide_from_marketplace" value="1"> Hide selected services from the marketplace</label>
            <button class="button button-small" type="submit">Apply to selected</button>
        </div>
    </form>
    @foreach($services as $service)
        <div class="admin-row admin-service-row">
            <div class="service-check-wrap"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" form="bulk-service-form"></div>
            <div class="service-meta"><strong>{{ $service->title }}</strong><span>by {{ $service->user->full_name }} · {{ $service->status }} @if($service->inactive_reason)· {{ $service->inactive_reason }}@endif</span></div>
            <div class="row-actions service-actions">
                <form method="POST" action="{{ route('admin.services.toggle', $service) }}">@csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $service->status === 'Active' ? 'Inactive' : 'Active' }}">
                    <input type="hidden" name="hide_from_marketplace" value="1">
                    <input type="hidden" name="inactive_reason" value="Moderated by administrator">
                    <button class="button button-small" type="submit">{{ $service->status === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                </form>
                <form method="POST" action="{{ route('admin.services.destroy', $service) }}">@csrf @method('DELETE')<button class="text-link" type="submit">Delete</button></form>
            </div>
        </div>
    @endforeach
    <div class="section-title announce-title"><h2>Broadcast</h2></div>
    <form method="POST" action="{{ route('admin.announcements.store') }}" class="form-stack">@csrf<label>Title<input name="title" required maxlength="150"></label><label>Message<textarea name="message" rows="3" required></textarea></label><button class="button" type="submit">Publish announcement</button></form>
</section></div>
@endsection
