<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRevision extends Model
{
    protected $fillable = ['transaction_id', 'submission_id', 'requested_by', 'revision_number', 'instructions', 'status', 'requested_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
