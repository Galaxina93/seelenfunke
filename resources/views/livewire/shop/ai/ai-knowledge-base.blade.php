<div>
    <div class="animate-fade-in-up font-mono antialiased text-emerald-600 pb-12 w-full">
        <div class="mb-12 text-center mt-4 font-mono">
            <h1 class="text-3xl sm:text-4xl font-black text-primary tracking-widest uppercase shadow-primary/20 drop-shadow-md">
                Wissensdatenbank & KI-Core
            </h1>
            <p class="text-gray-400 mt-2 text-sm uppercase tracking-widest">
                Das Gehirn von Mein Seelenfunke. Dokumentation, Fachbegriffe und Trainingsdaten.
            </p>
        </div>
        <div class="flex justify-end mb-8 relative z-10">
            <div class="bg-gray-950 p-2 rounded-xl border border-emerald-900/50 shadow-inner flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(16,185,129,0.2)]">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    KI-Sync Bereit
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row h-[calc(100vh-18rem)] min-h-[600px] bg-black/95 backdrop-blur-xl rounded-2xl shadow-[0_0_40px_rgba(16,185,129,0.05)] border border-emerald-900/40 overflow-hidden">

            <div class="w-full lg:w-1/3 xl:w-1/4 bg-gray-950/80 border-b lg:border-b-0 lg:border-r border-emerald-900/40 flex flex-col shrink-0 z-10 shadow-inner">
                <div class="p-6 border-b border-emerald-900/40">
                    <div class="relative group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Wissen durchsuchen..." class="w-full pl-11 pr-4 py-3.5 bg-black border border-emerald-900/50 rounded-xl text-sm font-bold text-emerald-400 focus:bg-gray-950 focus:ring-1 focus:ring-emerald-500/30 focus:border-emerald-500 shadow-[inset_0_0_15px_rgba(16,185,129,0.05)] outline-none transition-all placeholder-emerald-900/50 uppercase tracking-widest">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-emerald-800 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-emerald-500 transition-colors" />
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-emerald-900/40 flex overflow-x-auto custom-scrollbar gap-2 shrink-0">
                    <button wire:click="setCategory('')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategoryId === null ? 'bg-emerald-500/10 border-emerald-500/50 text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.2)]' : 'bg-black text-emerald-700 border border-emerald-900/40 hover:text-emerald-500 hover:border-emerald-700/50' }}">
                        Alle
                    </button>
                    @foreach($categories as $cat)
                        <button wire:click="setCategory('{{$cat->id}}')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategoryId == $cat->id ? 'bg-emerald-500/10 border-emerald-500/50 text-emerald-400 shadow-[0_0_10px_rgba(16,185,129,0.2)]' : 'bg-black text-emerald-700 border border-emerald-900/40 hover:text-emerald-500 hover:border-emerald-700/50' }}">
                            {{$cat->name}}
                        </button>
                    @endforeach
                </div>

                <div class="p-3 border-b border-emerald-900/40 shrink-0">
                    <button wire:click="createNewArticle" class="w-full py-2.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-emerald-500/20 hover:border-emerald-500/60 shadow-[0_0_15px_rgba(16,185,129,0.1)] transition-all flex items-center justify-center gap-2">
                        <x-heroicon-o-plus class="w-4 h-4" /> Neuer Eintrag
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
                    @forelse($articles as $article)
                        <button wire:click="selectArticle('{{$article->id}}')" class="w-full text-left p-4 rounded-xl transition-all duration-200 border {{ $activeArticleId == $article->id ? 'bg-emerald-950/30 border-emerald-500/40 shadow-[inset_4px_0_0_rgba(16,185,129,1)]' : 'bg-transparent border-transparent hover:bg-black hover:border-emerald-900/40' }}">
                            <h4 class="text-sm font-bold tracking-wider truncate {{ $activeArticleId == $article->id ? 'text-emerald-400' : 'text-emerald-700' }}">{{ $article->title }}</h4>
                            <div class="flex flex-wrap gap-1.5 mt-2 h-5 overflow-hidden">
                                @if($article->tags)
                                    @foreach($article->tags->take(3) as $tag)
                                        <span class="text-[8px] font-bold uppercase tracking-widest text-emerald-600 bg-black px-1.5 py-0.5 rounded border border-emerald-900/50 shadow-inner">#{{$tag->name}}</span>
                                    @endforeach
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="text-center py-10 px-4 text-emerald-800/60">
                            <x-heroicon-o-document-magnifying-glass class="w-10 h-10 mx-auto mb-3 opacity-50" />
                            <p class="text-[10px] uppercase tracking-widest font-black">Keine Daten gefunden</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex-1 bg-transparent relative overflow-hidden flex flex-col">
                @if($isEditing)
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12 space-y-8">
                         <h2 class="text-2xl font-black text-emerald-400 uppercase tracking-widest border-b border-emerald-900/40 pb-4">{{ $editForm['id'] ? 'Eintrag bearbeiten' : 'Neuen Eintrag erstellen' }}</h2>

                         <div class="space-y-6">
                             <div class="bg-black p-6 rounded-xl border border-emerald-900/50 shadow-inner">
                                 <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-emerald-600 mb-3">Titel</label>
                                 <input type="text" wire:model="editForm.title" placeholder="Eindeutiger Titel..." class="w-full bg-gray-950 border border-emerald-900/50 rounded-lg text-emerald-400 font-bold px-4 py-3 focus:ring-1 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none shadow-[inset_0_0_10px_rgba(16,185,129,0.05)] transition-all">
                                 @error('editForm.title') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block">{{ $message }}</span> @enderror
                             </div>
                             
                             <div class="flex flex-col lg:flex-row gap-6 w-full">
                                 <div class="flex-1 w-full min-w-0">
                                     <livewire:shop.ai.ai-knowledge-base-categories :selectedCategoryId="$editForm['ai_knowledge_base_category_id']" wire:key="kb-categories-{{ $editForm['id'] ?? 'new' }}" />
                                     @error('editForm.ai_knowledge_base_category_id') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block pl-2">{{ $message }}</span> @enderror
                                 </div>
                                 <div class="flex-1 w-full min-w-0">
                                     <livewire:shop.ai.ai-knowledge-base-tags :selectedTagIds="$editForm['tags']" wire:key="kb-tags-{{ $editForm['id'] ?? 'new' }}" />
                                 </div>
                             </div>

                             <div class="bg-black p-6 rounded-xl border border-emerald-900/50 shadow-inner">
                                 <label class="block text-[10px] font-black uppercase tracking-[0.2em] text-emerald-600 mb-3">Artikelinhalt</label>
                                 <textarea wire:model="editForm.content" rows="15" class="w-full bg-gray-950 border border-emerald-900/50 rounded-lg text-emerald-300 px-4 py-4 focus:ring-1 focus:ring-emerald-500/30 focus:border-emerald-500 outline-none shadow-[inset_0_0_10px_rgba(16,185,129,0.05)] transition-all custom-scrollbar font-mono text-sm leading-relaxed"></textarea>
                                 @error('editForm.content') <span class="text-red-500 text-[10px] uppercase tracking-widest mt-2 block">{{ $message }}</span> @enderror
                             </div>

                             <div class="flex gap-4 pt-4 border-t border-emerald-900/40">
                                 <button wire:click="saveArticle" class="px-8 py-3.5 bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 rounded-xl text-xs font-black uppercase tracking-widest hover:bg-emerald-500 hover:text-black hover:shadow-[0_0_20px_rgba(16,185,129,0.4)] transition-all flex items-center gap-2">
                                     <x-heroicon-o-check class="w-5 h-5"/> Speichern
                                 </button>
                                 <button wire:click="cancelEditing" class="px-8 py-3.5 bg-black border border-emerald-900/50 text-emerald-700 rounded-xl text-xs font-black uppercase tracking-widest hover:border-emerald-700 hover:text-emerald-500 transition-all">
                                     Abbrechen
                                 </button>
                             </div>
                         </div>
                    </div>
                @elseif($activeArticle)
                    <div class="p-8 lg:p-12 pb-6 border-b border-emerald-900/40 bg-black/60 shrink-0 relative">
                        <div class="absolute top-8 right-8 flex gap-2">
                             <button wire:click="editArticle('{{ $activeArticle->id }}')" class="p-2.5 bg-black border border-emerald-900/50 hover:bg-emerald-500/10 hover:text-emerald-400 hover:border-emerald-500/50 text-emerald-700 rounded-xl transition-all shadow-inner" title="Bearbeiten">
                                 <x-heroicon-o-pencil class="w-5 h-5" />
                             </button>
                             <button wire:click="deleteArticle('{{ $activeArticle->id }}')" wire:confirm="Sicher, dass du diesen Eintrag endgültig löschen möchtest?" class="p-2.5 bg-black border border-emerald-900/50 hover:bg-red-500/10 hover:text-red-500 hover:border-red-500/50 text-emerald-800/60 rounded-xl transition-all shadow-inner" title="Löschen">
                                 <x-heroicon-o-trash class="w-5 h-5" />
                             </button>
                        </div>
                        @if($activeArticle->category)
                            <span class="inline-block px-3 py-1 bg-emerald-500/10 border border-emerald-500/30 rounded-md text-[9px] font-black uppercase tracking-widest text-emerald-400 mb-4 shadow-[0_0_10px_rgba(16,185,129,0.1)]">
                                {{$activeArticle->category->name}}
                            </span>
                        @endif
                        <h2 class="text-3xl lg:text-4xl font-black text-emerald-500 tracking-widest uppercase mb-6">{{$activeArticle->title}}</h2>

                        @if($activeArticle->tags && count($activeArticle->tags) > 0)
                            <div class="flex flex-wrap gap-2.5">
                                @foreach($activeArticle->tags as $tag)
                                    <span class="text-[10px] font-bold text-emerald-400 bg-black border border-emerald-900/50 px-3 py-1.5 rounded-lg shadow-inner flex items-center gap-1.5 uppercase tracking-wider">
                                        <span class="text-emerald-600">#</span> {{$tag->name}}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12">
                        <style>
                            .kb-content h3 { color: #34d399; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; letter-spacing: 0.1em; text-transform: uppercase; font-size: 1.2rem; font-weight: 900; margin-top: 2.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(6, 78, 59, 0.5); padding-bottom: 0.5rem; }
                            .kb-content h3:first-child { margin-top: 0; }
                            .kb-content p { color: #a7f3d0; font-size: 0.95rem; line-height: 1.8; margin-bottom: 1.5rem; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif; }
                            .kb-content strong { color: #10b981; font-weight: 800; background: rgba(16, 185, 129, 0.1); padding: 0.1rem 0.4rem; border-radius: 0.25rem; border: 1px solid rgba(16, 185, 129, 0.2); }
                            .kb-content ul { color: #a7f3d0; list-style-type: square; padding-left: 1.5rem; margin-bottom: 1.5rem; font-family: ui-sans-serif, system-ui, -apple-system; }
                            .kb-content li { margin-bottom: 0.5rem; }
                            .kb-content li::marker { color: #059669; }
                        </style>

                        <div class="kb-content max-w-4xl text-emerald-300">
                            {!! $activeArticle->content !!}
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t border-emerald-900/40 bg-black text-[9px] font-black uppercase tracking-widest text-emerald-700 flex justify-between items-center shrink-0">
                        <span class="flex items-center gap-2"><div class="w-1.5 h-1.5 bg-emerald-700 rounded-full"></div> ID: {{ substr($activeArticle->id, 0, 8) }}</span>
                        <span>Letzte Änderung: {{$activeArticle->updated_at->format('d.m.Y H:i:s')}}</span>
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-emerald-800/50 p-10 text-center animate-pulse-slow">
                        <div class="w-24 h-24 bg-black border border-emerald-900/40 rounded-full flex items-center justify-center shadow-[inset_0_0_20px_rgba(16,185,129,0.05)] mb-6">
                            <x-heroicon-o-server-stack class="w-10 h-10 text-emerald-800" />
                        </div>
                        <h3 class="text-xl font-bold font-mono tracking-widest uppercase text-emerald-700 mb-2">System bereit</h3>
                        <p class="text-xs font-bold uppercase tracking-widest">Warte auf Eingabe.<br>Wähle einen Eintrag links oder erstelle einen neuen.</p>
                    </div>
                @endif
            </div>
            </div>
        </div>

        <!-- Wiki Storage Section -->
        <div class="mt-8 bg-black/95 backdrop-blur-xl rounded-2xl shadow-[0_0_40px_rgba(16,185,129,0.05)] border border-emerald-900/40 overflow-hidden">
            <div class="p-8 lg:p-10 border-b border-emerald-900/40 flex justify-between items-center bg-gray-950/80">
                <div>
                    <h2 class="text-2xl font-black text-emerald-500 tracking-widest uppercase flex items-center gap-3">
                        <x-heroicon-o-folder-open class="w-8 h-8 text-emerald-400" />
                        Datei Speicher
                    </h2>
                    <p class="text-emerald-700 mt-2 text-xs font-bold uppercase tracking-widest">Lade PDFs, TXT, DOCX oder CSV in die Agenten-Vektordatenbank.</p>
                </div>
                <div class="text-[10px] font-black uppercase tracking-widest text-emerald-700 border border-emerald-900/50 bg-black px-4 py-2 rounded-lg shadow-inner flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    <span class="text-emerald-400" x-text="$wire.uploadedWikiFiles.length"></span> Dateien indiziert
                </div>
            </div>

            <div class="p-8 lg:p-10 flex flex-col lg:flex-row gap-8 bg-black">
                <!-- Dropzone -->
                <div class="w-full lg:w-1/3">
                    <div
                        x-data="{ isDropping: false }"
                        x-on:dragover.prevent="isDropping = true"
                        x-on:dragleave.prevent="isDropping = false"
                        x-on:drop.prevent="isDropping = false; $wire.uploadMultiple('wikiFiles', $event.dataTransfer.files)"
                        :class="isDropping ? 'border-emerald-500 bg-emerald-500/10 shadow-[inset_0_0_30px_rgba(16,185,129,0.2)]' : 'border-emerald-900/50 hover:border-emerald-700/60 bg-gray-950'"
                        class="border-2 border-dashed rounded-xl p-10 flex flex-col items-center justify-center text-center transition-all cursor-pointer relative h-full min-h-[250px]"
                    >
                        <input type="file" wire:model="wikiFiles" multiple class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                        <x-heroicon-o-cloud-arrow-up class="w-16 h-16 text-emerald-800/60 mb-5 transition-colors" x-bind:class="isDropping ? 'text-emerald-400' : ''" />
                        <h3 class="text-emerald-500 font-black tracking-widest uppercase text-sm mb-2">Dateien hochladen</h3>
                        <p class="text-[10px] text-emerald-700 uppercase tracking-widest font-bold">Drag & Drop oder Klicken</p>

                        <div wire:loading wire:target="wikiFiles" class="mt-6 w-full">
                            <div class="flex items-center justify-center gap-3 text-emerald-400 text-[10px] font-black uppercase tracking-widest border border-emerald-500/30 bg-emerald-500/10 py-2 rounded-lg shadow-inner">
                                <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Lade hoch...
                            </div>
                        </div>

                        @if (session()->has('message'))
                            <div class="mt-6 text-emerald-400 text-[10px] uppercase tracking-widest font-black bg-emerald-500/10 px-4 py-2 rounded-lg border border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.1)] w-full">
                                {{ session('message') }}
                            </div>
                        @endif
                        @error('wikiFiles.*') <span class="mt-4 text-red-500 text-[10px] uppercase tracking-widest font-black block w-full">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- File List -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-gray-950 rounded-xl border border-emerald-900/40 overflow-hidden h-full shadow-[inset_0_0_20px_rgba(16,185,129,0.02)]">
                        <div class="p-5 border-b border-emerald-900/40 bg-black/60">
                            <h3 class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">Indizierte Dateien</h3>
                        </div>
                        <div class="p-3 max-h-[300px] overflow-y-auto custom-scrollbar">
                            @forelse($uploadedWikiFiles as $file)
                                <div class="flex items-center justify-between p-4 hover:bg-black border border-transparent hover:border-emerald-900/50 rounded-xl transition-all group shadow-inner">
                                    <div class="flex items-center gap-4 min-w-0">
                                        <div class="w-10 h-10 rounded-lg bg-black border border-emerald-900/50 flex items-center justify-center shrink-0 shadow-[inset_0_0_10px_rgba(16,185,129,0.05)]">
                                            <x-heroicon-o-document-magnifying-glass class="w-5 h-5 text-emerald-600" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-emerald-400 truncate max-w-[200px] sm:max-w-[300px]">{{ $file['name'] }}</p>
                                            <p class="text-[10px] uppercase font-black tracking-widest text-emerald-700 mt-1 flex gap-2 items-center">
                                                <span>{{ round($file['size'] / 1024, 1) }} KB</span>
                                                <span class="w-1 h-1 bg-emerald-800 rounded-full"></span>
                                                <span>{{ date('d.m.y H:i', $file['time']) }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <button wire:click="deleteWikiFile('{{ $file['name'] }}')" wire:confirm="Sicher, dass du diese Datei aus dem Vektorspeicher löschen möchtest?" class="p-2.5 text-emerald-800/60 hover:text-red-500 hover:bg-red-500/10 border border-transparent hover:border-red-500/30 rounded-lg transition-all opacity-0 group-hover:opacity-100 shadow-inner shrink-0">
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            @empty
                                <div class="p-10 text-center text-emerald-800/50">
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
        <div class="mt-8 bg-black/90 backdrop-blur-xl border border-emerald-900/50 rounded-2xl p-6 lg:p-10 flex flex-col lg:flex-row gap-12 items-center shadow-[0_0_40px_rgba(16,185,129,0.05)]">
            <div class="relative w-full max-w-md shrink-0">
                <svg viewBox="0 0 300 250" class="w-full h-auto drop-shadow-[0_0_15px_rgba(16,185,129,0.2)] font-mono" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <marker id="arrowUpMatrix" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#10b981" /></marker>
                        <marker id="arrowUpGrayM" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#064e3b" /></marker>
                        <linearGradient id="dbGradM" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="rgba(16, 185, 129, 0.15)"/>
                            <stop offset="100%" stop-color="rgba(16, 185, 129, 0.02)"/>
                        </linearGradient>
                    </defs>

                    <!-- ZENTRALE DB (BOTTOM) -->
                    <path d="M60 210 L60 230 A90 10 0 0 0 240 230 L240 210 Z" fill="url(#dbGradM)" stroke="#10b981" stroke-width="2"/>
                    <ellipse cx="150" cy="210" rx="90" ry="10" fill="rgba(16, 185, 129, 0.08)" stroke="#10b981" stroke-width="2"/>
                    <text x="150" y="214" fill="#34d399" font-size="8" font-weight="900" text-anchor="middle" letter-spacing="2">Zentrale Vektor-DB</text>
                    <text x="150" y="232" fill="#059669" font-size="6" font-weight="bold" text-anchor="middle" letter-spacing="1">WISSENSDATENBANK</text>

                    <!-- ARROWS UP TO FILTER -->
                    <line x1="150" y1="200" x2="150" y2="155" stroke="#064e3b" stroke-width="2" marker-end="url(#arrowUpGrayM)"/>
                    
                    <!-- TAGS / CATEGORIES (MIDDLE) -->
                    <rect x="70" y="125" width="160" height="30" rx="4" fill="rgba(16,185,129,0.05)" stroke="#059669" stroke-width="1.5" stroke-dasharray="4"/>
                    <text x="150" y="143" fill="#34d399" font-size="7.5" font-weight="900" text-anchor="middle" letter-spacing="2">Kategorien & Tags</text>

                    <!-- ARROWS UP TO AGENTS -->
                    <line x1="100" y1="125" x2="60" y2="70" stroke="#10b981" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>
                    <line x1="150" y1="125" x2="150" y2="70" stroke="#10b981" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>
                    <line x1="200" y1="125" x2="240" y2="70" stroke="#10b981" stroke-width="1.5" stroke-dasharray="2" marker-end="url(#arrowUpMatrix)"/>

                    <!-- AGENTS (TOP) -->
                    <circle cx="60" cy="40" r="25" fill="rgba(16, 185, 129, 0.15)" stroke="#10b981" stroke-width="2.5"/>
                    <circle cx="60" cy="40" r="15" fill="none" stroke="#34d399" stroke-width="1.5" stroke-dasharray="2" opacity="0.6"/>
                    <text x="60" y="43" fill="#6ee7b7" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">FUNKIRA</text>
                    <text x="60" y="78" fill="#059669" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">SYSTEM</text>

                    <circle cx="150" cy="40" r="25" fill="rgba(6, 78, 59, 0.4)" stroke="#059669" stroke-width="2"/>
                    <circle cx="150" cy="40" r="15" fill="none" stroke="#10b981" stroke-width="1" stroke-dasharray="2" opacity="0.4"/>
                    <text x="150" y="43" fill="#34d399" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">SALES</text>
                    <text x="150" y="78" fill="#064e3b" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">NODE_02</text>

                    <circle cx="240" cy="40" r="25" fill="rgba(6, 78, 59, 0.4)" stroke="#059669" stroke-width="2"/>
                    <circle cx="240" cy="40" r="15" fill="none" stroke="#10b981" stroke-width="1" stroke-dasharray="2" opacity="0.4"/>
                    <text x="240" y="43" fill="#34d399" font-size="7" font-weight="900" text-anchor="middle" letter-spacing="1">SUPPORT</text>
                    <text x="240" y="78" fill="#064e3b" font-size="6" font-weight="900" text-anchor="middle" letter-spacing="1">NODE_03</text>
                </svg>
            </div>
            <div class="flex-1 space-y-6">
                <h5 class="text-lg font-black tracking-widest uppercase text-emerald-400 flex items-center gap-3 border-b border-emerald-900/40 pb-4">
                    <x-heroicon-o-command-line class="w-6 h-6 text-emerald-500" />
                    RAG Architektur
                </h5>
                <div class="space-y-5 font-mono">
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-[0_0_10px_rgba(16,185,129,0.2)]">1</span>
                        <div>
                            <strong class="text-emerald-400 block mb-1 uppercase tracking-widest text-xs">Single Source of Truth</strong>
                            <p class="text-xs text-emerald-700 leading-relaxed">Alle Informationen (Text & Datei-Uploads) liegen zentral in einer einzigen Datenbank. Es gibt keine redundanten Speichertabellen für verschiedene Agenten. Globale Sync-Integrety ist gewährleistet.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-[0_0_10px_rgba(16,185,129,0.2)]">2</span>
                        <div>
                            <strong class="text-emerald-400 block mb-1 uppercase tracking-widest text-xs">Dynamisches Routing</strong>
                            <p class="text-xs text-emerald-700 leading-relaxed">Agenten rufen das Wissen über die vergebenen Kategorien & Tags per Eloquent Pivot ab. Wenn ein Kunde im Chat nach Versandkosten fragt, lockt sich der Support-Agent automatisch nur auf Artikel mit passenden Vektor-IDs ein.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <span class="w-6 h-6 rounded bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-[10px] font-black shrink-0 mt-0.5 shadow-[0_0_10px_rgba(16,185,129,0.2)]">3</span>
                         <div>
                            <strong class="text-emerald-400 block mb-1 uppercase tracking-widest text-xs">Endlose Skalierbarkeit</strong>
                            <p class="text-xs text-emerald-700 leading-relaxed">Egal ob 1 oder 256 Agenten: Jeder liest aus demselben Core, aber isoliert im Rahmen seiner Firewall-Policies und Kategorie-Zuweisung. Vector Embedding Engine bleibt pfeilschnell.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar { width: 4px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(5, 150, 105, 0.4); border-radius: 4px; }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.8); }
            
            ::selection { background: rgba(16, 185, 129, 0.3); color: #6ee7b7; }
        </style>
    </div>
</div>
