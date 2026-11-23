<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAgreement extends Model
{
    protected $fillable = ['transaction_id', 'title', 'description', 'agreed_amount', 'currency', 'revision_limit', 'deadline', 'commission_rate', 'terms'];

    protected function casts(): array
    {
        return ['agreed_amount' => 'decimal:2', 'commission_rate' => 'decimal:2', 'deadline' => 'datetime', 'terms' => 'array'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
