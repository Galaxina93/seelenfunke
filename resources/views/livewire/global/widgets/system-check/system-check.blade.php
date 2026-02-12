<div class="p-6 bg-slate-50 min-h-screen">
    {{-- Notwendig für Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="max-w-7xl mx-auto space-y-8">

        {{-- Header --}}
        <div>
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight flex items-center gap-3">
                <i class="solar-pulse-bold-duotone text-indigo-500"></i>
                System & Health Check
            </h1>
            <p class="text-slate-500 mt-2">Zentrale Überwachung der Systemintegrität und Analysen.</p>
        </div>

        {{-- SECTION 1: BUSINESS LOGIC CHECKS --}}
        @include('livewire.global.widgets.system-check.partials.business_logic_checks')

        {{-- SECTION 2: ANALYTICS & STATS --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            {{-- 2.1 Backend Stats --}}
            @include('livewire.global.widgets.system-check.partials.backend_stats')

            {{-- 2.2 Frontend Analytics Chart --}}
            @include('livewire.global.widgets.system-check.partials.frontend_analytics_chart')

        </div>

        {{-- SECTION 3: DETAIL TABLES --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="{ showFailed: @entangle('showFailedLogins'), showLogins: @entangle('showFullLogins') }">

            {{-- 3.1 Login History --}}
            @include('livewire.global.widgets.system-check.partials.login_history')

            {{-- 3.2 Failed Logins --}}
            @include('livewire.global.widgets.system-check.partials.failed_logins')

        </div>
    </div>

    {{-- Chart Initialization --}}
    @include('livewire.global.widgets.system-check.partials.chart_scripts')
</div>
