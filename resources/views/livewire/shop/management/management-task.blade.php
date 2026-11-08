<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"
     class="flex flex-col h-[95vh] md:h-[calc(100vh-10rem)] bg-white/95 dark:bg-gray-900/90 backdrop-blur-xl rounded-2xl md:rounded-[2.5rem] dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 overflow-hidden min-h-[500px] md:min-h-[700px] animate-fade-in-up transition-colors duration-300">

    @php
        $headerTitle = $smartMeta['name'] ?? ($activeList->name ?? 'Aufgaben');
        $headerSubtitle = $smartMeta['subtitle'] ?? null;
    @endphp

    {{-- MAIN 3-PANE CONTAINER --}}
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-0 overflow-hidden min-h-0">

        {{-- LEFT SIDEBAR: SEARCH, SMART LISTS, CUSTOM LISTS --}}
        <aside class="lg:col-span-3 xl:col-span-3 bg-gray-50/80 dark:bg-gray-950/50 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-800 flex flex-col justify-between p-4 sm:p-5 select-none overflow-hidden">
            <div class="space-y-4 flex-1 flex flex-col min-h-0">

                {{-- Search Bar --}}
                <div class="relative shrink-0">
                    <input type="text"
                           wire:model.live.debounce.250ms="searchQuery"
                           placeholder="Aufgaben durchsuchen..."
                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] rounded-xl pl-9 pr-8 py-2.5 text-xs sm:text-sm font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none transition-all">
                    <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    @if($searchQuery !== '')
                        <button wire:click="$set('searchQuery', '')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                {{-- Smart Lists (Mein Tag, Wichtig, Geplant, Alle Aufgaben) --}}
                <nav class="space-y-1 shrink-0">
                    <button wire:click="selectList('my_day')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activeListId === 'my_day' && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                            </svg>
                            <span>Mein Tag</span>
                        </span>
                        @if(($smartCounts['my_day'] ?? 0) > 0)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold">{{ $smartCounts['my_day'] }}</span>
                        @endif
                    </button>

                    <button wire:click="selectList('important')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activeListId === 'important' && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                            </svg>
                            <span>Wichtig</span>
                        </span>
                        @if(($smartCounts['important'] ?? 0) > 0)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold">{{ $smartCounts['important'] }}</span>
                        @endif
                    </button>

                    <button wire:click="selectList('planned')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activeListId === 'planned' && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            <span>Geplant</span>
                        </span>
                        @if(($smartCounts['planned'] ?? 0) > 0)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold">{{ $smartCounts['planned'] }}</span>
                        @endif
                    </button>

                    <button wire:click="selectList('all')"
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-all border {{ $activeListId === 'all' && $searchQuery === '' ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            <span>Alle Aufgaben</span>
                        </span>
                        @if(($smartCounts['all'] ?? 0) > 0)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] font-bold">{{ $smartCounts['all'] }}</span>
                        @endif
                    </button>
                </nav>

                <div class="border-t border-gray-200 dark:border-gray-800 my-1 shrink-0"></div>

                {{-- Custom User Lists --}}
                <div class="space-y-1 flex-1 overflow-y-auto pr-1 custom-scrollbar min-h-0">
                    @foreach($lists as $list)
                        @php
                            $isSelected = ($activeListId === $list->id && $searchQuery === '');
                        @endphp
                        <div class="group relative rounded-xl transition-all border {{ $isSelected ? 'bg-[var(--theme-color-15)] text-[var(--theme-color)] border-[var(--theme-color-30)] font-bold shadow-xs' : 'border-transparent font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-900/80' }}">
                            @if($editingListId === $list->id)
                                <form wire:submit.prevent="updateList" class="p-2 flex items-center gap-2">
                                    <input type="text" wire:model="editingListName" class="w-full bg-white dark:bg-gray-950 border border-[var(--theme-color)] rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white focus:outline-none" autofocus>
                                    <button type="submit" class="text-emerald-600 dark:text-emerald-400 hover:opacity-80 p-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    <button type="button" wire:click="cancelEditList" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </form>
                            @else
                                <div class="flex items-center justify-between px-3 py-2.5 cursor-pointer" wire:click="selectList('{{ $list->id }}')">
                                    <div class="flex items-center gap-3 min-w-0 pr-2">
                                        @if($list->name === 'Aufgaben')
                                            <svg class="w-5 h-5 shrink-0 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                            </svg>
                                        @else
                                            <svg class="w-5 h-5 shrink-0 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                        @endif
                                        <span class="text-sm truncate">{{ $list->name }}</span>
                                    </div>

                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <div class="hidden group-hover:flex items-center gap-1 mr-1">
                                            <button type="button" wire:click.stop="startEditList('{{ $list->id }}', '{{ addslashes($list->name) }}')" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 rounded" title="Liste umbenennen">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                            </button>
                                            @if($lists->count() > 1)
                                                <button type="button" wire:click.stop="deleteList('{{ $list->id }}')" wire:confirm="Liste '{{ $list->name }}' samt Aufgaben wirklich löschen?" class="text-gray-400 hover:text-red-500 p-1 rounded" title="Liste löschen">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                        @if($list->open_tasks_count > 0)
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $isSelected ? 'bg-[var(--theme-color-20)] text-[var(--theme-color)] font-bold' : 'bg-gray-200/70 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-semibold' }}">
                                                {{ $list->open_tasks_count }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Bottom Bar: + Neue Liste --}}
            <div class="pt-3 border-t border-gray-200 dark:border-gray-800 mt-3 shrink-0">
                <form wire:submit.prevent="createList" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text"
                               wire:model="newListName"
                               placeholder="Neue Liste erstellen..."
                               class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl pl-9 pr-3 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none transition-all">
                        <svg class="w-4 h-4 text-[var(--theme-color)] absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                    </div>
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-[var(--theme-color)] hover:brightness-110 text-gray-900 text-xs font-bold transition-all shrink-0 shadow-xs">
                        Anlegen
                    </button>
                </form>
            </div>
        </aside>

        {{-- CENTER PANEL: TASK LIST VIEW --}}
        <main class="{{ $selectedTask ? 'lg:col-span-5 xl:col-span-6' : 'lg:col-span-9 xl:col-span-9' }} flex flex-col justify-between p-4 sm:p-6 lg:p-8 min-h-0 overflow-hidden transition-all">

            <div class="flex-1 flex flex-col min-h-0">
                {{-- Header --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5 pb-4 border-b border-gray-200/80 dark:border-gray-800 shrink-0">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2.5">
                            <span class="w-2.5 h-6 rounded-full bg-[var(--theme-color)] inline-block"></span>
                            <span>{{ $headerTitle }}</span>
                        </h1>
                        @if($headerSubtitle)
                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 pl-5">{{ $headerSubtitle }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-950/80 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-800">
                            {{ $openTasks->count() }} offen · {{ $completedTasks->count() }} erledigt
                        </span>
                    </div>
                </div>

                {{-- Tasks Scrollable Area --}}
                <div class="flex-1 overflow-y-auto space-y-2 pr-1 custom-scrollbar min-h-0">

                    {{-- OPEN TASKS --}}
                    @forelse($openTasks as $task)
                        @php
                            $subCount = $task->subtasks->count();
                            $subDone = $task->subtasks->where('is_completed', true)->count();
                            $isStarred = $task->priority === 'hoch';
                            $isMyDay = $task->relevant_from && $task->relevant_from->isToday();
                            $isSelectedTask = $selectedTaskId === $task->id;
                            $fileCount = is_array($task->file_paths) ? count($task->file_paths) : 0;
                        @endphp

                        <div wire:key="open-task-{{ $task->id }}"
                             wire:click="selectTask('{{ $task->id }}')"
                             class="group flex items-center justify-between gap-3 px-4 py-3.5 rounded-2xl border transition-all cursor-pointer {{ $isSelectedTask ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)] shadow-sm' : 'bg-gray-50/90 hover:bg-gray-100/80 dark:bg-gray-950/60 dark:hover:bg-gray-800/70 border-gray-200/80 dark:border-gray-800/90' }}">

                            <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                {{-- Round Checkbox --}}
                                <button type="button"
                                        wire:click.stop="toggleTask('{{ $task->id }}')"
                                        class="mt-0.5 w-5 h-5 rounded-full border-2 border-gray-400 dark:border-gray-500 hover:border-[var(--theme-color)] flex items-center justify-center transition-all shrink-0 group/chk">
                                    <svg class="w-3 h-3 text-[var(--theme-color)] opacity-0 group-hover/chk:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div class="text-sm sm:text-[15px] font-semibold text-gray-900 dark:text-gray-100 leading-snug break-words">
                                        {{ $task->title }}
                                    </div>

                                    {{-- Subtitle Metadata Row --}}
                                    @if($subCount > 0 || $isMyDay || $task->relevant_from || !empty($task->ai_plan) || $fileCount > 0 || in_array($activeListId, ['my_day', 'important', 'planned', 'all'], true))
                                        <div class="flex flex-wrap items-center gap-2.5 mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if(in_array($activeListId, ['my_day', 'important', 'planned', 'all'], true) && $task->list)
                                                <span class="font-medium text-gray-600 dark:text-gray-300">{{ $task->list->name }}</span>
                                            @endif

                                            @if($subCount > 0)
                                                <span class="inline-flex items-center gap-1 {{ $subDone === $subCount ? 'text-emerald-600 dark:text-emerald-400 font-semibold' : '' }}">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    {{ $subDone }} von {{ $subCount }}
                                                </span>
                                            @endif

                                            @if($isMyDay && $activeListId !== 'my_day')
                                                <span class="inline-flex items-center gap-1 text-[var(--theme-color)] font-semibold">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                                                    Mein Tag
                                                </span>
                                            @elseif($task->relevant_from)
                                                <span class="inline-flex items-center gap-1 {{ $task->relevant_from->isPast() && !$task->relevant_from->isToday() ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-emerald-600 dark:text-emerald-400' }}">
                                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                                    {{ $task->relevant_from->format('d.m.Y') }}
                                                </span>
                                            @endif

                                            @if(!empty($task->ai_plan))
                                                <span class="inline-flex items-center gap-1" title="Notiz vorhanden">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                    Notiz
                                                </span>
                                            @endif

                                            @if($fileCount > 0)
                                                <span class="inline-flex items-center gap-1 text-[var(--theme-color)] font-semibold">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                                                    {{ $fileCount }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Star Button on the Right --}}
                            <button type="button"
                                    wire:click.stop="toggleImportant('{{ $task->id }}')"
                                    class="p-1.5 rounded-lg transition-colors shrink-0 {{ $isStarred ? 'text-[var(--theme-color)]' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-200' }}"
                                    title="Als wichtig markieren">
                                @if($isStarred)
                                    <svg class="w-5 h-5 fill-current text-[var(--theme-color)]" viewBox="0 0 24 24">
                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                    </svg>
                                @endif
                            </button>
                        </div>
                    @empty
                        @if($completedTasks->isEmpty())
                            <div class="flex flex-col items-center justify-center py-16 text-center">
                                <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center mb-4">
                                    <svg class="w-8 h-8 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                                <p class="text-base font-bold text-gray-800 dark:text-gray-200">Keine offenen Aufgaben</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Füge unten eine neue Aufgabe hinzu.</p>
                            </div>
                        @endif
                    @endforelse

                    {{-- COLLAPSIBLE "ERLEDIGT (X)" SECTION --}}
                    @if($completedTasks->count() > 0)
                        <div class="pt-3">
                            <button type="button"
                                    wire:click="toggleCompletedSection"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs sm:text-sm font-bold text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-15)] border border-[var(--theme-color-20)] transition-colors">
                                <svg class="w-4 h-4 transition-transform duration-200 {{ $showCompletedSection ? 'rotate-0' : '-rotate-90' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                                <span>Erledigt</span>
                                <span class="opacity-80">({{ $completedTasks->count() }})</span>
                            </button>

                            @if($showCompletedSection)
                                <div class="space-y-1.5 mt-2.5">
                                    @foreach($completedTasks as $task)
                                        @php
                                            $subCount = $task->subtasks->count();
                                            $subDone = $task->subtasks->where('is_completed', true)->count();
                                            $isStarred = $task->priority === 'hoch';
                                            $isSelectedTask = $selectedTaskId === $task->id;
                                        @endphp
                                        <div wire:key="done-task-{{ $task->id }}"
                                             wire:click="selectTask('{{ $task->id }}')"
                                             class="group flex items-center justify-between gap-3 px-4 py-3 rounded-2xl border transition-all cursor-pointer {{ $isSelectedTask ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)]' : 'bg-gray-50/60 hover:bg-gray-100/70 dark:bg-gray-950/40 dark:hover:bg-gray-900/70 border-gray-200/60 dark:border-gray-800/60' }}">

                                            <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                                {{-- Filled Circle Checkmark --}}
                                                <button type="button"
                                                        wire:click.stop="toggleTask('{{ $task->id }}')"
                                                        class="mt-0.5 w-5 h-5 rounded-full bg-[var(--theme-color)] flex items-center justify-center transition-all shrink-0">
                                                    <svg class="w-3.5 h-3.5 text-gray-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </button>

                                                <div class="min-w-0 flex-1">
                                                    <div class="text-sm sm:text-[15px] text-gray-400 dark:text-gray-500 line-through leading-snug break-words">
                                                        {{ $task->title }}
                                                    </div>
                                                    @if($subCount > 0)
                                                        <div class="flex items-center gap-1.5 mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            <span>{{ $subDone }} von {{ $subCount }}</span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <button type="button"
                                                    wire:click.stop="toggleImportant('{{ $task->id }}')"
                                                    class="p-1.5 rounded-lg transition-colors shrink-0 {{ $isStarred ? 'text-[var(--theme-color)]' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300' }}">
                                                @if($isStarred)
                                                    <svg class="w-5 h-5 fill-current text-[var(--theme-color)]" viewBox="0 0 24 24">
                                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                                                    </svg>
                                                @endif
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- BOTTOM-DOCKED "+ AUFGABE HINZUFÜGEN" BAR --}}
            <form wire:submit.prevent="addTask" class="mt-4 pt-2 shrink-0">
                <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-950/90 border-2 border-gray-200 dark:border-gray-800 focus-within:border-[var(--theme-color)] rounded-2xl px-4 py-2.5 shadow-sm transition-all">
                    <svg class="w-5 h-5 shrink-0 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    <input type="text"
                           wire:model="newTaskTitle"
                           placeholder="Aufgabe hinzufügen..."
                           class="w-full bg-transparent text-sm sm:text-base text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none">

                    <input type="date"
                           wire:model="newTaskRelevantFrom"
                           title="Fälligkeitsdatum wählen"
                           class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 text-xs text-gray-700 dark:text-gray-300 focus:outline-none hidden sm:block">

                    <button type="button"
                            wire:click="$set('newTaskPriority', '{{ $newTaskPriority === 'hoch' ? 'niedrig' : 'hoch' }}')"
                            title="Als wichtig markieren"
                            class="p-1.5 rounded-lg {{ $newTaskPriority === 'hoch' ? 'text-[var(--theme-color)]' : 'text-gray-400 hover:text-gray-700 dark:hover:text-white' }}">
                        <svg class="w-5 h-5 {{ $newTaskPriority === 'hoch' ? 'fill-current' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                        </svg>
                    </button>

                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-[var(--theme-color)] text-gray-900 hover:brightness-110 transition-all shrink-0 shadow-xs">
                        Hinzufügen
                    </button>
                </div>
            </form>
        </main>

        {{-- RIGHT SIDEBAR: TASK DETAIL INSPECTOR --}}
        @if($selectedTask)
            @php
                $isStarredDetail = $selectedTask->priority === 'hoch';
                $isMyDayDetail = $selectedTask->relevant_from && $selectedTask->relevant_from->isToday();
                $detailFiles = $selectedTask->file_paths ?? [];
            @endphp
            <aside class="lg:col-span-4 xl:col-span-3 bg-gray-50/80 dark:bg-gray-950/50 border-t lg:border-t-0 lg:border-l border-gray-200 dark:border-gray-800 flex flex-col justify-between p-4 sm:p-5 min-h-0 overflow-hidden">
                <div class="space-y-4 overflow-y-auto pr-1 custom-scrollbar flex-1 min-h-0">

                    {{-- Top Bar --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aufgabendetails</span>
                        <button type="button" wire:click="closeDetailPanel" class="p-1.5 rounded-xl text-gray-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-200/60 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Title + Steps Card --}}
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 space-y-3 border border-gray-200 dark:border-gray-800 shadow-xs">
                        <div class="flex items-start gap-3">
                            <button type="button"
                                    wire:click="toggleTask('{{ $selectedTask->id }}')"
                                    class="mt-1 w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 {{ $selectedTask->is_completed ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-500' }}">
                                @if($selectedTask->is_completed)
                                    <svg class="w-3.5 h-3.5 text-gray-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </button>

                            <input type="text"
                                   wire:model.blur="detailTitle"
                                   class="w-full bg-transparent text-sm sm:text-base font-bold text-gray-900 dark:text-white focus:outline-none {{ $selectedTask->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">

                            <button type="button" wire:click="toggleImportant('{{ $selectedTask->id }}')" class="mt-0.5 shrink-0">
                                @if($isStarredDetail)
                                    <svg class="w-5 h-5 fill-current text-[var(--theme-color)]" viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                                @else
                                    <svg class="w-5 h-5 text-gray-400 hover:text-gray-700 dark:hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                                @endif
                            </button>
                        </div>

                        {{-- Subtasks / Steps --}}
                        <div class="space-y-2 pt-2 border-t border-gray-200 dark:border-gray-800">
                            @foreach($selectedTask->subtasks as $sub)
                                <div wire:key="detail-sub-{{ $sub->id }}" class="flex items-start justify-between gap-2 py-1.5 group/sub">
                                    <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                        <button type="button"
                                                wire:click="toggleTask('{{ $sub->id }}')"
                                                class="mt-0.5 w-4 h-4 rounded-full border flex items-center justify-center shrink-0 {{ $sub->is_completed ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : 'border-gray-400 dark:border-gray-500' }}">
                                            @if($sub->is_completed)
                                                <svg class="w-2.5 h-2.5 text-gray-950" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                        <span class="text-xs sm:text-sm break-words leading-snug {{ $sub->is_completed ? 'line-through text-gray-400 dark:text-gray-500' : 'text-gray-800 dark:text-gray-200' }}">
                                            {{ $sub->title }}
                                        </span>
                                    </div>
                                    <button type="button" wire:click="deleteTask('{{ $sub->id }}')" class="opacity-0 group-hover/sub:opacity-100 text-gray-400 hover:text-red-500 p-1 shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            @endforeach

                            <form wire:submit.prevent="addSubtask('{{ $selectedTask->id }}')" class="flex items-center gap-2 pt-1">
                                <svg class="w-4 h-4 shrink-0 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                <input type="text"
                                       wire:model="newSubtaskTitle"
                                       placeholder="{{ $selectedTask->subtasks->count() > 0 ? 'Nächster Schritt...' : 'Schritt hinzufügen...' }}"
                                       class="w-full bg-transparent text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none">
                            </form>
                        </div>
                    </div>

                    {{-- Add to "Mein Tag" Button --}}
                    <button type="button"
                            wire:click="toggleMyDay('{{ $selectedTask->id }}')"
                            class="w-full flex items-center justify-between px-4 py-3 rounded-2xl border transition-all text-sm font-semibold {{ $isMyDayDetail ? 'bg-[var(--theme-color-15)] border-[var(--theme-color-40)] text-[var(--theme-color)]' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color-40)]' }}">
                        <span class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/>
                            </svg>
                            <span>{{ $isMyDayDetail ? 'Zu "Mein Tag" hinzugefügt' : 'Zu "Mein Tag" hinzufügen' }}</span>
                        </span>
                        @if($isMyDayDetail)
                            <span class="text-xs font-bold text-[var(--theme-color)]">Aktiv</span>
                        @endif
                    </button>

                    {{-- Due Date & List Selector --}}
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 space-y-3 border border-gray-200 dark:border-gray-800 shadow-xs">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Fälligkeitsdatum</label>
                            <input type="date"
                                   wire:model.live="detailRelevantFrom"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Liste</label>
                            <select wire:model.live="detailTaskListId"
                                    class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none">
                                @foreach($lists as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Note / AI Plan --}}
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 border border-gray-200 dark:border-gray-800 shadow-xs">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Notiz</label>
                        <textarea wire:model.blur="detailAiPlan"
                                  rows="4"
                                  placeholder="Notiz hinzufügen..."
                                  class="w-full bg-transparent text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none resize-y"></textarea>
                    </div>

                    {{-- File Attachments --}}
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-4 space-y-3 border border-gray-200 dark:border-gray-800 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Dateianhänge</span>
                            <label class="cursor-pointer inline-flex items-center gap-1.5 text-xs font-bold text-[var(--theme-color)] hover:opacity-80">
                                <input type="file" wire:model="taskUploads.{{ $selectedTask->id }}" multiple class="hidden">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                Datei hinzufügen
                            </label>
                        </div>

                        @if(count($detailFiles) > 0)
                            <div class="space-y-1.5">
                                @foreach($detailFiles as $filePath)
                                    <div class="flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 px-3 py-2 rounded-xl text-xs">
                                        <button type="button" wire:click="downloadFile('{{ $filePath }}')" class="text-gray-700 dark:text-gray-300 hover:text-[var(--theme-color)] truncate text-left flex-1 font-medium">
                                            📎 {{ basename($filePath) }}
                                        </button>
                                        <button type="button" wire:click="removeFile('{{ $selectedTask->id }}', '{{ $filePath }}')" class="text-gray-400 hover:text-red-500">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-400 dark:text-gray-500">Keine Dateien angehängt.</p>
                        @endif
                    </div>
                </div>

                {{-- Footer --}}
                <div class="pt-3 mt-3 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 shrink-0">
                    <span>Erstellt {{ $selectedTask->created_at ? $selectedTask->created_at->format('d.m.Y') : '' }}</span>
                    <button type="button"
                            wire:click="deleteTask('{{ $selectedTask->id }}')"
                            wire:confirm="Aufgabe wirklich löschen?"
                            class="text-gray-400 hover:text-red-500 p-1.5 rounded-lg transition-colors"
                            title="Aufgabe löschen">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </aside>
        @endif

    </div>
</div>
