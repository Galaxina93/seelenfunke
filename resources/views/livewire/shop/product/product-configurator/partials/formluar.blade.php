<div class="p-6 space-y-8 max-w-2xl mx-auto {{ $context === 'preview' ? 'opacity-60 grayscale-[0.5] pointer-events-none' : '' }}">

    @if($context !== 'preview')

        <!-- START: Design Speichern / Laden -->
        <div class="space-y-4" x-data="{
            draftExists: false,
            isSaving: false,
            isLoading: false,
            storageKey: 'seelenfunke_design_draft_' + '{{ $product->id }}',
            init() {
                this.draftExists = localStorage.getItem(this.storageKey) !== null;
            },
            saveDraft() {
                let draft = {
                    texts: $wire.texts,
                    logos: $wire.logos,
                    texts_back: $wire.texts_back,
                    logos_back: $wire.logos_back,
                    notes: $wire.notes,
                    uploaded_files: $wire.uploaded_files
                };
                localStorage.setItem(this.storageKey, JSON.stringify(draft));
                this.draftExists = true;

                this.isSaving = true;
                setTimeout(() => { this.isSaving = false; }, 2500);

                $dispatch('notify', {message: 'Dein Design wurde erfolgreich auf deinem Gerät gespeichert!'});
            },
            loadDraft() {
                let draft = localStorage.getItem(this.storageKey);
                if (draft) {
                    draft = JSON.parse(draft);
                    $wire.texts = draft.texts || [];
                    $wire.logos = draft.logos || [];
                    $wire.texts_back = draft.texts_back || [];
                    $wire.logos_back = draft.logos_back || [];
                    $wire.notes = draft.notes || '';
                    $wire.uploaded_files = draft.uploaded_files || [];

                    this.isLoading = true;
                    setTimeout(() => { this.isLoading = false; }, 2500);

                    $dispatch('notify', {message: 'Gespeichertes Design erfolgreich geladen!'});
                }
            }
        }">
            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                Dein Design Sichern
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" @click="saveDraft" class="flex flex-col items-center justify-center p-4 rounded-[2rem] border text-sm font-bold shadow-sm transition-all group {{ $isDark ? 'bg-gray-900 border-gray-700 text-gray-300 hover:border-[#C5A059] hover:bg-[#C5A059]/10' : 'bg-white border-slate-200 text-slate-700 hover:border-[#C5A059] hover:bg-[#C5A059]/5 hover:shadow-md' }}">
                    <template x-if="!isSaving">
                        <div class="flex flex-col items-center">
                            <svg class="w-6 h-6 mb-1 text-[#C5A059] group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                            Aktuelles Design Speichern
                        </div>
                    </template>
                    <template x-if="isSaving">
                        <div class="flex flex-col items-center text-green-600 animate-pulse">
                            <svg class="w-6 h-6 mb-1 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            Erfolgreich gespeichert!
                        </div>
                    </template>
                </button>

                <button type="button" @click="loadDraft" x-show="draftExists" x-cloak class="flex flex-col items-center justify-center p-4 rounded-[2rem] border border-[#C5A059] bg-[#C5A059]/10 text-sm font-bold text-[#C5A059] shadow-sm transition-all hover:bg-[#C5A059]/20 hover:shadow-md group">
                    <template x-if="!isLoading">
                        <div class="flex flex-col items-center">
                            <svg class="w-6 h-6 mb-1 text-[#C5A059] group-hover:-translate-y-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                            Gespeichertes Design Laden
                        </div>
                    </template>
                    <template x-if="isLoading">
                        <div class="flex flex-col items-center text-green-700 animate-pulse">
                            <svg class="w-6 h-6 mb-1 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            Design geladen!
                        </div>
                    </template>
                </button>
            </div>

            <p class="text-xs {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                Speichert dein Design lokal in deinem Browser, damit du es später exakt so für dieses Produkt fortsetzen kannst.
            </p>
        </div>
        <!-- END: Design Speichern / Laden -->

        <div class="space-y-4">
            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                Symbol-Bibliothek
            </h3>
            <div class="border rounded-[2rem] p-5 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-slate-50 border-slate-200' }}">
                <div class="flex gap-2 p-1 overflow-x-auto custom-scrollbar border-b pb-4 mb-4 {{ $isDark ? 'border-gray-800' : 'border-slate-200' }}">
                    @foreach($vectors as $category => $items)
                        <button wire:click="$set('selectedCategory', '{{ $category }}')" class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-all shadow-sm border {{ $selectedCategory === $category ? ($isDark ? 'bg-[#C5A059] text-gray-900 border-[#C5A059] shadow-[0_0_15px_rgba(197,160,89,0.3)]' : 'bg-[#C5A059] text-white border-[#C5A059] shadow-md') : ($isDark ? 'bg-gray-800 text-gray-400 border-gray-700 hover:text-gray-200 hover:border-gray-600' : 'bg-slate-100 text-slate-500 border-slate-200 hover:bg-slate-200 hover:text-slate-700') }}">
                            {{ $category }} ({{ count($items) }})
                        </button>
                    @endforeach
                </div>
                
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
                    @php
                        $activeVectors = $vectors[$selectedCategory] ?? [];
                    @endphp

                    @forelse($activeVectors as $v)
                        <button wire:click="addStandardVector('{{ $v['file'] }}')" class="aspect-square rounded-2xl border shadow-sm hover:border-primary hover:shadow-[0_0_15px_rgba(197,160,89,0.3)] hover:scale-105 transition-all p-3 flex flex-col items-center justify-center group {{ $isDark ? 'bg-amber-500/10 border-amber-500/30' : 'bg-amber-50/50 border-amber-200' }}">
                            <img src="{{ asset('shop/product/configurator/vectors/'.$v['file']) }}" class="w-full h-full object-contain opacity-80 group-hover:opacity-100 transition-opacity">
                        </button>
                    @empty
                        <div class="col-span-full text-center text-xs py-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                            <div class="w-12 h-12 rounded-full mb-3 mx-auto flex items-center justify-center {{ $isDark ? 'bg-gray-800' : 'bg-slate-100' }}">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                            </div>
                            Keine Motive in dieser Kategorie vorhanden.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if($configSettings['allow_logo'])
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                    <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                    Eigene Logos & Bilder
                </h3>
                <span class="text-[10px] font-bold px-2 py-1 rounded-md {{ count($uploaded_files) >= 10 ? 'bg-red-100 text-red-600' : ($isDark ? 'bg-gray-800 text-gray-400' : 'bg-slate-100 text-slate-500') }}">
                    {{ count($uploaded_files) }} / 10 Dateien
                </span>
            </div>

            @if($context !== 'preview')
                <div class="border rounded-xl p-4 flex gap-4 items-start shadow-sm {{ $isDark ? 'bg-blue-900/10 border-blue-900/30' : 'bg-blue-50/80 border-blue-100' }}">
                    <div class="shrink-0 text-blue-500 mt-0.5">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-xs leading-relaxed {{ $isDark ? 'text-blue-400/80' : 'text-blue-800/80' }}">
                        Laden Sie Ihre eigenen Medien hoch. Position, Größe und Rotation können Sie direkt oben im Vorschaubild durch Ziehen der Icons anpassen. Wir prüfen jede Datei manuell.
                    </p>
                </div>
            @endif

            <div x-data="{
                    isDropping: false,
                    currentCount: {{ count($uploaded_files) }},
                    uploadError: null,
                    handleDrop(e) {
                        this.isDropping = false;
                        this.uploadError = null;
                        let files = e.dataTransfer.files;
                        if(!this.validateFiles(files)) return;
                        this.$refs.fileInput.files = files;
                        this.$refs.fileInput.dispatchEvent(new Event('change'));
                    },
                    handleInput(e) {
                        this.uploadError = null;
                        let files = e.target.files;
                        if(!this.validateFiles(files)) {
                            this.$refs.fileInput.value = '';
                            return;
                        }
                    },
                    validateFiles(files) {
                        let newCount = files.length;
                        if ((this.currentCount + newCount) > 10) {
                            this.uploadError = 'Limit erreicht! Maximal 10 Dateien erlaubt.';
                            return false;
                        }
                        return true;
                    }
                 }" class="relative">

                <div x-show="uploadError" x-cloak class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-xs font-bold text-center animate-pulse">
                    <span x-text="uploadError"></span>
                </div>

                @if($errors->has('new_files') || $errors->has('new_files.*'))
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-xs font-bold text-center">
                        {{ $errors->first('new_files') ?: $errors->first('new_files.*') }}
                    </div>
                @endif

                @if(count($uploaded_files) < 10)
                    @if($context !== 'preview')
                        <div x-on:dragover.prevent="isDropping = true"
                             x-on:dragleave.prevent="isDropping = false"
                             x-on:drop.prevent="handleDrop($event)"
                             :class="isDropping ? 'border-primary bg-primary/5 scale-[1.02]' : '{{ $isDark ? 'border-gray-700 bg-gray-900' : 'border-slate-200 bg-slate-50/50' }}'"
                             class="group border-2 border-dashed rounded-[2rem] p-10 transition-all duration-300 flex flex-col items-center justify-center text-center cursor-pointer hover:border-primary/50 relative overflow-hidden"
                             @click="$refs.fileInput.click()">

                            <input type="file" x-ref="fileInput" wire:model.live="new_files" multiple accept=".jpg,.jpeg,.png,.webp,.svg,.pdf" class="hidden" x-on:change="handleInput($event)">

                            <div class="w-12 h-12 rounded-2xl shadow-sm border flex items-center justify-center transition-all mb-3 relative z-10 {{ $isDark ? 'bg-gray-800 border-gray-700 text-gray-500 group-hover:text-primary' : 'bg-white border-slate-100 text-slate-400 group-hover:text-primary' }}">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                                </svg>
                            </div>
                            <p class="text-[10px] font-black uppercase tracking-widest relative z-10 {{ $isDark ? 'text-gray-300' : 'text-slate-900' }}">Datei hochladen oder hierher ziehen</p>
                            <p class="text-[9px] mt-1 relative z-10 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">PDF, JPG, PNG (Max. 10 Dateien)</p>
                        </div>
                    @endif

                    <div wire:loading wire:target="new_files" class="absolute inset-0 backdrop-blur-sm rounded-[2rem] border z-20 flex items-center justify-center {{ $isDark ? 'bg-gray-950/80 border-gray-800' : 'bg-white/90 border-slate-100' }}">
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex flex-col items-center gap-3 text-center">
                            <svg class="animate-spin h-8 w-8 text-primary" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span class="text-xs font-black uppercase tracking-wide {{ $isDark ? 'text-gray-300' : 'text-slate-800' }}">
                                Wird verarbeitet...
                            </span>
                        </div>
                    </div>
                @else
                    <div class="border-2 rounded-[2rem] p-6 text-center {{ $isDark ? 'border-red-900/30 bg-red-900/10' : 'border-red-100 bg-red-50' }}">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center mx-auto mb-2 shadow-sm {{ $isDark ? 'bg-gray-900 text-red-500' : 'bg-white text-red-500' }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <p class="text-xs font-bold {{ $isDark ? 'text-red-400' : 'text-red-800' }}">Maximale Anzahl (10) erreicht.</p>
                        <p class="text-[10px] mt-1 {{ $isDark ? 'text-red-500' : 'text-red-600' }}">Bitte löschen Sie eine Datei, um eine neue hochzuladen.</p>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-3">
                @foreach($uploaded_files as $index => $path)
                    @php
                        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg']);
                        $isActive = $this->isLogoActive($path);
                    @endphp

                    <div x-data="{ width: 0, height: 0, init() { if('{{ $isImage }}') { let img = new Image(); img.onload = () => { this.width = img.width; this.height = img.height; }; img.src = '{{ asset('storage/'.$path) }}'; } } }"
                         class="flex flex-col p-3 rounded-2xl border transition-all {{ $isDark ? 'bg-gray-900' : 'bg-white' }} {{ $isActive ? 'border-primary shadow-md' : ($isDark ? 'border-gray-800' : 'border-slate-100') }}">

                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4 overflow-hidden">
                                <div class="w-12 h-12 rounded-xl overflow-hidden flex items-center justify-center border shrink-0 relative {{ $isDark ? 'bg-gray-800 border-gray-700' : 'bg-slate-50 border-slate-100' }}">
                                    @if($isImage)
                                        <img src="{{ asset('storage/'.$path) }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="flex flex-col items-center justify-center {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                            <span class="text-[8px] font-bold uppercase mt-0.5">{{ $ext }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[11px] font-bold truncate {{ $isDark ? 'text-gray-200' : 'text-slate-800' }}" title="{{ basename($path) }}">{{ basename($path) }}</p>

                                    @if($isImage)
                                        <button wire:click="toggleLogo('saved', '{{ $path }}')" class="text-[9px] font-black uppercase tracking-tighter mt-1 {{ $isActive ? 'text-primary' : ($isDark ? 'text-gray-500 hover:text-primary transition-colors' : 'text-slate-400 hover:text-primary transition-colors') }}">
                                            {{ $isActive ? 'In Vorschau aktiv' : 'Als Vorschau einblenden' }}
                                        </button>
                                    @else
                                        <div class="text-[9px] font-medium mt-1 flex items-center gap-1 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                                            <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                            Erfolgreich angehängt
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if($context !== 'preview')
                                <button wire:click="removeFile({{ $index }})" class="p-2 {{ $isDark ? 'text-gray-600 hover:text-rose-500' : 'text-slate-300 hover:text-rose-500' }}">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            @endif
                        </div>

                        <template x-if="{{ $isActive ? 'true' : 'false' }} && '{{ $ext }}' !== 'svg' && (width > 0 && (width < 877 || height < 877))">
                            <div class="mt-2 border p-2 rounded-xl text-[9px] font-bold flex items-center gap-2 {{ $isDark ? 'bg-amber-900/10 border-amber-900/30 text-amber-500' : 'bg-amber-50 border-amber-100 text-amber-700' }}">
                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span>Optimale Ergebnisse ab 877x877px. Aktuell: <span x-text="width + 'x' + height"></span>px.</span>
                            </div>
                        </template>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="pt-6 border-t {{ $isDark ? 'border-gray-800' : 'border-slate-100' }}" x-data="{ count: @entangle('notes').live?.length || 0 }">
        <label class="text-[10px] font-black uppercase tracking-[0.2em] mb-3 flex justify-between {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
            <span>Besondere Wünsche an die Manufaktur</span>
            <span class="text-[9px]" :class="count >= 400 ? 'text-red-500 font-bold' : '{{ $isDark ? 'text-gray-600' : 'text-slate-300' }}'" x-text="count + ' / 400'">0 / 400</span>
        </label>
        <textarea wire:model="notes"
                  maxlength="400"
                  x-on:input="count = $el.value.length"
                  rows="5"
                  class="w-full border-none rounded-[1.5rem] p-5 text-sm font-medium focus:ring-2 focus:ring-primary/20 transition-all resize-none shadow-inner {{ $isDark ? 'bg-gray-900 text-gray-300 placeholder-gray-600' : 'bg-slate-50 text-slate-700' }}"
                  placeholder="Geben Sie hier Details zu Ihrer gewünschten Gravur an..."
                  {{ $context === 'preview' ? 'readonly' : '' }}></textarea>
    </div>

</div>
