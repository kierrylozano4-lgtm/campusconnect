<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Notification;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function show(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeParticipant($request, $serviceRequest);
        $serviceRequest->load(['service', 'requester', 'provider']);
        $messages = $serviceRequest->messages()->oldest()->get();
        $otherUser = $serviceRequest->requester_id === $request->user()->id ? $serviceRequest->provider : $serviceRequest->requester;

        return view('chat.show', compact('serviceRequest', 'messages', 'otherUser'));
    }

    public function store(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeParticipant($request, $serviceRequest);
        $data = $request->validate(['message' => ['required', 'string', 'max:5000']]);
        $receiverId = $serviceRequest->requester_id === $request->user()->id ? $serviceRequest->provider_id : $serviceRequest->requester_id;

        Message::create([
            'service_request_id' => $serviceRequest->id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $receiverId,
            'message' => $data['message'],
        ]);

        Notification::create([
            'user_id' => $receiverId,
            'message' => 'New message from '.$request->user()->full_name.' about '.$serviceRequest->service->title.'.',
            'link' => route('chat.show', $serviceRequest),
            'is_read' => false,
        ]);

        return back();
    }

    private function authorizeParticipant(Request $request, ServiceRequest $serviceRequest): void
    {
        abort_unless(in_array($request->user()->id, [$serviceRequest->requester_id, $serviceRequest->provider_id], true), 403);
    }
}
