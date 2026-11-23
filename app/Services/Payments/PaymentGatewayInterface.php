<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function createCheckout(Transaction $transaction, Payment $payment): array;

    public function retrievePayment(Payment $payment): array;

    public function verifyWebhook(Request $request): bool;

    public function refund(Payment $payment, string $amount, string $reason): array;
}
