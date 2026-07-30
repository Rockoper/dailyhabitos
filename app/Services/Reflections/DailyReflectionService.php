<?php

namespace App\Services\Reflections;

use App\Enums\ReflectionStatus;
use App\Models\DailyReflection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de `DailyReflection`, siguiendo el mismo patrón
 * de `HabitLogger`: upsert por `(user_id, reflection_date)` dentro de una
 * transacción, nunca creación directa desde el componente Livewire.
 *
 * La búsqueda usa `whereDate()` (no un `where()` con string plano ni
 * `firstOrNew`/`updateOrCreate` con el atributo `date` como criterio):
 * Eloquent guarda los atributos casteados a `date` con el formato completo
 * del modelo (`Y-m-d H:i:s`), así que una comparación de igualdad contra un
 * string `Y-m-d` nunca encontraría la fila ya creada y terminaría insertando
 * un duplicado que choca con el único `(user_id, reflection_date)`.
 */
class DailyReflectionService
{
    /**
     * Autoguardado silencioso: conserva el estado actual (un borrador sigue
     * siendo borrador, una reflexión completada no se revierte a borrador).
     */
    public function saveDraft(User $user, CarbonImmutable $date, array $data): DailyReflection
    {
        return DB::transaction(function () use ($user, $date, $data) {
            $reflection = $this->findOrNew($user, $date);
            $reflection->fill($data);

            if (! $reflection->exists) {
                $reflection->status = ReflectionStatus::Draft;
            }

            $reflection->save();

            return $reflection;
        });
    }

    /**
     * Guardado explícito ("Guardar reflexión"): finaliza la reflexión del día.
     */
    public function complete(User $user, CarbonImmutable $date, array $data): DailyReflection
    {
        return DB::transaction(function () use ($user, $date, $data) {
            $reflection = $this->findOrNew($user, $date);
            $reflection->fill($data);
            $reflection->status = ReflectionStatus::Completed;
            $reflection->completed_at ??= now();
            $reflection->save();

            return $reflection;
        });
    }

    private function findOrNew(User $user, CarbonImmutable $date): DailyReflection
    {
        $reflection = $user->dailyReflections()
            ->whereDate('reflection_date', $date->toDateString())
            ->first();

        return $reflection ?? $user->dailyReflections()->make(['reflection_date' => $date->toDateString()]);
    }
}
