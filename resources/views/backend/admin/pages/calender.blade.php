<x-layouts.backend_layout guard="admin">

    @section('content')

        <div class="h-[calc(100vh-200px)] mt-6 animate-fade-in-up">
            @livewire('shop.management.management-calender')
        </div>

    @endsection

</x-layouts.backend_layout>
