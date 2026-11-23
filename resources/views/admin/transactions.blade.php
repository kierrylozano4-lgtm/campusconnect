@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Transaction operations</p>
        <h1>Manage transactions</h1>
        <p class="muted">Monitor payments, project delivery, disputes, refunds, and payout eligibility.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Back to admin</a>
</section>

@include('admin._navigation', ['active' => 'transactions'])

<section class="admin-transaction-summary" aria-label="Transaction summary">
    @foreach([
        'total' => 'Total transactions',
        'pending_payment' => 'Pending payments',
        'verified_payment' => 'Verified payments',
        'active_project' => 'Active projects',
        'submitted_work' => 'Submitted work',
        'pending_review' => 'Pending reviews',
        'revision' => 'Revision requests',
        'open_dispute' => 'Open disputes',
        'pending_refund' => 'Pending refunds',
        'pending_payout' => 'Pending payouts',
        'completed_payout' => 'Completed payouts',
    ] as $key => $label)
        <a class="panel admin-stat-card {{ $filter === $key ? 'is-filtered' : '' }}" href="{{ route('admin.transactions.index', $key === 'total' ? [] : ['status' => $key]) }}">
            <span class="eyebrow">{{ $label }}</span>
            <strong>{{ $summary[$key] }}</strong>
        </a>
    @endforeach
</section>

<section class="panel admin-section" aria-labelledby="admin-transactions-heading">
    <div class="admin-section-heading">
        <div><p class="eyebrow">Transaction records</p><h2 id="admin-transactions-heading">Campus transactions</h2></div>
        <strong class="muted">Verified revenue ₱{{ number_format((float) $summary['revenue'], 2) }}</strong>
    </div>

    <div class="admin-list">
        @forelse($transactions as $transaction)
            <article class="admin-row admin-transaction-row">
                <div>
                    <strong>{{ $transaction->reference }}</strong>
                    <span>{{ $transaction->serviceRequest->service->title ?? 'Service unavailable' }}</span>
                </div>
                <div>
                    <span class="muted">Client</span>
                    <strong>{{ $transaction->client->full_name }}</strong>
                </div>
                <div>
                    <span class="muted">Freelancer</span>
                    <strong>{{ $transaction->freelancer->full_name }}</strong>
                </div>
                <div>
                    <span class="admin-status">{{ str_replace('_', ' ', $transaction->payment_status) }}</span>
                    <span class="muted">{{ str_replace('_', ' ', $transaction->lifecycle_status) }}</span>
                </div>
                <div>
                    <strong>₱{{ number_format((float) $transaction->agreed_amount, 2) }}</strong>
                    <span class="muted">{{ str_replace('_', ' ', $transaction->payout_status) }}</span>
                </div>
                <a class="button button-small" href="{{ route('admin.transactions.show', $transaction) }}">Inspect</a>
            </article>
        @empty
            <p class="empty muted">No transactions match this view.</p>
        @endforelse
    </div>

    {{ $transactions->links() }}
</section>
@endsection
