@php
    $isWide = (bool) ($isWideContainer ?? false);
    $resolvedProductName = (string) ($productName ?? 'Produkt');
@endphp

@if($showTemplateSelection)
    {{-- 1. ZENTRALER STARTBILDSCHIRM: NEULING -> ANFÄNGER -> PROFI --}}
    <div class="flex-1 flex flex-col items-center justify-center {{ $isWide ? 'p-6 sm:p-10' : 'p-4 sm:p-6' }} bg-gray-50/50">
        <div class="{{ $isWide ? 'max-w-3xl mx-auto w-full' : 'w-full' }} flex flex-col items-center">
            <h2 class="{{ $isWide ? 'text-2xl sm:text-3xl mb-6' : 'text-xl sm:text-2xl mb-4' }} font-serif font-bold text-gray-900 text-center">Wie möchtest du starten?</h2>
            <div class="grid grid-cols-1 {{ !empty($productTemplates) ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-3 sm:gap-4 w-full">
                {{-- 1. NEULING: EINFACHE VARIANTE (SYMBOL + TEXT + GALERIE-VORSCHAU) --}}
                <button type="button"
                        wire:click="startBeginnerConfig"
                        class="bg-white border border-gray-200 p-4 rounded-2xl shadow-sm hover:shadow-lg hover:border-emerald-400 transition-all group text-center flex flex-col items-center justify-center cursor-pointer">
                    <div class="w-11 h-11 bg-emerald-50 border border-emerald-200/80 rounded-full flex items-center justify-center text-emerald-600 mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                    </div>
                    <span class="text-base font-serif font-bold text-gray-900 leading-tight">Neuling</span>
                    <h3 class="text-[11px] font-normal text-gray-600 mt-0.5 mb-1.5">Einfache Variante</h3>
                    <p class="text-gray-500 text-[11px] leading-snug">Gib einfach deinen Wunschtext und ein Symbol an.</p>
                </button>

                {{-- 2. ANFÄNGER: VORLAGE NUTZEN (SILBERRAHMEN) --}}
                @if(!empty($productTemplates))
                    <button type="button"
                            wire:click="openTemplatesList"
                            class="bg-gradient-to-b from-white to-slate-50/60 border-2 border-slate-300 ring-1 ring-slate-200/80 p-4 rounded-2xl shadow-sm hover:shadow-lg hover:border-slate-400 transition-all group text-center flex flex-col items-center justify-center cursor-pointer">
                        <div class="w-11 h-11 bg-slate-100 border border-slate-300/80 rounded-full flex items-center justify-center text-slate-600 mb-2 group-hover:scale-110 transition-transform">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                        </div>
                        <span class="text-base font-serif font-bold text-slate-800 leading-tight">Anfänger</span>
                        <h3 class="text-[11px] font-normal text-slate-600 mt-0.5 mb-1.5">Vorlage nutzen</h3>
                        <p class="text-gray-500 text-[11px] leading-snug">Wähle eine Vorlage aus, um einen ersten Eindruck zu erhalten.</p>
                    </button>
                @endif

                {{-- 3. PROFI: SELBER KONFIGURIEREN (GOLDRAHMEN) --}}
                <button type="button"
                        wire:click="startCustomConfig"
                        class="bg-gradient-to-b from-white to-[#fdfbf7] border-2 border-[#C5A059] ring-1 ring-[#C5A059]/30 p-4 rounded-2xl shadow-sm hover:shadow-lg hover:border-[#a8823b] transition-all group text-center flex flex-col items-center justify-center cursor-pointer">
                    <div class="w-11 h-11 bg-[#C5A059]/15 border border-[#C5A059]/40 rounded-full flex items-center justify-center text-[#9a732a] mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    </div>
                    <span class="text-base font-serif font-bold text-[#8a6520] leading-tight">Profi</span>
                    <h3 class="text-[11px] font-normal text-gray-600 mt-0.5 mb-1.5">Selber Konfigurieren</h3>
                    <p class="text-gray-500 text-[11px] leading-snug">Nur für Profis, die alle Details aus ihrem Produkt rausholen wollen. Leerstart.</p>
                </button>
            </div>

            @php
                $tutorialConfig = app(\App\Services\ProductConfiguratorService::class)->getTutorialConfig();
            @endphp
            @if(!empty($tutorialConfig['enabled']) && !empty($tutorialConfig['exists']) && !empty($tutorialConfig['url']))
                <div x-data="{ showTutorial: false }" class="w-full mt-4 pt-4 border-t border-gray-200/80 flex flex-col items-center">
                    <button type="button"
                            @click="showTutorial = !showTutorial"
                            class="inline-flex items-center gap-2.5 px-5 py-2.5 rounded-2xl border transition-all text-xs font-bold cursor-pointer select-none"
                            :class="showTutorial
                                ? 'bg-white text-gray-900 border-[var(--theme-color)] ring-2 ring-[var(--theme-color-20)] shadow-sm hover:bg-gray-50'
                                : 'bg-white text-gray-700 border-gray-200 shadow-xs hover:border-[var(--theme-color)] hover:text-gray-900 hover:shadow-md'">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center transition-colors"
                              :class="showTutorial ? 'bg-[var(--theme-color-20)] text-gray-900' : 'bg-[var(--theme-color-10)] text-[var(--theme-color)]'">
                            <svg x-show="!showTutorial" class="w-3.5 h-3.5 translate-x-[0.5px]" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            <svg x-show="showTutorial" x-cloak class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </span>
                        <span x-text="showTutorial ? 'Tutorial schließen' : 'Tutorial ansehen'">Tutorial ansehen</span>
                        <span x-show="!showTutorial" class="text-[10px] font-medium text-gray-400 hidden sm:inline">• Kurz erklärt</span>
                    </button>

                    {{-- Lazy-Loaded Video Player: Wird erst beim Klick auf "Tutorial ansehen" geladen --}}
                    <template x-if="showTutorial">
                        <div class="w-full mt-4 rounded-2xl overflow-hidden border border-gray-200 bg-white shadow-lg transition-all">
                            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200/80 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse"></span>
                                    <span class="text-[11px] font-bold text-gray-800 tracking-wide">So nutzt du den Produktkonfigurator</span>
                                </div>
                                <span class="text-[10px] text-gray-500 font-medium">Schritt-für-Schritt Anleitung</span>
                            </div>
                            <video src="{{ $tutorialConfig['url'] }}"
                                   controls
                                   autoplay
                                   playsinline
                                   preload="metadata"
                                   class="w-full aspect-video object-contain bg-[#FAF8F5] block">
                                Dein Browser unterstützt leider keine HTML5-Videos.
                            </video>
                        </div>
                    </template>
                </div>
            @endif
        </div>
    </div>

@elseif($showTemplatesList)
    {{-- 2. ZENTRALE LISTE DER VORLAGEN --}}
    <div class="flex-1 px-3 py-4 sm:p-6 bg-gray-50/50 {{ $isWide ? 'overflow-y-auto' : '' }}">
        <div class="{{ $isWide ? 'max-w-5xl mx-auto w-full' : 'w-full' }}">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h2 class="text-xl font-serif font-bold text-gray-900">Design Vorlagen</h2>
                <button type="button"
                        wire:click="backToStartSelection"
                        class="text-xs font-bold text-gray-500 hover:text-gray-900 flex items-center gap-1 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Zurück
                </button>
            </div>

            <div class="w-full bg-blue-50/80 border border-blue-200/70 rounded-xl px-3 py-2.5 sm:p-3.5 mb-5 relative overflow-hidden backdrop-blur-sm shadow-sm">
                <div class="relative flex items-start gap-2.5">
                    <div class="bg-blue-100/70 text-blue-600 p-1.5 rounded-lg shrink-0 mt-0.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-blue-900 text-xs sm:text-sm mb-0.5">Ausschluss vom Widerrufsrecht</h4>
                        <p class="text-[11px] text-blue-800/90 leading-snug">
                            Jede Vorlage wird individuell für dich gefertigt und ist daher (<span class="font-bold">auch ohne eigene Änderungen</span>) vom <a href="{{ route('agb') }}#widerruf" target="_blank" class="underline font-bold hover:text-blue-900 transition-colors">Widerrufsrecht ausgeschlossen</a>.
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 {{ $isWide ? 'sm:grid-cols-2 lg:grid-cols-3 gap-6' : 'sm:grid-cols-2 gap-4' }}">
                @foreach($productTemplates as $tpl)
                    <div wire:click="selectTemplate('{{ $tpl['id'] }}')"
                         class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-lg hover:border-[var(--theme-color)] transition-all cursor-pointer group">
                        <div class="aspect-square w-full bg-gray-100 relative overflow-hidden flex items-center justify-center">
                            @if(!empty($tpl['preview_image']))
                                <img src="{{ \App\Models\Product\ProductTemplate::resolveImageUrl($tpl['preview_image']) }}"
                                     alt="{{ $tpl['name'] ?? $resolvedProductName }} · Design-Vorlage"
                                     loading="lazy"
                                     decoding="async"
                                     @if($tpl['is_ai_generated'] ?? true) data-is-ai-generated="true" @endif
                                     class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500">
                                @if($tpl['is_ai_generated'] ?? true)
                                    <span style="font-size: 6.5px; line-height: 1; padding: 1.5px 4px; background: rgba(0,0,0,0.36); color: rgba(255,255,255,0.68);"
                                          class="pointer-events-none select-none absolute bottom-1.5 right-1.5 z-10 backdrop-blur-sm font-normal tracking-normal rounded border border-white/10">
                                        KI-generiert
                                    </span>
                                @endif
                            @else
                                <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            @endif
                            <div class="absolute inset-0 bg-[var(--theme-color)]/0 group-hover:bg-[var(--theme-color-10)] transition-colors"></div>
                        </div>
                        <div class="p-4 text-center">
                            <h3 class="font-bold text-gray-900 text-sm group-hover:text-[var(--theme-color)] transition-colors">{{ $tpl['name'] }}</h3>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($hiddenSeasonalTemplatesCount > 0 || $showAllSeasonalTemplates)
                <div class="mt-4 text-center">
                    <button type="button"
                            wire:click="toggleShowAllTemplates"
                            class="text-[11px] text-gray-400 hover:text-[var(--theme-color)] transition-colors underline underline-offset-4 cursor-pointer">
                        @if($showAllSeasonalTemplates)
                            Nur aktuelle Saison anzeigen
                        @else
                            Alle Vorlagen anzeigen ({{ count($productTemplates) + $hiddenSeasonalTemplatesCount }})
                        @endif
                    </button>
                </div>
            @endif
        </div>
    </div>
@endif
