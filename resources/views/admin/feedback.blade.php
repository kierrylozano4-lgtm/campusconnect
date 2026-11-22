@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Community support</p>
        <h1>User feedback</h1>
        <p class="muted">Review member concerns, product feedback, and messages for the development team.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Back to admin</a>
</section>

@include('admin._navigation', ['active' => 'feedback'])

<section class="panel admin-feedback-panel" aria-labelledby="admin-feedback-heading">
    <div class="admin-section-heading">
        <div>
            <p class="eyebrow">Inbox</p>
            <h2 id="admin-feedback-heading">Member messages</h2>
        </div>
        <span class="admin-feedback-unread">{{ $unreadFeedbackCount }} unread</span>
    </div>

    @forelse($feedbackMessages as $feedback)
        <article class="admin-feedback-item {{ $feedback->read_at ? 'is-read' : 'is-unread' }}">
            <header class="admin-feedback-item-header">
                <div>
                    <span class="admin-feedback-recipient">{{ $feedback->recipient === 'developer' ? 'Development team' : 'Admin team' }}</span>
                    <h3>{{ $feedback->subject }}</h3>
                    <p class="admin-feedback-sender">{{ $feedback->sender_name }} · <a href="mailto:{{ $feedback->sender_email }}">{{ $feedback->sender_email }}</a> · {{ $feedback->created_at->format('M j, Y g:i A') }}</p>
                </div>
                <span class="admin-feedback-status status-{{ \Illuminate\Support\Str::slug($feedback->status) }}">{{ $feedback->status }}</span>
            </header>
            <p class="admin-feedback-message">{{ $feedback->message }}</p>
            @if($feedback->status !== 'Resolved')
                <form method="POST" action="{{ route('admin.feedback.update', $feedback) }}" class="admin-feedback-actions">
                    @csrf
                    @method('PATCH')
                    <button class="button button-small" type="submit" name="status" value="In review">Mark in review</button>
                    <button class="button button-small" type="submit" name="status" value="Resolved">Resolve</button>
                </form>
            @endif
        </article>
    @empty
        <p class="empty muted">No feedback has been submitted yet.</p>
    @endforelse

    {{ $feedbackMessages->links() }}
</section>
@endsection