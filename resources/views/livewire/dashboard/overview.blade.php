@php
use Illuminate\Support\Str;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-heading text-xl font-semibold text-on-surface">¡Hola, {{ auth()->user()->name }}!</h1>
            <p class="mt-1 text-sm text-on-surface-variant">Hoy es {{ $today->translatedFormat('l j \d\e F \d\e Y') }}.</p>
        </div>
        <a href="{{ route('habits.create') }}" wire:navigate>
            <x-button type="button" class="w-auto px-4">
                <x-icon name="plus" class="mr-1.5 h-4 w-4" /> Nuevo hábito
            </x-button>
        </a>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:col-span-1">
            <p class="text-sm text-on-surface-variant">Cumplimiento de hoy</p>
            <p class="mt-1 font-heading text-3xl font-semibold text-primary">
                {{ $totalToday > 0 ? round($completedToday / $totalToday * 100) : 0 }}%
            </p>
            <p class="mt-1 text-sm text-on-surface-variant">{{ $completedToday }} de {{ $totalToday }} hábitos</p>
            <x-progress-bar :percentage="$totalToday > 0 ? $completedToday / $totalToday * 100 : 0" class="mt-3" />
        </div>

        <x-stat-card label="Resumen semanal" :value="$weeklyPercentage.'%'" icon="chart" hint="Cumplimiento de los últimos 7 días" class="sm:col-span-1" />

        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:col-span-1">
            <p class="mb-2 text-sm text-on-surface-variant">Hábitos en riesgo</p>
            @if ($atRisk->isEmpty())
                <p class="text-sm text-on-surface">Ninguno — vas muy bien hoy.</p>
            @else
                <ul class="space-y-1.5">
                    @foreach ($atRisk as $item)
                        <li class="flex items-center gap-2 text-sm">
                            <x-icon name="flame" class="h-4 w-4 text-error" />
                            <span class="truncate text-on-surface">{{ $item['habit']->is_private ? 'Hábito privado' : $item['habit']->name }}</span>
                            <span class="text-xs text-on-surface-variant">({{ $item['summary']['current_streak'] }} días)</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-heading text-base font-semibold text-on-surface">Hábitos de hoy</h2>
                <a href="{{ route('habits.today') }}" wire:navigate class="text-sm font-medium text-primary hover:underline">Ver todos</a>
            </div>

            @if ($todayItems->isEmpty())
                <div class="rounded-xl border border-dashed border-outline-variant p-8 text-center">
                    <p class="text-sm text-on-surface-variant">No tienes hábitos programados para hoy.</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($todayItems->take(6) as $item)
                        <x-habit-card :habit="$item['habit']" :current-streak="$item['summary']['current_streak']">
                            <x-slot:action>
                                @if ($item['log']?->status === \App\Enums\LogStatus::Completed)
                                    <span class="text-primary"><x-icon name="check" class="h-5 w-5" /></span>
                                @else
                                    <span class="text-on-surface-variant"><x-icon name="dot-circle" class="h-5 w-5" /></span>
                                @endif
                            </x-slot:action>
                        </x-habit-card>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Rachas activas</h2>
            @if ($topStreaks->isEmpty())
                <div class="rounded-xl border border-dashed border-outline-variant p-8 text-center">
                    <p class="text-sm text-on-surface-variant">Aún no tienes rachas activas.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($topStreaks as $item)
                        <div class="flex items-center justify-between rounded-xl border border-outline-variant bg-surface-container-lowest p-3">
                            <span class="truncate text-sm text-on-surface">{{ $item['habit']->is_private ? 'Hábito privado' : $item['habit']->name }}</span>
                            <x-streak-badge :days="$item['summary']['current_streak']" />
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Actividad reciente</h2>
        <x-mini-heatmap :cells="$activityGrid" />
    </div>
</div>
