<x-layouts.backend_layout guard="customer">

    @section('content')
        {{-- Wir entfernen die max-w-7xl Beschränkung für ein Full-Width Gaming Erlebnis --}}
        <div class="min-h-screen bg-gray-950">
            @livewire('customer.funki-shop-component')
        </div>
    @endsection

</x-layouts.backend_layout>
