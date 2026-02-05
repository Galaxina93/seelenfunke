<div class="p-8 animate-fade-in max-w-3xl mx-auto">
    <div class="text-center mb-8">
        <h3 class="text-xl font-bold text-gray-900">Fast geschafft!</h3>
        <p class="text-gray-500">Geben Sie Ihre Kontaktdaten ein, damit wir Ihnen das Angebot als PDF zusenden können.</p>
    </div>

    <form wire:submit.prevent="submit" class="grid grid-cols-1 gap-4 sm:grid-cols-2 bg-gray-50 p-4 sm:p-6 rounded-xl border border-gray-100">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Vorname *</label>
            <input wire:model.live="form.vorname" type="text" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
        </div>
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1">Nachname *</label>
            <input wire:model.live="form.nachname" type="text" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
        </div>
        <div class="col-span-1 sm:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Firma / Verein (Optional)</label>
            <input wire:model.live="form.firma" type="text" class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
        </div>
        <div class="col-span-1 sm:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">E-Mail für Angebot *</label>
            <input wire:model.live="form.email" type="email" required class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
        </div>

        {{-- LAND AUSWAHL (DYNAMISCH) --}}
        <div class="col-span-1 sm:col-span-2">
            <label class="block text-sm font-bold text-gray-700 mb-1">Land für Versand *</label>
            <select wire:model.live="form.country" class="w-full rounded border-gray-300 focus:ring-primary focus:border-primary p-2">
                @foreach(shop_setting('active_countries', ['DE' => 'Deutschland']) as $code => $name)
                    <option value="{{ $code }}">{{ $name }}</option>
                @endforeach
            </select>

            {{-- Dynamischer Hinweis unter dem Dropdown --}}
            <div class="mt-1.5 text-xs text-gray-500 animate-fade-in">
                @if($form['country'] === 'DE')
                    <span class="text-green-600 font-bold">Tipp:</span> Versandkostenfrei ab 50,00 € Warenwert (DE). Sonst pauschal 4,90 €.
                @else
                    <span class="text-blue-600 font-bold">Hinweis:</span> Versandkosten werden nach Gewicht & Zone berechnet.
                @endif
            </div>
        </div>

        <div class="col-span-1 sm:col-span-2 pt-6 border-t border-gray-100 mt-4 min-h-[100px] flex items-center justify-center sm:justify-end">

            {{-- ZURÜCK BUTTON: Verschwindet beim Laden ebenfalls, um Fokus zu halten --}}
            <div wire:loading.remove wire:target="submit" class="w-full flex flex-col sm:flex-row justify-between items-center gap-4">
                <button type="button"
                        wire:click="goBack"
                        class="text-gray-400 hover:text-gray-600 underline text-sm transition">
                    Zurück zur Übersicht
                </button>

                <button type="submit"
                        class="bg-green-600 text-white px-10 py-4 rounded-xl font-bold hover:bg-green-700 shadow-xl shadow-green-900/10 transition-all transform hover:-translate-y-1 active:scale-95 w-full sm:w-auto">
                    Kostenloses Angebot anfordern
                </button>
            </div>

            {{-- DIE GROSSE WARTE-ZONE: Erscheint NUR beim Laden --}}
            <div wire:loading wire:target="submit" class="w-full">
                <div class="flex flex-col text-left animate-fade-in">
                    <div class="flex items-center gap-3 mb-3">
                                    <span class="text-gray-900 font-serif font-bold text-xl lg:text-2xl italic">
                                        Einen kleinen Moment bitte...
                                    </span>
                        {{-- Ein etwas größerer, eleganter Spinner --}}
                        <svg class="animate-spin h-6 w-6 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div class="bg-primary/5 border-l-4 border-primary text-left p-4 rounded-r-xl max-w-md shadow-sm">
                        <p class="text-sm text-gray-700 leading-relaxed">
                            <strong>Wir bereiten alles für Sie vor 💛</strong><br>
                            Ihr individuelles PDF-Angebot wird gerade erstellt und in wenigen Sekunden direkt an Ihre E-Mail-Adresse versendet.
                        </p>
                        <div class="mt-2 flex items-center justify-center sm:justify-end gap-1 text-[10px] text-primary font-bold uppercase tracking-widest">
                                        <span class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                                        </span>
                            System arbeitet
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

</div>
