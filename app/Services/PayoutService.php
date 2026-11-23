<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PayoutService
{
    public function __construct(private TransactionActivityService $activity) {}

    public function authorize(Transaction $transaction, User $admin, string $note): void
    {
        abort_unless($admin->is_admin, 403);

        DB::transaction(function () use ($transaction, $admin, $note): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->payment_status === 'VERIFIED', 422, 'Payment is not verified.');
            abort_unless($locked->dispute_status === 'NONE' || $locked->dispute_status === 'RESOLVED', 422, 'A dispute must be resolved before payout authorization.');
            abort_unless($locked->refunds()->whereIn('status', ['REQUESTED', 'PROCESSING', 'COMPLETED'])->doesntExist(), 422, 'A refund or reversal blocks payout authorization.');

            $latestSubmission = $locked->submissions()->latest('version')->first();
            abort_unless($latestSubmission !== null, 422, 'No submitted work exists.');
            abort_unless($locked->confirmations()->where('role', 'CLIENT')->where('submission_id', $latestSubmission->id)->exists(), 422, 'Client acceptance is required.');

            $payout = $locked->payout()->lockForUpdate()->firstOrFail();
            abort_unless($payout->status === 'PENDING_APPROVAL', 422, 'Payout is not pending approval.');

            $payout->update([
                'status' => 'AUTHORIZED',
                'admin_note' => $note,
                'authorized_by' => $admin->id,
                'authorized_at' => now(),
            ]);
            $locked->update(['payout_status' => 'AUTHORIZED', 'lifecycle_status' => 'PAYOUT_PROCESSING']);

            $this->activity->record($locked, $admin, 'PAYOUT_PROCESSING', 'The transaction is eligible for payout processing.', [
                'transaction_id' => $locked->reference,
                'payout_id' => $payout->id,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'PAYOUT_AUTHORIZED',
                'idempotency_key' => 'PAYOUT-AUTHORIZED-'.$payout->id,
            ], [
                'actor_id' => $admin->id,
                'from_status' => 'PENDING_APPROVAL',
                'to_status' => 'AUTHORIZED',
                'metadata' => ['payout_id' => $payout->id, 'net_amount' => $payout->net_amount],
            ]);
        });
    }

    public function complete(Transaction $transaction, User $admin, string $note): void
    {
        abort_unless($admin->is_admin, 403);

        DB::transaction(function () use ($transaction, $admin, $note): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $payout = $locked->payout()->lockForUpdate()->firstOrFail();
            abort_unless($payout->status === 'AUTHORIZED', 422, 'Payout must be authorized before completion.');

            $payout->update([
                'status' => 'COMPLETED',
                'admin_note' => $note,
                'completed_at' => now(),
            ]);
            $locked->update([
                'payout_status' => 'PAID',
                'paid_out_at' => now(),
                'lifecycle_status' => 'TRANSACTION_COMPLETED',
            ]);

            $ledgerKey = 'PAYOUT-'.$payout->id.'-COMPLETED';
            $locked->ledgerEntries()->firstOrCreate([
                'entry_type' => $ledgerKey,
            ], [
                'payout_id' => $payout->id,
                'amount' => $payout->net_amount,
                'currency' => $payout->currency,
                'direction' => 'credit',
                'description' => 'Freelancer payout completed.',
                'posted_at' => now(),
            ]);

            $this->activity->recordForBoth($locked, $admin, 'PAYOUT_COMPLETED', "The freelancer's payout has been verified.", [
                'transaction_id' => $locked->reference,
                'payout_id' => $payout->id,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'PAYOUT_COMPLETED',
                'idempotency_key' => 'PAYOUT-COMPLETED-'.$payout->id,
            ], [
                'actor_id' => $admin->id,
                'from_status' => 'AUTHORIZED',
                'to_status' => 'COMPLETED',
                'metadata' => ['payout_id' => $payout->id],
            ]);
        });
    }
}
