<div class="p-5 bg-gray-50 rounded-xl border border-gray-100 transition-all">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-sm font-bold text-gray-900 flex items-center gap-2">
            <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Staffelpreise (Mengenrabatt)
        </h4>
        <button type="button" wire:click="addTier" class="text-xs bg-white border border-gray-300 hover:border-primary hover:text-primary text-gray-600 px-3 py-1.5 rounded-full font-bold transition flex items-center gap-1 shadow-sm">
            <span>+</span> Hinzufügen
        </button>
    </div>

    @if(empty($tiers))
        <p class="text-xs text-gray-400 italic text-center py-2">Noch keine Staffelpreise hinterlegt.</p>
    @else
        <div class="space-y-3">
            {{-- Loop über $id => $tier --}}
            @foreach($tiers as $tierId => $tier)
                <div wire:key="tier-row-{{ $tierId }}" class="flex items-center gap-3">

                    {{-- MENGE --}}
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400 text-xs font-bold">Ab</span>
                        </div>
                        {{-- FIX: Binding an ID statt Index --}}
                        <input type="number"
                               wire:model.blur="tiers.{{ $tierId }}.qty"
                               wire:change="updateTier('{{ $tierId }}')"
                               class="w-full pl-8 pr-12 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary text-sm font-bold text-center"
                               placeholder="5">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-400 text-xs">Stk.</span>
                        </div>
                    </div>

                    {{-- RABATT --}}
                    <div class="flex-1 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-gray-400 text-xs font-bold">Rabatt</span>
                        </div>
                        {{-- FIX: Binding an ID statt Index --}}
                        <input type="number" step="0.01"
                               wire:model.blur="tiers.{{ $tierId }}.percent"
                               wire:change="updateTier('{{ $tierId }}')"
                               class="w-full pl-14 pr-8 py-2 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary text-sm font-bold text-center text-green-600"
                               placeholder="10">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-400 text-xs">%</span>
                        </div>
                    </div>

                    {{-- LÖSCHEN --}}
                    <button type="button" wire:click="removeTier('{{ $tierId }}')" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-full transition" title="Entfernen">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            @endforeach
        </div>

        {{-- Vorschau (Optional: Wir nehmen einfach das erste Element aus dem Array für das Beispiel) --}}
        @php $firstTier = reset($tiers); @endphp
        @if((float)$currentPrice > 0 && $firstTier && isset($firstTier['qty']) && $firstTier['qty'] > 0)
            <div class="mt-4 pt-3 border-t border-gray-200 text-xs text-gray-500 flex justify-between items-center">
                <span>Beispiel (Erste Staffel):</span>
                <span>
                    Bei {{ $firstTier['qty'] }} Stück nur noch
                    <strong class="text-green-600">{{ number_format(((float)$currentPrice * (1 - ((float)$firstTier['percent'] / 100))), 2, ',', '.') }} €</strong>
                </span>
            </div>
        @endif
    @endif
</div>
