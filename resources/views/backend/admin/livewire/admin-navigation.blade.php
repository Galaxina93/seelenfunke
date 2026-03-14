<x-sections.vertical-nav>
    @php
        $currentPage = basename(request()->path());

        $isFunkiActive = in_array($currentPage, ['funki-routine', 'tasks', 'funki-kalender']);

        // Neu aufgeteilt in Produkte und Marketing
        $isProductsActive = in_array($currentPage, ['products', 'product-templates', 'reviews']);
        $isMarketingActive = in_array($currentPage, ['newsletter', 'voucher', 'blog']);

        $isOrderActive = in_array($currentPage, ['orders', 'quote-requests']);
        $isFinanceActive = in_array($currentPage, ['financial-evaluation', 'financial-fix-costs', 'financial-variable-costs', 'financial-tax', 'financial-banks', 'credit-management', 'invoices']);
        $isSystemFunkiraActive = in_array($currentPage, ['funkira-methods', 'funkira-log', 'knowledge_base', 'funkira-genui', 'person-profiles']);
    @endphp

    {{--Funkis Zentrale--}}
    <li>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/admin/dashboard" title="Dashboard" pageName="dashboard" icon="home" />

            <li x-data="{ open: {{ $isFunkiActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isFunkiActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-bolt class="h-5 w-5 shrink-0 transition-colors {{ $isFunkiActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Funkis Zentrale</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/funki-routine" title="Routine" pageName="funki-routine" icon="arrow-path" />
                    <x-forms.list-item route="/admin/tasks" title="Aufgaben" pageName="tasks" icon="check-circle" />
                    <x-forms.list-item route="/admin/funki-kalender" title="Kalender" pageName="funki-kalender" icon="calendar-days" />
                </ul>
            </li>
        </ul>
    </li>

    {{--Shopverwaltung--}}
    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">Shopverwaltung</div>
        <ul role="list" class="-mx-2 space-y-1">

            {{-- Produkte --}}
            <li x-data="{ open: {{ $isProductsActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isProductsActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-cube class="h-5 w-5 shrink-0 transition-colors {{ $isProductsActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Produkte</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="cube" />
                    <x-forms.list-item route="/admin/product-templates" title="Vorlagen" pageName="product-templates" icon="clipboard-document-list" />
                    <x-forms.list-item route="/admin/reviews" title="Bewertungen" pageName="reviews" icon="star" />
                </ul>
            </li>

            {{-- Marketing --}}
            <li x-data="{ open: {{ $isMarketingActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isMarketingActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-at-symbol class="h-5 w-5 shrink-0 transition-colors {{ $isMarketingActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Marketing</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/newsletter" title="Newsletter" pageName="newsletter" icon="newspaper" />
                    <x-forms.list-item route="/admin/voucher" title="Gutscheine" pageName="voucher" icon="gift" />
                    <x-forms.list-item route="/admin/blog" title="Blogeinträge" pageName="blog" icon="document-text" />
                </ul>
            </li>

            {{-- Bestellungen --}}
            <li x-data="{ open: {{ $isOrderActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isOrderActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-shopping-bag class="h-5 w-5 shrink-0 transition-colors {{ $isOrderActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Bestellungen</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/orders" title="Bestellungen" pageName="orders" icon="shopping-cart" />
                    <x-forms.list-item route="/admin/quote-requests" title="Angebote" pageName="quote-requests" icon="clipboard-document-list" />
                </ul>
            </li>

            {{-- Buchhaltung --}}
            <li x-data="{ open: {{ $isFinanceActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isFinanceActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-currency-dollar class="h-5 w-5 shrink-0 transition-colors {{ $isFinanceActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Buchhaltung</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/financial-banks" title="Banken" pageName="financial-banks" icon="scale" />
                    <x-forms.list-item route="/admin/financial-tax" title="Steuern" pageName="financial-tax" icon="banknotes" />
                    <x-forms.list-item route="/admin/financial-fix-costs" title="Fixkosten" pageName="financial-fix-costs" icon="banknotes" />
                    <x-forms.list-item route="/admin/financial-evaluation" title="Auswertung" pageName="financial-evaluation" icon="chart-bar" />
                    <x-forms.list-item route="/admin/credit-management" title="Gutschriften" pageName="credit-management" icon="document-minus" />
                    <x-forms.list-item route="/admin/invoices" title="Rechnungen" pageName="invoices" icon="document-text" />
                    <x-forms.list-item route="/admin/financial-variable-costs" title="Variable Kosten" pageName="financial-variable-costs" icon="banknotes" />
                    <x-forms.list-item route="/admin/financial-liquidity-planning" title="Liquiditätsplanung" pageName="financial-liquidity-planning" icon="shield-check" />

                </ul>
            </li>

            <x-forms.list-item route="/admin/configuration" title="Einstellungen" pageName="configuration" icon="cog-8-tooth" />
        </ul>
    </li>

    {{--SYSTEM--}}
    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">System</div>
        <ul role="list" class="-mx-2 space-y-1">

            <li x-data="{ open: {{ $isSystemFunkiraActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isSystemFunkiraActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-cpu-chip class="h-5 w-5 shrink-0 transition-colors {{ $isSystemFunkiraActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Funkira</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/funkira-log" title="Log" pageName="funkira-log" icon="document-text" />
                    <x-forms.list-item route="/admin/knowledge_base" title="Wiki" pageName="knowledge_base" icon="book-open" />
                    <x-forms.list-item route="/admin/funkira-methods" title="Fähigkeiten" pageName="funkira-methods" icon="chart-bar-square" />
                    <x-forms.list-item route="/admin/funkira-genui" title="UI Visualisierung" pageName="funkira-genui" icon="window" />
                    <x-forms.list-item route="/admin/person-profiles" title="Personen & Familie" pageName="person-profiles" icon="users" />
                </ul>
            </li>


            {{-- Ticketsystem inkl. rotem Benachrichtigungspunkt --}}
            <li x-data="{ unread: hasUnreadSupport }"
                @admin-ticket-badge-update.window="unread = true"
                @clear-admin-ticket-badge.window="unread = false">

                <a href="/admin/tickets" @click="unread = false; hasUnreadSupport = false" class="group flex items-center gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $currentPage === 'tickets' ? 'bg-primary/10 text-primary shadow-[0_0_15px_rgba(197,160,89,0.15)]' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <div class="relative">
                        <x-heroicon-o-ticket class="w-5 h-5 shrink-0 transition-transform duration-300 {{ $currentPage === 'tickets' ? 'text-primary' : 'text-gray-500 group-hover:text-white group-hover:scale-110' }}" />

                        {{-- Nutzt nun die isolierte 'unread' Variable, die sich sofort aktualisiert --}}
                        <span x-show="unread" style="display: none;" class="absolute -top-1 -right-1 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)]"></span>
                        </span>
                    </div>
                    <span class="truncate">Tickets</span>
                </a>
            </li>

            <x-forms.list-item route="/admin/user-management" title="Benutzer" pageName="user-management" icon="users" />
            <x-forms.list-item route="/admin/right-management" title="Rechte & Rollen" pageName="right-management" icon="shield-check" />

            <x-forms.list-item route="/admin/company-map" title="Architektur-Map" pageName="company-map" icon="map" />

        </ul>
    </li>

</x-sections.vertical-nav>
