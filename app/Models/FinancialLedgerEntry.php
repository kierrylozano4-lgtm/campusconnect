<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialLedgerEntry extends Model
{
    protected $fillable = ['transaction_id', 'payment_id', 'payout_id', 'refund_id', 'entry_type', 'amount', 'currency', 'direction', 'description', 'posted_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'posted_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
