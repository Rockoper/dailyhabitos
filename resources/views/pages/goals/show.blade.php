<x-layouts.app :title="$goal->is_private ? 'Objetivo privado' : $goal->name">
    <livewire:goals.goal-detail :goal="$goal" />
</x-layouts.app>
