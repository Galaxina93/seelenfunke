<x-sections.vertical-nav>
    <x-forms.list-item route="/admin/dashboard" title="Dashboard" pageName="dashboard" icon="home" />

    @section('special_title')
        Administrativ
    @endsection

    @section('special_slots')

        <x-forms.list-item route="/admin/right-management" title="Rechte" pageName="right-management" icon="lock-closed" />
        <x-forms.list-item route="/admin/user-management" title="Benutzer" pageName="user-management" icon="users" />

    @endsection


    @section('shop_title')
        Shop
    @endsection

    @section('shop_slots')

        <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="cube" />
        <x-forms.list-item route="/admin/newsletter" title="Newsletter" pageName="newsletter" icon="envelope" />
        <x-forms.list-item route="/admin/voucher" title="Gutscheine" pageName="voucher" icon="ticket" />
        <x-forms.list-item route="/admin/invoices" title="Rechnungen" pageName="invoice" icon="document-text" />
        <x-forms.list-item route="/admin/orders" title="Bestellungen" pageName="orders" icon="shopping-bag" />
        <x-forms.list-item route="/admin/quote-requests" title="Angebotsanfragen" pageName="quote-requests" icon="clipboard-document-list" />


    @endsection

</x-sections.vertical-nav>

