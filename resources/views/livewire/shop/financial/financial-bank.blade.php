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
                    <div class="bg-gray-900/60 backdrop-blur-md border border-gray-800 rounded-3xl p-5 shadow-inner group hover:border-orange-500/50 transition-colors relative overflow-hidden">

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
                    @if(count($recentTransactions) > 0)
                        <div class="divide-y divide-gray-800/50">
                            @foreach($recentTransactions as $tx)
                                <div class="p-4 flex items-center justify-between hover:bg-gray-800/30 transition-colors">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-inner {{ $tx['amount'] > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-gray-950 text-gray-400 border border-gray-800' }}">
                                            @if($tx['amount'] > 0)
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                                            @else
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-white">{{ $tx['description'] }}</div>
                                            <div class="text-xs text-gray-500 font-mono mt-0.5">{{ \Carbon\Carbon::parse($tx['date'])->format('d.m.Y') }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-base {{ $tx['amount'] > 0 ? 'text-emerald-400' : 'text-white' }}">
                                            {{ $tx['amount'] > 0 ? '+' : '' }}{{ number_format($tx['amount'], 2, ',', '.') }} €
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="p-3 border-t border-gray-800 bg-gray-950/30 text-center">
                            <button class="text-[10px] font-black uppercase tracking-widest text-orange-400 hover:text-orange-300 transition-colors">
                                Alle Umsätze anzeigen
                            </button>
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
