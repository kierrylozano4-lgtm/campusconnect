<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionEvidence extends Model
{
    protected $fillable = ['transaction_dispute_id', 'submitted_by', 'disk', 'path', 'original_name', 'mime_type', 'size', 'description'];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(TransactionDispute::class, 'transaction_dispute_id');
    }
}
