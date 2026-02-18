<div class="min-h-screen bg-gray-50 pb-20">

    {{-- HEADER & TABS --}}
    <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-4 gap-4">
                <div class="flex items-center gap-4">
                    <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-12 h-12 object-contain" alt="Funki">
                    <div>
                        <h1 class="text-xl font-serif font-bold text-gray-900">Funkis Zentrale</h1>
                        <p class="text-xs text-gray-500">Dein digitaler Assistent.</p>
                    </div>
                </div>

                {{-- TAB NAVIGATION --}}
                <div class="flex p-1 bg-gray-100 rounded-xl">
                    <button wire:click="setTab('instructions')"
                            class="px-6 py-2 rounded-lg text-sm font-bold transition-all {{ $activeTab === 'instructions' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        📋 Arbeitsanweisungen
                    </button>
                    <button wire:click="setTab('automation')"
                            class="px-6 py-2 rounded-lg text-sm font-bold transition-all {{ $activeTab === 'automation' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                        🤖 Automatisierungen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in">

        {{-- ========================================================================= --}}
        {{-- TAB 1: ARBEITSANWEISUNGEN (DAS HERZSTÜCK) --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'instructions')
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 items-start">
                {{-- LINKER BEREICH: FUNKI SPRECHBLASE (Wie bisher) --}}
                <div>
                    @include('livewire.shop.funki.partials.instructions')
                </div>

                {{-- RECHTER BEREICH: Funkitodo --}}
                <div class="sticky top-24">
                    @livewire('shop.funki.funki-to-do')
                </div>
            </div>

        {{-- ========================================================================= --}}
        {{-- TAB 2: AUTOMATISIERUNGEN (ALTE LOGIK) --}}
        {{-- ========================================================================= --}}
        @elseif($activeTab === 'automation')
            @include('livewire.shop.funki.partials.automation')
        @endif

    </div>
</div>
