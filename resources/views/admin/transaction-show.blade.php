@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Transaction inspection</p>
        <h1>{{ $transaction->reference }}</h1>
        <p class="muted">{{ $transaction->agreement->title ?? $transaction->serviceRequest->service->title ?? 'Service transaction' }}</p>
    </div>
    <a class="button button-small" href="{{ route('admin.transactions.index') }}">Back to transactions</a>
</section>

@include('admin._navigation', ['active' => 'transactions'])

<section class="admin-transaction-detail-grid">
    <article class="panel">
        <div class="admin-section-heading"><div><p class="eyebrow">Agreement</p><h2>Transaction details</h2></div></div>
        <dl class="admin-detail-list">
            <div><dt>Client</dt><dd>{{ $transaction->client->full_name }}</dd></div>
            <div><dt>Freelancer</dt><dd>{{ $transaction->freelancer->full_name }}</dd></div>
            <div><dt>Agreed amount</dt><dd>₱{{ number_format((float) $transaction->agreed_amount, 2) }}</dd></div>
            <div><dt>Deadline</dt><dd>{{ $transaction->deadline?->format('M j, Y g:i A') ?? 'Not set' }}</dd></div>
            <div><dt>Payment</dt><dd>{{ str_replace('_', ' ', $transaction->payment_status) }}</dd></div>
            <div><dt>Work</dt><dd>{{ str_replace('_', ' ', $transaction->work_status) }}</dd></div>
            <div><dt>Confirmation</dt><dd>{{ str_replace('_', ' ', $transaction->confirmation_status) }}</dd></div>
            <div><dt>Dispute</dt><dd>{{ str_replace('_', ' ', $transaction->dispute_status) }}</dd></div>
            <div><dt>Payout</dt><dd>{{ str_replace('_', ' ', $transaction->payout_status) }}</dd></div>
        </dl>
        @if($transaction->serviceRequest->conversation)
            <a class="button button-small" href="{{ route('conversations.show', $transaction->serviceRequest->conversation) }}">Open conversation</a>
        @endif
    </article>

    <article class="panel">
        <div class="admin-section-heading"><div><p class="eyebrow">Financial records</p><h2>Payments and ledger</h2></div></div>
        @forelse($transaction->payments as $payment)
            <div class="admin-detail-row"><strong>{{ $payment->provider }}</strong><span>{{ $payment->reference }} · {{ $payment->status }}</span><span>₱{{ number_format((float) $payment->amount, 2) }}</span></div>
        @empty
            <p class="empty muted">No payment attempts recorded.</p>
        @endforelse
        @forelse($transaction->ledgerEntries as $entry)
            <div class="admin-detail-row"><strong>{{ $entry->entry_type }}</strong><span>{{ $entry->direction }}</span><span>₱{{ number_format((float) $entry->amount, 2) }}</span></div>
        @empty
            <p class="empty muted">No ledger entries posted.</p>
        @endforelse
    </article>
</section>

<section class="panel admin-section">
    <div class="admin-section-heading"><div><p class="eyebrow">Delivery history</p><h2>Submissions and confirmations</h2></div></div>
    @forelse($transaction->submissions as $submission)
        <article class="admin-detail-row">
            <strong>Version {{ $submission->version }}</strong>
            <span>{{ $submission->description }}</span>
            <span>{{ $submission->submitted_at->format('M j, Y g:i A') }}</span>
        </article>
        @foreach($submission->files as $file)
            <a class="submission-file" href="{{ route('submission-files.download', $file) }}"><span>{{ $file->original_name }}</span><small>Private file</small></a>
        @endforeach
    @empty
        <p class="empty muted">No work submissions recorded.</p>
    @endforelse
</section>

<section class="panel admin-section">
    <div class="admin-section-heading"><div><p class="eyebrow">Audit trail</p><h2>Status history</h2></div></div>
    @forelse($transaction->statusLogs as $log)
        <div class="admin-detail-row"><strong>{{ str_replace('_', ' ', $log->event_type) }}</strong><span>{{ $log->actor?->full_name ?? 'System' }}</span><span>{{ $log->created_at->format('M j, Y g:i A') }}</span></div>
    @empty
        <p class="empty muted">No status events recorded.</p>
    @endforelse
</section>
@endsection
