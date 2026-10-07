<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['user_id', 'campus_address', 'grade_level', 'course_major', 'facebook', 'instagram', 'tiktok', 'github', 'year_level', 'bio', 'verification_status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
