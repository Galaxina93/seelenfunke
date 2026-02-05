<x-layouts.frontend_layout>

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-20 text-gray-800">
            <header class="mb-12 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-gray-900">Lieferung</h1>
                <p class="text-gray-600">Informationen zu Versandkosten, Liefergebieten und Versandlaufzeiten.</p>
            </header>

            <div id="lieferung" class="scroll-mt-28">
                <h2 class="text-2xl font-bold text-gray-900 mb-8 border-b pb-4 flex items-center gap-3">
                    <span class="bg-gray-900 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-normal">III</span>
                    Lieferung, Versand & Fertigungszeiten
                </h2>

                <div class="space-y-6 text-gray-700 leading-relaxed">

                    @php
                        // Dynamisches Laden der Werte aus der Shop-Konfiguration
                        $shopName = shop_setting('owner_name', 'Mein-Seelenfunke');
                        $shippingCost = (int) shop_setting('shipping_cost', 490);
                        $freeThreshold = (int) shop_setting('shipping_free_threshold', 5000);
                        $expressSurcharge = (int) shop_setting('express_surcharge', 2500);
                    @endphp

                    <p>
                        Die Lieferung unserer Produkte erfolgt mit größter Sorgfalt.
                        Da viele Artikel bei <strong>{{ $shopName }}</strong> individuell und nach Kundenwunsch gefertigt werden,
                        setzt sich die Lieferzeit aus der Fertigungsdauer und der anschließenden Versandlaufzeit zusammen.
                    </p>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">1. Versandgebiet und Versandkosten</h3>
                        <p>
                            Wir liefern innerhalb <strong>Deutschlands</strong> sowie in alle Länder der <strong>Europäischen Union (Zone 1)</strong>.
                            Ein Versand in Nicht-EU-Staaten (z.B. Schweiz, UK) oder weltweit wird aktuell nicht angeboten.
                        </p>
                        <p class="mt-4">
                            <strong>Versand innerhalb Deutschlands:</strong><br>
                            Erfolgt <strong>kostenfrei ab einem Bestellwert von {{ number_format($freeThreshold / 100, 2, ',', '.') }} €</strong>.
                            Bei einem Bestellwert unter {{ number_format($freeThreshold / 100, 2, ',', '.') }} € berechnen wir eine Versandpauschale von <strong>{{ number_format($shippingCost / 100, 2, ',', '.') }} €</strong>.
                        </p>

                        <div class="overflow-x-auto mt-6">
                            <h4 class="font-bold text-gray-900 mb-2">Versandkosten EU (Zone 1 - DHL Paket)</h4>
                            <p class="text-sm text-gray-600 mb-4">Die Versandkosten in die EU berechnen sich nach dem Gesamtgewicht der Bestellung:</p>
                            <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                                <thead class="bg-gray-100 text-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold border-b">Region</th>
                                    <th class="px-4 py-3 text-left font-semibold border-b">Gewichtsklasse</th>
                                    <th class="px-4 py-3 text-right font-semibold border-b">Versandkosten (Online-Tarif)</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y bg-white">
                                <tr>
                                    <td class="px-4 py-3 font-medium">Deutschland</td>
                                    <td class="px-4 py-3">Pauschal (bis 31,5 kg)</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($shippingCost / 100, 2, ',', '.') }} € <span class="text-xs text-green-600 font-bold ml-1">(Ab {{ number_format($freeThreshold / 100, 0, ',', '.') }}€ Kostenlos)</span></td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3" rowspan="5">
                                        <strong>EU - Zone 1</strong><br>
                                        <span class="text-[10px] text-gray-500 leading-tight block mt-1">
                                            Belgien, Bulgarien, Dänemark, Estland, Finnland, Frankreich, Griechenland, Irland, Italien, Kroatien, Lettland, Litauen, Luxemburg, Malta, Monaco, Niederlande, Österreich, Polen, Portugal, Rumänien, Schweden, Slowakei, Slowenien, Spanien, Tschechien, Ungarn, Zypern.
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">Paket bis 2 kg</td>
                                    <td class="px-4 py-3 text-right">14,49 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Paket bis 5 kg</td>
                                    <td class="px-4 py-3 text-right">17,49 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Paket bis 10 kg</td>
                                    <td class="px-4 py-3 text-right">22,49 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Paket bis 20 kg</td>
                                    <td class="px-4 py-3 text-right">28,49 €</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3">Paket bis 31,5 kg</td>
                                    <td class="px-4 py-3 text-right">45,49 €</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-4 text-xs text-gray-500 italic">
                            Hinweis: Für den Versand in Länder der EU fallen keine Zollgebühren an. Inselzuschläge sind in diesen Pauschalen nicht enthalten und können ggf. nachgefordert werden.
                        </p>
                    </div>

                    {{-- Ergänzung: Express-Option, da in Config vorhanden --}}
                    @if($expressSurcharge > 0)
                        <div>
                            <h3 class="font-bold text-lg text-gray-900 mb-2">2. Express-Option</h3>
                            <p>
                                Für besonders eilige Bestellungen bieten wir eine Express-Verarbeitung an. Für einen Aufpreis von <strong>{{ number_format($expressSurcharge / 100, 2, ',', '.') }} €</strong> wird Ihr Auftrag bevorzugt gefertigt und versendet.
                            </p>
                        </div>
                    @endif

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">3. Berechnung der Lieferzeit</h3>
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
                                    innerhalb Deutschlands. Die Laufzeit in andere EU-Länder beträgt in der Regel <strong>3-7 Werktage</strong>.
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">4. Versanddienstleister</h3>
                        <ul class="list-disc list-inside text-sm text-gray-600 mt-2">
                            <li>Versand mit DHL</li>
                            <li>Sendungsverfolgung zur Nachverfolgung Ihrer Bestellung</li>
                            <li>Zuverlässige und sichere Zustellung</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">5. Teillieferungen</h3>
                        <p>
                            Sollten einzelne Produkte einer Bestellung nicht zeitgleich verfügbar sein,
                            behalten wir uns vor, eine Teillieferung vorzunehmen,
                            sofern dies für Sie zumutbar ist.
                        </p>
                    </div>

                    <div>
                        <h3 class="font-bold text-lg text-gray-900 mb-2">6. Fehlgeschlagene Zustellung</h3>
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
