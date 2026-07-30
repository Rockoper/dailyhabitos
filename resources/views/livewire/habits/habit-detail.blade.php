@php
use App\Enums\LogStatus;
@endphp

<div x-data="{ confirmingDelete: false }" class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg"
                style="background-color: {{ $habit->color }}26; color: {{ $habit->color }}"
            >
                <x-icon :name="$habit->icon" class="h-6 w-6" />
            </div>
            <div>
                <h1 class="font-heading text-xl font-semibold text-on-surface">
                    {{ $habit->is_private ? 'Hábito privado' : $habit->name }}
                </h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-on-surface-variant">
                    <span>{{ $habit->type->label() }}</span>
                    @if ($habit->category)
                        <span>· {{ $habit->category->name }}</span>
                    @endif
                    @if ($habit->is_archived)
                        <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-xs">Archivado</span>
                    @endif
                </div>
                @if ($habit->description && ! $habit->is_private)
                    <p class="mt-2 text-sm text-on-surface-variant">{{ $habit->description }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1">
            <a href="{{ route('habits.edit', $habit) }}" wire:navigate class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Editar">
                <x-icon name="edit" class="h-4 w-4" />
            </a>

            @if ($habit->is_archived)
                <button type="button" wire:click="unarchive" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Desarchivar">
                    <x-icon name="unarchive" class="h-4 w-4" />
                </button>
            @else
                <button type="button" wire:click="archive" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Archivar">
                    <x-icon name="archive" class="h-4 w-4" />
                </button>
            @endif

            <template x-if="!confirmingDelete">
                <button type="button" @click="confirmingDelete = true" class="rounded-lg p-2 text-on-surface-variant hover:bg-error-container/40 hover:text-error" title="Eliminar">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </template>
            <template x-if="confirmingDelete">
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="delete" class="rounded-lg bg-error px-2 py-1 text-xs font-medium text-on-error">Confirmar</button>
                    <button type="button" @click="confirmingDelete = false" class="rounded-lg px-2 py-1 text-xs text-on-surface-variant">Cancelar</button>
                </div>
            </template>
        </div>
    </div>

    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="mb-3 font-heading text-sm font-semibold text-on-surface">Registro de hoy</h2>

        @if ($isQuantity)
            <div class="flex items-center gap-2">
                <input
                    type="number" step="0.01" min="0"
                    wire:model="quantityInput"
                    class="w-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
                    placeholder="0"
                >
                <span class="text-sm text-on-surface-variant">
                    {{ $habit->displayUnit() }}
                    @if ($habit->target_quantity)
                        / meta {{ rtrim(rtrim((string) $habit->target_quantity, '0'), '.') }}
                    @endif
                </span>
                <x-button type="button" wire:click="logQuantity" variant="ghost" class="w-auto px-4">Guardar</x-button>
            </div>
        @else
            <button
                type="button"
                wire:click="toggleBinary"
                class="flex items-center gap-2 rounded-lg border-2 px-4 py-2.5 text-sm font-medium transition-colors {{ $todayLog?->status === LogStatus::Completed ? 'border-primary bg-primary text-on-primary' : 'border-outline-variant text-on-surface-variant hover:border-primary' }}"
            >
                <x-icon name="check" class="h-4 w-4" />
                {{ $todayLog?->status === LogStatus::Completed ? 'Cumplido hoy' : 'Marcar como cumplido' }}
            </button>
        @endif

        <div class="mt-3">
            <x-input-label for="note" value="Nota (opcional)" />
            <x-textarea id="note" wire:model.blur="noteInput" rows="2" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Racha actual" :value="$summary['current_streak'].' días'" icon="flame" />
        <x-stat-card label="Mejor racha" :value="$summary['longest_streak'].' días'" icon="trophy" />
        <x-stat-card label="Consistencia (30 días)" :value="$summary['consistency'][30].'%'" icon="chart" />
    </div>

    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="mb-3 font-heading text-sm font-semibold text-on-surface">Consistencia</h2>
        <div class="space-y-3">
            <x-progress-bar :percentage="$summary['consistency'][30]" label="Últimos 30 días" />
            <x-progress-bar :percentage="$summary['consistency'][90]" label="Últimos 90 días" />
            <x-progress-bar :percentage="$summary['consistency'][365]" label="Últimos 365 días" />
            <x-progress-bar :percentage="$summary['consistency']['lifetime']" label="Desde el inicio" />
        </div>
    </div>

    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="mb-3 font-heading text-sm font-semibold text-on-surface">Actividad reciente</h2>
        <x-mini-heatmap :cells="$grid" />
    </div>

    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="mb-3 font-heading text-sm font-semibold text-on-surface">Historial reciente</h2>

        @if ($recentLogs->isEmpty())
            <p class="text-sm text-on-surface-variant">Todavía no hay registros para este hábito.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[420px] text-left text-sm">
                    <thead>
                        <tr class="text-xs text-on-surface-variant">
                            <th class="pb-2 font-medium">Fecha</th>
                            <th class="pb-2 font-medium">Estado</th>
                            <th class="pb-2 font-medium">Cantidad</th>
                            <th class="pb-2 font-medium">Nota</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant">
                        @foreach ($recentLogs as $log)
                            <tr>
                                <td class="py-2 text-on-surface">{{ $log->date->translatedFormat('j M Y') }}</td>
                                <td class="py-2"><x-status-badge :status="$log->status" /></td>
                                <td class="py-2 text-on-surface-variant">{{ $log->quantity_value !== null ? rtrim(rtrim((string) $log->quantity_value, '0'), '.') : '—' }}</td>
                                <td class="py-2 text-on-surface-variant">
                                    @if ($log->note)
                                        <x-icon name="note" class="h-4 w-4" />
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
