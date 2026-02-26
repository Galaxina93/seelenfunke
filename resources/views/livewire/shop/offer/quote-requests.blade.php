<div class="min-h-screen bg-transparent p-4 lg:p-6 font-sans antialiased text-gray-300">

    {{--
        =================================================================
        ABSCHNITT 1: DETAIL ANSICHT
        =================================================================
    --}}
    @if($selectedQuoteId)
        @php
            $detailQuote = \App\Models\Quote\QuoteRequest::with('items')->find($selectedQuoteId);
        @endphp

        @if($detailQuote)
            <div class="max-w-6xl mx-auto animate-fade-in-up">
                {{-- Header & Zurück Button --}}
                <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <button wire:click="closeDetail"
                            class="group flex items-center gap-3 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white transition-colors">
                        <div class="w-10 h-10 rounded-full bg-gray-950 border border-gray-800 flex items-center justify-center group-hover:bg-primary group-hover:border-primary group-hover:text-gray-900 transition-all duration-300 shadow-inner">
                            <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                        </div>
                        <span class="hidden sm:inline">Zurück zur Übersicht</span>
                    </button>

                    <div class="flex gap-3">
                        @if($detailQuote->status == 'open')
                            <span class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30 shadow-[0_0_15px_rgba(245,158,11,0.1)] flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span> Offen
                            </span>
                        @elseif($detailQuote->status == 'converted')
                            <span class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.1)] flex items-center gap-2">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> Angenommen
                            </span>
                        @elseif($detailQuote->status == 'rejected')
                            <span class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/30 shadow-[0_0_15px_rgba(239,68,68,0.1)] flex items-center gap-2">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Abgelehnt
                            </span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- LINKE SPALTE: DETAILS --}}
                    <div class="lg:col-span-2 space-y-6">

                        {{-- Karte: Positionen --}}
                        <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">
                            <div class="bg-gray-950 px-6 sm:px-8 py-5 border-b border-gray-800 flex items-center justify-between shadow-inner">
                                <h2 class="text-sm sm:text-base font-serif font-bold text-white tracking-wide">
                                    Positionen der Anfrage <span class="text-primary ml-1">#{{ $detailQuote->quote_number }}</span>
                                </h2>
                            </div>

                            <div class="overflow-x-auto w-full">
                                <table class="w-full text-left min-w-[500px]">
                                    <thead class="bg-gray-900/50 text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800">
                                    <tr>
                                        <th class="px-6 sm:px-8 py-4">Produkt</th>
                                        <th class="px-4 py-4 text-center">Menge</th>
                                        <th class="px-4 py-4 text-right">Einzel</th>
                                        <th class="px-6 sm:px-8 py-4 text-right">Gesamt</th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-800/50 text-sm">
                                    @foreach($detailQuote->items as $item)
                                        <tr class="hover:bg-gray-800/20 transition-colors group">
                                            <td class="px-6 sm:px-8 py-5">
                                                <div class="font-bold text-white mb-1 truncate max-w-[250px] sm:max-w-sm">{{ $item->product_name }}</div>
                                                @if(!empty($item->configuration))
                                                    <div class="text-[10px] text-gray-400 uppercase tracking-wider font-medium flex flex-wrap gap-2">
                                                        @foreach($item->configuration as $key => $val)
                                                            <span class="bg-gray-950 px-2 py-0.5 rounded border border-gray-800 shadow-inner truncate max-w-[150px]">{{ $key }}: {{ Str::limit($val, 20) }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-5 text-center font-bold text-gray-300">{{ $item->quantity }}</td>
                                            <td class="px-4 py-5 text-right font-medium text-gray-400 whitespace-nowrap">{{ number_format($item->unit_price / 100, 2, ',', '.') }} €</td>
                                            <td class="px-6 sm:px-8 py-5 text-right font-bold text-white whitespace-nowrap">{{ number_format($item->total_price / 100, 2, ',', '.') }} €</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Summenblock --}}
                            <div class="bg-gray-950 px-6 sm:px-8 py-6 border-t border-gray-800 shadow-inner">
                                <div class="flex flex-col gap-2 max-w-xs ml-auto">
                                    <div class="flex justify-between items-center text-xs font-medium text-gray-400 uppercase tracking-widest">
                                        <span>Zwischensumme</span>
                                        <span class="text-white">{{ number_format($detailQuote->net_total / 100, 2, ',', '.') }} €</span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs font-medium text-gray-400 uppercase tracking-widest">
                                        <span>MwSt</span>
                                        <span class="text-white">{{ number_format($detailQuote->tax_total / 100, 2, ',', '.') }} €</span>
                                    </div>
                                    @if($detailQuote->shipping_price > 0)
                                        <div class="flex justify-between items-center text-xs font-medium text-gray-400 uppercase tracking-widest">
                                            <span>Versand</span>
                                            <span class="text-white">{{ number_format($detailQuote->shipping_price / 100, 2, ',', '.') }} €</span>
                                        </div>
                                    @endif
                                    <div class="flex justify-between items-end text-lg sm:text-xl font-serif font-black text-primary mt-4 pt-4 border-t border-gray-800">
                                        <span class="uppercase tracking-tight text-white">Gesamt</span>
                                        <span class="drop-shadow-[0_0_10px_rgba(197,160,89,0.3)]">{{ number_format($detailQuote->gross_total / 100, 2, ',', '.') }} €</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- AKTIONEN / BUTTONS (Nur wenn Offen) --}}
                        @if($detailQuote->status === 'open')
                            <div class="bg-gradient-to-br from-gray-900 to-gray-950 backdrop-blur-xl rounded-[2.5rem] shadow-[0_0_30px_rgba(0,0,0,0.5)] border border-primary/20 p-6 sm:p-8 relative overflow-hidden group">
                                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-[80px] -translate-y-1/2 translate-x-1/3 pointer-events-none group-hover:bg-primary/10 transition-colors duration-700"></div>

                                <div class="relative z-10">
                                    <h3 class="font-serif font-bold text-xl text-white mb-1">Angebot annehmen</h3>
                                    <p class="text-xs text-gray-400 font-medium mb-6 uppercase tracking-widest">Wie soll der Kunde bezahlen?</p>

                                    <div class="flex flex-col sm:flex-row gap-4">
                                        {{-- OPTION 1: Klassische Rechnung --}}
                                        <button wire:click="convertToOrder('{{ $detailQuote->id }}', 'invoice')"
                                                wire:loading.attr="disabled"
                                                class="flex-1 flex items-center justify-center gap-3 px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-300 bg-gray-900 border border-gray-700 rounded-2xl hover:bg-gray-800 hover:text-white transition-all shadow-inner">
                                            <svg class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Als Rechnung
                                        </button>

                                        {{-- OPTION 2: Online-Zahlung --}}
                                        <button wire:click="convertToOrder('{{ $detailQuote->id }}', 'stripe_link')"
                                                wire:loading.attr="disabled"
                                                class="flex-1 flex items-center justify-center gap-3 px-6 py-4 text-xs font-black uppercase tracking-widest text-gray-900 bg-primary border border-primary/50 rounded-2xl hover:bg-primary-dark transition-all shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:scale-[1.02]">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                            </svg>
                                            Zahlungslink senden
                                        </button>
                                    </div>

                                    {{-- Loading Indicator --}}
                                    <div wire:loading wire:target="convertToOrder"
                                         class="mt-4 text-center text-[10px] uppercase tracking-widest text-primary font-black animate-pulse flex items-center justify-center gap-2">
                                        <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                        </svg>
                                        Wird verarbeitet...
                                    </div>
                                </div>
                            </div>
                        @endif

                    </div>

                    {{-- RECHTE SPALTE: KUNDE --}}
                    <div class="space-y-6">
                        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 p-6 sm:p-8">
                            <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] mb-5 border-b border-gray-800 pb-3 flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Kundendaten
                            </h3>

                            <div class="space-y-4 text-sm font-medium text-gray-400">
                                <div>
                                    <span class="block text-[9px] text-gray-600 uppercase tracking-widest mb-1">Name</span>
                                    <span class="font-bold text-white text-base">{{ $detailQuote->first_name }} {{ $detailQuote->last_name }}</span>
                                </div>
                                <div>
                                    <span class="block text-[9px] text-gray-600 uppercase tracking-widest mb-1">Email</span>
                                    <a href="mailto:{{ $detailQuote->email }}" class="text-primary hover:text-white transition-colors">{{ $detailQuote->email }}</a>
                                </div>
                                @if($detailQuote->company)
                                    <div>
                                        <span class="block text-[9px] text-gray-600 uppercase tracking-widest mb-1">Firma</span>
                                        <span class="font-bold text-amber-400">{{ $detailQuote->company }}</span>
                                    </div>
                                @endif
                                <div>
                                    <span class="block text-[9px] text-gray-600 uppercase tracking-widest mb-1">Adresse</span>
                                    <span class="block leading-relaxed">
                                        {{ $detailQuote->street }} {{ $detailQuote->house_number }}<br>
                                        {{ $detailQuote->postal }} {{ $detailQuote->city }}<br>
                                        <span class="uppercase text-[10px] tracking-wider text-gray-500 mt-0.5 block">{{ $detailQuote->country }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if($detailQuote->admin_notes)
                            <div class="bg-amber-900/10 rounded-[2rem] border border-amber-500/20 p-6 shadow-inner">
                                <span class="flex items-center gap-2 text-[10px] text-amber-400 uppercase font-black tracking-widest mb-2 drop-shadow-[0_0_8px_currentColor]">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                    Kunden-Anmerkung
                                </span>
                                <p class="text-sm text-amber-100/80 italic leading-relaxed">"{!! nl2br(e($detailQuote->admin_notes)) !!}"</p>
                            </div>
                        @endif

                        @if($detailQuote->is_express)
                            <div class="bg-red-500/10 rounded-[2rem] border border-red-500/30 p-6 shadow-[inset_0_0_20px_rgba(239,68,68,0.1)] relative overflow-hidden">
                                <div class="absolute -right-4 -top-4 text-red-500/20 rotate-12">
                                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                </div>
                                <span class="flex items-center gap-2 text-[10px] text-red-400 uppercase font-black tracking-widest mb-2 relative z-10">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> Express Service
                                </span>
                                @if($detailQuote->deadline)
                                    <p class="text-sm text-white font-bold tracking-wide relative z-10">
                                        Frist: <span class="text-red-400">{{ $detailQuote->deadline->format('d.m.Y') }}</span>
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="max-w-3xl mx-auto text-center py-28 bg-gray-900/80 backdrop-blur-md rounded-[3rem] border border-gray-800 shadow-2xl">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gray-950 border border-gray-800 mb-6 shadow-inner">
                    <svg class="w-10 h-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">Anfrage nicht gefunden</h3>
                <p class="text-gray-500 mb-8 max-w-sm mx-auto text-sm">Die gesuchte Angebotsanfrage existiert nicht oder wurde gelöscht.</p>
                <button wire:click="closeDetail" class="text-primary font-black hover:text-white transition-colors uppercase tracking-widest text-[10px] border-b border-primary/30 pb-0.5 hover:border-white">
                    Zurück zur Übersicht
                </button>
            </div>
        @endif

        {{--
            =================================================================
            ABSCHNITT 2: LIST VIEW
            =================================================================
        --}}
    @else

        {{-- STATS HEADER --}}
        @php
            $stats = [
                'total' => \App\Models\Quote\QuoteRequest::count(),
                'open' => \App\Models\Quote\QuoteRequest::where('status', 'open')->count(),
                'converted' => \App\Models\Quote\QuoteRequest::where('status', 'converted')->count(),
            ];

            // Helper for Stats
            $kpiCardDark = function($title, $value, $icon, $colorClass, $bgClass, $borderClass) {
                return "
                <div class='bg-gray-900/80 backdrop-blur-md p-5 sm:p-6 rounded-[2rem] border border-gray-800 shadow-2xl hover:border-{$colorClass}/50 transition-all duration-300 relative overflow-hidden group'>
                    <div class='absolute right-0 top-0 w-24 h-24 bg-gradient-to-bl from-{$colorClass}/10 to-transparent rounded-bl-full opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none'></div>
                    <div class='flex justify-between items-center relative z-10'>
                        <div>
                            <p class='text-[10px] font-black text-gray-500 uppercase tracking-widest mb-1'>{$title}</p>
                            <h3 class='text-3xl font-serif font-bold text-white group-hover:text-{$colorClass} transition-colors tracking-tight'>{$value}</h3>
                        </div>
                        <div class='p-3.5 {$bgClass} text-{$colorClass} rounded-2xl shadow-inner border {$borderClass} shrink-0'>
                            {$icon}
                        </div>
                    </div>
                </div>
                ";
            };
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6 mb-8 animate-fade-in-up">
            {!! $kpiCardDark('Anfragen Gesamt', $stats['total'], '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>', 'blue-400', 'bg-blue-500/10', 'border-blue-500/20') !!}
            {!! $kpiCardDark('Offene Anfragen', $stats['open'], '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'amber-400', 'bg-amber-500/10', 'border-amber-500/20') !!}
            {!! $kpiCardDark('Angenommen', $stats['converted'], '<svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>', 'emerald-400', 'bg-emerald-500/10', 'border-emerald-500/20') !!}
        </div>

        {{-- TOOLBAR --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] border border-gray-800 p-3 flex flex-col md:flex-row justify-between items-center gap-4 mb-6 shadow-2xl animate-fade-in-up" style="animation-delay: 100ms;">
            <div class="relative w-full md:w-[400px] group">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suchen nach Nr, Name, Firma..."
                       class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-sm text-white focus:bg-black focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all placeholder-gray-500 shadow-inner outline-none">
                <svg class="w-5 h-5 text-gray-500 absolute left-4 top-3.5 group-focus-within:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            <div class="flex gap-2 w-full md:w-auto overflow-x-auto no-scrollbar px-1 pb-1 md:pb-0">
                <select wire:model.live="filterStatus"
                        class="px-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest text-gray-400 focus:bg-black focus:ring-2 focus:ring-primary focus:border-primary cursor-pointer hover:bg-gray-800 hover:text-white transition-all outline-none shadow-inner w-full md:w-auto min-w-[150px]">
                    <option value="">Alle Status</option>
                    <option value="open">Offen</option>
                    <option value="converted">Angenommen</option>
                    <option value="rejected">Abgelehnt</option>
                </select>
            </div>
        </div>

        {{-- DESKTOP TABELLE --}}
        <div class="hidden md:block bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden w-full animate-fade-in-up" style="animation-delay: 200ms;">
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left min-w-[800px] border-collapse">
                    <thead>
                    <tr class="bg-gray-950/50 text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-gray-800">
                        <th class="px-6 py-5">Anfrage-Nr.</th>
                        <th class="px-6 py-5">Datum</th>
                        <th class="px-6 py-5">Kunde</th>
                        <th class="px-6 py-5 text-right">Summe</th>
                        <th class="px-6 py-5 text-center">Status</th>
                        <th class="px-6 py-5 text-right">Aktionen</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50 text-sm">
                    @forelse($quotes as $quote)
                        <tr class="hover:bg-gray-800/30 transition-colors duration-300 group cursor-pointer"
                            wire:click="selectQuote('{{ $quote->id }}')">

                            <td class="px-6 py-5 align-middle">
                                <span class="font-mono font-bold text-gray-300 group-hover:text-primary transition-colors text-base tracking-wide">{{ $quote->quote_number }}</span>
                            </td>

                            <td class="px-6 py-5 text-gray-400 align-middle">
                                <div class="font-medium text-gray-300">{{ $quote->created_at->format('d.m.Y') }} <span class="text-[10px] text-gray-500 ml-1">{{ $quote->created_at->format('H:i') }}</span></div>
                                @if($quote->is_express)
                                    <div class="mt-1">
                                        <span class="inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-red-400">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> Express
                                        </span>
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-5 align-middle">
                                <div class="font-bold text-white truncate max-w-[200px]">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                                <div class="text-[11px] text-gray-500 truncate max-w-[200px]">{{ $quote->company ?? $quote->email }}</div>
                            </td>

                            <td class="px-6 py-5 text-right font-serif font-bold text-white text-lg align-middle">
                                {{ number_format($quote->gross_total / 100, 2, ',', '.') }} €
                            </td>

                            <td class="px-6 py-5 text-center align-middle">
                                @if($quote->status == 'open')
                                    <span class="inline-flex items-center px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30">Offen</span>
                                @elseif($quote->status == 'converted')
                                    <span class="inline-flex items-center px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Angenommen</span>
                                @elseif($quote->status == 'rejected')
                                    <span class="inline-flex items-center px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/30">Abgelehnt</span>
                                @endif
                            </td>

                            <td class="px-6 py-5 text-right align-middle">
                                <button class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gray-950 border border-gray-800 text-gray-400 group-hover:bg-primary group-hover:border-primary group-hover:text-gray-900 transition-all duration-300 transform group-hover:-translate-y-0.5 shadow-inner">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-16 text-center text-gray-500">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-950 border border-gray-800 mb-4 shadow-inner">
                                    <svg class="w-8 h-8 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                </div>
                                <p class="font-serif italic text-lg">Keine Anfragen gefunden.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MOBILE KACHELN (Karten-Layout) --}}
        <div class="md:hidden space-y-3 animate-fade-in-up" style="animation-delay: 200ms;">
            @forelse($quotes as $quote)
                <div wire:click="selectQuote('{{ $quote->id }}')"
                     class="bg-gray-900/80 backdrop-blur-md p-5 rounded-[2rem] border border-gray-800 shadow-xl active:bg-gray-800/40 transition-colors cursor-pointer relative overflow-hidden group">

                    <div class="flex justify-between items-start mb-4">
                        <div class="flex flex-col gap-1.5">
                            <span class="font-mono font-bold text-gray-300 text-base tracking-wide">{{ $quote->quote_number }}</span>
                            <span class="text-[10px] font-medium text-gray-500 mt-0.5">{{ $quote->created_at->format('d.m.Y H:i') }}</span>
                            @if($quote->is_express)
                                <span class="text-[9px] font-black text-red-400 uppercase tracking-widest flex items-center gap-1.5 mt-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse shadow-[0_0_8px_currentColor]"></span> EXPRESS
                                </span>
                            @endif
                        </div>
                        <div>
                            @if($quote->status == 'open')
                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-amber-500/10 text-amber-400 border border-amber-500/30">Offen</span>
                            @elseif($quote->status == 'converted')
                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Angenommen</span>
                            @elseif($quote->status == 'rejected')
                                <span class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest bg-red-500/10 text-red-400 border border-red-500/30">Abgelehnt</span>
                            @endif
                        </div>
                    </div>

                    <div class="flex justify-between items-end">
                        <div class="min-w-0 pr-4 flex-1">
                            <div class="font-bold text-white text-sm truncate">{{ $quote->first_name }} {{ $quote->last_name }}</div>
                            <div class="text-[10px] text-gray-500 truncate mt-0.5">{{ $quote->company ?? $quote->email }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-serif font-bold text-primary text-xl whitespace-nowrap">{{ number_format($quote->gross_total / 100, 2, ',', '.') }} €</div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-gray-900/80 backdrop-blur-md p-12 text-center text-gray-500 border border-gray-800 rounded-[2rem] shadow-xl">
                    <p class="font-serif italic text-lg">Keine Anfragen gefunden.</p>
                </div>
            @endforelse
        </div>

        {{-- PAGINATION --}}
        @if($quotes->hasPages())
            <div class="mt-8 bg-gray-900/50 backdrop-blur-md rounded-2xl border border-gray-800 p-4">
                {{ $quotes->links() }}
            </div>
        @endif

    @endif

</div>
