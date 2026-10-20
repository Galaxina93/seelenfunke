<div class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 lg:py-7 animate-fade-in-up">
    {{-- Alerts & Profile Completion Banner --}}
    @if(count($profileSteps) > 0)
        <div class="mb-6 p-4 bg-red-500/5 border border-red-500/20 rounded-2xl sm:rounded-3xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-[0_0_20px_rgba(239,68,68,0.05)]">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-red-500/10 flex items-center justify-center text-red-500">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="text-red-700 dark:text-white font-bold text-sm">Profil unvollständig</h4>
                    <p class="text-red-600/80 dark:text-gray-400 text-xs mt-0.5">Bitte vervollständige deine Daten für eine reibungslose Abwicklung zukünftiger Käufe.</p>
                </div>
            </div>
            @foreach($profileSteps as $step)
                <button @click="{!! $step['action'] !!}" class="w-full sm:w-auto px-4 py-2 bg-red-500 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-[0_0_15px_rgba(239,68,68,0.3)] hover:bg-red-600 cursor-pointer">
                    {{ $step['label'] }}
                </button>
            @endforeach
        </div>
    @endif

    {{-- Welcome & Stats Header Grid (Kompakt nach oben/unten) --}}
    <div class="relative overflow-hidden bg-white dark:bg-gradient-to-r dark:from-gray-900 dark:via-gray-900 dark:to-indigo-950/20 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-3xl px-5 py-4 sm:px-6 sm:py-4 md:px-7 md:py-4 mb-6 shadow-sm dark:shadow-xl">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-500/5 via-transparent to-transparent pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4 sm:gap-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-center gap-4 text-center sm:text-left">
                {{-- Avatar --}}
                <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 overflow-hidden flex items-center justify-center shrink-0 shadow-inner">
                    @if(auth()->user()->profile && auth()->user()->profile->photo_path)
                        @php
                            $pp = auth()->user()->profile->photo_path;
                            $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/')) 
                                   ? asset($pp) : (\Illuminate\Support\Str::startsWith($pp, 'shop/') ? asset($pp) : Storage::url($pp));
                        @endphp
                        <img src="{{ $src }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-gray-500 font-bold text-xl sm:text-2xl flex items-center justify-center h-full uppercase">{{ substr(auth()->user()->first_name ?? 'U', 0, 1) }}</span>
                    @endif
                </div>

                <div>
                    <span class="text-primary text-[10px] sm:text-xs font-black uppercase tracking-[0.2em] block">Mitgliederbereich</span>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mt-0.5 tracking-tight">
                        Hallo, {{ auth()->user()->first_name }}
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5 max-w-lg leading-relaxed">
                        Willkommen in deinem persönlichen Bereich – behalte deine Bestellungen und Daten im Blick.
                    </p>
                </div>
            </div>

            {{-- Quick Stats Row --}}
            <div class="grid grid-cols-2 {{ count($activeGiftVouchers) > 0 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-2.5 sm:gap-3 shrink-0">
                <div class="bg-gray-50/80 dark:bg-gray-950/60 backdrop-blur-md border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm dark:shadow-inner">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Konto-Typ</span>
                    <span class="text-gray-900 dark:text-white text-xs font-bold flex items-center gap-1.5">
                        @if(isset(auth()->user()->profile) && auth()->user()->profile->is_business)
                            <x-heroicon-s-briefcase class="w-3.5 h-3.5 text-amber-500" /> Gewerblich
                        @else
                            <x-heroicon-s-user class="w-3.5 h-3.5 text-blue-400" /> Privatkunde
                        @endif
                    </span>
                </div>

                <div x-data="{ copied: false, code: '{{ strtoupper(explode('-', auth()->guard('customer')->id())[0]) }}' }"
                     class="bg-gray-50/80 dark:bg-gray-950/60 backdrop-blur-md border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm dark:shadow-inner cursor-pointer hover:border-gray-300 dark:hover:border-gray-700 transition-colors"
                     @click="navigator.clipboard.writeText(code); copied = true; setTimeout(() => copied = false, 2000)">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Kundennummer</span>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-primary font-mono text-xs font-bold tracking-wider" x-text="code"></span>
                        <x-heroicon-s-clipboard-document x-show="!copied" class="w-3.5 h-3.5 text-gray-500 hover:text-primary transition-colors" />
                        <x-heroicon-s-check x-show="copied" style="display: none;" class="w-3.5 h-3.5 text-emerald-500" />
                    </div>
                </div>

                @if(count($activeGiftVouchers) > 0)
                    <div class="bg-amber-500/10 dark:bg-amber-500/20 backdrop-blur-md border border-amber-500/30 rounded-xl px-3.5 py-2 min-w-[125px] shadow-sm">
                        <span class="text-amber-800 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest block mb-0.5">Guthaben</span>
                        <span class="text-amber-900 dark:text-amber-200 font-mono text-xs font-black">{{ number_format($activeGiftVouchers->sum('current_balance') / 100, 2, ',', '.') }} €</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Aktives Einkaufsguthaben Banner --}}
    {{-- Aktives Einkaufsguthaben Banner --}}
    @if(count($activeGiftVouchers) > 0)
        @php
            $voucherCount = count($activeGiftVouchers);
            $totalBalanceFormatted = number_format($activeGiftVouchers->sum('current_balance') / 100, 2, ',', '.');
        @endphp
        <div class="mb-6 p-4 sm:p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-amber-500/30 rounded-2xl sm:rounded-3xl shadow-sm dark:shadow-xl">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div class="max-w-md">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-800 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest">Verfügbares Einkaufsguthaben</span>
                        @if($voucherCount > 1)
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-900 dark:text-amber-200">
                                {{ $voucherCount }} Codes
                            </span>
                        @endif
                    </div>
                    <h3 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                        {{ $totalBalanceFormatted }} € Guthaben bereit zum Einlösen
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-0.5">
                        Einfach im Warenkorb oder Checkout eingeben, um den Betrag abzuziehen.
                    </p>
                </div>

                {{-- Scrollbarer Code-Bereich --}}
                <div class="w-full lg:w-auto lg:min-w-[320px] max-w-lg">
                    <div class="max-h-36 overflow-y-auto pr-1.5 space-y-1.5 overscroll-contain"
                         style="scrollbar-width: thin; scrollbar-color: rgba(245, 158, 11, 0.4) transparent;">
                        @foreach($activeGiftVouchers as $gv)
                            <div x-data="{ copied: false }" class="bg-white dark:bg-gray-900 border border-amber-300/80 rounded-xl px-3 py-2 flex items-center justify-between gap-3 text-xs shadow-sm hover:border-amber-400 transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="font-mono font-bold text-gray-900 dark:text-white tracking-wider truncate">{{ $gv->code }}</span>
                                    <span class="text-amber-600 dark:text-amber-400 font-bold shrink-0">({{ number_format($gv->current_balance / 100, 2, ',', '.') }} €)</span>
                                </div>
                                <button @click="navigator.clipboard.writeText('{{ $gv->code }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                        type="button" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-bold text-[11px] cursor-pointer shrink-0 ml-1">
                                    <span x-show="!copied">Kopieren</span>
                                    <span x-show="copied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Obere Zeile: Letzte Bestellungen (2/3) & Profildaten (1/3) nebeneinander --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch mb-6">
        {{-- Letzte Bestellungen (2/3 width on desktop) --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden h-full flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">Letzte Bestellungen</h3>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">Deine jüngsten Einkäufe auf einen Blick.</p>
                        </div>
                        <a href="{{ route('customer.orders') }}" class="text-xs font-black uppercase tracking-widest text-primary hover:opacity-80 transition-colors flex items-center gap-1.5">
                            Alle ansehen <x-heroicon-m-chevron-right class="w-4 h-4" />
                        </a>
                    </div>

                    @if(count($recentOrders) > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[540px]">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-800/60 pb-2.5">
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-4 pr-3">Bestellung</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Datum</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Betrag</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Status</th>
                                        <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-3 pr-4 sm:pr-5 text-right">Aktion</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800/40">
                                    @foreach($recentOrders as $order)
                                        <tr class="group hover:bg-gray-50/80 dark:hover:bg-gray-800/20 transition-colors rounded-xl">
                                            <td class="py-3 pl-4 pr-3 rounded-l-xl">
                                                <span class="text-gray-900 dark:text-white font-mono text-xs font-bold">#{{ $order->order_number }}</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="text-gray-600 dark:text-gray-400 text-xs">{{ $order->created_at->format('d.m.Y') }}</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                <span class="text-primary font-mono text-xs font-bold">{{ number_format($order->total_price / 100, 2, ',', '.') }} €</span>
                                            </td>
                                            <td class="py-3 px-3">
                                                @php
                                                    $statusClass = match($order->status) {
                                                        'pending' => 'bg-yellow-500/10 border-yellow-500/20 text-yellow-600 dark:text-yellow-500',
                                                        'processing' => 'bg-blue-500/10 border-blue-500/20 text-blue-600 dark:text-blue-400',
                                                        'shipped' => 'bg-purple-500/10 border-purple-500/20 text-purple-600 dark:text-purple-400',
                                                        'completed' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400',
                                                        'cancelled' => 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-500',
                                                        'refunded' => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400',
                                                        default => 'bg-gray-500/10 border-gray-500/20 text-gray-600 dark:text-gray-400',
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
                                            <td class="py-3 pl-3 pr-4 sm:pr-5 text-right rounded-r-xl">
                                                <a href="{{ route('customer.orders', ['order' => $order->id]) }}" title="Bestellung ansehen" class="inline-flex items-center justify-center p-1.5 bg-gray-100 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-lg text-gray-500 dark:text-gray-400 hover:text-primary hover:border-primary/30 transition-colors">
                                                    <x-heroicon-m-eye class="w-4 h-4" />
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-6 bg-gray-50 dark:bg-gray-950/40 rounded-2xl border border-gray-200 dark:border-gray-800/40 shadow-sm dark:shadow-inner">
                            <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 dark:text-gray-600 mx-auto mb-2" />
                            <h4 class="text-gray-900 dark:text-white font-bold text-sm">Noch keine Bestellungen</h4>
                            <p class="text-gray-500 text-xs mt-0.5 mb-3">Du hast bisher noch keine Einkäufe getätigt.</p>
                            <a href="{{ route('shop') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-primary text-gray-900 rounded-xl text-xs font-black uppercase tracking-widest hover:scale-105 transition-all shadow-sm">
                                Zum Shop <x-heroicon-m-shopping-cart class="w-4 h-4" />
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Profildaten (1/3 width on desktop) --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden h-full flex flex-col justify-between">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
                <div>
                    <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white mb-3.5 flex items-center gap-2">
                        <x-heroicon-s-identification class="w-5 h-5 text-primary" /> Profildaten
                    </h3>

                    <div class="space-y-2.5">
                        {{-- Personal Data Summary --}}
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Name & E-Mail</span>
                            <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                            <span class="text-gray-600 dark:text-gray-400 text-xs font-medium block mt-0.5 truncate">{{ auth()->user()->email }}</span>
                        </div>

                        {{-- Address Summary --}}
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Liefer- & Rechnungsadresse</span>
                            @if(isset(auth()->user()->profile) && !empty(auth()->user()->profile->street))
                                <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5 truncate">
                                    {{ auth()->user()->profile->street }} {{ auth()->user()->profile->house_number }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400 text-xs font-medium block mt-0.5 truncate">
                                    {{ auth()->user()->profile->postal }} {{ auth()->user()->profile->city }} ({{ auth()->user()->profile->country === 'DE' ? 'Deutschland' : auth()->user()->profile->country }})
                                </span>
                            @else
                                <span class="text-red-400/80 text-xs font-bold block mt-0.5">Keine Adresse hinterlegt</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3.5">
                    <button @click="$dispatch('open-profile-modal', {tab: 'profile'})" class="w-full py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-950 dark:hover:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 rounded-xl text-xs font-black uppercase tracking-widest transition-colors shadow-sm cursor-pointer">
                        Profil bearbeiten
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Untere Zeile: 3 Kacheln nebeneinander (Freunde werben, Magisches Abenteuer, Hilfe & Support) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">

        {{-- Kachel 1: Freunde werben --}}
        @if($hasCompletedOrder)
            @php
                $dashRefLink = route('shop', ['ref' => $referralCode, 'coupon' => $referralCode]);
                $dashWaMsg = "Hey! 🥰 Schau unbedingt mal bei Seelenfunke vorbei – die fertigen so wunderschöne, personalisierte Unikate und Geschenkideen an! ✨ Mit meinem persönlichen Code " . $referralCode . " sparst du jetzt 10% auf dein eigenes Unikat: " . $dashRefLink;
                $dashWaUrl = 'https://api.whatsapp.com/send?text=' . urlencode($dashWaMsg);
            @endphp
            <div x-data="{
                    linkCopied: false,
                    codeCopied: false,
                    shareUrl: '{{ $dashRefLink }}',
                    couponCode: '{{ $referralCode }}',
                    copyLink() {
                        navigator.clipboard.writeText(this.shareUrl);
                        this.linkCopied = true;
                        setTimeout(() => this.linkCopied = false, 2500);
                    },
                    copyCode() {
                        navigator.clipboard.writeText(this.couponCode);
                        this.codeCopied = true;
                        setTimeout(() => this.codeCopied = false, 2500);
                    }
                 }"
                 class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-primary/10 border-2 border-primary/30 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden flex flex-col justify-between h-full">
                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-lg">🎁</span> Freunde werben
                        </h3>
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                            5 € Guthaben
                        </span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                        Freunde erhalten <strong>10 % Rabatt</strong>, du bekommst <strong>5 € Einkaufsguthaben</strong> für jede erfolgreiche Bestellung!
                    </p>

                    {{-- Rabattcode Box --}}
                    <div class="bg-white/80 dark:bg-gray-950/80 rounded-xl p-2.5 border border-amber-200/60 dark:border-gray-800 flex items-center justify-between text-xs mb-3 shadow-sm">
                        <div class="min-w-0">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-400 block">Dein Rabattcode</span>
                            <span class="font-mono font-bold text-primary truncate block">{{ $referralCode }}</span>
                        </div>
                        <button @click="copyCode()" type="button" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white font-bold text-[11px] cursor-pointer shrink-0 ml-2">
                            <span x-show="!codeCopied">Code kopieren</span>
                            <span x-show="codeCopied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                        </button>
                    </div>

                    @if($successfulReferralsCount > 0)
                        <div class="mb-3 px-3 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between text-[11px]">
                            <span class="font-bold text-emerald-800 dark:text-emerald-300">🎉 {{ $successfulReferralsCount }} {{ $successfulReferralsCount === 1 ? 'Freund' : 'Freunde' }} geworben</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">+{{ number_format($totalReferralEarnedCents / 100, 2, ',', '.') }} €</span>
                        </div>
                    @endif
                </div>

                {{-- Action Buttons --}}
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <a href="{{ $dashWaUrl }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       style="background-color: #25D366 !important; color: #ffffff !important;"
                       onmouseover="this.style.backgroundColor='#20bd5a'"
                       onmouseout="this.style.backgroundColor='#25D366'"
                       class="py-2 px-3 rounded-xl font-bold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg style="width: 14px; height: 14px; fill: #ffffff !important;" class="shrink-0" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span style="color: #ffffff !important; font-weight: 700;">WhatsApp</span>
                    </a>
                    <button @click="copyLink()"
                            type="button"
                            class="py-2 px-3 rounded-xl bg-white dark:bg-gray-950 hover:bg-gray-50 dark:hover:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-800 dark:text-gray-200 font-bold text-xs shadow-sm transition flex items-center justify-center gap-1.5 cursor-pointer">
                        <span x-show="!linkCopied">Link kopieren</span>
                        <span x-show="linkCopied" style="display: none;" class="text-emerald-500 font-bold">Kopiert! ✓</span>
                    </button>
                </div>
            </div>
        @else
            {{-- Teaser für Neuregistrierte Kunden vor dem ersten Einkauf --}}
            <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-primary/10 border border-dashed border-primary/40 rounded-3xl p-5 sm:p-6 shadow-sm relative overflow-hidden flex flex-col justify-between h-full">
                <div>
                    <div class="flex items-center justify-between mb-2.5">
                        <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <span class="text-lg">🎁</span> Freunde werben
                        </h3>
                        <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                            5 € Guthaben
                        </span>
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-4">
                        Schalte <strong>5 € Einkaufsguthaben</strong> frei! Nach deiner ersten Bestellung kannst du deinen persönlichen 10 % Rabattcode mit deinen Liebsten teilen.
                    </p>
                </div>
                <a href="{{ route('shop') }}" class="w-full py-2.5 px-4 bg-primary hover:bg-primary/90 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                    <span>Jetzt im Shop stöbern</span>
                    <x-heroicon-m-arrow-right class="w-4 h-4" />
                </a>
            </div>
        @endif

        {{-- Kachel 2: Magisches Abenteuer (Spiele) --}}
        <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden group hover:border-emerald-500/40 transition-colors flex flex-col justify-between h-full">
            <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-500/5 rounded-full blur-2xl pointer-events-none group-hover:bg-emerald-500/10 transition-colors"></div>
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span class="text-lg">🎮</span> Magisches Abenteuer
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                        {{ $isGameModeActive ? 'Aktiviert' : 'Interaktiv' }}
                    </span>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                    Minispiele spielen, Seelenfunken sammeln, Level aufsteigen und exklusive Einkaufsgutscheine freischalten.
                </p>
                <div class="space-y-1.5 text-[11px] text-gray-500 dark:text-gray-400 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-500 font-bold">✦</span>
                        <span>Täglich neue Spielrunden & Highscores</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-emerald-500 font-bold">✦</span>
                        <span>Belohnungen & Rabatt-Meilensteine</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('customer.games') }}" class="block text-center py-2.5 bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-105 transition-all shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                {{ $isGameModeActive ? 'Zu den Spielen' : 'Jetzt entdecken' }}
            </a>
        </div>

        {{-- Kachel 3: Hilfe & Support Box --}}
        <div class="bg-white dark:bg-gray-900/40 backdrop-blur-md border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-xl relative overflow-hidden group hover:border-amber-500/40 transition-colors flex flex-col justify-between h-full">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-full blur-2xl pointer-events-none group-hover:bg-amber-500/10 transition-colors"></div>
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-lifebuoy class="w-5 h-5 text-amber-500" /> Hilfe & Support
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-full">
                        &lt; 24 Std.
                    </span>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-xs leading-relaxed mb-3">
                    Hast du Fragen zu deiner Bestellung, möchtest eine Änderung vornehmen oder benötigst Hilfe?
                </p>
                <div class="space-y-1.5 text-[11px] text-gray-500 dark:text-gray-400 mb-4">
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-clock class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                        <span>Antwortzeit: Meist unter 24 Stunden</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-s-envelope class="w-3.5 h-3.5 text-amber-500 shrink-0" />
                        <span>Direkter Support durch unser Manufaktur-Team</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('customer.support') }}" class="block text-center py-2.5 bg-amber-500 hover:bg-amber-400 text-gray-950 font-black uppercase tracking-widest text-xs rounded-xl hover:scale-105 transition-all shadow-[0_0_15px_rgba(245,158,11,0.2)]">
                Ticket erstellen
            </a>
        </div>

    </div>
</div>
