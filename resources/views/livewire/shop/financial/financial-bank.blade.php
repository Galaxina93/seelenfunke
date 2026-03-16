<div>
    <div class="space-y-6 w-full">

        {{-- Header Bereich --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden w-full p-6 md:p-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6">
            <div>
                <h2 class="text-xl font-serif font-bold text-white tracking-wide mb-1 flex items-center gap-3">
                    <svg class="w-6 h-6 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Bankverbindungen
                </h2>
                <p class="text-sm text-gray-400 font-medium">Live-Synchronisation deiner Geschäftskonten via finAPI.</p>
            </div>

            <div class="flex items-center gap-4">
                {{-- Manueller Sync Button falls der Redirect verloren geht --}}
                <button wire:click="syncAllAccounts"
                        wire:loading.attr="disabled"
                        class="shrink-0 flex items-center gap-2 bg-gray-900 border border-gray-700 hover:border-gray-500 text-gray-300 font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition-all transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading.remove wire:target="syncAllAccounts" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <svg wire:loading wire:target="syncAllAccounts" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span wire:loading.remove wire:target="syncAllAccounts">Sync</span>
                    <span wire:loading wire:target="syncAllAccounts">Lade...</span>
                </button>

                {{-- Der Button triggert jetzt die Livewire Methode, die das finAPI WebForm erstellt und weiterleitet --}}
                <button wire:click="connectNewBank"
                        wire:loading.attr="disabled"
                        class="shrink-0 flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-black font-bold uppercase tracking-widest text-xs px-6 py-3 rounded-xl transition-all shadow-[0_0_15px_rgba(249,115,22,0.3)] hover:shadow-[0_0_25px_rgba(249,115,22,0.5)] transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading.remove wire:target="connectNewBank" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"/></svg>
                    <svg wire:loading wire:target="connectNewBank" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    <span wire:loading.remove wire:target="connectNewBank">Neue Bank verbinden</span>
                    <span wire:loading wire:target="connectNewBank">Leite weiter...</span>
                </button>
            </div>
        </div>

        {{-- System-Meldungen --}}
        @if (session()->has('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-bold flex items-center gap-2 animate-fade-in-up">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('success') }}
            </div>
        @endif
        @if (session()->has('error'))
            <div class="p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm font-bold flex items-center gap-2 animate-fade-in-up">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                {{ session('error') }}
            </div>
        @endif
        @if (session()->has('info'))
            <div class="p-4 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm font-bold flex items-center gap-2 animate-fade-in-up">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ session('info') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- LINKE SPALTE: Verbundene Konten --}}
            <div class="lg:col-span-1 space-y-4">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 pl-2">Aktive Bankkonten</h3>

                @forelse($bankAccounts as $account)
                    <div wire:click="selectBank('{{ $account['id'] }}')" class="cursor-pointer bg-gray-900/60 backdrop-blur-md border {{ $selectedAccountId == $account['id'] ? 'border-orange-500 shadow-[0_0_20px_rgba(249,115,22,0.2)]' : 'border-gray-800 hover:border-orange-500/50' }} rounded-3xl p-5 shadow-inner group transition-all relative overflow-hidden">

                        {{-- Deko-Glow im Hintergrund --}}
                        <div class="absolute -top-10 -right-10 w-32 h-32 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <div class="text-white font-bold text-lg">{{ $account['bank_name'] }}</div>
                                <div class="text-gray-500 text-xs font-mono mt-1">{{ $account['account_name'] }}</div>
                                @if(!empty($account['iban']))
                                    <div class="text-gray-600 text-[10px] font-mono mt-0.5">{{ $account['iban'] }}</div>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            </div>
                        </div>

                        <div class="mb-5 relative z-10">
                            <div class="text-xs font-black uppercase tracking-widest text-gray-500 mb-1">Aktueller Saldo</div>
                            <div class="text-3xl font-mono font-bold text-white tracking-tight">
                                {{ number_format($account['balance'], 2, ',', '.') }} <span class="text-orange-400 text-xl">{{ $account['currency'] }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-gray-800 pt-4 relative z-10">
                            <div class="text-[10px] text-gray-500">
                                Letzter Sync: <br>
                                <span class="text-gray-400 font-bold">
                                {{ $account['last_synced_at'] ? \Carbon\Carbon::parse($account['last_synced_at'])->format('d.m.Y H:i') : 'Noch nie' }}
                            </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="toggleBankBusiness('{{ $account['id'] }}')" wire:loading.attr="disabled" class="p-2 rounded-lg bg-gray-950 border border-gray-800 {{ $account['is_business'] ? 'text-primary hover:border-primary/50' : 'text-blue-400 hover:text-blue-500 hover:border-blue-500/50' }} transition-colors" title="{{ $account['is_business'] ? 'Gewerbliches Konto - Klick für Privat' : 'Privates Konto - Klick für Gewerblich' }}">
                                    @if($account['is_business'])
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    @else
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    @endif
                                </button>
                                <button wire:click="toggleBankActive('{{ $account['id'] }}')" wire:loading.attr="disabled" class="p-2 rounded-lg bg-gray-950 border border-gray-800 {{ $account['is_active_for_analysis'] ? 'text-emerald-400 hover:border-emerald-500/50' : 'text-gray-600 hover:text-emerald-500 hover:border-emerald-500/50' }} transition-colors" title="{{ $account['is_active_for_analysis'] ? 'Analyse Aktiv - Klick zum Deaktivieren' : 'Analyse Inaktiv - Klick zum Aktivieren' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </button>
                                <button wire:click="syncAccount('{{ $account['id'] }}')" wire:loading.attr="disabled" class="p-2 rounded-lg bg-gray-950 border border-gray-800 text-gray-400 hover:text-orange-400 hover:border-orange-500/50 transition-colors" title="Jetzt synchronisieren">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                </button>
                                <button wire:click="disconnectAccount('{{ $account['id'] }}')" wire:confirm="Dieses Konto wirklich trennen?" class="p-2 rounded-lg bg-gray-950 border border-gray-800 text-gray-500 hover:text-red-400 hover:border-red-500/50 transition-colors" title="Konto trennen">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="bg-gray-900/40 border border-dashed border-gray-700 rounded-3xl p-8 text-center flex flex-col items-center justify-center">
                        <svg class="w-12 h-12 text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                        <span class="text-sm font-bold text-gray-400">Noch keine Bank verbunden</span>
                        <span class="text-xs text-gray-600 mt-1">Verbinde dein erstes Konto, um Salden zu laden.</span>
                    </div>
                @endforelse
            </div>

            {{-- RECHTE SPALTE: Letzte Transaktionen (Global) --}}
            <div class="lg:col-span-2 space-y-4">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 pl-2">Neueste Umsätze</h3>

                <div class="bg-gray-900/60 backdrop-blur-md border border-gray-800 rounded-3xl overflow-hidden shadow-inner">

                    {{-- Filter Toolbar --}}
                    <div class="p-4 border-b border-gray-800/50 flex flex-wrap gap-4 items-center justify-between bg-gray-950/30">
                        <div class="flex-1 min-w-[200px] relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input wire:model.live.debounce.300ms="searchTx" type="text" placeholder="Suche in Zweck, Name oder IBAN..." class="w-full pl-10 pr-4 py-2 bg-gray-900 border border-gray-800 rounded-xl text-sm focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none transition-all placeholder-gray-600">
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <select wire:model.live="filterType" class="bg-gray-900 border border-gray-800 text-sm rounded-xl py-2 pl-3 pr-8 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none cursor-pointer">
                                <option value="">Alle Buchungen</option>
                                <option value="income">Nur Einnahmen</option>
                                <option value="expense">Nur Ausgaben</option>
                            </select>

                            <select wire:model.live="filterCategoryId" class="bg-gray-900 border border-gray-800 text-sm rounded-xl py-2 pl-3 pr-8 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none cursor-pointer">
                                <option value="">Alle Kategorien</option>
                                <option value="unassigned">-- Unkategorisiert --</option>
                                @foreach($availableCategories as $cat)
                                    <option value="{{ $cat['id'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>

                            <input wire:model.live="dateFrom" type="date" class="bg-gray-900 border border-gray-800 text-sm rounded-xl px-3 py-2 text-gray-300 focus:border-orange-500 outline-none cursor-pointer">
                            <span class="text-gray-600">-</span>
                            <input wire:model.live="dateTo" type="date" class="bg-gray-900 border border-gray-800 text-sm rounded-xl px-3 py-2 text-gray-300 focus:border-orange-500 outline-none cursor-pointer">

                            @if($searchTx || $filterType || $filterCategoryId || $dateFrom || $dateTo)
                                <button wire:click="resetFilters" class="p-2 text-gray-500 hover:text-red-400 transition-colors" title="Filter zurücksetzen">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if(count($paginatedTransactions) > 0)
                        <div class="divide-y divide-gray-800/50">
                            @foreach($paginatedTransactions as $tx)
                                @php
                                    $isAssigned = !empty($tx->finance_category_id) || !empty($tx->finance_cost_item_id);
                                    $rowBgClass = $isAssigned
                                        ? 'bg-emerald-900/10 hover:bg-emerald-900/20 border-l-4 border-l-emerald-500'
                                        : 'bg-red-900/10 hover:bg-red-900/20 border-l-4 border-l-red-500';
                                @endphp
                                <div class="p-4 flex items-center justify-between gap-4 transition-colors {{ $rowBgClass }}">

                                    {{-- Left Side: Icon & Title/Tag --}}
                                    <div class="flex items-center gap-4 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-inner {{ $tx->amount > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-gray-950 text-gray-400 border border-gray-800' }}">
                                            @if($tx->amount > 0)
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                                            @else
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-bold text-white truncate" title="{{ $tx->counterpart_name ?? $tx->purpose ?? 'Kein Verwendungszweck' }}">{{ $tx->counterpart_name ?? $tx->purpose ?? 'Kein Verwendungszweck' }}</div>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-gray-400 font-mono">{{ $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date)->format('d.m.Y') : 'Unbekannt' }}</span>
                                                @if($isAssigned)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                                                        Vermerkt
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-red-500/20 text-red-400 border border-red-500/30">
                                                        Nicht zugeordnet
                                                    </span>
                                                @endif
                                                @if(optional($tx->bankAccount)->is_business)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-orange-500/20 text-orange-400 border border-orange-500/30" title="Gewerblich">
                                                        <svg class="w-3 h-3 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                        Gewerblich
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-500/20 text-blue-400 border border-blue-500/30" title="Privat">
                                                        <svg class="w-3 h-3 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                        Privat
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Right Side: Dropdowns & Amount --}}
                                    <div class="flex flex-col sm:flex-row items-end sm:items-center gap-3 sm:gap-6 shrink-0">

                                        {{-- Dropdowns Container (Fixed Width) --}}
                                        <div class="flex flex-col gap-2 w-32 sm:w-40 shrink-0">
                                            <select wire:change="assignCategory('{{ $tx->id }}', $event.target.value)" class="bg-gray-950 border {{ !empty($tx->finance_category_id) ? 'border-emerald-500/50 text-emerald-400' : 'border-gray-800 text-gray-300' }} text-[10px] sm:text-xs rounded-lg p-1.5 focus:border-orange-500 focus:ring-1 focus:ring-orange-500 outline-none w-full cursor-pointer transition-colors" onclick="event.stopPropagation()">
                                                <option value="" class="text-gray-500">Kategorie wählen...</option>
                                                @foreach($availableCategories as $cat)
                                                    <option value="{{ $cat['id'] }}" {{ $tx->finance_category_id === $cat['id'] ? 'selected' : '' }}>{{ $cat['name'] }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:change="assignCostItem('{{ $tx->id }}', $event.target.value)" class="bg-gray-950 border {{ !empty($tx->finance_cost_item_id) ? 'border-emerald-500/50 text-emerald-400' : 'border-gray-800 text-gray-300' }} text-[10px] sm:text-xs rounded-lg p-1.5 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none w-full cursor-pointer transition-colors" onclick="event.stopPropagation()">
                                                <option value="" class="text-gray-500">Fixkosten wählen...</option>
                                                @foreach($availableCostItems as $item)
                                                    <option value="{{ $item['id'] }}" {{ $tx->finance_cost_item_id === $item['id'] ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($item['name'], 20) }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        {{-- Amount (Fixed Width) --}}
                                        <div class="font-mono font-bold text-sm sm:text-base w-24 text-right whitespace-nowrap shrink-0 {{ $tx->amount > 0 ? 'text-emerald-400' : 'text-white' }}">
                                            {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 2, ',', '.') }} €
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-4 border-t border-gray-800 bg-gray-950/30">
                            {{ $paginatedTransactions->links() }}
                        </div>
                    @else
                        <div class="p-12 text-center flex flex-col items-center">
                            <svg class="w-10 h-10 text-gray-700 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span class="text-sm font-bold text-gray-400">Keine Umsätze gefunden</span>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
