<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionActivityService
{
    public function record(
        Transaction $transaction,
        User $actor,
        string $eventType,
        string $message,
        array $metadata = [],
    ): void {
        DB::transaction(function () use ($transaction, $actor, $eventType, $message, $metadata): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $serviceRequest = $locked->serviceRequest()->firstOrFail();
            $conversation = $serviceRequest->ensureConversation();
            $receiverId = $actor->id === $locked->client_id
                ? $locked->freelancer_id
                : $locked->client_id;
            $receiver = User::query()->findOrFail($receiverId);

            $activityKey = $eventType.'-'.$locked->id.'-'.hash('sha256', json_encode([$message, $metadata]));

            $conversation->messages()->firstOrCreate([
                'activity_key' => $activityKey,
            ], [
                'service_request_id' => $serviceRequest->id,
                'sender_id' => $actor->id,
                'receiver_id' => $receiver->id,
                'message' => $message,
                'type' => $eventType,
            ]);

            Notification::query()->firstOrCreate([
                'user_id' => $receiverId,
                'message' => $message,
                'link' => route('conversations.show', $conversation),
            ], [
                'is_read' => false,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => $eventType,
                'idempotency_key' => $activityKey,
            ], [
                'actor_id' => $actor->id,
                'from_status' => $locked->lifecycle_status,
                'to_status' => $locked->lifecycle_status,
                'metadata' => $metadata,
            ]);
        });
    }

    public function recordForBoth(
        Transaction $transaction,
        User $actor,
        string $eventType,
        string $message,
        array $metadata = [],
    ): void {
        $this->record($transaction, $actor, $eventType, $message, $metadata);

        $locked = Transaction::query()->findOrFail($transaction->id);
        foreach ([$locked->client_id, $locked->freelancer_id] as $recipientId) {
            if ($recipientId === $actor->id) {
                continue;
            }

            Notification::query()->firstOrCreate([
                'user_id' => $recipientId,
                'message' => $message,
                'link' => route('conversations.show', $locked->serviceRequest->ensureConversation()),
            ], [
                'is_read' => false,
            ]);
        }
    }
}
