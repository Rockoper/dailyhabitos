<div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-lg border border-outline-variant bg-surface-container-lowest p-1 text-sm">
            <button type="button" wire:click="$set('filter', 'active')" class="rounded-md px-3 py-1.5 {{ $filter === 'active' ? 'bg-primary text-on-primary' : 'text-on-surface-variant' }}">
                Activos
            </button>
            <button type="button" wire:click="$set('filter', 'archived')" class="rounded-md px-3 py-1.5 {{ $filter === 'archived' ? 'bg-primary text-on-primary' : 'text-on-surface-variant' }}">
                Archivados
            </button>
            <button type="button" wire:click="$set('filter', 'all')" class="rounded-md px-3 py-1.5 {{ $filter === 'all' ? 'bg-primary text-on-primary' : 'text-on-surface-variant' }}">
                Todos
            </button>
        </div>

        <a href="{{ route('habits.create') }}" wire:navigate>
            <x-button type="button" class="w-auto px-4">
                <x-icon name="plus" class="mr-1.5 h-4 w-4" /> Nuevo hábito
            </x-button>
        </a>
    </div>

    @if ($habits->isEmpty())
        <div class="rounded-xl border border-dashed border-outline-variant p-10 text-center">
            <p class="text-sm text-on-surface-variant">No tienes hábitos {{ $filter === 'archived' ? 'archivados' : ($filter === 'all' ? '' : 'activos') }} todavía.</p>
            <a href="{{ route('habits.create') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                Crear tu primer hábito
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($habits as $habit)
                <div wire:key="habit-{{ $habit->id }}" x-data="{ confirmingDelete: false }" class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        style="background-color: {{ $habit->color }}26; color: {{ $habit->color }}"
                    >
                        <x-icon :name="$habit->icon" class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <a href="{{ route('habits.show', $habit) }}" wire:navigate class="block truncate font-medium text-on-surface hover:underline">
                            {{ $habit->is_private ? 'Hábito privado' : $habit->name }}
                        </a>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-on-surface-variant">
                            <span>{{ $habit->type->label() }}</span>
                            @if ($habit->category)
                                <span>· {{ $habit->category->name }}</span>
                            @endif
                            @if ($habit->is_archived)
                                <span class="rounded-full bg-surface-container-high px-2 py-0.5">Archivado</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <a href="{{ route('habits.edit', $habit) }}" wire:navigate class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Editar">
                            <x-icon name="edit" class="h-4 w-4" />
                        </a>

                        @if ($habit->is_archived)
                            <button type="button" wire:click="unarchive({{ $habit->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Desarchivar">
                                <x-icon name="unarchive" class="h-4 w-4" />
                            </button>
                        @else
                            <button type="button" wire:click="archive({{ $habit->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Archivar">
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
                                <button type="button" wire:click="delete({{ $habit->id }})" class="rounded-lg bg-error px-2 py-1 text-xs font-medium text-on-error">
                                    Confirmar
                                </button>
                                <button type="button" @click="confirmingDelete = false" class="rounded-lg px-2 py-1 text-xs text-on-surface-variant">
                                    Cancelar
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $habits->links() }}
        </div>
    @endif
</div>
