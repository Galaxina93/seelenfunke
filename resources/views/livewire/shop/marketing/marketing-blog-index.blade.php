<div>
    @if(!empty($blogIndexSchema))
        @push('structured_data')
            <script type="application/ld+json">{!! json_encode($blogIndexSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endpush
    @endif
    <div class="bg-white min-h-screen">

        {{-- Header / Hero Section (Analog zur Kollektion) --}}
        <div class="bg-[#F5F1E8]/60 border-b border-[#D8C9A8]/60 py-16 md:py-20 text-center px-4">
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-4">
                Magazin & Ratgeber
            </h1>
            <p class="text-gray-600 max-w-2xl mx-auto text-lg leading-relaxed">
                Zeitlose Inspiration für Gravursprüche, Materialkunde zu 585er Echtgold, 925er Silber & K9-Kristall sowie Einblicke in unsere Gifhorner Manufaktur.
            </p>
        </div>

        {{-- Main Content --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

            {{-- FILTER & SUCHE --}}
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8 mb-16">

                {{-- Kategorien (Pills) --}}
                <div class="flex flex-wrap gap-3">
                    <button
                        wire:click="setCategory('')"
                        class="px-5 py-2.5 rounded-full text-sm font-bold transition-all duration-300 border cursor-pointer
                        {{ $categorySlug == ''
                            ? 'bg-[#F5F1E8] text-gray-900 border-[#D8C9A8] shadow-sm transform -translate-y-0.5'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-[#D8C9A8] hover:text-gray-900' }}">
                        Alle Beiträge
                    </button>
                    @foreach($categories as $cat)
                        <button
                            wire:click="setCategory('{{ $cat->slug }}')"
                            class="px-5 py-2.5 rounded-full text-sm font-bold transition-all duration-300 border cursor-pointer
                            {{ $categorySlug == $cat->slug
                                ? 'bg-[#F5F1E8] text-gray-900 border-[#D8C9A8] shadow-sm transform -translate-y-0.5'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#D8C9A8] hover:text-gray-900' }}">
                            {{ $cat->name }}
                        </button>
                    @endforeach
                </div>

                {{-- Suche (Prominent & Sichtbar) --}}
                <div class="relative w-full lg:w-96 group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400 group-focus-within:text-primary transition-colors duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="Wonach suchst du heute?"
                        class="block w-full pl-12 pr-4 py-3.5 bg-white border-2 border-gray-200 rounded-xl leading-5 placeholder-gray-400 focus:outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all duration-300 text-gray-900 font-medium shadow-sm hover:border-gray-300"
                    >
                </div>
            </div>

            {{-- POSTS GRID --}}
            @if($posts->isEmpty())
                <div class="text-center py-20 bg-gray-50 rounded-3xl border border-gray-100">
                    <div class="inline-flex bg-white p-5 rounded-full mb-6 shadow-sm">
                        <svg class="w-10 h-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                    </div>
                    <h3 class="text-xl font-serif font-bold text-gray-900 mb-2">Keine Artikel gefunden</h3>
                    <p class="text-gray-500 mb-6">Wir konnten leider nichts zu deinem Suchbegriff finden.</p>
                    <button wire:click="$set('search', '')" class="text-primary font-bold hover:underline uppercase tracking-widest text-xs">Filter zurücksetzen</button>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-12">
                    @foreach($posts as $post)
                        <article class="group flex flex-col h-full bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-xl transition-all duration-500 transform hover:-translate-y-1">

                            {{-- Image Container (Schwarzer Grund + Goldenes Line-Art Emblem) --}}
                            <a href="{{ route('blog.show', $post->slug) }}" class="block relative h-64 overflow-hidden bg-[#050505]">
                                @if($post->featured_image)
                                    <img src="{{ asset('storage/' . $post->featured_image) }}"
                                         alt="{{ $post->title }}"
                                         class="w-full h-full object-contain p-3 transition-transform duration-700 group-hover:scale-105">
                                @else
                                    <div class="flex items-center justify-center h-full bg-[#050505] text-primary">
                                        <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif

                                {{-- Kategorie Badge im leichten Beigeton --}}
                                @if($post->category)
                                    <span class="absolute top-4 left-4 bg-[#F5F1E8]/95 backdrop-blur-md text-gray-900 border border-[#D8C9A8] text-[10px] font-bold px-3 py-1.5 rounded-full shadow-sm uppercase tracking-wider z-20">
                                        {{ $post->category->name }}
                                    </span>
                                @endif

                                {{-- Werbung Badge --}}
                                @if($post->is_advertisement)
                                    <span class="absolute top-4 right-4 bg-[#F5F1E8]/95 text-gray-900 border border-[#D8C9A8] text-[10px] font-bold px-2 py-1 rounded shadow-sm uppercase tracking-widest z-20">
                                        Anzeige
                                    </span>
                                @endif
                            </a>

                            {{-- Content (Zeitlos ohne Verfallsdatum) --}}
                            <div class="p-8 flex flex-col flex-1 relative">
                                {{-- Titel --}}
                                <h2 class="text-xl font-serif font-bold text-gray-900 mb-3 leading-tight group-hover:text-primary transition-colors duration-300">
                                    <a href="{{ route('blog.show', $post->slug) }}">
                                        {{ $post->title }}
                                    </a>
                                </h2>

                                {{-- Excerpt --}}
                                <p class="text-gray-500 text-sm leading-relaxed mb-6 line-clamp-3 flex-1">
                                    {{ $post->excerpt }}
                                </p>

                                {{-- Footer / Author --}}
                                <div class="pt-6 border-t border-gray-100 flex items-center justify-between mt-auto">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold ring-2 ring-white shadow-sm">
                                            {{ substr($post->author->name ?? 'R', 0, 1) }}
                                        </div>
                                        <span class="text-xs font-bold text-gray-700 uppercase tracking-wide">
                                            {{ $post->author->name ?? 'Redaktion' }}
                                        </span>
                                    </div>

                                    <a href="{{ route('blog.show', $post->slug) }}" class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-50 text-gray-400 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                                        <svg class="w-4 h-4 transform group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                {{-- Pagination --}}
                <div class="mt-16">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
