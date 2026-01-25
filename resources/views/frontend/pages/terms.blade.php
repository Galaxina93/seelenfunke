<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">
            <h1 class="text-3xl md:text-4xl font-serif font-bold mb-8 text-gray-900">AGB & Widerrufsrecht</h1>

            <div class="space-y-12 text-base leading-relaxed text-gray-700">

                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-2">Allgemeine Geschäftsbedingungen</h2>

                    <div class="space-y-8">

                        {{-- §1 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">1. Geltungsbereich</h3>
                            <p class="mt-2">
                                Für die Geschäftsbeziehungen zwischen <strong>Mein Seelenfunke</strong> (Inhaberin: Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn) und dem Kunden gelten ausschließlich die nachfolgenden Allgemeinen Geschäftsbedingungen in ihrer zum Zeitpunkt der Bestellung gültigen Fassung.
                            </p>
                        </div>

                        {{-- §2 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">2. Vertragsschluss</h3>
                            <p class="mt-2">
                                Die Darstellung der Produkte auf dieser Website dient zur Präsentation unseres Portfolios. Der eigentliche Verkauf und Vertragsschluss erfolgt in der Regel über unseren Shop auf der Plattform <strong>Etsy</strong> (www.etsy.com) oder über unser Anfrageformular.
                                <br>
                                Soweit Sie über Etsy bestellen, gelten zusätzlich die AGB und Nutzungsbedingungen von Etsy.
                                <br>
                                Bei individuellen Aufträgen, die per E-Mail, Telefon oder über das Anfrage-Tool dieser Website geschlossen werden, kommt der Kaufvertrag durch unsere explizite Auftragsbestätigung (nicht die automatische Eingangsbestätigung) zustande.
                            </p>
                        </div>

                        {{-- §3 - ANGEPASST: Preise inkl. Versand --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">3. Preise und Zahlung</h3>
                            <p class="mt-2">
                                Die angegebenen Preise sind Endpreise. <strong>Die Preise enthalten die gesetzliche Umsatzsteuer sowie die Versandkosten innerhalb Deutschlands.</strong>
                                <br>
                                Die Zahlung erfolgt per Überweisung (Vorkasse) nach Rechnungserhalt oder über die im Checkout angebotenen Zahlungsdienstleister.
                            </p>
                        </div>

                        {{-- §4 VERSAND - ANGEPASST: Kostenlos --}}
                        <div id="versand" class="bg-gray-50 p-6 rounded-lg border border-gray-100 scroll-mt-24">
                            <h3 class="font-bold text-lg text-gray-900 mb-4 flex items-center gap-2">
                                4. Versand- und Lieferbedingungen
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <strong class="block text-gray-900 mb-1">4.1 Kostenloser Versand (Deutschland)</strong>
                                    <p>
                                        Wir liefern innerhalb Deutschlands <strong>versandkostenfrei</strong>. Es fallen für Sie keine zusätzlichen Versandgebühren an, da diese bereits in unseren Produktpreisen einkalkuliert sind.
                                        <br>
                                        <em class="text-sm text-gray-500">Hinweis: Für Lieferungen ins Ausland bitten wir um vorherige Anfrage, da hierfür gesonderte Gebühren anfallen können.</em>
                                    </p>
                                </div>

                                <div>
                                    <strong class="block text-gray-900 mb-1">4.2 Lieferzeiten & Fertigung</strong>
                                    <p>
                                        Da es sich bei unseren Artikeln oft um personalisierte Anfertigungen handelt, setzt sich die Lieferzeit aus zwei Komponenten zusammen:
                                    </p>
                                    <ul class="list-disc list-inside ml-2 mt-2 space-y-1 text-sm bg-white p-3 rounded border border-gray-200">
                                        <li><strong>Fertigungszeit:</strong> In der Regel 1-3 Werktage nach Zahlungseingang (bei Vorkasse) bzw. Vertragsschluss.</li>
                                        <li><strong>Paketlaufzeit:</strong> In der Regel 1-3 Werktage durch unseren Versanddienstleister (DHL).</li>
                                    </ul>
                                    <p class="mt-2">
                                        Feste Liefertermine (z.B. für Jubiläen) bedürfen unserer ausdrücklichen schriftlichen Bestätigung.
                                    </p>
                                </div>

                                <div>
                                    <strong class="block text-gray-900 mb-1">4.3 Versanddienstleister & Packstationen</strong>
                                    <p>
                                        Wir versenden standardmäßig als versichertes Paket mit <strong>DHL</strong>. Sie erhalten nach dem Versand eine E-Mail mit der Sendungsnummer zur Nachverfolgung.
                                        Die Lieferung an DHL Packstationen ist möglich. Bitte geben Sie hierfür Ihre Postnummer in der Adresse an.
                                    </p>
                                </div>

                                <div>
                                    <strong class="block text-gray-900 mb-1">4.4 Teillieferungen</strong>
                                    <p>
                                        Wir sind zu Teillieferungen berechtigt, soweit dies für Sie zumutbar ist. Zusätzliche Kosten entstehen Ihnen hierdurch nicht.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- §5 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">5. Eigentumsvorbehalt</h3>
                            <p class="mt-2">
                                Die Ware bleibt bis zur vollständigen Bezahlung aller Forderungen aus dem Kaufvertrag unser Eigentum.
                            </p>
                        </div>

                        {{-- §6 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">6. Transportschäden</h3>
                            <p class="mt-2">
                                Werden Waren mit offensichtlichen Transportschäden angeliefert, so reklamieren Sie solche Fehler bitte sofort beim Zusteller und nehmen Sie bitte schnellstmöglich Kontakt zu uns auf.
                                <br>
                                Die Versäumung einer Reklamation oder Kontaktaufnahme hat für Ihre gesetzlichen Ansprüche und deren Durchsetzung, insbesondere Ihre Gewährleistungsrechte, keinerlei Konsequenzen. Sie helfen uns aber, unsere eigenen Ansprüche gegenüber dem Frachtführer bzw. der Transportversicherung geltend machen zu können.
                            </p>
                        </div>

                        {{-- §7 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">7. Gewährleistung und Haftung</h3>
                            <p class="mt-2">
                                Es gilt das gesetzliche Mängelhaftungsrecht. Bei personalisierten Produkten stellt eine subjektiv nicht gefallende Gestaltung (z.B. Schriftart, Positionierung), die aber der Bestellung entspricht, keinen Mangel dar. Geringfügige Abweichungen in Farbe und Materialbeschaffenheit (z.B. bei Naturprodukten wie Holz oder Schiefer) sind fertigungsbedingt und stellen keinen Reklamationsgrund dar.
                            </p>
                        </div>

                        {{-- §8 --}}
                        <div>
                            <h3 class="font-bold text-lg text-gray-900">8. Streitbeilegung</h3>
                            <p class="mt-2">
                                Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit, die Sie hier finden: <a href="https://ec.europa.eu/consumers/odr/" target="_blank" class="text-primary hover:underline">https://ec.europa.eu/consumers/odr/</a>.
                                Wir sind nicht bereit und nicht verpflichtet, an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.
                            </p>
                        </div>

                    </div>
                </div>

                <div class="bg-gray-50 p-6 rounded-xl border border-gray-100">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-2">Widerrufsbelehrung</h2>

                    <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-8 rounded-r-md shadow-sm">
                        <div class="flex items-start gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 flex-shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <h3 class="font-bold text-red-700 text-lg">Ausschluss des Widerrufsrechts bei personalisierter Ware</h3>
                                <p class="text-red-900 mt-2 font-medium">
                                    Das Widerrufsrecht besteht <u>nicht</u> bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind.
                                </p>
                                <p class="text-red-900 mt-2">
                                    <strong>Das bedeutet: Da wir unsere Produkte (Glas, Schiefer, Metall etc.) speziell nach Ihren Wünschen mit individuellen Gravuren (Namen, Daten, Fotos) anfertigen, ist eine Rückgabe oder ein Widerruf für diese Artikel ausgeschlossen.</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="font-bold mb-2">Widerrufsrecht (nur für NICHT personalisierte Standardware)</p>
                    <p class="mb-4">
                        Soweit Sie Artikel bestellen, die <strong>nicht</strong> personalisiert sind (z.B. Standard-Zubehör ohne Gravur), haben Sie das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.
                        Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.
                    </p>

                    <p class="mb-4">
                        Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (Mein Seelenfunke, Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn, E-Mail: kontakt@mein-seelenfunke.de, Tel: +49 159 019 668 64) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.
                    </p>

                    <p class="mb-4">
                        Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.
                    </p>

                    <h3 class="font-bold text-lg mt-6">Folgen des Widerrufs</h3>
                    <p class="mb-4">
                        Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.
                    </p>
                    <p>
                        Wir können die Rückzahlung verweigern, bis wir die Waren wieder zurückerhalten haben oder bis Sie den Nachweis erbracht haben, dass Sie die Waren zurückgesandt haben.
                        Sie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden oder zu übergeben.
                        Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.
                    </p>
                </div>

                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 border-b pb-2">Muster-Widerrufsformular</h2>

                    {{-- DOWNLOAD BUTTON --}}
                    <div class="mb-8">
                        <p class="text-gray-600 mb-3 text-sm">Sie können das Formular bequem als PDF herunterladen oder den Text unten kopieren:</p>

                        <a href="{{ asset('images/projekt/downloads/Widerrufsformular.pdf') }}" target="_blank" download="Widerrufsformular_MeinSeelenfunke.pdf"
                           class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-md font-bold hover:bg-primary-dark transition-colors shadow-sm group">

                            {{-- Icon: Download --}}
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 group-hover:translate-y-1 transition-transform">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 12 12 16.5m0 0 4.5-4.5M12 16.5V3.75" />
                            </svg>
                            Formular als PDF herunterladen
                        </a>
                    </div>

                    {{-- TEXT VERSION --}}
                    <div class="border-2 border-dashed border-gray-300 p-6 rounded-lg bg-white">
                        <p class="text-sm italic mb-4">(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>

                        <p class="mb-4">
                            <strong>An:</strong><br>
                            Mein Seelenfunke<br>
                            Alina Steinhauer<br>
                            Carl-Goerdeler-Ring 26<br>
                            38518 Gifhorn<br>
                            E-Mail: kontakt@mein-seelenfunke.de
                        </p>

                        <p class="mb-4">
                            Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*):
                        </p>
                        <hr class="border-gray-300 my-4">

                        <p class="mb-2">Bestellt am (*) / erhalten am (*): __________________________</p>
                        <p class="mb-2">Name des/der Verbraucher(s): __________________________</p>
                        <p class="mb-4">Anschrift des/der Verbraucher(s): __________________________</p>

                        <br><br>
                        <p>_______________________________________________________</p>
                        <p class="text-sm">Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</p>
                        <br>
                        <p>Datum: __________________</p>

                        <p class="text-xs mt-4">(*) Unzutreffendes streichen.</p>
                    </div>
                </div>

            </div>
        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
