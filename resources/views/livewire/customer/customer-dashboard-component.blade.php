<div class="w-full max-w-6xl mx-auto px-4 py-8 sm:px-6 lg:py-12 animate-fade-in-up">
    {{-- Alerts & Profile Completion Banner --}}
    @if(count($profileSteps) > 0)
        <div class="mb-8 p-4 sm:p-5 bg-red-500/5 border border-red-500/20 rounded-3xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-[0_0_20px_rgba(239,68,68,0.05)]">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="text-white font-bold text-sm">Profil unvollständig</h4>
                    <p class="text-gray-400 text-xs mt-0.5">Bitte vervollständige deine Daten für eine reibungslose Abwicklung zukünftiger Käufe.</p>
                </div>
            </div>
            @foreach($profileSteps as $step)
                <button @click="{!! $step['action'] !!}" class="w-full sm:w-auto px-5 py-2.5 bg-red-500 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:bg-red-600">
                    {{ $step['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Welcome & Stats Header Grid --}}
    <div class="relative overflow-hidden bg-gradient-to-r from-gray-900 via-gray-900 to-indigo-950/20 border border-gray-800 rounded-3xl p-6 sm:p-8 md:p-10 mb-8 shadow-2xl">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-500/5 via-transparent to-transparent"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 text-center sm:text-left">
                {{-- Avatar --}}
                <div class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gray-950 border border-gray-800 overflow-hidden flex items-center justify-center shrink-0 shadow-inner">
                    @if(auth()->user()->profile && auth()->user()->profile->photo_path)
                        @php
                            $pp = auth()->user()->profile->photo_path;
                            $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/')) 
                                   ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                        @endphp
                        <img src="{{ $src }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-gray-500 font-bold text-2xl sm:text-3xl flex items-center justify-center h-full uppercase">{{ substr(auth()->user()->first_name ?? 'U', 0, 1) }}</span>
                    @endif
                </div>

                <div class="mt-2 sm:mt-0">
                    <span class="text-primary text-xs font-black uppercase tracking-[0.2em]">Mitgliederbereich</span>
                    <h2 class="text-3xl sm:text-4xl md:text-5xl font-serif font-bold text-white mt-1 mb-3 tracking-tight">
                        Hallo, {{ auth()->user()->first_name }}
                    </h2>
                    <p class="text-gray-400 text-sm max-w-xl leading-relaxed">
                        Willkommen in deinem persönlichen Seelenfunke-Bereich. Hier kannst du deine Bestellungen verfolgen, Dokumente verwalten und Support erhalten.
                    </p>
                </div>
            </div>

            {{-- Quick Stats Row --}}
            <div class="grid grid-cols-2 sm:grid-cols-2 gap-4 shrink-0">
                <div class="bg-gray-950/60 backdrop-blur-md border border-gray-800/80 rounded-2xl p-4 min-w-[140px] shadow-inner">
                    <span class="text-gray-500 text-[10px] font-black uppercase tracking-widest block mb-1">Konto-Typ</span>
                    <span class="text-white text-xs font-bold flex items-center gap-1.5 mt-0.5">
                        @if(isset(auth()->user()->profile) && auth()->user()->profile->is_business)
                            <x-heroicon-s-briefcase class="w-4 h-4 text-amber-500" /> Gewerblich
                        @else
                            <x-heroicon-s-user class="w-4 h-4 text-blue-400" /> Privatkunde
                        @endif
                    </span>
                </div>

                <div x-data="{ copied: false, code: '{{ strtoupper(explode('-', auth()->guard('customer')->id())[0]) }}' }"
                     class="bg-gray-950/60 backdrop-blur-md border border-gray-800/80 rounded-2xl p-4 min-w-[140px] shadow-inner cursor-pointer hover:border-gray-700 transition-colors"
                     @click="navigator.clipboard.writeText(code); copied = true; setTimeout(() => copied = false, 2000)">
                    <span class="text-gray-500 text-[10px] font-black uppercase tracking-widest block mb-1">Kundennummer</span>
                    <div class="flex items-center justify-between gap-2 mt-0.5">
                        <span class="text-primary font-mono text-xs font-bold tracking-wider" x-text="code"></span>
                        <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-primary transition-colors" />
                        <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Layout Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        {{-- Left Column (2/3 width on desktop) --}}
        <div class="lg:col-span-2 space-y-8">

            {{-- Letzte Bestellungen (Recent Orders) --}}
            <div class="bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-serif font-bold text-white">Letzte Bestellungen</h3>
                        <p class="text-gray-400 text-xs mt-0.5">Deine jüngsten Einkäufe auf einen Blick.</p>
                    </div>
                    <a href="{{ route('customer.orders') }}" class="text-xs font-black uppercase tracking-widest text-primary hover:text-white transition-colors flex items-center gap-1.5">
                        Alle ansehen <x-heroicon-m-chevron-right class="w-4 h-4" />
                    </a>
                </div>

                @if(count($recentOrders) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-800/60 pb-3">
                                    <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-3">Bestellung</th>
                                    <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-3">Datum</th>
                                    <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-3">Betrag</th>
                                    <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-3">Status</th>
                                    <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-3 text-right">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800/40">
                                @foreach($recentOrders as $order)
                                    <tr class="group hover:bg-gray-800/10 transition-colors">
                                        <td class="py-3.5 pr-3">
                                            <span class="text-white font-mono text-xs font-bold">#{{ $order->order_number }}</span>
                                        </td>
                                        <td class="py-3.5 pr-3">
                                            <span class="text-gray-400 text-xs">{{ $order->created_at->format('d.m.Y') }}</span>
                                        </td>
                                        <td class="py-3.5 pr-3">
                                            <span class="text-primary font-mono text-xs font-bold">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
                                        </td>
                                        <td class="py-3.5 pr-3">
                                            @php
                                                $statusClass = match($order->status) {
                                                    'pending' => 'bg-yellow-500/10 border-yellow-500/20 text-yellow-500',
                                                    'processing' => 'bg-blue-500/10 border-blue-500/20 text-blue-400',
                                                    'shipped' => 'bg-purple-500/10 border-purple-500/20 text-purple-400',
                                                    'completed' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
                                                    'cancelled' => 'bg-red-500/10 border-red-500/20 text-red-500',
                                                    'refunded' => 'bg-gray-500/10 border-gray-500/20 text-gray-400',
                                                    default => 'bg-gray-500/10 border-gray-500/20 text-gray-400',
                                                };
                                                $statusLabel = match($order->status) {
                                                    'pending' => 'Ausstehend',
                                                    'processing' => 'In Bearbeitung',
                                                    'shipped' => 'Versandt',
                                                    'completed' => 'Abgeschlossen',
                                                    'cancelled' => 'Storniert',
                                                    'refunded' => 'Erstattet',
                                                    default => ucfirst($order->status),
                                                };
                                            @endphp
                                            <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider {{ $statusClass }}">
                                                {{ $statusLabel }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 text-right">
                                            <a href="{{ route('customer.orders') }}" class="inline-flex items-center justify-center p-1.5 bg-gray-950 border border-gray-800 rounded-lg text-gray-400 hover:text-primary hover:border-primary/30 transition-colors">
                                                <x-heroicon-m-eye class="w-4 h-4" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-8 bg-gray-950/40 rounded-2xl border border-gray-800/40 shadow-inner">
                        <x-heroicon-o-shopping-bag class="w-10 h-10 text-gray-600 mx-auto mb-3" />
                        <h4 class="text-white font-bold text-sm">Noch keine Bestellungen</h4>
                        <p class="text-gray-500 text-xs mt-1 mb-4">Du hast bisher noch keine Einkäufe getätigt.</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-gray-900 rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all">
                            Zum Shop <x-heroicon-m-shopping-cart class="w-4 h-4" />
                        </a>
                    </div>
                @endif
            </div>

            {{-- Schnellzugriff Kacheln (Quick Action Grid) --}}
            <div>
                <h3 class="text-lg font-serif font-bold text-white mb-4">Schnellzugriff & Funktionen</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">

                    {{-- Kachel: Bestellungen --}}
                    <a href="{{ route('customer.orders') }}" class="group relative bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-primary/40 hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-[0_0_25px_rgba(197,160,89,0.08)] overflow-hidden flex flex-col justify-between min-h-[160px]">
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 text-primary flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform duration-300">
                                <x-heroicon-s-shopping-bag class="w-6 h-6" />
                            </div>
                            <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-600 group-hover:text-primary group-hover:translate-x-1 transition-all" />
                        </div>
                        <div class="mt-4 relative z-10">
                            <h4 class="text-white font-bold text-base">Meine Bestellungen</h4>
                            <p class="text-gray-400 text-xs mt-1 leading-relaxed">Status deiner Unikate verfolgen und Bestellungen einsehen.</p>
                        </div>
                    </a>

                    {{-- Kachel: Rechnungen --}}
                    <a href="{{ route('customer.invoices') }}" class="group relative bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-blue-500/40 hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-[0_0_25px_rgba(59,130,246,0.08)] overflow-hidden flex flex-col justify-between min-h-[160px]">
                        <div class="absolute inset-0 bg-gradient-to-br from-blue-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 text-blue-400 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform duration-300">
                                <x-heroicon-s-document-text class="w-6 h-6" />
                            </div>
                            <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-600 group-hover:text-blue-400 group-hover:translate-x-1 transition-all" />
                        </div>
                        <div class="mt-4 relative z-10">
                            <h4 class="text-white font-bold text-base">Meine Rechnungen</h4>
                            <p class="text-gray-400 text-xs mt-1 leading-relaxed">Alle PDF-Belege herunterladen und Belege verwalten.</p>
                        </div>
                    </a>

                    {{-- Kachel: Support --}}
                    <a href="{{ route('customer.support') }}" class="group relative bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-amber-500/40 hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-[0_0_25px_rgba(245,158,11,0.08)] overflow-hidden flex flex-col justify-between min-h-[160px]">
                        <div class="absolute inset-0 bg-gradient-to-br from-amber-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                        <div class="flex items-center justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 text-amber-500 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform duration-300">
                                <x-heroicon-s-chat-bubble-left-right class="w-6 h-6" />
                            </div>
                            <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-600 group-hover:text-amber-500 group-hover:translate-x-1 transition-all" />
                        </div>
                        <div class="mt-4 relative z-10">
                            <h4 class="text-white font-bold text-base">Hilfe & Support</h4>
                            <p class="text-gray-400 text-xs mt-1 leading-relaxed">Direktkontakt zu uns aufnehmen und Support-Tickets einsehen.</p>
                        </div>
                    </a>

                    {{-- Kachel: Spielmodus (Wenn aktiv oder optional) --}}
                    @if($isGameModeActive)
                        <a href="{{ route('customer.games') }}" class="group relative bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-emerald-500/40 hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-[0_0_25px_rgba(16,185,129,0.08)] overflow-hidden flex flex-col justify-between min-h-[160px]">
                            <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 text-emerald-400 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform duration-300">
                                    <x-heroicon-s-bolt class="w-6 h-6 animate-pulse" />
                                </div>
                                <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-600 group-hover:text-emerald-400 group-hover:translate-x-1 transition-all" />
                            </div>
                            <div class="mt-4 relative z-10">
                                <h4 class="text-white font-bold text-base">Magisches Abenteuer</h4>
                                <p class="text-gray-400 text-xs mt-1 leading-relaxed">Minispiele spielen, Funken sammeln und Rabattcodes freischalten.</p>
                            </div>
                        </a>
                    @else
                        <a href="{{ route('shop') }}" class="group relative bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 hover:border-purple-500/40 hover:-translate-y-1 transition-all duration-300 shadow-lg hover:shadow-[0_0_25px_rgba(168,85,247,0.08)] overflow-hidden flex flex-col justify-between min-h-[160px]">
                            <div class="absolute inset-0 bg-gradient-to-br from-purple-500/5 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            <div class="flex items-center justify-between">
                                <div class="w-12 h-12 rounded-2xl bg-gray-950 border border-gray-800 text-purple-400 flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform duration-300">
                                    <x-heroicon-s-building-storefront class="w-6 h-6" />
                                </div>
                                <x-heroicon-m-arrow-right class="w-5 h-5 text-gray-600 group-hover:text-purple-400 group-hover:translate-x-1 transition-all" />
                            </div>
                            <div class="mt-4 relative z-10">
                                <h4 class="text-white font-bold text-base">Zurück zum Shop</h4>
                                <p class="text-gray-400 text-xs mt-1 leading-relaxed">Weiter einkaufen, das Sortiment ansehen und Unikate entdecken.</p>
                            </div>
                        </a>
                    @endif

                </div>
            </div>

        </div>

        {{-- Right Column (1/3 width on desktop) --}}
        <div class="space-y-8">

            {{-- Kontoübersicht (At a Glance) --}}
            <div class="bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full blur-2xl"></div>
                <h3 class="text-base font-serif font-bold text-white mb-4 flex items-center gap-2">
                    <x-heroicon-s-identification class="w-5 h-5 text-primary" /> Profildaten
                </h3>

                <div class="space-y-4">
                    {{-- Personal Data Summary --}}
                    <div class="p-3 bg-gray-950/40 rounded-xl border border-gray-800/60">
                        <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Name & E-Mail</span>
                        <span class="text-white text-xs font-bold block mt-1">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                        <span class="text-gray-400 text-xs font-medium block mt-0.5 truncate">{{ auth()->user()->email }}</span>
                    </div>

                    {{-- Address Summary --}}
                    <div class="p-3 bg-gray-950/40 rounded-xl border border-gray-800/60">
                        <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Liefer- & Rechnungsadresse</span>
                        @if(isset(auth()->user()->profile) && !empty(auth()->user()->profile->street))
                            <span class="text-white text-xs font-bold block mt-1">
                                {{ auth()->user()->profile->street }} {{ auth()->user()->profile->house_number }}
                            </span>
                            <span class="text-gray-400 text-xs font-medium block mt-0.5">
                                {{ auth()->user()->profile->postal }} {{ auth()->user()->profile->city }}
                            </span>
                            <span class="text-gray-400 text-xs font-medium block mt-0.5">
                                {{ auth()->user()->profile->country === 'DE' ? 'Deutschland' : auth()->user()->profile->country }}
                            </span>
                        @else
                            <span class="text-red-400/80 text-xs font-bold block mt-1">Keine Adresse hinterlegt</span>
                        @endif
                    </div>

                    <button @click="$dispatch('open-profile-modal', {tab: 'profile'})" class="w-full py-2.5 bg-gray-950 hover:bg-gray-800 text-white border border-gray-800 hover:border-gray-700 rounded-xl text-xs font-black uppercase tracking-widest transition-colors">
                        Profil bearbeiten
                    </button>
                </div>
            </div>

            {{-- Support & Assistance Box --}}
            <div class="bg-gray-900/40 backdrop-blur-md border border-gray-800 rounded-3xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl"></div>
                <h3 class="text-base font-serif font-bold text-white mb-4 flex items-center gap-2">
                    <x-heroicon-s-lifebuoy class="w-5 h-5 text-amber-500" /> Support & Service
                </h3>
                <p class="text-gray-400 text-xs leading-relaxed mb-4">
                    Hast du Fragen zu deiner Bestellung, möchtest eine Änderung vornehmen oder hast ein technisches Problem?
                </p>
                <div class="space-y-2.5 mb-5 text-xs text-gray-300">
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-clock class="w-4 h-4 text-amber-500" />
                        <span>Antwortzeit: Meist unter 24 Std.</span>
                    </div>
                </div>
                <a href="{{ route('customer.support') }}" class="block text-center py-2.5 bg-amber-500 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-105 transition-all shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                    Ticket erstellen
                </a>
            </div>

        </div>

    </div>
</div>
