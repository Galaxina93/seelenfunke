@php
    $formattedTaxRate = rtrim(rtrim(number_format((float) ($defaultTaxRate ?? shop_setting('default_tax_rate', 19.0)), 2, ',', '.'), '0'), ',');
@endphp
<div x-data="{ showSettingsModal: @js($activeTab === 'settings' || $errors->any()), subTab: 'orders' }"
     class="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6 lg:py-7 animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300">

    {{-- Alerts & Profile Completion Banner --}}
    @if(!$influencer->isReadyForPayout())
        <div class="mb-6 p-4 bg-amber-500/5 border border-amber-500/30 rounded-2xl sm:rounded-3xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-amber-500/15 flex items-center justify-center text-amber-600 dark:text-amber-400 shrink-0">
                    <x-heroicon-s-exclamation-triangle class="w-5 h-5" />
                </div>
                <div>
                    <h4 class="text-gray-900 dark:text-white font-bold text-sm">Steuer- & Auszahlungsprofil unvollständig</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-xs mt-0.5">Bitte vervollständige deine Anschrift, Steuernummer und Bankverbindung für automatische Gutschriften (§ 14 Abs. 2 S. 2 UStG).</p>
                </div>
            </div>
            <button type="button" @click="showSettingsModal = true"
                    class="w-full sm:w-auto px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-xs cursor-pointer shrink-0">
                Daten vervollständigen
            </button>
        </div>
    @endif

    @if (session()->has('creator_success'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <span>{{ session('creator_success') }}</span>
            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0" />
        </div>
    @endif

    @if (session()->has('creator_error'))
        <div class="mb-6 p-4 rounded-2xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-300 text-xs font-bold flex items-center justify-between shadow-xs">
            <span>{{ session('creator_error') }}</span>
            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0" />
        </div>
    @endif

    {{-- Welcome & Stats Header Grid (wie Customer Dashboard) --}}
    <div class="relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl sm:rounded-3xl px-5 py-4 sm:px-6 sm:py-5 md:px-7 md:py-5 mb-6 shadow-sm">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-amber-500/5 via-transparent to-transparent pointer-events-none"></div>
        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 sm:gap-6">
            <div class="flex flex-col sm:flex-row items-center sm:items-center gap-4 text-center sm:text-left">
                {{-- Avatar --}}
                <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-amber-50 dark:bg-gray-950 border border-primary/30 overflow-hidden flex items-center justify-center shrink-0 shadow-inner">
                    @if($influencer->employee?->profile && $influencer->employee->profile->photo_path)
                        @php
                            $pp = $influencer->employee->profile->photo_path;
                            $src = (str_starts_with($pp, 'shopverwaltung/images/') || str_starts_with($pp, 'shop/') || str_starts_with($pp, '/'))
                                   ? asset($pp) : Storage::url($pp);
                        @endphp
                        <img src="{{ $src }}" class="w-full h-full object-cover" alt="{{ $influencer->handle }}">
                    @else
                        <span class="text-primary font-serif font-bold text-xl sm:text-2xl flex items-center justify-center h-full uppercase">
                            {{ substr($influencer->employee?->first_name ?? 'C', 0, 1) }}
                        </span>
                    @endif
                </div>

                <div>
                    <span class="text-primary text-[10px] sm:text-xs font-black uppercase tracking-[0.2em] block">
                        Seelenfunke Creator Cockpit &bull; {{ $influencer->handle }}
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mt-0.5 tracking-tight">
                        Hallo, {{ $influencer->employee?->first_name }}
                    </h1>
                    <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5 max-w-lg leading-relaxed">
                        Deine Provision: <strong class="text-gray-900 dark:text-white">{{ $influencer->commission_type === 'fixed_cents' ? number_format($influencer->commission_value / 100, 2, ',', '.') . ' €' : $influencer->commission_value . ' %' }}</strong> auf jeden vermittelten Verkauf &bull; Community-Vorteil: <strong class="text-primary">{{ $influencer->follower_discount_percent }} % Rabatt</strong>
                    </p>
                </div>
            </div>

            {{-- Quick Stats Row --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 shrink-0">
                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2.5 min-w-[120px] shadow-xs">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Link-Klicks</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($totalClicks, 0, ',', '.') }}</span>
                    <span class="text-gray-400 text-[10px] block">{{ number_format($totalUniqueClicks, 0, ',', '.') }} Unique</span>
                </div>

                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2.5 min-w-[120px] shadow-xs">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">Verkäufe</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($salesCount, 0, ',', '.') }}</span>
                    <span class="text-primary text-[10px] font-semibold block">{{ number_format($conversionRate, 1, ',', '.') }} % Rate</span>
                </div>

                <div class="bg-gray-50/80 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800/80 rounded-xl px-3.5 py-2.5 min-w-[120px] shadow-xs">
                    <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block mb-0.5">In Prüfung</span>
                    <span class="text-gray-900 dark:text-white text-sm font-serif font-bold block">{{ number_format($pendingCents / 100, 2, ',', '.') }} €</span>
                    <span class="text-gray-400 text-[10px] block">Vorgemerkt</span>
                </div>

                <div class="bg-amber-500/10 dark:bg-amber-500/15 border border-primary/30 rounded-xl px-3.5 py-2.5 min-w-[130px] shadow-xs">
                    <span class="text-amber-900 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest block mb-0.5">Auszahlbar</span>
                    <span class="text-primary font-serif text-sm font-bold block">{{ number_format($approvedCents / 100, 2, ',', '.') }} €</span>
                    <span class="text-gray-500 dark:text-gray-400 text-[10px] block">Bezahlt: {{ number_format($paidCents / 100, 2, ',', '.') }} €</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Goldener Creator-Banner: Kurzlink, Community-Code & Sofort-Auszahlung (analog zum Guthaben-Banner im Customer Dashboard) --}}
    <div class="mb-6 p-4 sm:p-5 bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border border-primary/30 rounded-2xl sm:rounded-3xl shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="space-y-1 max-w-xl">
                <div class="flex items-center gap-2">
                    <span class="text-amber-900 dark:text-amber-300 text-[9px] font-black uppercase tracking-widest">Deine Creator-Tools & Direkt-Auszahlung</span>
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-primary/15 text-primary-dark border border-primary/25">
                        {{ $influencer->cookie_days }} Tage Cookie-Tracking
                    </span>
                </div>
                <h2 class="text-lg sm:text-xl font-serif font-bold text-gray-900 dark:text-white">
                    Teile deinen Kurzlink oder Code mit deiner Community
                </h2>
                <p class="text-gray-600 dark:text-gray-400 text-xs">
                    Beide Wege sind automatisch mit deinem Creator-Konto verknüpft – egal ob über Link geklickt oder per Rabattcode eingelöst wird.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2.5">
                {{-- Haupt-Kurzlink --}}
                <div x-data="{ copied: false }" class="bg-white dark:bg-gray-900 border border-primary/30 rounded-xl px-3.5 py-2.5 flex items-center justify-between gap-3 text-xs shadow-xs">
                    <div class="min-w-0">
                        <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Dein Haupt-Kurzlink</span>
                        <span class="font-mono font-bold text-gray-900 dark:text-white truncate block">{{ $mainShortUrl }}</span>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText(@js($mainShortUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                            class="px-3 py-1.5 rounded-lg bg-primary hover:bg-primary-dark text-white text-[10px] font-black uppercase tracking-widest transition-colors cursor-pointer shrink-0">
                        <span x-show="!copied">Kopieren</span>
                        <span x-show="copied" x-cloak>Kopiert ✓</span>
                    </button>
                </div>

                {{-- Community-Rabattcode --}}
                @if($influencer->defaultVoucher)
                    <div x-data="{ copiedCode: false }" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 flex items-center justify-between gap-3 text-xs shadow-xs">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Community-Code ({{ $influencer->follower_discount_percent }} %)</span>
                            <span class="font-mono font-bold text-primary block">{{ $influencer->defaultVoucher->code }}</span>
                        </div>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($influencer->defaultVoucher->code)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                class="px-3 py-1.5 rounded-lg bg-gray-900 hover:bg-gray-800 text-white text-[10px] font-black uppercase tracking-widest transition-colors cursor-pointer shrink-0">
                            <span x-show="!copiedCode">Code kopieren</span>
                            <span x-show="copiedCode" x-cloak>Kopiert ✓</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        @php
            $effectiveMinPayoutCents = (int) ($minPayoutCents ?? \App\Services\Marketing\InfluencerService::MIN_PAYOUT_CENTS);
            $canRequestPayoutNow = $approvedCents >= $effectiveMinPayoutCents;
            $missingForPayoutCents = max(0, $effectiveMinPayoutCents - $approvedCents);
            $payoutProgressPercent = $effectiveMinPayoutCents > 0 ? min(100, (int) round(($approvedCents / $effectiveMinPayoutCents) * 100)) : 100;
        @endphp

        @if($approvedCents > 0)
            <div class="mt-4 pt-4 border-t border-primary/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                @if($canRequestPayoutNow)
                    <div class="text-xs text-gray-700 dark:text-gray-300">
                        <strong class="text-gray-900 dark:text-white">{{ number_format($approvedCents / 100, 2, ',', '.') }} €</strong> sind zur sofortigen Auszahlung freigegeben (automatische Gutschrift gem. § 14 Abs. 2 S. 2 UStG).
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="requestPayoutNow('{{ $influencer->payout_method }}')"
                                class="px-4 py-2 rounded-xl bg-gray-900 hover:bg-gray-800 text-white font-black text-[10px] uppercase tracking-widest transition-colors cursor-pointer shadow-xs">
                            Per {{ $influencer->payout_method === 'paypal' ? 'PayPal' : 'SEPA-Bankkonto' }} auszahlen ({{ number_format($approvedCents / 100, 2, ',', '.') }} €)
                        </button>
                        <button type="button" wire:click="requestPayoutNow('shop_voucher')"
                                class="px-4 py-2 rounded-xl bg-primary hover:bg-primary-dark text-white font-black text-[10px] uppercase tracking-widest transition-colors cursor-pointer shadow-xs">
                            🎁 Als Shop-Gutschein (+20 % = {{ number_format(($approvedCents * 1.2) / 100, 2, ',', '.') }} €)
                        </button>
                    </div>
                @else
                    <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="text-xs text-gray-700 dark:text-gray-300">
                            <strong class="text-gray-900 dark:text-white">{{ number_format($approvedCents / 100, 2, ',', '.') }} €</strong> von <strong class="text-gray-900 dark:text-white">{{ number_format($effectiveMinPayoutCents / 100, 2, ',', '.') }} €</strong> Mindestauszahlungsbetrag erreicht
                            <span class="text-gray-500 dark:text-gray-400">(noch {{ number_format($missingForPayoutCents / 100, 2, ',', '.') }} € bis zur Auszahlung)</span>.
                        </div>
                        <div class="w-full sm:w-48 bg-gray-200 dark:bg-gray-800 rounded-full h-2 overflow-hidden shrink-0">
                            <div class="bg-primary h-full rounded-full transition-all" style="width: {{ $payoutProgressPercent }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Obere Hauptzeile: Vermittelte Bestellungen & Gutschriften (2/3) + Steuer- & Auszahlungsprofil (1/3) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch mb-6">
        {{-- Links (2/3): Vermittelte Bestellungen & Gutschriften --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm h-full flex flex-col justify-between">
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">
                                <span x-show="subTab === 'orders'">Vermittelte Bestellungen</span>
                                <span x-show="subTab === 'payouts'" x-cloak>Provisions-Gutschriften (PDF-Belege)</span>
                            </h3>
                            <p class="text-gray-500 dark:text-gray-400 text-xs mt-0.5">
                                <span x-show="subTab === 'orders'">Alle Verkäufe über deine Links & Codes in Echtzeit.</span>
                                <span x-show="subTab === 'payouts'" x-cloak>Deine finanzamtskonformen Gutschriften nach § 14 Abs. 2 S. 2 UStG.</span>
                            </p>
                        </div>

                        <div class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800 self-start">
                            <button type="button" @click="subTab = 'orders'"
                                    :class="subTab === 'orders' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                Bestellungen ({{ $influencer->commissions->count() }})
                            </button>
                            <button type="button" @click="subTab = 'payouts'"
                                    :class="subTab === 'payouts' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all cursor-pointer">
                                Gutschriften ({{ $influencer->payouts->count() }})
                            </button>
                        </div>
                    </div>

                    {{-- Tabelle 1: Vermittelte Bestellungen --}}
                    <div x-show="subTab === 'orders'">
                        @if($influencer->commissions->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse min-w-[520px]">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-800/60 pb-2.5">
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-3 pr-3">Bestellung</th>
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Datum</th>
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Zuordnung</th>
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Netto-Wert</th>
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 px-3">Status</th>
                                            <th class="text-gray-500 text-[9px] font-black uppercase tracking-wider pb-2.5 pl-3 pr-3 text-right">Provision</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/40">
                                        @foreach($influencer->commissions->sortByDesc('created_at')->take(10) as $comm)
                                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-800/20 transition-colors">
                                                <td class="py-3 pl-3 pr-3">
                                                    <span class="text-gray-900 dark:text-white font-mono text-xs font-bold">
                                                        {{ substr($comm->order_number, 0, 9) . '•••' . substr($comm->order_number, -2) }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3">
                                                    <span class="text-gray-600 dark:text-gray-400 text-xs">{{ $comm->created_at?->format('d.m.Y') }}</span>
                                                </td>
                                                <td class="py-3 px-3">
                                                    <span class="text-gray-600 dark:text-gray-400 text-xs">
                                                        {{ $comm->attribution_source === 'both' ? 'Link & Code' : ($comm->attribution_source === 'coupon' ? 'Code ' . $comm->voucher_code : 'Kurzlink') }}
                                                    </span>
                                                </td>
                                                <td class="py-3 px-3">
                                                    <span class="text-gray-700 dark:text-gray-300 font-mono text-xs">{{ number_format($comm->order_net_amount_cents / 100, 2, ',', '.') }} €</span>
                                                </td>
                                                <td class="py-3 px-3">
                                                    @if($comm->status === 'paid')
                                                        <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-500/10 border-emerald-500/20 text-emerald-600">Ausgezahlt</span>
                                                    @elseif($comm->status === 'approved')
                                                        <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-500/10 border-primary/30 text-primary-dark">Freigegeben</span>
                                                    @elseif($comm->status === 'cancelled')
                                                        <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider bg-red-500/10 border-red-500/20 text-red-600">Storniert</span>
                                                    @else
                                                        <span class="inline-flex px-2 py-0.5 border rounded-full text-[9px] font-black uppercase tracking-wider bg-gray-500/10 border-gray-500/20 text-gray-600">In Prüfung</span>
                                                    @endif
                                                </td>
                                                <td class="py-3 pl-3 pr-3 text-right">
                                                    <span class="font-mono text-xs font-bold {{ $comm->status === 'cancelled' ? 'line-through text-gray-400' : 'text-primary' }}">
                                                        + {{ number_format($comm->commission_amount_cents / 100, 2, ',', '.') }} €
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 dark:bg-gray-950/40 rounded-2xl border border-gray-200 dark:border-gray-800/40">
                                <x-heroicon-o-shopping-bag class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                                <h4 class="text-gray-900 dark:text-white font-bold text-sm">Noch keine vermittelten Bestellungen</h4>
                                <p class="text-gray-500 text-xs mt-0.5">Sobald über deinen Kurzlink oder Rabattcode bestellt wird, erscheinen deine Provisionen hier in Echtzeit.</p>
                            </div>
                        @endif
                    </div>

                    {{-- Tabelle 2: Provisions-Gutschriften --}}
                    <div x-show="subTab === 'payouts'" x-cloak>
                        @if($influencer->payouts->count() > 0)
                            <div class="space-y-2.5">
                                @foreach($influencer->payouts->sortByDesc('created_at') as $payout)
                                    <div class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono font-bold text-xs text-gray-900 dark:text-white">{{ $payout->credit_note_number }}</span>
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border {{ $payout->status === 'paid' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600' : 'bg-amber-500/10 border-primary/30 text-primary-dark' }}">
                                                    {{ $payout->status === 'paid' ? 'Ausgezahlt' : 'In Bearbeitung' }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">
                                                Auszahlungsbetrag: <strong class="text-gray-900 dark:text-white">{{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €</strong>
                                                @if($payout->giftVoucher)
                                                    &bull; Gutscheincode: <strong class="font-mono text-primary">{{ $payout->giftVoucher->code }}</strong>
                                                @endif
                                            </p>
                                        </div>
                                        <a href="{{ route('employee.influencer.payout.pdf', $payout->id) }}" target="_blank"
                                           class="inline-flex items-center justify-center px-3.5 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-primary text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200 transition-colors">
                                            PDF-Gutschrift
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 dark:bg-gray-950/40 rounded-2xl border border-gray-200 dark:border-gray-800/40">
                                <x-heroicon-o-document-text class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                                <h4 class="text-gray-900 dark:text-white font-bold text-sm">Noch keine Gutschriften erstellt</h4>
                                <p class="text-gray-500 text-xs mt-0.5">Bei jeder Auszahlung wird automatisch eine steuerkonforme Gutschrift (§ 14 Abs. 2 S. 2 UStG) als PDF generiert.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Rechts (1/3): Steuer- & Auszahlungsdaten (analog zu Profildaten im Customer Dashboard) --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm relative overflow-hidden h-full flex flex-col justify-between">
                <div class="absolute top-0 right-0 w-24 h-24 bg-primary/5 rounded-full blur-2xl pointer-events-none"></div>
                <div>
                    <div class="flex items-center justify-between mb-3.5">
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-s-identification class="w-5 h-5 text-primary" /> Steuer- & Bankdaten
                        </h3>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $influencer->isReadyForPayout() ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-700 border border-amber-500/20' }}">
                            {{ $influencer->isReadyForPayout() ? 'Vollständig ✓' : 'Unvollständig' }}
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Steuer-Status & USt-IdNr.</span>
                            <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5">
                                @if($influencer->tax_status === 'standard_tax')
                                    Regelbesteuert (+{{ $formattedTaxRate }} % USt.)
                                @elseif($influencer->tax_status === 'reverse_charge')
                                    EU Reverse-Charge (0 % USt.)
                                @else
                                    Kleinunternehmer (§ 19 UStG – 0 % USt.)
                                @endif
                            </span>
                            <span class="text-gray-600 dark:text-gray-400 text-xs font-mono block mt-0.5 truncate">
                                {{ $influencer->tax_number ?: 'Keine Steuernr. hinterlegt' }}
                            </span>
                        </div>

                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Gutschrifts-Anschrift</span>
                            @if(!empty($street))
                                <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5 truncate">
                                    {{ $street }} {{ $houseNumber }}
                                </span>
                                <span class="text-gray-600 dark:text-gray-400 text-xs font-medium block mt-0.5 truncate">
                                    {{ $postal }} {{ $city }} ({{ $country }})
                                </span>
                            @else
                                <span class="text-amber-600 text-xs font-bold block mt-0.5">Keine Anschrift hinterlegt</span>
                            @endif
                        </div>

                        <div class="p-2.5 bg-gray-50 dark:bg-gray-950/40 rounded-xl border border-gray-200 dark:border-gray-800/60">
                            <span class="text-gray-500 text-[9px] font-black uppercase tracking-widest block">Auszahlungsweg</span>
                            <span class="text-gray-900 dark:text-white text-xs font-bold block mt-0.5">
                                @if($influencer->payout_method === 'paypal')
                                    PayPal ({{ $influencer->paypal_email ?: 'fehlt' }})
                                @elseif($influencer->payout_method === 'shop_voucher')
                                    Seelenfunke Shop-Gutschein (+20 % Bonus)
                                @else
                                    SEPA ({{ $influencer->masked_iban ?: 'IBAN fehlt' }})
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-3.5">
                    <button type="button" @click="showSettingsModal = !showSettingsModal"
                            class="w-full py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-950 dark:hover:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 hover:border-gray-300 rounded-xl text-xs font-black uppercase tracking-widest transition-colors shadow-xs cursor-pointer">
                        <span x-show="!showSettingsModal">Steuer- & Bankdaten bearbeiten</span>
                        <span x-show="showSettingsModal" x-cloak>Formular schließen</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Aufklappbares Formular für Steuer- & Auszahlungsdaten --}}
    <div x-show="showSettingsModal" x-cloak class="mb-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-7 shadow-sm">
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-gray-100 dark:border-gray-800">
            <div>
                <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg">Steuerliche Pflichtangaben & Auszahlungskonto</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Diese Angaben werden für deine automatischen Provisions-Gutschriften (§ 14 Abs. 2 S. 2 UStG) benötigt und verschlüsselt gespeichert.
                </p>
            </div>
            <button type="button" @click="showSettingsModal = false" class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer">
                <x-heroicon-m-x-mark class="w-5 h-5" />
            </button>
        </div>

        <form wire:submit.prevent="savePayoutAndTaxSettings" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Firmenname / Künstlername (optional)</label>
                    <input type="text" wire:model="companyName" placeholder="Optional"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Straße *</label>
                    <input type="text" wire:model="street"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('street') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Hausnummer *</label>
                    <input type="text" wire:model="houseNumber"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('houseNumber') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">PLZ *</label>
                    <input type="text" wire:model="postal"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('postal') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Ort *</label>
                    <input type="text" wire:model="city"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('city') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Land *</label>
                    <select wire:model="country"
                            class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        <option value="DE">Deutschland</option>
                        <option value="AT">Österreich</option>
                        <option value="CH">Schweiz</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Umsatzsteuer-Status (Shop-MwSt: {{ $formattedTaxRate }} %) *</label>
                    <select wire:model.live="taxStatus"
                            class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        <option value="small_business">Kleinunternehmer (§ 19 UStG) – 0 % USt.</option>
                        <option value="standard_tax">Regelbesteuert – zzgl. {{ $formattedTaxRate }} % USt.</option>
                        <option value="reverse_charge">EU Reverse-Charge – 0 % USt.</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Steuernummer oder USt-IdNr. *</label>
                    <input type="text" wire:model="taxNumber" placeholder="z.B. 19/123/45678 oder DE123456789"
                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('taxNumber') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Bevorzugter Auszahlungsweg *</label>
                    <select wire:model.live="payoutMethod"
                            class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        <option value="bank_transfer">SEPA-Banküberweisung (IBAN)</option>
                        <option value="paypal">PayPal</option>
                        <option value="shop_voucher">Seelenfunke Shop-Wertgutschein (+20 % Bonus)</option>
                    </select>
                </div>
            </div>

            @if($payoutMethod === 'bank_transfer')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">Kontoinhaber *</label>
                        <input type="text" wire:model="accountHolder"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        @error('accountHolder') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">IBAN *</label>
                        <input type="text" wire:model="iban" placeholder="DE00 0000 0000 0000 0000 00"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        @error('iban') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">BIC (optional)</label>
                        <input type="text" wire:model="bic"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    </div>
                </div>
            @elseif($payoutMethod === 'paypal')
                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800">
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 mb-1">PayPal E-Mail-Adresse *</label>
                    <input type="email" wire:model="paypalEmail" placeholder="dein-paypal@beispiel.de"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                    @error('paypalEmail') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="p-4 rounded-2xl bg-amber-50/70 dark:bg-amber-500/10 border border-primary/25">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="selfEmployedConfirmed" class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-xs text-gray-800 dark:text-gray-200 leading-relaxed">
                        <strong>Rechtliche Bestätigung (§ 14 BGB / § 14 Abs. 2 S. 2 UStG):</strong> Ich bestätige, dass ich als selbstständige/r Unternehmer/in bzw. Freiberufler/in auf eigene Rechnung handle, meine Einnahmen und ggf. erhaltenen PR-Samples eigenverantwortlich versteuere und die <a href="{{ route('agb') }}#creator-partnerprogramm" target="_blank" class="font-bold text-primary hover:text-primary-dark underline underline-offset-2">Bedingungen für das Seelenfunke Creator Partnerprogramm (§ 10 AGB)</a> sowie den <a href="{{ route('widerruf') }}#creator-partnerprogramm" target="_blank" class="font-bold text-primary hover:text-primary-dark underline underline-offset-2">B2B-Hinweis zur Widerrufsbelehrung</a> anerkenne. *
                    </span>
                </label>
                @error('selfEmployedConfirmed') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" @click="showSettingsModal = false"
                        class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 font-black text-xs uppercase tracking-widest hover:bg-gray-50 cursor-pointer">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-6 py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-black text-xs uppercase tracking-widest shadow-xs transition-colors cursor-pointer">
                    Steuer- & Auszahlungsdaten speichern
                </button>
            </div>
        </form>
    </div>

    {{-- Untere Zeile: 3 Kacheln nebeneinander (wie im Customer Dashboard) --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-stretch">

        {{-- Kachel 1: Deine Tracking-Links (/t/...) --}}
        <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm flex flex-col justify-between h-full">
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-link class="w-4 h-4 text-primary" /> Deine Kurzlinks
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-2 py-0.5 rounded-full">
                        {{ $influencer->trackingLinks->count() }} Aktiv
                    </span>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-3">
                    Alle aktiven Kurzlinks inkl. Live-Klickzähler.
                </p>

                <div class="space-y-2 max-h-52 overflow-y-auto pr-1">
                    @foreach($influencer->trackingLinks as $tLink)
                        <div x-data="{ copied: false }" class="p-3 rounded-xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-xs font-bold text-gray-900 dark:text-white block truncate">{{ $tLink->name }}</span>
                                <span class="text-[11px] font-mono text-primary block truncate">{{ $tLink->short_url }}</span>
                                <span class="text-[10px] text-gray-400">{{ number_format($tLink->clicks_count, 0, ',', '.') }} Klicks ({{ number_format($tLink->unique_clicks_count, 0, ',', '.') }} Unique)</span>
                            </div>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($tLink->short_url)); copied = true; setTimeout(() => copied = false, 2000)"
                                    class="px-2.5 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-gray-800 dark:text-gray-200 text-[10px] font-black uppercase tracking-wider shrink-0 cursor-pointer">
                                <span x-show="!copied">Kopieren</span>
                                <span x-show="copied" x-cloak class="text-emerald-600">✓</span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Kachel 2: Eigenen Produkt-Link erstellen --}}
        <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm flex flex-col justify-between h-full">
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-sparkles class="w-4 h-4 text-primary" /> Produkt-Link erstellen
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">
                        Deep-Link
                    </span>
                </div>
                <p class="text-gray-500 dark:text-gray-400 text-xs mb-3.5">
                    Erstelle eigene Kurzlinks für bestimmte Produkte, Reels oder Stories.
                </p>

                <form wire:submit.prevent="createProductTrackingLink" class="space-y-3">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1">Bezeichnung (z.B. Story Muttertag)</label>
                        <input type="text" wire:model="newLinkTitle" placeholder="Instagram Reel Kristall"
                               class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                        @error('newLinkTitle') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1">Ziel im Shop</label>
                        <select wire:model="newLinkTargetProductSlug"
                                class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-primary">
                            <option value="">Gesamter Shop (/shop)</option>
                            @foreach($activeProducts as $prod)
                                <option value="{{ $prod->slug }}">Produkt: {{ $prod->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="w-full py-2.5 rounded-xl bg-primary hover:bg-primary-dark text-white font-black text-[10px] uppercase tracking-widest transition-colors shadow-xs cursor-pointer">
                        + Eigenen Kurzlink generieren
                    </button>
                </form>
            </div>
        </div>

        {{-- Kachel 3: Persönlicher QR-Code & Partner-Richtlinien --}}
        <div class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm flex flex-col justify-between h-full">
            <div>
                <div class="flex items-center justify-between mb-2.5">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-s-qr-code class="w-4 h-4 text-primary" /> QR-Code & Rechtliches
                    </h3>
                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">
                        Print & Story
                    </span>
                </div>

                <div class="flex items-center gap-4 my-3 p-3 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800">
                    <div class="bg-white p-2 rounded-xl border border-gray-200 shrink-0 [&>svg]:w-20 [&>svg]:h-20">
                        {!! $qrCodeSvg !!}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                        Scanne oder teile deinen persönlichen QR-Code für Unboxing-Stories oder Visitenkarten. Er leitet direkt auf deinen Kurzlink weiter.
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-2 text-[11px]">
                <a href="{{ route('agb') }}#creator-partnerprogramm" target="_blank" class="font-bold text-primary hover:text-primary-dark underline underline-offset-2">
                    § 10 AGB Partnerprogramm
                </a>
                <a href="{{ route('widerruf') }}#creator-partnerprogramm" target="_blank" class="font-medium text-gray-500 hover:text-gray-800 underline underline-offset-2">
                    Widerrufshinweis (B2B)
                </a>
            </div>
        </div>

    </div>

    {{-- Vollbreiten-Kachel unten: Aktuelle Produkte bewerben (Schlanke Tabellen-Ansicht + Ausklapp-Erklärung) --}}
    @php
        $promotableCollection = collect($promotableProducts ?? []);
        $creatorVoucherCode = $influencer->defaultVoucher?->code ?? ('CODE' . $influencer->follower_discount_percent);
    @endphp
    <div x-data="{ showExplanation: false }" class="bg-white dark:bg-gray-900/40 border border-gray-200 dark:border-gray-800 rounded-3xl p-5 sm:p-6 shadow-sm mt-6">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <x-heroicon-s-shopping-bag class="w-4 h-4 text-primary" /> Aktuelle Produkte bewerben
            </h3>
            <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-2.5 py-0.5 rounded-full">
                {{ $promotableCollection->count() }} Produkte
            </span>
        </div>

        @if($promotableCollection->isEmpty())
            <div class="p-6 rounded-2xl bg-gray-50 dark:bg-gray-950/50 border border-dashed border-gray-200 dark:border-gray-800 text-center">
                <p class="text-xs font-bold text-gray-500 dark:text-gray-400">
                    Aktuell sind keine lagernden Produkte zur Bewerbung freigeschaltet.
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-widest text-gray-400">
                            <th class="py-2.5 pr-4">Produkt</th>
                            <th class="py-2.5 px-4">Kategorie</th>
                            <th class="py-2.5 px-4 text-center">Kann beworben werden mit: Code</th>
                            <th class="py-2.5 pl-4 text-center">Kann beworben werden mit: Kurzlink</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800/70 text-xs">
                        @foreach($promotableCollection as $item)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                {{-- Bild & Produktname --}}
                                <td class="py-3 pr-4">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}"
                                             class="w-10 h-10 rounded-xl object-cover border border-gray-200 dark:border-gray-800 bg-white shrink-0">
                                        <a href="{{ $item['shop_url'] }}" target="_blank"
                                           class="font-bold text-gray-900 dark:text-white hover:text-primary transition-colors line-clamp-1">
                                            {{ $item['name'] }}
                                        </a>
                                    </div>
                                </td>

                                {{-- Kategorie --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        {{ $item['category_name'] }}
                                    </span>
                                </td>

                                {{-- Kann beworben werden mit: Code (Ja/Nein + Copy-Icon) --}}
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    @if($item['is_coupon_compatible'] && $item['follower_discount_percent'] > 0)
                                        <div x-data="{ copiedCode: false }" class="inline-flex items-center justify-center gap-1.5">
                                            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-bold">
                                                Ja
                                            </span>
                                            <button type="button"
                                                    title="Rabattcode {{ $creatorVoucherCode }} kopieren"
                                                    @click="navigator.clipboard.writeText(@js($creatorVoucherCode)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                                    class="w-7 h-7 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-gray-600 dark:text-gray-300 hover:text-primary inline-flex items-center justify-center transition-colors cursor-pointer">
                                                <span x-show="!copiedCode"><x-heroicon-o-clipboard-document class="w-3.5 h-3.5" /></span>
                                                <span x-show="copiedCode" x-cloak><x-heroicon-s-check class="w-3.5 h-3.5 text-emerald-600" /></span>
                                            </button>
                                        </div>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 text-xs font-bold">
                                            Nein
                                        </span>
                                    @endif
                                </td>

                                {{-- Kann beworben werden mit: Kurzlink (Ja + Erstell-Icon / Copy-Icon) --}}
                                <td class="py-3 pl-4 text-center whitespace-nowrap">
                                    <div x-data="{ copiedLink: false }" class="inline-flex items-center justify-center gap-1.5">
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-bold">
                                            Ja
                                        </span>

                                        @if(!empty($item['existing_short_url']))
                                            <button type="button"
                                                    title="Produkt-Kurzlink kopieren ({{ $item['existing_short_url'] }})"
                                                    @click="navigator.clipboard.writeText(@js($item['existing_short_url'])); copiedLink = true; setTimeout(() => copiedLink = false, 2000)"
                                                    class="w-7 h-7 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 hover:border-primary text-primary inline-flex items-center justify-center transition-colors cursor-pointer">
                                                <span x-show="!copiedLink"><x-heroicon-o-clipboard-document class="w-3.5 h-3.5" /></span>
                                                <span x-show="copiedLink" x-cloak><x-heroicon-s-check class="w-3.5 h-3.5 text-emerald-600" /></span>
                                            </button>
                                        @else
                                            <button type="button"
                                                    title="Produkt-Kurzlink mit 1 Klick erstellen"
                                                    wire:click="quickCreateProductLink('{{ $item['slug'] }}')"
                                                    class="w-7 h-7 rounded-lg bg-primary/10 hover:bg-primary text-primary hover:text-white border border-primary/25 inline-flex items-center justify-center transition-colors cursor-pointer">
                                                <x-heroicon-o-plus class="w-3.5 h-3.5" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Unten mittig: Dezenter Button "Erklärung anzeigen" --}}
        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800/80 flex justify-center">
            <button type="button" @click="showExplanation = !showExplanation"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-gray-500 hover:text-primary dark:text-gray-400 dark:hover:text-primary hover:bg-gray-50 dark:hover:bg-gray-900 transition-colors cursor-pointer">
                <x-heroicon-o-information-circle class="w-4 h-4 text-primary" />
                <span x-text="showExplanation ? 'Erklärung ausblenden' : 'Erklärung anzeigen'">Erklärung anzeigen</span>
                <span class="transition-transform duration-200 inline-flex" :class="showExplanation ? 'rotate-180' : ''">
                    <x-heroicon-s-chevron-down class="w-3.5 h-3.5" />
                </span>
            </button>
        </div>

        {{-- Ausklappbare Erklärung (standardmäßig zugeklappt) --}}
        <div x-show="showExplanation" x-cloak x-transition class="mt-4 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950/60 border border-gray-200 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-400 space-y-2.5 leading-relaxed">
            <p class="font-bold text-gray-900 dark:text-white">
                Warum werden genau diese Produkte hier aufgelistet?
            </p>
            <ul class="list-disc pl-4 space-y-1.5">
                <li>
                    <strong>Code = Ja:</strong> Deine Follower erhalten mit deinem Code <strong>{{ $creatorVoucherCode }}</strong> sofort <strong>-{{ $influencer->follower_discount_percent }} % Rabatt</strong> im Warenkorb, und du erhältst deine volle Provision.
                </li>
                <li>
                    <strong>Code = Nein / Kurzlink = Ja:</strong> Bereits reduzierte Feiertags- & Aktions-Unikate sind im Shop nicht mit zusätzlichen Rabattcodes kombinierbar. Wenn du sie über deinen <strong>Kurzlink</strong> bewirbst ({{ $influencer->cookie_days ?: 30 }} Tage Cookie-Tracking), erhältst du bei jedem Verkauf <strong>trotzdem deine volle Provision</strong>!
                </li>
                <li>
                    <strong>Automatischer Bestands-Filter:</strong> Ausverkaufte Artikel sowie Geschenkgutscheine werden automatisch ausgeblendet, damit deine Links immer zu 100 % verfügbar sind.
                </li>
            </ul>

            @if($promotableCollection->isNotEmpty())
                <div class="pt-2 border-t border-gray-200/70 dark:border-gray-800 flex flex-wrap gap-2">
                    @foreach($promotableCollection as $item)
                        <span class="px-2.5 py-1 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-[11px]">
                            {{ $item['name'] }}: <strong class="text-primary">+{{ number_format($item['estimated_commission_cents'] / 100, 2, ',', '.') }} €</strong> Provision
                            @if(!empty($item['existing_short_url']))
                                <span class="text-gray-400 font-mono">({{ $item['existing_short_url'] }})</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
