<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->decimal('agreed_amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('payment_status', 30)->default('PAYMENT_REQUIRED');
            $table->string('work_status', 30)->default('NOT_STARTED');
            $table->string('confirmation_status', 30)->default('PENDING');
            $table->string('dispute_status', 30)->default('NONE');
            $table->string('payout_status', 30)->default('NOT_ELIGIBLE');
            $table->string('lifecycle_status', 30)->default('ORDER_CREATED');
            $table->timestamp('deadline')->nullable();
            $table->timestamp('payment_verified_at')->nullable();
            $table->timestamp('work_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_out_at')->nullable();
            $table->timestamps();
            $table->index(['payment_status', 'lifecycle_status'], 'tx_payment_lifecycle_idx');
            $table->index(['dispute_status', 'payout_status'], 'tx_dispute_payout_idx');
        });

        Schema::create('transaction_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('agreed_amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->unsignedTinyInteger('revision_limit')->default(2);
            $table->timestamp('deadline')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->json('terms')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('provider', 30);
            $table->string('reference', 80)->unique();
            $table->string('provider_reference', 150)->nullable()->unique();
            $table->string('checkout_id', 150)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 30)->default('PENDING');
            $table->json('provider_payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['transaction_id', 'status'], 'payments_tx_status_idx');
            $table->index(['provider', 'provider_reference'], 'payments_provider_ref_idx');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_key', 160);
            $table->string('provider_reference', 150)->nullable();
            $table->json('payload');
            $table->string('signature_status', 20)->default('PENDING');
            $table->string('processing_status', 20)->default('RECEIVED');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_key'], 'webhook_provider_event_unique');
            $table->index(['provider_reference', 'processing_status'], 'webhook_provider_status_idx');
        });

        Schema::create('transaction_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('description');
            $table->text('external_links')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['transaction_id', 'version'], 'submission_tx_version_unique');
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 30)->default('private');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->text('sha256');
            $table->timestamps();
            $table->index(['disk', 'path'], 'submission_files_disk_path_idx');
        });

        Schema::create('transaction_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('transaction_submissions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 20);
            $table->text('message')->nullable();
            $table->json('audit_metadata')->nullable();
            $table->timestamp('confirmed_at');
            $table->timestamps();
            $table->unique(['transaction_id', 'submission_id', 'role'], 'confirmation_tx_submission_role_unique');
        });

        Schema::create('transaction_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submission_id')->constrained('transaction_submissions')->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->text('instructions');
            $table->string('status', 20)->default('REQUESTED');
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['transaction_id', 'revision_number'], 'revision_tx_number_unique');
        });

        Schema::create('transaction_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('category', 50);
            $table->text('explanation');
            $table->string('status', 30)->default('OPEN');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('resolution_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique('transaction_id', 'dispute_tx_unique');
        });

        Schema::create('transaction_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 30)->default('private');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('idempotency_key', 160)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['transaction_id', 'event_type'], 'tx_status_log_event_idx');
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users')->restrictOnDelete();
            $table->string('reference', 80)->unique();
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 30)->default('PENDING_APPROVAL');
            $table->text('admin_note')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique('transaction_id', 'payout_tx_unique');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('provider_reference', 150)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('status', 30)->default('REQUESTED');
            $table->text('reason');
            $table->timestamps();
            $table->index(['transaction_id', 'status'], 'refunds_tx_status_idx');
        });

        Schema::create('financial_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('direction', 10);
            $table->text('description')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->index(['transaction_id', 'entry_type'], 'ledger_tx_entry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledger_entries');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('transaction_status_logs');
        Schema::dropIfExists('transaction_evidence');
        Schema::dropIfExists('transaction_disputes');
        Schema::dropIfExists('transaction_revisions');
        Schema::dropIfExists('transaction_confirmations');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('transaction_submissions');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('transaction_agreements');
        Schema::dropIfExists('transactions');
    }
};
