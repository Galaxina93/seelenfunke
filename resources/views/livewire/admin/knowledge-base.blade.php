<div>
    <div class="animate-fade-in-up font-sans antialiased text-gray-300 pb-12 w-full">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 bg-gray-900/80 backdrop-blur-md p-6 sm:p-10 rounded-[2.5rem] shadow-2xl border border-gray-800 relative overflow-hidden mb-8">
            <div class="absolute top-0 right-0 p-8 opacity-10 blur-sm pointer-events-none">
                <x-heroicon-o-cpu-chip class="w-40 h-40 text-primary drop-shadow-[0_0_20px_rgba(197,160,89,1)]" />
            </div>
            <div class="relative z-10">
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-white tracking-tight">Wissensdatenbank & KI-Core</h1>
                <p class="text-gray-400 mt-2 text-sm font-medium">Das Gehirn von Mein Seelenfunke. Dokumentation, Fachbegriffe und Trainingsdaten.</p>
            </div>
            <div class="relative z-10 bg-gray-950 p-2 rounded-2xl border border-gray-800 shadow-inner flex items-center gap-3">
                <div class="flex items-center gap-2 px-3 py-1.5 bg-primary/10 text-primary border border-primary/20 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-[0_0_10px_rgba(197,160,89,0.2)]">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse shadow-[0_0_8px_currentColor]"></span>
                    KI Sync Ready
                </div>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row h-[calc(100vh-18rem)] min-h-[600px] bg-gray-900/80 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-gray-800 overflow-hidden">

            <div class="w-full lg:w-1/3 xl:w-1/4 bg-gray-950/50 border-b lg:border-b-0 lg:border-r border-gray-800 flex flex-col shrink-0 z-10 shadow-inner">
                <div class="p-6 border-b border-gray-800">
                    <div class="relative group">
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Wissen durchsuchen..." class="w-full pl-11 pr-4 py-3.5 bg-gray-900 border border-gray-800 rounded-xl text-sm text-white focus:bg-gray-950 focus:ring-2 focus:ring-primary/30 focus:border-primary shadow-inner outline-none transition-all placeholder-gray-500">
                        <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-500 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-primary transition-colors" />
                    </div>
                </div>

                <div class="px-6 py-4 border-b border-gray-800 flex overflow-x-auto no-scrollbar gap-2 shrink-0">
                    <button wire:click="setCategory('')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategory === '' ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'bg-gray-900 text-gray-500 border border-gray-800 hover:text-white' }}">
                        Alle
                    </button>
                    @foreach($categories as $cat)
                        <button wire:click="setCategory('{{$cat}}')" class="whitespace-nowrap px-4 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all {{ $selectedCategory === $cat ? 'bg-primary text-gray-900 shadow-[0_0_10px_rgba(197,160,89,0.3)]' : 'bg-gray-900 text-gray-500 border border-gray-800 hover:text-white' }}">
                            {{$cat}}
                        </button>
                    @endforeach
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-1">
                    @forelse($articles as $article)
                        <button wire:click="selectArticle('{{$article->id}}')" class="w-full text-left p-4 rounded-2xl transition-all duration-200 border {{ $activeArticleId == $article->id ? 'bg-gray-800/80 border-primary/30 shadow-[inset_4px_0_0_rgba(197,160,89,1)]' : 'bg-transparent border-transparent hover:bg-gray-900/50 hover:border-gray-800' }}">
                            <h4 class="text-sm font-bold truncate {{ $activeArticleId == $article->id ? 'text-white' : 'text-gray-400' }}">{{ $article->title }}</h4>
                            <div class="flex flex-wrap gap-1 mt-2 h-4 overflow-hidden">
                                @if($article->tags)
                                    @foreach(array_slice($article->tags, 0, 3) as $tag)
                                        <span class="text-[8px] font-bold uppercase tracking-wider text-gray-600 bg-gray-950 px-1.5 py-0.5 rounded border border-gray-800">{{$tag}}</span>
                                    @endforeach
                                @endif
                            </div>
                        </button>
                    @empty
                        <div class="text-center py-10 px-4 text-gray-600">
                            <x-heroicon-o-document-magnifying-glass class="w-10 h-10 mx-auto mb-3 opacity-50" />
                            <p class="text-xs uppercase tracking-widest font-black">Kein Wissen gefunden</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="flex-1 bg-transparent relative overflow-hidden flex flex-col">
                @if($activeArticle)
                    <div class="p-8 lg:p-12 pb-6 border-b border-gray-800 bg-gray-900/30 shrink-0">
                    <span class="inline-block px-3 py-1 bg-gray-800 border border-gray-700 rounded-md text-[9px] font-black uppercase tracking-widest text-primary mb-4 shadow-inner">
                        {{$activeArticle->category}}
                    </span>
                        <h2 class="text-3xl lg:text-4xl font-serif font-bold text-white tracking-tight mb-4">{{$activeArticle->title}}</h2>

                        @if($activeArticle->tags)
                            <div class="flex flex-wrap gap-2">
                                @foreach($activeArticle->tags as $tag)
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-950 border border-gray-800 px-3 py-1.5 rounded-lg shadow-inner flex items-center gap-1.5">
                                    <span class="text-primary">#</span> {{$tag}}
                                </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-8 lg:p-12">
                        <style>
                            .kb-content h3 { color: #fff; font-family: ui-serif, Georgia, serif; font-size: 1.5rem; font-weight: bold; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid #374151; padding-bottom: 0.5rem; }
                            .kb-content h3:first-child { margin-top: 0; }
                            .kb-content p { color: #9ca3af; font-size: 1rem; line-height: 1.8; margin-bottom: 1.5rem; }
                            .kb-content strong { color: #e5e7eb; font-weight: 700; background: rgba(255,255,255,0.05); padding: 0.1rem 0.3rem; border-radius: 0.25rem; }
                            .kb-content ul { color: #9ca3af; list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.5rem; }
                            .kb-content li { margin-bottom: 0.5rem; }
                        </style>

                        <div class="kb-content max-w-3xl">
                            {!! $activeArticle->content !!}
                        </div>
                    </div>

                    <div class="px-8 py-4 border-t border-gray-800 bg-gray-950/80 text-[9px] font-black uppercase tracking-widest text-gray-600 flex justify-between items-center shrink-0">
                        <span>ID: {{str_pad($activeArticle->id, 4, '0', STR_PAD_LEFT)}}</span>
                        <span>Letztes Update: {{$activeArticle->updated_at->format('d.m.Y H:i')}}</span>
                    </div>
                @else
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-500 p-10 text-center">
                        <div class="w-24 h-24 bg-gray-950 border border-gray-800 rounded-full flex items-center justify-center shadow-inner mb-6">
                            <x-heroicon-o-book-open class="w-12 h-12 text-gray-700" />
                        </div>
                        <h3 class="text-xl font-serif font-bold text-white mb-2">Wissensdatenbank</h3>
                        <p class="text-sm font-medium">Wähle links einen Eintrag aus oder erstelle einen neuen, <br>um deine KI-Wissensbasis zu erweitern.</p>
                    </div>
                @endif
            </div>
        </div>

        <style>
            .custom-scrollbar::-webkit-scrollbar { width: 6px; }
            .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
            .custom-scrollbar::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #C5A059; }
        </style>
    </div>
</div>
