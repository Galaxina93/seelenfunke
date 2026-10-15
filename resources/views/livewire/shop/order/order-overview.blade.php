<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="min-h-screen bg-transparent p-4 md:p-8 font-sans antialiased text-gray-900 dark:text-gray-300">

    {{-- VIEW 1: LISTEN-ANSICHT --}}
    @if(!$selectedOrderId)

        {{-- FUNKI LOGIK: Status ermitteln --}}
        @php
            $prio = $this->priorityOrder;
            $hasPrio = $prio !== null;

            // Stimmung & Bild setzen
            if ($hasPrio && $prio->is_express) {
                $mood = 'alarm';
                $funkiImg = 'orders/bestelli_alarm.webp';
                $statusText = "HOCHDRUCK";
                $statusColor = "text-red-700 dark:text-red-300 bg-red-100 dark:bg-red-900/60 border-red-300 dark:border-red-500/50";
                $glowColor = "from-red-500/20";
                $iconBg = "bg-red-500/10 text-red-500 border-red-500/20";
            } elseif ($hasPrio) {
                $mood = 'work';
                $funkiImg = 'orders/bestelli_work.webp';
                $statusText = "PRODUKTIV";
                $statusColor = "text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/60 border-blue-300 dark:border-blue-500/50";
                $glowColor = "from-blue-500/20";
                $iconBg = "bg-blue-500/10 text-blue-500 border-blue-500/20";
            } else {
                $mood = 'chill';
                $funkiImg = 'orders/bestelli_chill.webp';
                $statusText = "STANDBY";
                $statusColor = "text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/60 border-emerald-300 dark:border-emerald-500/50";
                $glowColor = "from-emerald-500/20";
                $iconBg = "bg-emerald-500/10 text-emerald-500 border-emerald-500/20";
            }
        @endphp

        {{-- ================================================== --}}
        {{-- NEUER: FUNKI KOMMANDO-BEREICH (Adaptive High End UX) --}}
        {{-- ================================================== --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] sm:rounded-[3rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 relative overflow-hidden mb-8 sm:mb-12 group transition-all duration-500 flex flex-col md:flex-row">

            {{-- Dynamischer Hintergrund-Schein (Nur Dark Mode) --}}
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-gradient-to-bl {{ $glowColor }} to-transparent rounded-full blur-[80px] -translate-y-1/3 translate-x-1/3 pointer-events-none transition-colors duration-1000 opacity-60 z-0 hidden dark:block"></div>

            {{-- 1. FUNKI BILD (Voll in die Kachel integriert) --}}
            <div class="w-full md:w-[35%] lg:w-[30%] relative shrink-0 min-h-[280px] md:min-h-full bg-gray-100 dark:bg-gray-950 z-10 overflow-hidden border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-800">
                <img src="{{ asset('shop/ai/images/' . $funkiImg) }}" class="absolute inset-0 w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700 ease-out" alt="Funki">

                {{-- Farbverlauf für besseren Text-Kontrast und Übergang --}}
                <div class="absolute inset-0 bg-gradient-to-t from-white/90 dark:from-gray-950 via-transparent to-transparent opacity-80"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-transparent to-white/80 dark:to-gray-900/80 hidden md:block"></div>

            </div>

            {{-- 2. TEXT & ACTION BEREICH --}}
            <div class="flex-1 p-6 sm:p-10 relative z-10 flex flex-col justify-center text-center md:text-left">
                    <h2 class="font-serif font-bold text-3xl sm:text-4xl text-gray-900 dark:text-white mb-3 tracking-tight">
                        @if($mood === 'alarm')
                            <span class="text-red-500">⚠️ Achtung</span>, {{ auth()->user()->first_name }}!
                        @elseif($mood === 'chill')
                            Alles erledigt, {{ auth()->user()->first_name }}! <span class="text-[var(--theme-color)] drop-shadow-none dark:drop-shadow-[0_0_10px_currentColor]">✨</span>
                        @else
                            An die Arbeit, {{ auth()->user()->first_name }}.
                        @endif
                    </h2>

                    @if($hasPrio)
                        <p class="text-gray-600 dark:text-gray-400 text-sm sm:text-base mb-6 font-medium leading-relaxed max-w-2xl">
                            Die wichtigste Aufgabe liegt oben auf: <br class="hidden sm:block">
                            Bestellung <strong class="text-gray-900 dark:text-white font-bold tracking-wide">#{{ $prio->order_number }}</strong> von <strong class="text-gray-900 dark:text-white font-bold">{{ $prio->customer_name }}</strong>.
                        </p>

                        <div class="flex flex-col xl:flex-row gap-5 items-stretch">
                            {{-- PRIO ORDER CARD (Interaktiv) --}}
                            <div wire:click="openDetail('{{ $prio->id }}')"
                                 class="flex-1 inline-flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-5 bg-gray-50 dark:bg-gray-950 border {{ $prio->is_express ? 'border-red-500/30 shadow-sm dark:shadow-[0_0_20px_rgba(239,68,68,0.15)]' : 'border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner' }} p-5 rounded-[2rem] hover:border-[var(--theme-color-50)] transition-all duration-300 cursor-pointer group/card text-left relative overflow-hidden">

                                {{-- Hover Gradient Overlay --}}
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent translate-x-[-100%] group-hover/card:translate-x-[100%] transition-transform duration-1000 ease-in-out pointer-events-none hidden dark:block"></div>

                                {{-- Icon --}}
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shrink-0 border {{ $iconBg }}">
                                    {{ $prio->is_express ? '🚀' : '📦' }}
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 flex flex-col justify-center w-full min-w-0">
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mb-1.5">
                                        <span class="font-bold text-gray-900 dark:text-white text-lg tracking-wide truncate">{{ $prio->order_number }}</span>
                                        @if($prio->is_express)
                                            <span class="bg-red-500/10 dark:bg-red-500/20 border border-red-500/30 text-red-600 dark:text-red-400 text-[9px] font-black px-2 py-1 rounded-md uppercase tracking-wider shadow-sm">Express</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest text-center sm:text-left">
                                        Bestellt am {{ $prio->created_at->format('d.m. H:i') }} Uhr
                                    </div>
                                </div>

                                {{-- Action Arrow --}}
                                <div class="w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500 group-hover/card:bg-[var(--theme-color)] group-hover/card:text-gray-900 group-hover/card:border-[var(--theme-color)] transition-all duration-300 sm:ml-auto self-center shrink-0 shadow-sm dark:shadow-inner">
                                    <svg class="w-5 h-5 transition-transform group-hover/card:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                            </div>

                            {{-- FUNKI TIPP --}}
                            <div class="flex-1 flex items-start gap-4 bg-gray-50 dark:bg-gray-900/50 p-5 rounded-[2rem] border border-gray-200 dark:border-gray-800 text-left">
                                <div class="w-10 h-10 rounded-full bg-white dark:bg-gray-950 flex items-center justify-center shadow-sm dark:shadow-inner border border-gray-200 dark:border-gray-800 shrink-0 text-lg">
                                    💡
                                </div>
                                <div>
                                    <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Funki-Tipp</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed font-medium">
                                        {!! $this->priority_order_tip !!}
                                    </p>
                                </div>
                            </div>
                        </div>

                    @else
                        <p class="text-gray-600 dark:text-gray-400 mb-6 text-sm sm:text-base font-medium">
                            Im Moment liegen keine offenen Bestellungen vor. Das Dashboard ist blitzblank.
                        </p>
                        <div class="inline-flex items-center gap-3 text-xs text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-widest bg-emerald-50 dark:bg-emerald-500/10 px-5 py-2.5 rounded-full border border-emerald-200 dark:border-emerald-500/20 shadow-sm dark:shadow-[0_0_15px_rgba(16,185,129,0.15)]">
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            Alle Systeme operativ
                        </div>
                    @endif
                </div>
        </div>

        {{-- ================================================== --}}
        {{-- KPI DASHBOARD (Adaptive High End UX)               --}}
        {{-- ================================================== --}}
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-8 sm:mb-12 animate-fade-in-up" style="animation-delay: 100ms;">

            {{-- Helper Function für KPI Cards --}}
            @php
                $kpiCardAdaptive = function($title, $value, $subtext, $icon, $colorClass, $bgClass, $borderClass) {
                    return "
                    <div class='bg-white dark:bg-gray-900/80 backdrop-blur-md p-4 sm:p-6 rounded-[1.5rem] sm:rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl hover:border-{$colorClass}/50 hover:shadow-md dark:hover:shadow-[0_0_30px_rgba(var(--color-{$colorClass}-500),_0.15)] transition-all duration-300 relative overflow-hidden group'>
                        <div class='absolute right-0 top-0 w-24 h-24 sm:w-32 sm:h-32 bg-gradient-to-bl from-{$colorClass}/20 to-transparent rounded-bl-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none hidden dark:block'></div>
                        <div class='flex flex-col-reverse sm:flex-row justify-between items-start relative z-10 gap-3 sm:gap-0'>
                            <div class='w-full'>
                                <p class='text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 sm:mb-2 truncate'>{$title}</p>
                                <h3 class='text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white group-hover:text-{$colorClass} transition-colors tracking-tight'>{$value}</h3>
                                <p class='text-[9px] sm:text-[10px] text-gray-500 dark:text-gray-400 mt-1 sm:mt-2 font-bold uppercase tracking-wider truncate'>{$subtext}</p>
                            </div>
                            <div class='p-2 sm:p-3.5 {$bgClass} text-{$colorClass} rounded-xl sm:rounded-2xl shadow-sm dark:shadow-inner border {$borderClass} shrink-0'>
                                {$icon}
                            </div>
                        </div>
                    </div>
                    ";
                };
            @endphp

            {{-- Card 1: Offene Aufgaben --}}
            {!! $kpiCardAdaptive(
                'Offene Aufgaben',
                $stats['open'],
                'Warten auf Bearbeitung',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
                'blue-500 dark:text-blue-400',
                'bg-blue-500/10',
                'border-blue-500/20'
            ) !!}

            {{-- Card 2: Express Warnung --}}
            {!! $kpiCardAdaptive(
                'Davon Express',
                '<span class="' . ($stats['open_express'] > 0 ? 'text-red-500 dark:text-red-400 drop-shadow-none dark:drop-shadow-[0_0_8px_currentColor]' : '') . '">' . $stats['open_express'] . '</span>',
                'Höchste Priorität',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                'red-500 dark:text-red-400',
                'bg-red-500/10',
                'border-red-500/20'
            ) !!}

            {{-- Card 3: Umsatz Heute --}}
            {!! $kpiCardAdaptive(
                'Umsatz Heute',
                number_format($stats['revenue_today'] / 100, 2, ',', '.') . ' €',
                'Ø Korb: ' . number_format($stats['avg_cart'] / 100, 2, ',', '.') . ' €',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'emerald-600 dark:text-emerald-400',
                'bg-emerald-500/10',
                'border-emerald-500/20'
            ) !!}

            {{-- Card 4: Umsatz Monat --}}
            {!! $kpiCardAdaptive(
                'Umsatz ' . \Carbon\Carbon::now()->translatedFormat('F'),
                number_format($stats['revenue_month'] / 100, 2, ',', '.') . ' €',
                'Total: ' . $stats['total'] . ' Orders',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                'primary',
                'bg-[var(--theme-color-10)]',
                'border-[var(--theme-color-20)]'
            ) !!}
        </div>

        {{-- ================================================== --}}
        {{-- FILTER & SUCHE LEISTE                              --}}
        {{-- ================================================== --}}
        <div class="flex flex-col lg:flex-row justify-between items-center gap-4 mb-8 bg-white dark:bg-gray-900/80 backdrop-blur-md p-2.5 sm:p-3 rounded-[2rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 w-full">
            {{-- Suche --}}
            <div class="relative w-full lg:w-[400px] group">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suchen nach Nr., Name, Mail..."
                       class="w-full pl-12 pr-4 py-3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-[1.5rem] text-sm text-gray-900 dark:text-white focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all placeholder-gray-400 dark:placeholder-gray-500 shadow-sm dark:shadow-inner outline-none">
                <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 absolute left-4 top-3.5 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            {{-- Filter Dropdowns --}}
            <div class="flex items-center gap-2 sm:gap-3 w-full lg:w-auto overflow-x-auto no-scrollbar pb-1 lg:pb-0 px-1">
                <select wire:model.live="statusFilter" class="px-4 py-3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-[1.5rem] text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-400 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all outline-none shadow-sm dark:shadow-inner">
                    <option value="">Status: Alle</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>
                <select wire:model.live="paymentFilter" class="px-4 py-3 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-[1.5rem] text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-700 dark:text-gray-400 focus:bg-white dark:focus:bg-black focus:ring-2 focus:ring-[var(--theme-color)] focus:border-[var(--theme-color)] cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white transition-all outline-none shadow-sm dark:shadow-inner">
                    <option value="">Zahlung: Alle</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>

                {{-- Sammel-Druck Button --}}
                <button wire:click="downloadMergedLabels"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-2 px-4 py-3 bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:text-gray-900 rounded-[1.5rem] text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all shadow-sm dark:shadow-inner shrink-0 group"
                        title="Alle gefilterten DHL Labels als eine PDF herunterladen">
                    <svg class="w-4 h-4 group-hover:-translate-y-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Sammel-Druck
                </button>

                {{-- Reset Button --}}
                @if($search || $statusFilter || $paymentFilter || $sortField !== 'default_workflow')
                    <button wire:click="resetFilters"
                            class="flex items-center justify-center p-3 text-gray-500 hover:text-red-500 bg-gray-50 dark:bg-gray-950 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-[1.5rem] transition-colors border border-gray-200 dark:border-gray-800 hover:border-red-200 dark:hover:border-red-500/30 shrink-0 shadow-sm dark:shadow-inner"
                            title="Filter zurücksetzen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- ================================================== --}}
        {{-- TABELLE / EMPTY STATE                              --}}
        {{-- ================================================== --}}
        @if($orders->isNotEmpty())
            <div class="mb-12 animate-fade-in-up w-full">
                <div class="flex items-center gap-3 mb-6 px-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--theme-color)] opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-[var(--theme-color)] shadow-sm dark:shadow-[0_0_8px_currentColor]"></span>
                    </span>
                    <h2 class="text-xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">Bestellübersicht</h2>
                    <span class="bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-20)] text-xs font-black px-2.5 py-0.5 rounded-md shadow-sm dark:shadow-[0_0_10px_var(--theme-color-20)]">{{ $orders->total() }}</span>
                </div>

                {{-- Include der ausgelagerten Tabelle --}}
                <div class="bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 overflow-hidden w-full">
                    @include('livewire.shop.order.order-overview-partials.table', [
                        'orders' => $orders,
                        'sortField' => $sortField,
                        'sortDirection' => $sortDirection
                    ])
                </div>

            </div>
        @else
            {{-- EMPTY STATE --}}
            <div class="text-center py-28 bg-white dark:bg-gray-900/80 backdrop-blur-md rounded-[3rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl w-full">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 mb-6 shadow-sm dark:shadow-inner">
                    <svg class="w-10 h-10 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-2xl font-serif font-bold text-gray-900 dark:text-white mb-2 tracking-tight">Keine Treffer gefunden</h3>
                <p class="text-gray-500 mb-8 max-w-sm mx-auto text-sm">Zu deinen aktuellen Filtereinstellungen gibt es keine passenden Bestellungen.</p>
                <button wire:click="resetFilters" class="text-[var(--theme-color)] font-black hover:brightness-90 transition-colors uppercase tracking-widest text-[10px] border-b border-[var(--theme-color-30)] pb-0.5 hover:border-[var(--theme-color)]">
                    Filter zurücksetzen
                </button>
            </div>
        @endif

        {{-- VIEW 2: DETAIL ANSICHT --}}
    @else
        <div class="min-h-[calc(100vh-2rem)] lg:h-[calc(100vh-2rem)] flex flex-col bg-white dark:bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] shadow-xl dark:shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-200 dark:border-gray-800 lg:overflow-hidden animate-fade-in-up">
            @include("livewire.shop.order.order-overview-partials.detail-header")
            <div class="flex flex-col lg:flex-row flex-1 lg:overflow-hidden bg-gray-50/50 dark:bg-gray-950/50">
                @include('livewire.shop.order.order-offer-detail-content', [
                    'model' => $selectedOrder,
                    'context' => 'order',
                    'selectedItemId' => $selectedOrderItemId,
                    'previewItem' => $this->previewItem
                ])
            </div>
        </div>
    @endif

    {{-- VERSAND-MODAL --}}
    @include("livewire.shop.order.order-overview-partials.shipping-modal")

    {{-- DHL MEHRPAKET MODAL --}}
    @if($dhlModalOrderId)
        <div class="fixed inset-0 z-[100] flex items-start sm:items-center justify-center bg-black/50 dark:bg-black/60 backdrop-blur-sm p-4 pt-[8vh] sm:pt-4 overflow-y-auto animate-fade-in">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl max-w-xl sm:max-w-2xl w-full p-6 sm:p-7 animate-slide-up relative overflow-hidden my-auto">
                <div class="absolute top-0 right-0 w-36 h-36 bg-yellow-500/10 rounded-bl-full pointer-events-none hidden dark:block"></div>

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 mb-5 pb-4 border-b border-gray-200 dark:border-gray-800/80">
                    <div class="flex items-center gap-3.5">
                        <div class="w-11 h-11 rounded-xl bg-yellow-400/10 border border-yellow-400/20 flex items-center justify-center shrink-0">
                            <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-serif text-gray-900 dark:text-white font-bold">DHL Label Erstellung</h3>
                                @if(!empty($dhlWeightDetails['dhl_class']))
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border shadow-sm
                                        @if(($dhlWeightDetails['dhl_badge_color'] ?? '') === 'emerald') bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20
                                        @elseif(($dhlWeightDetails['dhl_badge_color'] ?? '') === 'yellow') bg-yellow-50 dark:bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-200 dark:border-yellow-500/20
                                        @elseif(($dhlWeightDetails['dhl_badge_color'] ?? '') === 'amber') bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20
                                        @elseif(($dhlWeightDetails['dhl_badge_color'] ?? '') === 'orange') bg-orange-50 dark:bg-orange-500/10 text-orange-700 dark:text-orange-400 border-orange-200 dark:border-orange-500/20
                                        @else bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border-red-200 dark:border-red-500/20 @endif">
                                        {{ $dhlWeightDetails['dhl_class'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 font-medium mt-0.5">Packtisch-Assistent für automatische Gewichts- & Tarifermittlung</p>
                        </div>
                    </div>
                    <button wire:click="closeDhlModal" class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-white transition-colors p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Artikelliste mit Einzel- und Positionsgewichten --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Enthaltene Artikel (Packliste)</span>
                        <span class="text-[10px] text-gray-500">{{ count($dhlWeightDetails['items'] ?? []) }} physische Position(en)</span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800/80 rounded-xl overflow-hidden shadow-sm dark:shadow-inner max-h-48 overflow-y-auto custom-scrollbar">
                        @if(!empty($dhlWeightDetails['items']))
                            <div class="divide-y divide-gray-200 dark:divide-gray-800/60">
                                @foreach($dhlWeightDetails['items'] as $item)
                                    <div class="px-3.5 py-2.5 flex items-center justify-between text-xs gap-3 hover:bg-gray-100 dark:hover:bg-gray-900/40 transition-colors">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-gray-900 dark:text-white truncate">{{ $item['name'] }}</div>
                                            @if($item['is_fallback'])
                                                <div class="text-[10px] text-amber-400/90 flex items-center gap-1 mt-0.5">
                                                    <span>⚠️ Kein Artikelgewicht gepflegt (100 g Schätzwert)</span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-4 text-right shrink-0">
                                            <div class="text-[11px] text-gray-400">
                                                <span class="text-gray-700 dark:text-gray-800 dark:text-gray-300 font-semibold">{{ $item['quantity'] }}×</span>
                                                <span>{{ number_format($item['single_weight_grams'], 0, ',', '.') }} g</span>
                                            </div>
                                            <div class="text-xs font-mono font-bold text-gray-900 dark:text-gray-200 min-w-[65px]">
                                                {{ number_format($item['total_weight_grams'], 0, ',', '.') }} g
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-4 text-center text-xs text-gray-500">
                                Keine physischen Artikel in der Bestellung gefunden.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Verpackungsart Schnellauswahl --}}
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Verpackungsart</label>
                        @if(!empty($dhlWeightDetails['can_fit_in_envelope']))
                            <span class="text-[10px] text-emerald-400 font-medium flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Für Luftpolstertasche geeignet (Schmuck/Leichtware)
                            </span>
                        @endif
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button type="button" 
                                wire:click="setPackagingType('box')" 
                                class="flex items-center justify-between p-3 rounded-xl border text-left transition-all {{ $dhlPackagingType === 'box' ? 'bg-yellow-400/10 border-yellow-400 text-yellow-900 dark:text-white shadow-sm ring-1 ring-yellow-400/30' : 'bg-gray-50 dark:bg-gray-950/70 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-700' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">📦</span>
                                <div>
                                    <div class="text-xs font-bold {{ $dhlPackagingType === 'box' ? 'text-yellow-700 dark:text-yellow-300' : 'text-gray-700 dark:text-gray-300' }}">Kartonage / Paket</div>
                                    <div class="text-[10px] text-gray-500">Standard (+350 g Tara)</div>
                                </div>
                            </div>
                            @if($dhlPackagingType === 'box')
                                <div class="w-4 h-4 rounded-full bg-yellow-400 text-gray-950 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                            @endif
                        </button>

                        <button type="button" 
                                wire:click="setPackagingType('envelope')" 
                                class="flex items-center justify-between p-3 rounded-xl border text-left transition-all {{ $dhlPackagingType === 'envelope' ? 'bg-yellow-400/10 border-yellow-400 text-yellow-900 dark:text-white shadow-sm ring-1 ring-yellow-400/30' : 'bg-gray-50 dark:bg-gray-950/70 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-700' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg">✉️</span>
                                <div>
                                    <div class="text-xs font-bold {{ $dhlPackagingType === 'envelope' ? 'text-yellow-700 dark:text-yellow-300' : 'text-gray-700 dark:text-gray-300' }}">Luftpolstertasche</div>
                                    <div class="text-[10px] text-gray-500">Umschlag (+30 g Tara)</div>
                                </div>
                            </div>
                            @if($dhlPackagingType === 'envelope')
                                <div class="w-4 h-4 rounded-full bg-yellow-400 text-gray-950 flex items-center justify-center shrink-0">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                </div>
                            @endif
                        </button>
                    </div>
                </div>

                {{-- Kalkulations-Aufschlüsselung (Ware + Tara + Puffer) --}}
                @if(!empty($dhlWeightDetails))
                    <div class="bg-gray-50 dark:bg-gray-950/40 border border-gray-200 dark:border-gray-800/70 rounded-xl p-3.5 mb-5 space-y-2 text-xs shadow-sm dark:shadow-inner">
                        <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                            <span>Reines Warengewicht:</span>
                            <span class="font-mono text-gray-800 dark:text-gray-300 font-semibold">{{ number_format($dhlWeightDetails['product_weight_grams'] ?? 0, 0, ',', '.') }} g ({{ number_format(($dhlWeightDetails['product_weight_grams'] ?? 0) / 1000, 2, ',', '.') }} kg)</span>
                        </div>
                        <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                            <span>Verpackung ({{ $dhlWeightDetails['packaging_name'] ?? 'Tara' }}):</span>
                            <span class="font-mono text-gray-800 dark:text-gray-300 font-semibold">+ {{ number_format($dhlWeightDetails['packaging_weight_grams'] ?? 0, 0, ',', '.') }} g</span>
                        </div>
                        @if(($dhlWeightDetails['buffer_grams'] ?? 0) > 0)
                            <div class="flex justify-between items-center text-gray-500 dark:text-gray-400">
                                <span>Sicherheits-Puffer ({{ ($dhlWeightDetails['packaging_type'] ?? '') === 'envelope' ? 'Label & Schutz' : 'Klebeband, Polster, Papiere' }}):</span>
                                <span class="font-mono text-gray-800 dark:text-gray-300 font-semibold">+ {{ number_format($dhlWeightDetails['buffer_grams'] ?? 0, 0, ',', '.') }} g</span>
                            </div>
                        @endif
                        <div class="pt-2 border-t border-gray-200 dark:border-gray-800 flex justify-between items-center font-bold">
                            <span class="text-gray-900 dark:text-white">Berechnetes Gesamtgewicht (Empfehlung):</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-yellow-600 dark:text-yellow-400 text-sm">
                                    {{ number_format($dhlRecommendedWeight ?? ($dhlWeightDetails['recommended_weight_per_package'] ?? 0), 2, ',', '.') }} kg
                                    @if(($dhlPackageCount ?: 1) > 1)
                                        <span class="text-[10px] text-gray-400 font-normal">/ Paket</span>
                                    @endif
                                </span>
                                <span class="font-mono text-gray-700 dark:text-gray-300 text-xs font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 px-2 py-0.5 rounded-lg shadow-sm dark:shadow-inner">
                                    {{ number_format((int) round(($dhlWeightDetails['total_grams'] ?? 0) / ($dhlPackageCount ?: 1)), 0, ',', '.') }} g
                                </span>
                            </div>
                        </div>
                        @if(($dhlPackageCount ?: 1) > 1)
                            <div class="text-[10px] text-gray-500 font-mono text-right mt-0.5">
                                Gesamtsendung aller {{ $dhlPackageCount }} Pakete: {{ number_format($dhlWeightDetails['total_grams'] ?? 0, 0, ',', '.') }} g ({{ number_format(($dhlWeightDetails['total_grams'] ?? 0) / 1000, 2, ',', '.') }} kg)
                            </div>
                        @endif

                        {{-- Schwellenwert-Warnung --}}
                        @if(!empty($dhlWeightDetails['is_near_limit']))
                            <div class="mt-2.5 pt-2 border-t border-amber-500/20 flex items-center gap-2 text-[11px] text-amber-300/90 bg-amber-500/10 px-2.5 py-1.5 rounded-lg">
                                <svg class="w-4 h-4 shrink-0 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span><strong>Achtung:</strong> Liegt knapp unter der DHL-Tarifgrenze. Bitte vor Versand zur Sicherheit mit der Paketwaage prüfen.</span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Formular-Felder & Empfehlungs-Button --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-2">Anzahl der Pakete</label>
                        <input type="number" wire:model.live="dhlPackageCount" min="1" max="30" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-[var(--theme-color-50)] focus:border-[var(--theme-color)] outline-none transition-all shadow-sm dark:shadow-inner">
                        @error('dhlPackageCount') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Gewicht pro Paket (in kg)</label>
                        </div>
                        <input type="number" step="0.01" wire:model.live="dhlWeightPerPackage" min="0.01" max="31.5" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-800 rounded-xl px-4 py-2.5 text-gray-900 dark:text-white text-sm font-mono focus:ring-2 focus:ring-[var(--theme-color-50)] focus:border-[var(--theme-color)] outline-none transition-all shadow-sm dark:shadow-inner">
                        @error('dhlWeightPerPackage') <span class="text-red-400 text-[10px] mt-1 block">{{ $message }}</span> @enderror

                        {{-- Empfehlung übernehmen Button --}}
                        @if($dhlRecommendedWeight !== null)
                            <div class="mt-2 flex items-center justify-end">
                                @if(abs((float)$dhlWeightPerPackage - (float)$dhlRecommendedWeight) > 0.001)
                                    <button type="button" wire:click="applyDhlRecommendation" class="text-[11px] font-bold text-yellow-400 hover:text-yellow-300 bg-yellow-500/10 hover:bg-yellow-500/20 border border-yellow-500/30 px-2.5 py-1 rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        Empfehlung übernehmen ({{ number_format($dhlRecommendedWeight, 2, ',', '.') }} kg)
                                    </button>
                                @else
                                    <span class="text-[10px] font-medium text-emerald-400/90 flex items-center gap-1">
                                        <svg class="w-3 h-3 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        Entspricht errechneter Empfehlung
                                    </span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Footer Aktionen --}}
                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-800/80">
                    <button wire:click="closeDhlModal" class="px-4 py-2.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white transition-colors shadow-sm dark:shadow-inner">
                        Abbrechen
                    </button>
                    <button wire:click="generateDhlLabels" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-yellow-400 text-yellow-950 hover:bg-yellow-300 transition-all shadow-lg flex items-center gap-2" wire:loading.attr="disabled">
                        <div wire:loading wire:target="generateDhlLabels" class="animate-spin w-3.5 h-3.5 border-2 border-yellow-900/30 border-t-yellow-900 rounded-full"></div>
                        <span wire:loading.remove wire:target="generateDhlLabels">
                            @if(($dhlPackageCount ?: 1) > 1)
                                {{ $dhlPackageCount }} DHL Labels generieren
                            @else
                                DHL Label generieren
                            @endif
                        </span>
                        <span wire:loading wire:target="generateDhlLabels">Wird erstellt...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- DHL ERROR MODAL --}}
    @if($dhlError)
        <div class="fixed inset-0 z-[100] flex items-start sm:items-center justify-center bg-black/50 dark:bg-black/60 backdrop-blur-sm p-4 pt-[15vh] sm:pt-4 animate-fade-in">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl max-w-lg w-full p-6 sm:p-8 animate-slide-up relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-red-500/10 rounded-bl-full pointer-events-none hidden dark:block"></div>
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-serif text-gray-900 dark:text-white font-bold mb-1">Fehler beim Label-Druck!</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed font-medium">Das DHL System hat deine Anfrage abgelehnt. Meistens liegt das an einer Fehlerhaften (z.B. falsche Hausnummer) Kundenadresse im Checkout.</p>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-black/50 border border-gray-200 dark:border-gray-800 rounded-xl p-4 mb-8 font-mono text-xs text-red-600 dark:text-red-400 break-words whitespace-pre-wrap max-h-48 overflow-y-auto custom-scrollbar shadow-sm dark:shadow-inner">
                    {{ $dhlError }}
                </div>

                <div class="flex justify-end gap-3">
                    <button wire:click="$set('dhlError', null)" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors shadow-sm dark:shadow-inner">Schließen</button>
                    <button wire:click="generateDhlLabels" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/40 hover:text-white transition-colors shadow-inner flex items-center gap-2" wire:loading.attr="disabled">
                         <div wire:loading wire:target="generateDhlLabels" class="animate-spin w-3 h-3 border-2 border-red-400/30 border-t-red-400 rounded-full"></div>
                        Erneut versuchen
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
