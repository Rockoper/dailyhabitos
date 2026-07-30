<x-layouts.app :title="$title">
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-20 text-center">
        <p class="font-heading text-lg font-semibold text-on-surface">{{ $title }}</p>
        <p class="mt-2 max-w-md text-sm text-on-surface-variant">
            {{ $description ?? 'Esta sección se construye en una fase posterior del plan (ver docs/PROJECT_PLAN.md).' }}
        </p>
    </div>
</x-layouts.app>
