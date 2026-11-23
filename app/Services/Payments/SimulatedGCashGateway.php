<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use RuntimeException;

class SimulatedGCashGateway implements PaymentGatewayInterface
{
    public function createCheckout(Transaction $transaction, Payment $payment): array
    {
        $this->assertLocalSimulation();
        $providerReference = 'SIM-GCASH-'.$payment->reference;

        return [
            'checkout_id' => $providerReference,
            'checkout_url' => route('payments.gcash.simulation', [
                'payment' => $payment->reference,
                'scenario' => 'success',
            ]),
            'provider_payload' => [
                'simulation' => true,
                'provider' => 'gcash',
                'provider_reference' => $providerReference,
                'scenario' => 'pending',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
        ];
    }

    public function retrievePayment(Payment $payment): array
    {
        $this->assertLocalSimulation();
        $payload = $payment->provider_payload ?: [];

        return [
            'simulation' => true,
            'provider' => 'gcash',
            'provider_reference' => $payload['provider_reference'] ?? $payment->provider_reference,
            'status' => $payload['scenario'] === 'success' ? 'SUCCESS' : strtoupper((string) ($payload['scenario'] ?? 'pending')),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        return $this->isLocalSimulation();
    }

    public function refund(Payment $payment, string $amount, string $reason): array
    {
        $this->assertLocalSimulation();

        throw new RuntimeException('GCash simulation refunds are not a real financial operation.');
    }

    private function assertLocalSimulation(): void
    {
        if (! $this->isLocalSimulation()) {
            throw new RuntimeException('GCash simulation is disabled outside local and testing environments.');
        }
    }

    private function isLocalSimulation(): bool
    {
        return app()->environment('local', 'testing')
            && filter_var(config('services.gcash.simulation_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }
}
