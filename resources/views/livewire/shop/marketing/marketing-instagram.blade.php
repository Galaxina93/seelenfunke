<div style="--theme-color: {{ $this->themeColorHex }}; --theme-color-10: {{ $this->themeColorHex }}1A; --theme-color-15: {{ $this->themeColorHex }}26; --theme-color-20: {{ $this->themeColorHex }}33; --theme-color-30: {{ $this->themeColorHex }}4D; --theme-color-40: {{ $this->themeColorHex }}66; --theme-color-50: {{ $this->themeColorHex }}80; --theme-color-60: {{ $this->themeColorHex }}99; --theme-color-80: {{ $this->themeColorHex }}CC;">
<div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
    <!-- Header -->
    <div class="sm:flex sm:justify-between sm:items-center mb-8">
        <div class="mb-4 sm:mb-0">
            <h1 class="text-2xl md:text-3xl font-black text-[var(--theme-color)] uppercase tracking-widest drop-shadow-[0_0_10px_var(--theme-color-30)]">Instagram Generator</h1>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-mono">Marketingagent: {{ $agent ? $agent->name : 'KI' }}</p>
        </div>
        <div class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
            <button wire:click="createDraftPost" class="px-6 py-2.5 bg-[var(--theme-color)] hover:opacity-90 text-white rounded-xl text-sm font-black uppercase tracking-widest transition-all shadow-[0_0_15px_var(--theme-color-40)] flex items-center gap-2">
                <span wire:loading.remove wire:target="createDraftPost">
                    <x-heroicon-s-plus class="w-5 h-5" />
                </span>
                <span wire:loading wire:target="createDraftPost" class="animate-spin">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                </span>
                <span wire:loading.remove wire:target="createDraftPost">Neue Vorlage</span>
                <span wire:loading wire:target="createDraftPost">Erstelle...</span>
            </button>
        </div>
    </div>

    <!-- Agent Banner -->
    <div class="mb-8 w-full">
        @if($agent)
            <div class="relative bg-gradient-to-r from-gray-900 via-gray-950 to-gray-900 border border-[var(--theme-color-30)] rounded-2xl p-4 md:p-6 shadow-[0_0_40px_var(--theme-color-15)] overflow-hidden">
                <div class="absolute inset-0 bg-[var(--theme-color)] opacity-5"></div>
                <div class="relative z-10 flex flex-col sm:flex-row gap-4 sm:gap-6 items-start sm:items-center">
                    <div class="shrink-0 relative">
                        <div class="absolute -inset-2 bg-[var(--theme-color)] opacity-20 blur-xl rounded-full animate-pulse-slow"></div>
                        <div class="w-14 h-14 md:w-16 md:h-16 bg-gray-950 border-2 border-[var(--theme-color)] rounded-full flex items-center justify-center shadow-inner relative z-10 overflow-hidden">
                            @if($agent->profile_picture)
                                <img src="{{ \Illuminate\Support\Str::startsWith($agent->profile_picture, 'shop/') ? asset($agent->profile_picture) : Storage::url($agent->profile_picture) }}" alt="{{ $agent->name }}" class="w-full h-full object-cover">
                            @else
                                <x-heroicon-m-megaphone class="w-6 h-6 md:w-8 md:h-8 text-[var(--theme-color)]" />
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-3 mb-1.5">
                            <span class="text-[10px] sm:text-xs font-black uppercase text-[var(--theme-color)] tracking-widest bg-[var(--theme-color-10)] px-2 py-0.5 rounded border border-[var(--theme-color-30)]">
                                {{ $agent->name }} | SOCIAL MEDIA
                            </span>
                        </div>
                        <p class="text-sm md:text-base text-gray-300 font-serif leading-relaxed">
                            "Lass mich deine Instagram-Timeline übernehmen. Ich analysiere den Shop, vermeide Redundanzen und designe den perfekten Post zu deinen Trophäen und Geschenken. Mach es dir gemütlich!"
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($posts->isEmpty())
        <div class="py-12 text-center border-2 border-dashed border-gray-800 rounded-2xl bg-gray-900/50 w-full mt-6">
            <x-heroicon-o-camera class="w-12 h-12 text-gray-600 mx-auto mb-4" />
            <h3 class="text-lg font-bold text-gray-300">Deine Timeline ist noch leer</h3>
            <p class="text-gray-500 mt-2">Klick oben auf "Neue Vorlage", um einen frischen Platzhalter-Post zu erzeugen!</p>
        </div>
    @else
        <!-- Kachelansicht (Masonry / Grid) -->
        <div class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-6 space-y-6">
            @foreach($posts as $post)
                <div class="break-inside-avoid bg-gray-950 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl relative group pb-4 transition-transform hover:-translate-y-1">

                    <!-- Status Badge -->
                    <div class="absolute top-3 right-3 z-20">
                        @if($post->status === 'published')
                            <span class="px-2 py-1 bg-emerald-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg">Posted</span>
                        @else
                            <span class="px-2 py-1 bg-amber-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg">Bereit zum Post</span>
                        @endif
                    </div>

                    <!-- Post Image -->
                    <div class="aspect-square w-full bg-gray-900 relative overflow-hidden group-hover:opacity-90 transition-opacity">
                        <!-- Lade-Indikator während Upload -->
                        <div wire:loading wire:target="photos.{{ $post->id }}" class="absolute inset-0 bg-gray-950/80 z-30 flex flex-col items-center justify-center text-[var(--theme-color)] backdrop-blur-sm">
                            <x-heroicon-o-arrow-path class="w-8 h-8 animate-spin mb-2" />
                            <span class="text-[10px] font-black uppercase tracking-widest">Lade Bild...</span>
                        </div>

                        @if($post->image_url)
                            <!-- Wenn Bild da ist, Overlay für erneuten Upload versteckt unter opacity-0 -->
                            <img src="{{ route('admin.marketing-instagram.file', ['id' => $post->id]) }}" alt="Instagram Post Image" class="w-full h-full object-cover">

                            <label class="absolute inset-0 z-20 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 bg-gray-950/70 cursor-pointer transition-opacity backdrop-blur-sm">
                                <input type="file" class="hidden" wire:model.live="photos.{{ $post->id }}" accept="image/*">
                                <x-heroicon-o-arrow-up-tray class="w-8 h-8 text-white mb-2 shadow-lg" />
                                <span class="bg-[var(--theme-color)] text-white text-[10px] font-black px-3 py-1.5 rounded uppercase tracking-widest shadow-lg">Bild austauschen</span>
                            </label>
                        @else
                            <!-- Blank State mit Upload Area -->
                            <label class="w-full h-full flex flex-col items-center justify-center text-gray-500 hover:text-[var(--theme-color)] hover:bg-[var(--theme-color-10)] transition-colors cursor-pointer border-2 border-dashed border-gray-700 hover:border-[var(--theme-color-50)] m-2 rounded-xl" style="width: calc(100% - 16px); height: calc(100% - 16px);">
                                <input type="file" class="hidden" wire:model.live="photos.{{ $post->id }}" accept="image/*">
                                <x-heroicon-o-photo class="w-10 h-10 mb-2" />
                                <span class="text-[10px] uppercase font-black tracking-widest">Klick für Foto-Upload</span>
                                <span class="text-[8px] text-gray-600 mt-1 uppercase font-mono">Aus der Manufaktur</span>
                            </label>
                        @endif
                    </div>

                    <div class="bg-gray-900/80 px-3 py-1.5 border-b border-gray-800 flex items-center justify-between text-[9px] uppercase tracking-widest text-gray-500 font-mono">
                        <span class="flex items-center gap-1"><x-heroicon-o-server class="w-3 h-3 text-[var(--theme-color)]/70" /> Speicherort:</span>
                        <span class="text-gray-400 truncate max-w-[150px]" title="{{ $post->image_url }}">{{ Str::limit($post->image_url, 30) }}</span>
                    </div>

                    <!-- Post Content -->
                    <div class="p-5">
                        @if(!empty(trim($post->caption)))
                            <p class="text-sm text-gray-300 font-sans leading-relaxed whitespace-pre-wrap mb-4">{!! preg_replace('/(#\w+)/', '<span class="text-[var(--theme-color)] font-bold">$1</span>', e($post->caption)) !!}</p>
                        @endif

                        @if($post->image_url)
                            <div class="mb-4 mt-2">
                                <button wire:click="generateCaptionForPost('{{ $post->id }}')" class="w-full py-3 bg-[var(--theme-color-10)] hover:bg-[var(--theme-color)] border border-[var(--theme-color-30)] hover:border-[var(--theme-color)] rounded-xl text-xs font-black text-[var(--theme-color)] hover:text-white uppercase tracking-widest transition-all flex items-center justify-center gap-2 relative overflow-hidden group">
                                    <span wire:loading wire:target="generateCaptionForPost('{{ $post->id }}')" class="absolute inset-0 bg-[var(--theme-color)] text-white flex items-center justify-center gap-2">
                                        <x-heroicon-o-sparkles class="w-4 h-4 animate-ping" /> Generiere...
                                    </span>
                                    <x-heroicon-s-sparkles class="w-4 h-4" />
                                    @if(empty(trim($post->caption)))
                                        Lass {{ $agent ? $agent->name : 'KI' }} zaubern
                                    @else
                                        Erneut Zaubern
                                    @endif
                                </button>
                            </div>
                        @endif

                        <div class="text-[10px] text-gray-500 font-mono flex items-center justify-between mb-4">
                            <span>Erstellt: {{ $post->created_at->diffForHumans() }}</span>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-2 border-t border-gray-800 pt-4">
                            @if($post->status === 'published')
                                <button wire:click="togglePublishPost('{{ $post->id }}')" class="flex-1 py-2 bg-emerald-500/10 hover:bg-gray-800 border border-emerald-500/50 rounded-xl text-xs font-bold text-emerald-500 transition-colors">
                                    Gepostet ✓
                                </button>
                            @else
                                <button wire:click="togglePublishPost('{{ $post->id }}')" class="flex-1 py-2 bg-[var(--theme-color)] hover:opacity-80 border border-[var(--theme-color-50)] rounded-xl text-xs font-bold text-white transition-colors">
                                    Posten
                                </button>
                            @endif

                            <button wire:click="deletePost('{{ $post->id }}')" class="px-3 py-2 bg-gray-900 hover:bg-red-500/20 border border-gray-700 hover:border-red-500/50 rounded-xl text-xs font-bold text-red-500 transition-colors" title="Verwerfen">
                                <x-heroicon-o-trash class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

</div>
