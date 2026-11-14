<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use App\Services\ConversationMessenger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function __construct(private ConversationMessenger $messenger) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->where(fn (Builder $query) => $query
                ->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id))
            ->with(['firstParticipant', 'secondParticipant', 'latestMessage'])
            ->withCount(['messages as unread_messages_count' => fn (Builder $query) => $query
                ->where('receiver_id', $user->id)
                ->whereNull('read_at')])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->orderByDesc('id')
            ->get();

        return view('conversations.index', compact('conversations'));
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->id !== $user->id, 403);

        $conversation = Conversation::between($request->user(), $user);

        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless(in_array($request->user()->id, [$conversation->user_one_id, $conversation->user_two_id], true), 403);

        $conversation->messages()
            ->where('receiver_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $notificationLinks = $conversation->serviceRequests()
            ->pluck('id')
            ->map(fn (int $serviceRequestId): string => route('chat.show', $serviceRequestId))
            ->push(route('conversations.show', $conversation))
            ->all();

        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('link', $notificationLinks)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $conversation->load([
            'firstParticipant',
            'secondParticipant',
            'messages.sender',
            'messages.serviceRequest.service',
        ]);
        $otherUser = $conversation->otherParticipant($request->user());

        return view('conversations.show', compact('conversation', 'otherUser'));
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless(in_array($request->user()->id, [$conversation->user_one_id, $conversation->user_two_id], true), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->messenger->sendMessage($conversation, $request->user(), $data['message']);

        return redirect()->route('conversations.show', $conversation);
    }
}
