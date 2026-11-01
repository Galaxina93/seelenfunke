<div class="p-6 space-y-6">
    {{-- HEADER --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Neurale Analyse & Architektur-Blueprints</h1>
                @if(!empty($audit['is_fully_compliant']))
                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        100% Blueprint-Konform ({{ $audit['compliant_models'] }}/{{ $audit['total_models'] }} Models)
                    </span>
                @endif
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Lebendes Architektur-Verzeichnis (Living Documentation) mit GoBD-, DSGVO-, Schnittstellen- und KI-Guardrail-Typenschildern direkt aus dem Quellcode.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button wire:click="exportGdprVvt"
                    class="bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-600 dark:text-emerald-300 px-3.5 py-2 rounded-lg border border-emerald-500/40 transition flex items-center gap-2 text-xs font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                Art. 30 DSGVO VVT exportieren
            </button>

            <button wire:click="rescanBrainMap"
                    wire:loading.attr="disabled"
                    class="bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-600 dark:text-indigo-300 px-3.5 py-2 rounded-lg border border-indigo-500/40 transition flex items-center gap-2 text-xs font-semibold">
                <svg wire:loading.remove wire:target="rescanBrainMap" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                </svg>
                <svg wire:loading wire:target="rescanBrainMap" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Gehirn & Blueprints neu scannen
            </button>

            <button wire:click="$refresh"
                    class="bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-3.5 py-2 rounded-lg border border-[var(--theme-color)]/50 shadow-sm transition flex items-center gap-2 text-xs font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Aktualisieren
            </button>
        </div>
    </div>

    {{-- KPI BLUEPRINT CARDS --}}
    @if(!empty($audit))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Blueprint-Abdeckung</span>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Pflicht-Interface</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['compliant_models'] }} / {{ $audit['total_models'] }}</span>
                    <span class="text-xs text-emerald-500 font-medium">Eloquent-Models</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Jedes Model deklariert Abteilung, DSGVO & Löschregel im Code.</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">GoBD 10-Jahre Schutz</span>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-500/15 text-amber-400 border border-amber-500/30">§ 147 AO / § 257 HGB</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['gobd_models_count'] }}</span>
                    <span class="text-xs text-amber-500 font-medium">Finanz- & Beleg-Models</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sperrung statt Löschung bei Rechnungen, Bestellungen & Buchungen.</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">DSGVO Art. 15 & Pruning</span>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-sky-500/15 text-sky-400 border border-sky-500/30">Art. 15 & Art. 17</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['gdpr_exportable_count'] }}</span>
                    <span class="text-xs text-sky-500 font-medium">Export-Models ({{ $audit['short_lived_prunable_count'] }} Auto-Prune)</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">1-Klick-Kundenauskunft + 30/90-Tage Speicherbereinigung.</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">KI-Guardrail (Funki)</span>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-rose-500/15 text-rose-400 border border-rose-500/30">Laufzeit-Sperre</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ $audit['ai_strictly_blocked_count'] + $audit['ai_read_only_count'] }}</span>
                    <span class="text-xs text-rose-500 font-medium">Schreibgeschützt ({{ $audit['ai_strictly_blocked_count'] }} Tabu)</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Verhindert unbefugte KI-Schreibzugriffe auf Finanzen & Auth-Daten.</p>
            </div>
        </div>
    @endif

    {{-- FILTER & TABLE --}}
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden shadow-sm dark:shadow-2xl">
        <div class="p-4 border-b border-gray-200 dark:border-gray-800 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Suche nach Datei, Model, Gesetz..."
                   class="bg-white dark:bg-black/50 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-900 dark:text-white px-3.5 py-2 text-xs focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)]">

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
                @if($search || $filterGroup || $filterDepartment || $filterRetention || $filterAiAccess || $filterSensitivity)
                    <button wire:click="resetFilters" title="Filter zurücksetzen" class="px-2.5 py-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs">
                        ✕
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-400">
                <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-600 dark:text-gray-300 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="px-6 py-3">Datei & Blueprint-Steckbrief</th>
                        <th class="px-6 py-3">Layer / Abteilung</th>
                        <th class="px-6 py-3">Compliance, GoBD & KI-Guardrail</th>
                        <th class="px-6 py-3">Abhängigkeiten</th>
                        <th class="px-6 py-3">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nodes as $node)
                        @php
                            $bp = $node->blueprint_data;
                        @endphp
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition align-top">
                            <td class="px-6 py-4">
                                <div class="font-mono text-xs text-[var(--theme-color)] font-semibold break-all">
                                    {{ $node->file_path }}
                                </div>
                                @if($bp)
                                    <div class="mt-1.5 text-xs font-bold text-gray-900 dark:text-white">
                                        {{ $bp['label'] ?? $node->name }}
                                    </div>
                                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 max-w-xl">
                                        {{ $bp['description'] ?? '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    @switch($node->group_id)
                                        @case(2) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">Model</span> @break
                                        @case(3) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-blue-500/15 text-blue-400 border border-blue-500/30">Controller</span> @break
                                        @case(4) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-pink-500/15 text-pink-400 border border-pink-500/30">Livewire</span> @break
                                        @case(5) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">View</span> @break
                                        @case(8) <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-cyan-500/15 text-cyan-400 border border-cyan-500/30">Service</span> @break
                                        @default <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-gray-500/15 text-gray-400 border border-gray-500/30">System</span>
                                    @endswitch
                                </div>
                                @if($bp && !empty($bp['department_label']))
                                    <div class="mt-2 text-[11px] font-semibold text-gray-700 dark:text-gray-300">
                                        {{ $bp['department_label'] }}
                                    </div>
                                    <div class="text-[10px] text-gray-500">
                                        {{ $bp['lifecycle_label'] ?? '' }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($bp)
                                    <div class="flex flex-wrap gap-1.5 max-w-md">
                                        {{-- GoBD / Retention --}}
                                        @if(!empty($bp['is_gobd_protected']))
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                                                GoBD 10 Jahre (Festgeschrieben)
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-gray-500/15 text-gray-300 border border-gray-700">
                                                {{ $bp['retention_label'] ?? '' }}
                                            </span>
                                        @endif

                                        {{-- DSGVO Sensitivity --}}
                                        <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-sky-500/15 text-sky-300 border border-sky-500/30">
                                            {{ $bp['sensitivity_label'] ?? '' }}
                                        </span>

                                        {{-- Art. 15 Export --}}
                                        @if(!empty($bp['is_gdpr_art15_exportable']))
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                                Art. 15 DSGVO Auto-Export
                                            </span>
                                        @endif

                                        {{-- AI Guardrail --}}
                                        @php
                                            $aiClass = match($bp['ai_access'] ?? '') {
                                                'none' => 'bg-rose-500/20 text-rose-300 border-rose-500/40',
                                                'read_only' => 'bg-orange-500/15 text-orange-300 border-orange-500/30',
                                                'read_and_propose' => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
                                                default => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                                            };
                                        @endphp
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $aiClass }}">
                                            KI: {{ $bp['ai_access_label'] ?? '' }}
                                        </span>

                                        {{-- Regulations --}}
                                        @foreach($bp['regulation_labels'] ?? [] as $regLabel)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-purple-500/15 text-purple-300 border border-purple-500/30">
                                                {{ $regLabel }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-gray-500 italic">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs">
                                {{ count($node->dependencies ?? []) }} referenziert
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <button wire:click="generateStructure('{{ $node->id }}')" class="text-xs bg-[var(--theme-color)]/20 hover:bg-[var(--theme-color)]/40 text-[var(--theme-color)] hover:text-white px-3 py-1.5 rounded-lg border border-[var(--theme-color)]/50 transition flex items-center gap-2">
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
                                Keine Knoten gefunden. Bitte auf „Gehirn & Blueprints neu scannen“ klicken.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-200 dark:border-gray-800">
            {{ $nodes->links() }}
        </div>
    </div>
</div>
