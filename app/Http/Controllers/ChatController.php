<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\ConversationMessenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private ConversationMessenger $messenger) {}

    public function show(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorizeParticipant($request, $serviceRequest);

        return redirect()->route('conversations.show', $serviceRequest->ensureConversation());
    }

    public function store(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $this->authorizeParticipant($request, $serviceRequest);
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        $conversation = $serviceRequest->ensureConversation();
        $this->messenger->sendMessage($conversation, $request->user(), $data['message'], $serviceRequest);

        return redirect()->route('conversations.show', $conversation);
    }

    private function authorizeParticipant(Request $request, ServiceRequest $serviceRequest): void
    {
        abort_unless(in_array($request->user()->id, [$serviceRequest->requester_id, $serviceRequest->provider_id], true), 403);
    }
}
