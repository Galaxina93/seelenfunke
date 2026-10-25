<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80;" class="font-sans antialiased text-gray-800 dark:text-gray-200 min-h-screen">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-6 pb-20">

        {{-- HEADER (Clean & Matt, ohne unnötige Icons oder Glow-Effekte) --}}
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 shadow-xs transition-colors">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-6">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-gray-900 dark:text-white tracking-tight">
                        Influencer &amp; Affiliate Hub
                    </h1>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-2xl">
                        Creator per Einladungslink &amp; 6-stelligem Sicherheitscode einladen und verwalten. Kurzlinks (<code class="font-mono text-gray-700 dark:text-gray-300">/t/name</code>), Rabattcodes und § 14 UStG Gutschriftsdaten werden bei der Registrierung automatisch erfasst.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" wire:click="createInvitation"
                            class="px-5 py-2.5 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[var(--theme-color)] dark:hover:opacity-90 text-white dark:text-gray-950 font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer">
                        + Influencer einladen
                    </button>

                    <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-950 p-1 rounded-xl border border-gray-200 dark:border-gray-800">
                        <button type="button" wire:click="$set('activeTab', 'influencers')"
                                class="px-3.5 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer {{ $activeTab === 'influencers' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                            Creator &amp; Einladungen ({{ $stats['active_count'] + $stats['pending_invitations'] }})
                        </button>
                        <button type="button" wire:click="$set('activeTab', 'payouts')"
                                class="px-3.5 py-2 rounded-lg text-xs font-bold transition-colors cursor-pointer {{ $activeTab === 'payouts' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-2xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                            Auszahlungen &amp; Gutschriften ({{ $payouts->count() }})
                        </button>
                    </div>
                </div>
            </div>

            {{-- KPI CARDS (Matt & Übersichtlich) --}}
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-800">
                <div class="bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800 rounded-xl p-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">Aktive Creator</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white mt-1 block">{{ $stats['active_count'] }}</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ $stats['pending_invitations'] > 0 ? $stats['pending_invitations'] . ' Einladung(en) offen' : 'Keine offenen Einladungen' }}
                    </span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800 rounded-xl p-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">Bio-Link Klicks</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white mt-1 block">{{ number_format($stats['total_clicks'], 0, ',', '.') }}</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($stats['total_unique_clicks'], 0, ',', '.') }} echte Besucher</span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800 rounded-xl p-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">Bestellungen</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white mt-1 block">{{ number_format($stats['total_orders'], 0, ',', '.') }}</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Über Link &amp; Code</span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800 rounded-xl p-4">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">Netto-Umsatz</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white mt-1 block">{{ number_format($stats['total_revenue_cents'] / 100, 2, ',', '.') }} €</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Exkl. Versand &amp; USt.</span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800 rounded-xl p-4 col-span-2 lg:col-span-1">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 block">Offene Provision</span>
                    <span class="text-2xl font-bold text-gray-900 dark:text-white mt-1 block">{{ number_format($stats['open_commission_cents'] / 100, 2, ',', '.') }} €</span>
                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Noch nicht ausgezahlt</span>
                </div>
            </div>
        </div>

        {{-- FLASH MESSAGES --}}
        @if (session()->has('influencer_saved'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs font-semibold flex items-center justify-between">
                <span>{{ session('influencer_saved') }}</span>
            </div>
        @endif

        @if (session()->has('influencer_error'))
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-800 dark:text-red-300 text-xs font-semibold flex items-center justify-between">
                <span>{{ session('influencer_error') }}</span>
            </div>
        @endif

        @if($activeTab === 'influencers')
            {{-- HAUPTBEREICH: TABELLEN-KARTE FÜR CREATOR & EINLADUNGEN --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xs overflow-hidden transition-colors">

                {{-- Filter- & Suchleiste im Tabellenkopf --}}
                <div class="p-4 sm:p-5 border-b border-gray-200 dark:border-gray-800 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" wire:click="$set('statusFilter', 'active')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer {{ $statusFilter === 'active' ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            Aktiv &amp; Einladungen ({{ $stats['active_count'] + $stats['pending_invitations'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'pending')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer {{ $statusFilter === 'pending' ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            Offene Einladungen ({{ $stats['pending_invitations'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'archived')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer {{ $statusFilter === 'archived' ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            Archiv ({{ $stats['archived_count'] }})
                        </button>
                        <button type="button" wire:click="$set('statusFilter', 'all')"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors cursor-pointer {{ $statusFilter === 'all' ? 'bg-gray-900 text-white border-gray-900 dark:bg-white dark:text-gray-900 dark:border-white' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                            Alle anzeigen
                        </button>
                    </div>

                    <div class="relative w-full lg:w-72">
                        <x-heroicon-o-magnifying-glass class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Suche nach Name, @Handle, E-Mail..."
                               class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 dark:focus:border-gray-600 rounded-xl pl-9 pr-4 py-2 text-xs text-gray-900 dark:text-white placeholder-gray-400 outline-none transition-colors">
                    </div>
                </div>

                @if($influencers->isNotEmpty())
                    {{-- ÜBERSICHTLICHE TABELLE (Name, Status, Code / Tracking, Erstellt am, Gültig bis, Aktionen) --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    <th class="py-3.5 px-5">Name / Konditionen</th>
                                    <th class="py-3.5 px-4">Status</th>
                                    <th class="py-3.5 px-4">Code / Tracking</th>
                                    <th class="py-3.5 px-4">Erstellt am</th>
                                    <th class="py-3.5 px-4">Gültig bis</th>
                                    <th class="py-3.5 px-5 text-right">Aktionen</th>
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

                                    <tr wire:key="influencer-row-{{ $inf->id }}" class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                        {{-- 1. NAME / KONDITIONEN --}}
                                        <td class="py-4 px-5 align-middle">
                                            @if($isPendingInvite)
                                                <div class="font-bold text-gray-900 dark:text-white text-sm">
                                                    {{ $inf->invitation_label ?: 'Creator-Einladung' }}
                                                </div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ $inf->commission_value }} % Provision &middot; {{ $inf->follower_discount_percent }} % Follower-Rabatt
                                                </div>
                                            @else
                                                <div class="flex items-center gap-2">
                                                    <span class="font-bold text-gray-900 dark:text-white text-sm">
                                                        {{ $inf->employee?->first_name }} {{ $inf->employee?->last_name }}
                                                    </span>
                                                    <span class="font-mono text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                        {{ $inf->handle }}
                                                    </span>
                                                </div>
                                                <div class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                                    {{ $inf->employee?->email }} &middot; {{ $inf->commission_value }} % Prov. &middot; {{ $inf->follower_discount_percent }} % Rabatt
                                                </div>
                                            @endif
                                        </td>

                                        {{-- 2. STATUS --}}
                                        <td class="py-4 px-4 align-middle whitespace-nowrap">
                                            @if($isPendingInvite)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30">
                                                    Wartet auf Registrierung
                                                </span>
                                            @elseif($inf->is_archived)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700">
                                                    Archiviert
                                                </span>
                                            @elseif($inf->is_active)
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30">
                                                    Aktiv
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-semibold bg-red-50 text-red-800 border border-red-200 dark:bg-red-500/10 dark:text-red-300 dark:border-red-500/30">
                                                    Pausiert
                                                </span>
                                            @endif
                                        </td>

                                        {{-- 3. CODE / TRACKING --}}
                                        <td class="py-4 px-4 align-middle whitespace-nowrap">
                                            @if($isPendingInvite)
                                                @if($verifyCode)
                                                    <div x-data="{ copiedCode: false }" class="inline-flex items-center gap-1.5">
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(@js($verifyCode)); copiedCode = true; setTimeout(() => copiedCode = false, 1800)"
                                                                class="group/tip relative inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 font-mono font-bold text-xs text-gray-900 dark:text-white transition-colors cursor-pointer"
                                                                title="Verifizierungscode kopieren">
                                                            <span>{{ $verifyCode }}</span>
                                                            <x-heroicon-o-clipboard x-show="!copiedCode" class="w-3.5 h-3.5 text-gray-500" />
                                                            <x-heroicon-o-check x-show="copiedCode" x-cloak class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-sans font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs"
                                                                  x-text="copiedCode ? 'Code kopiert' : 'Code kopieren'"></span>
                                                        </button>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            @else
                                                <div class="space-y-0.5">
                                                    <div class="font-mono text-xs font-semibold text-gray-900 dark:text-white">
                                                        /t/{{ $inf->slug }}
                                                        @if($voucherCode)
                                                            &middot; <span class="text-emerald-700 dark:text-emerald-400">{{ $voucherCode }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                        {{ $clicks }} Klicks &middot; {{ $ordersCount }} Sales ({{ number_format($netRevenueCents / 100, 2, ',', '.') }} €)
                                                        @if($approvedCents > 0)
                                                            &middot; <span class="text-emerald-700 dark:text-emerald-400 font-semibold">{{ number_format($approvedCents / 100, 2, ',', '.') }} € frei</span>
                                                        @elseif($pendingCents > 0)
                                                            &middot; <span class="text-amber-700 dark:text-amber-400 font-semibold">{{ number_format($pendingCents / 100, 2, ',', '.') }} € offen</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                        </td>

                                        {{-- 4. ERSTELLT AM --}}
                                        <td class="py-4 px-4 align-middle whitespace-nowrap text-gray-600 dark:text-gray-400">
                                            {{ $inf->created_at?->format('d.m.Y H:i') }} Uhr
                                        </td>

                                        {{-- 5. GÜLTIG BIS --}}
                                        <td class="py-4 px-4 align-middle whitespace-nowrap text-gray-600 dark:text-gray-400">
                                            @if($isPendingInvite)
                                                {{ $inf->invitation_expires_at ? $inf->invitation_expires_at->format('d.m.Y') : '-' }}
                                            @else
                                                <span class="text-gray-500">Registriert am {{ $inf->invitation_accepted_at?->format('d.m.Y') }}</span>
                                            @endif
                                        </td>

                                        {{-- 6. AKTIONEN (Kompakte Icon-Buttons mit Hover-Erklärung) --}}
                                        <td class="py-4 px-5 align-middle text-right whitespace-nowrap">
                                            @if($isPendingInvite)
                                                <div x-data="{ copiedLink: false }" class="inline-flex items-center justify-end gap-1.5">
                                                    {{-- Einladungs-Link kopieren --}}
                                                    @if($inviteUrl)
                                                        <button type="button"
                                                                @click="navigator.clipboard.writeText(@js($inviteUrl)); copiedLink = true; setTimeout(() => copiedLink = false, 1800)"
                                                                class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                                title="Einladungs-Link kopieren">
                                                            <x-heroicon-o-link x-show="!copiedLink" class="w-4 h-4" />
                                                            <x-heroicon-o-check x-show="copiedLink" x-cloak class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs"
                                                                  x-text="copiedLink ? 'Link kopiert' : 'Einladungs-Link kopieren'"></span>
                                                        </button>
                                                    @endif

                                                    {{-- Einladung & Text öffnen --}}
                                                    <button type="button" wire:click="openInviteModal('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="Einladung & Text öffnen">
                                                        <x-heroicon-o-document-text class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                            Einladung &amp; Text öffnen
                                                        </span>
                                                    </button>

                                                    {{-- Neu generieren --}}
                                                    <button type="button" wire:click="regenerateInvitationLink('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="Neu generieren">
                                                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                            Neu generieren
                                                        </span>
                                                    </button>

                                                    {{-- Löschen --}}
                                                    <button type="button" wire:click="deleteInfluencer('{{ $inf->id }}')"
                                                            wire:confirm="Möchtest du diese offene Einladung wirklich löschen?"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-red-50 dark:bg-gray-800 dark:hover:bg-red-500/15 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="Löschen">
                                                        <x-heroicon-o-trash class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
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
                                                                class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                                title="Tracking-Kurzlink kopieren">
                                                            <x-heroicon-o-link x-show="!copiedShort" class="w-4 h-4" />
                                                            <x-heroicon-o-check x-show="copiedShort" x-cloak class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs"
                                                                  x-text="copiedShort ? 'Kurzlink kopiert' : 'Tracking-Kurzlink kopieren'"></span>
                                                        </button>
                                                    @endif

                                                    {{-- Offene Provisionen freigeben --}}
                                                    @if($pendingCents > 0)
                                                        <button type="button" wire:click="approveCommissionsForInfluencer('{{ $inf->id }}')"
                                                                class="group/tip relative p-2 rounded-lg bg-amber-50 hover:bg-amber-100 dark:bg-amber-500/15 dark:hover:bg-amber-500/25 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 transition-colors cursor-pointer"
                                                                title="Provisionen freigeben">
                                                            <x-heroicon-o-check-badge class="w-4 h-4" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                                Provisionen freigeben
                                                            </span>
                                                        </button>
                                                    @endif

                                                    {{-- Gutschrift & Auszahlung erstellen --}}
                                                    @if($approvedCents > 0)
                                                        <button type="button" wire:click="createPayoutForInfluencer('{{ $inf->id }}')"
                                                                class="group/tip relative p-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 transition-colors cursor-pointer"
                                                                title="Gutschrift & Auszahlen">
                                                            <x-heroicon-o-banknotes class="w-4 h-4" />
                                                            <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                                Gutschrift &amp; Auszahlen
                                                            </span>
                                                        </button>
                                                    @endif

                                                    {{-- Konfigurieren --}}
                                                    <button type="button" wire:click="editInfluencer('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="Konfigurieren">
                                                        <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                            Konfigurieren
                                                        </span>
                                                    </button>

                                                    {{-- Pausieren / Aktivieren --}}
                                                    <button type="button" wire:click="toggleActive('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="{{ $inf->is_active ? 'Pausieren' : 'Aktivieren' }}">
                                                        <x-heroicon-o-power class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                            {{ $inf->is_active ? 'Pausieren' : 'Aktivieren' }}
                                                        </span>
                                                    </button>

                                                    {{-- Archivieren / Wiederherstellen --}}
                                                    <button type="button" wire:click="toggleArchive('{{ $inf->id }}')"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="{{ $inf->is_archived ? 'Wiederherstellen' : 'Archivieren' }}">
                                                        <x-heroicon-o-archive-box class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                            {{ $inf->is_archived ? 'Wiederherstellen' : 'Archivieren' }}
                                                        </span>
                                                    </button>

                                                    {{-- Löschen --}}
                                                    <button type="button" wire:click="deleteInfluencer('{{ $inf->id }}')"
                                                            wire:confirm="Möchtest du den Influencer {{ $inf->handle }} wirklich löschen?"
                                                            class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-red-50 dark:bg-gray-800 dark:hover:bg-red-500/15 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                            title="Löschen">
                                                        <x-heroicon-o-trash class="w-4 h-4" />
                                                        <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
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
                    {{-- EMPTY STATE (Matt, Clean, Ohne Icon-Box) --}}
                    <div class="p-12 sm:p-16 text-center">
                        <h3 class="text-lg font-serif font-bold text-gray-900 dark:text-white">Noch keine Influencer angelegt</h3>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1.5 max-w-lg mx-auto">
                            Klicke auf <strong class="text-gray-900 dark:text-white">„Influencer einladen“</strong>, um einen Einladungslink und einen 6-stelligen Verifizierungscode für die Schritt-für-Schritt Selbstregistrierung zu generieren.
                        </p>
                        <div class="mt-6">
                            <button type="button" wire:click="createInvitation"
                                    class="px-5 py-3 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[var(--theme-color)] dark:hover:opacity-90 text-white dark:text-gray-950 font-bold text-xs uppercase tracking-wider transition-colors inline-flex items-center gap-2 cursor-pointer">
                                + Influencer einladen
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            {{-- TAB 2: AUSZAHLUNGEN & GUTSCHRIFTEN (§ 14 Abs. 2 S. 2 UStG) --}}
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-xs overflow-hidden transition-colors">
                <div class="p-5 border-b border-gray-200 dark:border-gray-800">
                    <h3 class="text-base font-serif font-bold text-gray-900 dark:text-white">Provisions-Gutschriften &amp; Auszahlungen</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        Abrechnung im Gutschriftsverfahren (§ 14 Abs. 2 S. 2 UStG). Beim Verbuchen als bezahlt wird der PDF-Beleg automatisch in die Buchhaltung übernommen.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 dark:bg-gray-950/60 border-b border-gray-200 dark:border-gray-800 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <th class="py-3.5 px-5">Gutschrift-Nr.</th>
                                <th class="py-3.5 px-4">Creator</th>
                                <th class="py-3.5 px-4">Auszahlungsweg</th>
                                <th class="py-3.5 px-4">Netto</th>
                                <th class="py-3.5 px-4">USt. / Bonus</th>
                                <th class="py-3.5 px-4">Auszahlbetrag</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-5 text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800/80 text-xs">
                            @forelse($payouts as $payout)
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-950/40 transition-colors">
                                    <td class="py-4 px-5 font-mono font-bold text-gray-900 dark:text-white">
                                        {{ $payout->credit_note_number }}
                                        <span class="block text-[10px] text-gray-500 font-sans font-normal">{{ $payout->created_at?->format('d.m.Y H:i') }}</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        <span class="font-bold text-gray-900 dark:text-white block">{{ $payout->influencer?->handle ?? '-' }}</span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ $payout->influencer?->employee?->first_name }} {{ $payout->influencer?->employee?->last_name }}</span>
                                    </td>
                                    <td class="py-4 px-4">
                                        @if($payout->payout_method === 'bank_transfer')
                                            <span class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 font-semibold text-[11px]">SEPA-Überweisung</span>
                                        @elseif($payout->payout_method === 'paypal')
                                            <span class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 font-semibold text-[11px]">PayPal</span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 font-semibold text-[11px]">Shop-Gutschein (+20%)</span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-4 font-mono text-gray-700 dark:text-gray-300">
                                        {{ number_format($payout->net_amount_cents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-4 px-4 font-mono text-gray-500 dark:text-gray-400">
                                        @if($payout->bonus_amount_cents > 0)
                                            +{{ number_format($payout->bonus_amount_cents / 100, 2, ',', '.') }} € Bonus
                                        @elseif($payout->tax_amount_cents > 0)
                                            {{ number_format($payout->tax_amount_cents / 100, 2, ',', '.') }} € ({{ $payout->tax_rate }}%)
                                        @else
                                            0,00 € (§ 19 UStG)
                                        @endif
                                    </td>
                                    <td class="py-4 px-4 font-mono font-bold text-gray-900 dark:text-white text-sm">
                                        {{ number_format($payout->gross_amount_cents / 100, 2, ',', '.') }} €
                                    </td>
                                    <td class="py-4 px-4">
                                        @if($payout->status === 'paid')
                                            <span class="px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:border-emerald-500/30 text-[11px] font-semibold">
                                                Bezahlt &amp; Verbucht
                                            </span>
                                        @else
                                            <span class="px-2.5 py-1 rounded-md bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:border-amber-500/30 text-[11px] font-semibold">
                                                Zahlung offen
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-5 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center justify-end gap-1.5">
                                            <button type="button" wire:click="openPayoutModal('{{ $payout->id }}')"
                                                    class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors cursor-pointer"
                                                    title="QR-Code & Details">
                                                <x-heroicon-o-qr-code class="w-4 h-4" />
                                                <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                    QR-Code &amp; Details
                                                </span>
                                            </button>

                                            <a href="{{ route('admin.influencer.payout.pdf', ['payout' => $payout->id]) }}" target="_blank"
                                               class="group/tip relative p-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 transition-colors"
                                               title="PDF-Beleg öffnen">
                                                <x-heroicon-o-document-arrow-down class="w-4 h-4" />
                                                <span class="pointer-events-none absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
                                                    PDF-Beleg öffnen
                                                </span>
                                            </a>

                                            @if($payout->status !== 'paid')
                                                <button type="button" wire:click="confirmPayoutPaid('{{ $payout->id }}')"
                                                        class="group/tip relative p-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-500/15 dark:hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30 transition-colors cursor-pointer"
                                                        title="Als bezahlt verbuchen">
                                                    <x-heroicon-o-check class="w-4 h-4" />
                                                    <span class="pointer-events-none absolute -top-8 right-0 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] font-semibold text-white opacity-0 group-hover/tip:opacity-100 transition-opacity z-30 shadow-xs">
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

        {{-- MODAL 1: EINLADUNGSLINK & 6-STELLIGER SICHERHEITSCODE (MATT & CLEAN) --}}
        @if($showInviteModal && $generatedInviteUrl)
            @php
                $readyMessage = "Hallo,\n\nhier ist deine persönliche Creator-Einladung für das Mein Seelenfunke Partnerprogramm ({$inviteCommissionValue}% Provision & {$inviteFollowerDiscount}% Rabattcode für deine Community):\n\nEinladungslink: {$generatedInviteUrl}\nVerifizierungscode: {$generatedInviteCode}";
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-black/75 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-xl w-full p-6 sm:p-8 shadow-xl relative space-y-5 transition-colors">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-4">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Creator Onboarding</span>
                            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                                Einladungslink &amp; Verifizierungscode
                            </h3>
                        </div>
                        <button type="button" wire:click="closeInviteModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div x-data="{ copiedLink: false, copiedCode: false, copiedAll: false }" class="space-y-3">
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Einladungslink (14 Tage gültig)</span>
                                <button type="button"
                                        @click="navigator.clipboard.writeText(@js($generatedInviteUrl)); copiedLink = true; setTimeout(() => copiedLink = false, 2000)"
                                        class="text-xs font-bold text-gray-900 dark:text-white hover:underline cursor-pointer">
                                    <span x-text="copiedLink ? 'Kopiert ✓' : 'Link kopieren'"></span>
                                </button>
                            </div>
                            <div class="text-xs font-mono text-gray-800 dark:text-gray-200 break-all bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2">
                                {{ $generatedInviteUrl }}
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-4">
                            <div>
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 block">6-stelliger Sicherheitscode</span>
                                <span class="text-2xl font-mono font-bold tracking-[0.25em] text-gray-900 dark:text-white mt-0.5 block">{{ $generatedInviteCode }}</span>
                            </div>
                            <button type="button"
                                    @click="navigator.clipboard.writeText(@js($generatedInviteCode)); copiedCode = true; setTimeout(() => copiedCode = false, 2000)"
                                    class="px-3.5 py-2 rounded-lg bg-white hover:bg-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white font-bold text-xs transition-colors cursor-pointer">
                                <span x-text="copiedCode ? 'Kopiert ✓' : 'Code kopieren'"></span>
                            </button>
                        </div>

                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($readyMessage)); copiedAll = true; setTimeout(() => copiedAll = false, 2500)"
                                class="w-full py-3 px-5 rounded-xl bg-gray-900 hover:bg-gray-800 dark:bg-[var(--theme-color)] dark:hover:opacity-90 text-white dark:text-gray-950 font-bold text-xs uppercase tracking-wider transition-colors flex items-center justify-center gap-2 cursor-pointer">
                            <span x-text="copiedAll ? 'Einladungs-Text kopiert ✓' : 'Einladungs-Text (Link + Code) kopieren'"></span>
                        </button>
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 space-y-3">
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400 block">
                            Bezeichnung &amp; Konditionen dieser Einladung
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Bezeichnung / Notiz</label>
                                <input type="text" wire:model="inviteLabel" placeholder="z. B. Für @lara.living"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Provision (%)</label>
                                <input type="number" wire:model="inviteCommissionValue" min="1" max="80"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1">Follower-Rabatt (%)</label>
                                <input type="number" wire:model="inviteFollowerDiscount" min="0" max="50"
                                       class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" wire:click="updateInviteSlotSettings"
                                    class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white text-xs font-semibold transition-colors cursor-pointer">
                                Speichern
                            </button>
                            <button type="button" wire:click="closeInviteModal"
                                    class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer">
                                Schließen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- MODAL 2: INFLUENCER KONFIGURIEREN / BEARBEITEN --}}
        @if($showConfigModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-black/75 backdrop-blur-xs p-4 overflow-y-auto">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-2xl w-full p-6 sm:p-8 shadow-xl relative space-y-5 my-8 transition-colors">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 dark:border-gray-800 pb-4">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Creator Verwaltung</span>
                            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                                Influencer konfigurieren ({{ $handle ?: $firstName }})
                            </h3>
                        </div>
                        <button type="button" wire:click="closeConfigModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 cursor-pointer">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <form wire:submit.prevent="saveInfluencer" class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Vorname *</label>
                                <input type="text" wire:model="firstName" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                @error('firstName') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Nachname *</label>
                                <input type="text" wire:model="lastName" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                @error('lastName') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Login E-Mail *</label>
                                <input type="email" wire:model="email" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                @error('email') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Plattform</label>
                                <select wire:model="primaryChannel" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none">
                                    <option value="instagram">Instagram</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="youtube">YouTube</option>
                                    <option value="pinterest">Pinterest</option>
                                    <option value="blog">Blog / Website</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">@Handle *</label>
                                <input type="text" wire:model="handle" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                @error('handle') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Kurzlink-Slug (/t/...) *</label>
                                <input type="text" wire:model="slug" class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                                @error('slug') <span class="text-red-600 text-[11px]">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 p-4 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">Provision (%)</label>
                                <input type="number" wire:model="commissionValue" min="1" max="80" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">Follower-Rabatt (%)</label>
                                <input type="number" wire:model="followerDiscountPercent" min="0" max="50" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">Rabattcode</label>
                                <input type="text" wire:model="customVoucherCode" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2 text-xs font-mono uppercase text-gray-900 dark:text-white font-bold outline-none">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">Cookie-Tage</label>
                                <input type="number" wire:model="cookieDays" min="1" max="90" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg px-3 py-2 text-xs font-mono text-gray-900 dark:text-white outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Interne Admin-Notizen</label>
                            <textarea wire:model="internalNotes" rows="2" placeholder="Interne Notizen zu Absprachen..."
                                      class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 focus:border-gray-900 rounded-lg px-3 py-2 text-xs text-gray-900 dark:text-white outline-none"></textarea>
                        </div>

                        <div class="flex justify-end gap-2.5 pt-2">
                            <button type="button" wire:click="closeConfigModal"
                                    class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-50 cursor-pointer">
                                Abbrechen
                            </button>
                            <button type="submit"
                                    class="px-5 py-2 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-[var(--theme-color)] text-white dark:text-gray-950 font-bold text-xs uppercase tracking-wider transition-colors cursor-pointer">
                                Speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- MODAL 3: EPC-GIROCODE & AUSZAHLUNGS-DETAILS --}}
        @if($showPayoutModal && $selectedPayoutData)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 dark:bg-black/75 backdrop-blur-xs p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-lg w-full p-6 sm:p-8 shadow-xl relative space-y-5 transition-colors">
                    <div class="flex items-start justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                        <div>
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Gutschrift (§ 14 Abs. 2 S. 2 UStG)</span>
                            <h3 class="text-xl font-serif font-bold text-gray-900 dark:text-white mt-0.5">
                                {{ $selectedPayoutData['credit_note_number'] }} &middot; {{ $selectedPayoutData['handle'] }}
                            </h3>
                        </div>
                        <button type="button" wire:click="closePayoutModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 space-y-2 text-xs">
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
                            <div class="flex justify-between text-gray-700 dark:text-gray-300">
                                <span>+20 % Gutschein-Bonus:</span>
                                <span class="font-mono font-semibold">+{{ $selectedPayoutData['bonus_formatted'] }}</span>
                            </div>
                        @else
                            <div class="flex justify-between text-gray-500">
                                <span>Umsatzsteuer ({{ $selectedPayoutData['tax_rate'] }} %):</span>
                                <span class="text-gray-900 dark:text-white font-mono">{{ $selectedPayoutData['tax_formatted'] }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between text-sm font-bold text-gray-900 dark:text-white pt-2 border-t border-gray-200 dark:border-gray-800">
                            <span>Auszahlungsbetrag:</span>
                            <span class="font-mono">{{ $selectedPayoutData['gross_formatted'] }}</span>
                        </div>
                    </div>

                    @if($selectedPayoutData['payout_method'] === 'bank_transfer')
                        <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex flex-col sm:flex-row items-center gap-4">
                            @if($giroCodeSvg)
                                <div class="bg-white p-2 rounded-lg border border-gray-200 shrink-0">
                                    {!! $giroCodeSvg !!}
                                </div>
                            @endif
                            <div class="space-y-1 text-xs">
                                <span class="text-xs font-bold text-gray-900 dark:text-white block">EPC-GiroCode (Banking-App Scan)</span>
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
                        <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 text-xs">
                            <span class="text-xs font-semibold text-gray-500 block">PayPal-Empfängeradresse</span>
                            <span class="text-sm font-mono font-bold text-gray-900 dark:text-white mt-1 block">{{ $selectedPayoutData['paypal_email'] }}</span>
                        </div>
                    @elseif($selectedPayoutData['gift_voucher_code'])
                        <div class="bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-xl p-4 text-xs">
                            <span class="text-xs font-semibold text-gray-500 block">Generierter Wertgutschein (+20 % Bonus)</span>
                            <span class="text-base font-mono font-bold text-gray-900 dark:text-white mt-1 block">{{ $selectedPayoutData['gift_voucher_code'] }}</span>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <a href="{{ route('admin.influencer.payout.pdf', ['payout' => $selectedPayoutData['id']]) }}" target="_blank"
                           class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-900 dark:text-white text-xs font-semibold">
                            PDF-Gutschrift herunterladen
                        </a>

                        @if($selectedPayoutData['status'] !== 'paid')
                            <button type="button" wire:click="confirmPayoutPaid('{{ $selectedPayoutData['id'] }}')"
                                    class="px-5 py-2 rounded-lg bg-gray-900 hover:bg-gray-800 dark:bg-emerald-500 text-white dark:text-gray-950 font-bold text-xs uppercase tracking-wider cursor-pointer">
                                Als bezahlt verbuchen
                            </button>
                        @else
                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Bereits bezahlt &amp; verbucht ✓</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
