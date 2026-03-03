{{-- Sticky Header --}}
<div class="sticky top-0 z-40 bg-gray-900/80 backdrop-blur-xl border-b border-gray-800 px-6 sm:px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between shadow-2xl gap-4">
    <div class="flex items-center gap-4 sm:gap-6 w-full sm:w-auto">
        <button wire:click="backToList" class="group p-2.5 bg-gray-950 border border-gray-800 hover:border-gray-600 rounded-xl text-gray-500 hover:text-white transition-all shadow-inner shrink-0" title="Zurück">
            <svg class="w-5 h-5 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
        </button>
        <div class="min-w-0">
            <h2 class="text-xl sm:text-2xl font-serif font-bold text-white leading-tight tracking-wide truncate">{{ $name ?: 'Neues Produkt' }}</h2>
            <p class="text-[9px] sm:text-[10px] text-primary font-black uppercase tracking-[0.2em] mt-1 flex items-center gap-2 drop-shadow-[0_0_8px_currentColor]">
                @if($type === 'physical') Physisches Produkt
                @elseif($type === 'digital') Digitales Produkt
                @elseif($type === 'service') Dienstleistung
                @endif
                <span class="w-1 h-1 rounded-full bg-primary"></span>
                Schritt {{ $currentStep }} von {{ $totalSteps }}
            </p>
        </div>
    </div>
    <div class="flex items-center gap-4 sm:gap-6 w-full sm:w-auto justify-end">
        <span wire:loading wire:target="save" class="text-[10px] font-black uppercase tracking-widest text-primary animate-pulse">Speichere...</span>
        @if (session()->has('success'))
            <span class="text-[10px] font-black uppercase tracking-widest text-emerald-400 animate-pulse drop-shadow-[0_0_8px_currentColor]">{{ session('success') }}</span>
        @endif
        <button wire:click="save" class="text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white border-b border-gray-600 hover:border-white pb-0.5 transition-colors">Speichern</button>
    </div>
</div>

<div class="max-w-[1800px] mx-auto p-6 lg:p-10 font-sans antialiased text-gray-300">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 lg:gap-12 items-start">

        {{-- LINKE SPALTE: FORMULARE & EDITOR --}}
        {{-- Wenn currentStep == 4 ist, nimmt diese Spalte die volle Breite ein --}}
        <div class="{{ $currentStep === 4 ? 'xl:col-span-12' : 'xl:col-span-7' }} space-y-8 lg:space-y-10 transition-all duration-500">

            {{-- Dynamische Progress Bar --}}
            <div class="flex items-start gap-2 sm:gap-4 mb-10 overflow-x-auto no-scrollbar pb-2">
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
                         class="flex-1 min-w-[80px] flex flex-col gap-3 group transition-all duration-300
                                 {{ ($step <= $product->completion_step + 1) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40' }}"
                         @if($step > $product->completion_step + 1) title="Schritt noch nicht verfügbar" @endif
                    >
                        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest h-8 flex items-center transition-colors duration-300
                                           {{ $currentStep >= $step ? 'text-primary drop-shadow-[0_0_8px_currentColor]' : 'text-gray-500 group-hover:text-gray-400' }}">
                                    {{ $step }}. {{ $label }}
                        </span>
                        <div class="h-1.5 w-full rounded-full transition-all duration-500 overflow-hidden bg-gray-900 border border-gray-800 shadow-inner">
                            <div class="h-full w-full transition-transform duration-500 origin-left {{ $currentStep >= $step ? 'bg-primary shadow-[0_0_10px_rgba(197,160,89,0.8)] scale-x-100' : 'bg-transparent scale-x-0' }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- INCLUDES --}}
            <div class="animate-fade-in-up">
                @include('livewire.shop.product.partials.edit-partials._edit_step_1')
                @include('livewire.shop.product.partials.edit-partials._edit_step_2')
                @include('livewire.shop.product.partials.edit-partials._edit_step_3')

                @if($type === 'physical')
                    <div x-show="$wire.currentStep === 4">
                        @include('livewire.shop.product.partials.edit-partials._edit_step_4')
                    </div>
                @endif
            </div>

            {{-- FOOTER NAVIGATION --}}
            <div class="flex flex-col sm:flex-row justify-between items-center pt-10 border-t border-gray-800 mt-10 gap-4 sm:gap-0">
                <button @if($currentStep === 1) disabled @else wire:click="prevStep" @endif class="w-full sm:w-auto px-5 sm:px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-500 hover:text-white bg-gray-900 border border-gray-800 hover:bg-gray-800 disabled:opacity-30 disabled:cursor-not-allowed transition-all shadow-inner">
                    &larr; Zurück
                </button>

                <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                    {{-- NEU: Speichern Button im Footer --}}
                    <button wire:click="save" class="w-full sm:w-auto flex justify-center items-center gap-2 px-5 sm:px-6 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-white bg-gray-900/50 border border-gray-700 hover:border-gray-500 hover:bg-gray-800 transition-all shadow-inner">
                        <svg wire:loading wire:target="save" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                        <span wire:loading.remove wire:target="save">Zwischenspeichern</span>
                        <span wire:loading wire:target="save">Speichert...</span>
                    </button>

                    @php $isLastStep = ($type === 'physical' && $currentStep === 4) || ($type !== 'physical' && $currentStep === 3); @endphp

                    @if($isLastStep)
                        <button wire:click="finish" @if(!$canProceed) disabled @endif class="w-full sm:w-auto bg-primary text-gray-900 px-6 sm:px-10 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_rgba(197,160,89,0.3)] hover:bg-primary-dark hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none transition-all">
                            Fertigstellen & Veröffentlichen
                        </button>
                    @else
                        <button wire:click="nextStep" @if(!$canProceed) disabled @endif class="w-full sm:w-auto px-6 sm:px-8 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg transition-all transform {{ $canProceed ? 'bg-gray-800 text-white border border-gray-600 hover:bg-gray-700 hover:-translate-y-0.5' : 'bg-gray-950 text-gray-600 border border-gray-800 cursor-not-allowed' }}">
                            Weiter &rarr;
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- RECHTE SPALTE: PREVIEW --}}
        {{-- Wenn currentStep == 4 ist, wird diese Sidebar komplett versteckt --}}
        <div class="xl:col-span-5 relative hidden xl:block {{ $currentStep === 4 ? '!hidden' : '' }}">
            <div class="sticky top-32 space-y-6">
                <h3 class="text-[10px] font-black text-gray-500 uppercase tracking-[0.2em] text-center flex items-center justify-center gap-3">
                    <span class="w-8 h-px bg-gray-800"></span>
                    Shop Vorschau
                    <span class="w-8 h-px bg-gray-800"></span>
                </h3>
                <div class="bg-gray-900/80 backdrop-blur-md rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden transform transition hover:border-gray-700">
                    <div class="aspect-square bg-gray-950 relative overflow-hidden shadow-inner p-2">
                        <div class="w-full h-full rounded-[2rem] overflow-hidden relative">
                            @if(!empty($product->media_gallery[0]) && isset($product->media_gallery[0]['path']))
                                <img src="{{ asset('storage/'.$product->media_gallery[0]['path']) }}" class="w-full h-full object-cover">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-gray-700 font-serif italic text-xl">
                                    Vorschau
                                </div>
                            @endif
                        </div>

                        <div class="absolute top-6 right-6 bg-gray-900/90 backdrop-blur-md px-4 py-2 rounded-xl text-[9px] font-black text-gray-300 shadow-lg border border-gray-800 uppercase tracking-widest">
                            @if($type === 'physical') Physisch @elseif($type === 'digital') Digital @else Service @endif
                        </div>
                    </div>
                    <div class="p-8 sm:p-10">
                        <h1 class="text-3xl font-serif text-white leading-tight mb-3 tracking-wide">{{ $name ?: 'Produktname' }}</h1>
                        <div class="flex items-baseline gap-4 mb-8">
                            <span class="text-2xl font-mono font-bold text-primary drop-shadow-[0_0_10px_rgba(197,160,89,0.3)]">{{ $price_input ?: '0,00' }} €</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">
                                {{ $product->tax_included ? 'inkl.' : 'zzgl.' }} MwSt.
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-y-5 gap-x-8 text-sm text-gray-400 mb-8 pt-8 border-t border-gray-800">
                            @if($type === 'physical')
                                <div><span class="font-black text-[9px] uppercase tracking-widest text-gray-500 block mb-1">Material</span><span class="text-white">{{ $productAttributes['Material'] ?? '-' }}</span></div>
                                <div><span class="font-black text-[9px] uppercase tracking-widest text-gray-500 block mb-1">Größe</span><span class="text-white">{{ $productAttributes['Größe'] ?? '-' }}</span></div>
                            @endif

                            @if($track_quantity && $quantity > 0)
                                <div class="col-span-2 text-emerald-400 font-bold text-xs mt-2 flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 px-4 py-2.5 rounded-xl shadow-inner w-fit">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full inline-block shadow-[0_0_8px_currentColor]"></span>
                                    @if($type === 'service') Plätze verfügbar @else Auf Lager @endif
                                    ({{ $quantity }})
                                </div>
                            @endif
                        </div>

                        <button class="w-full bg-gray-800 text-gray-500 font-black text-[10px] uppercase tracking-widest py-4 rounded-2xl shadow-inner border border-gray-700 flex items-center justify-center gap-2 opacity-50 cursor-not-allowed">
                            <span>{{ $type === 'service' ? 'Buchen' : 'Jetzt kaufen' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
