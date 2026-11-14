<div style="--theme-color: {{ $this->themeColorHex ?? '#6366f1' }};" class="p-3 sm:p-6 space-y-4 sm:space-y-6">
    {{-- HEADER --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 sm:gap-4">
        <div class="flex items-center flex-wrap gap-2 sm:gap-3">
            <h1 class="text-lg sm:text-2xl font-bold text-gray-900 dark:text-white">Projekt-Gehirn & Architektur-Blueprints</h1>
            <span title="Lebendes Architektur-Verzeichnis mit direkter System-Log-Fehlerkopplung, GoBD-, DSGVO- und KI-Guardrail-Typenschildern aus dem Quellcode."
                  class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-700 cursor-help">!</span>

            @if(!empty($errorOverlay['has_errors']))
                <button type="button"
                        wire:click="$set('filterHealth', 'cluster')"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/40 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                    {{ $errorOverlay['error_core_count'] }} Fehlerkern{{ $errorOverlay['error_core_count'] === 1 ? '' : 'e' }} aktiv
                </button>
            @else
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                    System-Log 100% Grün
                </span>
            @endif

            @if(!empty($audit['is_fully_compliant']))
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                    {{ $audit['compliant_models'] }}/{{ $audit['total_models'] }} Blueprints
                </span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button type="button"
                    wire:click="runSystemCheck"
                    wire:loading.attr="disabled"
                    title="Prüft alle Frontend-Routen, Backend-Navigation, Rollen-Portale, Datenbank-Tabellen, Model-Blueprints und das System-Log."
                    class="bg-rose-600/15 hover:bg-rose-600/25 text-rose-600 dark:text-rose-300 px-3.5 py-2 rounded-lg border border-rose-500/40 transition flex items-center gap-2 text-xs font-bold">
                <svg wire:loading.remove wire:target="runSystemCheck" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <svg wire:loading wire:target="runSystemCheck" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                System prüfen
            </button>

            <button type="button"
                    wire:click="exportGdprVvt"
                    title="Exportiert das aktuelle Verzeichnis von Verarbeitungstätigkeiten nach Art. 30 DSGVO als Markdown-Dokument."
                    class="bg-emerald-600/15 hover:bg-emerald-600/25 text-emerald-600 dark:text-emerald-300 px-3.5 py-2 rounded-lg border border-emerald-500/40 transition flex items-center gap-2 text-xs font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                Art. 30 VVT Export
            </button>

            <button type="button"
                    wire:click="rescanBrainMap"
                    wire:loading.attr="disabled"
                    title="Synchronisiert alle Projektdateien, Ein-/Ausgangs-Abhängigkeiten und Model-Blueprints neu."
                    class="bg-indigo-600/15 hover:bg-indigo-600/25 text-indigo-600 dark:text-indigo-300 px-3.5 py-2 rounded-lg border border-indigo-500/40 transition flex items-center gap-2 text-xs font-semibold">
                <svg wire:loading.remove wire:target="rescanBrainMap" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
                <svg wire:loading wire:target="rescanBrainMap" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Gehirn scannen
            </button>

            <button type="button"
                    wire:click="$refresh"
                    class="bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-3.5 py-2 rounded-lg border border-[var(--theme-color)]/50 transition flex items-center gap-2 text-xs font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Aktualisieren
            </button>
        </div>
    </div>

    {{-- ERGEBNIS-PANEL DER LOGISCHEN SYSTEMPRÜFUNG --}}
    @if(!empty($systemCheckReport))
        <div class="bg-white dark:bg-gray-900/80 border {{ $systemCheckReport['passed'] ? 'border-emerald-500/40' : 'border-rose-500/50' }} rounded-xl p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2.5">
                    @if($systemCheckReport['passed'])
                        <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                            ✓ Systemprüfung 100% Bestanden
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-500/40">
                            ✕ Abweichungen erkannt ({{ $systemCheckReport['issues_count'] }})
                        </span>
                    @endif
                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">
                        {{ $systemCheckReport['checked_at'] }} ({{ $systemCheckReport['duration_ms'] }} ms)
                    </span>
                </div>
                <button type="button" wire:click="clearSystemCheckReport" class="text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white px-2 py-1 rounded">
                    ✕
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-2.5">
                @foreach($systemCheckReport['sections'] ?? [] as $sec)
                    <div class="rounded-lg p-3 border {{ $sec['passed'] ? 'bg-emerald-500/5 border-emerald-500/25' : 'bg-rose-500/10 border-rose-500/40' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[11px] font-bold text-gray-800 dark:text-gray-200 truncate">{{ $sec['label'] }}</span>
                            <span class="text-xs font-bold {{ $sec['passed'] ? 'text-emerald-500' : 'text-rose-500' }}">{{ $sec['passed'] ? '✓' : '✕' }}</span>
                        </div>
                        <div class="mt-1 text-xs font-mono font-bold {{ $sec['passed'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                            {{ $sec['metric'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- ROTE LIVE-FEHLERKERN-KOPPLUNG (SYSTEM-LOG -> PROJEKT-GEHIRN + VERBUNDENE DATEIEN) --}}
    @if(!empty($errorOverlay['error_cores']) || !empty($errorOverlay['unmapped_logs']))
        <div class="bg-rose-500/5 dark:bg-rose-950/20 border border-rose-500/50 rounded-xl p-5 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></span>
                    <h2 class="text-sm font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">
                        Aktive Fehlerkerne im Projekt-Gehirn ({{ $errorOverlay['error_core_count'] }})
                    </h2>
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-rose-500/15 text-rose-600 dark:text-rose-300 border border-rose-500/30">
                        {{ $errorOverlay['neighbor_count'] }} verbundene Nachbardateien
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            wire:click="$set('filterHealth', 'cluster')"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-500/15 hover:bg-rose-500/25 text-rose-600 dark:text-rose-300 border border-rose-500/30 transition">
                        Fehlercluster filtern
                    </button>
                    <a href="{{ route('admin.global-logs') }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition">
                        Zum System-Log
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4">
                @foreach($errorOverlay['error_cores'] as $corePath => $core)
                    <div class="bg-white dark:bg-gray-900/90 border-2 {{ $core['is_error'] ? 'border-rose-500/60' : 'border-amber-500/50' }} rounded-xl p-4 space-y-3">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-2">
                            <div class="space-y-1">
                                <div class="flex items-center flex-wrap gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $core['is_error'] ? 'bg-rose-600 text-white' : 'bg-amber-500 text-black' }}">
                                        {{ $core['is_error'] ? 'Fehlerkern (Rot)' : 'Warnung' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700">
                                        {{ $core['group_label'] }}
                                    </span>
                                    <button type="button"
                                            wire:click="focusFile('{{ addslashes($core['file_path']) }}')"
                                            class="font-mono text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline text-left break-all">
                                        {{ $core['file_path'] }}{{ $core['line'] ? ':' . $core['line'] : '' }}
                                    </button>
                                </div>
                                <div class="text-xs font-bold text-gray-900 dark:text-white">
                                    {{ $core['primary_title'] }}
                                </div>
                                <div class="text-xs font-mono text-gray-600 dark:text-gray-300 break-words">
                                    {{ \Illuminate\Support\Str::limit($core['primary_message'], 320) }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2 py-1 rounded text-[11px] font-mono bg-rose-500/10 text-rose-600 dark:text-rose-300 border border-rose-500/30">
                                    {{ $core['occurrences_total'] }}x im Log
                                </span>
                            </div>
                        </div>

                        {{-- ZUSAMMENHÄNGENDE DATEIEN RUND UM DEN FEHLERKERN --}}
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-800 grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                                    ← Aufrufer / Eingehend ({{ count($core['incoming_files']) }})
                                </div>
                                <div class="max-h-48 overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar pr-1 flex flex-wrap gap-1.5" style="-webkit-overflow-scrolling: touch;">
                                    @forelse($core['incoming_files'] as $inc)
                                        <button type="button"
                                                wire:click="focusFile('{{ addslashes($inc['file_path']) }}')"
                                                title="{{ $inc['file_path'] }}"
                                                class="px-2 py-1 rounded text-[11px] font-mono bg-gray-100 hover:bg-rose-500/15 dark:bg-gray-800 dark:hover:bg-rose-500/20 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition text-left break-all">
                                            {{ $inc['name'] }}
                                        </button>
                                    @empty
                                        <span class="text-[11px] text-gray-400">Direkteinstieg</span>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">
                                    → Abhängigkeiten / Ausgehend ({{ count($core['outgoing_files']) }})
                                </div>
                                <div class="max-h-48 overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar pr-1 flex flex-wrap gap-1.5" style="-webkit-overflow-scrolling: touch;">
                                    @forelse($core['outgoing_files'] as $out)
                                        <button type="button"
                                                wire:click="focusFile('{{ addslashes($out['file_path']) }}')"
                                                title="{{ $out['file_path'] }}"
                                                class="px-2 py-1 rounded text-[11px] font-mono bg-gray-100 hover:bg-rose-500/15 dark:bg-gray-800 dark:hover:bg-rose-500/20 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition text-left break-all">
                                            {{ $out['name'] }}
                                        </button>
                                    @empty
                                        <span class="text-[11px] text-gray-400">Keine ausgehenden Referenzen</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                @foreach($errorOverlay['unmapped_logs'] as $uLog)
                    <div class="bg-white dark:bg-gray-900/90 border border-rose-500/40 rounded-xl p-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/20 text-rose-600 dark:text-rose-300 mr-2">
                                Externes Log
                            </span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $uLog['title'] }}:</span>
                            <span class="text-xs font-mono text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($uLog['message'], 200) }}</span>
                        </div>
                        <a href="{{ route('admin.global-logs') }}" class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:underline">Im Log öffnen</a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- KPI BLUEPRINT CARDS (CLEAN DESIGN OHNE SUBTEXTE) --}}
    @if(!empty($audit))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Blueprint-Abdeckung</span>
                        <span title="Jedes Eloquent-Model deklariert Abteilung, DSGVO-Sensibilität und Löschregel verbindlich im Quellcode." class="text-[10px] font-bold text-gray-400 cursor-help">!</span>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Pflicht</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['compliant_models'] }} / {{ $audit['total_models'] }}</span>
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Models</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">GoBD 10-Jahre Schutz</span>
                        <span title="Sperrung statt physischer Löschung bei Rechnungen, Bestellungen und steuerrelevanten Belegen." class="text-[10px] font-bold text-gray-400 cursor-help">!</span>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">§ 147 AO</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['gobd_models_count'] }}</span>
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Finanz-Models</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">DSGVO Art. 15</span>
                        <span title="Automatisierte 1-Klick-Kundenauskunft sowie Speicherbereinigung kurzlebiger Protokolldaten." class="text-[10px] font-bold text-gray-400 cursor-help">!</span>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-sky-500/15 text-sky-600 dark:text-sky-400 border border-sky-500/30">Art. 15 / 17</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['gdpr_exportable_count'] }}</span>
                    <span class="text-xs text-sky-600 dark:text-sky-400 font-medium">Export ({{ $audit['short_lived_prunable_count'] }} Prune)</span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 border {{ !empty($errorOverlay['has_errors']) ? 'border-rose-500/60 bg-rose-500/5' : 'border-gray-200 dark:border-gray-800' }} rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Fehlerkerne & KI-Schutz</span>
                        <span title="Koppelt offene System-Log-Fehler in Echtzeit an betroffene Projektdateien und überwacht KI-Schreibsperren." class="text-[10px] font-bold text-gray-400 cursor-help">!</span>
                    </div>
                    <span class="text-xs font-mono px-2 py-0.5 rounded {{ !empty($errorOverlay['has_errors']) ? 'bg-rose-500/20 text-rose-600 dark:text-rose-300 border border-rose-500/40' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' }}">
                        {{ !empty($errorOverlay['has_errors']) ? ($errorOverlay['error_core_count'] . ' Rot') : '0 Fehler' }}
                    </span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold {{ !empty($errorOverlay['has_errors']) ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $audit['ai_strictly_blocked_count'] + $audit['ai_read_only_count'] }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">KI-geschützte Models</span>
                </div>
            </div>
        </div>
    @endif

    {{-- FILTER, MOBILE CARDS & DESKTOP TABLE --}}
    @php
        $activeFilterCount = collect([$filterGroup, $filterDepartment, $filterRetention, $filterAiAccess, $filterSensitivity, $filterHealth])
            ->filter(fn ($v) => filled($v))
            ->count();
    @endphp
    <div class="bg-white dark:bg-gray-900/80 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm"
         x-data="{ mobileFiltersOpen: false }">
        <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-800 space-y-3">
            <div class="flex items-center gap-2 md:hidden">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Datei, Model, Gesetz suchen..."
                       class="flex-1 min-w-0 bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                <button type="button"
                        @click="mobileFiltersOpen = !mobileFiltersOpen"
                        class="px-3 py-2 rounded-lg border text-xs font-semibold shrink-0 flex items-center gap-1.5 transition {{ $activeFilterCount > 0 ? 'bg-[var(--theme-color)]/15 text-[var(--theme-color)] border-[var(--theme-color)]/40' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700' }}">
                    <span>Filter</span>
                    @if($activeFilterCount > 0)
                        <span class="px-1.5 py-0.2 rounded-full bg-[var(--theme-color)] text-white text-[10px] font-bold">{{ $activeFilterCount }}</span>
                    @endif
                </button>
                @if($search || $activeFilterCount > 0)
                    <button type="button" wire:click="resetFilters" title="Filter zurücksetzen" class="px-2.5 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 text-xs shrink-0">
                        ✕
                    </button>
                @endif
            </div>

            <div :class="mobileFiltersOpen ? 'grid' : 'hidden md:grid'"
                 class="grid-cols-1 md:grid-cols-2 xl:grid-cols-7 gap-2.5 sm:gap-3">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Datei, Model, Gesetz suchen..."
                       class="hidden md:block bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3.5 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">

                <select wire:model.live="filterHealth" class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    <option value="">Alle Zustände</option>
                    <option value="errors">Nur Fehlerkerne (Rot)</option>
                    <option value="cluster">Fehlerkerne + Verbundene Dateien</option>
                </select>

                <select wire:model.live="filterGroup" class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    <option value="">Alle Architektur-Layer</option>
                    <option value="2">Models (mit Blueprint)</option>
                    <option value="3">Controllers</option>
                    <option value="4">Livewire Komponenten</option>
                    <option value="5">Blade Views</option>
                    <option value="6">Routes</option>
                    <option value="7">Config</option>
                    <option value="8">Services</option>
                    <option value="9">Console/Commands</option>
                </select>

                <select wire:model.live="filterDepartment" class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    <option value="">Alle Fachabteilungen</option>
                    @foreach($departments ?? [] as $dept)
                        <option value="{{ $dept->value }}">{{ $dept->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterRetention" class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    <option value="">Alle Aufbewahrungsfristen</option>
                    @foreach($retentions ?? [] as $ret)
                        <option value="{{ $ret->value }}">{{ $ret->label() }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterSensitivity" class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                    <option value="">Alle DSGVO-Kategorien</option>
                    @foreach($sensitivities ?? [] as $sens)
                        <option value="{{ $sens->value }}">{{ $sens->label() }}</option>
                    @endforeach
                </select>

                <div class="flex items-center gap-2">
                    <select wire:model.live="filterAiAccess" class="flex-1 bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">
                        <option value="">Alle KI-Guardrails</option>
                        @foreach($aiPolicies ?? [] as $pol)
                            <option value="{{ $pol->value }}">{{ $pol->label() }}</option>
                        @endforeach
                    </select>
                    @if($search || $activeFilterCount > 0)
                        <button type="button" wire:click="resetFilters" title="Filter zurücksetzen" class="hidden md:inline-flex px-2.5 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs">
                            ✕
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- MOBILE CARD ANSICHT (< md) MIT NATIVEM TOUCH-SCROLLING FÜR DATEI-ZUSAMMENHÄNGE --}}
        <div class="md:hidden divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($nodes as $node)
                @php
                    $bp = $node->blueprint_data;
                    $nodeError = $errorOverlay['error_cores'][$node->file_path] ?? null;
                    $connectedErrorCores = $errorOverlay['neighbors'][$node->file_path] ?? [];
                    $props = is_array($node->properties) ? $node->properties : [];
                    $incomingList = $props['incoming_dependents'] ?? [];
                    $outgoingList = $props['outgoing_dependencies'] ?? [];
                    $allDeps = is_array($node->dependencies) ? $node->dependencies : [];
                    $isExpanded = ($expandedNodePath === $node->file_path) || ($nodeError !== null);
                @endphp
                <div class="p-3.5 space-y-2.5 {{ $nodeError ? 'bg-rose-500/10 dark:bg-rose-950/30 border-l-4 border-l-rose-500' : (!empty($connectedErrorCores) ? 'bg-amber-500/5 dark:bg-amber-950/15 border-l-2 border-l-amber-500/60' : '') }}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center flex-wrap gap-1.5">
                            @switch($node->group_id)
                                @case(2) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Model</span> @break
                                @case(3) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">Controller</span> @break
                                @case(4) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-pink-500/15 text-pink-600 dark:text-pink-400 border border-pink-500/30">Livewire</span> @break
                                @case(5) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">View</span> @break
                                @case(6) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">Route</span> @break
                                @case(7) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/15 text-slate-600 dark:text-slate-300 border border-slate-500/30">Config</span> @break
                                @case(8) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30">Service</span> @break
                                @case(9) <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-500/15 text-teal-600 dark:text-teal-400 border border-teal-500/30">Command</span> @break
                                @default <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-500/15 text-gray-600 dark:text-gray-400 border border-gray-500/30">System</span>
                            @endswitch

                            @if($nodeError)
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-600 text-white">Fehlerkern</span>
                            @elseif(!empty($connectedErrorCores))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">Am Fehlerkern</span>
                            @endif
                        </div>

                        <button type="button"
                                wire:click="generateStructure('{{ $node->id }}')"
                                class="text-[11px] bg-[var(--theme-color)]/15 hover:bg-[var(--theme-color)]/30 text-[var(--theme-color)] px-2.5 py-1 rounded-lg border border-[var(--theme-color)]/40 transition shrink-0 font-semibold">
                            Struktur .md
                        </button>
                    </div>

                    <div class="font-mono text-xs font-semibold break-all {{ $nodeError ? 'text-rose-600 dark:text-rose-400' : 'text-[var(--theme-color)]' }}">
                        {{ $node->file_path }}
                    </div>

                    @if($bp)
                        <div class="text-xs font-bold text-gray-900 dark:text-white">
                            {{ $bp['label'] ?? $node->name }}
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @if(!empty($bp['is_gobd_protected']))
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">GoBD 10 Jahre</span>
                            @elseif(!empty($bp['retention_label']))
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-500/10 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700">{{ $bp['retention_label'] }}</span>
                            @endif
                            @if(!empty($bp['ai_access_label']))
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">KI: {{ $bp['ai_access_label'] }}</span>
                            @endif
                        </div>
                    @endif

                    @if($nodeError)
                        <div class="p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/30 text-xs space-y-1">
                            <div class="font-bold text-rose-700 dark:text-rose-300">
                                {{ $nodeError['primary_title'] }}{{ $nodeError['line'] ? ' (Zeile ' . $nodeError['line'] . ')' : '' }}
                            </div>
                            <div class="font-mono text-[11px] text-rose-600 dark:text-rose-200 break-words">
                                {{ \Illuminate\Support\Str::limit($nodeError['primary_message'], 240) }}
                            </div>
                        </div>
                    @endif

                    <button type="button"
                            wire:click="toggleNodeNeighborhood('{{ addslashes($node->file_path) }}')"
                            class="w-full px-3 py-2 rounded-xl font-mono text-xs border transition flex items-center justify-between gap-2 {{ $nodeError ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/40' : 'bg-gray-50 dark:bg-gray-800/80 text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-700' }}">
                        <span class="font-semibold">Datei-Zusammenhänge ({{ count($allDeps) }})</span>
                        <span class="text-[10px] text-gray-500 dark:text-gray-400">
                            ← {{ count($incomingList) }} | → {{ count($outgoingList) }} {{ $isExpanded ? '▲' : '▼' }}
                        </span>
                    </button>

                    @if($isExpanded && count($allDeps) > 0)
                        <div class="max-h-64 overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-black/40 p-2.5 space-y-2"
                             style="-webkit-overflow-scrolling: touch; touch-action: pan-y;">
                            @if(!empty($incomingList))
                                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                    ← Aufrufer ({{ count($incomingList) }})
                                </div>
                                <div class="space-y-1">
                                    @foreach($incomingList as $depPath)
                                        <button type="button"
                                                wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                class="w-full text-left px-2.5 py-2 rounded-lg bg-white dark:bg-gray-900/90 active:bg-amber-500/15 border border-gray-200 dark:border-gray-800 transition flex flex-col gap-0.5">
                                            <span class="text-xs font-mono font-semibold text-gray-900 dark:text-white truncate">← {{ basename($depPath) }}</span>
                                            <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400 break-all">{{ $depPath }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($outgoingList))
                                <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
                                    → Referenziert ({{ count($outgoingList) }})
                                </div>
                                <div class="space-y-1">
                                    @foreach($outgoingList as $depPath)
                                        <button type="button"
                                                wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                class="w-full text-left px-2.5 py-2 rounded-lg bg-white dark:bg-gray-900/90 active:bg-emerald-500/15 border border-gray-200 dark:border-gray-800 transition flex flex-col gap-0.5">
                                            <span class="text-xs font-mono font-semibold text-gray-900 dark:text-white truncate">→ {{ basename($depPath) }}</span>
                                            <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400 break-all">{{ $depPath }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if(empty($incomingList) && empty($outgoingList))
                                <div class="space-y-1">
                                    @foreach($allDeps as $depPath)
                                        <button type="button"
                                                wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                class="w-full text-left px-2.5 py-2 rounded-lg bg-white dark:bg-gray-900/90 border border-gray-200 dark:border-gray-800 transition flex flex-col gap-0.5">
                                            <span class="text-xs font-mono font-semibold text-gray-900 dark:text-white truncate">{{ basename($depPath) }}</span>
                                            <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400 break-all">{{ $depPath }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-center text-xs text-gray-500">
                    Keine Knoten für diesen Filter gefunden.
                </div>
            @endforelse
        </div>

        {{-- DESKTOP TABELLEN-ANSICHT (md+) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                <thead class="bg-gray-50 dark:bg-gray-800/80 text-xs uppercase text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="px-6 py-3">Datei & Status</th>
                        <th class="px-6 py-3">Layer</th>
                        <th class="px-6 py-3">Compliance & KI-Guardrail</th>
                        <th class="px-6 py-3">Verbundene Dateien</th>
                        <th class="px-6 py-3">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nodes as $node)
                        @php
                            $bp = $node->blueprint_data;
                            $nodeError = $errorOverlay['error_cores'][$node->file_path] ?? null;
                            $connectedErrorCores = $errorOverlay['neighbors'][$node->file_path] ?? [];
                            $props = is_array($node->properties) ? $node->properties : [];
                            $incomingList = $props['incoming_dependents'] ?? [];
                            $outgoingList = $props['outgoing_dependencies'] ?? [];
                            $allDeps = is_array($node->dependencies) ? $node->dependencies : [];
                            $isExpanded = ($expandedNodePath === $node->file_path) || ($nodeError !== null);
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 transition align-top {{ $nodeError ? 'bg-rose-500/10 dark:bg-rose-950/30 border-l-4 border-l-rose-500' : (!empty($connectedErrorCores) ? 'bg-amber-500/5 dark:bg-amber-950/15 border-l-2 border-l-amber-500/60' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40') }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center flex-wrap gap-2">
                                    @if($nodeError)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-600 text-white">
                                            Fehlerkern
                                        </span>
                                    @elseif(!empty($connectedErrorCores))
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                            Am Fehlerkern
                                        </span>
                                    @endif
                                    <span class="font-mono text-xs font-semibold break-all {{ $nodeError ? 'text-rose-600 dark:text-rose-400' : 'text-[var(--theme-color)]' }}">
                                        {{ $node->file_path }}
                                    </span>
                                </div>

                                @if($nodeError)
                                    <div class="mt-2 p-2.5 rounded-lg bg-rose-500/10 border border-rose-500/30 text-xs space-y-1">
                                        <div class="font-bold text-rose-700 dark:text-rose-300">
                                            {{ $nodeError['primary_title'] }}{{ $nodeError['line'] ? ' (Zeile ' . $nodeError['line'] . ')' : '' }}
                                        </div>
                                        <div class="font-mono text-[11px] text-rose-600 dark:text-rose-200 break-words">
                                            {{ \Illuminate\Support\Str::limit($nodeError['primary_message'], 240) }}
                                        </div>
                                    </div>
                                @endif

                                @if($bp)
                                    <div class="mt-1.5 text-xs font-bold text-gray-900 dark:text-white" title="{{ $bp['description'] ?? '' }}">
                                        {{ $bp['label'] ?? $node->name }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    @switch($node->group_id)
                                        @case(2) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Model</span> @break
                                        @case(3) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">Controller</span> @break
                                        @case(4) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-pink-500/15 text-pink-600 dark:text-pink-400 border border-pink-500/30">Livewire</span> @break
                                        @case(5) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">View</span> @break
                                        @case(6) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30">Route</span> @break
                                        @case(7) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-500/15 text-slate-600 dark:text-slate-300 border border-slate-500/30">Config</span> @break
                                        @case(8) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 border border-cyan-500/30">Service</span> @break
                                        @case(9) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-teal-500/15 text-teal-600 dark:text-teal-400 border border-teal-500/30">Command</span> @break
                                        @default <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-gray-500/15 text-gray-600 dark:text-gray-400 border border-gray-500/30">System</span>
                                    @endswitch
                                </div>
                                @if($bp && !empty($bp['department_label']))
                                    <div class="mt-2 text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $bp['department_label'] }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4">
                                @if($bp)
                                    <div class="flex flex-wrap gap-1.5 max-w-md">
                                        @if(!empty($bp['is_gobd_protected']))
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                                GoBD 10 Jahre
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-gray-500/10 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700">
                                                {{ $bp['retention_label'] ?? '' }}
                                            </span>
                                        @endif

                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">
                                            {{ $bp['sensitivity_label'] ?? '' }}
                                        </span>

                                        @if(!empty($bp['is_gdpr_art15_exportable']))
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                                Art. 15 Export
                                            </span>
                                        @endif

                                        @php
                                            $aiClass = match($bp['ai_access'] ?? '') {
                                                'none' => 'bg-rose-500/20 text-rose-700 dark:text-rose-300 border-rose-500/40',
                                                'read_only' => 'bg-orange-500/15 text-orange-700 dark:text-orange-300 border-orange-500/30',
                                                'read_and_propose' => 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border-indigo-500/30',
                                                default => 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300 border-cyan-500/30',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $aiClass }}">
                                            KI: {{ $bp['ai_access_label'] ?? '' }}
                                        </span>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">Intern</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-xs">
                                <button type="button"
                                        wire:click="toggleNodeNeighborhood('{{ addslashes($node->file_path) }}')"
                                        class="px-2.5 py-1 rounded-md font-mono text-[11px] border transition {{ $nodeError ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/40' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-[var(--theme-color)]' }}">
                                    {{ count($allDeps) }} verbunden
                                </button>

                                @if($isExpanded && count($allDeps) > 0)
                                    <div class="mt-2 max-h-56 overflow-y-auto overscroll-contain touch-pan-y custom-scrollbar pr-1.5 space-y-1.5 max-w-md">
                                        @if(!empty($incomingList))
                                            <div class="text-[10px] font-bold uppercase text-gray-500">← Aufrufer ({{ count($incomingList) }})</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($incomingList as $depPath)
                                                    <button type="button"
                                                            wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                            title="{{ $depPath }}"
                                                            class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)]/20 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                                                        {{ basename($depPath) }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(!empty($outgoingList))
                                            <div class="text-[10px] font-bold uppercase text-gray-500">→ Referenziert ({{ count($outgoingList) }})</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($outgoingList as $depPath)
                                                    <button type="button"
                                                            wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                            title="{{ $depPath }}"
                                                            class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)]/20 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                                                        {{ basename($depPath) }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if(empty($incomingList) && empty($outgoingList))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($allDeps as $depPath)
                                                    <button type="button"
                                                            wire:click="focusFile('{{ addslashes($depPath) }}')"
                                                            title="{{ $depPath }}"
                                                            class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-gray-100 dark:bg-gray-800 hover:bg-[var(--theme-color)]/20 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700">
                                                        {{ basename($depPath) }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap">
                                <button type="button"
                                        wire:click="generateStructure('{{ $node->id }}')"
                                        class="text-xs bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-3 py-1.5 rounded-lg border border-[var(--theme-color)]/50 transition flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    Struktur .md
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                Keine Knoten für diesen Filter gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3 sm:p-4 border-t border-gray-200 dark:border-gray-800">
            {{ $nodes->links() }}
        </div>
    </div>
</div>
