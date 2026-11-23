<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DualConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_acceptance_creates_eligible_payout_without_freelancer_acknowledgment(): void
    {
        [$client, $freelancer, $transaction] = $this->submittedFixture();

        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);

        $this->assertDatabaseHas('transaction_confirmations', [
            'transaction_id' => $transaction->id,
            'user_id' => $client->id,
            'role' => 'CLIENT',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'confirmation_status' => 'CLIENT_ACCEPTED',
            'payout_status' => 'ELIGIBLE',
        ]);
        $this->assertDatabaseHas('payouts', [
            'transaction_id' => $transaction->id,
            'freelancer_id' => $freelancer->id,
            'status' => 'PENDING_APPROVAL',
        ]);
    }

    public function test_freelancer_acknowledgment_is_recorded_separately_and_does_not_require_client_presence(): void
    {
        [$client, $freelancer, $transaction] = $this->submittedFixture();

        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);
        app(TransactionLifecycleService::class)->acknowledgeCompletion($transaction, $freelancer);

        $this->assertDatabaseHas('transaction_confirmations', [
            'transaction_id' => $transaction->id,
            'user_id' => $freelancer->id,
            'role' => 'FREELANCER',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'confirmation_status' => 'FULLY_CONFIRMED',
        ]);
    }

    public function test_open_dispute_blocks_payout_eligibility(): void
    {
        [$client, $freelancer, $transaction] = $this->submittedFixture();

        $transaction->dispute()->create([
            'opened_by' => $client->id,
            'category' => 'Incomplete work',
            'explanation' => 'The deliverable is missing a requirement.',
            'status' => 'OPEN',
        ]);

        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payout_status' => 'NOT_ELIGIBLE',
        ]);
        $this->assertDatabaseCount('payouts', 0);
    }

    private function submittedFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Dual confirmation service',
            'category' => 'Academic',
            'description' => 'A submitted and reviewed deliverable.',
            'price' => '500.00',
            'status' => 'Active',
            'visible_in_dashboard' => true,
        ]);
        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'requester_id' => $client->id,
            'provider_id' => $freelancer->id,
            'status' => 'Accepted',
        ]);
        $transaction = Transaction::create([
            'service_request_id' => $serviceRequest->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'reference' => 'TX-DUAL-CONFIRM',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'SUBMITTED',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_SUBMITTED',
        ]);
        $transaction->agreement()->create([
            'title' => $service->title,
            'description' => $service->description,
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'revision_limit' => 2,
            'commission_rate' => 10,
        ]);
        $submission = $transaction->submissions()->create([
            'freelancer_id' => $freelancer->id,
            'version' => 1,
            'description' => 'Submitted final work.',
            'submitted_at' => now(),
        ]);

        return [$client, $freelancer, $transaction->fresh(['agreement', 'submissions'])];
    }
}
