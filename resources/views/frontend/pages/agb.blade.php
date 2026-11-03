<x-layouts.frontend_layout
    title="Allgemeine Geschäftsbedingungen (AGB) | Mein Seelenfunke"
    meta_description="Allgemeine Geschäftsbedingungen (AGB) und Kundeninformationen für Bestellungen von personalisierten Geschenken bei Mein Seelenfunke.">

    <x-sections.page-container>

        <section class="max-w-4xl mx-auto px-4 py-12 md:py-16 text-gray-800">
            @php
                $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
                $ownerProprietor = shop_setting('owner_proprietor', 'Alina Steinhauer');
                $companyStreetLine = shop_setting('company_street')
                    ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26'))
                    : shop_setting('owner_street', 'Carl-Goerdeler-Ring 26');
                $companyCityLine = shop_setting('company_city')
                    ? trim(shop_setting('company_zip', '38518') . ' ' . shop_setting('company_city'))
                    : shop_setting('owner_city', '38518 Gifhorn');
                $companyEmail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
                $companyPhone = shop_setting('company_phone', shop_setting('owner_phone', '+49 159 019 668 64'));
                $ownerUstId = shop_setting('company_ust_id', shop_setting('owner_ust_id'));
            @endphp

            {{-- Anbieterkennzeichnung Header --}}
            <header class="mb-16 text-center md:text-left border-b pb-8">
                <h2 class="text-xl md:text-2xl font-serif font-bold mb-4 text-gray-900">Anbieterkennzeichnung (Angaben gemäß § 5 DDG)</h2>
                <div class="space-y-1 text-base leading-relaxed">
                    <p>
                        <strong>{{ $companyName }}</strong><br>
                        Inhaberin: {{ $ownerProprietor }}<br>
                        {{ $companyStreetLine }}<br>
                        {{ $companyCityLine }}, Deutschland
                    </p>
                    <p class="pt-4">
                        <strong>Kontakt:</strong><br>
                        E-Mail: <a href="mailto:{{ $companyEmail }}" class="text-primary hover:underline">{{ $companyEmail }}</a><br>
                        Telefon: {{ $companyPhone }}<br>
                        Web: <a href="{{ url('/') }}" class="text-primary hover:underline">{{ str_replace(['http://', 'https://'], '', shop_setting('owner_website', 'www.mein-seelenfunke.de')) }}</a>
                    </p>
                    <p class="pt-4 text-sm text-gray-600">
                        <strong>Umsatzsteuer-ID & Rechtliche Angaben:</strong><br>
                        @if($ownerUstId)
                            USt-IdNr. gemäß § 27a UStG: {{ $ownerUstId }}<br>
                        @endif
                        Verantwortlich gemäß § 18 Abs. 2 MStV & Art. 19 GPSR: {{ $ownerProprietor }} (Anschrift wie oben)
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
                                (1) Diese Allgemeinen Geschäftsbedingungen gelten für alle Bestellungen, die Sie bei dem Online-Shop <strong>{{ $companyName }}</strong> (nachfolgend „Anbieter“) tätigen.
                            </p>
                            <p class="mt-2 text-sm bg-gray-50 p-4 rounded border border-gray-200">
                                <strong>Anbieterkennzeichnung:</strong><br>
                                {{ $companyName }}<br>
                                Inhaberin: {{ $ownerProprietor }}<br>
                                {{ $companyStreetLine }}, {{ $companyCityLine }}<br>
                                E-Mail: {{ $companyEmail }}
                            </p>
                            <p class="mt-2">
                                (2) Das Warenangebot in unserem Online-Shop richtet sich sowohl an Verbraucher (§ 13 BGB) als auch an Unternehmer (§ 14 BGB), die das 18. Lebensjahr vollendet haben. Verbraucher ist jede natürliche Person, die ein Rechtsgeschäft zu Zwecken abschließt, die überwiegend weder ihrer gewerblichen noch ihrer selbständigen beruflichen Tätigkeit zugerechnet werden können (§ 13 BGB).
                            </p>
                        </div>

                        {{-- §2 Vertragsschluss --}}
                        <div id="vertragsschluss" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">2. Vertragsschluss & Bestellablauf im Online-Shop (§ 312i BGB)</h3>
                            <p>
                                (1) Die Darstellung der Produkte im Online-Shop stellt kein rechtlich bindendes Angebot, sondern einen unverbindlichen Online-Katalog (<em>invitatio ad offerendum</em>) dar.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Bestellablauf im Mein-Seelenfunke Online-Shop:</strong> Sie können unsere Produkte zunächst unverbindlich auswählen, im interaktiven 2D/3D-Produktkonfigurator individuell gestalten und über die Schaltfläche „In den Warenkorb“ in Ihren virtuellen Warenkorb legen. Im Warenkorb sowie auf der anschließenden Kassen-Übersichtsseite („Checkout“) können Sie Ihre Auswahl, die Stückzahl, Ihre Gravur- und Personalisierungsdaten sowie Ihre Rechnungs-, Liefer- und Zahlungsangaben jederzeit überprüfen und Eingabefehler vor dem Absenden Ihrer Bestellung über die vorgesehenen Änderungs- und Löschfunktionen oder die üblichen Browser-, Tastatur- und Mausfunktionen korrigieren.
                            </p>
                            <p class="mt-2">
                                (3) <strong>Verbindliches Kaufangebot & Vertragsannahme:</strong> Durch Anklicken des Buttons <strong>„Zahlungspflichtig bestellen“</strong> im abschließenden Schritt des Bestellprozesses geben Sie ein verbindliches Kaufangebot über die im Warenkorb enthaltenen Waren ab (§ 312j Abs. 3 BGB). Unmittelbar nach dem Absenden der Bestellung erhalten Sie eine automatisierte Bestellbestätigung per E-Mail, die den Eingang Ihrer Bestellung dokumentiert (§ 312i Abs. 1 Satz 1 Nr. 3 BGB). Ein verbindlicher Kaufvertrag kommt zustande:
                            </p>
                            <ul class="list-disc list-inside mt-2 space-y-1 text-sm text-gray-700 ml-2">
                                <li>bei Auswahl einer Sofortzahlungsart (Kreditkarte, Apple Pay, Google Pay, Klarna, SEPA-Lastschrift) unmittelbar mit Abschluss der Zahlungsautorisierung bzw. Zahlungsanweisung im Bestellprozess, oder</li>
                                <li>spätestens durch unsere ausdrückliche Auftrags- oder Versandbestätigung per E-Mail innerhalb von 2 Werktagen.</li>
                            </ul>
                            <p class="mt-2">
                                (4) <strong>Individuelle Aufträge & B2B-Großbestellungen:</strong> Bei individuellen Sonderanfertigungen per E-Mail, Telefon oder über den B2B-Projekt-Kalkulator dieser Website erstellen wir Ihnen ein verbindliches Angebot in Textform. Der Vertrag kommt zustande, sobald Sie dieses Angebot bestätigen oder die vereinbarte Zahlung leisten. Soweit Sie Waren über externe Marktplätze (z. B. Etsy) erwerben, gelten ergänzend die dortigen technischen Schritte zum Vertragsschluss.
                            </p>
                            <p class="mt-2">
                                (5) <strong>Vertragssprache & Vertragstextspeicherung:</strong> Die für den Vertragsschluss zur Verfügung stehende Sprache ist ausschließlich Deutsch. Wir speichern Ihre Bestelldaten im Rahmen der gesetzlichen Aufbewahrungsfristen und senden Ihnen den vollständigen Vertragsinhalt (Bestellübersicht, Personalisierungsdaten, AGB und Widerrufsbelehrung) unverzüglich nach Vertragsschluss auf einem dauerhaften Datenträger (per E-Mail) zu. Registrierte Kunden können ihre Bestellungen zudem jederzeit in ihrem passwortgeschützten Kundenkonto einsehen.
                            </p>
                        </div>

                        {{-- §3 Nutzung des Konfigurators & KI-gestützte Produktdarstellungen --}}
                        <div id="konfigurator" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">3. Nutzung des Produktkonfigurators & KI-gestützte Produktdarstellungen</h3>
                            <p>
                                (1) Der auf der Website bereitgestellte Produktkonfigurator dient als Visualisierungshilfe zur groben Platzierung von Texten, Bildern und Symbolen auf den angebotenen Waren.
                            </p>
                            <p class="mt-2">
                                (2) Die im Konfigurator dargestellte Vorschau ist <strong>nicht maßstabsgetreu</strong> und stellt keine millimetergenaue Abbildung des Endprodukts dar. Geringfügige Abweichungen in der Platzierung, Ausrichtung und Größe (im Bereich handwerklicher Toleranzen) sind produktionsbedingt möglich und stellen keinen Mangel dar.
                            </p>
                            <p class="mt-2">
                                (3) <strong>Technische Umsetzung & Farbwiedergabe:</strong> Der Kunde nimmt zur Kenntnis, dass farbige Bilddateien im Laserverfahren technisch bedingt nicht farbecht wiedergegeben werden können. Die Veredelung erfolgt als dauerhafte Oberflächenveränderung (Gravur), wobei Farben in materialabhängige Kontraststufen (monochrom/Graustufen) umgewandelt werden. Abweichungen zur digitalen RGB-Farbdarstellung am Monitor sind prozessbedingt und stellen keinen Reklamationsgrund dar.
                            </p>
                            <p class="mt-2">
                                (4) Der Kunde ist dafür verantwortlich, die im Konfigurator eingegebenen Inhalte (insbesondere Rechtschreibung von Namen oder Daten) vor Abschluss der Bestellung auf Richtigkeit zu prüfen. Da es sich um individuell nach Kundenspezifikation angefertigte Waren handelt, ist das Widerrufsrecht gemäß § 312g Abs. 2 Nr. 1 BGB ausgeschlossen.
                            </p>
                            <p class="mt-2">
                                (5) <strong>Charakter der grafischen Darstellung:</strong> Der Produktkonfigurator dient ausschließlich der besseren Informationsaufnahme Ihrer individuellen Gestaltungswünsche (Texte, Schriftarten, Symbole und Motive). Da es sich bei der Ansicht im Konfigurator um eine rein digitale, grafische Vorschau handelt, sind darstellungs- und fertigungsbedingte Abweichungen unvermeidbar; das tatsächliche Aussehen des handwerklich veredelten Endprodukts weicht naturgemäß von der digitalen Grafikanzeige ab.
                            </p>
                            <p class="mt-2">
                                (6) <strong>Transparenzhinweis zu KI-generierten und KI-gestützten Produkt- & Szenenbildern (Art. 50 KI-Verordnung / EU AI Act):</strong> Zur anschaulichen Präsentation saisonaler Stimmungen, Feiertagsvorlagen und exemplarischer Gravur-Inszenierungen setzen wir in unserem Online-Shop ergänzend künstliche Intelligenz (KI-gestützte Bildgenerierung und Bildbearbeitung) ein. Entsprechende Produkt-, Vorlagen- und Szenenbilder sind gemäß den Transparenzanforderungen der Verordnung (EU) 2024/1689 (EU AI Act) direkt im Bild mit dem sichtbaren Hinweis <strong>„KI-generiert“</strong> sowie im Quellcode mit dem maschinenlesbaren Attribut <code>data-is-ai-generated="true"</code> gekennzeichnet. Diese Aufnahmen dienen der gestalterischen Inspiration und Visualisierung; maßgeblich für die Beschaffenheit der bestellten Ware sind die jeweilige Produktbeschreibung sowie die im Konfigurator getroffenen Spezifikationen.
                            </p>

                            <div id="urheberrecht-freistellung" class="mt-6 bg-amber-50/70 border-l-4 border-amber-500 p-5 rounded-r-xl space-y-3 scroll-mt-28">
                                <h4 class="font-bold text-gray-900 text-base">Urheber-, Marken- und Persönlichkeitsrechte an Kundenmotiven & Haftungsfreistellung</h4>
                                <p class="text-sm leading-relaxed">
                                    (7) <strong>Rechtegarantie des Kunden & Nutzungsrechtseinräumung:</strong> Sofern der Kunde eigene Texte, Fotos, Grafiken, Logos, Wappen oder sonstige Motive (nachfolgend „Kundenmaterial“) in den Produktkonfigurator oder Kalkulator hochlädt bzw. übermittelt, versichert und garantiert der Kunde ausdrücklich, dass er sämtliche für die vertragliche Herstellung und Veredelung erforderlichen Urheber-, Marken-, Design-, Kennzeichen-, Namens- und Persönlichkeitsrechte (einschließlich des Rechts am eigenen Bild abgebildeter Personen gemäß § 22 KUG / Art. 6 DSGVO) besitzt oder über eine wirksame Lizenz des jeweiligen Rechteinhabers verfügt. Insbesondere dürfen ohne nachweisbare Lizenz <strong>keine geschützten Markenlogos, Vereinswappen, Comic-/Film-/Spielefiguren (z. B. Pokémon, Disney, Nintendo, Marvel), geschützten Songtexte oder fremden Fotografien</strong> zur Gravur in Auftrag gegeben werden. Für die Durchführung des Auftrags räumt der Kunde dem Anbieter ein einfaches, unentgeltliches, auf die Auftragsabwicklung und gesetzliche Beleg-/Archivierungspflicht beschränktes Nutzungs-, Bearbeitungs- und Vervielfältigungsrecht an dem übermittelten Kundenmaterial ein.
                                </p>
                                <p class="text-sm leading-relaxed">
                                    (8) <strong>Freistellung von Ansprüchen Dritter (Haftungsfreistellung):</strong> Der Kunde stellt den Anbieter (<strong>{{ $companyName }} – {{ $ownerProprietor }}</strong>) auf erstes Anfordern von sämtlichen Ansprüchen Dritter frei, die diese wegen einer Verletzung ihrer Urheber-, Marken-, Design-, Persönlichkeits- oder sonstigen Schutzrechte durch die vom Kunden beauftragte und vertragsgemäß ausgeführte Veredelung gegen den Anbieter geltend machen. Diese Freistellungspflicht umfasst auch die erforderlichen und angemessenen Kosten der Rechtsverteidigung des Anbieters (insbesondere Abmahn-, Anwalts- und Gerichtskosten in gesetzlicher Höhe). Der Kunde ist verpflichtet, den Anbieter unverzüglich und wahrheitsgemäß mit allen Informationen und Nachweisen zu unterstützen, die für eine Prüfung der Ansprüche und eine Rechtsverteidigung erforderlich sind. <em>Dies gilt nicht, soweit der Kunde die betreffende Rechtsverletzung nicht zu vertreten hat (§ 307 BGB).</em>
                                </p>
                                <p class="text-sm leading-relaxed">
                                    (9) <strong>Keine rechtliche Vorab-Prüfungspflicht & Ablehnungs-/Rücktrittsrecht des Anbieters:</strong> Eine manuelle Prüfung hochgeladener Dateien durch den Anbieter erfolgt ausschließlich in <strong>technischer Hinsicht</strong> (Auflösung, Kontrast und Gravurfähigkeit); der Anbieter ist nicht verpflichtet, die vom Kunden übermittelten Inhalte auf mögliche Verletzungen von Schutzrechten Dritter zu überprüfen. Der Anbieter behält sich jedoch ausdrücklich das Recht vor, Aufträge und Motive abzulehnen oder von einem bereits geschlossenen Vertrag unverzüglich zurückzutreten, sofern die übermittelten Inhalte gegen geltendes Recht (insbesondere straf- oder verfassungsrechtliche Verbote wie §§ 86, 86a, 130, 184 ff. StGB), die guten Sitten verstoßen oder der begründete Verdacht besteht, dass Urheber-, Marken- oder sonstige Schutzrechte Dritter verletzt werden.
                                </p>
                            </div>
                        </div>

                        <div id="preise-zahlung" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900">3a. Preise, Aktionspreise (§ 11 PAngV) und Zahlung</h3>
                            <p class="mt-2">
                                @if(shop_setting('is_small_business', false))
                                    (1) Die angegebenen Preise sind Endpreise zzgl. Liefer- und Versandkosten. Gemäß § 19 UStG wird keine Umsatzsteuer berechnet und ausgewiesen.
                                @else
                                    (1) Die angegebenen Preise sind Endpreise inklusive der gesetzlichen Umsatzsteuer zzgl. Liefer- und Versandkosten.
                                @endif
                            </p>
                            <p class="mt-2">
                                (2) <strong>Preisermäßigungen & 30-Tage-Bestpreis (§ 11 PAngV):</strong> Bei jeder Bekanntgabe einer Preisermäßigung für eine Ware weisen wir direkt am Produkt den niedrigsten Gesamtpreis aus, den wir innerhalb der letzten 30 Tage vor der Anwendung der Preisermäßigung gegenüber Verbrauchern angewendet haben (§ 11 Abs. 1 PAngV). Etwaige prozentuale oder betragsmäßige Ersparnisangaben beziehen sich stets auf diesen niedrigsten Gesamtpreis der letzten 30 Tage. Wird eine Ware weniger als 30 Tage im Sortiment angeboten, bezieht sich die Referenzangabe auf den niedrigsten Gesamtpreis seit dem Tag des erstmaligen Angebots (§ 11 Abs. 2 PAngV).
                            </p>
                            <p class="mt-2">
                                (3) <strong>Nicht-Kombinierbarkeit von Aktionspreisen und Rabattgutscheinen:</strong> Zeitlich befristete Saison-, Feiertags- oder Aktionsangebote stellen bereits rabattierte Sonderpreise dar und können nicht zusätzlich mit prozentualen Aktions- oder Rabattcodes kombiniert werden, sofern bei der jeweiligen Gutscheinaktion nicht ausdrücklich etwas anderes angegeben ist. Kaufbare Wert- bzw. Geschenkgutscheine sind hiervon unberührt und jederzeit einlösbar.
                            </p>
                            <p class="mt-2">
                                (4) Ihnen stehen folgende Zahlungsarten zur Verfügung:
                            </p>
                            <ul class="list-disc list-inside ml-4 mt-2 mb-2">
                                <li>Vorkasse (Banküberweisung / SEPA-Überweisung)</li>
                                <li>Kreditkarte (Visa, Mastercard, American Express)</li>
                                <li>Apple Pay / Google Pay</li>
                                <li>Klarna (Sofort bezahlen, Rechnung, Ratenkauf)</li>
                                <li>SEPA-Lastschrift</li>
                            </ul>
                            <p>
                                Die Abwicklung der Kartenzahlungen, digitalen Wallets, Klarna- und SEPA-Lastschriftzahlungen erfolgt über den Zahlungsdienstleister <strong>Stripe Payments Europe, Ltd.</strong> Die Belastung Ihres Zahlungsmittels erfolgt unmittelbar nach Abschluss der Bestellung bzw. gemäß den Bedingungen der gewählten Zahlungsart.
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
                            <p class="mt-2">
                                (4) <strong>Harmonisierte Mitteilung über die gesetzliche Gewährleistung der Vertragsmäßigkeit (EU-Recht):</strong> Für alle in unserem Online-Shop angebotenen Waren gilt die gesetzliche Gewährleistung der Vertragsmäßigkeit von mindestens <strong>zwei Jahren</strong> ab dem Zeitpunkt der Lieferung der Waren gemäß Richtlinie (EU) 2024/825 und Durchführungsverordnung (EU) 2025/1960. Verbraucherinnen und Verbraucher können ihre Rechte im Rahmen des gesetzlichen Gewährleistungsrechts geltend machen, wenn Waren nicht der Beschreibung entsprechen oder nicht ordnungsgemäß funktionieren.
                            </p>

                            {{-- Offizieller EU-Gewährleistung Hinweis --}}
                            <div class="mt-6 bg-gradient-to-br from-blue-50/80 via-white to-indigo-50/80 border border-blue-200 rounded-2xl p-6 shadow-sm">
                                <div class="flex flex-col md:flex-row gap-6 items-start">
                                    <div class="flex-shrink-0 mx-auto md:mx-0 w-36 bg-white p-2.5 rounded-xl border border-blue-200 shadow-sm text-center">
                                        <a href="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.svg') }}" target="_blank" class="block group" title="Offizielles EU-Hinweisblatt anzeigen">
                                            <img src="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.svg') }}" 
                                                 alt="EU Harmonisierte Mitteilung über die gesetzliche Gewährleistung der Vertragsmäßigkeit" 
                                                 class="w-full h-auto rounded group-hover:scale-[1.02] transition-transform">
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary hover:text-primary-dark mt-2.5 group-hover:underline transition-colors">
                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                                Dokument öffnen
                                            </span>
                                        </a>
                                    </div>
                                    <div class="flex-1 space-y-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-600 text-white font-bold text-xs shadow-xs">🇪🇺</span>
                                            <h4 class="font-bold text-blue-950 text-base">Gesetzliche Gewährleistung in der EU</h4>
                                        </div>
                                        <p class="text-sm text-blue-950/90 leading-relaxed">
                                            Verkäufer haften für jede Vertragswidrigkeit, die zum Zeitpunkt der Lieferung der Waren bestand und innerhalb des gesetzlichen Gewährleistungszeitraums erkennbar wird. In solchen Fällen haben Sie Anspruch auf:
                                        </p>
                                        <ul class="text-xs text-blue-950 space-y-2 list-none font-medium bg-white/80 p-3.5 rounded-xl border border-blue-100 shadow-xs">
                                            <li class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                <span><strong>Kostenlose Nachbesserung</strong> (Reparatur) oder <strong>kostenlose Ersatzlieferung</strong></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                                <span>In bestimmten gesetzlichen Fällen eine <strong>Preisminderung</strong> oder <strong>vollständige Kaufpreiserstattung</strong></span>
                                            </li>
                                            <li class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                <span>Dauer: <strong>Mindestens 2 Jahre</strong> ab Lieferung (in der gesamten Europäischen Union)</span>
                                            </li>
                                        </ul>
                                        <div class="pt-2 flex flex-wrap items-center gap-4 text-xs font-semibold">
                                            <a href="https://europa.eu/youreurope/garantien" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary hover:text-primary-dark underline transition-colors">
                                                <span>Offizielle Informationen auf Your Europe (EU)</span>
                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            </a>
                                            <a href="{{ asset('shop/frontend/legal/eu_gewaehrleistung_notice_de.png') }}" download="EU_Gesetzliche_Gewaehrleistung.png" class="inline-flex items-center gap-1 text-primary hover:text-primary-dark underline transition-colors">
                                                <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                                Mitteilung als Bild herunterladen (PNG)
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- §6a Produktsicherheit & Herstellerangaben (GPSR) --}}
                        <div id="produktsicherheit" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">6a. Produktsicherheit & Herstellerangaben gemäß Verordnung (EU) 2023/988 (GPSR)</h3>
                            <p>
                                (1) <strong>Verantwortlicher Hersteller / Veredler in der Europäischen Union (Art. 19 GPSR):</strong><br>
                                {{ $companyName }} – Inhaberin {{ $ownerProprietor }}, {{ $companyStreetLine }}, {{ $companyCityLine }}, Deutschland. Elektronische Kontaktadresse für Produktsicherheit: <a href="mailto:{{ $companyEmail }}" class="text-primary hover:underline">{{ $companyEmail }}</a>.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Allgemeine Sicherheits- und Verwendungshinweise:</strong> Unsere veredelten Produkte aus optischem K9-Kristallglas, Naturschiefer, Massivholz sowie Edelstahl/eloxiertem Aluminium sind als hochwertige Dekorations-, Erinnerungs- und Gebrauchsgegenstände konzipiert und stellen kein Kinderspielzeug im Sinne der Spielzeugrichtlinie 2009/48/EG dar.
                            </p>
                            <ul class="list-disc list-inside mt-2 space-y-1 text-sm text-gray-700 ml-2">
                                <li><strong>Kleinteile-Warnung (Schlüsselanhänger, Schmuck & Haustiermarken):</strong> Produkte mit Befestigungsringen, Ösen oder Karabinern enthalten verschluckbare Kleinteile (Erstickungsgefahr für Kinder unter 36 Monaten).</li>
                                <li><strong>Bruchgefahr bei Kristallglas & Naturschiefer:</strong> Bei Sturz oder starker mechanischer Einwirkung können scharfkantige Bruchstücke oder Splitter entstehen. Beschädigte Glas- oder Schieferprodukte nicht weiterverwenden; Kristallglas nicht dauerhaft gebündelter direkter Sonneneinstrahlung in der Nähe leicht entflammbarer Materialien aussetzen (Brennglaseffekt).</li>
                                <li><strong>Pflegehinweis:</strong> Lasergravierte Oberflächen bitte ausschließlich mit einem weichen, leicht angefeuchteten Mikrofasertuch ohne scheuernde oder lösungsmittelhaltige Reinigungsmittel säubern (nicht spülmaschinengeeignet).</li>
                            </ul>
                        </div>

                        {{-- §7 Verbraucherstreitbeilegung --}}
                        <div id="streitbeilegung" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">7. Verbraucherstreitbeilegung</h3>
                            <p>
                                <strong>Information gemäß § 36 VSBG:</strong><br>
                                Wir sind zur Teilnahme an einem Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle weder verpflichtet noch bereit. (Hinweis: Die ehemalige EU-Plattform zur Online-Streitbeilegung wurde gemäß Verordnung (EU) 2024/3228 zum 20.07.2025 endgültig eingestellt.)
                            </p>
                        </div>

                        {{-- §8 Seelenfunken-Treue- & Bonusprogramm --}}
                        <div id="agb-gamification" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">8. Seelenfunken-Treue- & Bonusprogramm</h3>
                            <p>
                                (1) <strong>Zweck & Freiwilligkeit:</strong> Mein-Seelenfunke stellt registrierten Kundinnen und Kunden im Kundenkonto („Dashboard“) das kostenfreie Treue- und Sammlerprogramm „Seelenfunken“ sowie den virtuellen Begleiter „Funki“ zur Verfügung. Die Teilnahme sowie die optionale Aktivierung der Website-Funkensuche („Magie aktivieren“) erfolgen freiwillig und unentgeltlich und können im Kundenbereich jederzeit pausiert oder wieder aufgenommen werden.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Sammeln von Seelenfunken, Funki-Level (1–10), Titel & Halle der Helden:</strong> Kundinnen und Kunden erhalten für abgeschlossene, bezahlte Bestellungen automatisch Treuepunkte („Seelenfunken“, in der Regel 1 Seelenfunke je 1,00 € bezahltem Warenwert), für freigegebene verifizierte Produktbewertungen, als jährliche Geburtstags- bzw. Stammkunden-Überraschung sowie nach Aktivierung der Funkensuche durch das gelegentliche Entdecken versteckter Seelenfunken auf den Shop-Seiten. Im Falle eines Widerrufs, einer Stornierung oder Rückerstattung einer Bestellung werden die hierfür gutgeschriebenen Seelenfunken entsprechend wieder in Abzug gebracht. Anhand der insgesamt gesammelten Lebenszeit-Seelenfunken entwickelt sich das Profil-Begleiterbild „Funki“ automatisch und kostenfrei über zehn Stufen (Level 1 bis 10) inklusive der zugehörigen Stufen-Hintergründe sowie freischaltbarer Ehren- und Rangtitel weiter; diese Darstellung dient als Standard-Profilbild im Kundenbereich sowie bei nicht-anonymen Produktbewertungen, sofern kein eigenes Profilfoto hinterlegt wurde. Optional können Teilnehmende per ausdrücklichem Opt-In (jederzeit widerrufbar) in der öffentlichen Sammler-Rangliste („Halle der Helden“) wahlweise mit einem frei gewählten Spielernamen oder mit Vornamen und Nachnamen-Initial erscheinen. Seelenfunken sind rein virtuelle Treuepunkte ohne Geldwert, nicht übertragbar und nicht in Bargeld auszahlbar.
                            </p>
                            <p class="mt-2">
                                (3) <strong>Einlösung in Rabatt-Gutscheine:</strong> Gesammelte, verfügbare Seelenfunken können im Kundenkonto gegen persönliche Einmal-Rabattgutscheine für künftige Bestellungen eingelöst werden (z. B. 60 Seelenfunken für 5 % Rabatt, 150 Seelenfunken für 10 % Rabatt, 350 Seelenfunken für 15 % Rabatt). Das erreichte Funki-Level und die Lebenszeit-Funken bleiben beim Einlösen von Seelenfunken vollständig erhalten. Die erzeugten Rabattcodes sind personengebunden an das jeweilige Kundenkonto geknüpft, nicht übertragbar, nicht mit anderen Rabattaktionen oder dem Kauf von Geschenkgutscheinen kombinierbar und jeweils einmalig einlösbar.
                            </p>
                            <p class="mt-2">
                                (4) <strong>Manipulationsschutz & Sanktionen:</strong> Jede Form der technischen Manipulation, die Ausnutzung von Software-Fehlern oder der Einsatz automatisierter Skripte, Bots oder Reload-Mechanismen zum künstlichen Sammeln von Seelenfunken ist strengstens untersagt und wird serverseitig durch kryptografische Einmal-Token sowie Zeit- und Tageslimits unterbunden. Bei Verstößen behält sich Mein-Seelenfunke das Recht vor, unrechtmäßig erlangte Seelenfunken und Gutscheine ersatzlos zu stornieren und das Konto vom Treueprogramm auszuschließen.
                            </p>
                            <p class="mt-2 text-sm bg-gray-50 border-l-4 border-gray-400 p-3 rounded">
                                (5) <strong>Änderungsvorbehalt & Haftungsausschluss:</strong> Da es sich um eine freiwillige, unentgeltliche Treueleistung handelt, besteht kein Rechtsanspruch auf eine bestimmte Verfügbarkeit oder Fortführung des Bonusprogramms. Mein-Seelenfunke behält sich vor, die Bedingungen, Punktewerte und Prämienstufen jederzeit mit Wirkung für die Zukunft anzupassen oder das Programm einzustellen.
                            </p>
                        </div>

                        {{-- §9 Geschenkgutscheine & BGB-Verjährungsfrist --}}
                        <div id="gutscheine-verjaehrung" class="scroll-mt-28">
                            <h3 class="font-bold text-lg text-gray-900 mb-2">9. Geschenkgutscheine & BGB-Verjährungsfrist</h3>
                            <p>
                                (1) <strong>Gültigkeitsdauer:</strong> Für unsere erworbenen Geschenkgutscheine (Wertgutscheine) gilt die regelmäßige gesetzliche Verjährungsfrist gemäß § 195 BGB. Diese beträgt <strong>3 Jahre</strong>, beginnend mit dem Schluss des Jahres, in dem der Gutschein ausgestellt wurde.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Einlösung und Teileinlösung:</strong> Gutscheine können für alle Produkte im Online-Shop des Anbieters eingelöst werden. Reicht der Wert des Gutscheins zur Deckung der Bestellung nicht aus, kann die Differenz über eine der anderen angebotenen Zahlungsarten beglichen werden. Bei einer Teileinlösung verbleibt das Restguthaben auf dem Gutschein und kann für künftige Einkäufe innerhalb der Gültigkeitsfrist verwendet werden. Eine Barauszahlung des Gutscheinwerts oder des Restguthabens ist ausgeschlossen.
                            </p>
                        </div>

                        {{-- §10 Seelenfunke Creator Partnerprogramm (B2B-Kooperationsbedingungen) --}}
                        <div id="creator-partnerprogramm" class="scroll-mt-28 bg-amber-50/40 border border-primary/20 rounded-2xl p-6 sm:p-8 shadow-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                <h3 class="font-bold text-lg text-gray-900">10. Seelenfunke Creator Partnerprogramm (B2B-Kooperations- & Affiliate-Bedingungen)</h3>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-primary/15 text-primary-dark border border-primary/25">
                                    Nur für Creator / B2B (§ 14 BGB)
                                </span>
                            </div>
                            <p>
                                (1) <strong>Geltungsbereich & Unternehmerstatus (§ 14 BGB):</strong> Die nachfolgenden besonderen Bedingungen gelten ausschließlich für Teilnehmerinnen und Teilnehmer („Creator“ / „Partner“) am <em>Seelenfunke Creator Partnerprogramm</em>. Die Teilnahme richtet sich ausschließlich an unbeschränkt geschäftsfähige natürliche oder juristische Personen, die bei Abschluss der Kooperationsvereinbarung in Ausübung ihrer gewerblichen oder selbstständigen beruflichen Tätigkeit handeln (<strong>Unternehmer im Sinne des § 14 BGB</strong>). Durch die Teilnahme wird <strong>kein Arbeitsverhältnis, kein Handelsvertreterverhältnis (§ 84 HGB) und keine Gesellschaft</strong> begründet; der Creator handelt frei, weisungsungebunden und auf eigenes unternehmerisches Risiko.
                            </p>
                            <p class="mt-2">
                                (2) <strong>Eigenverantwortliche Versteuerung & Gutschriftsverfahren (§ 14 Abs. 2 S. 2 UStG):</strong> Der Creator versichert, sämtliche aus dem Partnerprogramm erzielten Einnahmen (Provisionen sowie ggf. unentgeltlich überlassene Produkte/PR-Samples nach § 37b EStG bzw. als Betriebseinnahmen) eigenverantwortlich gegenüber dem zuständigen Finanzamt zu erklären und zu versteuern. Die Abrechnung der Provisionen erfolgt im steuerlichen <strong>Gutschriftsverfahren gemäß § 14 Abs. 2 Satz 2 UStG</strong> durch den Anbieter auf Basis der vom Creator im Creator-Cockpit hinterlegten Steuerdaten (Steuernummer / USt-IdNr. sowie Wahl zwischen Kleinunternehmerregelung gem. § 19 UStG, Regelbesteuerung mit dem im Shop gültigen gesetzlichen Umsatzsteuersatz oder Reverse-Charge im EU-Ausland). Der Creator verpflichtet sich, Änderungen seines steuerlichen Status oder seiner Unternehmensdaten unverzüglich im Creator-Cockpit zu aktualisieren; für Schäden oder Steuernachforderungen aufgrund unzutreffender Angaben haftet der Creator im Innenverhältnis vollumfänglich.
                            </p>
                            <p class="mt-2">
                                (3) <strong>Provisionsentstehung, Netto-Bemessungsgrundlage & Eigenkauf-Sperre:</strong> Ein Provisionsanspruch entsteht ausschließlich für solche Bestellungen von Endkunden im Online-Shop des Anbieters, die nachweislich über den dem Creator persönlich zugewiesenen, aktiven Tracking-Link oder Rabattcode vermittelt wurden, vollständig bezahlt sind und nicht widerrufen, storniert oder rückabgewickelt wurden. Die Berechnung der prozentualen Provision erfolgt stets auf Basis des <strong>Netto-Warenwerts</strong> (abzüglich Umsatzsteuer, Versandkosten und eingelöster Rabatte/Gutscheine). <strong>Eigenkäufe des Creators</strong> (Bestellungen unter eigenem Namen, eigener E-Mail-Adresse, eigener Zahlungs- oder Lieferadresse) sowie Bestellungen im eigenen Haushalt sind von der Provisionsvergütung strikt ausgeschlossen und werden vom System automatisch herausgefiltert.
                            </p>
                            <p class="mt-2">
                                (4) <strong>Freigabefrist (Clearing), Mindestauszahlung & Auszahlungsabwicklung:</strong> Vermittelte Provisionen werden zunächst als vorgemerktes Guthaben („In Prüfung“) erfasst und erst nach vollständigem Zahlungseingang, Versand und Ablauf etwaiger Storno-/Reklamationsfristen zur Auszahlung freigegeben. Bei Stornierung, Rücktritt oder Zahlungsausfall des Endkunden entfällt der Provisionsanspruch rückwirkend. Die selbstständige Auszahlungsanforderung freigegebener Guthaben im Creator-Cockpit ist ab Erreichen des Mindestauszahlungsbetrags von <strong>20,00 € Netto</strong> per SEPA-Überweisung, PayPal oder als Shop-Gutschein (+20 % Bonus) möglich; bei Beendigung der Partnerschaft wird ein etwaiges verbleibendes Restguthaben auch unterhalb der Mindestgrenze abgerechnet.
                            </p>
                            <p class="mt-2">
                                (5) <strong>Werbekennzeichnungspflicht & Markenrichtlinien:</strong> Der Creator verpflichtet sich, sämtliche Veröffentlichungen, Beiträge, Stories und Videos, die im Zusammenhang mit Produkten, Tracking-Links oder Rabattcodes von Mein Seelenfunke stehen, im Einklang mit den gesetzlichen Vorgaben (insbesondere <strong>§ 5a Abs. 4 UWG</strong> sowie <strong>§ 22 Medienstaatsvertrag, MStV</strong>) eindeutig, transparent und auf den ersten Blick erkennbar als <strong>„Werbung“</strong> oder <strong>„Anzeige“</strong> zu kennzeichnen. Irreführende Aussagen über Produkteigenschaften, künstliches Cookie-Dropping, Suchmaschinen-Markenbieten (Brand-Bidding auf „Mein Seelenfunke“) oder die Verbreitung rechtswidriger Inhalte sind untersagt.
                            </p>
                            <p class="mt-2">
                                (6) <strong>Laufzeit, Kündigung & Sperrung bei Missbrauch:</strong> Die Teilnahme am Creator Partnerprogramm läuft auf unbestimmte Zeit und kann von beiden Parteien jederzeit ordentlich in Textform (oder durch Deaktivierung des Creator-Zugangs) beendet werden. Das Recht zur außerordentlichen fristlosen Kündigung aus wichtigem Grund (insbesondere bei Verstoß gegen die Werbekennzeichnungspflicht, Manipulationsversuchen oder Falschangaben zum Unternehmer-/Steuerstatus) bleibt unberührt.
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
                    <div class="space-y-4 mb-10">
                        {{-- Physische Personalisierung --}}
                        <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-r-xl shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 mt-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-red-800 text-lg">Ausschluss bei personalisierten Produkten</h3>
                                    <p class="text-red-800 mt-2 text-sm leading-relaxed">
                                        Das Widerrufsrecht besteht <strong>nicht</strong> bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (§ 312g Abs. 2 Nr. 1 BGB).
                                    </p>
                                    <p class="text-red-900 mt-3 font-bold text-sm bg-white/50 p-2 rounded inline-block border border-red-100">
                                        Dies gilt für alle Produkte, die speziell für Sie graviert oder anderweitig personalisiert werden. Hierunter fallen ausdrücklich auch alle unsere Design-Vorlagen. Da diese Artikel erst nach Bestelleingang individuell auf Ihren Wunsch hin gefertigt werden, erlischt das Widerrufsrecht auch dann, wenn Sie an der Basis-Vorlage keine weiteren Änderungen vorgenommen haben.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Digitale Inhalte --}}
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded-r-xl shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 mt-1 text-blue-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-blue-800 text-lg">Erlöschen bei digitalen Inhalten</h3>
                                    <p class="text-blue-800 mt-2 text-sm leading-relaxed">
                                        Das Widerrufsrecht erlischt bei einem Vertrag über die Lieferung von nicht auf einem körperlichen Datenträger befindlichen digitalen Inhalten (z.B. Downloads, E-Books, Design-Vorlagen), wenn der Unternehmer mit der Ausführung des Vertrags begonnen hat, nachdem der Verbraucher:
                                    </p>
                                    <ul class="list-disc list-outside ml-4 mt-2 text-blue-800 text-sm font-medium">
                                        <li>ausdrücklich zugestimmt hat, dass der Unternehmer mit der Ausführung des Vertrags vor Ablauf der Widerrufsfrist beginnt, und</li>
                                        <li>seine Kenntnis davon bestätigt hat, dass er durch seine Zustimmung mit Beginn der Ausführung des Vertrags sein Widerrufsrecht verliert.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Wichtige Abgrenzung: Widerruf vs. Gesetzliche Gewährleistung --}}
                        <div class="bg-indigo-50 border-l-4 border-indigo-500 p-6 rounded-r-xl shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 mt-1 text-indigo-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-indigo-900 text-lg">Wichtige Abgrenzung: Widerrufsrecht vs. Gesetzliche Gewährleistung</h3>
                                    <p class="text-indigo-900 mt-2 text-sm leading-relaxed">
                                        Das hier geregelte <strong>14-tägige Widerrufsrecht</strong> dient der anlasslosen Rückabwicklung (und ist bei individueller Kundenanfertigung gesetzlich ausgeschlossen). Sollte ein von Ihnen bestelltes Produkt hingegen <strong>beschädigt, fehlerhaft oder vertragswidrig</strong> geliefert werden, bleibt Ihr gesetzlicher Anspruch in jedem Fall unberührt: Hierfür gilt die EU-weit harmonisierte <strong>gesetzliche Gewährleistung von mindestens 2 Jahren</strong> mit Anspruch auf kostenlose Reparatur oder Neulieferung.
                                    </p>
                                    <div class="mt-3 flex items-center gap-2">
                                        <a href="#gewaehrleistung" class="inline-flex items-center gap-1.5 text-xs font-bold text-primary hover:text-primary-dark underline underline-offset-2 transition-colors">
                                            <span>Mehr zur gesetzlichen Gewährleistung & EU-Hinweispflicht in Ziffer 6</span>
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Hinweis für Seelenfunke Creator Partnerprogramm (B2B-Ausschluss des Verbraucherwiderrufsrechts) --}}
                        <div id="widerruf-creator-partnerprogramm" class="scroll-mt-28 bg-amber-50 border-l-4 border-primary p-6 rounded-r-xl shadow-sm">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 mt-1 text-primary-dark">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg">Hinweis für Teilnehmer am „Seelenfunke Creator Partnerprogramm“ (B2B / § 14 BGB)</h3>
                                    <p class="text-gray-800 mt-2 text-sm leading-relaxed">
                                        Das gesetzliche Verbraucher-Widerrufsrecht gemäß §§ 312g, 355 BGB gilt ausschließlich für <strong>Verbraucher im Sinne des § 13 BGB</strong>. Da die Teilnahme am <em>Seelenfunke Creator Partnerprogramm</em> ausschließlich selbstständigen Creatorn, Freiberuflern und Gewerbetreibenden vorbehalten ist, die bei Registrierung und Abschluss der Kooperationsvereinbarung als <strong>Unternehmer im Sinne des § 14 BGB</strong> auf eigene Rechnung handeln, besteht für den Kooperations- und Affiliate-Vertrag <strong>kein gesetzliches Verbraucher-Widerrufsrecht</strong>.
                                    </p>
                                    <p class="text-gray-700 mt-2 text-xs leading-relaxed">
                                        Unabhängig davon kann die Teilnahme am Creator Partnerprogramm gemäß <a href="#creator-partnerprogramm" class="font-bold text-primary hover:text-primary-dark underline underline-offset-2">§ 10 Abs. 6 unserer AGB</a> jederzeit ordentlich und ohne Einhaltung einer Mindestvertragslaufzeit beendet werden.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="prose prose-gray max-w-none text-gray-700">
                        <div class="bg-gray-100 border-l-4 border-gray-700 p-6 rounded-r-xl shadow-sm mb-8">
                            <h3 class="font-bold text-gray-900 text-lg mb-2">Elektronische Widerrufsfunktion gemäß § 356a BGB („Vertrag widerrufen“)</h3>
                            <p class="text-gray-700 text-sm leading-relaxed">
                                Soweit Ihnen für eine Bestellung ein gesetzliches Widerrufsrecht zusteht (z. B. beim Erwerb nicht-personalisierter Standardartikel oder Geschenkgutscheine), können Sie Ihren Widerruf gemäß <strong>§ 356a BGB</strong> auch direkt elektronisch über unsere 2-stufige Online-Widerrufsfunktion unter <a href="{{ route('widerruf') }}" class="text-primary font-bold underline hover:text-primary-dark">Vertrag widerrufen</a> (jederzeit erreichbar in der Fußzeile unserer Website) erklären. Nach Eingabe Ihrer Bestelldaten (Stufe 1: „Vertrag widerrufen“) und verbindlicher Bestätigung (Stufe 2: „Jetzt verbindlich widerrufen“) übermitteln wir Ihnen unverzüglich eine Eingangsbestätigung Ihres Widerrufs inklusive Zeitstempel auf einem dauerhaften Datenträger (per E-Mail).
                            </p>
                        </div>
                        <p class="font-medium bg-gray-100 p-2 inline-block rounded text-sm mb-4">
                            Für alle anderen Waren (z.B. Standard-Zubehör ohne Personalisierung) gilt folgendes Widerrufsrecht:
                        </p>

                        <h3 class="font-bold text-lg text-gray-900">Widerrufsrecht</h3>
                        <p>
                            Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.
                            Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.
                        </p>
                        <p>
                            Um Ihr Widerrufsrecht auszuüben, müssen Sie uns (<strong>{{ $companyName }}, {{ $ownerProprietor }}, {{ $companyStreetLine }}, {{ $companyCityLine }}, Telefon: {{ $companyPhone }}, E-Mail: {{ $companyEmail }}</strong>) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.
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

                        <a href="{{ route('widerruf.pdf') }}"
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
                            {{ $companyName }}<br>
                            {{ $ownerProprietor }}<br>
                            {{ $companyStreetLine }}<br>
                            {{ $companyCityLine }}<br>
                            Telefon: {{ $companyPhone }}<br>
                            E-Mail: {{ $companyEmail }}
                        </p>

                        <p class="mb-6">
                            Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*) / die Erbringung der folgenden Dienstleistung (*):
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
