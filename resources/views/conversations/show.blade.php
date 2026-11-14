@extends('layouts.app')
@section('content')
<section class="conversation-shell panel" aria-labelledby="conversation-heading">
    <header class="conversation-header">
        <a class="conversation-back" href="{{ route('conversations.index') }}" aria-label="Back to Requests and Chat">←</a>
        <span class="conversation-avatar" aria-hidden="true">{{ $otherUser->initials() }}</span>
        <div>
            <p class="eyebrow">Campus conversation</p>
            <h1 id="conversation-heading">{{ $otherUser->full_name }}</h1>
            <a class="conversation-profile-link" href="{{ route('profile.show', $otherUser) }}">View profile</a>
        </div>
    </header>

    <div class="conversation-messages" aria-live="polite">
        @forelse($conversation->messages as $message)
            @if($message->type === 'text')
                <article class="conversation-message {{ $message->sender_id === auth()->id() ? 'mine' : '' }}">
                    @if($message->sender_id !== auth()->id())<strong>{{ $message->sender->full_name }}</strong>@endif
                    <p>{{ $message->message }}</p>
                    <small>{{ $message->created_at->format('M j, g:i A') }}@if($message->sender_id === auth()->id() && $message->read_at) · Seen @endif</small>
                </article>
            @else
                <article class="conversation-event">
                    <span class="conversation-event-icon" aria-hidden="true">{{ $message->type === 'service_request' ? '↗' : '◷' }}</span>
                    <div>
                        <strong>{{ $message->type === 'service_request' ? 'Service request' : 'Request update' }}</strong>
                        <p>{{ $message->message }}</p>
                        @if($message->serviceRequest)
                            <a href="{{ route('dashboard') }}#{{ $message->serviceRequest->provider_id === auth()->id() ? 'incoming-requests' : 'my-requests' }}">View request · {{ $message->serviceRequest->status }}</a>
                        @endif
                    </div>
                    <small>{{ $message->created_at->format('M j, g:i A') }}</small>
                </article>
            @endif
        @empty
            <div class="conversation-empty conversation-empty-inline">
                <p>No messages yet. Send a message to start the conversation.</p>
            </div>
        @endforelse
    </div>

    <form class="conversation-composer" method="POST" action="{{ route('conversations.messages.store', $conversation) }}">
        @csrf
        <label class="sr-only" for="conversation-message">Your message</label>
        <textarea id="conversation-message" name="message" rows="2" placeholder="Write a message..." required maxlength="5000">{{ old('message') }}</textarea>
        <button class="button" type="submit">Send</button>
    </form>
</section>
@endsection
