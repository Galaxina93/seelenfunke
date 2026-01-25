<x-layouts.backend_layout guard="admin">

    @section('content')

        @livewire('global.crud.admin-crud')
        @livewire('global.crud.customer-crud')
        @livewire('global.crud.employee-crud')

    @endsection

</x-layouts.backend_layout>
