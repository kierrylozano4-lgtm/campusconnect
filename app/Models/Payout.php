<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = ['transaction_id', 'freelancer_id', 'reference', 'gross_amount', 'commission_amount', 'deductions', 'net_amount', 'currency', 'status', 'admin_note', 'authorized_by', 'authorized_at', 'completed_at'];

    protected function casts(): array
    {
        return ['gross_amount' => 'decimal:2', 'commission_amount' => 'decimal:2', 'deductions' => 'decimal:2', 'net_amount' => 'decimal:2', 'authorized_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
