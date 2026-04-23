<div x-show="showFiles" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     style="display: none;"
     class="absolute right-6 top-24 bottom-24 w-96 bg-black/80 backdrop-blur-xl border border-indigo-900/50 rounded-2xl shadow-[0_0_30px_rgba(99,102,241,0.15)] flex flex-col overflow-hidden z-40 pointer-events-auto">
    
    <!-- Header -->
    <div class="px-4 py-3 border-b border-indigo-900/30 flex justify-between items-center bg-indigo-950/20">
        <h3 class="text-indigo-400 font-bold tracking-widest text-[10px] uppercase flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" /></svg>
            Dateien & Pläne
        </h3>
        <button @click="showFiles = false" class="text-gray-500 hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
        </button>
    </div>

    <!-- Content Area with Drag & Drop -->
    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 flex flex-col gap-6 relative"
         x-data="{ isDropping: false }"
         @dragover.prevent="isDropping = true"
         @dragleave.prevent="isDropping = false"
         @drop.prevent="isDropping = false; $wire.uploadMultiple('uploadedFiles', $event.dataTransfer.files, () => { $wire.createTaskFromChat(); }, () => {}, (e) => {})">
        
        <!-- Drag Overlay -->
        <div x-show="isDropping" 
             class="absolute inset-0 z-50 bg-indigo-900/40 backdrop-blur-sm border-2 border-dashed border-indigo-400 rounded-xl flex items-center justify-center pointer-events-none" style="display: none;">
            <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-indigo-400 animate-bounce" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                <p class="mt-2 text-sm font-bold text-indigo-300">Dateien hier ablegen</p>
            </div>
        </div>

        <!-- Pläne Sektion -->
        <div>
            <h4 class="text-[9px] font-black uppercase tracking-widest text-indigo-500/50 border-b border-indigo-900/30 pb-2 mb-3">Pläne & Artefakte</h4>
            
            @if(count($this->artifacts) > 0)
                <div class="flex flex-col gap-2">
                    @foreach($this->artifacts as $art)
                        <div class="flex items-center justify-between bg-black/40 border border-indigo-900/30 p-2 rounded-lg hover:border-indigo-500/50 transition-colors group">
                            <div class="flex items-center gap-2 overflow-hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                <span class="text-xs font-mono text-gray-300 truncate" title="{{ $art['name'] }}">{{ $art['name'] }}</span>
                            </div>
                            <span class="text-[8px] text-gray-500 shrink-0">{{ \Carbon\Carbon::createFromTimestamp($art['last_modified'])->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 text-gray-500 text-xs font-mono">
                    Noch keine Pläne generiert.
                </div>
            @endif
        </div>

        <!-- Uploads Sektion -->
        <div>
            <h4 class="text-[9px] font-black uppercase tracking-widest text-indigo-500/50 border-b border-indigo-900/30 pb-2 mb-3">Projektdateien</h4>
            
            @if(count($this->globalFiles) > 0)
                <div class="flex flex-col gap-2">
                    @foreach($this->globalFiles as $file)
                        <div class="flex items-center justify-between bg-black/40 border border-indigo-900/30 p-2 rounded-lg group">
                            <div class="flex items-center gap-2 overflow-hidden">
                                @if(str_starts_with($file['mime'] ?? '', 'image/'))
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-pink-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                @else
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                @endif
                                <span class="text-xs font-mono text-gray-300 truncate" title="{{ $file['name'] }}">{{ $file['name'] }}</span>
                            </div>
                            <button wire:click="removeGlobalFile('{{ $file['type'] }}', '{{ $file['path'] }}')" class="text-rose-500/50 hover:text-rose-400 transition-colors opacity-0 group-hover:opacity-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4 text-gray-500 text-xs font-mono">
                    Keine Dateien hochgeladen.
                </div>
            @endif
            
            <div class="mt-4 text-center">
                <label class="cursor-pointer inline-flex items-center justify-center gap-2 px-3 py-1.5 text-[10px] font-bold tracking-widest uppercase rounded-lg border border-indigo-500/50 text-indigo-400 hover:bg-indigo-900/30 transition-colors w-full">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                    Datei auswählen
                    <input type="file" class="hidden" wire:model="uploadedFiles" multiple>
                </label>
            </div>
        </div>
        
        <!-- Global Drag&Drop info text -->
        <div class="text-[9px] text-gray-500 text-center uppercase tracking-widest mt-auto font-mono">
            Du kannst Dateien auch per Drag & Drop hierhin ziehen.
        </div>
    </div>
</div>
