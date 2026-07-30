<?php

namespace App\Policies;

use App\Models\DailyReflection;
use App\Models\User;

class DailyReflectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DailyReflection $reflection): bool
    {
        return $user->id === $reflection->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, DailyReflection $reflection): bool
    {
        return $user->id === $reflection->user_id;
    }

    public function delete(User $user, DailyReflection $reflection): bool
    {
        return $user->id === $reflection->user_id;
    }
}
