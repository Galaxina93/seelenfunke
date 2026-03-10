<div x-data="{ saved: false }" @tax-saved.window="saved = true; setTimeout(() => saved = false, 3000)" class="bg-gray-900/80 backdrop-blur-xl p-6 sm:p-8 rounded-[2.5rem] shadow-2xl border border-gray-800 animate-fade-in-up">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-8 border-b border-gray-800 pb-5">
        <div class="flex items-center gap-4">
            <h2 class="text-xl font-serif font-bold text-white tracking-wide">Steuer & Mehrwertsteuer</h2>
            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest bg-blue-500/10 text-blue-400 border border-blue-500/20 shadow-[0_0_8px_currentColor] hidden sm:inline-flex">
                EU Richtlinie
            </span>
        </div>
        
        <div class="flex items-center mt-2 sm:mt-0">
            <span x-show="saved" x-transition.opacity style="display: none;" class="text-emerald-400 text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 drop-shadow-[0_0_8px_currentColor]">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                Gespeichert
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 sm:gap-8">

        {{-- Steuerklasse Auswahl --}}
        <div>
            <div class="flex items-center gap-2 mb-2 ml-1">
                <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500">
                    Steuerklasse
                </label>
                @if(isset($infoTexts['tax_class']))
                    <div x-data="{ show: false }" class="relative inline-block">
                        <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-500 hover:text-primary transition-colors"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                        <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 w-64 p-4 bg-gray-950 border border-gray-800 text-gray-300 text-xs font-medium rounded-xl shadow-2xl z-50 text-center leading-relaxed">
                            {{ $infoTexts['tax_class'] }}
                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                        </div>
                    </div>
                @endif
            </div>

            <select wire:model.live="tax_class" class="w-full px-4 py-3.5 rounded-xl border border-gray-800 bg-gray-950 text-white font-bold text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all outline-none shadow-inner appearance-none cursor-pointer">
                <option value="standard" class="bg-gray-900">Standard (Regelsteuersatz)</option>
                <option value="reduced" class="bg-gray-900">Ermäßigter Satz</option>
                <option value="zero" class="bg-gray-900">Steuerfrei / Steuerbefreit</option>
            </select>
        </div>

        {{-- Info-Anzeige (Keine Eingabe mehr) --}}
        <div class="flex flex-col justify-center text-[10px] font-black uppercase tracking-widest text-gray-500 bg-gray-950 rounded-2xl p-5 border border-gray-800 shadow-inner">
            <p class="text-primary drop-shadow-[0_0_5px_currentColor] mb-3">Steuer-Konfiguration:</p>
            <ul class="space-y-2">
                <li class="flex justify-between items-center border-b border-gray-800/50 pb-2">
                    <span>Eingabeart:</span>
                    <span class="font-mono text-white text-xs drop-shadow-[0_0_5px_rgba(255,255,255,0.2)]">{{ $product->tax_included ? 'Brutto (inkl. MwSt.)' : 'Netto (zzgl. MwSt.)' }}</span>
                </li>
                <li class="flex justify-between items-center pt-1">
                    <span>Satz (DE):</span>
                    <span class="font-mono text-white text-xs drop-shadow-[0_0_5px_rgba(255,255,255,0.2)]">{{ number_format($current_tax_rate, 0) }}%</span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="mt-8 p-5 bg-blue-900/10 rounded-[1.5rem] border border-blue-500/20 flex items-start gap-4 text-xs text-blue-200/70 font-medium leading-relaxed shadow-inner">
        <svg class="w-6 h-6 text-blue-400 shrink-0 mt-0.5 drop-shadow-[0_0_8px_currentColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <strong class="block mb-1.5 text-[10px] font-black uppercase tracking-widest text-blue-400 drop-shadow-[0_0_8px_currentColor]">Hinweis zur EU-MwSt-Richtlinie (OSS)</strong>
            Bei grenzüberschreitenden Verkäufen wird im Checkout automatisch der Steuersatz des Kundenlandes berechnet. Die Klasse "<span class="text-white font-bold">{{ $tax_class }}</span>" dient als Basis.
        </div>
    </div>
</div>
