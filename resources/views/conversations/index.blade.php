@extends('layouts.app')
@section('content')
<section class="conversation-inbox panel" aria-labelledby="conversation-inbox-heading">
    <header class="conversation-inbox-heading">
        <div>
            <p class="eyebrow">Your campus conversations</p>
            <h1 id="conversation-inbox-heading">Requests &amp; Chat</h1>
            <p class="muted">Messages and service-request updates with other students.</p>
        </div>
        <span class="conversation-count">{{ $conversations->count() }} conversation{{ $conversations->count() === 1 ? '' : 's' }}</span>
    </header>

    <div class="conversation-list">
        @forelse($conversations as $conversation)
            @php($otherUser = $conversation->otherParticipant(auth()->user()))
            <a class="conversation-list-item {{ $conversation->unread_messages_count > 0 ? 'has-unread' : '' }}" href="{{ route('conversations.show', $conversation) }}">
                <span class="conversation-avatar" aria-hidden="true">{{ $otherUser->initials() }}</span>
                <span class="conversation-preview">
                    <strong>{{ $otherUser->full_name }}</strong>
                    <span>{{ $conversation->latestMessage?->message ?? 'Conversation started. Send a message to say hello.' }}</span>
                </span>
                <span class="conversation-meta">
                    @if($conversation->latestMessage)<time datetime="{{ $conversation->latestMessage->created_at->toIso8601String() }}">{{ $conversation->latestMessage->created_at->format('M j') }}</time>@endif
                    @if($conversation->unread_messages_count > 0)<small aria-label="{{ $conversation->unread_messages_count }} unread messages">{{ $conversation->unread_messages_count }}</small>@endif
                </span>
            </a>
        @empty
            <div class="conversation-empty">
                <span aria-hidden="true">◌</span>
                <h2>No conversations yet</h2>
                <p>Visit a student's profile and choose Chat to start a conversation. Help requests will also appear here.</p>
                <a class="text-link" href="{{ route('dashboard') }}">Explore campus services</a>
            </div>
        @endforelse
    </div>
</section>
@endsection
