<x-sections.vertical-nav>

    {{-- GRUPPE 1: Dashboard --}}
    <li>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/admin/dashboard" title="Dashboard" pageName="dashboard" icon="home" />
        </ul>
    </li>

    {{-- GRUPPE 2: Shopverwaltung --}}
    <li>
        <div class="text-xs font-semibold leading-6 text-gray-200 uppercase tracking-wider mb-2">Shopverwaltung</div>
        <ul role="list" class="-mx-2 space-y-1">

            {{-- Funki --}}
            <x-forms.list-item route="/admin/funki" title="Funki" pageName="funki" icon="bolt" />

            {{-- Produkte & Marketing Dropdown --}}
            <li x-data="{ open: true }">
                <button @click="open = !open" class="flex items-center w-full text-left gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold text-white hover:bg-white/10 transition">
                    <x-heroicon-o-cube class="h-6 w-6 shrink-0 text-white" />
                    <span>Produkte & Marketing</span>
                    <x-heroicon-m-chevron-right class="ml-auto h-5 w-5 transform transition-transform duration-200 text-white" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 px-2 space-y-1">
                    {{-- HIER WAREN DIE FEHLER: Icons wieder hinzugefügt --}}
                    <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="cube" />
                    <x-forms.list-item route="/admin/blog" title="Blog" pageName="blog" icon="document-text" />
                </ul>
            </li>

            {{-- Bestellungen Dropdown --}}
            <li x-data="{ open: true }">
                <button @click="open = !open" class="flex items-center w-full text-left gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold text-white hover:bg-white/10 transition">
                    <x-heroicon-o-shopping-bag class="h-6 w-6 shrink-0 text-white" />
                    <span>Bestellungen</span>
                    <x-heroicon-m-chevron-right class="ml-auto h-5 w-5 transform transition-transform duration-200 text-white" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 px-2 space-y-1">
                    {{-- HIER WAREN DIE FEHLER: Icons wieder hinzugefügt --}}
                    <x-forms.list-item route="/admin/orders" title="Alle Bestellungen" pageName="orders" icon="shopping-bag" />
                    <x-forms.list-item route="/admin/quote-requests" title="Angebote" pageName="quote-requests" icon="clipboard-document-list" />
                </ul>
            </li>

            {{-- Finanzmanager Dropdown --}}
            <li x-data="{ open: true }">
                <button @click="open = !open" class="flex items-center w-full text-left gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold text-white hover:bg-white/10 transition">
                    <x-heroicon-o-shopping-bag class="h-6 w-6 shrink-0 text-white" />
                    <span>Finanzen</span>
                    <x-heroicon-m-chevron-right class="ml-auto h-5 w-5 transform transition-transform duration-200 text-white" ::class="open ? 'rotate-90' : ''" />
                </button>
                <ul x-show="open" x-collapse class="mt-1 px-2 space-y-1">
                    {{-- HIER WAREN DIE FEHLER: Icons wieder hinzugefügt --}}
                    <x-forms.list-item route="/admin/financial-evaluation" title="Auswertung" pageName="financial-evaluation" icon="calculator" />
                    <x-forms.list-item route="/admin/financial-categories-special-editions" title="Variable Kosten" pageName="financial-categories-special-editions" icon="rectangle-stack" />
                    <x-forms.list-item route="/admin/financial-contracts-groups" title="Fixkosten" pageName="financial-contracts-groups" icon="document-check" />
                    <x-forms.list-item route="/admin/invoices" title="Rechnungen" pageName="invoices" icon="document-text" />
                </ul>
            </li>

            {{-- Versand --}}
            <x-forms.list-item route="/admin/shipping" title="Versand & Logistik" pageName="shipping" icon="truck" />

            {{-- Shop Einstellungen --}}
            <x-forms.list-item route="/admin/configuration" title="Einstellungen" pageName="configuration" icon="cog" />

        </ul>
    </li>

    {{-- GRUPPE 3: System --}}
    <li>
        <div class="text-xs font-semibold leading-6 text-gray-200 uppercase tracking-wider mb-2 mt-6">System</div>
        <ul role="list" class="-mx-2 space-y-1">
            <x-forms.list-item route="/admin/user-management" title="Benutzer" pageName="users" icon="users" />
            <x-forms.list-item route="/admin/right-management" title="Rechte & Rollen" pageName="rights" icon="shield-check" />
            <x-forms.list-item route="/admin/profile" title="Mein Profil" pageName="profile" icon="user-circle" />
        </ul>
    </li>

</x-sections.vertical-nav>
