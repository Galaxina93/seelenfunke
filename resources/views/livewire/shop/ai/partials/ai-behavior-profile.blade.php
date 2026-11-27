@php
    $praiseCount = (int) ($behaviorProfile?->praise_count ?? 0);
    $criticismCount = (int) ($behaviorProfile?->criticism_count ?? 0);
    $correctionCount = (int) ($behaviorProfile?->correction_count ?? 0);
    $negativeTotal = $criticismCount + $correctionCount;
    $totalSignals = $praiseCount + $negativeTotal;
    $satisfactionScore = $totalSignals > 0 ? (float) ($behaviorProfile?->satisfaction_score ?? 100.0) : 100.0;
    $trendDelta = (float) ($behaviorProfile?->trend_delta ?? 0.0);
    $proposals = is_array($behaviorProfile?->pending_rule_proposals) ? $behaviorProfile->pending_rule_proposals : [];
    $topFriction = is_array($behaviorProfile?->top_friction_points) ? $behaviorProfile->top_friction_points : [];
    $topStrengths = is_array($behaviorProfile?->top_strengths) ? $behaviorProfile->top_strengths : [];
    $ruleImpacts = is_array($behaviorProfile?->rule_impact_metrics) ? $behaviorProfile->rule_impact_metrics : [];
    $activeRulesCount = $allBehaviorRules->where('is_active', true)->count();
    $improvedRulesCount = collect($ruleImpacts)->where('is_improved', true)->count();
    $primaryFriction = $topFriction[0] ?? null;
    $impactsByPrefId = collect($ruleImpacts)->keyBy('preference_id');
@endphp

@once
<style>
    @keyframes sfBehaviorOrbit {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes sfBehaviorPulseLine {
        0%, 100% { opacity: 0.45; transform: scaleX(0.96); }
        50% { opacity: 0.95; transform: scaleX(1.02); }
    }
    .sf-behavior-orbit {
        animation: sfBehaviorOrbit 26s linear infinite;
        transform-origin: center;
    }
    .sf-behavior-pulse-line {
        animation: sfBehaviorPulseLine 4.6s ease-in-out infinite;
        transform-origin: center;
    }
    @media (prefers-reduced-motion: reduce) {
        .sf-behavior-orbit,
        .sf-behavior-pulse-line {
            animation: none !important;
        }
    }
</style>
@endonce

<div class="space-y-6">

    @if($behaviorNotice)
        <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-white dark:bg-[#0D0D10] border border-[#C5A059]/40 text-xs font-semibold text-gray-900 dark:text-white shadow-xs">
            <span class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#C5A059]"></span>
                {{ $behaviorNotice }}
            </span>
            <button type="button" wire:click="$set('behaviorNotice', null)" class="text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">
                <x-heroicon-m-x-mark class="w-4 h-4" />
            </button>
        </div>
    @endif

    {{-- 4 HERO-KPI-KARTEN (Champagner-Gold Medaillons + architektonische Telemetrie-Geometrie, 0 Subtexte) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

        {{-- KPI 1: Zufriedenheits-Index --}}
        <div class="group relative overflow-hidden rounded-2xl p-6 bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/60 shadow-sm transition-colors">
            <svg class="pointer-events-none absolute -right-6 -bottom-6 w-36 h-36 text-[#C5A059] opacity-[0.07] dark:opacity-[0.11]" viewBox="0 0 120 120" fill="none">
                <circle cx="60" cy="60" r="46" stroke="currentColor" stroke-width="0.75" stroke-dasharray="4 6" class="sf-behavior-orbit" />
                <circle cx="60" cy="60" r="28" stroke="currentColor" stroke-width="0.6" />
                <path d="M12 60H108M60 12V108" stroke="currentColor" stroke-width="0.45" />
            </svg>
            <div class="relative z-10 flex items-center justify-between mb-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    Zufriedenheits-Index
                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Gewichtetes Verhältnis aus Lob gegenüber Kritik und Korrekturen im gewählten Zeitraum inkl. Trend zur Vorperiode." />
                </span>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#F7E8C3] via-[#DFC27D] to-[#B0893D] dark:from-[#2E2514] dark:via-[#1E1910] dark:to-[#120F0A] border border-[#C5A059]/60 shadow-xs">
                    <svg class="w-5 h-5 text-[#5E4414] dark:text-[#E5C98B]" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.4" stroke-dasharray="3 3" class="sf-behavior-orbit" />
                        <path d="M8.5 12.5l2.3 2.3 4.8-5.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
            <div class="relative z-10">
                <div class="flex items-baseline gap-2.5">
                    <span class="text-3xl font-black tracking-tight text-gray-900 dark:text-white">{{ number_format($satisfactionScore, 1, ',', '.') }} %</span>
                    @if($totalSignals > 0)
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $trendDelta >= 0 ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300' }}">
                            {{ $trendDelta >= 0 ? '+' : '' }}{{ number_format($trendDelta, 1, ',', '.') }} %
                        </span>
                    @endif
                </div>
                <div class="mt-3 w-full bg-gray-100 dark:bg-gray-900 h-1.5 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-[#B0893D] to-[#DFC27D] transition-all duration-500" style="width: {{ min(100, max(0, $satisfactionScore)) }}%"></div>
                </div>
            </div>
        </div>

        {{-- KPI 2: Lob vs. Kritik --}}
        <div class="group relative overflow-hidden rounded-2xl p-6 bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/60 shadow-sm transition-colors">
            <svg class="pointer-events-none absolute -right-4 -bottom-4 w-36 h-24 text-[#C5A059] opacity-[0.08] dark:opacity-[0.12]" viewBox="0 0 140 80" fill="none">
                <path d="M4 58 C 28 24, 52 72, 78 34 S 116 48, 136 18" stroke="currentColor" stroke-width="1.2" class="sf-behavior-pulse-line" />
                <path d="M4 68 H 136" stroke="currentColor" stroke-width="0.6" stroke-dasharray="3 5" />
            </svg>
            <div class="relative z-10 flex items-center justify-between mb-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    Lob vs. Kritik
                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Erfasste positive Signale gegenüber Kritik- und Korrektur-Signalen über alle Kanäle." />
                </span>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#F7E8C3] via-[#DFC27D] to-[#B0893D] dark:from-[#2E2514] dark:via-[#1E1910] dark:to-[#120F0A] border border-[#C5A059]/60 shadow-xs">
                    <svg class="w-5 h-5 text-[#5E4414] dark:text-[#E5C98B]" viewBox="0 0 24 24" fill="none">
                        <path d="M4 16l5-5 4 3 7-7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                        <circle cx="20" cy="7" r="2" fill="currentColor" />
                    </svg>
                </div>
            </div>
            <div class="relative z-10 flex items-baseline gap-2">
                <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ $praiseCount }}</span>
                <span class="text-sm font-bold text-gray-400">/</span>
                <span class="text-3xl font-black text-rose-600 dark:text-rose-400">{{ $negativeTotal }}</span>
                <span class="ml-auto text-[11px] font-mono font-bold text-gray-500 dark:text-gray-400">{{ $totalSignals }} Signale</span>
            </div>
        </div>

        {{-- KPI 3: Aktive Verhaltensregeln --}}
        <div class="group relative overflow-hidden rounded-2xl p-6 bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/60 shadow-sm transition-colors">
            <svg class="pointer-events-none absolute -right-5 -bottom-5 w-32 h-32 text-[#C5A059] opacity-[0.07] dark:opacity-[0.11]" viewBox="0 0 120 120" fill="none">
                <polygon points="60,12 104,36 104,84 60,108 16,84 16,36" stroke="currentColor" stroke-width="0.9" class="sf-behavior-orbit" />
                <polygon points="60,30 88,46 88,74 60,90 32,74 32,46" stroke="currentColor" stroke-width="0.6" />
            </svg>
            <div class="relative z-10 flex items-center justify-between mb-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    Aktive Regeln
                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Von Alina freigegebene, kanalspezifische Verhaltensregeln inkl. Vorher/Nachher-Wirkungsmessung." />
                </span>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#F7E8C3] via-[#DFC27D] to-[#B0893D] dark:from-[#2E2514] dark:via-[#1E1910] dark:to-[#120F0A] border border-[#C5A059]/60 shadow-xs">
                    <svg class="w-5 h-5 text-[#5E4414] dark:text-[#E5C98B]" viewBox="0 0 24 24" fill="none">
                        <path d="M12 3l7 3v5c0 5-3 8.5-7 10-4-1.5-7-5-7-10V6l7-3z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        <path d="M9.5 12.2l1.8 1.8 3.6-3.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
            <div class="relative z-10 flex items-baseline justify-between gap-2">
                <span class="text-3xl font-black text-gray-900 dark:text-white">{{ $activeRulesCount }}</span>
                <span class="text-[11px] font-bold {{ $improvedRulesCount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-500 dark:text-gray-400' }}">
                    {{ $improvedRulesCount > 0 ? "{$improvedRulesCount} nachweislich wirksam" : count($proposals) . ' Vorschläge' }}
                </span>
            </div>
        </div>

        {{-- KPI 4: Top-Verbesserungspotenzial --}}
        <div class="group relative overflow-hidden rounded-2xl p-6 bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/60 shadow-sm transition-colors">
            <svg class="pointer-events-none absolute -right-6 -bottom-6 w-36 h-36 text-[#C5A059] opacity-[0.07] dark:opacity-[0.11]" viewBox="0 0 120 120" fill="none">
                <circle cx="60" cy="60" r="42" stroke="currentColor" stroke-width="0.7" />
                <circle cx="60" cy="60" r="24" stroke="currentColor" stroke-width="0.7" stroke-dasharray="2 5" class="sf-behavior-orbit" />
                <circle cx="60" cy="60" r="6" fill="currentColor" />
            </svg>
            <div class="relative z-10 flex items-center justify-between mb-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    Top-Potenzial
                    <x-heroicon-o-information-circle class="w-3.5 h-3.5 text-gray-400 cursor-help" title="Häufigster erkannter Reibungspunkt im aktuellen Filterbereich." />
                </span>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-gradient-to-br from-[#F7E8C3] via-[#DFC27D] to-[#B0893D] dark:from-[#2E2514] dark:via-[#1E1910] dark:to-[#120F0A] border border-[#C5A059]/60 shadow-xs">
                    <svg class="w-5 h-5 text-[#5E4414] dark:text-[#E5C98B]" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.6" />
                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.6" />
                        <path d="M12 2v3M12 19v3M2 12h3M19 12h3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                </div>
            </div>
            <div class="relative z-10">
                @if($primaryFriction)
                    <div class="text-base font-black text-gray-900 dark:text-white truncate" title="{{ $primaryFriction['category_label'] }}">
                        {{ $primaryFriction['category_label'] }}
                    </div>
                    <div class="mt-1 flex items-center gap-2 text-[11px] font-bold text-[#9A742B] dark:text-[#C5A059]">
                        <span>{{ $primaryFriction['channel_label'] }}</span>
                        <span>·</span>
                        <span>{{ $primaryFriction['count'] }}×</span>
                    </div>
                @else
                    <div class="text-xl font-black text-emerald-600 dark:text-emerald-400">Stabil</div>
                    <div class="mt-1 text-[11px] font-bold text-gray-500 dark:text-gray-400">0 offene Reibungspunkte</div>
                @endif
            </div>
        </div>
    </div>

    {{-- KANAL-MATRIX (6 Bedienkanäle mit 1-Klick-Filter) --}}
    <div class="bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <h2 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white flex items-center gap-2">
                Kanal-Matrix
                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 cursor-help" title="Klicke auf einen Kanal, um Verhaltensprofil, Regelvorschläge und Signal-Tabelle gezielt auf diesen Kanal zu filtern." />
            </h2>
            @if($behaviorChannel !== 'all')
                <button type="button" wire:click="selectBehaviorChannel('all')" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 hover:border-[#C5A059] cursor-pointer">
                    Alle Kanäle
                </button>
            @endif
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            @foreach($channelMatrix as $cm)
                @php
                    $isActiveChan = $behaviorChannel === $cm['channel'];
                @endphp
                <button type="button"
                        wire:click="selectBehaviorChannel('{{ $isActiveChan ? 'all' : $cm['channel'] }}')"
                        class="text-left p-3.5 rounded-xl border transition-all cursor-pointer {{ $isActiveChan ? 'bg-[#C5A059]/10 border-[#C5A059] shadow-xs' : 'bg-gray-50/70 dark:bg-gray-950 border-gray-200 dark:border-gray-800 hover:border-[#C5A059]/50' }}">
                    <div class="flex items-center justify-between gap-1.5 mb-2">
                        <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $cm['label'] }}</span>
                        <span class="text-[11px] font-black {{ $cm['score'] === null ? 'text-gray-400' : ($cm['score'] >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($cm['score'] >= 50 ? 'text-[#9A742B] dark:text-[#C5A059]' : 'text-rose-600 dark:text-rose-400')) }}">
                            {{ $cm['score'] !== null ? number_format($cm['score'], 0) . '%' : '-' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-[10px] font-mono text-gray-500 dark:text-gray-400">
                        <span class="text-emerald-600 dark:text-emerald-400">+{{ $cm['praise'] }}</span>
                        <span class="text-rose-600 dark:text-rose-400">-{{ $cm['criticism'] + $cm['correction'] }}</span>
                        <span>{{ $cm['total'] }} ges.</span>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    {{-- REGELVORSCHLÄGE (CLOSED LOOP) & AKTIVE VERHALTENSREGELN (BEFORE/AFTER) --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        {{-- Linke Spalte: Regelvorschläge --}}
        <div class="bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white flex items-center gap-2">
                    Regelvorschläge
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 cursor-help" title="Werden automatisch ab 3 gleichartigen Kritiken (oder 2 starken Korrekturen) im selben Kanal vorgeschlagen. Kein Prompt wird ohne deine 1-Klick-Freigabe geändert." />
                </h2>
                <span class="px-2 py-0.5 rounded-lg text-[10px] font-black bg-[#C5A059]/15 text-[#9A742B] dark:text-[#C5A059]">
                    {{ count($proposals) }} offen
                </span>
            </div>

            <div class="space-y-3">
                @forelse($proposals as $prop)
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#C5A059]/15 text-[#9A742B] dark:text-[#C5A059]">
                                    {{ $prop['channel_label'] }}
                                </span>
                                <span class="text-xs font-black text-gray-900 dark:text-white">{{ $prop['category_label'] }}</span>
                            </div>
                            <span class="text-[11px] font-mono font-bold text-rose-600 dark:text-rose-400">
                                {{ $prop['signal_count'] }}× erkannt
                            </span>
                        </div>

                        <div class="text-xs text-gray-800 dark:text-gray-200 bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2 font-medium">
                            {{ $prop['suggested_directive'] }}
                        </div>

                        @if($isAdminViewer)
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button"
                                        wire:click="activateProposal('{{ $prop['channel'] }}', '{{ $prop['category'] }}', @js($prop['suggested_directive']), @js($prop['ai_agent_id']))"
                                        class="px-3 py-1.5 rounded-lg text-[11px] font-black bg-[#C5A059] text-gray-950 hover:brightness-105 transition-all cursor-pointer">
                                    Als Dauer-Regel aktivieren
                                </button>
                                <button type="button"
                                        wire:click="escalateProposal('{{ $prop['channel'] }}', '{{ $prop['category'] }}', @js($prop['suggested_directive']), @js($prop['ai_agent_id']))"
                                        class="px-3 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-700 hover:border-[#C5A059] cursor-pointer">
                                    Als Ticket übergeben
                                </button>
                                <button type="button"
                                        wire:click="dismissProposal('{{ $prop['channel'] }}', '{{ $prop['category'] }}', @js($prop['ai_agent_id']))"
                                        class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer ml-auto">
                                    Verwerfen
                                </button>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                        <x-heroicon-o-check-circle class="w-9 h-9 text-emerald-500/70 mx-auto mb-2" />
                        <span class="text-xs font-bold text-gray-600 dark:text-gray-300">Keine offenen Regelvorschläge</span>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Rechte Spalte: Aktive Verhaltensregeln + Vorher/Nachher (20 Interaktionen) --}}
        <div class="bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white flex items-center gap-2">
                    Verhaltensregeln & Wirkung
                    <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 cursor-help" title="Aktivierte Verhaltensregeln mit Vorher/Nachher-Vergleich der Kritik-Rate über die nächsten 20 Interaktionen auf demselben Kanal." />
                </h2>
                <span class="text-[11px] font-mono font-bold text-gray-500 dark:text-gray-400">
                    {{ $activeRulesCount }} aktiv
                </span>
            </div>

            <div class="space-y-3">
                @forelse($allBehaviorRules as $rule)
                    @php
                        $impact = $impactsByPrefId->get($rule->id);
                        $meta = is_array($rule->structured_value) ? $rule->structured_value : [];
                        $rChan = $meta['channel'] ?? 'all';
                        $rChanLabel = \App\Models\Ai\AiBehaviorFeedback::CHANNELS[$rChan] ?? $rChan;
                    @endphp
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-2 {{ !$rule->is_active ? 'opacity-60' : '' }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $rule->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                <span class="text-xs font-bold text-gray-900 dark:text-white truncate">{{ $rule->subject_label }}</span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-200/70 dark:bg-gray-900 text-gray-700 dark:text-gray-300">{{ $rChanLabel }}</span>
                                @if($isAdminViewer)
                                    <button type="button"
                                            wire:click="toggleBehaviorRule('{{ $rule->id }}')"
                                            class="px-2 py-0.5 rounded text-[10px] font-bold border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-[#C5A059] cursor-pointer">
                                        {{ $rule->is_active ? 'Pausieren' : 'Aktivieren' }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="text-xs text-gray-700 dark:text-gray-300">{{ $rule->rule_summary }}</div>

                        @if($impact)
                            <div class="flex flex-wrap items-center gap-3 pt-1 text-[10px] font-mono text-gray-500 dark:text-gray-400">
                                <span>Vorher: <strong class="text-gray-900 dark:text-white">{{ number_format($impact['baseline_criticism_rate'], 1, ',', '.') }} %</strong></span>
                                <span>Nachher ({{ $impact['after_sample_size'] }}/{{ $impact['target_window'] }}):
                                    <strong class="text-gray-900 dark:text-white">
                                        {{ $impact['after_criticism_rate'] !== null ? number_format($impact['after_criticism_rate'], 1, ',', '.') . ' %' : 'Sammelt Daten' }}
                                    </strong>
                                </span>
                                @if($impact['criticism_rate_delta'] !== null)
                                    <span class="px-1.5 py-0.5 rounded font-bold {{ $impact['criticism_rate_delta'] <= 0 ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300' }}">
                                        {{ $impact['criticism_rate_delta'] > 0 ? '+' : '' }}{{ number_format($impact['criticism_rate_delta'], 1, ',', '.') }} %
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-10 text-center border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400">Noch keine Verhaltensregeln aktiviert</span>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- SCHLANKE SIGNAL-TABELLE (Nur Zeit, Kanal, Agent, Polarität, Kategorie, Status, Details) --}}
    <div class="bg-white dark:bg-[#09090B] border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <h2 class="text-xs font-black uppercase tracking-widest text-gray-900 dark:text-white flex items-center gap-2">
                Signal-Verlauf
                <x-heroicon-o-information-circle class="w-4 h-4 text-gray-400 cursor-help" title="Schlanke Übersicht aller erfassten Lob-, Kritik-, Quality-Gate- und Tool-Signale. Prompt- und Antwort-Ausschnitte öffnen im Detail-Modal." />
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="behaviorAgentId" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs rounded-xl px-3 py-1.5 cursor-pointer">
                    <option value="all">Alle Agenten</option>
                    @foreach($internalAgents as $ia)
                        <option value="{{ $ia->id }}">{{ $ia->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="behaviorPolarity" class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white text-xs rounded-xl px-3 py-1.5 cursor-pointer">
                    <option value="all">Alle Polaritäten</option>
                    <option value="praise">Lob</option>
                    <option value="criticism">Kritik</option>
                    <option value="correction">Korrektur</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                        <th class="py-2.5 pr-3">Zeit</th>
                        <th class="py-2.5 px-3">Kanal</th>
                        <th class="py-2.5 px-3">Agent</th>
                        <th class="py-2.5 px-3">Polarität</th>
                        <th class="py-2.5 px-3">Kategorie</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 pl-3 text-right">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-900 text-xs">
                    @forelse($behaviorSignals as $sig)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-950/70 transition-colors">
                            <td class="py-2.5 pr-3 font-mono text-[11px] text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                {{ $sig->created_at?->format('d.m. H:i') }}
                            </td>
                            <td class="py-2.5 px-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                {{ $sig->channelLabel() }}
                            </td>
                            <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $sig->agent?->name ?? 'Funkira' }}
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold {{ $sig->polarity === 'praise' ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : ($sig->polarity === 'correction' ? 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300' : 'bg-[#C5A059]/15 text-[#9A742B] dark:text-[#C5A059]') }}">
                                    {{ $sig->polarityLabel() }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $sig->categoryLabel() }}
                            </td>
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <span class="text-[10px] font-bold uppercase tracking-wider {{ $sig->status === 'applied' ? 'text-emerald-600 dark:text-emerald-400' : ($sig->status === 'dismissed' ? 'text-gray-400' : 'text-gray-700 dark:text-gray-300') }}">
                                    {{ $sig->statusLabel() }}
                                </span>
                            </td>
                            <td class="py-2.5 pl-3 text-right whitespace-nowrap">
                                <button type="button"
                                        wire:click="openFeedbackDetail('{{ $sig->id }}')"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-gray-800 hover:border-[#C5A059] cursor-pointer">
                                    Prüfen
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-xs font-bold text-gray-500 dark:text-gray-400">
                                Noch keine Verhaltens-Signale im gewählten Filter
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- DETAIL-MODAL (Prompt- & Antwort-Abgleich ohne Tabellen-Überladung) --}}
    @if($selectedFeedback)
        <div class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
             wire:click.self="closeFeedbackDetail">
            <div class="w-full max-w-2xl rounded-2xl bg-white dark:bg-[#0D0D10] border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-[#C5A059]/15 text-[#9A742B] dark:text-[#C5A059]">
                            {{ $selectedFeedback->channelLabel() }}
                        </span>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white">{{ $selectedFeedback->categoryLabel() }}</h3>
                    </div>
                    <button type="button" wire:click="closeFeedbackDetail" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs max-h-[75vh] overflow-y-auto">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-bold uppercase text-gray-400">Agent</div>
                            <div class="mt-0.5 font-bold text-gray-900 dark:text-white">{{ $selectedFeedback->agent?->name ?? 'Funkira' }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-bold uppercase text-gray-400">Quelle</div>
                            <div class="mt-0.5 font-bold text-gray-900 dark:text-white">{{ $selectedFeedback->sourceLabel() }}</div>
                        </div>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-bold uppercase text-gray-400">Intensität</div>
                            <div class="mt-0.5 font-bold text-gray-900 dark:text-white">{{ $selectedFeedback->intensity }} / 5</div>
                        </div>
                        <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div class="text-[10px] font-bold uppercase text-gray-400">Umgebung</div>
                            <div class="mt-0.5 font-mono font-bold text-gray-900 dark:text-white">{{ $selectedFeedback->environment_tier }}</div>
                        </div>
                    </div>

                    @if($selectedFeedback->reason_note)
                        <div class="p-3.5 rounded-xl bg-[#C5A059]/10 border border-[#C5A059]/30 text-gray-900 dark:text-white font-semibold">
                            {{ $selectedFeedback->reason_note }}
                        </div>
                    @endif

                    @if($selectedFeedback->user_excerpt)
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Nutzer-Ausschnitt</div>
                            <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 leading-relaxed">
                                {{ $selectedFeedback->user_excerpt }}
                            </div>
                        </div>
                    @endif

                    @if($selectedFeedback->agent_excerpt)
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Agenten-Antwort</div>
                            <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 leading-relaxed">
                                {{ $selectedFeedback->agent_excerpt }}
                            </div>
                        </div>
                    @endif

                    @if(!empty($selectedFeedback->executed_tools))
                        <div>
                            <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1.5">Ausgeführte Werkzeuge</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($selectedFeedback->executed_tools as $toolName)
                                    <span class="px-2 py-0.5 rounded-md font-mono text-[10px] font-bold bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800">{{ $toolName }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-2 px-6 py-3.5 border-t border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950">
                    @if($isAdminViewer)
                        <div class="flex items-center gap-2">
                            <button type="button" wire:click="updateFeedbackStatus('{{ $selectedFeedback->id }}', 'reviewed')" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 cursor-pointer">
                                Als geprüft markieren
                            </button>
                            <button type="button" wire:click="updateFeedbackStatus('{{ $selectedFeedback->id }}', 'dismissed')" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-800 hover:border-rose-400 cursor-pointer">
                                Verwerfen
                            </button>
                        </div>
                    @endif
                    <button type="button" wire:click="closeFeedbackDetail" class="px-3 py-1.5 rounded-lg text-xs font-bold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer ml-auto">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
