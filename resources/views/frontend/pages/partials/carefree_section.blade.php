<section id="carefree" class="py-24 bg-gray-50 overflow-hidden" aria-labelledby="carefree-heading">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-12">
        <div class="grid md:grid-cols-2 gap-x-12 gap-y-16 items-center">

            <div class="fade-in">
                <div class="text-primary font-serif font-semibold mb-2 inline-block tracking-wider uppercase text-sm">
                    Full-Service Manufaktur
                </div>
                <h2 id="carefree-heading" class="text-3xl md:text-4xl font-bold text-gray-900 mb-6 font-serif">
                    Unser Rundum-Sorglos-Paket
                </h2>

                <p class="text-gray-700 text-lg mb-4 leading-relaxed">
                    Ob für verdiente Mitarbeiter, langjährige Partner oder einen Herzensmenschen: Schenken sollte Freude bereiten, keinen Stress verursachen.
                </p>
                <p class="text-gray-700 text-lg mb-6 leading-relaxed">
                    Bei <strong>Mein Seelenfunke</strong> erhalten Sie nicht einfach nur ein Stück Glas. Wir übernehmen für Sie den kompletten Prozess von der Prüfung Ihrer Daten bis zum sicheren Versand an den Empfänger.
                </p>

                {{-- Highlight Box --}}
                <p class="text-white text-lg mb-10 leading-relaxed bg-gradient-to-r from-primary to-primary-dark p-6 rounded-xl shadow-lg border-l-4 border-white/30">
                    <strong>Unser Anspruch:</strong> Sie bestellen bequem vom Schreibtisch oder Sofa aus und wir sorgen dafür, dass beim Auspacken Begeisterung entsteht.
                </p>

                <h3 class="text-xl font-bold text-gray-800 mb-6">Ihre Vorteile im Überblick:</h3>
                <ul class="space-y-4 text-gray-700 mb-12">

                    {{-- Vorteil 1 --}}
                    <li class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <div>
                            <strong class="font-semibold text-gray-900">Alles aus einer Hand:</strong>
                            Keine externen Dienstleister, keine Verzögerungen. Designprüfung, Veredelung und Logistik erfolgen direkt in unserer Manufaktur. Das garantiert kurze Wege und präzise Ergebnisse.
                        </div>
                    </li>

                    {{-- Vorteil 2 --}}
                    <li class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <div>
                            <strong class="font-semibold text-gray-900">Qualitätsversprechen:</strong> Bevor ein Paket unser Haus verlässt, wird es streng kontrolliert. Keine Kratzer, keine Fingerabdrücke. Nur reines Glas und eine perfekte Gravur.
                        </div>
                    </li>

                    {{-- Vorteil 3 --}}
                    <li class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <div>
                            <strong class="font-semibold text-gray-900">Geschenkfertig geliefert:</strong>
                            Ihr Unikat kommt <strong>standardmäßig</strong> in einer hochwertigen, mit Stoff ausgelegten Geschenkbox. Bereit zur sofortigen Übergabe oder Ehrung.
                        </div>
                    </li>

                    {{-- Vorteil 4 --}}
                    <li class="flex items-start">
                        <svg class="flex-shrink-0 h-6 w-6 text-primary mr-3 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        <div>
                            <strong class="font-semibold text-gray-900">Service:</strong>
                            Haben Sie Sonderwünsche? Wir finden für fast jede Anforderung eine Lösung.
                        </div>
                    </li>

                </ul>

            </div>

            {{-- BILD RECHTS --}}
            <div class="flex justify-center items-center relative fade-in" style="animation-delay: 0.3s;">
                {{-- Dekorativer Hintergrundkreis --}}
                <div class="absolute inset-0 bg-primary/5 rounded-full transform scale-90 blur-3xl"></div>

                <img src="{{ asset('images/projekt/logo/logo-up.png') }}"
                     alt="Mein Seelenfunke - Ihre Full-Service Manufaktur für personalisierte Geschenke"
                     loading="lazy"
                     class="relative w-full max-w-md object-cover transform hover:scale-105 transition duration-500 drop-shadow-2xl">
            </div>
        </div>
    </div>
</section>
