<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">

            {{-- Impressum Section --}}
            <header class="mb-16 text-center md:text-left border-b pb-8">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-6 text-gray-900">Impressum</h1>
                <div class="space-y-1 text-base leading-relaxed">
                    <p>
                        <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong><br>
                        Inhaberin: {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                        {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                        {{ shop_setting('owner_city', '38518 Gifhorn') }}
                    </p>
                    <p class="pt-4">
                        <strong>Kontakt:</strong><br>
                        E-Mail: <a href="mailto:{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}" class="text-primary hover:underline">{{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</a><br>
                        Telefon: {{ shop_setting('owner_phone', '+49 159 019 668 64') }}<br>
                        Web: <a href="{{ url('/') }}" class="text-primary hover:underline">{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</a>
                    </p>
                    <p class="pt-4 text-sm text-gray-600">
                        <strong>Rechtliche Angaben:</strong><br>
                        Steuernummer: {{ shop_setting('owner_tax_id') }}<br>
                        @if(shop_setting('owner_ust_id')) USt-IdNr.: {{ shop_setting('owner_ust_id') }}<br> @endif
                        Gerichtsstand: {{ shop_setting('owner_court', 'Gifhorn') }}<br>
                        IBAN: {{ shop_setting('owner_iban', 'Wird nachgereicht') }}
                    </p>
                </div>
            </header>

            {{-- AGB & Widerruf Header --}}
            <header class="mb-12 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-gray-900">AGB & Widerrufsrecht</h1>
                <p class="text-gray-500 text-sm">Stand: {{ now()->format('d.m.Y') }}</p>
            </header>

            <div class="space-y-16 text-base leading-relaxed text-gray-700">

                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">I</span>
                        Allgemeine Geschäftsbedingungen
                    </h2>

                    <div class="space-y-10">

                        {{-- §1 Geltungsbereich --}}
                        <div id="geltungsbereich" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">1. Geltungsbereich und Anbieter</h3>
                            <p>
                                (1) Diese Allgemeinen Geschäftsbedingungen gelten für alle Bestellungen, die Sie bei dem Online-Shop <strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}</strong> (nachfolgend „Anbieter“) tätigen.
                            </p>
                            <p class="mt-2 text-sm bg-gray-50 p-4 rounded border border-gray-200">
                                <strong>Anbieterkennzeichnung:</strong><br>
                                {{ shop_setting('owner_name', 'Mein Seelenfunke') }}<br>
                                Inhaberin: {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                                {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
                                E-Mail: {{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}
                            </p>
                            <p class="mt-2">
                                (2) Das Warenangebot in unserem Online-Shop richtet sich ausschließlich an Verbraucher, die das 18. Lebensjahr vollendet haben. Verbraucher ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken abschließt, die überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit zugerechnet werden können (§ 13 BGB).
                            </p>
                        </div>

                        {{-- §2 Vertragsschluss --}}
                        <div id="vertragsschluss" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">2. Vertragsschluss</h3>
                            <p>
                                (1) Die Präsentation der Waren im Online-Shop stellt kein rechtlich bindendes Angebot, sondern einen unverbindlichen Online-Katalog dar.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Bestellungen über Etsy:</strong> Soweit Sie Waren über unseren Shop auf der Plattform Etsy bestellen, gelten vorrangig die dortigen technischen Schritte zum Vertragsschluss sowie die AGB und Nutzungsbedingungen von Etsy.
                            </p>
                            <p class="mt-2">
                                (3) <strong>Individuelle Aufträge:</strong> Bei Bestellungen, die individuell per E-Mail, Telefon oder über das Anfrageformular dieser Website vereinbart werden, erhalten Sie von uns zunächst ein verbindliches Angebot. Der Vertrag kommt zustande, wenn Sie dieses Angebot durch eine Bestätigung (z.B. per E-Mail) oder durch Zahlung des Rechnungsbetrages annehmen.
                            </p>
                        </div>

                        {{-- §3 Nutzung des Konfigurators --}}
                        <div id="konfigurator" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">3. Nutzung des Produktkonfigurators</h3>
                            <p>
                                (1) Der auf der Website bereitgestellte Produktkonfigurator dient als Visualisierungshilfe zur groben Platzierung von Texten, Bildern und Symbolen auf den angebotenen Waren.
                            </p>
                            <p class="mt-2">
                                (2) Die im Konfigurator dargestellte Vorschau ist **nicht maßstabsgetreu** und stellt keine millimetergenaue Abbildung des Endprodukts dar. Geringfügige Abweichungen in der Platzierung, Ausrichtung und Größe (im Bereich handwerklicher Toleranzen) sind produktionsbedingt möglich und stellen keinen Mangel dar.
                            </p>
                            <p class="mt-2">
                                (3) **Farbliche Abweichungen:** Aufgrund unterschiedlicher Bildschirmeinstellungen, Helligkeiten und technischer Gegebenheiten (RGB-Darstellung am Monitor vs. physische Gravur oder Druck) kann die Farbdarstellung im Konfigurator von der tatsächlichen Farbe der Ware oder der Veredelung abweichen.
                            </p>
                            <p class="mt-2">
                                (4) Der Kunde ist dafür verantwortlich, die im Konfigurator eingegebenen Inhalte (insbesondere Rechtschreibung von Namen oder Daten) vor Abschluss der Bestellung auf Richtigkeit zu prüfen. Nachträgliche Korrekturen nach Produktionsbeginn sind ausgeschlossen.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-lg text-gray-900">3. Preise und Zahlung</h3>
                            <p class="mt-2">
                                (1) Die angegebenen Preise sind Endpreise inklusive der gesetzlichen Umsatzsteuer.
                                <br>
                                (2) Ihnen stehen folgende Zahlungsarten zur Verfügung:
                            </p>
                            <ul class="list-disc list-inside ml-4 mt-2 mb-2">
                                <li>Vorkasse (Überweisung)</li>
                                <li>Kreditkarte (Visa, Mastercard, American Express)</li>
                                <li>Apple Pay / Google Pay</li>
                                <li>Sofortüberweisung / Klarna</li>
                            </ul>
                            <p>
                                Die Abwicklung der Kartenzahlungen und digitalen Wallets erfolgt über den Zahlungsdienstleister <strong>Stripe Payments Europe, Ltd.</strong> Die Belastung Ihres Kontos erfolgt unmittelbar nach Abschluss der Bestellung.
                            </p>
                        </div>

                        {{-- §4 Eigentumsvorbehalt --}}
                        <div id="eigentum" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">4. Eigentumsvorbehalt</h3>
                            <p>Bis zur vollständigen Bezahlung bleibt die Ware unser Eigentum.</p>
                        </div>

                        {{-- §5 Transportschäden --}}
                        <div id="transportschaeden" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">5. Transportschäden</h3>
                            <p>
                                (1) Werden Waren mit offensichtlichen Transportschäden angeliefert, so reklamieren Sie solche Fehler bitte möglichst sofort beim Zusteller und nehmen Sie bitte unverzüglich Kontakt zu uns auf.
                            </p>
                            <p class="mt-2">
                                (2) Die Versäumung einer Reklamation oder Kontaktaufnahme hat für Ihre gesetzlichen Ansprüche und deren Durchsetzung, insbesondere Ihre Gewährleistungsrechte, keinerlei Konsequenzen. Sie helfen uns aber, unsere eigenen Ansprüche gegenüber dem Frachtführer bzw. der Transportversicherung geltend machen zu können.
                            </p>
                        </div>

                        {{-- §6 Gewährleistung --}}
                        <div id="gewaehrleistung" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">6. Gewährleistung und Mängelhaftung</h3>
                            <p>
                                (1) Es besteht ein gesetzliches Mängelhaftungsrecht.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Hinweis zu Naturprodukten:</strong> Holz, Schiefer und ähnliche Naturmaterialien unterliegen Schwankungen in Farbe, Maserung und Beschaffenheit. Solche naturbedingten Abweichungen sowie materialtypische Merkmale (z.B. Asteinschlüsse bei Holz) stellen keinen Mangel dar, solange die Tauglichkeit zum vertraglich vorausgesetzten Gebrauch nicht beeinträchtigt ist.
                            </p>
                            <p class="mt-2">
                                (3) Bei personalisierten Produkten stellt eine subjektiv nicht gefallende Gestaltung (z.B. Schriftart, Platzierung), die jedoch den Vorgaben der Bestellung entspricht, keinen Mangel dar.
                            </p>
                        </div>

                        {{-- §7 Streitbeilegung --}}
                        <div id="streitbeilegung" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">7. Streitbeilegung</h3>
                            <p>
                                Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit, die Sie hier finden: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline decoration-primary underline-offset-2">https://ec.europa.eu/consumers/odr/</a>.
                                <br>
                                Wir sind zur Teilnahme an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle weder verpflichtet noch bereit.
                            </p>
                        </div>

                    </div>
                </div>

                <div id="widerruf" class="scroll-mt-28">
                    <h2 class="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-3">
                        <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">II</span>
                        Widerrufsbelehrung
                    </h2>

                    {{-- Warnbox: Ausschluss --}}
                    <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-10 rounded-r-xl shadow-sm">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 mt-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-red-800 text-lg">Wichtiger Hinweis zum Ausschluss des Widerrufsrechts</h3>
                                <p class="text-red-800 mt-2 text-sm leading-relaxed">
                                    Das Widerrufsrecht besteht, soweit die Parteien nichts anderes vereinbart haben, <strong>nicht</strong> bei folgenden Verträgen:
                                </p>
                                <ul class="list-disc list-outside ml-4 mt-2 text-red-800 text-sm font-medium">
                                    <li>Verträge zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (§ 312g Abs. 2 Nr. 1 BGB).</li>
                                </ul>
                                <p class="text-red-900 mt-3 font-bold text-sm bg-white/50 p-2 rounded inline-block border border-red-100">
                                    Dies gilt für alle unsere Produkte, die wir speziell für Sie gravieren, bedrucken oder anderweitig personalisieren.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="prose prose-gray max-w-none text-gray-700">
                        <p class="font-medium bg-gray-100 p-2 inline-block rounded text-sm mb-4">
                            Für alle anderen Waren (z.B. Standard-Zubehör ohne Personalisierung) gilt folgendes Widerrufsrecht:
                        </p>

                        <h3 class="font-bold text-lg text-gray-900">Widerrufsrecht</h3>
                        <p>
                            Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.
                            Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.
                        </p>
                        <p>
                            Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (<strong>{{ shop_setting('owner_name', 'Mein Seelenfunke') }}, {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}, {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}, {{ shop_setting('owner_city', '38518 Gifhorn') }}, E-Mail: {{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}</strong>) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.
                        </p>
                        <p>
                            Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
                        </p>

                        <h3 class="font-bold text-lg text-gray-900 mt-6">Folgen des Widerrufs</h3>
                        <p>
                            Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.
                        </p>
                        <p>
                            Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben, je nachdem, welches der frühere Zeitpunkt ist.
                        </p>
                        <p>
                            Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben. Die Frist ist gewahrt, wenn Sie die Waren vor Ablauf der Frist von vierzehn Tagen absenden.
                        </p>
                        <p class="font-semibold">
                            Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.
                        </p>
                        <p>
                            Sie müssen für einen etwaigen Wertverlust der Waren nur aufkommen, wenn dieser Wertverlust auf einen zur Prüfung der Beschaffenheit, Eigenschaften und Funktionsweise der Waren nicht notwendigen Umgang mit ihnen zurückzuführen ist.
                        </p>
                    </div>
                </div>

                <div id="widerrufsformular" class="scroll-mt-28">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-2">Muster-Widerrufsformular</h2>

                    <div class="mb-8">
                        <p class="text-gray-600 mb-4">Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.</p>

                        <a href="{{ asset('images/projekt/downloads/Widerrufsformular.pdf') }}" target="_blank" download="Widerrufsformular_MeinSeelenfunke.pdf"
                           class="inline-flex items-center gap-3 bg-gray-900 text-white px-6 py-4 rounded-lg font-bold hover:bg-gray-800 transition-all shadow-md group hover:-translate-y-0.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:scale-110 transition-transform">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3.75" />
                            </svg>
                            <span>Formular als PDF herunterladen</span>
                        </a>
                    </div>

                    <div class="border border-gray-200 p-8 rounded-xl bg-white shadow-sm text-gray-800 font-mono text-sm leading-relaxed">
                        <p class="mb-6 font-bold">
                            An:<br>
                            {{ shop_setting('owner_name', 'Mein Seelenfunke') }}<br>
                            {{ shop_setting('owner_proprietor', 'Alina Steinhauer') }}<br>
                            {{ shop_setting('owner_street', 'Carl-Goerdeler-Ring 26') }}<br>
                            {{ shop_setting('owner_city', '38518 Gifhorn') }}<br>
                            E-Mail: {{ shop_setting('owner_email', 'kontakt@mein-seelenfunke.de') }}
                        </p>

                        <p class="mb-6">
                            Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*):
                        </p>

                        <div class="h-px bg-gray-200 w-full my-6"></div>

                        <div class="space-y-4">
                            <p>Bestellt am (*) / erhalten am (*): <span class="inline-block border-b border-gray-300 w-48">&nbsp;</span></p>
                            <p>Name des/der Verbraucher(s): <span class="inline-block border-b border-gray-300 w-48">&nbsp;</span></p>
                            <p>Anschrift des/der Verbraucher(s): <span class="inline-block border-b border-gray-300 w-64">&nbsp;</span></p>
                        </div>

                        <div class="mt-12 mb-4">
                            <span class="inline-block border-b border-gray-300 w-2/3">&nbsp;</span>
                            <p class="text-xs text-gray-500 mt-1">Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</p>
                        </div>

                        <p class="mb-4">Datum: <span class="inline-block border-b border-gray-300 w-32">&nbsp;</span></p>

                        <p class="text-xs text-gray-400 mt-8">(*) Unzutreffendes streichen.</p>
                    </div>
                </div>

            </div>
        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
