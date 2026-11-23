<?php

namespace Tests\Feature;

use App\Models\PaymentWebhookEvent;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MayaPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_maya_checkout_is_blocked_until_configuration_is_enabled(): void
    {
        [$client, $freelancer, $serviceRequest, $transaction] = $this->transactionFixture();

        config()->set('services.maya.enabled', false);

        $this->actingAs($client)
            ->post(route('transactions.payment.maya', $transaction))
            ->assertStatus(422);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_only_the_client_can_start_a_maya_checkout(): void
    {
        [$client, $freelancer, $serviceRequest, $transaction] = $this->transactionFixture();

        config()->set('services.maya.enabled', true);
        config()->set('services.maya.secret_key', 'test-secret');
        config()->set('services.maya.checkout_endpoint', 'https://sandbox.example/checkouts');

        $this->actingAs($freelancer)
            ->post(route('transactions.payment.maya', $transaction))
            ->assertForbidden();
    }

    public function test_maya_checkout_creation_uses_mocked_provider_response(): void
    {
        [$client, $freelancer, $serviceRequest, $transaction] = $this->transactionFixture();

        config()->set('services.maya.enabled', true);
        config()->set('services.maya.secret_key', 'test-secret');
        config()->set('services.maya.checkout_endpoint', 'https://sandbox.example/checkouts');
        config()->set('services.maya.callback_url', 'http://127.0.0.1:8000/payments/maya/callback');

        Http::fake([
            'https://sandbox.example/checkouts' => Http::response([
                'checkoutId' => 'checkout-test-123',
                'redirectUrl' => 'https://checkout.example/pay/checkout-test-123',
            ], 200),
        ]);

        $this->actingAs($client)
            ->post(route('transactions.payment.maya', $transaction))
            ->assertRedirect('https://checkout.example/pay/checkout-test-123');

        $this->assertDatabaseHas('payments', [
            'transaction_id' => $transaction->id,
            'initiated_by' => $client->id,
            'provider' => 'maya',
            'amount' => '350.00',
            'currency' => 'PHP',
            'checkout_id' => 'checkout-test-123',
            'status' => 'PROCESSING',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'selected_payment_method' => 'maya',
            'payment_status' => 'PROCESSING',
            'lifecycle_status' => 'PAYMENT_PROCESSING',
        ]);
    }

    public function test_invalid_maya_webhook_signature_is_rejected_and_recorded(): void
    {
        config()->set('services.maya.enabled', true);
        config()->set('services.maya.secret_key', 'test-secret');
        config()->set('services.maya.checkout_endpoint', 'https://sandbox.example/checkouts');

        $response = $this->postJson(route('payments.maya.webhook'), [
            'checkoutId' => 'unknown-checkout',
        ], ['x-paymaya-signature' => 'invalid-signature']);

        $response->assertUnauthorized();
        $this->assertDatabaseHas('payment_webhook_events', [
            'provider' => 'maya',
            'signature_status' => 'INVALID',
            'processing_status' => 'REJECTED',
        ]);
    }

    public function test_duplicate_maya_webhook_is_ignored(): void
    {
        config()->set('services.maya.enabled', true);
        config()->set('services.maya.secret_key', 'test-secret');
        config()->set('services.maya.checkout_endpoint', 'https://sandbox.example/checkouts');

        $payload = ['checkoutId' => 'duplicate'];
        $headers = ['x-paymaya-signature' => 'existing-signature'];

        $first = $this->postJson(route('payments.maya.webhook'), $payload, $headers);
        $first->assertUnauthorized();
        $event = PaymentWebhookEvent::firstOrFail();

        $second = $this->postJson(route('payments.maya.webhook'), $payload, $headers);
        $second->assertOk()->assertJsonPath('event', $event->id);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    private function transactionFixture(): array
    {
        $client = User::factory()->create();
        $freelancer = User::factory()->create();
        $service = Service::create([
            'user_id' => $freelancer->id,
            'title' => 'Campus proofreading',
            'category' => 'Academic',
            'description' => 'Proofread a campus paper.',
            'price' => '350.00',
            'price_negotiable' => false,
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
            'reference' => 'TX-TEST-'.strtoupper(bin2hex(random_bytes(4))),
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

        return [$client, $freelancer, $serviceRequest, $transaction];
    }
}
