@extends('layouts.app')
@section('content')
<section class="conversation-shell panel" aria-labelledby="conversation-heading">
    <header class="conversation-header">
        <a class="conversation-back" href="{{ route('conversations.index') }}" aria-label="Back to Requests and Chat">←</a>
        <span class="conversation-avatar" aria-hidden="true">{{ $otherUser->initials() }}</span>
        <div>
            <p class="eyebrow">Campus conversation</p>
            <h1 id="conversation-heading">{{ $otherUser->full_name }}</h1>
            <a class="conversation-profile-link" href="{{ route('profile.show', $otherUser) }}">View profile</a>
        </div>
    </header>

    @if($transaction)
        @php
            $latestSubmission = $transaction->submissions->sortByDesc('version')->first();
            $isClient = auth()->id() === $transaction->client_id;
            $isFreelancer = auth()->id() === $transaction->freelancer_id;
            $timeline = [
                ['label' => 'Order created', 'state' => 'completed'],
                ['label' => 'Waiting for payment', 'state' => $transaction->payment_status === 'PAYMENT_REQUIRED' ? 'current' : 'completed'],
                ['label' => 'Payment processing', 'state' => $transaction->payment_status === 'PROCESSING' ? 'current' : ($transaction->payment_status === 'VERIFIED' ? 'completed' : 'pending')],
                ['label' => 'Payment verified', 'state' => $transaction->payment_status === 'VERIFIED' ? 'completed' : 'pending'],
                ['label' => 'Work in progress', 'state' => $transaction->work_status === 'IN_PROGRESS' ? 'current' : (in_array($transaction->work_status, ['SUBMITTED', 'REVISION_REQUIRED'], true) ? 'completed' : 'pending')],
                ['label' => 'Work submitted', 'state' => $transaction->work_status === 'SUBMITTED' ? 'current' : 'pending'],
                ['label' => 'Client review', 'state' => $transaction->confirmation_status === 'PENDING' && $transaction->work_status === 'SUBMITTED' ? 'current' : 'pending'],
                ['label' => 'Revision requested', 'state' => $transaction->work_status === 'REVISION_REQUIRED' ? 'current' : 'pending'],
                ['label' => 'Client accepted', 'state' => $transaction->confirmation_status === 'CLIENT_ACCEPTED' ? 'completed' : 'pending'],
                ['label' => 'Payout processing', 'state' => $transaction->payout_status === 'ELIGIBLE' ? 'current' : 'pending'],
                ['label' => 'Transaction completed', 'state' => $transaction->completed_at ? 'completed' : 'pending'],
                ['label' => 'Freelancer paid', 'state' => $transaction->payout_status === 'PAID' ? 'completed' : 'pending'],
            ];
        @endphp

        <section class="transaction-lifecycle" data-transaction-lifecycle>
            <button class="transaction-lifecycle-toggle" type="button" aria-expanded="false" data-lifecycle-toggle>
                <span>
                    <small>Transaction {{ $transaction->reference }}</small>
                    <strong>{{ $transaction->agreement?->title ?? $serviceRequest->service->title }}</strong>
                </span>
                <span class="transaction-lifecycle-summary">
                    <b>₱{{ number_format((float) $transaction->agreed_amount, 2) }}</b>
                    <span class="transaction-status status-{{ strtolower(str_replace('_', '-', $transaction->lifecycle_status)) }}">{{ str_replace('_', ' ', $transaction->lifecycle_status) }}</span>
                    <span aria-hidden="true" data-lifecycle-chevron>⌄</span>
                </span>
            </button>

            <div class="transaction-lifecycle-content" hidden data-lifecycle-content>
                <div class="transaction-details">
                    <dl>
                        <div><dt>Client</dt><dd>{{ $transaction->client->full_name }}</dd></div>
                        <div><dt>Freelancer</dt><dd>{{ $transaction->freelancer->full_name }}</dd></div>
                        <div><dt>Agreed price</dt><dd>₱{{ number_format((float) $transaction->agreed_amount, 2) }}</dd></div>
                        <div><dt>Payment</dt><dd>{{ str_replace('_', ' ', $transaction->payment_status) }}</dd></div>
                        <div><dt>Deadline</dt><dd>{{ $transaction->deadline?->format('M j, Y g:i A') ?? 'Not set' }}</dd></div>
                        <div><dt>Last update</dt><dd>{{ $transaction->updated_at->format('M j, Y g:i A') }}</dd></div>
                    </dl>
                </div>

                <ol class="transaction-timeline" aria-label="Transaction progress">
                    @foreach($timeline as $stage)
                        <li class="transaction-stage {{ $stage['state'] }}"><span aria-hidden="true"></span><strong>{{ $stage['label'] }}</strong></li>
                    @endforeach
                </ol>

                <div class="transaction-actions">
                    @if($isClient && in_array($transaction->payment_status, ['PAYMENT_REQUIRED', 'FAILED', 'CANCELLED', 'EXPIRED'], true))
                        @if($mayaAvailable)
                            <form method="POST" action="{{ route('transactions.payment.maya', $transaction) }}">@csrf<button class="button" type="submit">Pay with Maya</button></form>
                        @endif
                        @if($gcashAvailable)
                            <form method="POST" action="{{ route('transactions.payment.gcash', $transaction) }}">
                                @csrf
                                <button class="button button-outline" type="submit">Pay with GCash <small>TEST MODE</small></button>
                            </form>
                        @endif
                    @endif

                    @if($isFreelancer && $transaction->payment_status === 'VERIFIED' && $transaction->work_status === 'NOT_STARTED')
                        <form method="POST" action="{{ route('transactions.start', $transaction) }}">@csrf<button class="button" type="submit">Start work</button></form>
                    @endif

                    @if($isFreelancer && in_array($transaction->work_status, ['IN_PROGRESS', 'REVISION_REQUIRED'], true))
                        <form class="transaction-inline-form" method="POST" action="{{ route('transactions.submit', $transaction) }}" enctype="multipart/form-data">
                            @csrf
                            <label>Delivery description<textarea name="description" required maxlength="5000" rows="3" placeholder="Describe the completed deliverables."></textarea></label>
                            <label>External links <span class="muted">Optional</span><textarea name="external_links" maxlength="5000" rows="2" placeholder="Links to hosted deliverables."></textarea></label>
                            <label>Deliverable files <span class="muted">Max 10 files, 20 MB each</span><input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"></label>
                            <button class="button" type="submit">{{ $transaction->work_status === 'REVISION_REQUIRED' ? 'Resubmit work' : 'Submit final work' }}</button>
                        </form>
                    @endif

                    @if($isClient && $transaction->work_status === 'SUBMITTED')
                        <button class="button" type="button" data-open-dialog="accept-work-dialog">Accept and confirm completion</button>
                        <button class="button button-outline" type="button" data-open-dialog="revision-dialog">Request revision</button>
                    @endif

                    @if(!$transaction->dispute && $transaction->payment_status !== 'PAYMENT_REQUIRED')
                        <button class="button button-danger" type="button" data-open-dialog="open-dispute-dialog">Open dispute</button>
                    @endif

                    @if($transaction->dispute)
                        @if(in_array($transaction->dispute_status, ['OPEN', 'EVIDENCE_REQUESTED'], true))
                            <button class="button button-danger" type="button" data-open-dialog="dispute-evidence-dialog">Submit evidence</button>
                        @endif
                    @endif

                    @if($isFreelancer && $latestSubmission)
                        <form method="POST" action="{{ route('transactions.acknowledge', $transaction) }}">@csrf<button class="button button-outline" type="submit">Acknowledge completion</button></form>
                    @endif
                </div>

                @if($transaction->dispute)
                    <div class="transaction-dispute-warning">
                        <strong>{{ $transaction->dispute_status === 'RESOLVED' ? 'Dispute resolved' : 'Dispute active — payout suspended' }}</strong>
                        <p>{{ $transaction->dispute->category }} · {{ $transaction->dispute->explanation }}</p>
                        @if($transaction->dispute->resolution_reason)
                            <small>Resolution: {{ $transaction->dispute->resolution_reason }}</small>
                        @endif
                        @foreach($transaction->dispute->evidence as $evidence)
                            <a class="submission-file" href="{{ route('dispute-evidence.download', $evidence) }}"><span>{{ $evidence->original_name }}</span><small>{{ $evidence->description ?: 'Dispute evidence' }}</small></a>
                        @endforeach
                    </div>
                @endif

                @if($transaction->submissions->isNotEmpty())
                    <div class="transaction-submissions">
                        <h2>Submission history</h2>
                        @foreach($transaction->submissions->sortByDesc('version') as $submission)
                            <article>
                                <div class="submission-heading"><strong>Version {{ $submission->version }}</strong><small>{{ $submission->submitted_at->format('M j, Y g:i A') }}</small></div>
                                <p>{{ $submission->description }}</p>
                                @if($submission->external_links)
                                    <a class="text-link" href="{{ str_starts_with(trim($submission->external_links), 'http') ? trim($submission->external_links) : '#' }}" @if(str_starts_with(trim($submission->external_links), 'http')) target="_blank" rel="noopener noreferrer" @endif>Open external deliverables</a>
                                @endif
                                @foreach($submission->files as $submissionFile)
                                    <a class="submission-file" href="{{ route('submission-files.download', $submissionFile) }}"><span>{{ $submissionFile->original_name }}</span><small>{{ number_format($submissionFile->size / 1024, 0) }} KB</small></a>
                                @endforeach
                            </article>
                        @endforeach
                    </div>
                @endif

                @if($transaction->payout || $transaction->confirmation_status !== 'PENDING')
                    @php
                        $grossAmount = (float) $transaction->agreed_amount;
                        $commissionRate = (float) ($transaction->agreement?->commission_rate ?? 10);
                        $commissionAmount = round($grossAmount * ($commissionRate / 100), 2);
                        $netAmount = $grossAmount - $commissionAmount;
                    @endphp
                    <div class="transaction-confirmation-summary">
                        <h2>Confirmation and payout</h2>
                        <dl>
                            <div><dt>Confirmation</dt><dd>{{ str_replace('_', ' ', $transaction->confirmation_status) }}</dd></div>
                            <div><dt>Gross payment</dt><dd>₱{{ number_format($grossAmount, 2) }}</dd></div>
                            <div><dt>Platform commission</dt><dd>₱{{ number_format($commissionAmount, 2) }} ({{ number_format($commissionRate, 0) }}%)</dd></div>
                            <div><dt>Expected earnings</dt><dd>₱{{ number_format($netAmount, 2) }}</dd></div>
                            <div><dt>Payout status</dt><dd>{{ str_replace('_', ' ', $transaction->payout_status) }}</dd></div>
                        </dl>
                    </div>
                @endif

                @if($transaction->revisions->isNotEmpty())
                    <div class="transaction-revisions">
                        <h2>Revision history</h2>
                        @foreach($transaction->revisions->sortByDesc('revision_number') as $revision)
                            <article><strong>Revision {{ $revision->revision_number }}</strong><p>{{ $revision->instructions }}</p><small>{{ $revision->requested_at->format('M j, Y g:i A') }}</small></article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        <dialog class="transaction-dialog" id="accept-work-dialog">
            <form method="dialog" class="transaction-dialog-close"><button aria-label="Close">×</button></form>
            <h2>Confirm work acceptance</h2>
            <p>I confirm that I have reviewed and received the agreed deliverables. I understand that accepting the work allows the transaction to proceed to its next eligible stage, subject to the platform's payment and dispute policies.</p>
            <form method="POST" action="{{ route('transactions.accept', $transaction) }}">
                @csrf
                <label class="check"><input type="checkbox" name="confirmed" value="1" required> I confirm acceptance</label>
                <button class="button" type="submit">Confirm acceptance</button>
            </form>
        </dialog>

        <dialog class="transaction-dialog" id="open-dispute-dialog">
            <form method="dialog" class="transaction-dialog-close"><button aria-label="Close">×</button></form>
            <h2>Open dispute</h2>
            <p>Opening a dispute freezes payout authorization and preserves this transaction's agreement, messages, submissions, and evidence.</p>
            <form method="POST" action="{{ route('transactions.dispute.open', $transaction) }}">
                @csrf
                <label>Dispute category
                    <select name="category" required>
                        @foreach(['Payment problem','Incomplete work','Incorrect deliverables','Freelancer unresponsive','Client unresponsive','Unauthorized cancellation','Agreement violation','Suspected fraud'] as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Explanation<textarea name="explanation" required maxlength="5000" rows="5" placeholder="Describe the problem and relevant agreement terms."></textarea></label>
                <button class="button button-danger" type="submit">Open dispute</button>
            </form>
        </dialog>

        <dialog class="transaction-dialog" id="dispute-evidence-dialog">
            <form method="dialog" class="transaction-dialog-close"><button aria-label="Close">×</button></form>
            <h2>Submit dispute evidence</h2>
            <form method="POST" action="{{ route('transactions.dispute.evidence', $transaction) }}" enctype="multipart/form-data">
                @csrf
                <label>Description<textarea name="description" maxlength="1000" rows="3" placeholder="Explain this evidence."></textarea></label>
                <label>Evidence file<input type="file" name="evidence" required accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"></label>
                <button class="button" type="submit">Submit evidence</button>
            </form>
        </dialog>

        <dialog class="transaction-dialog" id="revision-dialog">
            <form method="dialog" class="transaction-dialog-close"><button aria-label="Close">×</button></form>
            <h2>Request revision</h2>
            <form method="POST" action="{{ route('transactions.revise', $transaction) }}">
                @csrf
                <label>Revision instructions<textarea name="instructions" required maxlength="5000" rows="5" placeholder="Explain what needs correction."></textarea></label>
                <button class="button" type="submit">Submit revision request</button>
            </form>
        </dialog>
    @endif

    <div class="conversation-messages" aria-live="polite">
        @forelse($conversation->messages as $message)
            @if($message->type === 'text')
                <article class="conversation-message {{ $message->sender_id === auth()->id() ? 'mine' : '' }}">
                    @if($message->sender_id !== auth()->id())<strong>{{ $message->sender->full_name }}</strong>@endif
                    <p>{{ $message->message }}</p>
                    <small>{{ $message->created_at->format('M j, g:i A') }}@if($message->sender_id === auth()->id() && $message->read_at) · Seen @endif</small>
                </article>
            @else
                <article class="conversation-event">
                    <span class="conversation-event-icon" aria-hidden="true">{{ $message->type === 'service_request' ? '↗' : '◷' }}</span>
                    <div>
                        <strong>{{ match ($message->type) {
                            'service_request' => 'Service request',
                            'order_created' => 'Order created',
                            'PAYMENT_VERIFIED' => 'Payment verified',
                            'WORK_STARTED' => 'Work started',
                            'WORK_SUBMITTED' => 'Work submitted',
                            'REVISION_REQUESTED' => 'Revision requested',
                            'CLIENT_ACCEPTED' => 'Client accepted',
                            'DISPUTE_OPENED' => 'Dispute opened',
                            'DISPUTE_RESOLVED' => 'Dispute resolved',
                            'REFUND_REQUESTED' => 'Refund requested',
                            'REFUND_PROCESSED' => 'Refund processed',
                            'PAYOUT_PROCESSING' => 'Payout processing',
                            'PAYOUT_COMPLETED' => 'Payout completed',
                            default => 'Request update',
                        } }}</strong>
                        <p>{{ $message->message }}</p>
                        @if($message->serviceRequest)
                            <a href="{{ route('dashboard') }}#{{ $message->serviceRequest->provider_id === auth()->id() ? 'incoming-requests' : 'my-requests' }}">View request · {{ $message->serviceRequest->status }}</a>
                        @endif
                    </div>
                    <small>{{ $message->created_at->format('M j, g:i A') }}</small>
                </article>
            @endif
        @empty
            <div class="conversation-empty conversation-empty-inline">
                <p>No messages yet. Send a message to start the conversation.</p>
            </div>
        @endforelse
    </div>

    <form class="conversation-composer" method="POST" action="{{ route('conversations.messages.store', $conversation) }}">
        @csrf
        <label class="sr-only" for="conversation-message">Message now</label>
        <textarea id="conversation-message" name="message" rows="2" placeholder="Write a message..." required maxlength="5000">{{ old('message') }}</textarea>
        <button class="button" type="submit">Send</button>
    </form>
</section>
@endsection
