@php
    $customFieldsDef = $this->customFieldsDefinition ?? [];
    $savedSnapshotRows = !empty($this->savedCustomFieldsSnapshot) && is_array($this->savedCustomFieldsSnapshot)
        ? $this->savedCustomFieldsSnapshot
        : [];
    $fontsMap = (!empty($fonts) && is_array($fonts))
        ? $fonts
        : app(\App\Services\ConfiguratorService::class)->getFonts();

    // Ermittle den ersten eingegebenen Textwert für die Live-Schriftart-Vorschau
    $livePreviewSampleText = '';
    foreach ($customFieldsDef as $cfCheck) {
        if (in_array($cfCheck['type'] ?? 'text', ['text', 'textarea'], true)) {
            $candidate = trim((string) ($customFieldValues[$cfCheck['id']] ?? ''));
            if ($candidate !== '') {
                $livePreviewSampleText = $candidate;
                break;
            }
        }
    }
    if ($livePreviewSampleText === '') {
        $livePreviewSampleText = 'Mein Wunschname';
    }
@endphp

<div class="px-4 sm:px-6 pt-5 pb-6 max-w-2xl mx-auto w-full space-y-5">
    @if($context === 'preview')
        {{-- READ-ONLY VORSCHAU FÜR BESTELLHISTORIE / ADMIN (INKL. 1-KLICK COPY) --}}
        @php
            $previewRowsToRender = [];
            if (!empty($savedSnapshotRows)) {
                foreach ($savedSnapshotRows as $snapRow) {
                    $previewRowsToRender[] = [
                        'label' => (string) ($snapRow['label'] ?? 'Gravurfeld'),
                        'type' => (string) ($snapRow['type'] ?? 'text'),
                        'value' => trim((string) ($snapRow['value'] ?? '')),
                        'surcharge_cents' => (int) ($snapRow['surcharge_cents'] ?? 0),
                    ];
                }
            } elseif (!empty($customFieldsDef)) {
                foreach ($customFieldsDef as $cfDef) {
                    $cfId = $cfDef['id'];
                    $previewRowsToRender[] = [
                        'label' => (string) ($cfDef['label'] ?? 'Gravurfeld'),
                        'type' => (string) ($cfDef['type'] ?? 'text'),
                        'value' => trim((string) ($customFieldValues[$cfId] ?? '')),
                        'surcharge_cents' => (int) ($cfDef['surcharge_cents'] ?? 0),
                    ];
                }
            }
            $allPreviewCopyText = collect($previewRowsToRender)
                ->filter(fn($r) => $r['value'] !== '' && ($r['type'] ?? 'text') !== 'font' && !isset($fontsMap[$r['value']]) && !in_array(mb_strtolower($r['value']), ['kein symbol', 'keine', 'nein', '-'], true))
                ->map(fn($r) => $r['value'])
                ->implode("\n");
        @endphp
        <div class="p-5 sm:p-6 rounded-3xl border {{ $isDark ? 'bg-gray-900/90 border-gray-800 text-gray-200' : 'bg-gray-50/90 border-gray-200 text-gray-900' }} space-y-4 shadow-xs">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b {{ $isDark ? 'border-gray-800' : 'border-gray-200' }}">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black uppercase tracking-widest text-primary">⚡ Gewählte Personalisierung</span>
                    <span class="text-[10px] font-semibold {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">(Laser-Steckbrief)</span>
                </div>
                @if($allPreviewCopyText !== '')
                    <button type="button"
                            x-data="{ copiedAll: false }"
                            @click="navigator.clipboard.writeText(@js($allPreviewCopyText)); copiedAll = true; setTimeout(() => copiedAll = false, 2000)"
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all border cursor-pointer"
                            :class="copiedAll ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white dark:bg-gray-950 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-500/40 hover:bg-emerald-600 hover:text-white'">
                        <span x-show="!copiedAll">📋 Alle Werte kopieren</span>
                        <span x-show="copiedAll" x-cloak>✓ In Zwischenablage</span>
                    </button>
                @endif
            </div>

            @if(empty($previewRowsToRender))
                <p class="text-xs italic {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">Keine Personalisierungsfelder hinterlegt.</p>
            @else
                <div class="grid grid-cols-1 gap-3">
                    @foreach($previewRowsToRender as $pRow)
                        @php
                            $val = $pRow['value'];
                            $pType = $pRow['type'] ?? 'text';
                            $surcharge = $pRow['surcharge_cents'];
                            $isNeutral = in_array(mb_strtolower($val), ['', 'kein symbol', 'keine', 'nein', '-'], true);
                            $isFontRow = ($pType === 'font' || isset($fontsMap[$val])) && $val !== '';
                        @endphp
                        <div class="p-3.5 rounded-2xl border {{ $isDark ? 'bg-gray-950 border-gray-800' : 'bg-white border-gray-200/90' }} flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                                        {{ $pRow['label'] }}
                                    </span>
                                    @if($isFontRow)
                                        <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-indigo-500/15 text-indigo-600 dark:text-indigo-300">
                                            𝐀 Schriftart
                                        </span>
                                    @endif
                                    @if(!$isNeutral && $surcharge > 0)
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-primary/10 text-primary">
                                            + {{ number_format($surcharge / 100, 2, ',', '.') }}&nbsp;€
                                        </span>
                                    @endif
                                </div>
                                @if($isFontRow)
                                    <div class="flex flex-wrap items-baseline gap-3 mt-1">
                                        <span class="text-lg sm:text-xl {{ $isDark ? 'text-white' : 'text-gray-900' }} select-all"
                                              style="font-family: {!! $fontsMap[$val] ?? 'sans-serif' !!};">
                                            {{ $val }}
                                        </span>
                                        <span class="text-xs font-mono {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                                            ({{ $val }})
                                        </span>
                                    </div>
                                @else
                                    <span class="text-sm sm:text-base font-bold font-mono {{ $val !== '' ? ($isDark ? 'text-white' : 'text-gray-900') : 'text-gray-400 italic' }} break-words whitespace-pre-line block mt-0.5 select-all">
                                        {{ $val !== '' ? $val : 'Nicht angegeben' }}
                                    </span>
                                @endif
                            </div>
                            @if(!$isNeutral && !$isFontRow)
                                <button type="button"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText(@js($val)); copied = true; setTimeout(() => copied = false, 1800)"
                                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all border shadow-2xs cursor-pointer"
                                        :class="copied ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-gray-50 dark:bg-gray-900 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:border-emerald-500 hover:text-emerald-600 dark:hover:text-emerald-400'">
                                    <span x-show="!copied">Kopieren</span>
                                    <span x-show="copied" x-cloak>✓ Kopiert</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @else
        {{-- INTERAKTIVES KUNDEN-FORMULAR (SCHNELL-PERSONALISIERUNG) --}}
        <div class="p-5 sm:p-6 rounded-3xl border {{ $isDark ? 'bg-gray-900/90 border-gray-800' : 'bg-gray-50/80 border-gray-200/90' }} shadow-xs space-y-5">
            <div class="flex items-start justify-between gap-3 pb-3.5 border-b {{ $isDark ? 'border-gray-800' : 'border-gray-200' }}">
                <div>
                    <h3 class="text-base sm:text-lg font-serif font-bold {{ $isDark ? 'text-white' : 'text-gray-900' }}">
                        Deine persönliche Wunschgravur
                    </h3>
                    <p class="text-xs {{ $isDark ? 'text-gray-400' : 'text-gray-600' }} mt-0.5">
                        Gib hier deine Wunschdaten ein. Wir richten deine Gravur vor dem Lasern von Hand millimetergenau und harmonisch aus.
                    </p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-primary/15 text-primary border border-primary/30">
                    ✦ Unikat
                </span>
            </div>

            @if(empty($customFieldsDef))
                <div class="p-4 rounded-2xl border {{ $isDark ? 'bg-gray-950 border-gray-800 text-gray-400' : 'bg-white border-gray-200 text-gray-600' }} text-xs text-center">
                    Gib deine Gravurwünsche einfach unten im Bemerkungsfeld an.
                </div>
            @else
                <div class="space-y-4">
                    @foreach($customFieldsDef as $cfIdx => $cfDef)
                        @php
                            $cfId        = $cfDef['id'];
                            $cfLabel     = $cfDef['label'];
                            $cfType      = $cfDef['type'] ?? 'text';
                            $cfHolder    = $cfDef['placeholder'] ?? '';
                            $cfInfo      = $cfDef['info_text'] ?? '';
                            $cfRequired  = !empty($cfDef['required']);
                            $cfMaxLen    = max(1, (int) ($cfDef['max_length'] ?? 30));
                            $cfOptions   = is_array($cfDef['options'] ?? null) ? $cfDef['options'] : [];
                            if ($cfType === 'font' && empty($cfOptions)) {
                                $cfOptions = array_keys($fontsMap);
                            }
                            $cfSurcharge = max(0, (int) ($cfDef['surcharge_cents'] ?? 0));
                            $currentVal  = (string) ($customFieldValues[$cfId] ?? '');
                            $isLockedSurchargeField = false;
                            if (($context ?? 'add') === 'order_edit' && $cfSurcharge > 0 && !auth()->guard('admin')->check()) {
                                $savedById = collect($savedCustomFieldsSnapshot ?? [])->keyBy('id');
                                $savedEntry = $savedById->get($cfId);
                                $savedVal = is_array($savedEntry) ? trim((string) ($savedEntry['value'] ?? '')) : '';
                                $savedWasNeutral = in_array(mb_strtolower($savedVal), ['', 'kein symbol', 'keine', 'nein', '-'], true);
                                $wasPaid = is_array($savedEntry) && (((int) ($savedEntry['surcharge_cents'] ?? 0)) > 0 || !$savedWasNeutral);
                                $isLockedSurchargeField = !$wasPaid;
                            }
                        @endphp

                        <div wire:key="frontend-cf-{{ $cfId }}"
                             x-data="{ showInfo: false, charCount: {{ mb_strlen($currentVal) }} }"
                             class="p-4 rounded-2xl border transition-all {{ $isDark ? 'bg-gray-950/90 border-gray-800 focus-within:border-primary' : 'bg-white border-gray-200/90 focus-within:border-primary shadow-2xs' }} space-y-2">

                            {{-- Label + Pflichtfeld + !-Ausrufungszeichen + Aufpreis --}}
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                    <label for="cf-input-{{ $cfId }}" class="text-xs sm:text-sm font-bold {{ $isDark ? 'text-white' : 'text-gray-900' }} cursor-pointer">
                                        {{ $cfLabel }}
                                        @if($cfType !== 'font')
                                            @if($cfRequired)
                                                <span class="text-rose-500 font-black" title="Pflichtfeld">*</span>
                                            @else
                                                <span class="text-[11px] font-normal {{ $isDark ? 'text-gray-500' : 'text-gray-400' }}">(optional)</span>
                                            @endif
                                        @endif
                                    </label>

                                    @if($cfType !== 'font' && $cfInfo !== '')
                                        <button type="button"
                                                @click="showInfo = !showInfo"
                                                @mouseenter="showInfo = true"
                                                class="w-5 h-5 rounded-full bg-amber-500/15 hover:bg-amber-500/25 text-amber-600 dark:text-amber-400 border border-amber-500/40 text-[11px] font-black inline-flex items-center justify-center shrink-0 transition-transform hover:scale-105 cursor-pointer"
                                                aria-label="Hinweis zu {{ $cfLabel }}"
                                                title="Klicken für Hinweis">
                                            !
                                        </button>
                                    @endif
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if($cfSurcharge > 0)
                                        <span class="px-2 py-0.5 rounded-lg text-[11px] font-bold bg-primary/15 text-primary border border-primary/25">
                                            + {{ number_format($cfSurcharge / 100, 2, ',', '.') }}&nbsp;€
                                        </span>
                                    @endif

                                    @if(in_array($cfType, ['text', 'textarea'], true))
                                        <span class="text-[10px] font-mono font-semibold {{ $isDark ? 'text-gray-500' : 'text-gray-400' }}">
                                            <span x-text="charCount">{{ mb_strlen($currentVal) }}</span>/{{ $cfMaxLen }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- Ausklappbarer Info-Text zum !-Ausrufungszeichen (nur bei echten Eingabefeldern, nicht bei Schriftart) --}}
                            @if($cfType !== 'font' && $cfInfo !== '')
                                <div x-show="showInfo"
                                     x-cloak
                                     x-transition.opacity.duration.150ms
                                     class="p-3 rounded-xl border text-xs leading-relaxed flex items-start justify-between gap-2.5 {{ $isDark ? 'bg-amber-950/35 border-amber-800/60 text-amber-200' : 'bg-amber-50/90 border-amber-200 text-amber-950' }}">
                                    <div class="flex items-start gap-2">
                                        <span class="w-4 h-4 rounded-full bg-amber-500 text-white font-black text-[10px] inline-flex items-center justify-center shrink-0 mt-0.5">!</span>
                                        <span>{{ $cfInfo }}</span>
                                    </div>
                                    <button type="button" @click="showInfo = false" class="text-[10px] font-bold opacity-60 hover:opacity-100 shrink-0 cursor-pointer">✕</button>
                                </div>
                            @endif

                            {{-- Eingabefeld je nach Typ --}}
                            @if($cfType === 'font')
                                @php
                                    $defaultFontOption = in_array('Arial', $cfOptions, true) ? 'Arial' : ($cfOptions[0] ?? 'Arial');
                                    $activeFontName = ($currentVal !== '' && isset($fontsMap[$currentVal]))
                                        ? $currentVal
                                        : $defaultFontOption;
                                    $activeFontCss = $fontsMap[$activeFontName] ?? 'Arial, sans-serif';
                                @endphp
                                <div x-data="{ openFontDropdown: false }" class="space-y-2.5 relative">
                                    {{-- Visueller Schriftarten-Wähler (echte Konfigurator-Schriften, Standard: Arial) --}}
                                    <button type="button"
                                            id="cf-input-{{ $cfId }}"
                                            @click="openFontDropdown = !openFontDropdown"
                                            class="w-full rounded-xl border px-3.5 py-3 text-left transition-all flex items-center justify-between gap-3 cursor-pointer {{ $isDark ? 'bg-gray-900 border-gray-800 text-white hover:border-primary/60' : 'bg-gray-50/70 border-gray-200 text-gray-900 hover:border-primary/60' }}">
                                        <div class="min-w-0 flex items-baseline gap-3 flex-wrap">
                                            <span class="text-lg sm:text-xl leading-tight truncate" style="font-family: {!! $activeFontCss !!};">
                                                {{ $activeFontName }}
                                            </span>
                                            <span class="text-[11px] font-mono {{ $isDark ? 'text-gray-400' : 'text-gray-500' }}">
                                                ({{ $activeFontName }})
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0 text-primary">
                                            <span class="text-[10px] font-black uppercase tracking-wider hidden sm:inline">Schrift wechseln</span>
                                            <svg class="h-4 w-4 transition-transform duration-200" :class="openFontDropdown ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                    </button>

                                    {{-- Ausklappbares Schriftarten-Menü mit Live-Schriftbild --}}
                                    <div x-show="openFontDropdown"
                                         x-cloak
                                         @click.outside="openFontDropdown = false"
                                         x-transition.opacity.duration.150ms
                                         class="z-30 w-full max-h-72 overflow-y-auto rounded-2xl border shadow-xl p-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5 {{ $isDark ? 'bg-gray-900 border-gray-700' : 'bg-white border-gray-200' }}">
                                        @foreach($cfOptions as $optFont)
                                            @php
                                                $optCss = $fontsMap[$optFont] ?? 'Arial, sans-serif';
                                                $isSelectedFont = ($activeFontName === $optFont);
                                            @endphp
                                            <button type="button"
                                                    @click="$wire.set('customFieldValues.{{ $cfId }}', @js($optFont)); openFontDropdown = false"
                                                    class="px-3 py-2.5 rounded-xl border text-left transition-all flex items-center justify-between gap-2 cursor-pointer {{ $isSelectedFont ? 'bg-primary/15 border-primary text-primary font-bold' : ($isDark ? 'bg-gray-950/60 border-gray-800 text-gray-200 hover:border-gray-700' : 'bg-gray-50/70 border-gray-100 text-gray-900 hover:border-gray-300') }}">
                                                <div class="min-w-0">
                                                    <div class="text-base sm:text-lg truncate leading-snug" style="font-family: {!! $optCss !!};">
                                                        {{ $livePreviewSampleText }}
                                                    </div>
                                                    <div class="text-[10px] font-mono {{ $isSelectedFont ? 'text-primary' : ($isDark ? 'text-gray-400' : 'text-gray-500') }}">
                                                        {{ $optFont }}
                                                    </div>
                                                </div>
                                                @if($isSelectedFont)
                                                    <span class="w-5 h-5 rounded-full bg-primary text-gray-950 text-[10px] font-black flex items-center justify-center shrink-0">✓</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- Live-Schriftbild-Vorschau des eingegebenen Wunschnamens --}}
                                    <div class="px-4 py-3 rounded-xl border text-center {{ $isDark ? 'bg-gray-900/60 border-gray-800/80' : 'bg-amber-50/40 border-amber-200/60' }}">
                                        <div class="text-[9px] font-black uppercase tracking-widest {{ $isDark ? 'text-gray-500' : 'text-gray-400' }} mb-1">
                                            Schriftbild-Vorschau ({{ $activeFontName }})
                                        </div>
                                        <div class="text-xl sm:text-2xl {{ $isDark ? 'text-amber-200' : 'text-gray-900' }} break-words leading-relaxed py-0.5"
                                             style="font-family: {!! $activeFontCss !!};">
                                            {{ $livePreviewSampleText }}
                                        </div>
                                    </div>
                                </div>
                            @elseif($cfType === 'select')
                                <div class="relative">
                                    <select id="cf-input-{{ $cfId }}"
                                            data-custom-field-id="{{ $cfId }}"
                                            @disabled($isLockedSurchargeField)
                                            wire:model.live="customFieldValues.{{ $cfId }}"
                                            class="w-full rounded-xl border px-3.5 py-3 text-sm font-medium transition-all outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary appearance-none {{ $isLockedSurchargeField ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer' }} {{ $isDark ? 'bg-gray-900 border-gray-800 text-white' : 'bg-gray-50/70 border-gray-200 text-gray-900 focus:bg-white' }}">
                                        @if(!$cfRequired)
                                            <option value="">{{ $cfHolder ?: 'Bitte wählen (Optional)...' }}</option>
                                        @endif
                                        @foreach($cfOptions as $optItem)
                                            <option value="{{ $optItem }}">{{ $optItem }}</option>
                                        @endforeach
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-primary">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                                    </div>
                                </div>
                            @elseif($cfType === 'textarea')
                                <textarea id="cf-input-{{ $cfId }}"
                                          data-custom-field-id="{{ $cfId }}"
                                          rows="3"
                                          maxlength="{{ $cfMaxLen }}"
                                          @disabled($isLockedSurchargeField)
                                          @input="charCount = $event.target.value.length"
                                          wire:model.live.debounce.300ms="customFieldValues.{{ $cfId }}"
                                          placeholder="{{ $isLockedSurchargeField ? 'Nicht Teil dieser Bestellung' : ($cfHolder ?: 'Deine Wunschgravur eingeben...') }}"
                                          class="w-full rounded-xl border px-3.5 py-2.5 text-sm font-medium transition-all outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none {{ $isLockedSurchargeField ? 'opacity-50 cursor-not-allowed' : '' }} {{ $isDark ? 'bg-gray-900 border-gray-800 text-white placeholder-gray-600' : 'bg-gray-50/70 border-gray-200 text-gray-900 placeholder-gray-400 focus:bg-white' }}"></textarea>
                            @elseif($cfType === 'date')
                                <input id="cf-input-{{ $cfId }}"
                                       data-custom-field-id="{{ $cfId }}"
                                       type="date"
                                       @disabled($isLockedSurchargeField)
                                       wire:model.live="customFieldValues.{{ $cfId }}"
                                       class="w-full rounded-xl border px-3.5 py-2.5 text-sm font-medium transition-all outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary {{ $isLockedSurchargeField ? 'opacity-50 cursor-not-allowed' : '' }} {{ $isDark ? 'bg-gray-900 border-gray-800 text-white' : 'bg-gray-50/70 border-gray-200 text-gray-900 focus:bg-white' }}">
                            @else
                                <input id="cf-input-{{ $cfId }}"
                                       data-custom-field-id="{{ $cfId }}"
                                       type="text"
                                       maxlength="{{ $cfMaxLen }}"
                                       @disabled($isLockedSurchargeField)
                                       @input="charCount = $event.target.value.length"
                                       wire:model.live.debounce.300ms="customFieldValues.{{ $cfId }}"
                                       placeholder="{{ $isLockedSurchargeField ? 'Nicht Teil dieser Bestellung' : ($cfHolder ?: 'Deine Eingabe...') }}"
                                       class="w-full rounded-xl border px-3.5 py-2.5 text-sm font-medium transition-all outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary {{ $isLockedSurchargeField ? 'opacity-50 cursor-not-allowed' : '' }} {{ $isDark ? 'bg-gray-950 border-gray-800 text-white placeholder-gray-600' : 'bg-gray-50/70 border-gray-200 text-gray-900 placeholder-gray-400 focus:bg-white' }}">
                            @endif

                            @error('customFieldValues.' . $cfId)
                                <p class="text-xs font-bold text-red-600 dark:text-red-400 flex items-center gap-1.5 pt-0.5">
                                    <span>⚠</span>
                                    <span>{{ $message }}</span>
                                </p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Optionale Anmerkung an die Manufaktur --}}
            <div x-data="{ showNotesField: {{ !empty($notes) ? 'true' : 'false' }} }" class="pt-2 border-t {{ $isDark ? 'border-gray-800' : 'border-gray-200/80' }}">
                <button type="button"
                        @click="showNotesField = !showNotesField"
                        class="text-xs font-semibold {{ $isDark ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-gray-900' }} flex items-center gap-1.5 cursor-pointer">
                    <span x-text="showNotesField ? '−' : '+'" class="font-black text-primary"></span>
                    <span>Optional: Sonderwunsch oder Hinweis an unsere Manufaktur hinzufügen</span>
                </button>
                <div x-show="showNotesField" x-cloak class="mt-2.5">
                    <textarea wire:model.blur="notes"
                              rows="2"
                              maxlength="500"
                              placeholder="Hast du noch einen besonderen Wunsch zur Platzierung? Schreib ihn uns gerne hier..."
                              class="w-full rounded-xl border px-3.5 py-2.5 text-xs transition-all outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none {{ $isDark ? 'bg-gray-950 border-gray-800 text-white placeholder-gray-600' : 'bg-white border-gray-200 text-gray-900 placeholder-gray-400' }}"></textarea>
                </div>
            </div>
        </div>
    @endif
</div>
