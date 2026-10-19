<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;">
    <div x-data="{ open: false }" class="p-6 md:p-10 bg-transparent font-sans text-gray-600 dark:text-gray-300">

        {{-- Success Message --}}
        @if (session()->has('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mb-6 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] px-5 py-4 rounded-2xl flex items-center gap-3 text-[10px] font-black uppercase tracking-widest shadow-[0_0_20px_var(--theme-color-15)] animate-fade-in-down backdrop-blur-md">
                <div class="p-1.5 rounded-lg bg-[var(--theme-color-20)] border border-[var(--theme-color-30)] text-[var(--theme-color)] shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <span class="text-gray-900 dark:text-white font-bold">{{ session('success') }}</span>
            </div>
        @endif

        <h3 @click="open = !open" class="text-sm font-serif font-bold text-gray-900 dark:text-white text-center mb-4 flex items-center justify-center gap-3 tracking-wide cursor-pointer hover:text-[var(--theme-color)] transition-colors group select-none">
            <div class="p-2 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-20)] shadow-inner group-hover:bg-[var(--theme-color-20)] group-hover:border-[var(--theme-color-40)] transition-colors">
                <svg class="w-5 h-5 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            Schnellerfassung Sonderausgabe
            <svg class="w-4 h-4 text-gray-500 group-hover:text-[var(--theme-color)] transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
        </h3>

        <div x-show="open" x-collapse x-cloak class="max-w-4xl mx-auto space-y-5 pt-4">

            {{-- KI BELEG-FOTO & SCAN DROPZONE --}}
            <div class="p-5 sm:p-6 bg-white dark:bg-gray-900/90 rounded-2xl border-2 border-dashed border-[var(--theme-color-30)] hover:border-[var(--theme-color)] hover:shadow-[0_0_25px_var(--theme-color-15)] transition-all shadow-sm">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-5">
                    <div class="flex items-center gap-4 text-center sm:text-left">
                        <div class="w-14 h-14 rounded-2xl bg-[var(--theme-color-10)] text-[var(--theme-color)] flex items-center justify-center shrink-0 border border-[var(--theme-color-20)] shadow-inner">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2 justify-center sm:justify-start">
                                <span>Foto-Scan mit KI</span>
                                <span class="px-2 py-0.5 text-[9px] font-black uppercase tracking-wider bg-[var(--theme-color-10)] text-[var(--theme-color)] rounded-md border border-[var(--theme-color-20)]">Auto-Erkennung</span>
                            </h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Fotografiere einen Beleg oder lade ein Foto hoch – Händler, Betrag, Datum & Kategorie werden automatisch erfasst.</p>
                        </div>
                    </div>

                    <div class="flex flex-col items-center sm:items-end gap-2 w-full sm:w-auto shrink-0">
                        <label for="receiptCameraInput" class="cursor-pointer px-6 py-3 rounded-xl bg-[var(--theme-color)] text-gray-950 font-bold text-xs hover:brightness-105 transition-all shadow-sm hover:shadow-[0_0_20px_var(--theme-color-30)] flex items-center justify-center gap-2.5 w-full sm:w-auto">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Beleg fotografieren / Scan</span>
                            <input type="file" wire:model="specialPhoto" accept="image/*,.pdf" capture="environment" id="receiptCameraInput" class="hidden">
                        </label>
                    </div>
                </div>

                {{-- Auto-Save Checkbox --}}
                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between flex-wrap gap-2">
                    <label class="flex items-center gap-2.5 cursor-pointer text-xs text-gray-600 dark:text-gray-400 select-none">
                        <input type="checkbox" wire:model.live="autoSaveAfterScan" style="accent-color: var(--theme-color);" class="w-4 h-4 rounded border-gray-300 dark:border-gray-700 text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                        <span class="font-medium">Nach erfolgreichem Foto-Scan sofort automatisch in Buchhaltung speichern</span>
                    </label>
                    <span class="text-[11px] text-gray-400 dark:text-gray-500 font-mono">JPG, PNG, WebP oder PDF</span>
                </div>

                {{-- Livewire Loading Bar für KI-Analyse (in Theme Color) --}}
                <div wire:loading wire:target="specialPhoto,specialFiles" class="mt-4 p-4 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] shadow-[0_0_20px_var(--theme-color-15)] text-gray-900 dark:text-white text-xs font-semibold flex items-center gap-3 animate-pulse">
                    <div class="w-8 h-8 rounded-xl bg-[var(--theme-color-20)] text-[var(--theme-color)] flex items-center justify-center shrink-0 border border-[var(--theme-color-30)]">
                        <svg class="animate-spin h-5 w-5 shrink-0 text-[var(--theme-color)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-bold flex items-center gap-2">
                            <span>🤖 KI analysiert Belegfoto...</span>
                            <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-[var(--theme-color-20)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">Gemini Vision</span>
                        </p>
                        <p class="text-[11px] font-normal text-gray-600 dark:text-gray-300 mt-0.5">Händler, Gesamtsumme, Belegdatum, MwSt. und Kategorie werden automatisch ausgelesen.</p>
                    </div>
                </div>

                {{-- Erfolgs-Meldung & Vorschau (in Theme Color & sicherer PDF-Check ohne Exception) --}}
                @if($isAutoFilled)
                    <div class="mt-4 p-4 rounded-xl bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] shadow-[0_0_20px_var(--theme-color-15)] text-gray-900 dark:text-white text-xs font-medium flex items-center justify-between gap-4 animate-fade-in">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-[var(--theme-color-20)] text-[var(--theme-color)] flex items-center justify-center shrink-0 border border-[var(--theme-color-30)] shadow-inner">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            </div>
                            <div>
                                <p class="font-bold text-[var(--theme-color)] flex items-center gap-2">
                                    <span>Beleg erfolgreich erkannt!</span>
                                </p>
                                <p class="text-[11px] text-gray-600 dark:text-gray-300 mt-0.5">{{ $aiScanSuccessMessage ?: 'Daten wurden automatisch in das Formular eingetragen.' }}</p>
                            </div>
                        </div>
                        @if($this->isPhotoPreviewable())
                            <div class="relative w-12 h-12 rounded-xl overflow-hidden border border-[var(--theme-color-40)] shadow-sm shrink-0 bg-white dark:bg-gray-800">
                                <img src="{{ $specialPhoto->temporaryUrl() }}" class="w-full h-full object-cover" alt="Beleg Vorschau">
                            </div>
                        @elseif($specialPhoto)
                            <div class="relative w-12 h-12 rounded-xl flex flex-col items-center justify-center bg-[var(--theme-color-20)] border border-[var(--theme-color-30)] text-[var(--theme-color)] shadow-sm shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span class="text-[8px] font-black uppercase tracking-tight mt-0.5">
                                    {{ strtoupper(pathinfo($specialPhoto->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'PDF') }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @php
                $inputClass = "w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-950 py-3.5 px-5 text-sm font-medium text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--theme-color-50)] focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all shadow-inner outline-none";
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5 ml-1">Titel / Verwendungszweck</label>
                    <input type="text" wire:model="specialTitle" placeholder="z.B. Bauhaus - Schrauben & Dübel" class="{{ $inputClass }}">
                    @error('specialTitle') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5 ml-1">Betrag (Ausgabe: z.B. -49.99)</label>
                    <input type="text" wire:model="specialAmount" placeholder="-49.99" class="{{ $inputClass }} font-mono">
                    @error('specialAmount') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5 ml-1">Kategorie</label>
                    <input type="text" list="category-list" wire:model="specialCategory" placeholder="Kategorie (z.B. Werkzeug)" class="{{ $inputClass }}">
                    <datalist id="category-list">
                        @foreach($categories as $catName)
                            <option value="{{ $catName }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5 ml-1">Datum</label>
                    <input type="date" wire:model="specialDate" class="{{ $inputClass }} [&::-webkit-calendar-picker-indicator]:opacity-60 dark:[&::-webkit-calendar-picker-indicator]:filter-[invert(0.5)] cursor-pointer text-gray-700 dark:text-gray-400">
                    @error('specialDate') <span class="text-[10px] font-bold text-red-400 mt-1.5 ml-2 block uppercase tracking-widest">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 mb-1.5 ml-1">Ort / Geschäft (Optional)</label>
                <input type="text" wire:model="specialLocation" placeholder="z.B. Gifhorn" class="{{ $inputClass }}">
            </div>

            {{-- Business Toggle Kachel --}}
            <div x-data="{ open: @entangle('specialIsBusiness') }" class="bg-[var(--theme-color-5)] dark:bg-gray-950/60 rounded-[1.5rem] border border-[var(--theme-color-15)] dark:border-gray-800/80 p-5 shadow-inner">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-300">Geschäftliche Ausgabe?</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Aktivieren für Vorsteuerabzug und geschäftliche Rechnungsnummer.</p>
                    </div>
                    <button type="button"
                            x-on:click="open = !open; $wire.set('specialIsBusiness', open)"
                            :class="open ? 'bg-[var(--theme-color)] border-[var(--theme-color)]' : 'bg-gray-200 dark:bg-gray-800 border-gray-300 dark:border-gray-700'"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 transition-colors duration-300 ease-in-out focus:outline-none shadow-inner">
                        <span aria-hidden="true" :class="open ? 'translate-x-5 bg-white shadow-sm dark:shadow-[0_0_10px_var(--theme-color-50)]' : 'translate-x-0 bg-gray-400'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full ring-0 transition duration-300 ease-in-out"></span>
                    </button>
                </div>

                @if($specialIsBusiness)
                    <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4 animate-fade-in-down">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1 ml-1">Rechnungs- / Belegnummer</label>
                            <input type="text" wire:model="specialInvoiceNumber" placeholder="z.B. RE-2026-0042" class="{{ $inputClass }} !py-3 !text-xs font-mono">
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-1 ml-1">MwSt.-Satz</label>
                            <select wire:model="specialTaxRate" class="{{ $inputClass }} !py-3 !text-xs appearance-none cursor-pointer">
                                <option value="{{ shop_setting('default_tax_rate', 19) }}" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">{{ shop_setting('default_tax_rate', 19) }}% MwSt. (Standard)</option>
                                <option value="7" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">7% MwSt. (Ermäßigt)</option>
                                <option value="0" class="bg-white dark:bg-gray-900 text-gray-900 dark:text-white">0% / Steuerfrei</option>
                            </select>
                        </div>
                    </div>
                @endif

                {{-- Dateiupload Kachel --}}
                <div class="mt-6 border-t border-gray-200 dark:border-gray-800 pt-5">
                    <label class="block text-[9px] font-black text-gray-500 dark:text-gray-400 uppercase tracking-[0.2em] mb-3 ml-1">
                        Weitere Belege / E-Rechnungen (ZUGFeRD / XRechnung / Fotos)
                    </label>
                    <div class="relative w-full">
                        <input type="file" wire:model="specialFiles" multiple accept=".pdf,.xml,.jpg,.png,.jpeg,.webp"
                               class="block w-full text-xs text-gray-600 dark:text-gray-400 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:tracking-widest file:bg-[var(--theme-color-10)] file:text-[var(--theme-color)] file:border file:border-[var(--theme-color-20)] hover:file:bg-[var(--theme-color-20)] file:transition-colors file:cursor-pointer cursor-pointer">

                        <div wire:loading wire:target="specialFiles" class="absolute right-2 top-1/2 -translate-y-1/2 text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] flex items-center gap-2 bg-white dark:bg-gray-950 px-3 py-1.5 rounded-lg border border-[var(--theme-color-20)] shadow-sm">
                            <svg class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Analysiere Datei...
                        </div>
                    </div>
                </div>
            </div>

            <button wire:click="createSpecial" wire:loading.attr="disabled" class="w-full bg-[var(--theme-color)] hover:brightness-105 text-gray-950 px-8 py-4 rounded-xl text-xs font-black uppercase tracking-[0.2em] transition-all shadow-sm hover:shadow-[0_0_25px_var(--theme-color-40)] hover:scale-[1.01] mt-4 flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60">
                <svg wire:loading wire:target="createSpecial" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-950" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                <span wire:loading.remove wire:target="createSpecial">Eintrag speichern</span>
                <span wire:loading wire:target="createSpecial">Wird gespeichert...</span>
                <svg wire:loading.remove wire:target="createSpecial" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
            </button>

        </div>
    </div>
</div>
