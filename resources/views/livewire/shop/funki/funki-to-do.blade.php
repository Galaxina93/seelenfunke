<div class="flex flex-col h-full bg-white rounded-[2.5rem] shadow-sm border border-slate-100 overflow-hidden min-h-[600px]">

    {{-- HEADER & SUCHE (Immer oben) --}}
    <div class="p-6 border-b border-slate-100 bg-white z-20 shrink-0">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-xl font-serif font-bold text-slate-900 leading-none">Warteschlange</h3>
                <span class="text-[10px] font-black text-primary uppercase tracking-[0.2em]">Lückenfüller</span>
            </div>
            {{-- Kleiner Counter gesamt --}}
            <div class="bg-slate-100 text-slate-500 text-xs font-bold px-3 py-1 rounded-full">
                {{ $todos->where('is_completed', false)->count() }} Offen
            </div>
        </div>

        <div class="relative group">
            <input wire:model.live="search" type="text" placeholder="Aufgaben suchen..."
                   class="w-full pl-10 pr-4 py-3 bg-slate-50 border-none rounded-xl focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all text-sm font-medium placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <x-heroicon-o-magnifying-glass class="w-4 h-4" />
            </div>
        </div>
    </div>

    {{-- ZWEISPALTIGES LAYOUT (Ab Desktop) --}}
    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">

        {{-- 1. SIDEBAR: LISTEN (Mobil: Oben horizontal / Desktop: Links vertikal) --}}
        <div class="w-full lg:w-72 bg-slate-50/50 border-b lg:border-b-0 lg:border-r border-slate-100 flex flex-row lg:flex-col overflow-x-auto lg:overflow-y-auto custom-scrollbar p-3 gap-2 shrink-0">

            {{-- LISTE DER VORHANDENEN LISTEN --}}
            @foreach($lists as $list)
                <button wire:click="$set('selectedListId', '{{ $list->id }}')"
                    @class([
                        'flex items-center gap-3 p-3 rounded-xl transition-all duration-200 text-left group w-full relative min-w-[160px] lg:min-w-0',
                        'bg-white shadow-md ring-1 ring-black/5' => $selectedListId === $list->id,
                        'hover:bg-white/60 hover:shadow-sm text-slate-500' => $selectedListId !== $list->id
                    ])>

                    {{-- Icon Box --}}
                    <div @class([
                        'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors',
                        'bg-slate-900 text-white' => $selectedListId === $list->id,
                        'bg-white border border-slate-200 text-slate-400 group-hover:border-primary/30 group-hover:text-primary' => $selectedListId !== $list->id
                    ])>
                        <x-dynamic-component :component="'heroicon-o-' . $list->icon" class="w-4 h-4" />
                    </div>

                    {{-- Name & Count --}}
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold truncate {{ $selectedListId === $list->id ? 'text-slate-900' : 'text-slate-600' }}">
                            {{ $list->name }}
                        </div>
                        @if($list->open_count > 0)
                            <div class="text-[9px] font-medium text-slate-400">
                                {{ $list->open_count }} Aufgaben
                            </div>
                        @endif
                    </div>

                    {{-- Active Indicator (Desktop only visible mainly) --}}
                    @if($selectedListId === $list->id)
                        <div class="absolute right-2 w-1.5 h-1.5 rounded-full bg-primary"></div>
                    @endif
                </button>
            @endforeach

            {{-- TRENNLINIE --}}
            <div class="hidden lg:block h-px w-full bg-slate-200/50 my-2"></div>

            {{-- 2. NEUE LISTE ERSTELLEN (INLINE) --}}
            <div class="p-1 min-w-[200px] lg:min-w-0">
                @if(!$isAddingList)
                    <button wire:click="$set('isAddingList', true)"
                            class="w-full flex items-center gap-3 p-3 rounded-xl border-2 border-dashed border-slate-200 text-slate-400 hover:border-primary hover:text-primary hover:bg-primary/5 transition-all group">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center group-hover:bg-white">
                            <x-heroicon-o-plus class="w-4 h-4" />
                        </div>
                        <span class="text-xs font-bold uppercase tracking-wide">Neue Liste</span>
                    </button>
                @else
                    {{-- Inline Formular --}}
                    <div class="bg-white p-3 rounded-2xl shadow-lg border border-slate-100 animate-fade-in-up">
                        <input wire:model="newList_name" type="text" placeholder="Listen-Name..."
                               class="w-full text-xs font-bold bg-slate-50 border-none rounded-lg py-2 px-3 focus:ring-2 focus:ring-primary/20 mb-3 text-slate-900"
                               autofocus>

                        {{-- Icon Grid --}}
                        <div class="grid grid-cols-5 gap-1 mb-3">
                            @foreach(['bookmark', 'star', 'heart', 'bolt', 'home', 'briefcase', 'shopping-bag', 'trophy', 'sun', 'moon', 'computer-desktop', 'wrench', 'rocket-launch', 'tag', 'flag'] as $icon)
                                <button wire:click="$set('newList_icon', '{{ $icon }}')"
                                    @class([
                                        'w-6 h-6 rounded flex items-center justify-center transition-all',
                                        'bg-slate-900 text-white shadow-sm' => $newList_icon === $icon,
                                        'text-slate-300 hover:bg-slate-100 hover:text-slate-500' => $newList_icon !== $icon
                                    ])>
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-3.5 h-3.5" />
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="cancelCreateList" class="flex-1 py-1.5 text-[10px] font-bold text-slate-500 bg-slate-100 rounded-lg hover:bg-slate-200">Abbr.</button>
                            <button wire:click="createList" class="flex-1 py-1.5 text-[10px] font-bold text-white bg-primary rounded-lg hover:bg-primary-dark shadow-md">OK</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- 3. MAIN CONTENT: TASKS --}}
        <div class="flex-1 flex flex-col bg-white min-w-0 relative">

            {{-- Input Area --}}
            <div class="p-6 border-b border-slate-50 bg-white/80 backdrop-blur-sm z-10">
                <form wire:submit.prevent="createTask" class="relative group">
                    <div class="absolute inset-0 bg-primary/5 rounded-2xl transform scale-95 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-500"></div>
                    <input wire:model="newTask_title" type="text"
                           placeholder="{{ $selectedListId ? 'Neue Aufgabe hinzufügen...' : 'Erstelle zuerst eine Liste...' }}"
                           @disabled(!$selectedListId)
                           class="relative w-full pl-5 pr-14 py-4 bg-slate-50 border-none rounded-2xl focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold placeholder:text-slate-400 focus:bg-white disabled:opacity-50 disabled:cursor-not-allowed">

                    <button type="submit" @disabled(!$selectedListId)
                    class="absolute right-2 top-2 w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center hover:bg-primary transition-colors shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5 transform duration-200 disabled:bg-slate-200 disabled:shadow-none">
                        <x-heroicon-o-plus-small class="w-6 h-6" />
                    </button>
                </form>
            </div>

            {{-- Tasks List Scrollable --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-3 custom-scrollbar">
                @if(!$selectedListId)
                    <div class="flex flex-col items-center justify-center h-full text-center p-10 opacity-50">
                        <x-heroicon-o-arrow-left class="w-10 h-10 text-slate-300 mb-4 hidden lg:block" />
                        <x-heroicon-o-arrow-up class="w-10 h-10 text-slate-300 mb-4 lg:hidden" />
                        <p class="font-serif font-bold text-slate-500">Wähle oder erstelle eine Liste</p>
                    </div>
                @else
                    @forelse($todos as $todo)
                        <div class="group/task" x-data="{ showMenu: false, isAddingSub: false, subTitle: '' }">

                            {{-- Hauptaufgabe Karte --}}
                            <div @class([
                                'relative flex items-start gap-4 p-4 rounded-2xl border transition-all duration-300 group-hover/task:shadow-md',
                                'bg-slate-50/50 border-slate-100 opacity-60' => $todo->is_completed,
                                'bg-white border-slate-100 shadow-sm hover:border-primary/20' => !$todo->is_completed
                            ])>
                                {{-- Checkbox --}}
                                <button wire:click="toggleComplete('{{ $todo->id }}')"
                                        class="mt-0.5 flex-shrink-0 w-6 h-6 rounded-lg border-2 flex items-center justify-center transition-all duration-300 {{ $todo->is_completed ? 'bg-green-500 border-green-500 text-white rotate-0' : 'border-slate-200 text-transparent hover:border-primary hover:rotate-6 bg-white' }}">
                                    <x-heroicon-m-check class="w-3.5 h-3.5" />
                                </button>

                                {{-- Text --}}
                                <div class="flex-1 min-w-0 pt-0.5">
                                    <p @class(['text-sm font-bold leading-snug break-words', 'line-through text-slate-400' => $todo->is_completed, 'text-slate-700' => !$todo->is_completed])>
                                        {{ $todo->title }}
                                    </p>

                                    {{-- Subtasks Counter if collapsed (optional) --}}
                                    @if($todo->subtasks->count() > 0 && $todo->is_completed)
                                        <div class="text-[10px] text-slate-400 mt-1 font-medium">{{ $todo->subtasks->count() }} Unterschritte erledigt</div>
                                    @endif
                                </div>

                                {{-- Context Menu --}}
                                <div class="relative">
                                    <button @click="showMenu = !showMenu" class="p-1.5 text-slate-300 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                                        <x-heroicon-m-ellipsis-vertical class="w-5 h-5" />
                                    </button>

                                    <div x-show="showMenu" @click.away="showMenu = false" x-cloak
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         class="absolute right-0 top-8 w-48 bg-white rounded-xl shadow-xl border border-slate-100 z-30 overflow-hidden py-1 ring-1 ring-black/5">
                                        <button @click="isAddingSub = true; showMenu = false" class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-2">
                                            <x-heroicon-o-list-bullet class="w-4 h-4 text-primary" /> Schritt hinzufügen
                                        </button>
                                        <div class="h-px bg-slate-50 my-1"></div>
                                        <button wire:click="deleteTodo('{{ $todo->id }}')" class="w-full text-left px-4 py-2.5 text-xs font-bold text-red-500 hover:bg-red-50 flex items-center gap-2">
                                            <x-heroicon-o-trash class="w-4 h-4" /> Löschen
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Unterschritte --}}
                            @if(!$todo->is_completed || $todo->subtasks->where('is_completed', false)->count() > 0)
                                <div class="ml-7 pl-4 border-l-2 border-slate-100 space-y-1 mt-2">
                                    @foreach($todo->subtasks as $sub)
                                        <div class="group/sub flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors relative">
                                            <button wire:click="toggleComplete('{{ $sub->id }}')"
                                                    class="w-4 h-4 rounded border flex items-center justify-center transition-all {{ $sub->is_completed ? 'bg-green-500 border-green-500 text-white' : 'border-slate-300 text-transparent hover:border-primary bg-white' }}">
                                                <x-heroicon-m-check class="w-3 h-3" />
                                            </button>

                                            <span @class(['flex-1 text-xs font-medium truncate', 'line-through text-slate-400' => $sub->is_completed, 'text-slate-600' => !$sub->is_completed])>
                                                {{ $sub->title }}
                                            </span>

                                            {{-- Sub Menu Hover --}}
                                            <div class="flex opacity-0 group-hover/sub:opacity-100 transition-opacity" x-data="{ openSub: false }">
                                                <button @click="openSub = !openSub" class="text-slate-300 hover:text-slate-600 p-0.5 rounded">
                                                    <x-heroicon-m-ellipsis-horizontal class="w-4 h-4" />
                                                </button>
                                                <div x-show="openSub" @click.away="openSub = false" x-cloak class="absolute right-0 top-6 w-32 bg-white rounded-lg shadow-lg border border-slate-100 z-40 py-1">
                                                    <button wire:click="promoteToTask('{{ $sub->id }}')" class="w-full text-left px-3 py-1.5 text-[10px] font-bold text-primary hover:bg-slate-50">Zu Aufgabe</button>
                                                    <button wire:click="deleteTodo('{{ $sub->id }}')" class="w-full text-left px-3 py-1.5 text-[10px] font-bold text-red-500 hover:bg-red-50">Löschen</button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Subtask Input --}}
                                    <div x-show="isAddingSub" x-cloak class="pt-1 animate-fade-in">
                                        <div class="flex gap-2 items-center bg-white border border-primary/30 rounded-xl p-1 shadow-sm ring-2 ring-primary/5">
                                            <input type="text" x-model="subTitle"
                                                   @keydown.enter="$wire.addSubTask('{{ $todo->id }}', subTitle); subTitle = ''; isAddingSub = false"
                                                   placeholder="Nächster Schritt..."
                                                   class="flex-1 bg-transparent border-none px-3 py-1 text-xs font-bold focus:ring-0 placeholder:text-slate-300"
                                                   autofocus>
                                            <button @click="isAddingSub = false" class="text-slate-400 hover:text-red-500 p-1.5 rounded-lg hover:bg-slate-50">
                                                <x-heroicon-m-x-mark class="w-3.5 h-3.5"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="flex flex-col items-center justify-center h-64 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4 animate-bounce-slow">
                                <x-heroicon-o-check-badge class="w-8 h-8 text-slate-300" />
                            </div>
                            <h4 class="text-sm font-serif font-bold text-slate-800">Alles sauber!</h4>
                            <p class="text-xs text-slate-400 mt-1">Keine offenen Aufgaben in "{{ $lists->find($selectedListId)->name ?? 'dieser Liste' }}".</p>
                        </div>
                    @endforelse
                @endif
            </div>
        </div>
    </div>
</div>
