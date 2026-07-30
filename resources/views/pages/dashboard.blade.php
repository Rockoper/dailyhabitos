<x-layouts.app title="Dashboard">
    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-6">
        <p class="font-heading text-lg font-semibold text-on-surface">
            ¡Hola, {{ auth()->user()->name }}!
        </p>
        <p class="mt-1 text-sm text-on-surface-variant">
            Hoy es {{ now()->translatedFormat('l j \d\e F \d\e Y') }}.
        </p>
        <p class="mt-4 text-sm text-on-surface-variant">
            El panel principal con tus rachas, hábitos de hoy y calendario se construye en la Fase 1–3
            del plan (ver <code class="rounded bg-surface-container px-1.5 py-0.5">docs/PROJECT_PLAN.md</code>).
        </p>
    </div>
</x-layouts.app>
