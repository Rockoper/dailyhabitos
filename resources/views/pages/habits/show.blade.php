<x-layouts.app :title="$habit->is_private ? 'Hábito privado' : $habit->name">
    <livewire:habits.habit-detail :habit="$habit" />
</x-layouts.app>
