<div class="min-h-screen bg-gray-50 pb-20 font-sans text-gray-800">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- Success Notification --}}
    @if (session()->has('success'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 animate-fade-in-up">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Sticky Header mit Datumswähler --}}
    <div
        class="bg-white/90 backdrop-blur-md shadow-sm border-b border-gray-100 sticky top-0 z-30 transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">

            {{-- Titel --}}
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3 tracking-tight">
                <div class="p-2 bg-primary/10 rounded-xl text-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span>Finanzmanager</span>
            </h1>

            {{-- Datumswähler Dropdowns --}}
            <div class="flex items-center gap-3 bg-white p-1 rounded-xl border border-gray-200 shadow-sm">

                {{-- Monat --}}
                <div class="relative group">
                    <select wire:model.live="selectedMonth"
                            class="appearance-none bg-transparent pl-4 pr-10 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer border-none outline-none w-32">
                        @foreach(range(1,12) as $m)
                            <option
                                value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->locale('de')->monthName }}</option>
                        @endforeach
                    </select>
                    <div
                        class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400 group-hover:text-primary transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>

                <div class="h-6 w-px bg-gray-200"></div>

                {{-- Jahr --}}
                <div class="relative group">
                    <select wire:model.live="selectedYear"
                            class="appearance-none bg-transparent pl-4 pr-10 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer border-none outline-none w-24">
                        @foreach(range(date('Y')-2, date('Y')+2) as $y)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endforeach
                    </select>
                    <div
                        class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none text-gray-400 group-hover:text-primary transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 mt-8 space-y-8">

        {{-- 1. Dashboard Stats --}}
        @include('livewire.shop.financial.partials.section_header_stats')

        {{-- Sektion: Sonderausgaben --}}
        @include('livewire.shop.financial.partials.section_categories_special_issues')

        {{-- Sektion: Jahresübersicht --}}
        @include('livewire.shop.financial.partials.section_yearly_overview')

        {{-- Sektion: Verträge & Gruppen --}}
        @include('livewire.shop.financial.partials.section_groups')

    </div>

    {{-- Charts Script --}}
    @include('livewire.shop.financial.partials.chart_scripts')

</div>
