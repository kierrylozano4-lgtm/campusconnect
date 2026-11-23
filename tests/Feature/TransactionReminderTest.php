<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_24_hour_deadline_notifies_client_and_freelancer_once_per_type(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture(now()->addHours(12));

        $this->artisan('app:send-transaction-reminders')->assertExitCode(0);
        $this->artisan('app:send-transaction-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'message' => 'Reminder: the deadline for '.$transaction->reference.' is approaching within 24 hours.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $freelancer->id,
            'message' => 'Reminder: the deadline for '.$transaction->reference.' is approaching within 24 hours.',
        ]);
    }

    public function test_overdue_deadline_notifies_freelancer_once(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture(now()->subHour());

        $this->artisan('app:send-transaction-reminders')->assertExitCode(0);
        $this->artisan('app:send-transaction-reminders')->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $freelancer->id,
            'message' => 'The deadline for '.$transaction->reference.' has passed.',
        ]);
    }

    private function transactionFixture($deadline): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Reminder test service',
            'category' => 'Academic',
            'description' => 'A service for reminder testing.',
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
            'reference' => 'TX-REMINDER-TEST',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'IN_PROGRESS',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_IN_PROGRESS',
            'deadline' => $deadline,
        ]);

        return [$client, $freelancer, $transaction];
    }
}
