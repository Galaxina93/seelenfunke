<div>
    @if($isSubmitted)
        <div class="bg-emerald-50 border border-emerald-200 p-8 rounded-2xl text-center shadow-sm">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-6">
                <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Widerruf erfolgreich eingegangen</h2>
            <p class="text-gray-700 leading-relaxed max-w-lg mx-auto mb-6">
                Ihre Widerrufserklärung wurde an uns übermittelt. Wir haben Ihnen soeben eine automatische Eingangsbestätigung an <strong>{{ $email }}</strong> gesendet.
            </p>
            <p class="text-sm text-gray-500 bg-white p-4 rounded-xl shadow-inner border border-gray-100 max-w-xl mx-auto">
                <strong>Hinweis zur rechtlichen Wirksamkeit:</strong><br>
                Diese Eingangsbestätigung dokumentiert lediglich den fristgerechten Zugang Ihrer Erklärung. Sie stellt keine inhaltliche Anerkennung der rechtlichen Wirksamkeit des Widerrufs dar (insbesondere bei von vornherein ausgeschlossenen personalisierten Waren). Wir prüfen Ihr Anliegen und melden uns in Kürze manuell bei Ihnen.
            </p>
        </div>
    @else
        <form wire:submit.prevent="submitRevocation" class="bg-white border border-gray-200 p-6 md:p-8 rounded-2xl shadow-sm">
            <div class="mb-8">
                <div class="flex items-start gap-4 bg-amber-50 border-l-4 border-amber-500 p-5 rounded-r-xl">
                    <svg class="w-6 h-6 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h4 class="font-bold text-amber-900 text-sm">Wichtiger rechtlicher Hinweis (Personalisierte Ware)</h4>
                        <p class="text-amber-800 text-xs mt-1 leading-relaxed">
                            Ein gesetzliches Widerrufsrecht besteht gemäß § 312g Abs. 2 Nr. 1 BGB nicht bei Fernabsatzverträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung maßgeblich ist. <strong>Zusammengefasst: Personalisierte Gravur-Artikel können nicht widerrufen werden.</strong> <br>Der nachfolgende elektronische Widerruf ist ausschließlich für unsere (zukünftigen) Standardwaren ohne Anpassungen vorgesehen.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-bold text-gray-700 mb-2">Vollständiger Name des Verbrauchers *</label>
                    <input type="text" id="name" wire:model="name" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all @error('name') border-red-500 ring-2 ring-red-500/20 @enderror" placeholder="Max Mustermann">
                    @error('name') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- E-Mail --}}
                <div>
                    <label for="email" class="block text-sm font-bold text-gray-700 mb-2">E-Mail-Adresse für die Eingangsbestätigung *</label>
                    <input type="email" id="email" wire:model="email" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all @error('email') border-red-500 ring-2 ring-red-500/20 @enderror" placeholder="max@beispiel.de">
                    @error('email') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Bestellnummer --}}
                <div>
                    <label for="order_number" class="block text-sm font-bold text-gray-700 mb-2">Angaben zur Identifizierung des Vertrags *</label>
                    <input type="text" id="order_number" wire:model="order_number" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all @error('order_number') border-red-500 ring-2 ring-red-500/20 @enderror" placeholder="Z. B. Bestellnummer, Auftragsnummer">
                    @error('order_number') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Optionale Artikel --}}
                <div>
                    <label for="items" class="block text-sm font-bold text-gray-700 mb-2">Zusätzliche Angaben (Optional)</label>
                    <textarea id="items" wire:model="items" rows="3" class="w-full bg-gray-50 border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all custom-scrollbar" placeholder="Sollten Sie nur einen Teil der Bestellung widerrufen wollen, geben Sie hier bitte die betroffenen Artikel an."></textarea>
                    @error('items') <span class="text-red-500 text-xs font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 border-t border-gray-100 flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-xs text-gray-500 w-full md:w-2/3">
                        Nach Klick auf den Button erhalten Sie unverzüglich eine Eingangsbestätigung an die oben genannte E-Mail-Adresse.
                    </p>
                    
                    {{-- Der rechtlich zwingend vorgeschriebene Button: "Widerruf bestätigen" --}}
                    <button type="submit" class="w-full md:w-auto bg-gray-900 text-white font-bold py-3 px-8 rounded-lg shadow-md hover:bg-black hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 flex justify-center items-center group whitespace-nowrap">
                        <span wire:loading.remove wire:target="submitRevocation">Widerruf bestätigen</span>
                        <div wire:loading wire:target="submitRevocation" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                    </button>
                </div>
            </div>
        </form>
    @endif
</div>
