<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-8 relative z-10">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @include('livewire.shop.master.master-analytics-partials.header')

    @include('livewire.shop.master.master-analytics-partials.master-mission-banner')

    @include('livewire.shop.master.master-analytics-partials.master_scores')

    <livewire:shop.master.master-shop-capacity />
    <livewire:shop.master.master-storage-capacity />

    @include('livewire.shop.master.master-analytics-partials.profit')
    @include('livewire.shop.master.master-analytics-partials.charts')

    <div class="border-t border-gray-800 pt-8 mt-8">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @include('livewire.shop.master.master-analytics-partials.traffic')
        </div>
    </div>

    <div class="border-t border-gray-800 pt-8 mt-8">
        @include('livewire.shop.master.master-analytics-partials.customers')
    </div>

    @include('livewire.shop.master.master-analytics-partials.scripts')

</div>
