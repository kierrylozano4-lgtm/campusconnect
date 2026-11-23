<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['transaction_id', 'initiated_by', 'provider', 'reference', 'provider_reference', 'checkout_id', 'amount', 'currency', 'status', 'provider_payload', 'verified_at', 'failed_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'provider_payload' => 'array', 'verified_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
