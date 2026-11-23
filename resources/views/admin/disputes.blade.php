@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Transaction resolution</p>
        <h1>Dispute review</h1>
        <p class="muted">Review transaction disputes, evidence, and recorded resolution decisions.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Back to admin</a>
</section>

@include('admin._navigation', ['active' => 'disputes'])

<section class="panel admin-section" aria-labelledby="admin-disputes-heading">
    <div class="admin-section-heading">
        <div>
            <p class="eyebrow">Open cases</p>
            <h2 id="admin-disputes-heading">Transaction disputes</h2>
        </div>
        <span class="admin-count"><strong>{{ $disputes->total() }}</strong><span>cases</span></span>
    </div>

    @forelse($disputes as $dispute)
        <article class="admin-feedback-item">
            <header class="admin-feedback-item-header">
                <div>
                    <span class="admin-feedback-recipient">{{ $dispute->category }}</span>
                    <h3>{{ $dispute->transaction->reference ?? 'Transaction dispute' }}</h3>
                    <p class="admin-feedback-sender">{{ $dispute->transaction->client->full_name ?? 'Client' }} · {{ $dispute->transaction->freelancer->full_name ?? 'Freelancer' }} · {{ $dispute->created_at->format('M j, Y g:i A') }}</p>
                </div>
                <span class="admin-feedback-status status-{{ \Illuminate\Support\Str::slug($dispute->status) }}">{{ str_replace('_', ' ', $dispute->status) }}</span>
            </header>

            <p class="admin-feedback-message">{{ $dispute->explanation }}</p>

            @foreach($dispute->evidence as $evidence)
                <a class="submission-file" href="{{ route('dispute-evidence.download', $evidence) }}">
                    <span>{{ $evidence->original_name }}</span>
                    <small>{{ $evidence->description ?: 'Evidence file' }}</small>
                </a>
            @endforeach

            @if($dispute->status !== 'RESOLVED')
                <form method="POST" action="{{ route('admin.disputes.resolve', $dispute->transaction) }}" class="admin-feedback-actions">
                    @csrf
                    <label>Resolution action
                        <select name="action" required>
                            <option value="request_evidence">Request additional evidence</option>
                            <option value="full_refund">Full refund</option>
                            <option value="freelancer_payout">Freelancer payout</option>
                            <option value="partial_refund_payout">Partial refund and payout</option>
                            <option value="other">Other permitted resolution</option>
                        </select>
                    </label>
                    <label>Resolution reason
                        <textarea name="reason" rows="3" required maxlength="5000" placeholder="Explain the administrator's decision."></textarea>
                    </label>
                    <button class="button button-small" type="submit">Record resolution</button>
                </form>
            @else
                <p class="muted">Resolved: {{ $dispute->resolution_reason }}</p>
            @endif
        </article>
    @empty
        <p class="empty muted">No transaction disputes have been recorded.</p>
    @endforelse

    {{ $disputes->links() }}
</section>
@endsection
