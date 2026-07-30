@php
use Illuminate\Support\Str;

$moodOptions = [1 => 'Muy mal', 2 => 'Mal', 3 => 'Neutral', 4 => 'Bien', 5 => 'Muy bien'];
$scaleLabels = [1 => 'Muy baja', 2 => 'Baja', 3 => 'Media', 4 => 'Alta', 5 => 'Muy alta'];
$suggestedTags = ['Trabajo', 'Estudio', 'Salud', 'Familia', 'Finanzas', 'Relaciones', 'Descanso', 'Ejercicio', 'Estrés', 'Logro'];
$guidedQuestions = [
    ['field' => 'went_well', 'question' => '¿Qué salió bien hoy?', 'value' => $went_well],
    ['field' => 'challenges', 'question' => '¿Qué fue difícil?', 'value' => $challenges],
    ['field' => 'learned', 'question' => '¿Qué aprendiste hoy?', 'value' => $learned],
    ['field' => 'gratitude', 'question' => '¿Por qué estás agradecido?', 'value' => $gratitude],
    ['field' => 'improve_tomorrow', 'question' => '¿Qué puedes mejorar mañana?', 'value' => $improve_tomorrow],
    ['field' => 'tomorrow_priority', 'question' => '¿Cuál es tu prioridad principal para mañana?', 'value' => $tomorrow_priority],
];
$dateLabel = Str::ucfirst($selectedDate->translatedFormat('l, j \d\e F \d\e Y'));
$customTags = array_values(array_diff($tags, $suggestedTags));
@endphp

<div class="mx-auto max-w-3xl space-y-6" x-data="{ dirty: @entangle('isDirty') }" x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }">
    @if (session('status'))
        <p class="rounded-lg bg-secondary-container px-3.5 py-2.5 text-sm text-on-secondary-container">{{ session('status') }}</p>
    @endif

    {{-- Encabezado --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-heading text-xl font-semibold text-on-surface">Reflexión diaria</h1>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $greeting }} · {{ $dateLabel }}</p>
        </div>

        <div class="flex items-center gap-1.5 rounded-lg border border-outline-variant bg-surface-container-lowest p-1">
            <button type="button" wire:click="goPrevious" aria-label="Día anterior" class="flex h-8 w-8 items-center justify-center rounded-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>
            <button type="button" wire:click="goToday" @if($isToday) disabled @endif class="rounded-md px-3 py-1.5 text-xs font-semibold text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:cursor-default disabled:bg-primary-container disabled:text-on-primary-container disabled:hover:bg-primary-container">
                Hoy
            </button>
            <button type="button" wire:click="goNext" aria-label="Día siguiente" @disabled(! $canGoNext) class="flex h-8 w-8 items-center justify-center rounded-md text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-40">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </button>
        </div>
    </div>

    <p class="rounded-lg bg-secondary-container/60 px-4 py-3 text-sm text-on-secondary-container">
        Dedica unos minutos a revisar tu día con honestidad y sin juzgarte.
    </p>

    {{-- Resumen del día --}}
    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-heading text-base font-semibold text-on-surface">Resumen del día</h2>
            <span class="rounded-full bg-primary-container px-2.5 py-1 text-xs font-medium text-on-primary-container">{{ $summary['day_label'] }}</span>
        </div>

        @if ($summary['expected'] === 0)
            <p class="mt-3 text-sm text-on-surface-variant">No tenías hábitos programados este día. Puedes escribir tu reflexión de todas formas.</p>
        @else
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-progress-bar :percentage="$summary['percentage']" label="Hábitos completados ({{ $summary['completed'] }}/{{ $summary['expected'] }})" />
                <div class="grid grid-cols-2 gap-3">
                    <x-stat-card label="Racha" :value="$summary['current_streak'].' días'" icon="flame" />
                    <x-stat-card label="Última actividad" :value="$summary['last_activity_at']?->format('g:i a') ?? '—'" icon="dot-circle" />
                </div>
            </div>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Estado de ánimo --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <x-input-label value="¿Cómo te sientes hoy?" />
            <div class="mt-2 grid grid-cols-5 gap-2" role="radiogroup" aria-label="Estado de ánimo">
                @foreach ($moodOptions as $value => $label)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model.live="mood" value="{{ $value }}" class="peer sr-only" aria-label="{{ $label }}">
                        <span class="flex flex-col items-center gap-1.5 rounded-lg border border-outline-variant px-2 py-3 text-center peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40">
                            <x-icon name="mood-{{ $value }}" class="h-6 w-6" />
                            <span class="text-xs font-medium">{{ $label }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('mood')" />
        </div>

        {{-- Energía y productividad --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <x-input-label value="Nivel de energía (opcional)" />
                <div class="mt-2 flex gap-1.5" role="radiogroup" aria-label="Nivel de energía">
                    @foreach ($scaleLabels as $value => $label)
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" wire:model.live="energy_level" value="{{ $value }}" class="peer sr-only" aria-label="{{ $label }}">
                            <span class="flex h-14 flex-col items-center justify-center rounded-lg border border-outline-variant text-center peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40">
                                <span class="text-sm font-semibold">{{ $value }}</span>
                                <span class="text-[10px] leading-tight">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('energy_level')" />
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <x-input-label value="Productividad percibida (opcional)" />
                <div class="mt-2 flex gap-1.5" role="radiogroup" aria-label="Productividad percibida">
                    @foreach ($scaleLabels as $value => $label)
                        <label class="flex-1 cursor-pointer">
                            <input type="radio" wire:model.live="productivity_level" value="{{ $value }}" class="peer sr-only" aria-label="{{ $label }}">
                            <span class="flex h-14 flex-col items-center justify-center rounded-lg border border-outline-variant text-center peer-checked:border-primary peer-checked:bg-primary-container peer-checked:text-on-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40">
                                <span class="text-sm font-semibold">{{ $value }}</span>
                                <span class="text-[10px] leading-tight">{{ $label }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('productivity_level')" />
            </div>
        </div>

        {{-- Preguntas guiadas --}}
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($guidedQuestions as $item)
                <div x-data="{ count: {{ mb_strlen($item['value'] ?? '') }} }">
                    <x-input-label :for="$item['field']" :value="$item['question']" />
                    <x-textarea :id="$item['field']" wire:model.live.debounce.2000ms="{{ $item['field'] }}" rows="2" maxlength="500" x-on:input="count = $event.target.value.length" placeholder="Escribe una respuesta breve (opcional)" />
                    <div class="mt-1 flex items-center justify-between">
                        <x-input-error :messages="$errors->get($item['field'])" />
                        <span class="ml-auto text-xs text-on-surface-variant" x-text="count + '/500'"></span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Escritura libre --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5" x-data="{ count: {{ mb_strlen($free_notes ?? '') }} }">
            <x-input-label for="free_notes" value="Escribe libremente sobre tu día" />
            <p class="mb-2 text-xs text-on-surface-variant">Texto plano, sin formato especial. Tus saltos de línea se conservan.</p>
            <x-textarea id="free_notes" wire:model.live.debounce.2500ms="free_notes" rows="6" maxlength="10000" x-on:input="count = $event.target.value.length" placeholder="¿Qué más quieres recordar de hoy?" />
            <div class="mt-1 flex items-center justify-between">
                <x-input-error :messages="$errors->get('free_notes')" />
                <span class="ml-auto text-xs text-on-surface-variant" x-text="count + '/10000'"></span>
            </div>
        </div>

        {{-- Etiquetas --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <x-input-label value="Etiquetas (opcional)" />
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($suggestedTags as $tag)
                    <button type="button" wire:click="toggleTag('{{ $tag }}')" wire:key="tag-{{ $tag }}"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ in_array($tag, $tags, true) ? 'border-primary bg-primary-container text-on-primary-container' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-high' }}">
                        {{ $tag }}
                    </button>
                @endforeach

                @foreach ($customTags as $tag)
                    <span wire:key="custom-tag-{{ $tag }}" class="inline-flex items-center gap-1 rounded-full border border-primary bg-primary-container px-3 py-1.5 text-xs font-medium text-on-primary-container">
                        {{ $tag }}
                        <button type="button" wire:click="removeTag('{{ $tag }}')" aria-label="Quitar etiqueta {{ $tag }}" class="hover:opacity-70">
                            <x-icon name="close" class="h-3 w-3" />
                        </button>
                    </span>
                @endforeach
            </div>

            <div class="mt-3 flex max-w-xs gap-2">
                <x-text-input type="text" wire:model="customTag" wire:keydown.enter.prevent="addCustomTag" maxlength="30" placeholder="Otra etiqueta" aria-label="Agregar etiqueta personalizada" />
                <x-button type="button" variant="ghost" wire:click="addCustomTag" class="w-auto px-3">Agregar</x-button>
            </div>
            <x-input-error :messages="$errors->get('tags')" />
        </div>

        {{-- Barra de acciones --}}
        <div class="sticky bottom-4 flex items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest px-5 py-3 shadow-sm">
            <p class="flex items-center gap-1.5 text-xs text-on-surface-variant" aria-live="polite">
                <span wire:loading wire:target="save, autosave, toggleTag, addCustomTag, removeTag">Guardando…</span>
                <span wire:loading.remove wire:target="save, autosave, toggleTag, addCustomTag, removeTag">
                    @if ($status->value === 'completed')
                        <x-icon name="check" class="h-3.5 w-3.5 text-primary" />
                        Reflexión guardada{{ $lastSavedAt ? ' · '.$lastSavedAt : '' }}
                    @elseif ($lastSavedAt)
                        <x-icon name="check" class="h-3.5 w-3.5 text-primary" />
                        Borrador guardado {{ $lastSavedAt }}
                    @else
                        Aún no hay cambios guardados
                    @endif
                </span>
            </p>
            <x-button type="submit" class="w-auto px-6" wire:loading.attr="disabled" wire:target="save">
                Guardar reflexión
            </x-button>
        </div>
    </form>

    {{-- Reflexiones recientes --}}
    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="font-heading text-base font-semibold text-on-surface">Reflexiones recientes</h2>

        @if ($recent->isEmpty())
            <p class="mt-3 text-sm text-on-surface-variant">Todavía no has escrito ninguna reflexión. La de hoy puede ser la primera.</p>
        @else
            <div class="mt-3 space-y-2">
                @foreach ($recent as $item)
                    <div wire:key="recent-{{ $item->id }}" class="flex items-center justify-between gap-3 rounded-lg border border-outline-variant p-3">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <x-icon :name="'mood-'.($item->mood ?? 3)" class="h-5 w-5 shrink-0 text-on-surface-variant" />
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-on-surface">{{ Str::ucfirst($item->reflection_date->translatedFormat('D, j M')) }}</p>
                                <p class="truncate text-xs text-on-surface-variant">{{ Str::limit($item->went_well ?: $item->free_notes ?: 'Sin notas escritas.', 60) }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-[11px] font-medium text-on-surface-variant">{{ $item->status->label() }}</span>
                            <button type="button" wire:click="openDate('{{ $item->reflection_date->format('Y-m-d') }}')" class="text-xs font-semibold text-primary hover:underline">Abrir</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Observaciones --}}
    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="font-heading text-base font-semibold text-on-surface">Observaciones</h2>

        @if (! $insights['has_enough_data'])
            <p class="mt-3 text-sm text-on-surface-variant">Aún no hay suficientes reflexiones para mostrar observaciones. Sigue escribiendo cada día.</p>
        @elseif (empty($insights['insights']))
            <p class="mt-3 text-sm text-on-surface-variant">Todavía no encontramos patrones claros con tus datos actuales.</p>
        @else
            <ul class="mt-3 space-y-2">
                @foreach ($insights['insights'] as $insight)
                    <li class="flex items-start gap-2 text-sm text-on-surface">
                        <x-icon name="sparkle" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        {{ $insight }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
