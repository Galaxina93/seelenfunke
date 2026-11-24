<div id="sf-tasks-root"
     style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80;"
     x-data="sfTaskWorkspace({
        initialLeftWidth: {{ (int) $leftColWidthPx }},
        initialMyHeightPct: {{ (int) $myTicketsHeightPct }},
        initialMuted: @js((bool) $soundMuted),
        hasSelectedTask: @js((bool) $selectedTask),
        actors: @js($assignableActors)
     })"
     @sf-task-random-sound.window="playRandomSound()"
     @task-ticket-opened.window="mobileDetailOpen = true"
     @task-ticket-closed.window="mobileDetailOpen = false"
     @task-chat-updated.window="scrollChatToBottom()"
     @keydown.escape.window="if (lightboxOpen) { lightboxOpen = false; } else if (openAssigneeMenuId) { openAssigneeMenuId = null; } else if ($wire.profileModalOpen) { $wire.closeOperatorProfileModal(); } else if ($wire.selectedTaskId) { $wire.closeDetailPanel(); }"
     class="w-full max-w-full overflow-x-clip {{ $embeddedMode ? 'h-[690px] lg:h-[730px] p-0' : 'h-[calc(100dvh-5.25rem)] px-2.5 sm:px-5 pb-3 pt-1.5' }} flex flex-col font-sans text-gray-900 dark:text-white select-none">

    @once
        <script>
            (function () {
                let sfTaskAudioCtx = null;
                let sfLastSoundIdx = -1;

                function ensureTaskAudioCtx() {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (!Ctx) return null;
                    if (!sfTaskAudioCtx) {
                        sfTaskAudioCtx = new Ctx();
                    }
                    if (sfTaskAudioCtx.state === 'suspended') {
                        sfTaskAudioCtx.resume().catch(function () {});
                    }
                    return sfTaskAudioCtx;
                }

                // 8 unterschiedliche, harmonische Fokus-Modus Sounds (niemals derselbe Sound zweimal hintereinander)
                window.sfPlayRandomTaskSound = function (muted) {
                    if (muted) return;
                    const ctx = ensureTaskAudioCtx();
                    if (!ctx) return;

                    let idx = Math.floor(Math.random() * 8);
                    if (idx === sfLastSoundIdx) {
                        idx = (idx + 1 + Math.floor(Math.random() * 7)) % 8;
                    }
                    sfLastSoundIdx = idx;

                    const now = ctx.currentTime;
                    const playTone = function (freq, endFreq, type, startOffset, duration, peakGain) {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = type || 'sine';
                        osc.frequency.setValueAtTime(freq, now + startOffset);
                        if (endFreq && endFreq !== freq) {
                            osc.frequency.exponentialRampToValueAtTime(endFreq, now + startOffset + duration);
                        }
                        gain.gain.setValueAtTime(0.0001, now + startOffset);
                        gain.gain.linearRampToValueAtTime(peakGain, now + startOffset + 0.012);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + startOffset + duration);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now + startOffset);
                        osc.stop(now + startOffset + duration + 0.015);
                    };

                    switch (idx) {
                        case 0:
                            playTone(523.25, 783.99, 'sine', 0, 0.095, 0.055);
                            break;
                        case 1:
                            playTone(659.25, 440.00, 'triangle', 0, 0.075, 0.06);
                            break;
                        case 2:
                            playTone(587.33, 587.33, 'sine', 0, 0.08, 0.045);
                            playTone(880.00, 880.00, 'sine', 0.045, 0.11, 0.045);
                            break;
                        case 3:
                            playTone(392.00, 587.33, 'sine', 0, 0.09, 0.055);
                            break;
                        case 4:
                            playTone(880.00, 659.25, 'sine', 0, 0.085, 0.045);
                            break;
                        case 5:
                            playTone(698.46, 698.46, 'triangle', 0, 0.09, 0.04);
                            playTone(880.00, 987.77, 'sine', 0.035, 0.10, 0.04);
                            break;
                        case 6:
                            playTone(493.88, 659.25, 'triangle', 0, 0.08, 0.05);
                            break;
                        default:
                            playTone(523.25, 1046.50, 'sine', 0, 0.10, 0.045);
                            break;
                    }
                };

                // Fehlertolerante Client-Fuzzy-Suche (Umlaute, Initialen, Teilwoerter & Levenshtein <= 2)
                function sfNormStr(s) {
                    return String(s || '')
                        .toLowerCase()
                        .replace(/ä/g, 'ae')
                        .replace(/ö/g, 'oe')
                        .replace(/ü/g, 'ue')
                        .replace(/ß/g, 'ss')
                        .replace(/-/g, ' ')
                        .trim();
                }

                function sfLevenshtein(a, b) {
                    if (a === b) return 0;
                    if (!a.length) return b.length;
                    if (!b.length) return a.length;
                    const v0 = new Array(b.length + 1);
                    const v1 = new Array(b.length + 1);
                    for (let i = 0; i <= b.length; i++) v0[i] = i;
                    for (let i = 0; i < a.length; i++) {
                        v1[0] = i + 1;
                        for (let j = 0; j < b.length; j++) {
                            const cost = a[i] === b[j] ? 0 : 1;
                            v1[j + 1] = Math.min(v1[j] + 1, v0[j + 1] + 1, v0[j] + cost);
                        }
                        for (let j = 0; j <= b.length; j++) v0[j] = v1[j];
                    }
                    return v1[b.length];
                }

                window.sfScoreActorMatch = function (query, actor) {
                    const q = sfNormStr(query);
                    if (!q) return 100;
                    const hay = sfNormStr((actor.name || '') + ' ' + (actor.role_label || '') + ' ' + (actor.initials || ''));
                    const init = sfNormStr(actor.initials || '');
                    if (hay === q || (init && init === q)) return 100;
                    if (hay.indexOf(q) === 0) return 95;
                    if (hay.indexOf(q) !== -1) return 85;

                    const qParts = q.split(/\s+/).filter(Boolean);
                    const tParts = hay.split(/\s+/).filter(Boolean);
                    let hits = 0;
                    for (let i = 0; i < qParts.length; i++) {
                        const qt = qParts[i];
                        let best = 0;
                        for (let j = 0; j < tParts.length; j++) {
                            const tt = tParts[j];
                            if (tt.indexOf(qt) === 0) best = Math.max(best, 80);
                            else if (tt.indexOf(qt) !== -1) best = Math.max(best, 70);
                            else if (qt.length >= 3 && tt.length >= 3) {
                                const slice = tt.slice(0, Math.min(tt.length, qt.length + 2));
                                const dist = sfLevenshtein(qt, slice);
                                const maxDist = qt.length >= 5 ? 2 : 1;
                                if (dist <= maxDist) best = Math.max(best, 65 - dist * 10);
                            }
                        }
                        if (best > 0) hits++;
                        else return 0;
                    }
                    return hits > 0 ? 68 : 0;
                };

                window.sfTaskWorkspace = function (cfg) {
                    return {
                        leftWidthPx: Number(cfg.initialLeftWidth || 330),
                        myHeightPct: Number(cfg.initialMyHeightPct || 52),
                        soundMuted: Boolean(cfg.initialMuted),
                        mobileTab: 'open',
                        mobileDetailOpen: Boolean(cfg.hasSelectedTask),
                        resizingMode: null,
                        draggedTaskId: null,
                        dragSourceIsMine: false,
                        dragOverMine: false,
                        dragOverOpen: false,
                        dragOverRowId: null,
                        chatDropActive: false,
                        editingRowId: null,
                        editingRowTitle: '',
                        lightboxOpen: false,
                        lightboxUrl: '',
                        lightboxName: '',
                        actors: Array.isArray(cfg.actors) ? cfg.actors : [],
                        openAssigneeMenuId: null,
                        assigneeSearch: '',

                        init() {
                            const self = this;
                            window.addEventListener('pointermove', function (e) {
                                self.onSplitterMove(e);
                            });
                            window.addEventListener('pointerup', function () {
                                self.stopSplitterResize();
                            });
                            this.$nextTick(function () {
                                self.scrollChatToBottom();
                            });
                        },

                        filteredActors() {
                            const q = this.assigneeSearch;
                            if (!q || !q.trim()) return this.actors;
                            return this.actors
                                .map(function (a) {
                                    return { actor: a, score: window.sfScoreActorMatch(q, a) };
                                })
                                .filter(function (x) { return x.score > 0; })
                                .sort(function (a, b) { return b.score - a.score; })
                                .map(function (x) { return x.actor; });
                        },

                        findActorLabel(key) {
                            if (!key) return 'Bearbeiter...';
                            for (let i = 0; i < this.actors.length; i++) {
                                if (this.actors[i].key === key) return this.actors[i].name;
                            }
                            return 'Bearbeiter...';
                        },

                        toggleAssigneeMenu(menuId) {
                            this.playRandomSound();
                            if (this.openAssigneeMenuId === menuId) {
                                this.openAssigneeMenuId = null;
                                return;
                            }
                            this.openAssigneeMenuId = menuId;
                            this.assigneeSearch = '';
                            const self = this;
                            this.$nextTick(function () {
                                const inp = document.getElementById('sf-assignee-search-' + menuId);
                                if (inp) inp.focus();
                            });
                        },

                        playRandomSound() {
                            window.sfPlayRandomTaskSound(this.soundMuted);
                        },

                        toggleMute() {
                            this.soundMuted = !this.soundMuted;
                            if (!this.soundMuted) {
                                window.sfPlayRandomTaskSound(false);
                            }
                            this.$wire.saveSoundMuted(this.soundMuted);
                        },

                        startColResize(e) {
                            e.preventDefault();
                            this.resizingMode = 'col';
                            document.body.style.cursor = 'col-resize';
                        },

                        startRowResize(e) {
                            e.preventDefault();
                            this.resizingMode = 'row';
                            document.body.style.cursor = 'row-resize';
                        },

                        onSplitterMove(e) {
                            if (!this.resizingMode) return;
                            if (this.resizingMode === 'col') {
                                const wrap = this.$refs.workspaceGrid;
                                if (!wrap) return;
                                const rect = wrap.getBoundingClientRect();
                                const nextW = Math.round(e.clientX - rect.left);
                                this.leftWidthPx = Math.max(230, Math.min(620, nextW));
                            } else if (this.resizingMode === 'row') {
                                const leftCol = this.$refs.leftColumn;
                                if (!leftCol) return;
                                const rect = leftCol.getBoundingClientRect();
                                if (rect.height <= 100) return;
                                const relY = e.clientY - rect.top;
                                const pct = Math.round((relY / rect.height) * 100);
                                this.myHeightPct = Math.max(20, Math.min(82, pct));
                            }
                        },

                        stopSplitterResize() {
                            if (!this.resizingMode) return;
                            this.resizingMode = null;
                            document.body.style.cursor = '';
                            this.playRandomSound();
                            this.$wire.saveSplitterLayout(this.leftWidthPx, this.myHeightPct);
                        },

                        beginInlineTitleEdit(taskId, currentTitle) {
                            this.playRandomSound();
                            this.editingRowId = taskId;
                            this.editingRowTitle = currentTitle || '';
                            this.$nextTick(function () {
                                const input = document.getElementById('sf-inline-title-' + taskId);
                                if (input) {
                                    input.focus();
                                    input.select();
                                }
                            });
                        },

                        commitInlineTitleEdit(taskId) {
                            if (this.editingRowId !== taskId) return;
                            const clean = (this.editingRowTitle || '').trim();
                            this.editingRowId = null;
                            if (clean.length > 0) {
                                this.playRandomSound();
                                this.$wire.inlineUpdateTitle(taskId, clean);
                            }
                        },

                        cancelInlineTitleEdit() {
                            this.editingRowId = null;
                            this.editingRowTitle = '';
                        },

                        onTicketDragStart(e, taskId, isMineSource) {
                            this.draggedTaskId = taskId;
                            this.dragSourceIsMine = Boolean(isMineSource);
                            if (e.dataTransfer) {
                                e.dataTransfer.effectAllowed = 'move';
                                e.dataTransfer.setData('text/plain', taskId);
                            }
                        },

                        onTicketDragEnd() {
                            this.draggedTaskId = null;
                            this.dragOverMine = false;
                            this.dragOverOpen = false;
                            this.dragOverRowId = null;
                        },

                        computeOrderedIdsAfterDrop(containerEl, draggedId, beforeId) {
                            const nodes = Array.from(containerEl.querySelectorAll('[data-ticket-id]'));
                            const ids = nodes.map(function (n) { return n.getAttribute('data-ticket-id'); })
                                .filter(function (v) { return v && v !== draggedId; });

                            if (beforeId) {
                                const targetIdx = ids.indexOf(beforeId);
                                if (targetIdx >= 0) {
                                    ids.splice(targetIdx, 0, draggedId);
                                    return ids;
                                }
                            }
                            ids.push(draggedId);
                            return ids;
                        },

                        onDropIntoMine(e, beforeTaskId) {
                            e.preventDefault();
                            e.stopPropagation();
                            const id = this.draggedTaskId || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '');
                            this.dragOverMine = false;
                            this.dragOverRowId = null;
                            if (!id) return;

                            const container = this.$refs.mineListContainer;
                            const ordered = container ? this.computeOrderedIdsAfterDrop(container, id, beforeTaskId || null) : [id];

                            this.playRandomSound();
                            if (this.dragSourceIsMine) {
                                if (container && beforeTaskId) {
                                    const draggedEl = container.querySelector('[data-ticket-id="' + id + '"]');
                                    const targetEl = container.querySelector('[data-ticket-id="' + beforeTaskId + '"]');
                                    if (draggedEl && targetEl && draggedEl !== targetEl) {
                                        container.insertBefore(draggedEl, targetEl);
                                    }
                                }
                                this.$wire.reorderTickets(ordered, true);
                            } else {
                                this.$wire.dropTicketIntoPool(id, true, ordered);
                            }
                            this.draggedTaskId = null;
                        },

                        onDropIntoOpenTable(e, beforeTaskId) {
                            e.preventDefault();
                            e.stopPropagation();
                            const id = this.draggedTaskId || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '');
                            this.dragOverOpen = false;
                            this.dragOverRowId = null;
                            if (!id) return;

                            const tbody = this.$refs.openTicketsTbody;
                            const ordered = tbody ? this.computeOrderedIdsAfterDrop(tbody, id, beforeTaskId || null) : [id];

                            this.playRandomSound();
                            if (this.dragSourceIsMine) {
                                this.$wire.dropTicketIntoPool(id, false, ordered);
                            } else {
                                if (tbody && beforeTaskId) {
                                    const draggedRow = tbody.querySelector('[data-ticket-id="' + id + '"]');
                                    const targetRow = tbody.querySelector('[data-ticket-id="' + beforeTaskId + '"]');
                                    if (draggedRow && targetRow && draggedRow !== targetRow) {
                                        tbody.insertBefore(draggedRow, targetRow);
                                    }
                                }
                                this.$wire.reorderTickets(ordered, false);
                            }
                            this.draggedTaskId = null;
                        },

                        handleChatFileDrop(e) {
                            this.chatDropActive = false;
                            if (!e.dataTransfer || !e.dataTransfer.files || e.dataTransfer.files.length === 0) return;
                            e.preventDefault();
                            this.playRandomSound();
                            this.$wire.uploadMultiple('chatUploads', e.dataTransfer.files);
                        },

                        handleChatPaste(e) {
                            if (!e.clipboardData || !e.clipboardData.files || e.clipboardData.files.length === 0) return;
                            this.playRandomSound();
                            this.$wire.uploadMultiple('chatUploads', e.clipboardData.files);
                        },

                        handleAttachmentDragOut(e, mime, filename, absUrl) {
                            if (!e.dataTransfer) return;
                            const cleanMime = mime || 'application/octet-stream';
                            e.dataTransfer.setData('DownloadURL', cleanMime + ':' + filename + ':' + absUrl);
                        },

                        openLightbox(url, name) {
                            this.playRandomSound();
                            this.lightboxUrl = url;
                            this.lightboxName = name || 'Vorschau';
                            this.lightboxOpen = true;
                        },

                        scrollChatToBottom() {
                            const self = this;
                            setTimeout(function () {
                                const box = self.$refs.chatScrollBox;
                                if (box) {
                                    box.scrollTop = box.scrollHeight;
                                }
                            }, 45);
                        }
                    };
                };
            })();
        </script>
    @endonce

    @php
        $statusTextColor = function (?string $st, bool $isCompleted = false): string {
            if ($isCompleted || $st === 'completed') {
                return 'text-emerald-600 dark:text-emerald-400';
            }
            return match ($st) {
                'waiting' => 'text-yellow-500 dark:text-yellow-400',
                'in_progress' => 'text-orange-500 dark:text-orange-400',
                default => 'text-red-600 dark:text-red-400',
            };
        };
    @endphp

    {{-- MOBILE TOP TAB-SWITCHER (< lg, ausgeblendet sobald ein Ticket im Vollbild offen ist) --}}
    <div x-show="!mobileDetailOpen"
         class="flex lg:hidden items-center justify-between gap-1.5 mb-2 shrink-0 min-w-0">
        <div class="flex flex-1 min-w-0 bg-gray-100 dark:bg-gray-900 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
            <button type="button"
                    @click="mobileTab = 'open'; playRandomSound()"
                    :class="mobileTab === 'open' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                    class="flex-1 min-w-0 py-1.5 px-2 rounded-lg text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer">
                <span class="truncate">Offene</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-gray-200/80 dark:bg-gray-700 shrink-0">{{ $openPoolTickets->count() }}</span>
            </button>
            <button type="button"
                    @click="mobileTab = 'mine'; playRandomSound()"
                    :class="mobileTab === 'mine' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 dark:text-gray-400'"
                    class="flex-1 min-w-0 py-1.5 px-2 rounded-lg text-[11px] font-black transition-all flex items-center justify-center gap-1 cursor-pointer">
                <span class="truncate">Meine & Listen</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-[var(--theme-color-20)] text-[var(--theme-color)] shrink-0">{{ $myTickets->count() }}</span>
            </button>
        </div>

        @if($ownProfile)
            <button type="button"
                    wire:click="openOperatorProfileModal('{{ $currentActor['type'] }}', '{{ $currentActor['id'] }}')"
                    title="Profil öffnen"
                    class="px-2 h-9 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] flex items-center gap-1 text-[10px] font-black shrink-0 cursor-pointer">
                <span class="text-[#C5A059]">{{ str_replace('KLASSE ', 'KL. ', $ownProfile['rank_class']) }}</span>
                <span class="text-gray-700 dark:text-gray-200">{{ $ownProfile['ep_total'] }} EP</span>
            </button>
        @endif

        <button type="button"
                @click="toggleMute()"
                :title="soundMuted ? 'Klick-Sounds aktivieren' : 'Klick-Sounds stummschalten'"
                class="w-8 h-9 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-gray-600 dark:text-gray-300 shrink-0 cursor-pointer">
            <x-heroicon-m-speaker-wave x-show="!soundMuted" class="w-4 h-4 text-[var(--theme-color)]" />
            <x-heroicon-m-speaker-x-mark x-show="soundMuted" x-cloak class="w-4 h-4 text-gray-400" />
        </button>
    </div>

    {{-- HAUPT-GRID: LINKS (Meine Tickets + Listen) | ZIEHBARE LINIE | RECHTS (Offene Tickets Tabelle <-> Detail & Chat) --}}
    <div x-ref="workspaceGrid"
         @click="if (openAssigneeMenuId && !$event.target.closest('[data-assignee-popover]')) { openAssigneeMenuId = null; }"
         class="flex-1 min-h-0 flex flex-col lg:flex-row items-stretch overflow-hidden">

        {{-- ===================================================================== --}}
        {{-- LINKE SPALTE: Oben "Meine Tickets" + Unten "Listen" + "Neue Liste..." --}}
        {{-- ===================================================================== --}}
        <div x-ref="leftColumn"
             :class="mobileDetailOpen ? 'hidden lg:flex' : (mobileTab === 'mine' ? 'flex' : 'hidden lg:flex')"
             :style="window.innerWidth >= 1024 ? ('width: ' + leftWidthPx + 'px') : ''"
             class="w-full lg:shrink-0 flex-col min-h-0 h-full overflow-hidden">

            {{-- KACHEL OBEN: MEINE TICKETS --}}
            <div :style="window.innerWidth >= 1024 ? ('height: ' + myHeightPct + '%') : ''"
                 @dragover.prevent="dragOverMine = true"
                 @dragleave="dragOverMine = false"
                 @drop="onDropIntoMine($event, null)"
                 :class="dragOverMine ? 'ring-2 ring-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'bg-white dark:bg-gray-900/85'"
                 class="min-h-[150px] flex-1 lg:flex-none rounded-2xl border border-gray-200 dark:border-gray-800 shadow-xs flex flex-col overflow-hidden transition-colors">

                <div class="px-3.5 py-2.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2 shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shrink-0"></span>
                        <h2 class="text-xs sm:text-sm font-black tracking-tight text-gray-900 dark:text-white truncate">Meine Tickets</h2>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($ownProfile)
                            <button type="button"
                                    wire:click="openOperatorProfileModal('{{ $currentActor['type'] }}', '{{ $currentActor['id'] }}')"
                                    title="Mein Profil, Rang & Titel öffnen"
                                    class="hidden lg:inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:border-[var(--theme-color)] border border-transparent text-[10px] font-black cursor-pointer transition-colors">
                                <span class="text-[#C5A059]">{{ $ownProfile['rank_class'] }}</span>
                                <span class="text-gray-600 dark:text-gray-300">{{ $ownProfile['ep_total'] }} EP</span>
                            </button>
                        @endif

                        <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-[var(--theme-color-15)] text-[var(--theme-color)]">
                            {{ $myTickets->count() }}
                        </span>
                        <button type="button"
                                @click="toggleMute()"
                                :title="soundMuted ? 'Klick-Sounds aktivieren' : 'Klick-Sounds stummschalten'"
                                class="hidden lg:inline-flex w-7 h-7 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 items-center justify-center text-gray-500 dark:text-gray-400 cursor-pointer">
                            <x-heroicon-m-speaker-wave x-show="!soundMuted" class="w-4 h-4 text-[var(--theme-color)]" />
                            <x-heroicon-m-speaker-x-mark x-show="soundMuted" x-cloak class="w-4 h-4 text-gray-400" />
                        </button>
                    </div>
                </div>

                <div x-ref="mineListContainer"
                     class="flex-1 overflow-y-auto p-2 space-y-1.5">
                    @forelse($myTickets as $mTask)
                        @php
                            $mSelected = ($selectedTaskId === $mTask->id);
                            $mStatusColor = $statusTextColor($mTask->status, (bool) $mTask->is_completed);
                        @endphp
                        <div wire:key="mine-ticket-{{ $mTask->id }}"
                             data-ticket-id="{{ $mTask->id }}"
                             draggable="true"
                             @dragstart="onTicketDragStart($event, '{{ $mTask->id }}', true)"
                             @dragend="onTicketDragEnd()"
                             @dragover.prevent="dragOverRowId = '{{ $mTask->id }}'"
                             @drop="onDropIntoMine($event, '{{ $mTask->id }}')"
                             wire:click="selectTask('{{ $mTask->id }}')"
                             :class="dragOverRowId === '{{ $mTask->id }}' ? 'border-t-2 border-t-[var(--theme-color)]' : ''"
                             class="group px-3 py-2 rounded-xl border cursor-pointer transition-all {{ $mSelected ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'border-gray-100 dark:border-gray-800/90 bg-gray-50/70 dark:bg-gray-950/60 hover:border-gray-300 dark:hover:border-gray-700' }}">

                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-1.5 min-w-0 flex-1">
                                    <span class="text-gray-300 dark:text-gray-600 group-hover:text-gray-500 text-xs cursor-grab select-none">⋮⋮</span>
                                    @if($mTask->is_escalated)
                                        <x-heroicon-m-exclamation-triangle class="w-3.5 h-3.5 text-red-500 shrink-0" title="{{ $mTask->escalation_reason }}" />
                                    @endif
                                    <span class="text-xs font-bold text-gray-900 dark:text-white truncate {{ $mTask->is_completed ? 'line-through opacity-60' : '' }}">
                                        {{ $mTask->title }}
                                    </span>
                                </div>

                                <button type="button"
                                        wire:click.stop="toggleTicketMine('{{ $mTask->id }}')"
                                        title="Zurück in Offene Tickets legen (Status bleibt gleich)"
                                        class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-0.5 rounded transition-opacity shrink-0 cursor-pointer">
                                    <x-heroicon-m-arrow-right-start-on-rectangle class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <div class="mt-1 pl-4 flex items-center justify-between gap-2 text-[11px]">
                                <span class="text-gray-400 dark:text-gray-500 truncate">
                                    {{ $mTask->formatted_due ?: optional($mTask->created_at)->format('d.m.Y') }}
                                </span>
                                <span class="font-extrabold shrink-0 {{ $mStatusColor }}">
                                    {{ $mTask->status_label }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="h-full min-h-[100px] flex items-center justify-center text-center px-4">
                            <span class="text-xs font-medium text-gray-400 dark:text-gray-500">Ticket per Drag & Drop hierher ziehen</span>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ZIEHBARE HORIZONTALE UEBERGANGSLINIE (zwischen Meine Tickets & Listen) --}}
            <div @pointerdown="startRowResize($event)"
                 title="Höhe per Drag & Drop anpassen"
                 class="hidden lg:flex h-2.5 items-center justify-center cursor-row-resize group shrink-0">
                <div class="w-12 h-1 rounded-full bg-gray-200 dark:bg-gray-800 group-hover:bg-[var(--theme-color)] transition-colors"></div>
            </div>

            {{-- KACHEL UNTEN: LISTEN + NEUE LISTE --}}
            <div class="mt-2 lg:mt-0 flex-1 min-h-[160px] rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/85 shadow-xs flex flex-col overflow-hidden">
                <div class="px-3.5 py-2.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2 shrink-0">
                    <span class="text-xs font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        {{ $isEmployeeViewer ? 'Team-Listen' : 'Listen' }}
                    </span>
                    @if(!$isEmployeeViewer)
                        <a href="{{ route('admin.calender') }}"
                           class="text-[11px] font-bold text-[var(--theme-color)] hover:underline">
                            Kalender
                        </a>
                    @endif
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1">
                    <button type="button"
                            wire:click="selectList('all')"
                            @click="mobileTab = 'open'"
                            class="w-full px-3 py-2 rounded-xl text-left text-xs font-bold flex items-center justify-between gap-2 transition-colors cursor-pointer {{ $activeListId === 'all' ? 'bg-[var(--theme-color-15)] border border-[var(--theme-color)] text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/70' }}">
                        <span class="flex items-center gap-2 truncate">
                            <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] shrink-0"></span>
                            <span class="truncate">{{ $isEmployeeViewer ? 'Alle Team-Tickets' : 'Alle Listen' }}</span>
                        </span>
                        <span class="text-[11px] font-mono text-gray-500 dark:text-gray-400 shrink-0">{{ $totalAllCount }}</span>
                    </button>

                    @foreach($lists as $list)
                        <div wire:key="task-list-row-{{ $list->id }}" class="group">
                            @if(!$isEmployeeViewer && $editingListId === $list->id)
                                <form wire:submit.prevent="updateList" class="flex items-center gap-1.5 px-2 py-1">
                                    <input type="text"
                                           wire:model="editingListName"
                                           class="flex-1 min-w-0 bg-gray-50 dark:bg-gray-950 border border-[var(--theme-color)] rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white outline-none" />
                                    <button type="submit" class="px-2 py-1 rounded-lg bg-[var(--theme-color)] text-gray-900 text-[11px] font-black cursor-pointer">OK</button>
                                    <button type="button" wire:click="cancelEditList" class="px-1.5 py-1 text-xs text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">✕</button>
                                </form>
                            @else
                                <div class="w-full px-3 py-2 rounded-xl text-xs font-bold flex items-center justify-between gap-2 transition-colors {{ $activeListId === $list->id ? 'bg-[var(--theme-color-10)] border border-[var(--theme-color)] text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/70' }}">
                                    <button type="button"
                                            wire:click="selectList('{{ $list->id }}')"
                                            @click="mobileTab = 'open'"
                                            class="flex-1 min-w-0 flex items-center gap-2 text-left cursor-pointer">
                                        <span class="w-2 h-2 rounded-full shrink-0" style="background-color: {{ $list->color ?: '#C5A059' }};"></span>
                                        <span class="truncate">{{ $list->name }}</span>
                                        @if(!$isEmployeeViewer && $list->is_team_visible)
                                            <span title="Team-Liste (Für Mitarbeiter sichtbar)" class="px-1.5 py-0.2 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[9px] font-black uppercase shrink-0">Team</span>
                                        @endif
                                    </button>

                                    <div class="flex items-center gap-1 shrink-0">
                                        <span class="text-[11px] font-mono text-gray-500 dark:text-gray-400 {{ !$isEmployeeViewer ? 'group-hover:hidden' : '' }}">{{ $list->open_tasks_count }}</span>
                                        @if(!$isEmployeeViewer)
                                            <button type="button"
                                                    wire:click="toggleListTeamVisible('{{ $list->id }}')"
                                                    title="{{ $list->is_team_visible ? 'Auf Privat (Nur Leitung) umstellen' : 'Als Team-Liste für Mitarbeiter freigeben' }}"
                                                    class="hidden group-hover:inline-flex p-1 {{ $list->is_team_visible ? 'text-emerald-500' : 'text-gray-400 hover:text-gray-800 dark:hover:text-white' }} cursor-pointer">
                                                <x-heroicon-m-users class="w-3.5 h-3.5" />
                                            </button>
                                            <button type="button"
                                                    wire:click="startEditList('{{ $list->id }}', @js($list->name), @js($list->color))"
                                                    title="Liste umbenennen"
                                                    class="hidden group-hover:inline-flex p-1 text-gray-400 hover:text-gray-800 dark:hover:text-white cursor-pointer">
                                                <x-heroicon-m-pencil-square class="w-3.5 h-3.5" />
                                            </button>
                                            <button type="button"
                                                    wire:click="deleteList('{{ $list->id }}')"
                                                    wire:confirm="Liste '{{ $list->name }}' samt Tickets wirklich löschen?"
                                                    title="Liste löschen"
                                                    class="hidden group-hover:inline-flex p-1 text-gray-400 hover:text-red-500 cursor-pointer">
                                                <x-heroicon-m-trash class="w-3.5 h-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if(!$isEmployeeViewer)
                    <form wire:submit.prevent="createList"
                          class="p-2.5 border-t border-gray-100 dark:border-gray-800 flex items-center gap-1.5 shrink-0">
                        <input type="text"
                               wire:model="newListName"
                               placeholder="+ Neue Liste..."
                               class="flex-1 min-w-0 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                        <button type="submit"
                                class="px-3 py-1.5 rounded-xl bg-[var(--theme-color)] text-gray-900 text-[11px] font-black uppercase tracking-wider hover:brightness-105 transition-all shrink-0 cursor-pointer">
                            Anlegen
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ZIEHBARE VERTIKALE UEBERGANGSLINIE --}}
        <div @pointerdown="startColResize($event)"
             title="Spaltenbreite per Drag & Drop anpassen"
             class="hidden lg:flex w-3 items-center justify-center cursor-col-resize group shrink-0">
            <div class="w-1 h-16 rounded-full bg-gray-200 dark:bg-gray-800 group-hover:bg-[var(--theme-color)] transition-colors"></div>
        </div>

        {{-- ===================================================================== --}}
        {{-- RECHTE HAUPTSPALTEN-KACHEL: OFFENE TICKETS TABELLE <-> TICKET-DETAIL  --}}
        {{-- ===================================================================== --}}
        <div :class="(mobileDetailOpen || mobileTab === 'open') ? 'flex' : 'hidden lg:flex'"
             class="flex-1 min-w-0 min-h-0 h-full rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900/85 shadow-xs flex-col overflow-hidden">

            @if(!$selectedTask)
                {{-- ================================================================= --}}
                {{-- MODUS A: OFFENE TICKETS ALS INLINE-TABELLE (inkl. Bearbeiter)     --}}
                {{-- ================================================================= --}}
                <div class="px-3.5 sm:px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-2.5 shrink-0">
                    <div class="flex items-center gap-2 min-w-0">
                        <h1 class="text-sm sm:text-base font-black tracking-tight text-gray-900 dark:text-white truncate">
                            {{ $activeList ? ($activeList->name . ' · Offene Tickets') : ($isEmployeeViewer ? 'Offene Team-Tickets' : 'Offene Tickets') }}
                        </h1>
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-black bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                            {{ $openPoolTickets->count() }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        @if(!$isEmployeeViewer && count($assignableActors) > 1)
                            <select wire:model.live="assigneeFilter"
                                    class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 outline-none">
                                <option value="all">Alle Bearbeiter</option>
                                <option value="unassigned">Frei</option>
                                @foreach($assignableActors as $fAct)
                                    <option value="{{ $fAct['key'] }}">{{ $fAct['name'] }}</option>
                                @endforeach
                            </select>
                        @endif

                        <div class="relative flex-1 sm:w-56">
                            <x-heroicon-m-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                            <input type="text"
                                   wire:model.live.debounce.200ms="searchQuery"
                                   placeholder="Tickets durchsuchen..."
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl pl-9 pr-7 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                            @if($searchQuery !== '')
                                <button type="button"
                                        wire:click="$set('searchQuery', '')"
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">
                                    ✕
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Schnelle Anlage-Zeile mit dynamischem Fuzzy-Bearbeiter-Dropdown --}}
                <form wire:submit.prevent="addTask"
                      class="px-3 sm:px-5 py-2.5 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/40 flex flex-wrap xl:flex-nowrap items-center gap-2 shrink-0">
                    <input type="text"
                           wire:model="newTaskTitle"
                           placeholder="+ Neues Ticket erfassen..."
                           class="w-full xl:flex-1 min-w-0 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />

                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-1.5 w-full xl:w-auto justify-between sm:justify-end min-w-0">
                        {{-- DYNAMISCHER FUZZY BEARBEITER-DROPDOWN BEIM ANLEGEN --}}
                        <div class="relative shrink-0" data-assignee-popover>
                            <button type="button"
                                    @click="toggleAssigneeMenu('create')"
                                    class="inline-flex items-center gap-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] rounded-xl px-2.5 py-1.5 text-[11px] sm:text-xs font-bold text-gray-700 dark:text-gray-200 cursor-pointer transition-colors max-w-[165px]">
                                <x-heroicon-m-user class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" />
                                <span class="truncate" x-text="findActorLabel($wire.newTaskAssigneeKey)"></span>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                            </button>

                            <div x-show="openAssigneeMenuId === 'create'"
                                 x-cloak
                                 style="display: none;"
                                 class="absolute left-0 sm:right-0 sm:left-auto mt-1.5 w-64 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl z-40 p-2 space-y-1.5">
                                <input id="sf-assignee-search-create"
                                       type="text"
                                       x-model="assigneeSearch"
                                       placeholder="Bearbeiter suchen..."
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                                <div class="max-h-48 overflow-y-auto space-y-0.5">
                                    <button type="button"
                                            @click="$wire.set('newTaskAssigneeKey', ''); openAssigneeMenuId = null; playRandomSound()"
                                            class="w-full px-2.5 py-1.5 rounded-xl text-left text-xs font-semibold text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between cursor-pointer">
                                        <span>Kein Bearbeiter</span>
                                        <span x-show="!$wire.newTaskAssigneeKey" class="text-[var(--theme-color)] font-black">✓</span>
                                    </button>
                                    <template x-for="act in filteredActors()" :key="act.key">
                                        <button type="button"
                                                @click="$wire.set('newTaskAssigneeKey', act.key); openAssigneeMenuId = null; playRandomSound()"
                                                class="w-full px-2 py-1.5 rounded-xl text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between gap-2 cursor-pointer">
                                            <span class="flex items-center gap-2 min-w-0">
                                                <template x-if="act.avatar_url">
                                                    <img :src="act.avatar_url" class="w-6 h-6 rounded-full object-cover border border-[var(--theme-color)] shrink-0" />
                                                </template>
                                                <template x-if="!act.avatar_url">
                                                    <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] text-[10px] font-black inline-flex items-center justify-center shrink-0" x-text="act.initials"></span>
                                                </template>
                                                <span class="min-w-0">
                                                    <span class="block font-bold text-gray-900 dark:text-white truncate" x-text="act.name"></span>
                                                    <span class="block text-[10px] text-gray-400 truncate" x-text="act.role_label"></span>
                                                </span>
                                            </span>
                                            <span x-show="$wire.newTaskAssigneeKey === act.key" class="text-[var(--theme-color)] font-black shrink-0">✓</span>
                                        </button>
                                    </template>
                                    <div x-show="filteredActors().length === 0" class="py-2 text-center text-[11px] text-gray-400">
                                        Kein Treffer
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="date"
                               wire:model="newTaskRelevantFrom"
                               title="Fälligkeitsdatum (optional)"
                               class="flex-1 sm:flex-none min-w-0 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2 sm:px-2.5 py-1.5 text-[11px] sm:text-xs text-gray-700 dark:text-gray-200 focus:border-[var(--theme-color)] outline-none" />
                        <input type="time"
                               wire:model="newTaskDueTime"
                               title="Uhrzeit für Kalenderblock (optional)"
                               class="w-24 sm:w-auto min-w-0 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-2 sm:px-2.5 py-1.5 text-[11px] sm:text-xs text-gray-700 dark:text-gray-200 focus:border-[var(--theme-color)] outline-none" />
                        <button type="submit"
                                class="px-3.5 sm:px-4 py-2 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-[11px] sm:text-xs uppercase tracking-wider hover:brightness-105 transition-all shrink-0 cursor-pointer">
                            Anlegen
                        </button>
                    </div>
                </form>

                {{-- TABELLE: TITEL | BEARBEITER | ERSTELLT AM | STATUS --}}
                <div class="flex-1 overflow-y-auto overflow-x-hidden"
                     @dragover.prevent="dragOverOpen = true"
                     @dragleave="dragOverOpen = false"
                     @drop="onDropIntoOpenTable($event, null)"
                     :class="dragOverOpen ? 'bg-[var(--theme-color-10)]' : ''">
                    <table class="w-full table-fixed border-collapse text-left">
                        <thead class="sticky top-0 z-10 bg-gray-50/95 dark:bg-gray-950/95 backdrop-blur-xs border-b border-gray-200 dark:border-gray-800 text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                            <tr>
                                <th class="py-2.5 pl-3 sm:pl-5 pr-1.5 w-auto">Titel</th>
                                <th class="py-2.5 px-1 sm:px-2 w-[46px] sm:w-36 text-center sm:text-left">
                                    <span class="sm:hidden">Bearb.</span>
                                    <span class="hidden sm:inline">Bearbeiter</span>
                                </th>
                                <th class="py-2.5 px-1.5 sm:px-2 w-[62px] sm:w-28 text-right sm:text-left">
                                    <span class="sm:hidden">Datum</span>
                                    <span class="hidden sm:inline">Erstellt am</span>
                                </th>
                                <th class="py-2.5 pl-1 pr-2.5 sm:pr-5 w-[98px] sm:w-40 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody x-ref="openTicketsTbody" class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs sm:text-sm">
                            @forelse($openPoolTickets as $task)
                                @php
                                    $rowStatusColor = $statusTextColor($task->status, (bool) $task->is_completed);
                                    $rowAssigneeInitials = $task->assignee_name
                                        ? collect(preg_split('/\s+/u', trim($task->assignee_name)) ?: [])->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
                                        : '';
                                @endphp
                                <tr wire:key="open-table-row-{{ $task->id }}"
                                    data-ticket-id="{{ $task->id }}"
                                    draggable="true"
                                    @dragstart="onTicketDragStart($event, '{{ $task->id }}', false)"
                                    @dragend="onTicketDragEnd()"
                                    @dragover.prevent="dragOverRowId = '{{ $task->id }}'"
                                    @drop="onDropIntoOpenTable($event, '{{ $task->id }}')"
                                    :class="dragOverRowId === '{{ $task->id }}' ? 'border-t-2 border-t-[var(--theme-color)] bg-[var(--theme-color-10)]' : 'hover:bg-gray-50/90 dark:hover:bg-gray-800/50'"
                                    class="group transition-colors">

                                    {{-- SPALTE 1: TITEL --}}
                                    <td class="py-2.5 pl-3 sm:pl-5 pr-1.5 sm:pr-2 align-middle min-w-0">
                                        <div class="flex items-center gap-1.5 sm:gap-2 min-w-0">
                                            <span class="hidden sm:inline text-gray-300 dark:text-gray-600 group-hover:text-gray-500 cursor-grab active:cursor-grabbing text-xs select-none shrink-0"
                                                  title="Ziehen zum Sortieren oder in Meine Tickets schieben">⋮⋮</span>

                                            <template x-if="editingRowId === '{{ $task->id }}'">
                                                <input id="sf-inline-title-{{ $task->id }}"
                                                       type="text"
                                                       x-model="editingRowTitle"
                                                       @click.stop
                                                       @keydown.enter.prevent="commitInlineTitleEdit('{{ $task->id }}')"
                                                       @keydown.escape.prevent="cancelInlineTitleEdit()"
                                                       @blur="commitInlineTitleEdit('{{ $task->id }}')"
                                                       class="flex-1 min-w-0 bg-white dark:bg-gray-950 border border-[var(--theme-color)] rounded-lg px-2.5 py-1 text-xs sm:text-sm font-semibold text-gray-900 dark:text-white outline-none" />
                                            </template>

                                            <div x-show="editingRowId !== '{{ $task->id }}'"
                                                 class="flex items-center gap-1.5 min-w-0 flex-1">
                                                @if($task->is_escalated)
                                                    <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-red-500 shrink-0" title="{{ $task->escalation_reason }}" />
                                                @endif

                                                <button type="button"
                                                        wire:click="selectTask('{{ $task->id }}')"
                                                        @dblclick.stop="beginInlineTitleEdit('{{ $task->id }}', @js($task->title))"
                                                        class="text-left font-semibold text-gray-900 dark:text-white hover:text-[var(--theme-color)] dark:hover:text-[var(--theme-color)] line-clamp-2 sm:truncate break-words cursor-pointer flex-1 min-w-0 leading-snug {{ $task->is_completed ? 'line-through opacity-60' : '' }}">
                                                    {{ $task->title }}
                                                </button>

                                                @if(($task->messages_count ?? 0) > 0)
                                                    <span class="inline-flex items-center gap-0.5 text-[10px] sm:text-[11px] font-bold text-gray-400 shrink-0">
                                                        <x-heroicon-m-chat-bubble-left-right class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                                        {{ $task->messages_count }}
                                                    </span>
                                                @endif

                                                <button type="button"
                                                        @click.stop="beginInlineTitleEdit('{{ $task->id }}', @js($task->title))"
                                                        title="Titel direkt anpassen"
                                                        class="hidden sm:inline-flex sm:opacity-0 sm:group-hover:opacity-100 p-1 rounded-md hover:bg-gray-200/70 dark:hover:bg-gray-700 text-gray-400 hover:text-gray-800 dark:hover:text-white transition-opacity shrink-0 cursor-pointer">
                                                    <x-heroicon-m-pencil class="w-3.5 h-3.5" />
                                                </button>

                                                <button type="button"
                                                        wire:click="toggleTicketMine('{{ $task->id }}')"
                                                        @click.stop
                                                        title="In Meine Tickets schieben (Status bleibt gleich)"
                                                        class="hidden sm:inline-flex lg:opacity-0 lg:group-hover:opacity-100 p-1 rounded-md hover:bg-gray-200/70 dark:hover:bg-gray-700 text-gray-400 hover:text-[var(--theme-color)] transition-opacity shrink-0 cursor-pointer">
                                                    <x-heroicon-m-arrow-left-end-on-rectangle class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- SPALTE 2: BEARBEITER (Avatar klickt ins Profil-Modal, Name oeffnet Fuzzy-Dropdown) --}}
                                    <td class="py-2.5 px-1 sm:px-2 align-middle relative" @click.stop data-assignee-popover>
                                        <div class="flex items-center justify-center sm:justify-start gap-1.5 min-w-0">
                                            @if($task->assignee_id)
                                                <button type="button"
                                                        wire:click.stop="openOperatorProfileModal('{{ $task->assignee_type }}', '{{ $task->assignee_id }}', @js($task->assignee_name))"
                                                        title="Profil von {{ $task->assignee_name }} ansehen"
                                                        class="shrink-0 cursor-pointer hover:scale-105 transition-transform">
                                                    @if($task->assignee_avatar_url)
                                                        <img src="{{ $task->assignee_avatar_url }}"
                                                             alt="{{ $task->assignee_name }}"
                                                             class="w-6 h-6 rounded-full object-cover border border-[#C5A059]" />
                                                    @else
                                                        <span class="w-6 h-6 rounded-full bg-[var(--theme-color-20)] border border-[var(--theme-color)] text-[10px] font-black text-[var(--theme-color)] inline-flex items-center justify-center">
                                                            {{ $rowAssigneeInitials ?: 'SF' }}
                                                        </span>
                                                    @endif
                                                </button>
                                                <button type="button"
                                                        @click="toggleAssigneeMenu('row-{{ $task->id }}')"
                                                        class="hidden sm:inline-block text-xs font-semibold text-gray-700 dark:text-gray-200 hover:text-[var(--theme-color)] truncate cursor-pointer">
                                                    {{ $task->assignee_name }}
                                                </button>
                                            @else
                                                <button type="button"
                                                        @click="toggleAssigneeMenu('row-{{ $task->id }}')"
                                                        title="Bearbeiter zuweisen"
                                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 cursor-pointer">
                                                    <span class="w-6 h-6 rounded-full border border-dashed border-gray-300 dark:border-gray-700 inline-flex items-center justify-center text-[11px]">+</span>
                                                    <span class="hidden sm:inline">Frei</span>
                                                </button>
                                            @endif
                                        </div>

                                        {{-- Inline Fuzzy-Bearbeiter-Popover pro Tabellenzeile --}}
                                        <div x-show="openAssigneeMenuId === 'row-{{ $task->id }}'"
                                             x-cloak
                                             style="display: none;"
                                             class="absolute right-0 sm:left-0 sm:right-auto mt-1.5 w-60 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl z-40 p-2 space-y-1.5 text-left">
                                            <input id="sf-assignee-search-row-{{ $task->id }}"
                                                   type="text"
                                                   x-model="assigneeSearch"
                                                   placeholder="Bearbeiter suchen..."
                                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                                            <div class="max-h-44 overflow-y-auto space-y-0.5">
                                                <button type="button"
                                                        @click="$wire.assignTicketTo('{{ $task->id }}', ''); openAssigneeMenuId = null"
                                                        class="w-full px-2.5 py-1.5 rounded-xl text-left text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between cursor-pointer">
                                                    <span>Kein Bearbeiter</span>
                                                    @if(!$task->assignee_id)
                                                        <span class="text-[var(--theme-color)] font-black">✓</span>
                                                    @endif
                                                </button>
                                                <template x-for="act in filteredActors()" :key="act.key">
                                                    <button type="button"
                                                            @click="$wire.assignTicketTo('{{ $task->id }}', act.key); openAssigneeMenuId = null"
                                                            class="w-full px-2 py-1.5 rounded-xl text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between gap-2 cursor-pointer">
                                                        <span class="flex items-center gap-2 min-w-0">
                                                            <template x-if="act.avatar_url">
                                                                <img :src="act.avatar_url" class="w-5 h-5 rounded-full object-cover border border-[var(--theme-color)] shrink-0" />
                                                            </template>
                                                            <template x-if="!act.avatar_url">
                                                                <span class="w-5 h-5 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] text-[9px] font-black inline-flex items-center justify-center shrink-0" x-text="act.initials"></span>
                                                            </template>
                                                            <span class="truncate font-bold text-gray-900 dark:text-white" x-text="act.name"></span>
                                                        </span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- SPALTE 3: ERSTELLT AM --}}
                                    <td wire:click="selectTask('{{ $task->id }}')"
                                        class="py-2.5 px-1.5 sm:px-2 align-middle text-[10px] sm:text-xs font-mono text-gray-500 dark:text-gray-400 text-right sm:text-left whitespace-nowrap cursor-pointer">
                                        <span class="sm:hidden">{{ optional($task->created_at)->format('d.m.y') }}</span>
                                        <span class="hidden sm:inline">{{ optional($task->created_at)->format('d.m.Y') }}</span>
                                    </td>

                                    {{-- SPALTE 4: STATUS (Wort in jeweiliger Farbe; "In Bearbeitung" setzt automatisch Bearbeiter + Meine Tickets!) --}}
                                    <td class="py-2.5 pl-1 pr-2 sm:pr-4 align-middle text-right whitespace-nowrap" @click.stop>
                                        <select wire:key="tbl-status-{{ $task->id }}-{{ $task->status }}-{{ (int) $task->is_completed }}"
                                                wire:change="inlineUpdateStatus('{{ $task->id }}', $event.target.value)"
                                                @change="playRandomSound()"
                                                aria-label="Ticket-Status ändern"
                                                class="max-w-full bg-transparent border-0 py-0.5 pl-1 pr-6 text-[11px] sm:text-xs font-black cursor-pointer focus:ring-0 outline-none {{ $rowStatusColor }}">
                                            <option value="open" @selected($task->status === 'open' && !$task->is_completed) class="bg-white dark:bg-gray-900 text-red-600 dark:text-red-400 font-bold">Offen</option>
                                            <option value="waiting" @selected($task->status === 'waiting' && !$task->is_completed) class="bg-white dark:bg-gray-900 text-yellow-500 dark:text-yellow-400 font-bold">Warten</option>
                                            <option value="in_progress" @selected($task->status === 'in_progress' && !$task->is_completed) class="bg-white dark:bg-gray-900 text-orange-500 dark:text-orange-400 font-bold">In Bearbeitung</option>
                                            <option value="completed" @selected($task->is_completed || $task->status === 'completed') class="bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 font-bold">Erledigt</option>
                                        </select>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-16 px-6 text-center text-xs font-medium text-gray-400 dark:text-gray-500">
                                        Keine Tickets in dieser Ansicht.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                {{-- ================================================================= --}}
                {{-- MODUS B: TICKET-DETAIL & CHAT (inkl. Auto-Grow Beschreibung)      --}}
                {{-- ================================================================= --}}
                @php
                    $detailColor = $statusTextColor($selectedTask->status, (bool) $selectedTask->is_completed);
                    $subTotal = $selectedTask->subtasks->count();
                    $subDone = $selectedTask->subtasks->where('is_completed', true)->count();
                    $isMineForCurrent = $selectedTask->is_mine && (empty($selectedTask->assignee_id) || $selectedTask->isAssignedTo($currentActor['type'], $currentActor['id']));
                    $detInitials = $selectedTask->assignee_name
                        ? collect(preg_split('/\s+/u', trim($selectedTask->assignee_name)) ?: [])->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('')
                        : '';
                @endphp

                <div class="flex-1 min-h-0 flex flex-col overflow-hidden"
                     @dragover.prevent="chatDropActive = true"
                     @dragleave="chatDropActive = false"
                     @drop="handleChatFileDrop($event)"
                     @paste="handleChatPaste($event)">

                    {{-- KOPFLEISTE --}}
                    <div class="px-3.5 sm:px-5 py-2.5 sm:py-3 border-b border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/40 shrink-0 space-y-2 sm:space-y-0">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <button type="button"
                                        wire:click="closeDetailPanel"
                                        title="Zurück zur Übersicht"
                                        class="w-8 h-8 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)] flex items-center justify-center text-gray-700 dark:text-gray-200 shrink-0 cursor-pointer transition-colors">
                                    <x-heroicon-m-arrow-left class="w-4 h-4" />
                                </button>

                                <input type="text"
                                       wire:model.blur="detailTitle"
                                       class="hidden sm:block flex-1 min-w-0 bg-transparent border-0 focus:ring-1 focus:ring-[var(--theme-color)] rounded-lg px-1.5 py-0.5 text-base font-black text-gray-900 dark:text-white outline-none" />
                            </div>

                            <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                                <button type="button"
                                        wire:click="toggleTicketMine('{{ $selectedTask->id }}')"
                                        title="{{ $isMineForCurrent ? 'Aus Meine Tickets lösen' : 'Zu Meine Tickets hinzufügen' }}"
                                        class="px-2.5 py-1 rounded-xl border text-[11px] font-bold transition-colors cursor-pointer {{ $isMineForCurrent ? 'border-[var(--theme-color)] bg-[var(--theme-color-15)] text-[var(--theme-color)]' : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                                    <span class="hidden sm:inline">{{ $isMineForCurrent ? 'In Meine Tickets' : '+ Meine Tickets' }}</span>
                                    <span class="sm:hidden">{{ $isMineForCurrent ? '★ Meine' : '+ Meine' }}</span>
                                </button>

                                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl px-2.5 py-1 flex items-center">
                                    <select wire:model.live="detailStatus"
                                            @change="playRandomSound()"
                                            aria-label="Ticket-Status"
                                            class="bg-transparent border-0 p-0 pr-6 text-xs font-black cursor-pointer focus:ring-0 outline-none {{ $detailColor }}">
                                        <option value="open" class="bg-white dark:bg-gray-900 text-red-600 dark:text-red-400 font-bold">Offen</option>
                                        <option value="waiting" class="bg-white dark:bg-gray-900 text-yellow-500 dark:text-yellow-400 font-bold">Warten</option>
                                        <option value="in_progress" class="bg-white dark:bg-gray-900 text-orange-500 dark:text-orange-400 font-bold">In Bearbeitung</option>
                                        <option value="completed" class="bg-white dark:bg-gray-900 text-emerald-600 dark:text-emerald-400 font-bold">Erledigt</option>
                                    </select>
                                </div>

                                @if(!$isEmployeeViewer || ($selectedTask->created_by_type === 'employee' && (string) $selectedTask->created_by_id === (string) $currentActor['id']))
                                    <button type="button"
                                            wire:click="deleteTask('{{ $selectedTask->id }}')"
                                            wire:confirm="Ticket '{{ $selectedTask->title }}' wirklich löschen?"
                                            title="Ticket löschen"
                                            class="w-8 h-8 rounded-xl hover:bg-red-500/10 text-gray-400 hover:text-red-500 flex items-center justify-center transition-colors cursor-pointer">
                                        <x-heroicon-m-trash class="w-4 h-4" />
                                    </button>
                                @endif
                            </div>
                        </div>

                        <input type="text"
                               wire:model.blur="detailTitle"
                               class="sm:hidden w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-1.5 text-sm font-black text-gray-900 dark:text-white outline-none" />
                    </div>

                    {{-- KOMPAKTE BEARBEITER-, TERMIN- & METADATEN-ZEILE --}}
                    <div class="px-3.5 sm:px-5 py-2 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center gap-2 text-xs shrink-0">

                        {{-- BEARBEITER MIT PROFIL-KLICK & FUZZY-SUCHE IM TICKET-DETAIL --}}
                        <div class="relative inline-flex items-center gap-1.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2 py-1" data-assignee-popover>
                            @if($selectedTask->assignee_id)
                                <button type="button"
                                        wire:click="openOperatorProfileModal('{{ $selectedTask->assignee_type }}', '{{ $selectedTask->assignee_id }}', @js($selectedTask->assignee_name))"
                                        title="Profil von {{ $selectedTask->assignee_name }} öffnen"
                                        class="shrink-0 cursor-pointer">
                                    @if($selectedTask->assignee_avatar_url)
                                        <img src="{{ $selectedTask->assignee_avatar_url }}" class="w-5 h-5 rounded-full object-cover border border-[#C5A059]" />
                                    @else
                                        <span class="w-5 h-5 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] text-[9px] font-black inline-flex items-center justify-center">{{ $detInitials ?: 'SF' }}</span>
                                    @endif
                                </button>
                            @else
                                <x-heroicon-m-user class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                            @endif

                            <button type="button"
                                    @click="toggleAssigneeMenu('detail')"
                                    class="inline-flex items-center gap-1 font-bold text-gray-800 dark:text-gray-200 hover:text-[var(--theme-color)] cursor-pointer">
                                <span>{{ $selectedTask->assignee_name ?: 'Bearbeiter wählen' }}</span>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-400" />
                            </button>

                            <div x-show="openAssigneeMenuId === 'detail'"
                                 x-cloak
                                 style="display: none;"
                                 class="absolute left-0 top-full mt-1.5 w-64 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 shadow-2xl z-40 p-2 space-y-1.5">
                                <input id="sf-assignee-search-detail"
                                       type="text"
                                       x-model="assigneeSearch"
                                       placeholder="Bearbeiter suchen..."
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none" />
                                <div class="max-h-48 overflow-y-auto space-y-0.5">
                                    <button type="button"
                                            @click="$wire.assignTicketTo('{{ $selectedTask->id }}', ''); openAssigneeMenuId = null"
                                            class="w-full px-2.5 py-1.5 rounded-xl text-left text-xs font-semibold text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between cursor-pointer">
                                        <span>Kein Bearbeiter</span>
                                        @if(!$selectedTask->assignee_id)
                                            <span class="text-[var(--theme-color)] font-black">✓</span>
                                        @endif
                                    </button>
                                    <template x-for="act in filteredActors()" :key="act.key">
                                        <button type="button"
                                                @click="$wire.assignTicketTo('{{ $selectedTask->id }}', act.key); openAssigneeMenuId = null"
                                                class="w-full px-2 py-1.5 rounded-xl text-left text-xs hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center justify-between gap-2 cursor-pointer">
                                            <span class="flex items-center gap-2 min-w-0">
                                                <template x-if="act.avatar_url">
                                                    <img :src="act.avatar_url" class="w-5 h-5 rounded-full object-cover border border-[var(--theme-color)] shrink-0" />
                                                </template>
                                                <template x-if="!act.avatar_url">
                                                    <span class="w-5 h-5 rounded-full bg-[var(--theme-color-20)] text-[var(--theme-color)] text-[9px] font-black inline-flex items-center justify-center shrink-0" x-text="act.initials"></span>
                                                </template>
                                                <span class="min-w-0">
                                                    <span class="block font-bold text-gray-900 dark:text-white truncate" x-text="act.name"></span>
                                                    <span class="block text-[10px] text-gray-400 truncate" x-text="act.role_label"></span>
                                                </span>
                                            </span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <div class="inline-flex items-center gap-1 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1">
                            <input type="date"
                                   wire:model.change="detailRelevantFrom"
                                   class="bg-transparent border-0 p-0 text-xs text-gray-800 dark:text-gray-200 focus:ring-0 outline-none" />
                            <input type="time"
                                   wire:model.change="detailDueTime"
                                   class="bg-transparent border-0 p-0 pl-1.5 border-l border-gray-200 dark:border-gray-800 text-xs text-gray-800 dark:text-gray-200 focus:ring-0 outline-none" />
                            @if($detailRelevantFrom)
                                <button type="button"
                                        wire:click="clearSchedule"
                                        title="Termin entfernen"
                                        class="text-gray-400 hover:text-red-500 pl-1 cursor-pointer">✕</button>
                            @endif
                        </div>

                        @if($detailDueTime)
                            <select wire:model.change="detailDurationMinutes"
                                    title="Kalenderblock-Dauer"
                                    class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1 text-xs text-gray-700 dark:text-gray-200 outline-none">
                                <option value="30">30 Min.</option>
                                <option value="45">45 Min.</option>
                                <option value="60">60 Min.</option>
                                <option value="90">90 Min.</option>
                                <option value="120">120 Min.</option>
                            </select>
                        @endif

                        @if(!$isEmployeeViewer && $lists->isNotEmpty())
                            <select wire:model.change="detailTaskListId"
                                    class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-2.5 py-1 text-xs font-semibold text-gray-700 dark:text-gray-200 outline-none">
                                @foreach($lists as $lOpt)
                                    <option value="{{ $lOpt->id }}">{{ $lOpt->name }}</option>
                                @endforeach
                            </select>
                        @endif

                        <button type="button"
                                wire:click="$toggle('showSubtasksInDetail')"
                                class="px-2.5 py-1 rounded-xl border text-[11px] font-bold cursor-pointer {{ $showSubtasksInDetail ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white' : 'border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                            Schritte ({{ $subDone }}/{{ $subTotal }})
                        </button>

                        <button type="button"
                                wire:click="$toggle('showInitialPlanInDetail')"
                                class="px-2.5 py-1 rounded-xl border text-[11px] font-bold cursor-pointer {{ $showInitialPlanInDetail ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white' : 'border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400' }}">
                            Beschreibung
                        </button>
                    </div>

                    {{-- PFLICHT-ESKALATIONS-AUFLOESUNG --}}
                    @if($selectedTask->is_escalated)
                        <div class="mx-3.5 sm:mx-5 mt-3 p-3 rounded-xl border border-red-500/40 bg-red-500/5 dark:bg-red-950/30 space-y-2 shrink-0">
                            <div class="flex items-center gap-2 text-xs font-black text-red-600 dark:text-red-400">
                                <x-heroicon-m-exclamation-triangle class="w-4 h-4 shrink-0" />
                                <span>{{ $selectedTask->escalation_reason ?: 'Ticket eskaliert: Neues Fälligkeitsdatum und Status im Chat erforderlich.' }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <input type="date"
                                       wire:model="escalationNewDate"
                                       class="bg-white dark:bg-gray-900 border border-red-500/40 rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white outline-none" />
                                <input type="time"
                                       wire:model="escalationNewTime"
                                       class="bg-white dark:bg-gray-900 border border-red-500/40 rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white outline-none" />
                                <input type="text"
                                       wire:model="escalationChatNote"
                                       placeholder="Kurzer Status für den Chat (z. B. Warte weiter auf Rückmeldung)..."
                                       class="flex-1 min-w-[200px] bg-white dark:bg-gray-900 border border-red-500/40 rounded-lg px-3 py-1 text-xs text-gray-900 dark:text-white outline-none" />
                                <button type="button"
                                        wire:click="resolveTicketEscalation"
                                        class="px-3 py-1.5 rounded-lg bg-[var(--theme-color)] text-gray-900 text-xs font-black cursor-pointer">
                                    Auflösen
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($escalationError)
                        <div class="mx-3.5 sm:mx-5 mt-2 px-3 py-2 rounded-xl bg-red-500/10 border border-red-500/30 text-xs font-semibold text-red-600 dark:text-red-400 shrink-0">
                            {{ $escalationError }}
                        </div>
                    @endif

                    {{-- CHRONOLOGISCHER TICKET-STREAM --}}
                    <div x-ref="chatScrollBox"
                         :class="chatDropActive ? 'ring-2 ring-inset ring-[var(--theme-color)] bg-[var(--theme-color-10)]' : ''"
                         class="flex-1 overflow-y-auto px-3.5 sm:px-5 py-3 space-y-3">

                        @if($showSubtasksInDetail)
                            <div class="p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-950/50 space-y-2">
                                @foreach($selectedTask->subtasks as $sub)
                                    <div wire:key="detail-sub-{{ $sub->id }}" class="flex items-center justify-between gap-2 text-xs">
                                        <label class="flex items-center gap-2 cursor-pointer flex-1 min-w-0">
                                            <input type="checkbox"
                                                   wire:click="toggleTask('{{ $sub->id }}')"
                                                   @checked($sub->is_completed)
                                                   class="rounded border-gray-300 text-[var(--theme-color)] focus:ring-[var(--theme-color)]" />
                                            <span class="{{ $sub->is_completed ? 'line-through text-gray-400' : 'text-gray-800 dark:text-gray-200 font-medium' }} break-words">{{ $sub->title }}</span>
                                        </label>
                                        <button type="button"
                                                wire:click="deleteTask('{{ $sub->id }}')"
                                                class="text-gray-400 hover:text-red-500 cursor-pointer">✕</button>
                                    </div>
                                @endforeach

                                <form wire:submit.prevent="addSubtask('{{ $selectedTask->id }}')" class="flex items-center gap-1.5 pt-1">
                                    <input type="text"
                                           wire:model="newSubtaskTitle"
                                           placeholder="+ Teilschritt hinzufügen..."
                                           class="flex-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-2.5 py-1 text-xs text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]" />
                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-gray-200 dark:bg-gray-800 text-xs font-bold cursor-pointer">Plus</button>
                                </form>
                            </div>
                        @endif

                        {{-- AUTO-GROW TICKET-BESCHREIBUNG (zeigt sofort die volle Inhaltshoehe ohne Abschneiden!) --}}
                        @if($showInitialPlanInDetail)
                            <div wire:key="desc-card-{{ $selectedTask->id }}"
                                 class="p-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/40 space-y-2">
                                <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-wider text-[var(--theme-color)]">
                                    <span>Ticket-Beschreibung</span>
                                    <span class="font-mono text-gray-400">{{ optional($selectedTask->created_at)->format('d.m.Y H:i') }}</span>
                                </div>
                                <textarea wire:key="desc-textarea-{{ $selectedTask->id }}"
                                          wire:model.blur="detailAiPlan"
                                          x-init="
                                              const fitDesc = () => {
                                                  $el.style.height = 'auto';
                                                  $el.style.height = Math.max(76, $el.scrollHeight + 4) + 'px';
                                              };
                                              fitDesc();
                                              $nextTick(fitDesc);
                                              setTimeout(fitDesc, 70);
                                          "
                                          @input="$el.style.height = 'auto'; $el.style.height = Math.max(76, $el.scrollHeight + 4) + 'px'"
                                          placeholder="Notiz oder Beschreibung eintragen..."
                                          class="w-full bg-transparent border-0 p-0 text-xs sm:text-sm leading-relaxed text-gray-800 dark:text-gray-200 focus:ring-0 outline-none overflow-hidden resize-none"></textarea>
                            </div>
                        @endif

                        {{-- CHAT-CHRONIK (mit klickbarem Profilbild/Namen -> Profil-Modal & 5-Minuten-Nachbearbeitung!) --}}
                        @forelse($selectedTask->messages as $msg)
                            @php
                                $isSysMsg = in_array($msg->message_type, ['system_escalation', 'status_change'], true);
                                $canEditMsg = $msg->isEditable() && (!$isEmployeeViewer || ($msg->sender_actor_type === 'employee' && (string) $msg->sender_actor_id === (string) $currentActor['id']));
                                $msgActorType = $msg->sender_actor_type ?: ($msg->admin_id ? 'admin' : 'admin');
                                $msgActorId = $msg->sender_actor_id ?: ($msg->admin_id ?: $currentActor['id']);
                                $msgAvatar = \App\Services\Management\ManagementTaskTicketService::resolveAvatarUrl($msg->sender_avatar)
                                    ?: ($msgActorType === 'admin' ? $currentAdminAvatarUrl : null);
                            @endphp

                            @if($isSysMsg)
                                <div wire:key="tmsg-{{ $msg->id }}"
                                     class="flex items-center justify-between gap-2 px-3 py-1.5 rounded-xl bg-gray-100/80 dark:bg-gray-800/50 text-[11px] text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold">{{ $msg->content }}</span>
                                    <span class="font-mono text-[10px] text-gray-400 shrink-0">{{ optional($msg->created_at)->format('d.m.Y H:i') }}</span>
                                </div>
                            @else
                                <div wire:key="tmsg-{{ $msg->id }}"
                                     class="p-3.5 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950/70 shadow-2xs space-y-2">
                                    <div class="flex items-center justify-between gap-2">
                                        {{-- KLICK AUF AVATAR ODER NAMEN OEFFNET SOFORT DAS PROFIL-MODAL --}}
                                        <button type="button"
                                                wire:click="openOperatorProfileModal('{{ $msgActorType }}', '{{ $msgActorId }}', @js($msg->sender_name))"
                                                title="Profil von {{ $msg->sender_name }} ansehen"
                                                class="flex items-center gap-2 min-w-0 group/prof cursor-pointer text-left">
                                            @if($msgAvatar)
                                                <img src="{{ $msgAvatar }}" alt="{{ $msg->sender_name }}" class="w-7 h-7 rounded-full object-cover border border-[var(--theme-color)] group-hover/prof:scale-105 transition-transform shrink-0" />
                                            @else
                                                <span class="w-7 h-7 rounded-full bg-[var(--theme-color-20)] border border-[var(--theme-color)] text-[11px] font-black text-[var(--theme-color)] inline-flex items-center justify-center group-hover/prof:scale-105 transition-transform shrink-0">
                                                    {{ mb_substr($msg->sender_name ?: 'A', 0, 1) }}
                                                </span>
                                            @endif
                                            <span class="text-xs font-black text-gray-900 dark:text-white group-hover/prof:text-[var(--theme-color)] transition-colors truncate">{{ $msg->sender_name ?: $currentAdminName }}</span>
                                        </button>

                                        <div class="flex items-center gap-2 shrink-0">
                                            @if($canEditMsg && $editingMessageId !== $msg->id)
                                                <button type="button"
                                                        wire:click="startEditMessage('{{ $msg->id }}')"
                                                        title="Chateintrag anpassen (innerhalb von 5 Minuten möglich)"
                                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color-20)] text-[11px] font-bold text-gray-600 dark:text-gray-300 cursor-pointer transition-colors">
                                                    <x-heroicon-m-pencil-square class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                                    <span>Anpassen</span>
                                                </button>
                                            @endif
                                            <span class="text-[11px] font-mono text-gray-400">
                                                {{ optional($msg->created_at)->format('d.m.Y H:i') }}
                                                @if($msg->edited_at)
                                                    <span class="italic text-[10px]">(bearbeitet)</span>
                                                @endif
                                            </span>
                                        </div>
                                    </div>

                                    @if($editingMessageId === $msg->id)
                                        <div class="space-y-2 pt-1">
                                            <textarea wire:model="editingMessageContent"
                                                      rows="3"
                                                      class="w-full bg-gray-50 dark:bg-gray-900 border border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs sm:text-sm text-gray-900 dark:text-white outline-none"></textarea>

                                            @if(!empty($msg->attachments))
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach($msg->attachments as $att)
                                                        @php
                                                            $attPath = (string) ($att['path'] ?? '');
                                                            $attDisp = (string) ($att['original_name'] ?? ($att['name'] ?? basename($attPath)));
                                                        @endphp
                                                        @if(in_array($attPath, $editingMessageKeepPaths, true))
                                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-semibold">
                                                                <span class="truncate max-w-[160px]">{{ $attDisp }}</span>
                                                                <button type="button"
                                                                        wire:click="removeAttachmentFromEditingMessage(@js($attPath))"
                                                                        class="text-red-500 font-black cursor-pointer">✕</button>
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif

                                            <div class="flex items-center justify-between gap-2">
                                                <label class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300 cursor-pointer">
                                                    <input type="file" wire:model="editingMessageNewUploads" multiple class="hidden" />
                                                    <x-heroicon-m-paper-clip class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                                    <span>+ Datei</span>
                                                </label>

                                                <div class="flex items-center gap-1.5">
                                                    <button type="button"
                                                            wire:click="cancelEditMessage"
                                                            class="px-3 py-1 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-600 dark:text-gray-300 cursor-pointer">
                                                        Abbrechen
                                                    </button>
                                                    <button type="button"
                                                            wire:click="saveEditedMessage"
                                                            class="px-3 py-1 rounded-lg bg-[var(--theme-color)] text-gray-900 text-xs font-black cursor-pointer">
                                                        Speichern
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        @if(!empty($msg->content))
                                            <div class="text-xs sm:text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words pl-9">{{ $msg->content }}</div>
                                        @endif

                                        @if(!empty($msg->attachments) && is_array($msg->attachments))
                                            <div class="flex flex-wrap gap-2 pl-9 pt-1">
                                                @foreach($msg->attachments as $att)
                                                    @php
                                                        $attPath = (string) ($att['path'] ?? '');
                                                        $attName = (string) ($att['original_name'] ?? ($att['name'] ?? basename($attPath)));
                                                        $attMime = (string) ($att['mime'] ?? 'application/octet-stream');
                                                        $isImg = !empty($att['is_image']) || str_starts_with($attMime, 'image/');
                                                        $previewUrl = route($attachmentRouteName, ['taskId' => $selectedTask->id, 'path' => $attPath, 'inline' => 1]);
                                                        $downloadUrl = route($attachmentRouteName, ['taskId' => $selectedTask->id, 'path' => $attPath, 'download' => 1]);
                                                    @endphp
                                                    @if($isImg)
                                                        <div draggable="true"
                                                             @dragstart="handleAttachmentDragOut($event, @js($attMime), @js($attName), @js($downloadUrl))"
                                                             class="group/img relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900">
                                                            <button type="button"
                                                                    @click="openLightbox(@js($previewUrl), @js($attName))"
                                                                    class="block cursor-pointer">
                                                                <img src="{{ $previewUrl }}" alt="{{ $attName }}" class="h-20 w-auto max-w-[160px] object-cover" />
                                                            </button>
                                                            <a href="{{ $downloadUrl }}"
                                                               download="{{ $attName }}"
                                                               title="Herunterladen"
                                                               class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded bg-black/70 text-white text-[10px] font-bold opacity-0 group-hover/img:opacity-100 transition-opacity">
                                                                ↓
                                                            </a>
                                                        </div>
                                                    @else
                                                        <a href="{{ $downloadUrl }}"
                                                           download="{{ $attName }}"
                                                           draggable="true"
                                                           @dragstart="handleAttachmentDragOut($event, @js($attMime), @js($attName), @js($downloadUrl))"
                                                           title="Klicken zum Herunterladen oder direkt auf den Desktop ziehen"
                                                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 hover:border-[var(--theme-color)] text-xs font-semibold text-gray-800 dark:text-gray-200 transition-colors">
                                                            <x-heroicon-m-paper-clip class="w-3.5 h-3.5 text-[var(--theme-color)] shrink-0" />
                                                            <span class="truncate max-w-[200px]">{{ $attName }}</span>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        @empty
                            <div class="py-10 text-center text-xs text-gray-400 dark:text-gray-500">
                                Noch keine Chateinträge vorhanden. Schreibe ein Update oder ziehe Dateien per Drag & Drop hier hinein.
                            </div>
                        @endforelse
                    </div>

                    {{-- CHAT-COMPOSER --}}
                    <form wire:submit.prevent="sendChatMessage"
                          class="p-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 shrink-0 space-y-2">
                        @if(!empty($chatUploads))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($chatUploads as $uIdx => $uFile)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[var(--theme-color-15)] border border-[var(--theme-color)] text-xs font-bold text-gray-900 dark:text-white">
                                        <span class="truncate max-w-[180px]">{{ method_exists($uFile, 'getClientOriginalName') ? $uFile->getClientOriginalName() : 'Datei' }}</span>
                                        <button type="button" wire:click="removePendingChatUpload({{ $uIdx }})" class="text-red-500 font-black cursor-pointer">✕</button>
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex items-end gap-2">
                            <label title="Dateien anhängen (oder per Drag & Drop hineinziehen)"
                                   class="w-9 h-9 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] flex items-center justify-center text-gray-500 dark:text-gray-300 cursor-pointer shrink-0 transition-colors">
                                <input type="file" wire:model="chatUploads" multiple class="hidden" />
                                <x-heroicon-m-paper-clip class="w-4 h-4 text-[var(--theme-color)]" />
                            </label>

                            <textarea wire:model="chatMessage"
                                      @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.sendChatMessage(); }"
                                      rows="1"
                                      placeholder="Nachricht senden..."
                                      class="flex-1 min-w-0 h-9 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs sm:text-sm leading-5 text-gray-900 dark:text-white placeholder-gray-400 focus:border-[var(--theme-color)] outline-none resize-none max-h-28"></textarea>

                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="h-9 px-4 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-xs uppercase tracking-wider hover:brightness-105 transition-all shrink-0 cursor-pointer">
                                Senden
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>

    {{-- OPERATOR PROFIL-MODAL (KLASSE C..S, TITEL, EP & MATTE BACKGROUNDS) --}}
    @include('livewire.shop.management.partials.task-operator-profile-modal')

    {{-- LIGHTBOX MODAL IM SELBEN FENSTER --}}
    <div x-show="lightboxOpen"
         x-cloak
         style="display: none;"
         @click.self="lightboxOpen = false"
         class="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="relative max-w-4xl max-h-[90dvh] bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-2xl border border-gray-200 dark:border-gray-800 flex flex-col">
            <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                <span class="text-xs font-bold text-gray-900 dark:text-white truncate" x-text="lightboxName"></span>
                <button type="button" @click="lightboxOpen = false" class="text-gray-400 hover:text-gray-800 dark:hover:text-white font-bold cursor-pointer">✕</button>
            </div>
            <div class="p-3 overflow-auto flex items-center justify-center bg-gray-50 dark:bg-black">
                <img :src="lightboxUrl" :alt="lightboxName" class="max-h-[78dvh] w-auto object-contain rounded-lg" />
            </div>
        </div>
    </div>
</div>
