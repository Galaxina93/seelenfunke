<x-layouts.frontend_layout>
    <x-sections.page-container>
        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">

            {{-- Header Bereich --}}
            <header class="mb-16 border-b border-gray-100 pb-12">
                <h1 class="text-4xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                    Erklärung zur <span class="text-primary">Barrierefreiheit</span>
                </h1>
                <p class="text-lg text-gray-600 font-light leading-relaxed">
                    Wir bei <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> verstehen unser Handwerk als Brücke zwischen Menschen.
                    Dazu gehört auch, dass wir unsere digitalen Angebote für jeden zugänglich machen möchten. Als Betreiber dieser Website
                    sind wir bemüht, die Seite in Einklang mit den einschlägigen Vorschriften zur Barrierefreiheit zu gestalten.
                </p>
            </header>

            <div class="space-y-16">

                {{-- Rechtsvorschriften --}}
                <article>
                    <h2 class="text-xl font-bold uppercase tracking-widest text-primary mb-4">Geltende Vorschriften</h2>
                    <p class="mb-4">
                        Für unsere Website gelten die Anforderungen des <strong>Barrierefreiheitsstärkungsgesetzes (BFSG)</strong>.
                        Wir orientieren uns technisch an den Richtlinien der <strong>Web Content Accessibility Guidelines (WCAG) 2.1</strong> auf Konformitätsstufe AA.
                    </p>
                    <p class="text-sm text-gray-500 italic">
                        Diese Erklärung gilt für die Website: <a href="{{ url('/') }}" class="text-primary hover:underline">{{ url('/') }}</a>
                    </p>
                </article>

                {{-- Status der Vereinbarkeit --}}
                <article class="bg-gray-50 p-8 rounded-2xl border border-gray-100">
                    <h2 class="text-2xl font-serif font-bold mb-6">Stand der Vereinbarkeit</h2>
                    <p class="mb-6 leading-relaxed">
                        Aufgrund unserer Eigenentwicklung haben wir volle Kontrolle über den Code. Dennoch ist Barrierefreiheit ein fortlaufender Prozess.
                        Diese Website ist derzeit <strong>teilweise barrierefrei</strong>. Wir arbeiten kontinuierlich daran, die Nutzererfahrung für alle zu optimieren.
                    </p>

                    <h3 class="font-bold text-gray-900 mb-3">Derzeitige Einschränkungen:</h3>
                    <ul class="space-y-4 text-sm text-gray-600">
                        <li class="flex gap-3">
                            <span class="text-primary">●</span>
                            <span><strong>Dekorative Animationen:</strong> Die fließenden Hintergrund-Effekte ("Floating Animation") dienen dem emotionalen Markenerlebnis. Diese können bei Nutzern mit vestibulären Störungen Irritationen auslösen. Wir prüfen die automatische Deaktivierung bei entsprechenden Browser-Vorgaben (prefers-reduced-motion).</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="text-primary">●</span>
                            <span><strong>PDF-Dokumente:</strong> Unsere automatisch generierten Angebotskalkulationen und Rechnungen sind aktuell nur bedingt für Screenreader optimiert.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="text-primary">●</span>
                            <span><strong>Interaktive Marker:</strong> Die Koordinaten-Marker auf unseren Produktvorschauen sind visuell intuitiv, erfordern jedoch für eine rein auditive Navigation noch detailliertere ARIA-Beschreibungen.</span>
                        </li>
                    </ul>
                </article>

                {{-- Feedback & Kontakt --}}
                <article>
                    <h2 class="text-2xl font-serif font-bold mb-6">Feedback und Kontaktangaben</h2>
                    <p class="mb-8">
                        Sind Ihnen Mängel beim barrierefreien Zugang aufgefallen oder haben Sie Fragen? Als inhabergeführtes Unternehmen ist uns Ihr direktes Feedback besonders wichtig.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 bg-white border border-gray-200 p-8 rounded-2xl shadow-sm">
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">Postanschrift</h4>
                            <p class="text-sm leading-relaxed">
                                {{ shop_setting('owner_name', 'Mein Seelenfunke') }}<br>
                                Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                                {{ shop_setting('owner_city', '38518 Gifhorn') }}
                            </p>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">Direkter Kontakt</h4>
                            <p class="text-sm leading-relaxed">
                                E-Mail: <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary font-bold">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a><br>
                                Tel: +49 (0) 159 019 668 64<br>
                                <a href="{{ route('shop') }}#kontakt" class="text-primary hover:underline">Kontaktformular öffnen</a>
                            </p>
                        </div>
                    </div>
                </article>

                {{-- Prüfungsmethode --}}
                <article>
                    <h2 class="text-2xl font-serif font-bold mb-6">Evaluationsmethode</h2>
                    <p class="mb-4">
                        Die Bewertung der Barrierefreiheit erfolgt durch regelmäßige interne Prüfungen. Dabei kombinieren wir:
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-sm text-gray-600">
                        <li>Automatisierte Tests mit Google Lighthouse und dem WAVE Evaluation Tool.</li>
                        <li>Manuelle Überprüfung der Tastaturnavigation und Fokus-Reihenfolge.</li>
                        <li>Prüfung der Kontrastverhältnisse (insbesondere Text-Overlays auf Produktbildern).</li>
                    </ul>
                </article>

                {{-- Durchsetzungsverfahren --}}
                <article class="border-t border-gray-100 pt-12">
                    <h2 class="text-2xl font-serif font-bold mb-6">Durchsetzungsverfahren</h2>
                    <p class="mb-6 leading-relaxed">
                        Sollten Sie der Ansicht sein, dass Funktionen unserer Website nicht den Anforderungen des Barrierefreiheitsstärkungsgesetzes (BFSG) entsprechen,
                        können Sie sich jederzeit an uns wenden. Wir sind bemüht, eine einvernehmliche Lösung zu finden.
                        Darüber hinaus können Sie ein Schlichtungsverfahren gem. § 16 BFSG einleiten.
                    </p>
                    <div class="text-sm bg-primary/5 p-6 rounded-lg border-l-4 border-primary">
                        <strong>Schlichtungsstelle nach dem Behindertengleichstellungsgesetz</strong><br>
                        Mauerstraße 53, 10117 Berlin<br>
                        E-Mail: info@schlichtungsstelle-bgg.de
                    </div>
                </article>

                {{-- Meta Info --}}
                <footer class="text-xs text-gray-400 pt-8 border-t border-gray-50">
                    <p>Erstellt am: 01.01.2026</p>
                    <p>Zuletzt überprüft am: {{ now()->format('d.m.Y') }}</p>
                </footer>

            </div>
        </section>
    </x-sections.page-container>
</x-layouts.frontend_layout>
