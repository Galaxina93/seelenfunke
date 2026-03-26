<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-8 relative z-10">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @include('livewire.shop.management.management-analytics-partials.header')
    @include('livewire.shop.management.management-analytics-partials.master_scores')

    @include('livewire.shop.management.management-analytics-partials.profit')
    @include('livewire.shop.management.management-analytics-partials.charts')

    <div class="border-t border-gray-800 pt-8 mt-8">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @include('livewire.shop.management.management-analytics-partials.traffic')
        </div>
    </div>

    <div class="border-t border-gray-800 pt-8 mt-8">
        @include('livewire.shop.management.management-analytics-partials.customers')
    </div>

    @include('livewire.shop.management.management-analytics-partials.scripts')

</div>
