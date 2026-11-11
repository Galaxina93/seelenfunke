@if($currentStep === 1)
    <div class="space-y-6 md:space-y-8">

        @php
            $cardClass = "bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-sm dark:shadow-2xl border border-gray-200 dark:border-gray-800 p-6 sm:p-8 animate-fade-in-up transition-colors duration-300";
            $inputClassStep1 = "w-full px-4 py-3.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:bg-white dark:focus:bg-black focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-30)] transition-all dark:shadow-inner outline-none";
            $labelClassStep1 = "block text-[9px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-500 mb-2 ml-1";

            $currentPersMode = strtolower(trim((string) ($configSettings['personalization_mode'] ?? 'studio')));
            if (!in_array($currentPersMode, ['studio', 'custom_fields'], true)) {
                $currentPersMode = 'studio';
            }
            $customFieldsList = isset($configSettings['custom_fields']) && is_array($configSettings['custom_fields'])
                ? array_values($configSettings['custom_fields'])
                : [];

            $subStep1Done = !empty($type) && (!$is_personalizable || $currentPersMode !== 'custom_fields' || count($customFieldsList) > 0);
            $subStep2Done = !empty(trim((string) $name))
                && (float) $price_input > 0
                && ($type !== 'physical' || ($purchase_price_input !== '' && (float) $purchase_price_input > 0));
            $subStep3Done = true;
            $subStep4Done = !empty(trim((string) $seo_title)) || !empty(trim((string) $seo_description));
            $subStep5Done = !empty(trim((string) $sku))
                && ($type !== 'physical' || (!empty($supplier_id) && !empty(trim((string) $reorder_url))));

            $step1SubSteps = [
                1 => ['title' => 'Produkttyp wählen', 'done' => $subStep1Done, 'optional' => false],
                2 => ['title' => 'Basisinformationen', 'done' => $subStep2Done, 'optional' => false],
                3 => ['title' => 'Steuer & Mehrwertsteuer', 'done' => $subStep3Done, 'optional' => false],
                4 => ['title' => 'SEO & Suchmaschinen', 'done' => $subStep4Done, 'optional' => true],
                5 => ['title' => 'Produktidentifikatoren & Lieferant', 'done' => $subStep5Done, 'optional' => false],
            ];
        @endphp

        {{-- VISUELLE UNTER-STEPS UNTER "1. BASIS & LIEFERANT" --}}
        <div class="bg-white dark:bg-gray-900/80 backdrop-blur-xl rounded-2xl border border-gray-200 dark:border-gray-800 p-2.5 sm:p-3 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-2">
                @foreach($step1SubSteps as $subIdx => $subMeta)
                    @php
                        $isActiveSub = ($step1SubStep === $subIdx);
                    @endphp
                    <button type="button"
                            wire:click="goToStep1SubStep({{ $subIdx }})"
                            class="text-left px-3.5 py-2.5 rounded-xl border transition-all cursor-pointer flex items-center justify-between gap-2 {{ $isActiveSub
                                ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white shadow-2xs'
                                : 'border-gray-200/80 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/60 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-6 h-6 rounded-lg text-[10px] font-black flex items-center justify-center shrink-0 transition-colors {{ $isActiveSub
                                ? 'bg-[var(--theme-color)] text-white dark:text-gray-900'
                                : ($subMeta['done'] ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-gray-200 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                                {{ $subIdx }}
                            </span>
                            <span class="text-xs font-bold truncate">{{ $subMeta['title'] }}</span>
                        </div>

                        @if($subMeta['done'])
                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[9px] font-black shrink-0" title="Vollständig">✓</span>
                        @elseif($subMeta['optional'])
                            <span class="px-1.5 py-0.5 rounded-md bg-gray-100 dark:bg-gray-900 text-gray-500 dark:text-gray-400 text-[9px] font-bold shrink-0">Opt.</span>
                        @else
                            <span class="px-1.5 py-0.5 rounded-md bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[9px] font-bold shrink-0">Offen</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- SUB-STEP 1: PRODUKTTYP WÄHLEN --}}
        @if($step1SubStep === 1)
            <div class="{{ $cardClass }}">
                <div class="flex items-center justify-between gap-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <div class="flex items-center gap-2">
                        <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">1. Produkttyp wählen</h2>
                        @include('components.alerts.info-tooltip', ['key' => 'legal_type'])
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Unter-Schritt 1 / 5</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
                    {{-- OPTION: PHYSISCH --}}
                    <label class="cursor-pointer relative group">
                        <input type="radio" wire:model.live="type" value="physical" class="sr-only">
                        <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                    {{ $type === 'physical'
                                        ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white dark:shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                        : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-500 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-900/50 shadow-sm'
                                    }}">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                        {{ $type === 'physical' ? 'bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 dark:drop-shadow-[0_0_10px_currentColor]' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 opacity-80' }}">📦</div>
                            <div>
                                <span class="font-serif font-bold text-xl block mb-1">Physisch</span>
                                <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Versandartikel mit Konfigurator</span>
                            </div>
                            <div class="absolute top-4 right-4 text-[var(--theme-color)] transition-opacity {{ $type === 'physical' ? 'opacity-100 dark:drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                        </div>
                    </label>

                    {{-- OPTION: DIGITAL --}}
                    <label class="cursor-pointer relative group">
                        <input type="radio" wire:model.live="type" value="digital" class="sr-only">
                        <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                    {{ $type === 'digital'
                                        ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white dark:shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                        : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-500 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-900/50 shadow-sm'
                                    }}">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                        {{ $type === 'digital' ? 'bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 dark:drop-shadow-[0_0_10px_currentColor]' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 opacity-80' }}">☁️</div>
                            <div>
                                <span class="font-serif font-bold text-xl block mb-1">Digital</span>
                                <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Download oder E-Book</span>
                            </div>
                            <div class="absolute top-4 right-4 text-[var(--theme-color)] transition-opacity {{ $type === 'digital' ? 'opacity-100 dark:drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                        </div>
                    </label>

                    {{-- OPTION: DIENSTLEISTUNG --}}
                    <label class="cursor-pointer relative group">
                        <input type="radio" wire:model.live="type" value="service" class="sr-only">
                        <div class="p-6 rounded-[1.5rem] border-2 transition-all duration-300 ease-in-out text-center h-full flex flex-col items-center justify-center gap-4
                                    {{ $type === 'service'
                                        ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white dark:shadow-[0_0_20px_rgba(197,160,89,0.15)] scale-[1.02]'
                                        : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-500 hover:border-gray-300 dark:hover:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-900/50 shadow-sm'
                                    }}">
                            <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mb-1 transition-colors shadow-inner
                                        {{ $type === 'service' ? 'bg-[var(--theme-color)] border border-[var(--theme-color-50)] text-white dark:text-gray-900 dark:drop-shadow-[0_0_10px_currentColor]' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 opacity-80' }}">🤝</div>
                            <div>
                                <span class="font-serif font-bold text-xl block mb-1">Dienstleistung</span>
                                <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block leading-relaxed">Service, Termin oder Beratung</span>
                            </div>
                            <div class="absolute top-4 right-4 text-[var(--theme-color)] transition-opacity {{ $type === 'service' ? 'opacity-100 dark:drop-shadow-[0_0_5px_currentColor]' : 'opacity-0' }}">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                        </div>
                    </label>
                </div>

                {{-- KOLLEKTIONS-BEREICH (WERT-GRUPPIERUNG BEI PHYSISCH) --}}
                @if($type === 'physical')
                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <span class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Kollektions-Bereich im Shop</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            {{-- MANUFAKTUR-KOLLEKTION --}}
                            <label class="cursor-pointer relative group">
                                <input type="radio" wire:model.live="collection_line" value="manufaktur" class="sr-only">
                                <div class="p-4 rounded-2xl border-2 transition-all duration-200 flex items-center gap-4
                                            {{ $collection_line === 'manufaktur'
                                                ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white'
                                                : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-500 hover:border-gray-300 dark:hover:border-gray-700'
                                            }}">
                                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0
                                                {{ $collection_line === 'manufaktur' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800' }}">✨</div>
                                    <div class="min-w-0 flex-1">
                                        <span class="font-serif font-bold text-base block">Manufaktur-Kollektion</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block">Standard-Sortiment (Alltags-Unikate)</span>
                                    </div>
                                </div>
                            </label>

                            {{-- ATELIER ECHTGOLD & SILBER --}}
                            <label class="cursor-pointer relative group">
                                <input type="radio" wire:model.live="collection_line" value="atelier" class="sr-only">
                                <div class="p-4 rounded-2xl border-2 transition-all duration-200 flex items-center gap-4
                                            {{ $collection_line === 'atelier'
                                                ? 'border-[#C5A059] bg-[#C5A059]/10 text-gray-900 dark:text-white'
                                                : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 text-gray-500 hover:border-gray-300 dark:hover:border-gray-700'
                                            }}">
                                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0
                                                {{ $collection_line === 'atelier' ? 'bg-[#C5A059] text-white dark:text-gray-900' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800' }}">👑</div>
                                    <div class="min-w-0 flex-1">
                                        <span class="font-serif font-bold text-base block">Atelier Echtgold &amp; Silber</span>
                                        <span class="text-[9px] font-black uppercase tracking-widest opacity-70 block">Exklusiver Premium-Bereich (333/585 Gold &amp; 925 Silber)</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                @endif

                {{-- Personalisierbar Toggle --}}
                <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-800">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <label class="flex items-center gap-4 cursor-pointer w-max group">
                            <div class="relative">
                                <input type="checkbox" wire:model.live="is_personalizable" class="sr-only">
                                <div class="block w-14 h-8 {{ $is_personalizable ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-800' }} rounded-full transition-colors duration-300 shadow-inner"></div>
                                <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition transform duration-300 ease-in-out {{ $is_personalizable ? 'translate-x-6 shadow-md' : '' }}"></div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-900 dark:text-white tracking-wide group-hover:text-[var(--theme-color)] transition-colors">Personalisierbares Produkt</div>
                                <div class="text-[11px] text-gray-600 dark:text-gray-500 font-medium">Wenn deaktiviert, wird das Produkt als fertiger Sofortkauf-Artikel (ohne Konfigurator) angeboten.</div>
                            </div>
                        </label>

                        @if(!$is_personalizable)
                            <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-500/15 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                                <span>✓</span>
                                <span>Standard-Produkt (Fertig vorproduziert / Sofortkauf)</span>
                            </span>
                        @endif
                    </div>
                </div>

                @if(!$is_personalizable)
                    <div class="mt-5 p-4 sm:p-5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-900/40 flex items-start gap-3.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 font-bold text-base">✓</div>
                        <div class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed space-y-1">
                            <p class="font-bold text-gray-900 dark:text-white">Modus: Fertiges Standard-Produkt (Bereit zum Kauf)</p>
                            <p>Ideal für vorproduzierte Bestseller (z. B. fertig gelaserte Spruch-Schlüsselanhänger oder Geschenk-Sets). Der Kunde wählt im Shop direkt die Menge und legt den Artikel ohne Konfigurator-Hürde in den Warenkorb. Es werden keine 3D-Screenshots benötigt.</p>
                        </div>
                    </div>
                @endif

                {{-- Unter-Switch & Dynamischer Feld-Baukasten wenn Personalisierbar aktiv ist --}}
                @if($is_personalizable)
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-800 space-y-6">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-3">
                                <span class="text-[10px] font-black uppercase tracking-widest text-[var(--theme-color)]">
                                    1.1 Personalisierungs-Variante wählen
                                </span>
                                <span class="text-[10px] font-semibold text-gray-400">
                                    Steuert die Darstellung im Shop, Warenkorb, Rechnung &amp; Admin-Cockpit
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {{-- MODUS A: INTERAKTIVER 2D/3D STUDIO KONFIGURATOR --}}
                                <button type="button"
                                        wire:click="setPersonalizationMode('studio')"
                                        class="text-left p-5 rounded-2xl border-2 transition-all cursor-pointer flex items-start gap-4 relative {{ $currentPersMode === 'studio'
                                            ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white shadow-sm'
                                            : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700' }}">
                                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0 {{ $currentPersMode === 'studio' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 font-bold' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800' }}">
                                        🎨
                                    </div>
                                    <div class="min-w-0 flex-1 pr-6">
                                        <div class="font-serif font-bold text-base text-gray-900 dark:text-white">
                                            Interaktiver 2D/3D-Live-Konfigurator (Studio)
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                                            Freies Zeichenbrett mit Live-Vorschau, 3D-Ansicht, Schriftarten, Vektor-Symbolen, eigenem Foto/Logo-Upload und automatischem Design-Screenshot.
                                        </p>
                                    </div>
                                    @if($currentPersMode === 'studio')
                                        <span class="absolute top-4 right-4 text-[var(--theme-color)]">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </span>
                                    @endif
                                </button>

                                {{-- MODUS B: SCHLANKE SCHNELL-PERSONALISIERUNG (DYNAMISCHE EINGABEFELDER) --}}
                                <button type="button"
                                        wire:click="setPersonalizationMode('custom_fields')"
                                        class="text-left p-5 rounded-2xl border-2 transition-all cursor-pointer flex items-start gap-4 relative {{ $currentPersMode === 'custom_fields'
                                            ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] text-gray-900 dark:text-white shadow-sm'
                                            : 'border-gray-200 dark:border-gray-800 bg-gray-50/70 dark:bg-gray-950/70 text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-700' }}">
                                    <div class="w-11 h-11 rounded-xl flex items-center justify-center text-xl shrink-0 {{ $currentPersMode === 'custom_fields' ? 'bg-[var(--theme-color)] text-white dark:text-gray-900 font-bold' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800' }}">
                                        ⚡
                                    </div>
                                    <div class="min-w-0 flex-1 pr-6">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-serif font-bold text-base text-gray-900 dark:text-white">
                                                Schnell-Personalisierung (Dynamische Felder)
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                                Kein 3D/2D nötig
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                                            Schlankes Direkt-Formular für schnelle Schmuck- &amp; Geschenkartikel: Definiere beliebig viele Eingabefelder (z. B. Wunschname, Datum, Symbol) inkl. eigenem <strong>!</strong>-Info-Tooltip je Feld.
                                        </p>
                                    </div>
                                    @if($currentPersMode === 'custom_fields')
                                        <span class="absolute top-4 right-4 text-[var(--theme-color)]">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </span>
                                    @endif
                                </button>
                            </div>
                        </div>

                        {{-- FALLS MODUS A (STUDIO): 3D Animation Toggle --}}
                        @if($currentPersMode === 'studio')
                            <div class="p-4 sm:p-5 rounded-2xl bg-gray-50 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 flex items-center justify-between gap-4">
                                <label class="flex items-center gap-4 cursor-pointer group">
                                    <div class="relative">
                                        <input type="checkbox" wire:model.live="three_d_active" class="sr-only">
                                        <div class="block w-14 h-8 {{ $three_d_active ? 'bg-[var(--theme-color)]' : 'bg-gray-300 dark:bg-gray-800' }} rounded-full transition-colors duration-300 shadow-inner"></div>
                                        <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition transform duration-300 ease-in-out {{ $three_d_active ? 'translate-x-6 shadow-md' : '' }}"></div>
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 dark:text-white tracking-wide group-hover:text-[var(--theme-color)] transition-colors">3D Animation im Studio-Konfigurator</div>
                                        <div class="text-[11px] text-gray-600 dark:text-gray-500 font-medium">Wenn deaktiviert, wird im Studio-Konfigurator nur die 2D-Ansicht geladen und keine 3D-Screenshots generiert.</div>
                                    </div>
                                </label>
                            </div>
                        @endif

                        {{-- DYNAMISCHER EINGABEFELD-BAUKASTEN (Für Neulings-Modus im Studio sowie für reine Schnell-Personalisierung) --}}
                        @if($is_personalizable)
                            @php
                                $configuratorFontsMap = app(\App\Services\ConfiguratorService::class)->getFonts();
                            @endphp
                            <div class="p-5 sm:p-6 rounded-3xl bg-gray-50/90 dark:bg-gray-950/80 border border-gray-200 dark:border-gray-800 space-y-6">
                                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-gray-200 dark:border-gray-800">
                                    <div>
                                        <h3 class="text-base sm:text-lg font-serif font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                            <span>⚡ {{ $currentPersMode === 'studio' ? 'Eingabefelder für Start-Option „Neuling“ (Einfache Variante)' : 'Dynamische Eingabefelder für Kunden' }}</span>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                                                {{ count($customFieldsList) }} {{ count($customFieldsList) === 1 ? 'Feld' : 'Felder' }}
                                            </span>
                                        </h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            Erstelle hier die Eingabefelder (z. B. Wunsch-Symbol + Wunschtext), die der Kunde im Modus <strong>Neuling (Einfache Variante)</strong> direkt ausfüllt.
                                        </p>
                                    </div>

                                    {{-- 1-Klick Schnell-Vorlagen --}}
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 mr-1">Schnell-Vorlage:</span>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('symbol_and_text')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            Symbol + Text (Neuling)
                                        </button>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('single_name')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            1× Wunschname
                                        </button>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('name_and_font')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            Wunschname + Schriftart
                                        </button>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('name_date_symbol')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            Name + Datum + Symbol
                                        </button>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('family_names_4')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            Bis zu 4 Namen
                                        </button>
                                        <button type="button"
                                                wire:click="applyCustomFieldsPreset('front_back_engraving')"
                                                class="px-2.5 py-1.5 rounded-lg text-[11px] font-bold bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                            Vorder- &amp; Rückseite (+5&nbsp;€)
                                        </button>
                                    </div>
                                </div>

                                @if(count($customFieldsList) === 0)
                                    <div class="p-6 rounded-2xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 text-center space-y-3">
                                        <p class="text-xs font-bold text-amber-800 dark:text-amber-300">
                                            Noch kein Eingabefeld definiert. Lege mindestens ein Feld an oder wähle oben eine Schnell-Vorlage.
                                        </p>
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            <button type="button"
                                                    wire:click="addCustomField"
                                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-xs font-black uppercase tracking-wider shadow-sm hover:opacity-90 transition-all cursor-pointer">
                                                <span>+ Erstes Eingabefeld anlegen</span>
                                            </button>
                                            <button type="button"
                                                    wire:click="addCustomFontField"
                                                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 text-xs font-black uppercase tracking-wider shadow-sm hover:border-[var(--theme-color)] transition-all cursor-pointer">
                                                <span>𝐀 + Schriftart-Auswahl anlegen</span>
                                            </button>
                                        </div>
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        @foreach($customFieldsList as $cfIdx => $cfItem)
                                            @php
                                                $cfType = $cfItem['type'] ?? 'text';
                                                $cfReq  = filter_var($cfItem['required'] ?? true, FILTER_VALIDATE_BOOLEAN);
                                                $selectedFontOptions = is_array($cfItem['options'] ?? null) && !empty($cfItem['options'])
                                                    ? $cfItem['options']
                                                    : array_keys($configuratorFontsMap);
                                            @endphp
                                            <div wire:key="custom-field-card-{{ $cfItem['id'] ?? $cfIdx }}"
                                                 class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-2xs space-y-4">
                                                <div class="flex items-center justify-between gap-3 pb-3 border-b border-gray-100 dark:border-gray-800">
                                                    <div class="flex items-center gap-2.5 flex-wrap">
                                                        <span class="w-6 h-6 rounded-lg bg-[var(--theme-color-10)] text-[var(--theme-color)] font-black text-xs flex items-center justify-center border border-[var(--theme-color-30)]">
                                                            {{ $cfIdx + 1 }}
                                                        </span>
                                                        <span class="text-xs font-bold text-gray-900 dark:text-white">
                                                            {{ !empty($cfItem['label']) ? $cfItem['label'] : 'Eingabefeld #' . ($cfIdx + 1) }}
                                                        </span>
                                                        @if($cfType === 'font')
                                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300">
                                                                𝐀 Schriftart ({{ count($selectedFontOptions) }}/{{ count($configuratorFontsMap) }} aktiv · Standard: {{ in_array('Arial', $selectedFontOptions, true) ? 'Arial' : ($selectedFontOptions[0] ?? 'Arial') }})
                                                            </span>
                                                        @elseif($cfReq)
                                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-rose-50 dark:bg-rose-500/15 text-rose-600 dark:text-rose-400">Pflichtfeld *</span>
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">Optional</span>
                                                        @endif
                                                        @if((float) str_replace(',', '.', (string) ($cfItem['surcharge_price'] ?? 0)) > 0)
                                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300">
                                                                + {{ number_format((float) str_replace(',', '.', (string) $cfItem['surcharge_price']), 2, ',', '.') }}&nbsp;€ Aufpreis
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="flex items-center gap-1.5">
                                                        <button type="button"
                                                                wire:click="moveCustomFieldUp({{ $cfIdx }})"
                                                                @if($cfIdx === 0) disabled @endif
                                                                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white disabled:opacity-30 cursor-pointer"
                                                                title="Nach oben schieben">
                                                            &uarr;
                                                        </button>
                                                        <button type="button"
                                                                wire:click="moveCustomFieldDown({{ $cfIdx }})"
                                                                @if($cfIdx === count($customFieldsList) - 1) disabled @endif
                                                                class="p-1.5 rounded-lg border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-gray-900 dark:hover:text-white disabled:opacity-30 cursor-pointer"
                                                                title="Nach unten schieben">
                                                            &darr;
                                                        </button>
                                                        <button type="button"
                                                                wire:click="removeCustomField({{ $cfIdx }})"
                                                                class="p-1.5 rounded-lg border border-red-200 dark:border-red-900/50 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 cursor-pointer"
                                                                title="Feld entfernen">
                                                            ✕
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4">
                                                    {{-- Feld-Bezeichnung (Label) --}}
                                                    <div class="{{ in_array($cfType, ['text', 'textarea'], true) ? 'sm:col-span-5' : ($cfType === 'font' ? 'sm:col-span-7' : 'sm:col-span-6') }}">
                                                        <label class="{{ $labelClassStep1 }}">Feld-Beschriftung (Label im Shop) *</label>
                                                        <input type="text"
                                                               wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.label"
                                                               class="{{ $inputClassStep1 }} !py-2.5 text-sm"
                                                               placeholder="z. B. Dein Wunschname">
                                                    </div>

                                                    {{-- Feld-Typ --}}
                                                    <div class="{{ in_array($cfType, ['text', 'textarea'], true) ? 'sm:col-span-3' : ($cfType === 'font' ? 'sm:col-span-5' : 'sm:col-span-4') }}">
                                                        <label class="{{ $labelClassStep1 }}">Feld-Art</label>
                                                        <select wire:model.live="configSettings.custom_fields.{{ $cfIdx }}.type"
                                                                class="{{ $inputClassStep1 }} !py-2.5 text-sm">
                                                            <option value="text">Textfeld (Einzeilig)</option>
                                                            <option value="textarea">Textfeld (Mehrzeilig)</option>
                                                            <option value="font">Schriftart-Auswahl (Konfigurator-Schriften)</option>
                                                            <option value="select">Auswahl-Dropdown (Eigene Werte)</option>
                                                            <option value="date">Datum-Feld</option>
                                                        </select>
                                                    </div>

                                                    {{-- Max. Zeichen (nur für Freitext-Eingaben sinnvoll) --}}
                                                    @if(in_array($cfType, ['text', 'textarea'], true))
                                                        <div class="sm:col-span-2">
                                                            <label class="{{ $labelClassStep1 }}">Max. Zeichen</label>
                                                            <input type="number"
                                                                   min="1"
                                                                   max="500"
                                                                   wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.max_length"
                                                                   class="{{ $inputClassStep1 }} !py-2.5 text-sm font-mono"
                                                                   placeholder="25">
                                                        </div>
                                                    @endif

                                                    {{-- Optionaler Aufpreis (€) (nicht bei Schriftart-Auswahl) --}}
                                                    @if($cfType !== 'font')
                                                        <div class="sm:col-span-2">
                                                            <label class="{{ $labelClassStep1 }}">Aufpreis (€)</label>
                                                            <input type="number"
                                                                   step="0.10"
                                                                   min="0"
                                                                   wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.surcharge_price"
                                                                   class="{{ $inputClassStep1 }} !py-2.5 text-sm font-mono"
                                                                   placeholder="0.00">
                                                        </div>
                                                    @endif
                                                </div>

                                                @if($cfType === 'font')
                                                    {{-- SCHRIFTARTEN-AUSWAHL AUS DEM PRODUKTKONFIGURATOR --}}
                                                    <div class="p-4 rounded-2xl bg-gray-50/90 dark:bg-gray-950/70 border border-gray-200 dark:border-gray-800 space-y-3">
                                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                            <div>
                                                                <span class="text-[11px] font-black uppercase tracking-wider text-gray-800 dark:text-gray-200">
                                                                    Verfügbare Konfigurator-Schriftarten für den Kunden ({{ count($selectedFontOptions) }} von {{ count($configuratorFontsMap) }} ausgewählt)
                                                                </span>
                                                                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                                                    Klicke einzelne Schriften an, um sie für dieses Produkt ein- oder auszublenden, oder wähle rechts ein Schnell-Filterset.
                                                                </p>
                                                            </div>
                                                            <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                                                                <button type="button"
                                                                        wire:click="setCustomFieldFontPreset({{ $cfIdx }}, 'all')"
                                                                        class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] cursor-pointer">
                                                                    Alle {{ count($configuratorFontsMap) }} Schriften
                                                                </button>
                                                                <button type="button"
                                                                        wire:click="setCustomFieldFontPreset({{ $cfIdx }}, 'jewelry_top6')"
                                                                        class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] cursor-pointer">
                                                                    ✨ Top 6 Schmuck &amp; Schreibschrift
                                                                </button>
                                                                <button type="button"
                                                                        wire:click="setCustomFieldFontPreset({{ $cfIdx }}, 'clean_modern')"
                                                                        class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] cursor-pointer">
                                                                    Klassisch &amp; Modern
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2 pt-1">
                                                            @foreach($configuratorFontsMap as $fontName => $fontCss)
                                                                @php
                                                                    $isFontActive = in_array($fontName, $selectedFontOptions, true);
                                                                @endphp
                                                                <button type="button"
                                                                        wire:click="toggleCustomFieldFont({{ $cfIdx }}, '{{ $fontName }}')"
                                                                        class="px-3 py-2 rounded-xl border text-left transition-all flex items-center justify-between gap-2 cursor-pointer {{ $isFontActive ? 'bg-[var(--theme-color-10)] border-[var(--theme-color)] text-gray-900 dark:text-white shadow-2xs' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-500 opacity-60 hover:opacity-100' }}">
                                                                    <div class="min-w-0">
                                                                        <div class="text-sm truncate leading-tight" style="font-family: {!! $fontCss !!};">
                                                                            {{ $fontName }}
                                                                        </div>
                                                                        <div class="text-[9px] font-mono text-gray-400 dark:text-gray-500 truncate mt-0.5">
                                                                            {{ $fontName }}
                                                                        </div>
                                                                    </div>
                                                                    <span class="w-4 h-4 rounded-md text-[10px] font-black flex items-center justify-center shrink-0 {{ $isFontActive ? 'bg-[var(--theme-color)] text-white dark:text-gray-900' : 'border border-gray-300 dark:border-gray-700 text-transparent' }}">
                                                                        ✓
                                                                    </span>
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                @if($cfType !== 'font')
                                                    @php
                                                        $hasFieldSurcharge = ((float) str_replace(',', '.', (string) ($cfItem['surcharge_price'] ?? 0))) > 0;
                                                        $forceOptionalBySurcharge = $hasFieldSurcharge && in_array($cfType, ['text', 'textarea', 'date'], true);
                                                    @endphp
                                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                                                        {{-- Platzhalter oder Auswahl-Optionen --}}
                                                        @if($cfType === 'select')
                                                            <div class="sm:col-span-5">
                                                                <label class="{{ $labelClassStep1 }}">Dropdown-Auswahlwerte (mit Komma trennen) *</label>
                                                                <input type="text"
                                                                       wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.options_raw"
                                                                       class="{{ $inputClassStep1 }} !py-2.5 text-sm"
                                                                       placeholder="z. B. Kein Symbol, ♥ Herz, ∞ Unendlichkeit, ★ Stern">
                                                            </div>
                                                        @elseif(in_array($cfType, ['text', 'textarea'], true))
                                                            <div class="sm:col-span-4">
                                                                <label class="{{ $labelClassStep1 }}">Platzhalter-Beispiel im Feld</label>
                                                                <input type="text"
                                                                       wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.placeholder"
                                                                       class="{{ $inputClassStep1 }} !py-2.5 text-sm"
                                                                       placeholder="z. B. Emilia">
                                                            </div>
                                                        @endif

                                                        {{-- Info-Text für das !-Ausrufungszeichen --}}
                                                        <div class="{{ $cfType === 'select' ? 'sm:col-span-5' : ($cfType === 'date' ? 'sm:col-span-10' : 'sm:col-span-6') }}">
                                                            <label class="{{ $labelClassStep1 }} flex items-center gap-1.5">
                                                                <span class="w-4 h-4 rounded-full bg-[var(--theme-color)] text-white dark:text-gray-900 font-black text-[10px] inline-flex items-center justify-center">!</span>
                                                                <span>Hilfetext beim Klick/Hover auf das „!“-Ausrufungszeichen</span>
                                                            </label>
                                                            <input type="text"
                                                                   wire:model.blur="configSettings.custom_fields.{{ $cfIdx }}.info_text"
                                                                   class="{{ $inputClassStep1 }} !py-2.5 text-sm"
                                                                   placeholder="z. B. Wir gravieren deinen Namen zentriert in unserer Schmuckschrift.">
                                                        </div>

                                                        {{-- Pflichtfeld Switch --}}
                                                        <div class="sm:col-span-2 pt-5">
                                                            @if($forceOptionalBySurcharge)
                                                                <div class="text-[10px] font-bold text-amber-700 dark:text-amber-300 leading-tight" title="Felder mit Zusatz-Aufpreis sind immer optional, damit der Kunde nicht zum Aufpreis gezwungen wird.">
                                                                    Optional (Aufpreis)
                                                                </div>
                                                            @else
                                                                <label class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                                                                    <input type="checkbox"
                                                                           wire:model.live="configSettings.custom_fields.{{ $cfIdx }}.required"
                                                                           class="w-4 h-4 rounded text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200">Pflichtfeld</span>
                                                                </label>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="px-3.5 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-xs text-gray-600 dark:text-gray-400 flex flex-wrap items-center justify-between gap-2">
                                                        <span>Standard-Vorauswahl im Shop (ohne Pflichtfeld-Stern &amp; ohne !-Hinweis):</span>
                                                        <strong class="text-sm text-gray-900 dark:text-white" style="font-family: {!! $configuratorFontsMap[in_array('Arial', $selectedFontOptions, true) ? 'Arial' : ($selectedFontOptions[0] ?? 'Arial')] ?? 'Arial, sans-serif' !!};">
                                                            {{ in_array('Arial', $selectedFontOptions, true) ? 'Arial' : ($selectedFontOptions[0] ?? 'Arial') }}
                                                        </strong>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button type="button"
                                                    wire:click="addCustomField"
                                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-xs font-black uppercase tracking-wider hover:opacity-90 transition-all cursor-pointer">
                                                <span>+ Weiteres Eingabefeld hinzufügen</span>
                                            </button>
                                            <button type="button"
                                                    wire:click="addCustomFontField"
                                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-800 dark:text-gray-200 text-xs font-black uppercase tracking-wider hover:border-[var(--theme-color)] hover:text-[var(--theme-color)] transition-all cursor-pointer">
                                                <span>𝐀 + Schriftart-Auswahl hinzufügen</span>
                                            </button>
                                        </div>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400">
                                            Tipp: Aktiviere oben rechts „Shop Vorschau“, um deine Eingabefelder samt <strong>!</strong>-Info-Icon live zu testen.
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- SUB-STEP 2: BASISINFORMATIONEN --}}
        @if($step1SubStep === 2)
            <div class="{{ $cardClass }}">
                <div class="flex items-center justify-between gap-3 mb-8 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <h2 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">2. Basisinformationen</h2>
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Unter-Schritt 2 / 5</span>
                </div>

                <div class="space-y-6">
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Produktname *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'name'])
                        </div>
                        <input type="text" wire:model.live="name" class="{{ $inputClassStep1 }}" placeholder="z.B. Seelen-Kristall">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Verkaufspreis ({{ filter_var(shop_setting('prices_entered_gross', true), FILTER_VALIDATE_BOOLEAN) ? 'Brutto' : 'Netto' }}) *</label>
                                @include('components.alerts.info-tooltip', ['key' => 'price'])
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500 font-bold">€</span>
                                <input type="number" step="0.01" wire:model.live="price_input" class="{{ $inputClassStep1 }} pl-10 font-mono text-lg font-bold" placeholder="0.00">
                            </div>
                        </div>

                        @if($type === 'physical')
                            <div>
                                <div class="flex items-center gap-2 mb-2 ml-1">
                                    <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">EK Preis (Netto) *</label>
                                    @include('components.alerts.info-tooltip', ['key' => 'purchase_price'])
                                </div>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[var(--theme-color)] font-bold">€</span>
                                    <input type="number" step="0.01" wire:model.live="purchase_price_input" class="{{ $inputClassStep1 }} pl-10 font-mono text-lg font-bold border-gray-200 dark:border-[var(--theme-color-30)] focus:border-[var(--theme-color)]" placeholder="0.00">
                                </div>
                            </div>
                        @endif

                        <div>
                            <label class="{{ $labelClassStep1 }}">Vergleichspreis (Streichpreis)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-600 font-bold">€</span>
                                <input type="number" step="0.01" wire:model.live="compare_price_input" class="{{ $inputClassStep1 }} pl-10 font-mono text-lg text-gray-600 dark:text-gray-400" placeholder="z.B. 59.90">
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Beschreibung</label>
                            @include('components.alerts.info-tooltip', ['key' => 'description'])
                        </div>
                        <textarea wire:model.live="description" rows="6" class="{{ $inputClassStep1 }} resize-none leading-relaxed" placeholder="Erzähle die Geschichte des Produkts..."></textarea>
                    </div>
                </div>
            </div>
        @endif

        {{-- SUB-STEP 3: STEUER & MEHRWERTSTEUER --}}
        @if($step1SubStep === 3)
            <div class="space-y-4">
                <livewire:shop.product.product-tax :product="$product" :key="'product-tax-'.$product->id" />
            </div>
        @endif

        {{-- SUB-STEP 4: SEO & SUCHMASCHINEN --}}
        @if($step1SubStep === 4)
            <div class="{{ $cardClass }}">
                <div class="flex items-center justify-between mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">4. SEO &amp; Suchmaschinen</h3>
                        @include('components.alerts.info-tooltip', ['key' => 'seo'])
                    </div>
                    <span class="text-[9px] text-gray-600 dark:text-gray-400 uppercase tracking-widest font-black bg-gray-100 dark:bg-gray-950 px-3 py-1 rounded-md shadow-sm border border-gray-200 dark:border-gray-800">Optional · Unter-Schritt 4 / 5</span>
                </div>
                <div class="space-y-6">
                    <div>
                        <div class="flex items-center justify-between mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Seitentitel (Meta Title)</label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-600">{{ strlen($seo_title) }}/60 Zeichen</span>
                        </div>
                        <input type="text" wire:model.live="seo_title" class="{{ $inputClassStep1 }}" placeholder="{{ $name }}">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Beschreibung (Meta Description)</label>
                            <span class="text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-600">{{ strlen($seo_description) }}/160 Zeichen</span>
                        </div>
                        <textarea wire:model.live="seo_description" rows="3" class="{{ $inputClassStep1 }} resize-none" placeholder="Eine kurze Zusammenfassung für Google..."></textarea>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">URL-Handle (Slug)</label>
                            @include('components.alerts.info-tooltip', ['key' => 'slug'])
                        </div>
                        <div class="flex items-center rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800">
                            <span class="bg-gray-100 dark:bg-gray-900 px-4 py-3.5 text-gray-600 dark:text-gray-500 text-xs font-black uppercase tracking-widest">/produkt/</span>
                            <input type="text" wire:model.live="slug_input" class="w-full px-4 py-3.5 bg-white dark:bg-gray-950 text-gray-900 dark:text-white focus:bg-white dark:focus:bg-black outline-none font-mono text-sm transition-colors border-l border-gray-200 dark:border-gray-800">
                        </div>
                        @error('slug_input') <p class="text-[10px] text-red-600 dark:text-red-400 mt-2 font-bold uppercase tracking-widest">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        @endif

        {{-- SUB-STEP 5: PRODUKTIDENTIFIKATOREN & LIEFERANT --}}
        @if($step1SubStep === 5)
            <div class="{{ $cardClass }}">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6 border-b border-gray-200 dark:border-gray-800 pb-4">
                    <div class="flex items-center gap-2">
                        <h3 class="text-xl sm:text-2xl font-serif font-bold text-gray-900 dark:text-white tracking-wide">5. Produktidentifikatoren &amp; Lieferant</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                                wire:click="$toggle('showQuickSupplierForm')"
                                class="px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest border border-[var(--theme-color-30)] bg-[var(--theme-color-10)] text-[var(--theme-color)] hover:bg-[var(--theme-color)] hover:text-white dark:hover:text-gray-900 transition-all cursor-pointer">
                            + Neuer Lieferant
                        </button>
                        <button type="button"
                                wire:click="setActiveSection('suppliers')"
                                class="px-3 py-1.5 rounded-xl text-[10px] font-bold border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-all cursor-pointer">
                            Lieferanten-Stamm &rarr;
                        </button>
                    </div>
                </div>

                @if($showQuickSupplierForm)
                    <div class="mb-6 p-4 rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-900 dark:text-white">Neuen Lieferanten direkt anlegen &amp; verknüpfen</span>
                            <button type="button" wire:click="$set('showQuickSupplierForm', false)" class="text-xs text-gray-400 hover:text-gray-700 dark:hover:text-white cursor-pointer">&times; Schließen</button>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-2">
                                <label class="{{ $labelClassStep1 }}">Lieferantenname *</label>
                                <input type="text" wire:model="quickSupplierName" class="{{ $inputClassStep1 }} !py-2.5 text-xs" placeholder="z.B. Glücksfieber (Lübeck)">
                                @error('quickSupplierName') <span class="text-[10px] text-red-500 font-bold">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="{{ $labelClassStep1 }}">Land</label>
                                <select wire:model="quickSupplierCountry" class="{{ $inputClassStep1 }} !py-2.5 text-xs">
                                    <option value="DE">Deutschland (DE)</option>
                                    <option value="DK">Dänemark (DK)</option>
                                    <option value="AT">Österreich (AT)</option>
                                    <option value="NL">Niederlande (NL)</option>
                                    <option value="PL">Polen (PL)</option>
                                    <option value="CN">China (CN)</option>
                                </select>
                            </div>
                            <div>
                                <label class="{{ $labelClassStep1 }}">Lieferzeit (Tage)</label>
                                <input type="number" wire:model="quickSupplierLeadDays" class="{{ $inputClassStep1 }} !py-2.5 text-xs" placeholder="5">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="{{ $labelClassStep1 }}">Website (optional)</label>
                                <input type="url" wire:model="quickSupplierWebsite" class="{{ $inputClassStep1 }} !py-2.5 text-xs" placeholder="https://...">
                            </div>
                            <div class="flex items-end">
                                <button type="button"
                                        wire:click="createQuickSupplier"
                                        class="w-full py-2.5 px-4 rounded-xl bg-[var(--theme-color)] text-white dark:text-gray-900 text-xs font-bold hover:brightness-95 transition cursor-pointer">
                                    Anlegen &amp; wählen
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Artikelnummer (SKU) *</label>
                            @include('components.alerts.info-tooltip', ['key' => 'sku'])
                        </div>
                        <input type="text" wire:model.live="sku" class="{{ $inputClassStep1 }} font-mono" placeholder="z.B. GLAS-001">
                        @if(empty($sku)) <p class="text-[9px] text-red-600 dark:text-red-400 mt-2 uppercase font-black tracking-widest">Dieses Feld ist erforderlich</p> @endif
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Barcode (GTIN/EAN)</label>
                            @include('components.alerts.info-tooltip', ['key' => 'barcode'])
                        </div>
                        <input type="text" wire:model.live="barcode" class="{{ $inputClassStep1 }} font-mono" placeholder="Global Trade Item Number">
                    </div>
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Marke / Hersteller</label>
                                @include('components.alerts.info-tooltip', ['key' => 'brand'])
                            </div>
                            <input type="text" wire:model.live="brand" class="{{ $inputClassStep1 }}" placeholder="z.B. Mein Seelenfunke">
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-2 ml-1">
                                <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Lieferant (Intern)</label>
                                @include('components.alerts.info-tooltip', ['key' => 'supplier'])
                            </div>
                            <select wire:model.live="supplier_id" class="{{ $inputClassStep1 }} appearance-none cursor-pointer">
                                <option value="">-- Kein Lieferant zugewiesen --</option>
                                @foreach($this->suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <div class="flex items-center gap-2 mb-2 ml-1">
                            <label class="{{ $labelClassStep1 }} !mb-0 !ml-0">Nachbestell-Link (Lieferant)</label>
                            @include('components.alerts.info-tooltip', ['key' => 'reorder_url'])
                        </div>
                        <input type="url" wire:model.live.debounce.400ms="reorder_url" class="{{ $inputClassStep1 }}" placeholder="z.B. https://b2b.lieferant.de/artikel/123">
                    </div>
                </div>
            </div>
        @endif

    </div>
@endif
