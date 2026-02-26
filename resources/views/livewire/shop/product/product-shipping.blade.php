<div x-data="{ open: false }" class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 transition-colors hover:border-gray-700 animate-fade-in-up">

    {{-- Header (Klickbar zum Aufklappen) --}}
    <div @click="open = !open" class="flex items-center justify-between cursor-pointer group transition-all" :class="open ? 'mb-8 border-b border-gray-800 pb-5' : ''">
        <div class="flex items-center gap-4">
            <div class="p-2 rounded-xl bg-gray-950 border border-gray-800 text-gray-500 group-hover:text-primary group-hover:border-primary/30 transition-all shadow-inner shrink-0">
                <svg class="w-5 h-5 transition-transform duration-300" :class="open ? 'rotate-180 text-primary' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
            </div>
            <div class="flex items-center gap-2">
                <h3 class="text-xl font-serif font-bold text-white tracking-wide group-hover:text-primary transition-colors">Versand & Lieferung</h3>
                <div @click.stop>
                    @include('components.alerts.info-tooltip', ['key' => 'weight'])
                </div>
            </div>
        </div>

        <span class="text-[9px] font-black uppercase tracking-widest text-primary bg-primary/10 border border-primary/20 px-3 py-1.5 rounded-lg shadow-inner drop-shadow-[0_0_8px_currentColor] hidden sm:block">
            Physisches Produkt
        </span>
    </div>

    {{-- Eingeklappter Inhalt --}}
    <div x-show="open" x-collapse style="display: none;">
        <div class="space-y-8 pt-2">
            {{-- Zeile 1: Gewicht & Klasse --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">
                {{-- Gewicht --}}
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Gewicht</label>
                    </div>
                    <div class="relative">
                        <input type="number" wire:model.blur="weight"
                               class="w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all pr-12 shadow-inner outline-none placeholder-gray-600"
                               placeholder="0">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-gray-500">g</span>
                    </div>
                    @error('weight') <span class="text-[10px] text-red-400 mt-2 block font-bold uppercase tracking-widest">{{ $message }}</span> @enderror
                    <p class="text-[10px] text-gray-600 font-medium mt-2 ml-1">Bestimmt den Paketpreis (z.B. < 2kg, < 5kg)</p>
                </div>

                {{-- Versandklasse --}}
                <div>
                    <div class="flex items-center gap-2 mb-2 ml-1">
                        <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">Versandart</label>
                        @include('components.alerts.info-tooltip', ['key' => 'shipping_class'])
                    </div>
                    <div class="relative">
                        <select wire:model.blur="shipping_class"
                                class="w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner appearance-none cursor-pointer outline-none text-sm">

                            <option value="" class="bg-gray-900">DHL Standard (Gewichtsbasiert)</option>

                            <optgroup label="Spezialversand" class="text-gray-500 italic bg-gray-950">
                                <option value="brief" class="bg-gray-900 not-italic text-white">Brief / Großbrief (Günstiger)</option>
                                <option value="sperrgut" class="bg-gray-900 not-italic text-white">DHL Sperrgut (Zuschlag)</option>
                                <option value="spedition" class="bg-gray-900 not-italic text-white">Spedition (Palettenware)</option>
                            </optgroup>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </div>
                    </div>
                    @error('shipping_class') <span class="text-[10px] text-red-400 mt-2 block font-bold uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Zeile 2: Maße --}}
            <div class="pt-8 border-t border-gray-800">
                <div class="flex items-center gap-2 mb-5 ml-1">
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 drop-shadow-[0_0_8px_currentColor]">Abmessungen (L x B x H in mm)</label>
                    @include('components.alerts.info-tooltip', ['key' => 'dimensions'])
                </div>

                <div class="grid grid-cols-3 gap-5">
                    <div class="relative group">
                        <input type="number" wire:model.blur="length"
                               class="w-full px-3 py-4 text-center rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none placeholder-gray-600"
                               placeholder="0">
                        <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-primary">Länge</label>
                    </div>
                    <div class="relative group">
                        <input type="number" wire:model.blur="width"
                               class="w-full px-3 py-4 text-center rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none placeholder-gray-600"
                               placeholder="0">
                        <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-primary">Breite</label>
                    </div>
                    <div class="relative group">
                        <input type="number" wire:model.blur="height"
                               class="w-full px-3 py-4 text-center rounded-xl border border-gray-800 bg-gray-950 text-white font-mono font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all shadow-inner outline-none placeholder-gray-600"
                               placeholder="0">
                        <label class="absolute -bottom-6 left-0 w-full text-[9px] text-center text-gray-600 font-black uppercase tracking-widest transition-colors group-focus-within:text-primary">Höhe</label>
                    </div>
                </div>

                @if($errors->has('length') || $errors->has('width') || $errors->has('height'))
                    <div class="mt-10 flex items-center gap-3 text-red-400 bg-red-500/10 p-4 rounded-xl border border-red-500/20 animate-pulse shadow-inner">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span class="text-[10px] font-black uppercase tracking-widest">Bitte gültige Maße eingeben</span>
                    </div>
                @endif
            </div>

            {{-- Spacer --}}
            <div class="h-4"></div>
        </div>
    </div>
</div>
