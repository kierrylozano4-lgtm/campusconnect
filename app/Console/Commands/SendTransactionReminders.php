<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\TransactionNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-transaction-reminders')]
#[Description('Send deduplicated transaction deadline reminders')]
class SendTransactionReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(TransactionNotificationService $notifications): int
    {
        $now = now();
        $transactions = Transaction::query()
            ->whereNotNull('deadline')
            ->whereIn('lifecycle_status', [
                'ORDER_CREATED',
                'PAYMENT_PROCESSING',
                'PAYMENT_VERIFIED',
                'WORK_IN_PROGRESS',
                'WORK_SUBMITTED',
                'REVISION_REQUESTED',
                'CLIENT_ACCEPTED',
            ])
            ->with(['serviceRequest.service'])
            ->get();

        foreach ($transactions as $transaction) {
            $deadline = $transaction->deadline;
            $conversation = $transaction->serviceRequest->ensureConversation();
            $link = route('conversations.show', $conversation);

            if ($deadline->isFuture() && $deadline->diffInMinutes($now, false) <= 1440) {
                $notifications->transactionEvent(
                    $transaction,
                    $transaction->freelancer_id,
                    'DEADLINE_24H',
                    'Reminder: the deadline for '.$transaction->reference.' is approaching within 24 hours.',
                    $link,
                );
                $notifications->transactionEvent(
                    $transaction,
                    $transaction->client_id,
                    'DEADLINE_24H',
                    'Reminder: the deadline for '.$transaction->reference.' is approaching within 24 hours.',
                    $link,
                );
            }

            if ($deadline->isPast()) {
                $notifications->transactionEvent(
                    $transaction,
                    $transaction->freelancer_id,
                    'DEADLINE_OVERDUE',
                    'The deadline for '.$transaction->reference.' has passed.',
                    $link,
                );
            }
        }

        $this->info('Transaction deadline reminders processed.');

        return self::SUCCESS;
    }
}
