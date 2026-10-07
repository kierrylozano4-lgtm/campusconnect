<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = ['user_id', 'title', 'category', 'description', 'status', 'inactive_reason', 'visible_in_dashboard'];

    protected $casts = ['visible_in_dashboard' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
