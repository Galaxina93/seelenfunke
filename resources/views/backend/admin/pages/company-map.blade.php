<x-layouts.backend_layout guard="admin">
    @section('content')

        <div class="h-[calc(100vh-140px)] min-h-[600px] mt-4 sm:mt-6 px-2 sm:px-6 lg:px-8 max-w-[1800px] mx-auto animate-fade-in-up">
            @livewire('shop.map.company-map')
        </div>
    @endsection
</x-layouts.backend_layout>
