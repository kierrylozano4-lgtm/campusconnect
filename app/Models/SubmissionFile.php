<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    protected $fillable = ['transaction_submission_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(TransactionSubmission::class, 'transaction_submission_id');
    }
}
