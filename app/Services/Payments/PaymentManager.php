<?php

namespace App\Services\Payments;

use RuntimeException;

class PaymentManager
{
    public function gateway(string $provider): PaymentGatewayInterface
    {
        return match (strtolower($provider)) {
            'maya' => app(MayaPaymentGateway::class),
            'gcash' => app(SimulatedGCashGateway::class),
            default => throw new RuntimeException('Unsupported payment provider.'),
        };
    }

    public function gcashAvailable(): bool
    {
        return filter_var(config('services.gcash.simulation_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && app()->environment('local', 'testing');
    }

    public function mayaAvailable(): bool
    {
        return filter_var(config('services.maya.enabled', false), FILTER_VALIDATE_BOOLEAN)
            && is_string(config('services.maya.secret_key'))
            && trim((string) config('services.maya.secret_key')) !== ''
            && ! str_starts_with((string) config('services.maya.secret_key'), 'your_')
            && is_string(config('services.maya.checkout_endpoint'))
            && trim((string) config('services.maya.checkout_endpoint')) !== '';
    }
}
