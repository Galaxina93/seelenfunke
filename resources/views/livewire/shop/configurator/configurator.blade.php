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

    <div class="flex-1 custom-scrollbar pb-20">
        @if(!$isDigital)
            @include('livewire.shop.configurator.partials.preview')
            @include('livewire.shop.configurator.partials.formluar')
        @else
            {{-- Digital View... --}}
            <div class="p-8 space-y-8 max-w-2xl mx-auto">
                <div class="bg-blue-50 rounded-2xl p-6 flex gap-5 items-start border border-blue-100">
                    <div class="bg-blue-600 text-white p-3 rounded-xl shadow-lg">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-blue-900">Digitaler Inhalt</h3>
                        <p class="text-sm text-blue-800/70 mt-1">Sofortiger Download nach Zahlung.</p>
                    </div>
                </div>
                <textarea wire:model="notes" rows="4" class="w-full p-4 rounded-xl border border-gray-200 text-sm focus:ring-primary" placeholder="Anmerkung zur Bestellung..."></textarea>
            </div>
        @endif
    </div>

    @include('livewire.shop.configurator.partials.footer')
</div>
