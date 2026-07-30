@php
use App\Enums\GoalPriority;
use App\Enums\GoalType;

$icons = ['target', 'flame', 'trophy', 'star', 'chart', 'book', 'coin', 'heart', 'bolt', 'sparkle', 'note', 'drop'];
@endphp

<div class="mx-auto max-w-2xl">
    <form wire:submit="save" class="space-y-8">
        <section class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="font-heading text-base font-semibold text-on-surface">Información básica</h2>

            <div>
                <x-input-label for="name" value="Nombre" />
                <x-text-input id="name" wire:model="name" type="text" class="mt-0" placeholder="Ej. Leer 12 libros este año" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="description" value="Descripción (opcional)" />
                <x-textarea id="description" wire:model="description" placeholder="¿Por qué te importa este objetivo?" />
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
            <h2 class="font-heading text-base font-semibold text-on-surface">Tipo de objetivo</h2>

            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (GoalType::cases() as $case)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="type" value="{{ $case->value }}" class="peer sr-only">
                        <span class="block rounded-lg border border-outline-variant p-3 peer-checked:border-primary peer-checked:bg-primary-container/40">
                            <span class="block text-sm font-medium text-on-surface">{{ $case->label() }}</span>
                            <span class="mt-0.5 block text-xs text-on-surface-variant">{{ $case->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('type')" />

            @if ($type === GoalType::Habit->value || $type === GoalType::Streak->value || ($type === GoalType::Percentage->value))
                <div>
                    <x-input-label for="habit_id" :value="$type === GoalType::Percentage->value ? 'Hábito vinculado (opcional)' : 'Hábito vinculado'" />
                    <x-select id="habit_id" wire:model="habit_id">
                        <option value="">{{ $type === GoalType::Percentage->value ? 'Todos los hábitos activos' : 'Selecciona un hábito' }}</option>
                        @foreach ($this->habits as $habitOption)
                            <option value="{{ $habitOption->id }}">{{ $habitOption->is_private ? 'Hábito privado' : $habitOption->name }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('habit_id')" />
                </div>
            @endif

            @if ($type === GoalType::Habit->value)
                <div>
                    <x-input-label for="target_value" value="Cantidad de cumplimientos" />
                    <x-text-input id="target_value" wire:model="target_value" type="number" min="1" step="1" placeholder="Ej. 20" />
                    <x-input-error :messages="$errors->get('target_value')" />
                </div>
            @endif

            @if ($type === GoalType::Streak->value)
                <div>
                    <x-input-label for="target_value" value="Días de racha" />
                    <x-text-input id="target_value" wire:model="target_value" type="number" min="1" step="1" placeholder="Ej. 30" />
                    <x-input-error :messages="$errors->get('target_value')" />
                </div>
            @endif

            @if ($type === GoalType::Numeric->value)
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="initial_value" value="Valor inicial" />
                        <x-text-input id="initial_value" wire:model="initial_value" type="number" step="0.01" min="0" placeholder="0" />
                        <x-input-error :messages="$errors->get('initial_value')" />
                    </div>
                    <div>
                        <x-input-label for="target_value" value="Meta" />
                        <x-text-input id="target_value" wire:model="target_value" type="number" step="0.01" min="0.01" placeholder="Ej. 5000000" />
                        <x-input-error :messages="$errors->get('target_value')" />
                    </div>
                    <div>
                        <x-input-label for="unit" value="Unidad" />
                        <x-text-input id="unit" wire:model="unit" type="text" placeholder="Ej. COP, libros, horas" />
                        <x-input-error :messages="$errors->get('unit')" />
                    </div>
                </div>
            @endif

            @if ($type === GoalType::Percentage->value)
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="target_value" value="Porcentaje objetivo" />
                        <x-text-input id="target_value" wire:model="target_value" type="number" min="1" max="100" step="1" placeholder="Ej. 90" />
                        <x-input-error :messages="$errors->get('target_value')" />
                    </div>
                    <div>
                        <x-input-label for="settings_period" value="Periodo de medición" />
                        <x-select id="settings_period" wire:model="settings_period">
                            <option value="week">Esta semana</option>
                            <option value="month">Este mes</option>
                            <option value="year">Este año</option>
                        </x-select>
                        <x-input-error :messages="$errors->get('settings_period')" />
                    </div>
                </div>
            @endif

            @if ($type === GoalType::Manual->value)
                <p class="text-xs text-on-surface-variant">Este objetivo se marca como completado manualmente desde su detalle, sin seguimiento numérico.</p>
            @endif
        </section>

        <section class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="font-heading text-base font-semibold text-on-surface">Vigencia y prioridad</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Fecha de inicio" />
                    <x-text-input id="start_date" wire:model="start_date" type="date" />
                    <x-input-error :messages="$errors->get('start_date')" />
                </div>
                <div>
                    <x-input-label for="due_date" :value="$type === GoalType::Deadline->value ? 'Fecha límite' : 'Fecha límite (opcional)'" />
                    <x-text-input id="due_date" wire:model="due_date" type="date" />
                    <x-input-error :messages="$errors->get('due_date')" />
                </div>
            </div>

            <div>
                <x-input-label value="Prioridad" />
                <div class="flex flex-wrap gap-2">
                    @foreach (GoalPriority::cases() as $case)
                        <label class="cursor-pointer">
                            <input type="radio" wire:model="priority" value="{{ $case->value }}" class="peer sr-only">
                            <span class="flex h-10 items-center rounded-lg border border-outline-variant px-4 text-sm text-on-surface-variant peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container">
                                {{ $case->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('priority')" />
            </div>
        </section>

        <section class="space-y-1 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-2 font-heading text-base font-semibold text-on-surface">Preferencias</h2>

            <x-toggle wire:model="is_private" label="Objetivo privado" description="Oculta el nombre en vistas compartidas." />
            <x-toggle wire:model.live="reminder_enabled" label="Recordatorio" description="Las notificaciones se implementarán en una fase futura; por ahora solo se guarda la fecha." />

            @if ($reminder_enabled)
                <div class="pt-2">
                    <x-input-label for="reminder_date" value="Fecha del recordatorio" />
                    <x-text-input id="reminder_date" wire:model="reminder_date" type="date" class="max-w-[12rem]" />
                    <x-input-error :messages="$errors->get('reminder_date')" />
                </div>
            @endif

            <div class="pt-2">
                <x-input-label for="notes" value="Notas (opcional)" />
                <x-textarea id="notes" wire:model="notes" placeholder="Notas adicionales sobre este objetivo" />
                <x-input-error :messages="$errors->get('notes')" />
            </div>
        </section>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ $goal ? route('goals.show', $goal) : route('goals.index') }}" wire:navigate class="text-sm font-medium text-on-surface-variant hover:text-on-surface">
                Cancelar
            </a>
            <x-button type="submit" class="w-auto px-6" wire:loading.attr="disabled" wire:target="save">
                {{ $goal ? 'Guardar cambios' : 'Crear objetivo' }}
            </x-button>
        </div>
    </form>
</div>
