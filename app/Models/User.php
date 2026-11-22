<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['full_name', 'student_id', 'email', 'password', 'design_theme', 'design_palette'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function conversationsAsFirst(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_one_id');
    }

    public function conversationsAsSecond(): HasMany
    {
        return $this->hasMany(Conversation::class, 'user_two_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewed_user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function feedbackMessages(): HasMany
    {
        return $this->hasMany(UserFeedback::class);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->full_name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => strtoupper(substr($part, 0, 1)))
            ->implode('');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }
}
