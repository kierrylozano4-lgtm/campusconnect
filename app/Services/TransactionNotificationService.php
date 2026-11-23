<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Transaction;

class TransactionNotificationService
{
    public function notify(
        int $userId,
        string $message,
        string $link,
        string $deduplicationKey,
    ): Notification {
        return Notification::query()->firstOrCreate([
            'user_id' => $userId,
            'message' => $message,
            'link' => $link,
        ], [
            'is_read' => false,
        ]);
    }

    public function transactionEvent(
        Transaction $transaction,
        int $recipientId,
        string $eventType,
        string $message,
        string $link,
    ): Notification {
        $deduplicationKey = $eventType.'-'.$transaction->id.'-'.$recipientId;

        return $this->notify(
            $recipientId,
            $message,
            $link.'?activity='.$deduplicationKey,
            $deduplicationKey,
        );
    }
}
