<div class="min-h-screen bg-transparent p-4 md:p-8 font-sans antialiased text-gray-300">

    {{-- VIEW 1: LISTEN-ANSICHT --}}
    @if(!$selectedOrderId)

        {{-- FUNKI LOGIK: Status ermitteln --}}
        @php
            $prio = $this->priorityOrder;
            $hasPrio = $prio !== null;

            // Stimmung & Bild setzen
            if ($hasPrio && ($prio->is_express || ($prio->deadline && $prio->deadline->isPast()))) {
                $mood = 'alarm';
                $funkiImg = 'funki_selfie.png';
                $statusText = "HOCHDRUCK";
                $statusColor = "text-red-400 bg-red-500/10 border-red-500/30";
                $glowColor = "from-red-500/20";
                $iconBg = "bg-red-500/10 text-red-500 border-red-500/20";
            } elseif ($hasPrio) {
                $mood = 'work';
                $funkiImg = 'funki_selfie.png';
                $statusText = "PRODUKTIV";
                $statusColor = "text-blue-400 bg-blue-500/10 border-blue-500/30";
                $glowColor = "from-blue-500/20";
                $iconBg = "bg-blue-500/10 text-blue-500 border-blue-500/20";
            } else {
                $mood = 'chill';
                $funkiImg = 'funki.png';
                $statusText = "STANDBY";
                $statusColor = "text-emerald-400 bg-emerald-500/10 border-emerald-500/30";
                $glowColor = "from-emerald-500/20";
                $iconBg = "bg-emerald-500/10 text-emerald-500 border-emerald-500/20";
            }
        @endphp

        {{-- ================================================== --}}
        {{-- NEUER: FUNKI KOMMANDO-BEREICH (Dark High End UX)   --}}
        {{-- ================================================== --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] sm:rounded-[3rem] p-6 sm:p-10 shadow-2xl border border-gray-800 relative overflow-hidden mb-8 sm:mb-12 group transition-all duration-500">

            {{-- Dynamischer Hintergrund-Schein (Subtiler Glow) --}}
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-gradient-to-bl {{ $glowColor }} to-transparent rounded-full blur-[80px] -translate-y-1/3 translate-x-1/3 pointer-events-none transition-colors duration-1000 opacity-60"></div>

            <div class="relative z-10 flex flex-col md:flex-row gap-8 sm:gap-12 items-center md:items-start">

                {{-- 1. FUNKI AVATAR --}}
                <div class="relative shrink-0">
                    <div class="w-28 h-28 md:w-36 md:h-36 rounded-full bg-gray-950 border-2 border-gray-800 shadow-[inset_0_-2px_20px_rgba(0,0,0,0.5),_0_10px_30px_rgba(0,0,0,0.5)] flex items-center justify-center relative z-10 p-1">
                        <img src="{{ asset('images/projekt/funki/' . $funkiImg) }}" class="w-full h-full object-contain drop-shadow-[0_10px_15px_rgba(0,0,0,0.5)] hover:scale-105 hover:-rotate-3 transition-transform duration-500 ease-out" alt="Funki">
                    </div>
                    {{-- Status Badge am Avatar --}}
                    <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 {{ $statusColor }} px-4 py-1.5 rounded-full text-[9px] sm:text-[10px] font-black uppercase tracking-widest border shadow-lg flex items-center gap-2 z-20 backdrop-blur-md">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-current"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-current"></span>
                        </span>
                        {{ $statusText }}
                    </div>
                </div>

                {{-- 2. TEXT & ACTION BEREICH --}}
                <div class="flex-1 text-center md:text-left pt-2 w-full">
                    <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white mb-3 tracking-tight">
                        @if($mood === 'alarm')
                            <span class="text-red-500">⚠️ Achtung</span>, {{ auth()->user()->first_name }}!
                        @elseif($mood === 'chill')
                            Alles erledigt, {{ auth()->user()->first_name }}! <span class="text-primary drop-shadow-[0_0_10px_currentColor]">✨</span>
                        @else
                            An die Arbeit, {{ auth()->user()->first_name }}.
                        @endif
                    </h2>

                    @if($hasPrio)
                        <p class="text-gray-400 text-sm sm:text-base mb-6 font-medium leading-relaxed max-w-2xl">
                            Die wichtigste Aufgabe liegt oben auf: <br class="hidden sm:block">
                            Bestellung <strong class="text-white font-bold tracking-wide">#{{ $prio->order_number }}</strong> von <strong class="text-white font-bold">{{ $prio->customer_name }}</strong>.
                        </p>

                        <div class="flex flex-col xl:flex-row gap-5 items-stretch">
                            {{-- PRIO ORDER CARD (Interaktiv) --}}
                            <div wire:click="openDetail('{{ $prio->id }}')"
                                 class="flex-1 inline-flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-5 bg-gray-950 border {{ $prio->is_express ? 'border-red-500/30 shadow-[0_0_20px_rgba(239,68,68,0.15)]' : 'border-gray-800 shadow-inner' }} p-5 rounded-[2rem] hover:border-primary/50 transition-all duration-300 cursor-pointer group/card text-left relative overflow-hidden">

                                {{-- Hover Gradient Overlay --}}
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/5 to-transparent translate-x-[-100%] group-hover/card:translate-x-[100%] transition-transform duration-1000 ease-in-out pointer-events-none"></div>

                                {{-- Icon --}}
                                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shrink-0 border {{ $iconBg }}">
                                    {{ $prio->is_express ? '🚀' : '📦' }}
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 flex flex-col justify-center w-full min-w-0">
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-3 mb-1.5">
                                        <span class="font-bold text-white text-lg tracking-wide truncate">{{ $prio->order_number }}</span>
                                        @if($prio->is_express)
                                            <span class="bg-red-500/20 border border-red-500/30 text-red-400 text-[9px] font-black px-2 py-1 rounded-md uppercase tracking-wider shadow-sm">Express</span>
                                        @endif
                                    </div>
                                    <div class="text-[10px] sm:text-xs text-gray-500 font-bold uppercase tracking-widest text-center sm:text-left">
                                        @if($prio->deadline)
                                            Deadline: <span class="{{ $prio->deadline->isPast() ? 'text-red-400 font-black animate-pulse' : 'text-gray-300' }}">{{ $prio->deadline->format('d.m.y') }}</span>
                                        @else
                                            Bestellt am {{ $prio->created_at->format('d.m. H:i') }} Uhr
                                        @endif
                                    </div>
                                </div>

                                {{-- Action Arrow --}}
                                <div class="w-10 h-10 rounded-full bg-gray-900 border border-gray-800 flex items-center justify-center text-gray-500 group-hover/card:bg-primary group-hover/card:text-gray-900 group-hover/card:border-primary transition-all duration-300 sm:ml-auto self-center shrink-0 shadow-inner">
                                    <svg class="w-5 h-5 transition-transform group-hover/card:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </div>
                            </div>

                            {{-- FUNKI TIPP --}}
                            <div class="flex-1 flex items-start gap-4 bg-gray-900/50 p-5 rounded-[2rem] border border-gray-800 text-left">
                                <div class="w-10 h-10 rounded-full bg-gray-950 flex items-center justify-center shadow-inner border border-gray-800 shrink-0 text-lg">
                                    💡
                                </div>
                                <div>
                                    <h4 class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1">Funki-Tipp</h4>
                                    <p class="text-xs text-gray-400 leading-relaxed font-medium">
                                        @if($prio->is_express)
                                            Prüfe sofort den Lagerbestand, damit der Express-Versand heute noch rausgehen kann!
                                        @elseif($prio->deadline && $prio->deadline->isPast())
                                            <span class="text-red-400 font-bold">Kritisch!</span> Kontaktiere den Kunden wegen der Verzögerung, bevor du startest.
                                        @else
                                            Dies ist der älteste offene Auftrag. Arbeite ihn ab, um Wartezeiten gering zu halten.
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>

                    @else
                        <p class="text-gray-400 mb-6 text-sm sm:text-base font-medium">
                            Im Moment liegen keine offenen Bestellungen vor. Das Dashboard ist blitzblank.
                        </p>
                        <div class="inline-flex items-center gap-3 text-xs text-emerald-400 font-bold uppercase tracking-widest bg-emerald-500/10 px-5 py-2.5 rounded-full border border-emerald-500/20 shadow-[0_0_15px_rgba(16,185,129,0.15)]">
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            Alle Systeme operativ
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ================================================== --}}
        {{-- KPI DASHBOARD (Dark High End UX)                   --}}
        {{-- ================================================== --}}
        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-8 sm:mb-12 animate-fade-in-up" style="animation-delay: 100ms;">

            {{-- Helper Function für KPI Cards im Dark Mode --}}
            @php
                $kpiCardDark = function($title, $value, $subtext, $icon, $colorClass, $bgClass, $borderClass) {
                    return "
                    <div class='bg-gray-900/80 backdrop-blur-md p-4 sm:p-6 rounded-[1.5rem] sm:rounded-[2rem] border border-gray-800 shadow-2xl hover:border-{$colorClass}/50 hover:shadow-[0_0_30px_rgba(var(--color-{$colorClass}-500),_0.15)] transition-all duration-300 relative overflow-hidden group'>
                        <div class='absolute right-0 top-0 w-24 h-24 sm:w-32 sm:h-32 bg-gradient-to-bl from-{$colorClass}/20 to-transparent rounded-bl-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none'></div>
                        <div class='flex flex-col-reverse sm:flex-row justify-between items-start relative z-10 gap-3 sm:gap-0'>
                            <div class='w-full'>
                                <p class='text-[9px] sm:text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1 sm:mb-2 truncate'>{$title}</p>
                                <h3 class='text-2xl sm:text-3xl font-serif font-bold text-white group-hover:text-{$colorClass} transition-colors tracking-tight'>{$value}</h3>
                                <p class='text-[9px] sm:text-[10px] text-gray-400 mt-1 sm:mt-2 font-bold uppercase tracking-wider truncate'>{$subtext}</p>
                            </div>
                            <div class='p-2 sm:p-3.5 {$bgClass} text-{$colorClass} rounded-xl sm:rounded-2xl shadow-inner border {$borderClass} shrink-0'>
                                {$icon}
                            </div>
                        </div>
                    </div>
                    ";
                };
            @endphp

            {{-- Card 1: Offene Aufgaben --}}
            {!! $kpiCardDark(
                'Offene Aufgaben',
                $stats['open'],
                'Warten auf Bearbeitung',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
                'blue-400',
                'bg-blue-500/10',
                'border-blue-500/20'
            ) !!}

            {{-- Card 2: Express Warnung --}}
            {!! $kpiCardDark(
                'Davon Express',
                '<span class="' . ($stats['open_express'] > 0 ? 'text-red-400 drop-shadow-[0_0_8px_currentColor]' : '') . '">' . $stats['open_express'] . '</span>',
                'Höchste Priorität',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
                'red-400',
                'bg-red-500/10',
                'border-red-500/20'
            ) !!}

            {{-- Card 3: Umsatz Heute --}}
            {!! $kpiCardDark(
                'Umsatz Heute',
                number_format($stats['revenue_today'] / 100, 2, ',', '.') . ' €',
                'Ø Korb: ' . number_format($stats['avg_cart'] / 100, 2, ',', '.') . ' €',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'emerald-400',
                'bg-emerald-500/10',
                'border-emerald-500/20'
            ) !!}

            {{-- Card 4: Umsatz Monat --}}
            {!! $kpiCardDark(
                'Umsatz ' . \Carbon\Carbon::now()->translatedFormat('F'),
                number_format($stats['revenue_month'] / 100, 2, ',', '.') . ' €',
                'Total: ' . $stats['total'] . ' Orders',
                '<svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
                'primary',
                'bg-primary/10',
                'border-primary/20'
            ) !!}
        </div>

        {{-- ================================================== --}}
        {{-- FILTER & SUCHE LEISTE                              --}}
        {{-- ================================================== --}}
        <div class="flex flex-col lg:flex-row justify-between items-center gap-4 mb-8 bg-gray-900/80 backdrop-blur-md p-2.5 sm:p-3 rounded-[2rem] shadow-2xl border border-gray-800 w-full">
            {{-- Suche --}}
            <div class="relative w-full lg:w-[400px] group">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suchen nach Nr., Name, Mail..."
                       class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-sm text-white focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder-gray-500 shadow-inner outline-none">
                <svg class="w-5 h-5 text-gray-500 absolute left-4 top-3.5 group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            {{-- Filter Dropdowns --}}
            <div class="flex items-center gap-2 sm:gap-3 w-full lg:w-auto overflow-x-auto no-scrollbar pb-1 lg:pb-0 px-1">
                <select wire:model.live="statusFilter" class="px-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-400 focus:bg-black focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer hover:bg-gray-800 hover:text-white transition-all outline-none shadow-inner">
                    <option value="">Status: Alle</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>
                <select wire:model.live="paymentFilter" class="px-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-400 focus:bg-black focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer hover:bg-gray-800 hover:text-white transition-all outline-none shadow-inner">
                    <option value="">Zahlung: Alle</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>

                {{-- Reset Button --}}
                @if($search || $statusFilter || $paymentFilter || $sortField !== 'default_workflow')
                    <button wire:click="resetFilters"
                            class="flex items-center justify-center p-3 text-gray-500 hover:text-red-400 bg-gray-950 hover:bg-red-500/10 rounded-[1.5rem] transition-colors border border-gray-800 hover:border-red-500/30 shrink-0 shadow-inner"
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
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary shadow-[0_0_8px_currentColor]"></span>
                    </span>
                    <h2 class="text-xl font-serif font-bold text-white tracking-tight">Bestellübersicht</h2>
                    <span class="bg-primary/10 text-primary border border-primary/20 text-xs font-black px-2.5 py-0.5 rounded-md shadow-[0_0_10px_rgba(197,160,89,0.2)]">{{ $orders->total() }}</span>
                </div>

                {{-- Include der ausgelagerten Tabelle (Ist bereits im Dark Mode optimiert) --}}
                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                    @include('livewire.shop.order.orders-partials.table', [
                        'orders' => $orders,
                        'sortField' => $sortField,
                        'sortDirection' => $sortDirection
                    ])
                </div>

            </div>
        @else
            {{-- EMPTY STATE --}}
            <div class="text-center py-28 bg-gray-900/80 backdrop-blur-md rounded-[3rem] border border-gray-800 shadow-2xl w-full">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gray-950 border border-gray-800 mb-6 shadow-inner">
                    <svg class="w-10 h-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">Keine Treffer gefunden</h3>
                <p class="text-gray-500 mb-8 max-w-sm mx-auto text-sm">Zu deinen aktuellen Filtereinstellungen gibt es keine passenden Bestellungen.</p>
                <button wire:click="resetFilters" class="text-primary font-black hover:text-white transition-colors uppercase tracking-widest text-[10px] border-b border-primary/30 pb-0.5 hover:border-white">
                    Filter zurücksetzen
                </button>
            </div>
        @endif

        {{-- VIEW 2: DETAIL ANSICHT --}}
    @else
        <div class="h-[calc(100vh-2rem)] flex flex-col bg-gray-900/90 backdrop-blur-xl rounded-[2.5rem] shadow-[0_0_50px_rgba(0,0,0,0.5)] border border-gray-800 overflow-hidden animate-fade-in-up">
            @include("livewire.shop.order.orders-partials.detail-header")
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden bg-gray-950/50">
                @include('livewire.shop.shared.order-offer-detail-content', [
                    'model' => $selectedOrder,
                    'context' => 'order',
                    'selectedItemId' => $selectedOrderItemId,
                    'previewItem' => $this->previewItem
                ])
            </div>
        </div>
    @endif

    {{-- VERSAND-MODAL --}}
    @include("livewire.shop.order.orders-partials.shipping-modal")

</div>
