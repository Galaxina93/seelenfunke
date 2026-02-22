{{-- Sticky Header --}}
<div class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-gray-200 px-6 py-3 flex items-center justify-between shadow-sm">
    <div class="flex items-center gap-4">
        <button wire:click="backToList" class="p-2 hover:bg-gray-100 rounded-full text-gray-500 transition" title="Zurück">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>
        <div>
            <h2 class="text-lg font-serif font-bold text-gray-900 leading-tight">{{ $name ?: 'Neues Produkt' }}</h2>
            <p class="text-xs text-primary font-bold uppercase tracking-wider">
                @if($type === 'physical') Physisches Produkt
                @elseif($type === 'digital') Digitales Produkt
                @elseif($type === 'service') Dienstleistung
                @endif
                &bull; Schritt {{ $currentStep }} von {{ $totalSteps }}
            </p>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <span wire:loading class="text-xs text-gray-400">Speichere...</span>
        @if (session()->has('success'))
            <span class="text-xs text-green-600 font-bold animate-pulse">{{ session('success') }}</span>
        @endif
        <button wire:click="save" class="text-gray-500 hover:text-gray-900 text-sm font-medium px-2">Produkt speichern</button>
    </div>
</div>

<div class="max-w-[1800px] mx-auto p-6 lg:p-10">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-12 items-start">

        {{-- LINKE SPALTE: FORMULARE --}}
        <div class="xl:col-span-7 space-y-8">

            {{-- Dynamische Progress Bar --}}
            <div class="flex items-start gap-3 mb-10">
                @php
                    $stepLabels = [
                        1 => 'Basisdaten',
                        2 => 'Medien',
                        3 => 'Details',
                    ];
                    if ($type === 'physical') {
                        $stepLabels[4] = 'Konfigurator';
                    }
                @endphp

                @foreach($stepLabels as $step => $label)
                    <div wire:click="goToStep({{ $step }})"
                         class="flex-1 flex flex-col gap-2 group transition-all duration-300
                                 {{ ($step <= $product->completion_step + 1) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}"
                         @if($step > $product->completion_step + 1) title="Schritt noch nicht verfügbar" @endif
                    >
                        <span class="text-xs font-bold uppercase tracking-wider h-8 flex items-center transition-colors duration-300
                                           {{ $currentStep >= $step ? 'text-primary' : 'text-gray-400 group-hover:text-gray-600' }}">
                                    {{ $step }}. {{ $label }}
                        </span>
                        <div class="h-1.5 w-full rounded-full transition-all duration-500
                                    {{ $currentStep >= $step ? 'bg-primary shadow-sm shadow-primary/30' : 'bg-gray-200 group-hover:bg-gray-300' }}">
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- INCLUDES --}}
            @include('livewire.shop.product.partials.edit-partials._edit_step_1')
            @include('livewire.shop.product.partials.edit-partials._edit_step_2')
            @include('livewire.shop.product.partials.edit-partials._edit_step_3')

            {{-- WICHTIG: Das wire:ignore umschließt den Konfigurator-Step! --}}
            @if($type === 'physical')
                <div x-show="$wire.currentStep === 4" wire:ignore>
                    @include('livewire.shop.product.partials.edit-partials._edit_step_4')
                </div>
            @endif

            {{-- FOOTER NAVIGATION --}}
            <div class="flex justify-between pt-8">
                <button @if($currentStep === 1) disabled @else wire:click="prevStep" @endif class="px-6 py-3 rounded-full font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-900 disabled:opacity-30 disabled:cursor-not-allowed transition">
                    &larr; Zurück
                </button>

                @php $isLastStep = ($type === 'physical' && $currentStep === 4) || ($type !== 'physical' && $currentStep === 3); @endphp

                @if($isLastStep)
                    <button wire:click="finish" @if(!$canProceed) disabled @endif class="bg-primary text-white px-10 py-3 rounded-full font-bold shadow-lg shadow-primary/30 hover:bg-white hover:text-primary border border-transparent hover:border-primary transition transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none">
                        Fertigstellen & Veröffentlichen
                    </button>
                @else
                    <button wire:click="nextStep" @if(!$canProceed) disabled @endif class="px-8 py-3 rounded-full font-bold shadow-lg transition transform hover:-translate-y-0.5 {{ $canProceed ? 'bg-gray-900 text-white hover:bg-black' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}">
                        Weiter &rarr;
                    </button>
                @endif
            </div>
        </div>

        {{-- RECHTE SPALTE: PREVIEW --}}
        <div class="xl:col-span-5 relative hidden xl:block">
            <div class="sticky top-32 space-y-6">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Shop Vorschau</h3>
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden transform transition hover:scale-[1.01]">
                    <div class="aspect-square bg-gray-50 relative overflow-hidden">
                        @if(!empty($product->media_gallery[0]) && isset($product->media_gallery[0]['path']))
                            <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover">
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-gray-300 font-serif italic">
                                Vorschau
                            </div>
                        @endif

                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-xs font-bold shadow-sm uppercase tracking-wide">
                            @if($type === 'physical') Physisch @elseif($type === 'digital') Digital @else Service @endif
                        </div>
                    </div>
                    <div class="p-8">
                        <h1 class="text-3xl font-serif text-gray-900 leading-tight mb-2">{{ $name ?: 'Produktname' }}</h1>
                        <div class="flex items-baseline gap-4 mb-6">
                            <span class="text-2xl font-bold text-primary">{{ $price_input ?: '0,00' }} €</span>
                            <span class="text-xs text-gray-400">
                                {{ $product->tax_included ? 'inkl.' : 'zzgl.' }} MwSt.
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-y-4 gap-x-8 text-sm text-gray-500 mb-8 pt-6 border-t border-gray-100">
                            @if($type === 'physical')
                                <div><span class="font-bold text-gray-900 block mb-1">Material</span>{{ $productAttributes['Material'] ?? '-' }}</div>
                                <div><span class="font-bold text-gray-900 block mb-1">Größe</span>{{ $productAttributes['Größe'] ?? '-' }}</div>
                            @endif

                            @if($track_quantity && $quantity > 0)
                                <div class="col-span-2 text-green-600 font-bold text-xs mt-2 flex items-center gap-1">
                                    <span class="w-2 h-2 bg-green-500 rounded-full inline-block"></span>
                                    @if($type === 'service') Plätze verfügbar @else Auf Lager @endif
                                    ({{ $quantity }})
                                </div>
                            @endif
                        </div>
                        <button class="w-full bg-primary text-white font-bold py-4 rounded-full shadow-lg shadow-primary/20 flex items-center justify-center gap-2 opacity-50 cursor-default">
                            <span>{{ $type === 'service' ? 'Buchen' : 'Jetzt kaufen' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
