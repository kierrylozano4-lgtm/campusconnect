@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Account access</p>
        <h1>Manage users</h1>
        <p class="muted">Review campus accounts and manage administrator access.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Admin overview</a>
</section>

@include('admin._navigation', ['active' => 'users'])

<section class="panel admin-section" aria-labelledby="admin-users-heading">
            <div class="admin-section-heading">
                <div>
                    <p class="eyebrow">Campus directory</p>
                    <h2 id="admin-users-heading">User accounts</h2>
                    <p class="muted">Manage roles and remove accounts when needed.</p>
                </div>
                <span class="admin-count"><strong>{{ $users->count() }}</strong><span>accounts</span></span>
            </div>

            <form id="bulk-user-form" method="POST" action="{{ route('admin.users.bulk') }}" class="admin-user-form">
                @csrf
                @method('PATCH')
                <div class="admin-bulk-user-panel">
                    <label class="check admin-select-all">
                        <input type="checkbox" data-select-all-users aria-label="Select all eligible users">
                        Select all
                    </label>
                    <span class="admin-selection-hint">Select one or more accounts to apply a bulk action.</span>
                    <label class="admin-bulk-action">
                        Bulk action
                        <select name="action" required>
                            <option value="">Choose an action</option>
                            <option value="promote">Make admin</option>
                            <option value="demote">Make member</option>
                            <option value="delete">Delete selected</option>
                        </select>
                    </label>
                    <button class="button button-small" type="submit">Apply to selected</button>
                </div>
            </form>

            <div class="admin-list">
                @forelse($users as $user)
                    <div class="admin-row admin-user-row">
                        <div class="admin-user-select">
                            @if(!$user->is(auth()->user()))
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" form="bulk-user-form" class="admin-user-checkbox" aria-label="Select {{ $user->full_name }}">
                            @else
                                <span class="admin-current-user" aria-label="Your account">You</span>
                            @endif
                        </div>
                        <div>
                            <strong>{{ $user->full_name }}</strong>
                            <span>{{ $user->email }} · {{ $user->profile?->course_major ?? 'No course' }}</span>
                        </div>
                        <div class="row-actions">
                            <span class="status {{ $user->is_admin ? 'ongoing' : 'pending' }}">{{ $user->is_admin ? 'Admin' : 'Member' }}</span>
                            @if(!$user->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.role', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="button button-small" type="submit">{{ $user->is_admin ? 'Demote' : 'Make admin' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-small button-danger" type="submit">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="admin-empty">No campus accounts were found.</p>
                @endforelse
            </div>
</section>
@endsection
