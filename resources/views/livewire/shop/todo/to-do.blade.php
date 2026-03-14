<div class="flex flex-col h-[85vh] md:h-[calc(100vh-10rem)] bg-gray-900/80 backdrop-blur-xl rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden min-h-[500px] md:min-h-[700px] animate-fade-in-up">

    {{-- HEADER BEREICH --}}
    <div class="bg-gray-950/50 border-b border-gray-800 sticky top-0 z-30 shadow-sm backdrop-blur-md">
        <div class="max-w-7xl mx-auto px-6 py-5">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-6">
                <div class="flex items-center gap-5">
                    <div class="relative group">
                        <div class="absolute inset-0 bg-primary/20 rounded-2xl blur-xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="h-14 w-14 rounded-2xl bg-gray-950 border border-gray-800 flex items-center justify-center p-2 shadow-inner relative z-10">
                            <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain" alt="Funki">
                            <div class="absolute -bottom-1 -right-1 w-3.5 h-3.5 bg-emerald-500 border-2 border-gray-900 rounded-full animate-pulse shadow-[0_0_8px_#10b981]"></div>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-2xl font-serif font-bold text-white tracking-tight">Funkis Zentrale</h1>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-500">Dein Autopilot für deine Todos</p>
                    </div>
                </div>

                <div class="relative w-full md:w-80 group">
                    <input wire:model.live="search" type="text" placeholder="Aufgaben suchen..."
                           class="w-full pl-11 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-bold text-white placeholder:text-gray-600 shadow-inner outline-none">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-600 group-focus-within:text-primary transition-colors">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden min-h-0">

        {{-- SIDEBAR: LISTEN --}}
        <div class="w-full lg:w-72 bg-gray-950/30 border-b lg:border-b-0 lg:border-r border-gray-800 flex flex-row lg:flex-col overflow-x-auto lg:overflow-y-auto custom-scrollbar p-4 gap-3 shrink-0">
            <div class="hidden lg:block text-[9px] font-black text-gray-600 uppercase tracking-[0.3em] px-3 mb-1">Kategorien</div>

            @foreach($lists as $list)
                <div class="relative group/list-item w-full min-w-[180px] lg:min-w-0">
                    <button wire:click="$set('selectedListId', '{{ $list->id }}')"
                        @class([
                            'flex items-center gap-3 p-3.5 rounded-2xl transition-all duration-300 text-left group w-full border',
                            'bg-primary/10 border-primary/40 shadow-[0_0_15px_rgba(197,160,89,0.1)]' => $selectedListId === $list->id,
                            'bg-gray-900/50 border-transparent hover:bg-gray-800 hover:border-gray-700 text-gray-500' => $selectedListId !== $list->id
                        ])>

                        <div @class([
                            'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-500',
                            'bg-primary text-gray-900 shadow-glow' => $selectedListId === $list->id,
                            'bg-gray-950 border border-gray-800 text-gray-600 group-hover:text-primary group-hover:border-primary/50' => $selectedListId !== $list->id
                        ])>
                            <x-dynamic-component :component="'heroicon-o-' . $list->icon" class="w-4.5 h-4.5" />
                        </div>

                        <div class="flex-1 min-w-0 pr-6"> {{-- Padding rechts für den Trash-Button --}}
                            <div class="text-xs font-black truncate {{ $selectedListId === $list->id ? 'text-white' : 'text-gray-400 group-hover:text-gray-200' }} uppercase tracking-wider">
                                {{ $list->name }}
                            </div>
                            @if($list->open_count > 0)
                                <div class="text-[9px] font-bold {{ $selectedListId === $list->id ? 'text-primary/80' : 'text-gray-600' }}">
                                    {{ $list->open_count }} OFFEN
                                </div>
                            @endif
                        </div>
                    </button>

                    {{-- LÖSCHEN BUTTON (Nur bei Hover sichtbar) --}}
                    <button wire:click="deleteList('{{ $list->id }}')"
                            wire:confirm="Möchtest du die Liste '{{ $list->name }}' und alle darin enthaltenen Aufgaben wirklich löschen?"
                            class="absolute right-3 top-1/2 -translate-y-1/2 opacity-0 group-hover/list-item:opacity-100 p-2 text-gray-500 hover:text-red-500 transition-all duration-200 z-20">
                        <x-heroicon-s-trash class="w-4 h-4" />
                    </button>
                </div>
            @endforeach

            <div class="p-1 min-w-[200px] lg:min-w-0 mt-2">
                @if(!$isAddingList)
                    <button wire:click="$set('isAddingList', true)"
                            class="w-full flex items-center gap-3 p-3.5 rounded-2xl border-2 border-dashed border-gray-800 text-gray-600 hover:border-primary/50 hover:text-primary hover:bg-primary/5 transition-all group">
                        <div class="w-9 h-9 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center group-hover:bg-gray-900">
                            <x-heroicon-o-plus class="w-4.5 h-4.5" />
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest">Neue Liste</span>
                    </button>
                @else
                    <div class="bg-gray-900 p-4 rounded-2xl border border-gray-700 shadow-2xl animate-fade-in-up">
                        <input wire:model="newList_name" type="text" placeholder="Name..."
                               class="w-full text-xs font-bold bg-gray-950 border border-gray-800 rounded-xl py-2.5 px-3 focus:ring-2 focus:ring-primary/20 mb-3 text-white outline-none"
                               autofocus>

                        <div class="grid grid-cols-5 gap-1.5 mb-4">
                            @foreach(['bookmark', 'star', 'heart', 'bolt', 'home', 'briefcase', 'shopping-bag', 'trophy', 'sun', 'moon', 'wrench', 'rocket-launch', 'tag', 'flag'] as $icon)
                                <button wire:click="$set('newList_icon', '{{ $icon }}')"
                                    @class([
                                        'w-7 h-7 rounded-lg flex items-center justify-center transition-all border',
                                        'bg-primary text-gray-900 border-primary' => $newList_icon === $icon,
                                        'bg-gray-950 border-gray-800 text-gray-600 hover:text-white hover:border-gray-600' => $newList_icon !== $icon
                                    ])>
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-3.5 h-3.5" />
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="cancelCreateList" class="flex-1 py-2 text-[9px] font-black uppercase text-gray-500 hover:text-white transition-colors">Abbruch</button>
                            <button wire:click="createList" class="flex-1 py-2 text-[9px] font-black uppercase text-gray-900 bg-primary rounded-lg hover:bg-primary-dark shadow-md transition-all">Erstellen</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- MAIN CONTENT: TASKS --}}
        <div class="flex-1 flex flex-col bg-gray-950/20 min-w-0 min-h-0 relative">

            <div class="p-4 md:p-6 border-b border-gray-800 bg-gray-900/40 backdrop-blur-sm z-10 shadow-sm relative">
                <form wire:submit.prevent="createTask" class="relative group max-w-4xl mx-auto">
                    <div class="absolute inset-0 bg-primary/10 rounded-2xl md:rounded-3xl blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <input wire:model="newTask_title" type="text"
                           placeholder="{{ $selectedListId ? 'Was gibt es zu tun?' : 'Wähle links eine Liste...' }}"
                           @disabled(!$selectedListId)
                           class="relative w-full pl-6 pr-20 py-5 md:py-6 md:pl-8 md:pr-40 bg-gray-950 border-2 border-gray-800 rounded-2xl md:rounded-3xl focus:ring-4 focus:ring-primary/20 focus:border-primary transition-all text-lg md:text-xl font-bold text-white placeholder:text-gray-600 shadow-inner outline-none">

                    <button type="submit" @disabled(!$selectedListId)
                    class="absolute right-2 top-2 bottom-2 px-5 md:px-8 bg-primary text-gray-900 rounded-xl md:rounded-2xl flex items-center justify-center hover:bg-white transition-all shadow-lg active:scale-95 disabled:bg-gray-800 disabled:text-gray-600">
                        <x-heroicon-o-plus class="w-6 h-6 md:w-8 md:h-8 stroke-[3]" />
                        <span class="hidden md:inline-block ml-2 font-black uppercase text-sm tracking-widest">Hinzufügen</span>
                    </button>
                </form>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                @if(!$selectedListId)
                    <div class="flex flex-col items-center justify-center h-full text-center p-10 opacity-30">
                        <div class="w-20 h-20 bg-gray-900 rounded-full flex items-center justify-center mb-6 border border-gray-800 shadow-inner">
                            <x-heroicon-o-sparkles class="w-10 h-10 text-primary" />
                        </div>
                        <p class="font-serif font-bold text-gray-400 text-xl">Wähle eine Mission</p>
                        <p class="text-xs text-gray-600 mt-2 uppercase tracking-widest font-black">Funki wartet auf deine Anweisungen</p>
                    </div>
                @else
                    <div class="max-w-4xl mx-auto space-y-3">
                        @forelse($todos as $todo)
                            <div class="group/task" x-data="{ showMenu: false, isAddingSub: false, subTitle: '' }">

                                <div @class([
                                    'relative flex items-start gap-5 p-5 rounded-3xl border transition-all duration-500 group-hover/task:shadow-2xl',
                                    'bg-gray-950/40 border-gray-900 opacity-40 grayscale' => $todo->is_completed,
                                    'shadow-lg hover:border-primary/40' => !$todo->is_completed,
                                    'bg-gray-900 border-gray-800' => !$todo->is_completed && ($todo->priority ?? 'niedrig') === 'niedrig',
                                    'bg-orange-950/20 border-orange-900/50' => !$todo->is_completed && ($todo->priority ?? 'niedrig') === 'mittel',
                                    'bg-red-950/20 border-red-900/50' => !$todo->is_completed && ($todo->priority ?? 'niedrig') === 'hoch',
                                ])>
                                    <button wire:click="toggleComplete('{{ $todo->id }}')"
                                            class="mt-1 flex-shrink-0 w-7 h-7 rounded-xl border-2 flex items-center justify-center transition-all duration-500 {{ $todo->is_completed ? 'bg-emerald-500 border-emerald-500 text-gray-950 shadow-[0_0_15px_#10b981]' : 'border-gray-800 text-transparent hover:border-primary hover:bg-primary/5 bg-gray-950 shadow-inner' }}">
                                        <x-heroicon-m-check class="w-4 h-4 stroke-[3]" />
                                    </button>

                                    <div class="flex-1 min-w-0 pt-0.5" x-data="{ isEditing: false, updatedTitle: '{{ $todo->title }}' }">
                                        <div x-show="!isEditing"
                                             @click.self="isEditing = true; $nextTick(() => $refs.editInput.focus())"
                                            @class(['text-sm font-bold leading-relaxed break-words cursor-text hover:text-primary transition-colors pr-4', 'line-through text-gray-600 italic' => $todo->is_completed, 'text-gray-200' => !$todo->is_completed])>
                                            {!! preg_replace(
                                                  '/((?:https?|ftp|file):\/\/|www\.)[a-z0-9+&@#\/%?=~_|!:,.;\*\-]*[a-z0-9+&@#\/%=~_|]/i',
                                                  '<a href="$0" target="_blank" class="text-primary underline hover:text-white transition-colors" @click.stop>$0</a>',
                                                  e($todo->title)
                                              ) !!}
                                        </div>

                                        <input x-ref="editInput" x-show="isEditing" x-model="updatedTitle"
                                               @blur="isEditing = false; $wire.updateTodoTitle('{{ $todo->id }}', updatedTitle)"
                                               @keydown.enter="$el.blur()"
                                               class="w-full text-sm font-bold bg-gray-950 border border-primary/30 rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-primary/20 -ml-2 -mt-1 text-white shadow-inner"
                                               x-cloak
                                        >
                                    </div>

                                    <div class="relative flex items-center gap-3">
                                        @if(!$todo->is_completed)
                                            <div class="hidden sm:flex items-center">
                                                <select wire:change="updateTodoPriority('{{ $todo->id }}', $event.target.value)"
                                                        class="text-[10px] font-black uppercase tracking-tighter border border-gray-800 bg-gray-950 focus:ring-0 cursor-pointer py-1.5 pl-3 pr-8 rounded-lg transition-all
                                                        {{ ($todo->priority ?? 'niedrig') === 'hoch' ? 'text-red-400 border-red-900/50' : (($todo->priority ?? 'niedrig') === 'mittel' ? 'text-orange-400 border-orange-900/50' : 'text-gray-500 hover:text-gray-300') }}">
                                                    <option value="niedrig" class="bg-gray-900" {{ ($todo->priority ?? 'niedrig') === 'niedrig' ? 'selected' : '' }}>LOW</option>
                                                    <option value="mittel" class="bg-gray-900" {{ ($todo->priority ?? 'niedrig') === 'mittel' ? 'selected' : '' }}>MED</option>
                                                    <option value="hoch" class="bg-gray-900" {{ ($todo->priority ?? 'niedrig') === 'hoch' ? 'selected' : '' }}>HIGH</option>
                                                </select>
                                            </div>
                                        @endif

                                        <button @click="showMenu = !showMenu" class="p-2 text-gray-600 hover:text-white hover:bg-gray-800 rounded-xl transition-all">
                                            <x-heroicon-m-ellipsis-vertical class="w-5 h-5" />
                                        </button>

                                        <div x-show="showMenu" @click.away="showMenu = false" x-cloak
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                             class="absolute right-0 top-12 w-56 bg-gray-900 border border-gray-700 rounded-2xl shadow-[0_15px_30px_rgba(0,0,0,0.6)] z-50 overflow-hidden py-1.5 ring-1 ring-white/5 backdrop-blur-xl">
                                            <button @click="isAddingSub = true; showMenu = false" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-800 hover:text-primary flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-list-bullet class="w-4 h-4 text-primary" /> Schritt dazu
                                            </button>
                                            <div class="h-px bg-gray-800 mx-2 my-1.5"></div>
                                            <button wire:click="deleteTodo('{{ $todo->id }}')" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-trash class="w-4 h-4" /> Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- SUBTASKS --}}
                                <div class="ml-8 pl-6 border-l-2 border-gray-800/50 space-y-1.5 mt-2">
                                    @foreach($todo->subtasks as $sub)
                                        <div class="group/sub flex items-center gap-4 p-2.5 rounded-2xl hover:bg-gray-900/50 transition-all relative">
                                            <button wire:click="toggleComplete('{{ $sub->id }}')"
                                                    class="w-5 h-5 rounded-lg border-2 flex items-center justify-center transition-all duration-300 {{ $sub->is_completed ? 'bg-emerald-500 border-emerald-500 text-gray-950 shadow-[0_0_10px_#10b981]' : 'border-gray-800 text-transparent hover:border-primary bg-gray-950 shadow-inner' }}">
                                                <x-heroicon-m-check class="w-3.5 h-3.5 stroke-[3]" />
                                            </button>

                                            <div class="flex-1 min-w-0" x-data="{ isEditingSub: false, updatedSubTitle: '{{ $sub->title }}' }">
                                                <span x-show="!isEditingSub" @click.self="isEditingSub = true; $nextTick(() => $refs.editSubInput.focus())"
                                                      @class(['flex-1 text-[13px] font-medium truncate cursor-text hover:text-primary transition-colors', 'line-through text-gray-600 italic' => $sub->is_completed, 'text-gray-400' => !$sub->is_completed])>
                                                        {{ $sub->title }}
                                                </span>
                                                <input x-ref="editSubInput" x-show="isEditingSub" x-model="updatedSubTitle"
                                                       @blur="isEditingSub = false; $wire.updateTodoTitle('{{ $sub->id }}', updatedSubTitle)"
                                                       @keydown.enter="$el.blur()"
                                                       class="w-full text-xs font-medium bg-gray-950 border border-primary/30 rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-primary/20 -ml-2 text-white shadow-inner"
                                                       x-cloak
                                                >
                                            </div>

                                            <button wire:click="promoteToTask('{{ $sub->id }}')" class="opacity-0 group-hover/sub:opacity-100 p-1.5 text-gray-600 hover:text-primary transition-all" title="Zu Aufgabe befördern">
                                                <x-heroicon-o-chevron-double-up class="w-4 h-4" />
                                            </button>
                                        </div>
                                    @endforeach

                                    <div x-show="isAddingSub" x-cloak class="pt-2 animate-fade-in">
                                        <div class="flex gap-3 items-center bg-gray-950 border border-primary/30 rounded-2xl p-1.5 shadow-2xl ring-4 ring-primary/5">
                                            <input type="text" x-model="subTitle"
                                                   @keydown.enter="$wire.addSubTask('{{ $todo->id }}', subTitle); subTitle = ''; isAddingSub = false"
                                                   placeholder="Nächster Teilschritt..."
                                                   class="flex-1 bg-transparent border-none px-4 py-2 text-xs font-bold text-white focus:ring-0 placeholder:text-gray-700 shadow-none outline-none"
                                                   autofocus>
                                            <button @click="isAddingSub = false" class="text-gray-600 hover:text-red-500 p-2 rounded-xl hover:bg-red-500/10 transition-colors">
                                                <x-heroicon-m-x-mark class="w-4 h-4 stroke-2"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-24 text-center">
                                <div class="w-20 h-20 bg-gray-900 rounded-full flex items-center justify-center mb-6 shadow-inner border border-gray-800 animate-pulse">
                                    <x-heroicon-o-check-badge class="w-10 h-10 text-gray-700" />
                                </div>
                                <h4 class="text-lg font-serif font-bold text-white">Alles geschafft!</h4>
                                <p class="text-xs text-gray-500 mt-2 uppercase tracking-widest font-black italic">Keine anstehenden Aufgaben in "{{ $lists->find($selectedListId)->name ?? 'dieser Liste' }}".</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- CUSTOM SCROLLBAR --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #1f2937; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #C5A059; }
    </style>

    {{-- AUDIO FEEDBACK --}}
    <audio id="todoDoneSound" src="{{ asset('todo/sounds/todo_done.mp3') }}" preload="auto"></audio>
    
    @script
    <script>
        $wire.on('todo-completed', () => {
            let audio = document.getElementById('todoDoneSound');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(e => console.log('Audio Autoplay prevented'));
            }
        });
    </script>
    @endscript
</div>
