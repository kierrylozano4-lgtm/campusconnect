<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DisputeService
{
    public function __construct(private TransactionActivityService $activity) {}

    public function open(Transaction $transaction, User $user, string $category, string $explanation): void
    {
        DB::transaction(function () use ($transaction, $user, $category, $explanation): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless(in_array($user->id, [$locked->client_id, $locked->freelancer_id], true), 403);
            abort_unless($locked->dispute_status === 'NONE', 422, 'A dispute is already open or resolved.');

            $dispute = $locked->dispute()->create([
                'opened_by' => $user->id,
                'category' => $category,
                'explanation' => $explanation,
                'status' => 'OPEN',
            ]);

            $locked->update([
                'dispute_status' => 'OPEN',
                'payout_status' => 'NOT_ELIGIBLE',
                'lifecycle_status' => 'DISPUTED',
            ]);

            $receiverId = $user->id === $locked->client_id
                ? $locked->freelancer_id
                : $locked->client_id;
            Notification::create([
                'user_id' => $receiverId,
                'message' => 'A dispute has been opened for transaction '.$locked->reference.'.',
                'link' => route('conversations.show', $locked->serviceRequest->ensureConversation()),
                'is_read' => false,
            ]);

            $this->activity->record($locked, $user, 'DISPUTE_OPENED', 'A transaction dispute has been opened. Payout processing has been suspended.', [
                'transaction_id' => $locked->reference,
                'dispute_id' => $dispute->id,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'DISPUTE_OPENED',
                'idempotency_key' => 'DISPUTE_OPENED-'.$locked->id.'-'.$dispute->id,
            ], [
                'actor_id' => $user->id,
                'from_status' => 'NONE',
                'to_status' => 'OPEN',
                'metadata' => ['category' => $category, 'dispute_id' => $dispute->id],
            ]);
        });
    }

    public function addEvidence(Transaction $transaction, User $user, string $description, UploadedFile $file): TransactionEvidence
    {
        return DB::transaction(function () use ($transaction, $user, $description, $file): TransactionEvidence {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless(in_array($user->id, [$locked->client_id, $locked->freelancer_id], true), 403);
            $dispute = $locked->dispute()->whereIn('status', ['OPEN', 'EVIDENCE_REQUESTED'])->firstOrFail();

            $path = $file->store('transaction-evidence/'.$locked->id.'/'.$dispute->id, 'local');
            if (! is_string($path)) {
                throw new \RuntimeException('Dispute evidence could not be stored.');
            }

            try {
                return $dispute->evidence()->create([
                    'submitted_by' => $user->id,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize() ?: 0,
                    'description' => $description,
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        });
    }

    public function resolve(Transaction $transaction, User $admin, string $action, string $reason): void
    {
        abort_unless($admin->is_admin, 403);
        abort_unless(in_array($action, ['full_refund', 'freelancer_payout', 'partial_refund_payout', 'request_evidence', 'other'], true), 422);

        DB::transaction(function () use ($transaction, $admin, $action, $reason): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            $dispute = $locked->dispute()->whereIn('status', ['OPEN', 'EVIDENCE_REQUESTED'])->firstOrFail();

            $dispute->update([
                'status' => $action === 'request_evidence' ? 'EVIDENCE_REQUESTED' : 'RESOLVED',
                'resolved_by' => $action === 'request_evidence' ? null : $admin->id,
                'resolution_reason' => 'Action: '.$action.'; '.$reason,
                'resolved_at' => $action === 'request_evidence' ? null : now(),
            ]);

            $locked->update([
                'dispute_status' => $action === 'request_evidence' ? 'EVIDENCE_REQUESTED' : 'RESOLVED',
                'payout_status' => 'NOT_ELIGIBLE',
                'lifecycle_status' => $action === 'request_evidence' ? 'DISPUTE_EVIDENCE_REQUESTED' : 'DISPUTE_RESOLVED',
            ]);

            $this->activity->recordForBoth($locked, $admin, 'DISPUTE_RESOLVED', 'The transaction dispute has been resolved by an administrator.', [
                'transaction_id' => $locked->reference,
                'dispute_id' => $dispute->id,
                'action' => $action,
            ]);

            $locked->statusLogs()->firstOrCreate([
                'event_type' => 'DISPUTE_RESOLVED',
                'idempotency_key' => 'DISPUTE-'.$action.'-'.$dispute->id.'-'.$admin->id,
            ], [
                'actor_id' => $admin->id,
                'from_status' => 'OPEN',
                'to_status' => $action,
                'metadata' => ['dispute_id' => $dispute->id, 'reason' => $reason],
            ]);

            foreach ([$locked->client_id, $locked->freelancer_id] as $recipientId) {
                Notification::create([
                    'user_id' => $recipientId,
                    'message' => $action === 'request_evidence'
                        ? 'The administrator requested additional evidence for dispute '.$dispute->id.'.'
                        : 'The dispute for transaction '.$locked->reference.' was resolved: '.$action.'.',
                    'link' => route('conversations.show', $locked->serviceRequest->ensureConversation()),
                    'is_read' => false,
                ]);
            }
        });
    }
}
