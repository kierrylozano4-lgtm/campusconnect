<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GCashSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_client_can_start_gcash_simulation(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        config()->set('services.gcash.simulation_enabled', true);

        $this->actingAs($freelancer)
            ->post(route('transactions.payment.gcash', $transaction))
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_gcash_success_simulation_verifies_payment_without_real_money(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        config()->set('services.gcash.simulation_enabled', true);

        $start = $this->actingAs($client)
            ->post(route('transactions.payment.gcash', $transaction));
        $start->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'provider' => 'gcash',
            'initiated_by' => $client->id,
            'amount' => '350.00',
            'status' => 'PROCESSING',
        ]);

        $payment = $transaction->payments()->firstOrFail();

        $this->actingAs($client)
            ->get(route('payments.gcash.simulation', [
                'payment' => $payment->reference,
                'scenario' => 'success',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'VERIFIED',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'selected_payment_method' => 'gcash',
            'payment_status' => 'VERIFIED',
            'lifecycle_status' => 'PAYMENT_VERIFIED',
        ]);
    }

    public function test_gcash_failure_simulation_updates_only_the_test_payment(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        config()->set('services.gcash.simulation_enabled', true);

        $this->actingAs($client)
            ->post(route('transactions.payment.gcash', $transaction))
            ->assertRedirect();

        $payment = $transaction->payments()->firstOrFail();

        $this->actingAs($client)
            ->get(route('payments.gcash.simulation', [
                'payment' => $payment->reference,
                'scenario' => 'failure',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'FAILED',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'FAILED',
            'lifecycle_status' => 'PAYMENT_FAILED',
        ]);
    }

    public function test_gcash_simulation_is_disabled_when_flag_is_false(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        config()->set('services.gcash.simulation_enabled', false);

        $this->actingAs($client)
            ->post(route('transactions.payment.gcash', $transaction))
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    private function transactionFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'GCash test service',
            'category' => 'Personal',
            'description' => 'A local GCash simulation fixture.',
            'price' => '350.00',
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
            'reference' => 'TX-GCASH-TEST',
            'agreed_amount' => '350.00',
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
            'agreed_amount' => '350.00',
            'currency' => 'PHP',
            'revision_limit' => 2,
            'commission_rate' => 10,
        ]);

        return [$client, $freelancer, $transaction];
    }
}
