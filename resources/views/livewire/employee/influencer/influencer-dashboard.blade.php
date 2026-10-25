<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;" class="animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300 min-h-screen">
    <div class="space-y-8 pb-20">

        {{-- HERO HEADER & 1-CLICK SHARE BAR --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl relative overflow-hidden">
            <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-[var(--theme-color)] via-[var(--theme-color-50)] to-transparent"></div>

            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm shrink-0">
                        <x-heroicon-o-sparkles class="w-8 h-8" />
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-[var(--theme-color)]">
                            Seelenfunke Creator Cockpit &bull; {{ $influencer->handle }}
                        </span>
                        <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                            Hallo {{ $influencer->employee?->first_name }} ✨
                        </h1>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Deine Provision: <strong class="text-gray-900 dark:text-white">{{ $influencer->commission_value }} %</strong> auf jeden vermittelten Verkauf (über Link oder Code).
                        </p>
                    </div>
                </div>

                {{-- SOFORT-KOPIER-LEISTE FÜR BIO LINK & CODE --}}
                <div class="flex flex-wrap items-center gap-3">
                    <div x-data="{ copied: false }" class="p-3.5 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center gap-3">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 block">Dein Haupt-Kurzlink</span>
                            <span class="text-xs font-mono font-bold text-[var(--theme-color)]">{{ $mainShortUrl }}</span>
                        </div>
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($mainShortUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="px-3.5 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-widest">
                            <span x-show="!copied">Kopieren</span>
                            <span x-show="copied" x-cloak>Kopiert ✓</span>
                        </button>
                    </div>

                    @if($influencer->defaultVoucher)
                        <div x-data="{ copiedCode: false }" class="p-3.5 rounded-2xl bg-emerald-50/70 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center gap-3">
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-400 block">
                                    Community-Code ({{ $influencer->follower_discount_percent }}% Rabatt)
                                </span>
                                <span class="text-xs font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ $influencer->defaultVoucher->code }}</span>
                            </div>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($influencer->defaultVoucher->code)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                    class="px-3.5 py-2 rounded-xl bg-emerald-500 text-gray-950 text-[10px] font-black uppercase tracking-widest">
                                <span x-show="!copiedCode">Code kopieren</span>
                                <span x-show="copiedCode" x-cloak>Kopiert ✓</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (session()->has('creator_success'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-400 text-xs font-black uppercase tracking-widest flex items-center justify-between shadow-sm">
                <span>{{ session('creator_success') }}</span>
                <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
            </div>
        @endif

        @if (session()->has('creator_error'))
            <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 text-red-700 dark:text-red-400 text-xs font-black uppercase tracking-widest flex items-center justify-between shadow-sm">
                <span>{{ session('creator_error') }}</span>
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 shrink-0" />
            </div>
        @endif

        {{-- 4 KPI CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Link-Klicks (Gesamt)</p>
                    <h3 class="text-3xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($totalClicks, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-[var(--theme-color)] mt-1">{{ number_format($totalUniqueClicks, 0, ',', '.') }} Unique Besucher</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)]">
                    <x-heroicon-o-cursor-arrow-rays class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Vermittelte Verkäufe</p>
                    <h3 class="text-3xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($salesCount, 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-purple-500 mt-1">{{ number_format($conversionRate, 1, ',', '.') }} % Conversion-Rate</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-500">
                    <x-heroicon-o-shopping-bag class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Vorgemerkte Provision</p>
                    <h3 class="text-3xl font-serif font-bold text-amber-600 dark:text-amber-400">{{ number_format($pendingCents / 100, 2, ',', '.') }} €</h3>
                    <p class="text-[10px] font-mono text-gray-500 mt-1">Wird bei Versand freigegeben</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                    <x-heroicon-o-clock class="w-6 h-6" />
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-emerald-500/30 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400 mb-1">Auszahlbar (Freigegeben)</p>
                    <h3 class="text-3xl font-serif font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($approvedCents / 100, 2, ',', '.') }} €</h3>
                    <p class="text-[10px] font-mono text-gray-500 mt-1">Bereits ausgezahlt: {{ number_format($paidCents / 100, 2, ',', '.') }} €</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500">
                    <x-heroicon-o-banknotes class="w-6 h-6" />
                </div>
            </div>
        </div>

        {{-- NAVIGATION TABS --}}
        <div class="flex flex-wrap items-center gap-2 bg-white dark:bg-gray-900/80 p-2 rounded-2xl border border-gray-200 dark:border-gray-800 w-fit">
            <button type="button" wire:click="$set('activeTab', 'overview')"
                    class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $activeTab === 'overview' ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Links & Live-Verkäufe
            </button>
            <button type="button" wire:click="$set('activeTab', 'payouts')"
                    class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $activeTab === 'payouts' ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Auszahlung & Gutschriften ({{ $influencer->payouts->count() }})
            </button>
            <button type="button" wire:click="$set('activeTab', 'settings')"
                    class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all {{ $activeTab === 'settings' ? 'bg-[var(--theme-color)] text-gray-950 shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-white' }}">
                Steuer- & Bankdaten {{ $influencer->isReadyForPayout() ? '✓' : '!' }}
            </button>
        </div>

        @if($activeTab === 'overview')
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {{-- LINKS: DEEP-LINK GENERATOR & QR CODE --}}
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 space-y-4">
                        <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg">Produkt-Link erstellen</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Erstelle dir mit 1 Klick eigene Kurzlinks für bestimmte Produkte oder Stories, um genau zu messen, was am besten performt.
                        </p>

                        <form wire:submit.prevent="createProductTrackingLink" class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Bezeichnung (z.B. Story Muttertag)</label>
                                <input type="text" wire:model="newLinkTitle" placeholder="Instagram Reel Kristall"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                @error('newLinkTitle') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Ziel im Shop</label>
                                <select wire:model="newLinkTargetProductSlug"
                                        class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                    <option value="">Gesamter Shop (/shop)</option>
                                    @foreach($activeProducts as $prod)
                                        <option value="{{ $prod->slug }}">Produkt: {{ $prod->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit" class="w-full py-3 rounded-xl bg-[var(--theme-color)] text-gray-950 font-black text-[10px] uppercase tracking-widest">
                                + Eigenen Kurzlink generieren
                            </button>
                        </form>
                    </div>

                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 text-center space-y-3">
                        <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block">Dein persönlicher QR-Code</span>
                        <div class="bg-white p-4 rounded-2xl inline-block border border-gray-200">
                            {!! $qrCodeSvg !!}
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Perfekt für Story-Screenshots, Visitenkarten oder Unboxing-Beilagen.</p>
                    </div>
                </div>

                {{-- RECHTS: DEINE TRACKING LINKS & LIVE SALES CHRONIK --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 space-y-4">
                        <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg">Deine Tracking-Links (/t/...)</h2>
                        <div class="space-y-3">
                            @foreach($influencer->trackingLinks as $tLink)
                                <div x-data="{ copied: false }" class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-sm text-gray-900 dark:text-white">{{ $tLink->name }}</span>
                                            <span class="text-[10px] font-mono text-gray-400">&rarr; {{ $tLink->target_url }}</span>
                                        </div>
                                        <span class="text-xs font-mono font-bold text-[var(--theme-color)] block mt-0.5">{{ $tLink->short_url }}</span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="text-right">
                                            <span class="text-sm font-serif font-bold text-gray-900 dark:text-white block">{{ number_format($tLink->clicks_count, 0, ',', '.') }} Klicks</span>
                                            <span class="text-[10px] text-emerald-500">{{ number_format($tLink->unique_clicks_count, 0, ',', '.') }} Unique</span>
                                        </div>
                                        <button type="button"
                                                @click="navigator.clipboard.writeText(@js($tLink->short_url)); copied = true; setTimeout(() => copied = false, 2000)"
                                                class="px-3.5 py-2 rounded-xl bg-[var(--theme-color)] text-gray-950 text-[10px] font-black uppercase tracking-widest">
                                            <span x-show="!copied">Kopieren</span>
                                            <span x-show="copied" x-cloak>Kopiert ✓</span>
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 space-y-4">
                        <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg">Vermittelte Bestellungen (Live-Chronik)</h2>
                        <div class="space-y-3">
                            @forelse($influencer->commissions->sortByDesc('created_at') as $comm)
                                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-xs text-gray-900 dark:text-white">
                                                {{ substr($comm->order_number, 0, 9) . '•••' . substr($comm->order_number, -2) }}
                                            </span>
                                            <span class="text-[10px] text-gray-400">{{ $comm->created_at?->format('d.m.Y H:i') }} Uhr</span>
                                            @if($comm->status === 'paid')
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 text-[9px] font-black uppercase">Ausgezahlt</span>
                                            @elseif($comm->status === 'approved')
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-500 text-[9px] font-black uppercase">Freigegeben</span>
                                            @elseif($comm->status === 'cancelled')
                                                <span class="px-2 py-0.5 rounded bg-red-500/10 text-red-500 text-[9px] font-black uppercase">Storniert</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-amber-500/10 text-amber-500 text-[9px] font-black uppercase">Vorgemerkt</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-500 mt-0.5">
                                            Netto-Warenwert: {{ number_format($comm->order_net_amount_cents / 100, 2, ',', '.') }} €
                                            &bull; Erfasst über: {{ $comm->attribution_source === 'both' ? 'Link & Code' : ($comm->attribution_source === 'coupon' ? 'Rabattcode ' . $comm->voucher_code : 'Tracking-Link') }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-lg font-serif font-bold {{ $comm->status === 'cancelled' ? 'line-through text-gray-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                            + {{ number_format($comm->commission_amount_cents / 100, 2, ',', '.') }} €
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500 text-center py-8">Sobald über deinen Link oder Code bestellt wird, erscheinen deine Provisionen hier in Echtzeit.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @elseif($activeTab === 'payouts')
            {{-- TAB 2: AUSZAHLUNG ANFORDERN & GUTSCHRIFTEN --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 space-y-5">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">Gutschriftsverfahren (§ 14 Abs. 2 S. 2 UStG)</span>
                            <h2 class="font-serif font-bold text-gray-900 dark:text-white text-xl mt-1">Auszahlung anfordern</h2>
                            <p class="text-xs text-gray-500 mt-1">
                                Du musst keine eigene Rechnung schreiben! Unser System erstellt dir automatisch eine finanzamtskonforme Provisions-Gutschrift als PDF.
                            </p>
                        </div>

                        <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 block">Aktuell auszahlbar (Netto):</span>
                            <span class="text-3xl font-serif font-bold text-emerald-600 dark:text-emerald-400 block mt-1">
                                {{ number_format($approvedCents / 100, 2, ',', '.') }} €
                            </span>
                        </div>

                        @if($approvedCents > 0)
                            <div class="space-y-3">
                                <button type="button" wire:click="requestPayoutNow('{{ $influencer->payout_method }}')"
                                        class="w-full py-3.5 px-4 rounded-2xl bg-[var(--theme-color)] text-gray-950 font-black text-xs uppercase tracking-widest shadow-md">
                                    Als {{ $influencer->payout_method === 'paypal' ? 'PayPal' : 'Banküberweisung' }} auszahlen ({{ number_format($approvedCents / 100, 2, ',', '.') }} €)
                                </button>

                                <button type="button" wire:click="requestPayoutNow('shop_voucher')"
                                        class="w-full py-3.5 px-4 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-gray-950 font-black text-xs uppercase tracking-widest shadow-md">
                                    🎁 Als Seelenfunke Shop-Gutschein (+20 % Bonus = {{ number_format(($approvedCents * 1.2) / 100, 2, ',', '.') }} €!)
                                </button>
                            </div>
                        @else
                            <p class="text-xs text-gray-500">Sobald Bestellungen versendet und freigegeben sind, kannst du hier mit 1 Klick deine Auszahlung abrufen.</p>
                        @endif
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 space-y-4">
                        <h2 class="font-serif font-bold text-gray-900 dark:text-white text-lg">Deine Provisions-Gutschriften (PDF-Belege)</h2>
                        <div class="space-y-3">
                            @forelse($influencer->payouts->sortByDesc('created_at') as $payout)
                                <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-sm text-gray-900 dark:text-white">{{ $payout->credit_note_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase {{ $payout->status === 'paid' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500' }}">
                                                {{ $payout->status === 'paid' ? 'Ausgezahlt' : 'In Bearbeitung' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Auszahlungsbetrag: <strong>{{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €</strong>
                                            @if($payout->giftVoucher)
                                                &bull; Dein Gutscheincode: <strong class="font-mono text-emerald-500">{{ $payout->giftVoucher->code }}</strong>
                                            @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('employee.influencer.payout.pdf', $payout->id) }}" target="_blank"
                                       class="px-4 py-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 hover:border-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest text-gray-800 dark:text-gray-200">
                                        PDF-Gutschrift herunterladen
                                    </a>
                                </div>
                            @empty
                                <p class="text-xs text-gray-500 text-center py-8">Noch keine Gutschriften erstellt.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- TAB 3: STEUER- & BANKDATEN --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 max-w-3xl">
                <h2 class="font-serif font-bold text-gray-900 dark:text-white text-xl mb-1">Steuerliche Pflichtangaben & Auszahlungskonto</h2>
                <p class="text-xs text-gray-500 mb-6">
                    Diese Angaben werden für deine automatischen Provisions-Gutschriften (§ 14 Abs. 2 S. 2 UStG) benötigt und verschlüsselt gespeichert.
                </p>

                <form wire:submit.prevent="savePayoutAndTaxSettings" class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Firmenname / Künstlername (optional)</label>
                        <input type="text" wire:model="companyName" placeholder="Optional"
                               class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Straße *</label>
                            <input type="text" wire:model="street"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                            @error('street') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Hausnummer *</label>
                            <input type="text" wire:model="houseNumber"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                            @error('houseNumber') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">PLZ *</label>
                            <input type="text" wire:model="postal"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                            @error('postal') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Ort *</label>
                            <input type="text" wire:model="city"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                            @error('city') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-100 dark:border-gray-800">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Umsatzsteuer-Status *</label>
                            <select wire:model.live="taxStatus"
                                    class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                <option value="small_business">Kleinunternehmer (§ 19 UStG) – 0 % USt.</option>
                                <option value="standard_tax">Regelbesteuert – zzgl. 19 % USt.</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Steuernummer oder USt-IdNr. *</label>
                            <input type="text" wire:model="taxNumber" placeholder="z.B. 19/123/45678 oder DE123456789"
                                   class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                            @error('taxNumber') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Bevorzugter Auszahlungsweg *</label>
                            <select wire:model.live="payoutMethod"
                                    class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                <option value="bank_transfer">SEPA-Banküberweisung (IBAN)</option>
                                <option value="paypal">PayPal</option>
                                <option value="shop_voucher">Seelenfunke Shop-Wertgutschein (+20 % Bonus!)</option>
                            </select>
                        </div>

                        @if($payoutMethod === 'bank_transfer')
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">Kontoinhaber *</label>
                                    <input type="text" wire:model="accountHolder"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                    @error('accountHolder') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">IBAN (wird verschlüsselt) *</label>
                                    <input type="text" wire:model="iban" placeholder="DE00 0000 0000 0000 0000 00"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                    @error('iban') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @elseif($payoutMethod === 'paypal')
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5">PayPal E-Mail-Adresse *</label>
                                <input type="email" wire:model="paypalEmail" placeholder="name@paypal.de"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-2.5 text-sm font-mono font-bold text-gray-900 dark:text-white outline-none focus:border-[var(--theme-color)]">
                                @error('paypalEmail') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" wire:model="selfEmployedConfirmed" class="mt-1 rounded border-gray-300 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                            <span class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                                Ich bestätige verbindlich, dass ich als selbstständiger Kooperationspartner auf eigene Rechnung handle, kein Arbeitsverhältnis besteht und ich für die Versteuerung meiner Einnahmen selbst verantwortlich bin.
                            </span>
                        </label>
                        @error('selfEmployedConfirmed') <span class="text-red-500 text-[10px] font-bold mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="px-8 py-3.5 rounded-2xl bg-[var(--theme-color)] text-gray-950 font-black text-xs uppercase tracking-widest shadow-md">
                        Steuer- & Auszahlungsdaten speichern
                    </button>
                </form>
            </div>
        @endif

    </div>
</div>
