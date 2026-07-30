<?php

namespace App\Livewire\Habits;

use App\Models\Habit;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class HabitIndex extends Component
{
    use WithPagination;

    public string $filter = 'active';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function archive(Habit $habit): void
    {
        $this->authorize('archive', $habit);
        $habit->update(['is_archived' => true, 'archived_at' => now()]);
    }

    public function unarchive(Habit $habit): void
    {
        $this->authorize('archive', $habit);
        $habit->update(['is_archived' => false, 'archived_at' => null]);
    }

    public function delete(Habit $habit): void
    {
        $this->authorize('delete', $habit);
        $habit->delete();
    }

    public function render()
    {
        $query = Auth::user()->habits()->with('category')->orderBy('position')->orderBy('name');

        match ($this->filter) {
            'archived' => $query->archived(),
            'all' => null,
            default => $query->active(),
        };

        return view('livewire.habits.habit-index', [
            'habits' => $query->paginate(12),
        ]);
    }
}
