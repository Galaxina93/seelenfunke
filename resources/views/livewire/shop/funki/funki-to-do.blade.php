<div>
    <div class="flex flex-col h-full bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden min-h-[600px] lg:min-h-[700px]">

        {{-- SEARCH & HEADER --}}
        <div class="p-6 border-b border-slate-50 bg-white sticky top-0 z-20">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-serif font-bold text-slate-900">Warteschlange</h3>
                <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em]">Lückenfüller</span>
            </div>

            <div class="relative group">
                <input wire:model.live="search" type="text" placeholder="Aufgaben durchsuchen..."
                       class="w-full pl-10 pr-4 py-3 bg-slate-50 border-none rounded-2xl focus:ring-4 focus:ring-primary/10 transition-all text-sm font-medium">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                </div>
            </div>
        </div>

        <div class="flex flex-1 overflow-hidden">
            {{-- LIST SIDEBAR (Mobil horizontal, Desktop vertikal) --}}
            <div class="w-full lg:w-20 border-b lg:border-b-0 lg:border-r border-slate-50 bg-slate-50/30 flex lg:flex-col overflow-x-auto lg:overflow-y-auto custom-scrollbar p-2 gap-2">
                @foreach($lists as $list)
                    <button wire:click="$set('selectedListId', '{{ $list->id }}')"
                            @class([
                                'flex-shrink-0 w-12 h-12 rounded-2xl flex items-center justify-center transition-all duration-300 relative group',
                                'bg-slate-900 text-white shadow-lg' => $selectedListId === $list->id,
                                'bg-white text-slate-400 hover:text-primary hover:bg-white border border-slate-100' => $selectedListId !== $list->id
                            ])
                            title="{{ $list->name }}">
                        <x-dynamic-component :component="'heroicon-o-' . $list->icon" class="w-6 h-6" />
                        @if($list->open_count > 0)
                            <span class="absolute -top-1 -right-1 w-4 h-4 bg-primary text-white text-[8px] font-black rounded-full flex items-center justify-center border-2 border-white">
                            {{ $list->open_count }}
                        </span>
                        @endif
                    </button>
                @endforeach

                <button @click="$wire.set('isAddingList', true)" class="flex-shrink-0 w-12 h-12 rounded-2xl border-2 border-dashed border-slate-200 text-slate-300 flex items-center justify-center hover:border-primary hover:text-primary transition-all">
                    <x-heroicon-o-plus class="w-6 h-6" />
                </button>
            </div>

            {{-- MAIN CONTENT --}}
            <div class="flex-1 flex flex-col bg-white overflow-hidden relative">

                {{-- Task Input --}}
                <div class="p-6 border-b border-slate-50">
                    <form wire:submit.prevent="createTask" class="relative">
                        <input wire:model="newTask_title" type="text" placeholder="Neue Aufgabe in dieser Liste..."
                               class="w-full pl-4 pr-12 py-4 bg-slate-50 border-none rounded-2xl focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold placeholder:text-slate-400">
                        <button type="submit" class="absolute right-2 top-2 w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-primary transition-colors shadow-sm">
                            <x-heroicon-o-plus-small class="w-6 h-6" />
                        </button>
                    </form>
                </div>

                {{-- Task List --}}
                <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                    @forelse($todos as $todo)
                        <div class="space-y-2 group/task" x-data="{ showMenu: false, isAddingSub: false, subTitle: '' }">
                            {{-- Task Card --}}
                            <div @class([
                            'flex items-center gap-4 p-4 rounded-2xl border transition-all duration-300',
                            'bg-slate-50 border-slate-100 opacity-60' => $todo->is_completed,
                            'bg-white border-slate-100 shadow-sm hover:shadow-md' => !$todo->is_completed
                        ])>
                                <button wire:click="toggleComplete('{{ $todo->id }}')"
                                        class="w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all {{ $todo->is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-slate-200 text-transparent hover:border-primary' }}">
                                    <x-heroicon-m-check class="w-4 h-4" />
                                </button>

                                <span @class(['flex-1 text-sm font-bold', 'line-through text-slate-400' => $todo->is_completed, 'text-slate-700' => !$todo->is_completed])>
                                {{ $todo->title }}
                            </span>

                                <div class="relative">
                                    <button @click="showMenu = !showMenu" class="p-2 text-slate-300 hover:text-slate-600 transition-colors">
                                        <x-heroicon-m-ellipsis-horizontal class="w-5 h-5" />
                                    </button>
                                    <div x-show="showMenu" @click.away="showMenu = false" x-cloak
                                         class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-xl border border-slate-100 z-30 overflow-hidden">
                                        <button @click="isAddingSub = true; showMenu = false" class="w-full text-left px-4 py-3 text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-2">
                                            <x-heroicon-o-plus class="w-4 h-4" /> Schritt hinzufügen
                                        </button>
                                        <button wire:click="deleteTodo('{{ $todo->id }}')" class="w-full text-left px-4 py-3 text-xs font-bold text-red-500 hover:bg-red-50 flex items-center gap-2">
                                            <x-heroicon-o-trash class="w-4 h-4" /> Löschen
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- SUBTASKS --}}
                            <div class="ml-10 space-y-2">
                                @foreach($todo->subtasks as $sub)
                                    <div class="flex items-center gap-3 p-2 group/sub" x-data="{ showSubMenu: false }">
                                        <button wire:click="toggleComplete('{{ $sub->id }}')"
                                                class="w-5 h-5 rounded-md border flex items-center justify-center transition-all {{ $sub->is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-slate-200 text-transparent hover:border-primary' }}">
                                            <x-heroicon-m-check class="w-3 h-3" />
                                        </button>
                                        <span @class(['flex-1 text-xs font-medium', 'line-through text-slate-400' => $sub->is_completed, 'text-slate-600' => !$sub->is_completed])>
                                        {{ $sub->title }}
                                    </span>

                                        <div class="relative opacity-0 group-hover/sub:opacity-100 transition-opacity">
                                            <button @click="showSubMenu = !showSubMenu" class="text-slate-300 hover:text-slate-600">
                                                <x-heroicon-m-ellipsis-horizontal class="w-4 h-4" />
                                            </button>
                                            <div x-show="showSubMenu" @click.away="showSubMenu = false" x-cloak
                                                 class="absolute right-0 mt-1 w-40 bg-white rounded-xl shadow-xl border border-slate-100 z-30">
                                                <button wire:click="promoteToTask('{{ $sub->id }}')" class="w-full text-left px-3 py-2 text-[10px] font-black uppercase text-primary hover:bg-slate-50">
                                                    Aufwerten
                                                </button>
                                                <button wire:click="deleteTodo('{{ $sub->id }}')" class="w-full text-left px-3 py-2 text-[10px] font-black uppercase text-red-500 hover:bg-red-50">
                                                    Entfernen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Add Subtask Input --}}
                                <div x-show="isAddingSub" x-cloak class="pt-2 animate-fade-in">
                                    <div class="flex gap-2">
                                        <input type="text" x-model="subTitle" @keydown.enter="$wire.addSubTask('{{ $todo->id }}', subTitle); subTitle = ''; isAddingSub = false"
                                               placeholder="Name des Schritts..." class="flex-1 bg-slate-50 border-none rounded-xl px-3 py-2 text-xs font-bold">
                                        <button @click="isAddingSub = false" class="text-slate-400 p-2"><x-heroicon-o-x-mark class="w-4 h-4"/></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center py-20 text-slate-300">
                            <x-heroicon-o-check-badge class="w-12 h-12 opacity-20 mb-2" />
                            <p class="text-sm font-serif italic text-slate-400">Alles erledigt für den Moment.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- MODAL: NEUE LISTE --}}
        @if($isAddingList)
            <div class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in">
                <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-sm overflow-hidden transform animate-fade-in-up">
                    <div class="p-8">
                        <h4 class="text-xl font-serif font-bold text-slate-900 mb-6 text-center">Neue Liste</h4>
                        <div class="space-y-4">
                            <input wire:model="newList_name" type="text" placeholder="Name der Liste..."
                                   class="w-full bg-slate-50 border-none rounded-2xl px-5 py-4 focus:ring-4 focus:ring-primary/10 font-bold">

                            <div class="grid grid-cols-4 gap-2">
                                @foreach(['bookmark', 'shopping-cart', 'briefcase', 'heart', 'star', 'rocket-launch', 'home', 'wrench'] as $icon)
                                    <button wire:click="$set('newList_icon', '{{ $icon }}')"
                                        @class([
                                            'w-full aspect-square rounded-xl flex items-center justify-center border-2 transition-all',
                                            'border-primary bg-primary/5 text-primary' => $newList_icon === $icon,
                                            'border-slate-100 text-slate-300 hover:border-slate-200' => $newList_icon !== $icon
                                        ])>
                                        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-5 h-5" />
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="mt-8 flex gap-3">
                            <button @click="$wire.set('isAddingList', false)" class="flex-1 py-4 text-xs font-black uppercase tracking-widest text-slate-400">Abbrechen</button>
                            <button wire:click="createList" class="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-black uppercase tracking-widest text-xs shadow-lg shadow-slate-200 hover:bg-primary transition-all">Erstellen</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
