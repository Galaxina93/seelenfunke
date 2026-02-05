<div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
            <h3 class="text-lg font-serif font-bold text-gray-900">Staffelpreise</h3>

            {{-- Tooltip --}}
            <div x-data="{ show: false }" class="relative inline-block ml-2">
                <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                </button>
                <div x-show="show" x-cloak x-transition.opacity class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs leading-relaxed rounded shadow-lg z-50 text-center">
                    {{ $infoTexts['tier_pricing'] }}
                    <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                </div>
            </div>
        </div>

        {{-- Add Button (Position analog zum Toggle) --}}
        <button type="button" wire:click="addTier" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-300 hover:border-primary hover:text-primary text-gray-700 text-xs font-bold rounded-lg transition shadow-sm">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Hinzufügen
        </button>
    </div>

    {{-- Content Area --}}
    @if(empty($tiers))
        <div class="flex flex-col items-center justify-center py-10 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
            <div class="p-3 bg-white rounded-full shadow-sm mb-3">
                <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h4 class="text-gray-900 font-bold mb-1">Keine Mengenrabatte</h4>
            <p class="text-gray-500 text-sm">Fügen Sie Staffelpreise hinzu, um Kunden zum Kauf größerer Mengen zu animieren.</p>
        </div>
    @else
        <div class="p-6 bg-gray-50 rounded-xl border border-gray-100 transition-all space-y-4">

            {{-- Loop über Staffeln --}}
            @foreach($tiers as $tierId => $tier)
                <div wire:key="tier-row-{{ $tierId }}" class="grid grid-cols-12 gap-4 items-start bg-white p-3 rounded-lg border border-gray-200 shadow-sm">

                    {{-- Menge --}}
                    <div class="col-span-5 sm:col-span-5">
                        <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1 ml-1">Ab Menge</label>
                        <div class="relative">
                            <input type="number"
                                   wire:model.blur="tiers.{{ $tierId }}.qty"
                                   wire:change="updateTier('{{ $tierId }}')"
                                   class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm pr-8"
                                   placeholder="5">
                            <span class="absolute right-3 top-2 text-xs text-gray-400 font-bold">Stk.</span>
                        </div>
                    </div>

                    {{-- Rabatt --}}
                    <div class="col-span-5 sm:col-span-6">
                        <label class="block text-[10px] font-bold uppercase text-gray-500 mb-1 ml-1">Rabatt</label>
                        <div class="relative">
                            <input type="number" step="0.01"
                                   wire:model.blur="tiers.{{ $tierId }}.percent"
                                   wire:change="updateTier('{{ $tierId }}')"
                                   class="w-full px-3 py-2 rounded border border-gray-300 focus:ring-primary focus:border-primary text-sm pr-8 text-green-700 font-bold"
                                   placeholder="10">
                            <span class="absolute right-3 top-2 text-xs text-gray-400 font-bold">%</span>
                        </div>
                    </div>

                    {{-- Löschen --}}
                    <div class="col-span-2 sm:col-span-1 flex justify-end pt-6">
                        <button type="button" wire:click="removeTier('{{ $tierId }}')" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Entfernen">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            @endforeach

            {{-- Vorschau --}}
            @php $firstTier = reset($tiers); @endphp
            @if((float)$currentPrice > 0 && $firstTier && isset($firstTier['qty']) && $firstTier['qty'] > 0)
                <div class="mt-2 pt-3 border-t border-gray-200 text-xs text-gray-500 flex flex-wrap gap-1 justify-between items-center">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Beispielrechnung:
                    </span>
                    <span>
                        Bei <strong>{{ $firstTier['qty'] }} Stück</strong> sinkt der Preis auf
                        <strong class="text-green-600 bg-green-50 px-1 rounded">{{ number_format(((float)$currentPrice * (1 - ((float)$firstTier['percent'] / 100))), 2, ',', '.') }} €</strong> / Stk.
                    </span>
                </div>
            @endif
        </div>
    @endif
</div>
