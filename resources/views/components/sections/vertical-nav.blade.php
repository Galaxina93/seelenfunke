<nav class="flex flex-1 flex-col">
    <ul role="list" class="flex flex-1 flex-col gap-y-7">

        {{-- Dashboard / Start --}}
        <li>
            <ul role="list" class="-mx-2 space-y-1">
                {{ $slot }}
            </ul>
        </li>

        {{-- HAUPTBEREICH: Shopverwaltung --}}
        <li>
            {{-- Hauptüberschrift --}}
            <div class="text-sm font-bold leading-6 text-gray-500 mb-2">Shopverwaltung</div>

            <ul role="list" class="space-y-4"> {{-- Abstand zwischen den Gruppen --}}

                {{-- Untergruppe: Produkte & Marketing --}}
                <li>
                    <div class="text-xs font-semibold leading-6 text-gray-400">Produkte & Marketing</div>
                    <ul role="list" class="-mx-2 mt-1 space-y-1">
                        @yield('shop_products_marketing')
                    </ul>
                </li>

                {{-- Untergruppe: Bestellungen & Rechnungen --}}
                <li>
                    <div class="text-xs font-semibold leading-6 text-gray-400">Bestellungen & Rechnungen</div>
                    <ul role="list" class="-mx-2 mt-1 space-y-1">
                        @yield('shop_orders_invoices')
                    </ul>
                </li>

                {{-- Untergruppe: Versand & Logistik --}}
                <li>
                    <div class="text-xs font-semibold leading-6 text-gray-400">Versand & Logistik</div>
                    <ul role="list" class="-mx-2 mt-1 space-y-1">
                        @yield('shop_shipping')
                    </ul>
                </li>

            </ul>
        </li>

        {{-- HAUPTBEREICH: Administrativ (unverändert beibehalten) --}}
        <li>
            <div class="text-sm font-bold leading-6 text-gray-500 mb-2">@yield('special_title')</div>
            <ul role="list" class="-mx-2 mt-1 space-y-1">
                @yield('special_slots')
            </ul>
        </li>

    </ul>
</nav>
