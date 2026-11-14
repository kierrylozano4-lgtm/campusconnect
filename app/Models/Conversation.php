<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = ['user_one_id', 'user_two_id'];

    public static function between(User $firstUser, User $secondUser): self
    {
        $userIds = [$firstUser->id, $secondUser->id];
        sort($userIds, SORT_NUMERIC);

        return static::firstOrCreate([
            'user_one_id' => $userIds[0],
            'user_two_id' => $userIds[1],
        ]);
    }

    public function firstParticipant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function secondParticipant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    public function otherParticipant(User $user): User
    {
        abort_unless(in_array($user->id, [$this->user_one_id, $this->user_two_id], true), 403);

        if ($user->id === $this->user_one_id) {
            return $this->secondParticipant ?? $this->secondParticipant()->firstOrFail();
        }

        return $this->firstParticipant ?? $this->firstParticipant()->firstOrFail();
    }
}
