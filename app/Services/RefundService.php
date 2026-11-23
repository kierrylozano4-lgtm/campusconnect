<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(private TransactionActivityService $activity) {}

    public function request(Transaction $transaction, User $admin, float $amount, string $reason): void
    {
        abort_unless($admin->is_admin, 403);

        DB::transaction(function () use ($transaction, $admin, $amount, $reason): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $payment = $locked->payments()->where('status', 'VERIFIED')->latest()->lockForUpdate()->first();
            abort_unless($payment !== null, 422, 'No verified payment exists for refund.');
            abort_unless($amount > 0 && $amount <= (float) $payment->amount, 422, 'Refund amount is invalid.');
            abort_unless($locked->refunds()->whereIn('status', ['REQUESTED', 'PROCESSING', 'COMPLETED'])->doesntExist(), 422, 'A refund has already been recorded for this transaction.');

            $refund = $locked->refunds()->create([
                'payment_id' => $payment->id,
                'initiated_by' => $admin->id,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $payment->currency,
                'status' => 'REQUESTED',
                'reason' => $reason,
            ]);

            $locked->update([
                'payout_status' => 'NOT_ELIGIBLE',
                'lifecycle_status' => 'REFUND_REQUESTED',
            ]);

            $this->activity->recordForBoth($locked, $admin, 'REFUND_REQUESTED', 'A refund has been requested for this transaction.', [
                'transaction_id' => $locked->reference,
                'refund_id' => $refund->id,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'REFUND_REQUESTED',
                'idempotency_key' => 'REFUND-REQUESTED-'.$refund->id,
            ], [
                'actor_id' => $admin->id,
                'from_status' => 'VERIFIED',
                'to_status' => 'REQUESTED',
                'metadata' => ['refund_id' => $refund->id, 'amount' => $refund->amount],
            ]);
        });
    }

    public function complete(Transaction $transaction, User $admin, string $providerReference, string $reason): void
    {
        abort_unless($admin->is_admin, 403);

        DB::transaction(function () use ($transaction, $admin, $providerReference, $reason): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $refund = $locked->refunds()->whereIn('status', ['REQUESTED', 'PROCESSING'])->latest()->lockForUpdate()->first();
            abort_unless($refund !== null, 422, 'No pending refund exists.');

            $refund->update([
                'provider_reference' => $providerReference,
                'status' => 'COMPLETED',
                'reason' => $refund->reason.' Resolution: '.$reason,
            ]);

            $locked->ledgerEntries()->firstOrCreate([
                'entry_type' => 'REFUND-'.$refund->id.'-COMPLETED',
            ], [
                'payment_id' => $refund->payment_id,
                'refund_id' => $refund->id,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'direction' => 'debit',
                'description' => 'Verified refund processed.',
                'posted_at' => now(),
            ]);

            $locked->update([
                'payment_status' => 'REFUNDED',
                'payout_status' => 'NOT_ELIGIBLE',
                'lifecycle_status' => 'REFUND_COMPLETED',
            ]);

            $this->activity->recordForBoth($locked, $admin, 'REFUND_PROCESSED', 'The refund has been processed.', [
                'transaction_id' => $locked->reference,
                'refund_id' => $refund->id,
                'provider_reference' => $providerReference,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'REFUND_COMPLETED',
                'idempotency_key' => 'REFUND-COMPLETED-'.$refund->id,
            ], [
                'actor_id' => $admin->id,
                'from_status' => 'REQUESTED',
                'to_status' => 'COMPLETED',
                'metadata' => ['refund_id' => $refund->id, 'provider_reference' => $providerReference],
            ]);
        });
    }
}
