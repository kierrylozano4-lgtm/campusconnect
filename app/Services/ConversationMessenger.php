<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Notification;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationMessenger
{
    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $message,
        ?ServiceRequest $serviceRequest = null
    ): ConversationMessage {
        return DB::transaction(function () use ($conversation, $sender, $message, $serviceRequest): ConversationMessage {
            $receiver = $conversation->otherParticipant($sender);
            $conversationMessage = $conversation->messages()->create([
                'service_request_id' => $serviceRequest?->id,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'message' => $message,
                'type' => 'text',
            ]);

            Notification::create([
                'user_id' => $receiver->id,
                'message' => $serviceRequest === null
                    ? 'New message from '.$sender->full_name.'.'
                    : 'New message from '.$sender->full_name.' about '.$serviceRequest->service->title.'.',
                'link' => route('conversations.show', $conversation),
                'is_read' => false,
            ]);

            return $conversationMessage;
        });
    }

    public function recordEvent(
        ServiceRequest $serviceRequest,
        User $actor,
        string $message,
        string $type,
        string $notificationMessage
    ): ConversationMessage {
        return DB::transaction(function () use ($serviceRequest, $actor, $message, $type, $notificationMessage): ConversationMessage {
            $conversation = $serviceRequest->ensureConversation();
            $receiver = $conversation->otherParticipant($actor);
            $conversationMessage = $conversation->messages()->create([
                'service_request_id' => $serviceRequest->id,
                'sender_id' => $actor->id,
                'receiver_id' => $receiver->id,
                'message' => $message,
                'type' => $type,
            ]);

            Notification::create([
                'user_id' => $receiver->id,
                'message' => $notificationMessage,
                'link' => route('conversations.show', $conversation),
                'is_read' => false,
            ]);

            return $conversationMessage;
        });
    }
}
