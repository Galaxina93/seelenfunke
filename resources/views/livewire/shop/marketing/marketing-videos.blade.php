<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-60: {{ $this->themeColorHex }}99; --theme-color-80: {{ $this->themeColorHex }}CC;"
     x-data="videoEditor()"
     @config-saved.window="showNotification($event.detail.message, 'success')"
     @video-saved.window="showNotification('Video erfolgreich hochgeladen und in der Galerie gespeichert!', 'success')"
     class="text-gray-200 bg-[#0B0B0E] min-h-screen flex flex-col font-sans select-none antialiased">
 
    <!-- Hidden file input for Livewire uploads integration -->
    <input type="file" id="hidden-video-file-input" wire:model="videoFile" class="hidden">
 
    <!-- Unified Toast Notification System -->
    <div class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 w-80">
        <template x-for="n in notifications" :key="n.id">
            <div class="p-4 rounded-xl border bg-gray-950/95 backdrop-blur-md shadow-2xl flex items-center gap-3 transition-all duration-300 transform translate-y-0"
                 :class="n.type === 'success' ? 'border-emerald-500/30 text-emerald-400 shadow-emerald-500/5' : 'border-red-500/30 text-red-400 shadow-red-500/5'">
                <div class="p-1 rounded-lg bg-emerald-500/10" x-show="n.type === 'success'">
                    <x-heroicon-m-check-circle class="w-5 h-5 text-emerald-500" />
                </div>
                <div class="p-1 rounded-lg bg-red-500/10" x-show="n.type !== 'success'">
                    <x-heroicon-m-x-circle class="w-5 h-5 text-red-500" />
                </div>
                <span class="text-xs font-black font-mono tracking-wide" x-text="n.text"></span>
            </div>
        </template>
    </div>
 
    <!-- Dashboard Vorseite -->
    <div x-show="!activeVideoId" x-cloak class="flex-1 flex flex-col max-w-7xl mx-auto w-full p-8 space-y-8">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-[#212126] pb-6">
                <div class="flex items-center gap-4">
                    <div class="p-2.5 rounded-2xl bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)]">
                        <x-heroicon-o-video-camera class="w-8 h-8" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-black uppercase tracking-widest text-[var(--theme-color)] drop-shadow-[0_0_12px_var(--theme-color-30)] font-mono">
                            SEELENFUNKE VIDEO STUDIO
                        </h1>
                        <p class="text-xs text-gray-500 uppercase tracking-widest mt-1">
                            Erstellen und verwalten Sie hochwertige Videos für Ihr Marketing
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color-60)] animate-pulse"></span>
                    <span class="text-xs uppercase tracking-widest font-black font-mono text-gray-400">System Aktiv</span>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Left: Create Project Form (4 cols) -->
                <div class="lg:col-span-4 bg-[#141418] border border-[#212126] rounded-2xl p-6 shadow-2xl space-y-6">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-300">Neues Video-Projekt</h3>
                        <p class="text-[10px] text-gray-500 uppercase mt-0.5">Erstellen Sie ein neues Animations-Projekt</p>
                    </div>
                    
                    <form wire:submit.prevent="createDraftVideo" class="space-y-4">
                        <!-- Project Title -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Projektname / Titel</label>
                            <input type="text" wire:model="newTitle" placeholder="z.B. mein-seelenfunke" 
                                   class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors @error('newTitle') border-red-500/50 @enderror">
                            @error('newTitle')
                                <span class="text-[9px] text-red-500 font-bold uppercase tracking-wider mt-0.5">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Project Slogan -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Slogan / Untertitel</label>
                            <input type="text" wire:model="newSubtitle" placeholder="z.B. EIN FUNKE, DER BLEIBT" 
                                   class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                        </div>

                        <!-- Design-Stil Select -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Design-Stil</label>
                            <select wire:model="newDesignMode" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                                <option value="seelenfunke">Seelenfunke Design (Branded Gold)</option>
                                <option value="standard">Standard Design (Generic / Clean)</option>
                            </select>
                        </div>

                        <!-- Video-Format Select -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Video-Format / Plattform</label>
                            <select wire:model="newAspectRatio" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                                <option value="16:9">YouTube &amp; Website (16:9 Querformat)</option>
                                <option value="9:16">TikTok &amp; Reels (9:16 Hochformat)</option>
                                <option value="1:1">Instagram (1:1 Quadrat)</option>
                                <option value="4:5">Instagram Portrait (4:5 Hochformat)</option>
                                <option value="2:3">Pinterest Pin (2:3 Hochformat)</option>
                            </select>
                        </div>

                        <!-- Video Duration Select -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Video-Dauer</label>
                            <select wire:model.live="newDuration" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                                <option value="6">6 Sekunden</option>
                                <option value="10">10 Sekunden</option>
                                <option value="20">20 Sekunden</option>
                                <option value="30">30 Sekunden</option>
                                <option value="60">1 Minute</option>
                                <option value="120">2 Minuten</option>
                                <option value="180">3 Minuten</option>
                                <option value="custom">Benutzerdefinierte Dauer...</option>
                            </select>
                        </div>

                        @if($newDuration === 'custom')
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Eigene Dauer (Sekunden)</label>
                                <input type="number" min="1" max="600" wire:model="newDurationCustom" placeholder="z.B. 45" 
                                       class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-3 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                            </div>
                        @endif



                        <!-- Submit Button with Master Style -->
                        <button type="submit" 
                                class="w-full py-4 rounded-xl bg-[var(--theme-color)] text-gray-900 font-black text-[10px] uppercase tracking-[0.2em] hover:bg-white hover:scale-[1.02] active:scale-95 transition-all shadow-[0_0_20px_var(--theme-color-30)] text-center flex items-center justify-center gap-2">
                            <x-heroicon-o-video-camera class="w-4 h-4" />
                            PROJEKT ERSTELLEN
                        </button>
                    </form>
                </div>

                <!-- Right: Project List (8 cols) -->
                <div class="lg:col-span-8 space-y-4">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-widest text-gray-300">Produzierte Videos &amp; Entwürfe</h3>
                        <p class="text-[10px] text-gray-500 uppercase mt-0.5">Verwalten Sie Ihre bestehenden Video-Projekte</p>
                    </div>

                    @if($videos->isEmpty())
                        <div class="py-16 text-center border border-dashed border-[#2B2B33] rounded-2xl bg-[#141418]/30">
                            <x-heroicon-o-video-camera class="w-12 h-12 text-gray-700 mx-auto mb-4" />
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Keine Video-Projekte vorhanden</span>
                            <p class="text-[9px] text-gray-600 uppercase mt-1">Erstellen Sie links Ihr erstes Projekt.</p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-data="{ editingId: null, tempTitle: '' }">
                            @foreach($videos as $video)
                                <div wire:key="video-card-{{ $video->id }}" class="bg-[#141418] border border-[#212126] hover:border-[#2D2D37] rounded-2xl overflow-hidden shadow-2xl flex flex-col transition-all relative group">
                                    
                                    <!-- Video Player / Placeholder -->
                                    <div class="aspect-video w-full bg-black relative border-b border-[#212126]">
                                        @if($video->status === 'completed' && $video->video_path)
                                            <video src="{{ route('admin.marketing-videos.file', ['id' => $video->id]) }}" controls class="w-full h-full object-contain"></video>
                                        @else
                                            <div class="w-full h-full flex flex-col items-center justify-center text-gray-600 bg-gray-950/40 gap-3">
                                                <div class="p-3 rounded-full bg-[var(--theme-color-10)] text-[var(--theme-color)]">
                                                    <x-heroicon-o-clock class="w-6 h-6 animate-pulse" />
                                                </div>
                                                <span class="text-[9px] uppercase font-black tracking-widest text-gray-400 font-mono">Entwurf / Nicht gerendert</span>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Project details -->
                                    <div class="p-5 flex-grow flex flex-col justify-between gap-4">
                                        <div class="space-y-1">
                                            <!-- Rename UI -->
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex-grow min-w-0" @dblclick="editingId = '{{ $video->id }}'; tempTitle = '{{ $video->title }}'">
                                                    <template x-if="editingId === '{{ $video->id }}'">
                                                        <input type="text" x-model="tempTitle" 
                                                               @blur="if(tempTitle.trim() !== '') { @this.renameProject('{{ $video->id }}', tempTitle); } editingId = null" 
                                                               @keydown.enter="if(tempTitle.trim() !== '') { @this.renameProject('{{ $video->id }}', tempTitle); } editingId = null"
                                                               @keydown.escape="editingId = null"
                                                               class="bg-[#1C1C22] border border-[var(--theme-color)] rounded-lg px-2.5 py-1 text-xs font-bold text-white outline-none w-full"
                                                               x-init="$nextTick(() => $el.focus())">
                                                    </template>
                                                    <template x-if="editingId !== '{{ $video->id }}'">
                                                        <div class="flex items-center gap-1.5 cursor-pointer" title="Doppelklick zum Umbenennen">
                                                            <h4 class="font-black text-gray-300 text-xs truncate uppercase tracking-wide" x-text="'{{ $video->title }}'"></h4>
                                                            <button @click="editingId = '{{ $video->id }}'; tempTitle = '{{ $video->title }}'" class="text-gray-600 hover:text-white transition-colors">
                                                                <x-heroicon-m-pencil-square class="w-3.5 h-3.5" />
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                                <span class="text-[8px] font-black uppercase font-mono px-2 py-0.5 rounded {{ $video->status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                                    {{ $video->status === 'completed' ? 'Fertig' : 'Entwurf' }}
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-gray-500 italic truncate">{{ $video->subtitle ?? 'Kein Slogan' }}</p>
                                        </div>

                                        <!-- Action Buttons with Master Style -->
                                        <div class="flex gap-2 pt-3 border-t border-[#1F1F26]">
                                            <button wire:click="loadVideoTemplate('{{ $video->id }}')" 
                                                    class="flex-grow inline-flex justify-center items-center px-4 py-2.5 border border-[var(--theme-color-50)] text-[9px] uppercase tracking-widest font-black rounded-lg text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white transition-all shadow-[0_0_10px_var(--theme-color-10)]">
                                                Öffnen &amp; Editieren
                                            </button>
                                            
                                            @if($video->status === 'completed')
                                                <a href="{{ route('admin.marketing-videos.file', ['id' => $video->id]) }}" 
                                                   download="{{ Str::slug($video->title) }}_production.webm" 
                                                   class="inline-flex justify-center items-center px-3 py-2.5 border border-[#2B2B33] text-[9px] uppercase tracking-widest font-black rounded-lg text-gray-300 bg-[#1C1C22] hover:bg-[#25252D] transition-all">
                                                    <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                                </a>
                                            @endif
                                            
                                            <button wire:click="deleteVideo('{{ $video->id }}')" 
                                                    wire:confirm="Dieses Video-Projekt wirklich archivieren?"
                                                    class="inline-flex justify-center items-center px-3 py-2.5 border border-amber-500/20 text-[9px] uppercase tracking-widest font-black rounded-lg text-amber-500 bg-amber-500/5 hover:bg-amber-500 hover:text-white transition-all"
                                                    title="Archivieren">
                                                <x-heroicon-o-archive-box class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    
                    <!-- Video Archiv Section (Bottom Expandable Accordion) -->
                    <div class="pt-6 border-t border-[#212126] mt-6" x-data="{ showArchive: false }">
                        <button @click="showArchive = !showArchive" 
                                class="w-full flex items-center justify-between py-3 px-4 bg-[#141418] hover:bg-[#1C1C22] border border-[#212126] rounded-xl text-left transition-colors select-none">
                            <div class="flex items-center gap-2.5">
                                <x-heroicon-o-archive-box class="w-4 h-4 text-amber-500" />
                                <span class="text-xs font-black uppercase tracking-widest text-gray-300">Video-Archiv</span>
                                <span class="text-[9px] font-mono bg-amber-500/10 text-amber-400 px-2 py-0.5 rounded border border-amber-500/20" x-text="'{{ $archivedVideos->count() }} Entwürfe'"></span>
                            </div>
                            <div class="w-4 h-4 text-gray-500 transition-transform duration-200" :class="showArchive ? 'rotate-180' : ''">
                                <x-heroicon-m-chevron-down class="w-full h-full" />
                            </div>
                        </button>
                        
                        <div x-show="showArchive" x-cloak class="mt-6 space-y-4">
                            @if($archivedVideos->isEmpty())
                                <div class="py-10 text-center border border-dashed border-[#2B2B33] rounded-2xl bg-[#141418]/10">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-gray-600">Das Archiv ist leer</span>
                                </div>
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    @foreach($archivedVideos as $archived)
                                        <div wire:key="archived-card-{{ $archived->id }}" class="bg-[#101014] border border-[#1C1C22] rounded-xl overflow-hidden p-4 flex flex-col justify-between gap-4">
                                            <div class="space-y-1">
                                                <h4 class="font-black text-gray-400 text-xs truncate uppercase tracking-wide">{{ $archived->title }}</h4>
                                                <p class="text-[9px] text-gray-600 truncate">{{ $archived->subtitle ?? 'Kein Slogan' }}</p>
                                                <span class="inline-block text-[8px] font-mono text-gray-500">Archiviert: {{ $archived->updated_at->format('d.m.Y H:i') }}</span>
                                            </div>
                                            <div class="flex gap-2 pt-2 border-t border-[#1C1C22]">
                                                <button wire:click="restoreVideo('{{ $archived->id }}')" 
                                                        class="flex-grow inline-flex justify-center items-center px-3 py-1.5 border border-emerald-500/30 text-[9px] uppercase tracking-widest font-black rounded-lg text-emerald-400 bg-emerald-500/5 hover:bg-emerald-500 hover:text-white transition-all">
                                                    Wiederherstellen
                                                </button>
                                                <button wire:click="forceDeleteVideo('{{ $archived->id }}')" 
                                                        wire:confirm="Dieses Video-Projekt unwiderruflich und endgültig aus der Datenbank löschen?"
                                                        class="inline-flex justify-center items-center px-3 py-1.5 border border-red-500/20 text-[9px] uppercase tracking-widest font-black rounded-lg text-red-500 bg-red-500/5 hover:bg-red-500 hover:text-white transition-all"
                                                        title="Endgültig löschen">
                                                    <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

        </div>
        <div x-show="activeVideoId" wire:ignore class="flex-1 flex flex-col overflow-hidden" x-cloak>
            <!-- TOP TOOLBAR: Minimalistic dark bar containing menu options and quick saving / rendering -->
            <header class="h-14 bg-[#141418] border-b border-[#212126] px-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-6">
                <button wire:click="closeProject" class="px-3 py-1.5 bg-[#1C1C22] hover:bg-[#25252D] border border-[#2B2B33] rounded-lg text-[10px] uppercase tracking-wider font-black text-gray-400 hover:text-white transition-all flex items-center gap-1.5">
                    <x-heroicon-s-arrow-left class="w-3.5 h-3.5" />
                    Dashboard
                </button>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-[var(--theme-color)] shadow-[0_0_8px_var(--theme-color-60)]"></span>
                    <span class="text-xs uppercase tracking-widest font-black font-mono text-gray-400">Production Studio</span>
                </div>
                <!-- Dynamic project title editor in toolbar -->
                <div class="flex items-center gap-2 border-l border-[#212126] pl-6 ml-2">
                    <span class="text-[9px] uppercase font-mono text-gray-500">Projekt:</span>
                    <input type="text" x-model="projectTitle" @change="@this.renameProject(activeVideoId, projectTitle)" class="bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2.5 py-1 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors w-40">
                </div>
                <!-- Duration picker inside toolbar -->
                <div class="flex items-center gap-2 border-l border-[#212126] pl-6 ml-2 select-none" x-data="{ customDur: false }">
                    <span class="text-[9px] uppercase font-mono text-gray-500">Video-Dauer:</span>
                    <select x-model.number="totalDuration" @change="onDurationChanged(); if(totalDuration === 0) { customDur = true; } else { customDur = false; }" class="bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2.5 py-1 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors w-24">
                        <option value="6">6 Sek.</option>
                        <option value="10">10 Sek.</option>
                        <option value="20">20 Sek.</option>
                        <option value="30">30 Sek.</option>
                        <option value="60">1 Min.</option>
                        <option value="120">2 Min.</option>
                        <option value="180">3 Min.</option>
                        <option value="0">Eigene...</option>
                    </select>
                    <template x-if="customDur || ![6,10,20,30,60,120,180].includes(totalDuration)">
                        <div class="flex items-center gap-1">
                            <input type="number" min="1" max="600" x-model.number="totalDuration" @input="onDurationChanged()" class="bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors w-16" title="Dauer in Sekunden">
                            <span class="text-[9px] font-mono text-gray-500">Sek.</span>
                        </div>
                    </template>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="saveCurrentConfig()" class="inline-flex items-center px-4 py-2 border border-[var(--theme-color-40)] text-[10px] uppercase tracking-widest font-black rounded-lg text-[var(--theme-color)] bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] hover:text-white transition-all shadow-[0_0_10px_var(--theme-color-10)]">
                    <x-heroicon-o-document-duplicate class="w-3.5 h-3.5 mr-1.5" />
                    Speichern
                </button>
                <button id="btn-render-video" @click="openExportModal()" class="inline-flex items-center px-4 py-2 border border-[var(--theme-color-80)] text-[10px] uppercase tracking-widest font-black rounded-lg text-white bg-[var(--theme-color)] hover:opacity-95 transition-all shadow-[0_0_15px_var(--theme-color-30)]">
                    <x-heroicon-o-video-camera class="w-3.5 h-3.5 mr-1.5" />
                    Rendern &amp; Exportieren
                </button>
            </div>
        </header>

        <!-- WORKSPACE PANEL: Re-designed CapCut style layout divided into left library, center player monitor, right properties, and bottom timeline -->
        <main class="flex-grow grid grid-cols-12 overflow-hidden bg-[#0F0F12]">
            
            <!-- COLUMN 1: Left Library / Asset Adder (3 / 12 cols) -->
            <aside class="col-span-3 border-r border-[#1B1B22] bg-[#141418] flex flex-col overflow-hidden">
                <!-- Sidebar Header Tabs -->
                <div class="h-11 bg-[#1A1A22] border-b border-[#21212B] flex items-center px-4">
                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 font-mono">Mediathek</span>
                </div>
                
                <div class="p-4 space-y-5 overflow-y-auto flex-grow">
                    <!-- Add Layer Group -->
                    <div class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Elemente hinzufügen</span>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="addLayer('text')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-document-text class="w-4 h-4 text-[var(--theme-color)]" />
                                Text
                            </button>
                            <button @click="addLayer('image')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-photo class="w-4 h-4 text-[var(--theme-color)]" />
                                Bild / Logo
                            </button>
                            <button @click="addLayer('particles')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-sparkles class="w-4 h-4 text-[var(--theme-color)]" />
                                Partikel
                            </button>
                            <button @click="addLayer('shape')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-square-3-stack-3d class="w-4 h-4 text-[var(--theme-color)]" />
                                Formen
                            </button>
                            <button @click="addLayer('avatar')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-user class="w-4 h-4 text-[var(--theme-color)]" />
                                AI Avatar
                            </button>
                            <button @click="addLayer('subtitles')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex flex-col items-center gap-1">
                                <x-heroicon-o-chat-bubble-bottom-center-text class="w-4 h-4 text-[var(--theme-color)]" />
                                Untertitel
                            </button>
                            <button @click="addLayer('audio')" class="py-2.5 px-2 border border-[#2B2B33] bg-[#1C1C22] hover:bg-[var(--theme-color-10)] hover:border-[var(--theme-color-30)] text-[10px] uppercase tracking-wider font-black rounded-xl text-gray-400 hover:text-white transition-all flex items-center justify-center gap-2 col-span-2">
                                <x-heroicon-o-speaker-wave class="w-4 h-4 text-[var(--theme-color)]" />
                                Audio / Voiceover
                            </button>
                        </div>
                    </div>
      
                    <!-- Layer List Group -->
                    <div class="space-y-2">
                        <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Ebenen-Reihenfolge</span>
                        <div class="space-y-1.5 max-h-72 overflow-y-auto pr-1">
                            <template x-for="(layer, index) in layers.filter(l => l.id !== 'metadata').slice().reverse()" :key="layer.id">
                                <div class="p-2.5 border rounded-lg flex items-center justify-between gap-2 transition-all cursor-pointer bg-[#1C1C22]"
                                     :class="selectedLayerId === layer.id ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-[0_0_8px_var(--theme-color-10)]' : 'border-transparent hover:border-[#2D2D37]'"
                                     @click="selectedLayerId = layer.id">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="shrink-0 text-[var(--theme-color)]">
                                            <template x-if="layer.type === 'background'"><x-heroicon-s-square-2-stack class="w-3.5 h-3.5" /></template>
                                            <template x-if="layer.type === 'text'"><x-heroicon-s-document-text class="w-3.5 h-3.5" /></template>
                                            <template x-if="layer.type === 'image'"><x-heroicon-s-photo class="w-3.5 h-3.5" /></template>
                                            <template x-if="layer.type === 'particles'"><x-heroicon-s-sparkles class="w-3.5 h-3.5" /></template>
                                            <template x-if="layer.type === 'shape'"><x-heroicon-s-square-3-stack-3d class="w-3.5 h-3.5" /></template>
                                        </div>
                                        <span class="text-[11px] font-bold text-gray-300 truncate" x-text="layer.name"></span>
                                    </div>
                                    <div class="flex items-center gap-0.5 shrink-0">
                                        <button @click.stop="moveLayerUp(layer.id)" class="p-1 hover:text-white text-gray-600 transition-colors">
                                            <x-heroicon-s-chevron-up class="w-3 h-3" />
                                        </button>
                                        <button @click.stop="moveLayerDown(layer.id)" class="p-1 hover:text-white text-gray-600 transition-colors">
                                            <x-heroicon-s-chevron-down class="w-3 h-3" />
                                        </button>
                                        <button @click.stop="deleteLayer(layer.id)" class="p-1 hover:text-red-500 text-gray-600 transition-colors" :disabled="layer.type === 'background'">
                                            <x-heroicon-s-trash class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </aside>
      
            <!-- COLUMN 2: Center Player Monitor & View Stage (6 / 12 cols) -->
            <section class="col-span-6 flex flex-col overflow-hidden bg-[#0A0A0C]">
                <div class="h-11 bg-[#141418] border-b border-[#212126] flex items-center justify-between px-4 shrink-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 font-mono">Monitor</span>
                    <div class="flex items-center gap-2">
                        <span class="text-[8px] uppercase font-mono text-gray-500">Format:</span>
                        <select :value="aspectRatio" @change="changeAspectRatio($event.target.value)" class="bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2.5 py-1 text-[10px] font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors font-mono">
                            <option value="16:9">YouTube / Web (16:9)</option>
                            <option value="9:16">TikTok / Reels / Shorts (9:16)</option>
                            <option value="1:1">Instagram / LinkedIn (1:1)</option>
                            <option value="4:5">Instagram Portrait (4:5)</option>
                            <option value="2:3">Pinterest Pin (2:3)</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex-grow flex flex-col items-center justify-center p-6 relative">
                    <!-- Inner Rendering progress overlay -->
                    <div id="render-overlay" class="absolute inset-0 bg-[#0B0B0E]/95 z-50 flex flex-col items-center justify-center gap-4 text-[var(--theme-color)] hidden">
                        <svg class="animate-spin h-10 w-10 text-[var(--theme-color)]" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-[10px] font-black uppercase tracking-widest animate-pulse font-mono" id="render-status-text">Video wird gerendert...</span>
                        <div class="w-48 h-1 bg-[#1A1A22] border border-[#2B2B33] rounded-full overflow-hidden">
                            <div class="h-full bg-[var(--theme-color)] transition-all duration-300" id="render-progress-fill" style="width: 0%"></div>
                        </div>
                    </div>
      
                    <!-- Player Canvas monitor -->
                    <div id="canvas-monitor-container" class="w-full max-w-[700px] border-[6px] border-[#1C1C22] bg-[#FAF9F6] rounded-xl shadow-2xl overflow-hidden relative flex items-center justify-center" style="aspect-ratio: 16/9;">
                        <canvas id="logoCanvas" width="960" height="540" class="w-full h-full block"></canvas>
                    </div>
      
                    <!-- Minimalistic Monitor Controller -->
                    <div class="flex items-center justify-between w-full max-w-[700px] mt-4 bg-[#141418] px-4 py-2 border border-[#212126] rounded-xl">
                        <div class="flex items-center gap-2">
                            <button @click="togglePlay()" class="p-2 bg-[#1C1C22] hover:bg-[#25252D] text-gray-300 rounded-lg transition-all">
                                <template x-if="isPlaying">
                                    <x-heroicon-s-pause class="w-3.5 h-3.5 text-[var(--theme-color)]" />
                                </template>
                                <template x-if="!isPlaying">
                                    <x-heroicon-s-play class="w-3.5 h-3.5" />
                                </template>
                            </button>
                            <button @click="stop()" class="p-2 bg-[#1C1C22] hover:bg-[#25252D] text-gray-300 rounded-lg transition-all">
                                <x-heroicon-s-stop class="w-3.5 h-3.5" />
                            </button>
                            <button @click="restart()" class="p-2 bg-[#1C1C22] hover:bg-[#25252D] text-gray-300 rounded-lg transition-all">
                                <x-heroicon-s-arrow-path class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <div class="text-[10px] font-mono text-gray-400 bg-black/60 px-3 py-1 rounded-lg">
                            <span x-text="formatTime(currentFrameTime)"></span> / <span x-text="formatTime(totalDuration)"></span>
                        </div>
                    </div>
                </div>
            </section>
      
            <!-- COLUMN 3: Right Inspector / Properties Panel (3 / 12 cols) -->
            <aside class="col-span-3 border-l border-[#1B1B22] bg-[#141418] flex flex-col overflow-hidden font-mono">
                <div class="h-11 bg-[#1A1A22] border-b border-[#21212B] flex items-center px-4 shrink-0">
                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 font-mono">Eigenschaften</span>
                </div>
                
                <div class="p-4 overflow-y-auto flex-grow">
                    <template x-if="getSelectedLayer()">
                        <div class="space-y-4">
                            <!-- Ebene Name -->
                            <div class="flex flex-col gap-1">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Name</label>
                                <input type="text" x-model="getSelectedLayer().name" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-3 py-2 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors">
                            </div>
      
                            <!-- Start / End Frame settings -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Start (s)</label>
                                    <input type="number" min="0" max="6" step="0.1" x-model.number="getSelectedLayer().startTime" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-3 py-1.5 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors font-mono">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Ende (s)</label>
                                    <input type="number" min="0" max="6" step="0.1" x-model.number="getSelectedLayer().endTime" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-3 py-1.5 text-xs font-bold text-white outline-none focus:border-[var(--theme-color)] transition-colors font-mono">
                                </div>
                            </div>
      
                            <!-- Opacity Slider -->
                            <div class="flex flex-col gap-1">
                                <div class="flex justify-between items-center text-[9px] font-black uppercase tracking-wider text-gray-500">
                                    <span>Deckkraft</span>
                                    <span class="font-mono text-gray-400" x-text="Math.round(getSelectedLayer().opacity * 100) + '%'"></span>
                                </div>
                                <input type="range" min="0" max="1" step="0.05" x-model.number="getSelectedLayer().opacity" @input="updateFrame()" class="w-full h-1 bg-[#1C1C22] rounded-lg appearance-none cursor-pointer accent-[var(--theme-color)]">
                            </div>
      
                            <!-- Background spec fields -->
                            <template x-if="getSelectedLayer().type === 'background'">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between bg-[#1C1C22]/60 p-2.5 border border-[#2B2B33] rounded-lg">
                                        <span class="text-[10px] font-bold text-gray-300 font-sans">Gradient</span>
                                        <input type="checkbox" x-model="getSelectedLayer().useGradient" @change="updateFrame()" class="rounded border-gray-800 bg-[#141418] text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Farbe A</label>
                                        <div class="flex gap-2">
                                            <input type="color" x-model="getSelectedLayer().color" @input="updateFrame()" class="w-7 h-7 rounded bg-gray-900 border border-gray-800 cursor-pointer">
                                            <input type="text" x-model="getSelectedLayer().color" @input="updateFrame()" class="flex-1 bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2.5 py-1 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1" x-show="getSelectedLayer().useGradient">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Farbe B</label>
                                        <div class="flex gap-2">
                                            <input type="color" x-model="getSelectedLayer().gradientColor" @input="updateFrame()" class="w-7 h-7 rounded bg-gray-900 border border-gray-800 cursor-pointer">
                                            <input type="text" x-model="getSelectedLayer().gradientColor" @input="updateFrame()" class="flex-1 bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2.5 py-1 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            </template>
      
                            <!-- Text spec fields -->
                            <template x-if="getSelectedLayer().type === 'text'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Inhalt</label>
                                        <input type="text" x-model="getSelectedLayer().text" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-3 py-2 text-xs text-white font-sans">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Font</label>
                                        <select x-model="getSelectedLayer().fontFamily" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="Playfair Display">Playfair Display</option>
                                            <option value="Outfit">Outfit</option>
                                            <option value="Caveat">Caveat</option>
                                            <option value="Arial">Arial</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Größe</label>
                                            <input type="number" x-model.number="getSelectedLayer().fontSize" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Farbe</label>
                                            <input type="color" x-model="getSelectedLayer().color" @input="updateFrame()" class="w-full h-8 rounded border border-[#2B2B33] p-0.5 cursor-pointer">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X (0-960)</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y (0-540)</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Animation</label>
                                        <select x-model="getSelectedLayer().animation" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="none">Keine</option>
                                            <option value="fade">Fade</option>
                                            <option value="slide-up">Slide Up</option>
                                            <option value="typewriter">Typewriter</option>
                                        </select>
                                    </div>
                                </div>
                            </template>
      
                            <!-- Image spec fields -->
                            <template x-if="getSelectedLayer().type === 'image'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Bild-Vorlage</label>
                                        <select x-model="getSelectedLayer().imageUrl" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white mb-1.5 font-sans">
                                            <option value="shop/projekt/logo/mein-seelenfunke-logo.png">Seelenfunke Logo</option>
                                            <option value="/shop/ai/images/funkira_selfie.png">Funkira Selfie</option>
                                            <option value="custom">Benutzerdefinierte URL...</option>
                                        </select>
                                        <input type="text" x-model="getSelectedLayer().imageUrl" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-3 py-1.5 text-xs font-mono text-white">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Breite (px)</label>
                                            <input type="number" x-model.number="getSelectedLayer().width" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Höhe (px)</label>
                                            <input type="number" x-model.number="getSelectedLayer().height" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Animation</label>
                                        <select x-model="getSelectedLayer().animation" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="none">Keine</option>
                                            <option value="fade">Fade</option>
                                            <option value="scale">Scale</option>
                                        </select>
                                    </div>
                                    <div class="flex items-center justify-between bg-[#1C1C22]/60 p-2 border border-[#2B2B33] rounded-lg">
                                        <span class="text-[10px] font-bold text-gray-300 font-sans">Glanzschweif</span>
                                        <input type="checkbox" x-model="getSelectedLayer().shine" @change="updateFrame()" class="rounded border-gray-800 bg-[#141418] text-[var(--theme-color)] focus:ring-[var(--theme-color)]">
                                    </div>
                                </div>
                            </template>
      
                            <!-- Particles settings -->
                            <template x-if="getSelectedLayer().type === 'particles'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Partikel-Typ</label>
                                        <select x-model="getSelectedLayer().particleType" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="sparks">Seelenfunken</option>
                                            <option value="fireflies">Glühwürmchen</option>
                                            <option value="snow">Schnee</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Farbe</label>
                                        <input type="color" x-model="getSelectedLayer().color" @input="updateFrame()" class="w-full h-8 rounded border border-[#2B2B33] p-0.5 cursor-pointer">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Anzahl</label>
                                        <input type="number" min="5" max="100" x-model.number="getSelectedLayer().count" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Breite (px)</label>
                                            <input type="number" x-model.number="getSelectedLayer().width" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Höhe (px)</label>
                                            <input type="number" x-model.number="getSelectedLayer().height" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            </template>
      
                            <!-- Shapes settings -->
                            <template x-if="getSelectedLayer().type === 'shape'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Form</label>
                                        <select x-model="getSelectedLayer().shapeType" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="rect">Rechteck</option>
                                            <option value="circle">Kreis</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Farbe</label>
                                        <input type="color" x-model="getSelectedLayer().color" @input="updateFrame()" class="w-full h-8 rounded border border-[#2B2B33] p-0.5 cursor-pointer">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Breite / Radius</label>
                                            <input type="number" x-model.number="getSelectedLayer().width" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Höhe</label>
                                            <input type="number" x-model.number="getSelectedLayer().height" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Avatar settings -->
                            <template x-if="getSelectedLayer().type === 'avatar'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Avatar-Bild</label>
                                        <select x-model="getSelectedLayer().avatarUrl" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="/shop/ai/images/funkira_selfie.png">Funkira (Brand Avatar)</option>
                                            <option value="/shop/projekt/logo/mein-seelenfunke-logo.png">Seelenfunke Logo</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Form-Stil</label>
                                        <select x-model="getSelectedLayer().style" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="circle">Kreis (Standard)</option>
                                            <option value="rect">Rechteck</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Sprechtext (TTS Script)</label>
                                        <textarea x-model="getSelectedLayer().scriptText" rows="3" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg p-2 text-xs text-white font-sans outline-none focus:border-[var(--theme-color)]" placeholder="Hier Text eingeben, den der Avatar spricht..."></textarea>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Stimme</label>
                                        <select x-model="getSelectedLayer().voiceId" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="de-DE-Wavenet-D">Google Deutsch (Weiblich)</option>
                                            <option value="de-DE-Wavenet-B">Google Deutsch (Männlich)</option>
                                            <option value="en-US-Standard-C">Google English (Weiblich)</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Breite</label>
                                            <input type="number" x-model.number="getSelectedLayer().width" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Höhe</label>
                                            <input type="number" x-model.number="getSelectedLayer().height" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Subtitles settings -->
                            <template x-if="getSelectedLayer().type === 'subtitles'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Untertitel Text (Komplett)</label>
                                        <textarea x-model="getSelectedLayer().text" rows="3" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg p-2 text-xs text-white font-sans outline-none focus:border-[var(--theme-color)]" placeholder="Worte werden passend zur Abspieldauer angezeigt..."></textarea>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Schriftfarbe</label>
                                        <input type="color" x-model="getSelectedLayer().color" @input="updateFrame()" class="w-full h-8 rounded border border-[#2B2B33] p-0.5 cursor-pointer">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Schriftgröße</label>
                                        <input type="number" min="8" max="72" x-model.number="getSelectedLayer().fontSize" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Schriftart</label>
                                        <select x-model="getSelectedLayer().fontFamily" @change="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="Outfit">Outfit</option>
                                            <option value="Inter">Inter</option>
                                            <option value="Playfair Display">Playfair Display</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">X (Zentrierung)</label>
                                            <input type="number" x-model.number="getSelectedLayer().x" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                        <div class="flex flex-col gap-1">
                                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Y (Vertikale Position)</label>
                                            <input type="number" x-model.number="getSelectedLayer().y" @input="updateFrame()" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-mono">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Audio settings -->
                            <template x-if="getSelectedLayer().type === 'audio'">
                                <div class="space-y-3">
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Sprechtext (TTS Script)</label>
                                        <textarea x-model="getSelectedLayer().scriptText" rows="3" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg p-2 text-xs text-white font-sans outline-none focus:border-[var(--theme-color)]" placeholder="Hier Sprach-Skript eingeben..."></textarea>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Hintergrundmusik Preset</label>
                                        <select x-model="getSelectedLayer().audioUrl" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-lg px-2 py-1.5 text-xs text-white font-sans">
                                            <option value="calm">Ruhige Entspannung</option>
                                            <option value="energetic">Energetischer E-Commerce</option>
                                            <option value="branding">Corporate Branding</option>
                                            <option value="none">Keine Musik (Nur Stimme)</option>
                                        </select>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label class="text-[9px] font-black uppercase tracking-wider text-gray-500">Lautstärke</label>
                                        <input type="range" min="0" max="1" step="0.05" x-model.number="getSelectedLayer().volume" class="w-full h-1 bg-[#222] rounded-lg appearance-none cursor-pointer accent-[var(--theme-color)]">
                                        <span class="text-[9px] text-gray-500 text-right font-mono" x-text="Math.round((getSelectedLayer().volume || 1) * 100) + '%'"></span>
                                    </div>
                                </div>
                            </template>
      
                        </div>
                    </template>
                    <template x-if="!getSelectedLayer()">
                        <div class="flex flex-col items-center justify-center h-48 text-gray-600 uppercase text-[10px] tracking-widest text-center font-sans">
                            Wähle eine Ebene aus, um Parameter anzupassen.
                        </div>
                    </template>
                </div>
            </aside>
      
        </main>
      
        <!-- BOTTOM TIMELINE PANEL: Dedicated CapCut-style horizontal track panel with playhead scrubber -->
        <footer class="h-56 bg-[#141418] border-t border-[#212126] flex flex-col shrink-0 overflow-hidden">
            <!-- Timeline Header containing metadata -->
            <div class="h-10 border-b border-[#212126] px-4 flex items-center justify-between bg-[#1A1A22]">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 font-mono">Timeline &amp; Tracks</span>
                <span class="text-[10px] font-mono text-gray-600 font-mono">6.0s duration</span>
            </div>
            
            <!-- Scrubber Range Track -->
            <div class="px-6 py-2 bg-[#121216] border-b border-[#1C1C22]">
                <input type="range" min="0" max="6" step="0.05" x-model.number="currentFrameTime" @input="scrubTimeline()"
                       class="w-full h-1 bg-[#222] rounded-lg appearance-none cursor-pointer accent-[var(--theme-color)]">
            </div>
            
            <!-- Horizontally stackable layer tracks -->
            <div class="flex-grow overflow-y-auto px-6 py-3 space-y-2.5">
                <template x-for="layer in layers.filter(l => l.id !== 'metadata')" :key="layer.id">
                    <div class="flex items-center gap-4">
                        <div class="w-28 text-[10px] font-bold text-gray-500 truncate" x-text="layer.name"></div>
                        <div class="flex-1 h-7 bg-[#0B0B0E] rounded-lg border border-[#1A1A22] relative overflow-hidden">
                            <!-- Movable track block mapped to layer timings -->
                            <div class="absolute h-full rounded bg-[var(--theme-color-20)] border border-[var(--theme-color-40)] flex items-center px-3 text-[8px] font-mono text-[var(--theme-color)] cursor-move hover:bg-[var(--theme-color-30)] transition-all select-none"
                                 :style="'left: ' + (layer.startTime / totalDuration * 100) + '%; width: ' + ((layer.endTime - layer.startTime) / totalDuration * 100) + '%'"
                                 @mousedown.stop="startTimelineDrag($event, layer, 'move')">
                                <!-- Left edge handle -->
                                <div class="absolute left-0 top-0 w-2 h-full cursor-ew-resize hover:bg-white/20 transition-colors z-20 flex items-center justify-center group"
                                     @mousedown.stop.prevent="startTimelineDrag($event, layer, 'resize-start')">
                                    <div class="w-0.5 h-3 bg-[var(--theme-color-50)] group-hover:bg-white transition-colors"></div>
                                </div>
                                
                                <span class="truncate px-1" x-text="layer.startTime.toFixed(1) + 's - ' + layer.endTime.toFixed(1) + 's'"></span>
                                
                                <!-- Right edge handle -->
                                <div class="absolute right-0 top-0 w-2 h-full cursor-ew-resize hover:bg-white/20 transition-colors z-20 flex items-center justify-center group"
                                     @mousedown.stop.prevent="startTimelineDrag($event, layer, 'resize-end')">
                                    <div class="w-0.5 h-3 bg-[var(--theme-color-50)] group-hover:bg-white transition-colors"></div>
                                </div>
                            </div>
                            <!-- Playhead vertical line -->
                            <div class="absolute h-full w-0.5 bg-red-500 z-10 top-0 pointer-events-none"
                                 :style="'left: ' + (currentFrameTime / totalDuration * 100) + '%'"></div>
                        </div>
                    </div>
                </template>
            </div>
        </footer>
    </div>
    
    <!-- CAPCUT STYLE EXPORT MODAL -->
    <div x-show="showExportModal" 
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/85 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         x-cloak>
        
        <div class="bg-[#18181C] border border-[#2B2B33] rounded-2xl w-full max-w-4xl shadow-2xl flex flex-col overflow-hidden text-gray-200 font-sans">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-[#212126] flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-video-camera class="w-5 h-5 text-[var(--theme-color)]" />
                    <h3 class="text-sm font-black uppercase tracking-widest text-white font-mono" x-text="'Exportieren: ' + projectTitle"></h3>
                </div>
                <button @click="showExportModal = false" class="p-1 hover:bg-[#25252D] rounded-lg text-gray-400 hover:text-white transition-colors">
                    <x-heroicon-m-x-mark class="w-5 h-5" />
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-8 overflow-y-auto max-h-[75vh]">
                <!-- Left Column: Video Preview / Cover -->
                <div class="md:col-span-5 flex flex-col items-center justify-start gap-4">
                    <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono w-full text-left">Cover bearbeiten / Vorschau</span>
                    <div class="w-full bg-black border border-[#2B2B33] rounded-xl overflow-hidden flex items-center justify-center p-2 relative shadow-inner"
                         :style="'aspect-ratio: ' + (aspectRatio === '16:9' ? '16/9' : (aspectRatio === '9:16' ? '9/16' : (aspectRatio === '1:1' ? '1/1' : (aspectRatio === '4:5' ? '4/5' : '2/3')))) + '; max-height: 380px;'">
                        <canvas id="exportPreviewCanvas" class="w-auto h-auto max-w-full max-h-full block rounded"></canvas>
                    </div>
                </div>
                
                <!-- Right Column: Settings Form -->
                <div class="md:col-span-7 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Zeitleiste</span>
                            <span class="text-xs font-bold text-gray-400">Zeitleiste 01</span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">exportieren</span>
                            <span class="text-xs font-bold text-gray-400">Video</span>
                        </div>
                    </div>
                    
                    <div class="border-t border-[#212126] pt-4 space-y-3.5">
                        <!-- Project Name -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Name</label>
                            <input type="text" x-model="exportSettings.name" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-4 py-2.5 text-xs text-white outline-none focus:border-[var(--theme-color)] font-bold transition-colors">
                        </div>
                        
                        <!-- Export Path -->
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Exportieren nach</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="exportSettings.path" class="flex-1 bg-[#1C1C22]/60 border border-[#2B2B33] rounded-xl px-4 py-2.5 text-xs text-gray-300 font-mono outline-none focus:border-[var(--theme-color)]">
                                <button @click="chooseExportDirectory()" class="p-2.5 bg-[#1C1C22] hover:bg-[#25252D] border border-[#2B2B33] hover:border-gray-500 rounded-xl text-gray-300 hover:text-white transition-colors" title="Ordner wählen">
                                    <x-heroicon-o-folder-open class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Bitrate -->
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Bitrate</label>
                                <select x-model="exportSettings.bitrate" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-3 py-2 text-xs font-bold text-white outline-none font-mono">
                                    <option value="Empfohlen">Empfohlen</option>
                                    <option value="Hoch">Hoch</option>
                                    <option value="Niedrig">Niedrig</option>
                                </select>
                            </div>
                            
                            <!-- Codec -->
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Codec</label>
                                <select x-model="exportSettings.codec" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-3 py-2 text-xs font-bold text-white outline-none font-mono">
                                    <option value="H.264">H.264 / AVC</option>
                                    <option value="HEVC">HEVC / H.265</option>
                                    <option value="VP9">VP9 (Open Source)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-3">
                            <!-- Format -->
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Format</label>
                                <select x-model="exportSettings.format" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-3 py-2 text-xs font-bold text-white outline-none font-mono">
                                    <option value="mp4">mp4</option>
                                    <option value="webm">webm</option>
                                    <option value="gif">gif</option>
                                </select>
                            </div>
                            
                            <!-- Bildfrequenz -->
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Bildfrequenz</label>
                                <select x-model="exportSettings.frameRate" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-3 py-2 text-xs font-bold text-white outline-none font-mono">
                                    <option value="24fps">24fps</option>
                                    <option value="30fps">30fps</option>
                                    <option value="60fps">60fps</option>
                                </select>
                            </div>
                            
                            <!-- Auflösung -->
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[9px] font-black uppercase tracking-wider text-gray-500 font-mono">Auflösung</label>
                                <select x-model="exportSettings.resolution" class="w-full bg-[#1C1C22] border border-[#2B2B33] rounded-xl px-3 py-2 text-xs font-bold text-white outline-none font-mono">
                                    <option value="720p">720P (HD)</option>
                                    <option value="1080p">1080P (Full HD)</option>
                                    <option value="4K">4K (Ultra HD)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-between text-[10px] text-gray-500 pt-2 font-mono">
                            <span>Farbraum: Rec. 709 SDR</span>
                            <span>Audio: MP3 Stereo</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-[#121215] border-t border-[#212126] flex items-center justify-between font-mono text-xs">
                <div class="flex items-center gap-1 text-gray-400">
                    <x-heroicon-o-clock class="w-4 h-4 text-gray-500" />
                    <span>Dauer:</span>
                    <span class="font-bold text-gray-200">6.0s</span>
                    <span class="mx-2 text-gray-600">|</span>
                    <x-heroicon-o-circle-stack class="w-4 h-4 text-gray-500" />
                    <span>Größe:</span>
                    <span class="font-bold text-gray-200" x-text="calculateEstimatedSize()"></span>
                </div>
                
                <div class="flex gap-3">
                    <button @click="showExportModal = false" class="px-5 py-2.5 rounded-xl border border-[#2B2B33] bg-[#1C1C22] hover:bg-[#25252D] text-gray-300 font-black text-[10px] uppercase tracking-wider transition-all">
                        Abbrechen
                    </button>
                    <button @click="triggerRenderFromModal()" class="px-5 py-2.5 rounded-xl bg-teal-500 hover:bg-teal-400 text-gray-950 font-black text-[10px] uppercase tracking-[0.1em] transition-all shadow-[0_0_15px_rgba(20,184,166,0.3)]">
                        Exportieren
                    </button>
                </div>
            </div>
        </div>
    </div>
 
 
<!-- JAVASCRIPT ANIMATION timeline engine -->
<script>
function videoEditor() {
    return {
        activeVideoId: @entangle('activeVideoId'),
        projectTitle: '',
        showExportModal: false,
        aspectRatio: '16:9',
        exportSettings: {
            name: '',
            path: 'B:/Gewerbe/Meral/Tik Tok/',
            bitrate: 'Empfohlen',
            codec: 'H.264',
            format: 'mp4',
            frameRate: '30fps',
            resolution: '1080p'
        },
        notifications: [],
        layers: [],
        selectedLayerId: null,
        totalDuration: 6.0, // seconds
        currentFrameTime: 0.0,
        isPlaying: false,
        
        // Canvas & tick properties
        canvas: null,
        ctx: null,
        startTime: 0,
        animationFrameId: null,
        
        // Dragging & Resizing State properties
        isDragging: false,
        isResizing: false,
        dragStart: { x: 0, y: 0 },
        draggedLayer: null,
        resizeDirection: null,
        resizeStartSize: { w: 0, h: 0, x: 0, y: 0, fontSize: 0 },
 
        init() {
            this.layers = @js($this->config);
            if (this.layers.length > 0) {
                this.selectedLayerId = this.layers[0].id;
            }
 
            // Attempt to initialize canvas immediately if it exists
            this.initCanvas();
 
            this.$watch('layers', () => {
                this.updateFrame();
            }, { deep: true });
 
            // Find metadata and set aspect ratio
            const meta = this.layers.find(l => l.id === 'metadata');
            this.aspectRatio = meta ? meta.aspectRatio || '16:9' : '16:9';
            this.updateCanvasDimensions();

            setTimeout(() => this.updateFrame(), 300);
 
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
                    e.preventDefault();
                    this.splitSelectedLayer();
                }
            });
 
            window.addEventListener('video-loaded', (e) => {
                const detail = e.detail[0];
                this.layers = detail.config;
                const meta = this.layers.find(l => l.id === 'metadata');
                this.aspectRatio = meta ? meta.aspectRatio || '16:9' : '16:9';
                this.totalDuration = meta && meta.duration ? parseFloat(meta.duration) : 6.0;
                this.projectTitle = detail.title;
                this.updateCanvasDimensions();
                if (this.layers.length > 0) {
                    this.selectedLayerId = this.layers[0].id;
                }
                this.currentFrameTime = 0.0;
                
                // Initialize canvas references if they are not set yet
                this.initCanvas();
                this.updateFrame();
                this.showNotification(`Vorlage '${detail.title}' geladen!`, 'success');
            });
        },

        initCanvas() {
            if (this.canvas) return true;
            this.canvas = document.getElementById('logoCanvas');
            if (this.canvas) {
                this.ctx = this.canvas.getContext('2d');
                if (!this.canvas._hasListeners) {
                    this.canvas.addEventListener('mousedown', (e) => this.onMouseDown(e));
                    this.canvas.addEventListener('mousemove', (e) => this.onMouseMove(e));
                    window.addEventListener('mouseup', () => this.onMouseUp());
                    this.canvas._hasListeners = true;
                }
                return true;
            }
            return false;
        },
 
        getLayerBounds(layer) {
            if (!this.initCanvas()) return null;
            const w = layer.width || 100;
            const h = layer.height || 100;
            const cx = this.canvas.width / 2;
            const cy = this.canvas.height / 2;
            let x = layer.x !== undefined ? (layer.x - 480) + cx : cx;
            let y = layer.y !== undefined ? (layer.y - 270) + cy : cy;
            
            if (layer.type === 'text') {
                this.ctx.save();
                this.ctx.font = `bold ${layer.fontSize || 30}px "${layer.fontFamily || 'Outfit'}", sans-serif`;
                const textWidth = this.ctx.measureText(layer.text || '').width || 100;
                this.ctx.restore();
                const textHeight = layer.fontSize || 30;
                return {
                    x1: x - textWidth/2 - 10,
                    y1: y - textHeight/2 - 10,
                    x2: x + textWidth/2 + 10,
                    y2: y + textHeight/2 + 10,
                    w: textWidth + 20,
                    h: textHeight + 20
                };
            } else if (layer.type === 'image' || layer.type === 'shape' || layer.type === 'particles' || layer.type === 'avatar' || layer.type === 'subtitles') {
                return {
                    x1: x - w/2,
                    y1: y - h/2,
                    x2: x + w/2,
                    y2: y + h/2,
                    w: w,
                    h: h
                };
            }
            return null;
        },
 
        drawSelectionBorder(layer) {
            if (!this.initCanvas()) return;
            const bounds = this.getLayerBounds(layer);
            if (!bounds) return;
            
            this.ctx.save();
            this.ctx.strokeStyle = '#C5A059';
            this.ctx.lineWidth = 1.5;
            this.ctx.setLineDash([4, 4]);
            this.ctx.strokeRect(bounds.x1, bounds.y1, bounds.w, bounds.h);
            
            // Draw corner handles
            this.ctx.fillStyle = '#C5A059';
            this.ctx.setLineDash([]);
            const handleSize = 8;
            
            this.ctx.fillRect(bounds.x1 - handleSize/2, bounds.y1 - handleSize/2, handleSize, handleSize);
            this.ctx.fillRect(bounds.x2 - handleSize/2, bounds.y1 - handleSize/2, handleSize, handleSize);
            this.ctx.fillRect(bounds.x2 - handleSize/2, bounds.y2 - handleSize/2, handleSize, handleSize);
            this.ctx.fillRect(bounds.x1 - handleSize/2, bounds.y2 - handleSize/2, handleSize, handleSize);
            
            this.ctx.restore();
        },
 
        onMouseDown(e) {
            if (!this.initCanvas()) return;
            const rect = this.canvas.getBoundingClientRect();
            const clickX = (e.clientX - rect.left) * (this.canvas.width / rect.width);
            const clickY = (e.clientY - rect.top) * (this.canvas.height / rect.height);
            
            const cx = this.canvas.width / 2;
            const cy = this.canvas.height / 2;
            const clickStandardX = clickX - cx + 480;
            const clickStandardY = clickY - cy + 270;
            
            const selectedLayer = this.getSelectedLayer();
            if (selectedLayer) {
                const bounds = this.getLayerBounds(selectedLayer);
                if (bounds) {
                    const hs = 8;
                    const corners = {
                        nw: { x: bounds.x1, y: bounds.y1 },
                        ne: { x: bounds.x2, y: bounds.y1 },
                        se: { x: bounds.x2, y: bounds.y2 },
                        sw: { x: bounds.x1, y: bounds.y2 }
                    };
                    
                    for (let dir in corners) {
                        const c = corners[dir];
                        if (Math.abs(clickX - c.x) <= hs && Math.abs(clickY - c.y) <= hs) {
                            this.isResizing = true;
                            this.resizeDirection = dir;
                            this.resizeStartSize = {
                                w: selectedLayer.width || 100,
                                h: selectedLayer.height || 100,
                                x: selectedLayer.x || 480,
                                y: selectedLayer.y || 270,
                                fontSize: selectedLayer.fontSize || 30
                            };
                            this.dragStart = { x: clickStandardX, y: clickStandardY };
                            return;
                        }
                    }
                }
            }
            
            const activeLayers = this.layers.filter(l => this.currentFrameTime >= l.startTime && this.currentFrameTime <= l.endTime);
            for (let i = activeLayers.length - 1; i >= 0; i--) {
                const layer = activeLayers[i];
                if (layer.type === 'background') continue;
                const bounds = this.getLayerBounds(layer);
                if (bounds) {
                    if (clickX >= bounds.x1 && clickX <= bounds.x2 && clickY >= bounds.y1 && clickY <= bounds.y2) {
                        this.selectedLayerId = layer.id;
                        this.isDragging = true;
                        this.draggedLayer = layer;
                        this.dragStart = {
                            x: clickStandardX - (layer.x !== undefined ? layer.x : 480),
                            y: clickStandardY - (layer.y !== undefined ? layer.y : 270)
                        };
                        this.updateFrame();
                        return;
                    }
                }
            }

            // Click empty space to deselect
            this.selectedLayerId = null;
            this.updateFrame();
        },
 
        onMouseMove(e) {
            if (!this.initCanvas()) return;
            const rect = this.canvas.getBoundingClientRect();
            const clickX = (e.clientX - rect.left) * (this.canvas.width / rect.width);
            const clickY = (e.clientY - rect.top) * (this.canvas.height / rect.height);
            
            const cx = this.canvas.width / 2;
            const cy = this.canvas.height / 2;
            const clickStandardX = clickX - cx + 480;
            const clickStandardY = clickY - cy + 270;
            
            if (this.isDragging && this.draggedLayer) {
                this.draggedLayer.x = Math.round(clickStandardX - this.dragStart.x);
                this.draggedLayer.y = Math.round(clickStandardY - this.dragStart.y);
                this.updateFrame();
                return;
            } 
            
            if (this.isResizing) {
                const selectedLayer = this.getSelectedLayer();
                if (selectedLayer) {
                    const dx = clickStandardX - this.dragStart.x;
                    const dy = clickStandardY - this.dragStart.y;
                    
                    if (selectedLayer.type === 'text') {
                        const factor = this.resizeDirection.includes('s') ? 1 : -1;
                        selectedLayer.fontSize = Math.max(10, Math.round(this.resizeStartSize.fontSize + dy * factor));
                    } else {
                        const wFactor = this.resizeDirection.includes('e') ? 1 : -1;
                        const hFactor = this.resizeDirection.includes('s') ? 1 : -1;
                        
                        selectedLayer.width = Math.max(10, Math.round(this.resizeStartSize.w + dx * wFactor * 2));
                        selectedLayer.height = Math.max(10, Math.round(this.resizeStartSize.h + dy * hFactor * 2));
                    }
                    this.updateFrame();
                }
                return;
            }
 
            // Hover cursor styling
            let cursorStyle = 'default';
            const selectedLayer = this.getSelectedLayer();
            if (selectedLayer && this.currentFrameTime >= selectedLayer.startTime && this.currentFrameTime <= selectedLayer.endTime) {
                const bounds = this.getLayerBounds(selectedLayer);
                if (bounds) {
                    const hs = 8;
                    const corners = {
                        nw: { x: bounds.x1, y: bounds.y1 },
                        ne: { x: bounds.x2, y: bounds.y1 },
                        se: { x: bounds.x2, y: bounds.y2 },
                        sw: { x: bounds.x1, y: bounds.y2 }
                    };
                    for (let dir in corners) {
                        const c = corners[dir];
                        if (Math.abs(clickX - c.x) <= hs && Math.abs(clickY - c.y) <= hs) {
                            cursorStyle = (dir === 'nw' || dir === 'se') ? 'nwse-resize' : 'nesw-resize';
                            break;
                        }
                    }
                    if (cursorStyle === 'default') {
                        if (clickX >= bounds.x1 && clickX <= bounds.x2 && clickY >= bounds.y1 && clickY <= bounds.y2) {
                            cursorStyle = 'move';
                        }
                    }
                }
            }
 
            if (cursorStyle === 'default') {
                const activeLayers = this.layers.filter(l => this.currentFrameTime >= l.startTime && this.currentFrameTime <= l.endTime);
                for (let i = activeLayers.length - 1; i >= 0; i--) {
                    const layer = activeLayers[i];
                    if (layer.type === 'background') continue;
                    const bounds = this.getLayerBounds(layer);
                    if (bounds && clickX >= bounds.x1 && clickX <= bounds.x2 && clickY >= bounds.y1 && clickY <= bounds.y2) {
                        cursorStyle = 'pointer';
                        break;
                    }
                }
            }
 
            this.canvas.style.cursor = cursorStyle;
        },
 
        onMouseUp() {
            this.isDragging = false;
            this.isResizing = false;
            this.draggedLayer = null;
            this.resizeDirection = null;
        },
 
        showNotification(text, type = 'success') {
            const id = Date.now();
            this.notifications.push({ id: id, text: text, type: type });
            setTimeout(() => {
                this.notifications = this.notifications.filter(n => n.id !== id);
            }, 3000);
        },
 
        getSelectedLayer() {
            return this.layers.find(l => l.id === this.selectedLayerId);
        },
 
        addLayer(type) {
            const id = 'layer-' + Date.now();
            let newLayer = {
                id: id,
                name: 'Neue ' + type + ' Ebene',
                type: type,
                opacity: 1.0,
                startTime: 0.0,
                endTime: 6.0,
                animation: 'none'
            };
 
            if (type === 'text') {
                newLayer.text = 'Eigener Text';
                newLayer.x = 480;
                newLayer.y = 270;
                newLayer.fontSize = 24;
                newLayer.color = '#C5A059';
                newLayer.fontFamily = 'Outfit';
            } else if (type === 'image') {
                newLayer.imageUrl = 'shop/projekt/logo/mein-seelenfunke-logo.png';
                newLayer.x = 480;
                newLayer.y = 200;
                newLayer.width = 100;
                newLayer.height = 100;
                newLayer.shine = false;
            } else if (type === 'particles') {
                newLayer.particleType = 'sparks';
                newLayer.color = '#C5A059';
                newLayer.count = 30;
                newLayer.x = 480;
                newLayer.y = 270;
                newLayer.width = 120;
                newLayer.height = 120;
            } else if (type === 'shape') {
                newLayer.shapeType = 'rect';
                newLayer.color = '#C5A059';
                newLayer.width = 100;
                newLayer.height = 100;
                newLayer.x = 480;
                newLayer.y = 270;
            } else if (type === 'avatar') {
                newLayer.avatarUrl = '/shop/ai/images/funkira_selfie.png';
                newLayer.scriptText = 'Hallo, willkommen bei Seelenfunke!';
                newLayer.voiceId = 'de-DE-Wavenet-D';
                newLayer.style = 'circle';
                newLayer.x = 800;
                newLayer.y = 400;
                newLayer.width = 120;
                newLayer.height = 120;
            } else if (type === 'subtitles') {
                newLayer.text = 'Deine automatischen Untertitel werden hier angezeigt';
                newLayer.color = '#ffffff';
                newLayer.fontSize = 24;
                newLayer.fontFamily = 'Outfit';
                newLayer.x = 480;
                newLayer.y = 470;
            } else if (type === 'audio') {
                newLayer.name = 'Audio Voiceover';
                newLayer.audioUrl = 'calm';
                newLayer.scriptText = 'Hier steht der vorgelesene Text.';
                newLayer.voiceId = 'de-DE-Wavenet-D';
                newLayer.volume = 0.5;
            }
 
            this.layers.push(newLayer);
            this.selectedLayerId = id;
            this.updateFrame();
            this.showNotification(`${type.toUpperCase()}-Ebene erfolgreich hinzugefügt!`, 'success');
        },
 
        deleteLayer(id) {
            const layer = this.layers.find(l => l.id === id);
            if (layer && layer.type === 'background') {
                this.showNotification('Hintergrundebene kann nicht gelöscht werden!', 'error');
                return;
            }
            this.layers = this.layers.filter(l => l.id !== id);
            if (this.selectedLayerId === id) {
                this.selectedLayerId = this.layers.length > 0 ? this.layers[0].id : null;
            }
            this.updateFrame();
            this.showNotification('Ebene erfolgreich entfernt.', 'success');
        },
 
        moveLayerUp(id) {
            const index = this.layers.findIndex(l => l.id === id);
            if (index < this.layers.length - 1) {
                const temp = this.layers[index];
                this.layers[index] = this.layers[index + 1];
                this.layers[index + 1] = temp;
                this.updateFrame();
                this.showNotification('Ebene nach oben geschoben (höherer Z-Index)', 'success');
            }
        },
 
        moveLayerDown(id) {
            const index = this.layers.findIndex(l => l.id === id);
            if (index > 0) {
                const temp = this.layers[index];
                this.layers[index] = this.layers[index - 1];
                this.layers[index - 1] = temp;
                this.updateFrame();
                this.showNotification('Ebene nach unten geschoben (niedrigerer Z-Index)', 'success');
            }
        },
 
        loadPreset(type) {
            if (type === 'seelenfunke') {
                this.layers = [
                    { id: 'metadata', aspectRatio: this.aspectRatio, duration: this.totalDuration },
                    { id: 'l-bg', name: 'Hintergrund', type: 'background', color: '#FAF9F6', gradientColor: '#C5A059', useGradient: true, opacity: 1.0, startTime: 0.0, endTime: this.totalDuration },
                    { id: 'l-part', name: 'Goldschweif-Funken', type: 'particles', particleType: 'sparks', color: '#C5A059', opacity: 0.8, startTime: 1.8, endTime: this.totalDuration, x: 480, y: 160, width: 120, height: 120 },
                    { id: 'l-logo', name: 'Seelenfunke Flame', type: 'image', imageUrl: 'shop/projekt/logo/mein-seelenfunke-logo.png', x: 480, y: 160, width: 120, height: 120, opacity: 1.0, startTime: 0.2, endTime: this.totalDuration, animation: 'fade', shine: true },
                    { id: 'l-t1', name: 'Gold-Schriftzug', type: 'text', text: this.projectTitle, x: 480, y: 370, fontSize: 32, color: '#C5A059', fontFamily: 'Playfair Display', opacity: 1.0, startTime: 2.3, endTime: this.totalDuration, animation: 'fade' },
                    { id: 'l-t2', name: 'Gold-Slogan', type: 'text', text: 'EIN FUNKE, DER BLEIBT', x: 480, y: 405, fontSize: 12, color: '#5C5549', fontFamily: 'Outfit', opacity: 1.0, startTime: 2.6, endTime: this.totalDuration, animation: 'fade' }
                ];
            } else if (type === 'standard') {
                this.layers = [
                    { id: 'metadata', aspectRatio: this.aspectRatio, duration: this.totalDuration },
                    { id: 'l-bg', name: 'Hintergrund', type: 'background', color: '#111827', useGradient: false, opacity: 1.0, startTime: 0.0, endTime: this.totalDuration },
                    { id: 'l-logo', name: 'Standard Avatar', type: 'image', imageUrl: 'shop/ai/images/funkira_selfie.png', x: 480, y: 160, width: 120, height: 120, opacity: 1.0, startTime: 0.2, endTime: this.totalDuration, animation: 'fade' },
                    { id: 'l-t1', name: 'Standard Titel', type: 'text', text: this.projectTitle, x: 480, y: 370, fontSize: 32, color: '#3B82F6', fontFamily: 'Outfit', opacity: 1.0, startTime: 2.3, endTime: this.totalDuration, animation: 'fade' }
                ];
            }
            this.selectedLayerId = this.layers[0].id;
            this.updateFrame();
            this.showNotification(`Stil '${type}' geladen!`, 'success');
        },
        onDurationChanged() {
            if (!this.totalDuration || this.totalDuration <= 0) {
                this.totalDuration = 6.0;
            }
            
            let meta = this.layers.find(l => l.id === 'metadata');
            if (meta) {
                meta.duration = this.totalDuration;
            } else {
                this.layers.unshift({ id: 'metadata', aspectRatio: this.aspectRatio, duration: this.totalDuration });
            }

            this.layers.forEach(l => {
                if (l.type === 'background' || l.id === 'layer-bg' || l.id === 'l-bg') {
                    l.endTime = this.totalDuration;
                }
                if (l.endTime > this.totalDuration) {
                    l.endTime = this.totalDuration;
                }
                if (l.startTime > this.totalDuration) {
                    l.startTime = Math.max(0, this.totalDuration - 1.0);
                }
            });

            this.updateFrame();
            this.showNotification(`Videodauer auf ${this.totalDuration}s angepasst.`, 'info');
        },

        loadVideoToEditor(id) {
            @this.loadVideoTemplate(id);
        },
 
        saveCurrentConfig() {
            const title = this.projectTitle;
            const subLayer = this.layers.find(l => l.id === 'layer-subtitle' || l.id === 'l-t2');
            const subtitle = subLayer ? subLayer.text : '';
            const titleLayer = this.layers.find(l => l.id === 'layer-title' || l.id === 'l-t1');
            const themeColor = titleLayer ? titleLayer.color : '#C5A059';
            const hasParticles = this.layers.some(l => l.type === 'particles');
            
            @this.saveVideoConfig(
                this.activeVideoId || '', 
                title, 
                subtitle, 
                themeColor, 
                hasParticles, 
                JSON.stringify(this.layers)
            );
        },
        
        splitSelectedLayer() {
            const layer = this.getSelectedLayer();
            if (!layer) {
                this.showNotification('Wähle eine Ebene aus, um sie zu schneiden!', 'error');
                return;
            }
            
            const t = this.currentFrameTime;
            if (t <= layer.startTime || t >= layer.endTime) {
                this.showNotification('Cursor muss sich innerhalb des Timing-Bereichs der Ebene befinden!', 'error');
                return;
            }
            
            const originalEnd = layer.endTime;
            const splitTime = Math.round(t * 10) / 10;
            if (splitTime <= layer.startTime + 0.1 || splitTime >= originalEnd - 0.1) {
                this.showNotification('Zu nah am Rand! Schnitt nicht möglich.', 'error');
                return;
            }
            
            // Shorten the first part
            layer.endTime = splitTime;
            
            // Clone the layer for the second part
            const clone = JSON.parse(JSON.stringify(layer));
            clone.id = 'layer-' + Date.now();
            clone.name = layer.name + ' (Teil 2)';
            clone.startTime = splitTime;
            clone.endTime = originalEnd;
            
            // Insert the cloned layer into the array right after the original layer
            const index = this.layers.findIndex(l => l.id === layer.id);
            this.layers.splice(index + 1, 0, clone);
            
            // Select the new segment
            this.selectedLayerId = clone.id;
            
            this.updateFrame();
            this.showNotification('Spur erfolgreich geschnitten (Strg + B)!', 'success');
        },
        
        changeAspectRatio(ratio) {
            this.aspectRatio = ratio;
            let meta = this.layers.find(l => l.id === 'metadata');
            if (!meta) {
                meta = { id: 'metadata', aspectRatio: ratio };
                this.layers.push(meta);
            } else {
                meta.aspectRatio = ratio;
            }
            this.updateCanvasDimensions();
            this.updateFrame();
        },
        
        updateCanvasDimensions() {
            const canvasEl = document.getElementById('logoCanvas');
            if (!canvasEl) return;
            this.canvas = canvasEl;
            this.ctx = this.canvas.getContext('2d');
            
            let w = 960;
            let h = 540;
            const ratio = this.aspectRatio;
            if (ratio === '9:16') {
                w = 304;
                h = 540;
            } else if (ratio === '1:1') {
                w = 540;
                h = 540;
            } else if (ratio === '4:5') {
                w = 432;
                h = 540;
            } else if (ratio === '2:3') {
                w = 360;
                h = 540;
            }
            this.canvas.width = w;
            this.canvas.height = h;
            
            const container = document.getElementById('canvas-monitor-container');
            if (container) {
                container.style.aspectRatio = w + '/' + h;
                container.style.maxWidth = w + 'px';
            }
        },
        
        openExportModal() {
            this.exportSettings.name = this.projectTitle || 'mein-seelenfunke';
            this.exportSettings.path = `B:/Gewerbe/Meral/Tik Tok/${this.exportSettings.name}.${this.exportSettings.format}`;
            this.showExportModal = true;
            
            this.$nextTick(() => {
                const prevCanvas = document.getElementById('exportPreviewCanvas');
                if (prevCanvas && this.canvas) {
                    prevCanvas.width = this.canvas.width;
                    prevCanvas.height = this.canvas.height;
                    const prevCtx = prevCanvas.getContext('2d');
                    prevCtx.drawImage(this.canvas, 0, 0);
                }
            });
        },
        
        async chooseExportDirectory() {
            try {
                if (window.showDirectoryPicker) {
                    const handle = await window.showDirectoryPicker();
                    this.exportSettings.path = `${handle.name}/${this.exportSettings.name}.${this.exportSettings.format}`;
                    this.showNotification(`Export-Ordner '${handle.name}' ausgewählt!`, 'success');
                } else {
                    const path = prompt("Ordnerpfad eingeben:", this.exportSettings.path);
                    if (path) {
                        this.exportSettings.path = path;
                    }
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    const path = prompt("Pfad manuell eingeben oder anpassen:", this.exportSettings.path);
                    if (path) {
                        this.exportSettings.path = path;
                    }
                }
            }
        },
        
        calculateEstimatedSize() {
            let resMultiplier = 1.0;
            if (this.exportSettings.resolution === '720p') resMultiplier = 0.6;
            if (this.exportSettings.resolution === '4K') resMultiplier = 3.0;
            
            let rateMultiplier = 1.0;
            if (this.exportSettings.bitrate === 'Hoch') rateMultiplier = 1.5;
            if (this.exportSettings.bitrate === 'Niedrig') rateMultiplier = 0.5;
            
            let fpsMultiplier = 1.0;
            if (this.exportSettings.frameRate === '60fps') fpsMultiplier = 1.8;
            if (this.exportSettings.frameRate === '24fps') fpsMultiplier = 0.8;
            
            let formatMultiplier = 1.0;
            if (this.exportSettings.format === 'gif') {
                formatMultiplier = 0.15;
                resMultiplier = 0.2;
            }
            
            const baseSizeMB = 2.4;
            const est = baseSizeMB * resMultiplier * rateMultiplier * fpsMultiplier * formatMultiplier;
            return `ca. ${est.toFixed(1)} MB`;
        },
        
        triggerRenderFromModal() {
            this.showExportModal = false;
            this.renderAndExport();
        },
 
        formatTime(t) {
            return t.toFixed(2) + 's';
        },
 
        updateFrame() {
            if (!this.initCanvas()) return;
            if (!this.isPlaying) {
                this.draw(this.currentFrameTime);
            }
        },
 
        scrubTimeline() {
            this.updateFrame();
        },
 
        togglePlay() {
            if (this.isPlaying) {
                this.isPlaying = false;
                if (this.animationFrameId) {
                    cancelAnimationFrame(this.animationFrameId);
                    this.animationFrameId = null;
                }
                this.showNotification('Animation angehalten.', 'success');
            } else {
                if (this.animationFrameId) {
                    cancelAnimationFrame(this.animationFrameId);
                }
                this.isPlaying = true;
                this.startTime = Date.now() - (this.currentFrameTime * 1000);
                this.tick();
                this.showNotification('Wiedergabe gestartet...', 'success');
            }
        },
 
        stop() {
            this.isPlaying = false;
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
                this.animationFrameId = null;
            }
            this.currentFrameTime = 0.0;
            this.updateFrame();
            this.showNotification('Animation gestoppt.', 'success');
        },
 
        restart() {
            this.currentFrameTime = 0.0;
            if (this.isPlaying) {
                this.startTime = Date.now();
            } else {
                this.updateFrame();
            }
            this.showNotification('Animation neu gestartet.', 'success');
        },
 
        tick() {
            const elapsed = (Date.now() - this.startTime) / 1000;
            this.currentFrameTime = elapsed % this.totalDuration;
            
            this.draw(this.currentFrameTime);
 
            if (this.isPlaying) {
                this.animationFrameId = requestAnimationFrame(() => this.tick());
            }
        },
 
        hexToRgba(hex, alpha = 1) {
            if (hex.startsWith('rgba')) {
                return hex.replace(/[^,]+(?=\))/, alpha);
            }
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        },
 
        draw(t) {
            if (!this.initCanvas()) return;
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            
            let localSeed = 12345;
            const getSeededRandom = () => {
                let temp = localSeed += 0x6D2B79F5;
                temp = Math.imul(temp ^ (temp >>> 15), temp | 1);
                temp ^= temp + Math.imul(temp ^ (temp >>> 7), temp | 61);
                return ((temp ^ (temp >>> 14)) >>> 0) / 4294967296;
            };
            
            const imageCache = {};
            const getCachedImage = (url) => {
                if (!url) return null;
                if (imageCache[url]) return imageCache[url];
                const img = new Image();
                img.src = url.startsWith('http') || url.startsWith('/') ? url : '{{ asset("") }}' + url;
                imageCache[url] = img;
                return img;
            };
 
            this.layers.forEach(layer => {
                if (layer.id === 'metadata') return;
                if (t < layer.startTime || t > layer.endTime) return;
                
                const age = t - layer.startTime;
                this.ctx.save();
                
                let opacity = layer.opacity !== undefined ? layer.opacity : 1.0;
                if (layer.animation === 'fade' && age < 0.5) {
                    opacity *= (age / 0.5);
                }
                this.ctx.globalAlpha = opacity;
                
                const cx = this.canvas.width / 2;
                const cy = this.canvas.height / 2;
                
                let dx = layer.x !== undefined ? (layer.x - 480) + cx : cx;
                let dy = layer.y !== undefined ? (layer.y - 270) + cy : cy;
                
                if (layer.animation === 'slide-up' && age < 0.5) {
                    const slideProgress = (1 - age / 0.5);
                    dy += slideProgress * 30;
                }
 
                if (layer.type === 'background') {
                    if (layer.useGradient) {
                        const grad = this.ctx.createLinearGradient(0, 0, 0, this.canvas.height);
                        grad.addColorStop(0, layer.color || '#FAF9F6');
                        grad.addColorStop(1, layer.gradientColor || '#C5A059');
                        this.ctx.fillStyle = grad;
                    } else {
                        this.ctx.fillStyle = layer.color || '#FAF9F6';
                    }
                    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                } 
                
                else if (layer.type === 'text') {
                    this.ctx.fillStyle = layer.color || '#C5A059';
                    this.ctx.font = `bold ${layer.fontSize || 30}px "${layer.fontFamily || 'Outfit'}", sans-serif`;
                    this.ctx.textAlign = 'center';
                    this.ctx.textBaseline = 'middle';
                    
                    let txt = layer.text || '';
                    if (layer.animation === 'typewriter') {
                        const charCount = Math.floor(age / 0.05);
                        txt = txt.substring(0, charCount);
                    }
                    this.ctx.fillText(txt, dx, dy);
                } 
                
                else if (layer.type === 'image') {
                    const img = getCachedImage(layer.imageUrl);
                    if (img && img.complete && img.naturalWidth !== 0) {
                        const w = layer.width || 100;
                        const h = layer.height || 100;
                        
                        let currentW = w;
                        let currentH = h;
                        if (layer.animation === 'scale' && age < 0.5) {
                            const scaleProgress = age / 0.5;
                            currentW *= scaleProgress;
                            currentH *= scaleProgress;
                        }
                        
                        this.ctx.drawImage(img, dx - currentW/2, dy - currentH/2, currentW, currentH);
                        
                        if (layer.shine && age >= 1.0 && age < 2.0) {
                            const shineProgress = age - 1.0;
                            const shineX = (dx - currentW/2) - 50 + (shineProgress * (currentW + 100));
                            
                            this.ctx.globalCompositeOperation = 'source-atop';
                            this.ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
                            this.ctx.beginPath();
                            this.ctx.moveTo(shineX, dy - currentH/2);
                            this.ctx.lineTo(shineX + 30, dy - currentH/2);
                            this.ctx.lineTo(shineX + 10, dy + currentH/2);
                            this.ctx.lineTo(shineX - 20, dy + currentH/2);
                            this.ctx.closePath();
                            this.ctx.fill();
                        }
                    } else {
                        this.ctx.strokeStyle = layer.color || '#C5A059';
                        this.ctx.lineWidth = 2;
                        this.ctx.strokeRect(dx - 50, dy - 50, 100, 100);
                    }
                } 
                
                else if (layer.type === 'particles') {
                    const goldColor = layer.color || '#C5A059';
                    localSeed = 12345;
                    const count = layer.count || 35;
                    const scaleX = (layer.width !== undefined ? layer.width : 120) / 120;
                    const scaleY = (layer.height !== undefined ? layer.height : 120) / 120;
                    
                    for (let i = 0; i < count; i++) {
                        const angle = getSeededRandom() * Math.PI * 2;
                        const radius = getSeededRandom() * 60 + 20;
                        const size = getSeededRandom() * 6 + 3;
                        const driftSpeed = getSeededRandom() * 40 - 20;
                        const riseSpeed = getSeededRandom() * 80 + 40;
                        const lifeOffset = getSeededRandom() * 0.8;
                        
                        const pAge = age - lifeOffset;
                        if (pAge > 0 && pAge < 2.0) {
                            const driftX = (Math.cos(angle) * radius + (driftSpeed * pAge)) * scaleX;
                            const driftY = (Math.sin(angle) * radius - (riseSpeed * pAge)) * scaleY;
                            const sparkOpacity = Math.max(1 - pAge / 2.0, 0.0);
                            const sparkSize = size * (1 - pAge / 2.0);
                            
                            this.ctx.beginPath();
                            this.ctx.arc(dx + driftX, dy + driftY, sparkSize, 0, Math.PI * 2);
                            this.ctx.fillStyle = this.hexToRgba('#ffffff', sparkOpacity * opacity);
                            this.ctx.fill();
                            
                            const sparkGlow = this.ctx.createRadialGradient(dx + driftX, dy + driftY, 0, dx + driftX, dy + driftY, sparkSize * 3);
                            sparkGlow.addColorStop(0, this.hexToRgba(goldColor, sparkOpacity * opacity));
                            sparkGlow.addColorStop(1, 'rgba(197, 160, 89, 0)');
                            this.ctx.fillStyle = sparkGlow;
                            this.ctx.beginPath();
                            this.ctx.arc(dx + driftX, dy + driftY, sparkSize * 3, 0, Math.PI * 2);
                            this.ctx.fill();
                        }
                    }
                } 
                
                else if (layer.type === 'shape') {
                    this.ctx.fillStyle = layer.color || '#C5A059';
                    const w = layer.width || 100;
                    const h = layer.height || 100;
                    
                    this.ctx.beginPath();
                    if (layer.shapeType === 'circle') {
                        this.ctx.arc(dx, dy, w/2, 0, Math.PI * 2);
                    } else {
                        this.ctx.rect(dx - w/2, dy - h/2, w, h);
                    }
                    this.ctx.fill();
                }
                
                else if (layer.type === 'avatar') {
                    const avatarUrl = layer.avatarUrl || '/shop/ai/images/funkira_selfie.png';
                    const img = getCachedImage(avatarUrl);
                    const w = layer.width || 120;
                    const h = layer.height || 120;
                    
                    if (img && img.complete && img.naturalWidth !== 0) {
                        this.ctx.save();
                        this.ctx.beginPath();
                        if (layer.style === 'circle') {
                            this.ctx.arc(dx, dy, w/2, 0, Math.PI * 2);
                        } else {
                            this.ctx.rect(dx - w/2, dy - h/2, w, h);
                        }
                        this.ctx.clip();
                        this.ctx.drawImage(img, dx - w/2, dy - h/2, w, h);
                        this.ctx.restore();
                        
                        if (this.isPlaying) {
                            const ripple = (Date.now() / 1000) % 1.5;
                            this.ctx.strokeStyle = layer.color || 'var(--theme-color)';
                            this.ctx.lineWidth = 2 * (1 - ripple / 1.5);
                            this.ctx.beginPath();
                            this.ctx.arc(dx, dy, (w/2) + ripple * 20, 0, Math.PI * 2);
                            this.ctx.stroke();
                        }
                    } else {
                        this.ctx.strokeStyle = layer.color || '#C5A059';
                        this.ctx.lineWidth = 2;
                        this.ctx.beginPath();
                        this.ctx.arc(dx, dy, w/2, 0, Math.PI * 2);
                        this.ctx.stroke();
                        this.ctx.fillStyle = '#C5A059';
                        this.ctx.font = '10px sans-serif';
                        this.ctx.textAlign = 'center';
                        this.ctx.fillText('Avatar', dx, dy);
                    }
                }
                
                else if (layer.type === 'subtitles') {
                    const words = (layer.text || '').split(' ');
                    if (words.length > 0) {
                        const progress = (t - layer.startTime) / (layer.endTime - layer.startTime);
                        const currentWordIndex = Math.min(Math.floor(progress * words.length), words.length - 1);
                        const activeWord = words[currentWordIndex] || '';
                        
                        this.ctx.fillStyle = layer.color || '#ffffff';
                        this.ctx.font = `bold ${layer.fontSize || 24}px "${layer.fontFamily || 'Outfit'}", sans-serif`;
                        this.ctx.textAlign = 'center';
                        this.ctx.fillText(activeWord.toUpperCase(), dx, dy);
                    }
                }
                
                else if (layer.type === 'audio') {
                    if (this.selectedLayerId === layer.id) {
                        this.ctx.fillStyle = 'rgba(197, 160, 89, 0.05)';
                        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
                        this.ctx.fillStyle = '#C5A059';
                        this.ctx.font = '10px monospace';
                        this.ctx.fillText(`🔊 Voiceover: "${layer.scriptText || 'Kein Sprechtext'}"`, 20, 25);
                    }
                }
 
                this.ctx.restore();
            });
 
            // Draw selection outline over the active selected layer
            const selL = this.getSelectedLayer();
            if (selL && t >= selL.startTime && t <= selL.endTime) {
                this.drawSelectionBorder(selL);
            }
        },

        startTimelineDrag(e, layer, action = 'move') {
            this.selectedLayerId = layer.id;
            
            const blockEl = action === 'move' ? e.currentTarget : e.currentTarget.parentElement;
            const trackContainer = blockEl.parentElement;
            const containerWidth = trackContainer.clientWidth;
            
            const initialX = e.clientX;
            const initialStart = layer.startTime;
            const initialEnd = layer.endTime;
            const duration = initialEnd - initialStart;
            
            const onMouseMove = (moveEvent) => {
                const dx = moveEvent.clientX - initialX;
                const dt = (dx / containerWidth) * this.totalDuration;
                
                if (action === 'resize-start') {
                    let newStart = initialStart + dt;
                    if (newStart < 0) newStart = 0;
                    if (newStart > initialEnd - 0.1) newStart = initialEnd - 0.1;
                    layer.startTime = Math.round(newStart * 10) / 10;
                } else if (action === 'resize-end') {
                    let newEnd = initialEnd + dt;
                    if (newEnd > this.totalDuration) newEnd = this.totalDuration;
                    if (newEnd < initialStart + 0.1) newEnd = initialStart + 0.1;
                    layer.endTime = Math.round(newEnd * 10) / 10;
                } else {
                    // 'move'
                    let newStart = initialStart + dt;
                    let newEnd = initialEnd + dt;
                    
                    if (newStart < 0) {
                        newStart = 0;
                        newEnd = duration;
                    }
                    if (newEnd > this.totalDuration) {
                        newEnd = this.totalDuration;
                        newStart = this.totalDuration - duration;
                    }
                    
                    layer.startTime = Math.round(newStart * 10) / 10;
                    layer.endTime = Math.round(newEnd * 10) / 10;
                }
                
                this.updateFrame();
            };
            
            const onMouseUp = () => {
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
            };
            
            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        },
 
        async renderAndExport() {
            if (!this.initCanvas()) return;
            if (this.isPlaying) {
                this.isPlaying = false;
                cancelAnimationFrame(this.animationFrameId);
            }
 
            const renderOverlay = document.getElementById('render-overlay');
            const renderProgressFill = document.getElementById('render-progress-fill');
            const renderStatusText = document.getElementById('render-status-text');
 
            renderOverlay.classList.remove('hidden');
            renderProgressFill.style.width = '0%';
            renderStatusText.textContent = "Initialisiere localen Renderer...";
 
            const videoId = this.activeVideoId || @js(\Illuminate\Support\Str::uuid());
            this.activeVideoId = videoId;
            @this.set('activeVideoId', videoId);
 
            this.currentFrameTime = 0.0;
            
            let fps = 30;
            if (this.exportSettings.frameRate === '60fps') fps = 60;
            if (this.exportSettings.frameRate === '24fps') fps = 24;
            
            const stream = this.canvas.captureStream(fps);
            let mimeType = 'video/webm;codecs=vp9';
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/webm;codecs=vp8';
            }
            if (!MediaRecorder.isTypeSupported(mimeType)) {
                mimeType = 'video/webm';
            }
 
            const chunks = [];
            const recorder = new MediaRecorder(stream, { mimeType: mimeType });
 
            recorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    chunks.push(e.data);
                }
            };
 
            recorder.onstop = () => {
                renderStatusText.textContent = "Speichere Render-Projekt auf Server...";
                const outMime = this.exportSettings.format === 'gif' ? 'image/gif' : 'video/webm';
                const blob = new Blob(chunks, { type: outMime });
                
                // Trigger browser download immediately
                try {
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    const projectTitle = this.exportSettings.name || 'video';
                    const safeName = projectTitle.toLowerCase()
                        .replace(/[äöüß]/g, (m) => ({'ä':'ae', 'ö':'oe', 'ü':'ue', 'ß':'ss'}[m]))
                        .replace(/[^a-z0-9]+/g, '-');
                    a.download = `${safeName || 'video'}_render.${this.exportSettings.format}`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                } catch (err) {
                    console.error("Local download failed", err);
                }
 
                @this.upload('videoFile', blob, 
                    (uploadedName) => {
                        renderOverlay.classList.add('hidden');
                        this.showNotification('Rendering erfolgreich abgeschlossen und exportiert!', 'success');
                    },
                    (err) => {
                        alert('Render Upload Error: ' + err);
                        renderOverlay.classList.add('hidden');
                    },
                    (progress) => {
                        const pct = Math.round((progress.loaded / progress.total) * 100);
                        renderStatusText.textContent = `Hochladen: ${pct}%`;
                        renderProgressFill.style.width = `${pct}%`;
                    }
                );
            };
 
            recorder.start();
 
            const totalFrames = fps * this.totalDuration;
            let currentFrame = 0;
 
            const recordNext = () => {
                if (currentFrame <= totalFrames) {
                    this.currentFrameTime = currentFrame / fps;
                    this.draw(this.currentFrameTime);
                    
                    const pct = Math.round((currentFrame / totalFrames) * 100);
                    renderStatusText.textContent = `Rendere Frames: ${pct}%`;
                    renderProgressFill.style.width = `${pct}%`;
 
                    currentFrame++;
                    setTimeout(recordNext, 1000 / fps);
                } else {
                    recorder.stop();
                }
            };
 
            recordNext();
        }
    };
}
</script>
 
<style>
.accent-\[var\(--theme-color\)\]::-webkit-slider-thumb {
    background: var(--theme-color) !important;
    box-shadow: 0 0 10px var(--theme-color-50);
}
.accent-\[var\(--theme-color\)\]::-moz-range-thumb {
    background: var(--theme-color) !important;
    box-shadow: 0 0 10px var(--theme-color-50);
}
</style>
</div>
