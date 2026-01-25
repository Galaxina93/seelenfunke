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

        <x-forms.list-item route="/admin/products" title="Produkte" pageName="products" icon="lock-closed" />

    @endsection

</x-sections.vertical-nav>

