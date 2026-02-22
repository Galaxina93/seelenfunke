<x-layouts.frontend_layout>
    <x-sections.page-container>
        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">

            <header class="text-center mb-16">
                <h1 class="text-4xl md:text-5xl font-serif font-bold mb-4 text-gray-900">Impressum</h1>
                <div class="w-24 h-1 bg-primary/40 mx-auto rounded-full"></div>
            </header>

            <div class="space-y-10 text-base leading-relaxed text-gray-600 font-sans">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    {{-- 1. ANGABEN GEMÄSS TMG --}}
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-xl font-serif font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Angaben gemäß § 5 TMG</h2>
                        <p class="mt-2">
                            <strong class="text-gray-900">{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong><br>
                            Inhaberin: {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                            {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                            {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
                            Deutschland
                        </p>
                    </div>

                    {{-- 2. KONTAKT --}}
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-xl font-serif font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Kontakt</h2>
                        <p class="mt-2 flex flex-col gap-2">
                            <span>
                                <span class="text-gray-400 font-medium">Telefon:</span><br>
                                <a href="tel:{{ str_replace([' ', '(', ')', '-'], '', shop_setting('owner_phone', '+4915901966864')) }}" class="text-primary hover:text-primary-dark transition-colors font-medium">{{ shop_setting('owner_phone', '+49 (0) 159 019 668 64') }}</a>
                            </span>
                            <span>
                                <span class="text-gray-400 font-medium">E-Mail:</span><br>
                                <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary hover:text-primary-dark transition-colors font-medium">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    {{-- 3. UMSATZSTEUER --}}
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-xl font-serif font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Umsatzsteuer-ID</h2>
                        <p class="mt-2">
                            Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br>
                            @if(shop_setting('owner_ust_id'))
                                <strong class="text-gray-900">{{ shop_setting('owner_ust_id') }}</strong>
                            @else
                                <em class="text-gray-400">USt-IdNr. folgt...</em>
                            @endif
                        </p>
                        <p class="text-sm mt-3 text-gray-500 bg-gray-50 p-3 rounded-lg border border-gray-100">
                            Steuernummer: <strong>{{ shop_setting('owner_tax_id') }}</strong>
                        </p>
                    </div>

                    {{-- 4. VERANTWORTLICH FÜR INHALT --}}
                    <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                        <h2 class="text-xl font-serif font-bold text-gray-900 mb-4 border-b border-gray-100 pb-2">Verantwortlich für den Inhalt</h2>
                        <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">nach § 55 Abs. 2 RStV</p>
                        <p class="mt-2">
                            <strong class="text-gray-900">{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</strong><br>
                            {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                            {{ shop_setting('owner_city', '38518 Gifhorn') }}
                        </p>
                    </div>
                </div>

                {{-- 5. GELTUNGSBEREICH (WICHTIG FÜR ETSY & SOCIAL MEDIA) --}}
                <div class="bg-primary/5 p-8 rounded-2xl border border-primary/20 shadow-sm relative overflow-hidden">
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-primary/10 rounded-full blur-2xl pointer-events-none"></div>
                    <h2 class="text-xl font-serif font-bold text-gray-900 mb-4">Weitere Online-Präsenzen</h2>
                    <p class="mb-4">Dieses Impressum gilt auch für folgende Plattformen:</p>
                    <ul class="space-y-3">
                        <li class="flex items-center gap-3">
                            <span class="w-2 h-2 bg-primary rounded-full"></span>
                            <strong>Etsy Shop:</strong>
                            <a href="https://www.etsy.com/de/shop/DEIN_SHOPNAME" target="_blank" class="text-primary hover:text-primary-dark transition-colors">etsy.com/de/shop/DEIN_SHOPNAME</a>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-2 h-2 bg-primary rounded-full"></span>
                            <strong>Instagram:</strong>
                            <a href="https://www.instagram.com/Mein_Seelenfunke/" target="_blank" class="text-primary hover:text-primary-dark transition-colors">@Mein_Seelenfunke</a>
                        </li>
                        <li class="flex items-center gap-3">
                            <span class="w-2 h-2 bg-primary rounded-full"></span>
                            <strong>TikTok:</strong>
                            <a href="https://www.tiktok.com/@mein_seelenfunke" target="_blank" class="text-primary hover:text-primary-dark transition-colors">@mein_seelenfunke</a>
                        </li>
                    </ul>
                </div>

                {{-- 6. EU-STREITSCHLICHTUNG --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 bg-white p-8 rounded-2xl shadow-sm border border-gray-100">
                    <div>
                        <h3 class="font-serif font-bold text-lg text-gray-900 mb-3">EU-Streitschlichtung</h3>
                        <p class="text-sm">
                            Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
                            <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline block mt-1">https://ec.europa.eu/consumers/odr/</a>
                        </p>
                    </div>
                    <div>
                        <h3 class="font-serif font-bold text-lg text-gray-900 mb-3">Verbraucherstreitbeilegung</h3>
                        <p class="text-sm">
                            Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
                        </p>
                    </div>
                </div>

                {{-- 7. HAFTUNGSAUSSCHLUSS --}}
                <div class="space-y-8 mt-12 bg-gray-50 p-8 md:p-10 rounded-3xl border border-gray-200">
                    <div>
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-3">Haftung für Inhalte</h3>
                        <p class="text-sm">
                            Als Diensteanbieter sind wir gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich.
                            Nach §§ 8 bis 10 TMG sind wir als Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde Informationen zu überwachen
                            oder nach Umständen zu forschen, die auf eine rechtswidrige Tätigkeit hinweisen.
                            Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den allgemeinen Gesetzen bleiben hiervon unberührt.
                            Eine diesbezügliche Haftung ist jedoch erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich.
                            Bei Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend entfernen.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-3">Haftung für Links</h3>
                        <p class="text-sm">
                            Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben.
                            Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen.
                            Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber der Seiten verantwortlich.
                            Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf mögliche Rechtsverstöße überprüft.
                            Rechtswidrige Inhalte waren zum Zeitpunkt der Verlinkung nicht erkennbar.
                            Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete Anhaltspunkte einer Rechtsverletzung nicht zumutbar.
                            Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-xl font-serif font-bold text-gray-900 mb-3">Urheberrecht</h3>
                        <p class="text-sm">
                            Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht.
                            Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen
                            der schriftlichen Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind nur für den privaten,
                            nicht kommerziellen Gebrauch gestattet. Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden,
                            werden die Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche gekennzeichnet.
                            Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden, bitten wir um einen entsprechenden Hinweis.
                            Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Inhalte umgehend entfernen.
                        </p>
                    </div>
                </div>

                <div class="flex justify-between items-center text-xs text-gray-400 mt-12 pt-6 border-t border-gray-200">
                    <p>Gerichtsstand: {{ shop_setting('owner_court', 'Gifhorn') }}</p>
                    <p>Quelle: <a href="https://www.e-recht24.de" target="_blank" class="hover:text-primary transition-colors">e-recht24.de</a></p>
                </div>

            </div>
        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
