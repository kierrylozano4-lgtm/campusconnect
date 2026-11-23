<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PayoutService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class FinancialOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_authorize_and_complete_payout_once(): void
    {
        [$client, $freelancer, $admin, $transaction] = $this->fixture();

        app(PayoutService::class)->authorize($transaction, $admin, 'Verified bank destination and eligibility.');

        $this->assertDatabaseHas('payouts', [
            'transaction_id' => $transaction->id,
            'status' => 'AUTHORIZED',
            'authorized_by' => $admin->id,
        ]);

        app(PayoutService::class)->complete($transaction, $admin, 'External transfer verified.');

        $this->assertDatabaseHas('payouts', [
            'transaction_id' => $transaction->id,
            'status' => 'COMPLETED',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payout_status' => 'PAID',
            'lifecycle_status' => 'TRANSACTION_COMPLETED',
        ]);
        $this->assertDatabaseCount('financial_ledger_entries', 1);

        $this->expectException(HttpException::class);
        app(PayoutService::class)->complete($transaction, $admin, 'Duplicate completion attempt.');
    }

    public function test_refund_request_and_completion_are_recorded_once(): void
    {
        [$client, $freelancer, $admin, $transaction] = $this->fixture();

        app(RefundService::class)->request($transaction, $admin, 100, 'Partial dispute resolution.');

        $this->assertDatabaseHas('refunds', [
            'transaction_id' => $transaction->id,
            'amount' => '100.00',
            'status' => 'REQUESTED',
        ]);

        app(RefundService::class)->complete($transaction, $admin, 'PROVIDER-REFUND-123', 'Provider confirmed refund.');

        $this->assertDatabaseHas('refunds', [
            'transaction_id' => $transaction->id,
            'provider_reference' => 'PROVIDER-REFUND-123',
            'status' => 'COMPLETED',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'payment_status' => 'REFUNDED',
            'payout_status' => 'NOT_ELIGIBLE',
        ]);

        $this->expectException(HttpException::class);
        app(RefundService::class)->request($transaction, $admin, 50, 'Duplicate refund attempt.');
    }

    private function fixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Financial operations test',
            'category' => 'Personal',
            'description' => 'Test payout and refund records.',
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
            'reference' => 'TX-FINANCIAL-TEST',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'SUBMITTED',
            'confirmation_status' => 'CLIENT_ACCEPTED',
            'dispute_status' => 'NONE',
            'payout_status' => 'ELIGIBLE',
            'lifecycle_status' => 'CLIENT_ACCEPTED',
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
            'description' => 'Final work.',
            'submitted_at' => now(),
        ]);
        $transaction->confirmations()->create([
            'submission_id' => $submission->id,
            'user_id' => $client->id,
            'role' => 'CLIENT',
            'confirmed_at' => now(),
        ]);
        Payment::create([
            'transaction_id' => $transaction->id,
            'initiated_by' => $client->id,
            'provider' => 'maya',
            'reference' => 'PAY-FINANCIAL-TEST',
            'provider_reference' => 'MAYA-FINANCIAL-TEST',
            'amount' => '500.00',
            'currency' => 'PHP',
            'status' => 'VERIFIED',
            'verified_at' => now(),
        ]);
        $transaction->payout()->create([
            'freelancer_id' => $freelancer->id,
            'reference' => 'PO-FINANCIAL-TEST',
            'gross_amount' => '500.00',
            'commission_amount' => '50.00',
            'deductions' => '0.00',
            'net_amount' => '450.00',
            'currency' => 'PHP',
            'status' => 'PENDING_APPROVAL',
        ]);

        return [$client, $freelancer, $admin, $transaction];
    }
}
