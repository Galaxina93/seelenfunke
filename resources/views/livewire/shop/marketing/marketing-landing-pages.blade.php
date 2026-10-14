<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-80: {{ $this->themeColorHex }}CC;">
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto space-y-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-[0_0_10px_var(--theme-color-30)]">
                    Landing Pages
                </h1>
                <p class="mt-2 text-sm text-gray-400 font-mono tracking-wide uppercase">
                    Erstelle und verwalte hochkonvertierende Produkt-Landing-Pages für Kampagnen & QR-Codes.
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
                            <span class="text-2xl">✨</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1.5">
                            <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                ZENTRALES DYNAMISCHES MASTER-TEMPLATE
                            </span>
                        </div>
                        <p class="text-sm md:text-base text-gray-300 font-serif leading-relaxed">
                            Alle aktiven Landing Pages nutzen automatisch das optimierte, zentrale Design inklusive unserer <strong>❤️ Herzensprojekt-Sektion für Kinder vor Ort</strong>. Texte und Handlungsaufforderungen können direkt hier im Admin-Panel bearbeitet werden.
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
                    <h3 class="text-red-400 font-bold mb-1">Achtung</h3>
                    <p class="text-red-300 text-sm leading-relaxed">{{ $actionError }}</p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($products as $product)
                @php
                    $lp = $product->landingPage;
                    $hasCustomFile = $lp && \Illuminate\Support\Facades\File::exists(resource_path('views/frontend/pages/landingpages/' . $lp->slug . '.blade.php'));
                @endphp
                <div wire:key="product-{{ $product->id }}" class="group bg-gray-950 border border-gray-800 hover:border-[var(--theme-color-50)] overflow-hidden rounded-2xl flex flex-col relative transition-all duration-300 shadow-inner hover:shadow-[0_0_25px_var(--theme-color-15)]">
                    
                    @if($lp)
                        <div class="absolute top-3 right-3 z-10 flex items-center gap-2">
                            <button 
                                wire:click="toggleStatus('{{ $lp->id }}')" 
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] uppercase tracking-widest font-black border shadow-sm backdrop-blur-md transition-colors {{ $lp->status === 'active' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/20' : 'bg-gray-800 text-gray-400 border-gray-700 hover:bg-gray-700' }}"
                                title="Klicken, um Status zu ändern"
                            >
                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $lp->status === 'active' ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500' }}"></span>
                                {{ $lp->status === 'active' ? 'Aktiv' : 'Entwurf' }}
                            </button>
                        </div>
                    @endif
                    
                    <div class="p-6 flex-grow flex flex-col relative z-20">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                @if(is_array($product->media_gallery) && count($product->media_gallery) > 0)
                                    <img class="h-16 w-16 rounded-xl object-cover shadow-md border border-gray-800" src="{{ asset('storage/' . $product->media_gallery[0]['path']) }}" alt="{{ $product->name }}">
                                @else
                                    <div class="h-16 w-16 rounded-xl bg-gray-900 border border-gray-800 flex items-center justify-center shadow-inner">
                                        <svg class="h-8 w-8 text-[var(--theme-color-30)]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0 pt-1 pr-16">
                                <h3 class="text-base font-bold text-gray-200 truncate group-hover:text-white transition-colors" title="{{ $product->name }}">
                                    {{ $product->name }}
                                </h3>
                                <p class="text-sm font-mono text-[var(--theme-color)] mt-1 drop-shadow-[0_0_5px_var(--theme-color-30)]">
                                    {{ $product->formatted_price }}
                                </p>
                                
                                <div class="mt-2 flex items-center gap-2">
                                    @if($hasCustomFile)
                                        <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-blue-500/10 text-blue-400 border border-blue-500/20" title="Benutzerdefiniertes Blade-Template">
                                            Spezial-Blade
                                        </span>
                                    @else
                                        <span class="text-[9px] font-mono px-2 py-0.5 rounded bg-gray-800 text-gray-400 border border-gray-700" title="Verwendet das zentrale Master-Template">
                                            Master-Template
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-5 text-sm text-gray-400 font-serif leading-relaxed line-clamp-2">
                            {{ $lp ? ($lp->headline ?: $product->description) : $product->description }}
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-800 bg-gray-900/50 mt-auto relative z-20">
                        @if($lp)
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('landing-page', $lp->slug) }}" target="_blank" class="text-xs uppercase tracking-widest font-black text-gray-400 hover:text-[var(--theme-color)] flex items-center gap-1.5 group/link transition-colors">
                                    <div class="p-1.5 rounded-lg bg-gray-800 group-hover/link:bg-[var(--theme-color-10)] border border-transparent group-hover/link:border-[var(--theme-color-30)] transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </div>
                                    Öffnen
                                </a>
                                
                                <div class="flex items-center gap-1">
                                    {{-- Edit Button --}}
                                    <button 
                                        wire:click="openEditModal('{{ $lp->id }}')" 
                                        class="p-2 rounded-xl text-gray-400 hover:text-white hover:bg-white/10 border border-transparent hover:border-gray-700 transition-all"
                                        title="Texte & CTA bearbeiten"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    {{-- Copy Link Button --}}
                                    <button 
                                        x-data="{ copied: false }" 
                                        @click="
                                            navigator.clipboard.writeText('{{ url('/l/'.$lp->slug) }}'); 
                                            copied = true; 
                                            setTimeout(() => copied = false, 2000);
                                        " 
                                        class="p-2 rounded-xl text-gray-400 hover:text-[var(--theme-color)] hover:bg-[var(--theme-color-10)] border border-transparent hover:border-[var(--theme-color-20)] transition-all"
                                        title="Link kopieren"
                                    >
                                        <svg x-show="!copied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                        </svg>
                                        <svg x-show="copied" style="display:none;" class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>

                                    {{-- Delete Button --}}
                                    <button 
                                        wire:click="deleteLandingPage('{{ $lp->id }}')" 
                                        class="p-2 rounded-xl text-gray-500 hover:text-red-400 hover:bg-red-500/10 border border-transparent hover:border-red-500/20 transition-all"
                                        title="Landing Page deaktivieren/löschen"
                                        onclick="confirm('Möchtest du diese Landing Page wirklich löschen?') || event.stopImmediatePropagation()"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <button 
                                wire:click="generateLandingPage('{{ $product->id }}')" 
                                class="w-full inline-flex justify-center items-center px-4 py-2 border border-[var(--theme-color-50)] text-xs uppercase tracking-widest font-black rounded-lg text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white transition-all duration-300 shadow-[0_0_15px_var(--theme-color-20)]"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Landing-Page aktivieren
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- BEARBEITEN-MODAL --}}
    @if($showEditModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-950/80 backdrop-blur-md">
            <div class="relative w-full max-w-2xl bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl p-6 sm:p-8 space-y-6 animate-fade-in-up">
                <div class="flex items-center justify-between border-b border-gray-800 pb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Landing Page bearbeiten</h2>
                        <p class="text-xs text-gray-400 mt-1">Passe Titel, Werbetexte und Button für maximale Conversion an.</p>
                    </div>
                    <button wire:click="closeEditModal" class="text-gray-400 hover:text-white p-1 rounded-lg">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form wire:submit.prevent="saveEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Produkt-Titel auf Landingpage</label>
                        <input type="text" wire:model="editTitle" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[var(--theme-color)] focus:outline-none">
                        @error('editTitle') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Headline / Subline (Slogan)</label>
                        <input type="text" wire:model="editHeadline" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[var(--theme-color)] focus:outline-none" placeholder="z.B. Das perfekte Geschenk für besondere Momente">
                        @error('editHeadline') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Werbetext / Story (Details-Sektion)</label>
                        <textarea rows="5" wire:model="editSalesCopy" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[var(--theme-color)] focus:outline-none" placeholder="Beschreibe die Besonderheit, Emotion und Veredelung des Produkts..."></textarea>
                        @error('editSalesCopy') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase text-gray-400 mb-1">CTA-Button Text</label>
                            <input type="text" wire:model="editCtaText" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[var(--theme-color)] focus:outline-none" placeholder="Jetzt dein Unikat gestalten">
                            @error('editCtaText') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Status</label>
                            <select wire:model="editStatus" class="w-full bg-gray-950 border border-gray-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[var(--theme-color)] focus:outline-none">
                                <option value="active">Aktiv (öffentlich aufrufbar)</option>
                                <option value="draft">Entwurf (deaktiviert)</option>
                            </select>
                            @error('editStatus') <span class="text-xs text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-800">
                        <button type="button" wire:click="closeEditModal" class="px-5 py-2.5 text-sm text-gray-400 hover:text-white rounded-xl hover:bg-white/5 transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-[var(--theme-color)] hover:opacity-90 rounded-xl shadow-[0_0_15px_var(--theme-color-30)] transition-all">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
