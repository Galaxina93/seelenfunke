        <!-- FILES TAB CONTENT -->
        <div wire:key="tab-files" x-data="{ zoomImage: null }" :class="{'hidden': activeTab !== 'files'}" class="flex-1 shrink-0 rounded-2xl border border-gray-800 bg-gray-900/80 backdrop-blur-xl flex flex-col overflow-hidden relative shadow-[0_0_30px_rgba(0,0,0,0.5)] h-full w-full p-6">
            @php
                $computedFiles = $this->globalFiles;
            @endphp
            
            <!-- LIGHTBOX OVERLAY -->
            <div x-show="zoomImage" x-cloak class="fixed inset-0 z-[200] bg-black/90 flex items-center justify-center p-4 backdrop-blur-sm shadow-2xl">
                <div @click.outside="zoomImage = null" class="relative max-w-5xl w-full max-h-full flex justify-center shadow-[0_0_50px_black] rounded-xl">
                    <button @click="zoomImage = null" class="absolute -top-12 right-0 text-white hover:text-red-500 transition-colors drop-shadow-md">
                        <x-heroicon-o-x-mark class="w-10 h-10"/>
                    </button>
                    <img :src="zoomImage" class="max-w-full max-h-[85vh] object-contain rounded-lg border border-gray-800 shadow-[0_0_30px_rgba(255,255,255,0.05)]">
                </div>
            </div>

            @if(count($computedFiles) > 0)
                <div wire:key="files-grid" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4">
                    @foreach($computedFiles as $file)
                        @php
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            if (str_ends_with(strtolower($file['name']), '.blade.php')) $ext = 'blade';
                            
                            $props = match($ext) {
                                'php' => ['icon' => 'heroicon-o-code-bracket-square', 'class' => 'text-indigo-400 border-indigo-900/50 bg-indigo-950/30'],
                                'blade' => ['icon' => 'heroicon-o-rectangle-group', 'class' => 'text-orange-400 border-orange-900/50 bg-orange-950/30'],
                                'js', 'ts', 'vue', 'json' => ['icon' => 'heroicon-o-command-line', 'class' => 'text-yellow-400 border-yellow-900/50 bg-yellow-950/30'],
                                'css', 'scss', 'html' => ['icon' => 'heroicon-o-globe-alt', 'class' => 'text-sky-400 border-sky-900/50 bg-sky-950/30'],
                                'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp' => ['icon' => 'heroicon-o-photo', 'class' => 'text-fuchsia-400 border-fuchsia-900/50 bg-fuchsia-950/30'],
                                'pdf', 'csv', 'xlsx' => ['icon' => 'heroicon-o-table-cells', 'class' => 'text-emerald-400 border-emerald-900/50 bg-emerald-950/30'],
                                default => ['icon' => 'heroicon-o-document-text', 'class' => 'text-gray-400 border-gray-700 bg-gray-900']
                            };
                        @endphp
                        <div wire:key="global-file-{{ md5($file['path'] ?? $file['name']) }}" class="border rounded-xl p-4 flex flex-col items-center justify-center text-center gap-3 transition-all hover:scale-105 hover:bg-opacity-80 shadow-md {{ $props['class'] }} relative overflow-hidden group">
                            <!-- Type Badge -->
                            <div class="absolute top-2 right-2 text-[8px] uppercase font-bold px-1.5 py-0.5 rounded opacity-50 bg-black/40 {{ $file['type'] === 'project_file' ? 'text-cyan-400' : 'text-emerald-400' }}">
                                {{ $file['type'] === 'project_file' ? 'Project' : 'Upload' }}
                            </div>
                            
                            @if(in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) && $file['type'] === 'local_upload')
                                <!-- Real Image Preview if Local Upload -->
                                <button type="button" @click="zoomImage = '{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}'" class="cursor-pointer">
                                    <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-fuchsia-500/50 shadow-lg group-hover:border-fuchsia-400 group-hover:scale-110 group-hover:rotate-3 transition-transform relative">
                                        @if(isset($file['is_pending']))
                                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center backdrop-blur-sm z-10">
                                                <span class="text-[8px] font-bold text-white uppercase tracking-widest animate-pulse">Syncing...</span>
                                            </div>
                                        @endif
                                        <img src="{{ !empty($file['temporary_url']) ? $file['temporary_url'] : Storage::url($file['path']) }}" class="w-full h-full object-cover">
                                    </div>
                                </button>
                            @else
                                <div class="w-16 h-16 rounded-full bg-black/40 border border-current shadow-inner flex justify-center items-center">
                                    @svg($props['icon'], 'w-8 h-8 opacity-80')
                                </div>
                            @endif
                            <div class="font-mono text-xs font-bold leading-tight break-all line-clamp-2 w-full px-1" title="{{ basename($file['name']) }}">{{ basename($file['name']) }}</div>
                            <div class="text-[9px] opacity-70 mb-1 w-full truncate px-1" title="{{ dirname($file['path']) }}">{{ dirname($file['path']) === '.' ? 'Root' : dirname($file['path']) }}</div>
                            
                            <!-- Delete File Button -->
                            <div class="absolute top-2 left-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if(isset($file['is_pending']) && !empty($file['livewire_filename']))
                                    <button type="button" wire:click="$removeUpload('uploadedFiles', '{{ $file['livewire_filename'] }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Upload abbrechen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @else
                                    <button type="button" wire:click="removeGlobalFile('{{ $file['type'] }}', '{{ addslashes($file['path']) }}')" class="bg-red-500 text-white rounded p-1 shadow-md hover:bg-red-600 hover:scale-110 transition-all" title="Aus Projekt entfernen">
                                        <x-heroicon-s-trash class="w-3.5 h-3.5" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div wire:key="files-empty" class="h-full flex flex-col items-center justify-center text-gray-500 font-mono space-y-4">
                    <x-heroicon-o-folder-open class="w-20 h-20 opacity-20" />
                    <p>Noch keine Dateien in diese KI-Session eingeladen.</p>
                </div>
            @endif
        </div>
