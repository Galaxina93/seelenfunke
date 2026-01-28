<x-layouts.backend_layout guard="customer">

    @section('content')
        <div class="py-8 px-4 sm:px-6 lg:px-8">

            {{-- Greedings --}}
            <div class="bg-white dark:bg-gray-900 shadow rounded-2xl p-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
                    Willkommen zurück, {{ auth()->user()->first_name }}!
                </h1>
                <p class="text-gray-600 dark:text-gray-300 mt-2">
                    mein-seelenfunke wünscht dir einen wunderschönen Tag!
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pb-12 mt-8">

                <div class="lg:col-span-1 space-y-8">

                    @livewire('customer.orders')

                </div>

            </div>

        </div>
    @endsection

</x-layouts.backend_layout>
