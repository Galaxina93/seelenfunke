@include('livewire.shop.configurator-partials.scripts')

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
    <div class="flex-1 overflow-y-auto custom-scrollbar pb-20">

        @include('livewire.shop.configurator-partials.preview')
        @include('livewire.shop.configurator-partials.formluar')

    </div>

    @include('livewire.shop.configurator-partials.footer')

</div>
