@if($isPhotoIssueModalOpen)
    <div class="fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-black/50 dark:bg-black/80 backdrop-blur-md p-4 pt-[8vh] sm:pt-4 transition-all">
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden transform transition-all animate-fade-in-up border border-gray-200 dark:border-gray-800">

            {{-- HEADER --}}
            <div class="bg-gray-50 dark:bg-gray-950 px-6 py-5 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="bg-amber-500/10 text-amber-500 p-2.5 rounded-xl border border-amber-500/20 shadow-sm">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white">Foto bemängeln & Nachbesserung</h3>
                        <p class="text-[10px] uppercase tracking-widest text-amber-600 dark:text-amber-400 font-bold">Kundenkommunikation • Manufaktur</p>
                    </div>
                </div>
                <button wire:click="closePhotoIssueModal" type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 cursor-pointer">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- CONTENT --}}
            <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar">
                {{-- DATEI INFO --}}
                <div class="p-3 bg-gray-50 dark:bg-gray-950 rounded-xl border border-gray-200 dark:border-gray-800 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-gray-200 dark:bg-gray-800 flex items-center justify-center font-bold text-xs uppercase text-gray-700 dark:text-gray-300 shrink-0">
                        {{ strtoupper(pathinfo($photoIssueFileName, PATHINFO_EXTENSION) ?: 'IMG') }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-gray-900 dark:text-white truncate" title="{{ $photoIssueFileName }}">{{ $photoIssueFileName }}</p>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400">Artikel: <strong class="text-gray-700 dark:text-gray-300">{{ $photoIssueProductName }}</strong></p>
                    </div>
                </div>

                {{-- MÄNGELGRUND SCHNELLAUSWAHL --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                        Grund der Beanstandung:
                    </label>
                    <div class="space-y-2">
                        @foreach([
                            'resolution' => ['label' => 'Zu geringe Auflösung / unscharf / verpixelt', 'desc' => 'Für feine Lasergravuren werden Gesichter/Details verschwimmen.'],
                            'contrast' => ['label' => 'Zu dunkel / überbelichtet (fehlender Kontrast)', 'desc' => 'Helle oder dunkle Bereiche können vom Laser nicht sauber differenziert werden.'],
                            'crop' => ['label' => 'Motiv ungünstig abgeschnitten / Gesichter am Rand', 'desc' => 'Wichtige Bildbereiche liegen auf dem Rand oder werden durch die Gravurform verdeckt.'],
                            'other' => ['label' => 'Sonstige Anmerkung (siehe Notiz unten)', 'desc' => 'Individuelle Rückfrage oder Besonderheit der Manufaktur.']
                        ] as $key => $opt)
                            <label class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition-all {{ $photoIssueReasonKey === $key ? 'bg-amber-50/70 dark:bg-amber-950/30 border-amber-400 dark:border-amber-600 ring-1 ring-amber-400/50' : 'bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900' }}">
                                <input type="radio" wire:model.live="photoIssueReasonKey" value="{{ $key }}" class="mt-0.5 text-amber-500 focus:ring-amber-400">
                                <div>
                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ $opt['label'] }}</p>
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5 leading-snug">{{ $opt['desc'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- PERSÖNLICHE NOTIZ / TIPP --}}
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">
                        Persönlicher Hinweis / Tipp für den Kunden (optional):
                    </label>
                    <textarea wire:model="photoIssueCustomNote"
                              rows="3"
                              class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950 dark:text-white focus:ring-amber-400 focus:border-amber-400 p-3 leading-relaxed"
                              placeholder="z. B.: Bitte sende das Originalfoto direkt aus deiner Handy-Fotogalerie und keinen WhatsApp-Screenshot, damit die Details gestochen scharf gelasert werden."></textarea>
                </div>

                {{-- OPTIONEN --}}
                <div class="space-y-2.5 pt-3 border-t border-gray-100 dark:border-gray-800">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="photoIssueSetPending" class="mt-0.5 rounded text-amber-500 focus:ring-amber-400">
                        <span class="text-xs text-gray-700 dark:text-gray-300">
                            Status auf <strong>„Wartend“</strong> setzen (pausiert Fertigung & aktiviert „Artikel anpassen“ für Kunden)
                        </span>
                    </label>

                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model="photoIssueSendMail" class="mt-0.5 rounded text-amber-500 focus:ring-amber-400">
                        <span class="text-xs text-gray-700 dark:text-gray-300">
                            Kunden per E-Mail benachrichtigen mit Direktlink zum Editor
                        </span>
                    </label>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <button wire:click="closePhotoIssueModal" type="button" class="text-xs font-bold text-gray-500 hover:text-gray-900 dark:hover:text-white uppercase tracking-widest transition-colors cursor-pointer">
                    Abbrechen
                </button>

                <button wire:click="submitPhotoIssue"
                        type="button"
                        class="flex items-center gap-2 px-5 py-2.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-gray-950 font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    Nachbesserung anfordern
                </button>
            </div>
        </div>
    </div>
@endif
