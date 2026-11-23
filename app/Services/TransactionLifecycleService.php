<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\TransactionRevision;
use App\Models\TransactionSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransactionLifecycleService
{
    public function __construct(
        private ConversationMessenger $messenger,
        private TransactionActivityService $activity,
    ) {}

    public function createForServiceRequest(ServiceRequest $serviceRequest): Transaction
    {
        return DB::transaction(function () use ($serviceRequest): Transaction {
            $lockedRequest = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);
            $existing = Transaction::query()->where('service_request_id', $lockedRequest->id)->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            $service = $lockedRequest->service()->firstOrFail();
            $agreedAmount = $service->price !== null ? (float) $service->price : 0;

            $transaction = Transaction::create([
                'service_request_id' => $lockedRequest->id,
                'client_id' => $lockedRequest->requester_id,
                'freelancer_id' => $lockedRequest->provider_id,
                'reference' => 'TX-'.strtoupper(Str::random(10)),
                'agreed_amount' => $agreedAmount,
                'currency' => 'PHP',
                'payment_status' => 'PAYMENT_REQUIRED',
                'work_status' => 'NOT_STARTED',
                'confirmation_status' => 'PENDING',
                'dispute_status' => 'NONE',
                'payout_status' => 'NOT_ELIGIBLE',
                'lifecycle_status' => 'ORDER_CREATED',
            ]);

            $transaction->agreement()->create([
                'title' => $service->title,
                'description' => $service->description,
                'agreed_amount' => $agreedAmount,
                'currency' => 'PHP',
                'revision_limit' => 2,
                'commission_rate' => 10,
            ]);

            $this->recordStatusLog($transaction, $lockedRequest->provider, 'ORDER_CREATED', null, 'ORDER_CREATED', [
                'service_request_id' => $lockedRequest->id,
            ]);
            $this->messenger->recordEvent(
                $lockedRequest,
                $lockedRequest->provider,
                'An order has been created.',
                'order_created',
                'A transaction was created for '.$service->title.'.'
            );

            return $transaction;
        });
    }

    public function startWork(Transaction $transaction, User $freelancer): Transaction
    {
        return DB::transaction(function () use ($transaction, $freelancer): Transaction {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->freelancer_id === $freelancer->id, 403);
            abort_unless($locked->payment_status === 'VERIFIED', 422, 'Payment must be verified before work starts.');
            abort_unless($locked->work_status === 'NOT_STARTED', 422, 'Work has already started.');

            $from = $locked->lifecycle_status;
            $locked->update([
                'work_status' => 'IN_PROGRESS',
                'lifecycle_status' => 'WORK_IN_PROGRESS',
                'work_started_at' => now(),
            ]);
            $this->recordStatusLog($locked, $freelancer, 'WORK_STARTED', $from, 'WORK_IN_PROGRESS', []);
            $this->activity->record($locked, $freelancer, 'WORK_STARTED', 'The freelancer started working on this project.', [
                'transaction_id' => $locked->reference,
            ]);

            return $locked;
        });
    }

    public function submitWork(Transaction $transaction, User $freelancer, string $description, ?string $externalLinks): TransactionSubmission
    {
        return DB::transaction(function () use ($transaction, $freelancer, $description, $externalLinks): TransactionSubmission {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->freelancer_id === $freelancer->id, 403);
            abort_unless(in_array($locked->work_status, ['IN_PROGRESS', 'REVISION_REQUIRED'], true), 422, 'Work is not eligible for submission.');

            $nextVersion = ((int) $locked->submissions()->max('version')) + 1;
            $submission = $locked->submissions()->create([
                'freelancer_id' => $freelancer->id,
                'version' => $nextVersion,
                'description' => $description,
                'external_links' => $externalLinks,
                'submitted_at' => now(),
            ]);

            $from = $locked->lifecycle_status;
            $locked->update([
                'work_status' => 'SUBMITTED',
                'confirmation_status' => 'PENDING',
                'lifecycle_status' => 'WORK_SUBMITTED',
            ]);
            $this->recordStatusLog($locked, $freelancer, 'WORK_SUBMITTED', $from, 'WORK_SUBMITTED', [
                'submission_id' => $submission->id,
                'version' => $submission->version,
            ]);
            $this->activity->record($locked, $freelancer, 'WORK_SUBMITTED', 'The freelancer submitted the deliverables. Client review is required.', [
                'transaction_id' => $locked->reference,
                'submission_id' => $submission->id,
                'submission_version' => $submission->version,
            ]);

            return $submission;
        });
    }

    public function requestRevision(Transaction $transaction, User $client, string $instructions): TransactionRevision
    {
        return DB::transaction(function () use ($transaction, $client, $instructions): TransactionRevision {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->client_id === $client->id, 403);
            abort_unless($locked->work_status === 'SUBMITTED', 422, 'There is no submitted work to review.');
            abort_unless($locked->dispute_status === 'NONE', 422, 'A disputed transaction cannot request a revision.');

            $submission = $locked->submissions()->latest('version')->firstOrFail();
            $revisionCount = $locked->revisions()->count();
            $revisionLimit = (int) ($locked->agreement?->revision_limit ?? 2);
            abort_unless($revisionCount < $revisionLimit, 422, 'The agreed revision limit has been reached.');

            $revision = $locked->revisions()->create([
                'submission_id' => $submission->id,
                'requested_by' => $client->id,
                'revision_number' => $revisionCount + 1,
                'instructions' => $instructions,
                'status' => 'REQUESTED',
                'requested_at' => now(),
            ]);

            $from = $locked->lifecycle_status;
            $locked->update([
                'work_status' => 'REVISION_REQUIRED',
                'lifecycle_status' => 'REVISION_REQUESTED',
            ]);
            $this->recordStatusLog($locked, $client, 'REVISION_REQUESTED', $from, 'REVISION_REQUESTED', [
                'revision_id' => $revision->id,
                'submission_id' => $submission->id,
            ]);
            $this->activity->record($locked, $client, 'REVISION_REQUESTED', 'The client requested revisions. The freelancer can now review the feedback.', [
                'transaction_id' => $locked->reference,
                'revision_id' => $revision->id,
                'submission_id' => $submission->id,
            ]);

            return $revision;
        });
    }

    public function acceptWork(Transaction $transaction, User $client): void
    {
        DB::transaction(function () use ($transaction, $client): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->client_id === $client->id, 403);
            abort_unless($locked->work_status === 'SUBMITTED', 422, 'There is no submitted work to accept.');
            abort_unless($locked->dispute_status === 'NONE', 422, 'A disputed transaction cannot be accepted.');

            $submission = $locked->submissions()->latest('version')->firstOrFail();
            $locked->confirmations()->firstOrCreate([
                'submission_id' => $submission->id,
                'role' => 'CLIENT',
            ], [
                'user_id' => $client->id,
                'message' => 'Client accepted the submitted work.',
                'audit_metadata' => [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ],
                'confirmed_at' => now(),
            ]);

            $from = $locked->lifecycle_status;
            $clientConfirmed = $locked->confirmations()->where('role', 'CLIENT')->exists();
            $freelancerConfirmed = $locked->confirmations()->where('role', 'FREELANCER')->exists();
            $locked->update([
                'confirmation_status' => $freelancerConfirmed ? 'FULLY_CONFIRMED' : 'CLIENT_ACCEPTED',
                'lifecycle_status' => 'CLIENT_ACCEPTED',
                'completed_at' => now(),
            ]);

            $this->refreshPayoutEligibility($locked);
            $this->recordStatusLog($locked, $client, 'CLIENT_ACCEPTED', $from, 'CLIENT_ACCEPTED', [
                'submission_id' => $submission->id,
                'submission_version' => $submission->version,
            ]);
            $this->activity->record($locked, $client, 'CLIENT_ACCEPTED', 'The client accepted the submitted work.', [
                'transaction_id' => $locked->reference,
                'submission_id' => $submission->id,
                'submission_version' => $submission->version,
            ]);
        });
    }

    public function acknowledgeCompletion(Transaction $transaction, User $freelancer): void
    {
        DB::transaction(function () use ($transaction, $freelancer): void {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->freelancer_id === $freelancer->id, 403);
            $submission = $locked->submissions()->latest('version')->first();

            abort_unless($submission !== null, 422, 'There is no submitted work to acknowledge.');

            $locked->confirmations()->firstOrCreate([
                'submission_id' => $submission->id,
                'role' => 'FREELANCER',
            ], [
                'user_id' => $freelancer->id,
                'message' => 'Freelancer acknowledged completion.',
                'audit_metadata' => [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                ],
                'confirmed_at' => now(),
            ]);

            $clientConfirmed = $locked->confirmations()->where('role', 'CLIENT')->exists();
            $locked->update([
                'confirmation_status' => $clientConfirmed ? 'FULLY_CONFIRMED' : 'FREELANCER_ACKNOWLEDGED',
            ]);

            $this->refreshPayoutEligibility($locked);
        });
    }

    public function refreshPayoutEligibility(Transaction $transaction): void
    {
        $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
        $latestSubmissionId = $locked->submissions()->latest('version')->value('id');
        $clientAccepted = $latestSubmissionId !== null
            && $locked->confirmations()
                ->where('role', 'CLIENT')
                ->where('submission_id', $latestSubmissionId)
                ->exists();

        $openDispute = $locked->dispute()->where('status', 'OPEN')->exists();
        $pendingRefund = $locked->refunds()->whereIn('status', ['REQUESTED', 'PROCESSING'])->exists();
        $eligible = $locked->payment_status === 'VERIFIED'
            && $clientAccepted
            && ! $openDispute
            && ! $pendingRefund;

        if (! $eligible) {
            $locked->update(['payout_status' => 'NOT_ELIGIBLE']);

            return;
        }

        $locked->update(['payout_status' => 'ELIGIBLE']);
        $gross = (float) $locked->agreed_amount;
        $commission = round($gross * ((float) ($locked->agreement?->commission_rate ?? 10) / 100), 2);

        $locked->payout()->firstOrCreate([
            'transaction_id' => $locked->id,
        ], [
            'freelancer_id' => $locked->freelancer_id,
            'reference' => 'PO-'.strtoupper(bin2hex(random_bytes(12))),
            'gross_amount' => number_format($gross, 2, '.', ''),
            'commission_amount' => number_format($commission, 2, '.', ''),
            'deductions' => '0.00',
            'net_amount' => number_format($gross - $commission, 2, '.', ''),
            'currency' => $locked->currency,
            'status' => 'PENDING_APPROVAL',
        ]);
    }

    private function recordStatusLog(
        Transaction $transaction,
        User $actor,
        string $eventType,
        ?string $fromStatus,
        string $toStatus,
        array $metadata,
    ): void {
        $transaction->statusLogs()->firstOrCreate([
            'event_type' => $eventType,
            'idempotency_key' => $eventType.'-'.$transaction->id.'-'.$actor->id.'-'.$fromStatus.'-'.$toStatus,
        ], [
            'actor_id' => $actor->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
        ]);
    }
}
