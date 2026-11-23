<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\PaymentManager;
use App\Services\TransactionActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MayaWebhookController extends Controller
{
    public function __construct(
        private PaymentManager $payments,
        private TransactionActivityService $activity,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->payments->mayaAvailable()) {
            return response()->json(['message' => 'Maya is not enabled.'], 503);
        }

        $gateway = $this->payments->gateway('maya');
        $rawBody = $request->getContent();
        $eventKey = hash('sha256', $rawBody.'|'.$request->header('x-paymaya-signature', ''));

        $event = DB::transaction(function () use ($request, $rawBody, $eventKey): PaymentWebhookEvent {
            return PaymentWebhookEvent::query()->firstOrCreate([
                'provider' => 'maya',
                'event_key' => $eventKey,
            ], [
                'provider_reference' => $request->header('x-paymaya-checkout-id'),
                'payload' => json_decode($rawBody, true) ?: [],
                'signature_status' => 'PENDING',
                'processing_status' => 'RECEIVED',
            ]);
        });

        if (! $event->wasRecentlyCreated) {
            return response()->json(['message' => 'Duplicate webhook event ignored.', 'event' => $event->id]);
        }

        if (! $gateway->verifyWebhook($request)) {
            $event->update(['signature_status' => 'INVALID', 'processing_status' => 'REJECTED']);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        $event->update(['signature_status' => 'VALID']);
        $payload = $event->payload ?: [];
        $providerReference = $event->provider_reference
            ?? data_get($payload, 'checkoutId')
            ?? data_get($payload, 'id')
            ?? data_get($payload, 'paymentId');

        $payment = is_string($providerReference)
            ? Payment::query()->where('provider_reference', $providerReference)->first()
            : null;

        if ($payment === null) {
            $requestReference = data_get($payload, 'requestReferenceNumber')
                ?? data_get($payload, 'metadata.requestReferenceNumber');
            $payment = is_string($requestReference)
                ? Payment::query()->where('reference', $requestReference)->first()
                : null;
        }

        if ($payment === null) {
            $event->update(['processing_status' => 'UNMATCHED']);

            return response()->json(['message' => 'Webhook received but payment was not found.'], 202);
        }

        try {
            $reconciliation = $gateway->retrievePayment($payment);
            $providerStatus = (string) (data_get($reconciliation, 'paymentStatus')
                ?? data_get($reconciliation, 'status')
                ?? data_get($reconciliation, 'transactionStatus')
                ?? '');
            $isSuccessful = in_array(strtoupper($providerStatus), ['PAYMENT_SUCCESS', 'SUCCESS', 'COMPLETED', 'PAID', 'CAPTURED'], true);

            $currency = (string) (data_get($reconciliation, 'totalAmount.currency')
                ?? data_get($reconciliation, 'currency')
                ?? 'PHP');
            $amount = (float) (data_get($reconciliation, 'totalAmount.value')
                ?? data_get($reconciliation, 'amount')
                ?? 0);

            DB::transaction(function () use ($event, $payment, $reconciliation, $isSuccessful, $currency, $amount): void {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                $transaction = $lockedPayment->transaction()->lockForUpdate()->firstOrFail();

                $lockedPayment->update(['provider_payload' => $reconciliation]);

                $validAmount = abs($amount - (float) $lockedPayment->amount) < 0.001;
                $validCurrency = $currency === $lockedPayment->currency;

                if (! $isSuccessful || ! $validAmount || ! $validCurrency) {
                    $event->update(['processing_status' => 'MISMATCH', 'processing_error' => 'Provider status or payment amount/currency mismatch.']);

                    return;
                }

                if ($lockedPayment->status !== 'VERIFIED') {
                    $lockedPayment->update([
                        'status' => 'VERIFIED',
                        'provider_reference' => data_get($reconciliation, 'checkoutId')
                            ?? data_get($reconciliation, 'id')
                            ?? $lockedPayment->provider_reference,
                        'verified_at' => now(),
                    ]);
                    $transaction->update([
                        'payment_status' => 'VERIFIED',
                        'payment_verified_at' => now(),
                        'lifecycle_status' => 'PAYMENT_VERIFIED',
                    ]);
                    $this->activity->recordForBoth(
                        $transaction,
                        $transaction->client,
                        'PAYMENT_VERIFIED',
                        'The payment has been successfully verified through Maya.',
                        ['transaction_id' => $transaction->reference, 'payment_id' => $lockedPayment->reference],
                    );
                }

                $event->update(['processing_status' => 'PROCESSED', 'processed_at' => now()]);
            });

            return response()->json(['message' => 'Webhook processed.', 'event' => $event->id]);
        } catch (\Throwable $exception) {
            $event->update([
                'processing_status' => 'FAILED',
                'processing_error' => substr($exception->getMessage(), 0, 2000),
            ]);

            return response()->json(['message' => 'Webhook reconciliation failed.'], 202);
        }
    }
}
