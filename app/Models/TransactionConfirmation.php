<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionConfirmation extends Model
{
    protected $fillable = ['transaction_id', 'submission_id', 'user_id', 'role', 'message', 'audit_metadata', 'confirmed_at'];

    protected function casts(): array
    {
        return ['audit_metadata' => 'array', 'confirmed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TransactionSubmission::class, 'submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
