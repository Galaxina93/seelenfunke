<div class="relative p-8">

    {{-- KPI Header --}}
    @include('livewire.shop.newsletter.partials.kpi_header')

    {{-- Tabs --}}
    <div class="flex justify-center">
        <div class="bg-gray-100/50 p-1.5 rounded-2xl my-6 border border-gray-200 shadow-inner inline-flex gap-1">
            <button wire:click="$set('activeTab', 'calendar')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'calendar', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'calendar'])>Kalender</button>
            <button wire:click="$set('activeTab', 'archive')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'archive', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'archive'])>Archiv</button>
            <button wire:click="$set('activeTab', 'subscribers')" @class(['px-8 py-3 rounded-xl text-sm font-bold transition-all', 'bg-white text-orange-600 shadow-md border border-gray-100' => $activeTab === 'subscribers', 'text-gray-500 hover:text-gray-700' => $activeTab !== 'subscribers'])>Empfänger</button>
        </div>
    </div>

    {{-- MAIN CONTENT AREA --}}

    {{-- 1. KALENDER VIEW --}}
    @if($activeTab === 'calendar')
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-100 overflow-hidden animate-fade-in">
            {{-- Toolbar --}}
            @include('livewire.shop.newsletter.partials.toolbar')

            <div class="p-8">
                @if($calendarView === 'year')
                    @include('livewire.shop.newsletter.partials.yearly_table')
                @else
                    @include('livewire.shop.newsletter.partials.month_grid')
                @endif
            </div>
        </div>

        {{-- 2. ARCHIV VIEW --}}
    @elseif($activeTab === 'archive')
        @include('livewire.shop.newsletter.partials.archive_view')

        {{-- 3. ABONNENTEN --}}
    @elseif($activeTab === 'subscribers')
        @include('livewire.shop.newsletter.partials.subscribers_table')
    @endif

    {{-- EDIT MODAL (Vollständig modernisiert) --}}
    @include('livewire.shop.newsletter.partials.edit_modal')

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        @keyframes zoom-in { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .animate-zoom-in { animation: zoom-in 0.3s ease-out; }
    </style>
</div>
