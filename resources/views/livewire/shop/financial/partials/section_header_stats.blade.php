<div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">

    {{-- OBERER BEREICH: STATISTIKEN --}}
    <div class="p-8 border-b border-gray-100">
        <div class="flex flex-col md:flex-row items-center justify-between gap-8">

            {{-- Linke Seite der Stats: Hauptzahl (Frei Verfügbar) --}}
            <div class="flex-1 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start gap-3 mb-1">
                    <span class="text-sm font-bold text-gray-400 uppercase tracking-wider">Frei Verfügbar</span>

                    {{-- Toggle Switch --}}
                    <label class="inline-flex items-center cursor-pointer group">
                        <input type="checkbox" wire:model.live="excludeSpecialExpenses" class="sr-only peer">
                        <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-teal-500"></div>
                        <span class="ms-2 text-xs text-gray-400 group-hover:text-gray-600 transition select-none">Ohne Sonderausgaben</span>
                    </label>
                </div>

                <div class="text-5xl font-extrabold tracking-tight {{ ($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available']) >= 0 ? 'text-teal-600' : 'text-red-500' }}">
                    {{ number_format($excludeSpecialExpenses ? ($stats['total_budget'] + $stats['fixed_expenses']) : $stats['available'], 2, ',', '.') }} €
                </div>
                <p class="text-xs text-gray-400 mt-2">Berechnet basierend auf Einnahmen abzüglich Fixkosten {{ !$excludeSpecialExpenses ? 'und Sonderausgaben' : '' }}.</p>
            </div>

            {{-- Trennlinie (Desktop) --}}
            <div class="hidden md:block w-px h-24 bg-gray-100"></div>

            {{-- Rechte Seite der Stats: Aufschlüsselung --}}
            <div class="flex flex-col sm:flex-row gap-8 text-center sm:text-left">

                {{-- Budget --}}
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Monatsbudget</div>
                    <div class="text-xl font-bold text-emerald-600">
                        + {{ number_format($stats['total_budget'], 2, ',', '.') }} €
                    </div>
                </div>

                {{-- Fixkosten --}}
                <div>
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Fixkosten</div>
                    <div class="text-xl font-bold text-rose-500">
                        {{ number_format($stats['fixed_expenses'], 2, ',', '.') }} €
                    </div>
                </div>

                {{-- Sonderausgaben (Ausgegraut wenn inaktiv) --}}
                <div class="transition-opacity duration-300 {{ $excludeSpecialExpenses ? 'opacity-30 grayscale' : 'opacity-100' }}">
                    <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Sonderausgaben</div>
                    <div class="text-xl font-bold text-orange-500">
                        {{ number_format($stats['special_expenses'], 2, ',', '.') }} €
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- UNTERER BEREICH: SCHNELLERFASSUNG FORMULAR --}}
    <div class="p-8 bg-gray-50">
        <h3 class="text-base font-bold text-gray-700 text-center mb-6 flex items-center justify-center gap-2">
            <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Schnellerfassung Sonderausgabe
        </h3>

        {{-- Container für zentriertes Layout --}}
        <div class="max-w-2xl mx-auto flex flex-col gap-4">

            {{-- Titel --}}
            <div class="w-full">
                <input type="text" wire:model="specialTitle" placeholder="Was? (z.B. Tanken)" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                @error('specialTitle') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
            </div>

            {{-- Kategorie & Ort --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="relative w-full">
                    <input list="category-options-create-header" wire:model="specialCategory" placeholder="Kategorie" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                    <datalist id="category-options-create-header">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}"></option>
                        @endforeach
                    </datalist>
                </div>
                <input type="text" wire:model="specialLocation" placeholder="Wo?" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
            </div>

            {{-- Betrag & Datum --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="relative w-full">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">€</span>
                    <input type="number" step="0.01" wire:model="specialAmount" placeholder="0.00" class="pl-8 w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 font-mono bg-white shadow-sm text-gray-800 placeholder-gray-400 transition hover:border-orange-300">
                    @error('specialAmount') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>
                <div class="w-full">
                    <input type="date" wire:model="specialDate" class="w-full text-base p-3 rounded-xl border-gray-300 focus:ring-orange-400 cursor-pointer bg-white shadow-sm text-gray-800 transition hover:border-orange-300">
                    @error('specialDate') <span class="text-xs text-red-500 mt-1 block font-medium">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                {{-- Gewerbe Checkbox --}}
                <label class="inline-flex items-center cursor-pointer select-none group">
                    <input type="checkbox" wire:model="specialIsBusiness" class="sr-only peer">
                    <div class="relative w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    <span class="ms-3 text-sm font-medium text-gray-500 group-hover:text-gray-700 transition">Gewerblich</span>
                </label>

                {{-- Speichern Button --}}
                <button wire:click="createSpecial" class="w-full sm:w-auto bg-orange-500 text-white px-8 py-3 rounded-xl text-base font-bold hover:bg-orange-600 transition shadow-lg shadow-orange-100 transform hover:scale-[1.02] active:scale-95">
                    Eintrag speichern
                </button>
            </div>
        </div>

    </div>
</div>
