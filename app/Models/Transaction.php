<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    protected $fillable = [
        'service_request_id', 'client_id', 'freelancer_id', 'reference', 'agreed_amount', 'currency', 'selected_payment_method',
        'payment_status', 'work_status', 'confirmation_status', 'dispute_status', 'payout_status',
        'lifecycle_status', 'deadline', 'payment_verified_at', 'work_started_at', 'completed_at', 'paid_out_at',
    ];

    protected function casts(): array
    {
        return [
            'agreed_amount' => 'decimal:2',
            'deadline' => 'datetime',
            'payment_verified_at' => 'datetime',
            'work_started_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_out_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function freelancer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }

    public function agreement(): HasOne
    {
        return $this->hasOne(TransactionAgreement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(TransactionSubmission::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(TransactionConfirmation::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransactionRevision::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(TransactionDispute::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(TransactionStatusLog::class);
    }

    public function payout(): HasOne
    {
        return $this->hasOne(Payout::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(FinancialLedgerEntry::class);
    }
}
