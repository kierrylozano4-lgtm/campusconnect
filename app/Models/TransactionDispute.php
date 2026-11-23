<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionDispute extends Model
{
    protected $fillable = ['transaction_id', 'opened_by', 'category', 'explanation', 'status', 'resolved_by', 'resolution_reason', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(TransactionEvidence::class);
    }
}
