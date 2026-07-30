<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Categorías por defecto que se crean para que el usuario tenga
     * opciones al crear su primer hábito, sin necesidad de un módulo
     * de gestión de categorías en esta fase.
     */
    private const DEFAULT_CATEGORIES = [
        ['name' => 'Salud', 'color' => '#a8364b', 'icon' => 'heart'],
        ['name' => 'Productividad', 'color' => '#5b5a8b', 'icon' => 'bolt'],
        ['name' => 'Bienestar', 'color' => '#755478', 'icon' => 'sparkle'],
        ['name' => 'Aprendizaje', 'color' => '#5e5d72', 'icon' => 'book'],
        ['name' => 'Finanzas', 'color' => '#3f6b52', 'icon' => 'coin'],
    ];

    public function created(User $user): void
    {
        $user->categories()->createMany(
            collect(self::DEFAULT_CATEGORIES)
                ->map(fn (array $category, int $index) => [...$category, 'position' => $index])
                ->all()
        );
    }
}
