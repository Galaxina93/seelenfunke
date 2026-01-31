<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">
            <header class="mb-12 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-gray-900">Lieferung</h1>
            </header>

            <div id="lieferung" class="scroll-mt-28">
                <h2 class="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-3">
                    <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">III</span>
                    Lieferung, Versand & Fertigungszeiten
                </h2>

                <div class="space-y-6 text-gray-700 leading-relaxed">

                    <p>
                        Die Lieferung unserer Produkte erfolgt mit größter Sorgfalt.
                        Da viele Artikel bei <strong>Mein-Seelenfunke</strong> individuell und nach Kundenwunsch gefertigt werden,
                        setzt sich die Lieferzeit aus der Fertigungsdauer und der anschließenden Versandlaufzeit zusammen.
                    </p>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">1. Versandgebiet und Versandkosten</h3>
                        <p>
                            Wir liefern innerhalb Deutschlands sowie weltweit.
                        </p>
                        <p class="mt-2">
                            Innerhalb Deutschlands erfolgt der Versand
                            <strong>kostenfrei ab einem Bestellwert von 50,00 €</strong>.
                            Bei einem Bestellwert unter 50,00 € berechnen wir
                            <strong>4,99 € Versandkosten</strong>.
                        </p>
                        <p class="mt-2 text-sm text-gray-600">
                            Für Lieferungen ins Ausland gelten gesonderte Versandkosten, die im Bestellprozess transparent ausgewiesen werden.
                        </p>

                        <!-- Versandkosten Tabelle -->
                        <div class="overflow-x-auto mt-4">
                            <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                                <thead class="bg-gray-100 text-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold border-b">Region</th>
                                    <th class="px-4 py-3 text-left font-semibold border-b">Versandkosten</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y">
                                <tr>
                                    <td class="px-4 py-3">Deutschland, Österreich</td>
                                    <td class="px-4 py-3">4,99 € (ab 50 € Bestellwert kostenlos)</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Niederlande</td>
                                    <td class="px-4 py-3">6,99 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Belgien, Dänemark, Frankreich, Polen, Tschechien</td>
                                    <td class="px-4 py-3">9,99 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Restliches Europa</td>
                                    <td class="px-4 py-3">14,99 €</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">2. Berechnung der Lieferzeit</h3>
                        <p>
                            Die Gesamtlieferzeit ergibt sich aus zwei aufeinanderfolgenden Schritten:
                        </p>

                        <ul class="mt-4 grid gap-4 md:grid-cols-2">
                            <li class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <strong class="block text-gray-900 mb-1">Fertigungszeit</strong>
                                <p class="text-sm text-gray-600">
                                    Die Anfertigung personalisierter Produkte erfolgt in der Regel innerhalb von
                                    <strong>1–3 Werktagen</strong> nach Zahlungseingang (bei Vorkasse)
                                    bzw. nach Vertragsschluss.
                                </p>
                            </li>

                            <li class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <strong class="block text-gray-900 mb-1">Versandlaufzeit</strong>
                                <p class="text-sm text-gray-600">
                                    Der Versand erfolgt über den Versanddienstleister <strong>DHL</strong>.
                                    Die reguläre Paketlaufzeit beträgt ca. <strong>1–3 Werktage</strong>
                                    innerhalb Deutschlands. Internationale Lieferzeiten können abweichen.
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">3. Versanddienstleister</h3>
                        <ul class="list-disc list-inside text-sm text-gray-600 mt-2">
                            <li>Versand mit DHL</li>
                            <li>Sendungsverfolgung zur Nachverfolgung Ihrer Bestellung</li>
                            <li>Zuverlässige und sichere Zustellung</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">4. Teillieferungen</h3>
                        <p>
                            Sollten einzelne Produkte einer Bestellung nicht zeitgleich verfügbar sein,
                            behalten wir uns vor, eine Teillieferung vorzunehmen,
                            sofern dies für Sie zumutbar ist.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">5. Fehlgeschlagene Zustellung</h3>
                        <p>
                            Scheitert die Zustellung der Ware aus Gründen, die vom Kunden zu vertreten sind
                            (z. B. falsche Adressangaben oder Nichtannahme),
                            behalten wir uns vor, vom Vertrag zurückzutreten.
                            Bereits geleistete Zahlungen werden in diesem Fall unverzüglich erstattet.
                        </p>
                    </div>

                </div>
            </div>
        </section>

    </x-sections.page-container>

</x-layouts.frontend_layout>
