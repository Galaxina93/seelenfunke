<div class="p-6 space-y-6">
    {{-- Begrüßung --}}
    <div class="bg-white dark:bg-gray-900 shadow rounded-2xl p-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">
            Willkommen zurück, {{ auth()->user()->first_name }}!
        </h1>
        <p class="text-gray-600 dark:text-gray-300 mt-2">
            mein-seelenfunke wünscht dir einen wunderschönen Tag!
        </p>
    </div>

    {{-- Statistiken --}}
    @livewire("admin.evaluation-dashboard")

</div>
