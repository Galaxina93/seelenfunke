<x-layouts.frontend_layout
    title="Impressum & Gesetzliche Anbieterkennzeichnung | Mein Seelenfunke"
    meta_description="Impressum, gesetzliche Anbieterkennzeichnung gemäß § 5 DDG, § 18 Abs. 2 MStV sowie Hersteller- und Produktsicherheitsangaben nach Art. 19 GPSR von Mein Seelenfunke (Alina Steinhauer, Gifhorn).">

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-12 md:py-16 text-gray-800">
            <header class="mb-12 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-gray-900">Impressum</h1>
                <p class="text-gray-500">Gesetzliche Anbieterkennzeichnung gemäß § 5 DDG, § 18 Abs. 2 MStV & Verordnung (EU) 2023/988 (GPSR)</p>
            </header>

            <div class="space-y-12">

                {{-- 1. Anbieterkennzeichnung nach § 5 DDG --}}
                <div id="anbieterkennzeichnung" class="bg-gray-50 p-8 rounded-xl border border-gray-200 shadow-sm">
                    <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">1</span>
                        Angaben gemäß § 5 Digitale-Dienste-Gesetz (DDG)
                    </h2>

                    <div class="grid md:grid-cols-2 gap-8 text-gray-700 leading-relaxed">
                        <div>
                            <p class="font-bold text-lg text-gray-900 mb-2">{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</p>
                            <p>
                                Inhaberin: {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                {{ shop_setting('company_street', shop_setting('owner_street', 'Carl-Goerdeler-Ring')) }} {{ shop_setting('company_street_number', '26') }}<br>
                                {{ shop_setting('company_zip', '38518') }} {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}<br>
                                {{ shop_setting('company_country', 'Deutschland') }}
                            </p>
                        </div>

                        <div>
                            <p class="font-semibold text-gray-900 mb-2">Kontakt</p>
                            <p class="mb-4">
                                Telefon: <a href="tel:{{ preg_replace('/[^0-9+]/', '', shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'))) }}" class="hover:text-primary transition-colors">{{ shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64')) }}</a><br>
                                E-Mail: <a href="mailto:{{ shop_setting('owner_email_impressum', shop_setting('company_email', 'kontakt@mein-seelenfunke.de')) }}" class="text-primary hover:underline transition-colors">{{ shop_setting('owner_email_impressum', shop_setting('company_email', 'kontakt@mein-seelenfunke.de')) }}</a><br>
                                Website: <a href="{{ url('/') }}" class="hover:text-primary transition-colors">{{ url('/') }}</a>
                            </p>

                            <p class="font-semibold text-gray-900 mb-1">Umsatzsteuer-Identifikationsnummer</p>
                            <p class="text-sm text-gray-600">
                                Umsatzsteuer-Identifikationsnummer gemäß § 27 a Umsatzsteuergesetz:<br>
                                <span class="font-mono text-gray-800">{{ shop_setting('owner_ust_id', 'DE359662265') }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- 2. Redaktionelle Verantwortung & DSA Kontaktstelle --}}
                <div class="grid md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-xs">
                        <h2 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2.5">
                            <span class="bg-gray-900 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-normal shrink-0">2</span>
                            Redaktionell Verantwortliche (§ 18 Abs. 2 MStV)
                        </h2>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Verantwortlich für den Inhalt nach § 18 Abs. 2 Medienstaatsvertrag (MStV):<br><br>
                            <strong>{{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</strong><br>
                            {{ shop_setting('company_street', shop_setting('owner_street', 'Carl-Goerdeler-Ring')) }} {{ shop_setting('company_street_number', '26') }}<br>
                            {{ shop_setting('company_zip', '38518') }} {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}<br>
                            Deutschland
                        </p>
                    </div>

                    <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-xs">
                        <h2 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2.5">
                            <span class="bg-gray-900 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-normal shrink-0">3</span>
                            Kontaktstelle nach Digital Services Act (DSA)
                        </h2>
                        <p class="text-sm text-gray-700 leading-relaxed">
                            Zentrale Kontaktstelle für Nutzer und Behörden gemäß Art. 11 und Art. 12 der Verordnung (EU) 2022/2065 (Gesetz über digitale Dienste):<br><br>
                            E-Mail: <a href="mailto:{{ shop_setting('owner_email_impressum', shop_setting('company_email', 'kontakt@mein-seelenfunke.de')) }}" class="text-primary hover:underline font-medium">{{ shop_setting('owner_email_impressum', shop_setting('company_email', 'kontakt@mein-seelenfunke.de')) }}</a><br>
                            Telefon: {{ shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64')) }}<br>
                            <span class="text-xs text-gray-500">Kommunikationssprachen: Deutsch, Englisch.</span>
                        </p>
                    </div>
                </div>

                {{-- 4. Produktsicherheit & Herstellerangaben (GPSR) --}}
                <div id="gpsr" class="bg-amber-50/50 p-6 md:p-8 rounded-xl border border-amber-200/80 shadow-xs scroll-mt-28">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-3 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-7 h-7 flex items-center justify-center text-xs font-normal shrink-0">4</span>
                        Herstellerangaben & Produktsicherheit gemäß Verordnung (EU) 2023/988 (GPSR)
                    </h2>
                    <div class="text-sm text-gray-700 leading-relaxed space-y-3">
                        <p>
                            Verantwortlicher Hersteller bzw. Veredler für sämtliche im Online-Shop unter der Marke <strong>{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }}</strong> angebotenen personalisierten und veredelten Produkte im Sinne des Art. 19 der EU-Produktsicherheitsverordnung (Verordnung (EU) 2023/988 – General Product Safety Regulation, GPSR):
                        </p>
                        <div class="bg-white p-4 rounded-lg border border-amber-200/70 text-gray-800">
                            <strong>{{ shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) }} – Inh. {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}</strong><br>
                            {{ shop_setting('company_street', shop_setting('owner_street', 'Carl-Goerdeler-Ring')) }} {{ shop_setting('company_street_number', '26') }}, {{ shop_setting('company_zip', '38518') }} {{ shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')) }}, Deutschland<br>
                            Elektronische Kontaktadresse für Produktsicherheitsanfragen: <a href="mailto:{{ shop_setting('company_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary hover:underline font-medium">{{ shop_setting('company_email', 'kontakt@mein-seelenfunke.de') }}</a>
                        </div>
                        <p class="text-xs text-gray-600">
                            Produktspezifische Sicherheits- und Warnhinweise sind zudem direkt auf der jeweiligen Produktdetailseite im Reiter „Produktsicherheit & Hersteller (GPSR)“ sowie in unseren <a href="{{ route('agb') }}#produktsicherheit" class="text-primary underline hover:text-primary-dark">Allgemeinen Geschäftsbedingungen (§ 6a)</a> abrufbar.
                        </p>
                    </div>
                </div>

                {{-- 5. Verbraucherstreitbeilegung (§ 36 VSBG) --}}
                <div class="bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-xs">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-3 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-7 h-7 flex items-center justify-center text-xs font-normal shrink-0">5</span>
                        Verbraucherstreitbeilegung / Universalschlichtungsstelle (§ 36 VSBG)
                    </h2>
                    <div class="text-sm text-gray-700 leading-relaxed space-y-2">
                        <p>
                            Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle im Sinne des Verbraucherstreitbeilegungsgesetzes (VSBG) teilzunehmen.
                        </p>
                        <p class="text-xs text-gray-500">
                            Hinweis: Die ehemalige Plattform der Europäischen Kommission zur Online-Streitbeilegung (OS-Plattform) wurde gemäß der Verordnung (EU) 2024/3228 mit Wirkung zum 20. Juli 2025 endgültig eingestellt. Unser Kundenservice steht Ihnen bei Anliegen jederzeit direkt unter <a href="mailto:{{ shop_setting('company_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary hover:underline">{{ shop_setting('company_email', 'kontakt@mein-seelenfunke.de') }}</a> zur Verfügung.
                        </p>
                    </div>
                </div>

                {{-- 6. Urheberrecht, Haftung & KI-Transparenz (EU AI Act) --}}
                <div class="bg-white p-6 md:p-8 rounded-xl border border-gray-200 shadow-xs space-y-4">
                    <h2 class="text-lg md:text-xl font-bold text-gray-900 mb-2 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-7 h-7 flex items-center justify-center text-xs font-normal shrink-0">6</span>
                        Urheberrecht & Transparenzhinweis nach Art. 50 EU AI Act
                    </h2>
                    <div class="text-sm text-gray-700 leading-relaxed space-y-3">
                        <p>
                            <strong>Urheberrecht:</strong> Die durch die Seitenbetreiberin erstellten Inhalte, Gravurvorlagen, Fotografien, 3D-Modelle und Werke auf diesen Seiten unterliegen dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen Zustimmung der jeweiligen Autorin bzw. Erstellerin.
                        </p>
                        <p>
                            <strong>KI-Transparenzhinweis gemäß Art. 50 Verordnung (EU) 2024/1689 (EU AI Act):</strong> Zur realitätsnahen Visualisierung von Gravurbeispielen, Designvorlagen und Ambiente-Darstellungen setzen wir in unserem Online-Shop ergänzend KI-gestützte Bild- und Textwerkzeuge unter menschlicher Kuratierung ein. Entsprechende Darstellungen sind direkt am Bild mit dem Hinweis „KI-generiert“ gekennzeichnet. Die physische Fertigung und Lasergravur Ihres Produktes erfolgt stets individuell am echten Werkstück in unserer Manufaktur in Gifhorn.
                        </p>
                    </div>
                </div>

                {{-- 7. Weiterführende Rechtstexte (Schnellnavigation) --}}
                <div class="pt-4 border-t border-gray-200">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Weiterführende Rechtstexte & Kundeninformationen</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                        <a href="{{ route('agb') }}" class="p-4 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-primary hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-gray-800 group-hover:text-primary">AGB & Widerrufsbelehrung</span>
                            <span class="text-gray-400 group-hover:text-primary">&rarr;</span>
                        </a>
                        <a href="{{ route('datenschutz') }}" class="p-4 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-primary hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-gray-800 group-hover:text-primary">Datenschutzerklärung</span>
                            <span class="text-gray-400 group-hover:text-primary">&rarr;</span>
                        </a>
                        <a href="{{ route('widerruf') }}" class="p-4 rounded-xl border border-red-200 bg-red-50/50 hover:bg-white hover:border-red-500 hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-red-700 group-hover:text-red-800">Vertrag elektronisch widerrufen</span>
                            <span class="text-red-400 group-hover:text-red-700">&rarr;</span>
                        </a>
                        <a href="{{ route('versand') }}" class="p-4 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-primary hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-gray-800 group-hover:text-primary">Versand & Zahlungsarten</span>
                            <span class="text-gray-400 group-hover:text-primary">&rarr;</span>
                        </a>
                        <a href="{{ route('barrierefreiheit') }}" class="p-4 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-primary hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-gray-800 group-hover:text-primary">Barrierefreiheit (BFSG)</span>
                            <span class="text-gray-400 group-hover:text-primary">&rarr;</span>
                        </a>
                        <a href="{{ route('verhaltenskodex') }}" class="p-4 rounded-xl border border-gray-200 bg-gray-50 hover:bg-white hover:border-primary hover:shadow-sm transition-all flex items-center justify-between group">
                            <span class="font-semibold text-gray-800 group-hover:text-primary">Verhaltenskodex</span>
                            <span class="text-gray-400 group-hover:text-primary">&rarr;</span>
                        </a>
                    </div>
                </div>

            </div>
        </section>

    </x-sections.page-container>
</x-layouts.frontend_layout>
