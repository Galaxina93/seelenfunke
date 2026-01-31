<x-sections.vertical-nav>
    {{-- Dashboard (Slot) --}}
    <x-forms.list-item route="/admin/dashboard" title="Dashboard" pageName="dashboard" icon="home" />


    {{-- GRUPPE 1: Produkte & Marketing --}}
    @section('shop_products_marketing')
        <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="cube" />
        <x-forms.list-item route="/admin/newsletter" title="Newsletter" pageName="newsletter" icon="envelope" />
        <x-forms.list-item route="/admin/voucher" title="Gutscheine" pageName="voucher" icon="ticket" />
    @endsection


    {{-- GRUPPE 2: Bestellungen & Rechnungen --}}
    @section('shop_orders_invoices')
        <x-forms.list-item route="/admin/orders" title="Bestellungen" pageName="orders" icon="shopping-bag" />
        <x-forms.list-item route="/admin/invoices" title="Rechnungen" pageName="invoice" icon="document-text" />
        <x-forms.list-item route="/admin/quote-requests" title="Angebotsanfragen" pageName="quote-requests" icon="clipboard-document-list" />
    @endsection


    {{-- GRUPPE 3: Versand & Logistik --}}
    @section('shop_shipping')
        <x-forms.list-item route="/admin/shipping" title="Versand" pageName="shipping" icon="truck" />
    @endsection


    {{-- ADMINISTRATIV (unverändert) --}}
    @section('special_title')
        Administrativ
    @endsection

    @section('special_slots')
        <x-forms.list-item route="/admin/right-management" title="Rechte" pageName="right-management" icon="lock-closed" />
        <x-forms.list-item route="/admin/user-management" title="Benutzer" pageName="user-management" icon="users" />
    @endsection

</x-sections.vertical-nav>
