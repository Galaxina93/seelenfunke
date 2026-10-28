<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;" class="animate-fade-in-up font-sans antialiased text-gray-700 dark:text-gray-300 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8 pb-20">

        {{-- HEADER (Im Marketing Theme-Var Design wie Gutscheine, Blog, Linktree & Tracking) --}}
        <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6 bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl relative overflow-hidden transition-colors">
            <div class="hidden dark:block absolute inset-0 bg-gradient-to-br from-[var(--theme-color-10)] via-transparent to-transparent pointer-events-none"></div>
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-[var(--theme-color)] via-[var(--theme-color-50)] to-transparent"></div>

            <div class="flex items-center gap-5 relative z-10">
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-[0_0_20px_var(--theme-color-20)] shrink-0">
                    <x-heroicon-o-user-group class="w-7 h-7" />
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">
                        Influencer &amp; Affiliate Hub
                    </h1>
                    <p class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mt-1.5">
                        Creator-Einladungen, Kurzlinks (<code class="text-[var(--theme-color)] font-mono">/t/name</code>), Rabattcodes &amp; § 14 UStG Gutschriften
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 relative z-10">
                <div class="flex items-center gap-1.5 bg-gray-50 dark:bg-gray-950 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-inner">
                    <button type="button" wire:click="$set('activeTab', 'influencers')"
                            class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $activeTab === 'influencers' ? 'bg-[var(--theme-color)] text-gray-900 dark:text-gray-950 shadow-sm dark:shadow-[0_0_15px_var(--theme-color-30)]' : 'text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)]' }}">
                        Creator &amp; Einladungen ({{ $stats['active_count'] + $stats['pending_invitations'] }})
                    </button>
                    <button type="button" wire:click="$set('activeTab', 'payouts')"
                            class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer {{ $activeTab === 'payouts' ? 'bg-[var(--theme-color)] text-gray-900 dark:text-gray-950 shadow-sm dark:shadow-[0_0_15px_var(--theme-color-30)]' : 'text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)]' }}">
                        Auszahlungen &amp; Gutschriften ({{ $payouts->count() }})
                    </button>
                </div>

                <button type="button" wire:click="createInvitation"
                        class="inline-flex items-center gap-2.5 px-6 py-3 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 hover:scale-[1.02] text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] cursor-pointer">
                    <x-heroicon-m-plus class="w-4 h-4 stroke-2" />
                    Influencer einladen
                </button>
            </div>
        </div>

        {{-- KPI CARDS (Mit Theme-Var Akzenten analog zu Linktree & Tracking) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- KPI 1: Aktive Creator & Einladungen --}}
            <div class="relative bg-white dark:bg-gradient-to-br dark:from-[var(--theme-color-15)] dark:via-gray-900/90 dark:to-gray-900 border border-gray-200 dark:border-[var(--theme-color-30)] rounded-[2rem] p-6 shadow-sm dark:shadow-[0_0_30px_var(--theme-color-10)] flex items-center justify-between overflow-hidden group transition-colors">
                <div class="hidden dark:block absolute -right-4 -bottom-4 w-24 h-24 bg-[var(--theme-color-10)] rounded-full blur-xl pointer-events-none"></div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-1">Aktive Creator</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ $stats['active_count'] }}</h3>
                    <p class="text-[10px] font-mono text-gray-500 dark:text-gray-400 mt-1.5">
                        {{ $stats['pending_invitations'] > 0 ? $stats['pending_invitations'] . ' Einladung(en) offen' : 'Alle Einladungen aktiv' }}
                    </p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-user-group class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 2: Bio-Link Klicks --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Bio-Link Klicks</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_clicks'], 0, ',', '.') }}</h3>
                    <p class="text-[10px] font-mono text-[var(--theme-color)] mt-1.5">{{ number_format($stats['total_unique_clicks'], 0, ',', '.') }} Unique Besucher</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-cursor-arrow-rays class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 3: Vermittelter Netto-Umsatz --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Netto-Umsatz ({{ $stats['total_orders'] }} Sales)</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_revenue_cents'] / 100, 2, ',', '.') }} €</h3>
                    <p class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-1.5">Dual-Attribution (Link + Code)</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 dark:text-emerald-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-shopping-bag class="w-7 h-7" />
                </div>
            </div>

            {{-- KPI 4: Offene Provisionen --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl p-6 rounded-[2rem] border border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-2xl flex items-center justify-between relative overflow-hidden group transition-colors">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Offene Provisionen</p>
                    <h3 class="text-3xl sm:text-4xl font-serif font-bold text-gray-900 dark:text-white">{{ number_format($stats['open_commission_cents'] / 100, 2, ',', '.') }} €</h3>
                    <p class="text-[10px] font-mono text-amber-600 dark:text-amber-400 mt-1.5">Noch nicht ausgezahlt</p>
                </div>
                <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 dark:text-amber-400 flex items-center justify-center shadow-sm dark:shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                    <x-heroicon-o-banknotes class="w-7 h-7" />
                </div>
            </div>
        </div>

        {{-- FLASH MESSAGES --}}
        @if (session()->has('influencer_saved'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center justify-between shadow-sm dark:shadow-inner">
                <span>{{ session('influencer_saved') }}</span>
                <x-heroicon-s-check-circle class="w-5 h-5 shrink-0" />
            </div>
        @endif

        @if (session()->has('influencer_error'))
            <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 text-red-700 dark:text-red-400 text-[10px] font-black uppercase tracking-widest flex items-center justify-between shadow-sm dark:shadow-inner">
                <span>{{ session('influencer_error') }}</span>
                <x-heroicon-s-exclamation-triangle class="w-5 h-5 shrink-0" />
            </div>
        @endif

        @if($activeTab === 'influencers')
            {{-- HAUPTBEREICH: TABELLEN-KARTE FÜR CREATOR & EINLADUNGEN --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-sm dark:shadow-2xl overflow-hidden transition-colors">

                {{-- Filter- & Suchleiste im Tabellenkopf --}}
                <div class="p-6 sm:px-8 border-b border-gray-100 dark:border-gray-800 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="$set('statusFilter', 'active')"
                                class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all cursor-pointer {{ $statusFilter === 'active' ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)] shadow-xs' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color-30)]' }}">
                            Aktiv &amp; Einladungen ({{ $stats['active_count'] + $stats['pending_invitations'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'pending')"
                                class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all cursor-pointer {{ $statusFilter === 'pending' ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)] shadow-xs' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color-30)]' }}">
                            Offene Einladungen ({{ $stats['pending_invitations'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'archived')"
                                class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all cursor-pointer {{ $statusFilter === 'archived' ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)] shadow-xs' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color-30)]' }}">
                            Archiv ({{ $stats['archived_count'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'all')"
                                class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all cursor-pointer {{ $statusFilter === 'all' ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] text-[var(--theme-color)] shadow-xs' : 'bg-gray-50 dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:text-[var(--theme-color)] hover:border-[var(--theme-color-30)]' }}">
                            Alle anzeigen
                        </button>
                    </div>

                    <div class="relative w-full lg:w-80">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-[var(--theme-color)] absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche nach Name, @Handle, E-Mail..."
                               class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] rounded-2xl pl-10 pr-4 py-2.5 text-xs text-gray-900 dark:text-white placeholder-gray-400 outline-none transition-all">
                    </div>
                </div>

                @if($influencers->isNotEmpty())
                    {{-- ÜBERSICHTLICHE, KOMPAKTE TABELLE (OHNE HORIZONTALES SCROLLEN) --}}
                    <div class="w-full">
                        <table class="w-full table-auto text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <th class="py-3 px-4">Name</th>
                                    <th class="py-3 px-3">Status</th>
                                    <th class="py-3 px-3">Code / Tracking</th>
                                    <th class="py-3 px-3">Erstellt am</th>
                                    <th class="py-3 px-3">Gültig bis</th>
                                    <th class="py-3 px-4 text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs">
                                @foreach($influencers as $inf)
                                    @php
                                        $isPendingInvite = $inf->invitation_accepted_at === null;
                                        $inviteUrl = $inf->invitation_url;
                                        $verifyCode = $inf->verification_code;
                                        $clicks = $inf->trackingLinks->sum('clicks_count');
                                        $activeCommissions = $inf->commissions->whereIn('status', ['pending', 'approved', 'paid']);
                                        $ordersCount = $activeCommissions->count();
                                        $netRevenueCents = $activeCommissions->sum('order_net_amount_cents');
                                        $pendingCents = $inf->commissions->where('status', 'pending')->whereNull('payout_id')->sum('commission_amount_cents');
                                        $approvedCents = $inf->commissions->where('status', 'approved')->whereNull('payout_id')->sum('commission_amount_cents');
                                        $shortUrl = $inf->defaultTrackingLink?->short_url ?? ($inf->slug ? url('/t/' . $inf->slug) : null);
                                        $voucherCode = $inf->defaultVoucher?->code;
                                    @endphp

                                    <tr wire:key="influencer-row-{{ $inf->id }}" class="hover:bg-[var(--theme-color-5)] dark:hover:bg-gray-950/50 transition-colors group">
                                        {{-- 1. NAME / KONDITIONEN --}}
                                        <td class="py-3 px-4 align-middle">
                                            @if($isPendingInvite)
                                                <div class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm group-hover:text-[var(--theme-color)] transition-colors truncate max-w-[200px]">
                                                    {{ $inf->invitation_label ?: 'Creator-Einladung' }}
                                                </div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                    <span class="font-semibold text-[var(--theme-color)]">{{ $inf->commission_value }}% Prov.</span> &middot; {{ $inf->follower_discount_percent }}% Rabatt
                                                </div>
                                            @else
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-bold text-gray-900 dark:text-white text-xs sm:text-sm truncate max-w-[150px]">
                                                        {{ $inf->employee?->first_name }} {{ $inf->employee?->last_name }}
                                                    </span>
                                                    <span class="font-mono text-[11px] font-bold text-[var(--theme-color)]">
                                                        {{ $inf->handle }}
                                                    </span>
                                                </div>
                                                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 truncate max-w-[220px]">
                                                    {{ $inf->employee?->email }} &middot; {{ $inf->commission_value }}% Prov. &middot; {{ $inf->follower_discount_percent }}% Rabatt
                                                </div>
                                            @endif
                                        </td>

                                        {{-- 2. STATUS --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap">
                                            @if($isPendingInvite)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20">
                                                    Einladung offen
                                                </span>
                                            @elseif($inf->is_archived)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
                                                    Archiviert
                                                </span>
                                            @elseif($inf->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                                                    Aktiv
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-red-50 text-red-700 border border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
                                                    Pausiert
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 3. CODE / TRACKING --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap">
                                            @if($isPendingInvite)
                                                @if($verifyCode)
                                                    <div x-data="{ copiedCode: false }" class="inline-flex items-center">
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(@js($verifyCode)); copiedCode = true; setTimeout(() => copiedCode = false, 1800)"
                                                                class="group/tip relative inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-[var(--theme-color-10)] hover:bg-[var(--theme-color-20)] border border-[var(--theme-color-30)] font-mono font-bold text-[11px] text-[var(--theme-color)] transition-all cursor-pointer"
                                                                title="Verifizierungscode kopieren">
                                                            <span>{{ $verifyCode }}</span>
                                                            <x-heroicon-o-clipboard x-show="!copiedCode" class="w-3.5 h-3.5 opacity-80" />
                                                            <x-heroicon-o-check x-show="copiedCode" x-cloak class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-sans font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md"
                                                                  x-text="copiedCode ? 'Code kopiert ✓' : 'Verifizierungscode kopieren'"></span>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            @else
                                                <div class="space-y-0.5">
                                                    <div class="font-mono text-[11px] font-bold text-[var(--theme-color)]">
                                                        /t/{{ $inf->slug }}
                                                        @if($voucherCode)
                                                            &middot; <span class="text-emerald-600 dark:text-emerald-400">{{ $voucherCode }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[10px] text-gray-500 dark:text-gray-400">
                                                        {{ $clicks }} Klicks &middot; {{ $ordersCount }} Sales ({{ number_format($netRevenueCents / 100, 2, ',', '.') }} €)
                                                        @if($approvedCents > 0)
                                                            &middot; <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ number_format($approvedCents / 100, 2, ',', '.') }} € frei</span>
                                                        @elseif($pendingCents > 0)
                                                            &middot; <span class="text-amber-600 dark:text-amber-400 font-bold">{{ number_format($pendingCents / 100, 2, ',', '.') }} € offen</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- 4. ERSTELLT AM --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                            {{ $inf->created_at?->format('d.m.y H:i') }}
                                        </td>

                                        {{-- 5. GÜLTIG BIS --}}
                                        <td class="py-3 px-3 align-middle whitespace-nowrap font-mono text-[11px] text-gray-600 dark:text-gray-400">
                                            @if($isPendingInvite)
                                                {{ $inf->invitation_expires_at ? $inf->invitation_expires_at->format('d.m.y') : '-' }}
                                            @else
                                                <span class="font-sans text-[11px] text-gray-500">Reg. {{ $inf->invitation_accepted_at?->format('d.m.y') }}</span>
                                            @endif
                                        </td>

                                        {{-- 6. AKTIONEN (Kompakte Icon-Buttons mit Hover-Erklärung) --}}
                                        <td class="py-3 px-4 align-middle text-right whitespace-nowrap">
                                            @if($isPendingInvite)
                                                <div x-data="{ copiedLink: false }" class="inline-flex items-center justify-end gap-1.5">
                                                    {{-- Einladungs-Link kopieren --}}
                                                    @if($inviteUrl)
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(@js($inviteUrl)); copiedLink = true; setTimeout(() => copiedLink = false, 1800)"
                                                                class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                                title="Einladungs-Link kopieren">
                                                            <x-heroicon-o-link x-show="!copiedLink" class="w-4 h-4" />
                                                            <x-heroicon-o-check x-show="copiedLink" x-cloak class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md"
                                                                  x-text="copiedLink ? 'Link kopiert ✓' : 'Einladungs-Link kopieren'"></span>
                                                        </button>
                                                    @endif

                                                    {{-- Einladung & Text öffnen --}}
                                                    <button type="button" wire:click="openInviteModal('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                            title="Einladung & Text öffnen">
                                                        <x-heroicon-o-document-text class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            Einladung &amp; Text öffnen
                                                        </span>
                                                    </button>

                                                    {{-- Neu generieren --}}
                                                    <button type="button" wire:click="regenerateInvitationLink('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                            title="Neu generieren">
                                                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            Neu generieren
                                                        </span>
                                                    </button>

                                                    {{-- Löschen --}}
                                                    <button type="button" wire:click="deleteInfluencer('{{ $inf->id }}')"
                                                            wire:confirm="Möchtest du diese offene Einladung wirklich löschen?"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:bg-red-50 dark:hover:bg-red-500/10 hover:border-red-200 dark:hover:border-red-500/30 hover:text-red-600 dark:hover:text-red-400 shadow-xs transition-all cursor-pointer"
                                                            title="Löschen">
                                                        <x-heroicon-o-trash class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            Löschen
                                                        </span>
                                                    </button>
                                                </div>
                                            @else
                                                <div x-data="{ copiedShort: false }" class="inline-flex items-center justify-end gap-1.5">
                                                    {{-- Kurzlink kopieren --}}
                                                    @if($shortUrl)
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(@js($shortUrl)); copiedShort = true; setTimeout(() => copiedShort = false, 1800)"
                                                                class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                                title="Tracking-Kurzlink kopieren">
                                                            <x-heroicon-o-link x-show="!copiedShort" class="w-4 h-4" />
                                                            <x-heroicon-o-check x-show="copiedShort" x-cloak class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md"
                                                                  x-text="copiedShort ? 'Kurzlink kopiert ✓' : 'Tracking-Kurzlink kopieren'"></span>
                                                        </button>
                                                    @endif

                                                    {{-- Offene Provisionen freigeben --}}
                                                    @if($pendingCents > 0)
                                                        <button type="button" wire:click="approveCommissionsForInfluencer('{{ $inf->id }}')"
                                                                class="group/tip relative p-2 rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-500/15 dark:hover:bg-amber-500/25 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 shadow-xs transition-all cursor-pointer"
                                                                title="Provisionen freigeben">
                                                            <x-heroicon-o-check-badge class="w-4 h-4" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                                Provisionen freigeben
                                                            </span>
                                                        </button>
                                                    @endif

                                                    {{-- Gutschrift & Auszahlung erstellen --}}
                                                    @if($approvedCents > 0)
                                                        <button type="button" wire:click="createPayoutForInfluencer('{{ $inf->id }}')"
                                                                class="group/tip relative p-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 shadow-xs transition-all cursor-pointer"
                                                                title="Gutschrift & Auszahlen">
                                                            <x-heroicon-o-banknotes class="w-4 h-4" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                                Gutschrift &amp; Auszahlen
                                                            </span>
                                                        </button>
                                                    @endif

                                                    {{-- Konfigurieren --}}
                                                    <button type="button" wire:click="editInfluencer('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                            title="Konfigurieren">
                                                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            Konfigurieren
                                                        </span>
                                                    </button>

                                                    {{-- Pausieren / Aktivieren --}}
                                                    <button type="button" wire:click="toggleActive('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg border {{ $inf->is_active ? 'bg-emerald-50 text-emerald-600 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-400' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-400' }} shadow-xs transition-all cursor-pointer"
                                                            title="{{ $inf->is_active ? 'Pausieren' : 'Aktivieren' }}">
                                                        <x-heroicon-o-power class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            {{ $inf->is_active ? 'Pausieren' : 'Aktivieren' }}
                                                        </span>
                                                    </button>

                                                    {{-- Archivieren / Wiederherstellen --}}
                                                    <button type="button" wire:click="toggleArchive('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                            title="{{ $inf->is_archived ? 'Wiederherstellen' : 'Archivieren' }}">
                                                        <x-heroicon-o-archive-box class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            {{ $inf->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                                                        </span>
                                                    </button>

                                                    {{-- Löschen --}}
                                                    <button type="button" wire:click="deleteInfluencer('{{ $inf->id }}')"
                                                            wire:confirm="Möchtest du den Influencer {{ $inf->handle }} wirklich löschen?"
                                                            class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 hover:bg-red-50 dark:hover:bg-red-500/10 hover:border-red-200 dark:hover:border-red-500/30 hover:text-red-600 dark:hover:text-red-400 shadow-xs transition-all cursor-pointer"
                                                            title="Löschen">
                                                        <x-heroicon-o-trash class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                            Löschen
                                                        </span>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    {{-- EMPTY STATE (Mit Theme-Var Button & Badge wie auf den anderen Marketing-Seiten) --}}
                    <div class="p-12 sm:p-16 text-center">
                        <div class="w-16 h-16 rounded-2xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] flex items-center justify-center text-[var(--theme-color)] mx-auto mb-4 shadow-sm">
                            <x-heroicon-o-user-plus class="w-8 h-8" />
                        </div>
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Noch keine Influencer angelegt</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 max-w-lg mx-auto">
                            Klicke auf <strong class="text-gray-900 dark:text-white">„Influencer einladen“</strong>, um einen Einladungslink und einen 6-stelligen Verifizierungscode für die Schritt-für-Schritt Selbstregistrierung zu generieren.
                        </p>
                        <div class="mt-6">
                            <button type="button" wire:click="createInvitation"
                                    class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] cursor-pointer">
                                <x-heroicon-m-plus class="w-4 h-4 stroke-2" />
                                Influencer einladen
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- TAB 2: AUSZAHLUNGEN & GUTSCHRIFTEN (§ 14 Abs. 2 S. 2 UStG) --}}
            <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-sm dark:shadow-2xl overflow-hidden transition-colors">
                <div class="p-6 sm:px-8 border-b border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Provisions-Gutschriften &amp; Auszahlungen</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Abrechnung im Gutschriftsverfahren (§ 14 Abs. 2 S. 2 UStG). Beim Verbuchen als bezahlt wird der PDF-Beleg automatisch in die Buchhaltung übernommen.
                    </p>
                </div>

                <div class="w-full">
                    <table class="w-full table-auto text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-3 px-4">Gutschrift-Nr.</th>
                                <th class="py-3 px-3">Creator</th>
                                <th class="py-3 px-3">Auszahlungsweg</th>
                                <th class="py-3 px-3">Netto</th>
                                <th class="py-3 px-3">USt. / Bonus</th>
                                <th class="py-3 px-3">Auszahlbetrag</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-4 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs">
                            @forelse($payouts as $payout)
                                <tr class="hover:bg-[var(--theme-color-5)] dark:hover:bg-gray-950/50 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-gray-900 dark:text-white">
                                        {{ $payout->credit_note_number }}
                                        <span class="block text-[10px] text-gray-500 font-sans font-normal">{{ $payout->created_at?->format('d.m.y H:i') }}</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="font-bold text-[var(--theme-color)] block">{{ $payout->influencer?->handle ?? '-' }}</span>
                                        <span class="text-[10px] text-gray-500 dark:text-gray-400">{{ $payout->influencer?->employee?->first_name }} {{ $payout->influencer?->employee?->last_name }}</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($payout->payout_method === 'bank_transfer')
                                            <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/20 font-bold text-[10px] uppercase tracking-wider">SEPA</span>
                                        @elseif($payout->payout_method === 'paypal')
                                            <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-400 dark:border-indigo-500/20 font-bold text-[10px] uppercase tracking-wider">PayPal</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-lg bg-purple-50 text-purple-700 border border-purple-200 dark:bg-purple-500/10 dark:text-purple-400 dark:border-purple-500/20 font-bold text-[10px] uppercase tracking-wider">Gutschein (+20%)</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 font-mono text-gray-700 dark:text-gray-300">
                                        {{ number_format($payout->net_amount_cents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-3 px-3 font-mono text-gray-500 dark:text-gray-400">
                                        @if($payout->bonus_amount_cents > 0)
                                            <span class="text-purple-600 dark:text-purple-400 font-bold">+{{ number_format($payout->bonus_amount_cents / 100, 2, ',', '.') }} €</span>
                                        @elseif($payout->tax_amount_cents > 0)
                                            {{ number_format($payout->tax_amount_cents / 100, 2, ',', '.') }} € ({{ $payout->tax_rate }}%)
                                        @else
                                            0,00 € (§ 19)
                                        @endif
                                    </td>
                                    <td class="py-3 px-3 font-mono font-black text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">
                                        {{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-3 px-3">
                                        @if($payout->status === 'paid')
                                            <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20 text-[10px] font-black uppercase tracking-wider">
                                                Bezahlt
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/20 text-[10px] font-black uppercase tracking-wider">
                                                Offen
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="openPayoutModal('{{ $payout->id }}')"
                                                    class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all cursor-pointer"
                                                    title="QR-Code & Details">
                                                <x-heroicon-o-qr-code class="w-4 h-4" />
                                                <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                    QR-Code &amp; Details
                                                </span>
                                            </button>

                                            <a href="{{ route('admin.influencer.payout.pdf', ['payout' => $payout->id]) }}" target="_blank"
                                               class="group/tip relative p-2 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] hover:text-[var(--theme-color)] shadow-xs transition-all"
                                               title="PDF-Beleg öffnen">
                                                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                                <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                    PDF-Beleg öffnen
                                                </span>
                                            </a>

                                            @if($payout->status !== 'paid')
                                                <button type="button" wire:click="confirmPayoutPaid('{{ $payout->id }}')"
                                                        class="group/tip relative p-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 shadow-xs transition-all cursor-pointer"
                                                        title="Als bezahlt verbuchen">
                                                    <x-heroicon-o-check class="w-4 h-4" />
                                                    <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-lg bg-gray-900 px-2 py-1 text-[10px] font-bold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-md">
                                                        Als bezahlt verbuchen
                                                    </span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                        Noch keine Gutschriften oder Auszahlungen vorhanden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- MODAL 1: EINLADUNGSLINK & 6-STELLIGER SICHERHEITSCODE (IM THEME-VAR DESIGN) --}}
        @if($showInviteModal && $generatedInviteUrl)
            @php
                $readyMessage = "Hallo,\n\nhier ist deine persönliche Creator-Einladung für das Mein Seelenfunke Partnerprogramm ({$inviteCommissionValue}% Provision & {$inviteFollowerDiscount}% Rabattcode für deine Community):\n\nEinladungslink: {$generatedInviteUrl}\nVerifizierungscode: {$generatedInviteCode}";
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/80 backdrop-blur-md" wire:click="closeInviteModal"></div>

                <div class="relative w-full max-w-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl overflow-hidden z-10 animate-fade-in-up transition-colors">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50 dark:bg-gray-950/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center">
                                <x-heroicon-o-paper-airplane class="w-5 h-5 -rotate-45" />
                            </div>
                            <div>
                                <h3 class="font-serif font-bold text-gray-900 dark:text-white text-lg">
                                    Einladungslink &amp; Verifizierungscode
                                </h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">
                                    Creator Onboarding
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeInviteModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="p-6 sm:p-8 space-y-5">
                        <div x-data="{ copiedLink: false, copiedCode: false, copiedAll: false }" class="space-y-3">
                            <div class="p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Einladungslink (14 Tage gültig)</span>
                                    <button type="button"
                                            @click="navigator.clipboard.writeText(@js($generatedInviteUrl)); copiedLink = true; setTimeout(() => copiedLink = false, 2000)"
                                            class="text-xs font-black text-[var(--theme-color)] hover:underline cursor-pointer">
                                        <span x-text="copiedLink ? 'Kopiert ✓' : 'Link kopieren'"></span>
                                    </button>
                                </div>
                                <div class="text-xs font-mono text-gray-800 dark:text-gray-200 break-all bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-3 py-2">
                                    {{ $generatedInviteUrl }}
                                </div>
                            </div>

                            <div class="p-4 rounded-2xl bg-[var(--theme-color-5)] dark:bg-gray-950 border border-[var(--theme-color-30)] flex items-center justify-between gap-4">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block">6-stelliger Sicherheitscode</span>
                                    <span class="text-2xl font-mono font-black tracking-[0.25em] text-gray-900 dark:text-white mt-0.5 block">{{ $generatedInviteCode }}</span>
                                </div>
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($generatedInviteCode)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                        class="px-4 py-2.5 rounded-xl bg-white hover:bg-[var(--theme-color-10)] dark:bg-gray-900 border border-[var(--theme-color-30)] text-[var(--theme-color)] font-black text-[10px] uppercase tracking-widest transition-all cursor-pointer">
                                    <span x-text="copiedCode ? 'Kopiert ✓' : 'Code kopieren'"></span>
                                </button>
                            </div>

                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($readyMessage)); copiedAll = true; setTimeout(() => copiedAll = false, 2500)"
                                    class="w-full py-3.5 px-5 rounded-2xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest transition-all shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] flex items-center justify-center gap-2 cursor-pointer">
                                <x-heroicon-o-clipboard-document-check class="w-4 h-4" />
                                <span x-text="copiedAll ? 'Einladungs-Text kopiert ✓' : 'Einladungs-Text (Link + Code) kopieren'"></span>
                            </button>
                        </div>

                        <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 block">
                                Bezeichnung &amp; Konditionen dieser Einladung
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Bezeichnung / Notiz</label>
                                    <input type="text" wire:model="inviteLabel" placeholder="z. B. Für @lara.living"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Provision (%)</label>
                                    <input type="number" wire:model="inviteCommissionValue" min="1" max="80"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1">Follower-Rabatt (%)</label>
                                    <input type="number" wire:model="inviteFollowerDiscount" min="0" max="50"
                                           class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" wire:click="updateInviteSlotSettings"
                                        class="px-4 py-2 rounded-xl bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] border border-[var(--theme-color-30)] text-[var(--theme-color)] hover:text-gray-900 text-[10px] font-black uppercase tracking-widest transition-all cursor-pointer">
                                    Speichern
                                </button>
                                <button type="button" wire:click="closeInviteModal"
                                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                                    Schließen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL 2: INFLUENCER KONFIGURIEREN / BEARBEITEN (IM THEME-VAR DESIGN) --}}
        @if($showConfigModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
                <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/80 backdrop-blur-md" wire:click="closeConfigModal"></div>

                <div class="relative w-full max-w-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl overflow-hidden z-10 my-8 animate-fade-in-up transition-colors">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50 dark:bg-gray-950/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center">
                                <x-heroicon-o-pencil-square class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-serif font-bold text-gray-900 dark:text-white text-lg">
                                    Influencer konfigurieren ({{ $handle ?: $firstName }})
                                </h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">
                                    Creator Verwaltung
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConfigModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <form wire:submit.prevent="saveInfluencer" class="p-6 sm:p-8 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Vorname *</label>
                                <input type="text" wire:model="firstName" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white outline-none">
                                @error('firstName') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Nachname *</label>
                                <input type="text" wire:model="lastName" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white outline-none">
                                @error('lastName') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Login E-Mail *</label>
                                <input type="email" wire:model="email" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white outline-none">
                                @error('email') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Plattform</label>
                                <select wire:model="primaryChannel" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs text-gray-900 dark:text-white outline-none">
                                    <option value="instagram">Instagram</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="pinterest">Pinterest</option>
                                    <option value="blog">Blog / Website</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">@Handle *</label>
                                <input type="text" wire:model="handle" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                @error('handle') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Kurzlink-Slug (/t/...) *</label>
                                <input type="text" wire:model="slug" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2.5 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                @error('slug') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Provision (%)</label>
                                <input type="number" wire:model="commissionValue" min="1" max="80" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Follower-Rabatt (%)</label>
                                <input type="number" wire:model="followerDiscountPercent" min="0" max="50" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Rabattcode</label>
                                <input type="text" wire:model="customVoucherCode" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono uppercase text-[var(--theme-color)] font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Cookie-Tage</label>
                                <input type="number" wire:model="cookieDays" min="1" max="90" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1">Interne Admin-Notizen</label>
                            <textarea wire:model="internalNotes" rows="2" placeholder="Interne Notizen zu Absprachen..."
                                      class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-[var(--theme-color)] rounded-xl px-3.5 py-2 text-xs text-gray-900 dark:text-white outline-none"></textarea>
                        </div>

                        <div class="flex justify-end gap-2.5 pt-2">
                            <button type="button" wire:click="closeConfigModal"
                                    class="px-5 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                Abbrechen
                            </button>
                            <button type="submit"
                                    class="px-6 py-2.5 rounded-xl bg-[var(--theme-color)] border border-[var(--theme-color-50)] hover:opacity-90 text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest shadow-sm dark:shadow-[0_0_20px_var(--theme-color-30)] transition-all cursor-pointer">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- MODAL 3: EPC-GIROCODE & AUSZAHLUNGS-DETAILS (IM THEME-VAR DESIGN) --}}
        @if($showPayoutModal && $selectedPayoutData)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-900/60 dark:bg-black/80 backdrop-blur-md" wire:click="closePayoutModal"></div>

                <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-[2.5rem] shadow-2xl overflow-hidden z-10 animate-fade-in-up transition-colors">
                    <div class="px-6 sm:px-8 py-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between bg-gray-50 dark:bg-gray-950/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center">
                                <x-heroicon-o-qr-code class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-serif font-bold text-gray-900 dark:text-white text-lg">
                                    {{ $selectedPayoutData['credit_note_number'] }} &middot; {{ $selectedPayoutData['handle'] }}
                                </h3>
                                <p class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">
                                    Gutschrift (§ 14 Abs. 2 S. 2 UStG)
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="closePayoutModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">
                            <x-heroicon-o-x-mark class="w-6 h-6" />
                        </button>
                    </div>

                    <div class="p-6 sm:p-8 space-y-5">
                        <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 space-y-2 text-xs">
                            <div class="flex justify-between text-gray-500">
                                <span>Empfänger:</span>
                                <span class="text-gray-900 dark:text-white font-semibold">{{ $selectedPayoutData['full_name'] }}</span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Steuernummer / USt-IdNr.:</span>
                                <span class="text-gray-900 dark:text-white font-mono">{{ $selectedPayoutData['tax_number'] ?: '-' }}</span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>Netto-Provision ({{ $selectedPayoutData['commissions_count'] }} Bestellungen):</span>
                                <span class="text-gray-900 dark:text-white font-mono">{{ $selectedPayoutData['net_formatted'] }}</span>
                            </div>
                            @if($selectedPayoutData['bonus_formatted'])
                                <div class="flex justify-between text-purple-600 dark:text-purple-400">
                                    <span>+20 % Gutschein-Bonus:</span>
                                    <span class="font-mono font-bold">+{{ $selectedPayoutData['bonus_formatted'] }}</span>
                                </div>
                            @else
                                <div class="flex justify-between text-gray-500">
                                    <span>Umsatzsteuer ({{ $selectedPayoutData['tax_rate'] }} %):</span>
                                    <span class="text-gray-900 dark:text-white font-mono">{{ $selectedPayoutData['tax_formatted'] }}</span>
                                </div>
                            @endif
                            <div class="flex justify-between text-sm font-black text-emerald-600 dark:text-emerald-400 pt-2 border-t border-gray-200 dark:border-gray-800">
                                <span>Auszahlungsbetrag:</span>
                                <span class="font-mono">{{ $selectedPayoutData['gross_formatted'] }}</span>
                            </div>
                        </div>

                        @if($selectedPayoutData['payout_method'] === 'bank_transfer')
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-col sm:flex-row items-center gap-4">
                                @if($giroCodeSvg)
                                    <div class="bg-white p-2 rounded-xl border border-gray-200 shrink-0">
                                        {!! $giroCodeSvg !!}
                                    </div>
                                @endif
                                <div class="space-y-1 text-xs">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block">EPC-GiroCode (Banking-App Scan)</span>
                                    <p class="text-gray-500 dark:text-gray-400 text-[11px]">
                                        Mit der Banking-App scannen. Empfänger, IBAN, Betrag und Verwendungszweck sind vorausgefüllt.
                                    </p>
                                    <div class="pt-1 font-mono text-[11px] text-gray-800 dark:text-gray-200">
                                        <div>Inhaber: {{ $selectedPayoutData['account_holder'] }}</div>
                                        <div>IBAN: {{ $selectedPayoutData['iban'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @elseif($selectedPayoutData['payout_method'] === 'paypal')
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-xs">
                                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block">PayPal-Empfängeradresse</span>
                                <span class="text-sm font-mono font-bold text-gray-900 dark:text-white mt-1 block">{{ $selectedPayoutData['paypal_email'] }}</span>
                            </div>
                        @elseif($selectedPayoutData['gift_voucher_code'])
                            <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-xs">
                                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)] block">Generierter Wertgutschein (+20 % Bonus)</span>
                                <span class="text-base font-mono font-bold text-gray-900 dark:text-white mt-1 block">{{ $selectedPayoutData['gift_voucher_code'] }}</span>
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <a href="{{ route('admin.influencer.payout.pdf', ['payout' => $selectedPayoutData['id']]) }}" target="_blank"
                               class="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white text-[10px] font-black uppercase tracking-widest">
                                PDF-Gutschrift herunterladen
                            </a>

                            @if($selectedPayoutData['status'] !== 'paid')
                                <button type="button" wire:click="confirmPayoutPaid('{{ $selectedPayoutData['id'] }}')"
                                        class="px-5 py-2.5 rounded-xl bg-[var(--theme-color)] hover:opacity-90 text-gray-900 dark:text-gray-950 font-black text-[10px] uppercase tracking-widest cursor-pointer">
                                    Als bezahlt verbuchen
                                </button>
                            @else
                                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">Bereits bezahlt &amp; verbucht ✓</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
