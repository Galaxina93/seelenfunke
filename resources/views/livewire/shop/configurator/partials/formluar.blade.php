<div class="p-6 space-y-8 max-w-2xl mx-auto {{ $context === 'preview' ? 'opacity-60 grayscale-[0.5] pointer-events-none' : '' }}">

    @if($context !== 'preview')
        <div class="space-y-4">
            <h3 class="text-sm font-black uppercase tracking-widest flex items-center gap-2 {{ $isDark ? 'text-gray-200' : 'text-slate-900' }}">
                <span class="w-8 h-px {{ $isDark ? 'bg-gray-700' : 'bg-slate-200' }}"></span>
                Symbol-Bibliothek
            </h3>
            <div class="border rounded-[2rem] p-5 {{ $isDark ? 'bg-gray-900 border-gray-800' : 'bg-slate-50 border-slate-200' }}">
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-3">
                    @php
                        $vectorPath = public_path('images/configurator/vectors');
                        $vectors = [];
                        if(\Illuminate\Support\Facades\File::exists($vectorPath)) {
                            $files = \Illuminate\Support\Facades\File::files($vectorPath);
                            foreach($files as $file) {
                                if(strtolower($file->getExtension()) === 'svg') {
                                    $filename = $file->getFilename();
                                    $name = str_replace('.svg', '', $filename);
                                    $name = ucwords(str_replace(['-', '_'], ' ', $name));
                                    $vectors[] = ['file' => $filename, 'name' => $name];
                                }
                            }
                        }
                    @endphp

                    @forelse($vectors as $v)
                        <button wire:click="addStandardVector('{{ $v['file'] }}')" class="aspect-square rounded-2xl border shadow-sm hover:border-primary hover:scale-105 transition-all p-3 flex flex-col items-center justify-center group {{ $isDark ? 'bg-gray-950 border-gray-700' : 'bg-black border-slate-100' }}">
                            <img src="{{ asset('images/configurator/vectors/'.$v['file']) }}" class="w-full h-full object-contain opacity-60 group-hover:opacity-100 transition-opacity {{ $isDark ? 'filter invert brightness-0' : '' }}">
                            <span class="text-[8px] font-bold uppercase mt-2 group-hover:text-primary truncate w-full text-center {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">{{ $v['name'] }}</span>
                        </button>
                    @empty
                        <div class="col-span-full text-center text-xs py-4 {{ $isDark ? 'text-gray-500' : 'text-slate-400' }}">
                            Keine SVG-Dateien im Ordner "images/configurator/vectors" gefunden.
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
                        Laden Sie Ihr Logo hoch. Position, Größe und Rotation können Sie direkt oben im Vorschaubild durch Ziehen der Icons anpassen. Wir prüfen jede Datei manuell.
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
