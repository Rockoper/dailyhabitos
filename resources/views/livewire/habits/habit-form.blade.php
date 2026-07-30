@php
use App\Enums\FrequencyType;
use App\Enums\HabitType;
use App\Enums\HabitUnit;

$icons = ['sparkle', 'heart', 'bolt', 'book', 'coin', 'flame', 'trophy', 'chart', 'bell', 'lock', 'check', 'note', 'drop', 'star', 'target'];
$weekdays = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
@endphp

<div class="mx-auto max-w-2xl">
    <form wire:submit="save" class="space-y-8">
        <section class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="font-heading text-base font-semibold text-on-surface">Información básica</h2>

            <div>
                <x-input-label for="name" value="Nombre" />
                <x-text-input id="name" wire:model="name" type="text" class="mt-0" placeholder="Ej. Meditar 10 minutos" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="description" value="Descripción (opcional)" />
                <x-textarea id="description" wire:model="description" placeholder="Notas, motivación o contexto del hábito" />
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="category_id" value="Categoría (opcional)" />
                    <x-select id="category_id" wire:model="category_id">
                        <option value="">Sin categoría</option>
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('category_id')" />
                </div>

                <div>
                    <x-input-label for="color" value="Color" />
                    <input id="color" type="color" wire:model="color" class="h-[42px] w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-2">
                    <x-input-error :messages="$errors->get('color')" />
                </div>
            </div>

            <div>
                <x-input-label value="Ícono" />
                <div class="flex flex-wrap gap-2">
                    @foreach ($icons as $iconOption)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="icon" value="{{ $iconOption }}" class="peer sr-only">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container">
                                <x-icon :name="$iconOption" class="h-5 w-5" />
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('icon')" />
            </div>
        </section>

        <section class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="font-heading text-base font-semibold text-on-surface">Tipo y frecuencia</h2>

            <div>
                <x-input-label for="type" value="Tipo de hábito" />
                <x-select id="type" wire:model.live="type">
                    @foreach (HabitType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </x-select>
                <p class="mt-1 text-xs text-on-surface-variant">{{ HabitType::from($type)->description() }}</p>
                <x-input-error :messages="$errors->get('type')" />
            </div>

            @if ($type === HabitType::Quantity->value)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="target_quantity" value="Meta numérica (opcional)" />
                        <x-text-input id="target_quantity" wire:model="target_quantity" type="number" step="0.01" min="0" />
                        <x-input-error :messages="$errors->get('target_quantity')" />
                    </div>
                    <div>
                        <x-input-label for="unit" value="Unidad" />
                        <x-select id="unit" wire:model.live="unit">
                            <option value="">Sin unidad</option>
                            @foreach (HabitUnit::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </x-select>
                        <x-input-error :messages="$errors->get('unit')" />
                    </div>
                </div>

                @if ($unit === HabitUnit::Other->value)
                    <div>
                        <x-input-label for="unit_custom_label" value="Nombre de la unidad" />
                        <x-text-input id="unit_custom_label" wire:model="unit_custom_label" type="text" placeholder="Ej. sesiones" />
                        <x-input-error :messages="$errors->get('unit_custom_label')" />
                    </div>
                @endif
            @endif

            @if ($type === HabitType::Weekly->value)
                <div>
                    <x-input-label for="times_per_week" value="Veces por semana" />
                    <x-text-input id="times_per_week" wire:model="times_per_week" type="number" min="1" max="7" />
                    <x-input-error :messages="$errors->get('times_per_week')" />
                </div>
            @else
                <div>
                    <x-input-label for="frequency_type" value="Frecuencia" />
                    <x-select id="frequency_type" wire:model.live="frequency_type">
                        <option value="{{ FrequencyType::Daily->value }}">{{ FrequencyType::Daily->label() }}</option>
                        <option value="{{ FrequencyType::SpecificDays->value }}">{{ FrequencyType::SpecificDays->label() }}</option>
                        <option value="{{ FrequencyType::Interval->value }}">{{ FrequencyType::Interval->label() }}</option>
                    </x-select>
                    <x-input-error :messages="$errors->get('frequency_type')" />
                </div>

                @if ($frequency_type === FrequencyType::SpecificDays->value)
                    <div>
                        <x-input-label value="Días de la semana" />
                        <div class="flex flex-wrap gap-2">
                            @foreach ($weekdays as $iso => $labelDay)
                                <label class="cursor-pointer">
                                    <input type="checkbox" wire:model="specific_days" value="{{ $iso }}" class="peer sr-only">
                                    <span class="flex h-10 items-center rounded-lg border border-outline-variant px-3 text-sm text-on-surface-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container">
                                        {{ Str::limit($labelDay, 3, '') }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('specific_days')" />
                    </div>
                @endif

                @if ($frequency_type === FrequencyType::Interval->value)
                    <div>
                        <x-input-label for="interval_days" value="Repetir cada (días)" />
                        <x-text-input id="interval_days" wire:model="interval_days" type="number" min="1" max="365" />
                        <x-input-error :messages="$errors->get('interval_days')" />
                    </div>
                @endif
            @endif
        </section>

        <section class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="font-heading text-base font-semibold text-on-surface">Vigencia</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Fecha de inicio" />
                    <x-text-input id="start_date" wire:model="start_date" type="date" />
                    <x-input-error :messages="$errors->get('start_date')" />
                </div>
                <div>
                    <x-input-label for="end_date" value="Fecha final (opcional)" />
                    <x-text-input id="end_date" wire:model="end_date" type="date" />
                    <x-input-error :messages="$errors->get('end_date')" />
                </div>
            </div>
        </section>

        <section class="space-y-1 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-2 font-heading text-base font-semibold text-on-surface">Preferencias</h2>

            <x-toggle wire:model="never_fail_twice" label="Nunca fallar dos veces" description="Un fallo aislado no rompe la racha; dos fallos seguidos sí." />
            <x-toggle wire:model="is_private" label="Hábito privado" description="Oculta el nombre en notificaciones y vistas compartidas." />
            <x-toggle wire:model.live="remind_at_enabled" label="Recordatorio" description="Las notificaciones se implementarán en una fase futura; por ahora solo se guarda la hora." />

            @if ($remind_at_enabled)
                <div class="pt-2">
                    <x-input-label for="remind_at" value="Hora del recordatorio" />
                    <x-text-input id="remind_at" wire:model="remind_at" type="time" class="max-w-[10rem]" />
                    <x-input-error :messages="$errors->get('remind_at')" />
                </div>
            @endif
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $habit ? route('habits.show', $habit) : route('habits.index') }}" class="text-sm font-medium text-on-surface-variant hover:text-on-surface">
                Cancelar
            </a>
            <x-button type="submit" class="w-auto px-6">
                {{ $habit ? 'Guardar cambios' : 'Crear hábito' }}
            </x-button>
        </div>
    </form>
</div>
