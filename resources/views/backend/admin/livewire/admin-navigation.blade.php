{{-- FILE: resources\views\backend\admin\livewire\admin-navigation.blade.php --}}
<x-sections.vertical-nav>
    @php
        $currentPage = basename(request()->path());
        $isFunkiActive = in_array($currentPage, ['funki', 'funki-routine', 'funki-todos', 'funki-kalender', 'funki-company-map']);
        $isShopActive = in_array($currentPage, ['products', 'reviews', 'blog']);
        $isOrderActive = in_array($currentPage, ['orders', 'quote-requests', 'invoices']);
        $isFinanceActive = in_array($currentPage, ['financial-evaluation', 'financial-categories-special-editions', 'financial-contracts-groups']);
    @endphp

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
                    <x-forms.list-item route="/admin/funki" title="Funki" pageName="funki" icon="sparkles" />
                    <x-forms.list-item route="/admin/funki-routine" title="Routine" pageName="funki-routine" icon="arrow-path" />
                    <x-forms.list-item route="/admin/funki-todos" title="Todos" pageName="funki-todos" icon="check-circle" />
                    <x-forms.list-item route="/admin/funki-kalender" title="Kalender" pageName="funki-kalender" icon="calendar-days" />
                    <x-forms.list-item route="/admin/funki-company-map" title="Map" pageName="funki-company-map" icon="map" />
                </ul>
            </li>

            <x-forms.list-item route="/admin/knowledge_base" title="Gehirn" pageName="knowledge_base" icon="book-open" />

        </ul>
    </li>

    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">Shopverwaltung</div>
        <ul role="list" class="-mx-2 space-y-1">
            <li x-data="{ open: {{ $isShopActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isShopActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-wrench-screwdriver class="h-5 w-5 shrink-0 transition-colors {{ $isShopActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Produkte & Marketing</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="cube" />
                    <x-forms.list-item route="/admin/product-templates" title="Vorlagen" pageName="product-templates" icon="clipboard-document-list" />
                    <x-forms.list-item route="/admin/reviews" title="Bewertungen" pageName="reviews" icon="star" />
                    <x-forms.list-item route="/admin/blog" title="Blog" pageName="blog" icon="document-text" />
                </ul>
            </li>

            <li x-data="{ open: {{ $isOrderActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isOrderActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-shopping-bag class="h-5 w-5 shrink-0 transition-colors {{ $isOrderActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Bestellungen</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/orders" title="Alle Bestellungen" pageName="orders" icon="shopping-cart" />
                    <x-forms.list-item route="/admin/quote-requests" title="Angebote" pageName="quote-requests" icon="clipboard-document-list" />
                    <x-forms.list-item route="/admin/invoices" title="Rechnungen" pageName="invoices" icon="document-text" />
                </ul>
            </li>

            <li x-data="{ open: {{ $isFinanceActive ? 'true' : 'false' }} }">
                <button @click="open = !open" class="group flex items-center w-full text-left gap-x-3 rounded-xl p-2.5 text-sm font-semibold transition-all duration-300 {{ $isFinanceActive ? 'text-white' : 'text-gray-400 hover:text-white hover:bg-white/5' }}">
                    <x-heroicon-o-currency-dollar class="h-5 w-5 shrink-0 transition-colors {{ $isFinanceActive ? 'text-primary' : 'text-gray-500 group-hover:text-white' }}" />
                    <span class="flex-1">Finanzen</span>
                    <x-heroicon-m-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-300" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 space-y-1 pl-3 ml-3 border-l border-white/10">
                    <x-forms.list-item route="/admin/financial-evaluation" title="Auswertung" pageName="financial-evaluation" icon="chart-bar" />
                    <x-forms.list-item route="/admin/financial-categories-special-editions" title="Variable Kosten" pageName="financial-categories-special-editions" icon="rectangle-stack" />
                    <x-forms.list-item route="/admin/financial-contracts-groups" title="Fixkosten" pageName="financial-contracts-groups" icon="document-check" />
                </ul>
            </li>

            <x-forms.list-item route="/admin/shipping" title="Versand & Logistik" pageName="shipping" icon="truck" />
            <x-forms.list-item route="/admin/configuration" title="Einstellungen" pageName="configuration" icon="cog-8-tooth" />
        </ul>
    </li>

    <li>
        <div class="text-[10px] font-black text-gray-500 uppercase tracking-widest mb-3 ml-2 mt-6">System</div>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/admin/user-management" title="Benutzer" pageName="user-management" icon="users" />
            <x-forms.list-item route="/admin/right-management" title="Rechte & Rollen" pageName="right-management" icon="shield-check" />
        </ul>
    </li>
</x-sections.vertical-nav>
