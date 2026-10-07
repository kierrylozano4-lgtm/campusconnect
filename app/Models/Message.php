<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['service_request_id', 'sender_id', 'receiver_id', 'message'];
}
