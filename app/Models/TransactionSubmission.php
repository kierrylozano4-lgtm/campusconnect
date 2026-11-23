<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionSubmission extends Model
{
    protected $fillable = ['transaction_id', 'freelancer_id', 'version', 'description', 'external_links', 'submitted_at'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'transaction_submission_id');
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(TransactionConfirmation::class, 'submission_id');
    }
}
