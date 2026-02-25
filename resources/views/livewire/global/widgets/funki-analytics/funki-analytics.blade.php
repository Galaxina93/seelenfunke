<div class="p-4 md:p-6 bg-transparent min-h-screen space-y-8 relative z-10">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @include('livewire.global.widgets.funki-analytics.partials.header')
    @include('livewire.global.widgets.funki-analytics.partials.podest')
    @include('livewire.global.widgets.funki-analytics.partials.health')
    @include('livewire.global.widgets.funki-analytics.partials.kpis')
    @include('livewire.global.widgets.funki-analytics.partials.charts')

    <div class="border-t border-gray-800 pt-8 mt-8">
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            @include('livewire.global.widgets.funki-analytics.partials.traffic')
        </div>
        @include('livewire.global.widgets.funki-analytics.partials.history')
    </div>

    @include('livewire.global.widgets.funki-analytics.partials.scripts')
</div>
