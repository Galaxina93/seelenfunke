<div x-data="{ focusMode: true, dashboardReady: false }"
     x-init="setTimeout(() => { dashboardReady = true; }, 520)"
     class="p-4 md:p-6 bg-transparent min-h-screen relative z-10">

    <!-- LADEBALKEN-ANIMATION BEIM SEITENSTART -->
    <style>
        @keyframes sfDashboardProgress {
            0% { width: 0%; }
            45% { width: 68%; }
            85% { width: 92%; }
            100% { width: 100%; }
        }
        .sf-dashboard-loader-bar {
            width: 0%;
            animation: sfDashboardProgress 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
    </style>
    <div x-show="!dashboardReady"
         x-transition:leave="transition-opacity duration-250 ease-out"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="flex flex-col items-center justify-center min-h-[75vh] w-full select-none">
        <div class="relative w-16 h-16 mb-5 flex items-center justify-center">
            <div class="absolute inset-0 rounded-full border border-[#C5A059]/30 animate-ping"></div>
            <div class="relative w-14 h-14 rounded-full bg-[#141417] border border-[#C5A059]/80 shadow-[0_0_25px_rgba(197,160,89,0.45)] flex items-center justify-center">
                <x-heroicon-s-sparkles class="w-6 h-6 text-[#C5A059] animate-pulse" />
            </div>
        </div>
        <div class="text-[11px] font-black uppercase tracking-[0.28em] text-[#C5A059] mb-3">
            Chrono-Core wird geladen
        </div>
        <div class="w-64 sm:w-80 h-2 bg-gray-200/80 dark:bg-gray-900/90 rounded-full overflow-hidden border border-[#C5A059]/35 p-[1px] shadow-inner">
            <div class="h-full rounded-full bg-gradient-to-r from-[#8a6820] via-[#C5A059] to-[#f5dca0] shadow-[0_0_12px_#C5A059] sf-dashboard-loader-bar"></div>
        </div>
    </div>

    <!-- Basis-Sound aus /admin/tasks kombiniert mit futuristischem Seelenfunke Web-Audio-Synthesizer -->
    <audio id="sfBaseTaskSound" src="{{ asset('shop/management/todo/sounds/todo_done.mp3') }}" preload="auto"></audio>
    <script>
        window.sfAudioCtx = null;
        window.sfLastHoverSound = 0;
        window.sfHoverStep = 0;

        window.sfPlaySound = function(type = 'select') {
            try {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!window.sfAudioCtx && AudioContextClass) {
                    window.sfAudioCtx = new AudioContextClass();
                }
                const ctx = window.sfAudioCtx;
                if (ctx && ctx.state === 'suspended') {
                    ctx.resume();
                }

                // 1. HOVER: Feiner, kristalliner Star-Map Micro-Impuls mit leicht variierender Tonhöhe
                if (type === 'hover') {
                    const nowMs = Date.now();
                    if (nowMs - window.sfLastHoverSound < 75 || !ctx) return;
                    window.sfLastHoverSound = nowMs;
                    window.sfHoverStep = (window.sfHoverStep + 1) % 5;

                    const hoverFreqs = [987.77, 1108.73, 1174.66, 1318.51, 1479.98];
                    const baseF = hoverFreqs[window.sfHoverStep];
                    const now = ctx.currentTime;

                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(baseF, now);
                    osc.frequency.exponentialRampToValueAtTime(baseF * 1.45, now + 0.048);

                    gain.gain.setValueAtTime(0.001, now);
                    gain.gain.linearRampToValueAtTime(0.028, now + 0.01);
                    gain.gain.exponentialRampToValueAtTime(0.0008, now + 0.055);

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(now);
                    osc.stop(now + 0.06);
                    return;
                }

                // 2. Basis-Sound aus /admin/tasks (todo_done.mp3) mit kontextabhängigem Pitch & Volume
                const baseAudio = document.getElementById('sfBaseTaskSound');
                if (baseAudio) {
                    const clone = baseAudio.cloneNode(true);
                    const rateMap = {
                        select: 1.16,
                        chart: 1.04,
                        back: 0.86,
                        action: 1.32,
                        alert: 0.94,
                        switch: 1.25
                    };
                    const volMap = {
                        select: 0.40,
                        chart: 0.42,
                        back: 0.34,
                        action: 0.46,
                        alert: 0.44,
                        switch: 0.32
                    };
                    clone.playbackRate = rateMap[type] || 1.15;
                    clone.volume = volMap[type] || 0.40;
                    clone.play().catch(() => {});
                }

                if (!ctx) return;
                const now = ctx.currentTime;

                // 3. BACK: Sanfter Reverse-Warp / De-Materialisierungs-Glide zurück zum Hauptkern
                if (type === 'back') {
                    const backNotes = [1318.51, 1108.73, 880, 587.33];
                    backNotes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        const start = now + (idx * 0.042);
                        const dur = 0.28;
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, start);
                        osc.frequency.exponentialRampToValueAtTime(freq * 0.75, start + dur);
                        gain.gain.setValueAtTime(0.001, start);
                        gain.gain.linearRampToValueAtTime(0.055, start + 0.015);
                        gain.gain.exponentialRampToValueAtTime(0.0008, start + dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + dur + 0.02);
                    });
                    return;
                }

                // 4. CHART: Hologramm-Entfaltung (Kern fliegt nach oben, Chart entfaltet sich)
                if (type === 'chart') {
                    // Sub-Bass Impuls für räumliche Tiefe
                    const sub = ctx.createOscillator();
                    const subGain = ctx.createGain();
                    sub.type = 'sine';
                    sub.frequency.setValueAtTime(146.83, now);
                    sub.frequency.exponentialRampToValueAtTime(73.42, now + 0.35);
                    subGain.gain.setValueAtTime(0.08, now);
                    subGain.gain.exponentialRampToValueAtTime(0.001, now + 0.38);
                    sub.connect(subGain);
                    subGain.connect(ctx.destination);
                    sub.start(now);
                    sub.stop(now + 0.4);

                    const chartNotes = [440, 554.37, 659.25, 880, 1108.73, 1318.51];
                    chartNotes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        const start = now + (idx * 0.032);
                        const dur = 0.38 + (idx * 0.04);
                        osc.type = idx % 2 === 0 ? 'triangle' : 'sine';
                        osc.frequency.setValueAtTime(freq * 0.97, start);
                        osc.frequency.exponentialRampToValueAtTime(freq, start + 0.035);
                        gain.gain.setValueAtTime(0.001, start);
                        gain.gain.linearRampToValueAtTime(0.05, start + 0.018);
                        gain.gain.exponentialRampToValueAtTime(0.0008, start + dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + dur + 0.02);
                    });
                    return;
                }

                // 5. ACTION: Heller Portal-Bestätigungsklang beim Öffnen eines Direktlinks
                if (type === 'action') {
                    const actNotes = [783.99, 1174.66, 1567.98, 2349.32];
                    actNotes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        const start = now + (idx * 0.028);
                        const dur = 0.25 + (idx * 0.05);
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(freq, start);
                        gain.gain.setValueAtTime(0.001, start);
                        gain.gain.linearRampToValueAtTime(0.06, start + 0.012);
                        gain.gain.exponentialRampToValueAtTime(0.0008, start + dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + dur + 0.02);
                    });
                    return;
                }

                // 6. ALERT: Kritischer Satellit angeklickt (Tiefer Resonanz-Impuls + Gold-Chime)
                if (type === 'alert') {
                    const alertNotes = [293.66, 440, 587.33, 880, 1174.66];
                    alertNotes.forEach((freq, idx) => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        const start = now + (idx * 0.035);
                        const dur = 0.34 + (idx * 0.05);
                        osc.type = idx < 2 ? 'triangle' : 'sine';
                        osc.frequency.setValueAtTime(freq * 0.95, start);
                        osc.frequency.exponentialRampToValueAtTime(freq, start + 0.04);
                        gain.gain.setValueAtTime(0.001, start);
                        gain.gain.linearRampToValueAtTime(0.065, start + 0.016);
                        gain.gain.exponentialRampToValueAtTime(0.0008, start + dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(start);
                        osc.stop(start + dur + 0.02);
                    });
                    return;
                }

                // 7. SELECT / SWITCH: Orbital-Transformation in den Hauptkern
                const notes = type === 'switch'
                    ? [528, 792, 1056, 1584]
                    : [587.33, 880, 1174.66, 1760];

                notes.forEach((freq, idx) => {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const start = now + (idx * 0.038);
                    const dur = 0.36 + (idx * 0.06);

                    osc.type = idx % 2 === 0 ? 'sine' : 'triangle';
                    osc.frequency.setValueAtTime(freq * 0.98, start);
                    osc.frequency.exponentialRampToValueAtTime(freq, start + 0.04);

                    gain.gain.setValueAtTime(0.001, start);
                    gain.gain.linearRampToValueAtTime(idx === 3 ? 0.045 : 0.065, start + 0.018);
                    gain.gain.exponentialRampToValueAtTime(0.0008, start + dur);

                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(start);
                    osc.stop(start + dur + 0.02);
                });
            } catch (e) {
                // Audio blockiert oder nicht unterstützt
            }
        };
    </script>

    <div x-cloak
         style="opacity: 0; pointer-events: none; max-height: 0; overflow: hidden;"
         :style="dashboardReady ? 'opacity: 1; pointer-events: auto; max-height: none; overflow: visible;' : 'opacity: 0; pointer-events: none; max-height: 0; overflow: hidden;'"
         :class="focusMode ? 'space-y-1' : 'space-y-8'"
         class="transition-opacity duration-500 ease-out">

        @include('livewire.shop.master.master-analytics-partials.header')

        @include('livewire.shop.master.master-analytics-partials.master_scores')

        <div x-show="!focusMode" x-cloak style="display: none;" x-transition.opacity.duration.200ms class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- AKTIV ROUTINE -->
        <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 flex flex-col relative overflow-hidden group transition-colors duration-300">
            <div class="absolute top-0 right-0 p-4 opacity-5 dark:opacity-10">
                <x-heroicon-s-arrow-path class="w-24 h-24 text-gray-900 dark:text-white" />
            </div>
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center border border-primary/50">
                        <x-heroicon-s-clock class="w-5 h-5 text-primary" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Live Routine</h3>
                        <p class="text-sm font-bold text-gray-900 dark:text-white tracking-widest">Tagesablauf</p>
                    </div>
                </div>
                <a href="{{ route('admin.routine') }}" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex items-center gap-1">Alle ansehen <x-heroicon-s-chevron-right class="w-3 h-3" /></a>
            </div>

            @if($currentActiveRoutine)
               @php
                   $startT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->format('H:i');
                   $endT = \Carbon\Carbon::parse($currentActiveRoutine->start_time)->addMinutes($currentActiveRoutine->duration_minutes)->format('H:i');
               @endphp
               <div class="flex-1 flex flex-col justify-center">
                   <div class="flex items-center justify-between mb-2">
                       <h4 class="text-xl font-black text-gray-900 dark:text-white truncate pr-2">{{ $currentActiveRoutine->title }}</h4>
                       <span class="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/50 px-3 py-1 rounded-full text-xs font-bold dark:shadow-[0_0_8px_rgba(16,185,129,0.5)]">Aktiv</span>
                   </div>
                   <div class="text-sm font-bold text-primary mb-4">{{ $startT }} - {{ $endT }} Uhr ({{ $currentActiveRoutine->duration_minutes }} Min)</div>
                   <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-h-20 overflow-y-auto custom-scrollbar">{{ $currentActiveRoutine->message ?: 'Keine weiteren Details hinterlegt.' }}</p>
               </div>
            @else
               <div class="flex-1 flex flex-col items-center justify-center text-center py-4">
                   <x-heroicon-o-moon class="w-12 h-12 text-gray-400 dark:text-gray-700 mb-3" />
                   <h4 class="text-lg font-bold text-gray-500">Keine aktive Routine</h4>
                   <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Aktuell läuft kein geplanter Block.</p>
               </div>
            @endif
        </div>

        <!-- ANSTEHENDE TERMINE -->
        <div class="bg-white/90 dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] p-6 dark:shadow-2xl border border-gray-200/80 dark:border-gray-800 flex flex-col relative overflow-hidden group transition-colors duration-300">
            <div class="flex items-center justify-between mb-6 relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center border border-blue-500/50">
                        <x-heroicon-s-calendar class="w-5 h-5 text-blue-400" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em]">Kalender</h3>
                        <p class="text-sm font-bold text-gray-900 dark:text-white tracking-widest">Nächste Termine</p>
                    </div>
                </div>
                <a href="{{ route('admin.calender') }}" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors flex items-center gap-1">Alle ansehen <x-heroicon-s-chevron-right class="w-3 h-3" /></a>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 space-y-3 max-h-[180px]">
                @forelse($upcomingEvents as $event)
                    @php
                        $isToday = $event->start_date->isToday();
                        $isTomorrow = $event->start_date->isTomorrow();
                        $dateLabel = $isToday ? 'Heute' : ($isTomorrow ? 'Morgen' : $event->start_date->format('d.m.'));
                        $timeStr = $event->is_all_day ? 'Ganztägig' : $event->start_date->format('H:i');
                        $colorClass = $event->priority === 'high' ? 'text-red-400 bg-red-500/10 border-red-500/20' : ($event->priority === 'medium' ? 'text-amber-400 bg-amber-500/10 border-amber-500/20' : 'text-blue-400 bg-blue-500/10 border-blue-500/20');
                    @endphp
                    <div class="bg-gray-50 dark:bg-gray-950 rounded-xl p-3 border border-gray-200 dark:border-gray-800 flex items-start gap-4 hover:border-gray-300 dark:hover:border-gray-700 transition-colors">
                        <div class="flex flex-col items-center justify-center shrink-0 w-14 h-14 rounded-lg border {{ $colorClass }}">
                            <span class="text-[10px] font-bold uppercase">{{ $dateLabel }}</span>
                            <span class="{{ $event->is_all_day ? 'text-[8px] sm:text-[9px] tracking-tight uppercase mt-0.5' : 'text-[13px]' }} font-black">{{ $timeStr }}</span>
                        </div>
                        <div class="flex-1 min-w-0 py-0.5">
                            <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 truncate">{{ $event->title }}</h4>
                            <p class="text-xs text-gray-500 truncate mt-1">{{ $event->description ?: 'Keine Beschreibung' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center h-full text-center py-6">
                        <x-heroicon-o-calendar-days class="w-10 h-10 text-gray-400 dark:text-gray-700 mb-2" />
                        <p class="text-sm font-bold text-gray-500">Keine anstehenden Termine</p>
                    </div>
                @endforelse
            </div>
        </div>
        </div>
    </div>

    {{-- ABANDONED CARTS MODAL --}}

    @if($showAbandonedCarts)
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 xl:p-10" x-data @keydown.escape.window="$wire.set('showAbandonedCarts', false)">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-3xl w-full max-w-5xl max-h-full overflow-hidden relative shadow-2xl dark:shadow-[0_0_50px_rgba(245,158,11,0.1)] flex flex-col">
                <div class="p-6 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center bg-gray-50/80 dark:bg-gray-950/80">
                    <div>
                        <h3 class="text-gray-900 dark:text-white font-bold font-serif text-2xl flex items-center gap-3">
                            <x-heroicon-o-shopping-cart class="w-8 h-8 text-amber-500" />
                            Verlassene Körbe Detailansicht
                        </h3>
                        <p class="text-[10px] text-gray-500 font-black uppercase tracking-widest mt-1">Umsatzpotenzial und liegengebliebene Warenkörbe</p>
                    </div>
                    <button wire:click="$set('showAbandonedCarts', false)" class="text-gray-500 bg-gray-100 dark:bg-gray-800/50 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                    @if(isset($stats['abandoned_carts']['details']) && count($stats['abandoned_carts']['details']) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-800">
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Status / Alter</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Warenkorb ID</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest">Benutzer / Gast</th>
                                        <th class="p-3 text-[10px] uppercase font-black text-gray-500 tracking-widest text-right">Potenzial</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($stats['abandoned_carts']['details'] as $cart)
                                        <tr class="border-b border-gray-100 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/20 transition-colors">
                                             <td class="p-3 align-middle">
                                                <div class="flex items-center gap-2">
                                                    @if($cart['status'] === 'green')
                                                        <span class="w-3 h-3 rounded-full bg-emerald-500 dark:shadow-[0_0_8px_rgba(16,185,129,0.8)]" title="Kürzlich verlassen"></span>
                                                    @elseif($cart['status'] === 'yellow')
                                                        <span class="w-3 h-3 rounded-full bg-yellow-500 dark:shadow-[0_0_8px_rgba(234,179,8,0.8)]" title="Länger verlassen"></span>
                                                    @else
                                                        <span class="w-3 h-3 rounded-full bg-rose-500 dark:shadow-[0_0_8px_rgba(244,63,94,0.8)]" title="Sehr alt"></span>
                                                    @endif
                                                    <span class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ $cart['age'] }}</span>
                                                </div>
                                            </td>
                                            <td class="p-3 align-middle text-xs font-mono text-gray-500">{{ substr($cart['id'], 0, 8) }}...</td>
                                            <td class="p-3 align-middle">
                                                @if($cart['customer'])
                                                    <div class="font-bold text-sm text-gray-900 dark:text-white">{{ $cart['customer'] }}</div>
                                                    @if($cart['email'])
                                                        <div class="text-[10px] text-gray-500">{{ $cart['email'] }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-xs font-black text-gray-600 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">Gast</span>
                                                @endif
                                            </td>
                                            <td class="p-3 align-middle text-right font-black text-amber-500 group relative">
                                                {{ number_format($cart['total'], 2, ',', '.') }} €
                                                <div class="text-[9px] text-gray-500">{{ $cart['items_count'] }} Artikel</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="py-12 flex flex-col items-center justify-center text-gray-500">
                            <x-heroicon-o-shopping-bag class="w-12 h-12 mb-4 opacity-50" />
                            <p class="font-bold text-sm">Keine verlassenen Warenkörbe gefunden.</p>
                            <p class="text-xs mt-1">Im gewählten Zeitraum gab es keine Abbrüche.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @include('livewire.shop.master.master-analytics-partials.scripts')

</div>
