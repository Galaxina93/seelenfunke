@include('livewire.shop.configurator.partials.scripts')

<div class="h-full flex flex-col bg-white"
     x-data="window.universalConfigurator({
        wireModels: {
            texts: @entangle('texts').live,
            logos: @entangle('logos').live
        },
        config: {{ Js::from($configSettings) }},
        fonts: {{ Js::from($fonts) }},
        context: '{{ $context }}'
     })">

    {{-- SCROLLABLE CONTENT --}}
    <div class="flex-1 custom-scrollbar pb-20">

        @if(!$isDigital)
            {{-- PHYSISCH: Standard Konfigurator mit Vorschau & Formular --}}
            @include('livewire.shop.configurator.partials.preview')
            @include('livewire.shop.configurator.partials.formluar')
        @else
            {{-- DIGITAL / SERVICE: Vereinfachte Ansicht ohne Editor --}}
            <div class="p-8 space-y-8 max-w-2xl mx-auto">
                <div class="bg-blue-50 rounded-2xl p-6 flex gap-5 items-start border border-blue-100">
                    <div class="bg-blue-600 text-white p-3 rounded-xl shadow-lg shadow-blue-200">
                        @if($type === 'service')
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        @else
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-blue-900">{{ $type === 'service' ? 'Dienstleistung & Service' : 'Digitaler Inhalt' }}</h3>
                        <p class="text-sm text-blue-800/70 leading-relaxed mt-1">
                            {{ $type === 'service' ? 'Dieser Service wird nach Buchung individuell mit Ihnen abgestimmt.' : 'Dieses Produkt steht Ihnen nach erfolgreicher Zahlung sofort als Download zur Verfügung.' }}
                        </p>
                    </div>
                </div>

                {{-- Anmerkung --}}
                <div class="pt-4">
                    <label class="text-xs font-bold text-gray-500 mb-2 block uppercase tracking-wide">Optionale Nachricht / Anmerkung</label>
                    <textarea wire:model="notes" rows="4" class="w-full p-4 rounded-xl border border-gray-200 bg-gray-50 text-sm focus:border-primary focus:ring-primary transition-all resize-none" placeholder="{{ $type === 'service' ? 'Haben Sie einen Wunschtermin?' : 'Anmerkung zur Bestellung...' }}"></textarea>
                </div>
            </div>
        @endif

    </div>

    @include('livewire.shop.configurator.partials.footer')

</div>
