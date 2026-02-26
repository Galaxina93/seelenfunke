<div class="bg-slate-50 min-h-screen font-sans antialiased">

    {{-- 1. HEADER BILD (Hero) --}}
    <div class="relative h-[450px] md:h-[600px] w-full bg-gray-900 overflow-hidden">
        @if($post->featured_image)
            <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="w-full h-full object-cover opacity-50 transition-transform duration-1000 hover:scale-105">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-slate-800 to-slate-900 opacity-90"></div>
        @endif

        {{-- Feiner Farbverlauf nach unten für fließenden Übergang --}}
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent"></div>

        <div class="absolute inset-0 flex flex-col justify-end items-center text-center p-6 md:p-12 pb-16 md:pb-24">
            <div class="max-w-4xl animate-fade-in-up">
                @if($post->category)
                    <a href="{{ route('blog', ['kategorie' => $post->category->slug]) }}" class="inline-block px-4 py-1.5 rounded-full bg-white/10 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-widest mb-6 border border-white/20 hover:bg-white/20 hover:border-white/40 transition-all shadow-[0_0_15px_rgba(0,0,0,0.2)]">
                        {{ $post->category->name }}
                    </a>
                @endif

                <h1 class="text-4xl md:text-5xl lg:text-7xl font-serif font-bold text-white leading-[1.1] mb-8 drop-shadow-xl tracking-tight">
                    {{ $post->title }}
                </h1>

                <div class="flex items-center justify-center gap-4 text-white/80 text-xs font-medium uppercase tracking-wider">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="font-bold">{{ $post->author->name ?? 'Redaktion' }}</span>
                    </span>
                    <span class="text-primary/50">•</span>
                    <time datetime="{{ $post->published_at }}" class="font-bold flex items-center gap-2">
                        <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $post->published_at->format('d. M Y') }}
                    </time>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. CONTENT BEREICH --}}
    <div class="max-w-[800px] mx-auto px-6 py-12 md:py-20 bg-white shadow-[0_-20px_50px_rgba(0,0,0,0.05)] rounded-t-[3rem] relative -mt-8 border-t border-gray-100/50">

        {{-- NAVIGATION --}}
        <a href="{{ route('blog') }}" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-primary mb-12 transition-colors group">
            <span class="p-1.5 rounded-lg bg-gray-50 border border-gray-100 group-hover:border-primary/30 transition-colors">
                <svg class="w-4 h-4 transition-transform group-hover:-translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            </span>
            Zurück zur Übersicht
        </a>

        {{-- RECHTLICHE COMPLIANCE HINWEISE --}}
        @if($post->is_advertisement || $post->contains_affiliate_links)
            <div class="space-y-4 mb-12">
                @if($post->is_advertisement)
                    <div class="bg-gray-50 border border-gray-200 rounded-2xl p-5 sm:p-6 relative overflow-hidden group">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-gray-300"></div>
                        <p class="text-[9px] text-gray-400 uppercase font-black tracking-widest mb-1.5 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Transparenz-Hinweis
                        </p>
                        <p class="text-sm text-gray-600 font-medium leading-relaxed">
                            Dieser Beitrag ist als <strong class="text-gray-900">Anzeige / Werbung</strong> gekennzeichnet. Er kann gesponserte Inhalte, Produktplatzierungen oder Kooperationen enthalten.
                        </p>
                    </div>
                @endif

                @if($post->contains_affiliate_links)
                    <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-5 sm:p-6 relative overflow-hidden">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-blue-400"></div>
                        <p class="text-[9px] text-blue-500 uppercase font-black tracking-widest mb-1.5 flex items-center gap-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Affiliate / Provision
                        </p>
                        <p class="text-sm text-blue-900/80 font-medium leading-relaxed">
                            Dieser Artikel enthält sogenannte Affiliate-Links. Wenn du über diese Links einkaufst, erhalten wir eine kleine Provision. <strong class="text-blue-900">Für dich ändert sich der Preis nicht.</strong> Danke für deine Unterstützung!
                        </p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Einleitung / Excerpt --}}
        @if($post->excerpt)
            <div class="mb-12 relative">
                <svg class="absolute -top-4 -left-4 w-12 h-12 text-primary/10 rotate-180" fill="currentColor" viewBox="0 0 32 32" aria-hidden="true"><path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z"/></svg>
                <p class="font-serif text-xl sm:text-2xl text-gray-800 leading-relaxed italic pl-6 border-l-2 border-primary/50 relative z-10 font-medium">
                    {{ $post->excerpt }}
                </p>
            </div>
        @endif


        {{-- ========================================================= --}}
        {{-- BULLETPROOF CKEDITOR STYLING (Überschreibt Tailwind Resets) --}}
        {{-- ========================================================= --}}
        <style>
            .ck-content-output {
                color: #4b5563; /* text-gray-600 */
                font-size: 1.125rem; /* text-lg */
                line-height: 1.8;
                font-weight: 400; /* Stellt sicher, dass normaler Text nicht fett ist */
            }
            .ck-content-output p { margin-bottom: 1.5em; }

            /* Überschriften */
            .ck-content-output h1,
            .ck-content-output h2,
            .ck-content-output h3,
            .ck-content-output h4 {
                font-family: ui-serif, Georgia, serif;
                font-weight: 700;
                color: #111827; /* text-gray-900 */
                margin-top: 2em;
                margin-bottom: 0.75em;
                line-height: 1.3;
                letter-spacing: -0.025em;
            }
            .ck-content-output h2 { font-size: 1.875rem; }
            .ck-content-output h3 { font-size: 1.5rem; }
            .ck-content-output h4 { font-size: 1.25rem; }

            /* Listen (Nummeriert & Punkte) erzwingen */
            .ck-content-output ul {
                list-style-type: disc !important;
                padding-left: 1.5rem !important;
                margin-bottom: 1.5em !important;
            }
            .ck-content-output ol {
                list-style-type: decimal !important;
                padding-left: 1.5rem !important;
                margin-bottom: 1.5em !important;
            }
            .ck-content-output li {
                margin-bottom: 0.5em;
                display: list-item !important;
            }

            /* Fett & Kursiv */
            .ck-content-output strong,
            .ck-content-output b { font-weight: 700 !important; color: #111827 !important; }
            .ck-content-output em,
            .ck-content-output i { font-style: italic !important; }

            /* Links */
            .ck-content-output a {
                color: #C5A059;
                text-decoration: underline;
                text-underline-offset: 4px;
                font-weight: 600;
                transition: color 0.2s;
            }
            .ck-content-output a:hover { color: #a18042; text-decoration-color: #C5A059; }

            /* Zitate */
            .ck-content-output blockquote {
                border-left: 4px solid #C5A059;
                padding: 1.5rem;
                font-style: italic;
                color: #4b5563;
                background-color: #f8fafc; /* ganz leichtes grau */
                margin-top: 2em;
                margin-bottom: 2em;
                border-radius: 0 1rem 1rem 0;
            }

            /* Bilder */
            .ck-content-output img {
                border-radius: 1.5rem;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                margin-top: 2.5em;
                margin-bottom: 2.5em;
                max-width: 100%;
                height: auto;
                border: 1px solid #f3f4f6;
            }
        </style>

        {{-- ACTUAL CONTENT (Eingehüllt in unser neues Styling) --}}
        <div class="ck-content-output">
            {!! $post->content !!}
        </div>

        {{-- FOOTER / SHARE / TAGS --}}
        <div class="mt-20 pt-8 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-6">

                {{-- Kategorie Tag --}}
                <div>
                    @if($post->category)
                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Kategorie</span>
                            <a href="{{ route('blog', ['kategorie' => $post->category->slug]) }}" class="px-4 py-1.5 rounded-lg bg-gray-100 text-gray-600 text-xs font-bold hover:bg-primary hover:text-white transition-colors">
                                {{ $post->category->name }}
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Social Share --}}
                <div class="flex items-center gap-4 bg-gray-50 px-5 py-2.5 rounded-xl border border-gray-200">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-500">Beitrag teilen</span>
                    <a href="mailto:?subject={{ urlencode($post->title) }}&body={{ urlencode(route('blog.show', $post->slug)) }}" class="p-2 rounded-lg bg-white border border-gray-200 text-gray-400 hover:text-primary hover:border-primary/30 transition-all shadow-sm hover:scale-110" title="Per E-Mail senden">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
