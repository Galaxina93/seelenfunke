<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-5: {{ $this->themeColorHex }}0D; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-70: {{ $this->themeColorHex }}B3; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div>
    <div class="animate-fade-in-up font-sans antialiased text-gray-400 pb-12 w-full">

        <div class="flex flex-col lg:flex-row h-[calc(100vh-18rem)] min-h-[600px] bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl rounded-2xl shadow-xl shadow-[var(--theme-color-10)] border border-gray-800 overflow-hidden">

            <div class="w-full lg:w-1/3 xl:w-1/4 bg-gray-950/80 border-b lg:border-b-0 lg:border-r border-gray-800 flex flex-col shrink-0 z-10 shadow-inner">
                <div class="p-6 border-b border-gray-800">
                    <div class="relative group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Wissen durchsuchen..." class="w-full pl-11 pr-4 py-3.5 bg-gray-950 border border-gray-800 rounded-xl text-sm font-bold text-[var(--theme-color)] focus:bg-gray-950 focus:ring-1 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] shadow-[inset_0_0_15px_var(--theme-color-5)] outline-none transition-all placeholder-gray-600 uppercase tracking-widest">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-[var(--theme-color)] transition-colors" />
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-gray-800 flex overflow-x-auto custom-scrollbar gap-2 shrink-0">
                    <button wire:click="setCategory('')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategoryId === null ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-50)] text-[var(--theme-color)] shadow-xl shadow-[var(--theme-color-10)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-[var(--theme-color)] hover:border-emerald-700/50' }}">
                        Alle
                    </button>
                    @foreach($categories as $cat)
                        <button wire:click="setCategory('{{$cat->id}}')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategoryId == $cat->id ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-50)] text-[var(--theme-color)] shadow-xl shadow-[var(--theme-color-10)]' : 'bg-gray-950 text-gray-400 border border-gray-800 hover:text-[var(--theme-color)] hover:border-emerald-700/50' }}">
                            {{$cat->name}}
                        </button>
                    @endforeach
                </div>

                <div class="p-3 border-b border-gray-800 shrink-0">
                    <button wire:click="createNewArticle" class="w-full py-2.5 bg-[var(--theme-color-10)] text-[var(--theme-color)] border border-[var(--theme-color-30)] rounded-xl text-xs font-black uppercase tracking-widest hover:bg-[var(--theme-color-20)] hover:border-[var(--theme-color)]/60 shadow-xl shadow-[var(--theme-color-10)] transition-all flex items-center justify-center gap-2">
                        <x-heroicon-o-plus class="w-4 h-4" /> Neuer Eintrag
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
                    @forelse($articles as $article)
                        <button wire:click="selectArticle('{{$article->id}}')" class="w-full text-left p-4 rounded-xl transition-all duration-200 border {{ $activeArticleId == $article->id ? 'bg-[var(--theme-color-10)] border-[var(--theme-color-40)] shadow-[inset_4px_0_0_var(--theme-color)]' : 'bg-transparent border-transparent hover:bg-gray-950 hover:border-gray-800' }}">
                            <h4 class="text-sm font-bold tracking-wider truncate {{ $activeArticleId == $article->id ? 'text-[var(--theme-color)]' : 'text-gray-400' }}">{{ $article->title }}</h4>
                            <div class="flex flex-wrap gap-1.5 mt-2 h-5 overflow-hidden">
                                @if($article->tags)
                                    @foreach($article->tags->take(3) as $tag)
                                        <span class="text-[8px] font-bold uppercase tracking-widest text-gray-400 bg-gray-950 px-1.5 py-0.5 rounded border border-gray-800 shadow-inner">#{{$tag->name}}</span>
                                    @endforeach
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="text-center py-10 px-4 text-gray-500">
                            <x-heroicon-o-document-magnifying-glass class="w-10 h-10 mx-auto mb-3 opacity-50" />
                            <p class="text-[10px] uppercase tracking-widest font-black">Keine Daten gefunden</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex-1 bg-transparent relative overflow-hidden flex flex-col">
                @if($isEditing)
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12 space-y-8">
                         <h2 class="text-2xl font-black text-[var(--theme-color)] uppercase tracking-widest border-b border-gray-800 pb-4">{{ $editForm['id'] ? 'Eintrag bearbeiten' : 'Neuen Eintrag erstellen' }}</h2>

                         <div class="space-y-6">
                             <div class="bg-gray-950 p-6 rounded-xl border border-gray-800 shadow-inner">
                                 <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Titel</label>
                                 <input type="text" wire:model="editForm.title" placeholder="Eindeutiger Titel..." class="w-full bg-gray-950 border border-gray-800 rounded-lg text-[var(--theme-color)] font-bold px-4 py-3 focus:ring-1 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none shadow-[inset_0_0_10px_var(--theme-color-5)] transition-all">
                                 @error('editForm.title') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block">{{ $message }}</span> @enderror
                             </div>

                             <div class="flex flex-col gap-6 w-full">
                                 <div class="flex-1 w-full min-w-0">
                                     <livewire:shop.ai.ai-knowledge-base-categories :selectedCategoryId="$editForm['ai_knowledge_base_category_id']" wire:key="kb-categories-{{ $editForm['id'] ?? 'new' }}" />
                                     @error('editForm.ai_knowledge_base_category_id') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block pl-2">{{ $message }}</span> @enderror
                                 </div>
                                 <div class="flex-1 w-full min-w-0">
                                     <livewire:shop.ai.ai-knowledge-base-tags :selectedTagIds="$editForm['tags']" wire:key="kb-tags-{{ $editForm['id'] ?? 'new' }}" />
                                 </div>
                             </div>

                             <div class="bg-gray-950 p-6 rounded-xl border border-gray-800 shadow-inner">
                                 <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 mb-3">Artikelinhalt</label>
                                 <textarea wire:model="editForm.content" rows="15" class="w-full bg-gray-950 border border-gray-800 rounded-lg text-gray-300 px-4 py-4 focus:ring-1 focus:ring-[var(--theme-color-30)] focus:border-[var(--theme-color)] outline-none shadow-[inset_0_0_10px_var(--theme-color-5)] transition-all custom-scrollbar font-mono text-sm leading-relaxed"></textarea>
                                 @error('editForm.content') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block">{{ $message }}</span> @enderror
                             </div>

                             <div class="flex gap-4 pt-4 border-t border-gray-800">
                                 <button wire:click="saveArticle" class="px-8 py-3.5 bg-[var(--theme-color-10)] border border-[var(--theme-color-50)] text-[var(--theme-color)] rounded-xl text-xs font-black uppercase tracking-widest hover:bg-[var(--theme-color)] hover:text-black hover:shadow-xl shadow-[var(--theme-color-10)] transition-all flex items-center gap-2">
                                     <x-heroicon-o-check class="w-5 h-5"/> Speichern
                                 </button>
                                 <button wire:click="cancelEditing" class="px-8 py-3.5 bg-gray-950 border border-gray-800 text-gray-400 rounded-xl text-xs font-black uppercase tracking-widest hover:border-emerald-700 hover:text-[var(--theme-color)] transition-all">
                                     Abbrechen
                                 </button>
                             </div>
                         </div>
                    </div>
                @elseif($activeArticle)
                    <div class="p-8 lg:p-12 pb-6 mt-6 border-b border-gray-800 bg-gray-900/60 backdrop-blur-xl shrink-0 relative">
                        <div class="absolute top-8 right-8 flex gap-2">
                             <button wire:click="editArticle('{{ $activeArticle->id }}')" class="p-2.5 bg-gray-950 border border-gray-800 hover:bg-[var(--theme-color-10)] hover:text-[var(--theme-color)] hover:border-[var(--theme-color-50)] text-gray-400 rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                 <x-heroicon-o-pencil class="w-5 h-5" />
                             </button>
                             <button wire:click="deleteArticle('{{ $activeArticle->id }}')" wire:confirm="Sicher, dass du diesen Eintrag endgültig löschen möchtest?" class="p-2.5 bg-gray-950 border border-gray-800 hover:bg-red-500/10 hover:text-red-500 hover:border-red-500/50 text-gray-500 rounded-xl transition-all shadow-inner" title="Löschen">
                                 <x-heroicon-o-trash class="w-5 h-5" />
                             </button>
                        </div>
                        @if($activeArticle->category)
                            <span class="inline-block px-3 py-1 bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] rounded-md text-[9px] font-black uppercase tracking-widest text-[var(--theme-color)] mb-4 shadow-xl shadow-[var(--theme-color-10)]">
                                {{$activeArticle->category->name}}
                            </span>
                        @endif
                        <h2 class="text-3xl lg:text-4xl font-black text-[var(--theme-color)] tracking-widest uppercase mb-6">{{$activeArticle->title}}</h2>

                        @if($activeArticle->tags && count($activeArticle->tags) > 0)
                            <div class="flex flex-wrap gap-2.5">
                                @foreach($activeArticle->tags as $tag)
                                    <span class="text-[10px] font-bold text-[var(--theme-color)] bg-gray-950 border border-gray-800 px-3 py-1.5 rounded-lg shadow-inner flex items-center gap-1.5 uppercase tracking-wider">
                                        <span class="text-gray-400">#</span> {{$tag->name}}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12">
                        <style>
                            .kb-content h3 { color: var(--theme-color-80); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; letter-spacing: 0.1em; text-transform: uppercase; font-size: 1.2rem; font-weight: 900; margin-top: 2.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(6, 78, 59, 0.5); padding-bottom: 0.5rem; }
                            .kb-content h3:first-child { margin-top: 0; }
                            .kb-content p { color: #a7f3d0; font-size: 0.95rem; line-height: 1.8; margin-bottom: 1.5rem; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
                            .kb-content strong { color: var(--theme-color); font-weight: 800; background: var(--theme-color-10); padding: 0.1rem 0.4rem; border-radius: 0.25rem; border: 1px solid var(--theme-color-20); }
                            .kb-content ul { color: #a7f3d0; list-style-type: square; padding-left: 1.5rem; margin-bottom: 1.5rem; font-family: ui-sans-serif, system-ui, -apple-system; }
                            .kb-content li { margin-bottom: 0.5rem; }
                            .kb-content li::marker { color: var(--theme-color-50); }
                        </style>

                        <div class="kb-content max-w-4xl text-gray-300">
                            {!! $activeArticle->content !!}
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t border-gray-800 bg-gray-950 text-[9px] font-black uppercase tracking-widest text-gray-400 flex justify-between items-center shrink-0">
                        <span class="flex items-center gap-2"><div class="w-1.5 h-1.5 bg-emerald-700 rounded-full"></div> ID: {{ substr($activeArticle->id, 0, 8) }}</span>
                        <span>Letzte Änderung: {{$activeArticle->updated_at->format('d.m.Y H:i:s')}}</span>
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-500 p-10 text-center animate-pulse-slow">
                        <div class="w-24 h-24 bg-gray-950 border border-gray-800 rounded-full flex items-center justify-center shadow-[inset_0_0_20px_var(--theme-color-5)] mb-6">
                            <x-heroicon-o-server-stack class="w-10 h-10 text-gray-500" />
                        </div>
                        <h3 class="text-xl font-bold font-mono tracking-widest uppercase text-gray-400 mb-2">System bereit</h3>
                        <p class="text-xs font-bold uppercase tracking-widest">Warte auf Eingabe.<br>Wähle einen Eintrag links oder erstelle einen neuen.</p>
                    </div>
                @endif
            </div>
            </div>
        </div>

        <!-- Wiki Storage Section -->
        <div class="mt-8 bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl rounded-2xl shadow-xl shadow-[var(--theme-color-10)] border border-gray-800 overflow-hidden">
            <div class="p-8 lg:p-10 border-b border-gray-800 flex justify-between items-center bg-gray-950/80">
                <div>
                    <h2 class="text-2xl font-black text-[var(--theme-color)] tracking-widest uppercase flex items-center gap-3">
                        <x-heroicon-o-folder-open class="w-8 h-8 text-[var(--theme-color)]" />
                        Datei Speicher
                    </h2>
                    <p class="text-gray-400 mt-2 text-xs font-bold uppercase tracking-widest">Lade PDFs, TXT, DOCX oder CSV in die Agenten-Vektordatenbank.</p>
                </div>
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 border border-gray-800 bg-gray-950 px-4 py-2 rounded-lg shadow-inner flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[var(--theme-color)] animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    <span class="text-[var(--theme-color)]" x-text="$wire.uploadedWikiFiles.length"></span> Dateien indiziert
                </div>
            </div>

            <div class="p-8 lg:p-10 flex flex-col lg:flex-row gap-8 bg-gray-950">
                <!-- Dropzone -->
                <div class="w-full lg:w-1/3">
                    <div
                        x-data="{ isDropping: false }"
                        x-on:dragover.prevent="isDropping = true"
                        x-on:dragleave.prevent="isDropping = false"
                        x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('wikiFiles', $event.dataTransfer.files)"
                        :class="isDropping ? 'border-[var(--theme-color)] bg-[var(--theme-color-10)] shadow-[inset_0_0_30px_var(--theme-color-20)]' : 'border-gray-800 hover:border-emerald-700/60 bg-gray-950'"
                        class="border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center text-center transition-all cursor-pointer relative h-full min-h-[250px]"
                    >
                        <input type="file" wire:model="wikiFiles" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <x-heroicon-o-cloud-arrow-up class="w-16 h-16 text-gray-500 mb-5 transition-colors" x-bind:class="isDropping ? 'text-[var(--theme-color)]' : ''" />
                        <h3 class="text-[var(--theme-color)] font-black tracking-widest uppercase text-sm mb-2">Dateien hochladen</h3>
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-bold">Drag & Drop oder Klicken</p>

                        <div wire:loading wire:target="wikiFiles" class="mt-6 w-full">
                            <div class="flex items-center justify-center gap-3 text-[var(--theme-color)] text-[10px] font-black uppercase tracking-widest border border-[var(--theme-color-30)] bg-[var(--theme-color-10)] py-2 rounded-lg shadow-inner">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Lade hoch...
                            </div>
                        </div>

                        @if (session()->has('message'))
                            <div class="mt-6 text-[var(--theme-color)] text-[10px] uppercase tracking-widest font-black bg-[var(--theme-color-10)] px-4 py-2 rounded-lg border border-[var(--theme-color-30)] shadow-xl shadow-[var(--theme-color-10)] w-full">
                                {{ session('message') }}
                            </div>
                        @endif
                        @error('wikiFiles.*') <span class="mt-4 text-red-500 text-[10px] uppercase tracking-widest font-black block w-full">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- File List -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-gray-950 rounded-xl border border-gray-800 overflow-hidden h-full shadow-[inset_0_0_20px_var(--theme-color-10)]">
                        <div class="p-5 border-b border-gray-800 bg-gray-900/60 backdrop-blur-xl">
                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Indizierte Dateien</h3>
                        </div>
                        <div class="p-3 max-h-[300px] overflow-y-auto custom-scrollbar">
                            @forelse($uploadedWikiFiles as $file)
                                <div class="flex items-center justify-between p-4 hover:bg-gray-950 border border-transparent hover:border-gray-800 rounded-xl transition-all group shadow-inner">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="w-10 h-10 rounded-lg bg-gray-950 border border-gray-800 flex items-center justify-center shrink-0 shadow-[inset_0_0_10px_var(--theme-color-5)]">
                                            <x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-gray-400" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-[var(--theme-color)] truncate max-w-[200px] sm:max-w-[300px]">{{ $file['name'] }}</p>
                                            <p class="text-[10px] uppercase font-black tracking-widest text-gray-400 mt-1 flex gap-2 items-center">
                                                <span>{{ round($file['size'] / 1024, 1) }} KB</span>
                                                <span class="w-1 h-1 bg-emerald-800 rounded-full"></span>
                                                <span>{{ date('d.m.y H:i', $file['time']) }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <button wire:click="deleteWikiFile('{{ $file['name'] }}')" wire:confirm="Sicher, dass du diese Datei aus dem Vektorspeicher löschen möchtest?" class="p-2.5 text-gray-500 hover:text-red-500 hover:bg-red-500/10 border border-transparent hover:border-red-500/30 rounded-lg transition-all opacity-0 group-hover:opacity-100 shadow-inner shrink-0">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            @empty
                                <div class="p-10 text-center text-gray-500">
                                    <x-heroicon-o-cube-transparent class="w-12 h-12 mx-auto mb-4 opacity-30" />
                                    <p class="text-[10px] font-black uppercase tracking-widest">Keine Dateien gespeichert</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- INFOGRAFIK: RAG ARCHITECTURE (MATRIX THEME) -->
        <div class="mt-8 bg-gray-900/80 backdrop-blur-xl backdrop-blur-xl border border-gray-800 rounded-2xl p-6 lg:p-10 flex flex-col lg:flex-row gap-12 items-center shadow-xl shadow-[var(--theme-color-10)]">
            <div class="relative w-full max-w-md shrink-0">
                <svg viewBox="0 0 300 250" class="w-full h-auto drop-shadow-xl shadow-[var(--theme-color-10)] font-mono" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <marker id="arrowUpMatrix" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="var(--theme-color)" /></marker>
                        <marker id="arrowUpGrayM" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="var(--theme-color-30)" /></marker>
                        <linearGradient id="dbGradM" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--theme-color-15)"/>
                            <stop offset="100%" stop-color="var(--theme-color-10)"/>
                        </linearGradient>
                    </defs>

                    <!-- ZENTRALE DB (BOTTOM) -->
                    <path d="M60 210 L60 230 A90 10 0 0 0 240 230 L240 210 Z" fill="url(#dbGradM)" stroke="var(--theme-color)" stroke-width="2"/>
                    <ellipse cx="150" cy="210" rx="90" ry="10" fill="var(--theme-color-10)" stroke="var(--theme-color)" stroke-width="2"/>
                    <text x="150" y="214" fill="var(--theme-color-80)" font-size="8" font-weight="900" text-anchor="middle" letter-spacing="2">Zentrale Vektor-DB</text>
                    <text x="150" y="232" fill="var(--theme-color-50)" font-size="6" font-weight="bold" text-anchor="middle" letter-spacing="1">WISSENSDATENBANK</text>

                    <!-- ARROWS UP TO FILTER -->
                    <line x1="150" y1="200" x2="150" y2="155" stroke="var(--theme-color-30)" stroke-width="2" marker-end="url(#arrowUpGrayM)"/>

                    <!-- TAGS / CATEGORIES (MIDDLE) -->
                    <rect x="70" y="125" width="160" height="30" rx="4" fill="var(--theme-color-5)" stroke="var(--theme-color-50)" stroke-width="1.5" stroke-dasharray="4"/>
                    <text x="150" y="143" fill="var(--theme-color-80)" font-size="7.5" font-weight="900" text-anchor="middle" letter-spacing="2">Kategorien & Tags</text>

                    <!-- ARROWS UP TO AGENTS -->
                    <line x1="100" y1="125" x2="60" y2="70" stroke="var(--theme-color)" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>
                    <line x1="150" y1="125" x2="150" y2="70" stroke="var(--theme-color)" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>
                    <line x1="200" y1="125" x2="240" y2="70" stroke="var(--theme-color)" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>

                    <!-- AGENTS (TOP) -->
                    <circle cx="60" cy="40" r="25" fill="var(--theme-color-15)" stroke="var(--theme-color)" stroke-width="2.5"/>
                    <circle cx="60" cy="40" r="15" fill="none" stroke="var(--theme-color-80)" stroke-width="1.5" stroke-dasharray="2" opacity="0.6"/>
                    <text x="60" y="43" fill="var(--theme-color)" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">AGENT_01</text>
                    <text x="60" y="78" fill="var(--theme-color-50)" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">NODE_01</text>

                    <circle cx="150" cy="40" r="25" fill="var(--theme-color-10)" stroke="var(--theme-color-50)" stroke-width="2"/>
                    <circle cx="150" cy="40" r="15" fill="none" stroke="var(--theme-color)" stroke-width="1" stroke-dasharray="2" opacity="0.4"/>
                    <text x="150" y="43" fill="var(--theme-color-80)" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">AGENT_02</text>
                    <text x="150" y="78" fill="var(--theme-color-30)" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">NODE_02</text>

                    <circle cx="240" cy="40" r="25" fill="var(--theme-color-10)" stroke="var(--theme-color-50)" stroke-width="2"/>
                    <circle cx="240" cy="40" r="15" fill="none" stroke="var(--theme-color)" stroke-width="1" stroke-dasharray="2" opacity="0.4"/>
                    <text x="240" y="43" fill="var(--theme-color-80)" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">AGENT_03</text>
                    <text x="240" y="78" fill="var(--theme-color-30)" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">NODE_03</text>
                </svg>
            </div>
            <div class="flex-1 space-y-6">
                <h5 class="text-lg font-black tracking-widest uppercase text-[var(--theme-color)] flex items-center gap-3 border-b border-gray-800 pb-4">
                    <x-heroicon-o-command-line class="w-6 h-6 text-[var(--theme-color)]" />
                    RAG Architektur
                </h5>
                <div class="space-y-5 font-mono">
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-xl shadow-[var(--theme-color-10)]">1</span>
                        <div>
                            <strong class="text-[var(--theme-color)] block mb-1 uppercase tracking-widest text-xs">Single Source of Truth</strong>
                            <p class="text-xs text-gray-400 leading-relaxed">Alle Informationen (Text & Datei-Uploads) liegen zentral in einer einzigen Datenbank. Es gibt keine redundanten Speichertabellen für verschiedene Agenten. Globale Sync-Integrety ist gewährleistet.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-xl shadow-[var(--theme-color-10)]">2</span>
                        <div>
                            <strong class="text-[var(--theme-color)] block mb-1 uppercase tracking-widest text-xs">Dynamisches Routing</strong>
                            <p class="text-xs text-gray-400 leading-relaxed">Agenten rufen das Wissen über die vergebenen Kategorien & Tags per Eloquent Pivot ab. Wenn ein Kunde im Chat nach Versandkosten fragt, lockt sich der Support-Agent automatisch nur auf Artikel mit passenden Vektor-IDs ein.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-[var(--theme-color-10)] border border-[var(--theme-color-30)] text-[var(--theme-color)] flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-xl shadow-[var(--theme-color-10)]">3</span>
                         <div>
                            <strong class="text-[var(--theme-color)] block mb-1 uppercase tracking-widest text-xs">Endlose Skalierbarkeit</strong>
                            <p class="text-xs text-gray-400 leading-relaxed">Egal ob 1 oder 256 Agenten: Jeder liest aus demselben Core, aber isoliert im Rahmen seiner Firewall-Policies und Kategorie-Zuweisung. Vector Embedding Engine bleibt pfeilschnell.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar { width: 4px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--theme-color-40); border-radius: 4px; }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: var(--theme-color-80); }

            ::selection { background: var(--theme-color-30); color: #6ee7b7; }
        </style>
    </div>
</div>

</div>
