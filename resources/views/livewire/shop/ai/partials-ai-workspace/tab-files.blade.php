        <!-- FILES TAB CONTENT -->
        <div wire:key="tab-files" x-data="{ 
            previewUrl: null,
            previewType: null,
            previewName: null,
            viewMode: 'grid',
            contextMenuOpen: false,
            contextMenuX: 0,
            contextMenuY: 0,
            contextMenuPath: null,
            contextMenuName: null,
            renamingItemPath: null,
            renameInput: '',
            draggedItemPath: null,
            moveModalOpen: false,
            moveModalTarget: '',
            openPreview(url, type, name) {
                this.previewUrl = url;
                this.previewType = type;
                this.previewName = name;
            },
            closePreview() {
                this.previewUrl = null;
                this.previewType = null;
                this.previewName = null;
            },
            openContextMenu(e, path, name) {
                this.contextMenuOpen = true;
                this.contextMenuX = e.clientX;
                this.contextMenuY = e.clientY;
                this.contextMenuPath = path;
                this.contextMenuName = name;
            },
            closeContextMenu() {
                this.contextMenuOpen = false;
            },
            startRenaming() {
                this.renamingItemPath = this.contextMenuPath;
                this.renameInput = this.contextMenuName;
                this.closeContextMenu();
                setTimeout(() => {
                    let input = document.getElementById('renameInput_' + this.renamingItemPath);
                    if (input) { input.focus(); input.select(); }
                }, 50);
            },
            startMoving() {
                this.moveModalOpen = true;
                this.closeContextMenu();
            },
            submitRename() {
                if(this.renameInput.trim() !== '' && this.renameInput !== this.contextMenuName) {
                    this.$wire.renameFileManagerItem(this.renamingItemPath, this.renameInput);
                }
                this.renamingItemPath = null;
            }
        }" @click="closeContextMenu" @scroll.window="closeContextMenu" :class="{'hidden': activeTab !== 'files'}" class="flex-1 shrink-0 rounded-2xl border border-gray-800 bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_rgba(0,0,0,0.5)] h-full w-full p-6">
            
            <!-- Header & Navigation -->
            <div class="flex justify-between items-center mb-4 pb-4 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="goUpFileManagerFolder" class="p-2 rounded bg-gray-800 hover:bg-gray-700 text-gray-300 transition-colors" title="Ebene hoch">
                        <x-heroicon-o-arrow-up class="w-5 h-5" />
                    </button>
                    <div class="text-sm font-mono text-gray-400 bg-black/50 px-3 py-1.5 rounded-lg border border-gray-800 flex items-center">
                        <x-heroicon-o-folder-open class="w-4 h-4 mr-2 text-[var(--theme-color)]" />
                        {{ $this->currentFilePath }}
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    
                    <!-- CREATE FOLDER -->
                    <form wire:submit.prevent="createFileManagerFolder" class="flex items-center gap-2">
                        <input type="text" wire:model="newFolderName" placeholder="Neuer Ordner..." class="bg-black/50 border border-gray-800 rounded px-3 py-1.5 text-sm text-gray-200 focus:border-[var(--theme-color)] outline-none w-32 sm:focus:w-48 transition-all">
                        <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-[var(--theme-color)] p-1.5 rounded transition-colors shadow-md" title="Ordner erstellen">
                            <x-heroicon-o-folder-plus class="w-5 h-5" />
                        </button>
                    </form>
                    
                    <!-- UPLOAD FILE -->
                    <form class="flex items-center gap-2 border-l border-gray-800 pl-3">
                        <label class="cursor-pointer bg-gray-800 hover:bg-gray-700 text-[var(--theme-color)] p-1.5 rounded transition-colors shadow-md" title="Datei hochladen">
                            <x-heroicon-o-arrow-up-tray class="w-5 h-5" />
                            <input type="file" wire:model.live="fileUpload" class="hidden">
                        </label>
                        <div wire:loading wire:target="fileUpload" class="text-xs text-[var(--theme-color)] font-mono animate-pulse">Lädt hoch...</div>
                    </form>

                    <div class="flex bg-black/50 rounded-lg border border-gray-800 p-1 ml-1 sm:ml-2">
                        <button type="button" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-gray-800 text-white' : 'text-gray-500 hover:text-gray-300'" class="p-1.5 rounded transition-colors">
                            <x-heroicon-o-squares-2x2 class="w-5 h-5" />
                        </button>
                        <button type="button" @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-gray-800 text-white' : 'text-gray-500 hover:text-gray-300'" class="p-1.5 rounded transition-colors">
                            <x-heroicon-o-list-bullet class="w-5 h-5" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- GENERIC MEDIA LIGHTBOX OVERLAY -->
            <div x-show="previewUrl" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex flex-col items-center justify-center p-4 backdrop-blur-sm shadow-2xl">
                <div class="absolute top-4 right-4 flex gap-4 z-50">
                    <a :href="previewUrl" download target="_blank" class="text-gray-300 hover:text-white bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg font-bold text-sm shadow-lg flex items-center gap-2 transition-colors">
                        <x-heroicon-o-arrow-down-tray class="w-5 h-5"/> Herunterladen
                    </a>
                    <button @click="closePreview()" class="text-white hover:text-red-500 bg-gray-900 hover:bg-gray-800 p-2 rounded-lg shadow-lg transition-colors">
                        <x-heroicon-o-x-mark class="w-6 h-6"/>
                    </button>
                </div>
                
                <div class="text-white font-mono text-sm mb-4 bg-gray-900 px-4 py-1 rounded-full border border-gray-700" x-text="previewName"></div>

                <div @click.outside="closePreview()" class="relative max-w-5xl w-full flex justify-center items-center shadow-[0_0_50px_black] rounded-xl overflow-hidden" style="max-height: 85vh;">
                    
                    <!-- IMAGE -->
                    <template x-if="previewType === 'image'">
                        <img :src="previewUrl" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-[0_0_30px_rgba(255,255,255,0.05)]">
                    </template>

                    <!-- PDF -->
                    <template x-if="previewType === 'pdf'">
                        <iframe :src="previewUrl" class="w-full h-[80vh] rounded-lg bg-white"></iframe>
                    </template>

                    <!-- VIDEO -->
                    <template x-if="previewType === 'video'">
                        <video :src="previewUrl" controls autoplay class="max-w-full max-h-[80vh] rounded-lg shadow-[0_0_30px_rgba(255,255,255,0.05)]"></video>
                    </template>

                    <!-- AUDIO -->
                    <template x-if="previewType === 'audio'">
                        <div class="bg-gray-900 p-8 rounded-2xl border border-gray-800 flex flex-col items-center gap-6 w-full max-w-md">
                            <x-heroicon-o-musical-note class="w-24 h-24 text-[var(--theme-color)] opacity-80" />
                            <audio :src="previewUrl" controls autoplay class="w-full outline-none"></audio>
                        </div>
                    </template>

                    <!-- OTHER -->
                    <template x-if="previewType === 'other'">
                        <div class="bg-gray-900 p-10 rounded-2xl border border-gray-800 flex flex-col items-center gap-6 text-center max-w-md">
                            <x-heroicon-o-document class="w-24 h-24 text-gray-500" />
                            <div>
                                <h3 class="text-xl font-bold text-white mb-2">Keine Vorschau verfügbar</h3>
                                <p class="text-gray-400 text-sm">Für diesen Dateityp ist direkt im Browser keine Vorschau möglich.</p>
                            </div>
                            <a :href="previewUrl" download target="_blank" class="bg-[var(--theme-color)] hover:bg-[var(--theme-color-hover)] text-black px-6 py-3 rounded-xl font-bold transition-transform hover:scale-105 shadow-lg flex items-center gap-2 mt-2">
                                <x-heroicon-s-arrow-down-tray class="w-5 h-5" /> Datei jetzt herunterladen
                            </a>
                        </div>
                    </template>
                </div>
            </div>

            <!-- LIGHTBOX OVERLAY FOR TEXT/MARKDOWN -->
            @if($this->previewContent !== null)
            <div class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4 backdrop-blur-sm shadow-2xl" x-data="{ viewMode: 'markdown' }">
                <div class="relative max-w-6xl w-full max-h-full h-[85vh] flex flex-col bg-gray-950 shadow-[0_0_50px_black] rounded-xl border border-gray-800 overflow-hidden">
                    <button wire:click="closeFilePreview" class="absolute top-3 right-4 text-gray-400 hover:text-red-500 transition-colors z-10">
                        <x-heroicon-o-x-mark class="w-6 h-6"/>
                    </button>
                    
                    <!-- Viewer Header -->
                    <div class="bg-[var(--theme-color-10)] border-b border-gray-800 px-6 py-3 flex items-center pr-16">
                        <div class="font-mono text-[var(--theme-color)] font-bold text-sm tracking-widest uppercase flex-1 truncate">
                            <x-heroicon-o-document-text class="w-5 h-5 inline-block mr-2 -mt-0.5" />
                            {{ $this->previewFilename }}
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button @click="viewMode = 'markdown'" :class="viewMode === 'markdown' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">Preview</button>
                            <button @click="viewMode = 'code'"     :class="viewMode === 'code' ? 'bg-[var(--theme-color)] text-black' : 'bg-gray-800 text-gray-400 hover:text-white'" class="px-3 py-1 text-xs font-bold rounded shadow-sm">RAW Editor</button>
                        </div>
                    </div>

                    <!-- Viewer Body -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-6 relative">
                        <!-- Markdown Preview -->
                        <div x-show="viewMode === 'markdown'" 
                             class="ai-markdown-content w-full h-full"
                             x-html="window.renderAiMarkdown ? window.renderAiMarkdown(@js($this->previewContent)) : @js($this->previewContent)">
                        </div>

                        <!-- RAW Code Block -->
                        <div x-show="viewMode === 'code'" style="display: none;" class="w-full h-full">
                            <textarea class="w-full h-full bg-gray-950 text-emerald-400 font-mono text-sm p-4 border border-gray-800 rounded outline-none custom-scrollbar" readonly>{{ $this->previewContent }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- FOLDER CONTEXT MENU -->
            <template x-teleport="body">
                <div x-show="contextMenuOpen" x-cloak 
                     class="fixed z-[9999] bg-gray-900 border border-gray-700 rounded-lg shadow-xl py-1 overflow-hidden min-w-[150px]"
                     :style="`top: ${contextMenuY}px; left: ${contextMenuX}px;`"
                     @click.outside="closeContextMenu()"
                     @click.stop>
                    <button type="button" @click="startRenaming()" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 transition-colors text-sm font-mono flex items-center gap-2">
                        <x-heroicon-o-pencil class="w-4 h-4" /> Umbenennen
                    </button>
                    <button type="button" @click="startMoving()" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 transition-colors text-sm font-mono flex items-center gap-2">
                        <x-heroicon-o-arrows-right-left class="w-4 h-4" /> Verschieben in...
                    </button>
                    <button type="button" @click="$wire.archiveFileManagerItem(contextMenuPath); closeContextMenu()" class="w-full text-left px-4 py-2 text-gray-300 hover:bg-gray-800 transition-colors text-sm font-mono flex items-center gap-2">
                        <x-heroicon-o-archive-box class="w-4 h-4" /> Archivieren
                    </button>
                    <div class="h-px bg-gray-700 my-1"></div>
                    <button type="button" @click="if(confirm('Element wirklich löschen?')) { $wire.deleteFileManagerItem(contextMenuPath); } closeContextMenu()" class="w-full text-left px-4 py-2 text-red-500 hover:bg-gray-800 transition-colors text-sm font-mono flex items-center gap-2">
                        <x-heroicon-s-trash class="w-4 h-4" /> Löschen
                    </button>
                </div>
            </template>

            <div class="flex-1 overflow-y-auto custom-scrollbar pr-2 relative"
                 x-data="{ isDroppingFiles: false }"
                 @dragover.prevent="isDroppingFiles = true"
                 @dragleave.prevent="isDroppingFiles = false"
                 @drop.prevent="isDroppingFiles = false; if($event.dataTransfer.files.length > 0) { $wire.upload('fileUpload', $event.dataTransfer.files[0], () => { $wire.uploadFileManagerFile(); }); }">
                
                <!-- Drag Overlay for File Uploads -->
                <div x-show="isDroppingFiles" x-cloak
                     class="absolute inset-0 z-50 bg-[var(--theme-color-10)] backdrop-blur-sm border-2 border-dashed border-[var(--theme-color)] rounded-xl flex items-center justify-center pointer-events-none">
                    <div class="text-center">
                        <x-heroicon-o-arrow-up-tray class="h-12 w-12 mx-auto text-[var(--theme-color)] animate-bounce" />
                        <p class="mt-2 text-sm font-bold text-[var(--theme-color)] tracking-widest uppercase">Datei hier ablegen</p>
                    </div>
                </div>
                @if(count($this->fileManagerItems) > 0)
                    <!-- GRID VIEW -->
                    <div x-show="viewMode === 'grid'" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 p-2">
                        @foreach($this->fileManagerItems as $file)
                            @if($file['type'] === 'folder')
                                <div wire:key="file-item-{{ md5($file['path']) }}" 
                                     @click="if(renamingItemPath !== '{{ addslashes($file['path']) }}') { $wire.openFileManagerFolder('{{ addslashes($file['name']) }}') }" 
                                     @contextmenu.prevent.stop="openContextMenu($event, '{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')" 
                                     draggable="true"
                                     @dragstart="draggedItemPath = '{{ addslashes($file['path']) }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', draggedItemPath);"
                                     @dragover.prevent="$event.dataTransfer.dropEffect = 'move'; $el.classList.add('border-emerald-500', 'bg-emerald-950/30')"
                                     @dragleave="$el.classList.remove('border-emerald-500', 'bg-emerald-950/30')"
                                     @drop.prevent="$el.classList.remove('border-emerald-500', 'bg-emerald-950/30'); if(draggedItemPath && draggedItemPath !== '{{ addslashes($file['path']) }}') { $wire.moveFileManagerItem(draggedItemPath, '{{ addslashes($file['path']) }}'); draggedItemPath = null; }"
                                     class="cursor-pointer border border-gray-800 bg-gray-950 rounded-xl p-4 flex flex-col items-center justify-center text-center gap-3 transition-all hover:scale-105 hover:border-[var(--theme-color)] shadow-md group relative">
                                    <x-heroicon-o-folder class="w-16 h-16 text-[var(--theme-color)] opacity-80 group-hover:opacity-100 transition-opacity" />
                                    <div class="font-mono text-xs font-bold leading-tight break-all line-clamp-2 w-full px-1 text-gray-300">
                                        <template x-if="renamingItemPath === '{{ addslashes($file['path']) }}'">
                                            <input :id="'renameInput_{{ addslashes($file['path']) }}'" type="text" x-model="renameInput" @keydown.enter.prevent.stop="submitRename" @keydown.escape.prevent.stop="renamingItemPath = null" @click.stop class="w-full bg-black/50 border border-gray-600 rounded px-1 py-0.5 text-center text-white outline-none">
                                        </template>
                                        <template x-if="renamingItemPath !== '{{ addslashes($file['path']) }}'">
                                            <span>{{ $file['name'] }}</span>
                                        </template>
                                    </div>
                                </div>
                            @else
                                @php
                                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                    $props = match($ext) {
                                        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => ['icon' => 'heroicon-o-photo', 'class' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/30'],
                                        'pdf' => ['icon' => 'heroicon-o-document-text', 'class' => 'text-red-400 border-red-900/50 bg-red-950/30'],
                                        'csv', 'xlsx' => ['icon' => 'heroicon-o-table-cells', 'class' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/30'],
                                        'mp4', 'webm', 'ogg', 'mov' => ['icon' => 'heroicon-o-film', 'class' => 'text-blue-400 border-blue-900/50 bg-blue-950/30'],
                                        'mp3', 'wav', 'm4a' => ['icon' => 'heroicon-o-musical-note', 'class' => 'text-orange-400 border-orange-900/50 bg-orange-950/30'],
                                        default => ['icon' => 'heroicon-o-document', 'class' => 'text-gray-400 border-gray-700 bg-gray-900']
                                    };
                                    $previewType = match($ext) {
                                        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => 'image',
                                        'pdf' => 'pdf',
                                        'mp4', 'webm', 'ogg', 'mov' => 'video',
                                        'mp3', 'wav', 'm4a' => 'audio',
                                        'md', 'txt', 'json' => 'text',
                                        default => 'other'
                                    };
                                @endphp
                                <div wire:key="file-item-{{ md5($file['path']) }}"
                                     @contextmenu.prevent.stop="openContextMenu($event, '{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')"
                                     draggable="true"
                                     @dragstart="draggedItemPath = '{{ addslashes($file['path']) }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', draggedItemPath);"
                                     class="border rounded-xl p-4 flex flex-col items-center justify-center text-center gap-3 transition-all hover:scale-105 hover:bg-opacity-80 shadow-md {{ $props['class'] }} relative overflow-hidden group">
                                    
                                    @if($previewType === 'text')
                                        <button type="button" wire:click="openFilePreview('{{ addslashes($file['path']) }}')" class="cursor-pointer">
                                            <div class="w-16 h-16 rounded-full bg-black/40 border border-current shadow-inner flex justify-center items-center group-hover:scale-110 transition-transform">
                                                <x-dynamic-component :component="$props['icon']" class="w-8 h-8 opacity-80" />
                                            </div>
                                        </button>
                                    @elseif($previewType === 'image')
                                        <button type="button" @click="openPreview('{{ $file['url'] }}', '{{ $previewType }}', '{{ addslashes($file['name']) }}')" class="cursor-pointer">
                                            <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-fuchsia-500/50 shadow-lg group-hover:border-fuchsia-400 group-hover:scale-110 group-hover:rotate-3 transition-transform relative">
                                                <img src="{{ $file['url'] }}" class="w-full h-full object-cover">
                                            </div>
                                        </button>
                                    @else
                                        <button type="button" @click="openPreview('{{ $file['url'] }}', '{{ $previewType }}', '{{ addslashes($file['name']) }}')" class="cursor-pointer">
                                            <div class="w-16 h-16 rounded-full bg-black/40 border border-current shadow-inner flex justify-center items-center group-hover:scale-110 transition-transform">
                                                <x-dynamic-component :component="$props['icon']" class="w-8 h-8 opacity-80" />
                                            </div>
                                        </button>
                                    @endif
                                    <div class="font-mono text-xs font-bold leading-tight break-all line-clamp-2 w-full px-1 text-gray-300" title="{{ $file['name'] }}">
                                        <template x-if="renamingItemPath === '{{ addslashes($file['path']) }}'">
                                            <input :id="'renameInput_{{ addslashes($file['path']) }}'" type="text" x-model="renameInput" @keydown.enter.prevent.stop="submitRename" @keydown.escape.prevent.stop="renamingItemPath = null" @click.stop class="w-full bg-black/50 border border-gray-600 rounded px-1 py-0.5 text-center text-white outline-none">
                                        </template>
                                        <template x-if="renamingItemPath !== '{{ addslashes($file['path']) }}'">
                                            <span>{{ $file['name'] }}</span>
                                        </template>
                                    </div>
                                    <div class="text-[9px] opacity-70 mb-1">{{ round($file['size'] / 1024, 1) }} KB</div>
                                    
                                    <!-- Delete File Button -->
                                    <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" wire:click="deleteFileManagerItem('{{ addslashes($file['path']) }}')" wire:confirm="Datei wirklich löschen?" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all">
                                            <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <!-- TABLE VIEW -->
                    <div x-show="viewMode === 'table'" style="display: none;">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-xs uppercase text-gray-500 border-b border-gray-800">
                                    <th class="py-2 pl-2">Name</th>
                                    <th class="py-2">Größe</th>
                                    <th class="py-2">Typ</th>
                                    <th class="py-2">Zuletzt geändert</th>
                                    <th class="py-2 text-right pr-2">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-mono">
                                @foreach($this->fileManagerItems as $file)
                                    <tr wire:key="file-item-{{ md5($file['path']) }}"
                                        @contextmenu.prevent.stop="openContextMenu($event, '{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')"
                                        draggable="true"
                                        @dragstart="draggedItemPath = '{{ addslashes($file['path']) }}'; $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', draggedItemPath);"
                                        @if($file['type'] === 'folder')
                                            @dragover.prevent="$event.dataTransfer.dropEffect = 'move'; $el.classList.add('bg-emerald-900/40')"
                                            @dragleave="$el.classList.remove('bg-emerald-900/40')"
                                            @drop.prevent="$el.classList.remove('bg-emerald-900/40'); if(draggedItemPath && draggedItemPath !== '{{ addslashes($file['path']) }}') { $wire.moveFileManagerItem(draggedItemPath, '{{ addslashes($file['path']) }}'); draggedItemPath = null; }"
                                        @endif
                                        class="border-b border-gray-800/50 hover:bg-gray-800/20 transition-colors cursor-pointer">
                                        <td class="py-3 pl-2 flex items-center gap-3">
                                            @if($file['type'] === 'folder')
                                                <x-heroicon-s-folder class="w-6 h-6 text-[var(--theme-color)]" />
                                                <template x-if="renamingItemPath === '{{ addslashes($file['path']) }}'">
                                                    <input :id="'renameInput_{{ addslashes($file['path']) }}'" type="text" x-model="renameInput" @keydown.enter.prevent.stop="submitRename" @keydown.escape.prevent.stop="renamingItemPath = null" @click.stop class="w-full bg-black/50 border border-gray-600 rounded px-2 py-1 text-white outline-none font-bold">
                                                </template>
                                                <template x-if="renamingItemPath !== '{{ addslashes($file['path']) }}'">
                                                    <button @click="if(renamingItemPath !== '{{ addslashes($file['path']) }}') { $wire.openFileManagerFolder('{{ addslashes($file['name']) }}') }" class="font-bold text-gray-300 hover:text-white hover:underline">{{ $file['name'] }}</button>
                                                </template>
                                            @else
                                                @php
                                                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                                    $previewType = match($ext) {
                                                        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => 'image',
                                                        'pdf' => 'pdf',
                                                        'mp4', 'webm', 'ogg', 'mov' => 'video',
                                                        'mp3', 'wav', 'm4a' => 'audio',
                                                        'md', 'txt', 'json' => 'text',
                                                        default => 'other'
                                                    };
                                                @endphp
                                                @if($previewType === 'text')
                                                    <x-heroicon-o-document-text class="w-6 h-6 text-gray-400" />
                                                    <template x-if="renamingItemPath === '{{ addslashes($file['path']) }}'">
                                                        <input :id="'renameInput_{{ addslashes($file['path']) }}'" type="text" x-model="renameInput" @keydown.enter.prevent.stop="submitRename" @keydown.escape.prevent.stop="renamingItemPath = null" @click.stop class="w-full bg-black/50 border border-gray-600 rounded px-2 py-1 text-white outline-none">
                                                    </template>
                                                    <template x-if="renamingItemPath !== '{{ addslashes($file['path']) }}'">
                                                        <button wire:click="openFilePreview('{{ addslashes($file['path']) }}')" class="text-gray-400 hover:text-white hover:underline">{{ $file['name'] }}</button>
                                                    </template>
                                                @else
                                                    @if($previewType === 'image') <x-heroicon-o-photo class="w-6 h-6 text-gray-400" />
                                                    @elseif($previewType === 'video') <x-heroicon-o-film class="w-6 h-6 text-gray-400" />
                                                    @elseif($previewType === 'audio') <x-heroicon-o-musical-note class="w-6 h-6 text-gray-400" />
                                                    @else <x-heroicon-o-document class="w-6 h-6 text-gray-400" /> @endif
                                                    
                                                    <template x-if="renamingItemPath === '{{ addslashes($file['path']) }}'">
                                                        <input :id="'renameInput_{{ addslashes($file['path']) }}'" type="text" x-model="renameInput" @keydown.enter.prevent.stop="submitRename" @keydown.escape.prevent.stop="renamingItemPath = null" @click.stop class="w-full bg-black/50 border border-gray-600 rounded px-2 py-1 text-white outline-none">
                                                    </template>
                                                    <template x-if="renamingItemPath !== '{{ addslashes($file['path']) }}'">
                                                        <button @click="openPreview('{{ $file['url'] }}', '{{ $previewType }}', '{{ addslashes($file['name']) }}')" class="text-gray-400 hover:text-white hover:underline">{{ $file['name'] }}</button>
                                                    </template>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="py-3 text-gray-500">{{ $file['type'] === 'folder' ? '-' : round($file['size'] / 1024, 1) . ' KB' }}</td>
                                        <td class="py-3 text-gray-500">{{ $file['mimeType'] }}</td>
                                        <td class="py-3 text-gray-500">{{ \Carbon\Carbon::createFromTimestamp($file['lastModified'])->diffForHumans() }}</td>
                                        <td class="py-3 text-right pr-2">
                                            <button type="button" @click="openContextMenu($event, '{{ addslashes($file['path']) }}', '{{ addslashes($file['name']) }}')" class="text-gray-400 hover:text-white transition-colors p-1">
                                                <x-heroicon-o-ellipsis-horizontal class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4 pt-10">
                        <x-heroicon-o-folder-open class="w-20 h-20 opacity-20" />
                        <p>Dieser Ordner ist leer.</p>
                    </div>
                @endif
            </div>

            <!-- MOVE MODAL -->
            <template x-teleport="body">
                <div x-show="moveModalOpen" x-cloak class="fixed inset-0 z-[10000] bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm">
                    <div @click.outside="moveModalOpen = false" class="bg-gray-900 border border-gray-700 rounded-xl shadow-2xl p-6 w-full max-w-md">
                        <h3 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                            <x-heroicon-o-arrows-right-left class="w-6 h-6 text-[var(--theme-color)]" />
                            Element verschieben
                        </h3>
                        <p class="text-sm text-gray-400 mb-4 break-all">Wohin soll <span class="text-white font-bold" x-text="contextMenuName"></span> verschoben werden?</p>
                        
                        <div class="mb-6">
                            <select x-model="moveModalTarget" class="w-full bg-black/50 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-[var(--theme-color)] outline-none custom-scrollbar">
                                <option value="">-- Zielordner auswählen --</option>
                                @foreach($this->getAllWorkspaceDirectories() as $dir)
                                    <option value="{{ addslashes($dir) }}">{{ $dir }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex justify-end gap-3 mt-4">
                            <button type="button" @click="moveModalOpen = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-sm font-bold transition-colors">
                                Abbrechen
                            </button>
                            <button type="button" 
                                    @click="if(moveModalTarget) { $wire.moveFileManagerItem(contextMenuPath, moveModalTarget); moveModalOpen = false; }"
                                    :disabled="!moveModalTarget"
                                    :class="moveModalTarget ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-emerald-900/50 text-emerald-700/50 cursor-not-allowed'"
                                    class="px-4 py-2 rounded-lg text-sm font-bold transition-colors shadow-lg">
                                Verschieben
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
