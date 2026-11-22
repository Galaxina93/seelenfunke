<div class="space-y-6 md:space-y-8 pb-20 font-sans antialiased text-gray-900 dark:text-gray-200" style="--theme-color: {{ $this->themeColorHex }};">

    {{-- Header --}}
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-md p-6 sm:p-8 rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-visible">
        <div class="hidden dark:block absolute top-0 right-0 p-8 opacity-10 pointer-events-none overflow-hidden rounded-[2.5rem]">
            <x-heroicon-o-server-stack class="w-36 h-36 text-[var(--theme-color)]" />
        </div>

        <div class="relative z-10 flex items-center gap-3">
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Log</h1>
            <div x-data="{ tip: false }" class="relative inline-flex items-center">
                <button type="button"
                        @mouseenter="tip = true"
                        @mouseleave="tip = false"
                        @click="tip = !tip"
                        aria-label="Info"
                        class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 text-xs font-black flex items-center justify-center hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-colors cursor-pointer">
                    !
                </button>
                <div x-show="tip"
                     x-cloak
                     x-transition
                     class="absolute left-0 top-8 z-50 w-72 sm:w-80 p-3.5 rounded-2xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-xl text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                    Zentrales Fehler- und Selbstheilungs-Protokoll für Webportal, Android App, Mini-PC und Funkenwerk Software. Der 24/7 Funkira-Wächter heilt Infrastruktur-Störungen automatisch ins 7-Tage-Archiv ([AUTO-GEHEILT]) und legt echte Code-Bugs einmalig mit Reparatur-Dossier im Auffang-Pool für Antigravity ab.
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 relative z-10 w-full lg:w-auto justify-start lg:justify-end">
            {{-- Offen vs. Auffang-Pool vs. Archiv Umschalter --}}
            <div class="inline-flex flex-wrap items-center p-1 rounded-2xl bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                <button type="button"
                        wire:click="switchViewTab('open')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $viewTab === 'open' && $statusFilter === '' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm border border-gray-200/80 dark:border-gray-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <span class="w-2 h-2 rounded-full {{ $openTotal === 0 ? 'bg-emerald-500' : ($openErrors > 0 ? 'bg-red-500' : 'bg-amber-500') }}"></span>
                    Offen ({{ number_format($openTotal, 0, ',', '.') }})
                </button>
                <button type="button"
                        wire:click="switchViewTab('pool')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $viewTab === 'pool' && $statusFilter === '' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm border border-gray-200/80 dark:border-gray-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <span class="w-2 h-2 rounded-full {{ ($poolCount ?? 0) > 0 ? 'bg-[#C5A059]' : 'bg-emerald-500' }}"></span>
                    Auffang-Pool ({{ number_format($poolCount ?? 0, 0, ',', '.') }})
                </button>
                <button type="button"
                        wire:click="switchViewTab('archived')"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $viewTab === 'archived' && $statusFilter === '' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm border border-gray-200/80 dark:border-gray-800' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                    <x-heroicon-o-archive-box class="w-3.5 h-3.5" />
                    Archiv ({{ number_format($archivedCount, 0, ',', '.') }})
                </button>
            </div>

            <button type="button"
                    wire:click="runSentinelTriageNow"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-[#C5A059]/10 text-[#8F6B29] dark:text-[#C5A059] hover:bg-[#C5A059]/20 border border-[#C5A059]/35 cursor-pointer">
                <x-heroicon-o-shield-check class="w-4 h-4 mr-2" /> Wächter prüfen
            </button>

            @if($viewTab === 'open' && $openTotal > 0)
                <button type="button"
                        wire:click="markAllAsDone"
                        class="inline-flex items-center px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-emerald-200 dark:border-emerald-500/30 cursor-pointer">
                    <x-heroicon-o-check-circle class="w-4 h-4 mr-2" /> Alle erledigen
                </button>
            @endif

            @if($viewTab === 'archived' && $archivedCount > 0)
                <button type="button"
                        wire:click="pruneArchiveNow"
                        wire:confirm="Gelöste Einträge aus dem Archiv jetzt bereinigen? (DSGVO- und Sicherheitsnachweise bleiben geschützt)"
                        class="inline-flex items-center px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-100 dark:bg-gray-950 text-gray-700 dark:text-gray-300 hover:text-red-600 dark:hover:text-red-400 border border-gray-200 dark:border-gray-800 cursor-pointer">
                    <x-heroicon-o-trash class="w-4 h-4 mr-2" /> Archiv leeren
                </button>
            @endif

            <button type="button"
                    wire:click="clearFilters"
                    class="inline-flex items-center px-5 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white border border-gray-200 dark:border-gray-800 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer">
                <x-heroicon-o-x-mark class="w-4 h-4 mr-2" /> Reset
            </button>
        </div>
    </div>

    {{-- KPI Cards (Nur ungelöste Meldungen + 7-Tage-Archiv) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
        {{-- Card 1: Offene Fehler --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border {{ $openErrors > 0 ? 'border-red-200 dark:border-red-900/40' : 'border-emerald-200 dark:border-emerald-900/40' }} transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center border {{ $openErrors > 0 ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400' : 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400' }}">
                    @if($openErrors > 0)
                        <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                    @else
                        <x-heroicon-o-check-badge class="w-6 h-6" />
                    @endif
                </div>
                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest {{ $openErrors > 0 ? 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/20' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20' }}">
                    {{ $openErrors > 0 ? 'Handlungsbedarf' : 'Grün' }}
                </span>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-1">Offene Fehler</p>
                <div class="text-3xl font-serif font-bold {{ $openErrors > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    {{ number_format($openErrors, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Card 2: Offene Warnungen --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border {{ $openWarnings > 0 ? 'border-amber-200 dark:border-amber-900/40' : 'border-gray-200 dark:border-gray-800' }} transition-colors">
            <div class="flex justify-between items-start mb-4">
                <div class="w-11 h-11 rounded-xl flex items-center justify-center border {{ $openWarnings > 0 ? 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-emerald-600 dark:text-emerald-400' }}">
                    <x-heroicon-o-shield-exclamation class="w-6 h-6" />
                </div>
                @if($openWarnings === 0)
                    <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                        Grün
                    </span>
                @endif
            </div>
            <div>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-1">Offene Warnungen</p>
                <div class="text-3xl font-serif font-bold {{ $openWarnings > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($openWarnings, 0, ',', '.') }}
                </div>
            </div>
        </div>

        {{-- Card 3: Offen nach Bereich (Direktfilter) --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800">
            <p class="text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-3">Offen nach Bereich</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach($platforms as $pKey => $pMeta)
                    @php
                        $pOpenCount = (int) ($openByPlatform[$pKey] ?? 0);
                        $pActive = $platformFilter === $pKey;
                    @endphp
                    <button type="button"
                            wire:click="selectPlatform('{{ $pKey }}')"
                            class="flex items-center justify-between px-3 py-2 rounded-xl border text-left transition-all cursor-pointer {{ $pActive ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-950 dark:border-white' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700' }}">
                        <span class="text-[10px] font-black uppercase tracking-wider truncate">{{ $pMeta['short'] }}</span>
                        <span class="text-xs font-black ml-1.5 {{ $pOpenCount > 0 ? ($pActive ? 'text-red-300 dark:text-red-600' : 'text-red-600 dark:text-red-400') : ($pActive ? 'text-emerald-300 dark:text-emerald-600' : 'text-emerald-600 dark:text-emerald-400') }}">
                            {{ $pOpenCount }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Card 4: Archiv (Auto-Bereinigung 7 Tage) --}}
        <button type="button"
                wire:click="switchViewTab('archived')"
                class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 flex flex-col justify-between text-left shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 transition-colors cursor-pointer">
            <div class="flex justify-between items-start mb-4 w-full">
                <div class="w-11 h-11 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-o-archive-box class="w-6 h-6" />
                </div>
                <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest bg-gray-100 dark:bg-gray-950 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-800">
                    Auto 7 Tage
                </span>
            </div>
            <div>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 font-black uppercase tracking-widest mb-1">Gelöst im Archiv</p>
                <div class="text-3xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($archivedCount, 0, ',', '.') }}</div>
            </div>
        </button>
    </div>

    {{-- Filter Bar --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 sm:gap-4 bg-white dark:bg-gray-900/80 backdrop-blur-md p-3 sm:p-4 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl items-center">
        {{-- Filter 1: Bereich (Plattform) --}}
        <select wire:model.live="platformFilter" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.25rem] px-4 py-3.5 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 transition-colors cursor-pointer outline-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Bereiche</option>
            @foreach($platforms as $pKey => $pMeta)
                <option value="{{ $pKey }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $pMeta['label'] }}</option>
            @endforeach
        </select>

        {{-- Filter 2: Suche --}}
        <div class="relative group">
            <input wire:model.live.debounce.300ms="search"
                   type="text"
                   placeholder="Suche..."
                   class="w-full pl-11 pr-4 py-3.5 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.25rem] focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] transition-all text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 outline-none text-[10px] font-black tracking-widest uppercase">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-500 group-focus-within:text-[var(--theme-color)] transition-colors" />
            </div>
        </div>

        {{-- Filter 3: Modul / Domain --}}
        <select wire:model.live="domainFilter" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.25rem] px-4 py-3.5 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 transition-colors cursor-pointer outline-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Module</option>
            @foreach($uniqueDomains as $domain)
                <option value="{{ $domain }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ strtoupper($domain) }}</option>
            @endforeach
        </select>

        {{-- Filter 4: Typ --}}
        <select wire:model.live="typeFilter" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.25rem] px-4 py-3.5 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 transition-colors cursor-pointer outline-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">Alle Typen</option>
            @foreach($uniqueTypes as $type)
                <option value="{{ $type }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $type }}</option>
            @endforeach
        </select>

        {{-- Filter 5: Status --}}
        <select wire:model.live="statusFilter" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-[1.25rem] px-4 py-3.5 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)]/30 focus:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 transition-colors cursor-pointer outline-none">
            <option value="" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ $viewTab === 'archived' ? 'Archiv Status' : 'Offene Status' }}</option>
            <option value="error" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">FEHLER</option>
            <option value="warning" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">WARNUNG</option>
            <option value="success" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">GELÖST</option>
            <option value="info" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">INFO</option>
            <option value="running" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">RUNNING</option>
        </select>
    </div>

    {{-- Log Table (Spalte 1 = Bereich / Plattform) --}}
    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden w-full relative">
        <div class="overflow-x-auto w-full custom-scrollbar">
            <table class="w-full text-left border-collapse min-w-[960px]">
                <thead>
                    <tr class="bg-gray-50/80 dark:bg-gray-950/80 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black text-gray-600 dark:text-gray-400 uppercase tracking-widest">
                        <th class="px-6 sm:px-8 py-5 w-[24%]">Bereich & Unterseite</th>
                        <th class="px-6 py-5 w-[20%]">Modul & Aktion</th>
                        <th class="px-6 py-5 w-[36%]">Meldung</th>
                        <th class="px-6 sm:px-8 py-5 w-[20%] text-right">Status</th>
                    </tr>
                </thead>
                @forelse($logs as $log)
                    @php
                        $rowPayload = is_array($log->payload) ? $log->payload : (json_decode((string) $log->payload, true) ?? []);
                        $displayOccurrences = max(1, (int) ($rowPayload['occurrences_count'] ?? 1));
                        $areaMeta = $log->resolveAreaAndSubpageMeta();
                        $platMeta = $log->resolvePlatformMeta();
                        $platKey = $platMeta['key'];
                        $platBadgeClasses = match ($platKey) {
                            'android' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-500/30',
                            'minipc_linux' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-200 dark:border-sky-500/30',
                            'windows_desktop' => 'bg-[#C5A059]/10 text-[#8F6B29] dark:text-[#C5A059] border-[#C5A059]/35',
                            default => 'bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-200 dark:border-indigo-500/30',
                        };
                        $displayTime = $log->finished_at ?? $log->started_at ?? $log->created_at;
                    @endphp
                    <tbody wire:key="sys-log-{{ $log->id }}" x-data="{ expanded: false }" class="border-b border-gray-100 dark:border-gray-800/60 transition-colors group hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                        <tr @click="expanded = !expanded" class="cursor-pointer">
                            {{-- SPALTE 1: BEREICH (Plattform + Umgebung + Unterseite + Zeitstempel) --}}
                            <td class="px-6 sm:px-8 py-4 align-top border-l-[3px] {{ $log->status === 'error' ? 'border-red-500' : ($log->status === 'warning' ? 'border-amber-500' : ($log->status === 'success' ? 'border-emerald-500' : 'border-gray-400 dark:border-gray-700')) }}">
                                <div class="flex flex-col items-start gap-1.5">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider border whitespace-nowrap {{ $platBadgeClasses }}">
                                            @if($platKey === 'android')
                                                <x-heroicon-o-device-phone-mobile class="w-3.5 h-3.5 shrink-0" />
                                            @elseif($platKey === 'minipc_linux')
                                                <x-heroicon-o-cpu-chip class="w-3.5 h-3.5 shrink-0" />
                                            @elseif($platKey === 'windows_desktop')
                                                <x-heroicon-o-computer-desktop class="w-3.5 h-3.5 shrink-0" />
                                            @else
                                                <x-heroicon-o-globe-alt class="w-3.5 h-3.5 shrink-0" />
                                            @endif
                                            {{ $platMeta['short'] }}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest border whitespace-nowrap {{ $areaMeta['env_badge_class'] }}">
                                            {{ $areaMeta['env_label'] }}
                                        </span>
                                    </div>

                                    {{-- Unterseite / Modul-Pfad --}}
                                    <div class="flex flex-col gap-0.5 max-w-[260px]">
                                        <span class="text-[11px] font-bold text-gray-900 dark:text-white leading-tight truncate" title="{{ $areaMeta['subpage_label'] }}">
                                            {{ $areaMeta['subpage_label'] }}
                                        </span>
                                        @if(!empty($areaMeta['subpage_path']) && $areaMeta['subpage_path'] !== $areaMeta['subpage_label'])
                                            <span class="text-[10px] font-mono text-[var(--theme-color)] truncate" title="{{ $areaMeta['subpage_path'] }}">
                                                {{ $areaMeta['subpage_path'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if($displayTime)
                                        <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                            {{ $displayTime->format('d.m.Y · H:i:s') }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- SPALTE 2: MODUL & AKTION --}}
                            <td class="px-6 py-4 align-top">
                                <div class="flex flex-col gap-1">
                                    @if($log->agent)
                                        <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $log->agent->name }}</span>
                                    @else
                                        <span class="text-xs font-bold text-[var(--theme-color)]">{{ $rowPayload['component'] ?? $log->type }}</span>
                                    @endif
                                    <span class="text-[10px] font-mono text-gray-500 dark:text-gray-400 break-all">{{ $log->action_id }}</span>
                                    @if(!empty($areaMeta['livewire_action']))
                                        <span class="text-[10px] font-mono text-gray-700 dark:text-gray-300 truncate max-w-[210px]" title="{{ $areaMeta['livewire_action'] }}">
                                            {{ $areaMeta['livewire_action'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- SPALTE 3: MELDUNG --}}
                            <td class="px-6 py-4 align-top max-w-md">
                                @if(!empty($log->title))
                                    <div class="text-[11px] font-black uppercase tracking-wider text-gray-800 dark:text-gray-200 truncate">{{ $log->title }}</div>
                                @endif
                                <div class="text-xs text-gray-600 dark:text-gray-400 font-medium truncate mt-0.5 group-hover:text-gray-900 dark:group-hover:text-white transition-colors" title="{{ $log->message }}">
                                    {{ $log->message }}
                                </div>
                            </td>

                            {{-- SPALTE 4: STATUS & AKTIONEN --}}
                            <td class="px-6 sm:px-8 py-4 text-right align-top">
                                @php
                                    $rowTriageState = $rowPayload['triage']['state'] ?? null;
                                    $rowAutoHealed = !empty($rowPayload['auto_healed']) || $rowTriageState === 'auto_healed';
                                @endphp
                                <div class="flex flex-col items-end gap-2 mt-0.5">
                                    <div class="flex justify-end items-center gap-1.5 flex-wrap">
                                        @if($rowTriageState === 'queued_for_antigravity' && in_array($log->status, ['error', 'warning'], true))
                                            <span class="px-2.5 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest border border-[#C5A059]/35 bg-[#C5A059]/10 text-[#8F6B29] dark:text-[#C5A059]">
                                                POOL
                                            </span>
                                        @elseif($rowAutoHealed)
                                            <span class="px-2.5 py-1 rounded-lg text-[8px] font-black uppercase tracking-widest border border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                                AUTO-GEHEILT
                                            </span>
                                        @endif

                                        @if($log->status === 'success' || $log->status === 'resolved')
                                            <button type="button" wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-emerald-500/30 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors cursor-pointer">
                                                GELÖST
                                            </button>
                                        @elseif($log->status === 'running')
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-sky-500/30 bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400 inline-flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-ping"></span> AKTIV
                                            </span>
                                        @elseif($log->status === 'error')
                                            <button type="button" wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-red-500/30 bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 inline-flex items-center gap-1.5 hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors cursor-pointer">
                                                FEHLER
                                                @if($displayOccurrences > 1)
                                                    <span class="bg-red-600 text-white rounded px-1.5 py-0.5 text-[8px]">{{ $displayOccurrences }}x</span>
                                                @endif
                                            </button>
                                        @elseif($log->status === 'warning')
                                            <button type="button" wire:click.stop="toggleStatus({{ $log->id }})" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 inline-flex items-center gap-1.5 hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors cursor-pointer">
                                                WARNUNG
                                                @if($displayOccurrences > 1)
                                                    <span class="bg-amber-500 text-gray-950 rounded px-1.5 py-0.5 text-[8px]">{{ $displayOccurrences }}x</span>
                                                @endif
                                            </button>
                                        @else
                                            <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest border border-gray-300 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">{{ strtoupper($log->status) }}</span>
                                        @endif

                                        <button type="button" wire:click.stop="deleteLog({{ $log->id }})" wire:confirm="Diesen Log-Eintrag endgültig löschen?" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors cursor-pointer" title="Löschen">
                                            <x-heroicon-o-trash class="w-4 h-4" />
                                        </button>
                                    </div>

                                    {{-- Expliziter "Weitere Details"-Button --}}
                                    <button type="button"
                                            @click.stop="expanded = !expanded"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-600 dark:text-gray-300 hover:border-[var(--theme-color)]/50 hover:text-[var(--theme-color)] transition-colors cursor-pointer">
                                        <span x-text="expanded ? 'Details schließen' : 'Weitere Details'">Weitere Details</span>
                                        <x-heroicon-m-chevron-down class="w-3.5 h-3.5 transition-transform" x-bind:class="expanded ? 'rotate-180 text-[var(--theme-color)]' : ''" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        {{-- Detail-Ansicht --}}
                        <tr x-show="expanded" x-transition x-cloak>
                            <td colspan="4" class="px-6 sm:px-8 py-6 bg-gray-50/90 dark:bg-gray-950/80 border-t border-gray-200 dark:border-gray-800">
                                @php
                                    $cPayload = $rowPayload;
                                    $diag = $cPayload['diagnosis'] ?? null;
                                    $tel = $cPayload['session_telemetry'] ?? null;
                                    $execCtx = is_array($cPayload['execution_context'] ?? null) ? $cPayload['execution_context'] : [];
                                    $serverEnv = is_array($cPayload['server_environment'] ?? null) ? $cPayload['server_environment'] : [];
                                    $traceList = $cPayload['trace_excerpt'] ?? null;
                                    $rawStack = $cPayload['stack'] ?? $cPayload['stack_excerpt'] ?? null;
                                    $recentEvents = $cPayload['recent_events'] ?? null;
                                    $prevEx = is_array($cPayload['previous_exception'] ?? null) ? $cPayload['previous_exception'] : null;
                                    $authUser = is_array($execCtx['authenticated_user'] ?? null) ? $execCtx['authenticated_user'] : null;
                                @endphp
                                <div class="space-y-4">
                                    {{-- WEITERE DETAILS: 3-Spalten Diagnose-Cockpit (Umgebung & Unterseite | Auslöser & Sitzung | Technischer Fehler-Ursprung) --}}
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 text-xs">
                                        {{-- Box 1: Umgebung & Unterseite --}}
                                        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-1.5">
                                                <x-heroicon-o-map-pin class="w-3.5 h-3.5" /> Umgebung & Unterseite
                                            </div>
                                            <div class="space-y-1 text-[11px]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-gray-500 dark:text-gray-400">Umgebung:</span>
                                                    <span class="font-bold text-gray-900 dark:text-white">{{ $areaMeta['platform_label'] }} · {{ $areaMeta['env_label'] }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-gray-500 dark:text-gray-400">Bereich:</span>
                                                    <span class="font-bold text-gray-900 dark:text-white text-right">{{ $areaMeta['subpage_label'] }}</span>
                                                </div>
                                                <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
                                                    <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Unterseite / Route / CLI:</span>
                                                    <span class="font-mono text-[11px] font-bold text-[var(--theme-color)] break-all">{{ $areaMeta['subpage_path'] }}</span>
                                                </div>
                                                @if(!empty($areaMeta['full_url']))
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Request-URL:</span>
                                                        <span class="font-mono text-[10px] text-gray-700 dark:text-gray-300 break-all">{{ $areaMeta['http_method'] ? $areaMeta['http_method'] . ' ' : '' }}{{ $areaMeta['full_url'] }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($areaMeta['referer']) && $areaMeta['referer'] !== $areaMeta['full_url'])
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Referer (Herkunftsseite):</span>
                                                        <span class="font-mono text-[10px] text-gray-700 dark:text-gray-300 break-all">{{ $areaMeta['referer'] }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        {{-- Box 2: Auslöser, Komponente & Sitzung --}}
                                        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-sky-600 dark:text-sky-400 flex items-center gap-1.5">
                                                <x-heroicon-o-cursor-arrow-rays class="w-3.5 h-3.5" /> Auslöser & Sitzung
                                            </div>
                                            <div class="space-y-1 text-[11px]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-gray-500 dark:text-gray-400">Aktion-ID:</span>
                                                    <span class="font-mono text-[10px] text-gray-900 dark:text-white break-all text-right">{{ $log->action_id }}</span>
                                                </div>
                                                @if(!empty($areaMeta['livewire_action']))
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Livewire-Komponente & Methode:</span>
                                                        <span class="font-mono text-[11px] font-bold text-gray-900 dark:text-white break-all">{{ $areaMeta['livewire_action'] }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($cPayload['alpine_expression']))
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Alpine.js Ausdruck:</span>
                                                        <span class="font-mono text-[10px] text-amber-700 dark:text-amber-300 break-all">{{ $cPayload['alpine_expression'] }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($authUser))
                                                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                                                        <span class="text-gray-500 dark:text-gray-400">Benutzer (Guard):</span>
                                                        <span class="font-mono text-[10px] text-gray-900 dark:text-white">{{ strtoupper((string) ($authUser['guard'] ?? 'web')) }} · {{ $authUser['email'] ?? ($authUser['id'] ?? '-') }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($cPayload['influencer_handle']) || !empty($cPayload['influencer_id']))
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-gray-500 dark:text-gray-400">Creator / Influencer:</span>
                                                        <span class="font-mono text-[10px] font-bold text-[var(--theme-color)]">{{ $cPayload['influencer_handle'] ?? $cPayload['influencer_id'] }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($cPayload['ip']) || !empty($execCtx['ip']))
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="text-gray-500 dark:text-gray-400">Client-IP:</span>
                                                        <span class="font-mono text-[10px] text-gray-800 dark:text-gray-200">{{ $cPayload['ip'] ?? $execCtx['ip'] }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                                                    <span class="text-gray-500 dark:text-gray-400">Auftreten:</span>
                                                    <span class="font-bold text-gray-900 dark:text-white">{{ $displayOccurrences }}x{{ !empty($cPayload['last_seen_at']) ? ' (zuletzt ' . \Illuminate\Support\Str::limit((string) $cPayload['last_seen_at'], 19, '') . ')' : '' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Box 3: Technischer Fehler-Ursprung --}}
                                        <div class="p-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 space-y-2">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 flex items-center gap-1.5">
                                                <x-heroicon-o-cpu-chip class="w-3.5 h-3.5" /> Technischer Ursprung
                                            </div>
                                            <div class="space-y-1 text-[11px]">
                                                @if(!empty($cPayload['exception_class']))
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Exception-Klasse:</span>
                                                        <span class="font-mono text-[11px] font-bold text-red-600 dark:text-red-400 break-all">{{ $cPayload['exception_class'] }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($cPayload['file']) || !empty($cPayload['source']))
                                                    <div>
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Datei & Zeile:</span>
                                                        <span class="font-mono text-[10px] text-gray-900 dark:text-white break-all">{{ $cPayload['file'] ?? $cPayload['source'] }}:{{ $cPayload['line'] ?? ($cPayload['lineno'] ?? '?') }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($prevEx))
                                                    <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Ursprüngliche Exception (Previous):</span>
                                                        <span class="font-mono text-[10px] text-red-600 dark:text-red-400 break-all">{{ $prevEx['exception_class'] ?? '' }}: {{ $prevEx['message'] ?? '' }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($execCtx['input_keys']) && is_array($execCtx['input_keys']))
                                                    <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Übergebene Request-Felder:</span>
                                                        <span class="font-mono text-[10px] text-gray-700 dark:text-gray-300 break-all">{{ implode(', ', array_slice($execCtx['input_keys'], 0, 15)) }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($cPayload['console_output']))
                                                    <div class="pt-1 border-t border-gray-100 dark:border-gray-800">
                                                        <span class="text-gray-500 dark:text-gray-400 block text-[10px] uppercase font-bold">Konsolen-Ausgabe:</span>
                                                        <span class="font-mono text-[10px] text-amber-700 dark:text-amber-300 break-all">{{ \Illuminate\Support\Str::limit((string) $cPayload['console_output'], 260) }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($serverEnv['php_version']))
                                                    <div class="flex items-center justify-between gap-2 pt-1 border-t border-gray-100 dark:border-gray-800 text-[10px] text-gray-500 dark:text-gray-400">
                                                        <span>PHP {{ $serverEnv['php_version'] }}</span>
                                                        <span>Cache: {{ $serverEnv['cache_driver'] ?? '-' }} · Queue: {{ $serverEnv['queue_connection'] ?? '-' }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-2 flex items-center gap-2">
                                            <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                                            {{ $log->title ? $log->title . ': ' : '' }}Nachrichten-Trace
                                        </div>
                                        <div class="text-xs text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-all p-4 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 font-mono leading-relaxed">{{ $log->message }}</div>
                                    </div>

                                    @php
                                        $agDossier = is_array($cPayload['antigravity_dossier'] ?? null) ? $cPayload['antigravity_dossier'] : null;
                                        $triageMeta = is_array($cPayload['triage'] ?? null) ? $cPayload['triage'] : null;
                                    @endphp

                                    @if(!empty($triageMeta) && ($triageMeta['state'] ?? '') === 'auto_healed')
                                        <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/70 dark:bg-emerald-500/10 space-y-1">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 flex items-center gap-2">
                                                <x-heroicon-o-shield-check class="w-4 h-4" /> Funkira Selbstheilung ({{ $triageMeta['playbook'] ?? 'auto_heal' }})
                                            </div>
                                            <p class="text-xs text-gray-800 dark:text-gray-200 font-medium">{{ $triageMeta['summary'] ?? ($cPayload['auto_heal_action'] ?? '-') }}</p>
                                        </div>
                                    @endif

                                    @if(!empty($agDossier))
                                        <div class="p-4 rounded-xl border border-[#C5A059]/35 bg-white dark:bg-gray-900 space-y-2.5">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="text-[10px] font-black uppercase tracking-widest text-[#8F6B29] dark:text-[#C5A059] flex items-center gap-2">
                                                    <x-heroicon-o-sparkles class="w-4 h-4" /> Funkira Reparatur-Dossier (Antigravity-Pool)
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 rounded bg-[#C5A059]/10 text-[#8F6B29] dark:text-[#C5A059] font-black uppercase text-[9px]">
                                                        {{ strtoupper($agDossier['criticality'] ?? 'standard') }}
                                                    </span>
                                                    @if(!empty($agDossier['fingerprint']))
                                                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-mono text-[9px]">
                                                            #{{ substr((string) $agDossier['fingerprint'], 0, 12) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-800 dark:text-gray-200 font-medium"><strong>Wurzelursache:</strong> {{ $agDossier['probable_cause'] ?? '-' }}</p>
                                            <p class="text-xs text-emerald-700 dark:text-emerald-400 font-medium"><strong>Reparatur-Plan:</strong> {{ $agDossier['recommended_action'] ?? '-' }}</p>
                                            @if(!empty($agDossier['culprit_file']))
                                                <div class="text-[11px] font-mono text-gray-700 dark:text-gray-300">
                                                    <strong>Zieldatei:</strong> {{ $agDossier['culprit_file'] }}{{ !empty($agDossier['line']) ? ':' . $agDossier['line'] : '' }}
                                                </div>
                                            @endif
                                            @if(!empty($agDossier['code_snippet']))
                                                <pre class="p-3 rounded-xl bg-gray-50 dark:bg-black border border-gray-200 dark:border-gray-800 font-mono text-[10px] text-gray-800 dark:text-gray-200 overflow-x-auto leading-relaxed">{{ $agDossier['code_snippet'] }}</pre>
                                            @endif
                                        </div>
                                    @elseif(!empty($diag))
                                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 space-y-1.5">
                                            <div class="text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                                <x-heroicon-o-light-bulb class="w-4 h-4 text-[#C5A059]" /> Ursachen-Diagnose
                                                @if(is_array($diag) && !empty($diag['category']))
                                                    <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono text-[9px]">{{ $diag['category'] }}</span>
                                                @endif
                                            </div>
                                            @if(is_array($diag))
                                                <p class="text-xs text-gray-800 dark:text-gray-200 font-medium"><strong>Ursache:</strong> {{ $diag['probable_cause'] ?? '-' }}</p>
                                                @if(!empty($diag['recommended_action']))
                                                    <p class="text-xs text-emerald-700 dark:text-emerald-400"><strong>Empfehlung:</strong> {{ $diag['recommended_action'] }}</p>
                                                @endif
                                            @else
                                                <p class="text-xs text-gray-800 dark:text-gray-200 font-medium">{{ $diag }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($cPayload['file']) || !empty($cPayload['exception_class']) || !empty($tel) || !empty($execCtx) || !empty($cPayload['device']))
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px] font-mono">
                                            @if(!empty($cPayload['file']) || !empty($cPayload['exception_class']) || !empty($cPayload['device']))
                                                <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                    <div class="text-[9px] font-sans font-black uppercase tracking-widest text-red-600 dark:text-red-400">Code- & Geräte-Ursprung</div>
                                                    @if(!empty($cPayload['device']) || !empty($cPayload['app_version']))
                                                        <div class="text-gray-800 dark:text-gray-200">{{ $cPayload['device'] ?? $platMeta['label'] }}{{ !empty($cPayload['app_version']) ? ' (v' . $cPayload['app_version'] . ')' : '' }}</div>
                                                    @endif
                                                    @if(!empty($cPayload['exception_class']))
                                                        <div class="text-gray-900 dark:text-white font-bold break-all">{{ $cPayload['exception_class'] }}</div>
                                                    @endif
                                                    @if(!empty($cPayload['file']))
                                                        <div class="text-gray-600 dark:text-gray-400 break-all">{{ $cPayload['file'] }}:{{ $cPayload['line'] ?? '?' }}</div>
                                                    @endif
                                                </div>
                                            @endif

                                            @if(!empty($tel) && is_array($tel))
                                                <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                    <div class="text-[9px] font-sans font-black uppercase tracking-widest text-sky-600 dark:text-sky-400">Live-Session</div>
                                                    <div class="text-gray-800 dark:text-gray-300">Client: <strong>{{ $tel['client_type'] ?? 'Unbekannt' }}</strong> (IP: {{ $tel['client_ip'] ?? '-' }})</div>
                                                    <div class="text-gray-600 dark:text-gray-400">Modell: {{ $tel['requested_model'] ?? '-' }} &rarr; {{ $tel['effective_model'] ?? '-' }} | Dauer: {{ $tel['session_duration_sec'] ?? 0 }}s</div>
                                                </div>
                                            @elseif(!empty($execCtx))
                                                <div class="p-3.5 rounded-xl bg-white dark:bg-black border border-gray-200 dark:border-gray-800 space-y-1">
                                                    <div class="text-[9px] font-sans font-black uppercase tracking-widest text-sky-600 dark:text-sky-400">Aufruf-Kontext</div>
                                                    @if(is_array($execCtx))
                                                        <div class="text-gray-800 dark:text-gray-300 break-all">{{ strtoupper($execCtx['mode'] ?? 'HTTP') }}: {{ $execCtx['method'] ?? '' }} {{ $execCtx['url'] ?? ($execCtx['command'] ?? '') }}</div>
                                                        @if(!empty($execCtx['ip']))
                                                            <div class="text-gray-600 dark:text-gray-400">IP: {{ $execCtx['ip'] }} | Route: {{ $execCtx['route'] ?? '-' }}</div>
                                                        @endif
                                                    @else
                                                        <div class="text-gray-800 dark:text-gray-300 break-all">{{ $execCtx }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($recentEvents) && is_array($recentEvents))
                                        <div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-sky-600 dark:text-sky-400 mb-2 flex items-center gap-2">
                                                <x-heroicon-o-clock class="w-3.5 h-3.5" /> Letzte Protokoll-Ereignisse
                                            </div>
                                            <div class="p-3.5 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 font-mono text-[10px] space-y-1 text-gray-700 dark:text-gray-300">
                                                @foreach($recentEvents as $ev)
                                                    <div><span class="text-sky-600 dark:text-sky-400">[+{{ $ev['t_ms'] ?? 0 }}ms]</span> <strong>{{ $ev['event'] ?? 'event' }}</strong>{{ !empty($ev['detail']) ? ': ' . $ev['detail'] : '' }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if((!empty($traceList) && is_array($traceList)) || !empty($rawStack))
                                        <div>
                                            <div class="text-[10px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 mb-2 flex items-center gap-2">
                                                <x-heroicon-o-bug-ant class="w-3.5 h-3.5" /> Stack-Trace
                                            </div>
                                            <div class="p-4 bg-white dark:bg-black rounded-xl border border-red-500/20 font-mono text-[10px] text-red-700 dark:text-red-300/90 whitespace-pre-wrap break-all leading-relaxed">@if(!empty($traceList) && is_array($traceList)){{ implode("\n", $traceList) }}@else{{ $rawStack }}@endif</div>
                                        </div>
                                    @endif

                                    @if(!empty($cPayload))
                                        @php
                                            $clonedPayload = collect($cPayload)
                                                ->except(['changes', 'before', 'after'])
                                                ->toArray();
                                        @endphp
                                        @if(!empty($clonedPayload))
                                            <div>
                                                <div class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-2 flex items-center gap-2">
                                                    <x-heroicon-o-code-bracket class="w-3.5 h-3.5" /> Payload (JSON)
                                                </div>
                                                <div class="text-[10px] text-emerald-700 dark:text-emerald-400 whitespace-pre-wrap break-all p-4 bg-white dark:bg-black rounded-xl border border-gray-200 dark:border-gray-800 font-mono leading-relaxed">{{ json_encode($clonedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</div>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody wire:key="sys-log-empty">
                        <tr>
                            <td colspan="4" class="px-8 py-24 text-center">
                                @if($viewTab === 'open' && $statusFilter === '')
                                    <div class="inline-flex flex-col items-center gap-3">
                                        <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                            <x-heroicon-o-check-badge class="w-8 h-8" />
                                        </div>
                                        <span class="font-serif font-bold text-xl text-gray-900 dark:text-white">Keine offenen Meldungen</span>
                                    </div>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400 font-serif text-lg">Keine Einträge gefunden</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
        @if($logs->hasPages())
            <div class="px-8 py-6 bg-gray-50/50 dark:bg-gray-900/30 border-t border-gray-200 dark:border-gray-800">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
