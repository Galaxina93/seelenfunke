<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <div x-data="{ open: false }" class="p-6 md:p-10 bg-transparent font-sans text-gray-300">

        {{-- Success Message --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                 class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-5 py-4 rounded-2xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-[0_0_15px_rgba(16,185,129,0.15)] animate-fade-in-down backdrop-blur-md">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
        @endif

        <h3 @click="open = !open" class="text-sm font-serif font-bold text-white text-center mb-4 flex items-center justify-center gap-3 tracking-wide cursor-pointer hover:text-[var(--theme-color)] transition-colors group select-none">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] shadow-inner group-hover:bg-[var(--theme-color-20)] transition-colors">
                <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            Schnellerfassung Sonderausgabe
            <svg class="w-4 h-4 text-gray-500 group-hover:text-[var(--theme-color)] transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </h3>

        <div x-show="open" x-collapse x-cloak class="max-w-4xl mx-auto space-y-5 pt-4">

            {{-- AUTO-FILL NACHRICHT (Die blaue Box) --}}
            @if($isAutoFilled)
                <div class="bg-blue-900/20 border border-blue-500/30 text-blue-300 px-5 py-4 rounded-2xl flex items-center gap-4 shadow-inner animate-pulse backdrop-blur-md">
                    <div class="bg-blue-500/20 p-2.5 rounded-full border border-blue-500/30 shrink-0">
                        <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-black text-[10px] uppercase tracking-widest text-blue-400 mb-1">E-Rechnung erkannt!</p>
                        <p class="text-xs font-medium">Daten für Betrag, Datum und Rechnungsnummer wurden automatisch übernommen.</p>
                    </div>
                </div>
            @endif

            @php
                $inputClass = "w-full rounded-xl border border-gray-700 bg-gray-950 py-3.5 px-5 text-sm font-medium text-white placeholder-gray-600 focus:bg-gray-900 focus:border-[var(--theme-color-50)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none";
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <input type="text" wire:model="specialTitle" placeholder="Titel / Verwendungszweck" class="{{ $inputClass }}">
                    @error('specialTitle') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
                <div>
                    <input type="text" wire:model="specialAmount" placeholder="Betrag (z.B. 49.99)" class="{{ $inputClass }} font-mono">
                    @error('specialAmount') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <input type="text" list="category-list" wire:model="specialCategory" placeholder="Kategorie (z.B. Tanken)" class="{{ $inputClass }}">
                    <datalist id="category-list">
                        @foreach($categories as $catName)
                            <option value="{{ $catName }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <input type="date" wire:model="specialDate" class="{{ $inputClass }} [&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)] cursor-pointer text-gray-400">
                    @error('specialDate') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
            </div>

            <input type="text" wire:model="specialLocation" placeholder="Ort / Geschäft (Optional)" class="{{ $inputClass }}">

            {{-- Business Toggle --}}
            <div x-data="{ open: @entangle('specialIsBusiness') }" class="bg-gray-950/50 rounded-[1.5rem] border border-gray-800 p-5 shadow-inner">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Geschäftliche Ausgabe?</span>
                    <button type="button"
                            x-on:click="open = !open; $wire.set('specialIsBusiness', open)"
                            :class="open ? 'bg-[var(--theme-color-70)] border-[var(--theme-color)]' : 'bg-gray-800 border-gray-700'"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 transition-colors duration-300 ease-in-out focus:outline-none shadow-inner">
                        <span aria-hidden="true" :class="open ? 'translate-x-5 bg-white shadow-[0_0_10px_rgba(249,115,22,0.8)]' : 'translate-x-0 bg-gray-400'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full ring-0 transition duration-300 ease-in-out"></span>
                    </button>
                </div>

                @if($specialIsBusiness)
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-fade-in-down">
                        <input type="text" wire:model="specialInvoiceNumber" placeholder="Rechnungsnummer" class="{{ $inputClass }} !py-3 !text-xs font-mono">
                        <select wire:model="specialTaxRate" class="{{ $inputClass }} !py-3 !text-xs appearance-none cursor-pointer">
                            <option value="{{ shop_setting('default_tax_rate', 19) }}" class="bg-gray-900">{{ shop_setting('default_tax_rate', 19) }}% MwSt. (Standard)</option>
                            <option value="7" class="bg-gray-900">7% MwSt. (Ermäßigt)</option>
                            <option value="0" class="bg-gray-900">0% / Steuerfrei</option>
                        </select>
                    </div>
                @endif

                {{-- Dateiupload --}}
                <div class="mt-6 border-t border-gray-800 pt-5">
                    <label class="block text-[9px] font-black text-gray-500 uppercase tracking-[0.2em] mb-3 ml-1">
                        Belege / E-Rechnungen (ZUGFeRD/XRechnung)
                    </label>
                    <div class="relative w-full">
                        <input type="file" wire:model="specialFiles" multiple accept=".pdf,.xml,.jpg,.png,.jpeg"
                               class="block w-full text-xs text-gray-400 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-[var(--theme-color-10)] file:text-[var(--theme-color)] file:border file:border-[var(--theme-color-20)] hover:file:bg-[var(--theme-color-20)] file:transition-colors file:cursor-pointer cursor-pointer">

                        <div wire:loading wire:target="specialFiles" class="absolute right-2 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-2 bg-gray-950 px-3 py-1.5 rounded-lg border border-[var(--theme-color-20)]">
                            <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Analysiere Datei...
                        </div>
                    </div>
                </div>
            </div>

            <button wire:click="createSpecial" class="w-full bg-[var(--theme-color-70)] hover:bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white px-8 py-4 rounded-xl text-xs font-black uppercase tracking-[0.2em] transition-all shadow-[0_0_20px_var(--theme-color-20)] hover:shadow-[0_0_30px_var(--theme-color-40)] hover:scale-[1.01] mt-4">
                Speichern
            </button>

        </div>
    </div>
</div>
