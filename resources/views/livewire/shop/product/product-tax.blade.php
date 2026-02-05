<div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-200 animate-fade-in-up">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-8 border-b border-gray-100 pb-6">
        <div class="flex items-center gap-3">
            <h2 class="text-2xl font-serif text-gray-900">Steuer & Mehrwertsteuer</h2>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 tracking-wide">
                EU Richtlinie
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Steuerklasse Auswahl --}}
        <div>
            <div class="flex items-center gap-2 mb-2">
                <label class="block text-sm font-bold text-gray-800">
                    Steuerklasse
                </label>
                @if(isset($infoTexts['tax_class']))
                    <div x-data="{ show: false }" class="relative inline-block">
                        <button @mouseenter="show = true" @mouseleave="show = false" type="button" class="text-gray-400 hover:text-primary"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg></button>
                        <div x-show="show" x-cloak class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-xs rounded shadow-lg z-50 text-center">{{ $infoTexts['tax_class'] }}</div>
                    </div>
                @endif
            </div>

            <select wire:model.live="tax_class" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-primary focus:ring-primary transition bg-white cursor-pointer">
                <option value="standard">Standard (Regelsteuersatz)</option>
                <option value="reduced">Ermäßigter Satz</option>
                <option value="zero">Steuerfrei / Steuerbefreit</option>
            </select>
        </div>

        {{-- Info-Anzeige (Keine Eingabe mehr) --}}
        <div class="flex flex-col justify-center text-sm text-gray-600 bg-gray-50 rounded-xl p-4 border border-gray-100">
            <p class="font-bold text-gray-900 mb-1">Steuer-Konfiguration:</p>
            <ul class="space-y-1">
                <li class="flex justify-between">
                    <span>Eingabeart:</span>
                    {{-- Greift auf globale Config zu (über Model Accessor) --}}
                    <span class="font-mono font-bold">{{ $product->tax_included ? 'Brutto (inkl. MwSt.)' : 'Netto (zzgl. MwSt.)' }}</span>
                </li>
                <li class="flex justify-between">
                    <span>Satz (DE):</span>
                    <span class="font-mono font-bold">{{ number_format($current_tax_rate, 0) }}%</span>
                </li>
            </ul>
        </div>
    </div>

    {{-- Footer Info --}}
    <div class="mt-8 p-4 bg-blue-50 rounded-xl border border-blue-100 flex items-start gap-3 text-sm text-blue-800 leading-relaxed">
        <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div>
            <strong class="block mb-1 text-blue-900">Hinweis zur EU-MwSt-Richtlinie (OSS)</strong>
            Bei grenzüberschreitenden Verkäufen wird im Checkout automatisch der Steuersatz des Kundenlandes berechnet. Die Klasse "{{ $tax_class }}" dient als Basis.
        </div>
    </div>
</div>
