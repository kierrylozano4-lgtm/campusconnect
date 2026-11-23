<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisputeResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_participant_can_open_dispute_and_freeze_payout(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();

        app(DisputeService::class)->open(
            $transaction,
            $client,
            'Incomplete work',
            'The submitted work is missing the agreed appendix.',
        );

        $this->assertDatabaseHas('transaction_disputes', [
            'transaction_id' => $transaction->id,
            'opened_by' => $client->id,
            'status' => 'OPEN',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'dispute_status' => 'OPEN',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'DISPUTED',
        ]);
    }

    public function test_participant_can_submit_private_evidence_and_outsider_cannot_download(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        Storage::fake('local');
        $disputes = app(DisputeService::class);
        $disputes->open($transaction, $client, 'Incorrect deliverables', 'The wrong file was delivered.');

        $evidence = $disputes->addEvidence(
            $transaction,
            $freelancer,
            'Original requirement screenshot',
            UploadedFile::fake()->create('requirement.pdf', 100, 'application/pdf'),
        );

        Storage::disk('local')->assertExists($evidence->path);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('dispute-evidence.download', $evidence))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('dispute-evidence.download', $evidence))
            ->assertOk();
    }

    public function test_admin_can_record_reasoned_dispute_resolution(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        $admin = User::factory()->create(['is_admin' => true]);

        $disputes = app(DisputeService::class);
        $disputes->open($transaction, $client, 'Agreement violation', 'The agreed scope was not delivered.');
        $disputes->resolve($transaction, $admin, 'partial_refund_payout', 'The evidence supports a partial adjustment.');

        $this->assertDatabaseHas('transaction_disputes', [
            'transaction_id' => $transaction->id,
            'status' => 'RESOLVED',
            'resolved_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'dispute_status' => 'RESOLVED',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'DISPUTE_RESOLVED',
        ]);
    }

    private function transactionFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Dispute test service',
            'category' => 'Academic',
            'description' => 'A service for dispute testing.',
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
            'reference' => 'TX-DISPUTE-TEST',
            'agreed_amount' => '500.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'SUBMITTED',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_SUBMITTED',
        ]);

        return [$client, $freelancer, $transaction];
    }
}
