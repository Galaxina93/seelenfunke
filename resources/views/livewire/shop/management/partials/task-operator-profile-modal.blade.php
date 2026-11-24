@if(!empty($profileModalOpen) && !empty($profileModalData))
    @php
        $pm = $profileModalData;
        $bg = $pm['background'] ?? ['banner_css' => 'background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);', 'border_css' => 'border-slate-700', 'accent_hex' => '#C5A059', 'sparks_count' => 6, 'label' => 'Abendliche Funken-Werkbank'];
        $stats = $pm['stats'] ?? [];
        $sparksCount = max(4, min(30, (int) ($bg['sparks_count'] ?? 6)));
        $accentHex = $bg['accent_hex'] ?? '#C5A059';
        $rankBadgeColors = match ($pm['rank_class'] ?? 'KLASSE C') {
            'KLASSE S' => 'bg-[#C5A059] text-gray-950 border-[#FDE047]',
            'KLASSE A' => 'bg-black/55 text-[#FDE68A] border-[#C5A059]',
            'KLASSE B' => 'bg-black/55 text-emerald-300 border-emerald-400/60',
            default => 'bg-black/55 text-white border-white/25',
        };
    @endphp
    @once
        <style>
            @keyframes sfOperatorSparkRise {
                0% { transform: translate3d(0, 14px, 0) scale(0.55); opacity: 0; }
                22% { opacity: 0.92; }
                80% { opacity: 0.65; }
                100% { transform: translate3d(var(--sf-drift, 10px), -125px, 0) scale(1.05); opacity: 0; }
            }
            .sf-operator-spark {
                position: absolute;
                bottom: 6px;
                border-radius: 9999px;
                pointer-events: none;
                animation: sfOperatorSparkRise var(--sf-dur, 5.5s) ease-in-out var(--sf-delay, 0s) infinite;
            }
        </style>
    @endonce
    <div wire:click.self="closeOperatorProfileModal"
         class="fixed inset-0 z-50 bg-black/75 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 select-none">
        <div class="w-full max-w-lg rounded-3xl overflow-hidden bg-white dark:bg-[#0d1017] border border-gray-200 dark:border-gray-800 shadow-2xl">

            {{-- KOPFLEISTE: Clean-Design Header "Profil" --}}
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800/80 flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="text-xs font-black uppercase tracking-wider text-gray-900 dark:text-white">Profil</span>
                    @if(!empty($bg['label']))
                        <span class="text-[11px] font-semibold text-gray-400 dark:text-gray-500 truncate">· {{ $bg['label'] }}</span>
                    @endif
                </div>
                <button type="button"
                        wire:click="closeOperatorProfileModal"
                        class="w-7 h-7 rounded-xl bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 flex items-center justify-center text-xs font-bold text-gray-600 dark:text-gray-300 cursor-pointer shrink-0">
                    ✕
                </button>
            </div>

            {{-- EPISCHES SEELENFUNKE-WELTEN-BANNER MIT AUFSTEIGENDEN FUNKEN --}}
            <div class="relative overflow-hidden p-5 sm:p-6 min-h-[196px] flex flex-col justify-between text-white border-b-2 {{ $bg['border_css'] ?? 'border-slate-700' }}"
                 style="{{ $bg['banner_css'] ?? '' }}">

                {{-- Subtile schwebende Seelenfunken (Anzahl skaliert mit der Welten-Stufe 1..10) --}}
                <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
                    @for($s = 0; $s < $sparksCount; $s++)
                        @php
                            $leftPct = (int) (($s * 37 + 11) % 94) + 3;
                            $sizePx = ($s % 4 === 0) ? 4 : (($s % 2 === 0) ? 3 : 2);
                            $durSec = number_format(4.2 + (($s * 7) % 35) / 10, 1, '.', '');
                            $delaySec = number_format((($s * 13) % 42) / 10, 1, '.', '');
                            $driftPx = (($s % 2 === 0) ? 1 : -1) * (6 + (($s * 5) % 16));
                            $sparkHex = ($s % 3 === 0) ? $accentHex : '#FDE68A';
                        @endphp
                        <span class="sf-operator-spark"
                              style="left: {{ $leftPct }}%; width: {{ $sizePx }}px; height: {{ $sizePx }}px; background-color: {{ $sparkHex }}; --sf-dur: {{ $durSec }}s; --sf-delay: -{{ $delaySec }}s; --sf-drift: {{ $driftPx }}px;"></span>
                    @endfor
                </div>

                <div class="relative z-10 flex items-center justify-between gap-3 mb-4">
                    <span class="px-2.5 py-0.5 rounded-lg border backdrop-blur-xs text-[10px] font-black uppercase tracking-wider {{ $rankBadgeColors }}">
                        {{ $pm['rank_class'] ?? 'KLASSE C' }} · Stufe {{ $pm['level'] ?? 1 }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-black/60 backdrop-blur-xs border border-white/20 text-[11px] font-black text-[#E2C275]">
                        Score {{ $pm['efficiency_score'] ?? 100 }} %
                    </span>
                </div>

                <div class="relative z-10 flex items-center gap-3.5">
                    @if(!empty($pm['avatar_url']))
                        <img src="{{ $pm['avatar_url'] }}"
                             alt="{{ $pm['display_name'] }}"
                             class="w-16 h-16 rounded-2xl object-cover border-2 border-[#C5A059] shadow-lg shrink-0 bg-gray-900" />
                    @else
                        <div class="w-16 h-16 rounded-2xl bg-black/65 border-2 border-[#C5A059] shadow-lg flex items-center justify-center text-lg font-black text-[#C5A059] shrink-0">
                            {{ $pm['initials'] ?? 'SF' }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="text-base sm:text-lg font-black text-white drop-shadow-sm truncate">{{ $pm['display_name'] }}</div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span class="px-2.5 py-0.5 rounded-md bg-[#C5A059] text-gray-950 text-[11px] font-black shadow-xs">
                                {{ $pm['title_label'] ?? 'Starter' }}
                            </span>
                            <span class="px-2 py-0.5 rounded-md bg-black/45 text-[11px] font-semibold text-white/90 truncate">
                                {{ $pm['role_label'] ?? 'Team' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- EP-Fortschrittsbalken --}}
                <div class="relative z-10 mt-4">
                    <div class="flex items-center justify-between text-[11px] font-bold text-white/95 drop-shadow-xs mb-1">
                        <span>{{ number_format((int) ($pm['ep_total'] ?? 0), 0, ',', '.') }} EP</span>
                        @if(!empty($pm['next_level_ep']))
                            <span class="text-white/80">{{ number_format((int) $pm['next_level_ep'], 0, ',', '.') }} EP</span>
                        @else
                            <span class="text-[#FDE047] font-black">MAX</span>
                        @endif
                    </div>
                    <div class="w-full h-2 rounded-full bg-black/65 border border-white/10 overflow-hidden">
                        <div class="h-full rounded-full bg-[#C5A059] transition-all duration-300"
                             style="width: {{ max(4, min(100, (int) ($pm['progress_pct'] ?? 0))) }}%;"></div>
                    </div>
                </div>
            </div>

            {{-- 4 KOMPAKTE KENNZAHLEN-PILLS (Icon + Wert, Clean-Design) --}}
            <div class="p-4 sm:p-5 space-y-4 max-h-[58dvh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-check-circle class="w-3.5 h-3.5 text-[#C5A059] shrink-0" />
                            <span>Gelöst</span>
                        </span>
                        <span class="text-sm font-black text-gray-900 dark:text-white">{{ $stats['completed'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-clock class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                            <span>Pünktlich</span>
                        </span>
                        <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ $stats['on_time_pct'] ?? 100 }} %</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-bolt class="w-3.5 h-3.5 text-[#C5A059] shrink-0" />
                            <span>Gerettet</span>
                        </span>
                        <span class="text-sm font-black text-gray-900 dark:text-white">{{ $stats['escalations_saved'] ?? 0 }}</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-gray-400">
                            <x-heroicon-m-chat-bubble-left-right class="w-3.5 h-3.5 text-[#C5A059] shrink-0" />
                            <span>Reaktion</span>
                        </span>
                        <span class="text-xs font-black text-[#C5A059]">{{ $stats['reaction_label'] ?? 'Direkt' }}</span>
                    </div>
                </div>

                {{-- EIGENES PROFIL: 1-KLICK AUSRUESTUNG VON TITEL & HINTERGRUND --}}
                @if(!empty($pm['is_own_profile']))
                    <div class="space-y-2 pt-1 border-t border-gray-100 dark:border-gray-800">
                        <div class="text-[11px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Titel</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach(($pm['titles'] ?? []) as $tItem)
                                @php
                                    $isEquipped = ($pm['equipped_title_key'] ?? '') === $tItem['key'];
                                @endphp
                                @if($tItem['unlocked'])
                                    <button type="button"
                                            wire:click="equipOperatorTitle('{{ $tItem['key'] }}')"
                                            class="px-2.5 py-1 rounded-xl border text-[11px] font-bold transition cursor-pointer {{ $isEquipped ? 'bg-[#C5A059] text-gray-950 border-[#C5A059]' : 'bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-800 hover:border-[#C5A059]' }}">
                                        {{ $isEquipped ? '✓ ' : '' }}{{ $tItem['label'] }}
                                    </button>
                                @else
                                    <span title="Ab Stufe {{ $tItem['min_level'] }}"
                                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-100/60 dark:bg-gray-900/40 text-[11px] font-semibold text-gray-400 dark:text-gray-600 opacity-65">
                                        <x-heroicon-m-lock-closed class="w-3 h-3 shrink-0" />
                                        <span>{{ $tItem['label'] }}</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div x-data="{ showBackgrounds: false }"
                         class="space-y-2.5 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <button type="button"
                                @click="showBackgrounds = !showBackgrounds"
                                class="w-full flex items-center justify-between gap-2 py-1 text-left cursor-pointer group">
                            <span class="text-[11px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 group-hover:text-gray-900 dark:group-hover:text-white transition-colors">
                                Hintergrund
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 group-hover:border-[#C5A059] text-[11px] font-bold text-gray-700 dark:text-gray-200 transition-colors">
                                <span class="w-2 h-2 rounded-full bg-[#C5A059] shrink-0"></span>
                                <span class="truncate max-w-[180px]">{{ $bg['label'] ?? 'Abendliche Funken-Werkbank' }}</span>
                                <x-heroicon-m-chevron-down class="w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0"
                                                           ::class="showBackgrounds ? 'rotate-180 text-[#C5A059]' : ''" />
                            </span>
                        </button>

                        <div x-show="showBackgrounds"
                             x-cloak
                             class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-0.5">
                            @foreach(($pm['backgrounds'] ?? []) as $bItem)
                                @php
                                    $bgSelected = ($pm['equipped_background_key'] ?? '') === $bItem['key'];
                                    $rankShort = str_replace('KLASSE ', 'KL. ', (string) ($bItem['rank_class'] ?? 'KLASSE C'));
                                    $thumbStyle = $bItem['thumb_css'] ?? ($bItem['banner_css'] ?? '');
                                @endphp
                                @if($bItem['unlocked'])
                                    <button type="button"
                                            wire:click="equipOperatorBackground('{{ $bItem['key'] }}')"
                                            style="{{ $thumbStyle }}"
                                            class="relative h-16 p-2.5 rounded-2xl overflow-hidden flex flex-col justify-between text-left text-white transition-all cursor-pointer group {{ $bgSelected ? 'ring-2 ring-[#C5A059] border border-[#C5A059]' : 'border border-gray-300 dark:border-gray-700 hover:border-[#C5A059]' }}">
                                        <div class="flex items-center justify-between gap-1.5">
                                            <span class="px-1.5 py-0.5 rounded-md bg-black/60 backdrop-blur-xs text-[9px] font-black tracking-wider text-[#E2C275]">
                                                {{ $rankShort }} · St. {{ $bItem['min_level'] }}
                                            </span>
                                            @if($bgSelected)
                                                <span class="px-1.5 py-0.5 rounded-md bg-[#C5A059] text-gray-950 text-[9px] font-black">✓ Aktiv</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] font-black text-white drop-shadow-sm truncate block">{{ $bItem['label'] }}</span>
                                    </button>
                                @else
                                    <div title="Freigeschaltet ab Stufe {{ $bItem['min_level'] }} ({{ $bItem['rank_class'] ?? 'KLASSE C' }})"
                                         class="relative h-16 rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-gray-900">
                                        <div style="{{ $thumbStyle }}"
                                             class="absolute inset-0 brightness-50 saturate-75 scale-105"></div>
                                        <div class="relative z-10 h-full p-2.5 flex flex-col justify-between bg-black/35">
                                            <div class="flex items-center justify-between gap-1.5">
                                                <span class="px-1.5 py-0.5 rounded-md bg-black/65 text-[9px] font-black tracking-wider text-white/75">
                                                    {{ $rankShort }}
                                                </span>
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md bg-black/75 border border-white/15 text-[9px] font-black text-[#E2C275]">
                                                    <x-heroicon-m-lock-closed class="w-2.5 h-2.5 shrink-0" />
                                                    <span>St. {{ $bItem['min_level'] }}</span>
                                                </span>
                                            </div>
                                            <span class="text-[11px] font-bold text-white/80 truncate block">{{ $bItem['label'] }}</span>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
