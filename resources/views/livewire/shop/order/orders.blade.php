<div class="min-h-screen bg-gray-50 p-4 md:p-8">

    {{-- VIEW 1: LISTEN-ANSICHT --}}
    @if(!$selectedOrderId)

        {{-- FUNKI LOGIK: Status ermitteln --}}
        @php
            $prio = $this->priorityOrder;
            $hasPrio = $prio !== null;

            // Stimmung & Bild setzen
            if ($hasPrio && ($prio->is_express || ($prio->deadline && $prio->deadline->isPast()))) {
                $mood = 'alarm'; // Roter/Oranger Hintergrund-Schein
                $funkiImg = 'funki_party.png'; // Oder ein ernstes Bild, wenn vorhanden
                $statusText = "HOCHDRUCK";
                $statusColor = "text-red-600 bg-red-50 border-red-100";
            } elseif ($hasPrio) {
                $mood = 'work';
                $funkiImg = 'funki_selfie.png';
                $statusText = "PRODUKTIV";
                $statusColor = "text-blue-600 bg-blue-50 border-blue-100";
            } else {
                $mood = 'chill';
                $funkiImg = 'funki.png';
                $statusText = "STANDBY";
                $statusColor = "text-green-600 bg-green-50 border-green-100";
            }
        @endphp

        {{-- ================================================== --}}
        {{-- NEUER: FUNKI KOMMANDO-BEREICH (Mega Schick)       --}}
        {{-- ================================================== --}}
        <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-gray-200/50 border border-gray-100 relative overflow-hidden mb-10 group">

            {{-- Dynamischer Hintergrund-Schein --}}
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-gradient-to-br {{ $mood === 'alarm' ? 'from-red-500/10' : ($mood === 'chill' ? 'from-green-500/10' : 'from-blue-500/10') }} to-transparent rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none"></div>

            <div class="relative z-10 flex flex-col md:flex-row gap-8 items-center md:items-start">

                {{-- 1. FUNKI AVATAR --}}
                <div class="relative shrink-0">
                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-3xl bg-gray-50 border border-gray-100 shadow-inner flex items-center justify-center relative z-10">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-full h-full object-contain p-2 hover:scale-110 transition-transform duration-500">
                    </div>
                    {{-- Status Badge am Avatar --}}
                    <div class="absolute -bottom-3 -right-3 {{ $statusColor }} px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest border shadow-sm flex items-center gap-1.5 z-20">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 bg-current"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-current"></span>
                        </span>
                        {{ $statusText }}
                    </div>
                </div>

                {{-- 2. TEXT & ACTION BEREICH --}}
                <div class="flex-1 text-center md:text-left">
                    <h2 class="font-serif font-bold text-3xl text-gray-900 mb-2">
                        @if($mood === 'alarm')
                            ⚠️ Achtung, {{ auth()->user()->first_name }}!
                        @elseif($mood === 'chill')
                            Alles erledigt, {{ auth()->user()->first_name }}! ✨
                        @else
                            An die Arbeit, {{ auth()->user()->first_name }}.
                        @endif
                    </h2>

                    @if($hasPrio)
                        <p class="text-gray-500 text-lg mb-6 leading-relaxed">
                            Die wichtigste Aufgabe liegt oben auf: <br class="md:hidden">
                            Bestellung <strong class="text-gray-900">#{{ $prio->order_number }}</strong> von <strong class="text-gray-900">{{ $prio->customer_name }}</strong>.
                        </p>

                        {{-- PRIO ORDER CARD (Interaktiv) --}}
                        <div wire:click="openDetail('{{ $prio->id }}')"
                             class="inline-flex flex-col md:flex-row items-center gap-4 bg-white border {{ $prio->is_express ? 'border-red-200 ring-4 ring-red-50' : 'border-gray-200' }} p-4 rounded-2xl shadow-sm hover:shadow-md hover:scale-[1.01] transition-all cursor-pointer group/card text-left">

                            {{-- Icon --}}
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0 {{ $prio->is_express ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600' }}">
                                {{ $prio->is_express ? '🚀' : '📦' }}
                            </div>

                            {{-- Info --}}
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-gray-900">#{{ $prio->order_number }}</span>
                                    @if($prio->is_express)
                                        <span class="bg-red-600 text-white text-[9px] font-bold px-2 py-0.5 rounded uppercase">Express</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    @if($prio->deadline)
                                        Deadline: <span class="{{ $prio->deadline->isPast() ? 'text-red-600 font-bold' : 'text-gray-700 font-medium' }}">{{ $prio->deadline->format('d.m.Y') }}</span>
                                    @else
                                        Bestellt am {{ $prio->created_at->format('d.m. H:i') }}
                                    @endif
                                </div>
                            </div>

                            {{-- Action Arrow --}}
                            <div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center group-hover/card:bg-gray-900 group-hover/card:text-white transition-colors ml-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </div>
                        </div>

                        {{-- FUNKI TIPP --}}
                        <div class="mt-6 flex items-start gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100 max-w-xl">
                            <span class="text-xl">💡</span>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                <strong>Funki-Tipp:</strong>
                                @if($prio->is_express)
                                    Prüfe sofort den Lagerbestand, damit der Express-Versand heute noch rausgehen kann!
                                @elseif($prio->deadline && $prio->deadline->isPast())
                                    <span class="text-red-600 font-bold">Kritisch!</span> Kontaktiere den Kunden wegen der Verzögerung, bevor du startest.
                                @else
                                    Dies ist der älteste offene Auftrag. Arbeite ihn ab, um Wartezeiten gering zu halten.
                                @endif
                            </p>
                        </div>

                    @else
                        <p class="text-gray-500 mb-4">
                            Im Moment liegen keine offenen Bestellungen vor. Das Dashboard ist sauber.
                        </p>
                        <div class="flex items-center gap-2 text-sm text-green-600 font-bold bg-green-50 px-4 py-2 rounded-lg inline-block border border-green-100">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Alle Systeme operativ
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- 2. KPI DASHBOARD (Dein Code, integriert) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-10 animate-fade-in-up" style="animation-delay: 100ms;">
            {{-- Card 1: Offene Aufgaben --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-blue-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Offene Aufgaben</p>
                        <h3 class="text-3xl font-serif font-bold text-gray-900 group-hover:text-blue-600 transition-colors">{{ $stats['open'] }}</h3>
                    </div>
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 2: Express Warnung --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-red-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Davon Express</p>
                        <h3 class="text-3xl font-serif font-bold text-gray-900 {{ $stats['open_express'] > 0 ? 'text-red-600' : '' }}">{{ $stats['open_express'] }}</h3>
                    </div>
                    <div class="p-3 bg-red-50 text-red-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 3: Umsatz Heute --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-green-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Umsatz Heute</p>
                        <h3 class="text-2xl font-serif font-bold text-gray-900 group-hover:text-green-600 transition-colors">{{ number_format($stats['revenue_today'] / 100, 2, ',', '.') }} €</h3>
                        <p class="text-[10px] text-gray-400 mt-1">Ø Korb: {{ number_format($stats['avg_cart'] / 100, 2, ',', '.') }} €</p>
                    </div>
                    <div class="p-3 bg-green-50 text-green-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>

            {{-- Card 4: Umsatz Monat --}}
            <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                <div class="absolute right-0 top-0 h-full w-1 bg-purple-500 rounded-r-2xl"></div>
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Umsatz {{ \Carbon\Carbon::now()->translatedFormat('F') }}</p>
                        <h3 class="text-2xl font-serif font-bold text-gray-900 group-hover:text-purple-600 transition-colors">{{ number_format($stats['revenue_month'] / 100, 0, ',', '.') }} €</h3>
                        <p class="text-[10px] text-gray-400 mt-1">Total: {{ $stats['total'] }} Orders</p>
                    </div>
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- FILTER & SUCHE LEISTE --}}
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8 bg-white p-2 rounded-xl shadow-sm border border-gray-100">
            {{-- Suche --}}
            <div class="relative w-full md:w-96 group">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suche nach Nr., Name, Mail..."
                       class="w-full pl-10 pr-4 py-2 border-none bg-transparent text-sm focus:ring-0 placeholder-gray-400">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2 group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            {{-- Filter Dropdowns --}}
            <div class="flex items-center gap-2 w-full md:w-auto overflow-x-auto no-scrollbar px-2">
                <select wire:model.live="statusFilter" class="px-3 py-1.5 bg-gray-50 border-0 rounded-lg text-xs font-bold text-gray-600 focus:ring-2 focus:ring-primary cursor-pointer hover:bg-gray-100">
                    <option value="">Status: Alle</option>
                    <option value="pending">Wartend</option>
                    <option value="processing">In Bearbeitung</option>
                    <option value="shipped">Versendet</option>
                    <option value="completed">Abgeschlossen</option>
                    <option value="cancelled">Storniert</option>
                </select>
                <select wire:model.live="paymentFilter" class="px-3 py-1.5 bg-gray-50 border-0 rounded-lg text-xs font-bold text-gray-600 focus:ring-2 focus:ring-primary cursor-pointer hover:bg-gray-100">
                    <option value="">Zahlung: Alle</option>
                    <option value="paid">Bezahlt</option>
                    <option value="unpaid">Offen</option>
                </select>

                {{-- Reset Button --}}
                @if($search || $statusFilter || $paymentFilter || $sortField !== 'default_workflow')
                    <button wire:click="resetFilters"
                            class="flex items-center justify-center p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors border border-transparent hover:border-red-100"
                            title="Alle Filter & Sortierung zurücksetzen">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- TABELLE (Single Table) --}}
        @if($orders->isNotEmpty())
            <div class="mb-12 animate-fade-in-up">
                <div class="flex items-center gap-3 mb-4 px-1">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
                    </span>
                    <h2 class="text-xl font-serif font-bold text-gray-900">Alle Bestellungen</h2>
                    <span class="bg-primary/10 text-primary text-xs font-bold px-2 py-1 rounded-md">{{ $orders->total() }}</span>
                </div>

                {{-- Include der ausgelagerten Tabelle --}}
                @include('livewire.shop.order.orders-partials.table', [
                    'orders' => $orders,
                    'sortField' => $sortField,
                    'sortDirection' => $sortDirection
                ])

            </div>
        @else
            {{-- EMPTY STATE --}}
            <div class="text-center py-24 bg-white rounded-3xl border border-gray-100 shadow-sm border-dashed">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-6">
                    <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Keine Bestellungen gefunden</h3>
                <p class="text-gray-500 mb-8 max-w-sm mx-auto">Zu deinen aktuellen Filtereinstellungen gibt es keine passenden Ergebnisse.</p>
                <button wire:click="resetFilters" class="text-primary font-bold hover:underline uppercase tracking-widest text-sm">
                    Filter zurücksetzen
                </button>
            </div>
        @endif

        {{-- VIEW 2: DETAIL ANSICHT --}}
    @else
        <div class="h-[calc(100vh-2rem)] flex flex-col bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden animate-fade-in-up">
            @include("livewire.shop.order.orders-partials.detail-header")
            <div class="flex flex-col lg:flex-row flex-1 overflow-hidden">
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
