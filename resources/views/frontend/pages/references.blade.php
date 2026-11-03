<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="bg-gray-50 pt-28 pb-16 px-6 md:px-12">
            <div class="max-w-7xl mx-auto text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Du willst erleben, was echte Manufaktur-Teamarbeit ist?</h2>
                <p class="text-lg text-gray-700 max-w-3xl mx-auto">
                    Wir suchen engagierte Talente, die mit Herzblut und Präzision besondere Erinnerungsstücke in unserer Manufaktur in {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }} erschaffen wollen.
                </p>
                <a href="mailto:{{ shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de')) }}"
                   class="mt-8 inline-block bg-primary text-white font-semibold py-3 px-8 rounded-lg hover:bg-primary-dark transition">
                    JETZT Initiativbewerbung senden
                </a>
            </div>

            <div class="max-w-5xl mx-auto text-gray-800 space-y-12">
                <p class="text-xl font-semibold">Unsere Aufgabenbereiche in der Manufaktur:</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">

                    <div>
                        <h3 class="text-lg font-bold mb-2">Laserveredelung & Produktion</h3>
                        <p class="mb-4">Sorgfältige Fertigung und Veredelung individueller Unikate in unserer Werkstatt.</p>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Bedienung moderner Lasergravur-Anlagen</li>
                            <li>Einrichtung und Materialvorbereitung (Glas, Schiefer, Holz, Metall)</li>
                            <li>Handwerkliche Endkontrolle und Oberflächenfinish</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold mb-2">Qualitätskontrolle & Geschenkverpackung</h3>
                        <p class="mb-4">Liebevolle Konfektionierung und versandsichere Verpackung unserer Manufakturstücke.</p>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Sichtprüfung jedes einzelnen Unikats</li>
                            <li>Verpackung in edle Geschenkboxen</li>
                            <li>Kommissionierung und Versandvorbereitung (DHL)</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold mb-2">Grafik & 2D/3D-Produktdesign</h3>
                        <p class="mb-4">Kreatives Design für Gravurvorlagen, Konfigurator-Motive und neue Kollektionen.</p>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Erstellung von Vektorgrafiken und Laser-Layouts</li>
                            <li>Bildaufbereitung für Fotogravuren</li>
                            <li>Weiterentwicklung unserer 3D-Konfigurator-Modelle</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="text-lg font-bold mb-2">Kundenbetreuung & B2B-Projektservice</h3>
                        <p class="mb-4">Persönliche Beratung für Privatkunden, Vereine und Firmenkunden.</p>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>Betreuung individueller Sonderwünsche</li>
                            <li>Angebotserstellung für B2B- und Vereinsaufträge</li>
                            <li>Freundlicher Support per E-Mail und Telefon</li>
                        </ul>
                    </div>

                </div>
            </div>
        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
