<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return in_array($user->id, [$transaction->client_id, $transaction->freelancer_id], true);
    }

    public function start(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->freelancer_id;
    }

    public function submit(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->freelancer_id;
    }

    public function accept(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->client_id;
    }

    public function revise(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->client_id;
    }

    public function acknowledge(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->freelancer_id;
    }
}
