<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    public function maya(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->client_id === $request->user()->id, 403);
        abort_unless($this->payments->mayaAvailable(), 422, 'Maya payments are not configured.');
        abort_unless((float) $transaction->agreed_amount > 0, 422, 'This transaction needs an agreed amount before payment.');
        abort_unless(in_array($transaction->payment_status, ['PAYMENT_REQUIRED', 'FAILED', 'CANCELLED', 'EXPIRED'], true), 422, 'A payment cannot be created in the current state.');

        try {
            $payment = DB::transaction(function () use ($request, $transaction): Payment {
                $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
                abort_unless($locked->payment_status !== 'VERIFIED', 422, 'This transaction is already paid.');

                $existing = $locked->payments()
                    ->where('provider', 'maya')
                    ->whereIn('status', ['PENDING', 'PROCESSING'])
                    ->latest()
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }

                $payment = $locked->payments()->create([
                    'initiated_by' => $request->user()->id,
                    'provider' => 'maya',
                    'reference' => 'PAY-'.strtoupper(bin2hex(random_bytes(12))),
                    'amount' => $locked->agreed_amount,
                    'currency' => 'PHP',
                    'status' => 'PENDING',
                ]);

                $locked->update([
                    'selected_payment_method' => 'maya',
                    'payment_status' => 'PROCESSING',
                    'lifecycle_status' => 'PAYMENT_PROCESSING',
                ]);

                return $payment;
            });

            if (! $payment->checkout_id) {
                $checkout = $this->payments->gateway('maya')->createCheckout($transaction, $payment);

                DB::transaction(function () use ($payment, $checkout): void {
                    $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
                    $locked->update([
                        'checkout_id' => $checkout['checkout_id'],
                        'provider_payload' => $checkout['provider_payload'],
                        'status' => 'PROCESSING',
                    ]);
                });
                $checkoutUrl = $checkout['checkout_url'];
            } else {
                $checkoutUrl = data_get($payment->provider_payload, 'redirectUrl');
            }

            if (! is_string($checkoutUrl) || $checkoutUrl === '') {
                throw new RuntimeException('Maya did not return a valid checkout URL.');
            }

            return redirect()->away($checkoutUrl);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }
    }

    public function gcash(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->client_id === $request->user()->id, 403);
        abort_unless($this->payments->gcashAvailable(), 422, 'GCash simulation is unavailable in this environment.');
        abort_unless((float) $transaction->agreed_amount > 0, 422, 'This transaction needs an agreed amount before payment.');
        abort_unless(in_array($transaction->payment_status, ['PAYMENT_REQUIRED', 'FAILED', 'CANCELLED', 'EXPIRED'], true), 422, 'A payment cannot be created in the current state.');

        $payment = DB::transaction(function () use ($request, $transaction): Payment {
            $locked = Transaction::query()->lockForUpdate()->findOrFail($transaction->id);
            abort_unless($locked->payment_status !== 'VERIFIED', 422, 'This transaction is already paid.');

            $existing = $locked->payments()
                ->where('provider', 'gcash')
                ->whereIn('status', ['PENDING', 'PROCESSING'])
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $payment = $locked->payments()->create([
                'initiated_by' => $request->user()->id,
                'provider' => 'gcash',
                'reference' => 'SIM-PAY-GCASH-'.strtoupper(bin2hex(random_bytes(8))),
                'amount' => $locked->agreed_amount,
                'currency' => 'PHP',
                'status' => 'PENDING',
            ]);

            $locked->update([
                'selected_payment_method' => 'gcash',
                'payment_status' => 'PROCESSING',
                'lifecycle_status' => 'PAYMENT_PROCESSING',
            ]);

            return $payment;
        });

        $checkout = $this->payments->gateway('gcash')->createCheckout($transaction, $payment);

        DB::transaction(function () use ($payment, $checkout): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $locked->update([
                'checkout_id' => $checkout['checkout_id'],
                'provider_reference' => $checkout['checkout_id'],
                'provider_payload' => $checkout['provider_payload'],
                'status' => 'PROCESSING',
            ]);
        });

        return redirect()->to($checkout['checkout_url']);
    }

    public function gcashSimulation(Request $request): RedirectResponse
    {
        abort_unless($this->payments->gcashAvailable(), 404);

        $payment = Payment::query()
            ->where('reference', (string) $request->query('payment'))
            ->firstOrFail();
        abort_unless($payment->transaction->client_id === $request->user()->id, 403);

        $scenario = in_array($request->query('scenario'), ['success', 'failure', 'cancel', 'expire'], true)
            ? (string) $request->query('scenario')
            : 'failure';

        DB::transaction(function () use ($payment, $scenario): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $payload = $locked->provider_payload ?: [];
            $payload['scenario'] = $scenario;

            $locked->update(['provider_payload' => $payload]);

            if ($scenario === 'success' && $locked->status !== 'VERIFIED') {
                $locked->update([
                    'status' => 'VERIFIED',
                    'verified_at' => now(),
                ]);

                $transaction = $locked->transaction()->lockForUpdate()->firstOrFail();
                $transaction->update([
                    'payment_status' => 'VERIFIED',
                    'payment_verified_at' => now(),
                    'lifecycle_status' => 'PAYMENT_VERIFIED',
                ]);
            } elseif ($scenario === 'failure') {
                $locked->update(['status' => 'FAILED', 'failed_at' => now()]);
                $locked->transaction()->update(['payment_status' => 'FAILED', 'lifecycle_status' => 'PAYMENT_FAILED']);
            } elseif ($scenario === 'cancel') {
                $locked->update(['status' => 'CANCELLED', 'failed_at' => now()]);
                $locked->transaction()->update(['payment_status' => 'CANCELLED', 'lifecycle_status' => 'PAYMENT_CANCELLED']);
            } elseif ($scenario === 'expire') {
                $locked->update(['status' => 'EXPIRED', 'failed_at' => now()]);
                $locked->transaction()->update(['payment_status' => 'EXPIRED', 'lifecycle_status' => 'PAYMENT_EXPIRED']);
            }
        });

        $conversation = $payment->transaction->serviceRequest->ensureConversation();

        return redirect()->route('conversations.show', $conversation)
            ->with('status', $scenario === 'success'
                ? 'GCash TEST payment verified. No real money was processed.'
                : 'GCash TEST payment ended with '.$scenario.'.');
    }

    public function mayaCallback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('payment', '');
        $payment = $reference !== ''
            ? Payment::query()->where('reference', $reference)->first()
            : null;

        if ($payment === null) {
            return redirect()->route('conversations.index')->withErrors(['payment' => 'Payment reference was not found.']);
        }

        abort_unless($payment->transaction->client_id === $request->user()->id, 403);

        $conversation = $payment->transaction->serviceRequest->ensureConversation();

        return redirect()->route('conversations.show', $conversation)
            ->with('status', 'Payment is being verified. Do not resubmit payment.');
    }
}
