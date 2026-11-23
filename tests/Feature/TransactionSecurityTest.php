<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayoutService;
use App\Services\TransactionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TransactionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_outsider_cannot_accept_or_start_transaction_work(): void
    {
        [$client, $freelancer, $outsider, $transaction] = $this->fixture();

        $this->actingAs($outsider)
            ->post(route('transactions.accept', $transaction), ['confirmed' => '1'])
            ->assertForbidden();

        $this->actingAs($outsider)
            ->post(route('transactions.start', $transaction))
            ->assertForbidden();

        $this->assertDatabaseMissing('transaction_confirmations', [
            'transaction_id' => $transaction->id,
            'role' => 'CLIENT',
        ]);
    }

    public function test_duplicate_client_acceptance_does_not_create_duplicate_confirmation(): void
    {
        [$client, $freelancer, $outsider, $transaction] = $this->fixture();

        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);
        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);

        $this->assertDatabaseCount('transaction_confirmations', 1);
        $this->assertDatabaseHas('transaction_confirmations', [
            'transaction_id' => $transaction->id,
            'user_id' => $client->id,
            'role' => 'CLIENT',
        ]);
    }

    public function test_duplicate_payout_completion_is_blocked(): void
    {
        [$client, $freelancer, $outsider, $transaction] = $this->fixture();
        $admin = User::factory()->create(['is_admin' => true]);

        app(TransactionLifecycleService::class)->acceptWork($transaction, $client);
        app(PayoutService::class)->authorize($transaction, $admin, 'Approved for test verification.');
        app(PayoutService::class)->complete($transaction, $admin, 'External payout verified.');

        $this->expectException(HttpException::class);
        app(PayoutService::class)->complete($transaction, $admin, 'Duplicate completion attempt.');
    }

    private function fixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $outsider = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Security test service',
            'category' => 'Tech',
            'description' => 'Security fixture description.',
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
            'reference' => 'TX-SECURITY-TEST',
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
        $transaction->submissions()->create([
            'freelancer_id' => $freelancer->id,
            'version' => 1,
            'description' => 'Final secure delivery.',
            'submitted_at' => now(),
        ]);

        return [$client, $freelancer, $outsider, $transaction];
    }
}
