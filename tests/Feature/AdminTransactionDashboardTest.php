<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTransactionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_open_admin_transaction_dashboard(): void
    {
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)
            ->get(route('admin.transactions.index'))
            ->assertForbidden();

        $transaction = $this->transactionFixture();

        $this->actingAs($member)
            ->get(route('admin.transactions.show', $transaction))
            ->assertForbidden();
    }

    public function test_admin_can_list_filter_and_inspect_transactions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $transaction = $this->transactionFixture();

        $this->actingAs($admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Campus transactions');

        $this->actingAs($admin)
            ->get(route('admin.transactions.index', ['status' => 'submitted_work']))
            ->assertOk()
            ->assertSee($transaction->reference);

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertSee($transaction->reference)
            ->assertSee('Transaction details')
            ->assertSee('Status history');
    }

    private function transactionFixture(): Transaction
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Admin dashboard test service',
            'category' => 'Tech',
            'description' => 'A service for admin transaction inspection.',
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

        return Transaction::create([
            'service_request_id' => $serviceRequest->id,
            'client_id' => $client->id,
            'freelancer_id' => $freelancer->id,
            'reference' => 'TX-ADMIN-DASHBOARD',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'SUBMITTED',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_SUBMITTED',
        ]);
    }
}
