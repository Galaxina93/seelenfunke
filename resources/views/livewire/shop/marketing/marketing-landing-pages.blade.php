<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-[0_0_10px_var(--theme-color-30)]">
                    Landing Pages
                </h1>
                <p class="mt-2 text-sm text-gray-400 font-mono tracking-wide uppercase">
                    Erstelle hochkonvertierende Landing Pages für Social-Media-Kampagnen per Knopfdruck.
                </p>
            </div>
        </div>

        <div class="mb-4 w-full">
            <div class="relative bg-gradient-to-r from-gray-900 via-gray-950 to-gray-900 border border-[var(--theme-color-30)] rounded-2xl p-4 md:p-6 shadow-[0_0_40px_var(--theme-color-15)] overflow-hidden">
                <div class="absolute inset-0 bg-[var(--theme-color)] opacity-5"></div>
                <div class="relative z-10 flex flex-col sm:flex-row gap-4 sm:gap-6 items-start sm:items-center">
                    <div class="shrink-0 relative">
                        <div class="absolute -inset-2 bg-[var(--theme-color)] opacity-20 blur-xl rounded-full animate-pulse-slow"></div>
                        <div class="w-14 h-14 md:w-16 md:h-16 bg-gray-950 border-2 border-[var(--theme-color)] rounded-full flex items-center justify-center shadow-inner relative z-10 overflow-hidden">
                            <svg class="w-6 h-6 md:w-8 md:h-8 text-[var(--theme-color)]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1.5">
                            <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                LOKALE BLADE DATEIEN
                            </span>
                        </div>
                        <p class="text-sm md:text-base text-gray-300 font-serif leading-relaxed">
                            "Klicke auf Generieren, um eine individuelle Blade-Template Datei für das jeweilige Produkt anzulegen. Die neu erstellte Datei (<code>/landingpages/...</code>) kann im Anschluss nach eigenen Wünschen frei programmiert und gestaltet werden."
                        </p>
                    </div>
                </div>
            </div>
        </div>

        @if($actionError)
            <div class="mb-8 p-4 bg-red-500/10 border border-red-500/30 rounded-xl flex items-start gap-4 shadow-[0_0_15px_rgba(239,68,68,0.2)]">
                <div class="p-2 bg-red-500/20 rounded-lg shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-red-400 font-bold mb-1">Achtung: Generierung fehlgeschlagen</h3>
                    <p class="text-red-300 text-sm leading-relaxed">{{ $actionError }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
                <div wire:key="product-{{ $product->id }}" class="group bg-gray-950 border border-gray-800 hover:border-[var(--theme-color-50)] overflow-hidden rounded-2xl flex flex-col relative transition-all duration-300 shadow-inner hover:shadow-[0_0_25px_var(--theme-color-15)]">
                    
                    @if($product->landingPage)
                        <div class="absolute top-3 right-3 z-10">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase tracking-widest font-black bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] shadow-sm backdrop-blur-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] mr-1.5 animate-pulse"></span>
                                Aktiv
                            </span>
                        </div>
                    @endif
                    
                    <div class="p-6 flex-grow flex flex-col relative z-20">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                @if(count($product->media_gallery) > 0)
                                    <img class="h-16 w-16 rounded-xl object-cover shadow-md border border-gray-800" src="{{ asset('storage/' . $product->media_gallery[0]['path']) }}" alt="{{ $product->name }}">
                                @else
                                    <div class="h-16 w-16 rounded-xl bg-gray-900 border border-gray-800 flex items-center justify-center shadow-inner">
                                        <svg class="h-8 w-8 text-[var(--theme-color-30)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0 pt-1">
                                <h3 class="text-base font-bold text-gray-200 truncate group-hover:text-white transition-colors" title="{{ $product->name }}">
                                    {{ $product->name }}
                                </h3>
                                <p class="text-sm font-mono text-[var(--theme-color)] mt-1 drop-shadow-[0_0_5px_var(--theme-color-30)]">
                                    {{ $product->formatted_price }}
                                </p>
                                @php
                                    $mediaColl = is_array($product->media_gallery) ? collect($product->media_gallery) : collect();
                                    $vidCount = $mediaColl->where('type', 'video')->count();
                                    $imgCount = $mediaColl->count() - $vidCount;
                                @endphp
                                @if($mediaColl->count() > 0)
                                <div class="flex items-center gap-2 mt-2">
                                    @if($imgCount > 0)
                                        <span class="flex items-center gap-1 text-[10px] text-gray-400 bg-gray-900 border border-gray-800 px-1.5 py-0.5 rounded-full" title="{{ $imgCount }} Bild(er) hinterlegt">
                                            <svg class="w-3 h-3 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            <span class="font-bold">{{ $imgCount }}</span>
                                        </span>
                                    @endif
                                    @if($vidCount > 0)
                                        <span class="flex items-center gap-1 text-[10px] text-gray-400 bg-gray-900 border border-gray-800 px-1.5 py-0.5 rounded-full shadow-[0_0_8px_rgba(234,179,8,0.2)] border-amber-500/20" title="{{ $vidCount }} Video(s) hinterlegt">
                                            <svg class="w-3 h-3 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm12.553 1.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z" /></svg>
                                            <span class="font-bold text-amber-500">{{ $vidCount }}</span>
                                        </span>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </div>
                        
                        <div class="mt-5 text-sm text-gray-500 font-serif leading-relaxed line-clamp-3">
                            {{ $product->description }}
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-800 bg-gray-900/50 mt-auto relative z-20">
                        @if($product->landingPage)
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('landing-page', $product->landingPage->slug) }}" target="_blank" class="text-xs uppercase tracking-widest font-black text-gray-400 hover:text-[var(--theme-color)] flex items-center gap-2 group/link transition-colors">
                                    <div class="p-1.5 rounded-lg bg-gray-800 group-hover/link:bg-[var(--theme-color-10)] border border-transparent group-hover/link:border-[var(--theme-color-30)] transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </div>
                                    Seite öffnen
                                </a>
                                
                                <div class="flex items-center gap-1">
                                    <button 
                                        wire:click="regenerateLandingPage('{{ $product->id }}')" 
                                        class="inline-flex items-center justify-center p-2 rounded-xl text-gray-500 hover:text-amber-500 hover:bg-amber-500/10 border border-transparent hover:border-amber-500/20 focus:outline-none transition-all duration-200"
                                        title="Seite neu aus Template generieren"
                                        onclick="confirm('Möchtest du diese Landing Page wirklich neu generieren? Manuelle Änderungen in der Blade-Datei gehen dabei verloren!') || event.stopImmediatePropagation()"
                                    >
                                        <div wire:loading.remove wire:target="regenerateLandingPage('{{ $product->id }}')">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </div>
                                        <svg wire:loading wire:target="regenerateLandingPage('{{ $product->id }}')" class="animate-spin h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </button>

                                    <button 
                                        x-data="{ copied: false }" 
                                        @click="
                                            navigator.clipboard.writeText('{{ url('/l/'.$product->landingPage->slug) }}'); 
                                            copied = true; 
                                            setTimeout(() => copied = false, 2000);
                                        " 
                                        class="inline-flex items-center justify-center p-2 rounded-xl text-gray-500 hover:text-[var(--theme-color)] hover:bg-[var(--theme-color-10)] border border-transparent hover:border-[var(--theme-color-20)] focus:outline-none transition-all duration-200"
                                        title="Link kopieren"
                                    >
                                        <svg x-show="!copied" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        <svg x-show="copied" style="display:none;" class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <button 
                                wire:click="generateLandingPage('{{ $product->id }}')" 
                                wire:loading.attr="disabled" 
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-[var(--theme-color-50)] text-xs uppercase tracking-widest font-black rounded-lg text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white focus:outline-none transition-all duration-300 shadow-[0_0_15px_var(--theme-color-20)] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <div wire:loading.remove wire:target="generateLandingPage('{{ $product->id }}')" class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Landing-Page generieren
                                </div>
                                <div wire:loading.flex wire:target="generateLandingPage('{{ $product->id }}')" class="items-center">
                                    <svg class="animate-spin -ml-1 mr-2 w-4 h-4 text-current" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span>Generiere...</span>
                                </div>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
