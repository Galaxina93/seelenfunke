<section id="process" class="bg-white py-24" aria-labelledby="process-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- HEADLINE --}}
        <div class="text-center mb-20 fade-in">
            <h2 id="process-heading" class="text-3xl md:text-5xl font-serif font-bold text-gray-900 mb-6">
                Vom Rohling zum <span class="text-primary">personalisierten Unikat</span>
            </h2>
            <p class="text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
                Transparenz schafft Vertrauen. Ihr Unikat ist keine Lagerware. Erfahren Sie hier, wie wir Ihr Produkt Schritt für Schritt in unserer Manufaktur in Gifhorn fertigen – von der Datenprüfung bis zum sicheren Versand.
            </p>
        </div>

        <div class="relative">
            {{-- Verbindungslinie (Nur Desktop) --}}
            <div class="hidden lg:block absolute top-12 left-0 w-full h-1 bg-gray-100 my-4 rounded-full overflow-hidden z-0" aria-hidden="true">
                <div class="h-full bg-gradient-to-r from-primary-light via-primary to-primary-dark w-full opacity-30"></div>
            </div>

            {{-- PROZESS SCHRITTE --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10 lg:gap-6">

                @php
                    $steps = [
                        [
                            'video' => 'beratung',
                            'image' => '/images/projekt/process/beratung.png',
                            'title' => 'Auftrag & Design',
                            'text' => 'Alles beginnt mit Ihrer Idee. Wir prüfen Ihre Daten oder erstellen gemeinsam ein Layout, das perfekt auf das Glas abgestimmt ist.'
                        ],
                        [
                            'video' => 'lasergravur',
                            'image' => '/images/projekt/process/lasergravur.png',
                            'title' => 'High-End Laser',
                            'text' => 'Mit modernster Lasertechnologie wird Ihr Motiv dauerhaft und gestochen scharf in das Material eingearbeitet. Präzision im Mikrometerbereich.'
                        ],
                        [
                            'video' => '4_augen',
                            'image' => '/images/projekt/process/handveredelung.png',
                            'title' => 'Veredelung & Check',
                            'text' => 'Jedes Stück wird von Hand gereinigt, poliert und durchläuft unsere strenge 4-Augen-Qualitätsprüfung. Nur Makelloses verlässt das Haus.'
                        ],
                        [
                            'video' => 'edle_verpackung',
                            'image' => '/images/projekt/process/edle_verpackung.png',
                            'title' => 'Edle Verpackung',
                            'text' => 'Der erste Eindruck zählt. Ihr Unikat wird direkt in unserer hochwertigen Geschenkbox verpackt und ist somit bereit zur feierlichen Übergabe.'
                        ],
                        [
                            'video' => 'sicherer_versand',
                            'image' => '/images/projekt/process/sicherer_versand.png',
                            'title' => 'Sorgfältiger Versand',
                            'text' => 'Wir verpacken bruchsicher in speziellen Kartonagen. Ihr Paket wird sicher und gut gepolstert an unseren Logistikpartner übergeben.'
                        ]
                    ];
                @endphp

                @foreach($steps as $index => $step)
                    <div class="text-center fade-in group relative z-10" style="animation-delay: {{ $index * 0.2 }}s;">

                        {{-- Alpine.js Wrapper für Hover-to-Play Logik --}}
                        <div class="relative inline-block transition-transform transform group-hover:-translate-y-2 duration-300"
                             @if(isset($step['video']))
                                 x-data="{
                                 loaded: false,
                                 playVideo() {
                                     // Video-URL erst beim Hovern setzen! Zero Bytes vorab geladen.
                                     if (!this.loaded) {
                                         this.$refs.video.src = '{{ asset('images/projekt/process/' . $step['video'] . '.webm') }}';
                                         this.loaded = true;
                                     }
                                     this.$refs.video.play().catch(e => {}); // .catch fängt Autoplay-Fehler ab
                                 },
                                 pauseVideo() {
                                     if (this.loaded) {
                                         this.$refs.video.pause();
                                     }
                                 }
                             }"
                             @mouseenter="playVideo()"
                             @mouseleave="pauseVideo()"
                             @touchstart.passive="playVideo()" {{-- Touch-Support für Smartphones --}}
                            @endif
                        >

                            {{-- Container für Bild ODER Video --}}
                            <div class="w-32 h-32 bg-white border-4 border-primary rounded-full overflow-hidden mx-auto mb-6 shadow-xl relative z-10 group-hover:shadow-2xl group-hover:border-primary-dark transition-all">

                                @if(isset($step['video']))
                                    {{-- High-Performance Video --}}
                                    <video
                                        x-ref="video"
                                        loop
                                        muted
                                        playsinline
                                        preload="none"
                                        poster="{{ asset($step['image']) }}"
                                        class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500">

                                        {{-- Fallback Image falls Video vom Browser blockiert wird --}}
                                        <img src="{{ asset($step['image']) }}"
                                             alt="Prozess-Schritt: {{ $step['title'] }} - {{ $step['text'] }}"
                                             loading="lazy">
                                    </video>
                                @else
                                    {{-- Klassische Bild-Logik --}}
                                    <img src="{{ asset($step['image']) }}"
                                         onerror="this.src='https://placehold.co/200x200/f8f8f8/CCCCCC?text={{ $index+1 }}'; this.style.objectFit='cover';"
                                         alt="Prozess-Schritt: {{ $step['title'] }} - {{ $step['text'] }}"
                                         loading="lazy"
                                         class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500">
                                @endif

                            </div>

                            {{-- Nummer Badge --}}
                            <div class="absolute -top-1 -right-1 w-8 h-8 bg-primary text-white font-bold rounded-full flex items-center justify-center border-2 border-white shadow-md z-20" aria-hidden="true">
                                {{ $index + 1 }}
                            </div>
                        </div>

                        <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-primary transition-colors">
                            {{ $step['title'] }}
                        </h3>
                        <p class="text-gray-600 text-sm leading-relaxed px-1">
                            {{ $step['text'] }}
                        </p>
                    </div>
                @endforeach

            </div>
        </div>

        {{-- CALL TO ACTION --}}
        <div class="text-center mt-20 fade-in">
            <p class="text-xl text-gray-500 mb-10 italic font-serif max-w-3xl mx-auto">
                "Verlassen Sie sich auf einen reibungslosen Ablauf und ein Ergebnis, das begeistert."
            </p>

            <a href="{{ route('calculator') }}"
               title="Kalkulieren Sie jetzt den Preis für Ihr individuelles Geschenk"
               class="inline-flex items-center gap-3 bg-primary text-white px-10 py-4 rounded-full font-bold text-lg shadow-lg hover:bg-primary-dark transition-all transform hover:scale-105 hover:shadow-2xl">
                <span>Jetzt Preis berechnen</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </a>
        </div>
    </div>
</section>
