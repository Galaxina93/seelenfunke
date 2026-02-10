<div>
    <div class="bg-white min-h-screen">

        {{-- 1. HEADER BILD (Hero) --}}
        <div class="relative h-[400px] md:h-[500px] w-full bg-gray-900">
            @if($post->featured_image)
                <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover opacity-60">
            @else
                <div class="w-full h-full bg-gradient-to-r from-gray-800 to-gray-900 opacity-80"></div>
            @endif

            <div class="absolute inset-0 flex flex-col justify-center items-center text-center p-6">
                <div class="max-w-4xl animate-fade-in-up">
                    @if($post->category)
                        <span class="inline-block px-3 py-1 rounded-full bg-white/20 backdrop-blur text-white text-xs font-bold uppercase tracking-widest mb-4 border border-white/30">
                        {{ $post->category->name }}
                    </span>
                    @endif

                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-serif font-bold text-white leading-tight mb-6 shadow-sm">
                        {{ $post->title }}
                    </h1>

                    <div class="flex items-center justify-center gap-4 text-white/90 text-sm font-medium">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $post->author->name ?? 'Redaktion' }}
                    </span>
                        <span>•</span>
                        <time datetime="{{ $post->published_at }}">{{ $post->published_at->format('d. F Y') }}</time>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. CONTENT BEREICH --}}
        <div class="max-w-3xl mx-auto px-4 sm:px-6 py-12 md:py-20">

            {{-- NAVIGATION --}}
            <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline mb-8 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Zurück zur Übersicht
            </a>

            {{-- RECHTLICHE COMPLIANCE HINWEISE --}}
            @if($post->is_advertisement)
                <div class="bg-gray-50 border-l-4 border-gray-400 p-4 mb-10">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-widest mb-1">Transparenz-Hinweis</p>
                    <p class="text-sm text-gray-700 italic">
                        Dieser Beitrag ist als <strong>Anzeige / Werbung</strong> gekennzeichnet. Er kann gesponserte Inhalte, Produktplatzierungen oder Kooperationen enthalten.
                    </p>
                </div>
            @endif

            @if($post->contains_affiliate_links)
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-10 flex items-start gap-3">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="text-sm text-blue-900">
                        <strong>Hinweis zu Links:</strong> Dieser Artikel enthält sogenannte Affiliate-Links (mit * gekennzeichnet). Wenn du über diese Links einkaufst, erhalten wir eine kleine Provision. Für dich ändert sich der Preis nicht. Danke für deine Unterstützung!
                    </div>
                </div>
            @endif

            {{-- ACTUAL CONTENT --}}
            <div class="prose prose-lg max-w-none text-gray-600
            {{-- ÜBERSCHRIFTEN --}}
            prose-headings:font-serif prose-headings:font-bold prose-headings:text-gray-900 prose-headings:mb-4 prose-headings:mt-8
            {{-- FLIESSTEXT --}}
            prose-p:leading-relaxed prose-p:mb-6
            {{-- LINKS --}}
            prose-a:text-primary prose-a:font-bold prose-a:no-underline hover:prose-a:underline hover:prose-a:text-primary-dark
            {{-- FETTGEDRUCKTES --}}
            prose-strong:font-bold prose-strong:text-gray-900
            {{-- LISTEN (UL & OL) - HIER WAR DER FEHLER --}}
            prose-ul:list-disc prose-ul:pl-6 prose-ul:mb-6
            prose-ol:list-decimal prose-ol:pl-6 prose-ol:mb-6
            prose-li:marker:text-primary prose-li:marker:font-bold prose-li:pl-2 prose-li:mb-2
            {{-- BILDER --}}
            prose-img:rounded-2xl prose-img:shadow-md prose-img:my-8 prose-img:w-full
            {{-- ZITATE (Blockquotes) --}}
            prose-blockquote:border-l-4 prose-blockquote:border-primary prose-blockquote:bg-gray-50/50 prose-blockquote:py-4 prose-blockquote:pl-6 prose-blockquote:pr-4 prose-blockquote:italic prose-blockquote:text-gray-700 prose-blockquote:rounded-r-lg prose-blockquote:my-8
            {{-- TABELLEN --}}
            prose-table:border prose-table:border-gray-200 prose-table:shadow-sm prose-table:rounded-lg prose-table:overflow-hidden prose-table:my-8 prose-table:w-full
            prose-th:bg-gray-50 prose-th:text-gray-900 prose-th:font-serif prose-th:font-bold prose-th:p-4 prose-th:text-left
            prose-td:p-4 prose-td:text-gray-600 prose-td:border-t prose-td:border-gray-100
            {{-- HORIZONTALE LINIE --}}
            prose-hr:border-gray-200 prose-hr:my-12
        ">

                {{-- Einleitung / Excerpt (Manuell gestylt, falls vorhanden) --}}
                @if($post->excerpt)
                    <div class="not-prose mb-10">
                        <p class="font-serif text-xl md:text-2xl text-gray-800 leading-relaxed border-l-4 border-primary pl-6 py-1 italic">
                            {{ $post->excerpt }}
                        </p>
                    </div>
                @endif

                {{-- Hauptinhalt (HTML direkt ausgeben) --}}
                {!! $post->content !!}

            </div>

            {{-- FOOTER / SHARE / TAGS --}}
            <div class="mt-16 pt-8 border-t border-gray-100">
                <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                    <div>
                        @if($post->category)
                            <span class="text-sm text-gray-500">Kategorie: <a href="{{ route('blog', ['kategorie' => $post->category->slug]) }}" class="text-primary hover:underline font-bold">{{ $post->category->name }}</a></span>
                        @endif
                    </div>

                    {{-- Social Share Platzhalter (Rechtlich: Keine Tracker laden, nur statische Links!) --}}
                    <div class="flex items-center gap-4">
                        <span class="text-sm font-bold text-gray-900">Teilen:</span>
                        <a href="mailto:?subject={{ urlencode($post->title) }}&body={{ urlencode(route('blog.show', $post->slug)) }}" class="text-gray-400 hover:text-primary transition" title="Per E-Mail senden">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </a>
                        {{-- Hier könnten weitere DSGVO-konforme Share-Links hin (einfache URLs ohne JS) --}}
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
