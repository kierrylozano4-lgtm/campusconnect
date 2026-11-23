<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionStatusLog extends Model
{
    protected $fillable = ['transaction_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'idempotency_key', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
