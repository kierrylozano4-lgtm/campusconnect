@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Financial operations</p>
        <h1>Payouts and refunds</h1>
        <p class="muted">Admin-controlled records only. Actual disbursements require an approved provider arrangement.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Back to admin</a>
</section>

@include('admin._navigation', ['active' => 'financials'])

<section class="panel admin-section" aria-labelledby="financial-heading">
    <div class="admin-section-heading">
        <div><p class="eyebrow">Transaction finance</p><h2 id="financial-heading">Financial records</h2></div>
        <span class="admin-count"><strong>{{ $transactions->total() }}</strong><span>transactions</span></span>
    </div>

    @forelse($transactions as $transaction)
        <article class="admin-row admin-financial-row">
            <div>
                <strong>{{ $transaction->reference }}</strong>
                <span>{{ $transaction->client->full_name }} · {{ $transaction->freelancer->full_name }}</span>
                <span>Payment: {{ $transaction->payment_status }} · Payout: {{ $transaction->payout_status }}</span>
            </div>
            <div>
                <small class="muted">Gross / net</small>
                <strong>₱{{ number_format((float) $transaction->agreed_amount, 2) }}</strong>
                @if($transaction->payout)
                    <span>Net ₱{{ number_format((float) $transaction->payout->net_amount, 2) }}</span>
                    <span>Status {{ $transaction->payout->status }}</span>
                @endif
            </div>
            <div class="admin-financial-actions">
                @if($transaction->payout && $transaction->payout->status === 'PENDING_APPROVAL')
                    <form method="POST" action="{{ route('admin.payouts.authorize', $transaction) }}">
                        @csrf
                        <input name="note" required maxlength="2000" placeholder="Authorization note">
                        <button class="button button-small" type="submit">Authorize payout</button>
                    </form>
                @elseif($transaction->payout && $transaction->payout->status === 'AUTHORIZED')
                    <form method="POST" action="{{ route('admin.payouts.complete', $transaction) }}">
                        @csrf
                        <input name="note" required maxlength="2000" placeholder="Completion verification">
                        <button class="button button-small" type="submit">Record payout completed</button>
                    </form>
                @endif
            </div>
            <div class="admin-financial-actions">
                @if($transaction->payment_status === 'VERIFIED' && $transaction->refunds->whereIn('status', ['REQUESTED','PROCESSING'])->isEmpty())
                    <form method="POST" action="{{ route('admin.refunds.request', $transaction) }}">
                        @csrf
                        <input name="amount" type="number" min="0.01" step="0.01" max="{{ $transaction->agreed_amount }}" required placeholder="Refund amount">
                        <input name="reason" required maxlength="5000" placeholder="Refund reason">
                        <button class="button button-small button-outline" type="submit">Request refund</button>
                    </form>
                @endif
                @foreach($transaction->refunds->whereIn('status', ['REQUESTED','PROCESSING']) as $refund)
                    <form method="POST" action="{{ route('admin.refunds.complete', $transaction) }}">
                        @csrf
                        <input name="provider_reference" required maxlength="150" placeholder="Verified provider reference">
                        <input name="reason" required maxlength="5000" placeholder="Verification reason">
                        <button class="button button-small" type="submit">Record refund completed</button>
                    </form>
                @endforeach
            </div>
        </article>
    @empty
        <p class="empty muted">No transaction financial records found.</p>
    @endforelse

    {{ $transactions->links() }}
</section>
@endsection
