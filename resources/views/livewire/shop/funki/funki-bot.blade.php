<div class="min-h-screen bg-gray-50 pb-20">

    {{-- HEADER & TABS --}}
    <div class="bg-white border-b border-gray-100 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center py-4 gap-4">
                {{-- Logo & Titel --}}
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <img src="{{ asset('images/projekt/funki/funki_selfie.png') }}" class="w-12 h-12 object-contain" alt="Funki">
                        <div class="absolute -bottom-1 -right-1 w-3 h-3 bg-green-500 border-2 border-white rounded-full animate-pulse"></div>
                    </div>
                    <div>
                        <h1 class="text-xl font-serif font-bold text-gray-900">Funkis Zentrale</h1>
                        <p class="text-xs text-gray-500">Dein Autopilot für den Tag.</p>
                    </div>
                </div>

                {{-- TAB NAVIGATION --}}
                <div class="flex p-1 bg-gray-100 rounded-xl overflow-x-auto custom-scrollbar">
                    @foreach([
                        'instructions' => ['icon' => '📋', 'label' => 'Fokus'],
                        'routine'      => ['icon' => '⏰', 'label' => 'Routine'],
                        'todo'         => ['icon' => '✅', 'label' => 'ToDos'],
                        'automation'   => ['icon' => '🤖', 'label' => 'Auto-Pilot']
                    ] as $key => $tab)
                        <button wire:click="setTab('{{ $key }}')"
                                class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-all
                                {{ $activeTab === $key ? 'bg-white text-gray-900 shadow-sm ring-1 ring-black/5' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200/50' }}">
                            <span>{{ $tab['icon'] }}</span>
                            <span>{{ $tab['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-fade-in">

        {{-- ========================================================================= --}}
        {{-- TAB 1: ARBEITSANWEISUNGEN (DAS GEHIRN)                                    --}}
        {{-- ========================================================================= --}}
        @if($activeTab === 'instructions')
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8 items-start">

                {{-- LINKER BEREICH: DIE KLARE ANSAGE (Ultimate Command) --}}
                <div>
                    {{-- Bindet die Sprechblase mit getUltimateCommand Daten ein --}}
                    @include('livewire.shop.funki.partials.instructions')
                </div>

                {{-- RECHTER BEREICH: WARTESCHLANGE (Lückenfüller) --}}
                <div class="sticky top-24">
                    {{-- Die FunkiToDo-Liste immer griffbereit --}}
                    @livewire('shop.funki.funki-to-do')
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- TAB 2: TAGESROUTINE (BIO-RHYTHMUS)                                        --}}
            {{-- ========================================================================= --}}
        @elseif($activeTab === 'routine')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                {{-- Links: Die Verwaltungs-Komponente --}}
                @livewire('shop.funki.day-routine')

                {{-- Rechts: Motivation & Status --}}
                <div class="bg-gradient-to-br from-indigo-50 to-white rounded-[2.5rem] p-10 border border-indigo-100 flex flex-col justify-center items-center text-center shadow-sm relative overflow-hidden">
                    {{-- Deko Hintergrund --}}
                    <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-100/50 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>

                    <img src="{{ asset('images/projekt/funki/funki_yoga.png') }}"
                         class="w-48 h-48 object-contain mb-8 drop-shadow-xl relative z-10 hover:scale-105 transition-transform duration-500"
                         alt="Funki Yoga">

                    <h3 class="text-2xl font-serif font-bold text-indigo-900 relative z-10">Dein Rhythmus, dein Erfolg.</h3>
                    <p class="text-indigo-600/80 mt-4 max-w-sm text-sm font-medium leading-relaxed relative z-10">
                        "Ich achte darauf, dass du Pausen machst. Wer 24/7 arbeitet, brennt aus. Wer clever und nach Plan arbeitet, gewinnt langfristig."
                    </p>
                </div>
            </div>

            {{-- ========================================================================= --}}
            {{-- TAB 3: TODOS (VOLLBILD VERWALTUNG)                                        --}}
            {{-- ========================================================================= --}}
        @elseif($activeTab === 'todo')
            <div class="h-[calc(100vh-200px)]">
                @livewire('shop.funki.funki-to-do')
            </div>

            {{-- ========================================================================= --}}
            {{-- TAB 4: AUTOMATISIERUNGEN (MARKETING)                                      --}}
            {{-- ========================================================================= --}}
        @elseif($activeTab === 'automation')
            {{-- Nutzt die bestehende Automatisierungs-View --}}
            @include('livewire.shop.funki.partials.automation')
        @endif

    </div>
</div>
