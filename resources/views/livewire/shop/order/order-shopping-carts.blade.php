<div class="min-h-screen bg-transparent p-4 md:p-8 font-sans antialiased text-gray-300">
    @if(!$detailCartId)
        <div wire:key="cart-list-view" class="w-full flex-col flex">
        {{-- VIEW 1: LISTEN-ANSICHT --}}
        
        {{-- HEADER / TITLE --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] sm:rounded-[3rem] shadow-2xl border border-gray-800 relative overflow-hidden mb-8 sm:mb-12 group transition-all duration-500 p-6 sm:p-10 flex flex-col justify-center text-center">
            <h2 class="font-serif font-bold text-3xl sm:text-4xl text-white mb-3 tracking-tight">
                Verlassene Warenkörbe
            </h2>
            <p class="text-gray-400 text-sm sm:text-base font-medium leading-relaxed max-w-2xl mx-auto">
                Behalte die Übersicht über abgebrochene oder pausierte Einkaufsprozesse. Sende Zahlungserinnerungen, passe Mengen an oder lösche blockierte Körbe.
            </p>
            
            {{-- LEGENDE --}}
            <div class="mt-6 flex flex-wrap items-center justify-center gap-4 sm:gap-6 text-xs sm:text-sm text-gray-300 font-medium">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-500 shadow-[0_0_8px_rgba(107,114,128,0.6)]"></div>
                    <span>Aktiv / Neu</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.6)]"></div>
                    <span>Warnung (Verlassen)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.6)]"></div>
                    <span>Abgebrochen (Mail anstehend)</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></div>
                    <span>Erinnerung gesendet</span>
                </div>
            </div>
        </div>

        @if(session()->has('success'))
            <div class="mb-8 p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-[1rem] shadow-xl flex items-center gap-3">
                <svg class="w-6 h-6 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="text-sm font-bold text-emerald-400">{{ session('success') }}</span>
            </div>
        @endif
        @if(session()->has('error'))
            <div class="mb-8 p-4 bg-red-500/10 border border-red-500/30 rounded-[1rem] shadow-xl flex items-center gap-3">
                <svg class="w-6 h-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="text-sm font-bold text-red-400">{{ session('error') }}</span>
            </div>
        @endif

        {{-- FILTER & SUCHE --}}
        <div class="flex flex-col lg:flex-row justify-between items-center gap-4 mb-8 bg-gray-900/80 backdrop-blur-md p-2.5 sm:p-3 rounded-[2rem] shadow-2xl border border-gray-800 w-full relative z-40">
            <div class="relative w-full lg:w-[400px] group">
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Suchen nach Session, Name, E-Mail..."
                       class="w-full pl-12 pr-4 py-3 bg-gray-950 border border-gray-800 rounded-[1.5rem] text-sm text-white focus:bg-black focus:ring-2 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] transition-all placeholder-gray-500 shadow-inner outline-none">
                <svg class="w-5 h-5 text-gray-500 absolute left-4 top-3.5 group-focus-within:text-[var(--theme-color)] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            
            {{-- AMPEL-KONFIGURATION --}}
            <div x-data="{ open: false }" class="relative z-50">
                <button @click="open = !open" type="button" class="flex items-center gap-2 px-4 py-3 bg-gray-950 hover:bg-gray-800 border border-gray-800 rounded-[1.5rem] text-sm text-gray-300 transition-colors shadow-inner text-nowrap">
                    <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Ampeln konfigurieren
                </button>
                
                <div x-show="open" @click.away="open = false" 
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-1"
                     class="absolute right-0 top-full mt-3 w-80 bg-gray-900 border border-gray-700 rounded-2xl shadow-2xl p-5" style="display: none;">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white mb-4">Schwellenwerte in Stunden (h)</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="flex justify-between text-xs text-gray-400 mb-1">
                                <span class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-yellow-500 shadow-[0_0_8px_rgba(234,179,8,0.5)]"></div> Warnstufe Gelb ab</span>
                            </label>
                            <input type="number" wire:model.defer="cartYellowLimit" min="1" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-white text-sm focus:ring-2 focus:ring-yellow-500/50 outline-none transition-all shadow-inner">
                        </div>
                        <div>
                            <label class="flex justify-between text-xs text-gray-400 mb-1">
                                <span class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]"></div> Alarm Rot ab</span>
                            </label>
                            <input type="number" wire:model.defer="cartRedLimit" min="1" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-3 py-2 text-white text-sm focus:ring-2 focus:ring-red-500/50 outline-none transition-all shadow-inner">
                        </div>
                        <button wire:click="saveSettings" @click="open = false" class="w-full py-2.5 mt-2 bg-[var(--theme-color-10)] text-[var(--theme-color)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-30)] rounded-xl text-xs font-bold transition-colors">
                            Speichern
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- WARENKORB LISTE --}}
        @if($this->carts->isNotEmpty())
            <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden w-full">
                <div class="w-full overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-950/80 border-b border-gray-800">
                                <th class="w-16 px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">Aktion</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Kunde / Gast</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500">Letztes Update</th>
                                <th class="w-24 px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">Erinnerung</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-center">Artikel</th>
                                <th class="px-6 py-5 text-[10px] font-black uppercase tracking-widest text-gray-500 text-right">Potenzial</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            @foreach($this->carts as $cart)
                                @php
                                    // Calculate potential revenue
                                    $potentialRevenue = 0;
                                    $itemCount = 0;
                                    foreach($cart->items as $item) {
                                        $potentialRevenue += ($item->unit_price * $item->quantity);
                                        $itemCount += $item->quantity;
                                    }
                                    
                                    // Traffic light color based on age
                                    $hours = $cart->updated_at->diffInHours(now());
                                    $statusColor = 'bg-gray-500'; // Default gray (active/new)
                                    if ($cart->reminder_email_sent_at) {
                                        $statusColor = 'bg-emerald-500';
                                    } else {
                                        if ($hours >= $this->cartRedLimit) $statusColor = 'bg-red-500';
                                        elseif ($hours >= $this->cartYellowLimit) $statusColor = 'bg-yellow-500';
                                    }
                                @endphp
                                <tr wire:click="viewDetails('{{ $cart->id }}')" class="hover:bg-gray-800/50 cursor-pointer group transition-colors">
                                    <td class="px-6 py-4 text-center">
                                        <div class="w-3 h-3 rounded-full {{ $statusColor }} mx-auto shadow-[0_0_8px_currentColor] opacity-80 group-hover:opacity-100"></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($cart->customer)
                                            <div class="text-sm font-bold text-white">{{ $cart->customer->first_name }} {{ $cart->customer->last_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $cart->customer->email }}</div>
                                        @else
                                            <div class="text-sm font-medium text-gray-400 italic">Gast ({{ substr($cart->session_id, 0, 8) }}...)</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-300">{{ $cart->updated_at->format('d.m.Y H:i') }}</div>
                                        <div class="text-[10px] text-gray-500 uppercase tracking-widest">vor {{ $cart->updated_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-center" x-data x-on:click.stop>
                                        @if($cart->reminder_email_sent_at)
                                            <div class="flex flex-col items-center justify-center gap-1">
                                                <div class="text-[9px] text-blue-400 font-bold uppercase tracking-widest flex items-center justify-center gap-1" title="Mail versendet am {{ $cart->reminder_email_sent_at->format('d.m.Y H:i:s') }}">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                    Gesendet
                                                </div>
                                                <button wire:click.stop="sendReminderEmail('{{ $cart->id }}')" wire:confirm="Erneut Mail senden an {{ $cart->customer->email ?? 'unbekannt' }}?" class="text-[9px] text-gray-500 hover:text-blue-300 transition-colors uppercase tracking-widest border-b border-transparent hover:border-blue-300">
                                                    Erneut senden
                                                </button>
                                            </div>
                                        @else
                                            @if($cart->customer && $cart->customer->email)
                                                <button wire:click.stop="sendReminderEmail('{{ $cart->id }}')" 
                                                        wire:confirm="Erinnerungsmail an {{ $cart->customer->email }} senden?"
                                                        class="opacity-0 group-hover:opacity-100 transition-opacity bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] hover:bg-[var(--theme-color-20)] px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest flex items-center justify-center gap-1 mx-auto shadow-xl shadow-[var(--theme-color-10)]">
                                                    Senden
                                                </button>
                                            @else
                                                <span class="text-xs text-gray-600 block text-center" title="Gast ohne E-Mail">-</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center justify-center bg-gray-800 text-gray-300 text-xs font-bold px-2.5 py-1 rounded-md border border-gray-700 w-10">
                                            {{ $itemCount }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="text-sm font-bold text-emerald-400">{{ number_format($potentialRevenue / 100, 2, ',', '.') }} €</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="mt-6 flex justify-between items-center text-xs text-gray-500">
                <div>Zeige {{ $this->carts->firstItem() }} bis {{ $this->carts->lastItem() }} von {{ $this->carts->total() }} Einträgen</div>
                <div>{{ $this->carts->links('pagination::tailwind') }}</div>
            </div>
        @else
            <div class="text-center py-28 bg-gray-900/80 backdrop-blur-md rounded-[3rem] border border-gray-800 shadow-2xl w-full">
                <div class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-gray-950 border border-gray-800 mb-6 shadow-inner">
                    <svg class="w-10 h-10 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <h3 class="text-2xl font-serif font-bold text-white mb-2 tracking-tight">Keine Warenkörbe gefunden</h3>
                <p class="text-gray-500 mb-8 max-w-sm mx-auto text-sm">Es wurden keine Warenkörbe gefunden, die auf deine Filter passen.</p>
                <button wire:click="$set('search', '')" class="text-[var(--theme-color)] font-black hover:text-white transition-colors uppercase tracking-widest text-[10px] border-b border-[var(--theme-color-30)] pb-0.5 hover:border-white">
                    Filter zurücksetzen
                </button>
            </div>
        @endif

        </div>
    @else
        <div wire:key="cart-detail-view" class="w-full">
        {{-- VIEW 2: DETAIL ANSICHT --}}
        <div class="bg-gray-900/80 backdrop-blur-md rounded-[2rem] shadow-2xl border border-gray-800 overflow-hidden w-full relative">
            
            {{-- HEADER BAR --}}
            <div class="flex items-center justify-between p-6 border-b border-gray-800 bg-gray-950/50">
                <div class="flex items-center gap-4">
                    <button wire:click="closeDetails" class="w-10 h-10 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </button>
                    <div>
                        <h2 class="text-xl font-serif font-bold text-white">Warenkorb Details</h2>
                        <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">Sitzung: {{ substr($detailCart->session_id, 0, 12) }}...</p>
                    </div>
                </div>
                
                {{-- ACTIONS --}}
                <div class="flex items-center gap-3">
                    @if($detailCart->customer && $detailCart->customer->email)
                        <button wire:click="sendReminderEmail('{{ $detailCart->id }}')" 
                                wire:confirm="Möchtest du diese Erinnerungs E-Mail wirklich sicher an {{ $detailCart->customer->email }} versenden?"
                                class="flex items-center gap-2 px-4 py-2 {{ $detailCart->reminder_email_sent_at ? 'bg-blue-500/10 text-blue-400 border-blue-500/30 hover:bg-blue-500/20' : 'bg-[var(--theme-color-10)] text-[var(--theme-color)] hover:bg-[var(--theme-color-20)] border-[var(--theme-color-30)]' }} border rounded-xl text-sm font-bold transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            {{ $detailCart->reminder_email_sent_at ? 'Mail erneut senden' : 'Erinnerung Senden' }}
                        </button>
                    @endif
                    <button wire:click="deleteCart('{{ $detailCart->id }}')"
                            wire:confirm="Bist du sicher, dass du diesen gesamten Warenkorb löschen möchtest?"
                            class="flex items-center gap-2 px-4 py-2 bg-red-500/10 text-red-500 hover:bg-red-500/20 border border-red-500/20 rounded-xl text-sm font-bold transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Löschen
                    </button>
                </div>
            </div>

            <div class="p-6 md:p-8">
                
                {{-- FLASH MESSAGES --}}
                @if (session()->has('success'))
                    <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ session('success') }}
                    </div>
                @endif
                
                @if (session()->has('error'))
                    <div class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        {{ session('error') }}
                    </div>
                @endif
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    {{-- LEFT SIDE: ITEMS --}}
                    <div class="lg:col-span-2 space-y-4">
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-4 border-b border-gray-800 pb-2">Artikel im Korb</h3>
                        @foreach($detailCart->items as $item)
                            @php
                                $prod = $item->product;
                                if(!$prod) continue;
                                $type = $prod->type ?? 'physical';
                                $attributes = $prod->attributes ?? [];
                                $deliveryTime = $attributes['Lieferzeit'] ?? null;
                            @endphp
                            <div class="bg-gray-950 border border-gray-800 rounded-2xl p-4 flex flex-col sm:flex-row items-center sm:items-start gap-4">
                                {{-- Thumbnail --}}
                                <div class="w-20 h-20 bg-gray-900 rounded-xl overflow-hidden border border-gray-800 relative group shrink-0">
                                    @if(isset($prod->media_gallery[0]) && isset($prod->media_gallery[0]['path']))
                                        <img src="{{ asset('storage/'.$prod->media_gallery[0]['path']) }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-700">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif
                                    @if($type === 'digital')
                                        <div class="absolute bottom-0 left-0 right-0 bg-blue-600/90 text-white text-[9px] font-bold text-center py-0.5">DIGITAL</div>
                                    @elseif($type === 'service')
                                        <div class="absolute bottom-0 left-0 right-0 bg-orange-500/90 text-white text-[9px] font-bold text-center py-0.5">SERVICE</div>
                                    @endif
                                </div>
                                
                                {{-- Details --}}
                                <div class="flex-1 text-center sm:text-left min-w-0">
                                    <h4 class="text-white font-bold text-sm truncate">
                                        <a href="{{ route('product.show', $prod->slug) }}" target="_blank" class="hover:text-primary transition">{{ $prod->name }}</a>
                                    </h4>
                                    
                                    @if(!empty($item->configuration['variant_name']))
                                        <p class="text-[10px] font-black uppercase tracking-widest text-primary my-1 drop-shadow-[0_0_5px_currentColor] truncate">
                                            Ausführung: {{ $item->configuration['variant_name'] }}
                                        </p>
                                    @endif

                                    <div class="flex flex-wrap gap-2 justify-center sm:justify-start my-2">
                                        @if(!empty($item->configuration['text']))
                                            <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-400 bg-gray-900 border border-gray-800 px-2 py-1 rounded">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Text
                                            </span>
                                        @endif
                                        @if(!empty($item->configuration['logos']))
                                            <span class="inline-flex items-center gap-1 text-[10px] uppercase font-bold text-gray-400 bg-gray-900 border border-gray-800 px-2 py-1 rounded">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                                Motiv
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="text-[11px] text-gray-500 font-mono">Stückpreis: {{ number_format($item->unit_price / 100, 2, ',', '.') }} €</div>
                                </div>
                                
                                {{-- Controls --}}
                                <div class="flex flex-col items-center gap-3 shrink-0">
                                    @if($type === 'physical')
                                        <div class="flex items-center bg-gray-900 rounded-full border border-gray-800">
                                            <button wire:click="decrement('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-white transition font-bold text-lg">-</button>
                                            <span class="w-8 text-center text-sm font-bold text-white">{{ $item->quantity }}</span>
                                            <button wire:click="increment('{{ $item->id }}')" class="w-8 h-8 flex items-center justify-center text-gray-500 hover:text-white transition font-bold text-lg">+</button>
                                        </div>
                                    @else
                                        <div class="flex items-center justify-center bg-gray-900 rounded-full border border-gray-800 px-4 py-1.5 text-white">
                                            <span class="text-sm font-bold text-gray-300">{{ $item->quantity }}x</span>
                                        </div>
                                    @endif

                                    <div class="font-bold text-[15px] text-emerald-400">
                                        {{ number_format(($item->unit_price * $item->quantity) / 100, 2, ',', '.') }} €
                                    </div>
                                    
                                    <div class="flex gap-2">
                                        @if($type === 'physical' && $prod->isPersonalizable())
                                            <button wire:click="edit('{{ $item->id }}')" 
                                                    class="w-8 h-8 rounded-lg border border-gray-700 bg-gray-800 text-gray-400 flex items-center justify-center hover:bg-gray-700 hover:text-white transition-colors" title="Bearbeiten">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                            </button>
                                        @endif
                                        <button wire:click="removeItem('{{ $item->id }}')" 
                                                class="w-8 h-8 rounded-lg border border-red-500/20 bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-500 hover:text-white transition-colors" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    {{-- RIGHT SIDE: CUSTOMER INFO --}}
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-4 border-b border-gray-800 pb-2">Kundenprofil</h3>
                        <div class="bg-gray-950 border border-gray-800 rounded-2xl p-5">
                            @if($detailCart->customer)
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="w-12 h-12 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)] flex items-center justify-center text-lg font-bold">
                                        {{ substr($detailCart->customer->first_name, 0, 1) }}{{ substr($detailCart->customer->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-white">{{ $detailCart->customer->first_name }} {{ $detailCart->customer->last_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $detailCart->customer->email }}</div>
                                    </div>
                                </div>
                                
                                <div class="space-y-3 pt-3 border-t border-gray-800">
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="text-gray-500">Angemeldet seit</span>
                                        <span class="font-semibold text-gray-300">{{ $detailCart->customer->created_at->format('d.m.Y') }}</span>
                                    </div>
                                    @if($detailCart->reminder_email_sent_at)
                                        <div class="flex justify-between items-center text-sm">
                                            <span class="text-gray-500">Mail Erinnerung</span>
                                            <span class="font-semibold text-blue-400">Versandt am {{ $detailCart->reminder_email_sent_at->format('d.m.Y H:i') }}</span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="text-center py-6">
                                    <div class="w-12 h-12 rounded-full bg-gray-800 text-gray-500 flex items-center justify-center text-lg font-bold mx-auto mb-3">
                                        G
                                    </div>
                                    <div class="font-bold text-white mb-1">Unbekannter Gast</div>
                                    <div class="text-xs text-gray-500 font-mono">{{ substr($detailCart->session_id, 0, 20) }}...</div>
                                    
                                    <div class="mt-4 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400 text-[11px] leading-relaxed text-left font-medium">
                                        ⚠️ Keine hinterlegte Adresse oder Benutzerkonto verknüpft. Das Versenden einer E-Mail-Erinnerung ist nicht möglich.
                                    </div>
                                </div>
                            @endif
                            
                            @if($this->detailTotals)
                                <div class="mt-6">
                                    <x-shop.cost-summary 
                                        :totals="$this->detailTotals" 
                                        :showTitle="false"
                                        design="dark"
                                        containerOverride="bg-transparent border-t border-gray-800 pt-5 space-y-3"
                                    />
                                    
                                    <div class="mt-4 flex justify-between items-center text-[10px] text-gray-500 uppercase tracking-wider">
                                        <span>Zuletzt berührt</span>
                                        <span>{{ $detailCart->updated_at->format('d.m. H:i') }} Uhr</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Configurator als Modal Overlay --}}
        @if($editingItemId)
            @php
                $editingItem = $detailCart->items->firstWhere('id', $editingItemId);
            @endphp
            @if($editingItem && ($editingItem->product->type ?? 'physical') !== 'digital')
                <div class="fixed inset-0 z-[99999] flex items-center justify-center p-4 pt-20 sm:p-6 bg-black/80 backdrop-blur-sm animate-fade-in">
                    <div class="absolute inset-0" wire:click="closeModal"></div>

                    <div class="relative bg-white w-full max-w-5xl max-h-[95vh] rounded-[2rem] shadow-2xl flex flex-col overflow-hidden animate-fade-in-up">

                        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50 shrink-0 z-20">
                            <div>
                                <h2 class="text-xl font-serif font-bold text-gray-900">
                                    Admin Bearbeitung: {{ $editingItem->product->name }}
                                </h2>
                                <p class="text-[10px] uppercase tracking-widest text-[var(--theme-color)] font-bold mt-1">Sitzung: {{ substr($detailCart->session_id, 0, 12) }}...</p>
                            </div>
                            <button wire:click="closeModal" class="p-2 bg-white border border-gray-200 text-gray-500 hover:text-red-500 hover:border-red-200 rounded-full transition-all shadow-sm">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto custom-scrollbar relative bg-gray-50/30 p-0 sm:p-4">
                            <livewire:shop.product.product-configurator.product-configurator
                                :product="$editingItem->product"
                                :cartItem="$editingItem"
                                context="edit"
                                :key="'modal-edit-'.$editingItem->id"
                            />
                        </div>
                    </div>
                </div>
            @endif
        @endif
                    
                </div>
            </div>
        </div>
    @endif
</div>
