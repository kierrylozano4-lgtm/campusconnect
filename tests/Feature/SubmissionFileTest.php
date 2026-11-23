<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\SubmissionFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_freelancer_can_submit_private_file_and_participant_can_download_it(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        Storage::fake('local');

        $this->actingAs($freelancer)
            ->post(route('transactions.submit', $transaction), [
                'description' => 'The final deliverables are attached.',
                'external_links' => 'https://example.com/deliverable',
                'files' => [UploadedFile::fake()->create('campus-paper.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transaction_submissions', [
            'transaction_id' => $transaction->id,
            'freelancer_id' => $freelancer->id,
            'version' => 1,
            'description' => 'The final deliverables are attached.',
        ]);
        $submissionFile = SubmissionFile::firstOrFail();
        $this->assertDatabaseHas('submission_files', [
            'id' => $submissionFile->id,
            'disk' => 'local',
            'original_name' => 'campus-paper.pdf',
            'mime_type' => 'application/pdf',
        ]);
        Storage::disk('local')->assertExists($submissionFile->path);

        $this->actingAs($client)
            ->get(route('submission-files.download', $submissionFile))
            ->assertOk();

        $this->actingAs($freelancer)
            ->get(route('submission-files.download', $submissionFile))
            ->assertOk();
    }

    public function test_outsider_cannot_download_submission_file(): void
    {
        [$client, $freelancer, $transaction] = $this->transactionFixture();
        Storage::fake('local');

        $this->actingAs($freelancer)
            ->post(route('transactions.submit', $transaction), [
                'description' => 'Private delivery',
                'files' => [UploadedFile::fake()->create('delivery.pdf', 50, 'application/pdf')],
            ])
            ->assertRedirect();

        $submissionFile = SubmissionFile::firstOrFail();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('submission-files.download', $submissionFile))
            ->assertForbidden();
    }

    private function transactionFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Private delivery test',
            'category' => 'Academic',
            'description' => 'A service requiring private deliverables.',
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
            'reference' => 'TX-SUBMISSION-TEST',
            'agreed_amount' => '350.00',
            'currency' => 'PHP',
            'payment_status' => 'VERIFIED',
            'work_status' => 'IN_PROGRESS',
            'confirmation_status' => 'PENDING',
            'dispute_status' => 'NONE',
            'payout_status' => 'NOT_ELIGIBLE',
            'lifecycle_status' => 'WORK_IN_PROGRESS',
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
