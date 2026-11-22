<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;"
     x-data="{
        lightboxOpen: false,
        lightboxUrl: '',
        lightboxName: '',
        showCompactSummary: false,
        openLightbox(url, name) {
            this.lightboxUrl = url;
            this.lightboxName = name || 'Vorschau';
            this.lightboxOpen = true;
        }
     }"
     @keydown.escape.window="lightboxOpen = false"
     class="w-full min-h-[calc(100dvh-5rem)] flex flex-col gap-3 px-3 sm:px-6 pb-6 pt-2 font-sans text-gray-900 dark:text-white">

    @php
        $stats = $this->headerStats;
    @endphp

    {{-- ZEILE 1: ULTRA-CLEANE BEFEHLS- & ANSICHTSLEISTE (0 Subtitles) --}}
    <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 shadow-xs flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-lg sm:text-xl font-black tracking-tight text-gray-900 dark:text-white">Tickets</h1>

            <span class="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold uppercase border {{ $stats['environment_tier'] === 'LIVE' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700' }}"
                  title="Aktive Systemumgebung">
                {{ $stats['environment_tier'] }}
            </span>

            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-bold border {{ $stats['notebook_online'] ? 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/30' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700' }}"
                  title="Kopplungsstatus der lokalen Antigravity-Entwicklereinheit">
                <span class="w-1.5 h-1.5 rounded-full {{ $stats['notebook_online'] ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                Antigravity {{ $stats['notebook_online'] ? 'Online' : 'Standby' }}
            </span>

            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-mono font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700"
                  title="Geschätzte Vertex AI Inferenzkosten im laufenden Monat (Budget-Richtwert: 50,00 €)">
                {{ $stats['monthly_euro_formatted'] }} / 50 €
            </span>
        </div>

        {{-- Direkte Funkira-Befehlszeile --}}
        <form wire:submit.prevent="submitCommand" class="flex-1 max-w-2xl flex items-center gap-2">
            <div class="relative flex-1 flex items-center">
                <input type="text"
                       wire:model="commandInput"
                       placeholder="Anweisung an Funkira..."
                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-3.5 pr-10 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:bg-white dark:focus:bg-black focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-all" />
                <label class="absolute right-2.5 cursor-pointer text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Beleg oder Bild anhängen">
                    <input type="file" wire:model="commandUploads" multiple accept="image/*,application/pdf" class="hidden" />
                    <x-heroicon-m-paper-clip class="w-4 h-4" />
                </label>
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all shrink-0 cursor-pointer">
                Senden
            </button>
        </form>

        {{-- 1-Klick Umschalter: Tickets | Organigramm | Wissen --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold shrink-0">
            <button type="button"
                    wire:click="setMainView('tickets')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $mainView === 'tickets' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-ticket class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Tickets</span>
                @if($stats['waiting_count'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--theme-color)] text-gray-900 font-black">{{ $stats['waiting_count'] }}</span>
                @endif
            </button>
            <button type="button"
                    wire:click="setMainView('organigramm')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $mainView === 'organigramm' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-building-office-2 class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Organigramm</span>
            </button>
            <button type="button"
                    wire:click="setMainView('knowledge')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 {{ $mainView === 'knowledge' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-book-open class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Wissen</span>
            </button>
        </div>
    </div>

    @if($fastLaneFeedback)
        <div class="bg-white dark:bg-gray-900 border border-[var(--theme-color-50)] rounded-xl px-4 py-2.5 flex items-center justify-between gap-3 text-xs sm:text-sm shadow-xs">
            <div class="flex items-center gap-2 font-medium text-gray-900 dark:text-white">
                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-[var(--theme-color-20)] text-[var(--theme-color)]">0-Token Direkt</span>
                <span>{{ $fastLaneFeedback }}</span>
            </div>
            <button type="button" wire:click="$set('fastLaneFeedback', null)" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                <x-heroicon-m-x-mark class="w-4 h-4" />
            </button>
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- ANSICHT 1: ZENTRALES CHRONOLOGISCHES ABTEILUNGS-TICKETSYSTEM          --}}
    {{-- ===================================================================== --}}
    @if($mainView === 'tickets')
        {{-- ZEILE 2: FLACHE ABTEILUNGS-TABS + MINI-AGENTEN-RINGE + STATUS-FILTER --}}
        <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl px-3.5 py-2.5 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                <button type="button"
                        wire:click="selectDepartment('all')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all {{ $selectedDepartmentId === 'all' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    Alle ({{ $stats['open_count'] }})
                </button>
                @foreach($this->departments as $dept)
                    @php
                        $deptOpen = (int) ($stats['dept_open_counts'][$dept->id] ?? 0);
                    @endphp
                    <button type="button"
                            wire:click="selectDepartment('{{ $dept->id }}')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all flex items-center gap-1.5 {{ $selectedDepartmentId === $dept->id ? 'bg-[var(--theme-color)] text-gray-900 font-black' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                        <span>{{ $dept->name }}</span>
                        @if($deptOpen > 0)
                            <span class="text-[10px] opacity-80">({{ $deptOpen }})</span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex items-center justify-between lg:justify-end gap-3 flex-wrap">
                {{-- Kompakte 28px Agenten-Ringe mit Live-Statuspunkt --}}
                <div class="flex items-center gap-1.5">
                    @foreach($this->visibleAgents as $ag)
                        @php
                            $agState = $stats['agent_status_map'][$ag->id] ?? 'idle';
                            $dotClass = match ($agState) {
                                'waiting' => 'bg-amber-500 animate-pulse',
                                'working' => 'bg-sky-500',
                                default => 'bg-emerald-500',
                            };
                            $isSelectedAg = ($selectedAgentId === $ag->id);
                        @endphp
                        <div class="relative group">
                            <button type="button"
                                    wire:click="toggleAgentFilter('{{ $ag->id }}')"
                                    title="{{ $ag->name }} ({{ $agState === 'waiting' ? 'Wartet auf dich' : ($agState === 'working' ? 'In Arbeit' : 'Bereit') }})"
                                    class="w-7 h-7 rounded-full border flex items-center justify-center overflow-hidden bg-gray-100 dark:bg-gray-800 transition-transform hover:scale-105 {{ $isSelectedAg ? 'ring-2 ring-[var(--theme-color)] border-[var(--theme-color)]' : 'border-gray-300 dark:border-gray-700' }}">
                                @if($ag->profile_picture)
                                    <img src="{{ \Illuminate\Support\Str::startsWith($ag->profile_picture, 'shop/') ? asset($ag->profile_picture) : \Illuminate\Support\Facades\Storage::url($ag->profile_picture) }}"
                                         alt="{{ $ag->name }}"
                                         class="w-full h-full object-cover" />
                                @else
                                    <span class="text-[10px] font-black text-gray-700 dark:text-gray-200">{{ mb_substr($ag->name, 0, 2) }}</span>
                                @endif
                            </button>
                            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border border-white dark:border-gray-900 {{ $dotClass }}"></span>
                        </div>
                    @endforeach
                </div>

                {{-- Status-Filter & Kompakt-Suche --}}
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-[11px] font-bold">
                    <button type="button" wire:click="setStatusFilter('open')" class="px-2.5 py-1 rounded-lg transition-colors {{ $statusFilter === 'open' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Offen</button>
                    <button type="button" wire:click="setStatusFilter('waiting')" class="px-2.5 py-1 rounded-lg transition-colors {{ $statusFilter === 'waiting' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Wartet auf mich</button>
                    <button type="button" wire:click="setStatusFilter('completed')" class="px-2.5 py-1 rounded-lg transition-colors {{ $statusFilter === 'completed' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Erledigt</button>
                    <button type="button" wire:click="setStatusFilter('archived')" class="px-2.5 py-1 rounded-lg transition-colors {{ $statusFilter === 'archived' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">Archiv</button>
                </div>

                <input type="text"
                       wire:model.live.debounce.250ms="search"
                       placeholder="#SF-..."
                       class="w-28 sm:w-36 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
            </div>
        </div>

        {{-- HAUPT-SPLIT: LINKS SCHLANKE CHRONIK, RECHTS AKTIVER TICKET-STREAM --}}
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-4 min-h-[600px]">
            {{-- LINKE SPALTE: SCHLANKE TICKET-LISTE (0 Text-Overload) --}}
            <div class="lg:col-span-4 bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden flex flex-col">
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800/80 max-h-[calc(100dvh-15rem)]">
                    @forelse($this->tickets as $item)
                        @php
                            $isSelected = ($activeTicketId === $item->id);
                            $statusBadgeClass = match ($item->status) {
                                'waiting_for_alina' => 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border-amber-500/30',
                                'waiting_for_tresor' => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 border-emerald-500/30',
                                'waiting_for_notebook' => 'bg-indigo-500/15 text-indigo-800 dark:text-indigo-300 border-indigo-500/30',
                                'completed' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                                'archived' => 'bg-gray-500/10 text-gray-500 border-gray-500/20',
                                default => 'bg-sky-500/15 text-sky-800 dark:text-sky-300 border-sky-500/30',
                            };
                        @endphp
                        <button type="button"
                                wire:click="selectTicket('{{ $item->id }}')"
                                class="w-full text-left px-4 py-3 transition-colors flex flex-col gap-1.5 cursor-pointer {{ $isSelected ? 'bg-[var(--theme-color-10)] border-l-4 border-l-[var(--theme-color)]' : 'hover:bg-gray-50 dark:hover:bg-gray-800/60' }}">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono text-[11px] font-black text-[var(--theme-color)] shrink-0">{{ $item->ticket_number }}</span>
                                    @if($item->department)
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 truncate">{{ $item->department->name }}</span>
                                    @endif
                                    @if($item->occurrence_count > 1)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-red-500/15 text-red-600 dark:text-red-400">{{ $item->occurrence_count }}x</span>
                                    @endif
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border shrink-0 {{ $statusBadgeClass }}">
                                    {{ \App\Models\Ai\AiUniverseTicket::getStatusLabel($item->status) }}
                                </span>
                            </div>

                            <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white truncate">
                                {{ $item->title }}
                            </div>

                            <div class="flex items-center justify-between text-[10px] text-gray-500 dark:text-gray-400">
                                <span class="font-semibold">{{ $item->agent?->name ?? 'Funkira' }} · {{ \App\Models\Ai\AiUniverseTicket::getChannelLabel($item->source_channel) }}</span>
                                <span class="font-mono">{{ ($item->last_activity_at ?? $item->created_at)?->format('d.m. H:i') }}</span>
                            </div>
                        </button>
                    @empty
                        <div class="py-16 px-6 text-center text-gray-400 dark:text-gray-500 text-xs font-medium">
                            Keine Tickets in dieser Ansicht.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- RECHTE SPALTE: CHRONOLOGISCHER TICKET-STREAM & 1-KLICK-FREIGABEN --}}
            <div class="lg:col-span-8 bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden flex flex-col">
                @if($this->activeTicket)
                    @php
                        $ticket = $this->activeTicket;
                        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                    @endphp

                    {{-- Ticket-Kopf --}}
                    <div class="px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3 bg-gray-50/60 dark:bg-gray-950/60">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs font-black text-[var(--theme-color)]">{{ $ticket->ticket_number }}</span>
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $ticket->department?->name ?? 'Leitung' }} · {{ $ticket->agent?->name ?? 'Funkira' }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-gray-200/70 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $ticket->environment_tier }}</span>
                                @if(!empty($payload['ag_item_id']))
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-indigo-500/15 text-indigo-700 dark:text-indigo-300">{{ $payload['ag_item_id'] }}</span>
                                @endif
                                @if(!empty($payload['dismissed']))
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300">Verworfen</span>
                                @endif
                                @if(!empty($ticket->compact_context))
                                    <button type="button"
                                            @click="showCompactSummary = !showCompactSummary"
                                            class="px-2 py-0.5 rounded text-[10px] font-bold bg-[var(--theme-color-20)] text-[var(--theme-color)] cursor-pointer"
                                            title="Automatisch komprimierter Gesprächskontext anzeigen">
                                        Auto-Compact aktiv
                                    </button>
                                @endif
                            </div>
                            <h2 class="text-sm sm:text-base font-black text-gray-900 dark:text-white mt-0.5 truncate">{{ $ticket->title }}</h2>
                        </div>

                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if(!$ticket->assigned_to_antigravity && $ticket->status !== 'completed' && $ticket->status !== 'archived')
                                <button type="button"
                                        wire:click="triggerTicketAction('handoff_antigravity')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 transition-colors cursor-pointer">
                                    An Antigravity übergeben
                                </button>
                            @endif

                            @if($ticket->status !== 'completed')
                                <button type="button"
                                        wire:click="triggerTicketAction('complete')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 transition-colors cursor-pointer">
                                    Erledigt
                                </button>
                            @else
                                <button type="button"
                                        wire:click="triggerTicketAction('reopen')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer">
                                    Öffnen
                                </button>
                            @endif

                            @if($ticket->status !== 'archived')
                                <button type="button"
                                        wire:click="triggerTicketAction('archive')"
                                        class="p-1.5 rounded-xl text-gray-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer"
                                        title="Ticket archivieren">
                                    <x-heroicon-m-archive-box class="w-4 h-4" />
                                </button>
                            @endif
                        </div>
                    </div>

                    @if(!empty($ticket->compact_context))
                        <div x-show="showCompactSummary" x-cloak class="px-5 py-3 bg-gray-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 text-xs font-mono text-gray-600 dark:text-gray-300 whitespace-pre-line">
                            {{ $ticket->compact_context }}
                        </div>
                    @endif

                    {{-- 1-Klick Einkaufs- / Entscheidungs-Banner bei Status Wartet auf Alina --}}
                    @if($ticket->status === 'waiting_for_alina' && !empty($payload['type']))
                        <div class="mx-5 mt-4 p-3.5 rounded-xl border border-[var(--theme-color-50)] bg-[var(--theme-color-10)] flex flex-wrap items-center justify-between gap-3">
                            @if($payload['type'] === 'tresor_procurement')
                                <div class="text-xs space-y-0.5">
                                    <div class="font-black text-gray-900 dark:text-white">
                                        Tresor-Bestellung bereit: {{ $payload['packs'] ?? 1 }}x {{ $payload['item_name'] ?? '' }} ({{ $payload['gross_total_formatted'] ?? '' }} Brutto inkl. Versand)
                                    </div>
                                    <div class="text-gray-600 dark:text-gray-300">
                                        Händler: {{ $payload['supplier_name'] ?? '' }} · Zieladresse: {{ $payload['delivery_address_guard'] ?? '' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            wire:click="triggerTicketAction('approve_procurement')"
                                            class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all cursor-pointer">
                                        Kauf jetzt via Tresor ausführen
                                    </button>
                                </div>
                            @elseif($payload['type'] === 'ag_inbox_blockade')
                                <div class="text-xs space-y-0.5 min-w-0 flex-1">
                                    <div class="font-black text-gray-900 dark:text-white">
                                        Antigravity wartet auf dein Go / deine Entscheidung ({{ $payload['ag_item_id'] ?? '' }})
                                    </div>
                                    @if(!empty($payload['pending_question']))
                                        <div class="text-gray-600 dark:text-gray-300 line-clamp-2">
                                            {{ $payload['pending_question'] }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button"
                                            wire:click="triggerTicketAction('approve_ag_blockade')"
                                            class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all cursor-pointer">
                                        Go erteilen
                                    </button>
                                    <button type="button"
                                            wire:click="triggerTicketAction('dismiss_ag_blockade')"
                                            class="px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-xs font-bold text-rose-600 dark:text-rose-400 hover:border-rose-500 transition-colors cursor-pointer">
                                        Verwerfen
                                    </button>
                                </div>
                            @elseif($payload['type'] === 'antigravity_handoff')
                                <div class="text-xs font-bold text-gray-900 dark:text-white">
                                    Bereit zur Übergabe an Antigravity{{ !empty($payload['missing_capability']) ? ': ' . $payload['missing_capability'] : '' }}
                                </div>
                                <button type="button"
                                        wire:click="triggerTicketAction('handoff_antigravity')"
                                        class="px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all cursor-pointer">
                                    Jetzt an Antigravity übergeben
                                </button>
                            @elseif($payload['type'] === 'quick_choice' && !empty($payload['options']))
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach($payload['options'] as $opt)
                                        <button type="button"
                                                wire:click="triggerTicketAction('quick_option', '{{ addslashes($opt) }}')"
                                                class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-xs font-bold text-gray-900 dark:text-white hover:border-[var(--theme-color)] transition-colors cursor-pointer">
                                            {{ $opt }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Chronologische Nachrichtenliste --}}
                    <div class="flex-1 overflow-y-auto p-5 space-y-3 max-h-[calc(100dvh-23rem)]">
                        @foreach($ticket->messages as $msg)
                            @php
                                $isAlina = ($msg->sender_role === 'alina');
                            @endphp
                            <div class="flex {{ $isAlina ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-2xl rounded-2xl px-4 py-3 border {{ $isAlina ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-30)] text-gray-900 dark:text-white' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100' }}">
                                    <div class="flex items-center justify-between gap-4 mb-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        <span class="font-black uppercase tracking-wider {{ $isAlina ? 'text-[var(--theme-color)]' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $msg->sender_name }} · {{ \App\Models\Ai\AiUniverseTicket::getChannelLabel($msg->channel) }}
                                        </span>
                                        <span class="font-mono">{{ $msg->created_at?->format('d.m. H:i') }}</span>
                                    </div>

                                    <div class="text-xs sm:text-sm leading-relaxed whitespace-pre-line">{{ $msg->content }}</div>

                                    @if(!empty($msg->executed_tools))
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($msg->executed_tools as $toolName)
                                                <span class="px-2 py-0.5 rounded text-[9px] font-mono bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
                                                    ✓ {{ $toolName }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(!empty($msg->attachments) && is_array($msg->attachments))
                                        <div class="mt-2.5 flex flex-wrap gap-2">
                                            @foreach($msg->attachments as $att)
                                                @if(($att['is_image'] ?? '0') === '1')
                                                    <button type="button"
                                                            @click="openLightbox('{{ $att['url'] ?? '' }}', '{{ addslashes($att['name'] ?? 'Anhang') }}')"
                                                            class="group relative w-20 h-20 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 cursor-pointer">
                                                        <img src="{{ $att['url'] ?? '' }}" alt="{{ $att['name'] ?? '' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                                    </button>
                                                @else
                                                    <a href="{{ $att['url'] ?? '#' }}"
                                                       download
                                                       class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-1.5">
                                                        <x-heroicon-m-document-arrow-down class="w-4 h-4 text-[var(--theme-color)]" />
                                                        <span>{{ $att['name'] ?? 'Dokument' }}</span>
                                                    </a>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Antwortzeile im Ticket --}}
                    <form wire:submit.prevent="submitTicketReply" class="p-3.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/60 flex items-center gap-2">
                        <div class="relative flex-1 flex items-center">
                            <input type="text"
                                   wire:model="ticketReplyInput"
                                   placeholder="Antwort zu {{ $ticket->ticket_number }}..."
                                   class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl pl-3.5 pr-10 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                            <label class="absolute right-2.5 cursor-pointer text-gray-400 hover:text-gray-700 dark:hover:text-white" title="Datei anhängen">
                                <input type="file" wire:model="replyUploads" multiple accept="image/*,application/pdf" class="hidden" />
                                <x-heroicon-m-paper-clip class="w-4 h-4" />
                            </label>
                        </div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold text-xs uppercase tracking-wider hover:opacity-90 transition-opacity cursor-pointer">
                            Antworten
                        </button>
                    </form>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center p-10 text-center text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-ticket class="w-10 h-10 mb-2 opacity-50 text-[var(--theme-color)]" />
                        <span class="text-xs font-bold">Ticket links auswählen oder oben direkt Funkira beauftragen.</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- ANSICHT 2: GROSSES SCHICKES KI-ORGANIGRAMM (100 % ERHALTEN!)          --}}
    {{-- ===================================================================== --}}
    @if($mainView === 'organigramm')
        <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-xs">
            @livewire('shop.ai.ai-company-structure', ['embeddedInTickets' => true], key('embedded-organigramm'))
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- ANSICHT 3: FLACHES ABTEILUNGS-WISSEN & GELERNTE PRÄFERENZEN           --}}
    {{-- ===================================================================== --}}
    @if($mainView === 'knowledge')
        <div class="space-y-4">
            @if(count($this->learnedPreferences) > 0)
                <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-xs">
                    <h2 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white mb-3">Gelernte Dauer-Entscheidungen</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2.5">
                        @foreach($this->learnedPreferences as $pref)
                            <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-900 dark:text-white truncate">
                                        {{ $pref->subject_label }}
                                        @if($pref->department)
                                            <span class="text-[10px] font-mono text-[var(--theme-color)]">({{ $pref->department->name }})</span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $pref->rule_summary }}</div>
                                </div>
                                @if(\App\Services\AI\AiAuthHelper::isAdmin())
                                    <button type="button"
                                            wire:click="deleteLearnedPreference('{{ $pref->id }}')"
                                            class="p-1 text-gray-400 hover:text-red-500 shrink-0 cursor-pointer"
                                            title="Eintrag entfernen">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-xs min-h-[540px]">
                @livewire('shop.ai.ai-knowledge-base', [], key('embedded-knowledge-base'))
            </div>
        </div>
    @endif

    {{-- LIGHTBOX-MODAL FÜR BILD-ANHÄNGE & BELEGE (Baustein 1: Kein neuer Browser-Tab!) --}}
    <div x-show="lightboxOpen"
         x-cloak
         style="display: none;"
         class="fixed inset-0 z-[120] bg-black/80 backdrop-blur-xs flex items-center justify-center p-4"
         @click.self="lightboxOpen = false">
        <div class="relative max-w-4xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
            <div class="px-4 py-2.5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="lightboxName"></span>
                <button type="button" @click="lightboxOpen = false" class="p-1 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-white">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>
            <div class="p-4 flex items-center justify-center bg-gray-100 dark:bg-black max-h-[80vh] overflow-auto">
                <img :src="lightboxUrl" :alt="lightboxName" class="max-h-[74vh] w-auto object-contain rounded-lg" />
            </div>
        </div>
    </div>
</div>
