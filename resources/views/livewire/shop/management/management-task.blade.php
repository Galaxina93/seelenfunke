<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="flex flex-col h-[95vh] md:h-[calc(100vh-10rem)] bg-gray-900/80 backdrop-blur-xl rounded-2xl md:rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden min-h-[500px] md:min-h-[700px] animate-fade-in-up">

    {{-- HEADER BEREICH --}}
    <div class="bg-gray-950/50 border-b border-gray-800 sticky top-0 z-30 shadow-sm backdrop-blur-md shrink-0">
        <div class="max-w-7xl mx-auto px-4 md:px-6 py-3 md:py-5">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4 md:gap-6">
                <div class="flex items-center gap-2 md:gap-4 w-full">
                    <input wire:model.live="search" type="text" placeholder="Aufgaben suchen..."
                           class="w-full pl-4 md:pl-6 pr-4 py-2.5 md:py-3 bg-gray-950 border border-gray-800 rounded-xl md:rounded-2xl focus:ring-2 focus:ring-[var(--theme-color-20)] focus:border-[var(--theme-color)] transition-all text-xs md:text-sm font-bold text-white placeholder:text-gray-600 shadow-inner outline-none">

                    <button wire:click="toggleArchiveMode" class="flex-shrink-0 px-3 md:px-5 py-2.5 md:py-3 rounded-xl md:rounded-2xl border-2 {{ $showArchive ? 'border-amber-500 text-amber-500 bg-amber-500/10' : 'border-gray-800 text-gray-500 hover:text-white hover:border-[var(--theme-color-40)]' }} transition-all flex items-center gap-2 md:gap-3 font-black uppercase text-[9px] md:text-[10px] tracking-widest shadow-inner">
                        <x-heroicon-m-archive-box class="w-4 h-4 md:w-5 md:h-5" />
                        <span class="hidden sm:inline">{{ $showArchive ? 'Archiv schließen' : 'Archiv öffnen' }}</span>
                        <span class="sm:hidden">{{ $showArchive ? 'Archiv zu' : 'Archiv auf' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden min-h-0">

        {{-- SIDEBAR: LISTEN --}}
        <div class="w-full lg:w-72 max-h-[25vh] lg:max-h-none bg-gray-950/30 border-b lg:border-b-0 lg:border-r border-gray-800 flex flex-col overflow-y-auto custom-scrollbar p-3 md:p-4 gap-2 md:gap-3 shrink-0"
             id="category-sortable-list"
             x-data="{
                initListSortable() {
                    if (typeof Sortable === 'undefined') {
                        let script = document.createElement('script');
                        script.src = '{{ asset('vendor/sortablejs/Sortable.min.js') }}';
                        script.onload = () => this.bindSortables();
                        document.head.appendChild(script);
                    } else {
                        this.bindSortables();
                    }
                },
                bindSortables() {
                    Sortable.create(document.getElementById('category-sortable-list'), {
                        animation: 150,
                        handle: '.category-drag-handle',
                        ghostClass: 'opacity-40',
                        draggable: '.category-item',
                        onEnd: (evt) => {
                            let ids = Array.from(document.getElementById('category-sortable-list').querySelectorAll('.category-item'))
                                .map(child => child.getAttribute('data-list-id'))
                                .filter(Boolean);
                            $wire.updateListOrder(ids);
                        }
                    });
                }
             }"
             x-init="initListSortable()">
            <div class="text-[9px] font-black text-gray-600 uppercase tracking-[0.3em] px-3 mb-1 shrink-0">Deine Listen</div>

            @foreach($lists as $list)
                <div class="category-item shrink-0 relative group/list-item w-full" data-list-id="{{ $list->id }}" wire:key="list-{{ $list->id }}" x-data="{ showListMenu: false, isEditingName: false, updatedListName: '{{ addslashes($list->name) }}' }">
                    <div @contextmenu.prevent="showListMenu = true"
                         @class([
                            'flex items-center gap-2 p-2.5 md:p-3.5 rounded-xl md:rounded-2xl transition-all duration-300 w-full border',
                            'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] shadow-[0_0_15px_var(--theme-color-10)]' => $selectedListId === $list->id,
                            'bg-gray-900/50 border-transparent hover:bg-gray-800 hover:border-gray-700 text-gray-500' => $selectedListId !== $list->id
                        ])>

                        <div class="category-drag-handle cursor-grab active:cursor-grabbing opacity-0 group-hover/list-item:opacity-40 hover:!opacity-100 p-1 -ml-2 text-gray-500 transition-opacity">
                            <x-heroicon-m-bars-3 class="w-4 h-4" />
                        </div>

                        <div class="flex flex-1 items-center gap-3 text-left w-full pl-2">
                            <div @class([
                                'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 transition-all duration-500',
                                'bg-[var(--theme-color)] text-gray-900 shadow-glow' => $selectedListId === $list->id,
                                'bg-gray-950 border border-gray-800 text-gray-600 group-hover:text-[var(--theme-color)] group-hover:border-[var(--theme-color-50)]' => $selectedListId !== $list->id
                            ])>
                                <x-dynamic-component :component="'heroicon-o-' . $list->icon" class="w-4.5 h-4.5" />
                            </div>

                            <div class="flex-1 min-w-0 text-left">
                                <div x-show="!isEditingName" @click="$wire.set('selectedListId', '{{ $list->id }}')" class="text-xs font-black truncate {{ $list->is_archived ? 'text-amber-500/70 italic' : ($selectedListId === $list->id ? 'text-white' : 'text-gray-400 group-hover:text-gray-200') }} uppercase tracking-wider cursor-pointer">
                                    {{ $list->name }}
                                </div>
                                <input x-ref="listNameInput" x-show="isEditingName" x-model="updatedListName"
                                       @click.stop
                                       @blur="isEditingName = false; $wire.renameList('{{ $list->id }}', updatedListName)"
                                       @keydown.enter="$el.blur()"
                                       class="w-full text-xs font-bold bg-gray-950 border border-gray-800 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-20)] text-white shadow-inner outline-none"
                                       x-cloak>
                                @if($list->open_count > 0 && !$list->is_archived)
                                    <div class="text-[9px] font-bold {{ $selectedListId === $list->id ? 'text-[var(--theme-color)]/80' : 'text-gray-600' }}">
                                        {{ $list->open_count }} OFFEN
                                    </div>
                                @endif

                                @if($list->is_archived)
                                    <div class="text-[9px] font-bold text-amber-500 uppercase">Archiviert</div>
                                @endif
                            </div>

                            <button @click.stop="showListMenu = true" class="md:hidden p-1.5 text-gray-600 hover:text-white transition-opacity shrink-0">
                                <x-heroicon-m-ellipsis-vertical class="w-5 h-5" />
                            </button>
                        </div>
                    </div>

                    {{-- CONTEXT MENU / RECHTSKLICK MENU --}}
                    <div x-show="showListMenu" @click.away="showListMenu = false" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="absolute left-0 lg:left-12 top-full lg:top-3/4 mt-2 w-full lg:w-48 bg-gray-900 border border-gray-700 rounded-2xl shadow-[0_15px_30px_rgba(0,0,0,0.6)] z-[60] overflow-hidden py-1.5 ring-1 ring-white/5 backdrop-blur-xl">

                         <div class="px-4 py-2 border-b border-gray-800 mb-1">
                             <p class="text-[9px] font-black uppercase tracking-widest text-gray-500 truncate">{{ $list->name }}</p>
                         </div>
                        <button @click="isEditingName = true; showListMenu = false; $nextTick(() => $refs.listNameInput.focus())" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-300 hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-3 transition-colors">
                            <x-heroicon-s-pencil class="w-4 h-4 text-[var(--theme-color)]" /> Umbenennen
                        </button>
                        <button wire:click="toggleArchiveList('{{ $list->id }}')" @click="showListMenu = false" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-amber-500 hover:bg-gray-800 flex items-center gap-3 transition-colors">
                            <x-heroicon-s-archive-box class="w-4 h-4 text-amber-500" /> {{ $list->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                        </button>
                        <button wire:click="deleteList('{{ $list->id }}')" wire:confirm="Wirklich löschen?" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 flex items-center gap-3 transition-colors">
                            <x-heroicon-s-trash class="w-4 h-4 text-red-500" /> Löschen
                        </button>
                    </div>
                </div>
            @endforeach

            <div class="p-1 w-full mt-2 shrink-0">
                @if(!$isAddingList)
                    <button wire:click="$set('isAddingList', true)"
                            class="w-full flex items-center gap-3 p-3.5 rounded-2xl border-2 border-dashed border-gray-800 text-gray-600 hover:border-[var(--theme-color-50)] hover:text-[var(--theme-color)] hover:bg-[var(--theme-color-5)] transition-all group">
                        <div class="w-9 h-9 rounded-xl bg-gray-950 border border-gray-800 flex items-center justify-center group-hover:bg-gray-900">
                            <x-heroicon-o-plus class="w-4.5 h-4.5" />
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest">Neue Liste</span>
                    </button>
                @else
                    <div class="bg-gray-900 p-4 rounded-2xl border border-gray-700 shadow-2xl animate-fade-in-up">
                        <input wire:model="newList_name" type="text" placeholder="Name..."
                               class="w-full text-xs font-bold bg-gray-950 border border-gray-800 rounded-xl py-2.5 px-3 focus:ring-2 focus:ring-[var(--theme-color-20)] mb-3 text-white outline-none"
                               autofocus>

                        <div class="grid grid-cols-5 gap-1.5 mb-4">
                            @foreach(['bookmark', 'star', 'heart', 'bolt', 'home', 'briefcase', 'shopping-bag', 'trophy', 'sun', 'moon', 'wrench', 'rocket-launch', 'tag', 'flag'] as $icon)
                                <button wire:click="$set('newList_icon', '{{ $icon }}')"
                                    @class([
                                        'w-7 h-7 rounded-lg flex items-center justify-center transition-all border',
                                        'bg-[var(--theme-color)] text-gray-900 border-[var(--theme-color)]' => $newList_icon === $icon,
                                        'bg-gray-950 border-gray-800 text-gray-600 hover:text-white hover:border-gray-600' => $newList_icon !== $icon
                                    ])>
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-3.5 h-3.5" />
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="cancelCreateList" class="flex-1 py-2 text-[9px] font-black uppercase text-gray-500 hover:text-white transition-colors">Abbruch</button>
                            <button wire:click="createList" class="flex-1 py-2 text-[9px] font-black uppercase text-gray-900 bg-[var(--theme-color)] rounded-lg hover:brightness-110 shadow-md transition-all">Erstellen</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- MAIN CONTENT: TASKS --}}
        <div class="flex-1 flex flex-col bg-gray-950/20 min-w-0 min-h-0 relative">

            <div class="p-3 md:p-6 border-b border-gray-800 bg-gray-900/40 backdrop-blur-sm z-10 shadow-sm relative shrink-0">
                <form wire:submit.prevent="createTask" class="relative group max-w-4xl mx-auto">
                    <div class="absolute inset-0 bg-[var(--theme-color-10)] rounded-xl md:rounded-3xl blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    <input wire:model="newTask_title" type="text"
                           placeholder="{{ $selectedListId ? 'Was gibt es zu tun?' : 'Wähle links eine Liste...' }}"
                           @disabled(!$selectedListId)
                           class="relative w-full pl-4 md:pl-8 pr-4 md:pr-8 py-3.5 md:py-6 bg-gray-950 border-2 border-gray-800 rounded-xl md:rounded-3xl focus:ring-4 focus:ring-[var(--theme-color-20)] focus:border-[var(--theme-color)] transition-all text-base md:text-xl font-bold text-white placeholder:text-gray-600 shadow-inner outline-none">
                </form>
            </div>

            <div class="flex-1 overflow-y-auto p-3 md:p-6 space-y-3 md:space-y-4 custom-scrollbar">
                @if(!$selectedListId)
                    <div class="flex flex-col items-center justify-center h-full text-center p-10 opacity-30">
                        <div class="w-20 h-20 bg-gray-900 rounded-full flex items-center justify-center mb-6 border border-gray-800 shadow-inner">
                            <x-heroicon-o-sparkles class="w-10 h-10 text-[var(--theme-color)]" />
                        </div>
                        <p class="font-serif font-bold text-gray-400 text-xl">Wähle eine Mission</p>
                        <p class="text-xs text-gray-600 mt-2 uppercase tracking-widest font-black">Warte auf deine Anweisungen</p>
                    </div>
                @else
                    <div class="max-w-4xl mx-auto space-y-3" id="task-sortable-list"
                         x-data="{
                            initSortable() {
                                if (typeof Sortable === 'undefined') {
                                    let script = document.createElement('script');
                                    script.src = '{{ asset('vendor/sortablejs/Sortable.min.js') }}';
                                    script.onload = () => this.bindSortables();
                                    document.head.appendChild(script);
                                } else {
                                    this.bindSortables();
                                }
                            },
                            bindSortables() {
                                Sortable.create(document.getElementById('task-sortable-list'), {
                                    animation: 150,
                                    handle: '.drag-handle',
                                    ghostClass: 'opacity-40',
                                    onEnd: (evt) => {
                                        let ids = Array.from(document.getElementById('task-sortable-list').children)
                                            .map(child => child.getAttribute('data-task-id'))
                                            .filter(Boolean);
                                        $wire.updateTaskOrder(ids);
                                    }
                                });
                            }
                         }"
                         x-init="initSortable()">
                        @forelse($tasks as $task)
                            <div class="group/task transition-all" :class="showMenu ? 'relative z-50' : 'relative z-0'" data-task-id="{{ $task->id }}" x-data="{ showMenu: false, isAddingSub: false, subTitle: '', isEditing: false, updatedTitle: '{{ addslashes($task->title) }}' }" wire:key="task-{{ $task->id }}">

                                <div @class([
                                    'relative flex items-center justify-between p-3.5 md:p-5 rounded-2xl md:rounded-3xl border transition-all duration-500 group-hover/task:shadow-2xl',
                                    'bg-gray-950/40 border-gray-900 opacity-40 grayscale' => $task->is_completed,
                                    'shadow-lg hover:border-[var(--theme-color-40)]' => !$task->is_completed,
                                    'bg-gray-900 border-gray-800' => !$task->is_completed,
                                ])>

                                    <div class="flex items-center gap-3 w-full">
                                        <div class="cursor-grab active:cursor-grabbing drag-handle opacity-0 group-hover/task:opacity-40 hover:!opacity-100 p-2 -ml-3 text-gray-500 transition-opacity hidden md:block">
                                            <x-heroicon-m-bars-3 class="w-5 h-5" />
                                        </div>

                                        <button wire:click="toggleComplete('{{ $task->id }}')"
                                            class="mt-1 flex-shrink-0 w-7 h-7 rounded-xl border-2 flex items-center justify-center transition-all duration-500 {{ $task->is_completed ? 'bg-emerald-500 border-emerald-500 text-gray-950 shadow-[0_0_15px_#10b981]' : 'border-gray-800 text-transparent hover:border-[var(--theme-color)] hover:bg-[var(--theme-color-5)] bg-gray-950 shadow-inner' }}">
                                        <x-heroicon-m-check class="w-4 h-4 stroke-[3]" />
                                    </button>

                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div x-show="!isEditing"
                                             @click="isEditing = true; $nextTick(() => $refs.editInput.focus())"
                                            @class(['text-sm font-bold leading-relaxed break-words cursor-text hover:text-[var(--theme-color)] transition-colors pr-2 md:pr-4', 'line-through text-gray-600 italic' => $task->is_completed, 'text-gray-200' => !$task->is_completed])>
                                            {!! preg_replace(
                                                  '/((?:https?|ftp|file):\/\/|www\.)[a-z0-9+&@#\/%?=~_|!:,.;\*\-]*[a-z0-9+&@#\/%=~_|]/i',
                                                  '<a href="$0" target="_blank" class="text-[var(--theme-color)] underline hover:text-white transition-colors" @click.stop>$0</a>',
                                                  e($task->title)
                                              ) !!}
                                        </div>

                                        <input x-ref="editInput" x-show="isEditing" x-model="updatedTitle"
                                               @blur="isEditing = false; $wire.updateTaskTitle('{{ $task->id }}', updatedTitle)"
                                               @keydown.enter="$el.blur()"
                                                class="w-full text-sm font-bold bg-gray-950 border border-[var(--theme-color-30)] rounded-xl px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-20)] -ml-2 -mt-1 text-white shadow-inner"
                                                x-cloak>

                                        <div x-show="!isEditing" class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-[9px] font-black uppercase tracking-widest">
                                            <div class="text-[var(--theme-color-50)]">
                                                Offen seit: {{ str_replace([' Sekunden', ' Minuten', ' Stunden', ' Tagen'], [' Sek.', ' Min.', ' Std.', ' T.'], $task->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)) }}
                                            </div>

                                            <div x-data="{ isEditingDate: false, tempDate: '{{ $task->relevant_from ? $task->relevant_from->format('Y-m-d') : '' }}' }" class="relative flex items-center">
                                                <button type="button" x-show="!isEditingDate" @click="isEditingDate = true" 
                                                        class="flex items-center gap-1 hover:text-[var(--theme-color)] transition-colors {{ $task->relevant_from ? 'text-amber-500' : 'text-gray-500' }}"
                                                        title="Relevanzdatum festlegen/ändern">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                                                    <span>{{ $task->relevant_from ? 'Relevant ab: ' . $task->relevant_from->format('d.m.Y') : 'Planen' }}</span>
                                                </button>
                                                <input x-show="isEditingDate" x-ref="dateInput" type="date" x-model="tempDate"
                                                       @change="isEditingDate = false; $wire.updateTaskRelevantFrom('{{ $task->id }}', tempDate)"
                                                       @click.away="isEditingDate = false"
                                                       class="bg-gray-950 border border-[var(--theme-color-30)] rounded px-1.5 py-0.5 text-[9px] text-white shadow-inner font-sans outline-none">
                                            </div>
                                        </div>

                                        {{-- Dateianhänge --}}
                                        @if(!$task->is_completed)
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                @php
                                                    $taskFiles = $task->file_paths ?? [];
                                                @endphp
                                                @foreach($taskFiles as $index => $path)
                                                    @php
                                                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                                                        $iconName = match($ext) {
                                                            'pdf' => 'heroicon-o-document-duplicate',
                                                            'doc', 'docx', 'odt', 'rtf', 'txt' => 'heroicon-o-document-text',
                                                            'xls', 'xlsx', 'ods', 'csv' => 'heroicon-o-table-cells',
                                                            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => 'heroicon-o-photo',
                                                            'zip', 'rar', '7z', 'tar', 'gz' => 'heroicon-o-archive-box',
                                                            default => 'heroicon-o-document',
                                                        };
                                                        $iconColor = match($ext) {
                                                            'pdf' => 'text-red-400',
                                                            'doc', 'docx', 'odt', 'rtf', 'txt' => 'text-blue-400',
                                                            'xls', 'xlsx', 'ods', 'csv' => 'text-emerald-400',
                                                            'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => 'text-purple-400',
                                                            'zip', 'rar', '7z', 'tar', 'gz' => 'text-amber-500',
                                                            default => 'text-gray-400',
                                                        };
                                                        $filename = basename($path);
                                                    @endphp
                                                    <div class="flex items-center gap-1.5 px-2.5 py-1 bg-gray-950/80 border border-gray-800 rounded-lg text-[10px] text-gray-300 font-medium hover:border-gray-700 transition-all max-w-[200px] group/file-item">
                                                        <a href="{{ route('admin.accounting.receipt.show', ['path' => $path]) }}" target="_blank" class="flex items-center gap-1.5 truncate" title="{{ $filename }}">
                                                            <x-dynamic-component :component="$iconName" class="w-3.5 h-3.5 {{ $iconColor }} shrink-0" />
                                                            <span class="truncate">{{ $filename }}</span>
                                                        </a>
                                                        <button type="button" wire:click="deleteTaskFile('{{ $task->id }}', {{ $index }})" wire:confirm="Datei wirklich löschen?" class="text-gray-500 hover:text-red-500 transition-colors p-0.5" title="Datei löschen">
                                                            <x-heroicon-m-x-mark class="w-3 h-3 stroke-2" />
                                                        </button>
                                                    </div>
                                                @endforeach

                                                {{-- Inline Dateiupload mit Pinnadel-Icon --}}
                                                <div class="relative">
                                                    <label class="cursor-pointer flex items-center gap-1 px-2.5 py-1 bg-gray-900 border border-dashed border-gray-700 rounded-lg text-[10px] font-black uppercase tracking-wider text-gray-500 hover:text-[var(--theme-color)] hover:border-[var(--theme-color-50)] transition-all">
                                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M12 2v8M18 10H6M8 10v6c0 1.1.9 2 2 2h4c1.1 0 2-.9 2-2v-6M12 18v4" />
                                                        </svg>
                                                        <span>Anhängen</span>
                                                        <input type="file" class="hidden" wire:model.live="taskFilesUpload" wire:click="$set('uploadingTaskId', '{{ $task->id }}')">
                                                    </label>
                                                    @if($uploadingTaskId === $task->id && $taskFilesUpload)
                                                        <div class="absolute -top-1 -right-1 bg-gray-950 rounded-full p-0.5 border border-gray-800 shadow-sm z-10">
                                                            <svg class="animate-spin h-3.5 w-3.5 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="relative flex items-center gap-2 shrink-0">
                                        @if(!$task->is_completed)
                                            <div class="hidden sm:flex items-center">
                                                <select wire:change="updateTaskPriority('{{ $task->id }}', $event.target.value)"
                                                        class="text-[10px] font-black uppercase tracking-tighter border border-gray-800 bg-gray-950 focus:ring-0 cursor-pointer py-1.5 pl-3 pr-8 rounded-lg transition-all
                                                        {{ ($task->priority ?? 'niedrig') === 'hoch' ? 'text-red-400 border-red-900/50' : (($task->priority ?? 'niedrig') === 'mittel' ? 'text-orange-400 border-orange-900/50' : 'text-gray-500 hover:text-gray-300') }}">
                                                    <option value="niedrig" class="bg-gray-900" {{ ($task->priority ?? 'niedrig') === 'niedrig' ? 'selected' : '' }}>LOW</option>
                                                    <option value="mittel" class="bg-gray-900" {{ ($task->priority ?? 'niedrig') === 'mittel' ? 'selected' : '' }}>MED</option>
                                                    <option value="hoch" class="bg-gray-900" {{ ($task->priority ?? 'niedrig') === 'hoch' ? 'selected' : '' }}>HIGH</option>
                                                </select>
                                            </div>
                                        @endif

                                        {{-- ARCHIVE & DELETE INLINE HOVER --}}
                                        <div class="hidden md:flex items-center opacity-0 group-hover/task:opacity-100 transition-opacity">
                                            <button @click="isEditing = true; $nextTick(() => $refs.editInput.focus())" class="p-2 text-gray-500 hover:text-[var(--theme-color)] rounded-xl transition-all" title="Bearbeiten">
                                                <x-heroicon-m-cog-6-tooth class="w-5 h-5" />
                                            </button>
                                            <button wire:click="toggleArchiveTask('{{ $task->id }}')" class="p-2 text-gray-500 hover:text-amber-500 rounded-xl transition-all">
                                                <x-heroicon-m-archive-box class="w-5 h-5" />
                                            </button>
                                            <button wire:click="deleteTask('{{ $task->id }}')" class="p-2 text-gray-500 hover:text-red-500 rounded-xl transition-all">
                                                <x-heroicon-m-trash class="w-5 h-5" />
                                            </button>
                                        </div>

                                        <button @click="showMenu = !showMenu" class="p-2 text-gray-600 hover:text-white hover:bg-gray-800 rounded-xl transition-all">
                                            <x-heroicon-m-ellipsis-vertical class="w-5 h-5" />
                                        </button>

                                        <div x-show="showMenu" @click.away="showMenu = false" x-cloak
                                             x-transition:enter="transition ease-out duration-200"
                                             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                             class="absolute right-0 top-12 w-56 bg-gray-900 border border-gray-700 rounded-2xl shadow-[0_15px_30px_rgba(0,0,0,0.6)] z-[60] overflow-hidden py-1.5 ring-1 ring-white/5 backdrop-blur-xl">
                                            <button @click="isEditing = true; showMenu = false; $nextTick(() => $refs.editInput.focus())" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-pencil class="w-4 h-4 text-[var(--theme-color)]" /> Bearbeiten
                                            </button>

                                            <button @click="isAddingSub = true; showMenu = false; $nextTick(() => $refs.addSubInput.focus())" class="w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-list-bullet class="w-4 h-4 text-[var(--theme-color)]" /> Schritt dazu
                                            </button>

                                            <div class="border-t border-gray-800 my-1 md:hidden"></div>

                                            <button wire:click="toggleArchiveTask('{{ $task->id }}')" class="md:hidden w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-amber-500 hover:bg-gray-800 hover:text-amber-400 flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-archive-box class="w-4 h-4 text-amber-500" /> {{ $task->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                                            </button>

                                            <button wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Aufgabe wirklich löschen?" class="md:hidden w-full text-left px-5 py-3 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 hover:text-red-400 flex items-center gap-3 transition-colors">
                                                <x-heroicon-o-trash class="w-4 h-4 text-red-500" /> Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                {{-- SUBTASKS --}}
                                <div class="space-y-1.5 mt-2 ml-4 md:ml-12 border-l border-gray-800/50 pl-3 w-full pr-4 md:pr-12 relative">
                                    @foreach($task->subtasks as $sub)
                                        <div class="group/sub flex items-center gap-4 p-3 rounded-2xl bg-gray-950/40 border border-gray-800/50 hover:bg-gray-900 transition-all relative w-full" wire:key="sub-{{ $sub->id }}">
                                            <button wire:click="toggleComplete('{{ $sub->id }}')"
                                                    class="w-5 h-5 rounded-lg border-2 flex items-center justify-center transition-all duration-300 {{ $sub->is_completed ? 'bg-emerald-500 border-emerald-500 text-gray-950 shadow-[0_0_10px_#10b981]' : 'border-gray-800 text-transparent hover:border-[var(--theme-color)] bg-gray-950 shadow-inner' }}">
                                                <x-heroicon-m-check class="w-3.5 h-3.5 stroke-[3]" />
                                            </button>

                                            <div class="flex-1 min-w-0" x-data="{ isEditingSub: false, updatedSubTitle: '{{ $sub->title }}' }">
                                                <span x-show="!isEditingSub" @click="isEditingSub = true; $nextTick(() => $refs.editSubInput.focus())"
                                                      @class(['flex-1 text-[13px] font-medium truncate cursor-text hover:text-[var(--theme-color)] transition-colors', 'line-through text-gray-600 italic' => $sub->is_completed, 'text-gray-400' => !$sub->is_completed])>
                                                        {{ $sub->title }}
                                                </span>
                                                <input x-ref="editSubInput" x-show="isEditingSub" x-model="updatedSubTitle"
                                                       @blur="isEditingSub = false; $wire.updateTaskTitle('{{ $sub->id }}', updatedSubTitle)"
                                                       @keydown.enter="$el.blur()"
                                                       class="w-full text-xs font-medium bg-gray-950 border border-[var(--theme-color-30)] rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-[var(--theme-color-20)] -ml-2 text-white shadow-inner"
                                                       x-cloak
                                                >
                                            </div>

                                            <div class="flex items-center gap-1 opacity-0 group-hover/sub:opacity-100 transition-all">
                                                <button wire:click="promoteToTask('{{ $sub->id }}')" class="p-1.5 text-gray-600 hover:text-[var(--theme-color)] transition-all rounded-lg hover:bg-gray-800" title="Zu Aufgabe befördern">
                                                    <x-heroicon-o-chevron-double-up class="w-4 h-4" />
                                                </button>
                                                <button wire:click="deleteTask('{{ $sub->id }}')" wire:confirm="Unterschritt löschen?" class="p-1.5 text-gray-600 hover:text-red-500 transition-all rounded-lg hover:bg-red-500/10" title="Unterschritt löschen">
                                                    <x-heroicon-o-trash class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div x-show="isAddingSub" x-cloak class="pt-2 animate-fade-in">
                                        <div class="flex gap-3 items-center bg-gray-950 border border-[var(--theme-color-30)] rounded-2xl p-1.5 shadow-2xl ring-4 ring-[var(--theme-color)]/5">
                                            <input type="text" x-model="subTitle" x-ref="addSubInput"
                                                   @keydown.enter="$wire.addSubTask('{{ $task->id }}', subTitle); subTitle = ''; isAddingSub = false"
                                                   placeholder="Nächster Teilschritt..."
                                                   class="flex-1 bg-transparent border-none px-4 py-2 text-xs font-bold text-white focus:ring-0 placeholder:text-gray-700 shadow-none outline-none">
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
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color); }
    </style>

    {{-- AUDIO FEEDBACK --}}
    <audio id="taskDoneSound" src="{{ asset('shop/management/todo/sounds/todo_done.mp3') }}" preload="auto"></audio>

    @script
    <script>
        $wire.on('task-completed', () => {
            let audio = document.getElementById('taskDoneSound');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(e => console.log('Audio Autoplay prevented'));
            }
        });
    </script>
    @endscript
</div>
