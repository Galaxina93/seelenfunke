<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;" class="flex flex-col h-[95vh] md:h-[calc(100vh-10rem)] bg-white/95 dark:bg-gray-900/90 backdrop-blur-xl rounded-2xl md:rounded-3xl dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 overflow-hidden min-h-[500px] md:min-h-[700px] animate-fade-in-up transition-colors duration-300">

    {{-- GOOGLE FONT: INTER FÜR OPTIMALE LESBARKEIT --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- HEADER BEREICH --}}
    <div class="bg-gray-50/90 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-30 shadow-sm backdrop-blur-md shrink-0">
        <div class="max-w-7xl mx-auto px-4 md:px-6 py-3 md:py-4">
            <div class="flex flex-col md:flex-row justify-between items-center gap-3 md:gap-4">
                <div class="flex items-center gap-2 md:gap-3 w-full">
                    <div class="relative w-full">
                        <x-heroicon-m-magnifying-glass class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 pointer-events-none" />
                        <input wire:model.live="search" type="text" placeholder="Aufgaben durchsuchen..."
                               class="w-full pl-10 pr-4 py-2 md:py-2.5 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl focus:ring-2 focus:ring-[var(--theme-color-20)] focus:border-[var(--theme-color)] transition-all text-xs md:text-sm font-medium text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 outline-none">
                    </div>

                    <button wire:click="toggleArchiveMode" class="flex-shrink-0 px-3 md:px-4 py-2 md:py-2.5 rounded-xl border {{ $showArchive ? 'border-amber-500 text-amber-600 bg-amber-500/10' : 'border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:border-[var(--theme-color-40)] bg-white dark:bg-gray-950' }} transition-all flex items-center gap-2 font-medium text-xs">
                        <x-heroicon-m-archive-box class="w-4 h-4" />
                        <span class="hidden sm:inline">{{ $showArchive ? 'Archiv schließen' : 'Archiv öffnen' }}</span>
                        <span class="sm:hidden">{{ $showArchive ? 'Archiv' : 'Archiv' }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- HAUPTLAYOUT MIT RESIZER & SIDEBAR --}}
    <div class="flex flex-col lg:flex-row flex-1 overflow-hidden min-h-0 relative"
         x-data="{
             sidebarWidth: parseInt(localStorage.getItem('admin_tasks_sidebar_width') || '288'),
             isResizing: false,
             minWidth: 220,
             maxWidth: 580,
             startResize(e) {
                 this.isResizing = true;
                 const startX = e.clientX || (e.touches && e.touches[0].clientX);
                 const startW = this.sidebarWidth;
                 document.body.style.cursor = 'col-resize';
                 document.body.style.userSelect = 'none';

                 const onMove = (moveEvt) => {
                     if (!this.isResizing) return;
                     const currentX = moveEvt.clientX || (moveEvt.touches && moveEvt.touches[0].clientX);
                     const delta = currentX - startX;
                     let newWidth = Math.max(this.minWidth, Math.min(this.maxWidth, startW + delta));
                     this.sidebarWidth = newWidth;
                 };

                 const onUp = () => {
                     if (this.isResizing) {
                         this.isResizing = false;
                         localStorage.setItem('admin_tasks_sidebar_width', this.sidebarWidth);
                         document.body.style.cursor = '';
                         document.body.style.userSelect = '';
                         window.removeEventListener('mousemove', onMove);
                         window.removeEventListener('mouseup', onUp);
                         window.removeEventListener('touchmove', onMove);
                         window.removeEventListener('touchend', onUp);
                     }
                 };

                 window.addEventListener('mousemove', onMove);
                 window.addEventListener('mouseup', onUp);
                 window.addEventListener('touchmove', onMove);
                 window.addEventListener('touchend', onUp);
             }
         }">

        {{-- SIDEBAR: LISTEN --}}
        <div class="w-full lg:w-72 max-h-[30vh] lg:max-h-none bg-gray-50/60 dark:bg-gray-950/40 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-800 flex flex-col overflow-y-auto custom-scrollbar p-3 md:p-4 gap-1.5 shrink-0"
             :style="window.innerWidth >= 1024 ? ('width: ' + sidebarWidth + 'px') : ''"
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
            
            <div class="flex items-center justify-between px-2 mb-1.5 shrink-0">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Deine Listen</span>
                <span class="text-[11px] text-gray-400 dark:text-gray-500 font-medium">{{ count($lists) }}</span>
            </div>

            @foreach($lists as $list)
                <div class="category-item shrink-0 relative group/list-item w-full" data-list-id="{{ $list->id }}" wire:key="list-{{ $list->id }}" x-data="{ showListMenu: false, isEditingName: false, updatedListName: '{{ addslashes($list->name) }}' }">
                    <div @click="if (!isEditingName) $wire.set('selectedListId', '{{ $list->id }}')"
                         @contextmenu.prevent="showListMenu = true"
                         @class([
                            'flex items-center gap-2.5 p-2 rounded-xl transition-all duration-200 w-full border cursor-pointer select-none',
                            'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-gray-900 dark:text-white shadow-sm' => $selectedListId === $list->id,
                            'bg-white/70 dark:bg-gray-900/60 border-gray-200/70 dark:border-gray-800 hover:bg-gray-100/80 dark:hover:bg-gray-800/80 hover:border-gray-300 dark:hover:border-gray-700 text-gray-700 dark:text-gray-300' => $selectedListId !== $list->id
                        ])>

                        {{-- Drag-Griff mit @click.stop damit Klick nicht die Liste selektiert beim Ziehen --}}
                        <div @click.stop class="category-drag-handle cursor-grab active:cursor-grabbing opacity-0 group-hover/list-item:opacity-50 hover:!opacity-100 p-1 -ml-1 text-gray-400 dark:text-gray-500 transition-opacity">
                            <x-heroicon-m-bars-3 class="w-3.5 h-3.5" />
                        </div>

                        {{-- Icon der Liste (Jetzt VOLLSTÄNDIG im klickbaren Bereich) --}}
                        <div @class([
                            'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-200',
                            'bg-[var(--theme-color)] text-gray-950 shadow-sm' => $selectedListId === $list->id,
                            'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 group-hover:text-[var(--theme-color)]' => $selectedListId !== $list->id
                        ])>
                            <x-dynamic-component :component="'heroicon-o-' . $list->icon" class="w-4 h-4" />
                        </div>

                        {{-- Name & Status --}}
                        <div class="flex-1 min-w-0 text-left">
                            <div x-show="!isEditingName" class="text-xs md:text-sm font-medium truncate {{ $list->is_archived ? 'text-amber-500 italic' : ($selectedListId === $list->id ? 'font-semibold text-gray-950 dark:text-white' : 'text-gray-700 dark:text-gray-300') }}">
                                {{ $list->name }}
                            </div>
                            <input x-ref="listNameInput" x-show="isEditingName" x-model="updatedListName"
                                   @click.stop
                                   @blur="isEditingName = false; $wire.renameList('{{ $list->id }}', updatedListName)"
                                   @keydown.enter="$el.blur()"
                                   class="w-full text-xs font-medium bg-white dark:bg-gray-950 border border-[var(--theme-color-40)] rounded-lg px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-[var(--theme-color)] text-gray-900 dark:text-white outline-none"
                                   x-cloak>
                        </div>

                        {{-- Badges & Zähler --}}
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($list->open_count > 0 && !$list->is_archived)
                                <span @class([
                                    'px-1.5 py-0.5 rounded-md text-[10px] font-semibold transition-colors',
                                    'bg-[var(--theme-color-20)] text-gray-900 dark:text-white' => $selectedListId === $list->id,
                                    'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' => $selectedListId !== $list->id
                                ])>
                                    {{ $list->open_count }}
                                </span>
                            @endif

                            @if($list->is_archived)
                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-amber-500/10 text-amber-600 border border-amber-500/20">
                                    Archiv
                                </span>
                            @endif

                            {{-- Menü-Button (Rechtsklick oder 3-Punkte) --}}
                            <button @click.stop="showListMenu = !showListMenu" class="p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors rounded-md hover:bg-gray-200/50 dark:hover:bg-gray-700/50">
                                <x-heroicon-m-ellipsis-vertical class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {{-- CONTEXT MENU / RECHTSKLICK MENU --}}
                    <div x-show="showListMenu" @click.away="showListMenu = false" x-cloak
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="absolute left-6 top-full mt-1 w-48 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-[60] overflow-hidden py-1 ring-1 ring-black/5 dark:ring-white/5 backdrop-blur-xl">

                         <div class="px-3 py-1.5 border-b border-gray-100 dark:border-gray-800 mb-0.5">
                             <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 truncate">{{ $list->name }}</p>
                         </div>
                        <button @click="isEditingName = true; showListMenu = false; $nextTick(() => $refs.listNameInput.focus())" class="w-full text-left px-3.5 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-2.5 transition-colors">
                            <x-heroicon-s-pencil class="w-4 h-4 text-[var(--theme-color)]" /> Umbenennen
                        </button>
                        <button wire:click="toggleArchiveList('{{ $list->id }}')" @click="showListMenu = false" class="w-full text-left px-3.5 py-2 text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2.5 transition-colors">
                            <x-heroicon-s-archive-box class="w-4 h-4 text-amber-500" /> {{ $list->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                        </button>
                        <button wire:click="deleteList('{{ $list->id }}')" wire:confirm="Wirklich löschen?" class="w-full text-left px-3.5 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 flex items-center gap-2.5 transition-colors">
                            <x-heroicon-s-trash class="w-4 h-4 text-rose-500" /> Löschen
                        </button>
                    </div>
                </div>
            @endforeach

            {{-- LISTE ERSTELLEN --}}
            <div class="w-full mt-1.5 shrink-0">
                @if(!$isAddingList)
                    <button wire:click="$set('isAddingList', true)"
                            class="w-full flex items-center gap-2.5 p-2 rounded-xl border border-dashed border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-[var(--theme-color)] hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-all group">
                        <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center group-hover:border-[var(--theme-color-50)] text-gray-500 group-hover:text-[var(--theme-color)] transition-colors">
                            <x-heroicon-o-plus class="w-4 h-4" />
                        </div>
                        <span class="text-xs font-medium">Neue Liste erstellen</span>
                    </button>
                @else
                    <div class="bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-gray-700 shadow-md animate-fade-in-up">
                        <input wire:model="newList_name" type="text" placeholder="Listenname..."
                               class="w-full text-xs font-medium bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg py-2 px-2.5 focus:ring-1 focus:ring-[var(--theme-color)] mb-2.5 text-gray-900 dark:text-white outline-none"
                               autofocus>

                        <div class="grid grid-cols-5 gap-1 mb-3">
                            @foreach(['bookmark', 'star', 'heart', 'bolt', 'home', 'briefcase', 'shopping-bag', 'trophy', 'sun', 'moon', 'wrench', 'rocket-launch', 'tag', 'flag'] as $icon)
                                <button wire:click="$set('newList_icon', '{{ $icon }}')"
                                    @class([
                                        'w-7 h-7 rounded-md flex items-center justify-center transition-all border',
                                        'bg-[var(--theme-color)] text-gray-950 border-[var(--theme-color)] shadow-sm' => $newList_icon === $icon,
                                        'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' => $newList_icon !== $icon
                                    ])>
                                    <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-3.5 h-3.5" />
                                </button>
                            @endforeach
                        </div>

                        <div class="flex gap-2">
                            <button wire:click="cancelCreateList" class="flex-1 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors">Abbruch</button>
                            <button wire:click="createList" class="flex-1 py-1.5 text-xs font-semibold text-gray-950 bg-[var(--theme-color)] rounded-lg hover:brightness-105 shadow-sm transition-all">Erstellen</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- DRAGGABLE RESIZER SPLITTER (Desktop) --}}
        <div @mousedown.prevent="startResize($event)"
             @touchstart.prevent="startResize($event)"
             style="cursor: col-resize; cursor: ew-resize;"
             class="hidden lg:flex w-3.5 -mr-2 -ml-1.5 z-20 cursor-ew-resize resizer-splitter group relative items-center justify-center shrink-0 hover:bg-[var(--theme-color-20)] transition-colors select-none"
             :class="isResizing ? 'bg-[var(--theme-color-30)] !w-3.5' : ''"
             title="Seitenleiste durch Ziehen vergrößern oder verkleinern">
            {{-- Unsichtbare breitere Hitbox für mühelosen Mauszeiger-Wechsel --}}
            <div class="absolute inset-y-0 -left-2 -right-2 z-10" style="cursor: col-resize; cursor: ew-resize;"></div>
            {{-- Sichtbare Trennlinie und Grip-Handle --}}
            <div class="w-1 h-8 rounded-full bg-gray-300 dark:bg-gray-700 group-hover:bg-[var(--theme-color)] group-hover:h-12 transition-all duration-200 pointer-events-none"
                 :class="isResizing ? '!bg-[var(--theme-color)] !h-16 shadow-[0_0_8px_var(--theme-color)]' : ''"></div>
        </div>

        {{-- MAIN CONTENT: TASKS --}}
        <div class="flex-1 flex flex-col bg-transparent dark:bg-gray-950/20 min-w-0 min-h-0 relative">

            {{-- TASK CREATION INPUT BAR --}}
            <div class="p-3 md:p-5 border-b border-gray-200 dark:border-gray-800 bg-white/70 dark:bg-gray-900/40 backdrop-blur-sm z-10 shadow-sm relative shrink-0">
                <form wire:submit.prevent="createTask" class="relative group max-w-4xl mx-auto">
                    <div class="relative flex items-center">
                        {{-- Icon links --}}
                        <div class="absolute left-3.5 z-20 flex items-center pointer-events-none text-gray-400 group-focus-within:text-[var(--theme-color)] transition-colors">
                            <x-heroicon-m-plus class="w-5 h-5 transition-transform duration-200 group-focus-within:rotate-90" />
                        </div>

                        {{-- Inputfeld --}}
                        <input wire:model="newTask_title" type="text"
                               placeholder="{{ $selectedListId ? 'Neue Aufgabe hinzufügen...' : 'Wähle links eine Liste aus...' }}"
                               @disabled(!$selectedListId)
                               class="w-full pl-11 pr-28 md:pr-36 py-2.5 md:py-3 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl focus:ring-2 focus:ring-[var(--theme-color-20)] focus:border-[var(--theme-color)] transition-all text-sm md:text-base font-normal text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 outline-none disabled:opacity-50 disabled:cursor-not-allowed shadow-sm">

                        {{-- Button rechts --}}
                        <div class="absolute right-2 md:right-2.5 z-20 flex items-center">
                            <button type="submit" @disabled(!$selectedListId) class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-950 text-xs font-semibold hover:brightness-105 active:scale-95 disabled:opacity-30 disabled:pointer-events-none transition-all shadow-sm">
                                <span>Hinzufügen</span>
                                <kbd class="text-[10px] bg-black/15 px-1 py-0.5 rounded font-mono font-normal">↵</kbd>
                            </button>
                            <button type="submit" @disabled(!$selectedListId) class="sm:hidden p-1.5 rounded-lg bg-[var(--theme-color)] text-gray-950 active:scale-95 disabled:opacity-30 transition-all">
                                <x-heroicon-m-plus class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </form>

                {{-- QUICK FILTER PILLS --}}
                <div class="max-w-4xl mx-auto mt-3 flex items-center gap-1.5 overflow-x-auto custom-scrollbar pb-0.5">
                    <button wire:click="$set('quickFilter', 'all')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all {{ $quickFilter === 'all' ? 'bg-[var(--theme-color)] text-gray-950 font-bold shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white bg-gray-100 dark:bg-gray-800' }}">
                        Alle
                    </button>
                    <button wire:click="$set('quickFilter', 'high_prio')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 {{ $quickFilter === 'high_prio' ? 'bg-red-500 text-white font-bold shadow-sm' : 'text-gray-500 hover:text-red-500 bg-gray-100 dark:bg-gray-800' }}">
                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 {{ $quickFilter === 'high_prio' ? 'bg-white' : '' }}"></span>
                        Hohe Priorität
                    </button>
                    <button wire:click="$set('quickFilter', 'due_today')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 {{ $quickFilter === 'due_today' ? 'bg-amber-500 text-gray-950 font-bold shadow-sm' : 'text-gray-500 hover:text-amber-500 bg-gray-100 dark:bg-gray-800' }}">
                        <x-heroicon-m-clock class="w-3.5 h-3.5" />
                        Heute & Überfällig
                    </button>
                    <button wire:click="$set('quickFilter', 'with_attachments')"
                            class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all flex items-center gap-1.5 {{ $quickFilter === 'with_attachments' ? 'bg-indigo-500 text-white font-bold shadow-sm' : 'text-gray-500 hover:text-indigo-400 bg-gray-100 dark:bg-gray-800' }}">
                        <x-heroicon-m-paper-clip class="w-3.5 h-3.5" />
                        Mit Anhang
                    </button>
                </div>
            </div>

            {{-- TASKS LIST CONTAINER --}}
            <div class="flex-1 overflow-y-auto p-3 md:p-6 space-y-2.5 custom-scrollbar">
                @if(!$selectedListId)
                    <div class="flex flex-col items-center justify-center h-full text-center p-8 opacity-40">
                        <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 border border-gray-200 dark:border-gray-700">
                            <x-heroicon-o-queue-list class="w-8 h-8 text-[var(--theme-color)]" />
                        </div>
                        <p class="font-medium text-gray-700 dark:text-gray-300 text-base">Wähle eine Liste aus</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 font-normal">Klicke links auf eine Liste, um Aufgaben anzuzeigen oder zu erstellen</p>
                    </div>
                @else
                    <div class="max-w-4xl mx-auto space-y-2.5" id="task-sortable-list"
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

                                {{-- MODERNE AUFGABEN-KACHEL --}}
                                <div @class([
                                    'relative flex items-center justify-between p-3 sm:p-4 rounded-xl md:rounded-2xl border transition-all duration-200 group-hover/task:shadow-md',
                                    'bg-gray-50/70 dark:bg-gray-950/40 border-gray-200/60 dark:border-gray-800/60 opacity-60' => $task->is_completed,
                                    'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700' => !$task->is_completed,
                                ])>

                                    <div class="flex items-center gap-3 w-full min-w-0">
                                        {{-- Drag Handle --}}
                                        <div class="cursor-grab active:cursor-grabbing drag-handle opacity-0 group-hover/task:opacity-40 hover:!opacity-100 p-1 -ml-1 text-gray-400 dark:text-gray-500 transition-opacity hidden md:block shrink-0">
                                            <x-heroicon-m-bars-3 class="w-4 h-4" />
                                        </div>

                                        {{-- Schlanke, runde Checkbox --}}
                                        <button wire:click="toggleComplete('{{ $task->id }}')"
                                                class="flex-shrink-0 w-5.5 h-5.5 rounded-full border-2 flex items-center justify-center transition-all duration-200 {{ $task->is_completed ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm' : 'border-gray-300 dark:border-gray-600 hover:border-[var(--theme-color)] hover:bg-[var(--theme-color-10)] bg-white dark:bg-gray-950 text-transparent' }}"
                                                title="{{ $task->is_completed ? 'Als unerledigt markieren' : 'Als erledigt markieren' }}">
                                            <x-heroicon-m-check class="w-3.5 h-3.5 stroke-[3] {{ $task->is_completed ? 'opacity-100' : 'opacity-0 hover:opacity-40 hover:text-[var(--theme-color)]' }}" />
                                        </button>

                                        {{-- Inhalt & Titel --}}
                                        <div class="flex-1 min-w-0">
                                            <div x-show="!isEditing"
                                                 @click="isEditing = true; $nextTick(() => $refs.editInput.focus())"
                                                 @class([
                                                     'text-[14px] sm:text-[15px] font-medium leading-snug break-words cursor-text hover:text-[var(--theme-color)] transition-colors pr-2 md:pr-4',
                                                     'line-through text-gray-400 dark:text-gray-500' => $task->is_completed,
                                                     'text-gray-900 dark:text-gray-100' => !$task->is_completed
                                                 ])>
                                                {!! preg_replace(
                                                      '/((?:https?|ftp|file):\/\/|www\.)[a-z0-9+&@#\/%?=~_|!:,.;\*\-]*[a-z0-9+&@#\/%=~_|]/i',
                                                      '<a href="$0" target="_blank" class="text-[var(--theme-color)] underline hover:text-gray-900 dark:hover:text-white transition-colors" @click.stop>$0</a>',
                                                      e($task->title)
                                                  ) !!}
                                            </div>

                                            <input x-ref="editInput" x-show="isEditing" x-model="updatedTitle"
                                                   @blur="isEditing = false; $wire.updateTaskTitle('{{ $task->id }}', updatedTitle)"
                                                   @keydown.enter="$el.blur()"
                                                   class="w-full text-sm font-medium bg-white dark:bg-gray-950 border border-[var(--theme-color-40)] rounded-lg px-2.5 py-1 focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-20)] text-gray-900 dark:text-white outline-none"
                                                   x-cloak>

                                            {{-- Metadaten-Zeile (Offen seit, Fälligkeit / Planen, Teilschritte) --}}
                                            <div x-show="!isEditing" class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-1 font-normal text-gray-400 dark:text-gray-500">
                                                    <x-heroicon-m-clock class="w-3.5 h-3.5 opacity-70 shrink-0" />
                                                    <span>Offen seit {{ str_replace([' Sekunden', ' Minuten', ' Stunden', ' Tagen'], [' Sek.', ' Min.', ' Std.', ' T.'], $task->created_at->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE)) }}</span>
                                                </div>

                                                <span class="text-gray-300 dark:text-gray-700 hidden sm:inline">•</span>

                                                <div x-data="{ isEditingDate: false, tempDate: '{{ $task->relevant_from ? $task->relevant_from->format('Y-m-d') : '' }}' }" class="relative flex items-center">
                                                    @php
                                                        $isOverdue = $task->relevant_from && $task->relevant_from->isPast() && !$task->is_completed && !$task->relevant_from->isToday();
                                                        $isDueToday = $task->relevant_from && $task->relevant_from->isToday() && !$task->is_completed;
                                                    @endphp
                                                    <button type="button" x-show="!isEditingDate" @click="isEditingDate = true" 
                                                            class="flex items-center gap-1 transition-colors {{ $isOverdue ? 'text-red-600 dark:text-red-400 font-bold bg-red-500/10 px-1.5 py-0.5 rounded border border-red-500/20' : ($isDueToday ? 'text-amber-600 dark:text-amber-400 font-bold bg-amber-500/10 px-1.5 py-0.5 rounded border border-amber-500/20' : ($task->relevant_from ? 'text-amber-600 dark:text-amber-400 font-medium' : 'text-gray-400 dark:text-gray-500 hover:text-[var(--theme-color)]')) }}"
                                                            title="Relevanzdatum festlegen/ändern">
                                                        @if($isOverdue)
                                                            <x-heroicon-m-exclamation-triangle class="w-3.5 h-3.5 shrink-0 text-red-500" />
                                                            <span>Überfällig: {{ $task->relevant_from->format('d.m.Y') }}</span>
                                                        @elseif($isDueToday)
                                                            <x-heroicon-m-clock class="w-3.5 h-3.5 shrink-0 text-amber-500" />
                                                            <span>Heute fällig!</span>
                                                        @else
                                                            <x-heroicon-m-calendar class="w-3.5 h-3.5 shrink-0" />
                                                            <span>{{ $task->relevant_from ? 'Relevant ab: ' . $task->relevant_from->format('d.m.Y') : 'Planen' }}</span>
                                                        @endif
                                                    </button>
                                                    <input x-show="isEditingDate" x-ref="dateInput" type="date" x-model="tempDate"
                                                           @change="isEditingDate = false; $wire.updateTaskRelevantFrom('{{ $task->id }}', tempDate)"
                                                           @click.away="isEditingDate = false"
                                                           class="bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-2 py-0.5 text-xs text-gray-900 dark:text-white outline-none">
                                                </div>

                                                @if($task->subtasks->count() > 0)
                                                    <span class="text-gray-300 dark:text-gray-700 hidden sm:inline">•</span>
                                                    <span class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                                                        <x-heroicon-m-list-bullet class="w-3.5 h-3.5 opacity-70 shrink-0" />
                                                        <span>{{ $task->subtasks->where('is_completed', true)->count() }}/{{ $task->subtasks->count() }} Teilschritte</span>
                                                    </span>
                                                @endif
                                            </div>

                                            {{-- Dateianhänge --}}
                                            @if(!$task->is_completed && !empty($task->file_paths))
                                                <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
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
                                                                'pdf' => 'text-rose-500',
                                                                'doc', 'docx', 'odt', 'rtf', 'txt' => 'text-blue-500',
                                                                'xls', 'xlsx', 'ods', 'csv' => 'text-emerald-500',
                                                                'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg' => 'text-purple-500',
                                                                'zip', 'rar', '7z', 'tar', 'gz' => 'text-amber-500',
                                                                default => 'text-gray-500',
                                                            };
                                                            $filename = basename($path);
                                                        @endphp
                                                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-gray-100 dark:bg-gray-800/80 border border-gray-200 dark:border-gray-700/80 rounded-md text-xs text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600 transition-all max-w-[220px]">
                                                            <a href="{{ route('admin.accounting.receipt.show', ['path' => $path]) }}" target="_blank" class="flex items-center gap-1.5 truncate" title="{{ $filename }}">
                                                                <x-dynamic-component :component="$iconName" class="w-3.5 h-3.5 {{ $iconColor }} shrink-0" />
                                                                <span class="truncate">{{ $filename }}</span>
                                                            </a>
                                                            <button type="button" wire:click="deleteTaskFile('{{ $task->id }}', {{ $index }})" wire:confirm="Datei wirklich löschen?" class="text-gray-400 hover:text-rose-500 transition-colors p-0.5" title="Datei löschen">
                                                                <x-heroicon-m-x-mark class="w-3 h-3 stroke-2" />
                                                            </button>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Aktionen unter der Kachel (Anhängen, Teilschritt) --}}
                                            @if(!$task->is_completed)
                                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                                    {{-- Dateianhang Button --}}
                                                    <div class="relative">
                                                        <label class="cursor-pointer inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-dashed border-gray-300 dark:border-gray-700 text-xs font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white hover:border-gray-400 transition-all">
                                                            <x-heroicon-m-paper-clip class="w-3.5 h-3.5 shrink-0" />
                                                            <span>Anhang</span>
                                                            <input type="file" class="hidden" wire:model.live="taskFilesUpload" wire:click="$set('uploadingTaskId', '{{ $task->id }}')">
                                                        </label>
                                                        @if($uploadingTaskId === $task->id && $taskFilesUpload)
                                                            <div class="absolute -top-1 -right-1 bg-white dark:bg-gray-950 rounded-full p-0.5 border border-gray-200 dark:border-gray-800 shadow-sm z-10">
                                                                <svg class="animate-spin h-3.5 w-3.5 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    {{-- Schritt dazu Button --}}
                                                    <button type="button" @click="isAddingSub = !isAddingSub; $nextTick(() => isAddingSub && $refs.addSubInput.focus())" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md border border-dashed border-gray-300 dark:border-gray-700 text-xs font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white hover:border-gray-400 transition-all">
                                                        <x-heroicon-m-plus class="w-3.5 h-3.5 shrink-0" />
                                                        <span>Teilschritt</span>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Rechte Seite: Priorität & Aktionen --}}
                                    <div class="relative flex items-center gap-1.5 shrink-0 ml-2">
                                        @if(!$task->is_completed)
                                            <div class="hidden sm:flex items-center">
                                                <div class="relative inline-flex items-center">
                                                    @php
                                                        $pri = $task->priority ?? 'niedrig';
                                                        $priStyle = match($pri) {
                                                            'hoch' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-900/50',
                                                            'mittel' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-900/50',
                                                            default => 'bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700',
                                                        };
                                                        $dotColor = match($pri) {
                                                            'hoch' => 'bg-rose-500',
                                                            'mittel' => 'bg-amber-500',
                                                            default => 'bg-gray-400',
                                                        };
                                                    @endphp
                                                    <span class="absolute left-2.5 w-1.5 h-1.5 rounded-full {{ $dotColor }} pointer-events-none"></span>
                                                    <select wire:change="updateTaskPriority('{{ $task->id }}', $event.target.value)"
                                                            class="text-xs font-medium border rounded-lg cursor-pointer py-1 pl-6 pr-6 transition-all appearance-none {{ $priStyle }} focus:outline-none focus:ring-1 focus:ring-[var(--theme-color-30)]">
                                                        <option value="niedrig" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" {{ $pri === 'niedrig' ? 'selected' : '' }}>Niedrig</option>
                                                        <option value="mittel" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" {{ $pri === 'mittel' ? 'selected' : '' }}>Mittel</option>
                                                        <option value="hoch" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white" {{ $pri === 'hoch' ? 'selected' : '' }}>Hoch</option>
                                                    </select>
                                                    <x-heroicon-m-chevron-down class="w-3 h-3 absolute right-1.5 text-gray-400 pointer-events-none" />
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Inline Hover-Aktionen für Desktop --}}
                                        <div class="hidden md:flex items-center opacity-0 group-hover/task:opacity-100 transition-opacity">
                                            <button @click="isEditing = true; $nextTick(() => $refs.editInput.focus())" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-all" title="Bearbeiten">
                                                <x-heroicon-m-pencil-square class="w-4 h-4" />
                                            </button>
                                            <button wire:click="toggleArchiveTask('{{ $task->id }}')" class="p-1.5 text-gray-400 hover:text-amber-500 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-all" title="{{ $task->is_archived ? 'Wiederherstellen' : 'Archivieren' }}">
                                                <x-heroicon-m-archive-box class="w-4 h-4" />
                                            </button>
                                            <button wire:click="deleteTask('{{ $task->id }}')" wire:confirm="Aufgabe wirklich löschen?" class="p-1.5 text-gray-400 hover:text-rose-500 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-all" title="Löschen">
                                                <x-heroicon-m-trash class="w-4 h-4" />
                                            </button>
                                        </div>

                                        {{-- 3-Punkte Kontextmenü --}}
                                        <button @click="showMenu = !showMenu" class="p-1.5 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-all">
                                            <x-heroicon-m-ellipsis-vertical class="w-4 h-4" />
                                        </button>

                                        <div x-show="showMenu" @click.away="showMenu = false" x-cloak
                                             x-transition:enter="transition ease-out duration-150"
                                             x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                             class="absolute right-0 top-9 w-48 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl z-[60] overflow-hidden py-1 ring-1 ring-black/5 dark:ring-white/5 backdrop-blur-xl">
                                            <button @click="isEditing = true; showMenu = false; $nextTick(() => $refs.editInput.focus())" class="w-full text-left px-3.5 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-2.5 transition-colors">
                                                <x-heroicon-o-pencil class="w-4 h-4 text-[var(--theme-color)]" /> Bearbeiten
                                            </button>

                                            <button @click="isAddingSub = true; showMenu = false; $nextTick(() => $refs.addSubInput.focus())" class="w-full text-left px-3.5 py-2 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-[var(--theme-color)] flex items-center gap-2.5 transition-colors">
                                                <x-heroicon-o-list-bullet class="w-4 h-4 text-[var(--theme-color)]" /> Teilschritt dazu
                                            </button>

                                            <div class="border-t border-gray-100 dark:border-gray-800 my-0.5"></div>

                                            <button wire:click="toggleArchiveTask('{{ $task->id }}')" @click="showMenu = false" class="w-full text-left px-3.5 py-2 text-xs font-medium text-amber-600 dark:text-amber-400 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2.5 transition-colors">
                                                <x-heroicon-o-archive-box class="w-4 h-4 text-amber-500" /> {{ $task->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                                            </button>

                                            <button wire:click="deleteTask('{{ $task->id }}')" @click="showMenu = false" wire:confirm="Aufgabe wirklich löschen?" class="w-full text-left px-3.5 py-2 text-xs font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-500/10 flex items-center gap-2.5 transition-colors">
                                                <x-heroicon-o-trash class="w-4 h-4 text-rose-500" /> Löschen
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- TEILSCHRITTE (SUBTASKS) --}}
                                <div class="space-y-1 mt-1.5 ml-4 md:ml-9 border-l-2 border-gray-200 dark:border-gray-800 pl-3 pr-2 relative">
                                    @foreach($task->subtasks as $sub)
                                        <div class="group/sub flex items-center gap-2.5 py-1.5 px-2.5 rounded-lg bg-gray-50/80 dark:bg-gray-950/40 border border-gray-200/60 dark:border-gray-800/60 hover:bg-gray-100/80 dark:hover:bg-gray-900 transition-all relative w-full" wire:key="sub-{{ $sub->id }}">
                                            <button wire:click="toggleComplete('{{ $sub->id }}')"
                                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-all duration-200 {{ $sub->is_completed ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-300 dark:border-gray-600 hover:border-[var(--theme-color)] text-transparent bg-white dark:bg-gray-950' }}">
                                                <x-heroicon-m-check class="w-2.5 h-2.5 stroke-[3] {{ $sub->is_completed ? 'opacity-100' : 'opacity-0' }}" />
                                            </button>

                                            <div class="flex-1 min-w-0" x-data="{ isEditingSub: false, updatedSubTitle: '{{ $sub->title }}' }">
                                                <span x-show="!isEditingSub" @click="isEditingSub = true; $nextTick(() => $refs.editSubInput.focus())"
                                                      @class(['text-xs font-medium truncate cursor-text hover:text-[var(--theme-color)] transition-colors block', 'line-through text-gray-400 dark:text-gray-500' => $sub->is_completed, 'text-gray-700 dark:text-gray-300' => !$sub->is_completed])>
                                                        {{ $sub->title }}
                                                </span>
                                                <input x-ref="editSubInput" x-show="isEditingSub" x-model="updatedSubTitle"
                                                       @blur="isEditingSub = false; $wire.updateTaskTitle('{{ $sub->id }}', updatedSubTitle)"
                                                       @keydown.enter="$el.blur()"
                                                       class="w-full text-xs font-medium bg-white dark:bg-gray-950 border border-[var(--theme-color-30)] rounded px-2 py-0.5 focus:outline-none focus:ring-1 focus:ring-[var(--theme-color-20)] text-gray-900 dark:text-white outline-none"
                                                       x-cloak>
                                            </div>

                                            <div class="flex items-center gap-1 opacity-0 group-hover/sub:opacity-100 transition-all shrink-0">
                                                <button wire:click="promoteToTask('{{ $sub->id }}')" class="p-1 text-gray-400 hover:text-[var(--theme-color)] transition-all rounded hover:bg-gray-200 dark:hover:bg-gray-800" title="Zu Hauptaufgabe machen">
                                                    <x-heroicon-o-chevron-double-up class="w-3.5 h-3.5" />
                                                </button>
                                                <button wire:click="deleteTask('{{ $sub->id }}')" wire:confirm="Teilschritt wirklich löschen?" class="p-1 text-gray-400 hover:text-rose-500 transition-all rounded hover:bg-rose-500/10" title="Teilschritt löschen">
                                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    {{-- Teilschritt Hinzufügen Formular --}}
                                    <div x-show="isAddingSub" x-cloak class="pt-1 animate-fade-in">
                                        <div class="flex gap-2 items-center bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg p-1 shadow-sm">
                                            <input type="text" x-model="subTitle" x-ref="addSubInput"
                                                   @keydown.enter="$wire.addSubTask('{{ $task->id }}', subTitle); subTitle = ''; isAddingSub = false"
                                                   placeholder="Neuer Teilschritt..."
                                                   class="flex-1 bg-transparent border-none px-2.5 py-1 text-xs font-medium text-gray-900 dark:text-white focus:ring-0 placeholder:text-gray-400 outline-none">
                                            <button @click="isAddingSub = false" class="text-gray-400 hover:text-rose-500 p-1 rounded hover:bg-rose-500/10 transition-colors">
                                                <x-heroicon-m-x-mark class="w-3.5 h-3.5 stroke-2"/>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 border border-gray-200 dark:border-gray-700">
                                    <x-heroicon-o-check-badge class="w-8 h-8 text-emerald-500" />
                                </div>
                                <h4 class="text-base font-semibold text-gray-900 dark:text-white">Alles erledigt!</h4>
                                <p class="text-xs text-gray-500 mt-1 font-normal">Keine offenen Aufgaben in dieser Liste.</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- CUSTOM SCROLLBAR & SPLITTER CURSOR --}}
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.4); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color); }

        .resizer-splitter,
        .resizer-splitter * {
            cursor: col-resize !important;
            cursor: ew-resize !important;
        }
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
