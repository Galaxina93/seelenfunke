<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-[0_0_10px_var(--theme-color-30)]">
                    Google Ads Generator
                </h1>
                <p class="mt-2 text-sm text-gray-400 font-mono tracking-wide uppercase">
                    Generiere vollautomatisierte PPC-Kampagnen (Keywords & Anzeigentexte) bereit für den Google Ads Export.
                </p>
            </div>
        </div>

        <!-- Agent Banner -->
        <div class="mb-4 w-full">
            @if($agent)
                <div class="relative bg-gradient-to-r from-gray-900 via-gray-950 to-gray-900 border border-[var(--theme-color-30)] rounded-2xl p-4 md:p-6 shadow-[0_0_40px_var(--theme-color-15)] overflow-hidden">
                    <div class="absolute inset-0 bg-[var(--theme-color)] opacity-5"></div>
                    <div class="relative z-10 flex flex-col sm:flex-row gap-4 sm:gap-6 items-start sm:items-center">
                        <div class="shrink-0 relative">
                            <div class="absolute -inset-2 bg-[var(--theme-color)] opacity-20 blur-xl rounded-full animate-pulse-slow"></div>
                            <div class="w-14 h-14 md:w-16 md:h-16 bg-gray-950 border-2 border-[var(--theme-color)] rounded-full flex items-center justify-center shadow-inner relative z-10 overflow-hidden">
                                @if($agent->profile_picture)
                                    <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                                @else
                                    <x-heroicon-m-megaphone class="w-6 h-6 md:w-8 md:h-8 text-[var(--theme-color)]" />
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-3 mb-1.5">
                                <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                    {{ $agent->name }} | PPC EXPERTE
                                </span>
                            </div>
                            <p class="text-sm md:text-base text-gray-300 font-serif leading-relaxed">
                                "Hallo! Ich strukturiere deine Google Ads. Ich ermittle konvertierende Target-Keywords, schließe Budget-Fresser mit Negative-Keywords aus und texte klickstarke Headlines!"
                            </p>
                        </div>
                    </div>
                </div>
            @endif
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
                    
                    @if($product->googleAdsCampaign)
                        <div class="absolute top-3 right-3 z-10">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase tracking-widest font-black bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] shadow-sm backdrop-blur-md">
                                <span class="w-1.5 h-1.5 rounded-full bg-[var(--theme-color)] mr-1.5 animate-pulse"></span>
                                Generiert
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
                            </div>
                        </div>
                        
                        @if($product->googleAdsCampaign)
                            <div class="mt-4 pt-4 border-t border-gray-800/50">
                                <div class="space-y-4">
                                    <!-- Ad Copy Preview -->
                                    <div class="bg-blue-900/10 border border-blue-500/20 rounded-lg p-3">
                                        <div class="flex items-center gap-2 mb-2 text-blue-400 text-xs font-bold uppercase tracking-wider">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                            Ad Preview
                                        </div>
                                        <div class="text-blue-300 text-sm font-medium line-clamp-1">
                                            {{ $product->googleAdsCampaign->headline_1 }} | {{ $product->googleAdsCampaign->headline_2 }}
                                        </div>
                                        <div class="text-gray-400 text-xs mt-1 line-clamp-2">
                                            {{ $product->googleAdsCampaign->description_1 }}
                                        </div>
                                    </div>
                                    
                                    <!-- Target Keywords -->
                                    <div>
                                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Target Keywords</div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(array_slice($product->googleAdsCampaign->keywords ?? [], 0, 4) as $kw)
                                                <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded text-[10px]">{{ $kw }}</span>
                                            @endforeach
                                            @if(count($product->googleAdsCampaign->keywords ?? []) > 4)
                                                <span class="px-2 py-0.5 bg-gray-800 text-gray-400 border border-gray-700 rounded text-[10px]">+{{ count($product->googleAdsCampaign->keywords) - 4 }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Negative Keywords -->
                                    <div>
                                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Negative Keywords</div>
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach(array_slice($product->googleAdsCampaign->negative_keywords ?? [], 0, 4) as $kw)
                                                <span class="px-2 py-0.5 bg-red-500/10 text-red-400 border border-red-500/20 rounded text-[10px]">{{ $kw }}</span>
                                            @endforeach
                                            @if(count($product->googleAdsCampaign->negative_keywords ?? []) > 4)
                                                <span class="px-2 py-0.5 bg-gray-800 text-gray-400 border border-gray-700 rounded text-[10px]">+{{ count($product->googleAdsCampaign->negative_keywords) - 4 }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="mt-5 text-sm text-gray-500 font-serif leading-relaxed line-clamp-3">
                                {{ $product->description }}
                            </div>
                        @endif
                    </div>

                    <div class="px-6 py-4 border-t border-gray-800 bg-gray-900/50 mt-auto relative z-20">
                        @if($product->googleAdsCampaign)
                            <button 
                                disabled
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-700 text-xs uppercase tracking-widest font-black rounded-lg text-gray-500 bg-gray-800/50 cursor-not-allowed"
                                title="Export-Funktion kommt in Kürze"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                Zu Google Ads exportieren
                            </button>
                        @else
                            <button 
                                wire:click="generateCampaign('{{ $product->id }}')" 
                                wire:loading.attr="disabled" 
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-[var(--theme-color-50)] text-xs uppercase tracking-widest font-black rounded-lg text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white focus:outline-none transition-all duration-300 shadow-[0_0_15px_var(--theme-color-20)] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <div wire:loading.remove wire:target="generateCampaign('{{ $product->id }}')" class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    Ad generieren
                                </div>
                                <div wire:loading.flex wire:target="generateCampaign('{{ $product->id }}')" class="items-center">
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
