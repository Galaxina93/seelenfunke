<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3;" class="space-y-6 pb-20 animate-fade-in-up font-sans antialiased text-gray-300">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden">
            <x-heroicon-o-document-duplicate class="w-40 h-40 text-[var(--theme-color)] drop-shadow-[0_0_20px_var(--theme-color)]" />
        <div class="relative z-10">
            <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">Produktvorlagen</h1>
            <p class="text-gray-400 mt-2 text-sm font-medium">Erstellen und verwalten Sie vorkonfigurierte Design-Vorlagen für Ihre Kunden.</p>
        </div>
        <div class="relative z-10 flex gap-3">
            @if($viewMode === 'list')
                <button wire:click="createNew" class="bg-[var(--theme-color)] text-gray-900 px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest shadow-[0_0_20px_var(--theme-color-30)] hover:bg-white hover:scale-[1.02] transition-all flex items-center gap-2">
                    <x-heroicon-o-plus-circle class="w-5 h-5" /> Neue Vorlage
                </button>
            @else
                <button wire:click="cancel" class="bg-gray-800 text-gray-400 hover:text-white px-6 py-3.5 rounded-xl font-black text-[10px] uppercase tracking-widest transition-all flex items-center gap-2 border border-gray-700 shadow-inner">
                    Zurück
                </button>
            @endif
        </div>
    </div>

    @if($viewMode === 'list')
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @forelse($templates as $template)
                <div class="bg-gray-900/80 backdrop-blur-xl rounded-[2rem] border border-gray-800 p-6 shadow-2xl flex flex-col group relative hover:border-[var(--theme-color-50)] transition-all duration-300">
                    <div class="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity z-20">
                        <button wire:click="edit('{{$template->id}}')" class="p-2 bg-gray-900/90 backdrop-blur-md border border-gray-700 rounded-xl text-gray-400 hover:text-[var(--theme-color)] transition-colors shadow-lg"><x-heroicon-o-pencil class="w-4 h-4"/></button>
                        <button wire:click="delete('{{$template->id}}')" wire:confirm="Vorlage wirklich löschen?" class="p-2 bg-gray-900/90 backdrop-blur-md border border-gray-700 rounded-xl text-gray-400 hover:text-red-500 transition-colors shadow-lg"><x-heroicon-o-trash class="w-4 h-4"/></button>
                    </div>
                    <div class="w-full h-48 bg-gray-950 rounded-2xl mb-5 flex items-center justify-center border border-gray-800 shadow-inner overflow-hidden relative">
                        @if($template->preview_image)
                            <img src="{{ $template->preview_image_url }}" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500">
                        @else
                            <x-heroicon-o-photo class="w-12 h-12 text-gray-700" />
                        @endif
                    </div>
                    <h3 class="text-white font-bold text-lg mb-1 truncate">{{$template->name}}</h3>
                    <p class="text-xs text-gray-500 mb-4 truncate">{{$template->product->name ?? 'Unbekanntes Produkt'}}</p>

                    <div class="mt-auto pt-4 border-t border-gray-800 flex justify-between items-center text-[10px] font-black uppercase tracking-widest">
                        <span class="text-gray-500">Status:</span>
                        <button wire:click="toggleActive('{{$template->id}}')" class="{{$template->is_active ? 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20 hover:bg-emerald-500/20' : 'text-red-400 bg-red-500/10 border-red-500/20 hover:bg-red-500/20'}} px-3 py-1.5 rounded-md shadow-inner border transition-all flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full {{$template->is_active ? 'bg-emerald-400' : 'bg-red-400'}}"></span>
                            {{$template->is_active ? 'Aktiv' : 'Inaktiv'}}
                        </button>
                    </div>
                    @if($template->preview_image)
                        <div class="text-[9px] text-gray-500 font-mono truncate mt-3 border-t border-gray-800/50 pt-3" title="{{ str_starts_with($template->preview_image, 'shop/') || str_starts_with($template->preview_image, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{$template->preview_image}}">
                            <span class="text-[var(--theme-color)] font-bold">Pfad:</span> 
                            @if(str_starts_with($template->preview_image, 'http'))
                                <a href="{{ $template->preview_image }}" target="_blank" class="text-blue-400 hover:underline">{{ $template->preview_image }}</a>
                            @else
                                {{ str_starts_with($template->preview_image, 'shop/') || str_starts_with($template->preview_image, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{$template->preview_image}}
                            @endif
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-full text-center py-24 bg-gray-900/50 rounded-[3rem] border border-gray-800 border-dashed shadow-inner">
                    <div class="w-20 h-20 bg-gray-950 rounded-full flex items-center justify-center mx-auto mb-6 border border-gray-800">
                        <x-heroicon-o-document-duplicate class="w-10 h-10 text-gray-600" />
                    </div>
                    <p class="text-white font-serif font-bold text-xl mb-2">Keine Vorlagen vorhanden</p>
                    <p class="text-gray-500 text-sm">Erstellen Sie die erste Vorlage, um Ihren Kunden Inspiration zu bieten.</p>
                </div>
            @endforelse
        </div>
    @elseif($viewMode === 'create_select_product')
        <div class="bg-gray-900/80 backdrop-blur-xl rounded-[3rem] border border-gray-800 p-8 sm:p-16 shadow-2xl text-center max-w-5xl mx-auto">
            <h2 class="text-3xl font-serif font-bold text-white mb-3">Produkt auswählen</h2>
            <p class="text-gray-400 text-sm mb-12">Wählen Sie das Basis-Produkt, für welches Sie eine neue Vorlage designen möchten.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6">
                @foreach($products as $prod)
                    <button wire:click="selectProduct('{{$prod->id}}')" class="p-5 bg-gray-950 border border-gray-800 rounded-3xl hover:border-[var(--theme-color-50)] hover:bg-gray-900 hover:shadow-[0_0_30px_var(--theme-color-15)] transition-all duration-300 flex flex-col items-center gap-4 text-center group">
                        <div class="w-full aspect-square bg-gray-900 rounded-2xl flex items-center justify-center border border-gray-800 shadow-inner overflow-hidden relative">
                            @if($prod->preview_image_path)
                                <img src="{{asset('storage/'.$prod->preview_image_path)}}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                            @else
                                <x-heroicon-o-cube class="w-10 h-10 text-gray-600 group-hover:text-[var(--theme-color)] transition-colors" />
                            @endif
                            <div class="absolute inset-0 bg-[var(--theme-color)]/0 group-hover:bg-[var(--theme-color-10)] transition-colors"></div>
                        </div>
                        <span class="text-xs font-bold text-gray-400 group-hover:text-white transition-colors line-clamp-2 leading-snug">{{$prod->name}}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @elseif($viewMode === 'configure')
        <div class="bg-gray-900/80 backdrop-blur-xl rounded-[3rem] border border-gray-800 p-8 shadow-2xl flex flex-col min-h-screen">
            <div class="flex flex-col xl:flex-row items-start xl:items-center gap-6 mb-8 border-b border-gray-800 pb-6 shrink-0">
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Name der Vorlage <span class="text-[var(--theme-color)]">*</span></label>
                    <input wire:model="templateName" type="text" class="w-full bg-gray-950 border border-gray-800 rounded-2xl px-5 py-4 text-sm font-bold text-white focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] outline-none shadow-inner transition-all placeholder:text-gray-600" placeholder="z.B. Premium Hochzeit Layout">
                    @error('templateName') <span class="text-red-400 text-[10px] font-bold mt-2 block ml-1 uppercase tracking-widest">{{$message}}</span> @enderror
                </div>

                <div class="w-full xl:w-56 shrink-0">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Anlass (Feiertag)</label>
                    <select wire:model="templateHoliday" class="w-full bg-gray-950 border border-gray-800 rounded-2xl px-5 py-4 text-sm font-bold text-gray-300 focus:border-[var(--theme-color)] focus:ring-2 focus:ring-[var(--theme-color-20)] outline-none shadow-inner transition-all appearance-none cursor-pointer">
                        <option value="">Kein spezifischer Anlass</option>
                        <option value="muttertag">Muttertag</option>
                        <option value="valentinstag">Valentinstag</option>
                        <option value="weihnachten">Weihnachten</option>
                        <option value="vatertag">Vatertag</option>
                        <option value="ostern">Ostern</option>
                        <option value="geburtstag">Geburtstag</option>
                        <option value="hochzeit">Hochzeit</option>
                        <option value="taufe">Taufe</option>
                        <option value="einschulung">Einschulung</option>
                        <option value="jubilaeum">Jubiläum</option>
                        <option value="trauer">Trauer</option>
                    </select>
                </div>

                <div class="w-full xl:w-96 shrink-0">
                    <label class="block text-[10px] font-black text-gray-500 uppercase tracking-widest mb-2 ml-1">Eigenes Vorschaubild (Optional)</label>
                    
                    @if($existingTemplateImagePath)
                        <div class="bg-gray-950 p-3 rounded-2xl border border-gray-800 shadow-inner flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <x-heroicon-m-check-badge class="w-5 h-5 text-[var(--theme-color)] shrink-0 mt-0.5" />
                                <div class="text-[9px] font-mono text-gray-400 break-all leading-relaxed flex-1 space-y-2">
                                    <div>
                                        <span class="font-bold text-gray-300 uppercase tracking-widest block mb-0.5 font-sans">Bild hinterlegt:</span> 
                                        @if(str_starts_with($existingTemplateImagePath, 'http'))
                                            <a href="{{ $existingTemplateImagePath }}" target="_blank" class="text-blue-400 hover:underline">{{ $existingTemplateImagePath }}</a>
                                        @else
                                            <div class="text-gray-500">Physischer Server-Pfad:</div>
                                            <div class="text-amber-500/80 bg-amber-500/10 px-1 py-0.5 rounded">
                                                {{ str_starts_with($existingTemplateImagePath, 'shop/') || str_starts_with($existingTemplateImagePath, 'shopverwaltung/images/') ? 'public/' : 'storage/app/public/' }}{{ $existingTemplateImagePath }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" wire:click="removeExistingImage" class="flex-1 bg-gray-900 hover:bg-[var(--theme-color)] hover:text-gray-900 text-[10px] text-gray-400 font-black uppercase tracking-widest py-2.5 px-3 rounded-xl border border-gray-800 hover:border-transparent transition-all shadow-lg active:scale-95">
                                    Vorschau ersetzen
                                </button>
                                <button type="button" wire:click="removeExistingImage" class="w-10 shrink-0 flex flex-col items-center justify-center bg-gray-900 border border-gray-800 hover:border-red-500/50 hover:bg-red-500/10 text-gray-500 hover:text-red-400 rounded-xl transition-all shadow-lg active:scale-95" title="Bild komplett entfernen">
                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @else
                        <input type="file" wire:model="templateImage" accept="image/*" class="w-full bg-gray-950 border border-gray-800 rounded-2xl px-4 py-3 text-xs text-gray-400 file:mr-4 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-gray-800 file:text-white hover:file:bg-gray-700 cursor-pointer shadow-inner focus:outline-none focus:ring-2 focus:ring-[var(--theme-color-20)] transition-all">
                        @error('templateImage') <span class="text-red-400 text-[10px] font-bold mt-2 block ml-1 uppercase tracking-widest">{{$message}}</span> @enderror
                        <div wire:loading wire:target="templateImage" class="text-[10px] text-[var(--theme-color)] mt-2 ml-1 font-bold animate-pulse uppercase tracking-widest">Bild wird hochgeladen...</div>
                    @endif
                </div>

                <div class="flex items-center gap-4 xl:mt-6 bg-gray-950 p-4 rounded-2xl border border-gray-800 shadow-inner w-full xl:w-auto shrink-0 cursor-pointer group" x-on:click="$wire.set('templateIsActive', !@js($templateIsActive))">
                    <input type="checkbox" wire:model.live="templateIsActive" class="w-5 h-5 rounded border-gray-700 bg-gray-900 text-[var(--theme-color)] focus:ring-[var(--theme-color)] focus:ring-offset-gray-950 cursor-pointer">
                    <label class="text-[10px] font-black text-gray-400 group-hover:text-white transition-colors uppercase tracking-widest cursor-pointer select-none">Vorlage ist Aktiv</label>
                </div>
            </div>

            <div class="flex-1 bg-white rounded-[2rem] relative shadow-inner border border-gray-200 min-h-[900px] overflow-y-auto custom-scrollbar">
                <livewire:shop.product.product-configurator.product-configurator
                    :product="$selectedProductId"
                    :initialData="$templateConfig"
                    context="template_admin"
                    :key="'template-conf-'.$selectedProductId.'-'.($editingTemplateId ?: 'new')"
                />
            </div>
        </div>
    @endif
</div>
