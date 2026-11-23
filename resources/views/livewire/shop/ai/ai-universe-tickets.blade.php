<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;"
     x-data="{
        lightboxOpen: false,
        lightboxUrl: '',
        lightboxName: '',
        showCompactSummary: false,
        showTicketFilesDrawer: false,
        holoOpen: false,
        holoName: '',
        holoUrl: '',
        holoExt: '',
        holoFwUri: '',
        holoMode: 'hologram',
        holoTurntable: true,
        holoBed: true,
        holoLoading: false,
        holoStats: null,
        openLightbox(url, name) {
            this.lightboxUrl = url;
            this.lightboxName = name || 'Vorschau';
            this.lightboxOpen = true;
        },
        openHologram(url, name, ext, fwUri) {
            this.holoUrl = url;
            this.holoName = name || '3D-Modell';
            this.holoExt = ext || 'stl';
            this.holoFwUri = fwUri || '';
            this.holoStats = null;
            this.holoLoading = true;
            this.holoOpen = true;
            this.$nextTick(() => {
                import('/vendor/funkenwerk-3d/ticket-hologram.js').then((mod) => {
                    const el = this.$refs.holoViewport;
                    if (!el) return;
                    mod.openHologramModel(el, this.holoUrl, this.holoExt, (stats) => {
                        this.holoStats = stats;
                        this.holoLoading = false;
                    }).catch(() => {
                        this.holoLoading = false;
                    });
                });
            });
        },
        closeHologram() {
            this.holoOpen = false;
            if (window.SfTicketHologram) {
                window.SfTicketHologram.stopHologramLoop();
            }
        },
        toggleHoloStyle(style) {
            this.holoMode = style;
            if (window.SfTicketHologram) window.SfTicketHologram.setHologramStyle(style);
        },
        toggleHoloTurntable() {
            this.holoTurntable = !this.holoTurntable;
            if (window.SfTicketHologram) window.SfTicketHologram.setHologramTurntable(this.holoTurntable);
        },
        toggleHoloBed() {
            this.holoBed = !this.holoBed;
            if (window.SfTicketHologram) window.SfTicketHologram.setHologramBedVisible(this.holoBed);
        }
     }"
     @keydown.escape.window="lightboxOpen = false; closeHologram(); $wire.closeConceptModal()"
     class="w-full min-h-[calc(100dvh-5rem)] flex flex-col gap-3 px-3 sm:px-6 pb-6 pt-2 font-sans text-gray-900 dark:text-white">

    @php
        $stats = $this->headerStats;
        $mentionAgents = $this->mentionableAgents;
    @endphp

    @once
        <script>
            window.sfTicketComposer = function (wireModelField, submitAction, agentsList) {
                return {
                    agents: Array.isArray(agentsList) ? agentsList : [],
                    mentionOpen: false,
                    mentionQuery: '',
                    mentionStart: -1,
                    mentionIndex: 0,
                    isSubmitting: false,

                    get filteredAgents() {
                        const q = (this.mentionQuery || '').trim().toLowerCase();
                        if (!q) return this.agents;
                        return this.agents.filter((ag) => {
                            const name = String(ag.name || '').toLowerCase();
                            const compact = name.replace(/[\s.]+/g, '');
                            const dept = String(ag.dept || '').toLowerCase();
                            return name.includes(q) || compact.includes(q) || dept.includes(q);
                        });
                    },

                    autoGrow(el) {
                        if (!el) return;
                        el.style.height = 'auto';
                        const next = Math.min(Math.max(el.scrollHeight, 38), 152);
                        el.style.height = next + 'px';
                    },

                    detectMention(el) {
                        if (!el) return;
                        const val = el.value || '';
                        const caret = typeof el.selectionStart === 'number' ? el.selectionStart : val.length;
                        const before = val.slice(0, caret);
                        const match = before.match(/(?:^|[\s(\[{"',;:])@([A-Za-zÄÖÜäöüß0-9.\s]{0,22})$/u);
                        if (!match) {
                            this.mentionOpen = false;
                            this.mentionStart = -1;
                            return;
                        }
                        const query = match[1] || '';
                        if (query.includes('\n')) {
                            this.mentionOpen = false;
                            this.mentionStart = -1;
                            return;
                        }
                        const atPos = before.lastIndexOf('@');
                        this.mentionStart = atPos;
                        this.mentionQuery = query;
                        this.mentionIndex = 0;
                        this.mentionOpen = this.filteredAgents.length > 0;
                    },

                    onInput(e) {
                        this.autoGrow(e.target);
                        this.detectMention(e.target);
                    },

                    openMentionPicker() {
                        const el = this.$refs.composerInput;
                        if (!el) return;
                        el.focus();
                        const val = el.value || '';
                        const start = typeof el.selectionStart === 'number' ? el.selectionStart : val.length;
                        const end = typeof el.selectionEnd === 'number' ? el.selectionEnd : val.length;
                        const prevChar = start > 0 ? val.charAt(start - 1) : '';
                        const prefix = (start > 0 && !/\s/.test(prevChar) && prevChar !== '@') ? ' @' : (prevChar === '@' ? '' : '@');
                        if (prefix !== '') {
                            const updated = val.slice(0, start) + prefix + val.slice(end);
                            el.value = updated;
                            const nextCaret = start + prefix.length;
                            el.setSelectionRange(nextCaret, nextCaret);
                            el.dispatchEvent(new Event('input', { bubbles: true }));
                        } else {
                            this.detectMention(el);
                        }
                    },

                    selectMention(ag) {
                        const el = this.$refs.composerInput;
                        if (!el || !ag) return;
                        const val = el.value || '';
                        const caret = typeof el.selectionStart === 'number' ? el.selectionStart : val.length;
                        const startPos = this.mentionStart >= 0 ? this.mentionStart : caret;
                        const insertText = '@' + ag.name + ' ';
                        const updated = val.slice(0, startPos) + insertText + val.slice(caret);
                        el.value = updated;
                        const nextCaret = startPos + insertText.length;
                        el.focus();
                        el.setSelectionRange(nextCaret, nextCaret);
                        this.mentionOpen = false;
                        this.mentionStart = -1;
                        this.mentionQuery = '';
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        this.autoGrow(el);
                    },

                    onKeydown(e) {
                        if (this.mentionOpen && this.filteredAgents.length > 0) {
                            if (e.key === 'ArrowDown') {
                                e.preventDefault();
                                this.mentionIndex = (this.mentionIndex + 1) % this.filteredAgents.length;
                                return;
                            }
                            if (e.key === 'ArrowUp') {
                                e.preventDefault();
                                this.mentionIndex = (this.mentionIndex - 1 + this.filteredAgents.length) % this.filteredAgents.length;
                                return;
                            }
                            if ((e.key === 'Enter' && !e.shiftKey) || e.key === 'Tab') {
                                e.preventDefault();
                                const chosen = this.filteredAgents[this.mentionIndex] || this.filteredAgents[0];
                                if (chosen) {
                                    this.selectMention(chosen);
                                }
                                return;
                            }
                            if (e.key === 'Escape') {
                                e.preventDefault();
                                this.mentionOpen = false;
                                return;
                            }
                        }

                        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                            e.preventDefault();
                            this.triggerSubmit();
                        }
                    },

                    triggerSubmit() {
                        const el = this.$refs.composerInput;
                        if (this.isSubmitting) return;
                        const currentVal = el ? el.value : '';
                        this.mentionOpen = false;
                        this.isSubmitting = true;
                        this.$wire.set(wireModelField, currentVal, false);
                        Promise.resolve(this.$wire[submitAction]()).finally(() => {
                            this.isSubmitting = false;
                            if (el) {
                                el.value = '';
                                el.style.height = '38px';
                            }
                        });
                    }
                };
            };
        </script>
    @endonce

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

        {{-- Direkte Funkira-Befehlszeile (Enter = Senden, Shift+Enter = Umbruch, @ = Agenten inkl. Antigravity) --}}
        <form @submit.prevent="triggerSubmit()"
              x-data="sfTicketComposer('commandInput', 'submitCommand', @js($mentionAgents))"
              @click.outside="mentionOpen = false"
              class="flex-1 max-w-2xl flex items-end gap-2">
            <div class="relative flex-1 flex items-end">
                <textarea rows="1"
                          x-ref="composerInput"
                          wire:model="commandInput"
                          @input="onInput($event)"
                          @keydown="onKeydown($event)"
                          @click="detectMention($event.target)"
                          placeholder="Anweisung an Funkira oder @Agent..."
                          title="Enter zum Senden, Shift+Enter für Zeilenumbruch, @ für Agenten-Auswahl"
                          style="height: 38px;"
                          class="w-full resize-none overflow-y-auto bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-3.5 pr-16 py-2 text-xs sm:text-sm leading-snug text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:bg-white dark:focus:bg-black focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors"></textarea>

                <div class="absolute right-2.5 bottom-2 flex items-center gap-1.5">
                    <button type="button"
                            @click="openMentionPicker()"
                            title="Agent oder @Antigravity einbeziehen"
                            class="text-gray-400 hover:text-[var(--theme-color)] dark:hover:text-white font-black text-sm leading-none px-1 cursor-pointer transition-colors">
                        @
                    </button>
                    <label class="cursor-pointer text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Datei, Bild oder 3D-Modell anhängen">
                        <input type="file" wire:model="commandUploads" multiple accept="image/*,application/pdf,.stl,.3mf,.glb,.obj,.md,.txt,.docx,.csv,.xcs,.zip" class="hidden" />
                        <x-heroicon-m-paper-clip class="w-4 h-4" />
                    </label>
                </div>

                {{-- @-Mention Autocomplete Dropdown (nach unten öffnend) --}}
                <div x-show="mentionOpen && filteredAgents.length > 0"
                     x-cloak
                     style="display: none;"
                     class="absolute left-0 right-0 top-full mt-1.5 z-50 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                    <template x-for="(ag, idx) in filteredAgents" :key="ag.id">
                        <button type="button"
                                @mousedown.prevent="selectMention(ag)"
                                @mouseenter="mentionIndex = idx"
                                :class="mentionIndex === idx ? 'bg-[var(--theme-color-20)] text-gray-900 dark:text-white' : 'text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/80'"
                                class="w-full px-3 py-2 flex items-center justify-between gap-2 text-left text-xs transition-colors cursor-pointer">
                            <span class="flex items-center gap-2 min-w-0">
                                <img :src="ag.avatar" :alt="ag.name" class="w-6 h-6 rounded-full object-cover border border-[#C5A059]/60 shrink-0" />
                                <span class="font-bold truncate" x-text="'@' + ag.name"></span>
                            </span>
                            <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 shrink-0" x-text="ag.dept"></span>
                        </button>
                    </template>
                </div>
            </div>
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="h-[38px] px-4 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all shrink-0 cursor-pointer">
                Senden
            </button>
        </form>

        {{-- 1-Klick Umschalter: Tickets | Dateien | Organigramm | Wissen --}}
        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold shrink-0">
            <button type="button"
                    wire:click="setMainView('tickets')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer {{ $mainView === 'tickets' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-ticket class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Tickets</span>
                @if($stats['waiting_count'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--theme-color)] text-gray-900 font-black">{{ $stats['waiting_count'] }}</span>
                @endif
            </button>
            <button type="button"
                    wire:click="setMainView('files')"
                    title="Zentrale Datei-, Konzept- & 3D-Übersicht (Auto-Archiv nach 28 Tagen)"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer {{ $mainView === 'files' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-cube-transparent class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Dateien</span>
            </button>
            <button type="button"
                    wire:click="setMainView('organigramm')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer {{ $mainView === 'organigramm' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                <x-heroicon-m-building-office-2 class="w-4 h-4 text-[var(--theme-color)]" />
                <span>Organigramm</span>
            </button>
            <button type="button"
                    wire:click="setMainView('knowledge')"
                    class="px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer {{ $mainView === 'knowledge' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
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

                        @php
                            $headerStatusClass = match ($ticket->status) {
                                'waiting_for_alina' => 'bg-amber-500/15 text-amber-800 dark:text-amber-300 border-amber-500/30',
                                'waiting_for_tresor' => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300 border-emerald-500/30',
                                'waiting_for_notebook' => 'bg-indigo-500/15 text-indigo-800 dark:text-indigo-300 border-indigo-500/30',
                                'completed' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
                                'archived' => 'bg-gray-500/10 text-gray-500 border-gray-500/20',
                                default => 'bg-sky-500/15 text-sky-800 dark:text-sky-300 border-sky-500/30',
                            };

                            $ticketAllFiles = [];
                            foreach ($ticket->messages as $tMsg) {
                                if (!empty($tMsg->attachments) && is_array($tMsg->attachments)) {
                                    foreach ($tMsg->attachments as $tIdx => $tAtt) {
                                        if (is_array($tAtt) && empty($tAtt['purged'])) {
                                            $tAtt['_msg_id'] = $tMsg->id;
                                            $tAtt['_key'] = (string) ($tAtt['id'] ?? ($tAtt['rel_path'] ?? ($tAtt['url'] ?? $tIdx)));
                                            $ticketAllFiles[] = $tAtt;
                                        }
                                    }
                                }
                            }
                        @endphp

                        <div class="flex items-center gap-2 flex-wrap">
                            @if(count($ticketAllFiles) > 0)
                                <button type="button"
                                        @click="showTicketFilesDrawer = !showTicketFilesDrawer"
                                        title="Alle Dateien, Konzepte & 3D-Modelle dieses Tickets einblenden"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-[#C5A059] text-gray-900 dark:text-white flex items-center gap-1.5 transition-colors cursor-pointer">
                                    <x-heroicon-m-cube-transparent class="w-4 h-4 text-[#C5A059]" />
                                    <span>Dateien ({{ count($ticketAllFiles) }})</span>
                                </button>
                            @endif

                            @if(!$ticket->assigned_to_antigravity && $ticket->status !== 'completed' && $ticket->status !== 'archived')
                                <button type="button"
                                        wire:click="triggerTicketAction('handoff_antigravity')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-bold bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 transition-colors cursor-pointer">
                                    An Antigravity übergeben
                                </button>
                            @endif

                            {{-- Synchroner Live-Status & Manuelle Status-Änderung in 1 Klick --}}
                            <div class="relative flex items-center">
                                <select wire:key="status-select-{{ $ticket->id }}-{{ $ticket->status }}"
                                        wire:change="changeTicketStatus($event.target.value)"
                                        aria-label="Ticket-Status manuell ändern"
                                        class="appearance-none pl-3 pr-7 py-1.5 rounded-xl text-xs font-black border transition-colors cursor-pointer outline-none focus:ring-2 focus:ring-[var(--theme-color-50)] {{ $headerStatusClass }}">
                                    <option value="new" @selected($ticket->status === 'new') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Neu</option>
                                    <option value="in_progress" @selected($ticket->status === 'in_progress') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">In Arbeit</option>
                                    <option value="waiting_for_alina" @selected($ticket->status === 'waiting_for_alina') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Rückfrage / Go</option>
                                    <option value="waiting_for_notebook" @selected($ticket->status === 'waiting_for_notebook') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Wartet auf Notebook</option>
                                    <option value="waiting_for_tresor" @selected($ticket->status === 'waiting_for_tresor') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Tresor bereit</option>
                                    <option value="completed" @selected($ticket->status === 'completed') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Erledigt</option>
                                    <option value="archived" @selected($ticket->status === 'archived') class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-semibold">Archiv</option>
                                </select>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 pointer-events-none absolute right-2 opacity-75" />
                            </div>
                        </div>
                    </div>

                    @if(count($ticketAllFiles) > 0)
                        <div x-show="showTicketFilesDrawer" x-cloak class="px-5 py-3 bg-gray-50/90 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 flex flex-wrap items-center gap-2">
                            @foreach($ticketAllFiles as $tf)
                                @php
                                    $tfExt = strtolower((string) ($tf['ext'] ?? pathinfo((string) ($tf['name'] ?? ''), PATHINFO_EXTENSION)));
                                    $tfCat = (string) ($tf['category'] ?? \App\Services\AI\TicketDeliverableService::detectCategory($tfExt, (string) ($tf['mime'] ?? '')));
                                    $tfUrl = (string) ($tf['url'] ?? '#');
                                    $tfRel = (string) ($tf['rel_path'] ?? '');
                                    $tfFw = (string) ($tf['funkenwerk_3d_uri'] ?? '');
                                @endphp
                                <div class="px-2.5 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center gap-2 text-xs">
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-gray-100 dark:bg-gray-800 text-[#C5A059]">{{ $tfExt ?: 'FILE' }}</span>
                                    <span class="font-bold truncate max-w-[180px]">{{ $tf['name'] ?? 'Datei' }}</span>
                                    @if($tfCat === '3d' && empty($tf['archived']))
                                        <button type="button"
                                                @click="openHologram('{{ $tfUrl }}', '{{ addslashes($tf['name'] ?? '3D') }}', '{{ $tfExt }}', '{{ $tfFw }}')"
                                                class="px-2 py-0.5 rounded-md bg-sky-500/15 text-sky-700 dark:text-sky-300 font-bold text-[10px] cursor-pointer">
                                            Hologramm
                                        </button>
                                        @if($tfFw !== '')
                                            <a href="{{ $tfFw }}"
                                               title="Direkt in Funkenwerk 3D Desktop öffnen"
                                               class="px-2 py-0.5 rounded-md bg-[#C5A059]/20 text-gray-900 dark:text-[#E8CB8E] font-bold text-[10px]">
                                                Funkenwerk 3D
                                            </a>
                                        @endif
                                    @elseif($tfCat === 'concept' && $tfRel !== '')
                                        <button type="button"
                                                wire:click="openConceptModal('{{ addslashes($tfRel) }}', '{{ addslashes($tf['name'] ?? 'Konzept') }}')"
                                                class="px-2 py-0.5 rounded-md bg-[var(--theme-color-20)] text-gray-900 dark:text-white font-bold text-[10px] cursor-pointer">
                                            Lesen
                                        </button>
                                    @elseif($tfCat === 'image')
                                        <button type="button"
                                                @click="openLightbox('{{ $tfUrl }}', '{{ addslashes($tf['name'] ?? 'Bild') }}')"
                                                class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 font-bold text-[10px] cursor-pointer">
                                            Vorschau
                                        </button>
                                    @endif
                                    <a href="{{ $tfUrl }}{{ str_contains($tfUrl, '?') ? '&download=1' : '?download=1' }}"
                                       download
                                       title="Herunterladen"
                                       class="text-gray-500 hover:text-gray-900 dark:hover:text-white">
                                        <x-heroicon-m-arrow-down-tray class="w-3.5 h-3.5" />
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    @endif

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

                    {{-- Chronologische Nachrichtenliste mit edlen Gold-Rahmen-Profilbildern & Lightbox --}}
                    <div x-data="{ scrollBottom() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                         x-init="$nextTick(() => scrollBottom())"
                         wire:key="ticket-stream-{{ $ticket->id }}-{{ $ticket->messages_count }}"
                         class="flex-1 overflow-y-auto p-5 space-y-4 max-h-[calc(100dvh-23rem)]">
                        @foreach($ticket->messages as $msg)
                            @php
                                $isAlina = ($msg->sender_role === 'alina');
                                $avatarUrl = $this->resolveMessageAvatarUrl($msg);
                            @endphp
                            <div class="flex items-start gap-3 {{ $isAlina ? 'flex-row-reverse' : 'flex-row' }}">
                                {{-- Edles rundes Profilbild mit dezentem Seelenfunke-Goldrahmen (#C5A059) & Lightbox-Klick --}}
                                <button type="button"
                                        @click="openLightbox('{{ $avatarUrl }}', '{{ addslashes($msg->sender_name) }}')"
                                        title="{{ $msg->sender_name }} vergrößern"
                                        class="w-9 h-9 sm:w-10 sm:h-10 rounded-full overflow-hidden border border-[#C5A059]/75 ring-2 ring-[#C5A059]/15 bg-white dark:bg-gray-900 shrink-0 shadow-xs hover:scale-105 hover:border-[#C5A059] transition-all cursor-pointer">
                                    <img src="{{ $avatarUrl }}"
                                         alt="{{ $msg->sender_name }}"
                                         class="w-full h-full object-cover" />
                                </button>

                                <div class="max-w-2xl rounded-2xl px-4 py-3 border {{ $isAlina ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-30)] text-gray-900 dark:text-white' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-900 dark:text-gray-100' }}">
                                    <div class="flex items-center justify-between gap-4 mb-1 text-[10px] text-gray-500 dark:text-gray-400">
                                        <span class="font-black uppercase tracking-wider {{ $isAlina ? 'text-[var(--theme-color)]' : 'text-gray-700 dark:text-gray-300' }}">
                                            {{ $msg->sender_name }} · {{ \App\Models\Ai\AiUniverseTicket::getChannelLabel($msg->channel) }}
                                        </span>
                                        <span class="font-mono">{{ $msg->created_at?->format('d.m. H:i') }}</span>
                                    </div>

                                    <div class="text-xs sm:text-sm leading-relaxed whitespace-pre-line">{!! \App\Livewire\Shop\Ai\AiUniverseTickets::formatMessageHtml($msg->content) !!}</div>

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
                                            @foreach($msg->attachments as $attIdx => $att)
                                                @php
                                                    if (!is_array($att)) continue;
                                                    $attName = (string) ($att['name'] ?? 'Datei');
                                                    $attExt = strtolower((string) ($att['ext'] ?? pathinfo($attName, PATHINFO_EXTENSION)));
                                                    $attCat = (string) ($att['category'] ?? \App\Services\AI\TicketDeliverableService::detectCategory($attExt, (string) ($att['mime'] ?? '')));
                                                    $attUrl = (string) ($att['url'] ?? '#');
                                                    if (preg_match('#^/api/(?:funkenwerk/ag-inbox|system/ag-direct-inbox)/([A-Za-z0-9_-]+)/attachment$#', $attUrl, $mAg)) {
                                                        $attUrl = '/admin/ai/ag-attachment/' . $mAg[1];
                                                    }
                                                    $attRel = (string) ($att['rel_path'] ?? '');
                                                    $attFwUri = (string) ($att['funkenwerk_3d_uri'] ?? '');
                                                    $attKey = (string) ($att['id'] ?? ($attRel !== '' ? $attRel : ($attUrl ?: $attIdx)));
                                                    $attPinned = !empty($att['pinned']);
                                                    $attArchived = !empty($att['archived']);
                                                    $attPurged = !empty($att['purged']);
                                                @endphp

                                                @if($attPurged)
                                                    <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[10px] font-mono text-gray-400">
                                                        {{ $attName }} (automatisch bereinigt)
                                                    </span>
                                                @elseif(($att['is_image'] ?? '0') === '1' || $attCat === 'image')
                                                    <div class="group relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
                                                        <button type="button"
                                                                @click="openLightbox('{{ $attUrl }}', '{{ addslashes($attName) }}')"
                                                                class="block w-24 h-20 cursor-pointer">
                                                            <img src="{{ $attUrl }}" alt="{{ $attName }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" />
                                                        </button>
                                                        <div class="px-1.5 py-1 flex items-center justify-between gap-1 bg-white/95 dark:bg-gray-900/95 border-t border-gray-100 dark:border-gray-800 text-[9px]">
                                                            <span class="truncate max-w-[60px] font-bold">{{ $attName }}</span>
                                                            <a href="{{ $attUrl }}{{ str_contains($attUrl, '?') ? '&download=1' : '?download=1' }}" download title="Download" class="text-gray-500 hover:text-gray-900 dark:hover:text-white">
                                                                <x-heroicon-m-arrow-down-tray class="w-3 h-3" />
                                                            </a>
                                                        </div>
                                                    </div>
                                                @elseif($attCat === '3d')
                                                    <div class="px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-[#C5A059]/60 shadow-2xs flex flex-col gap-1.5 min-w-[210px]">
                                                        <div class="flex items-center justify-between gap-2">
                                                            <span class="flex items-center gap-1.5 min-w-0">
                                                                <x-heroicon-m-cube-transparent class="w-4 h-4 text-[#C5A059] shrink-0" />
                                                                <span class="text-xs font-black text-gray-900 dark:text-white truncate">{{ $attName }}</span>
                                                            </span>
                                                            <button type="button"
                                                                    wire:click="toggleAttachmentPin('{{ $msg->id }}', '{{ addslashes($attKey) }}')"
                                                                    title="{{ $attPinned ? 'Dauerhaft geschützt (Pin lösen)' : 'Vor 4-Wochen-Archivierung schützen (Pin)' }}"
                                                                    class="text-[10px] font-bold cursor-pointer {{ $attPinned ? 'text-[#C5A059]' : 'text-gray-400 hover:text-gray-700 dark:hover:text-white' }}">
                                                                {{ $attPinned ? '★ Pin' : '☆' }}
                                                            </button>
                                                        </div>
                                                        <div class="flex items-center flex-wrap gap-1.5 text-[10px]">
                                                            @if(!empty($att['size_human']))
                                                                <span class="font-mono text-gray-500 dark:text-gray-400">{{ $att['size_human'] }}</span>
                                                            @endif
                                                            @if(!empty($att['filament_grams']))
                                                                <span class="px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 font-mono font-bold">~{{ $att['filament_grams'] }} g PLA</span>
                                                            @endif
                                                            @if($attArchived)
                                                                <span class="px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 font-bold">Archiv</span>
                                                            @endif
                                                        </div>
                                                        <div class="flex items-center gap-1.5 pt-0.5">
                                                            @if(!$attArchived)
                                                                <button type="button"
                                                                        @click="openHologram('{{ $attUrl }}', '{{ addslashes($attName) }}', '{{ $attExt }}', '{{ $attFwUri }}')"
                                                                        class="px-2.5 py-1 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 text-sky-800 dark:text-sky-300 border border-sky-500/30 text-[10px] font-black uppercase tracking-wider cursor-pointer">
                                                                    3D Hologramm
                                                                </button>
                                                                @if($attFwUri !== '')
                                                                    <a href="{{ $attFwUri }}"
                                                                       title="Projektdatei direkt in der Funkenwerk 3D Desktop-App öffnen"
                                                                       class="px-2.5 py-1 rounded-lg bg-[#C5A059] text-gray-900 text-[10px] font-black uppercase tracking-wider hover:brightness-105">
                                                                        Funkenwerk 3D
                                                                    </a>
                                                                @endif
                                                            @else
                                                                <button type="button"
                                                                        wire:click="restoreArchivedFile('{{ $msg->id }}', '{{ addslashes($attKey) }}')"
                                                                        class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-[10px] font-bold cursor-pointer">
                                                                    Reaktivieren
                                                                </button>
                                                            @endif
                                                            <a href="{{ $attUrl }}{{ str_contains($attUrl, '?') ? '&download=1' : '?download=1' }}"
                                                               download
                                                               title="3D-Datei herunterladen"
                                                               class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 text-[10px] font-bold flex items-center gap-1">
                                                                <x-heroicon-m-arrow-down-tray class="w-3.5 h-3.5" />
                                                            </a>
                                                        </div>
                                                    </div>
                                                @elseif($attCat === 'concept' && $attRel !== '')
                                                    <div class="px-3 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center gap-2 text-xs">
                                                        <x-heroicon-m-document-text class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                                                        <span class="font-bold text-gray-900 dark:text-white truncate max-w-[180px]">{{ $attName }}</span>
                                                        <button type="button"
                                                                wire:click="openConceptModal('{{ addslashes($attRel) }}', '{{ addslashes($attName) }}')"
                                                                class="px-2 py-0.5 rounded-md bg-[var(--theme-color-20)] text-gray-900 dark:text-white font-bold text-[10px] cursor-pointer">
                                                            Öffnen
                                                        </button>
                                                        <a href="{{ $attUrl }}{{ str_contains($attUrl, '?') ? '&download=1' : '?download=1' }}"
                                                           download
                                                           title="Konzept herunterladen"
                                                           class="text-gray-500 hover:text-gray-900 dark:hover:text-white">
                                                            <x-heroicon-m-arrow-down-tray class="w-3.5 h-3.5" />
                                                        </a>
                                                    </div>
                                                @else
                                                    <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
                                                        <x-heroicon-m-document-arrow-down class="w-4 h-4 text-[var(--theme-color)] shrink-0" />
                                                        <a href="{{ $attUrl }}{{ str_contains($attUrl, '?') ? '&download=1' : '?download=1' }}"
                                                           download
                                                           class="hover:underline truncate max-w-[200px]">
                                                            {{ $attName }}
                                                        </a>
                                                        @if(!empty($att['size_human']))
                                                            <span class="text-[10px] font-mono text-gray-400">{{ $att['size_human'] }}</span>
                                                        @endif
                                                        <button type="button"
                                                                wire:click="toggleAttachmentPin('{{ $msg->id }}', '{{ addslashes($attKey) }}')"
                                                                title="{{ $attPinned ? 'Pin lösen' : 'Vor 4-Wochen-Archivierung schützen' }}"
                                                                class="text-[10px] cursor-pointer {{ $attPinned ? 'text-[#C5A059]' : 'text-gray-400 hover:text-gray-700 dark:hover:text-white' }}">
                                                            {{ $attPinned ? '★' : '☆' }}
                                                        </button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Antwortzeile im Ticket (Enter = Senden, Shift+Enter = Umbruch, @ = Agenten inkl. Antigravity) --}}
                    <form @submit.prevent="triggerSubmit()"
                          x-data="sfTicketComposer('ticketReplyInput', 'submitTicketReply', @js($mentionAgents))"
                          @click.outside="mentionOpen = false"
                          class="p-3.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/60 flex items-end gap-2">
                        <div class="relative flex-1 flex items-end">
                            {{-- @-Mention Autocomplete Dropdown (nach oben öffnend) --}}
                            <div x-show="mentionOpen && filteredAgents.length > 0"
                                 x-cloak
                                 style="display: none;"
                                 class="absolute left-0 right-0 bottom-full mb-1.5 z-50 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl overflow-hidden max-h-60 overflow-y-auto">
                                <template x-for="(ag, idx) in filteredAgents" :key="ag.id">
                                    <button type="button"
                                            @mousedown.prevent="selectMention(ag)"
                                            @mouseenter="mentionIndex = idx"
                                            :class="mentionIndex === idx ? 'bg-[var(--theme-color-20)] text-gray-900 dark:text-white' : 'text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800/80'"
                                            class="w-full px-3 py-2 flex items-center justify-between gap-2 text-left text-xs transition-colors cursor-pointer">
                                        <span class="flex items-center gap-2 min-w-0">
                                            <img :src="ag.avatar" :alt="ag.name" class="w-6 h-6 rounded-full object-cover border border-[#C5A059]/60 shrink-0" />
                                            <span class="font-bold truncate" x-text="'@' + ag.name"></span>
                                        </span>
                                        <span class="text-[10px] font-mono uppercase px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 shrink-0" x-text="ag.dept"></span>
                                    </button>
                                </template>
                            </div>

                            <textarea rows="1"
                                      x-ref="composerInput"
                                      wire:model="ticketReplyInput"
                                      @input="onInput($event)"
                                      @keydown="onKeydown($event)"
                                      @click="detectMention($event.target)"
                                      placeholder="Antwort zu {{ $ticket->ticket_number }} oder @Agent..."
                                      title="Enter zum Senden, Shift+Enter für Zeilenumbruch, @ für Agenten-Auswahl"
                                      style="height: 38px;"
                                      class="w-full resize-none overflow-y-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl pl-3.5 pr-16 py-2 text-xs sm:text-sm leading-snug text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] focus:ring-1 focus:ring-[var(--theme-color)] outline-none transition-colors"></textarea>

                            <div class="absolute right-2.5 bottom-2 flex items-center gap-1.5">
                                <button type="button"
                                        @click="openMentionPicker()"
                                        title="Agent oder @Antigravity einbeziehen"
                                        class="text-gray-400 hover:text-[var(--theme-color)] dark:hover:text-white font-black text-sm leading-none px-1 cursor-pointer transition-colors">
                                    @
                                </button>
                                <label class="cursor-pointer text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors" title="Datei, Bild oder 3D-Modell anhängen">
                                    <input type="file" wire:model="replyUploads" multiple accept="image/*,application/pdf,.stl,.3mf,.glb,.obj,.md,.txt,.docx,.csv,.xcs,.zip" class="hidden" />
                                    <x-heroicon-m-paper-clip class="w-4 h-4" />
                                </label>
                            </div>
                        </div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                class="h-[38px] px-4 rounded-xl bg-gray-900 text-white dark:bg-white dark:text-gray-900 font-bold text-xs uppercase tracking-wider hover:opacity-90 transition-opacity shrink-0 cursor-pointer">
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
    {{-- ANSICHT 1B: ZENTRALE DATEI-, KONZEPT- & 3D-BÜHNE (4-WOCHEN-AUTOPILOT) --}}
    {{-- ===================================================================== --}}
    @if($mainView === 'files')
        @php
            $deliv = $this->ticketDeliverables;
            $dStats = $deliv['stats'];
            $dItems = $deliv['items'];
        @endphp
        <div class="space-y-3">
            <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 shadow-xs flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold">
                        <button type="button" wire:click="setFileStateFilter('active')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileStateFilter === 'active' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500' }}">
                            Aktiv ({{ $dStats['active_count'] }} · {{ $dStats['active_human'] }})
                        </button>
                        <button type="button" wire:click="setFileStateFilter('pinned')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileStateFilter === 'pinned' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500' }}">
                            ★ Geschützt ({{ $dStats['pinned_count'] }})
                        </button>
                        <button type="button" wire:click="setFileStateFilter('archived')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileStateFilter === 'archived' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500' }}">
                            Archiv ({{ $dStats['archived_count'] }} · {{ $dStats['archived_human'] }})
                        </button>
                    </div>

                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 text-xs font-bold">
                        <button type="button" wire:click="setFileTypeFilter('all')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileTypeFilter === 'all' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500' }}">Alle</button>
                        <button type="button" wire:click="setFileTypeFilter('3d')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileTypeFilter === '3d' ? 'bg-white dark:bg-gray-800 text-[#C5A059]' : 'text-gray-500' }}">3D-Modelle</button>
                        <button type="button" wire:click="setFileTypeFilter('concept')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileTypeFilter === 'concept' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500' }}">Konzepte</button>
                        <button type="button" wire:click="setFileTypeFilter('document')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileTypeFilter === 'document' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500' }}">Dokumente</button>
                        <button type="button" wire:click="setFileTypeFilter('image')" class="px-2.5 py-1 rounded-lg cursor-pointer {{ $fileTypeFilter === 'image' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white' : 'text-gray-500' }}">Bilder</button>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/25"
                          title="Täglicher Cronjob um 04:10 Uhr verschiebt ungeschützte Dateien nach 28 Tagen automatisch komprimiert ins Archiv und löscht Altlasten nach 180 Tagen.">
                        Autopilot: 28 Tage -> Archiv
                    </span>
                    <input type="text"
                           wire:model.live.debounce.250ms="fileSearch"
                           placeholder="Datei oder #SF-..."
                           class="w-44 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-xs">
                @if(count($dItems) === 0)
                    <div class="p-12 text-center text-xs font-bold text-gray-400">
                        Keine hinterlegten Dateien in diesem Filter.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-950 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Datei</th>
                                    <th class="px-4 py-3">Ticket</th>
                                    <th class="px-4 py-3">Agent</th>
                                    <th class="px-4 py-3">Größe</th>
                                    <th class="px-4 py-3">Lebenszyklus</th>
                                    <th class="px-4 py-3 text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70">
                                @foreach($dItems as $item)
                                    <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40 transition-colors">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-gray-100 dark:bg-gray-800 text-[#C5A059]">{{ $item['ext'] ?: 'FILE' }}</span>
                                                <span class="font-bold text-gray-900 dark:text-white truncate max-w-[260px]">{{ $item['name'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <button type="button"
                                                    wire:click="jumpToTicketFromFiles('{{ $item['ticket_id'] }}')"
                                                    class="font-mono font-black text-[var(--theme-color)] hover:underline cursor-pointer">
                                                {{ $item['ticket_number'] }}
                                            </button>
                                            <span class="text-gray-500 dark:text-gray-400 ml-1 truncate max-w-[200px] inline-block align-bottom">{{ $item['ticket_title'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-bold text-gray-700 dark:text-gray-300">{{ $item['sender_name'] }}</td>
                                        <td class="px-4 py-3 font-mono text-gray-600 dark:text-gray-400">{{ $item['size_human'] }}</td>
                                        <td class="px-4 py-3">
                                            @if($item['pinned'])
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[#C5A059]/20 text-gray-900 dark:text-[#E8CB8E]">★ Dauerhaft geschützt</span>
                                            @elseif($item['archived'])
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-800 dark:text-amber-300">Archiviert</span>
                                            @else
                                                <span class="font-mono text-[11px] text-gray-500 dark:text-gray-400">Auto-Archiv in {{ $item['days_until_archive'] }} T.</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if($item['category'] === '3d' && !$item['archived'])
                                                    <button type="button"
                                                            @click="openHologram('{{ $item['url'] }}', '{{ addslashes($item['name']) }}', '{{ $item['ext'] }}', '{{ $item['funkenwerk_3d_uri'] ?? '' }}')"
                                                            class="px-2.5 py-1 rounded-lg bg-sky-500/15 text-sky-800 dark:text-sky-300 font-bold text-[10px] cursor-pointer">
                                                        Hologramm
                                                    </button>
                                                    @if(!empty($item['funkenwerk_3d_uri']))
                                                        <a href="{{ $item['funkenwerk_3d_uri'] }}"
                                                           class="px-2.5 py-1 rounded-lg bg-[#C5A059] text-gray-900 font-black text-[10px]">
                                                            Funkenwerk 3D
                                                        </a>
                                                    @endif
                                                @elseif($item['category'] === 'concept' && $item['rel_path'] !== '')
                                                    <button type="button"
                                                            wire:click="openConceptModal('{{ addslashes($item['rel_path']) }}', '{{ addslashes($item['name']) }}')"
                                                            class="px-2.5 py-1 rounded-lg bg-[var(--theme-color-20)] text-gray-900 dark:text-white font-bold text-[10px] cursor-pointer">
                                                        Lesen
                                                    </button>
                                                @elseif($item['category'] === 'image')
                                                    <button type="button"
                                                            @click="openLightbox('{{ $item['url'] }}', '{{ addslashes($item['name']) }}')"
                                                            class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 font-bold text-[10px] cursor-pointer">
                                                        Vorschau
                                                    </button>
                                                @endif

                                                @if($item['archived'])
                                                    <button type="button"
                                                            wire:click="restoreArchivedFile('{{ $item['message_id'] }}', '{{ addslashes($item['key']) }}')"
                                                            class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 font-bold text-[10px] cursor-pointer">
                                                        Reaktivieren
                                                    </button>
                                                @endif

                                                <button type="button"
                                                        wire:click="toggleAttachmentPin('{{ $item['message_id'] }}', '{{ addslashes($item['key']) }}')"
                                                        title="{{ $item['pinned'] ? 'Pin lösen' : 'Vor Auto-Archivierung schützen' }}"
                                                        class="px-2 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 font-bold text-[11px] cursor-pointer {{ $item['pinned'] ? 'text-[#C5A059]' : 'text-gray-400' }}">
                                                    {{ $item['pinned'] ? '★' : '☆' }}
                                                </button>

                                                <a href="{{ $item['url'] }}{{ str_contains($item['url'], '?') ? '&download=1' : '?download=1' }}"
                                                   download
                                                   title="Herunterladen"
                                                   class="p-1.5 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700">
                                                    <x-heroicon-m-arrow-down-tray class="w-3.5 h-3.5" />
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
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
                <button type="button" @click="lightboxOpen = false" class="p-1 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>
            <div class="p-4 flex items-center justify-center bg-gray-100 dark:bg-black max-h-[80vh] overflow-auto">
                <img :src="lightboxUrl" :alt="lightboxName" class="max-h-[74vh] w-auto object-contain rounded-lg" />
            </div>
        </div>
    </div>

    {{-- TONY STARK 60-FPS 3D-HOLOGRAMM- & CAD-MODAL + 1-KLICK FUNKENWERK 3D START --}}
    <div x-show="holoOpen"
         x-cloak
         style="display: none;"
         class="fixed inset-0 z-[130] bg-black/85 flex items-center justify-center p-4"
         @click.self="closeHologram()">
        <div class="relative max-w-5xl w-full bg-[#090d16] border border-[#C5A059]/60 rounded-2xl overflow-hidden shadow-2xl text-white flex flex-col">
            <div class="px-4 py-3 border-b border-gray-800 flex flex-wrap items-center justify-between gap-2 bg-[#0d1320]">
                <div class="flex items-center gap-2.5 min-w-0">
                    <x-heroicon-m-cube-transparent class="w-5 h-5 text-[#C5A059] shrink-0" />
                    <span class="text-xs sm:text-sm font-black truncate" x-text="holoName"></span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-sky-500/15 text-sky-300 border border-sky-500/30" x-text="holoExt"></span>
                </div>

                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button"
                            @click="toggleHoloStyle('hologram')"
                            :class="holoMode === 'hologram' ? 'bg-sky-500/25 text-sky-200 border-sky-400/60' : 'bg-gray-900 text-gray-400 border-gray-700'"
                            class="px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase cursor-pointer">
                        Hologramm
                    </button>
                    <button type="button"
                            @click="toggleHoloStyle('matte')"
                            :class="holoMode === 'matte' ? 'bg-[#C5A059] text-gray-900 border-[#C5A059]' : 'bg-gray-900 text-gray-400 border-gray-700'"
                            class="px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase cursor-pointer">
                        Matt PLA
                    </button>
                    <button type="button"
                            @click="toggleHoloTurntable()"
                            :class="holoTurntable ? 'bg-gray-800 text-white border-gray-600' : 'bg-gray-900 text-gray-500 border-gray-800'"
                            class="px-2.5 py-1 rounded-lg border text-[10px] font-bold cursor-pointer">
                        Drehteller
                    </button>
                    <button type="button"
                            @click="toggleHoloBed()"
                            :class="holoBed ? 'bg-gray-800 text-[#E8CB8E] border-[#C5A059]/50' : 'bg-gray-900 text-gray-500 border-gray-800'"
                            class="px-2.5 py-1 rounded-lg border text-[10px] font-bold cursor-pointer">
                        256mm Bett
                    </button>
                    <template x-if="holoFwUri">
                        <a :href="holoFwUri"
                           class="px-3 py-1 rounded-lg bg-[#C5A059] text-gray-900 text-[10px] font-black uppercase tracking-wider hover:brightness-105">
                            In Funkenwerk 3D öffnen
                        </a>
                    </template>
                    <button type="button" @click="closeHologram()" class="p-1 rounded-lg text-gray-400 hover:text-white cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div class="relative w-full h-[64vh] min-h-[380px] bg-[#070b12]">
                <div x-ref="holoViewport" class="w-full h-full"></div>

                <div x-show="holoLoading" class="absolute inset-0 flex items-center justify-center bg-[#070b12]/80 text-xs font-mono text-sky-300">
                    3D-Geometrie wird geladen...
                </div>

                {{-- Tony Stark Telemetrie-HUD unten links --}}
                <template x-if="holoStats">
                    <div class="absolute left-4 bottom-4 px-3.5 py-2.5 rounded-xl bg-[#0b111e]/90 border border-[#C5A059]/40 text-[11px] font-mono space-y-1 pointer-events-none">
                        <div class="text-[#E8CB8E] font-bold">
                            Maße: <span x-text="holoStats.widthMm + ' × ' + holoStats.depthMm + ' × ' + holoStats.heightMm + ' mm'"></span>
                        </div>
                        <div class="flex items-center gap-3 text-gray-300">
                            <span x-text="'Dreiecke: ' + holoStats.triangles.toLocaleString('de-DE')"></span>
                            <span :class="holoStats.fitsBambuA1 ? 'text-emerald-400' : 'text-amber-400'"
                                  x-text="holoStats.fitsBambuA1 ? '✓ Bambu A1 Monoblock (<=256mm)' : '! Größer als 256mm Druckbett'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- INLINE KONZEPT- & MARKDOWN-READER MODAL (Kein Tab-Wechsel nötig) --}}
    @if($conceptModalHtml !== null)
        <div class="fixed inset-0 z-[125] bg-black/75 flex items-center justify-center p-4"
             wire:click.self="closeConceptModal">
            <div class="relative max-w-3xl w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-2xl flex flex-col max-h-[85vh]">
                <div class="px-5 py-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between gap-3 bg-gray-50 dark:bg-gray-950">
                    <span class="text-xs sm:text-sm font-black text-gray-900 dark:text-white truncate">{{ $conceptModalTitle }}</span>
                    <div class="flex items-center gap-2">
                        @if($conceptModalDownloadUrl)
                            <a href="{{ $conceptModalDownloadUrl }}"
                               download
                               class="px-3 py-1 rounded-lg bg-gray-200/80 dark:bg-gray-800 text-xs font-bold text-gray-900 dark:text-white">
                                Download
                            </a>
                        @endif
                        <button type="button" wire:click="closeConceptModal" class="p-1 rounded-lg text-gray-500 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div class="p-6 overflow-y-auto prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-100">
                    {!! $conceptModalHtml !!}
                </div>
            </div>
        </div>
    @endif
</div>
