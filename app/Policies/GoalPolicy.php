<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function restore(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    /** Completar, reabrir, pausar, reanudar, archivar, cancelar. */
    public function transition(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    /** Crear, editar o eliminar registros de progreso propios del objetivo. */
    public function manageProgress(User $user, Goal $goal): bool
    {
        return $user->id === $goal->user_id;
    }
}
