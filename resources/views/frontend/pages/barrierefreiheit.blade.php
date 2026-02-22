<x-layouts.frontend_layout>
    <x-sections.page-container>
        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">

            {{-- Header Bereich --}}
            <header class="mb-16 text-center md:text-left border-b border-gray-100 pb-12">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary/10 text-primary text-xs font-bold uppercase tracking-widest mb-6">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                    </svg>
                    Digitale Teilhabe
                </div>
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6 leading-tight">
                    Erklärung zur <span class="text-primary italic">Barrierefreiheit</span>
                </h1>
                <p class="text-lg text-gray-600 font-light leading-relaxed max-w-3xl">
                    Wir bei <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> verstehen unser Handwerk als Brücke zwischen Menschen. Dazu gehört aus tiefer Überzeugung auch, dass wir unsere digitalen Angebote und unseren Onlineshop für jeden zugänglich machen möchten.
                </p>
            </header>

            <div class="space-y-16">

                {{-- Rechtsvorschriften --}}
                <article>
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-4">1. Geltende Vorschriften & Stand der Vereinbarkeit</h2>
                    <p class="mb-4 text-gray-700 leading-relaxed">
                        Als Betreiber dieser Website sind wir bemüht, die Seite in Einklang mit den Anforderungen des <strong>Barrierefreiheitsstärkungsgesetzes (BFSG)</strong> zu gestalten. Wir orientieren uns technisch an den Richtlinien der <strong>Web Content Accessibility Guidelines (WCAG) 2.1</strong> auf Konformitätsstufe AA.
                    </p>
                    <div class="bg-gray-50 border-l-4 border-primary p-6 rounded-r-2xl mt-6">
                        <p class="font-bold text-gray-900 mb-1">Aktueller Status: Teilweise barrierefrei</p>
                        <p class="text-sm text-gray-600">
                            Aufgrund der hohen technischen Komplexität unserer Eigenentwicklung (insbesondere des 3D-Produktkonfigurators) sind aktuell noch nicht alle Inhalte vollständig barrierefrei nutzbar. Wir arbeiten jedoch kontinuierlich an Optimierungen.
                        </p>
                    </div>
                </article>

                {{-- Spezifische Einschränkungen --}}
                <article>
                    <h2 class="text-2xl font-serif font-bold text-gray-900 mb-6">2. Bekannte Einschränkungen (Nicht barrierefreie Inhalte)</h2>
                    <p class="text-gray-700 mb-6">
                        Trotz größter Sorgfalt bei der Entwicklung unserer Plattform gibt es Bereiche, die für einige Nutzergruppen aktuell schwer zugänglich sein könnten:
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- 3D Konfigurator --}}
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-gray-900 mb-2">3D-Konfigurator & Zeichenbrett</h3>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Die visuelle Platzierung von Texten und Logos auf den 3D-Glasmodellen (WebGL/Canvas) ist für Screenreader nicht vollständig auslesbar. <strong>Alternative:</strong> Sie können uns Ihre Gravurwünsche jederzeit per E-Mail senden, wir übernehmen die Gestaltung für Sie!
                            </p>
                        </div>

                        {{-- PDF Dokumente --}}
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-gray-900 mb-2">PDF-Exporte</h3>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Unsere systemgenerierten Dokumente (Rechnungen, Angebots-Kalkulationen) sind aktuell noch nicht vollständig mit PDF/UA-Tags für Vorleseprogramme (Screenreader) optimiert.
                            </p>
                        </div>

                        {{-- Animationen --}}
                        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm md:col-span-2">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-gray-900 mb-2">Dekorative Animationen & Effekte</h3>
                            <p class="text-sm text-gray-600 leading-relaxed">
                                Um unser Handwerk emotional zu präsentieren, nutzen wir schwebende Elemente und dezente Animationen (z.B. bei unserem Maskottchen "Funki"). Wir planen künftig eine automatische Reduzierung dieser Effekte, wenn im Browser die Einstellung "prefers-reduced-motion" (Weniger Bewegung) aktiviert ist.
                            </p>
                        </div>
                    </div>
                </article>

                {{-- Feedback & Kontakt --}}
                <article class="bg-gray-900 text-white p-8 md:p-12 rounded-[2.5rem] relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-primary/20 rounded-full blur-3xl -mt-20 -mr-20 pointer-events-none"></div>

                    <div class="relative z-10">
                        <h2 class="text-2xl font-serif font-bold mb-4">Wir helfen Ihnen gerne persönlich weiter!</h2>
                        <p class="text-gray-300 mb-8 max-w-2xl leading-relaxed">
                            Stoßen Sie auf unüberwindbare Barrieren oder benötigen Sie Hilfe bei der Bestellung Ihres Unikats? Als inhabergeführtes Unternehmen ist uns Ihr direktes Feedback besonders wichtig. Melden Sie sich einfach bei uns – wir finden eine Lösung.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                            <div>
                                <h4 class="text-[10px] font-bold uppercase tracking-widest text-primary mb-3">Direkter Kontakt</h4>
                                <ul class="space-y-3 text-sm text-gray-300">
                                    <li class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}" class="hover:text-primary transition-colors font-bold">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a>
                                    </li>
                                    <li class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        <span>{{ shop_setting('owner_phone', '+49 (0) 159 019 668 64') }}</span>
                                    </li>
                                    <li class="flex items-center gap-3 pt-2">
                                        <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 text-primary font-bold hover:text-white transition-colors">
                                            Zum Kontaktformular <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="text-[10px] font-bold uppercase tracking-widest text-primary mb-3">Postanschrift</h4>
                                <address class="text-sm text-gray-300 not-italic leading-relaxed">
                                    {{ shop_setting('owner_name', 'Mein Seelenfunke') }}<br>
                                    Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                    {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                                    {{ shop_setting('owner_city', '38518 Gifhorn') }}
                                </address>
                            </div>
                        </div>
                    </div>
                </article>

                {{-- Evaluation & Durchsetzung --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                    <article>
                        <h2 class="text-xl font-serif font-bold mb-4">Evaluationsmethode</h2>
                        <p class="text-sm text-gray-700 mb-4 leading-relaxed">
                            Die fortlaufende Bewertung der Barrierefreiheit erfolgt durch regelmäßige interne Prüfungen des Systems. Dabei setzen wir auf:
                        </p>
                        <ul class="list-disc pl-5 space-y-2 text-sm text-gray-600">
                            <li>Laufende Code-Prüfungen und semantische HTML-Kontrollen während der Entwicklung.</li>
                            <li>Manuelle Überprüfung der Tastaturnavigation und Tab-Reihenfolge innerhalb der Benutzeroberfläche.</li>
                            <li>Sichtprüfung der Kontrastverhältnisse (insbesondere bei Text-Overlays auf Produktbildern).</li>
                        </ul>
                    </article>

                    <article>
                        <h2 class="text-xl font-serif font-bold mb-4">Durchsetzungsverfahren</h2>
                        <p class="text-sm text-gray-700 mb-4 leading-relaxed">
                            Sollten Sie der Ansicht sein, dass wir Ihre Hinweise zur Barrierefreiheit nicht zufriedenstellend bearbeitet haben, können Sie sich an die zuständige Schlichtungsstelle wenden (gem. § 16 BFSG):
                        </p>
                        <div class="text-sm bg-gray-50 p-5 rounded-xl border border-gray-200">
                            <strong class="block text-gray-900 mb-1">Schlichtungsstelle (BGG)</strong>
                            <p class="text-gray-600">
                                Mauerstraße 53, 10117 Berlin<br>
                                E-Mail: <a href="mailto:info@schlichtungsstelle-bgg.de" class="text-primary hover:underline">info@schlichtungsstelle-bgg.de</a>
                            </p>
                        </div>
                    </article>
                </div>

                {{-- Meta Info --}}
                <footer class="flex items-center justify-between text-xs text-gray-400 pt-8 border-t border-gray-100 uppercase tracking-widest font-bold">
                    <p>Erstellt am: 01.01.2026</p>
                    <p>Zuletzt überprüft am: {{ now()->format('d.m.Y') }}</p>
                </footer>

            </div>
        </section>
    </x-sections.page-container>
</x-layouts.frontend_layout>
