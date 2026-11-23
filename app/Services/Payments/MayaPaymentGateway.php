<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MayaPaymentGateway implements PaymentGatewayInterface
{
    public function createCheckout(Transaction $transaction, Payment $payment): array
    {
        $this->assertConfigured();
        $amount = number_format((float) $payment->amount, 2, '.', '');
        $callback = rtrim((string) config('services.maya.callback_url', config('app.url')), '/');

        $payload = [
            'totalAmount' => [
                'currency' => 'PHP',
                'value' => $amount,
            ],
            'buyer' => [
                'firstName' => $transaction->client->full_name,
                'contact' => [
                    'email' => $transaction->client->email,
                ],
            ],
            'items' => [[
                'name' => $transaction->agreement?->title ?? 'CampusConnect service',
                'quantity' => '1',
                'code' => (string) $transaction->reference,
                'amount' => [
                    'value' => $amount,
                ],
                'totalAmount' => [
                    'value' => $amount,
                ],
            ]],
            'requestReferenceNumber' => $payment->reference,
            'redirectUrl' => [
                'success' => $callback.'?payment='.$payment->reference.'&result=success',
                'failure' => $callback.'?payment='.$payment->reference.'&result=failure',
                'cancel' => $callback.'?payment='.$payment->reference.'&result=cancel',
            ],
            'metadata' => [
                'transaction_id' => (string) $transaction->id,
                'payment_id' => (string) $payment->id,
            ],
        ];

        $response = Http::acceptJson()
            ->withBasicAuth((string) config('services.maya.secret_key'), '')
            ->connectTimeout(10)
            ->timeout(30)
            ->post((string) config('services.maya.checkout_endpoint'), $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Maya checkout creation failed: '.$response->status().' '.$this->safeError($response));
        }

        $checkoutUrl = $response->json('redirectUrl');
        if (! is_string($checkoutUrl) || $checkoutUrl === '') {
            throw new RuntimeException('Maya checkout response did not contain a redirect URL.');
        }

        return [
            'checkout_id' => $response->json('checkoutId'),
            'checkout_url' => $checkoutUrl,
            'provider_payload' => $response->json(),
        ];
    }

    public function retrievePayment(Payment $payment): array
    {
        $this->assertConfigured();
        $providerReference = $payment->provider_reference;

        if (! is_string($providerReference) || $providerReference === '') {
            throw new RuntimeException('A Maya payment reference is required for reconciliation.');
        }

        $baseUrl = rtrim((string) config('services.maya.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('MAYA_BASE_URL is not configured.');
        }

        $response = Http::acceptJson()
            ->withBasicAuth((string) config('services.maya.secret_key'), '')
            ->connectTimeout(10)
            ->timeout(30)
            ->get($baseUrl.'/checkout/v1/checkouts/'.$providerReference);

        if (! $response->successful()) {
            throw new RuntimeException('Maya reconciliation failed: '.$response->status().' '.$this->safeError($response));
        }

        return $response->json();
    }

    public function verifyWebhook(Request $request): bool
    {
        $publicKeys = array_values(array_filter([
            config('services.maya.webhook_public_key_1'),
            config('services.maya.webhook_public_key_2'),
        ], fn ($key): bool => is_string($key) && trim($key) !== ''));

        if ($publicKeys === []) {
            return false;
        }

        $signature = $request->header('x-paymaya-signature') ?? $request->header('X-PayMaya-Signature');
        if (! is_string($signature) || $signature === '') {
            return false;
        }

        foreach ($publicKeys as $publicKey) {
            $verified = openssl_verify($request->getContent(), base64_decode($signature, true) ?: '', $publicKey, OPENSSL_ALGO_SHA256);
            if ($verified === 1) {
                return true;
            }
        }

        return false;
    }

    public function refund(Payment $payment, string $amount, string $reason): array
    {
        $this->assertConfigured();

        if (! is_string($payment->provider_reference) || $payment->provider_reference === '') {
            throw new RuntimeException('A Maya payment reference is required for a refund.');
        }

        throw new RuntimeException('Maya refund endpoint support must be approved and documented for this merchant account.');
    }

    private function assertConfigured(): void
    {
        if (! filter_var(config('services.maya.enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Maya payments are not enabled.');
        }

        if (! is_string(config('services.maya.secret_key')) || trim((string) config('services.maya.secret_key')) === ''
            || str_starts_with((string) config('services.maya.secret_key'), 'your_')) {
            throw new RuntimeException('Maya secret key is not configured.');
        }

        if (! is_string(config('services.maya.checkout_endpoint')) || trim((string) config('services.maya.checkout_endpoint')) === '') {
            throw new RuntimeException('Maya checkout endpoint is not configured.');
        }
    }

    private function safeError(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('error');

        return is_string($message) ? substr($message, 0, 300) : 'Unknown provider response.';
    }
}
