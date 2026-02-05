<div class="mb-10">
    <h3 class="text-lg font-bold text-gray-800 border-b border-gray-200 pb-2 mb-4 uppercase tracking-wider">
        Unser Sortiment
    </h3>

    @if(empty($dbProducts))
        <div class="flex flex-col items-center justify-center py-12 text-gray-500 bg-gray-50 rounded-xl border border-dashed border-gray-300">
            <span class="text-sm font-medium">Aktuell keine Produkte verfügbar.</span>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 md:gap-4">
            @foreach($dbProducts as $product)
                <div class="relative group flex flex-row items-stretch p-3 md:p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-primary/30 transition-all duration-200 cursor-pointer active:scale-[0.99]"
                     wire:click="openConfig('{{ $product['id'] }}')"
                     wire:loading.class="opacity-50 pointer-events-none"
                     wire:target="openConfig('{{ $product['id'] }}')">

                    {{-- Bild --}}
                    <div class="flex-shrink-0 mr-4 self-start">
                        @if(!empty($product['image']))
                            <img src="{{ asset($product['image']) }}" class="w-24 h-24 md:w-24 md:h-24 object-cover rounded-lg bg-gray-50 border border-gray-100">
                        @else
                            <div class="w-24 h-24 md:w-24 md:h-24 bg-gray-50 rounded-lg border border-gray-100 flex items-center justify-center text-gray-300">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                    </div>

                    {{-- Inhalt --}}
                    <div class="flex-1 min-w-0 flex flex-col justify-between">
                        <div>
                            <h4 class="font-bold text-gray-900 group-hover:text-primary transition truncate">{{ $product['name'] }}</h4>
                            <p class="text-xs text-gray-500 mt-1 line-clamp-2 leading-relaxed">{{ $product['desc'] }}</p>
                        </div>
                        <div class="mt-3">
                            <div class="flex flex-wrap items-baseline gap-x-1 justify-between items-end">
                                <div>
                                    <div class="flex flex-wrap items-baseline gap-x-1">
                                        <span class="text-sm font-bold text-primary">ab {{ number_format($product['display_price'], 2, ',', '.') }} €</span>
                                        <span class="text-[10px] uppercase tracking-wide text-gray-400">{{ $product['tax_included'] ? 'inkl.' : 'zzgl.' }} MwSt.</span>
                                    </div>
                                </div>
                                <div class="hidden md:flex w-8 h-8 rounded-full bg-white/90 backdrop-blur border border-gray-200 text-gray-400 items-center justify-center shadow-sm transition-all duration-200 group-hover:border-primary/60 group-hover:text-primary group-hover:shadow-md group-hover:scale-[1.03]">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                            </div>

                            {{-- Staffelpreise --}}
                            @if(!empty($product['tier_pricing']))
                                <div class="mt-2 pt-2 border-t border-gray-50">
                                    <span class="text-[10px] font-bold uppercase text-green-600 block mb-1">Staffelpreise verfügbar:</span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach(collect($product['tier_pricing'])->sortBy('qty')->take(4) as $tier)
                                            <span class="text-[10px] bg-green-50 text-green-700 px-1.5 py-0.5 rounded border border-green-100">
                                                                    ab {{ $tier['qty'] }} Stk: -{{ 0 + $tier['percent'] }}%
                                                                </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
