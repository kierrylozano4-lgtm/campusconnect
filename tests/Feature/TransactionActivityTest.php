<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_transaction_activity_creates_one_card_log_and_notification(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        $activity = app(TransactionActivityService::class);

        $activity->record(
            $transaction,
            $freelancer,
            'WORK_STARTED',
            'The freelancer started working on this project.',
            ['transaction_id' => $transaction->reference],
        );
        $activity->record(
            $transaction,
            $freelancer,
            'WORK_STARTED',
            'The freelancer started working on this project.',
            ['transaction_id' => $transaction->reference],
        );

        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseHas('conversation_messages', [
            'type' => 'WORK_STARTED',
            'message' => 'The freelancer started working on this project.',
        ]);
        $this->assertDatabaseCount('transaction_status_logs', 1);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'message' => 'The freelancer started working on this project.',
        ]);
    }

    public function test_different_activity_types_for_the_same_transaction_are_not_collapsed(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        $activity = app(TransactionActivityService::class);

        $activity->record($transaction, $freelancer, 'WORK_STARTED', 'The freelancer started working on this project.');
        $activity->record($transaction, $freelancer, 'WORK_SUBMITTED', 'The freelancer submitted the deliverables.');

        $this->assertDatabaseCount('conversation_messages', 2);
        $this->assertDatabaseCount('notifications', 2);
    }

    private function transactionFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Activity test service',
            'category' => 'Academic',
            'description' => 'Activity test description.',
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
            'reference' => 'TX-ACTIVITY-TEST',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'IN_PROGRESS',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_IN_PROGRESS',
        ]);

        return [$client, $freelancer, $transaction];
    }
}
