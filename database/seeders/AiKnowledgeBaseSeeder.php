<?php

namespace Database\Seeders;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiWorkspaceDocument;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiKnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates comprehensive AI knowledge base from Mein-Seelenfunke company policies,
     * legal/medical/financial master dossiers, and the private workspace document catalog.
     */
    public function run(): void
    {
        // 1. Kategorien definieren und anlegen
        $categories = [
            'Unternehmen & Kontakt' => 'Grundlegende Informationen über Mein-Seelenfunke, Inhaberin und Kontaktwege.',
            'Konto & Datenschutz' => 'Login, Passwort, Datenlöschung und Adressänderungen.',
            'Bestellung & Logistik' => 'Versand, DHL, Tracking und internationale Lieferungen.',
            'Zahlung & Checkout' => 'Bezahlmethoden, Steuern und Rechnungskauf.',
            'Rückgabe & Garantie' => 'Retouren, Mängel und gesetzliche Gewährleistung.',
            'Produkte & B2B' => 'Sortiment, Verfügbarkeiten, Großbestellungen und Rabatte.',
            'Rechtliches & AGB' => 'Widerrufsrecht, AGB, Zahlungs- und Lieferbedingungen.',
            'Datenschutz & Infrastruktur' => 'Informationen zur Datenverarbeitung, Server-Hosting und KI.',
            'Bestell- & Produktionsprozess' => 'Konfigurator, Laser-Personalisierung und Toleranzen.',
            'Gamification & 3D-Welt' => '3D-Dashboard, Mini-Spiele, Level-System und Rabattcodes.',
            'Support & Kundenservice' => 'Umgang mit Reklamationen, Beschwerden und Eskalationen.',
            'Buchhaltung & Steuern' => 'Wissen über DATEV-Exporte, Steuersätze, Sonderausgaben, Privates vs. Gewerbliches und BWAs.',
            'Laserschutz & Sicherheit' => 'Alles rund um die sichere Bedienung der Lasermaschinen und die Laserschutzschulung.',
            'Laserbauarten & Anwendungen' => 'Detailwissen zu verschiedenen Laser-Bauarten, Medien und deren spezifische Einsatzgebiete.',
            'Gesundheit & Sozialrecht (BKK firmus)' => 'Rechtliche & medizinische Verfahrensakten, Krankengeld, GA-OP und Sozialgerichtsverfahren.',
            'Existenzgründung & Arbeitsagentur' => 'Gründungszuschuss § 93 SGB III, Businessplan, Fachkundige Stelle und 150-Tage-Regel.',
            'Finanz-Audit & Leistungsansprüche' => 'Anspruchsvergleiche (ALG 1 vs. KG), Kontostände und Liquiditätsanalysen.',
            'Workspace-Dokumentenkatalog' => 'Master-Inventar aller vertraulichen Dokumente im privaten Workspace.',
        ];

        $catMap = [];
        foreach ($categories as $name => $desc) {
            $catMap[$name] = AiKnowledgeBaseCategory::firstOrCreate([
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
            ])->id;
        }

        // 2. Tags definieren und anlegen
        $tags = [
            'Kontakt', 'Recht', 'Personalisierung', 'Laser', 'Gamification', 'Datenschutz', 'Widerruf', 'Etsy',
            'Zahlung', '3D', 'KI-Sicherheit', 'Support', 'Ticket', 'Reklamation', 'Versand', 'Retoure', 'B2B',
            'Produkte', 'Buchhaltung', 'Steuern', 'Sonderausgaben', 'Export', 'Schulung', 'Sicherheit',
            'Bauarten', 'Anwendungen', 'Wissen', 'Tabelle',
            'BKK firmus', 'Krankengeld', 'GA-OP', 'Widerspruch', 'eAU', 'Krankschreibungen', 'Aussteuerung',
            'Endokrinologikum', 'Laborbefunde', 'Humangenetik', 'Hormontherapie', 'Dorothea Jung',
            'Dr. Christian Schmidt', 'Sozialgericht', 'Eilantrag § 86b', 'Gründungszuschuss',
            'Mein Justizpostfach', 'BundID', 'Businessplan', 'Liquiditätsplan', 'Tragfähigkeitsbescheinigung',
            'Agentur für Arbeit', 'Finanz-Audit', 'Volksbank eG', 'ALG 1', 'Workspace', 'Dokumente', 'Existenzgründung',
        ];
        $tagMap = [];
        foreach ($tags as $tagName) {
            $tagMap[$tagName] = AiKnowledgeBaseTag::firstOrCreate([
                'slug' => Str::slug($tagName),
            ], [
                'name' => $tagName,
            ])->id;
        }

        // 3. Shop- & Unternehmens-Artikelstruktur (Knowledge Base Entries)
        $articles = [
            // Artikel 1: Identität
            [
                'title' => 'Unternehmensidentität & Kontakt',
                'category' => 'Unternehmen & Kontakt',
                'tags' => ['Kontakt'],
                'content' => "
# Mein-Seelenfunke Identität
**Anschrift:** Carl-Goerdeler-Ring 26, 38518 Gifhorn
**Gerichtsstand:** Gifhorn

**Offizielle Kontaktmöglichkeiten:**
- E-Mail: kontakt@mein-seelenfunke.de
- Telefon: +49 159 019 668 64
- Webseite: www.mein-seelenfunke.de
- Support-Öffnungszeiten: 24/7 über Funki, ansonsten Bearbeitung der offenen Tickets durch unser internes Support-Team Werktags zwischen 09:00 und 17:00 Uhr. Es existiert kein Live-Chat mit Menschen.

**Unternehmensfokus & Brand:**
Mein-Seelenfunke ist eine Manufaktur für hochindividualisierte, personalisierte Produkte. Der Schwerpunkt liegt auf präzisen Lasergravuren auf hochwertigen Naturmaterialien. Wir fertigen zu 100% nachhaltig im Schwarzwald durch zertifizierte kleine Betriebe. Der Verkauf erfolgt primär über den eigenen Online-Shop sowie Etsy. 
Der Kundenservice wird durch ein fortschrittliches KI-Netzwerk verwaltet, welches eng mit der internen Produktions- und Supportleitung zusammenarbeitet.
                "
            ],
            // Artikel 2: Konto & Profile
            [
                'title' => 'Konto, Profileinstellungen & Datenschutz',
                'category' => 'Konto & Datenschutz',
                'tags' => ['Datenschutz'],
                'content' => "
# Kundenkonto und Gastbestellungen
**Account Erstellung & Gast-Checkout:** Kunden können einfach im Checkout ein kostenloses Kundenkonto anlegen. Alternativ ist eine Bestellung jederzeit als Gast ohne Account möglich.
**Passwort vergessen:** Sollte der Login fehlschlagen, gibt es im Login-Bereich den Button 'Passwort vergessen', um eine Rücksetzungs-E-Mail anzufordern.
**Änderung von E-Mail & Adresse:** Die Adresse und E-Mail-Adresse lassen sich jederzeit problemlos im eigenen Dashboard (Kundenprofil) ändern.
**Newsletter:** Eine Abmeldung vom Newsletter ist jederzeit über den Unsubscribe-Link am Ende jeder Werbe-E-Mail oder im Profil möglich.
**Konto löschen & DSGVO:** Kunden haben das Recht, ihre Daten komplett löschen zu lassen. Eine Kontolöschung oder ein DSGVO-Datenexport kann über den Bereich 'Sicherheit & Datenschutz' im Profil oder per E-Mail an den Support beantragt werden.
                "
            ],
            // Artikel 3: Versand & Logistik
            [
                'title' => 'Versand, DHL, Tracking & Ausland',
                'category' => 'Bestellung & Logistik',
                'tags' => ['Versand'],
                'content' => "
# Alles rund um Lieferung
**Versanddauer & Kosten:** Der Versand erfolgt klimaneutral (Eco-Packaging ohne Plastik) innerhalb von 3-5 Werktagen nach Produktionsbeginn. Die Versandkosten innerhalb Deutschlands betragen 4,90 €. Ab 50 € Bestellwert ist der Versand komplett kostenlos.
**Dienstleister & Tracking:** Wir versenden standardmäßig mit DHL. Den Tracking-Link (Sendungsverfolgung) erhält der Kunde automatisch in der Versandbestätigungs-E-Mail, sobald das Paket fertig gepackt ist. Er ist auch im Bereich 'Bestellhistorie' des Accounts einsehbar.
**Fehlende Bestellbestätigung:** Falls keine Bestätigung im Postfach ist, sollte der Kunde den Spam-Ordner prüfen oder nachsehen, ob ein Tippfehler in der E-Mail vorliegt. Im Zweifel prüft das Support-Team das Konto.
**Internationaler Versand, Zoll & Steuern:** Wir liefern (mit DHL) auch international in die gesamte EU sowie nach Österreich und Schweiz für eine Pauschale von 29,90 €. Außerhalb der EU (z.B. Schweiz) können landesspezifische Zoll-Gebühren oder Importkosten anfallen, die der Kunde trägt.
**Spezial-Wünsche (Click&Collect, Zeitfenster):** Eine Selbstabholung (Click & Collect) unserer Waren ist nicht möglich (reiner Online-Betrieb). Auch fixe Lieferzeitfenster können bei DHL Standardversand nicht garantiert werden.
                "
            ],
            // Artikel 4: Zahlung
            [
                'title' => 'Zahlungsmethoden & Rechnungen',
                'category' => 'Zahlung & Checkout',
                'tags' => ['Zahlung'],
                'content' => "
# Bezahlung im Shop
**Methoden:** Wir akzeptieren PayPal, Kreditkarte, Vorkasse und Klarna (für Kauf auf Rechnung und Ratenkauf).
**Kauf auf Rechnung:** Über unseren Partner Klarna bieten wir ab der ersten Bestellung den klassischen Kauf auf Rechnung an.
**Abgelehnt / Fehlgeschlagen:** Wenn eine Zahlung (besonders Klarna oder PayPal) abgelehnt wird, liegt dies restriktiv an der automatischen Bonitätsprüfung des Dienstleisters oder am Banklimit. Wir helfen da nicht manuell - der Kunde sollte es einfach per Vorkasse erneut probieren.
**Rechnungsdokument:** Die offizielle Steuerrechnung (Invoice) erhält der Kunde als PDF nach Abschluss der Bestellung per E-Mail. Zusätzlich ist diese für eingeloggte User in der Bestellhistorie zum erneuten Download verfügbar.
**Währung & MwSt:** Alle ausgewiesenen Preise sind inklusive der gesetzlichen Mehrwertsteuer (Taxes included). Wir fertigen nur Rechnungen und rechnen in Euro (EUR) ab, andere Währungen können im Shop nicht erzwungen werden.
**Sicherheit:** Sämtliche Zahlungen sind SSL-verschlüsselt und werden hochsicher anonym über Stripe oder PayPal verarbeitet - wir sehen keine sensiblen Bankdaten.
                "
            ],
            // Artikel 5: Reklamation
            [
                'title' => 'Reklamation, Retouren & Garantie',
                'category' => 'Rückgabe & Garantie',
                'tags' => ['Retoure', 'Reklamation', 'Widerruf'],
                'content' => "
# Rückgabe und Mängel
**Normale Retouren (Widerrufsrecht):** Der Kunde hat ein 14-tägiges Rückgaberecht nach Erhalt der Ware. Die Rücksendekosten trägt dabei der Käufer. Eine einfache Mitteilung an den Support reicht, um den Rücksendeprozess einzuleiten.
**AUSNAHME Personalisierung:** Für personalisierte Produkte mit Gravur ist das reguläre Widerrufsrecht streng gesetzlich ***ausgeschlossen***, da diese unverkäuflich sind.
**Defekte oder Falsche Lieferung:** Bei Transportschäden, beschädigten Artikeln (damaged item), fehlenden Teilen (partial delivery) oder den Erhalt eines komplett falschen Artikels übernehmen wir selbstverständlich alle Kosten! Wir fordern ein Foto an und organisieren Ersatz.
**Garantie & Reparatur:** Es greift die gesetzliche Gewährleistung (2 Jahre). Separate 'Garantie-Upgrades' oder Reparatur-Services für abgenutzte Produkte bieten wir derzeit nicht an, da es sich um Verbrauchsware (Holz/Glas) handelt. Spezifische Ersatzteile für Verschleiß liegen nicht vor, aber bei echten Mängeln tauschen wir um.
**Rückerstattung (Refund):** Tritt ein Kunde zurück, so wird der Erstattungsbetrag i.d.R. 2-4 Werktage nach Eintreffen der Retoure automatisch auf die beim Kauf gewählte Zahlungsmethode zurücktransferiert.
                "
            ],
            // Artikel 6: Sortiment
            [
                'title' => 'Produktdetails, Vorbestellungen & Verfügbarkeit',
                'category' => 'Produkte & B2B',
                'tags' => ['Produkte'],
                'content' => "
# Unser Sortiment verstehen
**Varianten & Größen:** Alles was wir an anderen Größen, Farben und Kompatibilitäten anbieten, ist direkt auf der Produktkatalog-Seite (Size Guide / Variantenwahl) zu finden.
**Verfügbarkeit & Out of Stock:** Bei Holz gibt es selten Engpässe. Falls doch mal ein Material ausgeht, können Kunden die 'Benachrichtigen wenn verfügbar' (Out of stock notification) Funktion nutzen oder im System nachschauen. Generelle Pre-Orders für noch nicht veröffentlichte Kollektionen kündigen wir via Newsletter an.
**Kompatibilität & Details:** Die technischen Abmessungen der Seelenanhänger etc. findet der User immer auf der jeweiligen Produktseite unter 'Details'.
**Erneute Bestellung (Reorder) & Merkliste:** Im eigenen Dashboard können gespeicherte Produkte (Save for later / Merkliste) betrachtet und alte Bestellungen mit einem Klick über die Historie erneut geordert werden.
**Echte Bewertungen (Reviews):** Zu jedem Produkt können registrierte Käufer Bewertungen hinterlassen. Echte Reviews anderer Kunden sind im Shop beim jeweiligen Artikel einsehbar.
                "
            ],
            // Artikel 7: Rabatte
            [
                'title' => 'Rabatte, B2B & Großbestellungen',
                'category' => 'Produkte & B2B',
                'tags' => ['B2B', 'Gamification'],
                'content' => "
# Angebote & Firmenkunden
**Rabattcodes & Gutscheine:** Rabattcodes können direkt im Warenkorb (Checkout) eingelöst werden. Normale Geschenkgutscheine (Gift Cards) bieten wir ebenfalls zum Kauf für Freunde an. 
**Saisonale Aktionen (Sales) & Studentenrabatt:** Große Rabattaktionen (Black Friday, Summer Sale) werden gesammelt beworben. Einen dedizierten Schüler- oder Studentenrabatt bieten wir nicht standardmäßig an, stattdessen können alle über Gamification ihre Rabatte erspielen. Es gilt keine Preisgarantie im Nachhinein (Price match).
**Das Gamification & Treueprogramm:** Kunden sammeln bei uns 'Seelenfunken' (Loyalty points) für Bestellungen und Aufgaben im 3D-Dashboard, die ab gewissen Leveln in Rabatt-Gutscheine umgetauscht werden können.
**B2B & Großbestellungen:** Wer als Unternehmen extrem hohe Stückzahlen ordert, ist herzlich willkommen! Der Kunde soll bitte das Support-Team kontaktieren oder eine B2B-Anfrage (Bulk Order Inquiry) stellen, das interne Verkaufsteam meldet sich dann bezüglich Mengenrabatten.
                "
            ],
            // Artikel 8
            [
                'title' => 'Online-Konfigurator & Laserverfahren',
                'category' => 'Bestell- & Produktionsprozess',
                'tags' => ['Personalisierung', 'Laser'],
                'content' => "
# Online-Konfigurator & Laserverfahren
**Vorschau vs. Realität:** Der auf der Website bereitgestellte Produktkonfigurator dient als visuelle Hilfe. Die Vorschau ist **nicht millimetergenau maßstabsgetreu**. Kleine produktionsbedingte Abweichungen sind normal und stellen keinen Mangel dar.
**Bestellung nachträglich ändern:** Änderungen an Gravuren oder Stornierungen sind als Support-Ticket nur möglich, solange die Produktion noch nicht begonnen hat ('Status: open'). Ist das Holz geläsert, geht nichts mehr zurückzunehmen.
                "
            ],
            // Artikel 9
            [
                'title' => 'Datenschutz, Server-Hosting & souveräne KI',
                'category' => 'Datenschutz & Infrastruktur',
                'tags' => ['Datenschutz', 'Recht', 'KI-Sicherheit'],
                'content' => "
# Strikter Datenschutz & Hosting
Ein absolutes Alleinstellungsmerkmal von Mein-Seelenfunke ist der souveräne Umgang mit Nutzerdaten. Sowohl die Kern-Website als auch alle Künstlichen Intelligenz (KI)-Sprachmodelle laufen autark auf dedizierten Servern in Deutschland bei der Mittwald CM Service GmbH. Es gibt keine Verbindungen zu OpenAI. Alle Support-Interaktionen sind hochsicher, vertraulich und 100% DSGVO-konform.
Mehrsprachigkeit (Multi-language) bieten wir derzeit technisch nicht nativ an, unsere Seite ist primär deutsch orientiert. Technische Probleme auf der Website ('Seite lädt nicht') können sofort dem Support als Ticket ('technical issue') gemeldet werden. Eine eigene Handy-App existiert aktuell nicht, die Seite ist jedoch extrem mobiloptimiert (PWA fähig).
                "
            ],
            // Artikel 10
            [
                'title' => 'Umgang mit Kundenbeschwerden & Eskalationen',
                'category' => 'Support & Kundenservice',
                'tags' => ['Support', 'Ticket', 'Reklamation'],
                'content' => "
# Support-Richtlinien für Funki (KI)
**Grundsätzliche Haltung:**
Funki ist loyal und 100% deeskalierend.
Egal ob ein Kunde wütend ist oder Sonderwünsche hat, Funki beruhigt die Lage professionell.
Wenn dem Kunden nicht systemweit im Chat geholfen werden kann, ruft Funki das Werkzeug `support_mark_needs_employee` auf. Dieses Werkzeug legt ein echtes Ticket an, das direkt auf dem Schreibtisch der Produktions- und Supportleitung (dem internen Team) landet. Funki gibt niemals vor, Aktionen (wie Pakete verpacken) auszuführen, sondern reicht die Tickets faktisch und sauber an das menschliche Team weiter.
Sobald Fragen geklärt sind, ruft er `support_resolve_chat` auf.
                "
            ],
            // Artikel 11
            [
                'title' => 'Sonderausgaben & Schnellerfassung (Buchhaltung)',
                'category' => 'Buchhaltung & Steuern',
                'tags' => ['Buchhaltung', 'Sonderausgaben'],
                'content' => "
# Sonderausgaben & Schnellerfassung
Die Buchhaltung unterscheidet streng zwischen:
1. **Gewerbliche Ausgaben (is_business = true):** Anschaffungen für Seelenfunke (z.B. Serverkosten, Arbeitsmaterial, Werbung). Nur hier greift der Steuersatz (meist 19%).
2. **Private Ausgaben (is_business = false):** Privatentnahmen oder private Einkäufe des Geschäftsführers (z.B. privater Einkauf, Essen gehen). Hier gibt es keine abziehbare Vorsteuer (tax_rate = null).
**Erfassung durch KI:**
Die KI kann Ausgaben mittels `finance_create_quick_entry_expense` direkt erfassen. Zuvor sollte mit `finance_list_categories` geprüft werden, in welche Kategorie die Ausgabe gehört.
                "
            ],
            // Artikel 12
            [
                'title' => 'Steuer-Export & Jahresabschluss (DATEV)',
                'category' => 'Buchhaltung & Steuern',
                'tags' => ['Buchhaltung', 'Steuern', 'Export'],
                'content' => "
# Steuer-Export
Seelenfunke bietet einen vollständigen Rechnungs- und Transaktions-Export für Steuerberater (DATEV-konform) an.
**Ausführung durch KI:**
Die KI kann den Export anstoßen, indem sie `finance_generate_tax_export` aufruft. Dies generiert eine ZIP-Datei mit allen Rechnungen (PDFs) und Buchungs-CSV-Daten des gewählten Monats. Den Link zur generierten Datei gibt die KI dem Benutzer, welcher diesen über das Dashboard herunterladen kann.
                "
            ],
            // Artikel 13
            [
                'title' => 'System-Architektur & API-Schnittstellen',
                'category' => 'Datenschutz & Infrastruktur',
                'tags' => ['Datenschutz', 'KI-Sicherheit'],
                'content' => "
# System-Architektur & API-Schnittstellen
Unser ERP-Ökosystem 'Mein-Seelenfunke' ist das Herzstück der Infrastruktur. Es ist mit folgenden zentralen APIs verbunden:
- **Mittwald:** Server & Hosting (Unsere sichere Cloud)
- **Google API:** Maps, Fonts, Auth (Social Login)
- **Stripe API:** Payments & Wallets
- **Etsy API:** Marktplatz Sync für Bestellungen
- **Finom & Fin API:** Geschäftskonto und Open Banking / Kontosync
- **ERiC (Elster):** Finanzamt UStVA API (Native XML Direktübertragung)
- **DATEV API:** Steuer Export (ZIP/CSV)
- **Firebase API:** Push Notifications für die App
- **DHL API:** Automatischer Label-Druck (geplant)

## KI-Architektur Workflow
        Der Sprach-Assistent verarbeitet Eingaben nach folgendem Flow:
        1. **KI Lauscht...:** Wake Word & Mic Input über das Computermikrofon
        2. **Spracheingabe via Interface:** Web Speech API / Alpine.js übersetzt Audio zu Text
        3. **FunkiraChat.php:** Livewire Component Base packt den Kontext & Historie
        4. **MittwaldAgent.php:** Prompt System Assembler tätigt den Remote API Call
        5. **LLaMA / GPT:** Mittwald AI Proxy antwortet mit Tool Call (z.B. create_task)
        6. **AIFunctionsRegistry.php:** Verifiziert und führt Werkzeuge aus
        7. **DB & Models:** Datenbank Read/Write Actions (ORM Action)
        8. **Final Response:** LLM sendet die fertige Antwort als Text
        9. **Audioausgabe (TTS):** Browser liest die Antwort vor
                "
            ],
            // --- START LASERSCHUTZSCHULUNG ---
            // Ausgelagert in AiKnowledgeBaseLaserSeeder.php
        ];

        foreach ($articles as $art) {
            $kb = AiKnowledgeBase::updateOrCreate([
                'title' => $art['title'],
            ], [
                'slug' => $art['slug'] ?? Str::slug($art['title']),
                'ai_knowledge_base_category_id' => $catMap[$art['category']],
                'content' => trim($art['content']),
                'is_published' => true,
            ]);

            $syncTags = [];
            foreach ($art['tags'] as $t) {
                if (isset($tagMap[$t])) {
                    $syncTags[] = $tagMap[$t];
                }
            }
            $kb->tags()->sync($syncTags);
        }

        // 4. Funkira, Medizin- & Workspace-Master-Dossiers
        $dossiers = [
            [
                'slug' => 'bkk-firmus-verfahren-chronologie',
                'title' => 'BKK firmus Verfahren: Ablehnung Krankengeld & Medizinischer Sachverhalt',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['BKK firmus', 'Krankengeld', 'GA-OP', 'Widerspruch', 'eAU', 'Sozialgericht', 'Eilantrag § 86b'],
                'content' => <<<MD
# Master-Dossier: BKK firmus Verfahren, GA-OP & Krankengeld

### 1. Eckdaten der Beteiligten
* **Versicherte / Betroffene:** Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn (geb. 01.09.1993, Versichertennummer: O603571189)
* **Krankenkasse:** BKK firmus (Körperschaft des öffentlichen Rechts), Vorstand: Dirk Harrer
  * Anschrift: Gottlieb-Daimler-Str. 11, 28237 Bremen (Tel: 0421 64343, Fax: 0421 6434-451, E-Mail: impressum@bkk-firmus.de)
* **Kernkonflikt:** Rechtswidriger Zahlungsstopp von Krankengeld nach schwerer stationärer geschlechtsangleichender Operation (GA-OP).
  * **Faktischer Zahlungsstopp seit 03.08.2026:** Die BKK firmus hat seit dem **03.08.2026** keinerlei Krankengeld mehr überwiesen! Der Ablehnungsbescheid datiert erst vom 01.10.2026 (nach zwei Monaten rechtswidriger Zahlungsverweigerung).
  * **Fehlbegründung der Kasse (Aussteuerung Januar 2026 / F64):** Die BKK firmus behauptet, wegen der Aussteuerung zum 29.01.2026 (78 Wochen mit seelischer Diagnose F64.0) bestehe kein Anspruch mehr, da die OP angeblich dieselbe Krankheit sei.
  * **Rechtliche Entkräftung (§ 48 SGB V & BSG):** 
    1. Die GA-OP ist ein neuer somatischer Heileingriff (postoperative Wundheilung, N99.8, T81) und nicht identisch mit dem seelischen Vorleiden vor der OP.
    2. Alina war nach dem 29.01. fast 5 Monate lang (bis 21.06.2026) nicht arbeitsunfähig, stand der BA zur Verfügung (ALG 1) und war mit Anspruch auf Krankengeld versichert.
    3. Treu und Glauben (§ 242 BGB): Die Kasse bewilligte die stationäre OP (§ 39 SGB V) und darf das Anschlusskrankengeld während der Heilung nicht treuwidrig verweigern.
* **Widerspruch:** Fristgerecht eingereicht am 01.10.2026 per Einwurf-Einschreiben (Sendungsnummer: RT613794858DE, Zustellung am 02.10.2026) mit Fristsetzung zur Zahlung bis 08.10.2026.
* **Aktuelle Reaktion der Kasse:** Am 07.10.2026 teilte die BKK firmus mit, die Unterlagen erst jetzt an den Medizinischen Dienst (MD) zur Begutachtung weitergeleitet zu haben (nach über zwei Monaten Zahlungsstopp!).

### 2. Medizinischer Verlauf & Lückenlose Krankschreibungen
* **Stationäre OP in Dr. Lubos Kliniken Bogenhausen München:** 22.06.2026 bis 12.07.2026.
* **Krankenhaus-Krankschreibung (poststationär):** Durchgehend von Dr. Lubos Kliniken bis einschließlich 19.07.2026 ausgestellt.
* **Lückenlose Folge-Krankschreibungen (eAU):**
  * Hausarztpraxis Leiferde (Dorothea Jung / Dr. Schmidt) hat ab 13.07. / 17.07.2026, 07.08.2026, 03.09.2026 fortlaufend nahtlos per elektronischer Arbeitsunfähigkeitsbescheinigung (eAU) krankgeschrieben.
  * Sämtliche eAUs wurden über die Telematikinfrastruktur digital an die BKK firmus übermittelt und in der BKK-App als verarbeitet quittiert.
  * Parallel gingen alle Veränderungsmitteilungen über das Onlineportal an die Bundesagentur für Arbeit (BA).

### 3. Die Zwickmühle der Ärzte & Behandler
* **Hausarztpraxis Leiferde (Frau Dorothea Jung):** Ist grundsätzlich bereit und willens, Alina weiterhin laufend per eAU krankzuschreiben. Sie weigert sich jedoch, gesonderte individuelle Kausalitätsbescheinigungen oder Gutachten zu unterzeichnen, aus massiver Furcht vor rechtlichen Konsequenzen und Regressansprüchen der Krankenkasse.
* **Dr. Lubos Kliniken München:** Antwortschreiben der Geschäftsführung vom 08.10.2026 (Büroleiter Sören Kopmann): Das Klinikum stellt nachträglich keine veränderten Diagnoseschlüssel oder Kausalitätsatteste aus, erteilt jedoch die ausdrückliche Freigabe, sämtliche vorliegenden OP-Berichte, Krankenakten und Befunde für das Sozialgericht, den MD und die Krankenkasse zu verwenden.
MD
            ],
            [
                'slug' => 'sozialgericht-eilantrag-status-und-mjp',
                'title' => 'Eilverfahren Sozialgericht Braunschweig (§ 86b SGG) & BundID / MJP',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Sozialgericht', 'Eilantrag § 86b', 'Mein Justizpostfach', 'BundID', 'Volksbank eG'],
                'content' => <<<MD
# Master-Dossier: Eilantrag Sozialgericht Braunschweig & Digitaler Klageweg

### 1. Gerichtsdaten & Zuständigkeit
* **Gericht:** Sozialgericht Braunschweig
* **Hausanschrift:** Wilhelmstraße 55, 38100 Braunschweig (Postfach 42 65, 38032 Braunschweig)
* **Telefon:** 0531 / 488-1500
* **Telefax für Rechtssachen:** 05141 / 5937-31600 (Zentralfax Niedersachsen)
* **Elektronischer Rechtsverkehr (EGVP-ID):** `govello-1272982110140-000216760`
* **Verfahren:** Einstweiliger Rechtsschutz gem. § 86b Abs. 2 Satz 2 SGG (Regelungsanordnung auf vorläufige Krankengeldzahlung ab 20.07.2026, hilfsweise ab 13.07.2026, höchsthilfsweise als Vorschuss nach § 43 SGB I).

### 2. Anordnungsgrund: Akute existenzielle Notlage & Fixkosten
* **Bank:** Volksbank eG Braunschweig Wolfsburg (IBAN DE85 2699 1066 8583 1960 00, BIC GENODEF1WOB)
* **Guthaben per 08.10.2026:** Nur noch **1.519,09 €**!
* **Monatliche unabweisbare Fixkosten:** Ca. **1.600 €**, bestehend aus:
  * **546,00 € Hauskredit** (Volksbank eG – für das selbstbewohnte Eigenheim zur Abwendung der Kündigung und Zwangsversteigerung; keine Mietwohnung!).
  * **355,00 € gesetzlicher Kindesunterhalt** für den minderjährigen Sohn Noah (Sohn lebt bei der Kindsmutter / Ex-Partnerin).
  * Elementare Lebenshaltung, Energie, Grundversorgung.
* **Gefährdung der Existenzgründung („Mein Seelenfunke“):**
  * Geplanter Start: 01.11.2026.
  * Eine Zwangsmeldung beim Arbeitsamt (anstelle von Krankengeld) würde den zwingend erforderlichen 150-Tage-Restanspruch auf ALG 1 (§ 93 SGB III) aufbrauchen und den Gründungszuschuss vernichten.

### 3. Aktueller Status der Einreichung (Stand 08.10.2026)
* Das gesamte Eilantragspaket liegt fertig formatiert auf dem Desktop (`Antrag_Sozialgericht_Krankengeld_BKK_firmus`):
  * Hauptantrag: `00_EILANTRAG_Sozialgericht_Braunschweig_Alina_Steinhauer.pdf`
  * Anlagen: K01 bis K08 im Ordner `Anlage/`
  * Krankschreibungen: 22 AU-Dokumente + eAU-Nachweis im Ordner `Krankschreibungen/`
  * Timeline-Dokumente: Alle 12 Aktenstücke im Ordner `Timeline_Dokumente_Historische_Akten/`
* **Wartezustand auf Online-Ausweis (BundID):**
  * Alina wartet aktuell auf den **PIN-Rücksetzbrief der Bundesdruckerei / Bürgeramt** für ihren Personalausweis (Online-Ausweis / eID).
  * Sie reicht den Eilantrag digital, papierlos und kostenfrei über **„Mein Justizpostfach“ (MJP)** unter `mein-justizpostfach.bund.de` direkt beim EGVP des Sozialgerichts ein, um hohe Druck- und Portokosten zu vermeiden.
  * Die Identifikation per BundID ersetzt nach § 65a Abs. 3 SGG die handschriftliche Unterschrift rechtswirksam.
MD
            ],
            [
                'slug' => 'existenzgruendung-seelenfunke-gruendungszuschuss',
                'title' => 'Existenzgründung Mein Seelenfunke & Gründungszuschuss (§ 93 SGB III)',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Gründungszuschuss', 'Businessplan', 'Liquiditätsplan', 'Tragfähigkeitsbescheinigung', 'Agentur für Arbeit'],
                'content' => <<<MD
# Master-Dossier: Existenzgründung Mein Seelenfunke & Gründungszuschuss

### 1. Rahmenbedingungen & Dringlichkeit
* **Unternehmen:** Mein Seelenfunke (Personalisierte Lasergravuren, Manufaktur & E-Commerce)
* **Gründungsdatum:** Geplant zum 01.11.2026.
* **150-Tage-Restanspruch:** Für den Gründungszuschuss nach § 93 Abs. 1 Satz 1 Nr. 1 SGB III muss am Tag der Gründung noch ein Restanspruch auf ALG 1 von **mindestens 150 Tagen** bestehen.
* **Ansprechpartnerin Arbeitsagentur:** Frau Grandke (Agentur für Arbeit Gifhorn).

### 2. Erforderliche Antragsdokumente
1. **Businessplan Mein Seelenfunke** (Stand 15.08.2026 / aktualisiert 2026)
2. **Liquiditäts- und Rentabilitätsplan** für die ersten 3 Geschäftsjahre
3. **Tragfähigkeitsbescheinigung der fachkundigen Stelle:** Übergabe des Businessplans an die Steuerberaterin zur Bestätigung der Tragfähigkeit.
4. **Vordruck Bundesagentur für Arbeit:** "Stellungnahme der fachkundigen Stelle zur Tragfähigkeit der Existenzgründung".
MD
            ],
            [
                'slug' => 'finanz-audit-leistungsansprueche-luecke',
                'title' => 'Finanz-Audit: Leistungen 22.06.-02.08.2026 & Krankengeld-Lücke ab 03.08.2026',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanz-Audit', 'Krankengeld', 'BKK firmus', 'Agentur für Arbeit', 'Volksbank eG', 'ALG 1'],
                'content' => <<<MD
# Master-Dossier: Finanz-Audit & Leistungsansprüche 2026

### 1. Tagessätze & Anspruchskalkulation
* **Arbeitslosengeld I (AfA):** Kalendertäglicher Leistungsbetrag: **60,21 €** (brutto = netto).
* **Krankengeld (BKK firmus):** Maßgebliches Regelentgelt gem. § 47b SGB V: **65,90 €** kalendertäglich.

### 2. Welches Geld bezog Alina vom 22.06.2026 bis 02.08.2026?
* **Leistungsträger:** **Bundesagentur für Arbeit (Arbeitslosengeld I)**
* **Rechtsgrundlage:** Gesetzliche **6-wöchige Leistungsfortzahlung bei Arbeitsunfähigkeit nach § 146 SGB III** (analog zur Lohnfortzahlung durch den Arbeitgeber).
* **Mathematischer & buchhalterischer Nachweis (Volksbank eG):**
  * **OP-Beginn:** 22.06.2026
  * **22.06. bis 30.06.2026 (9 Tage):** Im regulären Juni-ALG1 (1.806,30 €, Überweisung am 29.06.2026) enthalten.
  * **01.07. bis 31.07.2026 (31 Tage):** Voll ausgezahlt von der BA (1.806,30 €, Überweisung am 30.07.2026).
  * **01.08. bis 02.08.2026 (2 Tage):** Ausgezahlt von der BA am 25.08.2026: **120,42 €** (2 Tage à 60,21 €) mit Buchungstext `01.08.26-02.08.26`.
  * **Gesamtdauer:** 9 + 31 + 2 = **exakt 42 Tage (6 Wochen)**!

### 3. Der abrupte Zahlungsstopp ab 03.08.2026 durch die BKK firmus
* Mit Ablauf des 02.08.2026 endete die gesetzliche 6-Wochen-Fortzahlungspflicht der Bundesagentur für Arbeit nach § 146 SGB III auf den Tag genau.
* **Ab dem 03.08.2026** war kraft Gesetzes die **BKK firmus** zur Zahlung von **Krankengeld** nach § 44 Abs. 1 i.V.m. § 47b SGB V (65,90 € kalendertäglich) verpflichtet.
* **Verhalten der Kasse:** Die BKK firmus hat seit dem **03.08.2026 keinen einzigen Cent Krankengeld gezahlt**! Sie hat die Auszahlung blockiert, Alina 2 Monate lang ohne Bescheid gelassen und erst am 01.10.2026 die Ablehnung erteilt.
* **Akute Krankengeld-Lücke:** Vom 03.08.2026 bis 08.10.2026 (67 Tage) steht ein Betrag von mindestens **4.415,30 €** Krankengeld offen!
MD
            ],
            [
                'slug' => 'master-dokumenten-katalog-workspace',
                'title' => 'Master-Dokumentenkatalog: Wo liegen Dokumente & Wofür sind sie da',
                'category' => 'Workspace-Dokumentenkatalog',
                'tags' => ['Workspace', 'Dokumente', 'BKK firmus', 'Existenzgründung'],
                'content' => <<<MD
# Master-Dokumentenkatalog des internen Workspaces

Alle Dokumente sind revisionssicher unter `storage/app/agenten/workspace/` in einer strengen 3-Säulen-Struktur mit rein einteiligen Ordnernamen strukturiert:

1. `Berufsleben/`
   - `Existenzgruendung/` (Businessplan, Liquiditätsplan, Lebenslauf, Tragfähigkeit, Seelenfunke)
   - `Arbeitsamt/` (Bescheide, Anträge, Briefe, Onlineportal-Exporte)
   - `Projekte/` (Projektnotizen und Dokumentation)

2. `Dokumente/`
   - `Bank/` (Bankauszüge der Volksbank eG, Kontoumsätze)
   - `Steuern/` (Finanzamt, Steuererklärung, Gewerbesteuer)
   - `Finanzen/` (Rechnungen, Mahnungen, Verträge)
   - `Berichte/` (Systemanalysen und Berichte)
   - `Allgemein/` (Allgemeine Dokumente, Erinnerungen)

3. `Gesundheit/`
   - `Krankenkasse/` (BKK firmus Briefe, Digitaler Briefkasten, Chats)
   - `Krankengeld/` (Widersprüche, Berechnungen, Bescheide)
   - `Klinik/` (Dr. Lubos Kliniken München, Operationsberichte, Liegebescheinigungen)
   - `Atteste/` (Hausarzt-Atteste, Befunde, MDK-Gutachten)
   - `Nachweise/` (Einlieferungsbelege, Einschreiben, Fotodokumentation)
   - `Krankschreibungen/` (Chronologische Arbeitsunfähigkeitsbescheinigungen 2024–2026 und eAU-Dokumentation)

Alle Dokumente sind in der Datenbanktabelle `ai_workspace_documents` registriert.
KI-Agenten können diese per `workspace_find_documents` und `workspace_get_document_info` direkt abrufen.
MD
            ]
        ];

        $resolvedDossiers = [
            [
                'slug' => 'krankschreibungen-au-eau-chronologie-aussteuerung-2016-2026',
                'title' => 'Krankschreibungen (AU / eAU 2016–2026), ICD-10-Diagnosen & Aussteuerungsberechnung',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['BKK firmus', 'Krankengeld', 'eAU', 'Krankschreibungen', 'Aussteuerung', 'Dorothea Jung', 'Dr. Christian Schmidt', 'Sozialgericht'],
                'content' => '# Vollständiges Verzeichnis aller Krankschreibungen (AU / eAU 2016–2026) & Krankengeld-Aussteuerungsberechnung

**Versicherte:** Alina Steinhauer (geb. 01.09.1993, ehem. rechtl. Nick Steinhauer), Carl-Goerdeler-Ring 26, 38518 Gifhorn  
**Krankenkasse:** BKK firmus (Kostenträgerkennung / IK: `103121137`, Versicherten-Nr.: `O603571189`, Status: `1 00 00 00`)  
**Behandelnde Hausarztpraxis:** Gemeinschaftspraxis Wilhelm-Busch-Straße 2, 38542 Leiferde (Tel.: `05373-1814`, Fax: `05373-330635`, Betriebsstätten-Nr. / BSNR: `078354200`)  
- **Ärztin:** Dorothea Jung, Fachärztin für Allgemeinmedizin (Arzt-Nr. / LANR: `840902301`)  
- **Arzt:** Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin / Flugmedizin (Arzt-Nr. / LANR: `742315201`)  
**Archivort der Originalbelege (Desktop):** `C:\\Users\\konta\\Desktop\\Funkira\\Dateien\\Verarbeitet\\Gesundheit & Medizin\\Krankschreibungen`  

---

## 1. Lückenlose Aufstellung der AU-Bescheinigungen 2024–2026 (Belege 01 bis 15)

| Nr. | Zeitraum der AU (von – bis) | Festgestellt am | Art / Krankengeldfall | Ausstellender Arzt (LANR) | ICD-10-Diagnosecodes (gesichert) | Prüfnummer (PRF.NR.) & Originaldatei |
|:---|:---|:---|:---|:---|:---|:---|
| **01** | **29.04.2024 – 03.05.2024** | 30.04.2024 | Erstbescheinigung (arbeitsunfähig seit 29.04.2024) | Dr. med. Christian Schmidt (`742315201`) | `J06.9 G` (Akute Infektion der oberen Atemwege) | `Y/80/2108/36/266` (`45da3f4c-5832-4b54-9e64-61269bf584bf`) – `01_AU_2024-04-29_bis_2024-05-03.pdf` |
| **02** | **18.06.2024 – 08.07.2024** | 18.06.2024 | Erstbescheinigung (arbeitsunfähig seit 18.06.2024, Ausfertigung Arbeitgeber) | Dorothea Jung (`840902301`) | Übermittelt an Krankenkasse (Beginn der durchgehenden Haupt-AU) | `Y/80/2108/36/266` – `02_AU_2024-06-18_bis_2024-07-08.pdf` |
| **03** | **12.07.2024 – 02.08.2024** | 12.07.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` (Reaktion auf schwere Belastung) | `Y/80/2108/36/266` – `03_AU_2024-07-12_bis_2024-08-02.pdf` |
| **04** | **06.08.2024 – 02.09.2024** | 06.08.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` (`d9a73048-b871-45d3-9a0a-eb9b0451566d`) – `04_AU_2024-08-06_bis_2024-09-02.jpg` |
| **05** | **02.09.2024 – 30.09.2024** | 02.09.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` – `05_AU_2024-09-02_bis_2024-09-30.jpg` |
| **06** | **01.10.2024 – 31.10.2024** | 01.10.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` (`c90ee109-3254-43a2-b374-156bc6147785`) – `06_AU_2024-10-01_bis_2024-10-31.jpg` |
| **07** | **22.10.2024 – 15.11.2024** | 22.10.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G` (Neurasthenie / Erschöpfungssyndrom), `F64.0 G` (Transsexualismus) | `Y/80/2408/24/266` (`6f2d523e-3b9c-485c-b932-c24088ec8347`) – `07_AU_2024-10-22_bis_2024-11-15.jpg` |
| **08** | **28.01.2025 – 21.02.2025** | 28.01.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall; ab hier Vorname „Alina“) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`047b13f3-ee2a-4944-93c8-572eb4ccec49`) – `08_AU_2025-01-28_bis_2025-02-21.jpg` |
| **09** | **24.02.2025 – 21.03.2025** | 24.02.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`d545475f-1a5e-44a6-8cf3-32f6a1ca47e6`) – `09_AU_2025-02-24_bis_2025-03-21.pdf` |
| **10** | **21.03.2025 – 18.04.2025** | 21.03.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`67004ded-8abe-42cb-80ef-e449775beef4`) – `10_AU_2025-03-21_bis_2025-04-18.jpg` |
| **11** | **17.04.2025 – 28.04.2025** | 17.04.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dr. med. Christian Schmidt (`742315201`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`d68061f4-d2af-4ceb-8e6b-a8b65b598508`) – `11_AU_2025-04-17_bis_2025-04-28.jpg` |
| **12** | **28.04.2025 – 23.05.2025** | 28.04.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall; Hinweis: Dateiname lautete `bis_2025-04-28`, auf der Urkunde ist jedoch **bis einschließlich 23.05.2025** bescheinigt!) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`b268e8bc-627c-4cf8-b4e9-b537d8c41109`) – `12_AU_2025-04-28_bis_2025-04-28.jpg` |
| **13** | **22.05.2025 – 20.06.2025** | 22.05.2025 | Folgebescheinigung / ab 7. AU-Woche (Ausfertigung Krankenkasse) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`a1650880-f5e7-4f9e-8c57-f14968d44281`) – `13_AU_2025-05-22_bis_2025-20-06.jpg` |
| **14** | **29.12.2025 – 26.01.2026** | 29.12.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`691a400c-ddd2-478f-bc74-6b1bea3cba89`) – `14_AU_2025-12-29_bis_2026-01-26_Praxis_Jung.jpg` |
| **15** | **23.01.2026 – 29.01.2026** | 23.01.2026 | Folgebescheinigung bis zum **Aussteuerungstag** / ab 7. AU-Woche | Dr. med. Christian Schmidt (`742315201`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`70970c3c-5c10-4643-9584-bfc4157f471b`) – `15_AU_2026-01-23_bis_2026-01-29_Praxis_Dr_Schmidt_Aussteuerung.jpg` |

---

## 2. OP- und Post-OP-Zeitraum 2026 (Elektronische Krankschreibungen / eAU & BA-Meldungen)

Gemäß § 295 Abs. 1 Satz 1 SGB V wurden alle Arbeitsunfähigkeitsbescheinigungen im Jahr 2026 nach der geschlechtsangleichenden Operation (GA-OP vom 23.06.2026) direkt digital über die Telematikinfrastruktur als **eAU** an die BKK firmus übermittelt:

### A. Chronologie der Post-OP-Zeiträume 2026
1. **22.06.2026 – 12.07.2026** (Festgestellt 22.06.2026): Stationäre Aufnahme & GA-OP in den **Dr. Lubos Kliniken Bogenhausen** (München) – belegt durch Kostenübernahme & Liegebescheinigung der Klinik.
2. **12.07.2026 – 19.07.2026** (Festgestellt 12.07.2026): Post-stationäre Krankschreibung Dr. Lubos Kliniken – elektronisch gemeldet an BA am 13.07.2026.
3. **17.07.2026 – 07.08.2026** (Feststellungstag 17.07.2026): Nahtlose eAU Hausarztpraxis Leiferde (Dorothea Jung) – Eingang & Verarbeitung bei BKK firmus bestätigt am 13.07. / 21.07.2026.
4. **07.08.2026 – 03.09.2026** (Feststellungstag 07.08.2026): Nahtlose Folge-eAU Hausarztpraxis Leiferde – Eingang & Verarbeitung bei BKK firmus bestätigt am 03.08. / 10.08.2026.
5. **ab 04.09.2026 fortlaufend** (Feststellungstag 03.09.2026): Nahtlose Folge-eAU Hausarztpraxis Leiferde – Eingang & Verarbeitung bei BKK firmus bestätigt am 04.09.2026.

### B. Verbindliche Systembestätigungen aus der BKK-Service-App (Beleg 16)
- **13.07.2026, 10:15 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 17.07.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **21.07.2026, 07:31 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 17.07.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **23.07.2026, 07:32 Uhr (BKK firmus Nachricht):** *„Guten Tag, vielen Dank für die Einreichung Ihrer Unterlagen, wir werden diese schnellstmöglich prüfen und Ihnen eine Rückmeldung geben. Freundliche Grüße, Ihre BKK firmus“*
- **03.08.2026, 13:20 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 07.08.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **10.08.2026, 07:40 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 03.09.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **04.09.2026, 07:23 Uhr:** Verarbeitung der nahtlosen Folgebescheinigung für September / Oktober 2026.

### C. Nachweise im Onlineportal der Bundesagentur für Arbeit (BA Gifhorn)
- **13.07.2026, 10:38 Uhr:** Veränderungsmitteilung versendet: Krankmeldung für den Zeitraum **22.06.2026 bis voraussichtlich einschließlich 19.07.2026**.
- **10.08.2026, 09:10 Uhr:** Veränderungsmitteilung versendet: Weiterhin krank für den Zeitraum **10.08.2026 bis voraussichtlich einschließlich 04.09.2026**.

---

## 3. Berechnung der Krankengeld-Bezugsdauer & Aussteuerung (Beleg 17)
- **Gesetzliche Höchstbezugsdauer (§ 48 Abs. 1 SGB V):** **546 Kalendertage (78 Wochen)** innerhalb einer Blockfrist von 3 Jahren wegen derselben Krankheit.
- **Rechnerische Kalkulation (Stand 13.07.2025):**
  - Referenzbeginn: 23.08.2024 (+ 546 Tage = theoretisches Ende 21.02.2026; bis 13.07.2025 waren 324 Tage verbraucht, Rest 222 Tage).
  - Tatsächliche Aussteuerung durch die BKK firmus unter Einrechnung der Vorerkrankungszeiten ab 18.06.2024: **23.01.2026** (bzw. Folge-AU Nr. 15 bis **29.01.2026**, Übergang in ALG 1 am 30.01.2026, woraus die 6-tägige Nahtlosigkeitslücke vom 24.01.–29.01.2026 über 361,26 € resultierte).

---

## 4. Historische Arbeitsunfähigkeitsbescheinigungen (2016–2022)
Ebenfalls im Desktop-Archiv (`Krankschreibungen/Historische_AUs_2016-2022/`) hinterlegt:
- `AU_2016-11-22_bis_2020-01-30_Sammelakte.pdf` (Sammeldokumentation der Jahre 2016–2020)
- `AU_2020-07-14_bis_2020-07-17_HEK.pdf` (14.07.2020 – 17.07.2020, Hanseatische Krankenkasse HEK)
- `AU_2020-08-18_bis_2020-08-21.pdf` (18.08.2020 – 21.08.2020)
- `AU_2021-07-23_bis_2021-07-28.pdf` (23.07.2021 – 28.07.2021)
- `AU_2021-12-02_bis_2021-12-03.pdf` (02.12.2021 – 03.12.2021)
- `AU_2022-12-19_bis_2022-12-21.jpg` (19.12.2022 – 21.12.2022)',
            ],
            [
                'slug' => 'endokrinologikum-hannover-laborbefunde-humangenetik-hormonstatus-2024',
                'title' => 'Endokrinologikum Hannover: Arztbrief, Humangenetik (46,XY / FISH) & Laborbefunde (2024)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Endokrinologikum', 'Laborbefunde', 'Humangenetik', 'Hormontherapie', 'Dr. Christian Schmidt', 'BKK firmus'],
                'content' => '# Endokrinologikum Hannover: Arztbrief, Humangenetik (46,XY / FISH) & Vollständige Laborbefunde (April–Juni 2024)

**Patientin:** Frau Alina (rechtl. damals Nick) Steinhauer, geb. 01.09.1993, wohnhaft Carl-Goerdeler-Ring 26, 38518 Gifhorn  
**Fachärztliches Zentrum:** MVZ Endokrinologikum Hannover (Zentrum für Hormon- und Stoffwechselerkrankungen, Rundestraße 10, 30161 Hannover, Tel. `0511 21 55 58 - 2000`, Fax `0511 21 55 58 - 2119`)  
**Behandelnde Ärztin:** **Dr. med. Susanne Krusche**, Fachärztin für Innere Medizin, Endokrinologie und Diabetologie (LANR: `356126803`, Diktatzeichen: `SK/wei`)  
**Empfänger des Arztbriefs vom 05.06.2024:** Herrn Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin / Flugmedizin, Wilhelm-Busch-Straße 2, 38542 Leiferde (nachrichtlich an Frau Alina Steinhauer)  
**Labor:** amedes MVZ wagnerstibbe für Laboratoriumsmedizin, Hämostaseologie, Humangenetik und Mikrobiologie Hannover, Georgstraße 50, 30159 Hannover  
**Archivort des Originaldokuments (9 Seiten PDF, Desktop):** `C:\\Users\\konta\\Desktop\\Funkira\\Dateien\\Verarbeitet\\Gesundheit & Medizin\\Atteste\\Endokrinologikum_Laborbefunde.pdf`  

---

## 1. Ärztlicher Befundbericht & Epikrise (Vorstellung am 09.04.2024, Brief vom 05.06.2024)

### Diagnosen
1. **Transidentität Mann-zu-Frau (MtF)**
2. **Unauffälliger männlicher Chromosomensatz (`46,XY`)**
3. **Vorliegendes Indikationsschreiben**

### Anamnese & Körperlicher Untersuchungsbefund (09.04.2024)
- **Anamnese:** Vorstellung mit dem Wunsch, das Geschlecht in Richtung Frau zu ändern. Die Patientin berichtet, sich schon während des ganzen Lebens eher als Frau gefühlt zu haben. Sie habe diese Gefühle zunächst unterdrückt, ein Haus gebaut, eine Frau geheiratet und einen Sohn (zum Untersuchungszeitpunkt 1,5 Jahre alt).
- **Vormedikation:** Keine.
- **Körperliche Untersuchung (30-jährige Patientin):**
  - **Blutdruck:** `129/71 mmHg`
  - **Puls:** `67 S/min`
  - **Körpergröße:** `186 cm`
  - **Körpergewicht:** `81,7 kg`

### Epikrise (Dr. med. Susanne Krusche)
> *„Unsere Untersuchung zeigen einen unauffälligen männlichen Chromosomensatz, normale männliche Geschlechtshormone, keine auffällige Gerinnungsdiagnostik. Ein Indikationsschreiben liegt vor. Somit sind die Voraussetzungen für den Beginn einer gegengeschlechtlichen Hormontherapie gegeben, die Patientin stellt sich am 08.05.2024 zu einer Besprechung und schriftlichen Aufklärung über Nebenwirkungen und Risiken der Hormontherapie vor.“*

---

## 2. Klinische Chemie, Elektrolyte, Schilddrüse & Vitamine (Entnahme 09.04.2024, 11:42 Uhr)
*Barcodenummer: `7019021329` | Tagesnummer: `HQ YD09 8623` | Endbefund: 11.04.2024 (validiert von Dr. Arndt Gröning)*

| Parameter | Messwert | Einheit | Referenzbereich | Bewertung |
|:---|:---|:---|:---|:---|
| **Glucose** | `99` | mg/dl | `< 100` | Normal |
| **Hämoglobin A1c (HbA1c)** | `4,7` | % | `< 6,2` | Normal |
| **HbA1c (IFCC)** | `28` | mmol/mol | `< 44` | Normal |
| **Harnsäure** | `6,4` | mg/dl | `3,4 – 7,0` | Normal |
| **GPT (ALAT)** | `17` | U/l | `< 50` | Normal |
| **GOT (ASAT)** | `22` | U/l | `< 50` | Normal |
| **gamma-GT** | `8` | U/l | `< 60` | Normal |
| **Alk. Phosphatase** | `58` | U/l | `40 – 129` | Normal |
| **Creatinin (Jaffé)** | `1,1` | mg/dl | `< 1,2` | Normal |
| **GFR (CKD-EPI)** | `90` | ml/min/1,73qm | `> 60` | Normal |
| **Harnstoff** | `22` | mg/dl | `19 – 44` | Normal |
| **Natrium** | `141` | mmol/l | `136 – 145` | Normal |
| **Kalium (Serum)** | `4,0` | mmol/l | `3,6 – 5,4` (Plasma `3,4–5,1`) | Normal |
| **Calcium** | `2,4` | mmol/l | `2,15 – 2,50` | Normal |
| **Phosphat, anorg.** | `0,97` | mmol/l | `0,80 – 1,60` | Normal |
| **C-reaktives Protein (CRP)** | `0,2` | mg/dl | `< 0,5` | Normal (keine Entzündung) |
| **25-OH-Vitamin D** | `40` ↓ | nmol/l | Zielwert Prävention `> 75` | **Vitamin-D-Mangel** (`< 50 nmol/l`) |
| **FT3 (Freies Trijodthyronin)** | `3,4` | ng/l | `1,9 – 5,1` | Normal (euthyreot) |
| **FT4 (Freies Thyroxin)** | `1,3` | ng/dl | `0,9 – 1,7` | Normal (euthyreot) |
| **TSH, basal** | `1,90` | mIU/l | `0,4 – 4,0` | Normal (euthyreot) |

---

## 3. Endokrinologische Ausgangsdiagnostik vor HRT (Entnahme 09.04.2024, 11:42 Uhr)

| Hormon / Parameter | Messwert | Einheit | Referenzbereich | Bewertung |
|:---|:---|:---|:---|:---|
| **FSH, basal** | `2,9` | IU/l | `1,5 – 12,4` | Normal |
| **LH, basal** | `3,0` | IU/l | `1,7 – 8,6` | Normal |
| **17-beta-Östradiol (E2)** | `39` | ng/l | `11,3 – 43,2` | Normal (Ausgangswert) |
| **Prolaktin, 1. Basalwert** | `186` / `8,7` | mIU/l / ng/ml | `86 – 324` / `4,0 – 15,2` | Normal |
| **Testosteron, gesamt** | `6,04` | µg/l | `2,49 – 8,36` | Normal (Ausgangswert vor HRT) |
| **Freier Androgenindex (FAI)** | `54` | – | `36 – 155` | Normal |
| **SHBG** | `39` | nmol/l | `18 – 54` | Normal |
| **Androstendion (ECLIA)** | `0,94` | ng/ml | `0,34 – 2,13` | Normal |
| **17-OH-Progesteron** | `1,2` | µg/l | `0,6 – 2,2` | Normal |
| **ACTH** | `19,2` | ng/l | `7,2 – 63,3` | Normal |
| **Cortisol** | `147` | µg/l | vormittags `48 – 195` | Normal |

---

## 4. Hämatologie / Kleines Blutbild im Verlauf (09.04.2024 vs. 08.05.2024)
*Kontrollbefund vom 08.05.2024 (Entnahme 14:10 Uhr, Barcode `7019329412`, Tagesnr. `HQ YE08 9676`, validiert von Dr. Andre Siegel):*

| Parameter | 09.04.2024 (EDTA / Citrat) | 08.05.2024 (Kontrolle zum HRT-Start) | Einheit | Referenzbereich |
|:---|:---|:---|:---|:---|
| **Leukozyten** | `5,6` / `5,8` | **`7,6`** | G/l | `4,0 – 9,0` |
| **Erythrozyten** | `5,87` / `5,89` | **`5,36`** | T/l | `4,3 – 5,9` |
| **Hämoglobin** | `17,0` | **`15,5`** | g/dl | `14,0 – 18,0` |
| **Hämatokrit** | `48,2` / `50` | **`44,9`** | % | `40 – 50` |
| **MCV** | `82,1` / `84,9` | **`83,8`** | fl | `80 – 96` |
| **MCH** | `29,0` / `28,9` | **`28,9`** | pg | `28 – 32` |
| **MCHC** | `35,3` / `34,0` | **`34,5`** | g/dl | `30 – 36` |
| **Thrombozyten** | `144` ↓ / `139` ↓ | **`203` (normalisiert)** | G/l | `150 – 400` |

---

## 5. Fettstoffwechsel, Gerinnungsdiagnostik & Thrombophilie-Ausschluss (09.04.2024)
*Barcodenummer: `7019021355` | Tagesnummer: `HQ YD09 8609` | Endbefund: 12.04.2024 (validiert von Dr. Frauke Bergmann)*

- **Fettstoffwechsel / Atheroskleroserisiko:**
  - **Lp(a) (Turbidimetrie, Roche):** `< 19 nmol/l` (Ref: `< 75 nmol/l`) – unauffällig
  - **Homocystein:** `12,1 µmol/l` (Ref: `< 15,0 µmol/l`) – unauffällig
- **Gerinnungsparameter:**
  - **Quick-Test:** `70 %` ↓ (Ref: `80 – 126 %`), **INR:** `1,2`
  - **Part. Thromboplastinzeit (PTT):** `34 sec.` (Ref: `23 – 34 sec.`)
  - **D-Dimere:** `170 µg/l` (Ref: `< 500 µg/l`)
  - **Einzelfaktoren:** Faktor VII `60 %` (`50–130 %`), Faktor VIII `55 %` (`50–150 %`), Faktor IX `90 %` (`65–150 %`), Antithrombin III `101 %` (`80–120 %`), Protein C `86 %` (`70–140 %`), Protein S frei `97 %` (`64–139 %`), aPC-Resistenz `2,73 Ratio` (normal `> 2,18`).
- **Molekulargenetische & Immunologische Thrombophilie-Diagnostik:**
  - **Faktor-V-Leiden-Genotyp (`p.Arg506Gln`):** **nicht nachweisbar**
  - **Prothrombin-Genotyp (`20210G>A`):** **nicht nachweisbar**
  - **Lupusantikoagulanz (Lupus-Inhibitor):** dRVVT-Test `negativ`, LA-Screen `0,69` (`< 1,2`), PTT LA `negativ`, LA-Quotient `0,93` (`< 1,10 = negativ`).
  - **Autoantikörper (Antiphospholipid):** Cardiolipin IgG (FIA) `negativ`, Cardiolipin IgM (FIA) `negativ`, beta-2-Glykoprotein 1 IgG (FIA) `negativ`, beta-2-Glykoprotein 1 IgM (FIA) `negativ`.
- **Hämostaseologische Beurteilung (Dr. Frauke Bergmann):**
  - Eine Faktor-VII-Verminderung sowie eine Lebersynthesestörung konnten ausgeschlossen werden (Antithrombin normal). **Kein Hinweis auf eine angeborene oder erworbene Thrombophilie** (wichtig für die Sicherheit der Östrogen-Hormontherapie!).

---

## 6. Humangenetische & Molekularzytogenetische Diagnostik (09.04.2024 – 29.04.2024)
*Barcodenummer: `7019021362` | Tagesnummer: `HQ YD09 8607` | Endbefund: 29.04.2024 (validiert von Prof. Dr. med. Gudrun Göhring / MVZ Institut für Labormedizin und klinische Genetik Rhein/Ruhr)*

- **Indikation:** Transgender (Mann-zu-Frau)
- **1. Chromosomendiagnostik aus Lymphozyten (GTG-Färbung, 12 Metaphasen ausgewertet, ca. 400 Banden):**
  - **Karyotyp:** **`46,XY` (nach ISCN 2020)**
  - **Ergebnis:** Unauffälliger männlicher Chromosomensatz; keine Hinweise auf eine numerische oder strukturelle Chromosomenveränderung.
- **2. Molekularzytogenetische Diagnostik / FISH (Fluoreszenz-in-situ-Hybridisierung, Sonden `LSI SRY Spectrum Orange / CEP X Spectrum Green`, Fa. Abbott Molecular):**
  - **Karyotyp:** **`nuc ish(DXZ1,SRY)x1` (ISCN 2020)**
  - **Ergebnis:** In **197 ausgewerteten Interphasezellkernen** und **8 Metaphasen** fand sich jeweils ein Signal für die Zentromerregion des X-Chromosoms (`DXZ1`) und ein Signal für die `SRY`-Region des Y-Chromosoms (`Yp11.3`).
  - **Beurteilung:** Spricht gegen das Vorliegen einer Deletion der `SRY`-Region; zudem **kein Hinweis auf ein gonosomales Mosaik**.',
            ],
            [
                'slug' => 'existenzgruendung-businessplan-und-antragsdokumentation-2026',
                'title' => 'Existenzgründung Mein Seelenfunke: Businessplan, Gründungszuschuss & Antragsdokumentation',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Existenzgründung', 'Businessplan', 'Gründungszuschuss', 'Tragfähigkeitsbescheinigung', 'Agentur für Arbeit', 'Workspace', 'Dokumente'],
                'content' => '# Existenzgründung Mein Seelenfunke: Businessplan, Gründungszuschuss & Antragsdokumentation

Vollständige Wissensbasis aller Gründungsunterlagen, Businessplan-Inhalte, Tragfähigkeitsbescheinigungen und Gründerprofil-Dokumente aus `Berufsleben/Existenzgruendung` und `Berufsleben/Projekte`.

## 1. Projekt Notizen Ki Assistent
- **Dateipfad:** `agenten/workspace/Berufsleben/Projekte/Projekt_Notizen_KI_Assistent.md`
- **Kategorie:** Arbeit & Projekte | **Datum:** 2026-09-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.
- **Zusammenfassung:** Arbeit & Projekte: # Funkira Projekt Notizen Datum: 15.09.2026 Idee: Erstellung eines Second Brains für Alina. Tasks: Sprachmodul, Vision, Datei-Ingestion fertigstellen.

### Volltext / Inhalt
# Funkira Projekt Notizen
Datum: 15.09.2026
Idee: Erstellung eines Second Brains für Alina.
Tasks: Sprachmodul, Vision, Datei-Ingestion fertigstellen.


## 2. Offizielles Profilbild / Bewerbungsfoto: Alina Steinhauer
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Alina_Steinhauer_Gruenderin_Profilbild.png`
- **Kategorie:** Allgemein | **Datum:** 2026-06-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.
- **Zusammenfassung:** Hochauflösendes professionelles Porträtfoto von Gründerin Alina Steinhauer.
- **Auszug:** `Hochauflösendes professionelles Porträtfoto von Gründerin Alina Steinhauer.`

## 3. BA-Vordruck: Stellungnahme der fachkundigen Stelle zur Tragfähigkeit der Existenzgründung
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/BA_Formular_Stellungnahme_fachkundige_Stelle_Gruendungszuschuss.pdf`
- **Kategorie:** Existenzgründung & Arbeitsagentur | **Datum:** 2026-06-15
- **Zweck:** Vordruck der Bundesagentur für Arbeit: Fachkundige Stellungnahme zur Tragfähigkeit der Existenzgründung nach § 93 SGB III.
- **Zusammenfassung:** Offizieller Vordruck der Bundesagentur für Arbeit (BA 053733) zur Begutachtung und Bestätigung der Tragfähigkeit der Existenzgründung durch eine fachkundige Stelle (IHK, HWK, Steuerberater).
- **Auszug:** `1 Anschrift des Kunden 2 Anschrift der fachkundigen Stelle (Absender)
Stellungnahme der fachkundigen Stelle zur 
Tragfähigkeit der Existenzgründung
nach § 93 Absatz 2 Nr. 2 Drittes Buch Sozialgesetzbuch (SGB III)
Hinweis: Zutreffendes bitte ausfüllen oder ankreuzen. 
Pflichtfelder sind mit Stern * m`

## 4. Businessplan: Mein Seelenfunke (E-Commerce Manufaktur & Laserveredelung)
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Businessplan_Mein_Seelenfunke_15-08-2026.md`
- **Kategorie:** Existenzgründung & Arbeitsagentur | **Datum:** 2026-06-15
- **Zweck:** Offizieller Businessplan zur Vorlage bei der Steuerberaterin für die Tragfähigkeitsbescheinigung und beim Arbeitsamt für den Gründungszuschuss (§ 93 SGB III).
- **Zusammenfassung:** Umfassender 13-teiliger Businessplan für das Gründungsvorhaben \'Mein Seelenfunke\' von Alina Steinhauer. E-Commerce-Manufaktur für hochemotionale personalisierte Geschenke (Galvo- und UV-Laserveredelung von Glas, Holz, Metall, Schiefer, Schmuck). Vertrieb über Etsy und mein-seelenfunke.de. Standort: Carl-Goerdeler-Ring 26, Gifhorn. Ziel-Nettoeinkommen 1.600–2.000 EUR/Monat.

### Volltext / Inhalt
# Businessplan: Mein Seelenfunke
**Gründerin:** Alina Steinhauer  
**Stand des Businessplans:** Aktualisiert am 15.06.2026 (Anpassung des Zeitplans aufgrund von Krankenhausaufenthalt – Start am 15.08.2026)

---

## Inhalt
1. [Zusammenfassung](#1-zusammenfassung)
2. [Gründerprofil & Kompetenzen](#2-gründerprofil--kompetenzen)
3. [Geschäftsidee & Produkte](#3-geschäftsidee--produkte)
4. [Markt- & Wettbewerbsanalyse](#4-markt--wettbewerbsanalyse)
5. [Marketing & Vertriebsstrategie](#5-marketing--vertriebsstrategie)
6. [Organisation & Standort (Dachboden)](#6-organisation--standort-dachboden)
7. [Risikoanalyse](#7-risikoanalyse)
8. [Investitionsübersicht](#8-investitionsübersicht)
9. [Jahresplanung & Zeitlicher Ablauf (2026/2027)](#9-jahresplanung--zeitlicher-ablauf-20262027)
10. [Umsatzplanung (2028-2030)](#10-umsatzplanung-2028-2030)
11. [Detaillierte Finanzübersicht (Ziel-Szenario 2028)](#11-detaillierte-finanzübersicht-ziel-szenario-2028)
12. [Private Budgetplanung (2028)](#12-private-budgetplanung-2028)
13. [Produktkalkulationen (Starter-Kollektion)](#13-produktkalkulationen-starter-kollektion)

---

## 1. Zusammenfassung

Mein-Seelenfunke ist eine E-Commerce-Manufaktur für hoch-emotionale, personalisierte Geschenke. Unter dem Motto **"Ein Funke, der bleibt"** werden mittels modernster UV- und Galvo-Lasertechnologien Rohmaterialien wie Glas, Acryl, Metall und Schiefer personalisiert und veredelt. Auch die eigene Produktion von Schmuck (Halsketten), Glasbildern und Holzgeschenken wird angestrebt.

Im Gegensatz zu massenproduzierter Ware bietet Mein-Seelenfunke individuelle Unikate, die speziell für Anlässe wie Jahrestage, Hochzeiten oder Trauerbewältigung gefertigt werden. Der Vertrieb startet über den Marktplatz Etsy und wird später über den eigenen Onlineshop unter `mein-seelenfunke.de` ausgebaut. Ziel ist der direkte Übergang in die volle Selbstständigkeit bis **Ende 2027** mit einem angestrebten verfügbaren Nettoeinkommen zwischen 1.600 € und 2.000 €/Monat.

---

## 2. Gründerprofil & Kompetenzen

Die Gründerin Alina Steinhauer verfügt aufgrund ihrer 12-jährigen Erfahrung im Bereich Support, Softwareentwicklung, Vor-Ort-Service und einer Nebentätigkeit als Drohnenpilotin über ein ausgeprägtes technisches Verständnis und beste Voraussetzungen für die Umsetzung der neu geplanten Gewerbestruktur. Die Anlauf- und Vorbereitungsphase bis Ende 2027 wird intensiv zur Weiterbildung in den Bereichen E-Commerce, Digital Marketing und Lasertechnik genutzt (z. B. Zertifizierung zur Laserschutzbeauftragten nach OStrV & TROS).

---

## 3. Geschäftsidee & Produkte

Der Name "Seelenfunke" suggeriert tiefgehende Bedeutung. Wir verkaufen keine einfachen "Produkte", sondern "konservierte Emotionen". Das Ziel ist der Aufbau einer profitablen E-Commerce-Manufaktur für hoch-emotionale, personalisierte Geschenke.

### Das Kernprodukt: Der „Seelen-Kristall“ Glas-Trophäe
*   **Rolle:** Premium-Flaggschiff. Hoher Verkaufspreis, hoher absoluter Gewinn pro Stück (ca. 30 €).
*   **Risiko:** Höchste Bruchgefahr. Erfordert Investitionen in hochwertige Polsterung und Verpackung.

### Weitere Produktideen:
1.  **Der "Seelen-Song"** (Acryl-Aufsteller mit Holzsockel und LED)
2.  **Der "Liebesfunke"** (personalisierte Metallkarte) – Die "Cash Cow"
3.  **Der "Erinnerungsfunke"** (personalisierter Schmuck/Halsketten)
4.  **Der "Seelen Moment"** (hochwertige Naturschieferplatte mit Fotogravur)

---

## 4. Markt- & Wettbewerbsanalyse

*   **Zielgruppe:** Überwiegend weiblich (20–50 Jahre), emotional kaufend. Gesucht wird "das besondere Geschenk", das sich von klassischer Massenware abhebt.
*   **Wettbewerb:** Mein-Seelenfunke differenziert sich durch edlere Materialien (Metall, Acryl, Glas) und extrem schnelle Lieferzeiten von der Konkurrenz. Dies wird durch den Einsatz des High-End-Lasergravierers *xTool F2 Ultra UV* ermöglicht.
*   **Preisstrategie:** Premium-Personalisierung. Keine Preiskämpfe. Der Wert und die Marge entstehen durch die Personalisierung und ein erstklassiges Auspackerlebnis ("Unboxing Experience").

---

## 5. Marketing & Vertriebsstrategie

*   **Hauptvertriebskanal:** Etsy als Start-Booster für schnellen Traffic und erste Kundenbewertungen, gefolgt vom Aufbau eines eigenen Onlineshops (Shopify) unter `www.mein-seelenfunke.de`.
*   **Social-Media-Strategie (TikTok/Instagram):**
    *   *KI-Influencer:* Ein sympathischer, digitaler Markenbotschafter ("Das Gesicht von Mein Seelenfunke") präsentiert die Produkte emotional, ohne dass die Gründerin selbst vor die Kamera treten muss.
    *   *Viraler Content:* Videos aus der Produktion, dem Entstehungsprozess der personalisierten Geschenke und emotionale Reaction-Videos von Kunden.
*   **Kern-Elemente:** Deutsche Domain, Qualitätsversprechen "Made in Germany", schneller versicherter Versand.

---

## 6. Organisation & Standort (Dachboden)

Die Produktion erfolgt kosteneffizient im eigenen, komplett ausgebauten Dachboden, was die Fixkosten zu Beginn minimiert.
*   **Zustand:** Komplett ausgebaut, inklusive Dämmung, Beleuchtung und Heizung.
*   **Beleuchtung:** Ausreichend LED-Spots sind verbaut.
*   **Klimatisierung:** Eine Klimaanlage (Innen- und Außengerät) wird bei Bedarf installiert, ist jedoch vorerst nicht zwingend notwendig.
*   **Abluft:** Die Absaugung der Laserdämpfe erfolgt über die zwei Dachfenster mittels eines Rohradapters. Langfristig ist eine direkte Kernbohrung/Wandabluft geplant.
*   **Lager:** Schwerlastregale für Verpackungen und Rohlinge; dedizierte Arbeitstische für Verpackung und Laserstation.

---

## 7. Risikoanalyse

*   **Stärken:** Eigene Immobilie (Dachboden = mietfreie Nutzung, geringe Fixkosten), High-End-Equipment (schneller als Konkurrenz), einprägsamer deutscher Markenname.
*   **Schwächen:** Einzelunternehmerin (Herausforderung Zeitmanagement), Abhängigkeit von Lieferanten für Rohlinge.
*   **Chancen:** Hohe Skalierbarkeit durch Erweiterung des Sortiments auf neue Materialien (Holz, Leder etc.).
*   **Risiken & Gegenmaßnahmen:** 
    *   *Ausfall des Lasers:* Schnelle Kooperation mit lokalen Produktionspartnern oder Anschaffung eines Zweitgeräts.
    *   *Gesundheitsrisiken:* Minimierung durch professionelle Fensterabluft.
    *   *Ausfallzeiten:* Absicherung über spezielle Elektronik- und Betriebsunterbrechungsversicherungen.

---

## 8. Investitionsübersicht

Zur Gründung sind folgende Einmalkosten veranschlagt:

| Kategorie | Posten | Geschätzte Kosten (€) | Anmerkung |
| :--- | :--- | :---: | :--- |
| **Produktionstechnik** | xTool F2 Ultra UV | 4.550,00 | Kernstück der Produktion |
| **Sicherheit & Abluft** | xTool SafetyPro™ IF2/IF2 2.0 Rohrventilator | 200,00 | Absaugung über Dach/Fenster |
| | xTool Laser-Schutzbrille (Zertifiziert) | 65,00 | Pflicht für Wartung/Betrieb |
| **Schulung & Recht** | Laserschutzbeauftragter (LSB) Online-Kurs | 600,00 | Rechtliche Absicherung (OStrV) |
| **Infrastruktur** | Anpassung Dachboden (Elektro/Kleinteile) | 150,00 | Minimale Anpassung vorhanden |
| **Arbeitsplatz** | Profi-Arbeitsplatz (Tische, Beleuchtung, Stuhl) | 450,00 | Fokus auf Ergonomie (Dachboden) |
| **Zusatz-Hardware** | Professioneller Labeldrucker (z.B. Brother/Dymo) | 250,00 | Effizienz im Versand |
| **Material-Puffer** | Muster-Rohlinge & Erstbestand (Glas, Schiefer) | 1.000,00 | Sofortiger Start & Mustererstellung |
| **Reserve** | Unvorhergesehenes / Puffer | 550,00 | Sicherheit für den Start |
| **GESAMT** | **Summe der Einmalkosten** | **7.815,00 €** | |

---

## 9. Jahresplanung & Zeitlicher Ablauf (2026/2027)

Aufgrund eines Krankenhausaufenthalts wurde der Zeitplan angepasst. Der offizielle Start des Gewerbes ist nun der **15.08.2026**.

```mermaid
gantt
    title Zeitplan "Mein Seelenfunke" (2026/2027)
    dateFormat  YYYY-MM-DD
    axisFormat %d.%m.%Y
    section Vorbereitung
    Krankenhausaufenthalt           :active, 2026-06-23, 2026-07-14
    Genesung & Vorbereitung         :2026-07-15, 2026-08-01
    Gründungszuschuss sichern       :2026-08-02, 2026-08-11
    section Aufbauphase
    Gewerbeanmeldung (15.08.2026)   :milestone, 2026-08-15, 0d
    Prüfung & Investitionen         :2026-08-15, 2026-12-06
    Backoffice-Einrichtung          :2026-12-07, 2027-01-06
    section Launch-Phase
    Produktionshochlauf             :2027-01-07, 2027-02-15
    Markteintritt & Anlauf          :2027-02-16, 2027-06-16
```

### Phase 1: Vorbereitungen & Krankheits-Unterbrechung (23.06.2026 – 14.08.2026)
*   **Bis 22.06.2026:** Bezug von normalem ALG 1.
*   **23.06.2026 – 14.07.2026:** Krankenhausaufenthalt (ca. 3 Wochen).
*   **15.07.2026 – 31.07.2026:** Genesungsphase & Vorbereitung der Gründung (Bezug von normalem ALG 1).
*   **02.08.2026 – 11.08.2026:** Sicherung des Gründungszuschusses bei der Agentur für Arbeit (Einreichung der Tragfähigkeitsstellungnahme). Vorbereitung der Gewerbeanmeldung zum **15.08.2026**.

### Phase 2: Gewerbestart & Technische Vorbereitung (15.08.2026 – 06.12.2026)
*   **15.08.2026:** Offizielle Anmeldung des Gewerbes „Mein-Seelenfunke“ (Start der Selbstständigkeit im Haupterwerb).
*   **1. Start des Bezugs:** Beginn von ALG 1 + Gründungszuschuss (Phase 1: 6 Monate Sicherung des Lebensunterhalts).
*   **2. Geschäftskonto:** Eröffnung eines geschäftlichen Bankkontos.
*   **3. Ausbildung:** Absolvierung der Schulung zur zertifizierten Laserschutzbeauftragten (LSB) nach OStrV.
*   **4. Rechtliches:** Anmeldung der Verpackungslizenz über das Verpackungsregister (LUCID).
*   **5. Investitionen:** Kauf der Produktionstechnik (xTool Laser, SafetyPro Abluft, Schutzbrille) und Einrichtung des Arbeitsplatzes. Marken- und Logoschutz über das DPMA beantragen.
*   **6. IT & Buchhaltung:** Einrichtung des Etsy-Shops sowie Anbindung von *Billbee* (Rechnungserstellung) und *Sevdesk* (Buchhaltung) zur automatisierten Erfassung aller Ein- und Ausgaben.

### Phase 3: Backoffice & Ablaufprozesse (07.12.2026 – 06.01.2027)
*   **1. Lagerorganisation:** Definition der Lagerlogistik (Mindestbestände Rohlinge, Nachbestellprozesse, Warenkontrolle).
*   **2. Ablaufdefinition:** Exakte zeitliche und finanzielle Kalkulation der Produktionsschritte pro Produkt (Rohmaterial, Gravurdauer, Verpackungszeit, Gebühren, Versand).
*   **3. Verpackung & Versand:** Testen von Kartonagen und Schutzfolien zur Minimierung von Transportschäden.
*   **4. Marketing-Vorbereitung:** Erstellung von erstem Bildmaterial, Social-Media-Kanäle aufbauen (TikTok, Instagram) und Produktion erster KI-gestützter Produktvideos.
*   **5. Testläufe:** Probelauf der gesamten Kette (Bestellung -> Billbee -> Sevdesk -> Produktion -> Versand).

### Phase 4: Produktionshochlauf & Logistik-Optimierung (07.01.2027 – 15.02.2027)
*   Fokus liegt auf der Skalierung des Warenbestands und der Ablaufoptimierung.
*   **1. Vorproduktion:** Fertigung der "Starter-Kollektion" (alle 5 Hauptprodukte) in höheren Stückzahlen zur Lagerbildung.
*   **2. Lieferantenverträge:** Finalisierung von Bulk-Lieferverträgen für Rohlinge zur Absicherung von Rabatten und Liefergarantien.
*   **3. Packstraße:** Optimierung der Arbeitsprozesse beim Verpacken für maximalen Durchsatz.

### Phase 5: Markteintritt & Saisonales Wachstum (16.02.2027 – 16.06.2027)
*   **März 2027 (Markteintritt & Anlaufphase):**
    *   *Fokus:* Technische Abläufe im Echtbetrieb testen, erste organische Bewertungen generieren, "Early-Bird"-Angebote schalten.
    *   *Prognose:* ca. 50–60 Verkäufe.
    *   *Umsatz-Ziel:* ca. 1.800 € – 2.100 € (deckt die laufenden Kosten).
*   **April 2027 (Wachstumsphase & Ostern):**
    *   *Fokus:* Schaltung erster bezahlter Werbeanzeigen (Etsy Ads, Meta Ads). Nutzung des Ostergeschäfts für den Verkauf von Frühlings-Deko und personalisierten Geschenken.
    *   *Prognose:* ca. 80–100 Verkäufe.
    *   *Umsatz-Ziel:* ca. 2.800 € – 3.500 €.
*   **Mai 2027 (Hauptsaison & Muttertag/Vatertag):**
    *   *Fokus:* Maximierung der Conversion-Rate für Geschenkartikel. Bewerbung des "Seelen-Kristalls" und der "Liebesfunken"-Metallkarten als ideales Muttertags- und Vatertagsgeschenk. Express-Optionen für Last-Minute-Käufer anbieten.
    *   *Prognose:* ca. 110–130 Verkäufe.
    *   *Umsatz-Ziel:* ca. 3.900 € – 4.500 € (gleicht die Anlaufverluste aus dem März aus).

---

## 10. Umsatzplanung (2028-2030)

Da das Gewerbe im August 2026 startet und die Anlaufphase bis Mitte 2027 andauert, ist das Jahr **2028** das erste vollumfänglich geplante Verkaufsjahr.

### Strategieübersicht der Folgejahre:
*   **Jahr 2028 (Etablierung & Kundenstamm):** Fokus auf die wichtigsten Geschenke-Feiertage (Valentinstag, Ostern, Muttertag, Weihnachten). Aufbau von Stammkunden und Generierung von 5-Sterne-Bewertungen. Ziel: 10–15 Bestellungen pro Tag.
*   **Jahr 2029 (Wachstum & Optimierung):** Nutzung von Top-Rankings (Google/Etsy SEO). Skalierung durch Effizienzsteigerung und erste B2B-Projekte (Firmenkunden). Anschaffung eines zweiten Lasers oder Einstellung einer Aushilfe auf 520-€-Basis für den Versand. Geplantes Wachstum: 15–20 %.
*   **Jahr 2030 (Festigung & Reifephase):** Steigerung des durchschnittlichen Warenkorbwerts durch Cross-Selling. Starker Fokus auf das B2B-Geschäft (Mitarbeitergeschenke, Firmenpräsente mit Logo) zum Ausgleich saisonaler Schwankungen. Strukturierung: Zwei Mitarbeiter für Produktion und Versand, die Gründerin konzentriert sich auf die strategische Leitung.

### Monatliche Umsatzprognose (Brutto)

| Monat | Jahr 2028 (Etablierung) | Jahr 2029 (Wachstum) | Jahr 2030 (Festigung) | Fokus & Begründung |
| :--- | :---: | :---: | :---: | :--- |
| **Januar** | 2.200 € | 2.800 € | 3.200 € | Saison-Tief ("Kaufkater" nach Weihnachten), Fokus auf Inventur |
| **Februar** | 3.800 € | 4.500 € | 4.900 € | **Peak 1 (Valentinstag):** Hochsaison für "Seelen-Song" & "Liebesfunke" |
| **März** | 2.800 € | 3.200 € | 3.600 € | Normalisierung. Ostervorbereitung (kleinere Geschenke) |
| **April** | 3.200 € | 3.800 € | 4.100 € | Anlauf Muttertag, Start von Werbekampagnen |
| **Mai** | 4.200 € | 5.000 € | 5.400 € | **Peak 2 (Muttertag/Vatertag):** Stärkster Monat im 1. Halbjahr |
| **Juni** | 2.500 € | 3.000 € | 3.500 € | Beginn Sommerzeit, Fokus auf Hochzeitsgeschenke |
| **Juli** | 2.400 € | 2.800 € | 3.100 € | Sommerloch (Ferienzeit), Zeit für Design-Entwicklung |
| **August** | 2.500 € | 3.000 € | 3.200 € | Sommerloch, Vorbereitung auf Q4 & B2B-Weihnachtsakquise |
| **September** | 3.000 € | 3.500 € | 3.900 € | Herbst-Beginn, "Cosy Season" startet (Deko-Artikel) |
| **Oktober** | 3.500 € | 4.200 € | 4.800 € | Start Weihnachtsgeschäft (Early Bird), B2B-Abschlüsse |
| **November** | 5.200 € | 6.000 € | 6.800 € | **Peak 3 (Black Week):** Höchste Bestellfrequenz des Jahres |
| **Dezember** | 5.800 € | 6.500 € | 7.100 € | **Peak 4 (Weihnachten):** Endspurt bis zum 20.12. |
| **GESAMT** | **ca. 41.100 €** | **ca. 48.300 €** | **ca. 53.600 €** | |
| **Monatsschnitt** | **ca. 3.425 €** | **ca. 4.025 €** | **ca. 4.460 €** | |
| **Reingewinn (Netto)**| **ca. 1.800 €** | **> 2.000 €** | **> 2.300 €** | *(Nach Steuern und Betriebskosten)* |

---

## 11. Detaillierte Finanzübersicht: Ziel-Szenario 2028

Die folgende Kalkulation stellt ein durchschnittliches Ziel-Szenario für ein stabiles Geschäftsjahr (Basis: 2028) dar.

| Position | Monatlich (Ø) | Jährlich (Gesamt 2028) |
| :--- | :---: | :---: |
| **I. Gewerbliche Einnahmen** | | |
| Umsatzerlöse (Brutto-Verkauf) | 4.250,00 € | 51.000,00 € |
| Material & Verpackung (15%) | - 637,50 € | - 7.650,00 € |
| Versandkosten (15%) | - 637,50 € | - 7.650,00 € |
| Gebühren (Etsy Shop 18%) | - 765,00 € | - 9.180,00 € |
| **= Rohertrag (Deckungsbeitrag)** | **2.210,00 €** | **26.520,00 €** |
| | | |
| **II. Betriebliche Fixkosten** | | |
| Marketing (Organisch + Ads) | - 50,00 € | - 600,00 € |
| Strom & Heizung | - 20,00 € | - 240,00 € |
| Instandhaltung | - 30,00 € | - 360,00 € |
| Software (Billbee, Sevdesk etc.) | - 45,00 € | - 540,00 € |
| Versicherungen (Gewerbe) | - 45,00 € | - 540,00 € |
| Steuerberater | - 120,00 € | - 1.440,00 € |
| **= Gewerblicher Gewinn (EÜR)** | **1.900,00 €** | **22.800,00 €** |

---

## 12. Private Budgetplanung (2028)

Berechnung des verfügbaren Reinkommens für die Gründerin im Jahr 2028.

| Position | Monatlich (Ø) | Jährlich (2028) |
| :--- | :---: | :---: |
| **Gewerblicher Gewinn (EÜR)** | **1.900,00 €** | **22.800,00 €** |
| + Mieteinnahmen (Hausanteil) | + 550,00 € | + 6.600,00 € |
| **= Gesamt-Einkommen (Brutto)** | **2.450,00 €** | **29.400,00 €** |
| - Krankenkasse & Pflege (GKV) | - 470,00 € | - 5.640,00 € |
| **= NETTO (Auszahlungsbetrag)** | **1.980,00 €** | **23.760,00 €** |
| - Steuer-Rücklage | - 380,00 € | - 4.560,00 € |
| **= VERFÜGBARES NETTO** | **1.600,00 €** | **19.200,00 €** |

*   **Absatzrechnung:** Bei einem gewünschten Monatsumsatz von 4.250,00 € und einem durchschnittlichen Verkaufspreis von 39,90 € entspricht dies **ca. 106,5 verkauften Produkten pro Monat**.
*   Bei 30 Tagen im Monat entspricht dies einem täglichen Absatz von **3 bis 4 Verkäufen**.

---

## 13. Produktkalkulationen (Starter-Kollektion)

### A. Produkt-Kalkulation: „Seelen-Kristall“ (Premium-Glas)
*   **Verkaufspreis (Brutto):** 39,90 €
*   **Umsatzsteuer (19%):** 6,37 €
*   **Verkaufspreis (Netto):** 33,53 €

| Kalkulationsschritt | Kosten pro Stück | Fakten / Erläuterung |
| :--- | :---: | :--- |
| 1. Einstandspreis (Rohling) | 3,69 € | K9 Glasblock (Importpreis) |
| 2. Veredelung (Produktion) | 1,50 € | Strom, Laserabnutzung, Hilfsmittel |
| 3. Verpackung | 0,71 € | Standard Buchverpackung (kein Premium) |
| 4. Logistik (Versand) | 5,90 € | Versicherter Versand (DHL) |
| **Einstandskosten (COGS)** | **11,80 €** | Direkte Herstellkosten inkl. Versand |
| 5. Marktplatz-Gebühren (18%) | 7,18 € | Provision auf 39,90 € Verkaufspreis |
| 6. Marketing-Anteil | 3,00 € | Werbebudget (Ads) pro Verkauf |
| **Selbstkosten Gesamt** | **21,98 €** | Gesamtkosten bis zum Verkauf |
| **7. DEIN GEWINN (Netto)** | **11,55 €** | Reingewinn pro Trophäe |

---

### B. Produkt-Kalkulation: „Seelen-Moment“ (Schieferplatte 20x20 cm)
*   **Verkaufspreis (Brutto, Shop):** 41,53 €
*   **Umsatzsteuer (19%):** 6,63 €
*   **Verkaufspreis (Netto):** 34,90 €

| Kalkulationsschritt | Kosten pro Stück | Erläuterung |
| :--- | :---: | :--- |
| 1. Einstandspreis (Rohling) | 2,20 € | Einzelne Naturschieferplatte (ca. 200x200 mm) |
| 2. Veredelung (Produktion) | 1,50 € | Vollflächige Gravur (220x220 mm Limit), Strom via PV |
| 3. Spezial-Verpackung | 2,80 € | Stabiler flacher Karton + Luftpolster/Eckenschutz |
| 4. Logistik (Versand) | 6,90 € | Paketversand (wegen Gewicht & Bruchschutz) |
| **Einstandskosten (COGS)** | **13,40 €** | Direkte Kosten inkl. sicherem Versand |
| 5. Marktplatz-Gebühren (ca. 18%) | 6,28 € | Gebühren auf den Brutto-Verkaufspreis |
| 6. Marketing-Anteil (pro Stück) | 1,50 € | Geringerer Anteil, da oft über Suchbegriffe gefunden |
| **Selbstkosten Gesamt** | **21,18 €** | Gesamte Selbstkosten |
| **7. Wunsch-Gewinn (Netto)** | **13,72 €** | Dein Reingewinn pro verkaufte Platte |

---

### C. Produkt-Kalkulation: „Seelen-Song“ (Acryl-Aufsteller mit Holzsockel)
*   Format: ca. 150 x 210 mm (passend für den 220 x 220 mm Arbeitsbereich des Lasers)
*   **Verkaufspreis (Brutto, Shop):** 41,53 €
*   **Umsatzsteuer (19%):** 6,63 €
*   **Verkaufspreis (Netto):** 34,90 €

| Kalkulationsschritt | Kosten pro Stück | Erläuterung |
| :--- | :---: | :--- |
| 1. Einstandspreis (Material) | 4,50 € | Hochwertiges GS-Acryl (5mm) + LED-Holzsockel (USB) |
| 2. Veredelung (Produktion) | 1,20 € | Gravur & Zuschnitt; Schutzfolie bleibt währenddessen drauf |
| 3. Kratzschutz-Verpackung | 1,80 € | Mikrofaser-Beutel oder weiches Seidenpapier + Luftpolsterfolie |
| 4. Logistik (Versand) | 4,50 € | Günstigerer Versand (z. B. DHL 1kg oder Maxibrief Prio) |
| **Einstandskosten (COGS)** | **12,00 €** | Deine Basis-Kosten für ein versandfertiges Produkt |
| 5. Marktplatz-Gebühren (ca. 18%) | 6,28 € | Gebühren auf den Brutto-Verkaufspreis |
| 6. Marketing-Anteil (Social Media) | 3,50 € | Höheres Werbebudget da TikTok/Insta Trend-Produkt |
| **Selbstkosten Gesamt** | **21,78 €** | Kosten inklusive Werbung und Gebühren |
| **7. Wunsch-Gewinn (Netto)** | **13,12 €** | Dein Reingewinn pro "Song" |


## 5. Businessplan PDF: Mein Seelenfunke (Druckfassung)
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Businessplan_Mein_Seelenfunke_15-08-2026.pdf`
- **Kategorie:** Existenzgründung & Arbeitsagentur | **Datum:** 2026-06-15
- **Zweck:** Offizieller Businessplan zur Vorlage bei der Steuerberaterin für die Tragfähigkeitsbescheinigung und beim Arbeitsamt für den Gründungszuschuss (§ 93 SGB III).
- **Zusammenfassung:** Offizielle PDF-Druckausfertigung des Businessplans \'Mein Seelenfunke\' zur Einreichung bei der fachkundigen Stelle und der Agentur für Arbeit.
- **Auszug:** `BUSINESSPLAN
E-Commerce-Manufaktur für personalisierte Geschenke
UNTERNEHMENSDATEN:
Gründerin: Alina Steinhauer
Rechtsform: Einzelunternehmerin
Standort: Carl-Goerdeler-Ring 26, 38518 Gifhorn
Gewerbestart: 15.08.2026 (Verschoben aufgrund von Krankenhausaufenthalt)
Webseite: www.mein-seelenfunke.de
K`

## 6. Dokumentation: Online-Antrag Gründungszuschuss § 93 SGB III (Status & Stammdaten)
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Dokumentation_Gruendungszuschuss_Onlineantrag.md`
- **Kategorie:** Arbeit & Projekte | **Datum:** 2026-06-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.
- **Zusammenfassung:** Detaillierte Dokumentation des Antragsverfahrens für den Gründungszuschuss nach § 93 SGB III bei der Agentur für Arbeit Gifhorn (Ansprechpartner Herr Klingner, Beratung am 10.02.2026). Dokumentiert Schritte 1 bis 6 und die Checklist für die fachkundige Stelle.

### Volltext / Inhalt
# Dokumentation: Online-Antrag auf Gründungszuschuss (Bundesagentur für Arbeit)

Diese Dokumentation fasst alle Informationen aus dem laufenden Online-Antragsverfahren der Bundesagentur für Arbeit (Stand: 15.06.2026) und dem dazugehörigen Dokument für die fachkundige Stelle zusammen.

---

## 1. Status und Stammdaten des Antrags

*   **Antragstyp:** Gründungszuschuss nach § 93 Abs. 2 Nr. 2 SGB III
*   **Aktueller Fortschritt:** Schritt 6 von 11 ("Arbeitslosengeldbezug") ist ausgefüllt.
*   **Persönlicher Ansprechpartner:** Herr Klingner (Agentur für Arbeit)
*   **Frist zur Einreichung:** Der Antrag muss innerhalb von **90 Tagen** nach Beginn abgeschlossen werden. 
    *   *Kritische Frist:* Bei Nichtabschluss wird der Vorgang am **13.09.2026** dauerhaft gelöscht.
*   **Zugeordnetes Dokument im Ordner:** `gruendungszuschuss-gz-stellungnahme-fachkundige-stelle_ba053733.pdf`
    *   *Bedeutung:* Offizielle PDF-Vorlage der Bundesagentur für Arbeit zur Stellungnahme der fachkundigen Stelle über die Tragfähigkeit der Existenzgründung.

---

## 2. Auswertung der ausgefüllten Antragsschritte (Screenshots)

Hier sind die genauen Eingaben und Auswahlen aufgeführt, wie sie in den Screenshots zu sehen sind:

### Schritt 1: Allgemeine Hinweise
*   **Beratung zum Gründungszuschuss:**
    *   *Frage:* Wurden Sie bereits durch die Agentur für Arbeit zu den Fördermöglichkeiten mit einem Gründungszuschuss beraten oder haben Sie bereits einen Beratungstermin vereinbart?
    *   *Auswahl:* **Ja**
    *   *Datum des Beratungstermins:* **10.02.2026**
*   **Erforderliche Pflichtdokumente (im Laufe der Formularstrecke hochzuladen):**
    1.  **Stellungnahme einer fachkundigen Stelle** über die Tragfähigkeit der Existenzgründung (Vorlage befindet sich in diesem Ordner).
    2.  **Eingangsbestätigung der Anzeige der Gewerbeanmeldung** bei der zuständigen Behörde bzw. die Anzeige einer freiberuflichen Tätigkeit bei der Finanzbehörde.
    3.  **Beschreibung des Existenzgründungsvorhabens** (Businessplan).
    4.  **Lebenslauf** und Qualifikationsnachweise.
    5.  *Nur bei Betriebsübernahme:* Nachweise zur bisherigen Geschäftstätigkeit (z. B. BWA, GuV, Steuerbescheide).
*   **Nachreichbare Dokumente (können später übermittelt werden):**
    *   Mitteilung des Finanzamtes über die Zuteilung der Steuernummer
    *   Gewerbeanmeldung (falls noch nicht vorliegend)
    *   Nachweis der Handwerkskammer über die Eintragung in die Handwerksrolle
    *   Gesellschaftsvertrag / Bescheid der Clearingstelle
    *   Nachweise über eine oder mehrere selbstständige Tätigkeiten

### Schritt 2: Frühere Existenzgründungen
*   **Bisherige selbstständige Tätigkeiten:**
    *   *Frage:* Haben Sie bereits eine oder mehrere selbstständige Tätigkeiten ausgeübt?
    *   *Auswahl:* **Nein**
    *   *Hinweis der Agentur:* Wer in den letzten 24 Monaten bereits einen Gründungszuschuss erhalten hat, ist in der Regel von einer erneuten Förderung ausgeschlossen (Ausnahme: personenbedingte Gründe wie Krankheit oder Schwangerschaft).

### Schritt 3: Aufnahme der Tätigkeit
*(Kein Screenshot für diesen Schritt vorhanden. Im Seitenmenü ab Schritt 4 mit einem grünen Haken als erledigt markiert).*

### Schritt 4: Selbständige Tätigkeit
*   **Nebenerwerb:**
    *   *Frage:* Haben Sie die selbstständige Tätigkeit bereits im Nebenerwerb mit weniger als 15 Stunden wöchentlich ausgeübt?
    *   *Auswahl:* **Nein**
*   **Hauptberufliche Ausübung:**
    *   *Frage:* Wird die selbstständige Tätigkeit hauptberuflich ausgeübt?
    *   *Auswahl:* **Ja**
*   **Weitere Tätigkeiten:**
    *   *Frage:* Üben Sie zusätzlich zu Ihrer selbstständigen Tätigkeit noch eine oder mehrere Tätigkeiten aus?
    *   *Auswahl:* **Nein**

### Schritt 5: Abgrenzung der Tätigkeit
*Diese Angaben dienen der Abgrenzung zur Scheinselbstständigkeit bzw. zur Feststellung der echten Selbstständigkeit.*
*   **GmbH-Geschäftsführer/Gesellschafter:**
    *   *Frage:* Üben Sie eine Tätigkeit als Geschäftsführerin oder Geschäftsführer, mitarbeitende oder geschäftsführende Gesellschafterin oder mitarbeitender oder geschäftsführender Gesellschafter einer GmbH aus?
    *   *Auswahl:* **Nein**
*   **Unternehmerisches Risiko:**
    *   *Frage:* Tragen Sie eigenes unternehmerisches Risiko (zum Beispiel eigenes Betriebskapital, eigene Beschäftigte, eigene Geschäftsräume)?
    *   *Auswahl:* **Ja**
*   **Weisungsgebundenheit:**
    *   *Frage:* Sind Sie von einer Auftraggeberin oder einem Auftraggeber abhängig und insbesondere durch örtliche, zeitliche, inhaltliche oder fachliche Weisungen gebunden?
    *   *Auswahl:* **Nein**
*   **Eingliederung in Betriebsabläufe:**
    *   *Frage:* Nutzen Sie Arbeitsmittel einer Auftraggeberin/eines Auftraggebers oder arbeiten Sie eng mit Beschäftigten einer Auftraggeberin/eines Auftraggebers zusammen? Sind Sie dadurch in den organisierten Betriebs- und Arbeitsablauf einer Auftraggeberin oder eines Auftraggebers eingegliedert?
    *   *Auswahl:* **Nein**
*   **Marktauftritt:**
    *   *Frage:* Verfügen Sie über einen eigenen Marktauftritt?
    *   *Auswahl:* **Ja**

### Schritt 6: Arbeitslosengeldbezug
*   **Nebeneinkommen:**
    *   *Frage:* Haben Sie während Ihrer Arbeitslosigkeit Nebeneinkommen erzielt, das auf Ihren Arbeitslosengeldanspruch angerechnet wurde?
    *   *Auswahl:* **Nein**

---

## 3. Analyse des PDF-Dokuments (Stellungnahme fachkundige Stelle)

Das in den Ordner verschobene Dokument `gruendungszuschuss-gz-stellungnahme-fachkundige-stelle_ba053733.pdf` ist das offizielle Formular (Stand 01/2025), das eine fachkundige Stelle ausfüllen muss, um die Tragfähigkeit der Gründung zu bestätigen. Folgende Kriterien und Bereiche werden darin geprüft:

*   **A. Allgemeines:** 
    *   Prüfung, ob alle Unterlagen vorliegen: Businessplan (Beschreibung der Geschäftsidee), Lebenslauf/Zeugnisse, Kapitalbedarfs- und Finanzierungsplan, Umsatz- und Rentabilitätsvorschau.
*   **B. Angaben zur Person:**
    *   Bewertung der fachlichen/branchenspezifischen Eignung (Ja/Nein).
    *   Bewertung der kaufmännischen/unternehmerischen Eignung (Ja/Nein).
    *   Vorliegen eventueller Zulassungsvoraussetzungen (Konzessionen, Kammereinträge) (Ja/Nein).
*   **C. Angaben zum Vorhaben:**
    *   Konkurrenzfähigkeit des Vorhabens (Ja/Nein).
    *   Realistische Einschätzung der Umsätze, Betriebsergebnisse und des Kapitalbedarfs (Ja/Nein).
    *   Eignung des erwarteten Einkommens als ausreichende Lebensgrundlage (Ja/Nein).
*   **D. Zusammenfassende Beurteilung:**
    *   Freitextfeld für die detaillierte fachliche Begründung der Tragfähigkeit.
*   **E. Abschließende Einschätzung:**
    *   Gesamturteil: Ist der Aufbau einer tragfähigen Existenzgründung insgesamt realisierbar? (Ja/Nein).

---

## 4. Offene und kritische Punkte (To-Do-Liste)

> [!WARNING]
> **Fehler in Schritt 7 ("Ihre Geschäftsidee"):**
> Laut der Statusleiste in den Screenshots 4 und 5 ist Schritt 7 mit einem **roten Kreuz** markiert. Das bedeutet, dass dort entweder Pflichtfelder nicht ausgefüllt wurden oder Fehler bei der Eingabe aufgetreten sind. Dieser Schritt muss zwingend korrigiert werden, bevor der Antrag fortgeführt werden kann.

### Nächste Schritte zur erfolgreichen Einreichung:
1.  **Portal öffnen & Schritt 7 korrigieren:** Gehen Sie im Online-Portal der Bundesagentur für Arbeit zu "Schritt 7: Ihre Geschäftsidee" und beheben Sie die unvollständigen Angaben.
2.  **Businessplan & Finanzplanung finalisieren:** Erstellen Sie die für die fachkundige Stelle und die Arbeitsagentur notwendigen Unterlagen (Businessplan, Lebenslauf, Kapitalbedarfsplan, Umsatz- und Rentabilitätsvorschau).
3.  **Fachkundige Stelle beauftragen:** Kontaktieren Sie eine fachkundige Stelle (z. B. Steuerberater, IHK, HWK oder Gründerzentrum) und legen Sie ihr Ihren Businessplan sowie das PDF-Formular vor, damit diese die Tragfähigkeit prüft und das Formular ausfüllt und unterschreibt.
4.  **Einreichungsfrist beachten:** Stellen Sie sicher, dass alle Schritte bis zum **13.09.2026** ausgefüllt, alle Pflichtdokumente hochgeladen und der Antrag abgeschickt wurde.


## 7. Ausführlicher Lebenslauf & Gründerprofil: Alina Steinhauer
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Lebenslauf_Alina_Steinhauer_Gruenderprofil.pdf`
- **Kategorie:** Arbeit & Projekte | **Datum:** 2026-06-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.
- **Zusammenfassung:** Vollständiger beruflicher Werdegang und Qualifikationsprofil von Alina Steinhauer (12 Jahre Erfahrung im IT-Support, Softwareentwicklung, Kundendienst und Drohnentechnik) als Nachweis der unternehmerischen Eignung.
- **Auszug:** `PERSÖNLICHE DATEN
Geburtsdatum:
01.09.1993
Anschrift:
Carl-Goerdeler-Ring 26
38518 Gifhorn
Telefon:
+49 1590 1966 864
E-Mail:
kontakt@alina-steinhauer.de
Webseite:
mein-seelenfunke.de
SPRACHEN
Deutsch
Muttersprache
Englisch
Grundkenntnisse
ALINA STEINHAUER
Webentwicklerin & IT-Spezialistin
Erfahrene`

## 8. Zusammenfassung: Gründungszuschuss Antragsstand & Checkliste
- **Dateipfad:** `agenten/workspace/Berufsleben/Existenzgruendung/Zusammenfassung_Gruendungszuschuss_Antragsstand.pdf`
- **Kategorie:** Arbeit & Projekte | **Datum:** 2026-06-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.
- **Zusammenfassung:** Übersichtsdokument zum Bearbeitungsstand des Gründungszuschusses im BA-Portal (Schritt 6 von 11) nebst To-Do-Liste zur Tragfähigkeitsprüfung.
- **Auszug:** `ZUSAMMENFASSUNG: GRÜNDUNGSZUSCHUSS
Aktueller Bearbeitungsstand und ausgewählte Antworten
ANTRAGS-STATUS
Schritt 6 von 11
In Bearbeitung
EINREICHUNGSFRIST
13.09.2026
(Kritisch - 90 Tage)
IHR ANSPRECHPARTNER
Herr Klingner
Agentur für Arbeit
1. Allgemeine Hinweise & Beratung
1. Gründungszuschuss-Beratu`',
            ],
            [
                'slug' => 'arbeitsagentur-bescheide-nahtlosigkeit-und-korrespondenz-2021-2026',
                'title' => 'Bundesagentur für Arbeit (BA Gifhorn): Bescheide, Nahtlosigkeit & Schriftverkehr (2021–2026)',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Agentur für Arbeit', 'ALG 1', 'Gründungszuschuss', 'Krankengeld', 'Finanz-Audit', 'Workspace', 'Dokumente'],
                'content' => '# Bundesagentur für Arbeit (BA Gifhorn): Bescheide, Nahtlosigkeit & Schriftverkehr (2021–2026)

Chronologische Übersicht aller Bescheide, Veränderungsmitteilungen, Leistungsfortzahlungen (§ 146 SGB III), Nahtlosigkeits-Korrekturen (24.01.–29.01.2026) und vorläufigen Leistungsanträge (§ 43 SGB I / § 328 SGB III) aus `Berufsleben/Arbeitsamt`.

## 1. 2026 09 22 Neue Mitteilungen Ihrer Bundesagentur F R Arbeit
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-09-22_Neue_Mitteilungen_Ihrer_Bundesagentur_f_r_Arbeit.md`
- **Kategorie:** Allgemein
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.

### Volltext / Inhalt
# Neue Mitteilungen Ihrer Bundesagentur für Arbeit

- **Von:** Mitteilungsservice <mitteilungsservice@arbeitsagentur.de>
- **An:** alina.stone@t-online.de
- **Datum:** 2026-09-22 03:36:31
- **Kategorie:** Behörde & Amt
- **Priorität:** high
- **KI-Zusammenfassung:** Die Bundesagentur für Arbeit informiert Alina über neue Mitteilungen in ihrem Online-Portal. Sie wird aufgefordert, sich dort anzumelden, um die Neuigkeiten einzusehen.
- **Handlungsbedarf:** Neue Mitteilungen im Portal der Bundesagentur für Arbeit: Im Online-Portal der Bundesagentur für Arbeit anmelden und neue Mitteilungen prüfen.

---

## Nachrichtentext
Guten Tag,



in Ihrem Portal bei der Bundesagentur für Arbeit liegen neue Mitteilungen für Sie vor.



So rufen Sie diese Mitteilungen auf:

Melden Sie sich auf der Internetseite der Bundesagentur für Arbeit im Online-Portal an. Klicken Sie anschließend im Kopfbereich auf das Glockensymbol, um zu Ihren Neuigkeiten zu gelangen. Beachten Sie, dass die Mitteilungen für jedes Profil getrennt abgelegt sind. Prüfen Sie alle Profile auf neue Mitteilungen.



Aus Datenschutzgründen enthält diese E-Mail keine detaillierteren Informationen zu den Mitteilungen.



Mit freundlichen Grüßen

Ihre Bundesagentur für Arbeit







E-Mail-Service abbestellen

Diesen E-Mail-Service können Sie jederzeit und ohne Angabe von Gründen abbestellen: www.arbeitsagentur.de/link/e-mail-benachrichtigung

Melden Sie sich auf der Internetseite der Bundesagentur für Arbeit im Online-Portal an - Sie werden dann automatisch zu Ihrem letzten gewählten Profil weitergeleitet.

Rufen Sie im Kopfbereich [Profil]>[Profil bearbeiten] auf. Unter [Kommunikationsoptionen]>[E-Mail-Benachrichtigung] können Sie die E-Mail-Benachrichtigung deaktivieren bzw. anpassen.



Bitte antworten Sie nicht auf diese E-Mail: Sie wurde automatisch versandt. Ihre Fragen rund um Ihr Konto beantworten wir gern telefonisch.



Wie Sie unseren Technischen Support erreichen können, erfahren Sie auf folgender Seite: https://www.arbeitsagentur.de/link/kontakt



Hinweis: Informationen zu unserem Servicetelefon für Menschen mit Hörbeeinträchtigungen sind dort ebenfalls zu finden.



Auf dieser Seite finden Sie auch Kontaktdaten für weitere Anliegen.


## 2. BA-Nachricht: Automatische Erinnerung zu Online-Antrag 2021
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2021-04-15_BA_Automatische_Erinnerung_Antrag_2021.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Automatische Systemnachricht der Bundesagentur für Arbeit aus April 2021.
- **Auszug:** `Allgemeine Anfrage
Neueste: Nachricht 1 von 1
Datum: 15.04.2021 | 02:02
Absender:  
Betreff: Fehlende Dokumente zu Ihrem Antrag vom 08.04.2021 – 
automatische Erinnerung
Text: Sehr geehrte Nutzerin, sehr geehrter Nutzer,
dies ist eine automatisch erstellte Nachricht.
Sie haben Ihren Antrag am 08.04.`

## 3. BA-Nachricht: Dringende Reklamation fehlendes Krankengeld August/September 2024
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2024-09-30_BA_Nachricht_Fehlendes_Geld_August_September_2024.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Dringende Nachricht von Alina (damals Nick) Steinhauer vom 30.09.2024 bezüglich unberechtigter Deckungslücke durch BKK firmus im August 2024.
- **Auszug:** `Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 30.09.2024 | 15:54
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld - Dringend
Text: Hallo,
das Krankengeld welches ich erhalten habe, wurde jetzt nur für den 
Zeitraum vom 23.08.24 - 02.09.24 ausgezahlt.
Mir fehlt das komplette Geld für A`

## 4. BA-Nachricht: Aufstellung fehlendes Geld (31.07.–22.08.2024) Zwischen BKK und BA
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2024-10-03_BA_Nachricht_Aufstellung_fehlende_Gelder_Krankengeld.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Nachricht an die Agentur für Arbeit Gifhorn vom 03.10.2024 mit Zahlungsaufstellung zur Klärung der 3-wöchigen Lücke zwischen ALG 1 und Krankengeld.
- **Auszug:** `Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 03.10.2024 | 19:51
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld
Text: Sehr geehrte Damen und Herren,
bitte prüfen Sie das. Meine Krankenkasse sagt, dass ich mit Ihnen 
über das fehlende Geld vor dem 23.08.2024 sprechen soll.
Meine erha`

## 5. BA-Antwortschreiben: Bestätigung Ende der Leistungsfortzahlung zum 30.07.2024
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2024-10-14_BA_Antwort_Ende_Leistungsfortzahlung_30-07-2024.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Offizielles Antwortschreiben der Agentur für Arbeit Gifhorn vom 14.10.2024. Bestätigt das reguläre Ende der 6-wöchigen Leistungsfortzahlung (§ 146 SGB III) zum 30.07.2024 und verweist für den Anschluss ab 31.07.2024 zwingend auf die Krankenkasse (BKK firmus).
- **Auszug:** `Anfrage Arbeitslosengeld
Neueste: Nachricht 2 von 2
Datum: 14.10.2024 | 13:32
Absender: Agentur für Arbeit Gifhorn
Betreff: Fehlendes Geld
Text: Sehr geehrter Herr Steinhauer,
vielen Dank für Ihre Nachricht.
Unter Bezugnahme auf Ihre Nachricht verweisen wir auf unser 
Schreiben vom 12.08.2024. Wie S`

## 6. BA-Nachricht: Nachdrückliche Mahnung wegen Zahlungsrückstand 31.07.–22.08.2024
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2024-10-14_BA_Nachricht_Mahnung_Geldluecke.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Nachricht an die Agentur für Arbeit Gifhorn bezüglich finanzieller Notlage durch verspätete Krankengeldzahlung der BKK firmus.
- **Auszug:** `Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 14.10.2024 | 11:19
Absender: Steinhauer, Nick
Betreff: Ich bin im Minus! Fehlendes Geld! Wichtig!
Text: Hallo,
ich habe eine erhebliche geldliche Lücke und telefonisch ist es nicht 
möglich durchzukommen. Nach mehrmaligen (20min) Wartesessio`

## 7. BA-Nachricht: Antrag auf rückwirkende Leistungskorrektur (24.01.–29.01.2026 Nahtlosigkeit)
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-09-02_BA_Nachricht_Antrag_rueckwirkende_Leistungskorrektur_24-29_Januar.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Schreiben von Alina Steinhauer über das BA-Onlineportal an die Agentur für Arbeit Gifhorn vom 02.09.2026. Beantragung von 361,26 EUR ALG 1 für die 6 Tage 24.01.–29.01.2026 im Anschluss an die Aussteuerung der BKK firmus.
- **Auszug:** `Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 02.09.2026 | 10:47
Absender: Alina Steinhauer
Betreff: Antrag auf rückwirkende Leistungskorrektur
Text: Sehr geehrte Damen und Herren,
mit Bewilligungsbescheid vom 25.02.2026 wurde mir 
Arbeitslosengeld gem. § 136 SGB III ab dem 30.01.2026 m`

## 8. BA-Nachricht: Nachreichung Aussteuerungsbescheid der Krankenkasse an Frau Schinke
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-09-22_BA_Nachricht_Nachreichung_Aussteuerungsbescheid_Frau_Schinke.pdf`
- **Kategorie:** Behörden & Ämter | **Datum:** 2026-10-05
- **Zweck:** Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.
- **Zusammenfassung:** Schreiben von Alina Steinhauer vom 22.09.2026 z.H. Frau Schinke (Agentur für Arbeit) zur Nachreichung des BKK-Aussteuerungsbescheides (Dok. 9650125178105.pdf) bezüglich des Antrags auf Leistungskorrektur.
- **Auszug:** `Unterlagen nachreichen
Neueste: Nachricht 1 von 1
Datum: 22.09.2026 | 13:46
Absender: Alina Steinhauer
Betreff: Bescheid der Krankenkasse über die Aussteuerung
Text: Z.H. Frau Schinke
Sehr geehrte Frau Schinke,
da ich aktuell nicht mobil bin und mich von einer OP erhole, reiche ich 
den Nachweis (Be`

## 9. BA-Onlineportal Transaktionsverlauf: Bescheide & Nachweise (Export 05.10.2026)
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Bescheide_und_Nachweise.csv`
- **Kategorie:** Behörden & Ämter | **Datum:** 2026-10-05
- **Zweck:** Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.
- **Zusammenfassung:** Vollständiger Audit-Export des Online-Portals der Bundesagentur für Arbeit. Belegt u.a. Bereitstellung des Aufhebungsbescheids am 20.08.2026 (abgerufen am 21.08.2026) sowie Antwortschreiben vom 21.09./22.09.2026.
- **Auszug:** `Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
22.09.2026 09:24;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Antwortschreiben wurde in der Bescheidablage abgerufen.;
22.09.2026 09:23;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der`

## 10. BA-Onlineportal Transaktionsverlauf: Gründungszuschuss
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Gruendungszuschuss.csv`
- **Kategorie:** Arbeit & Projekte | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.
- **Zusammenfassung:** Audit-Export des Gründungszuschuss-Vorgangs im BA-Portal (begonnen 15.06.2026, geschlossen 14.09.2026 wegen OP/AU).
- **Auszug:** `Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
14.09.2026 03:06;Vorgang geschlossen;Gründungszuschuss;Die Frist zur Einreichung von Dokumenten ist abgelaufen.;
15.06.2026 15:19;Antrag begonnen;Gründungszuschuss;Sie haben einen Antrag auf Gründungszuschuss (Phase 1) begonnen.;`

## 11. BA-Onlineportal Transaktionsverlauf: Veränderungsmitteilungen Arbeitsunfähigkeit
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Veraenderungsmitteilungen_AU.csv`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Vollständiger Audit-Export der gemeldeten Arbeitsunfähigkeiten im BA-Portal. Belegt Übermittlung der stationären AU ab 22.06.2026 (gesendet am 13.07.2026) sowie der Folge-AU ab 10.08.2026 (gesendet am 10.08.2026).
- **Auszug:** `Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
10.08.2026 09:10;Arbeitsunfähigkeit mitgeteilt;Veränderungsmitteilung;Sie haben die Mitteilung zur Arbeitsunfähigkeit am 10.08.2026 versendet.;Ich melde mich für den Zeitraum vom 10.08.2026 voraussichtlich bis einschließlich 04.09.2026 weite`

## 12. 2026 10 06 Chat Alina Status Arbeitsamt Gruendungszuschuss
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/2026-10-06_Chat_Alina_Status_Arbeitsamt_Gruendungszuschuss.txt`
- **Kategorie:** Allgemein
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.
- **Auszug:** `[11:52, 6.10.2026] Alina: lächerlich alles
[11:53, 6.10.2026] Alina: gibt kein Notgeld, Auffanggeld oder sonst was
[11:53, 6.10.2026] Alina: Ich bekomme kein ALG
[11:53, 6.10.2026] Alina: Sonst kann ich die Selbstständigkeit gleich sein lassen. Ohne Gründerzuschuss bekomme ich auch kein ALG
[11:53, 6.10.2026] Alina: ich muss mich jetzt sofort selbstständig machen sonst sind die 150 Resttage verstrichen
[11:54, 6.10.2026] Alina: Ich reiche den Businessplan und Liquiditätsplan bei der Steuerberate`

## 13. Antrag auf vorläufige Weiterleistung von Arbeitslosengeld (§ 43 SGB I / § 328 SGB III)
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/Antrag_Arbeitsagentur_Vorlaeufige_Leistung_Alina_Steinhauer.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Offizieller, 1-seitiger Antrag an die Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) auf vorläufige Weiterzahlung des Arbeitslosengeldes nach § 43 SGB I / § 328 SGB III (hilfsweise Vorschuss nach § 42 SGB I) ab dem 03.08.2026. Begründet durch das Auslaufen der 6-wöchigen Leistungsfortzahlung (§ 146 SGB III) am 02.08.2026 und die rechtswidrige Krankengeld-Verweigerung der BKK firmus. Vollständige Freistellung der BA über § 104 SGB X.
- **Auszug:** `Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn · Tel: 01570 1916814
Agentur für Arbeit Helmstedt
Geschäftsstelle Gifhorn · Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
 Datum: 05.10.2026
Kundennummer / Az.: 241D129534
 (Dienststelle 241 / Team 7002)
Name: Alina Steinhaue`

## 14. Kurzantrag auf vorläufige Weiterzahlung von Arbeitslosengeld (Agentur für Arbeit Gifhorn)
- **Dateipfad:** `agenten/workspace/Berufsleben/Arbeitsamt/Kurzantrag_Arbeitsagentur_Gifhorn_Alina_Steinhauer.pdf`
- **Kategorie:** Recht & Behörden | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.
- **Zusammenfassung:** Kompakter, auf 1 Seite optimierter Kurzantrag zur Vorlage bei der Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) am 05.10.2026. Beantragt die vorläufige Weiterzahlung des ALG 1 ab 03.08.2026 gemäß § 43 Abs. 1 SGB I / § 328 SGB III wegen des laufenden Zuständigkeitsstreits mit der BKK firmus. Vollständige Freistellung der BA vom Ausfallrisiko nach § 104 SGB X.
- **Auszug:** `Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Agentur für Arbeit Gifhorn
Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
Datum:
  05.10.2026
Kundennummer: 241D129534
Geburtsdatum: 01.09.1993
Persönliche Vorsprache
Antrag auf vorläufige Weiterzahlung von Arbeitslosengeld
Re`',
            ],
            [
                'slug' => 'bkk-firmus-bescheide-widersprueche-und-chatprotokolle-komplettregister',
                'title' => 'BKK firmus & Sozialrecht: Komplettregister aller Bescheide, Widersprüche, Chatprotokolle & Nachweise (2024–2026)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['BKK firmus', 'Krankengeld', 'GA-OP', 'Widerspruch', 'eAU', 'Sozialgericht', 'Workspace', 'Dokumente'],
                'content' => '# BKK firmus & Sozialrecht: Komplettregister aller Bescheide, Widersprüche, Chatprotokolle & Nachweise (2024–2026)

Revisionssicheres Gesamtregister aller 94 Aktenstücke aus `Gesundheit/Krankenkasse`, `Gesundheit/Krankengeld` und `Gesundheit/Nachweise` (inkl. Bescheide `9650060300010` bis `9650226941682`, Widerspruchsausschuss, Einschreiben `RT613794858DE` und App-Chatprotokolle).

## 1. Vollständiges Chatprotokoll: Arbeitsunfähigkeit
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Arbeitsunfähigkeit.txt`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2023-06-20
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Arbeitsunfähigkeit.
- **Auszug:** `Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

20.06.2023, 13:14:13
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 30.04.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

03.05.2024, 05:35:35
Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

06.05.2024, 12:06:56
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem`

## 2. Vollständiges Chatprotokoll: Familienversicherung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Familienversicherung.txt`
- **Kategorie:** Familienversicherung | **Datum:** 2025-06-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Familienversicherung.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Familienversicherung.
- **Auszug:** `Icon für Absender
Hallo lieber Support,

bitte ändern Sie die Wohnadresse von Noah Steinhauer (KV-Nr.: Z838294313) zum 01.06.2025 in:
Rigaer Str. 18 in 38542 Leiferde

Vielen Dank.


Gruß,
Steinhauer

22.05.2025, 20:34:10
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage.

Die Adresse wird geändert.

Freundliche Grüße

Ihre BKK firmus

23.05.2025, 11:19:32
Icon für Absender
Der Familienfragebogen (Bestandspflege) befindet sich im Anhang

BestandspflegeFamilienversicherung.pdf (65 KB)
30.01.2026, 10:08:24
Icon für Absender
Haben Sie Noah Steinhauer aus der Versicherung ausgeschlossen? `

## 3. Vollständiges Chatprotokoll: Krankengeld
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Krankengeld.txt`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-10-22
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Krankengeld.
- **Auszug:** `Guten Tag,

vielen Dank für Ihre Nachricht.

Leider können wir Ihre Nachricht nicht zuordnen.

Um welche Erstattung handelt es sich bei Ihnen? Um die aktuelle Krankengeldzahlung ?


Freundliche Grüße

Ihre BKK firmus

22.10.2024, 13:03:46
Icon für Absender
Bezogen auf den Widerspruch?

23.10.2024, 00:43:31
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Das Schreiben vom 21.10.2024 hat sich auf Ihre Email vom 18.10.2024 bezogen.

Freundliche Grüße

Ihre BKK firmus

23.10.2024, 14:03:09
Icon für Absender
Guten Tag,

bezüglich Ihrer Arbeitsunfähigkeitszeiträume hätten wir noch einm`

## 4. Vollständiges Chatprotokoll: Krankenhaus
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Krankenhaus.txt`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-11-09
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Krankenhaus.
- **Auszug:** `Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht.
Für die Prüfung einer möglichen Kostenübernahme von geschlechtsangleichenden Operationen werden folgende Unterlagen von uns benötigt:

-Arztbericht über gegengeschlechtliche Hormontherapie
-Formloser Antrag vom Kunden auf Kostenübernahme
-Aktueller Psychotherapiebericht mit Angaben zum Befund, zum Therapieverlauf, zum begleitenden Alltagstest in allen psychosozialen Lebensbereichen, zum Erreichen der Therapieziele und zur prognostischen Einschätzung im Falle der Durchführung der Operation
-Gynäkologischer Befundbericht zum Ausschlus`

## 5. Eingangsbestätigung: Antrag auf Kostenübernahme Barthaarentfernung mit Laser
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650060300010.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-01-23
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** BKK firmus bestätigt den Eingang des Antrags auf Kostenübernahme für die Barthaarentfernung mittels Lasertherapie. Der Antrag wird zur Begutachtung an den Medizinischen Dienst (MD) weitergeleitet.
- **Eckdaten:** antrag: Barthaarentfernung Laser, status: Weiterleitung an MD, datum: 23.01.2024
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Barthaarentfernung mit Laser 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich`

## 6. Zwischennachricht: Hormonersatztherapie (HRT) Begutachtung beim MD
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650061225545.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2024-02-05
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** Mitteilung der BKK firmus, dass sich der Antrag und die ärztlichen Unterlagen zur Hormonersatztherapie (HRT) derzeit noch in der sozialmedizinischen Begutachtung beim Medizinischen Dienst befinden.
- **Eckdaten:** thema: Hormonersatztherapie, status: In Begutachtung beim MD, datum: 05.02.2024
- **Auszug:** `--- Seite 1 ---
Hormonersatztherapie 
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht.  
 
Ihr Antrag befindet sich derzeit noch zur Begutachtung beim Medizinischen Dienst (MD). 
Sobald wir eine Rückmeldung erhalten haben, werden wir uns mit Ihnen in Verbindung 
setzen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Rufen Sie uns an 
oder schauen Sie bei uns vorbei. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
3`

## 7. Eingangsbestätigung: Unterlagen zur Haarentfernung an den MD gesandt
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650062101659.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-02-19
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** BKK firmus bestätigt die Einreichung weiterer Unterlagen zur Haarentfernung und teilt mit, dass diese zur Prüfung an den Medizinischen Dienst weitergeleitet wurden.
- **Eckdaten:** thema: Haarentfernung Unterlagen, status: Weiterleitung MD, datum: 19.02.2024
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Haarentfernung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wi`

## 8. Zwischenbescheid: Antrag auf Kostenübernahme in Prüfung beim MD
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650062820219.pdf`
- **Kategorie:** Allgemeiner Schriftverkehr | **Datum:** 2024-02-28
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Allgemeiner Schriftverkehr\'.
- **Zusammenfassung:** Mitteilung über den aktuellen Bearbeitungsstand des Antrags auf Kostenübernahme und die Begutachtung durch den Medizinischen Dienst Niedersachsen.
- **Eckdaten:** thema: Kostenübernahme, status: MD Begutachtung läuft, datum: 28.02.2024
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
info`

## 9. Prüfmitteilung: Kostenübernahme Barthaarentfernung mit Laser
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650064287652.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-03-21
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** Detaillierte Mitteilung der BKK firmus zur beantragten Barthaarentfernung mit Laser. Erläuterung der gesetzlichen Voraussetzungen und der Begutachtungsschritte des Medizinischen Dienstes.
- **Eckdaten:** thema: Barthaarentfernung Laser, status: Prüfung gesetzliche Voraussetzungen, datum: 21.03.2024
- **Auszug:** `--- Seite 1 ---
Kostenübernahme Barthaarentfernung mit Laser
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf  Kostenübernahme. 
 
Damit der Medizinische Dienst ( MD ) die Bearbeitung fortsetzen kann, benötigt dieser noch 
die folgenden Unterlagen: 
 
- 
Foto Dokumentation der von Haaren zu entfernenden Körperregionen  
- 
Dermatologischer Befundbericht hinsichtlich Indikation/ Durchführung der 
Haarentfernung  
- 
Endokrinologischer/urologischer Befund/Verlaufsbericht  
 
Sobald wir Ihre Unterlagen bekommen haben, erhalten Sie kurzfristig Nachricht von uns. 
 
Haben Sie no`

## 10. Eingangsbestätigung: Antrag auf Stimmanpassung (Logopädie)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650067808155.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2024-05-31
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** Bestätigung des Eingangs des Antrags auf Kostenübernahme für stimmanpassende Maßnahmen (Logopädie/Stimmtherapie). Unterlagen wurden zur sozialmedizinischen Prüfung an den MD gesandt.
- **Eckdaten:** thema: Stimmanpassung / Logopädie, status: Weiterleitung MD, datum: 31.05.2024
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Stimmanpassung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
infor`

## 11. Aufforderung: Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen (AU ab 12.07.2024)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650071371588.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-07-12
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** Schreiben der BKK firmus zur Arbeitsunfähigkeit ab dem 12.07.2024. Aufforderung zur Einreichung der ausgefüllten Selbstauskunft / Erklärung zur Prüfung des Anspruchs auf Krankengeld / Entgeltersatzleistungen.
- **Eckdaten:** thema: Krankengeldanspruch, au_beginn: 12.07.2024, aufforderung: Erklärung einreichen, datum: 12.07.2024
- **Auszug:** `--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 12.07.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreiche`

## 12. Bescheid: Erstattung von Arzneimitteln Hylo Dual, Cationorm und Blephademodex
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650071568085.pdf`
- **Kategorie:** Medikamente & Hilfsmittel | **Datum:** 2024-08-15
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Medikamente & Hilfsmittel\'.
- **Zusammenfassung:** Bescheid der BKK firmus bezüglich des Antrags auf Kostenerstattung für apothekenpflichtige, nicht verschreibungspflichtige Arzneimittel (Hylo Dual, Cationorm Augentropfen, Blephademodex Tücher) mit Rechtsbehelfsbelehrung.
- **Eckdaten:** medikamente: [\'Hylo Dual\', \'Cationorm\', \'Blephademodex\'], thema: Kostenerstattung Arzneimittel, datum: 15.08.2024
- **Auszug:** `--- Seite 1 ---
Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und Blephademodex 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und 
Blephademodex. 
 
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise nicht um 
verschreibungspflichtige Arzneimittel handelt und daher die Kosten nicht von der gesetzlichen 
Krankenversicherung übernommen werden können. 
 
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über 
die Verordnung von Arzneimitteln in der vertragsärztlic`

## 13. Erneute Aufforderung: Erklärung des Mitglieds für Entgeltersatzleistungen (AU ab 06.08.2024)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650072456583.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-08-06
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** Erneutes Anschreiben der BKK firmus zur Arbeitsunfähigkeit ab dem 06.08.2024 zur Feststellung und Berechnung des Krankengeldes. Enthält Kontaktdaten des Krankengeld-Teams (0421 6434-403).
- **Eckdaten:** au_datum: 06.08.2024, telefon: 0421 6434-403, datum: 06.08.2024
- **Auszug:** `--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 06.08.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreiche`

## 14. BEWILLIGUNG: Wir übernehmen die Kosten für Ihre Psychotherapie (Kostenzusage)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650072673267.pdf`
- **Kategorie:** Psychotherapie & Kostenerstattung | **Datum:** 2024-09-06
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.
- **Zusammenfassung:** ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus erteilt die schriftliche Zusage zur Kostenübernahme der ambulanten Psychotherapie für Alina Steinhauer.
- **Eckdaten:** leistung: Psychotherapie, status: BEWILLIGT (Kostenübernahme zugesagt), datum: 06.09.2024
- **Auszug:** `--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt`

## 15. HAUPTBESCHEID KRANKENGELD: Ihr Krankengeldbezug ab dem 23.08.2024 (Tagessatz: 65,90 €)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650073846318.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-08-23
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** ZENTRALER BEWILLIGUNGSBESCHEID KRANKENGELD: BKK firmus bewilligt Krankengeld ab dem 23.08.2024. Bruttokrankengeld kalendertäglich: 65,90 € (monatlich 1.977,00 € brutto). Erläutert gesetzliche Abzüge für Renten-, Arbeitslosen- und Pflegeversicherung sowie die Auszahlungsvoraussetzungen.
- **Eckdaten:** leistungsbeginn: 23.08.2024, tagessatz_brutto: 65,90 €, monatsbetrag_brutto: 1.977,00 €, leistungsart: Regelkrankengeld § 44 SGB V, datum: 23.08.2024
- **Auszug:** `--- Seite 1 ---
Ihr Krankengeldbezug ab dem 23.08.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
damit Sie auch im Falle einer längeren Arbeitsunfähigkeit abgesichert sind, hilft Ihnen die 
BKK firmus mit der Zahlung von Krankengeld. Zu Ihrer Krankengeldzahlung erhalten Sie 
heute einige wichtige Informationen.  
 
Die Entgeltfortzahlung Ihres Arbeitgebers endet aufgrund der uns vorliegenden Unterlagen 
voraussichtlich am 31.07.2024. Vorerkrankungen wegen derselben Erkrankung werden 
gegebenenfalls auf die Entgeltfortzahlung angerechnet.  
 
Ihr Krankengeld erhalten Sie daher ab dem 23.08.2024.     `

## 16. Aufforderung zur schriftlichen Widerspruchseinreichung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650075219970.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2024-10-18
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** BKK firmus reagiert auf eine Chat-Nachricht vom 18.10.2024 und fordert Alina auf, form- und fristgerecht einen schriftlichen Widerspruch gegen den Bescheid vom 10.10.2024 einzureichen.
- **Eckdaten:** thema: Widerspruchserhebung, bezug_bescheid: 10.10.2024, datum: 18.10.2024
- **Auszug:** `--- Seite 1 ---
Ihre Nachricht vom 18.10.2024
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht. 
 
Bitte reichen Sie uns einen schriftlichen Widerspruch gegen unseren Bescheid vom 
10.10.2024 ein. Die Schriftlichkeit ist nur gewahrt, wenn der Widerspruch handschriftlich 
unterschrieben ist. Gerne reichen Sie auch eine Begründung ein. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
N`

## 17. Eingangsbestätigung: Ihr Widerspruch vom 21.10.2024
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650075540649.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2024-10-21
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** BKK firmus bestätigt den fristgerechten Eingang des Widerspruchs vom 21.10.2024 gegen die Krankengeldberechnung und leitet diesen zur erneuten sozialmedizinischen Begutachtung weiter.
- **Eckdaten:** widerspruch_datum: 21.10.2024, aktenzeichen: KSWIEEINKG, datum: 21.10.2024
- **Auszug:** `--- Seite 1 ---
KSWIEEINKG 
 
Ihr Widerspruch vom 21.10.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen. 
 
Hierzu haben wir Ihren behandelnden Arzt aufgefordert entsprechende Befundberichte zum 
Medizinischen Dienst zu senden, damit wir eine weitere Überprüfung veranlassen können. 
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Ged`

## 18. ABHILFEBESCHEID: Krankengeld - Ihr Widerspruch vom 25.10.2024 (Volle Stattgabe)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650076822528.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2024-10-25
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** ERFOLG / ABHILFEBESCHEID: Die BKK firmus hilft dem Widerspruch vom 25.10.2024 gegen den Bescheid vom 18.10.2024 vollständig ab! Die Feststellung des Krankengeldanspruchs wird korrigiert und der Anspruch ab dem 23.08.2024 vollumfänglich anerkannt.
- **Eckdaten:** bescheid_art: Abhilfebescheid, ergebnis: VOLLSTÄNDIGE STATTGABE, bezug: Krankengeld ab 23.08.2024, datum: 25.10.2024
- **Auszug:** `--- Seite 1 ---
Abhilfebescheid  
Ihr Widerspruch vom 25.10.2024

 
 
Guten Tag Frau Steinhauer, 
 
am 25.10.204 haben Sie Widerspruch gegen unseren Bescheid 10.10.2024 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Wir haben das ausstehende Krankengeld angewiesen mit dem heutigen Tag. 
 
Ihrem Widerspruch vom 25.10.2024 haben wir somit abgeholfen. Gemäß  
§ 63 Abs. 1 und 2 SGB X können die für das Widers`

## 19. Bescheid: Ablehnung der Haarentfernung mittels INOS-Technologie (Hairfree)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650078218824.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-12-11
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** Ablehnungsbescheid der BKK firmus bezüglich der Kostenübernahme der Haarentfernung mit dem INOS-Verfahren (Institut Hairfree), da es sich um eine nicht im EBM gelistete Methode handele. Enthält Rechtsbehelfsbelehrung.
- **Eckdaten:** bescheid_art: Ablehnungsbescheid, methode: INOS (Hairfree), begruendung: Keine vertragsärztliche Leistung, datum: 11.12.2024
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Erbringung einer kosmetischen Dienstleistung 
an unerwünschten Haaren mittels des INOS/INOSplus Behandlungssystems (Bart 
komplett gemäß der Kundenvereinbarung vom 02.01.2024) 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten d`

## 20. BEWILLIGUNG: Kostenübernahme für Psychotherapie (Fortführung)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650078585061.pdf`
- **Kategorie:** Psychotherapie & Kostenerstattung | **Datum:** 2024-12-12
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.
- **Zusammenfassung:** ERFOLG / BEWILLIGUNG: Bestätigung der fortlaufenden Kostenübernahme für die ambulante Psychotherapie von Alina Steinhauer durch die BKK firmus.
- **Eckdaten:** leistung: Psychotherapie Fortführung, status: BEWILLIGT, datum: 12.12.2024
- **Auszug:** `--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt`

## 21. Unterlagenanforderung: Geschlechtsangleichende Operation (Vorbereitung MD)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650078742642.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2024-12-16
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** Schreiben der BKK firmus bezüglich der Kostenübernahme einer geschlechtsangleichenden Operation. Detaillierte Auflistung der für die Begutachtung durch den Medizinischen Dienst noch benötigten Berichte und Nachweise.
- **Eckdaten:** thema: Geschlechtsangleichende OP, status: Anforderung medizinischer Unterlagen, datum: 16.12.2024
- **Auszug:** `--- Seite 1 ---
Ihre Nachricht vom 13.12.2024
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Um die Kostenübernahme einer geschlechtsangleichenden Operation zu beantragen, benötigt 
die BKK firmus nur einen formlosen Antrag von Ihnen. Alle weiteren Unterlagen müssen 
aufgrund von neuen Datenschutzrichtlinien direkt an den Medizinischen Dienst (MD) übermittelt 
werden. Nach Antragsstellung erhalten Sie ein Schreiben, welches weitere Informationen sowie 
die Adresse des zuständigen MD enthält. Aufgrund von gesetzlich ein`

## 22. Bescheinigung über den Bezug von Krankengeld (Jahresende 2024)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650079272706.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-12-23
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** Offizielle Bescheinigung der BKK firmus über den laufenden Bezug von Geldleistungen (Krankengeld) zum Jahresende 2024 für Behörden, Ämter und steuerliche Nachweise.
- **Eckdaten:** nachweis: Krankengeldbezug 2024, aussteller: BKK firmus, datum: 23.12.2024
- **Auszug:** `--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
13.12.2024  
65,90 €  
65,90 €  
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen?`

## 23. Bestätigung AU-Bescheinigung ab 28.01.2025 (Hinweis auf Lücke 27.01.2025)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650083792245.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2025-01-28
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** WICHTIGER BELEG: BKK firmus bestätigt den Erhalt der AU-Bescheinigung vom 28.01.2025 bis zum 21.02.2025. Da der vorherige AU-Abschnitt am 26.01.2025 endete, entstand der ungedeckte Tag am 27.01.2025 (Unterbrechungstag à 65,90 €).
- **Eckdaten:** au_zeitraum: 28.01.2025 bis 21.02.2025, relevanz: Dokumentiert die AU-Unterbrechung vom 27.01.2025, datum: 28.01.2025
- **Auszug:** `--- Seite 1 ---
Arbeitsunfähigkeitsbescheinigung ab dem 28.01.2025 euerBrief]]
 
 
Guten Tag Frau Steinhauer, 
 
wir haben Ihre Arbeitsunfähigkeitsbescheinigung für den 28.01.2025 bis zum 21.02.2025 
erhalten. Jedoch können wir diese nicht erfassen, da ab dem 25.01.2025 kein 
Versicherungsverhältnis besteht.  
 
Die Arbeitsunfähigkeit muss lückenlos nachgewiesen werden. Sie hätten spätestens am 
27.01.2025 wieder bei Ihrem Arzt vorstellig werden müssen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fr`

## 24. Antwort der BKK firmus zur geplanten stationären Behandlung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650090394429.pdf`
- **Kategorie:** Krankenhaus & Stationär | **Datum:** 2025-02-18
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankenhaus & Stationär\'.
- **Zusammenfassung:** Schreiben der BKK firmus als Antwort auf Alinas Nachricht vom 17.02.2025 bezüglich der Kostenübernahme und Organisation einer stationären Krankenhausbehandlung.
- **Eckdaten:** thema: Stationäre Behandlung, bezug_nachricht: 17.02.2025, datum: 18.02.2025
- **Auszug:** `--- Seite 1 ---
Ihre Nachricht vom 17.02.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Vielen Dank für Ihre Nachricht vom 17.02.2025. In diesem Fall ist der gynäkologische 
Befundbebricht nicht erforderlich.       
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Sarah Üffing 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
`

## 25. Versicherungsbescheinigung für ärztliche Behandlung (Alina Steinhauer)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650090870349.pdf`
- **Kategorie:** Versicherungsbescheinigung | **Datum:** 2025-02-20
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Versicherungsbescheinigung\'.
- **Zusammenfassung:** Offizielle Mitglieds- und Versicherungsbescheinigung der BKK firmus für Alina Steinhauer (geb. 01.09.1993) zur Vorlage bei Ärzten, Kliniken und Therapeuten.
- **Eckdaten:** mitglied: Alina Steinhauer, geburtsdatum: 01.09.1993, status: Versichert bei BKK firmus, datum: 20.02.2025
- **Auszug:** `--- Seite 1 ---
Versicherungsbescheinigung für ärztliche Behandlung 
 
 
Guten Tag Frau Steinhauer, 
 
gerne bescheinigen wir Ihnen, dass Sie bei der BKK firmus versichert sind: 
 
Name: 
Steinhauer, Alina 
Geburtsdatum:  
01.09.1993 
Kassen-Nr.: 
103 121 137 
Versicherten-Nr.: 
O603571189 
Status: 
Mitglied 
Papiergebundener Anspruchsnachweis gültig für: 
1. Quartal 2025 
 
Für Rückfragen oder weitere Informationen rufen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Bet`

## 26. Bescheinigung über den Bezug von Krankengeld (März 2025)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650097392433.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2025-03-27
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** Bescheinigung über den laufenden Bezug von Geldleistungen (Krankengeld ab 23.08.2024 bis März 2025) für Alina Steinhauer zur Vorlage bei Sozialleistungsträgern.
- **Eckdaten:** thema: Krankengeld-Bescheinigung, zeitraum: Laufender Bezug März 2025, datum: 27.03.2025
- **Auszug:** `--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  
Frau Alina Steinhauer, geb. 01.09.1993

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
21.03.2025 
65,90  
65,90 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünsche`

## 27. Detaillierte Bescheinigung der Krankengeld-Auszahlungszeiträume
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650100261190.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2025-04-23
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** WICHTIGE AUSZAHLUNGSLISTE: Detaillierte Bescheinigung aller Krankengeldzahlungen von 23.08.2024 bis 31.12.2024 und ab 01.01.2025 (unter Aussparung des 27.01.2025). Belegt die exakte Zahlungsfolge der BKK firmus.
- **Eckdaten:** leistungszeitraum: 23.08.2024 bis April 2025, relevanz: Offizieller Nachweis der geleisteten Krankengeld-Auszahlungen, datum: 23.04.2025
- **Auszug:** `--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
31.12.2024  
65,90 €  
65,90 €  
Krankengeld 
01.01.2025 
26.01.2025 
65,90 € 
65,90 € 
Krankengeld 
28.01.2025 
laufend 
65,90 € 
65,90 € 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei `

## 28. BEWILLIGUNG: Kostenübernahme Psychotherapie (Bewilligungsbescheid Mai 2025)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650107004279.pdf`
- **Kategorie:** Psychotherapie & Kostenerstattung | **Datum:** 2025-05-16
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.
- **Zusammenfassung:** ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus bewilligt weitere Psychotherapiestunden für Alina Steinhauer und sagt die vollständige Kostenübernahme zu.
- **Eckdaten:** leistung: Psychotherapie, status: BEWILLIGT, datum: 16.05.2025
- **Auszug:** `--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt`

## 29. Zwischenbescheid: Antrag auf geschlechtsangleichende Operation beim MD
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650111610893.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2025-06-19
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** Mitteilung der BKK firmus, dass die Unterlagen für die geschlechtsangleichende Operation (Penisentfernung, Hodenentfernung, Bildung Neovulva und Neovagina) beim Medizinischen Dienst eingereicht wurden und die Begutachtung läuft.
- **Eckdaten:** eingriff: Geschlechtsangleichende Operation, status: MD Begutachtung eingeleitet, datum: 19.06.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme geschlechtsangleichender Operationen (Entfernung 
des Penis, der Hoden und Bildung von Neovulva und Neovagina)

 
 
Guten Tag Frau Steinhauer, 
vielen Dank für die Einreichung der Unterlagen bei dem Medizinischen Dienst (MD). 
 
Den MD haben wir mit der Prüfung Ihres Antrages auf Kostenübernahme 
geschlechtsangleichender Operationen (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina beauftragt.  
 
Aus diesem Grund bitten wir Sie um etwas Geduld. Bis zum 08.07.2025 können Sie jedoch 
mit unserer Antwort rechnen. 
 
Für Fragen`

## 30. Stellungnahme der BKK firmus zum Bescheid vom 07.07.2025 (OP-Antrag)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650114201740.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2025-07-14
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** Antwort der BKK firmus auf Alinas Schreiben vom 09.07. und 10.07.2025 bezüglich des ablehnenden Bescheids vom 07.07.2025. Unmittelbare Vorstufe zum Abhilfebescheid.
- **Eckdaten:** thema: OP-Bescheidprüfung, datum: 14.07.2025
- **Auszug:** `--- Seite 1 ---
Ihre Nachrichten vom 09.07.2025 und 10.07.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kunden im Vordergrund.  
 
Vielen Dank für Ihre Nachrichten vom 09.07.2025 und vom 10.07.2025. Die BKK firmus hat 
am 07.07.2025 einen Bescheid erlassen. Es besteht kein weiterer Anspruch auf eine 
Genehmigungsfikton. Das Gutachten vom Medizinischen Dienst Niedersachsen liegt der 
BKK firmus bisher nicht vor.  
 
Ihr Widerspruch vom 09.07.2025 ist bei der BKK firmus eingegangen und befindet sich in 
Prüfung.         
 
Haben Sie noch Fragen?`

## 31. ABHILFEBESCHEID: Bewilligung der geschlechtsangleichenden Operation bis 15.01.2026
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650114306636.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2025-07-15
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** HERAUSRAGENDER ERFOLG / ABHILFEBESCHEID: Die BKK firmus hebt den Ablehnungsbescheid vom 07.07.2025 vollständig auf und BEWILLIGT die geschlechtsangleichende Operation (Entfernung Penis, Hoden, Bildung Neovulva und Neovagina)! Gültig bis 15.01.2026.
- **Eckdaten:** bescheid_art: Abhilfebescheid (Stattgabe), leistung: Geschlechtsangleichende Operation, befristet_bis: 15.01.2026, aufgehobener_bescheid: 07.07.2025, datum: 15.07.2025
- **Auszug:** `--- Seite 1 ---
Abhilfebescheid  
Bewilligung der Kostenübernahme einer geschlechtsangleichenden Operationen 
(Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
am 09.07.2025 haben Sie Widerspruch gegen unseren Bescheid vom 07.07.2025 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie positiven Ergebnis geführt hat. 
 
Aus den vorliegenden Unterlagen kann eine sichere Diagnostik, eine au`

## 32. Unterlagenprüfung: Antrag Haarentfernung (Bart komplett) INOS
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650117915024.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2025-08-20
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** Mitteilung der BKK firmus, dass der erneute Antrag auf Haarentfernung (Bart komplett) mittels INOS-Technologie auf Vollständigkeit geprüft wurde und eine Begutachtung durch den MD erforderlich ist.
- **Eckdaten:** leistung: Haarentfernung Bart komplett, status: MD Begutachtung veranlasst, datum: 20.08.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf die Haarentfernung (Bart komplett) mittels INOS-Technologie 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf die Haarentfernung mittels INOS-Technologie haben wir auf Vollständigkeit 
geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD`

## 33. Zwischenmitteilung: Verlängerung der Kostenübernahme für die geschlechtsangleichende OP
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650119701902.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2025-09-10
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** Bestätigung des Eingangs des Antrags auf Fristverlängerung für die bewilligte geschlechtsangleichende Operation über den 15.01.2026 hinaus.
- **Eckdaten:** antrag: Verlängerung Kostenübernahme OP, status: In Bearbeitung, datum: 10.09.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen m`

## 34. BEWILLIGUNG DER VERLÄNGERUNG: Geschlechtsangleichende OP verlängert bis 30.06.2026
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650122075850.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2025-10-01
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.
- **Zusammenfassung:** ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus verlängert die Kostenübernahme für die geschlechtsangleichende Operation antragsgemäß bis zum 30.06.2026!
- **Eckdaten:** bescheid_art: Bewilligung Verlängerung, leistung: Geschlechtsangleichende OP, neue_frist: 30.06.2026, datum: 01.10.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen m`

## 35. Ablehnungsbescheid: Kostenübernahme Barthaarentfernung (Widerspruchsbelehrung)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650122855980.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2025-10-09
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** Ablehnungsbescheid vom 09.10.2025 bezüglich der Kostenübernahme für die Barthaarentfernung / Epilation. Gegen diesen Bescheid legte Alina am 19.10.2025 erfolgreich Widerspruch ein!
- **Eckdaten:** bescheid_art: Ablehnungsbescheid, datum_bescheid: 09.10.2025, folge: Widerspruch durch Alina am 19.10.2025, datum: 09.10.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Barthaarentfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zu`

## 36. Eingangsbestätigung: Ihr Widerspruch vom 19.10.2025 (Barthaarentfernung)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650124914937.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2025-10-29
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** BKK firmus bestätigt den Eingang des Widerspruchs vom 19.10.2025 gegen die Ablehnung der Barthaarentfernung und leitet die Akte zur erneuten Begutachtung an den Medizinischen Dienst.
- **Eckdaten:** widerspruch_datum: 19.10.2025, gegen_bescheid: 09.10.2025, status: Weiterleitung MD, datum: 29.10.2025
- **Auszug:** `--- Seite 1 ---
Ihr Widerspruch vom 19.10.2025 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht nochmal durch den Medizinischen Dienst überprüfen lassen. Die 
Medizinischen Dienste beraten die gesetzlichen Krankenkassen in Fragen der allgemeinen 
medizinischen Versorgung und prüfen, welche Behandlung oder Therapie für Sie die 
Richtige ist und ob weitere Maßnahmen für Sie sinnvoll sind. 
 
Reichen Sie uns hierzu bitte noch folgende Unterlagen ein: 
 
- 
Eine ausführliche ärztliche Widerspruchsbegründung 
 
Soba`

## 37. ZENTRALER BESCHEID: Ende des Anspruchs auf Krankengeld (Aussteuerung zum 29.01.2026)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650125178105.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2025-11-03
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** WICHTIGSTER AUSSTEUERUNGSBESCHEID: Die BKK firmus teilt verbindlich mit, dass der Anspruch auf Krankengeld nach 78 Wochen (= 546 Tagen) innerhalb der 3-Jahres-Rahmenfrist (18.06.2024 bis 17.06.2027) mit Ablauf des 29.01.2026 endet. Dringende Aufforderung zur Beantragung von Arbeitslosengeld (Nahtlosigkeit § 145 SGB III) bei der Agentur für Arbeit.
- **Eckdaten:** bescheid_art: Aussteuerungsbescheid, anspruchsende: 29.01.2026, rahmenfrist: 18.06.2024 bis 17.06.2027, hoechstdauer: 78 Wochen (546 Tage), handlungsempfehlung: Meldung Arbeitsagentur für Nahtlosigkeit / ALG 1, datum: 03.11.2025
- **Auszug:** `--- Seite 1 ---
Ende des Anspruchs auf Krankengeld      
 
 
Guten Tag Frau Steinhauer, 
 
der Anspruch auf Krankengeld wegen derselben Krankheit besteht für längstens 78 Wochen 
(= 546 Tage) innerhalb von 3 Jahren. Ihr Anspruch auf Krankengeld endet mit Ablauf des 
29.01.2026. 
 
Die für Sie maßgebende Rahmenfrist von drei Jahren verläuft vom 18.06.2024 bis zum 
17.06.2027. 
 
Auch wenn während der Arbeitsunfähigkeit eine weitere Krankheit hinzutritt, verlängert sich 
nicht die Dauer des Anspruchs von 78 Wochen. Die Arbeitsunfähigkeitszeiten, für die der 
Anspruch auf Krankengeld ruht (z. B. `

## 38. Unterlagenprüfung: Antrag auf Haarentfernung nach Widerspruch
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650128163289.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2025-12-01
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** BKK firmus bestätigt den Eingang ergänzender fachärztlicher Unterlagen zur Haarentfernung im laufenden Widerspruchsverfahren und die erneute Vorlage beim MD.
- **Eckdaten:** thema: Haarentfernung Unterlagen, status: Erneute MD Prüfung, datum: 01.12.2025
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Haarentfernung (nach Widerspruch) 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstver`

## 39. Zwischenbescheid: Barthaarentfernung Begutachtung beim MD
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650135013368.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2026-01-20
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.
- **Zusammenfassung:** Schreiben der BKK firmus zur andauernden Begutachtung der Barthaarentfernung beim Medizinischen Dienst mit Fristsetzung zur Stellungnahme bis 02.02.2026.
- **Eckdaten:** thema: Barthaarentfernung, frist: 02.02.2026, datum: 20.01.2026
- **Auszug:** `--- Seite 1 ---
Ihr Antrag auf Kostenübernahme für die Barthaarenfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns`

## 40. Bestätigung: Aufrechterhaltung des Widerspruches vom 22.01.2026 (Vorlage Widerspruchsausschuss)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650136945228.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2026-01-27
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** BKK firmus bestätigt, dass Alina ihren Widerspruch ausdrücklich aufrechterhält. Die Entscheidung wird nun an den unabhängigen Widerspruchsausschuss der BKK firmus übergeben.
- **Eckdaten:** widerspruch: Aufrechterhalten, gremium: Widerspruchsausschuss, datum: 27.01.2026
- **Auszug:** `--- Seite 1 ---
Aufrechterhaltung des Widerspruches vom 22.01.2026  
 
 
Guten Tag Frau Steinhauer, 
 
mit Schreiben vom 22.01.2026 teilen Sie uns mit, dass Sie Ihren Widerspruch aufrecht erhalten. 
 
Die Entscheidung über Ihren Widerspruch trifft der Widerspruchsausschuss.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert. 
 
Bis dahin bitten wir Sie um etwas Geduld.  
 
Für eventuelle Fragen stehen wir Ihnen gerne zur Verfügung. 
 
Mit freundlichen Grüßen 
 
Ihre BKK firmus  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
`

## 41. Eingangsbestätigung: Unterlagen zum Widerspruchsausschuss (Schreiben vom 01.04.2026)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650150500199.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2026-04-10
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** Bestätigung des Eingangs von Alinas ergänzendem Schreiben vom 01.04.2026 zur Weiterleitung an die Mitglieder des Widerspruchsausschusses.
- **Eckdaten:** schreiben_alina: 01.04.2026, status: Weiterleitung Ausschuss, datum: 10.04.2026
- **Auszug:** `--- Seite 1 ---
Ihre Unterlagen zum Widerspruch vom 19.10.2026       
 
 
Guten Tag Frau Steinhauer, 
 
hiermit bestätigen wir Ihnen den Eingang Ihres Schreibens vom 01.04.2026. 
 
Mit Schreiben vom 27.01.2026 teilten wir Ihnen mit, dass die Entscheidung über Ihren 
Widerspruch der Widerspruchsausschuss trifft.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert.  
 
Bis dahin bitten wir Sie um etwas Geduld. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Ali`

## 42. Erklärung Entgeltersatzleistungen bei neuer Arbeitsunfähigkeit ab 22.06.2026
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650160857472.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-06-25
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** Aufforderung der BKK firmus zur Einreichung der Entgeltersatz-Erklärung bei Eintritt einer neuen Arbeitsunfähigkeit ab dem 22.06.2026. Relevant für die Prüfung des neuen Krankengeldanspruchs nach der Nahtlosigkeit.
- **Eckdaten:** neue_au: 22.06.2026, thema: Neuer Krankengeldanspruch, datum: 25.06.2026
- **Auszug:** `--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Frau Steinhauer,
Sie sind seit dem 22.06.2026 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreiche`

## 43. RÜCKFORDERUNGSBESCHEID: Rückforderung Krankengeld 2.306,50 € (Zeitraum 19.12.2025 – 23.01.2026)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650193779680.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-07-07
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.
- **Zusammenfassung:** AKUTER STREITFALL: BKK firmus fordert mit Bescheid vom 07.07.2026 Krankengeld in Höhe von 2.306,50 € für die Zeit vom 19.12.2025 bis 23.01.2026 zurück. Begründung: Angeblich fehlerhafte Anrechnung von 42 Wartetagen. Gegen diesen unberechtigten Rückforderungsbescheid hat Alina am 06.08.2026 Widerspruch eingelegt!
- **Eckdaten:** bescheid_art: Rückforderungsbescheid, rueckforderungsbetrag: 2.306,50 €, zeitraum: 19.12.2025 bis 23.01.2026, behaupteter_grund: Falschangabe 42 Wartetage, rechtsmittel: Widerspruch eingelegt am 06.08.2026 (siehe 9650219328069.pdf), datum: 07.07.2026
- **Auszug:** `--- Seite 1 ---
Rückforderung Krankengeld
 
 
 
Guten Tag Frau Steinhauer, 
 
mit Datum vom 29.12.2025 und 23.01.2026 haben wir Ihnen für die Zeit vom 19.12.2025 bis 
zum 23.01.2026 Krankengeld überwiesen.  
 
Nach unseren Berechnungen haben Sie insgesamt 2.306,50 € Krankengeld zu viel erhalten. 
Durch die Falschangabe der Wartetage von 42 Tagen, haben wir Ihr Krankengeld überzahlt.  
Daher möchten wir freundlich um Rückzahlung des überzahlten Betrages bitten. 
 
Wir bitten um Überweisung des Betrages innerhalb der nächsten 14 Tage auf das unten 
angegebene Konto. Geben Sie bitte bei der Überw`

## 44. Eingangsbestätigung: Widerspruch vom 06.08.2026 gegen die Krankengeld-Rückforderung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650219328069.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2026-08-07
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** WICHTIGER BEWEIS: BKK firmus bestätigt den fristgerechten Eingang von Alinas Widerspruch vom 06.08.2026 gegen den Rückforderungsbescheid über 2.306,50 €. Die BKK kündigt eine erneute Überprüfung durch den Medizinischen Dienst an. Aufschiebende Wirkung beachten!
- **Eckdaten:** widerspruch_datum: 06.08.2026, gegen_rueckforderung: 2.306,50 € (Bescheid vom 07.07.2026), status: In Prüfung bei BKK/MD, datum: 07.08.2026
- **Auszug:** `--- Seite 1 ---
Ihr Widerspruch vom 06.08.2026
 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen.      
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer`

## 45. ABHILFEBESCHEID: Barthaarentfernung - Ihr Widerspruch vom 19.10.2025 (Volle Bewilligung bis 31.08.2026)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/9650222505257.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2026-08-20
- **Zweck:** Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.
- **Zusammenfassung:** GROSSER ERFOLG / ABHILFEBESCHEID: Die BKK firmus hilft dem Widerspruch vom 19.10.2025 gegen den Ablehnungsbescheid vom 09.10.2025 VOLLSTÄNDIG AB! Die Kosten für die Barthaarentfernung (Nadelepilation / Laser) werden antragsgemäß bis zum 31.08.2026 übernommen!
- **Eckdaten:** bescheid_art: Abhilfebescheid (Volle Stattgabe), leistung: Barthaarentfernung / Epilation, bewilligt_bis: 31.08.2026, aufgehobener_bescheid: 09.10.2025, widerspruch_datum: 19.10.2025
- **Auszug:** `--- Seite 1 ---
Abhilfebescheid - Ihr Widerspruch vom 19.10.2025
 
 
Guten Tag Frau Steinhauer, 
 
am 19.10.2025 haben Sie Widerspruch gegen den Ablehnungsbescheid vom 09.10.2025 
eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Nach erneuter Prüfung der vorliegenden Unterlagen über mehrere Instanzen sehen wir 
von der Vorlage vor dem Widerspruchsausschuss ab und erstatten Ihnen den 
fehlenden Betrag in Höh`

## 46. Vollständiges Chatprotokoll: Digitaler Briefkasten Chatverlauf
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Digitaler Briefkasten Chatverlauf.txt`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2024-01-23
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Digitaler Briefkasten Chatverlauf.
- **Auszug:** `Icon für Absender
Antrag

9650060300010.pdf (299 KB)
23.01.2024, 14:56:05
Icon für Absender
Antrag

9650061225545.pdf (544 KB)
05.02.2024, 09:31:48
Icon für Absender
Antrag

9650062101659.pdf (299 KB)
19.02.2024, 16:00:44
Icon für Absender
Antrag

9650062820219.pdf (299 KB)
28.02.2024, 07:17:27
Icon für Absender
Antrag

9650064287652.pdf (549 KB)
22.03.2024, 11:19:51
Icon für Absender
Antrag

9650067808155.pdf (298 KB)
31.05.2024, 13:02:40
Icon für Absender
Digitaler Posteingang

9650071371588.pdf (230 KB)
15.08.2024, 07:16:22
Icon für Absender
Antrag

9650071568085.pdf (317 KB)
15.08.2024, 10`

## 47. Zahlungsbeleg / Überweisungsnachweis
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/1723563105659.jpg`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2024-08-13
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Bescheide & Rechtsmittel.
- **Zusammenfassung:** Nachweis über getätigte Behandlungszahlung zur Kostenerstattung.
- **Auszug:** `[Bilddatei: 1723563105659.jpg - Zahlungsbeleg / Überweisungsnachweis. Zusammenfassung: Nachweis über getätigte Behandlungszahlung zur Kostenerstattung.]`

## 48. Ärztliche Widerspruchsbegründung zur Kostenübernahme der Barthaarentfernung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/AErztlicheWiderspruchsbegruendung_Arzt.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2024-02-15
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Facharzt-Stellungnahme zur medizinischen Notwendigkeit der dauerhaften Epilation/Barthaarentfernung bei bestehender Diagnose und starkem Leidensdruck.

## 49. ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/AErztlicheWiderspruchsbegruendung_Therapeutin.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 1993-09-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG Absender (Praxis / Therapeut*in): Frau Dipl.-Psych. Jill Tegge
- **Auszug:** `--- Seite 1 ---
ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG 
 
Absender (Praxis / Therapeut*in): 
Frau Dipl.-Psych. Jill Tegge 
Jasperallee 1 c, 38102 Braunschweig 
05 31 / 23 44 51 19 
An die 
BKK firmus 
Servicezentrum Bremen 
Gottlieb-Daimler-Str. 11 
28237 Bremen 
 
Betreff: Ärztliche Widerspruchsbegründung zur Kostenübernahme der medizinisch 
notwendigen Bart-Epilation 
Versicherte: Alina Steinhauer 
Geburtsdatum: 01.09.1993 
Versichertennummer: O603571189 
Datum: 05.11.2025 
 
Sehr geehrte Damen und Herren, 
hiermit bestätige ich als behandelnde Fachperson im Rahmen der geschlechtsangleichenden 
Be`

## 50. Zusammenstellung aller Rechnungsbelege und Quittungen für Kostenerstattung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/AlleBelege.pdf`
- **Kategorie:** Bescheide & Rechtsmittel | **Datum:** 2024-06-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Bescheide & Rechtsmittel.
- **Zusammenfassung:** Sammelbeleg aller verauslagten Rechnungen (Psychotherapie, Medikamente, Behandlungskosten) zur Geltendmachung nach § 13 Abs. 3 SGB V.

## 51. Allianz Private Krankenversicherung Kostenübernahmezusage
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Allianz_Zusage.jpg`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-09-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Vergleichsnachweis über bewilligte Epilations- und Therapiemaßnahmen.
- **Auszug:** `[Bilddatei: Allianz_Zusage.jpg - Allianz Private Krankenversicherung Kostenübernahmezusage. Zusammenfassung: Vergleichsnachweis über bewilligte Epilations- und Therapiemaßnahmen.]`

## 52. AOK Vergleichsbescheid / Zusage Kostenübernahme Barthaarentfernung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/AOK_Zusage.jpg`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-08-10
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Vergleichsfall / Bewilligungsbescheid der AOK über die Kostenübernahme für ambulante Laserepilation als Referenzbeleg für Systemversagen.
- **Auszug:** `[Bilddatei: AOK_Zusage.jpg - AOK Vergleichsbescheid / Zusage Kostenübernahme Barthaarentfernung. Zusammenfassung: Vergleichsfall / Bewilligungsbescheid der AOK über die Kostenübernahme für ambulante Laserepilation als Referenzbeleg für Systemversagen.]`

## 53. Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung mein
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/AufrechterhaltungmeinesWiderspruchsundNachforderung.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2025-11-19
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung meines Widerspruchs gegen den Bescheid vom 09.10.2025 2. Nachforderung der noch ausstehenden Erstattung (600,00 EUR) nach § 13 Abs. 3 SGB V
- **Auszug:** `--- Seite 1 ---
Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung meines 
Widerspruchs gegen den Bescheid vom 09.10.2025 2. Nachforderung der noch 
ausstehenden Erstattung (600,00 EUR) nach § 13 Abs. 3 SGB V 
 
Versicherte: Alina Steinhauer, Vers. Nr: O603571189 
Sehr geehrte Damen und Herren, 
ich beziehe mich auf Ihr Schreiben vom 19.11.2025 und den Zahlungseingang von 1.400,00 EUR, 
den ich als Teilanerkenntnis verbuche. 
In Ihrer Abrechnung haben Sie lediglich 7 der insgesamt 10 eingereichten Behandlungstermine 
erstattet (Zeitraum 13.02.2024 bis 09.12.2024). Es f`

## 54. Barthaarentferung – Alina Steinhauer
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Barthaarentfernung_Verlauf.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-01-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Barthaarentferung – Alina Steinhauer 01.01.2024 – 10.06.2025 Ich schreite voran wie man hier sehen kann
- **Auszug:** `--- Seite 1 ---
Barthaarentferung – Alina Steinhauer 
01.01.2024 – 10.06.2025 
 
 
 
 
 
Ich schreite voran wie man hier sehen kann`

## 55. BKK Gesundheit Bewilligung Kostenübernahme
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/BKKGesundheit_Zusage.jpg`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-09-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Vergleichszusage einer anderen Betriebskrankenkasse für geschlechtsangleichende Epilationsbehandlung.
- **Auszug:** `[Bilddatei: BKKGesundheit_Zusage.jpg - BKK Gesundheit Bewilligung Kostenübernahme. Zusammenfassung: Vergleichszusage einer anderen Betriebskrankenkasse für geschlechtsangleichende Epilationsbehandlung.]`

## 56. Dr. med Patrick Sander
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/DermatologischerBefundbericht_Dr.P.Sander.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-04-18
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Dr. med Patrick Sander Goethestraße 4 - 8 Frankfurt, den 18.04.2024
- **Auszug:** `--- Seite 1 ---
Dr. med Patrick Sander 
Goethestraße 4 - 8 
60313 Frankfurt 
 
 
Frankfurt, den 18.04.2024 
 
Dermatologischer Bericht über Nick Steinhauer, geb. am 01.09.1993, wohnhaft in 
Carl-Goerdeler-Ring 26, 38518 Gifhorn: 
 
 
Die Geschlechtsdysphorie besteht bereits das gesamte Leben. Durch die tägliche 
Bartrasur besteht ein erhöhtes Risiko für die Entwicklung einer irritativen, schmerzhaften 
Dermatitis sowie bakteriellen Superinfektionsgefahr.  
 
Des Weiteren haben die Bartstoppeln, Gesichtsbehaarung sowie der Bartschatten einen 
optisch störenden Effekt insb. nach Auftragen der Ge`

## 57. Endokrinologischer Befundbericht zur Hormonsubstitution
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Endo_Befund_Bericht.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2024-04-10
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.
- **Zusammenfassung:** Befund und Laborwerte des Endokrinologikums zur Einleitung und Überwachung der Hormonersatztherapie (HRT).

## 58. Vollständiges Chatprotokoll: Erstattungen Chatverlauf
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Erstattungen Chatverlauf.txt`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-02-04
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Vollständiger BKK Firmus Chatverlauf zu Erstattungen Chatverlauf.
- **Auszug:** `Einkauf lefax Pump liquid 50ml ppl für unseren Sohn Noah.

Bauchschmerzen und Blähungen

NachrichtAnhang1.jpg (886 KB)
04.02.2023, 17:54:53
Icon für Absender

vielen Dank für Ihren Antrag auf Kostenübernahme für das Arzneimittel.
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise um ein nicht verschreibungspflichtiges Arzneimittel handelt und daher die Kosten nicht von der Gesetzlichen Krankenversicherung übernommen werden können.
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über die Verordnung von Arzneimitteln in der vertragsärztlichen V`

## 59. Fotodokumentation Barthaarwuchs / Epilationsbefund
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/FotodokumentationBart.png`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-02-10
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Medizinische Bilddokumentation des dichten Barthaarwuchses zur Einreichung beim Medizinischen Dienst für die Epilations-Bewilligung.
- **Auszug:** `[Bilddatei: FotodokumentationBart.png - Fotodokumentation Barthaarwuchs / Epilationsbefund. Zusammenfassung: Medizinische Bilddokumentation des dichten Barthaarwuchses zur Einreichung beim Medizinischen Dienst für die Epilations-Bewilligung.]`

## 60. GEK / Barmer Zusage Kostenübernahme
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/GEK_Zusage.jpg`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2023-09-20
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Referenzbescheid über Kostenübernahme bei Transsexualismus / Epilation.
- **Auszug:** `[Bilddatei: GEK_Zusage.jpg - GEK / Barmer Zusage Kostenübernahme. Zusammenfassung: Referenzbescheid über Kostenübernahme bei Transsexualismus / Epilation.]`

## 61. Hausärztliche Anfrage und Begründung zur Kostenübernahme
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Hausarzt_Anfrage_an_KK_Kostenuebernahme.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2023-11-15
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.
- **Zusammenfassung:** Schreiben der Hausarztpraxis an die BKK firmus zur Unterstützung des Antrags auf Kostenübernahme.

## 62. Antrag auf Erstattung von Impfkosten
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Impfkosten.pdf`
- **Kategorie:** Arbeitsunfähigkeit & AU | **Datum:** 2023-03-22
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeitsunfähigkeit & AU.
- **Zusammenfassung:** Antrag auf Erstattung von Impfkosten die BKK firmus ist Marktführer in der Kostenübernahme und Erstattung von Schutzimpfungen. Wir übernehmen zusätzlich zu den Empfehlungen der Ständigen
- **Auszug:** `--- Seite 1 ---
Antrag auf Erstattung von Impfkosten  
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus ist Marktführer in der Kostenübernahme und Erstattung von 
Schutzimpfungen. Wir übernehmen zusätzlich zu den Empfehlungen der Ständigen 
Impfkommission (STIKO) alle ärztlich verordneten Schutzimpfungen gegen übertragbare 
Krankheiten, wenn der Impfstoff nach arzneimittelrechtlichen Vorschriften zugelassen ist und 
alle vorgesehenen Voraussetzungen erfüllt sind. 
 
Wir haben Ihre Anfrage für die Erstattung der Impfkosten erhalten. Damit wir Ihnen die 
verauslagten Kosten erstatten können, s`

## 63. 08.01.2024
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Indikationsschreiben_Psychotherapeutin.pdf`
- **Kategorie:** Psychotherapie & Kostenerstattung | **Datum:** 2024-01-08
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Psychotherapie & Kostenerstattung.
- **Zusammenfassung:** PSYCHOTHERAPEUTISCHE STELLUNGNAHME ZUR GESCHLECHTSANGLEICHENDEN HOR- MONERSATZTHERAPIE VON FRAU STEINHAUER Alina (Vorname im Personalausweis: Nick) Steinhauer stellte sich bei mir in psychothe-
- **Auszug:** `--- Seite 1 ---
08.01.2024 
PSYCHOTHERAPEUTISCHE STELLUNGNAHME ZUR GESCHLECHTSANGLEICHENDEN HOR-
MONERSATZTHERAPIE VON FRAU STEINHAUER 
	
	
	
	
	
	
 
Sehr geehrte Mitarbeitende aus der behandelnden endokrinologischen Praxis, 
Alina (Vorname im Personalausweis: Nick) Steinhauer stellte sich bei mir in psychothe-
rapeutischer Sprechstunde zur Indikationsstellung für die Hormonersatztherapie vor. 
Insgesamt haben 2,5 einzeltherapeutische Sitzungen stattgefunden im Rahmen einer 
Verhaltenstherapie.  
Zudem ist eine Anbindung an eine Transpeergroup seit dem 01.12.2023 ebenfalls ge-
geben. Dort konn`

## 64. Logopädische Praxis
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Kostenvoranschlag28052024.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 2024-05-28
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.
- **Zusammenfassung:** Logopädische Praxis Ganzheitliche Behandlung von Sprach-, Sprech- und Stimmstörungen Maren Schwarz, Logopädie, Bosteled5, 38518 Gifhorn
- **Auszug:** `--- Seite 1 ---
Logopädische Praxis 
Maren Schwarz 
Ganzheitliche Behandlung von Sprach-, Sprech- und Stimmstörungen 
Maren Schwarz, Logopädie, Bosteled5, 38518 Gifhorn 
Nick Steinhauer 
Car] - Goerdeler - Ring 26 
38518 Gifhorn 
Deutschland 
Kostenvoranschlag 
Sehr geehrter Herr Steinhauer, 
Ich danke für Ihre Anfrage und erstellen Ihnen folgendes Angebot gemäß unserem 
Telefonat: 
796,63 € 
Zuzahlung 	
79,02€ 
Mit freundlichen Grüßen 
1\' 
 
Maren Schwarz 
Lcgopädische Praxis 
Maren Schwarz 
3cstark5- 38S8G:farn 
rel: 05371-21 38 546 Fcr 115371-el 36 647 
Maren schwarz 
Staatlich geprüfte Log`

## 65. Lebenslauf - Nick
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Lebenslauf (1).pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 1993-09-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.
- **Zusammenfassung:** Lebenslauf - Nick Geboren 01.09.1993 Kindergartenzeit 1996 – 1999
- **Auszug:** `--- Seite 1 ---
Lebenslauf - Nick 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) Ich habe auf de`

## 66. Lebenslauf – Alina Steinhauer
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Lebenslauf.pdf`
- **Kategorie:** Geschlechtsangleichung & HRT | **Datum:** 1993-09-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.
- **Zusammenfassung:** Lebenslauf – Alina Steinhauer Geboren 01.09.1993 Kindergartenzeit 1996 – 1999
- **Auszug:** `--- Seite 1 ---
Lebenslauf – Alina Steinhauer 
 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) I`

## 67. Sozialmedizinische Begutachtung durch den Medizinischen Dienst (MD Niedersachsen
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/MD_Begutachtung_Transsexualitaet.pdf`
- **Kategorie:** MDK-Begutachtungen & Atteste | **Datum:** 2024-01-20
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich MDK-Begutachtungen & Atteste.
- **Zusammenfassung:** MD-Gutachten zu geschlechtsangleichenden Maßnahmen (Barthaarentfernung / Epilation). Beurteilt Voraussetzungen nach den Begutachtungsrichtlinien Transsexualismus (BGA).

## 68. Kassenbon / Apothekenbeleg Arzneimittel Lefax
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/NachrichtAnhang1.jpg`
- **Kategorie:** Medikamente & Hilfsmittel | **Datum:** 2023-02-04
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Medikamente & Hilfsmittel.
- **Zusammenfassung:** Beleg über den Kauf von Lefax Pump-Liquid für Sohn Noah zur Erstattung.
- **Auszug:** `[Bilddatei: NachrichtAnhang1.jpg - Kassenbon / Apothekenbeleg Arzneimittel Lefax. Zusammenfassung: Beleg über den Kauf von Lefax Pump-Liquid für Sohn Noah zur Erstattung.]`

## 69. Behandlungsvertrag Hairfree Institut für dauerhafte Haarentfernung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Vertrag_Hairfreevom14.01.2024.pdf`
- **Kategorie:** Barthaarentfernung & Epilation | **Datum:** 2024-01-14
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.
- **Zusammenfassung:** Dienstleistungsvertrag und Kostenvoranschlag für die Laser-/Lichtepilationsbehandlung der Barthaare vom 14.01.2024.

## 70. Alina Steinhauer
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Widerspruch.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-01-22
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Alina Steinhauer Carl-Goerdeler-Ring 26 Betreff: Aufrechterhaltung meines Widerspruchs bzgl. Ihres Schreibens vom 20.01.2026
- **Auszug:** `--- Seite 1 ---
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
 
22.01.2026 
Betreff: Aufrechterhaltung meines Widerspruchs bzgl. Ihres Schreibens vom 20.01.2026 
 
Sehr geehrte Damen und Herren, 
hiermit nehme ich Bezug auf Ihr Schreiben vom 20.01.2026 und Teile Ihnen mit, dass ich meinen 
Widerspruch vollumfänglich aufrechterhalte. 
Ihre Argumentation, eine Kostenübernahme sei aufgrund der "geltenden Rechtslage" bei nicht-
ärztlichen Leistungserbringern grundsätzlich ausgeschlossen, ist im vorliegenden Einzelfall 
rechtlich unzutreffend. Ich begründe dies wie folgt: 
1. Nachgewiese`

## 71. Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/WiderspruchgegendieAblehnungvom09.10.2025.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2025-10-09
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der Barthaarentfernung vielen Dank für Ihr Schreiben vom 09.10.2025 zu meinem Antrag auf Kostenübernahme für die
- **Auszug:** `--- Seite 1 ---
Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der 
Barthaarentfernung 
 
Sehr geehrte Damen und Herren, 
vielen Dank für Ihr Schreiben vom 09.10.2025 zu meinem Antrag auf Kostenübernahme für die 
Barthaarentfernung. 
Mit großem Interesse habe ich zur Kenntnis genommen, dass der Medizinische Dienst (MD) in 
seiner Prüfung die grundsätzliche medizinische Indikation für eine Epilation in meinem Fall 
bestätigt hat. 
Ihrer darauffolgenden Ablehnung, die sich auf ein Urteil des Bundessozialgerichts (BSG) vom 
17.12.2020 stützt, muss ich jedoch entschiede`

## 72. Vorladung / Information zur Sitzung des Widerspruchsausschusses
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Widerspruchsauschuss.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2025-04-15
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Schreiben der BKK firmus bezüglich der Vorlage des Widerspruchs beim Widerspruchsausschuss.

## 73. Entscheidung / Protokollauszug des Widerspruchsausschusses April
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/WiderspruchsauschussApril.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2025-04-20
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Beratungsergebnis des Widerspruchsausschusses über die erhobenen Widersprüche.

## 74. Letzte Zahlungsaufforderung und Fristsetzung zur Restzahlung
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankenkasse/Widerspruch_LetzteAufforderungd.Restzahlung.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2025-11-20
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Nachdrückliche Zahlungsaufforderung mit Fristsetzung bezüglich noch ausstehender Erstattungsbeträge / Krankengeld.

## 75. Dokumenten-Begleitschein / Lieferschein für Postsendung an BKK firmus (Einschreiben)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/01_Lieferschein_BKK_firmus_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Nachweis über die fristgerechte und vollständige Einreichung von Antrags- und Widerspruchsunterlagen.
- **Zusammenfassung:** Offizieller 1-seitiger Dokumenten-Begleitschein / Lieferschein mit tiefergesetztem Fensterbereich für den Postumschlag. Weist Pos. 1 (Widerspruch) und Pos. 2 (BKK-Originalformular nebst Anlage) aus. Beinhaltet den ausdrücklichen Korrekturhinweis, dass diese Neufassung die frühere fehlerhafte App-Übermittlung vollumfänglich ersetzt. Frist: 08.10.2026.
- **Auszug:** `Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -62791437`

## 76. Formeller Widerspruch gegen Ablehnungsbescheid Krankengeld (Ref: 9650226941682)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/02_Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Umfassender 2-seitiger Widerspruch gegen den Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682). Geltendmachung des Krankengeldes für die geschlechtsangleichende Großoperation ab 22.06.2026 (Tagessatz: 65,90 EUR brutto). Rüge des Berechnungsirrtums der BKK (18.12.2025 vs. 29.01.2026), Einrede des Rechtsmissbrauchs nach § 242 BGB wegen verbindlicher OP-Kostenzusage bis 30.06.2026, Nachweis eines neuen somatischen Falls (§ 48 Abs. 2 SGB V). Fristsetzung bis 08.10.2026, Androhung SG-Eilantrag (§ 86b Abs. 2 SGG) und BAS-Aufsichtsbeschwerde.
- **Auszug:** `Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Versorgungsmanagement Geldleistungen / Widerspruchsstelle
Gottlieb-Daimler-Straße 11, 28237 Bremen (Postanschrift: 28192
Bremen)
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rücksche`

## 77. Anlage & Ergänzende Erläuterungen zum Formular Klärung Versicherungsverhältnis (BKK firmus)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/03_Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-10-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** 1-seitiges offizielles Erläuterungsblatt zu den Ziffern 1 bis 6 des handschriftlich ausgefüllten BKK-Originalformulars (Az. -6279143758668390890). Begründet das gesetzliche Fortbestehen der beitragsfreien Pflichtmitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V aufgrund des Krankengeldanspruchs ab 03.08.2026 und weist eine freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V ausdrücklich zurück.
- **Auszug:** `Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum:
 01.09.1993 · Versichertennummer: O603571189
An die BKK firmus  · 28192 Bremen
Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis`

## 78. Ärztliches Attest & sozialmedizinische Stellungnahme (Hausarztpraxis Dorothea Jung, Leiferde)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/04_Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Medizinisches Facharztattest zur Vorlage bei Krankenkasse/Medizinischem Dienst zum Nachweis der Behandlungsnotwendigkeit und Arbeitsunfähigkeit.
- **Zusammenfassung:** 1-seitige sozialmedizinische Stellungnahme zur Vorlage und Unterzeichnung bei Hausärztin Dorothea Jung (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) am Montag, 05.10.2026. Bescheinigt die rein somatische Genese der AU infolge der GA-Großoperation vom 23.06.2026 und hebt die Aufhebung des Leistungsvermögens für weitere 6–8 Wochen hervor.
- **Auszug:** `GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND S`

## 79. Ablehnungsbescheid Krankengeld BKK firmus vom 01.10.2026 (Ref: 9650226941682)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/05_Bescheid_BKK_firmus_Krankengeld_01-10-2026.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.
- **Zusammenfassung:** Schriftlicher Ablehnungsbescheid der BKK firmus vom 01.10.2026 bezüglich der Arbeitsunfähigkeit ab 22.06.2026. Behauptet fälschlich Anspruchsende zum 18.12.2025 und Ursachenzusammenhang mit früherer Aussteuerung.
- **Auszug:** `Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgesc`

## 80. Ablehnungsbescheid Krankengeld BKK firmus vom 01.10.2026 (Ref: 9650226941682)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/9650226941682.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-10-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Schriftlicher Ablehnungsbescheid der BKK firmus vom 01.10.2026 bezüglich der Arbeitsunfähigkeit ab 22.06.2026. Behauptet fälschlich Anspruchsende zum 18.12.2025 und Ursachenzusammenhang mit früherer Aussteuerung.
- **Auszug:** `Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgesc`

## 81. Ärztliches Attest & sozialmedizinische Stellungnahme (Hausarztpraxis Dorothea Jung, Leiferde)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Medizinisches Facharztattest zur Vorlage bei Krankenkasse/Medizinischem Dienst zum Nachweis der Behandlungsnotwendigkeit und Arbeitsunfähigkeit.
- **Zusammenfassung:** 1-seitige sozialmedizinische Stellungnahme zur Vorlage und Unterzeichnung bei Hausärztin Dorothea Jung (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) am Montag, 05.10.2026. Bescheinigt die rein somatische Genese der AU infolge der GA-Großoperation vom 23.06.2026 und hebt die Aufhebung des Leistungsvermögens für weitere 6–8 Wochen hervor.
- **Auszug:** `GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND S`

## 82. Anlage & Ergänzende Erläuterungen zum Formular Klärung Versicherungsverhältnis (BKK firmus)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-10-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** 1-seitiges offizielles Erläuterungsblatt als Anlage zum Originalformular \'Klärung Versicherungsverhältnis\' der BKK firmus (Az. -6279143758668390890). Erläutert die Punkte 1 bis 6 und begründet die beitragsfreie Mitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V.
- **Auszug:** `Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum: 01.09.1993 · Versichertennummer: O603571189
 An die BKK firmus · 28192 Bremen
 Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis`

## 83. Chronologische Gesamttimeline: OP-Verzögerung, Aussteuerung & Krankengeldstreit (Alina Steinhauer)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Chronologische_Timeline_OP_Krankengeld_Alina_Steinhauer.md`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-10-05
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Vollständige, lückenlose chronologische Gesamttimeline von der OP-Beantragung Ende 2024 über die 7-monatige Hinhaltetaktik und Erst-Ablehnung der BKK firmus, die verbindliche Kostenzusage bis 30.06.2026, die Aussteuerung zum 29.01.2026, die OP am 23.06.2026 in den Dr. Lubos Kliniken, das Ende der 6-wöchigen Leistungsfortzahlung am 02.08.2026 bis zum aktuellen Ablehnungsbescheid vom 01.10.2026 und dem Eklat mit Dr. Schmidt am 05.10.2026.

### Volltext / Inhalt
# Chronologische Gesamttimeline: OP-Verzögerung, Aussteuerung & Krankengeldstreit
**Versicherte:** Alina Steinhauer (geb. 01.09.1993, Vers.-Nr.: O603571189)  
**Krankenkasse:** BKK firmus (Ref: 9650226941682 / Az: -6279143758668390890)  
**Bundesagentur für Arbeit:** Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534)  
**Stand:** 05.10.2026  

---

### Chronologischer Ablauf (von der OP-Beantragung bis zum aktuellen Eklat)

| Datum | Ereignis, Bescheid & rechtliche Bedeutung | Akten-/Dokument-Referenz |
| :--- | :--- | :--- |
| **13.12.2024 / 16.12.2024** | **Antrag auf geschlechtsangleichende Operation (GA-OP):** Alina beantragt die Kostenübernahme bei der BKK firmus. Die Kasse fordert zunächst zahlreiche Nachweise an und verzögert die Vorlage beim Medizinischen Dienst (MD) über ein halbes Jahr. | Dok. `9650078742642.pdf` |
| **19.06.2025** | **Verzögerte Weiterleitung an MD:** Erst über 6 Monate nach Antragstellung leitet die BKK firmus die OP-Unterlagen an den Medizinischen Dienst weiter. | Dok. `9650111610893.pdf` |
| **07.07.2025** | **Rechtswidrige Ablehnung der OP:** Die BKK firmus lehnt die Kostenübernahme für die geschlechtsangleichende Operation ab. | Ablehnungsbescheid vom 07.07.2025 |
| **09.07. / 10.07.2025** | **Sofortiger Widerspruch:** Alina legt energisch Widerspruch gegen die OP-Ablehnung ein und weist die Fehler des MD nach. | Widerspruchsschreiben |
| **15.07.2025** | **Einlenken der Kasse / Abhilfebescheid:** Die BKK firmus hebt die Ablehnung auf und bewilligt die GA-OP (Penisentfernung, Hodenentfernung, Neovagina) befristet bis 15.01.2026. **Kausalität der Verzögerung:** Durch das 7-monatige Zögern und die Erst-Ablehnung der Kasse war an den Dr. Lubos Kliniken München (Wartezeiten ~12 Monate) ein OP-Termin vor Ablauf der Frist nicht mehr zu bekommen. Der frühestmögliche Termin wurde auf den 23.06.2026 datiert. | Abhilfebescheid Dok. `9650114306636.pdf` |
| **10.09.2025** | **Antrag auf Verlängerung der Kostenzusage:** Alina beantragt Fristverlängerung wegen des OP-Termins am 23.06.2026. | Antrag Dok. `9650119701902.pdf` |
| **01.10.2025** | **Verlängerung der Kostenzusage bis 30.06.2026:** Die BKK firmus verlängert die Bewilligung verbindlich bis zum 30.06.2026 – in voller Kenntnis des Termins 23.06.2026! *(Begründet Bindungswirkung nach § 242 BGB).* | Bewilligungsbescheid Dok. `9650122075850.pdf` |
| **03.11.2025** | **Aussteuerungsbescheid der BKK firmus:** Nur einen Monat nach Verlängerung teilt die BKK firmus verbindlich mit, dass der Anspruch auf Krankengeld nach 78 Wochen innerhalb der Rahmenfrist (18.06.2024–17.06.2027) mit Ablauf des **29.01.2026** endet. Aufforderung zur Meldung bei der Arbeitsagentur (Nahtlosigkeit § 145 SGB III). | Aussteuerungsbescheid Dok. `9650125178105.pdf` |
| **30.01.2026** | **Beginn Bezug von Arbeitslosengeld (ALG 1):** Bewilligung durch Agentur für Arbeit Gifhorn (Tagessatz: 60,21 € brutto). | Bescheid vom 25.02.2026 (Kd.-Nr. 241D129534) |
| **22.06.2026** | **Stationäre Klinikaufnahme:** Aufnahme in den MVZ Dr. Lubos Kliniken München-Bogenhausen. Beginn der operationsbedingten Arbeitsunfähigkeit. | Klinik-Bescheinigung Dok. `krankmeldung1.pdf` |
| **23.06.2026** | **Erfolgreiche Durchführung der GA-Großoperation:** Durchführung der bewilligten geschlechtsangleichenden Operation. | Dr. Lubos Kliniken München |
| **22.06. – 02.08.2026** | **6 Wochen Leistungsfortzahlung der Arbeitsagentur (§ 146 SGB III):** Die Agentur für Arbeit zahlt das ALG 1 für exakt 42 Kalendertage fort. Letzte Zahlung von 120,42 € am 25.08.2026 für den 01.08.–02.08.2026. | Banknachweis & BA-Aufhebungsbescheid 20.08.2026 |
| **03.08.2026** | **Gesetzlicher Übergang in das Krankengeld (§ 47b SGB V):** Unmittelbarer Anspruch auf Krankengeld gegen die BKK firmus in Höhe des bisherigen ALG 1 (65,90 €/Tag brutto). Fortbestehen der beitragsfreien Mitgliedschaft gem. § 192 Abs. 1 Nr. 2 SGB V. | Gesetzlicher Anspruch (§ 44 / § 47b SGB V) |
| **05.08.2026** | **Krankengeldantrag per BKK-App:** Alina beantragt formell Krankengeld im Anschluss an die Klinik und nahtlose Hausarzt-Krankschreibung. | BKK-App Chatverlauf |
| **02.09.2026** | **Antrag auf Leistungskorrektur bei der Arbeitsagentur:** Beantragung von 361,26 € für die 6 Tage 24.01.–29.01.2026 (Nahtlosigkeit). | Dok. `2026-09-02_BA_Nachricht...` |
| **01.10.2026** | **Rechtswidriger Ablehnungsbescheid Krankengeld der BKK firmus:** Kasse behauptet fälschlich Anspruchsende 18.12.2025 (Widerspruch zum eigenen Bescheid vom 03.11.2025!) und verweigert Krankengeld unter Berufung auf angebliche Aussteuerungsursache. | Ablehnungsbescheid Ref: `9650226941682` |
| **01.10.2026** | **Digitaler Widerspruch per BKK-App:** Fristwahrende Einreichung des 2-seitigen begründeten Widerspruchs mit Fristsetzung bis 08.10.2026. | BKK-App Übermittlung |
| **02.10.2026** | **Postalischer Versand per Einwurf-Einschreiben:** Versand von 1. Lieferschein, 2. Widerspruch (Original), 3. BKK-Originalformular \'Klärung Versicherungsverhältnis\' und 4. Erläuterungsblatt (Anlage Ziffern 1–6 mit § 192 SGB V) bei der Post in 38536 Meinersen (10:59 Uhr). | Sendungsnummer: **RT 6137 9485 8DE** (Dok. ID 114) |
| **05.10.2026** | **Praxisbesuch & Eklat mit Dr. med. Christian Schmidt:**<br>1. Dr. Schmidt bestätigt schriftlich: Zweifel an Krankheitszusammenhängen erfordern zwingend eine offizielle **Kassen-Arztanfrage**.<br>2. Dr. Schmidt ruft um **10:30 Uhr persönlich bei der BKK firmus** an, um den Sachverhalt ärztlich aufzuklären. Die BKK firmus **weist den Hausarzt am Telefon ab** unter dem Vorwand eines angeblich \'ungeklärten Versicherungsverhältnisses\'! | Dok. `09_Nachweis_Aerztliche_Stellungnahme_Dr_Schmidt_Arztanfrage.png` |
| **05.10.2026** | **Antrag an Agentur für Arbeit Gifhorn:** Kurzantrag auf vorläufige Weiterzahlung von ALG 1 gem. § 43 SGB I / § 328 SGB III fertiggestellt. Vollständige Integration aller BA-Vorgänge in Funkiras Wissensdatenbank. | Dok. `Kurzantrag_Arbeitsagentur...` |
| **08.10.2026** | **Ablauf Zahlungsfrist Krankengeld:** Bei Nichtzahlung: Am 09.10.2026 Einreichung Eilantrag beim Sozialgericht (§ 86b Abs. 2 SGG) und Fachaufsichtsbeschwerde beim BAS. | Fristüberwachung aktiv |


## 84. Fragebogen Klärung Versicherungsverhältnis BKK firmus (Ref: -6279143758668390890) – Rechtssicher ausgefüllt
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Klaerung_Versicherungsverhaeltnis_BKK_firmus_Alina_Steinhauer.pdf`
- **Kategorie:** Krankengeld & Entgeltersatz | **Datum:** 2026-10-01
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.
- **Zusammenfassung:** Offizieller Fragebogen \'Klärung Versicherungsverhältnis\' der BKK firmus (Az. -6279143758668390890 zum 02.08.2026), digital und rechtssicher ausgefüllt. Enthält den Nachweis der beitragsfreien Pflichtversicherung nach § 192 Abs. 1 Nr. 2 SGB V aufgrund fortbestehenden Krankengeldanspruchs.
- **Auszug:** `BKK firmus · Postfach 28192 Bremen · (Ref-Code: 0000 p08e/ DLGSVV0263945602_1_30 // 680823 12749 20406)
Klärung Versicherungsverhältnis
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890
Versicherte: Alina Steinhauer, geb. 01.09.1993 · Vers.-Nr.: O603571189
1. Familienstand
[`

## 85. Dokumenten-Begleitschein / Inhaltsverzeichnis Postsendung BKK firmus Widerspruch Krankengeld
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Lieferschein_BKK_firmus_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Nachweis über die fristgerechte und vollständige Einreichung von Antrags- und Widerspruchsunterlagen.
- **Zusammenfassung:** Offizieller 1-seitiger Dokumenten-Begleitschein / Lieferschein für die postalische Übersendung des Widerspruchs per Einschreiben an die BKK firmus Bremen.
- **Auszug:** `Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Geldleistungen
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer
 Geburtsdatum: 01.09.1993
 Vers.-Nr.: O603571189
Ihr Bescheid: 9650226941682
Versandart: Einschreiben / Vo`

## 86. Dokumenten-Begleitschein / Lieferschein für Postsendung Widerspruch BKK firmus (nur Widerspruch)
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Lieferschein_Widerspruch_BKK_firmus_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Dokumenten-Begleitschein / Lieferschein für Postsendung an BKK firmus. Beinhaltet: 1. Widerspruch Krankengeld (2 Seiten) und 2. Neufassung Klärung Versicherungsverhältnis (1 Seite) mit ausdrücklichem Korrekturhinweis zur Ersetzung der fehlerhaften Erstübermittlung.
- **Auszug:** `Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -62791437`

## 87. Textfassung Widerspruch: Krankengeld für GA-OP ab 22.06.2026
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Widerspruch_BKK_firmus_Krankengeld_Alina_Steinhauer.txt`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Reine Textfassung des 2-seitigen Widerspruchs gegen die Krankengeldablehnung ab 22.06.2026 zur unkomplizierten Übernahme in die BKK-firmus-App.
- **Auszug:** `Alina Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
Geburtsdatum: 01.09.1993
Versichertennummer: O603571189

An die
BKK firmus
Körperschaft des öffentlichen Rechts
- Widerspruchsstelle / Versorgungsmanagement Krankengeld -
Gottlieb-Daimler-Straße 11
28237 Bremen

(Vorab per BKK-firmus-App / Fax an`

## 88. Widerspruch: Krankengeld für geschlechtsangleichende Operation ab 22.06.2026
- **Dateipfad:** `agenten/workspace/Gesundheit/Krankengeld/Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Fokussierter 2-seitiger Widerspruch gegen die Verweigerung von Krankengeld ab 22.06.2026. Begründung: Bindungswirkung der Kassen-Kostenzusage bis 30.06.2026 (Treu und Glauben, § 242 BGB), neuer somatischer Versicherungsfall (Z98.8, T81.8, R52.2), lückenlose AU und Fristsetzung bis 08.10.2026 unter Androhung eines Eilantrags nach § 86b Abs. 2 SGG.
- **Auszug:** `Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Widerspruchsstelle / Versorgungsmanagement Krankengeld
Gottlieb-Daimler-Straße 11
28237 Bremen
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rückschein
Datum: 01.10.2026
Versicherte:`

## 89. Nachweisfoto Briefinhalt 1: Lieferschein und Widerspruch (unterzeichnet)
- **Dateipfad:** `agenten/workspace/Gesundheit/Nachweise/06_Nachweis_Foto_Briefinhalt_1_Lieferschein_Widerspruch.jpeg`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).
- **Zusammenfassung:** Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig unterzeichneten Dokumente: 1. Lieferschein / Begleitschein und 2. Formeller Widerspruch (Seiten 1 und 2) vor Kuvertierung für den Postversand per Einschreiben.
- **Auszug:** `Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig unterzeichneten Dokumente: 1. Lieferschein / Begleitschein und 2. Formeller Widerspruch (Seiten 1 und 2) vor Kuvertierung für den Postversand per Einschreiben. [Bildnachweis / Foto der Originalunterschriften und Kuvertierung]`

## 90. Nachweisfoto Briefinhalt 2: BKK-Originalformular und Anlage (unterzeichnet)
- **Dateipfad:** `agenten/workspace/Gesundheit/Nachweise/07_Nachweis_Foto_Briefinhalt_2_Formular_Anlage.jpeg`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-01
- **Zweck:** Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).
- **Zusammenfassung:** Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig ausgefüllten und unterzeichneten Dokumente: 1. Handschriftlich ausgefülltes Original-Formular der BKK firmus \'Klärung Versicherungsverhältnis\' (mit Telefon 01570 1916814) und 2. Offizielles Erläuterungsblatt (Anlage Ziffern 1–6) nebst Widerspruch Seite 2.
- **Auszug:** `Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig ausgefüllten und unterzeichneten Dokumente: 1. Handschriftlich ausgefülltes Original-Formular der BKK firmus \'Klärung Versicherungsverhältnis\' (mit Telefon 01570 1916814) und 2. Offizielles Erläuterungsblatt (Anlage Ziffern 1–6) n`

## 91. Deutsche Post Einlieferungsbeleg Einschreiben Einwurf BKK firmus (Sendungsnummer RT 6137 9485 8DE)
- **Dateipfad:** `agenten/workspace/Gesundheit/Nachweise/08_Nachweis_Foto_Einlieferungsbeleg_Einschreiben_RT613794858DE.jpeg`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-02
- **Zweck:** Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).
- **Zusammenfassung:** Offizieller Einlieferungsbeleg der Deutschen Post AG (Filiale 38536 Meinersen) vom 02.10.2026, 10:59 Uhr über den postalischen Versand der Widerspruchsunterlagen und der Neufassung Klärung Versicherungsverhältnis an die BKK firmus Bremen. Versandart: Einschreiben Einwurf, Sendungsnummer: RT 6137 9485 8DE.
- **Auszug:** `Einlieferungsbeleg
Bitte Beleg gut aufbewahren!
Deutsche Post AG 38536 Meinersen
81047784 6596 02.10.26 10:59
Sendungsnummer: RT 6137 9485 8DE
Einschreiben Einwurf
Information zum Sendungsstatus:
Code bequem mit unserer App scannen
oder Sendungsnummer unter www.deutschepost.de/briefstatus eingeben
K`

## 92. Schriftliche Stellungnahme Dr. med. Christian Schmidt: Verweis auf Krankenkassen-Arztanfrage
- **Dateipfad:** `agenten/workspace/Gesundheit/Nachweise/09_Nachweis_Aerztliche_Stellungnahme_Dr_Schmidt_Arztanfrage.png`
- **Kategorie:** Gesundheit & Sozialrecht (BKK firmus) | **Datum:** 2026-10-05
- **Zweck:** Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).
- **Zusammenfassung:** Schriftliche Bestätigung auf offiziellem Praxisvordruck von Hausarzt Dr. med. Christian Schmidt (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) vom 02.10./05.10.2026. Dr. Schmidt stellt klar: \'Im Falle von Unstimmigkeiten bzgl. der Zusammenhänge von Erkrankungen sollte die Krankenkasse regelkonform bitte hier eine Arztanfrage stellen.\' Am 05.10.2026 um 10:30 Uhr rief Dr. Schmidt persönlich bei der BKK firmus an und wurde von der Kasse mit der Begründung eines \'ungeklärten Versicherungsverhältnisses\' abgewiesen.
- **Auszug:** `Rezeptvordruck / Ärztliche Stellungnahme: Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin, Wilhelm-Busch-Str. 2, 38542 Leiferde, Tel: 05373 1814. Patientin: Alina Steinhauer, geb. 01.09.1993, Carl-Goerdeler-Ring 26, 38518 Gifhorn. Text: Sehr geehrte Frau Steinhauer, vielen Dank für Ihre Ar`',
            ],
            [
                'slug' => 'finanzen-steuern-und-bankumsaetze-2025-2026',
                'title' => 'Finanzen, Steuern & Volksbank eG Kontoauszüge (2025–2026)',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanz-Audit', 'Volksbank eG', 'Buchhaltung', 'Steuern', 'Workspace', 'Dokumente'],
                'content' => '# Finanzen, Steuern & Volksbank eG Kontoauszüge (2025–2026)

Übersicht der Finanz-, Bank- und Steuerdokumente aus `Dokumente/Bank`, `Dokumente/Steuern`, `Dokumente/Finanzen` und `Dokumente/Allgemein` (inkl. Steuererklärung 2025, Gewerbesteuer, Finanzbericht 09/2026 und Volksbank eG Kontoumsätze `DE85269910668583196000`).

## 1. Alina
- **Dateipfad:** `agenten/workspace/Dokumente/Allgemein/alina.jpg`
- **Kategorie:** Allgemein | **Datum:** 2026-09-16
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.
- **Zusammenfassung:** Allgemein: Bilddatei (JPG, 535.0 KB)
- **Auszug:** `Bilddatei (JPG, 535.0 KB)`

## 2. Whatsapp Image 2026-09-17 At 12.38.59
- **Dateipfad:** `agenten/workspace/Dokumente/Allgemein/Erinnerung.jpeg`
- **Kategorie:** Allgemein | **Datum:** 2026-09-17
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.
- **Zusammenfassung:** Allgemein: Bilddatei (JPEG, 152.2 KB)
- **Auszug:** `Bilddatei (JPEG, 152.2 KB)`

## 3. 2026 09 17 Automatische Antwort   Extern  Gewerbesteuer F R 2
- **Dateipfad:** `agenten/workspace/Dokumente/Steuern/2026-09-17_Automatische_Antwort___EXTERN__Gewerbesteuer_f_r_2.md`
- **Kategorie:** Finanzen & Verträge
- **Zweck:** Steuerdokumentation (Steuererklärung / Steuerbescheid) zur Vermögens- und Ertragsübersicht.

### Volltext / Inhalt
# Automatische Antwort: [EXTERN] Gewerbesteuer für 2025 - SteuerNr. 19/143/03250

- **Von:** Finanzamt Gifhorn  Poststelle <poststelle@fa-gf.niedersachsen.de>
- **An:** Alina Steinhauer <alina.stone@t-online.de>
- **Datum:** 2026-09-17 11:11:10
- **Kategorie:** Behörde & Amt
- **Priorität:** normal
- **KI-Zusammenfassung:** Das Finanzamt Gifhorn sendet eine automatische Empfangsbestätigung, die darauf hinweist, dass die ursprüngliche E-Mail nur bei Angabe spezifischer Zuordnungskriterien bearbeitet werden kann. Alina muss prüfen, ob ihre gesendete E-Mail diese Kriterien enthielt.
- **Handlungsbedarf:** Prüfen, ob die ursprüngliche E-Mail an das Finanzamt alle erforderlichen Zuordnungskriterien enthielt.: Ursprüngliche E-Mail an das Finanzamt überprüfen und ggf. mit fehlenden Informationen erneut senden.

---

## Nachrichtentext
Sehr geehrte Dame, sehr geehrter Herr,

das Finanzamt Gifhorn hat Ihre eMail erhalten.

Eine Weiterleitung Ihrer eMail an die zuständige Stelle kann nur erfolgen, wenn die eMail anhand der gemachten Angaben eindeutig identifiziert werden kann.

Mindestens eines der nachstehend genannten vier Zuordnungskriterien muss enthalten sein, ansonsten ist ein Bearbeitung leider nicht möglich:

- Angabe der Steuernummer,

    oder

- der Identifikationsnummer

    oder

- des Aktenzeichens (in Grundbesitzangelegenheiten)

    oder

- des vollständigen Namens mit Anschrift und Geburtsdatum.



Wenn Sie in Ihrer übersandten eMail mindestens eines dieser zuvor genannten Zuordnungskriterien angegeben haben, sind keinen weiteren Angaben erforderlich.

Sofern Sie keines dieser vier Zuordnungskriterien angegeben haben, senden Sie uns bitte ggf. eine gleichlautende eMail unter Ergänzung der erforderlichen Angaben; Ihre bisherige eMail kann leider nicht weiterbearbeitet werden und wird gelöscht.

Darüber hinaus wird stets um Angabe des konkreten Betreffs gebeten

(z.B. Einkommensteuererklärung 2024, Einkommensteuerbescheid 2024 vom …,

Grundsteuererklärung oder Rückfrage vom ...).

Mit freundlichen Grüßen

Ihr Finanzamt Gifhorn


## 4. 2026 09 02 Aw  Mahnung   Konto  17020 09
- **Dateipfad:** `agenten/workspace/Dokumente/Finanzen/2026-09-02_AW__Mahnung_-_Konto__17020_09.md`
- **Kategorie:** Finanz-Audit & Leistungsansprüche
- **Zweck:** Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.

### Volltext / Inhalt
# AW: Mahnung - Konto: 17020 09

- **Von:** finAPI Buchhaltung <buchhaltung@finapi.io>
- **An:** \'Alina Steinhauer\' <kontakt@mein-seelenfunke.de>, finAPI Buchhaltung

	<buchhaltung@finapi.io>
- **Datum:** 2026-09-02 12:34:16
- **Kategorie:** Rechnung
- **Priorität:** normal
- **KI-Zusammenfassung:** finAPI Buchhaltung entschuldigt sich für eine fälschlicherweise gesendete Mahnung und bestätigt, dass die ursprüngliche Rechnung storniert wurde.
- **Handlungsbedarf:** Kein Handlungsbedarf

---

## Nachrichtentext
Hallo Frau Steinhauer, 



bitte entschuldigen Sie das Versehen. Die ursprüngliche Rechnung wurde bei uns bereits storniert. 



Viele Grüße

Monika Melchior



Monika Melchior

Head of Business Operations

T +49 89 41617755-501 

melchior@finapi.io | www.finapi.io







finAPI GmbH, Adams-Lehmann-Str. 44, 80797 München

Subscribe to our Newsletter | Follow us on LinkedIn





Geschäftsführer: Georg Schardt; Registergericht: Amtsgericht München, HRB 175250.

Diese Nachricht (inklusive aller Anhänge) ist vertraulich. Sie darf ausschließlich durch den vorgesehenen Empfänger und Adressaten gelesen, kopiert oder genutzt werden. Sollten Sie diese Nachricht versehentlich erhalten haben, bitten wir, den Absender (durch Antwort-E-Mail) hiervon unverzüglich zu informieren und die Nachricht zu löschen. Jede unerlaubte Nutzung oder Weitergabe des Inhalts dieser Nachricht, sei es vollständig oder teilweise, ist unzulässig.

Unsere Hinweise zum Umgang mit personenbezogenen Daten finden Sie unter www.finapi/datenschutz.









-----Ursprüngliche Nachricht-----

Von: Alina Steinhauer <kontakt@mein-seelenfunke.de> 

Gesendet: Mittwoch, 2. September 2026 09:04

An: finAPI Buchhaltung <buchhaltung@finapi.io>

Betreff: Mahnung - Konto: 17020 09



Hallöchen,



ich hatte damals ein sehr nettes Telefonat mit einer Kollegin von Ihnen und auch einigen Mail verkehr.

Darauf hin hieß es, dass alles geklärt sei und mein Fall als "Test" 

anerkannt wurde.





Ich habe tatasächtlich nur die API einmal testen wollen. Mehr steckte da 

nicht hinter.



Ich bitte Sie daher, die Mahngebühr zu streichen und den Prozess 

dahinter zu schließen.





Ich hoffe da jetzt wirklich auf Ihr entgegenkommen.





PS: Mir war auch nicht mal bewusst, das dort irgendwelche Mahnungen bei 

Ihnen entstehen,



Ab dem Moment wo mir die Gutschrift bestätigt wurde, war das Thema vom 

Tisch.





Schöne Grüße,



Steinhauer Alina


## 5. 2026 09 21 Finanzbericht 2026 9
- **Dateipfad:** `agenten/workspace/Dokumente/Finanzen/2026-09-21_Finanzbericht_2026_9.zip`
- **Kategorie:** Allgemein
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.

## 6. 2026 09 21 Finanzbericht 9 2026
- **Dateipfad:** `agenten/workspace/Dokumente/Finanzen/2026-09-21_Finanzbericht_9_2026.md`
- **Kategorie:** Allgemein
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.

### Volltext / Inhalt
# Finanzbericht 9/2026

- **Von:** Mein-Seelenfunke-Stage <kontakt@mein-seelenfunke.de>
- **An:** kontakt@mein-seelenfunke.de
- **Datum:** 2026-09-21 13:45:29
- **Kategorie:** Vertrag & Finanzen
- **Priorität:** high
- **KI-Zusammenfassung:** Ein interner Finanzbericht für September 2026 von Mein-Seelenfunke-Stage. Der Inhalt ist ein HTML-Snippet.
- **Handlungsbedarf:** Finanzbericht zur Prüfung: Finanzbericht für September 2026 prüfen

---

## Nachrichtentext
 

 

 

     

     

     Finanzbericht 9/2026 

     

        body {

            font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;

            background-color: #f4f4f4;

            margin: 0;

            padding: 0;

            color: #333;

        }

        .container {

            max-width: 600px;

            margin: 40px auto;

            background-color: #ffffff;

            border-radius: 8px;

            overflow: hidden;

            box-shadow: 0 2px 10px rgba(0,0,0,0.1);

        }

        .header {

            background-color: #ffffff;

            padding: 30px 40px;

            text-align: center;

            border-bottom: 3px solid #C5A059;

        }

        .header h1 {

            color: #C5A059;

            margin: 0;

            font-size: 22px;

            font-family: serif;

        }

        .content {

            padding: 40px;

            line-height: 1.6;

        }

        .agent-badge {

            display: inline-block;

            background-color: #f3f4f6;

            color: #4b5563;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            padding: 4px 10px;

            border-radius: 12px;

            margin-bottom: 20px;

            border: 1px solid #e5e7eb;

        }

        .message-box {

            background-color: #f9f9f9;

            padding: 20px;

            border-left: 4px solid #C5A059;

            border-radius: 4px;

            margin-top: 20px;

            margin-bottom: 20px;

            white-space: pre-wrap; /* Wichtig für Zeilenumbrüche aus Text */

        }

        .footer {

            background-color: #f9f9f9;

            padding: 20px;

            text-align: center;

            font-size: 12px;

            color: #888;

            border-top: 1px solid #eee;

        }

     

 

 

 

     

         Nachricht von Mein-Seelenfunke-Stage 

     



     

         

            Generiert von Agent:  System-Agent 

         

        

         Hallo, 

         Du hast eine neue Nachricht aus deinem Arbeitsbereich erhalten: 



         Hallo, 

 

anbei erhalten Sie den angeforderten Finanzbericht für den Monat 9/2026 als ZIP-Archiv. Darin enthalten ist die Übersicht als PDF sowie alle Transaktionen als CSV und die Belege. 

 

Viele Grüße, 

Dein System-Agent 



         Herzliche Grüße, System-Agent ✨ 

     



     

        Diese E-Mail wurde automatisch von Deinem persönlichen KI-Assistenten generiert. 

        21.09.2026 13:45 Uhr


## 7. 2026 09 30 Rechnung 2026 987 F Lligkeit 15.10.2026
- **Dateipfad:** `agenten/workspace/Dokumente/Finanzen/2026-09-30_Rechnung_2026-987_F_lligkeit_15.10.2026.md`
- **Kategorie:** Allgemein
- **Zweck:** Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.

### Volltext / Inhalt
# Rechnung 2026-987 Fälligkeit 15.10.2026

- **Von:** Host Europe <buchhaltung@host-europe-service.de>
- **An:** me@test.de
- **Datum:** 2026-09-30 07:30:42
- **Kategorie:** Rechnung
- **Priorität:** high
- **KI-Zusammenfassung:** Eine Rechnung von Host Europe über 49,99€ mit Fälligkeitsdatum 15.10.2026.
- **Handlungsbedarf:** Zahlungsfrist bis 15.10.2026: Rechnung prüfen und Zahlung veranlassen

---

## Nachrichtentext
Bitte überweisen Sie den Betrag von 49,99€ auf unsere IBAN.


## 8. Steuererklärung Für 2025
- **Dateipfad:** `agenten/workspace/Dokumente/Steuern/Steuererklärung für 2025.pdf`
- **Kategorie:** Finanzen & Verträge | **Datum:** 1993-09-01
- **Zweck:** Steuerdokumentation (Steuererklärung / Steuerbescheid) zur Vermögens- und Ertragsübersicht.
- **Zusammenfassung:** Finanzen & Verträge: -- Vorschau - Vorschau - Vorschau -- 2025 *** Vorschau *** *** Vorschau *** *** Vorschau *** *** Vorschau *** *** Vorschau *** smartsteuer gedruckt mit ERiC-Print 43.4.6/ESt_2025 43.4.6 Steuernummer 19/143/23928Finanzamt Gifhorn Hauptvordruck ESt 1 A Art der Erklärung Einkommensteuererklärung Angabe
- **Auszug:** `-- Vorschau - Vorschau - Vorschau --
2025
*** Vorschau *** *** Vorschau *** *** Vorschau ***
*** Vorschau *** *** Vorschau ***
smartsteuer gedruckt mit ERiC-Print 43.4.6/ESt_2025 43.4.6
Steuernummer 19/143/23928Finanzamt Gifhorn
Hauptvordruck ESt 1 A
Art der Erklärung
Einkommensteuererklärung
Angaben zu Belegen
Grundsätzlich brauchen Sie keine Belege mit Ihrer Steuererklärung einzureichen. Bitte bewahren Sie Ihre Unterlagen
für eventuelle Rückfragen auf und reichen diese nur auf Anforderung durch Ihr Finanzamt nach. Beabsichtigen Sie
dennoch Belege einzureichen, kreuzen Sie bitte das nachfolge`

## 9. Kontoauszug Girokonto (Umsaetze_DE85269910668583196000_02-09-2026_10-29.pdf)
- **Dateipfad:** `agenten/workspace/Dokumente/Bank/Umsaetze_DE85269910668583196000_02-09-2026_10-29.pdf`
- **Kategorie:** Finanz-Audit & Leistungsansprüche | **Datum:** 2026-09-02
- **Zweck:** Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.
- **Zusammenfassung:** Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.
- **Auszug:** `Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.`

## 10. Kontoauszug Girokonto (Umsaetze_DE85269910668583196000_2026.09.02.csv)
- **Dateipfad:** `agenten/workspace/Dokumente/Bank/Umsaetze_DE85269910668583196000_2026.09.02.csv`
- **Kategorie:** Finanz-Audit & Leistungsansprüche | **Datum:** 2026-09-02
- **Zweck:** Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.
- **Zusammenfassung:** Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.
- **Auszug:** `Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.`',
            ],
        ];

        foreach (array_merge($dossiers, $resolvedDossiers) as $d) {
            $kb = AiKnowledgeBase::updateOrCreate(
                ['slug' => $d['slug']],
                [
                    'title' => $d['title'],
                    'ai_knowledge_base_category_id' => $catMap[$d['category']],
                    'content' => trim($d['content']),
                    'is_published' => true,
                ]
            );

            $syncIds = array_values(array_filter(array_map(fn($t) => $tagMap[$t] ?? null, $d['tags'])));
            $kb->tags()->sync($syncIds);
        }

        // 5. Workspace-Dokumentenkatalog (ai_workspace_documents) direkt anlegen
        $this->seedWorkspaceDocuments();
    }

    /**
     * Seeds the AI Workspace document catalog directly without external JSON files.
     */
    protected function seedWorkspaceDocuments(): void
    {
        if (!Schema::hasTable('ai_workspace_documents')) {
            return;
        }

        $documents = [
            [
                'file_path' => 'agenten/workspace/Dokumente/Allgemein/alina.jpg',
                'sha256' => '7ef80ba00b9711834d564660f4cea9b1d4a89e2908896e98ace3f6663a356d37',
                'filename' => 'alina.jpg',
                'file_type' => 'jpg',
                'file_size' => 547804,
                'title' => 'Alina',
                'category' => 'Allgemein',
                'tags' => ['Allgemein'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => 'Allgemein: Bilddatei (JPG, 535.0 KB)',
                'key_facts' => [],
                'extracted_date' => '2026-09-16',
                'content_preview' => 'Bilddatei (JPG, 535.0 KB)',
                'full_text' => 'Bilddatei (JPG, 535.0 KB)',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Allgemein/Erinnerung.jpeg',
                'sha256' => '74160ec39035acd20661e375eaffe72be37d65558b01b63fc9be596d96517f51',
                'filename' => 'Erinnerung.jpeg',
                'file_type' => 'jpeg',
                'file_size' => 155853,
                'title' => 'Whatsapp Image 2026-09-17 At 12.38.59',
                'category' => 'Allgemein',
                'tags' => ['Allgemein'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => 'Allgemein: Bilddatei (JPEG, 152.2 KB)',
                'key_facts' => [],
                'extracted_date' => '2026-09-17',
                'content_preview' => 'Bilddatei (JPEG, 152.2 KB)',
                'full_text' => 'Bilddatei (JPEG, 152.2 KB)',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Projekte/Projekt_Notizen_KI_Assistent.md',
                'sha256' => 'a4d6545ea5c9b1493df4da7ad2214e4d4c2861f284149d578f7c3c3953090c88',
                'filename' => 'Projekt_Notizen_KI_Assistent.md',
                'file_type' => 'md',
                'file_size' => 154,
                'title' => 'Projekt Notizen Ki Assistent',
                'category' => 'Arbeit & Projekte',
                'tags' => ['Arbeit & Projekte'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.',
                'summary' => 'Arbeit & Projekte: # Funkira Projekt Notizen Datum: 15.09.2026 Idee: Erstellung eines Second Brains für Alina. Tasks: Sprachmodul, Vision, Datei-Ingestion fertigstellen.',
                'key_facts' => [],
                'extracted_date' => '2026-09-15',
                'content_preview' => '# Funkira Projekt Notizen
Datum: 15.09.2026
Idee: Erstellung eines Second Brains für Alina.
Tasks: Sprachmodul, Vision, Datei-Ingestion fertigstellen.',
                'full_text' => '# Funkira Projekt Notizen
Datum: 15.09.2026
Idee: Erstellung eines Second Brains für Alina.
Tasks: Sprachmodul, Vision, Datei-Ingestion fertigstellen.',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Alina_Steinhauer_Gruenderin_Profilbild.png',
                'sha256' => '726a3ac92deb4c1f6798836ba7f469b469b3e16cedc3b6da19e47950879d713f',
                'filename' => 'Alina_Steinhauer_Gruenderin_Profilbild.png',
                'file_type' => 'png',
                'file_size' => 2264166,
                'title' => 'Offizielles Profilbild / Bewerbungsfoto: Alina Steinhauer',
                'category' => 'Allgemein',
                'tags' => ['Foto', 'Profilbild', 'Alina Steinhauer', 'Gründerin', 'Mein Seelenfunke'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => 'Hochauflösendes professionelles Porträtfoto von Gründerin Alina Steinhauer.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => 'Hochauflösendes professionelles Porträtfoto von Gründerin Alina Steinhauer.',
                'full_text' => 'Hochauflösendes professionelles Porträtfoto von Gründerin Alina Steinhauer.',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/BA_Formular_Stellungnahme_fachkundige_Stelle_Gruendungszuschuss.pdf',
                'sha256' => '7ba54390a2257d95ff7de3cde5ca694e12635ac615ebdeb9ef42c4a385302548',
                'filename' => 'BA_Formular_Stellungnahme_fachkundige_Stelle_Gruendungszuschuss.pdf',
                'file_type' => 'pdf',
                'file_size' => 159533,
                'title' => 'BA-Vordruck: Stellungnahme der fachkundigen Stelle zur Tragfähigkeit der Existenzgründung',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Fachkundige Stelle', 'Tragfähigkeitsprüfung', 'Formular', 'BA', 'Gründungszuschuss', '§ 93 SGB III'],
                'purpose' => 'Vordruck der Bundesagentur für Arbeit: Fachkundige Stellungnahme zur Tragfähigkeit der Existenzgründung nach § 93 SGB III.',
                'summary' => 'Offizieller Vordruck der Bundesagentur für Arbeit (BA 053733) zur Begutachtung und Bestätigung der Tragfähigkeit der Existenzgründung durch eine fachkundige Stelle (IHK, HWK, Steuerberater).',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => '1 Anschrift des Kunden 2 Anschrift der fachkundigen Stelle (Absender)
Stellungnahme der fachkundigen Stelle zur 
Tragfähigkeit der Existenzgründung
nach § 93 Absatz 2 Nr. 2 Drittes Buch Sozialgesetzbuch (SGB III)
Hinweis: Zutreffendes bitte ausfüllen oder ankreuzen. 
Pflichtfelder sind mit Stern * m',
                'full_text' => '1 Anschrift des Kunden 2 Anschrift der fachkundigen Stelle (Absender)
Stellungnahme der fachkundigen Stelle zur 
Tragfähigkeit der Existenzgründung
nach § 93 Absatz 2 Nr. 2 Drittes Buch Sozialgesetzbuch (SGB III)
Hinweis: Zutreffendes bitte ausfüllen oder ankreuzen. 
Pflichtfelder sind mit Stern * markiert.
A. Allgemeines
3 Wurden alle erforderlichen Unterlagen zur Beurteilung des Gründungsvorhabens vorgelegt?
 Ja    Nein
4 Welche Unterlagen wurden eingereicht?
 Aussagefähige Beschreibung des Existenzgründungsvorhabens zur Erläuterung der Geschäftsidee
 Lebenslauf (einschließlich Zeugnisse beziehungsweise Befähigungsnachweis)
 Kapitalbedarfs- und Finanzierungsplan
 Umsatz- und Rentabilitätsvorschau
 Begründung der letzten Geschäftsaufgabe (falls zutreffend)
B. Angaben zur Person
5 Nachname * 6  Vorname *
7 Kundennummer *
8 Sind die Voraussetzungen für das Existenzgründungsvorhaben in fachlicher und branchenspezifischer Hinsicht gegeben?
 Ja    Nein
9 Sind die Voraussetzungen für das Existenzgründungsvorhaben in kaufmännischer und unternehmerischer Hinsicht 
gegeben?
 Ja    Nein
10 Liegen die Zulassungsvoraussetzungen (zum Beispiel Konzession, Eintragung ins Handelsregister oder in die 
Handwerksrolle) für das Existenzgründungsvorhaben vor?
 Ja    Nein
BA GZ 04 - 01/2025 Seite 1 von 2
C. Angaben zum Vorhaben
11 Scheint das Existenzgründungsvorhaben – auch in absehbarer Zeit – konkurrenzfähig?
 Ja    Nein
12 Schätzt die Existenzgründerin oder der Existenzgründer die voraussichtlichen Umsätze realistisch ein?
 Ja    Nein
13 Schätzt die Existenzgründerin oder der Existenzgründer die voraussichtlichen Betriebsergebnisse vor Steuerabzug 
realistisch ein?
 Ja    Nein
14 Schätzt die Existenzgründerin oder der Existenzgründer den voraussichtlichen Kapitalbedarf realistisch ein?
 Ja    Nein
15 Kann das zu erwartende Einkommen der Existenzgründerin oder dem Existenzgründer voraussichtlich eine ausreichende 
Lebensgrundlage bieten?
 Ja    Nein
D. Zusammenfassende Beurteilung der Tragfähigkeit der Existenzgründung
16 Bitte geben Sie nachfolgend eine zusammenfassende Beurteilung der Tragfähigkeit der Existenzgründung ab.
E. Abschließende Einschätzung
17 Scheint mit dem Vorhaben der Aufbau einer tragfähigen Existenzgründung insgesamt realisierbar?
 Ja    Nein
Diese Stellungnahme wird von der fachkundigen Stelle nach Prüfung der vorgelegten Unterlagen und auf der Grundlage der 
Angaben der gründungswilligen Person abgegeben.
18 Ort 19 Datum 20 Unterschrift der fachkundige',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Businessplan_Mein_Seelenfunke_15-08-2026.md',
                'sha256' => '7d451371b12ab44b38e3b0cc3a7694fc4ab4da9529ff6045ddbe7d5164668c9a',
                'filename' => 'Businessplan_Mein_Seelenfunke_15-08-2026.md',
                'file_type' => 'md',
                'file_size' => 20662,
                'title' => 'Businessplan: Mein Seelenfunke (E-Commerce Manufaktur & Laserveredelung)',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Businessplan', 'Mein Seelenfunke', 'E-Commerce', 'Lasertechnik', 'Manufaktur', 'Etsy', 'Alina Steinhauer', 'Existenzgründung', 'Tragfähigkeitsbescheinigung', 'Gründungszuschuss'],
                'purpose' => 'Offizieller Businessplan zur Vorlage bei der Steuerberaterin für die Tragfähigkeitsbescheinigung und beim Arbeitsamt für den Gründungszuschuss (§ 93 SGB III).',
                'summary' => 'Umfassender 13-teiliger Businessplan für das Gründungsvorhaben \'Mein Seelenfunke\' von Alina Steinhauer. E-Commerce-Manufaktur für hochemotionale personalisierte Geschenke (Galvo- und UV-Laserveredelung von Glas, Holz, Metall, Schiefer, Schmuck). Vertrieb über Etsy und mein-seelenfunke.de. Standort: Carl-Goerdeler-Ring 26, Gifhorn. Ziel-Nettoeinkommen 1.600–2.000 EUR/Monat.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => '# Businessplan: Mein Seelenfunke
**Gründerin:** Alina Steinhauer  
**Stand des Businessplans:** Aktualisiert am 15.06.2026 (Anpassung des Zeitplans aufgrund von Krankenhausaufenthalt – Start am 15.08.2026)

---

## Inhalt
1. [Zusammenfassung](#1-zusammenfassung)
2. [Gründerprofil & Kompetenzen](#2-g',
                'full_text' => '# Businessplan: Mein Seelenfunke
**Gründerin:** Alina Steinhauer  
**Stand des Businessplans:** Aktualisiert am 15.06.2026 (Anpassung des Zeitplans aufgrund von Krankenhausaufenthalt – Start am 15.08.2026)

---

## Inhalt
1. [Zusammenfassung](#1-zusammenfassung)
2. [Gründerprofil & Kompetenzen](#2-gründerprofil--kompetenzen)
3. [Geschäftsidee & Produkte](#3-geschäftsidee--produkte)
4. [Markt- & Wettbewerbsanalyse](#4-markt--wettbewerbsanalyse)
5. [Marketing & Vertriebsstrategie](#5-marketing--vertriebsstrategie)
6. [Organisation & Standort (Dachboden)](#6-organisation--standort-dachboden)
7. [Risikoanalyse](#7-risikoanalyse)
8. [Investitionsübersicht](#8-investitionsübersicht)
9. [Jahresplanung & Zeitlicher Ablauf (2026/2027)](#9-jahresplanung--zeitlicher-ablauf-20262027)
10. [Umsatzplanung (2028-2030)](#10-umsatzplanung-2028-2030)
11. [Detaillierte Finanzübersicht (Ziel-Szenario 2028)](#11-detaillierte-finanzübersicht-ziel-szenario-2028)
12. [Private Budgetplanung (2028)](#12-private-budgetplanung-2028)
13. [Produktkalkulationen (Starter-Kollektion)](#13-produktkalkulationen-starter-kollektion)

---

## 1. Zusammenfassung

Mein-Seelenfunke ist eine E-Commerce-Manufaktur für hoch-emotionale, personalisierte Geschenke. Unter dem Motto **"Ein Funke, der bleibt"** werden mittels modernster UV- und Galvo-Lasertechnologien Rohmaterialien wie Glas, Acryl, Metall und Schiefer personalisiert und veredelt. Auch die eigene Produktion von Schmuck (Halsketten), Glasbildern und Holzgeschenken wird angestrebt.

Im Gegensatz zu massenproduzierter Ware bietet Mein-Seelenfunke individuelle Unikate, die speziell für Anlässe wie Jahrestage, Hochzeiten oder Trauerbewältigung gefertigt werden. Der Vertrieb startet über den Marktplatz Etsy und wird später über den eigenen Onlineshop unter `mein-seelenfunke.de` ausgebaut. Ziel ist der direkte Übergang in die volle Selbstständigkeit bis **Ende 2027** mit einem angestrebten verfügbaren Nettoeinkommen zwischen 1.600 € und 2.000 €/Monat.

---

## 2. Gründerprofil & Kompetenzen

Die Gründerin Alina Steinhauer verfügt aufgrund ihrer 12-jährigen Erfahrung im Bereich Support, Softwareentwicklung, Vor-Ort-Service und einer Nebentätigkeit als Drohnenpilotin über ein ausgeprägtes technisches Verständnis und beste Voraussetzungen für die Umsetzung der neu geplanten Gewerbestruktur. Die Anlauf- und Vorbereitungsphase bis Ende 2027 wird intensiv zur Weiterbildung in den Bereichen E-Commerce, Digital Marketing und Lasertechnik genutzt (z. B. Z',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Businessplan_Mein_Seelenfunke_15-08-2026.pdf',
                'sha256' => '07ed387a891bc95e04dd59339d5bedf7517d0c68ba352824a7e2466859474f5c',
                'filename' => 'Businessplan_Mein_Seelenfunke_15-08-2026.pdf',
                'file_type' => 'pdf',
                'file_size' => 997659,
                'title' => 'Businessplan PDF: Mein Seelenfunke (Druckfassung)',
                'category' => 'Existenzgründung & Arbeitsagentur',
                'tags' => ['Businessplan', 'Mein Seelenfunke', 'PDF', 'Druckfassung', 'Existenzgründung', 'Tragfähigkeitsbescheinigung', 'Gründungszuschuss'],
                'purpose' => 'Offizieller Businessplan zur Vorlage bei der Steuerberaterin für die Tragfähigkeitsbescheinigung und beim Arbeitsamt für den Gründungszuschuss (§ 93 SGB III).',
                'summary' => 'Offizielle PDF-Druckausfertigung des Businessplans \'Mein Seelenfunke\' zur Einreichung bei der fachkundigen Stelle und der Agentur für Arbeit.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => 'BUSINESSPLAN
E-Commerce-Manufaktur für personalisierte Geschenke
UNTERNEHMENSDATEN:
Gründerin: Alina Steinhauer
Rechtsform: Einzelunternehmerin
Standort: Carl-Goerdeler-Ring 26, 38518 Gifhorn
Gewerbestart: 15.08.2026 (Verschoben aufgrund von Krankenhausaufenthalt)
Webseite: www.mein-seelenfunke.de
K',
                'full_text' => 'BUSINESSPLAN
E-Commerce-Manufaktur für personalisierte Geschenke
UNTERNEHMENSDATEN:
Gründerin: Alina Steinhauer
Rechtsform: Einzelunternehmerin
Standort: Carl-Goerdeler-Ring 26, 38518 Gifhorn
Gewerbestart: 15.08.2026 (Verschoben aufgrund von Krankenhausaufenthalt)
Webseite: www.mein-seelenfunke.de
Kontakt-E-Mail: info@mein-seelenfunke.de
Datum der Erstellung: 15.06.2026 (Version 2.1 - Zeit- und Design-angepasst)
INHALT:
1. Zusammenfassung S. 2
2. Gründerprofil & Kompetenzen S. 2
3. Geschäftsidee & Produkte S. 2
4. Markt- & Wettbewerbsanalyse S. 3
5. Marketing & Vertriebsstrategie S. 3
6. Organisation & Standort S. 3
7. Risikoanalyse & Investitionsübersicht S. 4
8. Jahresplanung & Zeitlicher Ablauf (2026/2027) S. 5
9. Umsatzplanung (2028-2030) S. 8
10. Detaillierte Finanzübersicht (EÜR 2028) S. 11
11. Private Budgetplanung & Produktkalkulationen S. 12
Businessplan: Mein Seelenfunke Stand: 15.08.2026 (Aktualisiert)
1. Zusammenfassung
Mein-Seelenfunke ist eine E-Commerce-Manufaktur für hoch-emotionale, personalisierte Geschenke. Unter dem
Motto "Ein Funke, der bleibt" werden mittels modernster UV- und Galvo-Lasertechnologien Rohmaterialien wie
Glas,  Acryl,  Metall  und  Schiefer  personalisiert  und  veredelt.  Auch  die  eigene  Produktion  von  Schmuck
(Halsketten), Glasbildern und Holzgeschenken wird angestrebt.
Im Gegensatz zu massenproduzierter Ware bietet Mein-Seelenfunke individuelle Unikate, die speziell für Anlässe
wie Jahrestage, Hochzeiten oder Trauerbewältigung gefertigt werden. Der Vertrieb startet über den Marktplatz
Etsy  und  wird  später  über  den  eigenen  Onlineshop  unter  mein-seelenfunke.de  ausgebaut.  Ziel  ist  der  direkte
Übergang  in  die  volle  Selbstständigkeit  bis  Ende  2027  mit  einem  angestrebten  verfügbaren  Nettoeinkommen
zwischen 1.600 € und 2.000 €/Monat.
2. Gründerprofil & Kompetenzen
Die  Gründerin  (Alina  Steinhauer)  verfügt  aufgrund  der  12-jährigen  Erfahrung  im  Bereich  Support,
Softwareentwicklung,  vor  Ort  Service  und  einer  Nebentätigkeit  als  Drohnenpilotin  über  ein  hoch  technisches
Verständnis und beste Voraussetzungen für die Umsetzung der neu geplanten Gewerbestruktur. Die Anlaufphase
bis  Ende  2027  wird  intensiv  zur  Weiterbildung  in  den  Bereichen  E-Commerce,  Digital  Marketing  und  dem
Laserbereich genutzt (Beispiel: Zertifizierung zur Laserschutzbeauftragen nach OStrV & TROS).
3. Geschäftsidee & Produkte
Der Name "Seelenfunke" suggeriert tiefgehende Bedeutung. Wir verkaufen ',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Dokumentation_Gruendungszuschuss_Onlineantrag.md',
                'sha256' => '6e22938c9c8549adec18f71a78195ac24f3f5ef7e470174c90f06c840407103a',
                'filename' => 'Dokumentation_Gruendungszuschuss_Onlineantrag.md',
                'file_type' => 'md',
                'file_size' => 8193,
                'title' => 'Dokumentation: Online-Antrag Gründungszuschuss § 93 SGB III (Status & Stammdaten)',
                'category' => 'Arbeit & Projekte',
                'tags' => ['Gründungszuschuss', '§ 93 SGB III', 'Agentur für Arbeit', 'Herr Klingner', 'Dokumentation', 'Online-Antrag'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.',
                'summary' => 'Detaillierte Dokumentation des Antragsverfahrens für den Gründungszuschuss nach § 93 SGB III bei der Agentur für Arbeit Gifhorn (Ansprechpartner Herr Klingner, Beratung am 10.02.2026). Dokumentiert Schritte 1 bis 6 und die Checklist für die fachkundige Stelle.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => '# Dokumentation: Online-Antrag auf Gründungszuschuss (Bundesagentur für Arbeit)

Diese Dokumentation fasst alle Informationen aus dem laufenden Online-Antragsverfahren der Bundesagentur für Arbeit (Stand: 15.06.2026) und dem dazugehörigen Dokument für die fachkundige Stelle zusammen.

---

## 1. Sta',
                'full_text' => '# Dokumentation: Online-Antrag auf Gründungszuschuss (Bundesagentur für Arbeit)

Diese Dokumentation fasst alle Informationen aus dem laufenden Online-Antragsverfahren der Bundesagentur für Arbeit (Stand: 15.06.2026) und dem dazugehörigen Dokument für die fachkundige Stelle zusammen.

---

## 1. Status und Stammdaten des Antrags

*   **Antragstyp:** Gründungszuschuss nach § 93 Abs. 2 Nr. 2 SGB III
*   **Aktueller Fortschritt:** Schritt 6 von 11 ("Arbeitslosengeldbezug") ist ausgefüllt.
*   **Persönlicher Ansprechpartner:** Herr Klingner (Agentur für Arbeit)
*   **Frist zur Einreichung:** Der Antrag muss innerhalb von **90 Tagen** nach Beginn abgeschlossen werden. 
    *   *Kritische Frist:* Bei Nichtabschluss wird der Vorgang am **13.09.2026** dauerhaft gelöscht.
*   **Zugeordnetes Dokument im Ordner:** `gruendungszuschuss-gz-stellungnahme-fachkundige-stelle_ba053733.pdf`
    *   *Bedeutung:* Offizielle PDF-Vorlage der Bundesagentur für Arbeit zur Stellungnahme der fachkundigen Stelle über die Tragfähigkeit der Existenzgründung.

---

## 2. Auswertung der ausgefüllten Antragsschritte (Screenshots)

Hier sind die genauen Eingaben und Auswahlen aufgeführt, wie sie in den Screenshots zu sehen sind:

### Schritt 1: Allgemeine Hinweise
*   **Beratung zum Gründungszuschuss:**
    *   *Frage:* Wurden Sie bereits durch die Agentur für Arbeit zu den Fördermöglichkeiten mit einem Gründungszuschuss beraten oder haben Sie bereits einen Beratungstermin vereinbart?
    *   *Auswahl:* **Ja**
    *   *Datum des Beratungstermins:* **10.02.2026**
*   **Erforderliche Pflichtdokumente (im Laufe der Formularstrecke hochzuladen):**
    1.  **Stellungnahme einer fachkundigen Stelle** über die Tragfähigkeit der Existenzgründung (Vorlage befindet sich in diesem Ordner).
    2.  **Eingangsbestätigung der Anzeige der Gewerbeanmeldung** bei der zuständigen Behörde bzw. die Anzeige einer freiberuflichen Tätigkeit bei der Finanzbehörde.
    3.  **Beschreibung des Existenzgründungsvorhabens** (Businessplan).
    4.  **Lebenslauf** und Qualifikationsnachweise.
    5.  *Nur bei Betriebsübernahme:* Nachweise zur bisherigen Geschäftstätigkeit (z. B. BWA, GuV, Steuerbescheide).
*   **Nachreichbare Dokumente (können später übermittelt werden):**
    *   Mitteilung des Finanzamtes über die Zuteilung der Steuernummer
    *   Gewerbeanmeldung (falls noch nicht vorliegend)
    *   Nachweis der Handwerkskammer über die Eintragung in die Handwerksrolle
    *   Gesellschaftsvertrag / Bescheid der Cl',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Lebenslauf_Alina_Steinhauer_Gruenderprofil.pdf',
                'sha256' => '731c4ec48f737f5e12d651dfb3f9b4e2fa6618a603175d823d2d6d551c6cb344',
                'filename' => 'Lebenslauf_Alina_Steinhauer_Gruenderprofil.pdf',
                'file_type' => 'pdf',
                'file_size' => 2993365,
                'title' => 'Ausführlicher Lebenslauf & Gründerprofil: Alina Steinhauer',
                'category' => 'Arbeit & Projekte',
                'tags' => ['Lebenslauf', 'Gründerprofil', 'Alina Steinhauer', 'IT-Support', 'Softwareentwicklung', 'Drohnenpilotin', 'Qualifikationen'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.',
                'summary' => 'Vollständiger beruflicher Werdegang und Qualifikationsprofil von Alina Steinhauer (12 Jahre Erfahrung im IT-Support, Softwareentwicklung, Kundendienst und Drohnentechnik) als Nachweis der unternehmerischen Eignung.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => 'PERSÖNLICHE DATEN
Geburtsdatum:
01.09.1993
Anschrift:
Carl-Goerdeler-Ring 26
38518 Gifhorn
Telefon:
+49 1590 1966 864
E-Mail:
kontakt@alina-steinhauer.de
Webseite:
mein-seelenfunke.de
SPRACHEN
Deutsch
Muttersprache
Englisch
Grundkenntnisse
ALINA STEINHAUER
Webentwicklerin & IT-Spezialistin
Erfahrene',
                'full_text' => 'PERSÖNLICHE DATEN
Geburtsdatum:
01.09.1993
Anschrift:
Carl-Goerdeler-Ring 26
38518 Gifhorn
Telefon:
+49 1590 1966 864
E-Mail:
kontakt@alina-steinhauer.de
Webseite:
mein-seelenfunke.de
SPRACHEN
Deutsch
Muttersprache
Englisch
Grundkenntnisse
ALINA STEINHAUER
Webentwicklerin & IT-Spezialistin
Erfahrene  IT-Spezialistin  und  Full-Stack-Webentwicklerin  mit  über  14  Jahren
Berufserfahrung  in  der  Informationstechnologie.  Nach  fundierten  Stationen  im
IT-Support  und  System  Engineering  habe  ich  mich  in  den  vergangenen  6  Jahren
erfolgreich  auf  moderne  Webtechnologien  (PHP/Laravel,  Vue.js,  TailwindCSS)  und
E-Commerce  spezialisiert.  Ich  verbinde  tiefgehendes  Systeminfrastruktur-Wissen  mit
agiler  Softwareentwicklung  und  realisiere  performante,  nutzerzentrierte  digitale
Lösungen.
BERUFLICHER WERDEGANG
Webentwicklerin 03/2023 – 09/2023
S-KON eKontor 24 GmbH
Full-Stack-Webentwicklung auf Basis von PHP 8 und Laravel.
Entwicklung und Integration von E-Commerce-Schnittstellen (APIs).
Responsive Frontend-Umsetzungen mit TailwindCSS, Alpine.js und JavaScript.
Automatisierung von Buchhaltungs- und Rechnungsabläufen.
Webentwicklerin 09/2021 – 03/2023
Biceps GmbH
Konzeption und Umsetzung von Webanwendungen mit Vue.js und Laravel.
Datenbankdesign, Abfrage-Optimierung und Anbindung von MySQL-Datenbanken.
API-Entwicklung und Integration externer Webservices (REST APIs).
Qualitätssicherung und systematisches API-Testing mittels Postman.
Webentwicklerin 03/2019 – 08/2021
High Office IT GmbH
Entwicklung maßgeschneiderter WordPress-Plugins und Custom-Themes (PHP/JS).
Konzeption, Umsetzung und Betreuung kundenindividueller E-Commerce-Shops.
Beratung  von  Kunden  bzgl.  technischer  Machbarkeit  und
Performance-Optimierung.
Projektkoordination und Versionierung im agilen Team über Git/GitHub.
FACHKOMPETENZEN
Web & Frontend
HTML5, CSS3, Less, TailwindCSS,
JavaScript, Vue.js, Alpine.js
Backend & Datenbanken
PHP,  Laravel,  Golang,  Livewire,
Jetstream, MySQL
IT-Infrastruktur & Support
Systemintegration,
Softwareverteilung,  SCCM,  PXE
Boot,  Windows  Server,  Linux
(Ubuntu), OTRS, Zendesk
Tools & Workflows
Git,  GitHub,  Jira,  Confluence,
Asana, Postman, AutoIT
SOFT SKILLS
Agile Arbeitsweise (Scrum)
Strukturiertes Troubleshooting
Team- & Kommunikationsfähigkeit
Präzise technische Dokumentation
BERUFLICHER WERDEGANG (Forts.)
Elternzeit 10/2023 – 02/2024
Private Auszeit
Junior System Engineer 07/2017 – 03/2019
Computacenter AG & Co. oHG
Konfiguration,',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Existenzgruendung/Zusammenfassung_Gruendungszuschuss_Antragsstand.pdf',
                'sha256' => 'b3c37aea9fd724117b0caa6be8e16398ff35002eb9e753c4af7dbae0ac8599b9',
                'filename' => 'Zusammenfassung_Gruendungszuschuss_Antragsstand.pdf',
                'file_type' => 'pdf',
                'file_size' => 36963,
                'title' => 'Zusammenfassung: Gründungszuschuss Antragsstand & Checkliste',
                'category' => 'Arbeit & Projekte',
                'tags' => ['Gründungszuschuss', 'Zusammenfassung', 'Checkliste', 'BA', 'Existenzgründung'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.',
                'summary' => 'Übersichtsdokument zum Bearbeitungsstand des Gründungszuschusses im BA-Portal (Schritt 6 von 11) nebst To-Do-Liste zur Tragfähigkeitsprüfung.',
                'key_facts' => [],
                'extracted_date' => '2026-06-15',
                'content_preview' => 'ZUSAMMENFASSUNG: GRÜNDUNGSZUSCHUSS
Aktueller Bearbeitungsstand und ausgewählte Antworten
ANTRAGS-STATUS
Schritt 6 von 11
In Bearbeitung
EINREICHUNGSFRIST
13.09.2026
(Kritisch - 90 Tage)
IHR ANSPRECHPARTNER
Herr Klingner
Agentur für Arbeit
1. Allgemeine Hinweise & Beratung
1. Gründungszuschuss-Beratu',
                'full_text' => 'ZUSAMMENFASSUNG: GRÜNDUNGSZUSCHUSS
Aktueller Bearbeitungsstand und ausgewählte Antworten
ANTRAGS-STATUS
Schritt 6 von 11
In Bearbeitung
EINREICHUNGSFRIST
13.09.2026
(Kritisch - 90 Tage)
IHR ANSPRECHPARTNER
Herr Klingner
Agentur für Arbeit
1. Allgemeine Hinweise & Beratung
1. Gründungszuschuss-Beratung  bereits  stattgefunden  oder  Termin
vereinbart?
Ja
2. Datum des vereinbarten/stattgefundenen Beratungstermins 10.02.2026
2. Frühere Existenzgründungen
3. Haben Sie bereits eine oder mehrere selbständige Tätigkeiten ausgeübt? Nein
3. Selbständige Tätigkeit (Umfang)
4. Wurde  die  selbständige  Tätigkeit  bereits  im  Nebenerwerb  ausgeübt  (<  15
Std./Woche)?
Nein
5. Wird die selbständige Tätigkeit hauptberuflich ausgeübt? Ja
6. Üben Sie zusätzlich noch eine oder mehrere andere Tätigkeiten aus? Nein
Vertrauliches Dokument - Nur für den persönlichen Gebrauch Seite 1/2
Zusammenfassung: Antrag auf Gründungszuschuss Stand: 15.06.2026
4. Abgrenzung der Tätigkeit / Statusfeststellung
7. Tätigkeit als Geschäftsführer oder Gesellschafter einer GmbH? Nein
8. Tragen  Sie  ein  eigenes  unternehmerisches  Risiko  (Betriebskapital,
Angestellte, etc.)?
Ja
9. Abhängig oder weisungsgebunden durch einen Auftraggeber? Nein
10. Eingegliedert  in  Betriebsabläufe  oder  Nutzung  von  Arbeitsmitteln  des
Auftraggebers?
Nein
11. Verfügen Sie über einen eigenen Marktauftritt? Ja
5. Arbeitslosengeldbezug
12. Nebeneinkommen  während  der  Arbeitslosigkeit  erzielt,  das  angerechnet
wurde?
Nein
6. Dringender Handlungsbedarf & To-Do-Liste
ACHTUNG: Schritt 7 ("Ihre Geschäftsidee") fehlerhaft/unvollständig!
In den Antrags-Screenshots ist dieser Schritt mit einem roten Kreuz markiert und muss korrigiert werden.
Nächste Schritte checkliste:
Schritt 7 ("Ihre Geschäftsidee") im BA-Portal korrigieren / vervollständigen.
Businessplan, Finanzplan und Lebenslauf für die fachkundige Stelle finalisieren.
Fachkundige Stelle (IHK, HWK, Steuerberater) mit Prüfung der Tragfähigkeit beauftragen.
Ausgefüllte & unterschriebene \'Stellungnahme der fachkundigen Stelle\' (PDF) erhalten.
Gewerbeanmeldung durchführen bzw. Steuernummer beim Finanzamt beantragen.
Alle Nachweise in Schritt 10 hochladen und Antrag vor dem 13.09.2026 absenden.
Vertrauliches Dokument - Nur für den persönlichen Gebrauch Seite 2/2',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Steuern/2026-09-17_Automatische_Antwort___EXTERN__Gewerbesteuer_f_r_2.md',
                'sha256' => '3e0f88b0411385d139eb60a57aebf0596a447e6d860460b49c14c85eb46d377f',
                'filename' => '2026-09-17_Automatische_Antwort___EXTERN__Gewerbesteuer_f_r_2.md',
                'file_type' => 'md',
                'file_size' => 2131,
                'title' => '2026 09 17 Automatische Antwort   Extern  Gewerbesteuer F R 2',
                'category' => 'Finanzen & Verträge',
                'tags' => [],
                'purpose' => 'Steuerdokumentation (Steuererklärung / Steuerbescheid) zur Vermögens- und Ertragsübersicht.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '# Automatische Antwort: [EXTERN] Gewerbesteuer für 2025 - SteuerNr. 19/143/03250

- **Von:** Finanzamt Gifhorn  Poststelle <poststelle@fa-gf.niedersachsen.de>
- **An:** Alina Steinhauer <alina.stone@t-online.de>
- **Datum:** 2026-09-17 11:11:10
- **Kategorie:** Behörde & Amt
- **Priorität:** normal
- **KI-Zusammenfassung:** Das Finanzamt Gifhorn sendet eine automatische Empfangsbestätigung, die darauf hinweist, dass die ursprüngliche E-Mail nur bei Angabe spezifischer Zuordnungskriterien bearbei',
                'full_text' => '# Automatische Antwort: [EXTERN] Gewerbesteuer für 2025 - SteuerNr. 19/143/03250

- **Von:** Finanzamt Gifhorn  Poststelle <poststelle@fa-gf.niedersachsen.de>
- **An:** Alina Steinhauer <alina.stone@t-online.de>
- **Datum:** 2026-09-17 11:11:10
- **Kategorie:** Behörde & Amt
- **Priorität:** normal
- **KI-Zusammenfassung:** Das Finanzamt Gifhorn sendet eine automatische Empfangsbestätigung, die darauf hinweist, dass die ursprüngliche E-Mail nur bei Angabe spezifischer Zuordnungskriterien bearbeitet werden kann. Alina muss prüfen, ob ihre gesendete E-Mail diese Kriterien enthielt.
- **Handlungsbedarf:** Prüfen, ob die ursprüngliche E-Mail an das Finanzamt alle erforderlichen Zuordnungskriterien enthielt.: Ursprüngliche E-Mail an das Finanzamt überprüfen und ggf. mit fehlenden Informationen erneut senden.

---

## Nachrichtentext
Sehr geehrte Dame, sehr geehrter Herr,

das Finanzamt Gifhorn hat Ihre eMail erhalten.

Eine Weiterleitung Ihrer eMail an die zuständige Stelle kann nur erfolgen, wenn die eMail anhand der gemachten Angaben eindeutig identifiziert werden kann.

Mindestens eines der nachstehend genannten vier Zuordnungskriterien muss enthalten sein, ansonsten ist ein Bearbeitung leider nicht möglich:

- Angabe der Steuernummer,

    oder

- der Identifikationsnummer

    oder

- des Aktenzeichens (in Grundbesitzangelegenheiten)

    oder

- des vollständigen Namens mit Anschrift und Geburtsdatum.



Wenn Sie in Ihrer übersandten eMail mindestens eines dieser zuvor genannten Zuordnungskriterien angegeben haben, sind keinen weiteren Angaben erforderlich.

Sofern Sie keines dieser vier Zuordnungskriterien angegeben haben, senden Sie uns bitte ggf. eine gleichlautende eMail unter Ergänzung der erforderlichen Angaben; Ihre bisherige eMail kann leider nicht weiterbearbeitet werden und wird gelöscht.

Darüber hinaus wird stets um Angabe des konkreten Betreffs gebeten

(z.B. Einkommensteuererklärung 2024, Einkommensteuerbescheid 2024 vom …,

Grundsteuererklärung oder Rückfrage vom ...).

Mit freundlichen Grüßen

Ihr Finanzamt Gifhorn',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-09-22_Neue_Mitteilungen_Ihrer_Bundesagentur_f_r_Arbeit.md',
                'sha256' => '772a359cfacf63cf3db2b8a96a3f29d33156d75aa0f0d0bb00652e94af66ab1f',
                'filename' => '2026-09-22_Neue_Mitteilungen_Ihrer_Bundesagentur_f_r_Arbeit.md',
                'file_type' => 'md',
                'file_size' => 2306,
                'title' => '2026 09 22 Neue Mitteilungen Ihrer Bundesagentur F R Arbeit',
                'category' => 'Allgemein',
                'tags' => [],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '# Neue Mitteilungen Ihrer Bundesagentur für Arbeit

- **Von:** Mitteilungsservice <mitteilungsservice@arbeitsagentur.de>
- **An:** alina.stone@t-online.de
- **Datum:** 2026-09-22 03:36:31
- **Kategorie:** Behörde & Amt
- **Priorität:** high
- **KI-Zusammenfassung:** Die Bundesagentur für Arbeit informiert Alina über neue Mitteilungen in ihrem Online-Portal. Sie wird aufgefordert, sich dort anzumelden, um die Neuigkeiten einzusehen.
- **Handlungsbedarf:** Neue Mitteilungen im Portal der Bundesage',
                'full_text' => '# Neue Mitteilungen Ihrer Bundesagentur für Arbeit

- **Von:** Mitteilungsservice <mitteilungsservice@arbeitsagentur.de>
- **An:** alina.stone@t-online.de
- **Datum:** 2026-09-22 03:36:31
- **Kategorie:** Behörde & Amt
- **Priorität:** high
- **KI-Zusammenfassung:** Die Bundesagentur für Arbeit informiert Alina über neue Mitteilungen in ihrem Online-Portal. Sie wird aufgefordert, sich dort anzumelden, um die Neuigkeiten einzusehen.
- **Handlungsbedarf:** Neue Mitteilungen im Portal der Bundesagentur für Arbeit: Im Online-Portal der Bundesagentur für Arbeit anmelden und neue Mitteilungen prüfen.

---

## Nachrichtentext
Guten Tag,



in Ihrem Portal bei der Bundesagentur für Arbeit liegen neue Mitteilungen für Sie vor.



So rufen Sie diese Mitteilungen auf:

Melden Sie sich auf der Internetseite der Bundesagentur für Arbeit im Online-Portal an. Klicken Sie anschließend im Kopfbereich auf das Glockensymbol, um zu Ihren Neuigkeiten zu gelangen. Beachten Sie, dass die Mitteilungen für jedes Profil getrennt abgelegt sind. Prüfen Sie alle Profile auf neue Mitteilungen.



Aus Datenschutzgründen enthält diese E-Mail keine detaillierteren Informationen zu den Mitteilungen.



Mit freundlichen Grüßen

Ihre Bundesagentur für Arbeit







E-Mail-Service abbestellen

Diesen E-Mail-Service können Sie jederzeit und ohne Angabe von Gründen abbestellen: www.arbeitsagentur.de/link/e-mail-benachrichtigung

Melden Sie sich auf der Internetseite der Bundesagentur für Arbeit im Online-Portal an - Sie werden dann automatisch zu Ihrem letzten gewählten Profil weitergeleitet.

Rufen Sie im Kopfbereich [Profil]>[Profil bearbeiten] auf. Unter [Kommunikationsoptionen]>[E-Mail-Benachrichtigung] können Sie die E-Mail-Benachrichtigung deaktivieren bzw. anpassen.



Bitte antworten Sie nicht auf diese E-Mail: Sie wurde automatisch versandt. Ihre Fragen rund um Ihr Konto beantworten wir gern telefonisch.



Wie Sie unseren Technischen Support erreichen können, erfahren Sie auf folgender Seite: https://www.arbeitsagentur.de/link/kontakt



Hinweis: Informationen zu unserem Servicetelefon für Menschen mit Hörbeeinträchtigungen sind dort ebenfalls zu finden.



Auf dieser Seite finden Sie auch Kontaktdaten für weitere Anliegen.',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2021-04-15_BA_Automatische_Erinnerung_Antrag_2021.pdf',
                'sha256' => '7601017ba3976830418de3f491091ddaf0019b475d7c501ea7eb0f64e0cf18ed',
                'filename' => '2021-04-15_BA_Automatische_Erinnerung_Antrag_2021.pdf',
                'file_type' => 'pdf',
                'file_size' => 76259,
                'title' => 'BA-Nachricht: Automatische Erinnerung zu Online-Antrag 2021',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Historie 2021'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Automatische Systemnachricht der Bundesagentur für Arbeit aus April 2021.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Allgemeine Anfrage
Neueste: Nachricht 1 von 1
Datum: 15.04.2021 | 02:02
Absender:  
Betreff: Fehlende Dokumente zu Ihrem Antrag vom 08.04.2021 – 
automatische Erinnerung
Text: Sehr geehrte Nutzerin, sehr geehrter Nutzer,
dies ist eine automatisch erstellte Nachricht.
Sie haben Ihren Antrag am 08.04.',
                'full_text' => 'Allgemeine Anfrage
Neueste: Nachricht 1 von 1
Datum: 15.04.2021 | 02:02
Absender:  
Betreff: Fehlende Dokumente zu Ihrem Antrag vom 08.04.2021 – 
automatische Erinnerung
Text: Sehr geehrte Nutzerin, sehr geehrter Nutzer,
dies ist eine automatisch erstellte Nachricht.
Sie haben Ihren Antrag am 08.04.2021 online gesendet und dabei 
angegeben, dass noch fehlende Unterlagen bis zum 14.04.2021 
nachgereicht werden.
Falls Sie die Unterlagen bereits persönlich abgegeben oder per Post 
an die Agentur für Arbeit gesandt haben, können Sie diese Nachricht 
als gegenstandslos betrachten.
Nach dem derzeitigen Stand wurden folgende Unterlagen bisher nicht 
online nachgereicht:
- Arbeitsbescheinigung High Office IT GmbH 01.03.2019 - 31.05.2021
- Arbeitsbescheinigung Schnellecke 01.04.2016 - 28.02.2017
- Kopie des Kündigungsschreibens
Sie haben die Möglichkeit, fehlende Nachweise über die Schaltfläche 
„Unterlagen nachreichen“ auf der Seite „Meine Übersicht“ online an 
die Agentur für Arbeit zu übermitteln.
Bitte beachten Sie, dass über Ihren Antrag auf Arbeitslosengeld erst 
nach Vorlage aller erforderlichen Unterlagen entschieden werden 
kann.
Mit freundlichen Grüßen
Ihre Agentur für Arbeit
1 von 1',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2024-09-30_BA_Nachricht_Fehlendes_Geld_August_September_2024.pdf',
                'sha256' => '2b064c8c0d78b78f2c5ae0b57ef801b25b202ae2be8e9ce693f85581eb4b4955',
                'filename' => '2024-09-30_BA_Nachricht_Fehlendes_Geld_August_September_2024.pdf',
                'file_type' => 'pdf',
                'file_size' => 85956,
                'title' => 'BA-Nachricht: Dringende Reklamation fehlendes Krankengeld August/September 2024',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'BKK firmus', 'Streit 2024', 'Fehlendes Geld', 'Krankengeld', '30.09.2024'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Dringende Nachricht von Alina (damals Nick) Steinhauer vom 30.09.2024 bezüglich unberechtigter Deckungslücke durch BKK firmus im August 2024.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 30.09.2024 | 15:54
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld - Dringend
Text: Hallo,
das Krankengeld welches ich erhalten habe, wurde jetzt nur für den 
Zeitraum vom 23.08.24 - 02.09.24 ausgezahlt.
Mir fehlt das komplette Geld für A',
                'full_text' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 30.09.2024 | 15:54
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld - Dringend
Text: Hallo,
das Krankengeld welches ich erhalten habe, wurde jetzt nur für den 
Zeitraum vom 23.08.24 - 02.09.24 ausgezahlt.
Mir fehlt das komplette Geld für August und September habe ich jetzt 
nur 659,00€ erhalten (hätten aber 1977,00€ sein müssen)
Hier der Verlauf:
3. Agentur für Arbeit (ALG 1)
11.04.2024 = 1977,00€ (Geld für März)
29.04.2024 = 1977,00€ (Geld für April)
29.05.2024 = 1977,00€ (Geld für Mai)
26.06.2024 = 1977,00€ (Geld für Juni)
30.07.2024 = 1977,00€ (Geld für Juli)
4. BKK Firmus (Krankengeld)
August = NICHTS ERHAL TEN
27.09.2024 = 659€ (Geld für September)
Bitte aber wirklich BITTE klären Sie das schnell... ich bin mehrere 
Tausend Euro im Minus. Ich habe ein Kind, Haus, Auto und viele Dinge 
die bezahlt werden müssen.
Bitte schnell klären... Meine Krankenkasse hat geschrieben, das ich 
für den Zeitraum vor dem 23.08.2024 mit Ihnen Rücksprache halten 
soll.
Gruß,
Steinhauer
Anhänge:
 Krankmeldung_06_08_2024-02_09_2024.pdf (116 KB)
1 von 2
Krankmeldung_18_06_2024-08_07_2024.pdf (148 KB)
KrankengeldBKKFirmus.pdf (446 KB)
Krankmeldung_02_09_2024-30_09_2024.pdf (100 KB)
Krankmeldung_12_07_2024-02_08_2024.pdf (111 KB)
2 von 2',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2024-10-03_BA_Nachricht_Aufstellung_fehlende_Gelder_Krankengeld.pdf',
                'sha256' => '2a3436c60e9a69eabb3ab93416a787ed719f2d2ec531283a31c650611471ddad',
                'filename' => '2024-10-03_BA_Nachricht_Aufstellung_fehlende_Gelder_Krankengeld.pdf',
                'file_type' => 'pdf',
                'file_size' => 76562,
                'title' => 'BA-Nachricht: Aufstellung fehlendes Geld (31.07.–22.08.2024) Zwischen BKK und BA',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'BKK firmus', 'Aufstellung', 'Geldlücke', '03.10.2024'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Nachricht an die Agentur für Arbeit Gifhorn vom 03.10.2024 mit Zahlungsaufstellung zur Klärung der 3-wöchigen Lücke zwischen ALG 1 und Krankengeld.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 03.10.2024 | 19:51
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld
Text: Sehr geehrte Damen und Herren,
bitte prüfen Sie das. Meine Krankenkasse sagt, dass ich mit Ihnen 
über das fehlende Geld vor dem 23.08.2024 sprechen soll.
Meine erha',
                'full_text' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 03.10.2024 | 19:51
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld
Text: Sehr geehrte Damen und Herren,
bitte prüfen Sie das. Meine Krankenkasse sagt, dass ich mit Ihnen 
über das fehlende Geld vor dem 23.08.2024 sprechen soll.
Meine erhalten Gelder sehen so aus:
3. Agentur für Arbeit (ALG 1)
11.04.2024 = 1977,00€ (Geld für März)
29.04.2024 = 1977,00€ (Geld für April)
29.05.2024 = 1977,00€ (Geld für Mai)
26.06.2024 = 1977,00€ (Geld für Juni)
30.07.2024 = 1977,00€ (Geld für Juli)
4. BKK Firmus (Krankengeld)
31.07.2024 bis 22.08.2024 = KEIN GELD ERHAL TEN (August)
23.08.2024 bis 02.09.2024 = 659,00€ (September)
03.09.2024 bis 01.10.2024 = 1911,10€ (September)
Wir fehlt also das komplette Geld im Zeitraum 31.07.2024 - 
22.08.2024.
Ich bitte dringend um Klärung.
Vielen Dank.
Gruß,
Steinhauer
1 von 1',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2024-10-14_BA_Antwort_Ende_Leistungsfortzahlung_30-07-2024.pdf',
                'sha256' => '958dc80982a557de15b02877e7a5345f22f99dca6bf7a94818a98744f63acb33',
                'filename' => '2024-10-14_BA_Antwort_Ende_Leistungsfortzahlung_30-07-2024.pdf',
                'file_type' => 'pdf',
                'file_size' => 82104,
                'title' => 'BA-Antwortschreiben: Bestätigung Ende der Leistungsfortzahlung zum 30.07.2024',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Leistungsfortzahlung', '§ 146 SGB III', 'Abmeldung 30.07.2024', 'BKK firmus Krankengeld'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Offizielles Antwortschreiben der Agentur für Arbeit Gifhorn vom 14.10.2024. Bestätigt das reguläre Ende der 6-wöchigen Leistungsfortzahlung (§ 146 SGB III) zum 30.07.2024 und verweist für den Anschluss ab 31.07.2024 zwingend auf die Krankenkasse (BKK firmus).',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 2 von 2
Datum: 14.10.2024 | 13:32
Absender: Agentur für Arbeit Gifhorn
Betreff: Fehlendes Geld
Text: Sehr geehrter Herr Steinhauer,
vielen Dank für Ihre Nachricht.
Unter Bezugnahme auf Ihre Nachricht verweisen wir auf unser 
Schreiben vom 12.08.2024. Wie S',
                'full_text' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 2 von 2
Datum: 14.10.2024 | 13:32
Absender: Agentur für Arbeit Gifhorn
Betreff: Fehlendes Geld
Text: Sehr geehrter Herr Steinhauer,
vielen Dank für Ihre Nachricht.
Unter Bezugnahme auf Ihre Nachricht verweisen wir auf unser 
Schreiben vom 12.08.2024. Wie Sie diesem entnehmen können, 
erfolgte die Abmeldung zum 30.07.2024 aufgrund des Endes der 
Leistungsfortzahlung im Krankheitsfall. Mit erfolgter Abmeldung 
werden auch keine Krankenkassenbeiträge mehr an Ihre 
Krankenkasse ausgezahlt.
Bitte setzen Sie sich daher zeitnah mit Ihrer Krankenkasse bzgl. der 
Beantragung von Krankengeld in Verbindung.
Hinweis: Die Ihnen mitgeteilten Informationen stellen keinen 
Rechtsanspruch dar. Maßgebend sind die Bescheide und Schreiben 
der Agentur für Arbeit.
Mit freundlichen Grüßen
Ihre Agentur für Arbeit
Bei weiteren Fragen können Sie uns direkt auf diese Nachricht 
antworten.
Telefonisch erreichen Sie uns außerdem:
Montag bis Donnerstag von 08:00 bis 18:00 Uhr, Freitag von 08:00 bis 
14:00 Uhr unter 0800 4 5555 00 (Arbeitnehmer).
Montag bis Freitag von 08:00 bis 18:00 Uhr unter 0800 4 5555 20 
(Arbeitgeber).
Sie finden Ihre Agentur für Arbeit im Internet unter: https://
www.arbeitsagentur.de > Finden Sie Ihre Dienststelle.
Menschen mit Hörbeeinträchtigung: Nehmen Sie gerne Kontakt über 
unser Servicetelefon für Menschen mit Hörbeeinträchtigungen auf. 
Informationen hierzu finden Sie auf unserer Internetseite https://
www.arbeitsagentur.de/menschen-mit-behinderungen/servicetelefon-
fuer-menschen-mit-hoerbeeintraechtigungen.
Wenn Sie uns aus dem Ausland telefonisch erreichen möchten, 
nutzen Sie bitte die Telefonnummer 0049 911 1203 10 10.
1 von 2
Nachricht 1 von 2
Datum: 03.10.2024 | 19:51
Absender: Steinhauer, Nick
Betreff: Fehlendes Geld
Text: Sehr geehrte Damen und Herren,
bitte prüfen Sie das. Meine Krankenkasse sagt, dass ich mit Ihnen 
über das fehlende Geld vor dem 23.08.2024 sprechen soll.
Meine erhalten Gelder sehen so aus:
3. Agentur für Arbeit (ALG 1)
11.04.2024 = 1977,00€ (Geld für März)
29.04.2024 = 1977,00€ (Geld für April)
29.05.2024 = 1977,00€ (Geld für Mai)
26.06.2024 = 1977,00€ (Geld für Juni)
30.07.2024 = 1977,00€ (Geld für Juli)
4. BKK Firmus (Krankengeld)
31.07.2024 bis 22.08.2024 = KEIN GELD ERHAL TEN (August)
23.08.2024 bis 02.09.2024 = 659,00€ (September)
03.09.2024 bis 01.10.2024 = 1911,10€ (September)
Wir fehlt also das komplette Geld im Zeitraum 31.07.2024 - 
22.08.2024.
Ich bitte dringend um Klärung',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2024-10-14_BA_Nachricht_Mahnung_Geldluecke.pdf',
                'sha256' => '002de5c09c95d985ccce8ab6ccc5257a67250936cf21d3f6957533b84a342497',
                'filename' => '2024-10-14_BA_Nachricht_Mahnung_Geldluecke.pdf',
                'file_type' => 'pdf',
                'file_size' => 77247,
                'title' => 'BA-Nachricht: Nachdrückliche Mahnung wegen Zahlungsrückstand 31.07.–22.08.2024',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Mahnung', '14.10.2024'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Nachricht an die Agentur für Arbeit Gifhorn bezüglich finanzieller Notlage durch verspätete Krankengeldzahlung der BKK firmus.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 14.10.2024 | 11:19
Absender: Steinhauer, Nick
Betreff: Ich bin im Minus! Fehlendes Geld! Wichtig!
Text: Hallo,
ich habe eine erhebliche geldliche Lücke und telefonisch ist es nicht 
möglich durchzukommen. Nach mehrmaligen (20min) Wartesessio',
                'full_text' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 14.10.2024 | 11:19
Absender: Steinhauer, Nick
Betreff: Ich bin im Minus! Fehlendes Geld! Wichtig!
Text: Hallo,
ich habe eine erhebliche geldliche Lücke und telefonisch ist es nicht 
möglich durchzukommen. Nach mehrmaligen (20min) Wartesessions, 
wird einfach gesagt (Zu hohes Anrufer aufkommen) und es wird 
aufgelegt.
bitte prüfen Sie das. Meine Krankenkasse teilte mir mit, dass Sie mir 
das fehlende Geld vom 31.07. - 22.08.2024 zahlen müssen. Ich habe 
indem Zeitraum nichts erhalten...
Meine erhalten Gelder sehen so aus:
3. Agentur für Arbeit (ALG 1)
11.04.2024 = 1977,00€ (Geld für März)
29.04.2024 = 1977,00€ (Geld für April)
29.05.2024 = 1977,00€ (Geld für Mai)
26.06.2024 = 1977,00€ (Geld für Juni)
30.07.2024 = 1977,00€ (Geld für Juli)
31.07.2024 bis 22.08.2024 = KEIN GELD ERHAL TEN (August)
4. BKK Firmus (Krankengeld)
23.08.2024 bis 02.09.2024 = 659,00€ (September)
03.09.2024 bis 01.10.2024 = 1911,10€ (September)
Mir fehlt also das komplette Geld im Zeitraum 31.07.2024 - 
22.08.2024.
Ich bitte absolut dringend um Klärung!
Vielen Dank.
Gruß,
Steinhauer
1 von 1',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-09-02_BA_Nachricht_Antrag_rueckwirkende_Leistungskorrektur_24-29_Januar.pdf',
                'sha256' => 'f0f2008e46881e45635c8c06fe910d79bfd8f61adc1bb9ecaabc7bcab924e4bc',
                'filename' => '2026-09-02_BA_Nachricht_Antrag_rueckwirkende_Leistungskorrektur_24-29_Januar.pdf',
                'file_type' => 'pdf',
                'file_size' => 79246,
                'title' => 'BA-Nachricht: Antrag auf rückwirkende Leistungskorrektur (24.01.–29.01.2026 Nahtlosigkeit)',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Nahtlosigkeit', '§ 145 SGB III', 'Nachzahlung 361', '26 EUR', 'Aussteuerung BKK', '02.09.2026'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Schreiben von Alina Steinhauer über das BA-Onlineportal an die Agentur für Arbeit Gifhorn vom 02.09.2026. Beantragung von 361,26 EUR ALG 1 für die 6 Tage 24.01.–29.01.2026 im Anschluss an die Aussteuerung der BKK firmus.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 02.09.2026 | 10:47
Absender: Alina Steinhauer
Betreff: Antrag auf rückwirkende Leistungskorrektur
Text: Sehr geehrte Damen und Herren,
mit Bewilligungsbescheid vom 25.02.2026 wurde mir 
Arbeitslosengeld gem. § 136 SGB III ab dem 30.01.2026 m',
                'full_text' => 'Anfrage Arbeitslosengeld
Neueste: Nachricht 1 von 1
Datum: 02.09.2026 | 10:47
Absender: Alina Steinhauer
Betreff: Antrag auf rückwirkende Leistungskorrektur
Text: Sehr geehrte Damen und Herren,
mit Bewilligungsbescheid vom 25.02.2026 wurde mir 
Arbeitslosengeld gem. § 136 SGB III ab dem 30.01.2026 mit einem 
täglichen Leistungsbetrag von 60,21 € bewilligt.
Mein vorheriger Krankengeldbezug bei der BKK firmus endete wegen 
Aussteuerung (Erreichen der 78-wöchigen Höchstanspruchsdauer) 
mit Ablauf des 23.01.2026.
Für den anschließenden Übergangszeitraum vom 24.01.2026 bis 
einschließlich 29.01.2026 (6 Kalendertage) lag eine lückenlose 
Arbeitsunfähigkeitsbescheinigung vor (siehe Anhang). Da der 
Anspruch auf Krankengeld mit dem 23.01.2026 erschöpft war, 
schließt der Anspruch auf Arbeitslosengeld gem. § 145 SGB III 
(Nahtlosigkeit) unmittelbar am Folgetag der Aussteuerung 
(24.01.2026) an.
Da für die besagten 6 Tage bisher keine Leistungen erbracht wurden, 
beantrage ich hiermit die rückwirkende Anpassung des 
Anspruchsbeginns auf den 24.01.2026 sowie die Nachzahlung des 
zustehenden Arbeitslosengeldes:
Berechnung: 6 Kalendertage×60,21 €=361,26 €
Die Überweisung erbitte ich auf mein bei Ihnen hinterlegtes Girokonto 
(IBAN: DE85 2699 1066 8583 1960 00).
Als Nachweis füge ich die Arbeitsunfähigkeitsbescheinigung vom 
23.01.2026 bis 29.01.2026 diesem Schreiben bei.
Vielen Dank für die Prüfung und Bearbeitung.
Mit freundlichen Grüßen
Alina Steinhauer
1 von 2
Anhang:
 Krankschreibung 23.01.2026 - 29.01.2026.pdf (189 KB)
2 von 2',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-09-22_BA_Nachricht_Nachreichung_Aussteuerungsbescheid_Frau_Schinke.pdf',
                'sha256' => '1065c08f95b805a7a40bcfb90c2d5236236faccaf465569bca6f41fdd38b3e45',
                'filename' => '2026-09-22_BA_Nachricht_Nachreichung_Aussteuerungsbescheid_Frau_Schinke.pdf',
                'file_type' => 'pdf',
                'file_size' => 75991,
                'title' => 'BA-Nachricht: Nachreichung Aussteuerungsbescheid der Krankenkasse an Frau Schinke',
                'category' => 'Behörden & Ämter',
                'tags' => ['Agentur für Arbeit', 'Frau Schinke', 'Aussteuerungsbescheid', '9650125178105', '22.09.2026'],
                'purpose' => 'Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.',
                'summary' => 'Schreiben von Alina Steinhauer vom 22.09.2026 z.H. Frau Schinke (Agentur für Arbeit) zur Nachreichung des BKK-Aussteuerungsbescheides (Dok. 9650125178105.pdf) bezüglich des Antrags auf Leistungskorrektur.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Unterlagen nachreichen
Neueste: Nachricht 1 von 1
Datum: 22.09.2026 | 13:46
Absender: Alina Steinhauer
Betreff: Bescheid der Krankenkasse über die Aussteuerung
Text: Z.H. Frau Schinke
Sehr geehrte Frau Schinke,
da ich aktuell nicht mobil bin und mich von einer OP erhole, reiche ich 
den Nachweis (Be',
                'full_text' => 'Unterlagen nachreichen
Neueste: Nachricht 1 von 1
Datum: 22.09.2026 | 13:46
Absender: Alina Steinhauer
Betreff: Bescheid der Krankenkasse über die Aussteuerung
Text: Z.H. Frau Schinke
Sehr geehrte Frau Schinke,
da ich aktuell nicht mobil bin und mich von einer OP erhole, reiche ich 
den Nachweis (Bescheid der Krankenkasse über die Aussteuerung) 
hierrüber digital nach.
Es geht um den Antrag auf rückwirkende Leistungskorrektur vom 
02.09.2026 10:47 Uhr.
Mit freundlichen Grüßen,
Alina Steinhauer
Anhang:
 9650125178105.pdf (64 KB)
1 von 1',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Bescheide_und_Nachweise.csv',
                'sha256' => '75cf7746ff5f9f1ce06af302fc200f545c2d9dfa82659561d97d1814489dd05f',
                'filename' => '2026-10-05_BA_Onlineportal_Export_Bescheide_und_Nachweise.csv',
                'file_type' => 'csv',
                'file_size' => 5810,
                'title' => 'BA-Onlineportal Transaktionsverlauf: Bescheide & Nachweise (Export 05.10.2026)',
                'category' => 'Behörden & Ämter',
                'tags' => ['Agentur für Arbeit', 'Onlineportal', 'Audit-Trail', 'Bescheide', 'Aufhebungsbescheid 20.08.2026', '05.10.2026'],
                'purpose' => 'Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.',
                'summary' => 'Vollständiger Audit-Export des Online-Portals der Bundesagentur für Arbeit. Belegt u.a. Bereitstellung des Aufhebungsbescheids am 20.08.2026 (abgerufen am 21.08.2026) sowie Antwortschreiben vom 21.09./22.09.2026.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
22.09.2026 09:24;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Antwortschreiben wurde in der Bescheidablage abgerufen.;
22.09.2026 09:23;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der',
                'full_text' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
22.09.2026 09:24;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Antwortschreiben wurde in der Bescheidablage abgerufen.;
22.09.2026 09:23;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der Bescheidablage abgerufen.;
21.09.2026 12:43;Dokument bereitgestellt;Bescheide & Nachweise;Das Dokument Antwortschreiben wurde in der Bescheidablage bereitgestellt.;
21.09.2026 12:43;Dokument bereitgestellt;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der Bescheidablage bereitgestellt.;
21.08.2026 08:10;Bescheid abgerufen;Bescheide & Nachweise;Das Dokument Aufhebungsbescheid und Entgeltbescheinigung wurde in der Bescheidablage abgerufen.;
20.08.2026 15:56;Bescheid bereitgestellt;Bescheide & Nachweise;Das Dokument Aufhebungsbescheid und Entgeltbescheinigung wurde in der Bescheidablage bereitgestellt.;
13.04.2026 10:09;Dokumente eingereicht;Eigenbemühungen;"Sie haben Dokumente zu Ihrem Anliegen ""Eigenbemühungen"" eingereicht.";"Sie haben am 13.04.2026 Dokumente eingereicht. Wie geht es weiter? Ihre Agentur für Arbeit prüft Ihre Dokumente auf Vollständigkeit. Sollten noch weitere Informationen fehlen, wird sich Ihre Agentur für Arbeit bei Ihnen melden. Sollten alle Informationen vollständig vorliegen, wird Ihr Anliegen bearbeitet. "
25.02.2026 09:35;Bescheid abgerufen;Bescheide & Nachweise;Das Dokument Bewilligungsbescheid wurde in der Bescheidablage abgerufen.;
25.02.2026 06:24;Bescheid bereitgestellt;Bescheide & Nachweise;Das Dokument Bewilligungsbescheid wurde in der Bescheidablage bereitgestellt.;
17.02.2026 11:17;Dokument abgerufen;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der Bescheidablage abgerufen.;
17.02.2026 10:30;Dokument bereitgestellt;Bescheide & Nachweise;Das Dokument Zwischennachricht zum Antrag wurde in der Bescheidablage bereitgestellt.;
05.01.2026 11:05;Online-Kommunikation aktiviert;Profil;Sie haben in die Online-Kommunikation eingewilligt.;"Ab sofort stellen wir Ihnen aktiv persönlichen Inhalte in ihr Profil ein. Dazu zählen Schriftstücke (Bescheide, Nachweise, Schreiben), Anträge und Postfachnachrichten im Leistungspostfach. Sie haben im Rahmen der Aktivierung folgendes erklärt und zur Kenntnis genommen: Ich bin damit einverstanden, dass die Agentur für Arbeit/gemeinsame Einrichtung meine E-Mail-Adresse nutzt, um mir eine Nachricht zuzusenden, wenn neue Inhalte für mich in meinem Profil',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Gruendungszuschuss.csv',
                'sha256' => 'aa125a71a6041311e11a0bd505c3069acff44745b633464b84efc5b247f7e148',
                'filename' => '2026-10-05_BA_Onlineportal_Export_Gruendungszuschuss.csv',
                'file_type' => 'csv',
                'file_size' => 295,
                'title' => 'BA-Onlineportal Transaktionsverlauf: Gründungszuschuss',
                'category' => 'Arbeit & Projekte',
                'tags' => ['Agentur für Arbeit', 'Gründungszuschuss', '15.06.2026', '05.10.2026'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeit & Projekte.',
                'summary' => 'Audit-Export des Gründungszuschuss-Vorgangs im BA-Portal (begonnen 15.06.2026, geschlossen 14.09.2026 wegen OP/AU).',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
14.09.2026 03:06;Vorgang geschlossen;Gründungszuschuss;Die Frist zur Einreichung von Dokumenten ist abgelaufen.;
15.06.2026 15:19;Antrag begonnen;Gründungszuschuss;Sie haben einen Antrag auf Gründungszuschuss (Phase 1) begonnen.;',
                'full_text' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
14.09.2026 03:06;Vorgang geschlossen;Gründungszuschuss;Die Frist zur Einreichung von Dokumenten ist abgelaufen.;
15.06.2026 15:19;Antrag begonnen;Gründungszuschuss;Sie haben einen Antrag auf Gründungszuschuss (Phase 1) begonnen.;',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-10-05_BA_Onlineportal_Export_Veraenderungsmitteilungen_AU.csv',
                'sha256' => 'c2318357357468712e769304d28b8841b8dbadfa51c1af77c9bca1c498833cd1',
                'filename' => '2026-10-05_BA_Onlineportal_Export_Veraenderungsmitteilungen_AU.csv',
                'file_type' => 'csv',
                'file_size' => 845,
                'title' => 'BA-Onlineportal Transaktionsverlauf: Veränderungsmitteilungen Arbeitsunfähigkeit',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'AU-Mitteilungen', '13.07.2026 OP-Krankenhaus', '10.08.2026 Folge-AU', '05.10.2026'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Vollständiger Audit-Export der gemeldeten Arbeitsunfähigkeiten im BA-Portal. Belegt Übermittlung der stationären AU ab 22.06.2026 (gesendet am 13.07.2026) sowie der Folge-AU ab 10.08.2026 (gesendet am 10.08.2026).',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
10.08.2026 09:10;Arbeitsunfähigkeit mitgeteilt;Veränderungsmitteilung;Sie haben die Mitteilung zur Arbeitsunfähigkeit am 10.08.2026 versendet.;Ich melde mich für den Zeitraum vom 10.08.2026 voraussichtlich bis einschließlich 04.09.2026 weite',
                'full_text' => 'Ereignisdatum;Titel;Untertitel;Zusammenfassung;Detailtext
10.08.2026 09:10;Arbeitsunfähigkeit mitgeteilt;Veränderungsmitteilung;Sie haben die Mitteilung zur Arbeitsunfähigkeit am 10.08.2026 versendet.;Ich melde mich für den Zeitraum vom 10.08.2026 voraussichtlich bis einschließlich 04.09.2026 weiterhin krank.
13.07.2026 10:38;Arbeitsunfähigkeit mitgeteilt;Veränderungsmitteilung;Sie haben die Mitteilung zur Arbeitsunfähigkeit am 13.07.2026 versendet.;Ich melde mich für den Zeitraum vom 22.06.2026 voraussichtlich bis einschließlich 19.07.2026 krank.
21.01.2025 02:05;Kontakt geändert;Stammdatenänderung online;Sie haben online Ihre Kontaktdaten aktualisiert;"Sie haben folgende Änderung an Ihren Kontaktinformationen vorgenommen: Kontakt geändert: E-Mail alina.stone@t-online.de mit Status Privat (nicht veröffentlicht) "',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/2026-10-06_Chat_Alina_Status_Arbeitsamt_Gruendungszuschuss.txt',
                'sha256' => 'd8b70309d9e5ba4d1a808c0485abdb8485b563c4462bb64255a8ed16d28547d5',
                'filename' => '2026-10-06_Chat_Alina_Status_Arbeitsamt_Gruendungszuschuss.txt',
                'file_type' => 'txt',
                'file_size' => 725,
                'title' => '2026 10 06 Chat Alina Status Arbeitsamt Gruendungszuschuss',
                'category' => 'Allgemein',
                'tags' => [],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '[11:52, 6.10.2026] Alina: lächerlich alles
[11:53, 6.10.2026] Alina: gibt kein Notgeld, Auffanggeld oder sonst was
[11:53, 6.10.2026] Alina: Ich bekomme kein ALG
[11:53, 6.10.2026] Alina: Sonst kann ich die Selbstständigkeit gleich sein lassen. Ohne Gründerzuschuss bekomme ich auch kein ALG
[11:53, 6.10.2026] Alina: ich muss mich jetzt sofort selbstständig machen sonst sind die 150 Resttage verstrichen
[11:54, 6.10.2026] Alina: Ich reiche den Businessplan und Liquiditätsplan bei der Steuerberate',
                'full_text' => '[11:52, 6.10.2026] Alina: lächerlich alles
[11:53, 6.10.2026] Alina: gibt kein Notgeld, Auffanggeld oder sonst was
[11:53, 6.10.2026] Alina: Ich bekomme kein ALG
[11:53, 6.10.2026] Alina: Sonst kann ich die Selbstständigkeit gleich sein lassen. Ohne Gründerzuschuss bekomme ich auch kein ALG
[11:53, 6.10.2026] Alina: ich muss mich jetzt sofort selbstständig machen sonst sind die 150 Resttage verstrichen
[11:54, 6.10.2026] Alina: Ich reiche den Businessplan und Liquiditätsplan bei der Steuerberaterin von meinen Papa ein
[11:54, 6.10.2026] Alina: die können auch eine Tragfähigkeitsbescheinigung austellen.
[11:55, 6.10.2026] Alina: dann gehts zu Frau Grandke von Arbeitsamt für den Gründerzuschuss Antrag.',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/Antrag_Arbeitsagentur_Vorlaeufige_Leistung_Alina_Steinhauer.pdf',
                'sha256' => '93bba363af191e991f7cd63b0cb905918edb23ccf4e0d8f9de4fb0eb8fe750d4',
                'filename' => 'Antrag_Arbeitsagentur_Vorlaeufige_Leistung_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 3896,
                'title' => 'Antrag auf vorläufige Weiterleistung von Arbeitslosengeld (§ 43 SGB I / § 328 SGB III)',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Gifhorn', '241D129534', '§ 43 SGB I', '§ 328 SGB III', 'Vorschuss § 42 SGB I', '§ 146 SGB III', 'Krankengeld'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Offizieller, 1-seitiger Antrag an die Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) auf vorläufige Weiterzahlung des Arbeitslosengeldes nach § 43 SGB I / § 328 SGB III (hilfsweise Vorschuss nach § 42 SGB I) ab dem 03.08.2026. Begründet durch das Auslaufen der 6-wöchigen Leistungsfortzahlung (§ 146 SGB III) am 02.08.2026 und die rechtswidrige Krankengeld-Verweigerung der BKK firmus. Vollständige Freistellung der BA über § 104 SGB X.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn · Tel: 01570 1916814
Agentur für Arbeit Helmstedt
Geschäftsstelle Gifhorn · Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
 Datum: 05.10.2026
Kundennummer / Az.: 241D129534
 (Dienststelle 241 / Team 7002)
Name: Alina Steinhaue',
                'full_text' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn · Tel: 01570 1916814
Agentur für Arbeit Helmstedt
Geschäftsstelle Gifhorn · Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
 Datum: 05.10.2026
Kundennummer / Az.: 241D129534
 (Dienststelle 241 / Team 7002)
Name: Alina Steinhauer, geb. 01.09.1993
 Persönliche Vorsprache (mit Beistand)
ANTRAG AUF VORLÄUFIGE WEITERLEISTUNG VON ARBEITSLOSENGELD
Rechtsgrundlage: § 43 SGB I i.V.m. § 328 Abs. 1 Satz 1 Nr. 1 SGB III (hilfsweise Vorschuss gem. § 42 SGB I)
Sehr geehrte Damen und Herren,
zur Abwendung einer unverschuldeten existenziellen Notlage beantrage ich hiermit die sofortige vorläufige Weiterzahlung
meines Arbeitslosengeldes ab dem 03.08.2026. Nachfolgend erhalten Sie den Sachverhalt kurz und verbindlich auf den
Punkt:
1. Sachverhalt & Chronologie (Leistungsfortzahlung bis 02.08.2026)
 Leistungsfortzahlung nach § 146 SGB III: Am 22.06.2026 wurde ich zur geschlechtsangleichenden Großoperation in den
MVZ Dr. Lubos Kliniken München stationär aufgenommen (OP am 23.06.2026). Die Agentur für Arbeit Gifhorn hat mein
Arbeitslosengeld gemäß § 146 Abs. 1 Satz 1 SGB III für die gesetzliche Dauer von genau 6 Wochen (= 42 Tage vom
22.06.2026 bis einschließlich 02.08.2026) fortgezahlt.
 Planmäßige Leistungseinstellung: Mit Ablauf des 02.08.2026 endete die 6-wöchige Leistungsfortzahlung (letzte Auszahlung
von 120,42 € am 25.08.2026 für den 01.08.–02.08.2026).
 Gesetzlicher Krankengeldanspruch ab 03.08.2026: Ab dem 03.08.2026 besteht bei lückenlos fortdauernder
Arbeitsunfähigkeit (Dr. Lubos Kliniken & Hausarztpraxis D. Jung, Leiferde) gesetzlicher Krankengeldanspruch gegen die BKK
firmus (§ 44 Abs. 1 i.V.m. § 47b SGB V).
 Kompetenzkonflikt: Die BKK firmus hat die Krankengeldzahlung mit Bescheid vom 01.10.2026 (Ref: 9650226941682)
rechtswidrig abgelehnt. Dagegen wurde am 01.10.2026 formeller Widerspruch erhoben.
2. Rechtliche Begründung & Volle Risikoabsicherung der Bundesagentur
 Vorleistungspflicht nach § 43 Abs. 1 Satz 1 SGB I: Besteht zwischen zwei Leistungsträgern (hier: BKK firmus und Agentur
für Arbeit) Streit über die Zuständigkeit oder Leistungspflicht, hat der zuerst angegangene Leistungsträger vorläufige
Leistungen zu erbringen, um die Existenz des Versicherten zu sichern.
 Vorläufige Entscheidung nach § 328 Abs. 1 Satz 1 Nr. 1 SGB III: Die Bundesagentur für Arbeit ist ausdrücklich gesetzlich
ermächtigt, die Leistung vorläufig weiterzuzahlen, solange der Streit über den Anspruch andauert.
',
            ],
            [
                'file_path' => 'agenten/workspace/Berufsleben/Arbeitsamt/Kurzantrag_Arbeitsagentur_Gifhorn_Alina_Steinhauer.pdf',
                'sha256' => '21d24ed3d94b6bd9acfcb548ac64cfb4490baef8827f68bf679a17d3a363cbd2',
                'filename' => 'Kurzantrag_Arbeitsagentur_Gifhorn_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 91005,
                'title' => 'Kurzantrag auf vorläufige Weiterzahlung von Arbeitslosengeld (Agentur für Arbeit Gifhorn)',
                'category' => 'Recht & Behörden',
                'tags' => ['Agentur für Arbeit', 'Gifhorn', 'Kurzantrag', '241D129534', '§ 43 SGB I', '§ 328 SGB III', 'Einnahmeausfall', 'Krankengeld'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Recht & Behörden.',
                'summary' => 'Kompakter, auf 1 Seite optimierter Kurzantrag zur Vorlage bei der Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) am 05.10.2026. Beantragt die vorläufige Weiterzahlung des ALG 1 ab 03.08.2026 gemäß § 43 Abs. 1 SGB I / § 328 SGB III wegen des laufenden Zuständigkeitsstreits mit der BKK firmus. Vollständige Freistellung der BA vom Ausfallrisiko nach § 104 SGB X.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Agentur für Arbeit Gifhorn
Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
Datum:
  05.10.2026
Kundennummer: 241D129534
Geburtsdatum: 01.09.1993
Persönliche Vorsprache
Antrag auf vorläufige Weiterzahlung von Arbeitslosengeld
Re',
                'full_text' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Agentur für Arbeit Gifhorn
Eingangszone / Leistungsabteilung
Kastanienweg 5
38518 Gifhorn
Datum:
  05.10.2026
Kundennummer: 241D129534
Geburtsdatum: 01.09.1993
Persönliche Vorsprache
Antrag auf vorläufige Weiterzahlung von Arbeitslosengeld
Rechtsgrundlage: § 43 SGB I i.V.m. § 328 Abs. 1 Satz 1 Nr. 1 SGB III
Sehr geehrte Damen und Herren,
wegen eines Zuständigkeitsstreits mit der Krankenkasse beantrage ich die vorläufige Weiterzahlung
meines Arbeitslosengeldes:
1. Bis 02.08.2026:
 Die Agentur für Arbeit hat mir mein Arbeitslosengeld während/nach
Krankenhausaufenthalt (§ 146 SGB III) ordnungsgemäß fortgezahlt.
2. Ab 03.08.2026: Mit Ablauf der 6 Wochen endete die Fortzahlung. Ab diesem Tag ist gesetzlich die BKK
firmus für das anschließende Krankengeld zuständig (§ 47b SGB V). Die Kasse verweigert die Leistung
jedoch rechtswidrig (Widerspruch läuft).
3. Einnahmeausfall: Seit dem 03.08.2026 erhalte ich keinerlei laufende Entgeltersatzleistung zur Deckung
meiner Lebenshaltungskosten (weder Krankengeld noch Arbeitslosengeld).
Mein Antrag an die Agentur für Arbeit:
Ich beantrage die vorläufige Weiterzahlung meines Arbeitslosengeldes rückwirkend ab dem
03.08.2026 nach § 43 Abs. 1 SGB I bzw. § 328 SGB III, bis der Krankengeldanspruch mit der
BKK firmus abschließend geklärt ist.
Rechtlicher Hintergrund (Kein finanzielles Risiko für die Arbeitsagentur):
 
Gesetzliche Vorleistungspflicht (§ 43 Abs. 1 SGB I): Ist zwischen zwei Leistungsträgern streitig, wer
zur Leistung verpflichtet ist, hat der zuerst angegangene Leistungsträger vorläufig einzutreten.
 Vollständiger Erstattungsanspruch (§ 104 SGB X): Sobald die BKK firmus das Krankengeld anweist,
erstattet die Kasse der Agentur für Arbeit alle vorverauslagten Beträge direkt zurück. Für die Agentur für
Arbeit besteht keinerlei Ausfallrisiko.
Hinweis: Meine Mutter begleitet mich heute als gesetzlicher Beistand gemäß § 13 Abs. 4 SGB X.
Gifhorn, 05.10.2026
Ort, Datum
________________________________________
Alina Steinhauer
 (Unterschrift)',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Finanzen/2026-09-02_AW__Mahnung_-_Konto__17020_09.md',
                'sha256' => 'f2a6a120d9fca63e76281244d9d8ddb3ddd108f1163a371db53464704849efd2',
                'filename' => '2026-09-02_AW__Mahnung_-_Konto__17020_09.md',
                'file_type' => 'md',
                'file_size' => 2543,
                'title' => '2026 09 02 Aw  Mahnung   Konto  17020 09',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => [],
                'purpose' => 'Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '# AW: Mahnung - Konto: 17020 09

- **Von:** finAPI Buchhaltung <buchhaltung@finapi.io>
- **An:** \'Alina Steinhauer\' <kontakt@mein-seelenfunke.de>, finAPI Buchhaltung

	<buchhaltung@finapi.io>
- **Datum:** 2026-09-02 12:34:16
- **Kategorie:** Rechnung
- **Priorität:** normal
- **KI-Zusammenfassung:** finAPI Buchhaltung entschuldigt sich für eine fälschlicherweise gesendete Mahnung und bestätigt, dass die ursprüngliche Rechnung storniert wurde.
- **Handlungsbedarf:** Kein Handlungsbedarf

---

##',
                'full_text' => '# AW: Mahnung - Konto: 17020 09

- **Von:** finAPI Buchhaltung <buchhaltung@finapi.io>
- **An:** \'Alina Steinhauer\' <kontakt@mein-seelenfunke.de>, finAPI Buchhaltung

	<buchhaltung@finapi.io>
- **Datum:** 2026-09-02 12:34:16
- **Kategorie:** Rechnung
- **Priorität:** normal
- **KI-Zusammenfassung:** finAPI Buchhaltung entschuldigt sich für eine fälschlicherweise gesendete Mahnung und bestätigt, dass die ursprüngliche Rechnung storniert wurde.
- **Handlungsbedarf:** Kein Handlungsbedarf

---

## Nachrichtentext
Hallo Frau Steinhauer, 



bitte entschuldigen Sie das Versehen. Die ursprüngliche Rechnung wurde bei uns bereits storniert. 



Viele Grüße

Monika Melchior



Monika Melchior

Head of Business Operations

T +49 89 41617755-501 

melchior@finapi.io | www.finapi.io







finAPI GmbH, Adams-Lehmann-Str. 44, 80797 München

Subscribe to our Newsletter | Follow us on LinkedIn





Geschäftsführer: Georg Schardt; Registergericht: Amtsgericht München, HRB 175250.

Diese Nachricht (inklusive aller Anhänge) ist vertraulich. Sie darf ausschließlich durch den vorgesehenen Empfänger und Adressaten gelesen, kopiert oder genutzt werden. Sollten Sie diese Nachricht versehentlich erhalten haben, bitten wir, den Absender (durch Antwort-E-Mail) hiervon unverzüglich zu informieren und die Nachricht zu löschen. Jede unerlaubte Nutzung oder Weitergabe des Inhalts dieser Nachricht, sei es vollständig oder teilweise, ist unzulässig.

Unsere Hinweise zum Umgang mit personenbezogenen Daten finden Sie unter www.finapi/datenschutz.









-----Ursprüngliche Nachricht-----

Von: Alina Steinhauer <kontakt@mein-seelenfunke.de> 

Gesendet: Mittwoch, 2. September 2026 09:04

An: finAPI Buchhaltung <buchhaltung@finapi.io>

Betreff: Mahnung - Konto: 17020 09



Hallöchen,



ich hatte damals ein sehr nettes Telefonat mit einer Kollegin von Ihnen und auch einigen Mail verkehr.

Darauf hin hieß es, dass alles geklärt sei und mein Fall als "Test" 

anerkannt wurde.





Ich habe tatasächtlich nur die API einmal testen wollen. Mehr steckte da 

nicht hinter.



Ich bitte Sie daher, die Mahngebühr zu streichen und den Prozess 

dahinter zu schließen.





Ich hoffe da jetzt wirklich auf Ihr entgegenkommen.





PS: Mir war auch nicht mal bewusst, das dort irgendwelche Mahnungen bei 

Ihnen entstehen,



Ab dem Moment wo mir die Gutschrift bestätigt wurde, war das Thema vom 

Tisch.





Schöne Grüße,



Steinhauer Alina',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Finanzen/2026-09-21_Finanzbericht_2026_9.zip',
                'sha256' => '6d50bc0eb9cf264167d6da4e93f77529794ca5b1b25973a636f0e66faf0874e0',
                'filename' => '2026-09-21_Finanzbericht_2026_9.zip',
                'file_type' => 'zip',
                'file_size' => 14214,
                'title' => '2026 09 21 Finanzbericht 2026 9',
                'category' => 'Allgemein',
                'tags' => [],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Finanzen/2026-09-21_Finanzbericht_9_2026.md',
                'sha256' => 'b539ac707eefccb37da3f5c9bbed702d3f529b982224a415bfa8117fed5579c7',
                'filename' => '2026-09-21_Finanzbericht_9_2026.md',
                'file_type' => 'md',
                'file_size' => 3182,
                'title' => '2026 09 21 Finanzbericht 9 2026',
                'category' => 'Allgemein',
                'tags' => [],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '# Finanzbericht 9/2026

- **Von:** Mein-Seelenfunke-Stage <kontakt@mein-seelenfunke.de>
- **An:** kontakt@mein-seelenfunke.de
- **Datum:** 2026-09-21 13:45:29
- **Kategorie:** Vertrag & Finanzen
- **Priorität:** high
- **KI-Zusammenfassung:** Ein interner Finanzbericht für September 2026 von Mein-Seelenfunke-Stage. Der Inhalt ist ein HTML-Snippet.
- **Handlungsbedarf:** Finanzbericht zur Prüfung: Finanzbericht für September 2026 prüfen

---

## Nachrichtentext
 

 

 

     

     

     Finanzb',
                'full_text' => '# Finanzbericht 9/2026

- **Von:** Mein-Seelenfunke-Stage <kontakt@mein-seelenfunke.de>
- **An:** kontakt@mein-seelenfunke.de
- **Datum:** 2026-09-21 13:45:29
- **Kategorie:** Vertrag & Finanzen
- **Priorität:** high
- **KI-Zusammenfassung:** Ein interner Finanzbericht für September 2026 von Mein-Seelenfunke-Stage. Der Inhalt ist ein HTML-Snippet.
- **Handlungsbedarf:** Finanzbericht zur Prüfung: Finanzbericht für September 2026 prüfen

---

## Nachrichtentext
 

 

 

     

     

     Finanzbericht 9/2026 

     

        body {

            font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif;

            background-color: #f4f4f4;

            margin: 0;

            padding: 0;

            color: #333;

        }

        .container {

            max-width: 600px;

            margin: 40px auto;

            background-color: #ffffff;

            border-radius: 8px;

            overflow: hidden;

            box-shadow: 0 2px 10px rgba(0,0,0,0.1);

        }

        .header {

            background-color: #ffffff;

            padding: 30px 40px;

            text-align: center;

            border-bottom: 3px solid #C5A059;

        }

        .header h1 {

            color: #C5A059;

            margin: 0;

            font-size: 22px;

            font-family: serif;

        }

        .content {

            padding: 40px;

            line-height: 1.6;

        }

        .agent-badge {

            display: inline-block;

            background-color: #f3f4f6;

            color: #4b5563;

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1px;

            padding: 4px 10px;

            border-radius: 12px;

            margin-bottom: 20px;

            border: 1px solid #e5e7eb;

        }

        .message-box {

            background-color: #f9f9f9;

            padding: 20px;

            border-left: 4px solid #C5A059;

            border-radius: 4px;

            margin-top: 20px;

            margin-bottom: 20px;

            white-space: pre-wrap; /* Wichtig für Zeilenumbrüche aus Text */

        }

        .footer {

            background-color: #f9f9f9;

            padding: 20px;

            text-align: center;

            font-size: 12px;

            color: #888;

            border-top: 1px solid #eee;

        }

     

 

 

 

     

         Nachricht von Mein-Seelenfunke-Stage 

     



     

         

            Generiert von Agent:  System-Agent 

   ',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Finanzen/2026-09-30_Rechnung_2026-987_F_lligkeit_15.10.2026.md',
                'sha256' => 'e73f3955b417c0dcfda5d49e566c5d34a151bbdbb49350961f389c90b9d16ccc',
                'filename' => '2026-09-30_Rechnung_2026-987_F_lligkeit_15.10.2026.md',
                'file_type' => 'md',
                'file_size' => 508,
                'title' => '2026 09 30 Rechnung 2026 987 F Lligkeit 15.10.2026',
                'category' => 'Allgemein',
                'tags' => [],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Allgemein.',
                'summary' => '',
                'key_facts' => [],
                'extracted_date' => null,
                'content_preview' => '# Rechnung 2026-987 Fälligkeit 15.10.2026

- **Von:** Host Europe <buchhaltung@host-europe-service.de>
- **An:** me@test.de
- **Datum:** 2026-09-30 07:30:42
- **Kategorie:** Rechnung
- **Priorität:** high
- **KI-Zusammenfassung:** Eine Rechnung von Host Europe über 49,99€ mit Fälligkeitsdatum 15.10.2026.
- **Handlungsbedarf:** Zahlungsfrist bis 15.10.2026: Rechnung prüfen und Zahlung veranlassen

---

## Nachrichtentext
Bitte überweisen Sie den Betrag von 49,99€ auf unsere IBAN.',
                'full_text' => '# Rechnung 2026-987 Fälligkeit 15.10.2026

- **Von:** Host Europe <buchhaltung@host-europe-service.de>
- **An:** me@test.de
- **Datum:** 2026-09-30 07:30:42
- **Kategorie:** Rechnung
- **Priorität:** high
- **KI-Zusammenfassung:** Eine Rechnung von Host Europe über 49,99€ mit Fälligkeitsdatum 15.10.2026.
- **Handlungsbedarf:** Zahlungsfrist bis 15.10.2026: Rechnung prüfen und Zahlung veranlassen

---

## Nachrichtentext
Bitte überweisen Sie den Betrag von 49,99€ auf unsere IBAN.',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Steuern/Steuererklärung für 2025.pdf',
                'sha256' => '26b42297c83c32609576c79f88791bc4cbaf669fa239d0fd284a47aa363203da',
                'filename' => 'Steuererklärung für 2025.pdf',
                'file_type' => 'pdf',
                'file_size' => 115196,
                'title' => 'Steuererklärung Für 2025',
                'category' => 'Finanzen & Verträge',
                'tags' => ['Finanzen & Verträge', 'Rechnung', 'Steuer', 'Versicherung'],
                'purpose' => 'Steuerdokumentation (Steuererklärung / Steuerbescheid) zur Vermögens- und Ertragsübersicht.',
                'summary' => 'Finanzen & Verträge: -- Vorschau - Vorschau - Vorschau -- 2025 *** Vorschau *** *** Vorschau *** *** Vorschau *** *** Vorschau *** *** Vorschau *** smartsteuer gedruckt mit ERiC-Print 43.4.6/ESt_2025 43.4.6 Steuernummer 19/143/23928Finanzamt Gifhorn Hauptvordruck ESt 1 A Art der Erklärung Einkommensteuererklärung Angabe',
                'key_facts' => [],
                'extracted_date' => '1993-09-01',
                'content_preview' => '-- Vorschau - Vorschau - Vorschau --
2025
*** Vorschau *** *** Vorschau *** *** Vorschau ***
*** Vorschau *** *** Vorschau ***
smartsteuer gedruckt mit ERiC-Print 43.4.6/ESt_2025 43.4.6
Steuernummer 19/143/23928Finanzamt Gifhorn
Hauptvordruck ESt 1 A
Art der Erklärung
Einkommensteuererklärung
Angaben zu Belegen
Grundsätzlich brauchen Sie keine Belege mit Ihrer Steuererklärung einzureichen. Bitte bewahren Sie Ihre Unterlagen
für eventuelle Rückfragen auf und reichen diese nur auf Anforderung durch Ihr Finanzamt nach. Beabsichtigen Sie
dennoch Belege einzureichen, kreuzen Sie bitte das nachfolgende Feld an. Reichen Sie Belege und separate
Aufstellungen nur dann ein, wenn sich dies ausdrücklich aus den Vordrucken und Anleitungen ergibt.
Bitte übermitteln Sie Belege und andere Dokumente zur Steuererklärung möglichst elektronisch (Belegnachreichung
zur Steuererklärung). Dies ist sowohl über Mein ELSTER (www.elster.de) als auch über Software anderer Anbieter
möglich. Falls Sie Ihrem Finanzamt Belege in Papierform übermitteln möchten, reichen Sie bitte ausschließlich Kopien
ein. Bitte übersenden Sie keine Originalbelege.
Digitale Belege können Sie auch direkt mit den entsprechenden Eingabefeldern Ihrer Einkommensteuererklärung in
Mein ELSTER verknüpfen. Ihr Finanzamt kann dann diese Belege bei der Bearbeitung Ihrer Einkommensteuererklärung
eigenständig und zeitsparend abrufen, ohne dass Ihnen zusätzlicher Aufwand entsteht. Das Verknüpfen von Belegen
ist auch über Software anderer Anbieter möglich, soweit diese die Funktion technisch unterstützen. Sofern Ihr
Finanzamt Belege abgerufen hat, wird Ihnen dies in Mein ELSTER oder über Software anderer Anbieter angezeigt.
Belege werden nachgereicht.
Allgemeine Angaben
Steuerpflichtige Person. Nur bei Zusammenveranlagung: Ehemann oder Person A
(Ehepartner/-in A / Lebenspartner/-in A nach dem LPartG)
Bitte Anleitung beachten.
8 Identifikationsnummer 66324780911
8 Geburtsdatum 01.09.1993
9 Name Steinhauer
10 Vorname Alina
11 Religio',
                'full_text' => '-- Vorschau - Vorschau - Vorschau --
2025
*** Vorschau *** *** Vorschau *** *** Vorschau ***
*** Vorschau *** *** Vorschau ***
smartsteuer gedruckt mit ERiC-Print 43.4.6/ESt_2025 43.4.6
Steuernummer 19/143/23928Finanzamt Gifhorn
Hauptvordruck ESt 1 A
Art der Erklärung
Einkommensteuererklärung
Angaben zu Belegen
Grundsätzlich brauchen Sie keine Belege mit Ihrer Steuererklärung einzureichen. Bitte bewahren Sie Ihre Unterlagen
für eventuelle Rückfragen auf und reichen diese nur auf Anforderung durch Ihr Finanzamt nach. Beabsichtigen Sie
dennoch Belege einzureichen, kreuzen Sie bitte das nachfolgende Feld an. Reichen Sie Belege und separate
Aufstellungen nur dann ein, wenn sich dies ausdrücklich aus den Vordrucken und Anleitungen ergibt.
Bitte übermitteln Sie Belege und andere Dokumente zur Steuererklärung möglichst elektronisch (Belegnachreichung
zur Steuererklärung). Dies ist sowohl über Mein ELSTER (www.elster.de) als auch über Software anderer Anbieter
möglich. Falls Sie Ihrem Finanzamt Belege in Papierform übermitteln möchten, reichen Sie bitte ausschließlich Kopien
ein. Bitte übersenden Sie keine Originalbelege.
Digitale Belege können Sie auch direkt mit den entsprechenden Eingabefeldern Ihrer Einkommensteuererklärung in
Mein ELSTER verknüpfen. Ihr Finanzamt kann dann diese Belege bei der Bearbeitung Ihrer Einkommensteuererklärung
eigenständig und zeitsparend abrufen, ohne dass Ihnen zusätzlicher Aufwand entsteht. Das Verknüpfen von Belegen
ist auch über Software anderer Anbieter möglich, soweit diese die Funktion technisch unterstützen. Sofern Ihr
Finanzamt Belege abgerufen hat, wird Ihnen dies in Mein ELSTER oder über Software anderer Anbieter angezeigt.
Belege werden nachgereicht.
Allgemeine Angaben
Steuerpflichtige Person. Nur bei Zusammenveranlagung: Ehemann oder Person A
(Ehepartner/-in A / Lebenspartner/-in A nach dem LPartG)
Bitte Anleitung beachten.
8 Identifikationsnummer 66324780911
8 Geburtsdatum 01.09.1993
9 Name Steinhauer
10 Vorname Alina
11 Religion am 31.12.2025 nicht
kirchensteuerpflichtig
12 Ausgeübter Beruf Krank geschrieben
13 Straße (derzeitige Adresse) Carl-Goerdeler-Ring
14 Hausnummer 26
15 Postleitzahl (Inland) 38518
16 Wohnort Gifhorn
Bankverbindung
30 IBAN (inländisches Geldinstitut) DE852699106685831960
00
Kontoinhaber/-in
33 Steuerpflichtige Person / Ehemann / Person A
Einkommensersatzleistungen (Steuerpflichtige Person / Ehemann / Person
A)
– ohne Beträge laut Zeile 20 der Anlage N –
Seite 1 von 7
Datum der Ausfertigung: 26.',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Bank/Umsaetze_DE85269910668583196000_02-09-2026_10-29.pdf',
                'sha256' => 'ef0e6c04fb7da41641744ab488e4840c5f15dc42182c9f7a6e161dafe373453f',
                'filename' => 'Umsaetze_DE85269910668583196000_02-09-2026_10-29.pdf',
                'file_type' => 'pdf',
                'file_size' => 2490779,
                'title' => 'Kontoauszug Girokonto (Umsaetze_DE85269910668583196000_02-09-2026_10-29.pdf)',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanzen & Verträge', 'Bankauszug', 'Girokonto', 'Umsätze'],
                'purpose' => 'Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.',
                'summary' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
                'key_facts' => [],
                'extracted_date' => '2026-09-02',
                'content_preview' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
                'full_text' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
            ],
            [
                'file_path' => 'agenten/workspace/Dokumente/Bank/Umsaetze_DE85269910668583196000_2026.09.02.csv',
                'sha256' => '77c5c6494fe2ab82a2f75a94fb4bf486cfcf36734e9b22478abcb40ca53a57cd',
                'filename' => 'Umsaetze_DE85269910668583196000_2026.09.02.csv',
                'file_type' => 'csv',
                'file_size' => 251030,
                'title' => 'Kontoauszug Girokonto (Umsaetze_DE85269910668583196000_2026.09.02.csv)',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanzen & Verträge', 'Bankauszug', 'Girokonto', 'Umsätze'],
                'purpose' => 'Bankumsatz- und Kontoauszugsnachweis für das Finanz-Audit und zur lückenlosen Dokumentation der Geldeingänge und Außenstände.',
                'summary' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
                'key_facts' => [],
                'extracted_date' => '2026-09-02',
                'content_preview' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
                'full_text' => 'Offizieller Bankauszug / Umsatzaufstellung des Girokontos DE85269910668583196000 mit allen Gehalts-, Krankengeld- und ALG-1-Zahlungen bis September 2026.',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Arbeitsunfähigkeit.txt',
                'sha256' => '0f7cb72d29cb0564366d40e6d75ef0b298015d3ce1ca92a94c6e5082b53365c2',
                'filename' => 'Arbeitsunfähigkeit.txt',
                'file_type' => 'txt',
                'file_size' => 14958,
                'title' => 'Vollständiges Chatprotokoll: Arbeitsunfähigkeit',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Widerspruch', 'Auszahlung', '§ 44 SGB V', 'Entgeltersatz', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Arbeitsunfähigkeit.',
                'key_facts' => [],
                'extracted_date' => '2023-06-20',
                'content_preview' => 'Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

20.06.2023, 13:14:13
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 30.04.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

03.05.2024, 05:35:35
Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

06.05.2024, 12:06:56
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 30.04.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

08.05.2024, 05:34:09
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 12.07.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

31.07.2024, 06:10:44
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 06.08.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

08.08.2024, 05:07:57
Icon für Absender
sehr geehrte Damen und Herren ich bitte um schnellstmögliche fortlaufende Auszahlung des Krankengeldes.

Da ich schon länger krank bin, beziehe ich aktuell kein ALG 1 mehr.

vielen Dank.


Gruß
Steinhauer

AufhebungsbescheidALG1wegenKrankheit1.pdf (1338 KB)
02.09.2024, 11:08:15
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht und die Informationen. Sofern das Krankengeld berechnet wurde erhalten Sie zeitnah eine Rückmeldung.

Freundliche Grüße

Ihre BKK firmus

03.09.2024, 10:19:05
Icon für Absender
Anbei erhalten Sie das geforderte Formular ausgefüllt zurück.

03092024202346_9650072456583_ausgefuellt.pdf (364 KB)
03.09.2024, 20:38:01
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 02.09.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

04.09.2024, 05:44:07
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Leider ist',
                'full_text' => 'Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

20.06.2023, 13:14:13
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 30.04.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

03.05.2024, 05:35:35
Icon für Absender
Eine neue Arbeitsunfähigkeitsbescheinigung wurde eingereicht. Bitte bewahren Sie den Originalbeleg für eventuelle Rückfragen auf.

06.05.2024, 12:06:56
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 30.04.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

08.05.2024, 05:34:09
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 12.07.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

31.07.2024, 06:10:44
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 06.08.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

08.08.2024, 05:07:57
Icon für Absender
sehr geehrte Damen und Herren ich bitte um schnellstmögliche fortlaufende Auszahlung des Krankengeldes.

Da ich schon länger krank bin, beziehe ich aktuell kein ALG 1 mehr.

vielen Dank.


Gruß
Steinhauer

AufhebungsbescheidALG1wegenKrankheit1.pdf (1338 KB)
02.09.2024, 11:08:15
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht und die Informationen. Sofern das Krankengeld berechnet wurde erhalten Sie zeitnah eine Rückmeldung.

Freundliche Grüße

Ihre BKK firmus

03.09.2024, 10:19:05
Icon für Absender
Anbei erhalten Sie das geforderte Formular ausgefüllt zurück.

03092024202346_9650072456583_ausgefuellt.pdf (364 KB)
03.09.2024, 20:38:01
Icon für Absender
Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 02.09.2024 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen

04.09.2024, 05:44:07
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Leider ist das Formular als Anlage nicht einsehbar. Gerne können Sie dies erneut hochladen.

Freundliche Grüße

Ihre BKK firmus

04.09.2024, 07:12:24
Icon für Absender
Anbei das PDF Formular als einzelne PNG Dateien. Bitte um schnelle Bearbeitung.

Seite1.png (150 KB)
Seite2.png (109 KB)
Seite3.png (41 KB)
Seite4.png (105 KB)
05.09.2024, 18:32:47
Icon für Absender
Das ist sehr nett das sie 12 Therapie Stunden übernehmen. ich bin ja aktuell schon in Therapie.

wie sieht es mit dem Krankengeld aus?

06.09.2',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Familienversicherung.txt',
                'sha256' => '423305094405e58fe55881e1d2ac476156a83d2ed0fc3aadae497181a9e20934',
                'filename' => 'Familienversicherung.txt',
                'file_type' => 'txt',
                'file_size' => 727,
                'title' => 'Vollständiges Chatprotokoll: Familienversicherung',
                'category' => 'Familienversicherung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Familienversicherung', 'Mitgliedschaft', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Familienversicherung.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Familienversicherung.',
                'key_facts' => [],
                'extracted_date' => '2025-06-01',
                'content_preview' => 'Icon für Absender
Hallo lieber Support,

bitte ändern Sie die Wohnadresse von Noah Steinhauer (KV-Nr.: Z838294313) zum 01.06.2025 in:
Rigaer Str. 18 in 38542 Leiferde

Vielen Dank.


Gruß,
Steinhauer

22.05.2025, 20:34:10
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage.

Die Adresse wird geändert.

Freundliche Grüße

Ihre BKK firmus

23.05.2025, 11:19:32
Icon für Absender
Der Familienfragebogen (Bestandspflege) befindet sich im Anhang

BestandspflegeFamilienversicherung.pdf (65 KB)
30.01.2026, 10:08:24
Icon für Absender
Haben Sie Noah Steinhauer aus der Versicherung ausgeschlossen? Mein Sohn wird mir nicht mehr unter "Meine Familie" angezeigt?

09.09.2026, 17:20:27',
                'full_text' => 'Icon für Absender
Hallo lieber Support,

bitte ändern Sie die Wohnadresse von Noah Steinhauer (KV-Nr.: Z838294313) zum 01.06.2025 in:
Rigaer Str. 18 in 38542 Leiferde

Vielen Dank.


Gruß,
Steinhauer

22.05.2025, 20:34:10
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage.

Die Adresse wird geändert.

Freundliche Grüße

Ihre BKK firmus

23.05.2025, 11:19:32
Icon für Absender
Der Familienfragebogen (Bestandspflege) befindet sich im Anhang

BestandspflegeFamilienversicherung.pdf (65 KB)
30.01.2026, 10:08:24
Icon für Absender
Haben Sie Noah Steinhauer aus der Versicherung ausgeschlossen? Mein Sohn wird mir nicht mehr unter "Meine Familie" angezeigt?

09.09.2026, 17:20:27',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Krankengeld.txt',
                'sha256' => '8fd1e8d1d291ee7392a442e1e7a888f6d0fa517423ec960fd8efc81d35b618b1',
                'filename' => 'Krankengeld.txt',
                'file_type' => 'txt',
                'file_size' => 16687,
                'title' => 'Vollständiges Chatprotokoll: Krankengeld',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Ablehnungsbescheid', 'Krankengeld', 'Widerspruch', 'Auszahlung', '§ 44 SGB V', 'Entgeltersatz', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Krankengeld.',
                'key_facts' => [],
                'extracted_date' => '2024-10-22',
                'content_preview' => 'Guten Tag,

vielen Dank für Ihre Nachricht.

Leider können wir Ihre Nachricht nicht zuordnen.

Um welche Erstattung handelt es sich bei Ihnen? Um die aktuelle Krankengeldzahlung ?


Freundliche Grüße

Ihre BKK firmus

22.10.2024, 13:03:46
Icon für Absender
Bezogen auf den Widerspruch?

23.10.2024, 00:43:31
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Das Schreiben vom 21.10.2024 hat sich auf Ihre Email vom 18.10.2024 bezogen.

Freundliche Grüße

Ihre BKK firmus

23.10.2024, 14:03:09
Icon für Absender
Guten Tag,

bezüglich Ihrer Arbeitsunfähigkeitszeiträume hätten wir noch einmal eine Frage an Sie. Sind Sie seitdem 18.06.2024 durchgängig Arbeitsunfähig ?

Vielen Dank.

Freundliche Grüße

Ihre BKK firmus

23.10.2024, 17:27:21
Icon für Absender
eigentlich nein. die Durchgängigkeit startet am 30.07 da auch da das Arbeitsamt offiziell den Übergang ins Krankengeld definiert hat.

23.10.2024, 20:18:31
Icon für Absender
Guten Tag,

vielen Dank für Ihre Rückmeldung.

Also in dem Zeitraum 09.07.2024 bis zum 11.07.2024 waren Sie nicht Arbeitsunfähig ?

Freundliche Grüße

Ihre BKK firmus

24.10.2024, 09:05:31
Icon für Absender
Wo haben Sie diesen Zeitraum (09.07.2024 bis zum 11.07.2024 ) her?

Also ich habe in meinen Unterlagen eine Krankmeldung vom 12.07.2024 - 02.08.2024...

Also krank war ich da wohl nicht :)

PS: Bitte beachten Sie meine aktuellste und angepasste Folgekrankmeldung. Ich hatte schon ein Widerspruch gegen die Beendigung des Krankengeldes eingereicht (Brief ist unterwegs).

Daher hoffe ich, dass aufgrund der neuen Krankmeldung einer unproblematischer weiteren Auszahlung des Krankengeldes nichts im Wege steht. Ich bin wirklich echt angeschlagen und finde es nur zusätzlich sehr belastend soviel (hin und her) mit Arzt und Krankenkasse zu betreiben.

25.10.2024, 12:19:18
Icon für Absender
Guten Tag,

vielen Dank für die Rückmeldung.

Sobald wir den Widerspruch von Ihnen erhalten haben, werden wir uns bei Ihnen melden.

Freundliche Grüße

Ihre BKK f',
                'full_text' => 'Guten Tag,

vielen Dank für Ihre Nachricht.

Leider können wir Ihre Nachricht nicht zuordnen.

Um welche Erstattung handelt es sich bei Ihnen? Um die aktuelle Krankengeldzahlung ?


Freundliche Grüße

Ihre BKK firmus

22.10.2024, 13:03:46
Icon für Absender
Bezogen auf den Widerspruch?

23.10.2024, 00:43:31
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Das Schreiben vom 21.10.2024 hat sich auf Ihre Email vom 18.10.2024 bezogen.

Freundliche Grüße

Ihre BKK firmus

23.10.2024, 14:03:09
Icon für Absender
Guten Tag,

bezüglich Ihrer Arbeitsunfähigkeitszeiträume hätten wir noch einmal eine Frage an Sie. Sind Sie seitdem 18.06.2024 durchgängig Arbeitsunfähig ?

Vielen Dank.

Freundliche Grüße

Ihre BKK firmus

23.10.2024, 17:27:21
Icon für Absender
eigentlich nein. die Durchgängigkeit startet am 30.07 da auch da das Arbeitsamt offiziell den Übergang ins Krankengeld definiert hat.

23.10.2024, 20:18:31
Icon für Absender
Guten Tag,

vielen Dank für Ihre Rückmeldung.

Also in dem Zeitraum 09.07.2024 bis zum 11.07.2024 waren Sie nicht Arbeitsunfähig ?

Freundliche Grüße

Ihre BKK firmus

24.10.2024, 09:05:31
Icon für Absender
Wo haben Sie diesen Zeitraum (09.07.2024 bis zum 11.07.2024 ) her?

Also ich habe in meinen Unterlagen eine Krankmeldung vom 12.07.2024 - 02.08.2024...

Also krank war ich da wohl nicht :)

PS: Bitte beachten Sie meine aktuellste und angepasste Folgekrankmeldung. Ich hatte schon ein Widerspruch gegen die Beendigung des Krankengeldes eingereicht (Brief ist unterwegs).

Daher hoffe ich, dass aufgrund der neuen Krankmeldung einer unproblematischer weiteren Auszahlung des Krankengeldes nichts im Wege steht. Ich bin wirklich echt angeschlagen und finde es nur zusätzlich sehr belastend soviel (hin und her) mit Arzt und Krankenkasse zu betreiben.

25.10.2024, 12:19:18
Icon für Absender
Guten Tag,

vielen Dank für die Rückmeldung.

Sobald wir den Widerspruch von Ihnen erhalten haben, werden wir uns bei Ihnen melden.

Freundliche Grüße

Ihre BKK firmus

28.10.2024, 07:49:13
Icon für Absender
Mir wurde eine Überweisung von meiner Hausärztin zum Psychiater gegeben.

Ich habe tatsächlich einen Termin zeitnah bekommen. Ich war am Montag den 20.01.2025 im Psychiatriezentrum (AWO) Psychiatrische Instituts-ambulanz (PIA) Gifhorn.

Adresse: Campus 7, 38518 Gifhorn

Telefon: 05371 9381214
Email: pia-gifhorn@awo-apz.de

Mir wurde nachdem Termin gesagt, dass sich jetzt meine Krankenkasse bei denen melden soll damit sie den Bericht bzgl. meiner Depr',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Krankenhaus.txt',
                'sha256' => '7d9fff1f3810c69baeac140c04844e749280ee3f75242bb870fad19969cbfb9b',
                'filename' => 'Krankenhaus.txt',
                'file_type' => 'txt',
                'file_size' => 7951,
                'title' => 'Vollständiges Chatprotokoll: Krankenhaus',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Ablehnungsbescheid', '§ 44 SGB V', 'Medizinischer Dienst (MD)', 'Lasertherapie', 'Widerspruch', '§ 13 Abs. 3 SGB V', 'Barthaare', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Krankenhaus.',
                'key_facts' => [],
                'extracted_date' => '2023-11-09',
                'content_preview' => 'Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht.
Für die Prüfung einer möglichen Kostenübernahme von geschlechtsangleichenden Operationen werden folgende Unterlagen von uns benötigt:

-Arztbericht über gegengeschlechtliche Hormontherapie
-Formloser Antrag vom Kunden auf Kostenübernahme
-Aktueller Psychotherapiebericht mit Angaben zum Befund, zum Therapieverlauf, zum begleitenden Alltagstest in allen psychosozialen Lebensbereichen, zum Erreichen der Therapieziele und zur prognostischen Einschätzung im Falle der Durchführung der Operation
-Gynäkologischer Befundbericht zum Ausschluss relevanter Differentialdiagnosen und Komorbiditäten

Sobald Ihnen alle Unterlagen vorliegen, können Sie diese gesammelt bei uns einreichen.

Freundliche Grüße

Ihre BKK firmus

09.11.2023, 13:59:42
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage. Ihre Nachricht wurde in die Fachabteilung weitergeleitet und Sie werden Anfang der kommenden Woche eine Rückmeldung erhalten.

Freundliche Grüße

Ihre BKK firmus

24.01.2025, 12:59:55
Icon für Absender
Ich habe nochmal eine Verständnisfrage: Ist ihnen bewusst das die Operation dich auf "Mann zu Frau" bezieht? Ich bin ja trans und werde körperlich von einem "Mann" zu einer "Frau".

meine Therapeutin und ich sind verwirrt, da ein Gynäkologischer Befundbericht benötigt wird.

Bitte hier um Rückmeldung. Vielen Dank.

17.02.2025, 19:25:15
Icon für Absender
Sehr geehrte Damen und Herren,

am 01.06.2025 habe ich einen Antrag auf Kostenübernahme für geschlechtsangleichende Operationen gestellt. In Ihrer Mitteilung kündigten Sie eine Entscheidung bis zum 08.07.2025 an.

Da bis heute keine Entscheidung eingegangen ist und auch keine ordnungsgemäße Fristverlängerung mit Begründung gemäß § 13 Abs. 3a SGB V erfolgte, gehe ich davon aus, dass die beantragte Leistung als genehmigt gilt (Genehmigungsfiktion).

Ich habe daher bereits Kontakt mit der Lubos Klinik in München aufgenommen, um die weiteren Schritte zur OP-Terminvereinbarung zu',
                'full_text' => 'Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht.
Für die Prüfung einer möglichen Kostenübernahme von geschlechtsangleichenden Operationen werden folgende Unterlagen von uns benötigt:

-Arztbericht über gegengeschlechtliche Hormontherapie
-Formloser Antrag vom Kunden auf Kostenübernahme
-Aktueller Psychotherapiebericht mit Angaben zum Befund, zum Therapieverlauf, zum begleitenden Alltagstest in allen psychosozialen Lebensbereichen, zum Erreichen der Therapieziele und zur prognostischen Einschätzung im Falle der Durchführung der Operation
-Gynäkologischer Befundbericht zum Ausschluss relevanter Differentialdiagnosen und Komorbiditäten

Sobald Ihnen alle Unterlagen vorliegen, können Sie diese gesammelt bei uns einreichen.

Freundliche Grüße

Ihre BKK firmus

09.11.2023, 13:59:42
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage. Ihre Nachricht wurde in die Fachabteilung weitergeleitet und Sie werden Anfang der kommenden Woche eine Rückmeldung erhalten.

Freundliche Grüße

Ihre BKK firmus

24.01.2025, 12:59:55
Icon für Absender
Ich habe nochmal eine Verständnisfrage: Ist ihnen bewusst das die Operation dich auf "Mann zu Frau" bezieht? Ich bin ja trans und werde körperlich von einem "Mann" zu einer "Frau".

meine Therapeutin und ich sind verwirrt, da ein Gynäkologischer Befundbericht benötigt wird.

Bitte hier um Rückmeldung. Vielen Dank.

17.02.2025, 19:25:15
Icon für Absender
Sehr geehrte Damen und Herren,

am 01.06.2025 habe ich einen Antrag auf Kostenübernahme für geschlechtsangleichende Operationen gestellt. In Ihrer Mitteilung kündigten Sie eine Entscheidung bis zum 08.07.2025 an.

Da bis heute keine Entscheidung eingegangen ist und auch keine ordnungsgemäße Fristverlängerung mit Begründung gemäß § 13 Abs. 3a SGB V erfolgte, gehe ich davon aus, dass die beantragte Leistung als genehmigt gilt (Genehmigungsfiktion).

Ich habe daher bereits Kontakt mit der Lubos Klinik in München aufgenommen, um die weiteren Schritte zur OP-Terminvereinbarung zu klären.

Ich bitte Sie dennoch, mir eine formelle Kostenzusage schriftlich zukommen zu lassen, damit die Unterlagen vollständig vorliegen.

Vielen Dank im Voraus.

Mit freundlichen Grüßen
Alina Steinhauer

09.07.2025, 11:25:43
Icon für Absender
Hier noch der Widerspruch:

Sehr geehrte Damen und Herren,

gegen Ihren Ablehnungsbescheid vom 07.07.2025 lege ich hiermit fristgerecht Widerspruch ein.

Sie hatten mir zuvor mitgeteilt, dass ich bis zum 08.07.2025 mit einer Entscheidung rechnen könne. De',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650060300010.pdf',
                'sha256' => '9d1810c901047a58a61f19ab2640e3afdc11ad755ac81c0a073c7be01195fe55',
                'filename' => '9650060300010.pdf',
                'file_type' => 'pdf',
                'file_size' => 229244,
                'title' => 'Eingangsbestätigung: Antrag auf Kostenübernahme Barthaarentfernung mit Laser',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Barthaarentfernung', 'Lasertherapie', 'MDK-Begutachtung', 'Antrag', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'BKK firmus bestätigt den Eingang des Antrags auf Kostenübernahme für die Barthaarentfernung mittels Lasertherapie. Der Antrag wird zur Begutachtung an den Medizinischen Dienst (MD) weitergeleitet.',
                'key_facts' => ['antrag' => 'Barthaarentfernung Laser', 'status' => 'Weiterleitung an MD', 'datum' => '23.01.2024'],
                'extracted_date' => '2024-01-23',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Barthaarentfernung mit Laser 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.01.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650060300010',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Barthaarentfernung mit Laser 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.01.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650060300010',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650061225545.pdf',
                'sha256' => '1d713e935e2e2c2dfca06e3ae1894622c1b5a4de5c802dddc0aaee1005eb7cdb',
                'filename' => '9650061225545.pdf',
                'file_type' => 'pdf',
                'file_size' => 417321,
                'title' => 'Zwischennachricht: Hormonersatztherapie (HRT) Begutachtung beim MD',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Hormontherapie', 'HRT', 'MD-Begutachtung', 'Endokrinologie', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'Mitteilung der BKK firmus, dass sich der Antrag und die ärztlichen Unterlagen zur Hormonersatztherapie (HRT) derzeit noch in der sozialmedizinischen Begutachtung beim Medizinischen Dienst befinden.',
                'key_facts' => ['thema' => 'Hormonersatztherapie', 'status' => 'In Begutachtung beim MD', 'datum' => '05.02.2024'],
                'extracted_date' => '2024-02-05',
                'content_preview' => '--- Seite 1 ---
Hormonersatztherapie 
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht.  
 
Ihr Antrag befindet sich derzeit noch zur Begutachtung beim Medizinischen Dienst (MD). 
Sobald wir eine Rückmeldung erhalten haben, werden wir uns mit Ihnen in Verbindung 
setzen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Rufen Sie uns an 
oder schauen Sie bei uns vorbei. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
05.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANFOR',
                'full_text' => '--- Seite 1 ---
Hormonersatztherapie 
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht.  
 
Ihr Antrag befindet sich derzeit noch zur Begutachtung beim Medizinischen Dienst (MD). 
Sobald wir eine Rückmeldung erhalten haben, werden wir uns mit Ihnen in Verbindung 
setzen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Rufen Sie uns an 
oder schauen Sie bei uns vorbei. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
05.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANFOR',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650062101659.pdf',
                'sha256' => 'db2690c9c7b26276c84cfa0e3b8759caf2f126296b93fff850872c245955f318',
                'filename' => '9650062101659.pdf',
                'file_type' => 'pdf',
                'file_size' => 229229,
                'title' => 'Eingangsbestätigung: Unterlagen zur Haarentfernung an den MD gesandt',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Haarentfernung', 'Epilation', 'MD-Prüfung', 'Unterlageneingang', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'BKK firmus bestätigt die Einreichung weiterer Unterlagen zur Haarentfernung und teilt mit, dass diese zur Prüfung an den Medizinischen Dienst weitergeleitet wurden.',
                'key_facts' => ['thema' => 'Haarentfernung Unterlagen', 'status' => 'Weiterleitung MD', 'datum' => '19.02.2024'],
                'extracted_date' => '2024-02-19',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Haarentfernung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
19.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650062101659',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme Haarentfernung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
19.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650062101659',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650062820219.pdf',
                'sha256' => '2b3e230ebb3518a8e0f207bd6379a3a43283fa48ea42c30aebeefbfd7d5cbf49',
                'filename' => '9650062820219.pdf',
                'file_type' => 'pdf',
                'file_size' => 229025,
                'title' => 'Zwischenbescheid: Antrag auf Kostenübernahme in Prüfung beim MD',
                'category' => 'Allgemeiner Schriftverkehr',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Kostenübernahme', 'MD-Begutachtung', 'Zwischenbescheid', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Allgemeiner Schriftverkehr\'.',
                'summary' => 'Mitteilung über den aktuellen Bearbeitungsstand des Antrags auf Kostenübernahme und die Begutachtung durch den Medizinischen Dienst Niedersachsen.',
                'key_facts' => ['thema' => 'Kostenübernahme', 'status' => 'MD Begutachtung läuft', 'datum' => '28.02.2024'],
                'extracted_date' => '2024-02-28',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
28.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650062820219',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
28.02.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650062820219',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650064287652.pdf',
                'sha256' => '57fce8698d7ad3bf55e3658e9340d16c2553b027dca03d8d509d2731d0817dfc',
                'filename' => '9650064287652.pdf',
                'file_type' => 'pdf',
                'file_size' => 421189,
                'title' => 'Prüfmitteilung: Kostenübernahme Barthaarentfernung mit Laser',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Barthaarentfernung', 'Lasertherapie', 'Voraussetzungen', 'MD-Gutachten', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'Detaillierte Mitteilung der BKK firmus zur beantragten Barthaarentfernung mit Laser. Erläuterung der gesetzlichen Voraussetzungen und der Begutachtungsschritte des Medizinischen Dienstes.',
                'key_facts' => ['thema' => 'Barthaarentfernung Laser', 'status' => 'Prüfung gesetzliche Voraussetzungen', 'datum' => '21.03.2024'],
                'extracted_date' => '2024-03-21',
                'content_preview' => '--- Seite 1 ---
Kostenübernahme Barthaarentfernung mit Laser
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf  Kostenübernahme. 
 
Damit der Medizinische Dienst ( MD ) die Bearbeitung fortsetzen kann, benötigt dieser noch 
die folgenden Unterlagen: 
 
- 
Foto Dokumentation der von Haaren zu entfernenden Körperregionen  
- 
Dermatologischer Befundbericht hinsichtlich Indikation/ Durchführung der 
Haarentfernung  
- 
Endokrinologischer/urologischer Befund/Verlaufsbericht  
 
Sobald wir Ihre Unterlagen bekommen haben, erhalten Sie kurzfristig Nachricht von uns. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Rufen Sie uns an 
oder schauen Sie bei uns vorbei. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
21.03.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANFOR 
9650064287652',
                'full_text' => '--- Seite 1 ---
Kostenübernahme Barthaarentfernung mit Laser
 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf  Kostenübernahme. 
 
Damit der Medizinische Dienst ( MD ) die Bearbeitung fortsetzen kann, benötigt dieser noch 
die folgenden Unterlagen: 
 
- 
Foto Dokumentation der von Haaren zu entfernenden Körperregionen  
- 
Dermatologischer Befundbericht hinsichtlich Indikation/ Durchführung der 
Haarentfernung  
- 
Endokrinologischer/urologischer Befund/Verlaufsbericht  
 
Sobald wir Ihre Unterlagen bekommen haben, erhalten Sie kurzfristig Nachricht von uns. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Rufen Sie uns an 
oder schauen Sie bei uns vorbei. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Annalena Berndsen 
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
21.03.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANFOR 
9650064287652',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650067808155.pdf',
                'sha256' => '5be0d6fec7b5ae36738ba1ae642a62d7420e4a5d38c23770f2aab456b5b16523',
                'filename' => '9650067808155.pdf',
                'file_type' => 'pdf',
                'file_size' => 228474,
                'title' => 'Eingangsbestätigung: Antrag auf Stimmanpassung (Logopädie)',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Stimmanpassung', 'Logopädie', 'Stimmtherapie', 'MD-Prüfung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'Bestätigung des Eingangs des Antrags auf Kostenübernahme für stimmanpassende Maßnahmen (Logopädie/Stimmtherapie). Unterlagen wurden zur sozialmedizinischen Prüfung an den MD gesandt.',
                'key_facts' => ['thema' => 'Stimmanpassung / Logopädie', 'status' => 'Weiterleitung MD', 'datum' => '31.05.2024'],
                'extracted_date' => '2024-05-31',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Stimmanpassung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Melanie Schulz 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0541 331410   
Telefax: 
0541 33141285  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
31.05.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650067808155',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Stimmanpassung 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Melanie Schulz 
 
 
 
BKK firmus  28192 Bremen   
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0541 331410   
Telefax: 
0541 33141285  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
31.05.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650067808155',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650071371588.pdf',
                'sha256' => 'eec4add63eea36942d9980a8dbc927d5ccfc17113e7a4bead7f50ca9b0681565',
                'filename' => '9650071371588.pdf',
                'file_type' => 'pdf',
                'file_size' => 176451,
                'title' => 'Aufforderung: Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen (AU ab 12.07.2024)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Entgeltersatzleistungen', 'Selbstauskunft', 'AU ab 12.07.2024', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'Schreiben der BKK firmus zur Arbeitsunfähigkeit ab dem 12.07.2024. Aufforderung zur Einreichung der ausgefüllten Selbstauskunft / Erklärung zur Prüfung des Anspruchs auf Krankengeld / Entgeltersatzleistungen.',
                'key_facts' => ['thema' => 'Krankengeldanspruch', 'au_beginn' => '12.07.2024', 'aufforderung' => 'Erklärung einreichen', 'datum' => '12.07.2024'],
                'extracted_date' => '2024-07-12',
                'content_preview' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 12.07.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Lisa Pfister
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
KG
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Herrn
Nick Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
14.08.2024
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BKK firmus',
                'full_text' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 12.07.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Lisa Pfister
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
KG
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Herrn
Nick Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
14.08.2024
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BKK firmus
28192 Bremen
Bankverbindung
Commerzbank AG
IBAN: DE53 2654 0070 0526 2100 00
BIC: COBADEFFXXX

--- Seite 2 ---
Zurück an:
BKK firmus
28192 Bremen
Arbeitsunfähigkeit/Reha-Maßnahme
12.07.2024
Name Vorname, Geburtstag des Versicherten
Versicherten-Nr.
Steinhauer, Nick, geboren am 01.09.1993
O603571189
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Frage
Antwort
 Ja,
Name: Noah Steinhauer
geb. am: 31.12.2022
Name:
geb. am: 
Name:
geb. am:
Name:
geb. am:
Name:
geb. am:
Haben Sie Ki',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650071568085.pdf',
                'sha256' => 'f64b4526fbbe60980174f46944ed34b6dadd72e527de48738dc937ee751a46df',
                'filename' => '9650071568085.pdf',
                'file_type' => 'pdf',
                'file_size' => 242824,
                'title' => 'Bescheid: Erstattung von Arzneimitteln Hylo Dual, Cationorm und Blephademodex',
                'category' => 'Medikamente & Hilfsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Arzneimittel', 'Hylo Dual', 'Cationorm', 'Blephademodex', 'Kostenerstattung', 'Bescheid', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Medikamente & Hilfsmittel\'.',
                'summary' => 'Bescheid der BKK firmus bezüglich des Antrags auf Kostenerstattung für apothekenpflichtige, nicht verschreibungspflichtige Arzneimittel (Hylo Dual, Cationorm Augentropfen, Blephademodex Tücher) mit Rechtsbehelfsbelehrung.',
                'key_facts' => ['medikamente' => '[\'Hylo Dual\', \'Cationorm\', \'Blephademodex\']', 'thema' => 'Kostenerstattung Arzneimittel', 'datum' => '15.08.2024'],
                'extracted_date' => '2024-08-15',
                'content_preview' => '--- Seite 1 ---
Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und Blephademodex 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und 
Blephademodex. 
 
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise nicht um 
verschreibungspflichtige Arzneimittel handelt und daher die Kosten nicht von der gesetzlichen 
Krankenversicherung übernommen werden können. 
 
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über 
die Verordnung von Arzneimitteln in der vertragsärztlichen Versorgung) sind diese Präparate 
als Eigenleistung vollständig selbst zu zahlen. 
 
Eine Erstattung Ihrer angefallenen Kosten ist uns leider nicht möglich. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar schriftlich, zur 
Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-Straße 11 in 28237 Bremen. Der 
Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle der BKK firmus eingereicht werden. Die Frist ist auch 
gewahrt, wenn der Widerspruch rechtzeitig bei einer anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer 
deutschen Konsularbehörde eingelegt wird.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.08.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654',
                'full_text' => '--- Seite 1 ---
Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und Blephademodex 
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihren Antrag auf Erstattung der Arzneimittel Hylo Dual, Cationorm und 
Blephademodex. 
 
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise nicht um 
verschreibungspflichtige Arzneimittel handelt und daher die Kosten nicht von der gesetzlichen 
Krankenversicherung übernommen werden können. 
 
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über 
die Verordnung von Arzneimitteln in der vertragsärztlichen Versorgung) sind diese Präparate 
als Eigenleistung vollständig selbst zu zahlen. 
 
Eine Erstattung Ihrer angefallenen Kosten ist uns leider nicht möglich. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar schriftlich, zur 
Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-Straße 11 in 28237 Bremen. Der 
Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle der BKK firmus eingereicht werden. Die Frist ist auch 
gewahrt, wenn der Widerspruch rechtzeitig bei einer anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer 
deutschen Konsularbehörde eingelegt wird.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.08.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650072456583.pdf',
                'sha256' => '2952e34f262407fea2ab448eda0fcdfbe76dbbad22e3c324c4b39938f64c159b',
                'filename' => '9650072456583.pdf',
                'file_type' => 'pdf',
                'file_size' => 176359,
                'title' => 'Erneute Aufforderung: Erklärung des Mitglieds für Entgeltersatzleistungen (AU ab 06.08.2024)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Entgeltersatz', 'AU ab 06.08.2024', 'Fragebogen', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'Erneutes Anschreiben der BKK firmus zur Arbeitsunfähigkeit ab dem 06.08.2024 zur Feststellung und Berechnung des Krankengeldes. Enthält Kontaktdaten des Krankengeld-Teams (0421 6434-403).',
                'key_facts' => ['au_datum' => '06.08.2024', 'telefon' => '0421 6434-403', 'datum' => '06.08.2024'],
                'extracted_date' => '2024-08-06',
                'content_preview' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 06.08.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Lisa Pfister
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
KG
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Herrn
Nick Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
03.09.2024
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BKK firmus',
                'full_text' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Herr Steinhauer,
Sie sind seit dem 06.08.2024 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Lisa Pfister
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
KG
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Herrn
Nick Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
03.09.2024
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BKK firmus
28192 Bremen
Bankverbindung
Commerzbank AG
IBAN: DE53 2654 0070 0526 2100 00
BIC: COBADEFFXXX

--- Seite 2 ---
Zurück an:
BKK firmus
28192 Bremen
Arbeitsunfähigkeit/Reha-Maßnahme
06.08.2024
Name Vorname, Geburtstag des Versicherten
Versicherten-Nr.
Steinhauer, Nick, geboren am 01.09.1993
O603571189
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Frage
Antwort
 Ja,
Name: Noah Steinhauer
geb. am: 31.12.2022
Name:
geb. am: 
Name:
geb. am:
Name:
geb. am:
Name:
geb. am:
Haben Sie Ki',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650072673267.pdf',
                'sha256' => 'e01b67b0c4af252a4ccdac20f9580e8c147c78d01507a9b6a00fed37406be22b',
                'filename' => '9650072673267.pdf',
                'file_type' => 'pdf',
                'file_size' => 239461,
                'title' => 'BEWILLIGUNG: Wir übernehmen die Kosten für Ihre Psychotherapie (Kostenzusage)',
                'category' => 'Psychotherapie & Kostenerstattung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Psychotherapie', 'Bewilligung', 'Kostenzusage', 'Kostenerstattung', '§ 13 Abs. 3 SGB V', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.',
                'summary' => 'ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus erteilt die schriftliche Zusage zur Kostenübernahme der ambulanten Psychotherapie für Alina Steinhauer.',
                'key_facts' => ['leistung' => 'Psychotherapie', 'status' => 'BEWILLIGT (Kostenübernahme zugesagt)', 'datum' => '06.09.2024'],
                'extracted_date' => '2024-09-06',
                'content_preview' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 12 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Angela Ungefug  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
06.09.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650072673267',
                'full_text' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 12 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Angela Ungefug  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
06.09.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650072673267',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650073846318.pdf',
                'sha256' => '99612ac1d6a0dd8d69be1b283867c957a0b2bb9f4cab99c016c149da1d1eb493',
                'filename' => '9650073846318.pdf',
                'file_type' => 'pdf',
                'file_size' => 284436,
                'title' => 'HAUPTBESCHEID KRANKENGELD: Ihr Krankengeldbezug ab dem 23.08.2024 (Tagessatz: 65,90 €)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', '65,90 €', '1.977,00 €', 'Bewilligungsbescheid', 'Tagessatz', 'Krankengeldbezug', '23.08.2024', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'ZENTRALER BEWILLIGUNGSBESCHEID KRANKENGELD: BKK firmus bewilligt Krankengeld ab dem 23.08.2024. Bruttokrankengeld kalendertäglich: 65,90 € (monatlich 1.977,00 € brutto). Erläutert gesetzliche Abzüge für Renten-, Arbeitslosen- und Pflegeversicherung sowie die Auszahlungsvoraussetzungen.',
                'key_facts' => ['leistungsbeginn' => '23.08.2024', 'tagessatz_brutto' => '65,90 €', 'monatsbetrag_brutto' => '1.977,00 €', 'leistungsart' => 'Regelkrankengeld § 44 SGB V', 'datum' => '23.08.2024'],
                'extracted_date' => '2024-08-23',
                'content_preview' => '--- Seite 1 ---
Ihr Krankengeldbezug ab dem 23.08.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
damit Sie auch im Falle einer längeren Arbeitsunfähigkeit abgesichert sind, hilft Ihnen die 
BKK firmus mit der Zahlung von Krankengeld. Zu Ihrer Krankengeldzahlung erhalten Sie 
heute einige wichtige Informationen.  
 
Die Entgeltfortzahlung Ihres Arbeitgebers endet aufgrund der uns vorliegenden Unterlagen 
voraussichtlich am 31.07.2024. Vorerkrankungen wegen derselben Erkrankung werden 
gegebenenfalls auf die Entgeltfortzahlung angerechnet.  
 
Ihr Krankengeld erhalten Sie daher ab dem 23.08.2024.       
 
Das Krankengeld beträgt 70 % Ihres entgangenen Bruttoentgeltes. Dabei bleiben steuerfreie 
Zuschläge unberücksichtigt. Das Entgelt wird berücksichtigt bis zur Höhe der gesetzlichen 
Beitragsbemessungsgrenze. Das Krankengeld darf jedoch 90 % des Nettoentgeltes nicht 
übersteigen. Hinzugerechnet werden eventuelle Einmalzahlungen (z. B. Urlaubs- und/oder 
Weihnachtsgeld), falls diese in den letzten 12 Monaten beitragspflichtig waren.  
 
Vom Krankengeld werden die Beiträge zur Renten-, Arbeitslosen- und 
Pflegeversicherung einbehalten. Diese werden grundsätzlich je zur Hälfte von Ihnen 
und von der BKK firmus getragen. Kinderlose tragen zudem einen Beitragszuschlag 
zur Pflegeversicherung.  
 
Anhand des übermittelten Arbeitsentgelts haben wir für Sie folgendes 
tägliches Krankengeld berechnet: 
 
Krankengeld 
(kalendertäglich) 
abzgl. Beiträge 
(kalendertäglich) 
Zahlbetrag 
(kalendertäglich) 
65,90 €  
0,00 €  
65,90 €  
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
26.09.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Br',
                'full_text' => '--- Seite 1 ---
Ihr Krankengeldbezug ab dem 23.08.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
damit Sie auch im Falle einer längeren Arbeitsunfähigkeit abgesichert sind, hilft Ihnen die 
BKK firmus mit der Zahlung von Krankengeld. Zu Ihrer Krankengeldzahlung erhalten Sie 
heute einige wichtige Informationen.  
 
Die Entgeltfortzahlung Ihres Arbeitgebers endet aufgrund der uns vorliegenden Unterlagen 
voraussichtlich am 31.07.2024. Vorerkrankungen wegen derselben Erkrankung werden 
gegebenenfalls auf die Entgeltfortzahlung angerechnet.  
 
Ihr Krankengeld erhalten Sie daher ab dem 23.08.2024.       
 
Das Krankengeld beträgt 70 % Ihres entgangenen Bruttoentgeltes. Dabei bleiben steuerfreie 
Zuschläge unberücksichtigt. Das Entgelt wird berücksichtigt bis zur Höhe der gesetzlichen 
Beitragsbemessungsgrenze. Das Krankengeld darf jedoch 90 % des Nettoentgeltes nicht 
übersteigen. Hinzugerechnet werden eventuelle Einmalzahlungen (z. B. Urlaubs- und/oder 
Weihnachtsgeld), falls diese in den letzten 12 Monaten beitragspflichtig waren.  
 
Vom Krankengeld werden die Beiträge zur Renten-, Arbeitslosen- und 
Pflegeversicherung einbehalten. Diese werden grundsätzlich je zur Hälfte von Ihnen 
und von der BKK firmus getragen. Kinderlose tragen zudem einen Beitragszuschlag 
zur Pflegeversicherung.  
 
Anhand des übermittelten Arbeitsentgelts haben wir für Sie folgendes 
tägliches Krankengeld berechnet: 
 
Krankengeld 
(kalendertäglich) 
abzgl. Beiträge 
(kalendertäglich) 
Zahlbetrag 
(kalendertäglich) 
65,90 €  
0,00 €  
65,90 €  
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
26.09.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650073846318

--- Seite 2 ---
Das Krankengeld wird Ihnen bei vollen Monaten für 30 Tage, bei Teilmonaten für die 
tatsächlichen Tage gezahlt. Die Auszahlung des Krankengeldes erfolgt immer bis zum 
Ausstellungsdatum Ihrer Arbeitsunfähigkeit (= festgestellt am), niemals bis zum 
voraussichtlichen Ende Ihrer Arbeitsunfähigkeit, es sei denn, Sie sind wieder voll 
',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650075219970.pdf',
                'sha256' => '8880e285e4887f0545d144f96befdf1dcd94c2e49a5bc110ee675297a1659fd5',
                'filename' => '9650075219970.pdf',
                'file_type' => 'pdf',
                'file_size' => 236210,
                'title' => 'Aufforderung zur schriftlichen Widerspruchseinreichung',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruch', 'Frist', 'Formvorschrift', 'Bescheid vom 10.10.2024', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'BKK firmus reagiert auf eine Chat-Nachricht vom 18.10.2024 und fordert Alina auf, form- und fristgerecht einen schriftlichen Widerspruch gegen den Bescheid vom 10.10.2024 einzureichen.',
                'key_facts' => ['thema' => 'Widerspruchserhebung', 'bezug_bescheid' => '10.10.2024', 'datum' => '18.10.2024'],
                'extracted_date' => '2024-10-18',
                'content_preview' => '--- Seite 1 ---
Ihre Nachricht vom 18.10.2024
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht. 
 
Bitte reichen Sie uns einen schriftlichen Widerspruch gegen unseren Bescheid vom 
10.10.2024 ein. Die Schriftlichkeit ist nur gewahrt, wenn der Widerspruch handschriftlich 
unterschrieben ist. Gerne reichen Sie auch eine Begründung ein. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
21.10.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
                'full_text' => '--- Seite 1 ---
Ihre Nachricht vom 18.10.2024
 
 
Guten Tag Herr Steinhauer, 
 
vielen Dank für Ihre Nachricht. 
 
Bitte reichen Sie uns einen schriftlichen Widerspruch gegen unseren Bescheid vom 
10.10.2024 ein. Die Schriftlichkeit ist nur gewahrt, wenn der Widerspruch handschriftlich 
unterschrieben ist. Gerne reichen Sie auch eine Begründung ein. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
21.10.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650075540649.pdf',
                'sha256' => '4f21a022fc0211e842898e807aef5b05c907177215d1696fff76a2560595a012',
                'filename' => '9650075540649.pdf',
                'file_type' => 'pdf',
                'file_size' => 238025,
                'title' => 'Eingangsbestätigung: Ihr Widerspruch vom 21.10.2024',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruch', 'Krankengeld', 'Eingangsbestätigung', 'MD-Überprüfung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'BKK firmus bestätigt den fristgerechten Eingang des Widerspruchs vom 21.10.2024 gegen die Krankengeldberechnung und leitet diesen zur erneuten sozialmedizinischen Begutachtung weiter.',
                'key_facts' => ['widerspruch_datum' => '21.10.2024', 'aktenzeichen' => 'KSWIEEINKG', 'datum' => '21.10.2024'],
                'extracted_date' => '2024-10-21',
                'content_preview' => '--- Seite 1 ---
KSWIEEINKG 
 
Ihr Widerspruch vom 21.10.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen. 
 
Hierzu haben wir Ihren behandelnden Arzt aufgefordert entsprechende Befundberichte zum 
Medizinischen Dienst zu senden, damit wir eine weitere Überprüfung veranlassen können. 
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Luca Klostermann 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
28.10.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650075540649',
                'full_text' => '--- Seite 1 ---
KSWIEEINKG 
 
Ihr Widerspruch vom 21.10.2024
 
 
 
Guten Tag Herr Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen. 
 
Hierzu haben wir Ihren behandelnden Arzt aufgefordert entsprechende Befundberichte zum 
Medizinischen Dienst zu senden, damit wir eine weitere Überprüfung veranlassen können. 
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Luca Klostermann 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
28.10.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650075540649',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650076822528.pdf',
                'sha256' => '75857574fa658a2263e2fcaaafbaca2697b069cdb449634f314c432edf49a941',
                'filename' => '9650076822528.pdf',
                'file_type' => 'pdf',
                'file_size' => 240733,
                'title' => 'ABHILFEBESCHEID: Krankengeld - Ihr Widerspruch vom 25.10.2024 (Volle Stattgabe)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Abhilfebescheid', 'Stattgabe', 'Widerspruchserfolg', 'Krankengeld ab 23.08.2024', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'ERFOLG / ABHILFEBESCHEID: Die BKK firmus hilft dem Widerspruch vom 25.10.2024 gegen den Bescheid vom 18.10.2024 vollständig ab! Die Feststellung des Krankengeldanspruchs wird korrigiert und der Anspruch ab dem 23.08.2024 vollumfänglich anerkannt.',
                'key_facts' => ['bescheid_art' => 'Abhilfebescheid', 'ergebnis' => 'VOLLSTÄNDIGE STATTGABE', 'bezug' => 'Krankengeld ab 23.08.2024', 'datum' => '25.10.2024'],
                'extracted_date' => '2024-10-25',
                'content_preview' => '--- Seite 1 ---
Abhilfebescheid  
Ihr Widerspruch vom 25.10.2024

 
 
Guten Tag Frau Steinhauer, 
 
am 25.10.204 haben Sie Widerspruch gegen unseren Bescheid 10.10.2024 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Wir haben das ausstehende Krankengeld angewiesen mit dem heutigen Tag. 
 
Ihrem Widerspruch vom 25.10.2024 haben wir somit abgeholfen. Gemäß  
§ 63 Abs. 1 und 2 SGB X können die für das Widerspruchsverfahren notwendigen 
Aufwendungen, Gebühren und Auslagen erstattet werden. 
 
Der Ablehnungsbescheid mit unserem Schreiben vom 10.10.2024 wird somit aufgehoben. 
 
Bei eventuellen Fragen können Sie uns selbstverständlich gerne anrufen. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Luca Klostermann 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
18.11.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650076822528',
                'full_text' => '--- Seite 1 ---
Abhilfebescheid  
Ihr Widerspruch vom 25.10.2024

 
 
Guten Tag Frau Steinhauer, 
 
am 25.10.204 haben Sie Widerspruch gegen unseren Bescheid 10.10.2024 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Wir haben das ausstehende Krankengeld angewiesen mit dem heutigen Tag. 
 
Ihrem Widerspruch vom 25.10.2024 haben wir somit abgeholfen. Gemäß  
§ 63 Abs. 1 und 2 SGB X können die für das Widerspruchsverfahren notwendigen 
Aufwendungen, Gebühren und Auslagen erstattet werden. 
 
Der Ablehnungsbescheid mit unserem Schreiben vom 10.10.2024 wird somit aufgehoben. 
 
Bei eventuellen Fragen können Sie uns selbstverständlich gerne anrufen. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Luca Klostermann 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434 - 403   
Telefax: 
0421 6434 - 451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
18.11.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650076822528',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650078218824.pdf',
                'sha256' => '98c33c3dbb674f729288dd2bb1ed92a0f334b8944c5cea382f4025de6f7c63ef',
                'filename' => '9650078218824.pdf',
                'file_type' => 'pdf',
                'file_size' => 251913,
                'title' => 'Bescheid: Ablehnung der Haarentfernung mittels INOS-Technologie (Hairfree)',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Barthaarentfernung', 'Hairfree', 'INOS-Technologie', 'Ablehnungsbescheid', 'Widerspruchsfrist', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'Ablehnungsbescheid der BKK firmus bezüglich der Kostenübernahme der Haarentfernung mit dem INOS-Verfahren (Institut Hairfree), da es sich um eine nicht im EBM gelistete Methode handele. Enthält Rechtsbehelfsbelehrung.',
                'key_facts' => ['bescheid_art' => 'Ablehnungsbescheid', 'methode' => 'INOS (Hairfree)', 'begruendung' => 'Keine vertragsärztliche Leistung', 'datum' => '11.12.2024'],
                'extracted_date' => '2024-12-11',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Erbringung einer kosmetischen Dienstleistung 
an unerwünschten Haaren mittels des INOS/INOSplus Behandlungssystems (Bart 
komplett gemäß der Kundenvereinbarung vom 02.01.2024) 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Die Prüfung hat ergeben, dass für eine Beurteilung durch den Medizinischen Dienst ein 
endokrinologischer Verlaufsbericht und ein psychotherapeutischer Verlaufsbericht (hinsichtlich 
des Alltagstestes) fehlen. Die Epilationsbehandlung ist eine abrechnungsfähige 
vertragsärztliche Leistung. Möglich ist eine Nadelepilation zu Lasten der gesetzlichen 
Krankenversicherung unter Liquidation der EBM-Ziffer 10340.  
 
Aus diesem Grund ist die gewünschte Kostenübernahme der Erbringung einer kosmetischen 
Dienstleistung an unerwünschten Haaren mittels des INOS/INOSplus Behandlungssystems 
(Bart komplett gemäß der Kundenvereinbarung vom 02.01.2024) leider nicht möglich.  
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
       
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben:',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Erbringung einer kosmetischen Dienstleistung 
an unerwünschten Haaren mittels des INOS/INOSplus Behandlungssystems (Bart 
komplett gemäß der Kundenvereinbarung vom 02.01.2024) 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Die Prüfung hat ergeben, dass für eine Beurteilung durch den Medizinischen Dienst ein 
endokrinologischer Verlaufsbericht und ein psychotherapeutischer Verlaufsbericht (hinsichtlich 
des Alltagstestes) fehlen. Die Epilationsbehandlung ist eine abrechnungsfähige 
vertragsärztliche Leistung. Möglich ist eine Nadelepilation zu Lasten der gesetzlichen 
Krankenversicherung unter Liquidation der EBM-Ziffer 10340.  
 
Aus diesem Grund ist die gewünschte Kostenübernahme der Erbringung einer kosmetischen 
Dienstleistung an unerwünschten Haaren mittels des INOS/INOSplus Behandlungssystems 
(Bart komplett gemäß der Kundenvereinbarung vom 02.01.2024) leider nicht möglich.  
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
       
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
06.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650078218824

--- Seite 2 ---
Gerne hätten wir Ihnen eine positivere Nachricht mitgeteilt. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650078585061.pdf',
                'sha256' => '8b25d8419bc5286020eaa4d7f5c85e1d4c31bc1cc4a13149616ded09044be959',
                'filename' => '9650078585061.pdf',
                'file_type' => 'pdf',
                'file_size' => 239862,
                'title' => 'BEWILLIGUNG: Kostenübernahme für Psychotherapie (Fortführung)',
                'category' => 'Psychotherapie & Kostenerstattung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Psychotherapie', 'Bewilligung', 'Kostenzusage', 'Therapiestunden', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.',
                'summary' => 'ERFOLG / BEWILLIGUNG: Bestätigung der fortlaufenden Kostenübernahme für die ambulante Psychotherapie von Alina Steinhauer durch die BKK firmus.',
                'key_facts' => ['leistung' => 'Psychotherapie Fortführung', 'status' => 'BEWILLIGT', 'datum' => '12.12.2024'],
                'extracted_date' => '2024-12-12',
                'content_preview' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 12 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
12.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650078585061',
                'full_text' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 12 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
12.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650078585061',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650078742642.pdf',
                'sha256' => '4898ffa94c53f50406c8170a4e3be730134e678b6ea482640d422a7a98173c5f',
                'filename' => '9650078742642.pdf',
                'file_type' => 'pdf',
                'file_size' => 417554,
                'title' => 'Unterlagenanforderung: Geschlechtsangleichende Operation (Vorbereitung MD)',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Geschlechtsangleichung', 'Operation', 'OP-Kostenübernahme', 'MD-Begutachtungsrichtlinie', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'Schreiben der BKK firmus bezüglich der Kostenübernahme einer geschlechtsangleichenden Operation. Detaillierte Auflistung der für die Begutachtung durch den Medizinischen Dienst noch benötigten Berichte und Nachweise.',
                'key_facts' => ['thema' => 'Geschlechtsangleichende OP', 'status' => 'Anforderung medizinischer Unterlagen', 'datum' => '16.12.2024'],
                'extracted_date' => '2024-12-16',
                'content_preview' => '--- Seite 1 ---
Ihre Nachricht vom 13.12.2024
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Um die Kostenübernahme einer geschlechtsangleichenden Operation zu beantragen, benötigt 
die BKK firmus nur einen formlosen Antrag von Ihnen. Alle weiteren Unterlagen müssen 
aufgrund von neuen Datenschutzrichtlinien direkt an den Medizinischen Dienst (MD) übermittelt 
werden. Nach Antragsstellung erhalten Sie ein Schreiben, welches weitere Informationen sowie 
die Adresse des zuständigen MD enthält. Aufgrund von gesetzlich einzuhaltenden Fristen ist es 
empfehlenswert, den Kostenübernahmeantrag erst dann zu stellen, wenn alle erforderlichen 
Unterlagen vorliegen. Diese wären: 
 
- ein Arztbericht über eine gegengeschlechtliche Hormontherapie 
- ein aktueller Psychotherapiebericht mit Angaben zum Befund, zum 
Therapieverlauf, zum begleitenden Alltagstest in allen psychosozialen 
Lebensbereichen, zum Erreichen der Therapieziele und zur prognostischen 
Einschätzung im Falle der Durchführung der Operation 
- ein gynäkologischer Befundbericht zum Ausschluss relevanter 
Differentialdiagnosen und Komorbiditäten 
 
Im Verlauf kann es vorkommen, dass der MD nach Sichtung dieser Unterlagen weitere 
anfordert. Darauf hat die BKK firmus im Vorfeld keinen Einfluss.         
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Pia Theders 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541-33141-0   
Telefax: 
0541-33141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
16.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzba',
                'full_text' => '--- Seite 1 ---
Ihre Nachricht vom 13.12.2024
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Um die Kostenübernahme einer geschlechtsangleichenden Operation zu beantragen, benötigt 
die BKK firmus nur einen formlosen Antrag von Ihnen. Alle weiteren Unterlagen müssen 
aufgrund von neuen Datenschutzrichtlinien direkt an den Medizinischen Dienst (MD) übermittelt 
werden. Nach Antragsstellung erhalten Sie ein Schreiben, welches weitere Informationen sowie 
die Adresse des zuständigen MD enthält. Aufgrund von gesetzlich einzuhaltenden Fristen ist es 
empfehlenswert, den Kostenübernahmeantrag erst dann zu stellen, wenn alle erforderlichen 
Unterlagen vorliegen. Diese wären: 
 
- ein Arztbericht über eine gegengeschlechtliche Hormontherapie 
- ein aktueller Psychotherapiebericht mit Angaben zum Befund, zum 
Therapieverlauf, zum begleitenden Alltagstest in allen psychosozialen 
Lebensbereichen, zum Erreichen der Therapieziele und zur prognostischen 
Einschätzung im Falle der Durchführung der Operation 
- ein gynäkologischer Befundbericht zum Ausschluss relevanter 
Differentialdiagnosen und Komorbiditäten 
 
Im Verlauf kann es vorkommen, dass der MD nach Sichtung dieser Unterlagen weitere 
anfordert. Darauf hat die BKK firmus im Vorfeld keinen Einfluss.         
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Pia Theders 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541-33141-0   
Telefax: 
0541-33141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
16.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650078742642',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650079272706.pdf',
                'sha256' => '589259c41833484073b1189c2a80a3e073a5793ae46e5800539c56280a0dc431',
                'filename' => '9650079272706.pdf',
                'file_type' => 'pdf',
                'file_size' => 247467,
                'title' => 'Bescheinigung über den Bezug von Krankengeld (Jahresende 2024)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Bescheinigung', 'Krankengeld', 'Leistungsnachweis', 'Steuer', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'Offizielle Bescheinigung der BKK firmus über den laufenden Bezug von Geldleistungen (Krankengeld) zum Jahresende 2024 für Behörden, Ämter und steuerliche Nachweise.',
                'key_facts' => ['nachweis' => 'Krankengeldbezug 2024', 'aussteller' => 'BKK firmus', 'datum' => '23.12.2024'],
                'extracted_date' => '2024-12-23',
                'content_preview' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
13.12.2024  
65,90 €  
65,90 €  
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650079272706',
                'full_text' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
13.12.2024  
65,90 €  
65,90 €  
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.12.2024 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650079272706',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650083792245.pdf',
                'sha256' => '0de7755ebd4a9f1c334f2fddd36f5c9845cee4e0e9df51dbadbacbf86e897538',
                'filename' => '9650083792245.pdf',
                'file_type' => 'pdf',
                'file_size' => 237135,
                'title' => 'Bestätigung AU-Bescheinigung ab 28.01.2025 (Hinweis auf Lücke 27.01.2025)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Arbeitsunfähigkeit', 'Krankengeld', 'Unterbrechungslücke', '27.01.2025', '65,90 €', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'WICHTIGER BELEG: BKK firmus bestätigt den Erhalt der AU-Bescheinigung vom 28.01.2025 bis zum 21.02.2025. Da der vorherige AU-Abschnitt am 26.01.2025 endete, entstand der ungedeckte Tag am 27.01.2025 (Unterbrechungstag à 65,90 €).',
                'key_facts' => ['au_zeitraum' => '28.01.2025 bis 21.02.2025', 'relevanz' => 'Dokumentiert die AU-Unterbrechung vom 27.01.2025', 'datum' => '28.01.2025'],
                'extracted_date' => '2025-01-28',
                'content_preview' => '--- Seite 1 ---
Arbeitsunfähigkeitsbescheinigung ab dem 28.01.2025 euerBrief]]
 
 
Guten Tag Frau Steinhauer, 
 
wir haben Ihre Arbeitsunfähigkeitsbescheinigung für den 28.01.2025 bis zum 21.02.2025 
erhalten. Jedoch können wir diese nicht erfassen, da ab dem 25.01.2025 kein 
Versicherungsverhältnis besteht.  
 
Die Arbeitsunfähigkeit muss lückenlos nachgewiesen werden. Sie hätten spätestens am 
27.01.2025 wieder bei Ihrem Arzt vorstellig werden müssen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0541 333141250  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
30.01.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
                'full_text' => '--- Seite 1 ---
Arbeitsunfähigkeitsbescheinigung ab dem 28.01.2025 euerBrief]]
 
 
Guten Tag Frau Steinhauer, 
 
wir haben Ihre Arbeitsunfähigkeitsbescheinigung für den 28.01.2025 bis zum 21.02.2025 
erhalten. Jedoch können wir diese nicht erfassen, da ab dem 25.01.2025 kein 
Versicherungsverhältnis besteht.  
 
Die Arbeitsunfähigkeit muss lückenlos nachgewiesen werden. Sie hätten spätestens am 
27.01.2025 wieder bei Ihrem Arzt vorstellig werden müssen.  
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0541 333141250  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
30.01.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650090394429.pdf',
                'sha256' => 'd22f9402dec4a2e04f6385ffd188216997179d97406aee5880996378ab312903',
                'filename' => '9650090394429.pdf',
                'file_type' => 'pdf',
                'file_size' => 414441,
                'title' => 'Antwort der BKK firmus zur geplanten stationären Behandlung',
                'category' => 'Krankenhaus & Stationär',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankenhaus', 'Stationäre Behandlung', 'Kostenübernahme', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankenhaus & Stationär\'.',
                'summary' => 'Schreiben der BKK firmus als Antwort auf Alinas Nachricht vom 17.02.2025 bezüglich der Kostenübernahme und Organisation einer stationären Krankenhausbehandlung.',
                'key_facts' => ['thema' => 'Stationäre Behandlung', 'bezug_nachricht' => '17.02.2025', 'datum' => '18.02.2025'],
                'extracted_date' => '2025-02-18',
                'content_preview' => '--- Seite 1 ---
Ihre Nachricht vom 17.02.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Vielen Dank für Ihre Nachricht vom 17.02.2025. In diesem Fall ist der gynäkologische 
Befundbebricht nicht erforderlich.       
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Sarah Üffing 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 3314130017   
Telefax: 
0541 33141130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
18.02.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650090394429',
                'full_text' => '--- Seite 1 ---
Ihre Nachricht vom 17.02.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kund*innen im Vordergrund.  
 
Vielen Dank für Ihre Nachricht vom 17.02.2025. In diesem Fall ist der gynäkologische 
Befundbebricht nicht erforderlich.       
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Sarah Üffing 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 3314130017   
Telefax: 
0541 33141130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
18.02.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650090394429',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650090870349.pdf',
                'sha256' => 'ffdb3f4d9bb3f70a26cdc5b057f80b650334357cdd67d7ef083a250c6e69d605',
                'filename' => '9650090870349.pdf',
                'file_type' => 'pdf',
                'file_size' => 244422,
                'title' => 'Versicherungsbescheinigung für ärztliche Behandlung (Alina Steinhauer)',
                'category' => 'Versicherungsbescheinigung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Versicherungsbescheinigung', 'Mitgliedsnachweis', 'Krankenversicherung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Versicherungsbescheinigung\'.',
                'summary' => 'Offizielle Mitglieds- und Versicherungsbescheinigung der BKK firmus für Alina Steinhauer (geb. 01.09.1993) zur Vorlage bei Ärzten, Kliniken und Therapeuten.',
                'key_facts' => ['mitglied' => 'Alina Steinhauer', 'geburtsdatum' => '01.09.1993', 'status' => 'Versichert bei BKK firmus', 'datum' => '20.02.2025'],
                'extracted_date' => '2025-02-20',
                'content_preview' => '--- Seite 1 ---
Versicherungsbescheinigung für ärztliche Behandlung 
 
 
Guten Tag Frau Steinhauer, 
 
gerne bescheinigen wir Ihnen, dass Sie bei der BKK firmus versichert sind: 
 
Name: 
Steinhauer, Alina 
Geburtsdatum:  
01.09.1993 
Kassen-Nr.: 
103 121 137 
Versicherten-Nr.: 
O603571189 
Status: 
Mitglied 
Papiergebundener Anspruchsnachweis gültig für: 
1. Quartal 2025 
 
Für Rückfragen oder weitere Informationen rufen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
20.02.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650090870349',
                'full_text' => '--- Seite 1 ---
Versicherungsbescheinigung für ärztliche Behandlung 
 
 
Guten Tag Frau Steinhauer, 
 
gerne bescheinigen wir Ihnen, dass Sie bei der BKK firmus versichert sind: 
 
Name: 
Steinhauer, Alina 
Geburtsdatum:  
01.09.1993 
Kassen-Nr.: 
103 121 137 
Versicherten-Nr.: 
O603571189 
Status: 
Mitglied 
Papiergebundener Anspruchsnachweis gültig für: 
1. Quartal 2025 
 
Für Rückfragen oder weitere Informationen rufen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
20.02.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650090870349',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650097392433.pdf',
                'sha256' => '26daeee38930dcdfcec785baa801838e47be4a9fa661dc00e4b3f1dd0ae3ad1c',
                'filename' => '9650097392433.pdf',
                'file_type' => 'pdf',
                'file_size' => 247599,
                'title' => 'Bescheinigung über den Bezug von Krankengeld (März 2025)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Bescheinigung', 'Geldleistungen', 'Leistungsnachweis', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'Bescheinigung über den laufenden Bezug von Geldleistungen (Krankengeld ab 23.08.2024 bis März 2025) für Alina Steinhauer zur Vorlage bei Sozialleistungsträgern.',
                'key_facts' => ['thema' => 'Krankengeld-Bescheinigung', 'zeitraum' => 'Laufender Bezug März 2025', 'datum' => '27.03.2025'],
                'extracted_date' => '2025-03-27',
                'content_preview' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  
Frau Alina Steinhauer, geb. 01.09.1993

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
21.03.2025 
65,90  
65,90 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
27.03.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650097392433',
                'full_text' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  
Frau Alina Steinhauer, geb. 01.09.1993

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
21.03.2025 
65,90  
65,90 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
27.03.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650097392433',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650100261190.pdf',
                'sha256' => 'f6113bb3c4902f7771d65bbae4cb3c2e7b381477bf0f9aa7f179a1590b3762ef',
                'filename' => '9650100261190.pdf',
                'file_type' => 'pdf',
                'file_size' => 250520,
                'title' => 'Detaillierte Bescheinigung der Krankengeld-Auszahlungszeiträume',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Auszahlungsübersicht', 'Zahlungsbeleg', 'Kalendertage', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'WICHTIGE AUSZAHLUNGSLISTE: Detaillierte Bescheinigung aller Krankengeldzahlungen von 23.08.2024 bis 31.12.2024 und ab 01.01.2025 (unter Aussparung des 27.01.2025). Belegt die exakte Zahlungsfolge der BKK firmus.',
                'key_facts' => ['leistungszeitraum' => '23.08.2024 bis April 2025', 'relevanz' => 'Offizieller Nachweis der geleisteten Krankengeld-Auszahlungen', 'datum' => '23.04.2025'],
                'extracted_date' => '2025-04-23',
                'content_preview' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
31.12.2024  
65,90 €  
65,90 €  
Krankengeld 
01.01.2025 
26.01.2025 
65,90 € 
65,90 € 
Krankengeld 
28.01.2025 
laufend 
65,90 € 
65,90 € 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.04.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650100261190',
                'full_text' => '--- Seite 1 ---
Bescheinigung über den Bezug von Geldleistungen  

 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus werden Sie ...gesundum gut versorgt und betreut! 
 
Gerne bescheinigen wir Ihnen den Bezug von folgender Geldleistung: 
 
Art der Leistung 
vom 
bis 
tägl. Brutto / € 
 
tägl. Netto / €  
 
Krankengeld 
23.08.2024  
31.12.2024  
65,90 €  
65,90 €  
Krankengeld 
01.01.2025 
26.01.2025 
65,90 € 
65,90 € 
Krankengeld 
28.01.2025 
laufend 
65,90 € 
65,90 € 
 
Die o.g. Leistung wurde kalendertäglich gezahlt.  
 
Die Zahlung erfolgte bei Teilmonaten für die tatsächlichen Tage, bei vollen Kalendermonaten 
für 30 Tage. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
23.04.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650100261190',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650107004279.pdf',
                'sha256' => 'd8db046af8dc8ca7f17c9a972dcb89731b803d1d39a51523a098c5d2ed13e001',
                'filename' => '9650107004279.pdf',
                'file_type' => 'pdf',
                'file_size' => 239869,
                'title' => 'BEWILLIGUNG: Kostenübernahme Psychotherapie (Bewilligungsbescheid Mai 2025)',
                'category' => 'Psychotherapie & Kostenerstattung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Psychotherapie', 'Bewilligung', 'Kostenzusage', 'Therapie', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Psychotherapie & Kostenerstattung\'.',
                'summary' => 'ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus bewilligt weitere Psychotherapiestunden für Alina Steinhauer und sagt die vollständige Kostenübernahme zu.',
                'key_facts' => ['leistung' => 'Psychotherapie', 'status' => 'BEWILLIGT', 'datum' => '16.05.2025'],
                'extracted_date' => '2025-05-16',
                'content_preview' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 36 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Justine Raschkowski 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.05.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650107004279',
                'full_text' => '--- Seite 1 ---
Wir übernehmen die Kosten für Ihre Therapie      
 
 
 
Guten Tag Frau Steinhauer, 
 
die BKK firmus bietet bewährte Leistungspakete für die Gesundheit und unterstützt in allen 
Lebensbereichen. Gesundheit bedeutet, immer wieder selbstverantwortlich für körperliches 
und psychisches Wohlbefinden zu sorgen.  
 
Aus medizinischer Sicht empfiehlt Ihre Therapeutin/ Ihr Therapeut eine Psychotherapie und 
wir tragen gerne zur Behandlung Ihrer Erkrankung bei. Die Therapie soll dabei helfen, 
schwierige Lebensumstände besser zu bewältigen. Daher übernehmen wir die Kosten für 
insgesamt 36 Sitzungen.  
 
Wenn Sie Fragen haben, freuen wir uns auf Ihren Anruf. Unsere kompetenten 
Kundenberater betreuen Sie individuell und persönlich.  
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Justine Raschkowski 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.05.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSPSYKÜK 
9650107004279',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650111610893.pdf',
                'sha256' => '77142e0d1ae3115cee59d6ae30431c65f60258b46e776a58279a260daaed0192',
                'filename' => '9650111610893.pdf',
                'file_type' => 'pdf',
                'file_size' => 238928,
                'title' => 'Zwischenbescheid: Antrag auf geschlechtsangleichende Operation beim MD',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Geschlechtsangleichung', 'Operation', 'MD-Begutachtung', 'Neovagina', 'Neovulva', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'Mitteilung der BKK firmus, dass die Unterlagen für die geschlechtsangleichende Operation (Penisentfernung, Hodenentfernung, Bildung Neovulva und Neovagina) beim Medizinischen Dienst eingereicht wurden und die Begutachtung läuft.',
                'key_facts' => ['eingriff' => 'Geschlechtsangleichende Operation', 'status' => 'MD Begutachtung eingeleitet', 'datum' => '19.06.2025'],
                'extracted_date' => '2025-06-19',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme geschlechtsangleichender Operationen (Entfernung 
des Penis, der Hoden und Bildung von Neovulva und Neovagina)

 
 
Guten Tag Frau Steinhauer, 
vielen Dank für die Einreichung der Unterlagen bei dem Medizinischen Dienst (MD). 
 
Den MD haben wir mit der Prüfung Ihres Antrages auf Kostenübernahme 
geschlechtsangleichender Operationen (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina beauftragt.  
 
Aus diesem Grund bitten wir Sie um etwas Geduld. Bis zum 08.07.2025 können Sie jedoch 
mit unserer Antwort rechnen. 
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141216   
Telefax: 
0541 33141130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
19.06.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650111610893',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme geschlechtsangleichender Operationen (Entfernung 
des Penis, der Hoden und Bildung von Neovulva und Neovagina)

 
 
Guten Tag Frau Steinhauer, 
vielen Dank für die Einreichung der Unterlagen bei dem Medizinischen Dienst (MD). 
 
Den MD haben wir mit der Prüfung Ihres Antrages auf Kostenübernahme 
geschlechtsangleichender Operationen (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina beauftragt.  
 
Aus diesem Grund bitten wir Sie um etwas Geduld. Bis zum 08.07.2025 können Sie jedoch 
mit unserer Antwort rechnen. 
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141216   
Telefax: 
0541 33141130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
19.06.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650111610893',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650114201740.pdf',
                'sha256' => '75eb7075c25e37c7b81b99d292d542a6fb55195c8aeafc783109b68da22f495d',
                'filename' => '9650114201740.pdf',
                'file_type' => 'pdf',
                'file_size' => 237477,
                'title' => 'Stellungnahme der BKK firmus zum Bescheid vom 07.07.2025 (OP-Antrag)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Operation', 'Widerspruch', 'Bescheid vom 07.07.2025', 'Stellungnahme', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'Antwort der BKK firmus auf Alinas Schreiben vom 09.07. und 10.07.2025 bezüglich des ablehnenden Bescheids vom 07.07.2025. Unmittelbare Vorstufe zum Abhilfebescheid.',
                'key_facts' => ['thema' => 'OP-Bescheidprüfung', 'datum' => '14.07.2025'],
                'extracted_date' => '2025-07-14',
                'content_preview' => '--- Seite 1 ---
Ihre Nachrichten vom 09.07.2025 und 10.07.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kunden im Vordergrund.  
 
Vielen Dank für Ihre Nachrichten vom 09.07.2025 und vom 10.07.2025. Die BKK firmus hat 
am 07.07.2025 einen Bescheid erlassen. Es besteht kein weiterer Anspruch auf eine 
Genehmigungsfikton. Das Gutachten vom Medizinischen Dienst Niedersachsen liegt der 
BKK firmus bisher nicht vor.  
 
Ihr Widerspruch vom 09.07.2025 ist bei der BKK firmus eingegangen und befindet sich in 
Prüfung.         
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
14.07.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650114201740',
                'full_text' => '--- Seite 1 ---
Ihre Nachrichten vom 09.07.2025 und 10.07.2025
 
 
Guten Tag Frau Steinhauer, 
 
bei der BKK firmus stehen Service und Qualität für unsere Kunden im Vordergrund.  
 
Vielen Dank für Ihre Nachrichten vom 09.07.2025 und vom 10.07.2025. Die BKK firmus hat 
am 07.07.2025 einen Bescheid erlassen. Es besteht kein weiterer Anspruch auf eine 
Genehmigungsfikton. Das Gutachten vom Medizinischen Dienst Niedersachsen liegt der 
BKK firmus bisher nicht vor.  
 
Ihr Widerspruch vom 09.07.2025 ist bei der BKK firmus eingegangen und befindet sich in 
Prüfung.         
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
14.07.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650114201740',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650114306636.pdf',
                'sha256' => 'bde08e860628f035548baf3467f98f61985394eefdc3f683a6a38444d2c98eb2',
                'filename' => '9650114306636.pdf',
                'file_type' => 'pdf',
                'file_size' => 420983,
                'title' => 'ABHILFEBESCHEID: Bewilligung der geschlechtsangleichenden Operation bis 15.01.2026',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Abhilfebescheid', 'Bewilligung', 'Geschlechtsangleichung', 'Operation', 'Neovagina', 'Erfolg', '15.01.2026', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'HERAUSRAGENDER ERFOLG / ABHILFEBESCHEID: Die BKK firmus hebt den Ablehnungsbescheid vom 07.07.2025 vollständig auf und BEWILLIGT die geschlechtsangleichende Operation (Entfernung Penis, Hoden, Bildung Neovulva und Neovagina)! Gültig bis 15.01.2026.',
                'key_facts' => ['bescheid_art' => 'Abhilfebescheid (Stattgabe)', 'leistung' => 'Geschlechtsangleichende Operation', 'befristet_bis' => '15.01.2026', 'aufgehobener_bescheid' => '07.07.2025', 'datum' => '15.07.2025'],
                'extracted_date' => '2025-07-15',
                'content_preview' => '--- Seite 1 ---
Abhilfebescheid  
Bewilligung der Kostenübernahme einer geschlechtsangleichenden Operationen 
(Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
am 09.07.2025 haben Sie Widerspruch gegen unseren Bescheid vom 07.07.2025 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie positiven Ergebnis geführt hat. 
 
Aus den vorliegenden Unterlagen kann eine sichere Diagnostik, eine ausreichende 
psychotherapeutische Behandlung, die Möglichkeit zur Weiterbehandlung, eine sorgfältig 
geführte gegengeschlechtliche Hormonersatztherapie, sowie eine ausreichenden Stabilität 
entnommen werden. Zudem kann ein Leidensdruck abgeleitet werden,  der mit der Methodik 
der Psychotherapie nicht zu lindern oder ausreichend zu beeinflussen ist.  
 
Ihrem Widerspruch haben wir somit abgeholfen. Gemäß § 63 Abs. 1 und 2 SGB X können 
die für das Widerspruchsverfahren notwendigen Aufwendungen, Gebühren und Auslagen 
erstattet werden. 
 
Der oben benannte Bescheid wird somit aufgehoben. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen. 
 
Diese Kostenzusage ist bis zu 6 Monate nach dem Ausstellungsdatum gültig (15.01.2026). 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Einschreiben 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.07.2025 
BKK firmus',
                'full_text' => '--- Seite 1 ---
Abhilfebescheid  
Bewilligung der Kostenübernahme einer geschlechtsangleichenden Operationen 
(Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
am 09.07.2025 haben Sie Widerspruch gegen unseren Bescheid vom 07.07.2025 eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie positiven Ergebnis geführt hat. 
 
Aus den vorliegenden Unterlagen kann eine sichere Diagnostik, eine ausreichende 
psychotherapeutische Behandlung, die Möglichkeit zur Weiterbehandlung, eine sorgfältig 
geführte gegengeschlechtliche Hormonersatztherapie, sowie eine ausreichenden Stabilität 
entnommen werden. Zudem kann ein Leidensdruck abgeleitet werden,  der mit der Methodik 
der Psychotherapie nicht zu lindern oder ausreichend zu beeinflussen ist.  
 
Ihrem Widerspruch haben wir somit abgeholfen. Gemäß § 63 Abs. 1 und 2 SGB X können 
die für das Widerspruchsverfahren notwendigen Aufwendungen, Gebühren und Auslagen 
erstattet werden. 
 
Der oben benannte Bescheid wird somit aufgehoben. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen. 
 
Diese Kostenzusage ist bis zu 6 Monate nach dem Ausstellungsdatum gültig (15.01.2026). 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Einschreiben 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
15.07.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650114306636

--- Seite 2 ---
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
Abhilfebescheid nach WS',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650117915024.pdf',
                'sha256' => '8fa16cde9df89ba1a724fa9a446a7312df2f6a0228f6b358af718be2237d3529',
                'filename' => '9650117915024.pdf',
                'file_type' => 'pdf',
                'file_size' => 238166,
                'title' => 'Unterlagenprüfung: Antrag Haarentfernung (Bart komplett) INOS',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Barthaarentfernung', 'INOS', 'MD-Begutachtung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'Mitteilung der BKK firmus, dass der erneute Antrag auf Haarentfernung (Bart komplett) mittels INOS-Technologie auf Vollständigkeit geprüft wurde und eine Begutachtung durch den MD erforderlich ist.',
                'key_facts' => ['leistung' => 'Haarentfernung Bart komplett', 'status' => 'MD Begutachtung veranlasst', 'datum' => '20.08.2025'],
                'extracted_date' => '2025-08-20',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf die Haarentfernung (Bart komplett) mittels INOS-Technologie 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf die Haarentfernung mittels INOS-Technologie haben wir auf Vollständigkeit 
geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
20.08.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650117915024',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf die Haarentfernung (Bart komplett) mittels INOS-Technologie 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf die Haarentfernung mittels INOS-Technologie haben wir auf Vollständigkeit 
geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
20.08.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650117915024',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650119701902.pdf',
                'sha256' => '7faadb88dd9fb91ad90ea18eba81abdc02e963c55c63fa2d44622d0b9347871f',
                'filename' => '9650119701902.pdf',
                'file_type' => 'pdf',
                'file_size' => 240400,
                'title' => 'Zwischenmitteilung: Verlängerung der Kostenübernahme für die geschlechtsangleichende OP',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Fristverlängerung', 'Operation', 'Geschlechtsangleichung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'Bestätigung des Eingangs des Antrags auf Fristverlängerung für die bewilligte geschlechtsangleichende Operation über den 15.01.2026 hinaus.',
                'key_facts' => ['antrag' => 'Verlängerung Kostenübernahme OP', 'status' => 'In Bearbeitung', 'datum' => '10.09.2025'],
                'extracted_date' => '2025-09-10',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen mit, dass wir die Kostenzusage 
der oben genannten Operation bis zum 30.06.2025 verlängern können. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen.  
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
10.09.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650119701902',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen mit, dass wir die Kostenzusage 
der oben genannten Operation bis zum 30.06.2025 verlängern können. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen.  
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Krankenhausabrechnung  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0541 33141-211   
Telefax: 
054133141-130  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
10.09.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650119701902',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650122075850.pdf',
                'sha256' => 'f97e2e4a097b4fbf9608477b923850a549a5982694acac207e887c7a67b39dba',
                'filename' => '9650122075850.pdf',
                'file_type' => 'pdf',
                'file_size' => 240761,
                'title' => 'BEWILLIGUNG DER VERLÄNGERUNG: Geschlechtsangleichende OP verlängert bis 30.06.2026',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Verlängerung', 'Bewilligung', 'Operation', 'Geschlechtsangleichung', '30.06.2026', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Geschlechtsangleichung & HRT\'.',
                'summary' => 'ERFOLG / BEWILLIGUNGSBESCHEID: Die BKK firmus verlängert die Kostenübernahme für die geschlechtsangleichende Operation antragsgemäß bis zum 30.06.2026!',
                'key_facts' => ['bescheid_art' => 'Bewilligung Verlängerung', 'leistung' => 'Geschlechtsangleichende OP', 'neue_frist' => '30.06.2026', 'datum' => '01.10.2025'],
                'extracted_date' => '2025-10-01',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen mit, dass wir die Kostenzusage 
der oben genannten Operation bis zum 30.06.2026 verlängern können. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen.  
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0541 331410   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
01.10.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650122075850',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Verlängerung der Kostenübernahme einer geschlechtsangleichenden 
Operation (Entfernung des Penis, der Hoden und Bildung von Neovulva und 
Neovagina)
  
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen jede Behandlung, 
wenn sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich ist. 
 
Wir haben Ihren Antrag auf Verlängerung der Kostenübernahme einer 
geschlechtsangleichenden Operation (Entfernung des Penis, der Hoden und Bildung von 
Neovulva und Neovagina) erhalten. Gerne teilen wir Ihnen mit, dass wir die Kostenzusage 
der oben genannten Operation bis zum 30.06.2026 verlängern können. 
 
Bitte beachten Sie: Die BKK firmus übernimmt die Kosten für Leistungen in einem nach 
§ 108 SGB V zugelassenen Krankenhaus. Alle Kosten, die über die Kassenleistungen 
hinausgehen (z.B. sogenannte Privatleistungen wie Chefarztkosten), sind durch Sie als 
Eigenleistung zu übernehmen.  
 
Für Fragen erreichen Sie uns über die BKK firmus APP oder telefonisch unter 0421 64343. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0541 331410   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
01.10.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650122075850',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650122855980.pdf',
                'sha256' => '4ff5322a5c8f29eccd93d74da83245ef355a05f8289118f5626e6ee97fec79db',
                'filename' => '9650122855980.pdf',
                'file_type' => 'pdf',
                'file_size' => 242845,
                'title' => 'Ablehnungsbescheid: Kostenübernahme Barthaarentfernung (Widerspruchsbelehrung)',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Ablehnungsbescheid', 'Barthaarentfernung', 'Bescheid vom 09.10.2025', 'Widerspruchsverfahren', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'Ablehnungsbescheid vom 09.10.2025 bezüglich der Kostenübernahme für die Barthaarentfernung / Epilation. Gegen diesen Bescheid legte Alina am 19.10.2025 erfolgreich Widerspruch ein!',
                'key_facts' => ['bescheid_art' => 'Ablehnungsbescheid', 'datum_bescheid' => '09.10.2025', 'folge' => 'Widerspruch durch Alina am 19.10.2025', 'datum' => '09.10.2025'],
                'extracted_date' => '2025-10-09',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Barthaarentfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Die Prüfung hat ergeben, dass einige Voraussetzungen für eine Kostenübernahme fehlen.  
 
Grundsätzlich besteht die medizinische Indikation für eine Epilation, sei es per Nadel- oder 
Laserepilation. Das Bundessozialgericht hat in der Verhandlung vom 17.12.2020 festgelegt, 
dass die Entfernung der Barthaare bei Transsexualität nur als ärztliche Leistung beansprucht 
werden kann. Für die Leistungserbringung durch Kosmetiker*innen oder 
Elektrologistiker*innen besteht keine Leistungspflicht der gesetzlichen Krankenkasse, sofern 
diese nicht selbstständig unter ärztlicher Leitung tätig werden.  
 
Im Hinblick auf die Abrechnungshöhe wird auf die GOP 02300 und 10340 des EBM sowie die 
GOÄ-Ziffer 742 hingewiesen. 
 
Eine Kostenübernahme für die in Ihrem Fall gewünschte Leistungserbringung durch die 
selbstständig tätige Kosmetikerin der Praxis Hairfree kann daher nicht befürwortet werden.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 64',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Barthaarentfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Die Prüfung hat ergeben, dass einige Voraussetzungen für eine Kostenübernahme fehlen.  
 
Grundsätzlich besteht die medizinische Indikation für eine Epilation, sei es per Nadel- oder 
Laserepilation. Das Bundessozialgericht hat in der Verhandlung vom 17.12.2020 festgelegt, 
dass die Entfernung der Barthaare bei Transsexualität nur als ärztliche Leistung beansprucht 
werden kann. Für die Leistungserbringung durch Kosmetiker*innen oder 
Elektrologistiker*innen besteht keine Leistungspflicht der gesetzlichen Krankenkasse, sofern 
diese nicht selbstständig unter ärztlicher Leitung tätig werden.  
 
Im Hinblick auf die Abrechnungshöhe wird auf die GOP 02300 und 10340 des EBM sowie die 
GOÄ-Ziffer 742 hingewiesen. 
 
Eine Kostenübernahme für die in Ihrem Fall gewünschte Leistungserbringung durch die 
selbstständig tätige Kosmetikerin der Praxis Hairfree kann daher nicht befürwortet werden.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
09.10.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650122855980

--- Seite 2 ---
Gerne hätten wir Ihnen eine positivere Nachricht mitgeteilt. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
Rechtsbehelfsbelehru',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650124914937.pdf',
                'sha256' => '490231a88df050a73b92dcc9d62e051d3c76b30c00555acb542a227c680a4a64',
                'filename' => '9650124914937.pdf',
                'file_type' => 'pdf',
                'file_size' => 240739,
                'title' => 'Eingangsbestätigung: Ihr Widerspruch vom 19.10.2025 (Barthaarentfernung)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruch', '19.10.2025', 'Barthaarentfernung', 'MD-Begutachtung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'BKK firmus bestätigt den Eingang des Widerspruchs vom 19.10.2025 gegen die Ablehnung der Barthaarentfernung und leitet die Akte zur erneuten Begutachtung an den Medizinischen Dienst.',
                'key_facts' => ['widerspruch_datum' => '19.10.2025', 'gegen_bescheid' => '09.10.2025', 'status' => 'Weiterleitung MD', 'datum' => '29.10.2025'],
                'extracted_date' => '2025-10-29',
                'content_preview' => '--- Seite 1 ---
Ihr Widerspruch vom 19.10.2025 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht nochmal durch den Medizinischen Dienst überprüfen lassen. Die 
Medizinischen Dienste beraten die gesetzlichen Krankenkassen in Fragen der allgemeinen 
medizinischen Versorgung und prüfen, welche Behandlung oder Therapie für Sie die 
Richtige ist und ob weitere Maßnahmen für Sie sinnvoll sind. 
 
Reichen Sie uns hierzu bitte noch folgende Unterlagen ein: 
 
- 
Eine ausführliche ärztliche Widerspruchsbegründung 
 
Sobald der Medizinische Dienst Ihren Fall begutachtet hat, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
       
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
29.10.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650124914937',
                'full_text' => '--- Seite 1 ---
Ihr Widerspruch vom 19.10.2025 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht nochmal durch den Medizinischen Dienst überprüfen lassen. Die 
Medizinischen Dienste beraten die gesetzlichen Krankenkassen in Fragen der allgemeinen 
medizinischen Versorgung und prüfen, welche Behandlung oder Therapie für Sie die 
Richtige ist und ob weitere Maßnahmen für Sie sinnvoll sind. 
 
Reichen Sie uns hierzu bitte noch folgende Unterlagen ein: 
 
- 
Eine ausführliche ärztliche Widerspruchsbegründung 
 
Sobald der Medizinische Dienst Ihren Fall begutachtet hat, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen oder wünschen Sie noch weitere Informationen? Gerne sprechen 
wir auch persönlich mit Ihnen über Ihre Wünsche und Fragen!  
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
 
 
       
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
29.10.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650124914937',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650125178105.pdf',
                'sha256' => '81c2a124fc490f2c02e345eed581ad48cd8b10765151a8fdfa73af29fab5b22e',
                'filename' => '9650125178105.pdf',
                'file_type' => 'pdf',
                'file_size' => 242079,
                'title' => 'ZENTRALER BESCHEID: Ende des Anspruchs auf Krankengeld (Aussteuerung zum 29.01.2026)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Aussteuerung', 'Ende Anspruch', '29.01.2026', '78 Wochen', '546 Tage', 'Rahmenfrist', 'Nahtlosigkeit', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'WICHTIGSTER AUSSTEUERUNGSBESCHEID: Die BKK firmus teilt verbindlich mit, dass der Anspruch auf Krankengeld nach 78 Wochen (= 546 Tagen) innerhalb der 3-Jahres-Rahmenfrist (18.06.2024 bis 17.06.2027) mit Ablauf des 29.01.2026 endet. Dringende Aufforderung zur Beantragung von Arbeitslosengeld (Nahtlosigkeit § 145 SGB III) bei der Agentur für Arbeit.',
                'key_facts' => ['bescheid_art' => 'Aussteuerungsbescheid', 'anspruchsende' => '29.01.2026', 'rahmenfrist' => '18.06.2024 bis 17.06.2027', 'hoechstdauer' => '78 Wochen (546 Tage)', 'handlungsempfehlung' => 'Meldung Arbeitsagentur für Nahtlosigkeit / ALG 1', 'datum' => '03.11.2025'],
                'extracted_date' => '2025-11-03',
                'content_preview' => '--- Seite 1 ---
Ende des Anspruchs auf Krankengeld      
 
 
Guten Tag Frau Steinhauer, 
 
der Anspruch auf Krankengeld wegen derselben Krankheit besteht für längstens 78 Wochen 
(= 546 Tage) innerhalb von 3 Jahren. Ihr Anspruch auf Krankengeld endet mit Ablauf des 
29.01.2026. 
 
Die für Sie maßgebende Rahmenfrist von drei Jahren verläuft vom 18.06.2024 bis zum 
17.06.2027. 
 
Auch wenn während der Arbeitsunfähigkeit eine weitere Krankheit hinzutritt, verlängert sich 
nicht die Dauer des Anspruchs von 78 Wochen. Die Arbeitsunfähigkeitszeiten, für die der 
Anspruch auf Krankengeld ruht (z. B. wegen Entgeltfortzahlung oder Bezug von 
Übergangsgeld), werden auf die Gesamtdauer angerechnet. 
 
Auf die Anspruchsdauer wurden in Ihrem Fall folgende Arbeitsunfähigkeitszeiten 
angerechnet: 18.06.2024 - 08.07.2024       
 
Damit Sie weiterhin über den 29.01.2026 finanziell abgesichert sind, wenden Sie sich mit 
diesem Schreiben bitte sofort an die Agentur für Arbeit. Sollte diesem Leistungsantrag nicht 
entsprochen werden, setzen Sie sich bitte mit dem für Sie zuständigen Sozialamt in 
Verbindung, damit Ihr Lebensunterhalt gesichert bleibt.       
 
Bei der Klärung Ihres weiteren Krankenversicherungsschutzes oder wenn Sie Fragen haben, 
sind wir gerne für Sie da. Rufen Sie uns an. 
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar 
schriftlich, zur Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-
Straße 11 in 28237 Bremen. Der Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle 
der BKK firmus eingereicht werden. Die Frist ist auch gewahrt, wenn der Widerspruch rechtzeitig bei einer 
anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer deutschen Konsularbehörde 
eingelegt wird.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler',
                'full_text' => '--- Seite 1 ---
Ende des Anspruchs auf Krankengeld      
 
 
Guten Tag Frau Steinhauer, 
 
der Anspruch auf Krankengeld wegen derselben Krankheit besteht für längstens 78 Wochen 
(= 546 Tage) innerhalb von 3 Jahren. Ihr Anspruch auf Krankengeld endet mit Ablauf des 
29.01.2026. 
 
Die für Sie maßgebende Rahmenfrist von drei Jahren verläuft vom 18.06.2024 bis zum 
17.06.2027. 
 
Auch wenn während der Arbeitsunfähigkeit eine weitere Krankheit hinzutritt, verlängert sich 
nicht die Dauer des Anspruchs von 78 Wochen. Die Arbeitsunfähigkeitszeiten, für die der 
Anspruch auf Krankengeld ruht (z. B. wegen Entgeltfortzahlung oder Bezug von 
Übergangsgeld), werden auf die Gesamtdauer angerechnet. 
 
Auf die Anspruchsdauer wurden in Ihrem Fall folgende Arbeitsunfähigkeitszeiten 
angerechnet: 18.06.2024 - 08.07.2024       
 
Damit Sie weiterhin über den 29.01.2026 finanziell abgesichert sind, wenden Sie sich mit 
diesem Schreiben bitte sofort an die Agentur für Arbeit. Sollte diesem Leistungsantrag nicht 
entsprochen werden, setzen Sie sich bitte mit dem für Sie zuständigen Sozialamt in 
Verbindung, damit Ihr Lebensunterhalt gesichert bleibt.       
 
Bei der Klärung Ihres weiteren Krankenversicherungsschutzes oder wenn Sie Fragen haben, 
sind wir gerne für Sie da. Rufen Sie uns an. 
 
Freundliche Grüße 
 
Ihre BKK firmus 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar 
schriftlich, zur Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-
Straße 11 in 28237 Bremen. Der Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle 
der BKK firmus eingereicht werden. Die Frist ist auch gewahrt, wenn der Widerspruch rechtzeitig bei einer 
anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer deutschen Konsularbehörde 
eingelegt wird.  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Krankengeld  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0541 33141250  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
03.11.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: CO',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650128163289.pdf',
                'sha256' => '4599fd54e7648c1255f5a9790e76f9bb08f2ab17c32dcaa44c0386633243c164',
                'filename' => '9650128163289.pdf',
                'file_type' => 'pdf',
                'file_size' => 238751,
                'title' => 'Unterlagenprüfung: Antrag auf Haarentfernung nach Widerspruch',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Haarentfernung', 'Widerspruch', 'MD-Begutachtung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'BKK firmus bestätigt den Eingang ergänzender fachärztlicher Unterlagen zur Haarentfernung im laufenden Widerspruchsverfahren und die erneute Vorlage beim MD.',
                'key_facts' => ['thema' => 'Haarentfernung Unterlagen', 'status' => 'Erneute MD Prüfung', 'datum' => '01.12.2025'],
                'extracted_date' => '2025-12-01',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Haarentfernung (nach Widerspruch) 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
01.12.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650128163289',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme der Haarentfernung (nach Widerspruch) 
 
 
Guten Tag Frau Steinhauer, 
 
vielen Dank für die Einreichung Ihrer Unterlagen.  
 
Ihren Antrag auf Kostenübernahme haben wir auf Vollständigkeit geprüft.  
 
Es ist eine Begutachtung durch den Medizinischen Dienst (MD) notwendig, um über Ihren 
Antrag entscheiden zu können.  
 
Da der Medizinische Dienst Ihren Antrag zurzeit prüft, bitten wir Sie um ein wenig Geduld. 
Darum verstehen Sie dieses Schreiben bitte als Zwischenmitteilung. Sobald uns die 
Begutachtung des MD vorliegt, werden wir Sie selbstverständlich so schnell wie möglich 
informieren.  
 
Sollten Sie in der Zwischenzeit Fragen haben, freuen wir uns auf Ihren Anruf unter der oben 
angegebenen Telefonnummer. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
01.12.2025 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSZMDK 
9650128163289',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650135013368.pdf',
                'sha256' => '8c4b234e6ad141f900d1f3368854c8b7a94e0eac1d4be807299a54abd78e8eee',
                'filename' => '9650135013368.pdf',
                'file_type' => 'pdf',
                'file_size' => 218458,
                'title' => 'Zwischenbescheid: Barthaarentfernung Begutachtung beim MD',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Barthaarentfernung', 'MD-Begutachtung', 'Frist', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Barthaarentfernung & Epilation\'.',
                'summary' => 'Schreiben der BKK firmus zur andauernden Begutachtung der Barthaarentfernung beim Medizinischen Dienst mit Fristsetzung zur Stellungnahme bis 02.02.2026.',
                'key_facts' => ['thema' => 'Barthaarentfernung', 'frist' => '02.02.2026', 'datum' => '20.01.2026'],
                'extracted_date' => '2026-01-20',
                'content_preview' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme für die Barthaarenfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Der Medizinische Dienst hat Ihren Antrag auf Barthaarentfernung erneut geprüft. Grund 
hierfür war die aktuelle sozialmedizinische Begutachtung im Rahmen des 
Widerspruchsverfahrens unter Einbeziehung der bereits vorliegenden Gutachten. 
 
Im Ergebnis der Begutachtung konnte leider keine Änderung gegenüber den 
vorangegangenen Entscheidungen festgestellt werden. Die sozialmedizinischen 
Voraussetzungen für eine Leistungsgewährung bzw. Kostenerstattung der beantragten nicht-
ärztlichen Epilation sind weiterhin nicht erfüllt. Eine Leistungspflicht der gesetzlichen 
Krankenversicherung besteht daher nach der geltenden Rechtslage nicht.    
 
Bitte teilen Sie uns bis zum 02.02.2026 ob Sie Ihren Widerspruch aufrechterhalten möchten 
oder zurückziehen möchten.  
 
Gerne hätten wir Ihnen eine positivere Nachricht mitgeteilt. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 6',
                'full_text' => '--- Seite 1 ---
Ihr Antrag auf Kostenübernahme für die Barthaarenfernung       
 
 
Guten Tag Frau Steinhauer, 
 
Ihre Gesundheit steht für uns immer im Vordergrund. Wir unterstützen Behandlungen, wenn 
sie medizinisch notwendig und zur Linderung einer Krankheit erforderlich sind.       
 
Um für Sie eine richtige Entscheidung treffen zu können, haben wir Ihre Unterlagen an den 
Medizinischen Dienst (MD) gesandt. Die Medizinischen Dienste beraten die gesetzlichen 
Krankenkassen in Fragen der allgemeinen medizinischen Versorgung und sprechen im 
Einzelfall Empfehlungen aus. Der MD prüft für uns zum Beispiel, ob eine Behandlung aus 
medizinischen Gründen erforderlich ist und wir die entstandenen Kosten übernehmen dürfen. 
Ein unabhängiger Gutachter des MD hat Ihre Unterlagen intensiv geprüft.      
 
Der Medizinische Dienst hat Ihren Antrag auf Barthaarentfernung erneut geprüft. Grund 
hierfür war die aktuelle sozialmedizinische Begutachtung im Rahmen des 
Widerspruchsverfahrens unter Einbeziehung der bereits vorliegenden Gutachten. 
 
Im Ergebnis der Begutachtung konnte leider keine Änderung gegenüber den 
vorangegangenen Entscheidungen festgestellt werden. Die sozialmedizinischen 
Voraussetzungen für eine Leistungsgewährung bzw. Kostenerstattung der beantragten nicht-
ärztlichen Epilation sind weiterhin nicht erfüllt. Eine Leistungspflicht der gesetzlichen 
Krankenversicherung besteht daher nach der geltenden Rechtslage nicht.    
 
Bitte teilen Sie uns bis zum 02.02.2026 ob Sie Ihren Widerspruch aufrechterhalten möchten 
oder zurückziehen möchten.  
 
Gerne hätten wir Ihnen eine positivere Nachricht mitgeteilt. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
20.01.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650135013368

--- Seite 2 ---
Alina Steinhauer, Carl-Goerdeler-Ring 26, 38518 Gifhorn  
KV-Nr. O603571189  
 
 
 
 
 
Zurück an: 
 
BKK firmus 
KS LEI HB  
28192 Bremen  
 
 
',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650136945228.pdf',
                'sha256' => 'd498155c943c6a4905467ff1377770e42d648df96b63dcc7dc83992792d25f12',
                'filename' => '9650136945228.pdf',
                'file_type' => 'pdf',
                'file_size' => 237498,
                'title' => 'Bestätigung: Aufrechterhaltung des Widerspruches vom 22.01.2026 (Vorlage Widerspruchsausschuss)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruch', 'Aufrechterhaltung', 'Widerspruchsausschuss', 'Rechtsmittel', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'BKK firmus bestätigt, dass Alina ihren Widerspruch ausdrücklich aufrechterhält. Die Entscheidung wird nun an den unabhängigen Widerspruchsausschuss der BKK firmus übergeben.',
                'key_facts' => ['widerspruch' => 'Aufrechterhalten', 'gremium' => 'Widerspruchsausschuss', 'datum' => '27.01.2026'],
                'extracted_date' => '2026-01-27',
                'content_preview' => '--- Seite 1 ---
Aufrechterhaltung des Widerspruches vom 22.01.2026  
 
 
Guten Tag Frau Steinhauer, 
 
mit Schreiben vom 22.01.2026 teilen Sie uns mit, dass Sie Ihren Widerspruch aufrecht erhalten. 
 
Die Entscheidung über Ihren Widerspruch trifft der Widerspruchsausschuss.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert. 
 
Bis dahin bitten wir Sie um etwas Geduld.  
 
Für eventuelle Fragen stehen wir Ihnen gerne zur Verfügung. 
 
Mit freundlichen Grüßen 
 
Ihre BKK firmus  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
27.01.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650136945228',
                'full_text' => '--- Seite 1 ---
Aufrechterhaltung des Widerspruches vom 22.01.2026  
 
 
Guten Tag Frau Steinhauer, 
 
mit Schreiben vom 22.01.2026 teilen Sie uns mit, dass Sie Ihren Widerspruch aufrecht erhalten. 
 
Die Entscheidung über Ihren Widerspruch trifft der Widerspruchsausschuss.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert. 
 
Bis dahin bitten wir Sie um etwas Geduld.  
 
Für eventuelle Fragen stehen wir Ihnen gerne zur Verfügung. 
 
Mit freundlichen Grüßen 
 
Ihre BKK firmus  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
27.01.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650136945228',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650150500199.pdf',
                'sha256' => '8856a709f3bd2dfc36cee2ab3792d5e59e8bcc9f210ebdc2c2906c221bfe453b',
                'filename' => '9650150500199.pdf',
                'file_type' => 'pdf',
                'file_size' => 238072,
                'title' => 'Eingangsbestätigung: Unterlagen zum Widerspruchsausschuss (Schreiben vom 01.04.2026)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruchsausschuss', 'Unterlagen', 'Eingangsbestätigung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'Bestätigung des Eingangs von Alinas ergänzendem Schreiben vom 01.04.2026 zur Weiterleitung an die Mitglieder des Widerspruchsausschusses.',
                'key_facts' => ['schreiben_alina' => '01.04.2026', 'status' => 'Weiterleitung Ausschuss', 'datum' => '10.04.2026'],
                'extracted_date' => '2026-04-10',
                'content_preview' => '--- Seite 1 ---
Ihre Unterlagen zum Widerspruch vom 19.10.2026       
 
 
Guten Tag Frau Steinhauer, 
 
hiermit bestätigen wir Ihnen den Eingang Ihres Schreibens vom 01.04.2026. 
 
Mit Schreiben vom 27.01.2026 teilten wir Ihnen mit, dass die Entscheidung über Ihren 
Widerspruch der Widerspruchsausschuss trifft.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert.  
 
Bis dahin bitten wir Sie um etwas Geduld. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
10.04.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
                'full_text' => '--- Seite 1 ---
Ihre Unterlagen zum Widerspruch vom 19.10.2026       
 
 
Guten Tag Frau Steinhauer, 
 
hiermit bestätigen wir Ihnen den Eingang Ihres Schreibens vom 01.04.2026. 
 
Mit Schreiben vom 27.01.2026 teilten wir Ihnen mit, dass die Entscheidung über Ihren 
Widerspruch der Widerspruchsausschuss trifft.  
 
Über das Ergebnis werden Sie im Anschluss an die Sitzung automatisch schriftlich informiert.  
 
Bis dahin bitten wir Sie um etwas Geduld. 
 
Bei Fragen sprechen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
10.04.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650160857472.pdf',
                'sha256' => '671824937e2af3eee4ba0ee12f2bad037173c24c9b7a4ff3175fee61cba0c0a1',
                'filename' => '9650160857472.pdf',
                'file_type' => 'pdf',
                'file_size' => 176300,
                'title' => 'Erklärung Entgeltersatzleistungen bei neuer Arbeitsunfähigkeit ab 22.06.2026',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Entgeltersatz', 'AU ab 22.06.2026', 'Selbstauskunft', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'Aufforderung der BKK firmus zur Einreichung der Entgeltersatz-Erklärung bei Eintritt einer neuen Arbeitsunfähigkeit ab dem 22.06.2026. Relevant für die Prüfung des neuen Krankengeldanspruchs nach der Nahtlosigkeit.',
                'key_facts' => ['neue_au' => '22.06.2026', 'thema' => 'Neuer Krankengeldanspruch', 'datum' => '25.06.2026'],
                'extracted_date' => '2026-06-25',
                'content_preview' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Frau Steinhauer,
Sie sind seit dem 22.06.2026 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
Kundenservice Leistungen
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Frau
Alina Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
25.06.2026
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BK',
                'full_text' => '--- Seite 1 ---
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Guten Tag Frau Steinhauer,
Sie sind seit dem 22.06.2026 arbeitsunfähig erkrankt.
Um Ihren Anspruch auf Entgeltersatzleistungen prüfen zu können, füllen Sie bitte die beigefügte 
Erklärung aus und senden uns diese schnellstmöglich zurück.
Gerne unterstützen wir Sie während Ihrer Arbeitsunfähigkeit ganz individuell. Hierfür benötigen 
wir die beigelegte Einwilligungserklärung.
Wir wünschen Ihnen eine gute Genesung.
Haben Sie noch Fragen? Für alle Fragen rund um das Thema Arbeitsunfähigkeit und 
Krankengeld erreichen Sie unsere Fachexperten unter der Service-Nummer
Krankengeld 0421 6434-403. Wir beraten Sie gerne.
Ihre BKK firmus
Datenschutzhinweis:
Die Daten werden zur Erfüllung unserer Aufgaben nach § 284 Abs. 1 Satz 1 Nr. 4 SGB V zum Zwecke der Leistungsprüfung 
erhoben und verarbeitet. Ihr Mitwirken ist nach § 60 SGB I erforderlich. Fehlende Mitwirkung kann zu Nachteilen bei der 
Leistungsgewährung führen. Empfänger Ihrer Daten können im Rahmen gesetzlicher Pflichten und Mitteilungsbefugnisse Dritte 
oder von uns beauftragte Dienstleister sein. Allgemeine Informationen zur Datenverarbeitung und zu Ihren Rechten im Rahmen der 
Datenschutzgrundverordnung finden Sie unter www.bkk-firmus.de/datenschutz oder stellen wir Ihnen auf Wunsch zur Verfügung. 
Bei Fragen wenden Sie sich an die BKK firmus, Gottlieb-Daimler-Straße 11, 28237 Bremen, oder unseren Datenschutzbeauftragten 
unter datenschutz@bkk-firmus.de. Dieser Nutzung können Sie jederzeit für die Zukunft widersprechen. 
Betriebskrankenkasse firmus
Körperschaft des öffentlichen Rechts
Kundenservice Leistungen
Gottlieb-Daimler-Straße 11
28237 Bremen
Telefon: 
0421 6434 403  
Telefax:
0421 6434-451
Webseite: www.bkk-firmus.de
Bei Antworten immer angeben:
O603571189
BKK firmus - 28192 Bremen  
Frau
Alina Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
 
Datum: 
25.06.2026
BKK firmus
Gottlieb-Daimler-Straße 11
28237 Bremen
Postanschrift
BKK firmus
28192 Bremen
Bankverbindung
Commerzbank AG
IBAN: DE53 2654 0070 0526 2100 00
BIC: COBADEFFXXX

--- Seite 2 ---
Zurück an:
BKK firmus
28192 Bremen
Arbeitsunfähigkeit/Reha-Maßnahme
22.06.2026
Name Vorname, Geburtstag des Versicherten
Versicherten-Nr.
Steinhauer, Alina, geboren am 01.09.1993
O603571189
Erklärung des Mitglieds zur Erlangung von Entgeltersatzleistungen
Frage
Antwort
 Ja,
Name: Noah Steinhauer
geb. am: 31.12.2022
Name:
geb. am: 
Name:
geb. am:
Name:
geb. am:
Name:
geb. am:
Ha',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650193779680.pdf',
                'sha256' => '732d75dd36ada9185faf8e15ab56cef1d1909e8b47635a3838ccbe6722f1bf19',
                'filename' => '9650193779680.pdf',
                'file_type' => 'pdf',
                'file_size' => 241762,
                'title' => 'RÜCKFORDERUNGSBESCHEID: Rückforderung Krankengeld 2.306,50 € (Zeitraum 19.12.2025 – 23.01.2026)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Rückforderung', '2.306,50 €', 'Krankengeld', 'Wartetage', 'Anhörung', 'Streitfall', 'Bescheid vom 07.07.2026', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Krankengeld & Entgeltersatz\'.',
                'summary' => 'AKUTER STREITFALL: BKK firmus fordert mit Bescheid vom 07.07.2026 Krankengeld in Höhe von 2.306,50 € für die Zeit vom 19.12.2025 bis 23.01.2026 zurück. Begründung: Angeblich fehlerhafte Anrechnung von 42 Wartetagen. Gegen diesen unberechtigten Rückforderungsbescheid hat Alina am 06.08.2026 Widerspruch eingelegt!',
                'key_facts' => ['bescheid_art' => 'Rückforderungsbescheid', 'rueckforderungsbetrag' => '2.306,50 €', 'zeitraum' => '19.12.2025 bis 23.01.2026', 'behaupteter_grund' => 'Falschangabe 42 Wartetage', 'rechtsmittel' => 'Widerspruch eingelegt am 06.08.2026 (siehe 9650219328069.pdf)', 'datum' => '07.07.2026'],
                'extracted_date' => '2026-07-07',
                'content_preview' => '--- Seite 1 ---
Rückforderung Krankengeld
 
 
 
Guten Tag Frau Steinhauer, 
 
mit Datum vom 29.12.2025 und 23.01.2026 haben wir Ihnen für die Zeit vom 19.12.2025 bis 
zum 23.01.2026 Krankengeld überwiesen.  
 
Nach unseren Berechnungen haben Sie insgesamt 2.306,50 € Krankengeld zu viel erhalten. 
Durch die Falschangabe der Wartetage von 42 Tagen, haben wir Ihr Krankengeld überzahlt.  
Daher möchten wir freundlich um Rückzahlung des überzahlten Betrages bitten. 
 
Wir bitten um Überweisung des Betrages innerhalb der nächsten 14 Tage auf das unten 
angegebene Konto. Geben Sie bitte bei der Überweisung die folgende Referenznummer 
1006165288 und 1006165289 an. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar schriftlich, zur 
Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-Straße 11 in 28237 Bremen. Der 
Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle der BKK firmus eingereicht werden. Die Frist ist auch 
gewahrt, wenn der Widerspruch rechtzeitig bei einer anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer 
deutschen Konsularbehörde eingelegt wird. 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
07.07.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
                'full_text' => '--- Seite 1 ---
Rückforderung Krankengeld
 
 
 
Guten Tag Frau Steinhauer, 
 
mit Datum vom 29.12.2025 und 23.01.2026 haben wir Ihnen für die Zeit vom 19.12.2025 bis 
zum 23.01.2026 Krankengeld überwiesen.  
 
Nach unseren Berechnungen haben Sie insgesamt 2.306,50 € Krankengeld zu viel erhalten. 
Durch die Falschangabe der Wartetage von 42 Tagen, haben wir Ihr Krankengeld überzahlt.  
Daher möchten wir freundlich um Rückzahlung des überzahlten Betrages bitten. 
 
Wir bitten um Überweisung des Betrages innerhalb der nächsten 14 Tage auf das unten 
angegebene Konto. Geben Sie bitte bei der Überweisung die folgende Referenznummer 
1006165288 und 1006165289 an. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
Rechtsbehelfsbelehrung 
Gegen diesen Bescheid können Sie innerhalb eines Monats nach Bekanntgabe Widerspruch erheben, und zwar schriftlich, zur 
Niederschrift oder in elektronischer Form nach § 36a SGB I bei der BKK firmus, Gottlieb-Daimler-Straße 11 in 28237 Bremen. Der 
Widerspruch kann auch bei jedem anderen Servicezentrum bzw. Servicestelle der BKK firmus eingereicht werden. Die Frist ist auch 
gewahrt, wenn der Widerspruch rechtzeitig bei einer anderen inländischen Behörde oder bei einem Versicherungsträger oder bei einer 
deutschen Konsularbehörde eingelegt wird. 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
07.07.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650219328069.pdf',
                'sha256' => '2b3622857bb8f330b1ad2e4a62c81c9ee825ed36627fdbd3b934531e7c5ff16a',
                'filename' => '9650219328069.pdf',
                'file_type' => 'pdf',
                'file_size' => 236634,
                'title' => 'Eingangsbestätigung: Widerspruch vom 06.08.2026 gegen die Krankengeld-Rückforderung',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruch', 'Rückforderung', '2.306,50 €', '06.08.2026', 'Eingangsbestätigung', 'MD-Überprüfung', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'WICHTIGER BEWEIS: BKK firmus bestätigt den fristgerechten Eingang von Alinas Widerspruch vom 06.08.2026 gegen den Rückforderungsbescheid über 2.306,50 €. Die BKK kündigt eine erneute Überprüfung durch den Medizinischen Dienst an. Aufschiebende Wirkung beachten!',
                'key_facts' => ['widerspruch_datum' => '06.08.2026', 'gegen_rueckforderung' => '2.306,50 € (Bescheid vom 07.07.2026)', 'status' => 'In Prüfung bei BKK/MD', 'datum' => '07.08.2026'],
                'extracted_date' => '2026-08-07',
                'content_preview' => '--- Seite 1 ---
Ihr Widerspruch vom 06.08.2026
 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen.      
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
07.08.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650219328069',
                'full_text' => '--- Seite 1 ---
Ihr Widerspruch vom 06.08.2026
 
 
 
Guten Tag Frau Steinhauer, 
 
Ihren Widerspruch haben wir erhalten. Gerne werden wir Ihren Widerspruch aus 
medizinischer Sicht erneut durch unsere beratenden Ärzte vom Medizinischen Dienst 
überprüfen lassen.      
 
Sobald unsere beratenden Ärzte Ihren Fall begutachtet haben, werden wir uns mit Ihnen in 
Verbindung setzen. Bis dahin bitten wir um etwas Geduld. 
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  
0421 6434403   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
07.08.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650219328069',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/9650222505257.pdf',
                'sha256' => '45757ea1a356c0530a924f048b18a4cc5574371004505e750a19fa11672a634a',
                'filename' => '9650222505257.pdf',
                'file_type' => 'pdf',
                'file_size' => 245859,
                'title' => 'ABHILFEBESCHEID: Barthaarentfernung - Ihr Widerspruch vom 19.10.2025 (Volle Bewilligung bis 31.08.2026)',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Abhilfebescheid', 'Stattgabe', 'Barthaarentfernung', 'Bewilligung', '31.08.2026', 'Erfolg', 'Digitaler Briefkasten'],
                'purpose' => 'Offizielles Dokument aus dem digitalen Briefkasten der BKK firmus zum Vorgang \'Bescheide & Rechtsmittel\'.',
                'summary' => 'GROSSER ERFOLG / ABHILFEBESCHEID: Die BKK firmus hilft dem Widerspruch vom 19.10.2025 gegen den Ablehnungsbescheid vom 09.10.2025 VOLLSTÄNDIG AB! Die Kosten für die Barthaarentfernung (Nadelepilation / Laser) werden antragsgemäß bis zum 31.08.2026 übernommen!',
                'key_facts' => ['bescheid_art' => 'Abhilfebescheid (Volle Stattgabe)', 'leistung' => 'Barthaarentfernung / Epilation', 'bewilligt_bis' => '31.08.2026', 'aufgehobener_bescheid' => '09.10.2025', 'widerspruch_datum' => '19.10.2025'],
                'extracted_date' => '2026-08-20',
                'content_preview' => '--- Seite 1 ---
Abhilfebescheid - Ihr Widerspruch vom 19.10.2025
 
 
Guten Tag Frau Steinhauer, 
 
am 19.10.2025 haben Sie Widerspruch gegen den Ablehnungsbescheid vom 09.10.2025 
eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Nach erneuter Prüfung der vorliegenden Unterlagen über mehrere Instanzen sehen wir 
von der Vorlage vor dem Widerspruchsausschuss ab und erstatten Ihnen den 
fehlenden Betrag in Höhe von 600,00 €. 
 
Ihrem Widerspruch vom 19.10.2025 haben wir somit abgeholfen.  
 
Gemäß § 63 Abs. 1 und 2 SGB X können die für das Widerspruchsverfahren notwendigen 
Aufwendungen, Gebühren und Auslagen erstattet werden. 
 
Der Bescheid mit unserem Schreiben vom 09.10.2025 wird somit aufgehoben. 
 
Bei Fragen rufen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
31.08.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
Abhilfebescheid nach WS 
9650222505257',
                'full_text' => '--- Seite 1 ---
Abhilfebescheid - Ihr Widerspruch vom 19.10.2025
 
 
Guten Tag Frau Steinhauer, 
 
am 19.10.2025 haben Sie Widerspruch gegen den Ablehnungsbescheid vom 09.10.2025 
eingelegt. 
 
Nach wiederholter Prüfung können wir Ihnen diesbezüglich mitteilen, dass Ihr Antrag jetzt 
genehmigt wird, da zwischenzeitlich die erneute Beurteilung des Sachverhaltes zu einem für 
Sie verbesserten Ergebnis geführt hat. 
 
Nach erneuter Prüfung der vorliegenden Unterlagen über mehrere Instanzen sehen wir 
von der Vorlage vor dem Widerspruchsausschuss ab und erstatten Ihnen den 
fehlenden Betrag in Höhe von 600,00 €. 
 
Ihrem Widerspruch vom 19.10.2025 haben wir somit abgeholfen.  
 
Gemäß § 63 Abs. 1 und 2 SGB X können die für das Widerspruchsverfahren notwendigen 
Aufwendungen, Gebühren und Auslagen erstattet werden. 
 
Der Bescheid mit unserem Schreiben vom 09.10.2025 wird somit aufgehoben. 
 
Bei Fragen rufen Sie uns gerne an. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
  
 
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
 
 
 
 
Datum:  
31.08.2026 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
Abhilfebescheid nach WS 
9650222505257',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Digitaler Briefkasten Chatverlauf.txt',
                'sha256' => '78b83ead0034e1012b074757c0741b40d40db9a45f0e1d19e1dbf382c106cfa3',
                'filename' => 'Digitaler Briefkasten Chatverlauf.txt',
                'file_type' => 'txt',
                'file_size' => 3550,
                'title' => 'Vollständiges Chatprotokoll: Digitaler Briefkasten Chatverlauf',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Auszahlung', '§ 44 SGB V', 'Entgeltersatz', 'Krankengeld', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Digitaler Briefkasten Chatverlauf.',
                'key_facts' => [],
                'extracted_date' => '2024-01-23',
                'content_preview' => 'Icon für Absender
Antrag

9650060300010.pdf (299 KB)
23.01.2024, 14:56:05
Icon für Absender
Antrag

9650061225545.pdf (544 KB)
05.02.2024, 09:31:48
Icon für Absender
Antrag

9650062101659.pdf (299 KB)
19.02.2024, 16:00:44
Icon für Absender
Antrag

9650062820219.pdf (299 KB)
28.02.2024, 07:17:27
Icon für Absender
Antrag

9650064287652.pdf (549 KB)
22.03.2024, 11:19:51
Icon für Absender
Antrag

9650067808155.pdf (298 KB)
31.05.2024, 13:02:40
Icon für Absender
Digitaler Posteingang

9650071371588.pdf (230 KB)
15.08.2024, 07:16:22
Icon für Absender
Antrag

9650071568085.pdf (317 KB)
15.08.2024, 10:36:50
Icon für Absender
Digitaler Posteingang

9650072456583.pdf (230 KB)
03.09.2024, 08:19:13
Icon für Absender
Therapie

9650072673267.pdf (312 KB)
06.09.2024, 09:59:38
Icon für Absender
Therapie

06.09.2024, 09:59:38
Icon für Absender
Krankengeld

9650073846318.pdf (371 KB)
26.09.2024, 17:25:45
Icon für Absender
Krankengeld

9650075219970.pdf (308 KB)
21.10.2024, 10:36:15
Icon für Absender
Krankengeld

9650075540649.pdf (310 KB)
28.10.2024, 09:18:33
Icon für Absender
Krankengeld

9650076822528.pdf (314 KB)
18.11.2024, 13:32:40
Icon für Absender
Antrag

9650078218824.pdf (329 KB)
11.12.2024, 06:16:45
Icon für Absender
Therapie

9650078585061.pdf (313 KB)
12.12.2024, 16:09:19
Icon für Absender
Stationäre Behandlung

9650078742642.pdf (544 KB)
16.12.2024, 11:38:39
Icon für Absender
Krankengeld

9650079272706.pdf (323 KB)
23.12.2024, 14:31:02
Icon für Absender
Krankengeld

9650083792245.pdf (309 KB)
30.01.2025, 16:37:48
Icon für Absender
Stationäre Behandlung

9650090394429.pdf (540 KB)
18.02.2025, 10:39:42
Icon für Absender
Versicherungsbescheinigung

9650090870349.pdf (319 KB)
20.02.2025, 10:16:20
Icon für Absender
Krankengeld

9650097392433.pdf (323 KB)
27.03.2025, 13:07:06
Icon für Absender
Krankengeld

9650100261190.pdf (327 KB)
23.04.2025, 10:01:42
Icon für Absender
Therapie

9650107004279.pdf (313 KB)
16.05.2025, 09:39:08
Icon für Absender
Stationäre Behandlung

9650111',
                'full_text' => 'Icon für Absender
Antrag

9650060300010.pdf (299 KB)
23.01.2024, 14:56:05
Icon für Absender
Antrag

9650061225545.pdf (544 KB)
05.02.2024, 09:31:48
Icon für Absender
Antrag

9650062101659.pdf (299 KB)
19.02.2024, 16:00:44
Icon für Absender
Antrag

9650062820219.pdf (299 KB)
28.02.2024, 07:17:27
Icon für Absender
Antrag

9650064287652.pdf (549 KB)
22.03.2024, 11:19:51
Icon für Absender
Antrag

9650067808155.pdf (298 KB)
31.05.2024, 13:02:40
Icon für Absender
Digitaler Posteingang

9650071371588.pdf (230 KB)
15.08.2024, 07:16:22
Icon für Absender
Antrag

9650071568085.pdf (317 KB)
15.08.2024, 10:36:50
Icon für Absender
Digitaler Posteingang

9650072456583.pdf (230 KB)
03.09.2024, 08:19:13
Icon für Absender
Therapie

9650072673267.pdf (312 KB)
06.09.2024, 09:59:38
Icon für Absender
Therapie

06.09.2024, 09:59:38
Icon für Absender
Krankengeld

9650073846318.pdf (371 KB)
26.09.2024, 17:25:45
Icon für Absender
Krankengeld

9650075219970.pdf (308 KB)
21.10.2024, 10:36:15
Icon für Absender
Krankengeld

9650075540649.pdf (310 KB)
28.10.2024, 09:18:33
Icon für Absender
Krankengeld

9650076822528.pdf (314 KB)
18.11.2024, 13:32:40
Icon für Absender
Antrag

9650078218824.pdf (329 KB)
11.12.2024, 06:16:45
Icon für Absender
Therapie

9650078585061.pdf (313 KB)
12.12.2024, 16:09:19
Icon für Absender
Stationäre Behandlung

9650078742642.pdf (544 KB)
16.12.2024, 11:38:39
Icon für Absender
Krankengeld

9650079272706.pdf (323 KB)
23.12.2024, 14:31:02
Icon für Absender
Krankengeld

9650083792245.pdf (309 KB)
30.01.2025, 16:37:48
Icon für Absender
Stationäre Behandlung

9650090394429.pdf (540 KB)
18.02.2025, 10:39:42
Icon für Absender
Versicherungsbescheinigung

9650090870349.pdf (319 KB)
20.02.2025, 10:16:20
Icon für Absender
Krankengeld

9650097392433.pdf (323 KB)
27.03.2025, 13:07:06
Icon für Absender
Krankengeld

9650100261190.pdf (327 KB)
23.04.2025, 10:01:42
Icon für Absender
Therapie

9650107004279.pdf (313 KB)
16.05.2025, 09:39:08
Icon für Absender
Stationäre Behandlung

9650111610893.pdf (312 KB)
19.06.2025, 15:37:15
Icon für Absender
Stationäre Behandlung

9650114201740.pdf (310 KB)
15.07.2025, 11:56:40
Icon für Absender
Stationäre Behandlung

9650114306636.pdf (549 KB)
15.07.2025, 12:16:49
Icon für Absender
Antrag

9650117915024.pdf (311 KB)
21.08.2025, 08:30:47
Icon für Absender
Stationäre Behandlung

9650119701902.pdf (314 KB)
10.09.2025, 11:46:11
Icon für Absender
Stationäre Behandlung

9650122075850.pdf (314 KB)
01.10.2025, 13:55:06
Icon für Absender
Antrag

9650',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/1723563105659.jpg',
                'sha256' => '150a1e54eabc15a41ecf6dd02b3d4367842004afee4ad3d262f874dc723d256e',
                'filename' => '1723563105659.jpg',
                'file_type' => 'jpg',
                'file_size' => 1645277,
                'title' => 'Zahlungsbeleg / Überweisungsnachweis',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Zahlungsnachweis', 'Beleg'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Bescheide & Rechtsmittel.',
                'summary' => 'Nachweis über getätigte Behandlungszahlung zur Kostenerstattung.',
                'key_facts' => [],
                'extracted_date' => '2024-08-13',
                'content_preview' => '[Bilddatei: 1723563105659.jpg - Zahlungsbeleg / Überweisungsnachweis. Zusammenfassung: Nachweis über getätigte Behandlungszahlung zur Kostenerstattung.]',
                'full_text' => '[Bilddatei: 1723563105659.jpg - Zahlungsbeleg / Überweisungsnachweis. Zusammenfassung: Nachweis über getätigte Behandlungszahlung zur Kostenerstattung.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/AErztlicheWiderspruchsbegruendung_Arzt.pdf',
                'sha256' => '1725adab4f21300135011b3967ae48fe9392755db4d224829a74ac24c9b4d4c4',
                'filename' => 'AErztlicheWiderspruchsbegruendung_Arzt.pdf',
                'file_type' => 'pdf',
                'file_size' => 139935,
                'title' => 'Ärztliche Widerspruchsbegründung zur Kostenübernahme der Barthaarentfernung',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Ärztliches Attest', 'Barthaarentfernung', '§ 84 SGG', 'Medizinische Notwendigkeit', 'Widerspruch', 'Frist', 'Bescheid'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Facharzt-Stellungnahme zur medizinischen Notwendigkeit der dauerhaften Epilation/Barthaarentfernung bei bestehender Diagnose und starkem Leidensdruck.',
                'key_facts' => [],
                'extracted_date' => '2024-02-15',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/AErztlicheWiderspruchsbegruendung_Therapeutin.pdf',
                'sha256' => 'bc8813b54a6d4235cd9b6654da8994cb29e148cc92e51962ef1131452fefb40a',
                'filename' => 'AErztlicheWiderspruchsbegruendung_Therapeutin.pdf',
                'file_type' => 'pdf',
                'file_size' => 127340,
                'title' => 'ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Lasertherapie', 'Widerspruch', 'Barthaare'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG Absender (Praxis / Therapeut*in): Frau Dipl.-Psych. Jill Tegge',
                'key_facts' => [],
                'extracted_date' => '1993-09-01',
                'content_preview' => '--- Seite 1 ---
ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG 
 
Absender (Praxis / Therapeut*in): 
Frau Dipl.-Psych. Jill Tegge 
Jasperallee 1 c, 38102 Braunschweig 
05 31 / 23 44 51 19 
An die 
BKK firmus 
Servicezentrum Bremen 
Gottlieb-Daimler-Str. 11 
28237 Bremen 
 
Betreff: Ärztliche Widerspruchsbegründung zur Kostenübernahme der medizinisch 
notwendigen Bart-Epilation 
Versicherte: Alina Steinhauer 
Geburtsdatum: 01.09.1993 
Versichertennummer: O603571189 
Datum: 05.11.2025 
 
Sehr geehrte Damen und Herren, 
hiermit bestätige ich als behandelnde Fachperson im Rahmen der geschlechtsangleichenden 
Behandlung von Frau Alina Steinhauer, dass die dauerhafte Entfernung der Barthaare aus 
medizinischer Sicht erforderlich ist. 
Frau Steinhauer erfüllt die diagnostischen und therapeutischen Kriterien gemäß ICD-10 F64.0 / 
ICD-11 HA60 – Genderinkongruenz. 
Die Patientin hat den Alltagstest erfolgreich abgeschlossen, befindet sich seit dem 
08.05.2024 in der Hormon-Ersatz-Behandlung (Östradiol + Antiandrogen) und zeigt eine 
stabile psychosoziale Entwicklung unter der Transition. 
Die geschlechtsangleichende Operation (GaOP) wurde bereits durch die BKK firmus bewilligt 
und ist für den 23.06.2026 terminiert. 
Damit ist die Frage des bestehenden Geschlechtsdysphorieleidens sowie der medizinischen 
Notwendigkeit der Transition bereits eindeutig anerkannt. 
 
Medizinische Begründung 
Der Bartwuchs ist eine der sichtbarsten sekundären männlichen Geschlechtsmerkmale. 
Selbst bei maximaler feminisierender Hormonersatztherapie bleibt dieser dauerhaft bestehen, 
da androgeninduzierte Terminalhaare nicht rückbildbar sind. 
Die verbleibende, sichtbare Barthaarstruktur führt bei der Patientin zu: 
• 
Dauerhafter Dysphorie und erheblichem psychischen Leidensdruck 
• 
Sozialen Rückzugstendenzen 
• 
Starker Einschränkung im Alltag (insbesondere in Öffentlichkeit, Beruf und sozialen 
Kontakten)

--- Seite 2 ---
ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG 
 
• 
Starker emotionaler Belastung beim täglichen Ve',
                'full_text' => '--- Seite 1 ---
ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG 
 
Absender (Praxis / Therapeut*in): 
Frau Dipl.-Psych. Jill Tegge 
Jasperallee 1 c, 38102 Braunschweig 
05 31 / 23 44 51 19 
An die 
BKK firmus 
Servicezentrum Bremen 
Gottlieb-Daimler-Str. 11 
28237 Bremen 
 
Betreff: Ärztliche Widerspruchsbegründung zur Kostenübernahme der medizinisch 
notwendigen Bart-Epilation 
Versicherte: Alina Steinhauer 
Geburtsdatum: 01.09.1993 
Versichertennummer: O603571189 
Datum: 05.11.2025 
 
Sehr geehrte Damen und Herren, 
hiermit bestätige ich als behandelnde Fachperson im Rahmen der geschlechtsangleichenden 
Behandlung von Frau Alina Steinhauer, dass die dauerhafte Entfernung der Barthaare aus 
medizinischer Sicht erforderlich ist. 
Frau Steinhauer erfüllt die diagnostischen und therapeutischen Kriterien gemäß ICD-10 F64.0 / 
ICD-11 HA60 – Genderinkongruenz. 
Die Patientin hat den Alltagstest erfolgreich abgeschlossen, befindet sich seit dem 
08.05.2024 in der Hormon-Ersatz-Behandlung (Östradiol + Antiandrogen) und zeigt eine 
stabile psychosoziale Entwicklung unter der Transition. 
Die geschlechtsangleichende Operation (GaOP) wurde bereits durch die BKK firmus bewilligt 
und ist für den 23.06.2026 terminiert. 
Damit ist die Frage des bestehenden Geschlechtsdysphorieleidens sowie der medizinischen 
Notwendigkeit der Transition bereits eindeutig anerkannt. 
 
Medizinische Begründung 
Der Bartwuchs ist eine der sichtbarsten sekundären männlichen Geschlechtsmerkmale. 
Selbst bei maximaler feminisierender Hormonersatztherapie bleibt dieser dauerhaft bestehen, 
da androgeninduzierte Terminalhaare nicht rückbildbar sind. 
Die verbleibende, sichtbare Barthaarstruktur führt bei der Patientin zu: 
• 
Dauerhafter Dysphorie und erheblichem psychischen Leidensdruck 
• 
Sozialen Rückzugstendenzen 
• 
Starker Einschränkung im Alltag (insbesondere in Öffentlichkeit, Beruf und sozialen 
Kontakten)

--- Seite 2 ---
ÄRZTLICHE WIDERSPRUCHSBEGRÜNDUNG 
 
• 
Starker emotionaler Belastung beim täglichen Versuch, das Barthaar zu kaschieren 
Die medizinische Fachgesellschaft (AWMF-Leitlinie 138-001) empfiehlt für trans Frauen 
ausdrücklich eine dauerhafte Entfernung der Gesichtsbehaarung, sofern Dysphorie vorliegt. 
Die Epilation ist damit Bestandteil der anerkannten Regelversorgung. 
 
Zusammenfassung 
Die dauerhafte Bart-Epilation ist: 
• 
medizinisch notwendig, nicht kosmetisch 
• 
Teil der leitliniengerechten Behandlung einer Geschlechtsinkongruenz 
• 
für das seelische Wohlbefinden der Patient',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/AlleBelege.pdf',
                'sha256' => '129b429a66064b6aec7ba3edd71b3580b9db5dea3808c9b74afbd28e5ed2b1c4',
                'filename' => 'AlleBelege.pdf',
                'file_type' => 'pdf',
                'file_size' => 345579,
                'title' => 'Zusammenstellung aller Rechnungsbelege und Quittungen für Kostenerstattung',
                'category' => 'Bescheide & Rechtsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Rechnungen', 'Belege', '§ 13 SGB V', 'Schriftverkehr', 'Kostenerstattung'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Bescheide & Rechtsmittel.',
                'summary' => 'Sammelbeleg aller verauslagten Rechnungen (Psychotherapie, Medikamente, Behandlungskosten) zur Geltendmachung nach § 13 Abs. 3 SGB V.',
                'key_facts' => [],
                'extracted_date' => '2024-06-01',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Allianz_Zusage.jpg',
                'sha256' => '6f6df899afc4c0ded0aef701d8297bf302d91c327e0a0e9ec073ff6f6ad0f137',
                'filename' => 'Allianz_Zusage.jpg',
                'file_type' => 'jpg',
                'file_size' => 360332,
                'title' => 'Allianz Private Krankenversicherung Kostenübernahmezusage',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Vergleichsbescheid', 'Allianz', 'Kostenübernahme'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Vergleichsnachweis über bewilligte Epilations- und Therapiemaßnahmen.',
                'key_facts' => [],
                'extracted_date' => '2023-09-01',
                'content_preview' => '[Bilddatei: Allianz_Zusage.jpg - Allianz Private Krankenversicherung Kostenübernahmezusage. Zusammenfassung: Vergleichsnachweis über bewilligte Epilations- und Therapiemaßnahmen.]',
                'full_text' => '[Bilddatei: Allianz_Zusage.jpg - Allianz Private Krankenversicherung Kostenübernahmezusage. Zusammenfassung: Vergleichsnachweis über bewilligte Epilations- und Therapiemaßnahmen.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/AOK_Zusage.jpg',
                'sha256' => 'bb9a2364d016f1184d1d1493ce78207599aa47352e77bfd4154ab542b322ac0f',
                'filename' => 'AOK_Zusage.jpg',
                'file_type' => 'jpg',
                'file_size' => 427137,
                'title' => 'AOK Vergleichsbescheid / Zusage Kostenübernahme Barthaarentfernung',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Vergleichsbescheid', 'AOK', 'Kostenübernahme', 'Präzedenzfall', '§ 13 SGB V'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Vergleichsfall / Bewilligungsbescheid der AOK über die Kostenübernahme für ambulante Laserepilation als Referenzbeleg für Systemversagen.',
                'key_facts' => [],
                'extracted_date' => '2023-08-10',
                'content_preview' => '[Bilddatei: AOK_Zusage.jpg - AOK Vergleichsbescheid / Zusage Kostenübernahme Barthaarentfernung. Zusammenfassung: Vergleichsfall / Bewilligungsbescheid der AOK über die Kostenübernahme für ambulante Laserepilation als Referenzbeleg für Systemversagen.]',
                'full_text' => '[Bilddatei: AOK_Zusage.jpg - AOK Vergleichsbescheid / Zusage Kostenübernahme Barthaarentfernung. Zusammenfassung: Vergleichsfall / Bewilligungsbescheid der AOK über die Kostenübernahme für ambulante Laserepilation als Referenzbeleg für Systemversagen.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/AufrechterhaltungmeinesWiderspruchsundNachforderung.pdf',
                'sha256' => '93a3549897a656ab85784102ae0cd6314a7a124688023866c0bc3d44b4ef4600',
                'filename' => 'AufrechterhaltungmeinesWiderspruchsundNachforderung.pdf',
                'file_type' => 'pdf',
                'file_size' => 113892,
                'title' => 'Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung mein',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Ablehnungsbescheid', 'Medizinischer Dienst (MD)', 'Lasertherapie', 'Widerspruch', '§ 13 Abs. 3 SGB V', 'Barthaare'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung meines Widerspruchs gegen den Bescheid vom 09.10.2025 2. Nachforderung der noch ausstehenden Erstattung (600,00 EUR) nach § 13 Abs. 3 SGB V',
                'key_facts' => [],
                'extracted_date' => '2025-11-19',
                'content_preview' => '--- Seite 1 ---
Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung meines 
Widerspruchs gegen den Bescheid vom 09.10.2025 2. Nachforderung der noch 
ausstehenden Erstattung (600,00 EUR) nach § 13 Abs. 3 SGB V 
 
Versicherte: Alina Steinhauer, Vers. Nr: O603571189 
Sehr geehrte Damen und Herren, 
ich beziehe mich auf Ihr Schreiben vom 19.11.2025 und den Zahlungseingang von 1.400,00 EUR, 
den ich als Teilanerkenntnis verbuche. 
In Ihrer Abrechnung haben Sie lediglich 7 der insgesamt 10 eingereichten Behandlungstermine 
erstattet (Zeitraum 13.02.2024 bis 09.12.2024). Es fehlen jedoch weiterhin die Erstattungen für 
drei durchgeführte und belegte Behandlungen. 
Hiermit mache ich die Restforderung in Höhe von 600,00 EUR geltend. 
Hierzu trage ich Folgendes vor: 
1. Aufstellung der noch offenen Belege Wie Sie den beigefügten Zahlungsnachweisen (Anlage 
"Alle Belege.pdf") entnehmen können, fanden folgende Behandlungen im Rahmen der 
notwendigen Therapie statt, wurden von Ihnen aber bisher nicht berücksichtigt: 
• 
02.01.2024 – 200,00 EUR (Beleg-Nr. 1123) 
• 
11.02.2025 – 200,00 EUR (Beleg-Nr. 0194) 
• 
14.04.2025 – 200,00 EUR (Beleg-Nr. 0341) 
Gesamtsumme offen: 600,00 EUR 
2. Begründung der Erstattungspflicht (§ 13 Abs. 3 SGB V - Systemversagen) Sie verweigern 
die Übernahme für die Behandlungen im Jahr 2025 sowie für den Beginn der Therapie (Januar 
2024) mit dem Verweis auf Fristen oder fehlende Vertragsarzteneigenschaften. Diese 
Argumentation ist rechtlich nicht haltbar. 
a) Zu den Behandlungen 2025 (Systemversagen): Auch für die Behandlungen im Jahr 2025 
besteht ein voller Erstattungsanspruch, der sich unabhängig von der Genehmigungsfiktion direkt 
aus dem Systemversagen ergibt. Da der ursprüngliche Ablehnungsgrund (fehlende Unterlagen 
für den MDK) durch meine Nachreichungen entfallen ist und der MDK die medizinische 
Notwendigkeit grundsätzlich bestätigt hat (Bescheid vom 09.10.2025), ist die Behandlung 
medizinisch indiziert. Da Sie mir k',
                'full_text' => '--- Seite 1 ---
Ihr Schreiben vom 19.11.2025 – Aktenzeichen 0603571189 1. Aufrechterhaltung meines 
Widerspruchs gegen den Bescheid vom 09.10.2025 2. Nachforderung der noch 
ausstehenden Erstattung (600,00 EUR) nach § 13 Abs. 3 SGB V 
 
Versicherte: Alina Steinhauer, Vers. Nr: O603571189 
Sehr geehrte Damen und Herren, 
ich beziehe mich auf Ihr Schreiben vom 19.11.2025 und den Zahlungseingang von 1.400,00 EUR, 
den ich als Teilanerkenntnis verbuche. 
In Ihrer Abrechnung haben Sie lediglich 7 der insgesamt 10 eingereichten Behandlungstermine 
erstattet (Zeitraum 13.02.2024 bis 09.12.2024). Es fehlen jedoch weiterhin die Erstattungen für 
drei durchgeführte und belegte Behandlungen. 
Hiermit mache ich die Restforderung in Höhe von 600,00 EUR geltend. 
Hierzu trage ich Folgendes vor: 
1. Aufstellung der noch offenen Belege Wie Sie den beigefügten Zahlungsnachweisen (Anlage 
"Alle Belege.pdf") entnehmen können, fanden folgende Behandlungen im Rahmen der 
notwendigen Therapie statt, wurden von Ihnen aber bisher nicht berücksichtigt: 
• 
02.01.2024 – 200,00 EUR (Beleg-Nr. 1123) 
• 
11.02.2025 – 200,00 EUR (Beleg-Nr. 0194) 
• 
14.04.2025 – 200,00 EUR (Beleg-Nr. 0341) 
Gesamtsumme offen: 600,00 EUR 
2. Begründung der Erstattungspflicht (§ 13 Abs. 3 SGB V - Systemversagen) Sie verweigern 
die Übernahme für die Behandlungen im Jahr 2025 sowie für den Beginn der Therapie (Januar 
2024) mit dem Verweis auf Fristen oder fehlende Vertragsarzteneigenschaften. Diese 
Argumentation ist rechtlich nicht haltbar. 
a) Zu den Behandlungen 2025 (Systemversagen): Auch für die Behandlungen im Jahr 2025 
besteht ein voller Erstattungsanspruch, der sich unabhängig von der Genehmigungsfiktion direkt 
aus dem Systemversagen ergibt. Da der ursprüngliche Ablehnungsgrund (fehlende Unterlagen 
für den MDK) durch meine Nachreichungen entfallen ist und der MDK die medizinische 
Notwendigkeit grundsätzlich bestätigt hat (Bescheid vom 09.10.2025), ist die Behandlung 
medizinisch indiziert. Da Sie mir keinen Vertragsarzt für diese Leistung (Nadelepilation/Laser im 
Gesicht) zur Verfügung stellen können (faktische Versorgungslücke), sind Sie verpflichtet, die 
Kosten der selbstbeschafften Leistung bei qualifizierten Anbietern wie Hairfree zu erstatten. 
Dies gilt für die gesamte Dauer der Behandlung, also auch für die Termine am 11.02.2025 und 
14.04.2025. 
b) Zur Behandlung Januar 2024: Diese Behandlung ist integraler Bestandteil der medizinisch 
notwendigen Gesamttherapie. Aufgrund des hohen ',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Barthaarentfernung_Verlauf.pdf',
                'sha256' => '6c2c327de475baf77ddb8fad2ee25fc70893423679337c49238039425febb7ae',
                'filename' => 'Barthaarentfernung_Verlauf.pdf',
                'file_type' => 'pdf',
                'file_size' => 156519,
                'title' => 'Barthaarentferung – Alina Steinhauer',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'Lasertherapie', 'MDK-Begutachtung', 'Barthaare'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Barthaarentferung – Alina Steinhauer 01.01.2024 – 10.06.2025 Ich schreite voran wie man hier sehen kann',
                'key_facts' => [],
                'extracted_date' => '2024-01-01',
                'content_preview' => '--- Seite 1 ---
Barthaarentferung – Alina Steinhauer 
01.01.2024 – 10.06.2025 
 
 
 
 
 
Ich schreite voran wie man hier sehen kann',
                'full_text' => '--- Seite 1 ---
Barthaarentferung – Alina Steinhauer 
01.01.2024 – 10.06.2025 
 
 
 
 
 
Ich schreite voran wie man hier sehen kann',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/BKKGesundheit_Zusage.jpg',
                'sha256' => 'd87d6479c93a52336b1d4e169169ca7376dfd7c17803c68c81bc8baf93bda8dd',
                'filename' => 'BKKGesundheit_Zusage.jpg',
                'file_type' => 'jpg',
                'file_size' => 421829,
                'title' => 'BKK Gesundheit Bewilligung Kostenübernahme',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'BKK Zusage', 'Vergleichsfall', 'Barthaarentfernung'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Vergleichszusage einer anderen Betriebskrankenkasse für geschlechtsangleichende Epilationsbehandlung.',
                'key_facts' => [],
                'extracted_date' => '2023-09-15',
                'content_preview' => '[Bilddatei: BKKGesundheit_Zusage.jpg - BKK Gesundheit Bewilligung Kostenübernahme. Zusammenfassung: Vergleichszusage einer anderen Betriebskrankenkasse für geschlechtsangleichende Epilationsbehandlung.]',
                'full_text' => '[Bilddatei: BKKGesundheit_Zusage.jpg - BKK Gesundheit Bewilligung Kostenübernahme. Zusammenfassung: Vergleichszusage einer anderen Betriebskrankenkasse für geschlechtsangleichende Epilationsbehandlung.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/DermatologischerBefundbericht_Dr.P.Sander.pdf',
                'sha256' => '6aa45e20f7fd1aa0caf1e9d456d877b6bd59062559809ffb284929331bb434ee',
                'filename' => 'DermatologischerBefundbericht_Dr.P.Sander.pdf',
                'file_type' => 'pdf',
                'file_size' => 109293,
                'title' => 'Dr. med Patrick Sander',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'Lasertherapie', 'MDK-Begutachtung', 'Barthaare'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Dr. med Patrick Sander Goethestraße 4 - 8 Frankfurt, den 18.04.2024',
                'key_facts' => [],
                'extracted_date' => '2024-04-18',
                'content_preview' => '--- Seite 1 ---
Dr. med Patrick Sander 
Goethestraße 4 - 8 
60313 Frankfurt 
 
 
Frankfurt, den 18.04.2024 
 
Dermatologischer Bericht über Nick Steinhauer, geb. am 01.09.1993, wohnhaft in 
Carl-Goerdeler-Ring 26, 38518 Gifhorn: 
 
 
Die Geschlechtsdysphorie besteht bereits das gesamte Leben. Durch die tägliche 
Bartrasur besteht ein erhöhtes Risiko für die Entwicklung einer irritativen, schmerzhaften 
Dermatitis sowie bakteriellen Superinfektionsgefahr.  
 
Des Weiteren haben die Bartstoppeln, Gesichtsbehaarung sowie der Bartschatten einen 
optisch störenden Effekt insb. nach Auftragen der Gesichtsschminke.  
 
Zusammenfassend wird hierdurch die allgemeine Lebensqualität von Nick Steinhauer 
sowohl in der Schule als auch im Arbeitsleben erheblich beeinträchtigt. 
 
 
 
 
 
 
Mit freundlichen Grüßen 
Praxis Dr Sander

--- Seite 2 ---
Anamnese: 
1. Seit wann besteht die Geschlechtsdysphorie? 
a. Das gesamte Leben. 
 
2. Wie äußert sich die Geschlechtsdysphorie? 
a. Bezogen auf den Bart: 
i. Tägliches mehrfaches rasieren, was wiederum zur Reizung der Haut 
beiträgt 
ii. Der Bartschatten und die stoppeln die man beim Schminken bemerkt 
iii. Gesichtsbehaarung erinnert einen immer wieder daran, nicht man 
selbst sein zu können 
b. Allgemein: 
i. Man fühlt sich einfach falsch 
ii. In jeder Lebenssituation hat es mich negativ in irgendeiner Form 
beeinflusst (In der Schule, in der Arbeitswelt und überall anders 
auch) 
iii. Man fühlt eine gewisse Unzufriedenheit und leere, egal was man im 
Leben erreicht oder getan hat 
 
3. Welche Therapien wurden bereits durchgeführt? 
a. Laserbehandlung über „Hairfree“ in Wolfsburg. Gestartet am 02.01.2024 
 
4. Welche Medikamente werden aktuell eingenommen? 
a. Keine 
 
5. Bestehen relevante Vorerkrankungen? 
a. Nein',
                'full_text' => '--- Seite 1 ---
Dr. med Patrick Sander 
Goethestraße 4 - 8 
60313 Frankfurt 
 
 
Frankfurt, den 18.04.2024 
 
Dermatologischer Bericht über Nick Steinhauer, geb. am 01.09.1993, wohnhaft in 
Carl-Goerdeler-Ring 26, 38518 Gifhorn: 
 
 
Die Geschlechtsdysphorie besteht bereits das gesamte Leben. Durch die tägliche 
Bartrasur besteht ein erhöhtes Risiko für die Entwicklung einer irritativen, schmerzhaften 
Dermatitis sowie bakteriellen Superinfektionsgefahr.  
 
Des Weiteren haben die Bartstoppeln, Gesichtsbehaarung sowie der Bartschatten einen 
optisch störenden Effekt insb. nach Auftragen der Gesichtsschminke.  
 
Zusammenfassend wird hierdurch die allgemeine Lebensqualität von Nick Steinhauer 
sowohl in der Schule als auch im Arbeitsleben erheblich beeinträchtigt. 
 
 
 
 
 
 
Mit freundlichen Grüßen 
Praxis Dr Sander

--- Seite 2 ---
Anamnese: 
1. Seit wann besteht die Geschlechtsdysphorie? 
a. Das gesamte Leben. 
 
2. Wie äußert sich die Geschlechtsdysphorie? 
a. Bezogen auf den Bart: 
i. Tägliches mehrfaches rasieren, was wiederum zur Reizung der Haut 
beiträgt 
ii. Der Bartschatten und die stoppeln die man beim Schminken bemerkt 
iii. Gesichtsbehaarung erinnert einen immer wieder daran, nicht man 
selbst sein zu können 
b. Allgemein: 
i. Man fühlt sich einfach falsch 
ii. In jeder Lebenssituation hat es mich negativ in irgendeiner Form 
beeinflusst (In der Schule, in der Arbeitswelt und überall anders 
auch) 
iii. Man fühlt eine gewisse Unzufriedenheit und leere, egal was man im 
Leben erreicht oder getan hat 
 
3. Welche Therapien wurden bereits durchgeführt? 
a. Laserbehandlung über „Hairfree“ in Wolfsburg. Gestartet am 02.01.2024 
 
4. Welche Medikamente werden aktuell eingenommen? 
a. Keine 
 
5. Bestehen relevante Vorerkrankungen? 
a. Nein',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Endo_Befund_Bericht.pdf',
                'sha256' => '8fcb9cf7727a35f96ccd60eaeddda74f1187cd7526dd93bc88884e953bfa67e8',
                'filename' => 'Endo_Befund_Bericht.pdf',
                'file_type' => 'pdf',
                'file_size' => 849600,
                'title' => 'Endokrinologischer Befundbericht zur Hormonsubstitution',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Endokrinologie', 'Hormontherapie', 'Befundbericht', 'Schriftverkehr', 'Laborbefunde'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.',
                'summary' => 'Befund und Laborwerte des Endokrinologikums zur Einleitung und Überwachung der Hormonersatztherapie (HRT).',
                'key_facts' => [],
                'extracted_date' => '2024-04-10',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Erstattungen Chatverlauf.txt',
                'sha256' => 'a51b8f348a216aa7844b69305197b6f5c98bf114e031ac4e6873886a20830444',
                'filename' => 'Erstattungen Chatverlauf.txt',
                'file_type' => 'txt',
                'file_size' => 20943,
                'title' => 'Vollständiges Chatprotokoll: Erstattungen Chatverlauf',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Ablehnungsbescheid', '§ 44 SGB V', 'Medizinischer Dienst (MD)', 'Lasertherapie', 'Widerspruch', '§ 13 Abs. 3 SGB V', 'Barthaare', 'Chatprotokoll'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Vollständiger BKK Firmus Chatverlauf zu Erstattungen Chatverlauf.',
                'key_facts' => [],
                'extracted_date' => '2023-02-04',
                'content_preview' => 'Einkauf lefax Pump liquid 50ml ppl für unseren Sohn Noah.

Bauchschmerzen und Blähungen

NachrichtAnhang1.jpg (886 KB)
04.02.2023, 17:54:53
Icon für Absender

vielen Dank für Ihren Antrag auf Kostenübernahme für das Arzneimittel.
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise um ein nicht verschreibungspflichtiges Arzneimittel handelt und daher die Kosten nicht von der Gesetzlichen Krankenversicherung übernommen werden können.
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über die Verordnung von Arzneimitteln in der vertragsärztlichen Versorgung) sind diese Präparate als Eigenleistung vollständig selbst zu zahlen.
Eine Erstattung Ihrer angefallenen Kosten ist uns daher leider nicht möglich.

06.02.2023, 10:02:09
Icon für Absender
Sehr geehrter Herr Steinhauer,

wie telefonisch besprochen erhalten Sie anhängend den Antrag auf Erstattung der Impfkosten.

Freundliche Grüße

Ihre BKK firmus

Impfkosten.pdf (117 KB)
22.03.2023, 12:25:46
Icon für Absender
sehr geehrte Damen und Herren,

gibt es über die BKK firmus die Möglichkeit, eine Hormontherapie und eine Geschlechtsangleichende Operation bezahlen zu lassen?

Wenn ja, was sind die Voraussetzungen damit sie die Kosten übernehmen würden?

ich befinde mich aktuell in einer Therapie. Auch hier würde ich gerne wissen ob sie die Therapie kosten übernehmen? Ich erhalte für jede Sitzung eine Rechnung von der Therapeutin.

Ich würde mich über eine ausführliche Antwort bzgl. der genannten Punkte freuen.

vielen Dank.

schöne Grüße,
Steinhauer

09.11.2023, 10:32:24
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage. Bzgl. der Hormontherapie benötigen wir einen Antrag mit medizinischer Begründung vom Arzt. Es muss mitgeteilt werden, welche Therapie genau für Sie in Frage kommt. Wenn wir diese Unterlagen vorliegen haben, prüfen wir, ob eine Kostenübernahme möglich ist.
Die Psychotherapie müsste eigentlich bei uns beantragt und dann auch direkt mit uns abgerechnet we',
                'full_text' => 'Einkauf lefax Pump liquid 50ml ppl für unseren Sohn Noah.

Bauchschmerzen und Blähungen

NachrichtAnhang1.jpg (886 KB)
04.02.2023, 17:54:53
Icon für Absender

vielen Dank für Ihren Antrag auf Kostenübernahme für das Arzneimittel.
Unsere Prüfung hat ergeben, dass es sich hierbei bedauerlicherweise um ein nicht verschreibungspflichtiges Arzneimittel handelt und daher die Kosten nicht von der Gesetzlichen Krankenversicherung übernommen werden können.
Aufgrund der Arzneimittel-Richtlinie (Richtlinie des Gemeinsamen Bundesausschusses über die Verordnung von Arzneimitteln in der vertragsärztlichen Versorgung) sind diese Präparate als Eigenleistung vollständig selbst zu zahlen.
Eine Erstattung Ihrer angefallenen Kosten ist uns daher leider nicht möglich.

06.02.2023, 10:02:09
Icon für Absender
Sehr geehrter Herr Steinhauer,

wie telefonisch besprochen erhalten Sie anhängend den Antrag auf Erstattung der Impfkosten.

Freundliche Grüße

Ihre BKK firmus

Impfkosten.pdf (117 KB)
22.03.2023, 12:25:46
Icon für Absender
sehr geehrte Damen und Herren,

gibt es über die BKK firmus die Möglichkeit, eine Hormontherapie und eine Geschlechtsangleichende Operation bezahlen zu lassen?

Wenn ja, was sind die Voraussetzungen damit sie die Kosten übernehmen würden?

ich befinde mich aktuell in einer Therapie. Auch hier würde ich gerne wissen ob sie die Therapie kosten übernehmen? Ich erhalte für jede Sitzung eine Rechnung von der Therapeutin.

Ich würde mich über eine ausführliche Antwort bzgl. der genannten Punkte freuen.

vielen Dank.

schöne Grüße,
Steinhauer

09.11.2023, 10:32:24
Icon für Absender
Guten Tag,

vielen Dank für Ihre Anfrage. Bzgl. der Hormontherapie benötigen wir einen Antrag mit medizinischer Begründung vom Arzt. Es muss mitgeteilt werden, welche Therapie genau für Sie in Frage kommt. Wenn wir diese Unterlagen vorliegen haben, prüfen wir, ob eine Kostenübernahme möglich ist.
Die Psychotherapie müsste eigentlich bei uns beantragt und dann auch direkt mit uns abgerechnet werden. Sind Sie bei einem privaten Psychotherapeuten in Behandlung?

Freundliche Grüße

Ihre BKK firmus

09.11.2023, 12:19:26
Icon für Absender
ja ich bin in Behandlung einer privaten Psycho-Therapeutin.

09.11.2023, 18:01:59
Icon für Absender
Guten Tag,

vielen Dank für Ihre Nachricht. Dann kann ein Antrag im Kostenerstattungsverfahren gestellt werden. Voraussetzung ist jedoch, dass Sie Nachweisen, dass Sie keinen Platz bei einem kassenzugelassenen Psychotherapeuten erhalten konnten.

Freundlich',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/FotodokumentationBart.png',
                'sha256' => 'a5b6f5ef6a9e431187cc8df01d52ae20cd46a46fdac41533ff38b8d704c4e380',
                'filename' => 'FotodokumentationBart.png',
                'file_type' => 'png',
                'file_size' => 343848,
                'title' => 'Fotodokumentation Barthaarwuchs / Epilationsbefund',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Fotodokumentation', 'Befund', 'Barthaare', 'MDK-Nachweis'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Medizinische Bilddokumentation des dichten Barthaarwuchses zur Einreichung beim Medizinischen Dienst für die Epilations-Bewilligung.',
                'key_facts' => [],
                'extracted_date' => '2024-02-10',
                'content_preview' => '[Bilddatei: FotodokumentationBart.png - Fotodokumentation Barthaarwuchs / Epilationsbefund. Zusammenfassung: Medizinische Bilddokumentation des dichten Barthaarwuchses zur Einreichung beim Medizinischen Dienst für die Epilations-Bewilligung.]',
                'full_text' => '[Bilddatei: FotodokumentationBart.png - Fotodokumentation Barthaarwuchs / Epilationsbefund. Zusammenfassung: Medizinische Bilddokumentation des dichten Barthaarwuchses zur Einreichung beim Medizinischen Dienst für die Epilations-Bewilligung.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/GEK_Zusage.jpg',
                'sha256' => '831ad85e58fbd2849c6bb122ec9f62db06b78c33e1c32c73913aeac743a36553',
                'filename' => 'GEK_Zusage.jpg',
                'file_type' => 'jpg',
                'file_size' => 401679,
                'title' => 'GEK / Barmer Zusage Kostenübernahme',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'GEK Zusage', 'Vergleichsfall'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Referenzbescheid über Kostenübernahme bei Transsexualismus / Epilation.',
                'key_facts' => [],
                'extracted_date' => '2023-09-20',
                'content_preview' => '[Bilddatei: GEK_Zusage.jpg - GEK / Barmer Zusage Kostenübernahme. Zusammenfassung: Referenzbescheid über Kostenübernahme bei Transsexualismus / Epilation.]',
                'full_text' => '[Bilddatei: GEK_Zusage.jpg - GEK / Barmer Zusage Kostenübernahme. Zusammenfassung: Referenzbescheid über Kostenübernahme bei Transsexualismus / Epilation.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Hausarzt_Anfrage_an_KK_Kostenuebernahme.pdf',
                'sha256' => '25d7ee2d7032596f494d09134cbe6536d6d95e58d500036caf5a2102cbba1699',
                'filename' => 'Hausarzt_Anfrage_an_KK_Kostenuebernahme.pdf',
                'file_type' => 'pdf',
                'file_size' => 1318018,
                'title' => 'Hausärztliche Anfrage und Begründung zur Kostenübernahme',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Kostenübernahme', 'Schriftverkehr', 'Hausarzt', 'Ärztliche Notwendigkeit'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.',
                'summary' => 'Schreiben der Hausarztpraxis an die BKK firmus zur Unterstützung des Antrags auf Kostenübernahme.',
                'key_facts' => [],
                'extracted_date' => '2023-11-15',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Impfkosten.pdf',
                'sha256' => '9abb9fd0a348cba4ee4a169ae14ebea43512c408d2748d528ebdb3460b28c2b4',
                'filename' => 'Impfkosten.pdf',
                'file_type' => 'pdf',
                'file_size' => 88171,
                'title' => 'Antrag auf Erstattung von Impfkosten',
                'category' => 'Arbeitsunfähigkeit & AU',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'eAU', 'Arbeitsunfähigkeit', 'Krankmeldung'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Arbeitsunfähigkeit & AU.',
                'summary' => 'Antrag auf Erstattung von Impfkosten die BKK firmus ist Marktführer in der Kostenübernahme und Erstattung von Schutzimpfungen. Wir übernehmen zusätzlich zu den Empfehlungen der Ständigen',
                'key_facts' => [],
                'extracted_date' => '2023-03-22',
                'content_preview' => '--- Seite 1 ---
Antrag auf Erstattung von Impfkosten  
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus ist Marktführer in der Kostenübernahme und Erstattung von 
Schutzimpfungen. Wir übernehmen zusätzlich zu den Empfehlungen der Ständigen 
Impfkommission (STIKO) alle ärztlich verordneten Schutzimpfungen gegen übertragbare 
Krankheiten, wenn der Impfstoff nach arzneimittelrechtlichen Vorschriften zugelassen ist und 
alle vorgesehenen Voraussetzungen erfüllt sind. 
 
Wir haben Ihre Anfrage für die Erstattung der Impfkosten erhalten. Damit wir Ihnen die 
verauslagten Kosten erstatten können, senden Sie uns bitte den beigefügten Antrag 
ausgefüllt und unterschrieben zurück.       
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann  
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen Bremen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
Z838294313  
  
 
 
 
 
Datum:  
22.03.2023 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANTIMPF 
9650049837234

--- Seite 2 ---
Antwort 
 
BKK firmus  
Kundenservice Leistungen Bremen  
28192 Bremen 
 
 
 
 
 
 
Erstattungsantrag für Impfkosten gegen       für Steinhauer, Noah / Z838294313 
 
 
Ich bitte um Erstattung der beiliegenden Rechnung/en über die durchgeführten Impfungen auf das Konto: 
 
Bank/Geldinstitut 
 
 
Kontoinhaber 
 
IBAN 
 
 
BIC 
 
Die Verordnung des Impfstoffes und die Rechnung/en vom behandelnden Arzt  
   
 
sind diesem Antrag beigefügt. 
   
 
liegt der Krankenkasse bereits vor. 
 
Die Impfung gegen ……………….…........……….. (Name der Im',
                'full_text' => '--- Seite 1 ---
Antrag auf Erstattung von Impfkosten  
 
 
 
Guten Tag Herr Steinhauer, 
 
die BKK firmus ist Marktführer in der Kostenübernahme und Erstattung von 
Schutzimpfungen. Wir übernehmen zusätzlich zu den Empfehlungen der Ständigen 
Impfkommission (STIKO) alle ärztlich verordneten Schutzimpfungen gegen übertragbare 
Krankheiten, wenn der Impfstoff nach arzneimittelrechtlichen Vorschriften zugelassen ist und 
alle vorgesehenen Voraussetzungen erfüllt sind. 
 
Wir haben Ihre Anfrage für die Erstattung der Impfkosten erhalten. Damit wir Ihnen die 
verauslagten Kosten erstatten können, senden Sie uns bitte den beigefügten Antrag 
ausgefüllt und unterschrieben zurück.       
 
Haben Sie noch Fragen? Rufen Sie uns an. Wir beraten Sie gerne. 
 
Freundliche Grüße 
 
Ihre BKK firmus  
 
Janicke Wolpmann  
 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Herrn 
Nick Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Kundenservice Leistungen Bremen  
Gottlieb-Daimler-Straße 11  
28237 Bremen  
 
Telefon:  
0421 64343   
Telefax: 
0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
Z838294313  
  
 
 
 
 
Datum:  
22.03.2023 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
KSANTIMPF 
9650049837234

--- Seite 2 ---
Antwort 
 
BKK firmus  
Kundenservice Leistungen Bremen  
28192 Bremen 
 
 
 
 
 
 
Erstattungsantrag für Impfkosten gegen       für Steinhauer, Noah / Z838294313 
 
 
Ich bitte um Erstattung der beiliegenden Rechnung/en über die durchgeführten Impfungen auf das Konto: 
 
Bank/Geldinstitut 
 
 
Kontoinhaber 
 
IBAN 
 
 
BIC 
 
Die Verordnung des Impfstoffes und die Rechnung/en vom behandelnden Arzt  
   
 
sind diesem Antrag beigefügt. 
   
 
liegt der Krankenkasse bereits vor. 
 
Die Impfung gegen ……………….…........……….. (Name der Impfung) wurde am .............................. 
vorgenommen. 
 
In dieser Impfperiode erfolgen noch ……………(Anzahl) weitere Impfungen.  
 
Berufliche Tätigkeit  …......…………….…………….....………………………… 
 
Grund für die Impfung:  
    
 
Berufliche Vorschrift 
    
 
Reise 
    
 
Sonstiges 
 
Wenn unter Grund für die Impfung „Reise“ ausgewählt wurde, bitte ankreuzen: 
   
 
Der Auslandsaufenthalt ist beruflich bedingt. 
   
 
Der Auslandsaufenthalt ist Teil meiner Ausbildung und ist durch  
    
die Stud',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Indikationsschreiben_Psychotherapeutin.pdf',
                'sha256' => '9a44cc42f6046f98af346cfc58472256719e72cdf90ed4b0eb4c14353f6c8165',
                'filename' => 'Indikationsschreiben_Psychotherapeutin.pdf',
                'file_type' => 'pdf',
                'file_size' => 74402,
                'title' => '08.01.2024',
                'category' => 'Psychotherapie & Kostenerstattung',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Indikation', 'Kostenerstattung', '§ 13 Abs. 3 SGB V', 'Psychotherapie'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Psychotherapie & Kostenerstattung.',
                'summary' => 'PSYCHOTHERAPEUTISCHE STELLUNGNAHME ZUR GESCHLECHTSANGLEICHENDEN HOR- MONERSATZTHERAPIE VON FRAU STEINHAUER Alina (Vorname im Personalausweis: Nick) Steinhauer stellte sich bei mir in psychothe-',
                'key_facts' => [],
                'extracted_date' => '2024-01-08',
                'content_preview' => '--- Seite 1 ---
08.01.2024 
PSYCHOTHERAPEUTISCHE STELLUNGNAHME ZUR GESCHLECHTSANGLEICHENDEN HOR-
MONERSATZTHERAPIE VON FRAU STEINHAUER 
	
	
	
	
	
	
 
Sehr geehrte Mitarbeitende aus der behandelnden endokrinologischen Praxis, 
Alina (Vorname im Personalausweis: Nick) Steinhauer stellte sich bei mir in psychothe-
rapeutischer Sprechstunde zur Indikationsstellung für die Hormonersatztherapie vor. 
Insgesamt haben 2,5 einzeltherapeutische Sitzungen stattgefunden im Rahmen einer 
Verhaltenstherapie.  
Zudem ist eine Anbindung an eine Transpeergroup seit dem 01.12.2023 ebenfalls ge-
geben. Dort konnte bereits eine erste Aufklärung stattfinden und in Folge eine psy-
chische Stabilisierung erfolgen. 
Ich bin psychologische Psychotherapeutin und umfassend fortgebildet in der Transiti-
onsbegleitung, sowie Teilnehmerin an einem deutschlandweiten Qualitätszirkel und 
regelmäßiger Intervision mit langjährig erfahrenen Kolleg*innen. Gern bin ich daher 
der Bitte der Patientin nach Diagnostik und Indikationsstellung nachgekommen.  
Bei der Patientin liegt nach ICD-11 HA60 („gender incongruence“) mit deutlicher 
Dysphorie bzw. nach ICD-10 F64.0 (Transsexualismus, Mann zu Frau) vor.  
Es liegen abgesehen von einer Genderdysphorie aktuell aus meiner Sicht keine psy-
chischen Störungen vor.  Es gibt keine psychotherapeutischen Vorbehandlungen und 
in Screeningfragebögen, im Selbstbericht sowie auch im Kontakt mit mir keine Hin-
weise auf psychische Erkrankungen. Drogen- und Alkoholkonsum wird verneint. 
Die Patientin beschreibt ihre Transidentität als seit dem Kindergartenalter immer wie-
der sich manifestierendes Selbstkonzept, auch wenn sie dies am Anfang noch nicht 
als Transidentität benennen konnte. Erste Erinnerungen aus der Kindergartenzeit be-
stehen, in der die Kinder nackt im Planschbecken waren und die Patientin sich auf-
grund ihres Geschlechts unwohl fühlte und gar nicht ausziehen wollte. In der Schulzeit 
Vivien Schenkel 
Psychologische Psychotherapeutin
S010993
Psychot',
                'full_text' => '--- Seite 1 ---
08.01.2024 
PSYCHOTHERAPEUTISCHE STELLUNGNAHME ZUR GESCHLECHTSANGLEICHENDEN HOR-
MONERSATZTHERAPIE VON FRAU STEINHAUER 
	
	
	
	
	
	
 
Sehr geehrte Mitarbeitende aus der behandelnden endokrinologischen Praxis, 
Alina (Vorname im Personalausweis: Nick) Steinhauer stellte sich bei mir in psychothe-
rapeutischer Sprechstunde zur Indikationsstellung für die Hormonersatztherapie vor. 
Insgesamt haben 2,5 einzeltherapeutische Sitzungen stattgefunden im Rahmen einer 
Verhaltenstherapie.  
Zudem ist eine Anbindung an eine Transpeergroup seit dem 01.12.2023 ebenfalls ge-
geben. Dort konnte bereits eine erste Aufklärung stattfinden und in Folge eine psy-
chische Stabilisierung erfolgen. 
Ich bin psychologische Psychotherapeutin und umfassend fortgebildet in der Transiti-
onsbegleitung, sowie Teilnehmerin an einem deutschlandweiten Qualitätszirkel und 
regelmäßiger Intervision mit langjährig erfahrenen Kolleg*innen. Gern bin ich daher 
der Bitte der Patientin nach Diagnostik und Indikationsstellung nachgekommen.  
Bei der Patientin liegt nach ICD-11 HA60 („gender incongruence“) mit deutlicher 
Dysphorie bzw. nach ICD-10 F64.0 (Transsexualismus, Mann zu Frau) vor.  
Es liegen abgesehen von einer Genderdysphorie aktuell aus meiner Sicht keine psy-
chischen Störungen vor.  Es gibt keine psychotherapeutischen Vorbehandlungen und 
in Screeningfragebögen, im Selbstbericht sowie auch im Kontakt mit mir keine Hin-
weise auf psychische Erkrankungen. Drogen- und Alkoholkonsum wird verneint. 
Die Patientin beschreibt ihre Transidentität als seit dem Kindergartenalter immer wie-
der sich manifestierendes Selbstkonzept, auch wenn sie dies am Anfang noch nicht 
als Transidentität benennen konnte. Erste Erinnerungen aus der Kindergartenzeit be-
stehen, in der die Kinder nackt im Planschbecken waren und die Patientin sich auf-
grund ihres Geschlechts unwohl fühlte und gar nicht ausziehen wollte. In der Schulzeit 
Vivien Schenkel 
Psychologische Psychotherapeutin
S010993
Psychotherapiepraxis Jurilj & Schenkel 
Schoonebeekstraße 1 
49124 Georgsmarienhütte
AN 
Die MitarbeiterInnen der endokrinolo-
gischen Praxis

--- Seite 2 ---
waren wichtige Momente Fasching und Halloween, in denen die Patientin sich gerne 
mit weiblichen Kostümen verkleidet hätte, sich dies aber durch die Erwartungen in 
ihrer Familie nicht getraut habe. In der Familie habe es eine klare Rollenverteilung 
gegeben, in die sich die Patientin auch hineingedrängt gefühlt habe. Ein Ausleben 
ihrer Transide',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Kostenvoranschlag28052024.pdf',
                'sha256' => 'e03930289daeff4ce1f949b36fa1a36ca991716dff0356ade46ca2e40c1c79fb',
                'filename' => 'Kostenvoranschlag28052024.pdf',
                'file_type' => 'pdf',
                'file_size' => 18830,
                'title' => 'Logopädische Praxis',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Transidentität', 'Hormontherapie', 'Indikation', 'MDK-Richtlinie'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.',
                'summary' => 'Logopädische Praxis Ganzheitliche Behandlung von Sprach-, Sprech- und Stimmstörungen Maren Schwarz, Logopädie, Bosteled5, 38518 Gifhorn',
                'key_facts' => [],
                'extracted_date' => '2024-05-28',
                'content_preview' => '--- Seite 1 ---
Logopädische Praxis 
Maren Schwarz 
Ganzheitliche Behandlung von Sprach-, Sprech- und Stimmstörungen 
Maren Schwarz, Logopädie, Bosteled5, 38518 Gifhorn 
Nick Steinhauer 
Car] - Goerdeler - Ring 26 
38518 Gifhorn 
Deutschland 
Kostenvoranschlag 
Sehr geehrter Herr Steinhauer, 
Ich danke für Ihre Anfrage und erstellen Ihnen folgendes Angebot gemäß unserem 
Telefonat: 
796,63 € 
Zuzahlung 	
79,02€ 
Mit freundlichen Grüßen 
1\' 
 
Maren Schwarz 
Lcgopädische Praxis 
Maren Schwarz 
3cstark5- 38S8G:farn 
rel: 05371-21 38 546 Fcr 115371-el 36 647 
Maren schwarz 
Staatlich geprüfte Logopödin 
Sosteleck 5 
38518 Gifhorn 
Tel.: 05371-8138 646 
Fax: 05371-8138 647 
info@logopaedie-schwarz-gifhorn.de',
                'full_text' => '--- Seite 1 ---
Logopädische Praxis 
Maren Schwarz 
Ganzheitliche Behandlung von Sprach-, Sprech- und Stimmstörungen 
Maren Schwarz, Logopädie, Bosteled5, 38518 Gifhorn 
Nick Steinhauer 
Car] - Goerdeler - Ring 26 
38518 Gifhorn 
Deutschland 
Kostenvoranschlag 
Sehr geehrter Herr Steinhauer, 
Ich danke für Ihre Anfrage und erstellen Ihnen folgendes Angebot gemäß unserem 
Telefonat: 
796,63 € 
Zuzahlung 	
79,02€ 
Mit freundlichen Grüßen 
1\' 
 
Maren Schwarz 
Lcgopädische Praxis 
Maren Schwarz 
3cstark5- 38S8G:farn 
rel: 05371-21 38 546 Fcr 115371-el 36 647 
Maren schwarz 
Staatlich geprüfte Logopödin 
Sosteleck 5 
38518 Gifhorn 
Tel.: 05371-8138 646 
Fax: 05371-8138 647 
info@logopaedie-schwarz-gifhorn.de',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Lebenslauf (1).pdf',
                'sha256' => '0775283bc6873a968db2a69ab951eb946b794ade239fd878d0a140b98777ef97',
                'filename' => 'Lebenslauf (1).pdf',
                'file_type' => 'pdf',
                'file_size' => 168371,
                'title' => 'Lebenslauf - Nick',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Ablehnungsbescheid', 'Transidentität', 'Indikation', 'Hormontherapie', 'MDK-Richtlinie'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.',
                'summary' => 'Lebenslauf - Nick Geboren 01.09.1993 Kindergartenzeit 1996 – 1999',
                'key_facts' => [],
                'extracted_date' => '1993-09-01',
                'content_preview' => '--- Seite 1 ---
Lebenslauf - Nick 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) Ich habe auf dem Schulhof mit einem Mädchen aus unserer Klasse 
gesprochen und sie hatte so einen Glitzerlippenstift, den ich auch gerne benutzt hätte 
4. (3 – 4 Klasse) Mit einem besten Freund zusammen die Klamotten seiner Mama 
angezogen (Fasching gespielt) 
5. (3 – 4 Klasse) Wir wurden von der Mama meines Freundes geschminkt 
 
Realschule 2005 – 2010 / Crossdressing war vorhanden 
6. (5 – 6 Klasse) ich habe unsere damalige Vermieterin im Keller öfters gefragt, ob sie 
Klamotten zu verschenken hätte. 
7. (7 – 8 Klasse) Ich habe mir das erste Mal selbst Klamotten bei KIK gekauft, da ich z.B. die 
Jeans Hosen von den Mädchen aus der Klasse schön fand. Danach habe ich immer 
häufiger alles Mögliche ausprobieren wollen und habe immer öfters auch andere 
Klamotten gekauft.  
 
BBS 1 in Gifhorn 2011 – 2012 / Crossdressing war vorhanden 
8. Sehr viel Kontakt mit Mädchen gehabt. Es waren regelmäßig viele Freundinnen mit Ihren 
Freundinnen bei mir zuhause. Wir waren auch öfters zsm. Shoppen (Da habe ich immer 
das Gefühl gehabt, das ich gerne auch für mich was kaufen möchte). Ich weiß noch, dass 
die Mädchen aus spaß gesagt haben, dass ich mal hohe Schuhe anziehen soll. Ich habe 
das abgelehnt aber im inneren wollte ich es. 
 
Ausbildung BEL NET - Otto-Bennemann-Schule Braunschweig 2012-2015 / Crossdressing wurde 
stärker 
9. Ich habe bzgl. des Themas Crossdressing gemerkt, das ich mehr wollte als v',
                'full_text' => '--- Seite 1 ---
Lebenslauf - Nick 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) Ich habe auf dem Schulhof mit einem Mädchen aus unserer Klasse 
gesprochen und sie hatte so einen Glitzerlippenstift, den ich auch gerne benutzt hätte 
4. (3 – 4 Klasse) Mit einem besten Freund zusammen die Klamotten seiner Mama 
angezogen (Fasching gespielt) 
5. (3 – 4 Klasse) Wir wurden von der Mama meines Freundes geschminkt 
 
Realschule 2005 – 2010 / Crossdressing war vorhanden 
6. (5 – 6 Klasse) ich habe unsere damalige Vermieterin im Keller öfters gefragt, ob sie 
Klamotten zu verschenken hätte. 
7. (7 – 8 Klasse) Ich habe mir das erste Mal selbst Klamotten bei KIK gekauft, da ich z.B. die 
Jeans Hosen von den Mädchen aus der Klasse schön fand. Danach habe ich immer 
häufiger alles Mögliche ausprobieren wollen und habe immer öfters auch andere 
Klamotten gekauft.  
 
BBS 1 in Gifhorn 2011 – 2012 / Crossdressing war vorhanden 
8. Sehr viel Kontakt mit Mädchen gehabt. Es waren regelmäßig viele Freundinnen mit Ihren 
Freundinnen bei mir zuhause. Wir waren auch öfters zsm. Shoppen (Da habe ich immer 
das Gefühl gehabt, das ich gerne auch für mich was kaufen möchte). Ich weiß noch, dass 
die Mädchen aus spaß gesagt haben, dass ich mal hohe Schuhe anziehen soll. Ich habe 
das abgelehnt aber im inneren wollte ich es. 
 
Ausbildung BEL NET - Otto-Bennemann-Schule Braunschweig 2012-2015 / Crossdressing wurde 
stärker 
9. Ich habe bzgl. des Themas Crossdressing gemerkt, das ich mehr wollte als vorher. 
a. Sehr häufig auf dem Nachhause weg von der Arbeit/Schule habe ich entweder 
bei KIK-Sachen eingekauft oder was dabeigehabt, um es irgendwo in einem Dorf 
anzuziehen 
b. Ich bin auf irgendwelchen Parkplätzen gefahren und habe mich im Auto schnell 
umgezogen (Auch mit Perücke usw.) bin dann schnell ins Dorf gelaufen, um es ein

--- Seite 2 ---
Lebenslauf - Nick 
bisschen zu genießen und dann musste ich aber auch schon wieder schnell nach 
Hause damit keine komischen Fragen aufkommen, wie',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Lebenslauf.pdf',
                'sha256' => '3c794aacb3a71c699582a20b35c15c848d51e0252029dbaf70d9d38e393ab2bf',
                'filename' => 'Lebenslauf.pdf',
                'file_type' => 'pdf',
                'file_size' => 186642,
                'title' => 'Lebenslauf – Alina Steinhauer',
                'category' => 'Geschlechtsangleichung & HRT',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Ablehnungsbescheid', 'Transidentität', 'Indikation', 'Hormontherapie', 'MDK-Richtlinie'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Geschlechtsangleichung & HRT.',
                'summary' => 'Lebenslauf – Alina Steinhauer Geboren 01.09.1993 Kindergartenzeit 1996 – 1999',
                'key_facts' => [],
                'extracted_date' => '1993-09-01',
                'content_preview' => '--- Seite 1 ---
Lebenslauf – Alina Steinhauer 
 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) Ich habe auf dem Schulhof mit einem Mädchen aus unserer Klasse 
gesprochen und sie hatte so einen Glitzerlippenstift, den ich auch gerne benutzt hätte 
4. (3 – 4 Klasse) Mit einem besten Freund zusammen die Klamotten seiner Mama 
angezogen (Fasching gespielt) 
5. (3 – 4 Klasse) Wir wurden von der Mama meines Freundes geschminkt 
 
Realschule 2005 – 2010 / Crossdressing war vorhanden 
6. (5 – 6 Klasse) ich habe unsere damalige Vermieterin im Keller öfters gefragt, ob sie 
Klamotten zu verschenken hätte. 
7. (7 – 8 Klasse) Ich habe mir das erste Mal selbst Klamotten bei KIK gekauft, da ich z.B. die 
Jeans Hosen von den Mädchen aus der Klasse schön fand. Danach habe ich immer 
häufiger alles Mögliche ausprobieren wollen und habe immer öfters auch andere 
Klamotten gekauft.  
 
BBS 1 in Gifhorn 2011 – 2012 / Crossdressing war vorhanden 
8. Sehr viel Kontakt mit Mädchen gehabt. Es waren regelmäßig viele Freundinnen mit Ihren 
Freundinnen bei mir zuhause. Wir waren auch öfters zsm. Shoppen (Da habe ich immer 
das Gefühl gehabt, das ich gerne auch für mich was kaufen möchte). Ich weiß noch, dass 
die Mädchen aus spaß gesagt haben, dass ich mal hohe Schuhe anziehen soll. Ich habe 
das abgelehnt aber im inneren wollte ich es. 
 
Ausbildung BEL NET - Otto-Bennemann-Schule Braunschweig 2012-2015 / Crossdressing wurde 
stärker 
9. Ich habe bzgl. des Themas Crossdressing gemerkt, das ich meh',
                'full_text' => '--- Seite 1 ---
Lebenslauf – Alina Steinhauer 
 
Geboren 01.09.1993 
 
Kindergartenzeit 1996 – 1999 
1. Geschämt nackt ins Planschbecken zu gehen 
2. Zuhause viel verkleiden gespielt 
3. Immer den Casper gespielt (vllt. Um gewisse Gefühle zu unterdrücken) 
 
Grundschule 2000 – 2005 / Erste Ansätze von Crossdressing und oder im falschen Körper zu sein 
1. (2 – 3 Klasse) Ich war neidisch auf die Mädchen die z.B. zum Fasching Röcke und Kleider 
anhatten oder sich geschminkt haben. 
2. (2 – 3 Klasse) Ich habe noch richtig vor Augen, wie ich mich gerne auch so geschminkt 
hätte 
3. (3 – 4 Klasse) Ich habe auf dem Schulhof mit einem Mädchen aus unserer Klasse 
gesprochen und sie hatte so einen Glitzerlippenstift, den ich auch gerne benutzt hätte 
4. (3 – 4 Klasse) Mit einem besten Freund zusammen die Klamotten seiner Mama 
angezogen (Fasching gespielt) 
5. (3 – 4 Klasse) Wir wurden von der Mama meines Freundes geschminkt 
 
Realschule 2005 – 2010 / Crossdressing war vorhanden 
6. (5 – 6 Klasse) ich habe unsere damalige Vermieterin im Keller öfters gefragt, ob sie 
Klamotten zu verschenken hätte. 
7. (7 – 8 Klasse) Ich habe mir das erste Mal selbst Klamotten bei KIK gekauft, da ich z.B. die 
Jeans Hosen von den Mädchen aus der Klasse schön fand. Danach habe ich immer 
häufiger alles Mögliche ausprobieren wollen und habe immer öfters auch andere 
Klamotten gekauft.  
 
BBS 1 in Gifhorn 2011 – 2012 / Crossdressing war vorhanden 
8. Sehr viel Kontakt mit Mädchen gehabt. Es waren regelmäßig viele Freundinnen mit Ihren 
Freundinnen bei mir zuhause. Wir waren auch öfters zsm. Shoppen (Da habe ich immer 
das Gefühl gehabt, das ich gerne auch für mich was kaufen möchte). Ich weiß noch, dass 
die Mädchen aus spaß gesagt haben, dass ich mal hohe Schuhe anziehen soll. Ich habe 
das abgelehnt aber im inneren wollte ich es. 
 
Ausbildung BEL NET - Otto-Bennemann-Schule Braunschweig 2012-2015 / Crossdressing wurde 
stärker 
9. Ich habe bzgl. des Themas Crossdressing gemerkt, das ich mehr wollte als vorher. 
a. Sehr häufig auf dem Nachhause weg von der Arbeit/Schule habe ich entweder 
bei KIK-Sachen eingekauft oder was dabeigehabt, um es irgendwo in einem Dorf 
anzuziehen 
b. Ich bin auf irgendwelchen Parkplätzen gefahren und habe mich im Auto schnell 
umgezogen (Auch mit Perücke usw.) bin dann schnell ins Dorf gelaufen, um es ein

--- Seite 2 ---
Lebenslauf – Alina Steinhauer 
 
bisschen zu genießen und dann musste ich aber auch schon wieder schnell nach 
Hause damit keine kom',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/MD_Begutachtung_Transsexualitaet.pdf',
                'sha256' => '4aaf1355f371b7cb5b22f195121d83af42036f9931a096e936dac98ad2c47797',
                'filename' => 'MD_Begutachtung_Transsexualitaet.pdf',
                'file_type' => 'pdf',
                'file_size' => 3724209,
                'title' => 'Sozialmedizinische Begutachtung durch den Medizinischen Dienst (MD Niedersachsen',
                'category' => 'MDK-Begutachtungen & Atteste',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'BGA Richtlinie', 'Transidentität', 'Indikation', 'MDK-Gutachten', 'Hormontherapie', 'Transsexualismus', 'Medizinischer Dienst', 'MDK-Richtlinie'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich MDK-Begutachtungen & Atteste.',
                'summary' => 'MD-Gutachten zu geschlechtsangleichenden Maßnahmen (Barthaarentfernung / Epilation). Beurteilt Voraussetzungen nach den Begutachtungsrichtlinien Transsexualismus (BGA).',
                'key_facts' => [],
                'extracted_date' => '2024-01-20',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/NachrichtAnhang1.jpg',
                'sha256' => 'bdcee533f74732e60b084659d602d62bb254edbc8da25342713ea15fbded09c9',
                'filename' => 'NachrichtAnhang1.jpg',
                'file_type' => 'jpg',
                'file_size' => 906407,
                'title' => 'Kassenbon / Apothekenbeleg Arzneimittel Lefax',
                'category' => 'Medikamente & Hilfsmittel',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Apothekenbeleg', 'Lefax', 'Erstattung'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Medikamente & Hilfsmittel.',
                'summary' => 'Beleg über den Kauf von Lefax Pump-Liquid für Sohn Noah zur Erstattung.',
                'key_facts' => [],
                'extracted_date' => '2023-02-04',
                'content_preview' => '[Bilddatei: NachrichtAnhang1.jpg - Kassenbon / Apothekenbeleg Arzneimittel Lefax. Zusammenfassung: Beleg über den Kauf von Lefax Pump-Liquid für Sohn Noah zur Erstattung.]',
                'full_text' => '[Bilddatei: NachrichtAnhang1.jpg - Kassenbon / Apothekenbeleg Arzneimittel Lefax. Zusammenfassung: Beleg über den Kauf von Lefax Pump-Liquid für Sohn Noah zur Erstattung.]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Vertrag_Hairfreevom14.01.2024.pdf',
                'sha256' => '8ea7b3088e8ad2a8243e434150338c65a6801e4c515811a0413adbdcf37a5c1a',
                'filename' => 'Vertrag_Hairfreevom14.01.2024.pdf',
                'file_type' => 'pdf',
                'file_size' => 298512,
                'title' => 'Behandlungsvertrag Hairfree Institut für dauerhafte Haarentfernung',
                'category' => 'Barthaarentfernung & Epilation',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Barthaarentfernung', 'Vertrag', 'Lasertherapie', 'Hairfree', 'Kostenvoranschlag', 'Barthaare'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Barthaarentfernung & Epilation.',
                'summary' => 'Dienstleistungsvertrag und Kostenvoranschlag für die Laser-/Lichtepilationsbehandlung der Barthaare vom 14.01.2024.',
                'key_facts' => [],
                'extracted_date' => '2024-01-14',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Widerspruch.pdf',
                'sha256' => '556843b3ee049a621b1724b96ddec46d9a5bea2a4c9af8d68062ff4c5c4406b9',
                'filename' => 'Widerspruch.pdf',
                'file_type' => 'pdf',
                'file_size' => 72896,
                'title' => 'Alina Steinhauer',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Medizinischer Dienst (MD)', 'Lasertherapie', 'Widerspruch', '§ 13 Abs. 3 SGB V', 'Barthaare'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Alina Steinhauer Carl-Goerdeler-Ring 26 Betreff: Aufrechterhaltung meines Widerspruchs bzgl. Ihres Schreibens vom 20.01.2026',
                'key_facts' => [],
                'extracted_date' => '2026-01-22',
                'content_preview' => '--- Seite 1 ---
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
 
22.01.2026 
Betreff: Aufrechterhaltung meines Widerspruchs bzgl. Ihres Schreibens vom 20.01.2026 
 
Sehr geehrte Damen und Herren, 
hiermit nehme ich Bezug auf Ihr Schreiben vom 20.01.2026 und Teile Ihnen mit, dass ich meinen 
Widerspruch vollumfänglich aufrechterhalte. 
Ihre Argumentation, eine Kostenübernahme sei aufgrund der "geltenden Rechtslage" bei nicht-
ärztlichen Leistungserbringern grundsätzlich ausgeschlossen, ist im vorliegenden Einzelfall 
rechtlich unzutreffend. Ich begründe dies wie folgt: 
1. Nachgewiesenes Systemversagen gemäß § 13 Abs. 3 SGB V 
Das von Ihnen zitierte BSG-Urteil vom 17.12.2020 setzt voraus, dass die Leistung durch 
Vertragsärzte in zumutbarer Weise erbracht werden kann. In der Realität liegt jedoch ein 
Systemversagen vor: Es gibt im Einzugsbereich keine Hautärzte mit Kassenzulassung, die die 
Epilation im erforderlichen Umfang (Bartbereich bei Transsexualität) zu den von Ihnen 
genannten EBM-Ziffern (GOP 02300, 10340) tatsächlich anbieten oder durchführen. 
Wenn die Versorgung im vertragsärztlichen System nicht gewährleistet ist, ist die Krankenkasse 
gemäß § 13 Abs. 3 SGB V verpflichtet, die Kosten für eine selbstbeschaffte, medizinisch 
notwendige Leistung außerhalb des Systems (hier: Hairfree mit INOS-Technologie) zu erstatten. 
Da der MD die medizinische Notwendigkeit bereits bestätigt hat, reduziert sich Ihr Ermessen 
hierbei auf Null. 
2. Verletzung des Gleichbehandlungsgrundsatzes (Art. 3 GG) 
Ihrer Behauptung, die Rechtslage ließe eine Erstattung bei Hairfree nicht zu, stehen die 
Genehmigungsverfahren zahlreicher anderer gesetzlicher Krankenkassen entgegen. Ihnen liegen 
ebenfalls die von mir bereitgestellten rechtskräftigen Kostenzusagen für exakt die Behandlung 
bei Hairfree von folgenden Kassen vor: 
• 
AOK - Die Gesundheitskasse 
• 
GEK - Gmünder ErsatzKasse 
• 
BKK-Gesundheit 
• 
KKH / Allianz (Zusage nach MDK-Prüfung) 
Diese Kassen unterliegen',
                'full_text' => '--- Seite 1 ---
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
 
22.01.2026 
Betreff: Aufrechterhaltung meines Widerspruchs bzgl. Ihres Schreibens vom 20.01.2026 
 
Sehr geehrte Damen und Herren, 
hiermit nehme ich Bezug auf Ihr Schreiben vom 20.01.2026 und Teile Ihnen mit, dass ich meinen 
Widerspruch vollumfänglich aufrechterhalte. 
Ihre Argumentation, eine Kostenübernahme sei aufgrund der "geltenden Rechtslage" bei nicht-
ärztlichen Leistungserbringern grundsätzlich ausgeschlossen, ist im vorliegenden Einzelfall 
rechtlich unzutreffend. Ich begründe dies wie folgt: 
1. Nachgewiesenes Systemversagen gemäß § 13 Abs. 3 SGB V 
Das von Ihnen zitierte BSG-Urteil vom 17.12.2020 setzt voraus, dass die Leistung durch 
Vertragsärzte in zumutbarer Weise erbracht werden kann. In der Realität liegt jedoch ein 
Systemversagen vor: Es gibt im Einzugsbereich keine Hautärzte mit Kassenzulassung, die die 
Epilation im erforderlichen Umfang (Bartbereich bei Transsexualität) zu den von Ihnen 
genannten EBM-Ziffern (GOP 02300, 10340) tatsächlich anbieten oder durchführen. 
Wenn die Versorgung im vertragsärztlichen System nicht gewährleistet ist, ist die Krankenkasse 
gemäß § 13 Abs. 3 SGB V verpflichtet, die Kosten für eine selbstbeschaffte, medizinisch 
notwendige Leistung außerhalb des Systems (hier: Hairfree mit INOS-Technologie) zu erstatten. 
Da der MD die medizinische Notwendigkeit bereits bestätigt hat, reduziert sich Ihr Ermessen 
hierbei auf Null. 
2. Verletzung des Gleichbehandlungsgrundsatzes (Art. 3 GG) 
Ihrer Behauptung, die Rechtslage ließe eine Erstattung bei Hairfree nicht zu, stehen die 
Genehmigungsverfahren zahlreicher anderer gesetzlicher Krankenkassen entgegen. Ihnen liegen 
ebenfalls die von mir bereitgestellten rechtskräftigen Kostenzusagen für exakt die Behandlung 
bei Hairfree von folgenden Kassen vor: 
• 
AOK - Die Gesundheitskasse 
• 
GEK - Gmünder ErsatzKasse 
• 
BKK-Gesundheit 
• 
KKH / Allianz (Zusage nach MDK-Prüfung) 
Diese Kassen unterliegen derselben Rechtslage und denselben BSG-Urteilen wie die BKK 
firmus. Dass diese Mitbewerber die Behandlung bei Hairfree als erstattungsfähig einstufen, 
belegt zweifelsfrei, dass Ihr pauschaler Verweis auf eine "nicht bestehende Leistungspflicht" 
einer rechtlichen Prüfung nicht standhält.

--- Seite 2 ---
3. Qualifikation des Leistungserbringers 
Die von mir gewählte Praxis Hairfree arbeitet mit der INOS-Technologie, welche unter ärztlicher 
Fachaufsicht entwickelt wurde und deren Wirksamkeit ',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/WiderspruchgegendieAblehnungvom09.10.2025.pdf',
                'sha256' => '51d4e10bff324f320d54dcea0083299482ebc5d75ad275721fd945e5e5b6c9da',
                'filename' => 'WiderspruchgegendieAblehnungvom09.10.2025.pdf',
                'file_type' => 'pdf',
                'file_size' => 73561,
                'title' => 'Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Epilation', 'MDK-Begutachtung', 'Ablehnungsbescheid', 'Medizinischer Dienst (MD)', 'Lasertherapie', 'Widerspruch', 'Barthaare'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der Barthaarentfernung vielen Dank für Ihr Schreiben vom 09.10.2025 zu meinem Antrag auf Kostenübernahme für die',
                'key_facts' => [],
                'extracted_date' => '2025-10-09',
                'content_preview' => '--- Seite 1 ---
Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der 
Barthaarentfernung 
 
Sehr geehrte Damen und Herren, 
vielen Dank für Ihr Schreiben vom 09.10.2025 zu meinem Antrag auf Kostenübernahme für die 
Barthaarentfernung. 
Mit großem Interesse habe ich zur Kenntnis genommen, dass der Medizinische Dienst (MD) in 
seiner Prüfung die grundsätzliche medizinische Indikation für eine Epilation in meinem Fall 
bestätigt hat. 
Ihrer darauffolgenden Ablehnung, die sich auf ein Urteil des Bundessozialgerichts (BSG) vom 
17.12.2020 stützt, muss ich jedoch entschieden widersprechen. Ihre Schlussfolgerung, die 
Behandlung bei "Hairfree" könne nicht übernommen werden, da sie durch eine "selbstständig 
tätige Kosmetikerin" erfolge und somit keine "ärztliche Leistung" darstelle, ist aus mehreren 
Gründen nicht haltbar. 
1. Fehlende Anerkennung der Qualifikation und des Behandlungsstandards von Hairfree 
Ihre Einstufung der bei Hairfree tätigen Spezialisten als "selbstständig tätige Kosmetikerinnen" 
greift zu kurz und ignoriert die nachweisbaren Qualifikationen und den medizinisch-technischen 
Standard des Anbieters. 
• 
Ärztliche Empfehlung und Aufsicht: Wie aus den offiziellen Unterlagen von Hairfree 
ersichtlich (siehe Anhang, "Der Spezialist"), wird die dort angewandte INOS-Technologie 
explizit "von Ärzten und Dermatologen empfohlen". Namentlich bescheinigt Dr. med. 
Navid Roshanaei, Facharzt für Chirurgie, dass die Technologie "die höchsten 
Qualitätsmaßstäbe" erfüllt und das Personal "die erforderliche Qualifikation" besitzt, 
diese "fachmännisch anzuwenden". 
• 
Spezialisierte Ausbildung: Die Mitarbeiter von Hairfree durchlaufen eine 
standardisierte "Ausbildung und Zertifizierung" in der unternehmenseigenen Academy. 
Es handelt sich hierbei nicht um eine allgemeine kosmetische Tätigkeit, sondern um die 
Anwendung eines spezifischen, in "zahlreichen medizinischen Studien" (siehe Anhang, 
"Die Behandlung") dokumentierten Verfahrens. 
Es',
                'full_text' => '--- Seite 1 ---
Widerspruch gegen Ihren Bescheid vom 09.10.2025 Antrag auf Kostenübernahme der 
Barthaarentfernung 
 
Sehr geehrte Damen und Herren, 
vielen Dank für Ihr Schreiben vom 09.10.2025 zu meinem Antrag auf Kostenübernahme für die 
Barthaarentfernung. 
Mit großem Interesse habe ich zur Kenntnis genommen, dass der Medizinische Dienst (MD) in 
seiner Prüfung die grundsätzliche medizinische Indikation für eine Epilation in meinem Fall 
bestätigt hat. 
Ihrer darauffolgenden Ablehnung, die sich auf ein Urteil des Bundessozialgerichts (BSG) vom 
17.12.2020 stützt, muss ich jedoch entschieden widersprechen. Ihre Schlussfolgerung, die 
Behandlung bei "Hairfree" könne nicht übernommen werden, da sie durch eine "selbstständig 
tätige Kosmetikerin" erfolge und somit keine "ärztliche Leistung" darstelle, ist aus mehreren 
Gründen nicht haltbar. 
1. Fehlende Anerkennung der Qualifikation und des Behandlungsstandards von Hairfree 
Ihre Einstufung der bei Hairfree tätigen Spezialisten als "selbstständig tätige Kosmetikerinnen" 
greift zu kurz und ignoriert die nachweisbaren Qualifikationen und den medizinisch-technischen 
Standard des Anbieters. 
• 
Ärztliche Empfehlung und Aufsicht: Wie aus den offiziellen Unterlagen von Hairfree 
ersichtlich (siehe Anhang, "Der Spezialist"), wird die dort angewandte INOS-Technologie 
explizit "von Ärzten und Dermatologen empfohlen". Namentlich bescheinigt Dr. med. 
Navid Roshanaei, Facharzt für Chirurgie, dass die Technologie "die höchsten 
Qualitätsmaßstäbe" erfüllt und das Personal "die erforderliche Qualifikation" besitzt, 
diese "fachmännisch anzuwenden". 
• 
Spezialisierte Ausbildung: Die Mitarbeiter von Hairfree durchlaufen eine 
standardisierte "Ausbildung und Zertifizierung" in der unternehmenseigenen Academy. 
Es handelt sich hierbei nicht um eine allgemeine kosmetische Tätigkeit, sondern um die 
Anwendung eines spezifischen, in "zahlreichen medizinischen Studien" (siehe Anhang, 
"Die Behandlung") dokumentierten Verfahrens. 
Es handelt sich bei Hairfree demnach um ein hochgradig standardisiertes, qualitätsgesichertes 
und von Ärzten befürwortetes Verfahren. Die Argumentation, es fehle die "ärztliche Leitung", ist 
angesichts dieser Faktenlage nicht stichhaltig. 
2. Gängige Erstattungspraxis anderer Gesetzlicher Krankenkassen (GKV) 
Der entscheidende Punkt ist die gängige Regulierungspraxis anderer gesetzlicher 
Krankenkassen. Diese operieren unter exakt denselben rechtlichen Rahmenbedingungen (SGB 
V) und sind an dies',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Widerspruchsauschuss.pdf',
                'sha256' => '6013ff950d0ac56b5e904a2ca73f32a3ecf8ad597b0d09d12314720a181a3a89',
                'filename' => 'Widerspruchsauschuss.pdf',
                'file_type' => 'pdf',
                'file_size' => 134588,
                'title' => 'Vorladung / Information zur Sitzung des Widerspruchsausschusses',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'SGB IV', 'Widerspruchsausschuss', '§ 84 SGG', 'Widerspruch', 'Widerspruchsbescheid', 'Frist', 'Bescheid'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Schreiben der BKK firmus bezüglich der Vorlage des Widerspruchs beim Widerspruchsausschuss.',
                'key_facts' => [],
                'extracted_date' => '2025-04-15',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/WiderspruchsauschussApril.pdf',
                'sha256' => '7b253aa395370e8a0818c3ca172e0f9c07521d4c5e3c58aeea76d001f1a3398d',
                'filename' => 'WiderspruchsauschussApril.pdf',
                'file_type' => 'pdf',
                'file_size' => 133808,
                'title' => 'Entscheidung / Protokollauszug des Widerspruchsausschusses April',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Widerspruchsausschuss', '§ 84 SGG', 'Entscheidung', 'Widerspruch', 'Widerspruchsbescheid', 'Frist', 'Bescheid'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Beratungsergebnis des Widerspruchsausschusses über die erhobenen Widersprüche.',
                'key_facts' => [],
                'extracted_date' => '2025-04-20',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankenkasse/Widerspruch_LetzteAufforderungd.Restzahlung.pdf',
                'sha256' => 'ee31ed7b937f3b705411c9edc6a136ce6a2b9778f613c9c15f5fa97163671001',
                'filename' => 'Widerspruch_LetzteAufforderungd.Restzahlung.pdf',
                'file_type' => 'pdf',
                'file_size' => 261531,
                'title' => 'Letzte Zahlungsaufforderung und Fristsetzung zur Restzahlung',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Mahnung', 'Fristsetzung', 'Zahlungsaufforderung', 'Arbeitsunfähigkeit', 'Krankmeldung', 'Restzahlung', 'Widerspruch', 'eAU'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Nachdrückliche Zahlungsaufforderung mit Fristsetzung bezüglich noch ausstehender Erstattungsbeträge / Krankengeld.',
                'key_facts' => [],
                'extracted_date' => '2025-11-20',
                'content_preview' => null,
                'full_text' => null,
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/01_Lieferschein_BKK_firmus_Alina_Steinhauer.pdf',
                'sha256' => '0fc6593f56d36c936beac21bab4056776499788a04fbeb590c2fab368435c4d6',
                'filename' => '01_Lieferschein_BKK_firmus_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 4394,
                'title' => 'Dokumenten-Begleitschein / Lieferschein für Postsendung an BKK firmus (Einschreiben)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Lieferschein', 'Begleitschein', 'Post', 'BKK firmus', 'Einschreiben', 'Widerspruch', 'Krankengeld', '9650226941682'],
                'purpose' => 'Nachweis über die fristgerechte und vollständige Einreichung von Antrags- und Widerspruchsunterlagen.',
                'summary' => 'Offizieller 1-seitiger Dokumenten-Begleitschein / Lieferschein mit tiefergesetztem Fensterbereich für den Postumschlag. Weist Pos. 1 (Widerspruch) und Pos. 2 (BKK-Originalformular nebst Anlage) aus. Beinhaltet den ausdrücklichen Korrekturhinweis, dass diese Neufassung die frühere fehlerhafte App-Übermittlung vollumfänglich ersetzt. Frist: 08.10.2026.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -62791437',
                'full_text' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -6279143758668390890
Versand: Einschreiben / Vorab per App
DOKUMENTEN-BEGLEITSCHEIN / LIEFERSCHEIN
Zur Postsendung: Widerspruch Krankengeld & Neufassung Klärung Versicherungsverhältnis
Sehr geehrte Damen und Herren,
diesem Briefumschlag liegen die nachfolgend aufgeführten Unterlagen im Original zur rechtswirksamen Bearbeitung und
Aktenführung bei:
Pos.
Enthaltenes Dokument / Bezeichnung
Datum
Umfang & Status
1
Formeller Widerspruch und Widerspruchsbegründung
Gegen Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682) – Betreff:
Krankengeld für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026
(GA-OP Dr. Lubos Kliniken).
(Vorab per BKK-firmus-App übermittelt. Zahlungsfrist: 08.10.2026)
01.10.2026
2 Seiten
Original
2
Original-Formular »Klärung Versicherungsverhältnis« (BKK firmus)
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890.
Ausgefülltes Original-Formular nebst beiliegendem ergänzenden
Erläuterungsblatt (Anlage) zur Feststellung der beitragsfreien Mitgliedschaft
nach § 192 Abs. 1 Nr. 2 SGB V.
01.10.2026
2 Seiten
Original + Anlage
WICHTIGER HINWEIS ZUR KLÄRUNG DES VERSICHERUNGSVERHÄLTNISSES:
Die beiliegende Neufassung des Formulars nebst Anlage (Pos. 2) ersetzt die Ihnen zuvor versehentlich fehlerhaft ausgefüllte und
per App übermittelte Variante vollumfänglich. Ich habe den Bogen von Ihnen nochmals postalisch erhalten und nunmehr vollständig
und korrekt ausgefüllt. Ich bitte ausdrücklich darum, ausschließlich die beiliegende Neufassung zur Akte zu nehmen und die
vorangegangene fehlerhafte App-Übermittlung als gegenstandslos zu betrachten.
Rechtliche Hinweise & Fristwahrung:
Es gilt die im beiliegenden Widerspruch gesetzte Zahlungsfrist bis zum 08. Oktober 2026. Nach § 192 Abs. 1 Nr. 2 SGB V bleibt die
Mitgliedschaft beitragsfrei erhalten. Eine beitragspflichtige freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V wird
ausdrücklich abgelehnt.
Mit freundlichen Grüßen
___________________________________________________
Alina Steinhauer',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/02_Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf',
                'sha256' => '826cd382a005b98ffae968ec1e6990306b9b9113a5907e41aa513461fe08ae4d',
                'filename' => '02_Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 97507,
                'title' => 'Formeller Widerspruch gegen Ablehnungsbescheid Krankengeld (Ref: 9650226941682)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Widerspruch', 'Krankengeld', 'GA-OP', 'Dr. Lubos Kliniken', '§ 242 BGB', '§ 48 SGB V', 'Z98.8', 'T81.8', 'R52.2', 'Frist 08.10.2026'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Umfassender 2-seitiger Widerspruch gegen den Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682). Geltendmachung des Krankengeldes für die geschlechtsangleichende Großoperation ab 22.06.2026 (Tagessatz: 65,90 EUR brutto). Rüge des Berechnungsirrtums der BKK (18.12.2025 vs. 29.01.2026), Einrede des Rechtsmissbrauchs nach § 242 BGB wegen verbindlicher OP-Kostenzusage bis 30.06.2026, Nachweis eines neuen somatischen Falls (§ 48 Abs. 2 SGB V). Fristsetzung bis 08.10.2026, Androhung SG-Eilantrag (§ 86b Abs. 2 SGG) und BAS-Aufsichtsbeschwerde.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Versorgungsmanagement Geldleistungen / Widerspruchsstelle
Gottlieb-Daimler-Straße 11, 28237 Bremen (Postanschrift: 28192
Bremen)
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rücksche',
                'full_text' => 'Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Versorgungsmanagement Geldleistungen / Widerspruchsstelle
Gottlieb-Daimler-Straße 11, 28237 Bremen (Postanschrift: 28192
Bremen)
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rückschein
Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
Versicherten-Nr.: O603571189
Ihr Bescheid: 01.10.2026 (Ref: 9650226941682)
Krankengeld-Tagessatz: 65,90 € brutto
Eilt sehr – Fristwahrung
WIDERSPRUCH UND UMFASSENDE WIDERSPRUCHSBEGRÜNDUNG
1. Gegen Ihren schriftlichen Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682) – Betreff: Ihre Arbeitsunfähigkeit vom
22.06.2026 – sowie gegen die vorausgegangenen Mitteilungen vom 06.08. und 09.09.2026
2. Antrag auf sofortige Feststellung und Auszahlung des Krankengeldes für die operationsbedingte Arbeitsunfähigkeit ab dem
22.06.2026 (Tagessatz: 65,90 € brutto / monatlich 1.977,00 € brutto gemäß Bescheid vom 23.08.2024)
3. Einrede von Treu und Glauben (§ 242 BGB - Venire contra factum proprium): Bindungswirkung der Kassen-Kostenzusage vom
01.10.2025 bis zum 30.06.2026 / Keine treuwidrige Berufung auf die Aussteuerung
4. Nachweis eines eigenständigen somatischen Versicherungsfalls: Erstbescheinigung Dr. Lubos Kliniken & Attest Hausärztin
Dorothea Jung / Fristsetzung zur Auszahlung bis 08.10.2026
Sehr geehrte Damen und Herren, sehr geehrte Mitglieder des Widerspruchsausschusses,
hiermit lege ich gegen Ihren heute zugestellten Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682) sowie gegen die
vorangegangenen Verweigerungen vom 06.08.2026 und 09.09.2026 form- und fristgerecht WIDERSPRUCH ein.
Ich beantrage:
1. Ihren Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682) vollumfänglich aufzuheben.
 
2. Meinen Anspruch auf Krankengeld (§§ 44, 46 SGB V) für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026 und die
 postoperative Rekonvaleszenz in gesetzlicher Höhe von kalendertäglich 65,90 EUR brutto verbindlich anzuerkennen und die
aufgelaufenen Beträge unverzüglich auf mein Girokonto zu überweisen.
3. Die Fortdauer meiner gesetzlichen Krankenversicherung festzustellen.
 
4. Mir die notwendigen Aufwendungen gemäß § 63 SGB X zu erstatten sowie fällige Geldleistungen nach § 44 SGB I mit 4 % zu
 verzinsen.
Frist für den Zahlungseingang auf meinem Girokonto: 08. OKTOBER 2026.
I. Streitgegenstand & Eklatanter Berechnungsirrtum der BKK firmus
Gegenstand dieses Widerspruchs ist die Bewilligung und unverz',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/03_Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf',
                'sha256' => 'b5d50b08a44db5ec64f9f9346dfd4987bc51badaab98d1a849bfd21e71650826',
                'filename' => '03_Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 91763,
                'title' => 'Anlage & Ergänzende Erläuterungen zum Formular Klärung Versicherungsverhältnis (BKK firmus)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Klärung Versicherungsverhältnis', 'Anlage', 'Erläuterungsblatt', '§ 192 SGB V', 'Beitragsfreie Mitgliedschaft', 'BKK firmus', '-6279143758668390890'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => '1-seitiges offizielles Erläuterungsblatt zu den Ziffern 1 bis 6 des handschriftlich ausgefüllten BKK-Originalformulars (Az. -6279143758668390890). Begründet das gesetzliche Fortbestehen der beitragsfreien Pflichtmitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V aufgrund des Krankengeldanspruchs ab 03.08.2026 und weist eine freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V ausdrücklich zurück.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum:
 01.09.1993 · Versichertennummer: O603571189
An die BKK firmus  · 28192 Bremen
Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis',
                'full_text' => 'Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum:
 01.09.1993 · Versichertennummer: O603571189
An die BKK firmus  · 28192 Bremen
Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890
Sehr geehrte Damen und Herren, das nachfolgende Erläuterungsblatt ist verbindlicher Bestandteil des beigefügten Originalformulars
und ergänzt die dortigen Angaben zu den Ziffern 1 bis 6:
Zu Ziffer 1: Familienstand
Die Ehe mit Frau Lena Salewski (geb. 18.11.1997) ist seit dem 18.10.2024 rechtskräftig geschieden. Es besteht kein Eheband und
keine eingetragene Lebenspartnerschaft mehr. Eine Familienversicherung über einen Ehegatten scheidet aus.
Zu Ziffer 2: Beschäftigungsaufnahme
Eine Beschäftigungsaufnahme liegt nicht vor. Seit dem 22.06.2026 besteht fortdauernde somatische Arbeitsunfähigkeit infolge der am
23.06.2026 in den MVZ Dr. Lubos Kliniken München durchgeführten geschlechtsangleichenden Großoperation (stat. Aufenthalt bis
19.07.2026, danach lückenlose Krankschreibung durch Hausärztin D. Jung).
Zu Ziffer 3: Leistungsantrag bei der Agentur für Arbeit / ARGE
Leistungen der Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) wurden im Rahmen der gesetzlichen 6-wöchigen Leistungsfortzahlung
nach 
§ 146 Abs. 1 Satz 1 SGB III bis einschließlich 02.08.2026 bezogen. Die Zahlung wurde mit Ablauf der 6 Wochen am 02.08.2026
planmäßig beendet. Wegen der rechtswidrigen Leistungsverweigerung des Krankengeldes durch die BKK firmus wurde bei der Agentur
für Arbeit Gifhorn Antrag auf vorläufige Weiterzahlung gem. § 43 SGB I / § 328 SGB III gestellt.
Zu Ziffer 4 & 5: Freiwillige Weiterversicherung & Familienversicherung (nicht zutreffend)
Nicht zutreffend. Eine beitragspflichtige freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V kommt nicht zum Tragen und wird
ausdrücklich abgelehnt. Der gesetzliche Versicherungsschutz besteht kraft Gesetzes als beitragsfreie Pflichtmitgliedschaft fort (siehe
Ziffer 6). Ein Anspruch auf Familienversicherung besteht nicht.
Zu Ziffer 6: Sonstiges, Anmerkungen & Rechtliche Feststellung der beitragsfreien Mitgliedschaft
1. Beitragsfreie Mitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V: Gemäß § 192 Abs. 1 Nr. 2 SGB V bleibt die Mitgliedschaft
Versicherungspflichtiger erhalten, solange Anspruch auf Krankengeld besteht. Da im Anschluss an die 6-wöchige Leistungsfortzahlung
der BA (§ 146 SGB III) seit dem 03.08.2026 ',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/04_Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf',
                'sha256' => 'e3b7d648e01f3dc10950b821d04cf354553840bbb1d1b9f0c2f0dba855632951',
                'filename' => '04_Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 5203,
                'title' => 'Ärztliches Attest & sozialmedizinische Stellungnahme (Hausarztpraxis Dorothea Jung, Leiferde)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Attest', 'Hausärztin', 'Dorothea Jung', 'Leiferde', 'Somatik', 'Z98.8', 'T81.8', 'R52.2', 'N99.8', 'M62.88', 'Krankengeld'],
                'purpose' => 'Medizinisches Facharztattest zur Vorlage bei Krankenkasse/Medizinischem Dienst zum Nachweis der Behandlungsnotwendigkeit und Arbeitsunfähigkeit.',
                'summary' => '1-seitige sozialmedizinische Stellungnahme zur Vorlage und Unterzeichnung bei Hausärztin Dorothea Jung (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) am Montag, 05.10.2026. Bescheinigt die rein somatische Genese der AU infolge der GA-Großoperation vom 23.06.2026 und hebt die Aufhebung des Leistungsvermögens für weitere 6–8 Wochen hervor.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND S',
                'full_text' => 'GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND SOZIALMEDIZINISCHE STELLUNGNAHME
 Zur Vorlage bei der BKK firmus (Versorgungsmanagement Krankengeld) und beim Sozialgericht
Patientin: Frau Alina Steinhauer
Geburtsdatum: 01.09.1993
Anschrift: Carl-Goerdeler-Ring 26, 38518 Gifhorn
Krankenkasse: BKK firmus
Versichertennummer: O603571189
Datum der Stellungnahme: 01.10.2026
Maßgebliche somatische Haupt- und Behandlungsdiagnosen der aktuellen Arbeitsunfähigkeit:
 ICD-10 Z98.8: Zustand nach sonstigen näher bezeichneten Operationen (Geschlechtsangleichende Großoperation am 23.06.2026)
 
 ICD-10 T81.8: Sonstige Komplikationen bei Eingriffen / ausgedehnte postoperative Wundheilungsstörung und Gewebereaktion
 
 ICD-10 R52.2: Sonstiger chronischer und postoperativer Schmerz (akutes postoperatives Wundschmerzsyndrom)
 
 ICD-10 N99.8: Sonstige Krankheiten des Urogenitalsystems nach medizinischen Maßnahmen (Zustand nach Rekonstruktion Neovagina)
 
 ICD-10 M62.88: Massive Bewegungseinschränkung des Beckens und Rumpfes / strikte Aufhebung der Sitz- und Stehfähigkeit
 
1. Behandlungsverlauf & Operationsereignis
Frau Alina Steinhauer befindet sich in meiner kontinuierlichen hausärztlichen Betreuung. Am 22.06.2026 erfolgte die stationäre Aufnahme in
der Fachklinik MVZ Dr. Lubos Kliniken München-Pasing zur Durchführung einer hochkomplexen geschlechtsangleichenden Operation
(chirurgischer Eingriff am 23.06.2026: Penektomie, beidseitige Orchiektomie, Rekonstruktion von Neovagina und Neovulva). Nach der
Entlassung am 19.07.2026 habe ich in unserer Praxis in Leiferde die lückenlose ambulante Weiterbehandlung und Nachsorge übernommen.
2. Medizinische Differenzierung & Eigenständigkeit des Krankheitsbildes
Es wird hiermit ausdrücklich sozialmedizinisch festgestellt, dass die seit dem 22.06.2026 bestehende Arbeitsunfähigkeit ein
eigenständiges, akut-somatisches Krankheitsgeschehen darstellt. Sie resultiert unmittelbar und primär aus den schweren chirurgischen
Wund- und Gewebefolgen des Großeingriffs.
Abgrenzung zur vorangegangenen Arbeitsunfähigkeit (Aussteuerung Januar 2026): Die bis zum 29.01.2026 dokumentierte
Arbeitsunfähigkeit beruhte im Wesentlichen auf psychischen und psychosozialen Belastungsreaktionen (Geschlechtsdysphorie, ',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/05_Bescheid_BKK_firmus_Krankengeld_01-10-2026.pdf',
                'sha256' => '68903033a9d04f990d98e35f021177658ea67eb5e2a868c0fb1620231de2c907',
                'filename' => '05_Bescheid_BKK_firmus_Krankengeld_01-10-2026.pdf',
                'file_type' => 'pdf',
                'file_size' => 247825,
                'title' => 'Ablehnungsbescheid Krankengeld BKK firmus vom 01.10.2026 (Ref: 9650226941682)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Bescheid', 'Ablehnung', 'BKK firmus', '9650226941682', 'Aussteuerung', 'Krankengeld', '01.10.2026'],
                'purpose' => 'Offizieller Verwaltungsakt / Bescheid mit Frist- und Rechtsbehelfsbelehrung.',
                'summary' => 'Schriftlicher Ablehnungsbescheid der BKK firmus vom 01.10.2026 bezüglich der Arbeitsunfähigkeit ab 22.06.2026. Behauptet fälschlich Anspruchsende zum 18.12.2025 und Ursachenzusammenhang mit früherer Aussteuerung.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgesc',
                'full_text' => 'Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgeschrieben. Hiernach ist die Bezugsdauer des Krankengeldes wegen 
derselben Krankheit auf längstens 78 Wochen innerhalb von je drei Jahren begrenzt 
(gerechnet vom ersten Tag der Arbeitsunfähigkeit).  
 
Der Dreijahreszeitraum beginnt in Ihrem Fall mit dem 18.06.2024 und endet zum 17.06.2027. 
Folgende Arbeitsunfähigkeitszeiten wurden angerechnet:  
18.06.2024 bis 08.07.2024 
12.07.2024 bis 29.01.2026 
 
Ihr Anspruch auf Krankengeld endet demnach am 18.12.2025.
 
Am 18.06.2027 beginnt ein neuer Dreijahreszeitraum wegen derselben Erkrankung, in dem 
grundsätzlich ein neuer Anspruch auf Krankengeld besteht.  
 
Voraussetzung hierfür ist, dass Sie bei Eintritt einer erneuten Arbeitsunfähigkeit mit 
Anspruch auf Krankengeld versichert sind und in der Zwischenzeit mindestens 6 Monate  
 
1. nicht wegen dieser Krankheit arbeitsunfähig waren und 
2. erwerbstätig waren oder der Arbeitsvermittlung zur Verfügung standen. 
 
Bedauerlicherweise sind die Voraussetzungen in Ihrem Fall nicht erfüllt.  
Ab dem 22.06.2026 sind Sie wegen derselben Diagnose, die ebenfalls im 
ursächlichen Zusammenhang mit der Aussteuerungsdiagnose steht, 
innerhalb der laufenden Blockfrist arbeitsunfähig.  
 
Bitte haben Sie Verständnis, dass daher ab Beginn der neuen 
Arbeitsunfähigkeit ab 22.06.2026 leider kein Anspruch auf Krankengeld 
besteht. 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  0421 6434403   
Telefax: 0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
    Datum:  01.10.2026 
      
      
 
 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650226941682 
 
Bitte wenden Sie sich sofort, wegen eventueller Ansprüche, an die Agentur für Arbeit. Sollte 
diesem Leistungsantrag nicht entsprochen werden, setzen Sie sich bitte mit dem für Sie 
zuständigen Sozialamt in Verbindung, damit Ihr Lebensunterhalt',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/9650226941682.pdf',
                'sha256' => '68903033a9d04f990d98e35f021177658ea67eb5e2a868c0fb1620231de2c907',
                'filename' => '9650226941682.pdf',
                'file_type' => 'pdf',
                'file_size' => 247825,
                'title' => 'Ablehnungsbescheid Krankengeld BKK firmus vom 01.10.2026 (Ref: 9650226941682)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Bescheid', 'Ablehnung', 'BKK firmus', '9650226941682', 'Aussteuerung', 'Krankengeld', '01.10.2026'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Schriftlicher Ablehnungsbescheid der BKK firmus vom 01.10.2026 bezüglich der Arbeitsunfähigkeit ab 22.06.2026. Behauptet fälschlich Anspruchsende zum 18.12.2025 und Ursachenzusammenhang mit früherer Aussteuerung.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgesc',
                'full_text' => 'Ihre Arbeitsunfähigkeit vom 22.06.2026[[neuerBrief]] 
 
 
Guten Tag Frau Steinhauer, 
 
Ihre o.g. Arbeitsunfähigkeitsbescheinigung haben wir erhalten und Ihren Anspruch auf 
Krankengeld geprüft. 
 
Die Dauer und Höhe des Krankengeldes sind – einheitlich für alle Krankenkassen – 
gesetzlich vorgeschrieben. Hiernach ist die Bezugsdauer des Krankengeldes wegen 
derselben Krankheit auf längstens 78 Wochen innerhalb von je drei Jahren begrenzt 
(gerechnet vom ersten Tag der Arbeitsunfähigkeit).  
 
Der Dreijahreszeitraum beginnt in Ihrem Fall mit dem 18.06.2024 und endet zum 17.06.2027. 
Folgende Arbeitsunfähigkeitszeiten wurden angerechnet:  
18.06.2024 bis 08.07.2024 
12.07.2024 bis 29.01.2026 
 
Ihr Anspruch auf Krankengeld endet demnach am 18.12.2025.
 
Am 18.06.2027 beginnt ein neuer Dreijahreszeitraum wegen derselben Erkrankung, in dem 
grundsätzlich ein neuer Anspruch auf Krankengeld besteht.  
 
Voraussetzung hierfür ist, dass Sie bei Eintritt einer erneuten Arbeitsunfähigkeit mit 
Anspruch auf Krankengeld versichert sind und in der Zwischenzeit mindestens 6 Monate  
 
1. nicht wegen dieser Krankheit arbeitsunfähig waren und 
2. erwerbstätig waren oder der Arbeitsvermittlung zur Verfügung standen. 
 
Bedauerlicherweise sind die Voraussetzungen in Ihrem Fall nicht erfüllt.  
Ab dem 22.06.2026 sind Sie wegen derselben Diagnose, die ebenfalls im 
ursächlichen Zusammenhang mit der Aussteuerungsdiagnose steht, 
innerhalb der laufenden Blockfrist arbeitsunfähig.  
 
Bitte haben Sie Verständnis, dass daher ab Beginn der neuen 
Arbeitsunfähigkeit ab 22.06.2026 leider kein Anspruch auf Krankengeld 
besteht. 
 
 
 
BKK firmus  28192 Bremen   
 
 
 
 
 
 
Frau 
Alina Steinhauer 
Carl-Goerdeler-Ring 26 
38518 Gifhorn 
Betriebskrankenkasse firmus 
Körperschaft des öffentlichen Rechts 
 
Versorgungsmanagement Geldleistungen  
Knollstraße 16  
49074 Osnabrück  
 
Telefon:  0421 6434403   
Telefax: 0421 6434451  
Webseite:  www.bkk-firmus.de 
 
Bei Antworten immer angeben: 
O603571189  
  
    Datum:  01.10.2026 
      
      
 
 
BKK firmus 
Gottlieb-Daimler-Straße 11 
28237 Bremen 
 
Postanschrift 
BKK firmus 
28192 Bremen 
 
Bankverbindung 
Commerzbank AG 
IBAN: DE53 2654 0070 0526 2100 00 
BIC: COBADEFFXXX 
9650226941682 
 
Bitte wenden Sie sich sofort, wegen eventueller Ansprüche, an die Agentur für Arbeit. Sollte 
diesem Leistungsantrag nicht entsprochen werden, setzen Sie sich bitte mit dem für Sie 
zuständigen Sozialamt in Verbindung, damit Ihr Lebensunterhalt',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf',
                'sha256' => 'e3b7d648e01f3dc10950b821d04cf354553840bbb1d1b9f0c2f0dba855632951',
                'filename' => 'Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 5203,
                'title' => 'Ärztliches Attest & sozialmedizinische Stellungnahme (Hausarztpraxis Dorothea Jung, Leiferde)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Attest', 'Hausärztin', 'Dorothea Jung', 'Leiferde', 'Somatik', 'Z98.8', 'T81.8', 'R52.2', 'N99.8', 'M62.88', 'Krankengeld'],
                'purpose' => 'Medizinisches Facharztattest zur Vorlage bei Krankenkasse/Medizinischem Dienst zum Nachweis der Behandlungsnotwendigkeit und Arbeitsunfähigkeit.',
                'summary' => '1-seitige sozialmedizinische Stellungnahme zur Vorlage und Unterzeichnung bei Hausärztin Dorothea Jung (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) am Montag, 05.10.2026. Bescheinigt die rein somatische Genese der AU infolge der GA-Großoperation vom 23.06.2026 und hebt die Aufhebung des Leistungsvermögens für weitere 6–8 Wochen hervor.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND S',
                'full_text' => 'GEMEINSCHAFTSPRAXIS FÜR ALLGEMEINMEDIZIN
Dorothea Jung  Fachärztin für Allgemeinmedizin
Dr. med. Christian Schmidt  Facharzt für Allgemeinmedizin, Flugmedizin | Sandra Rauch  Fachärztin für Innere Medizin
Wilhelm-Busch-Straße 2  38542 Leiferde  Telefon: 05373 1814
 ÄRZTLICHE BESCHEINIGUNG UND SOZIALMEDIZINISCHE STELLUNGNAHME
 Zur Vorlage bei der BKK firmus (Versorgungsmanagement Krankengeld) und beim Sozialgericht
Patientin: Frau Alina Steinhauer
Geburtsdatum: 01.09.1993
Anschrift: Carl-Goerdeler-Ring 26, 38518 Gifhorn
Krankenkasse: BKK firmus
Versichertennummer: O603571189
Datum der Stellungnahme: 01.10.2026
Maßgebliche somatische Haupt- und Behandlungsdiagnosen der aktuellen Arbeitsunfähigkeit:
 ICD-10 Z98.8: Zustand nach sonstigen näher bezeichneten Operationen (Geschlechtsangleichende Großoperation am 23.06.2026)
 
 ICD-10 T81.8: Sonstige Komplikationen bei Eingriffen / ausgedehnte postoperative Wundheilungsstörung und Gewebereaktion
 
 ICD-10 R52.2: Sonstiger chronischer und postoperativer Schmerz (akutes postoperatives Wundschmerzsyndrom)
 
 ICD-10 N99.8: Sonstige Krankheiten des Urogenitalsystems nach medizinischen Maßnahmen (Zustand nach Rekonstruktion Neovagina)
 
 ICD-10 M62.88: Massive Bewegungseinschränkung des Beckens und Rumpfes / strikte Aufhebung der Sitz- und Stehfähigkeit
 
1. Behandlungsverlauf & Operationsereignis
Frau Alina Steinhauer befindet sich in meiner kontinuierlichen hausärztlichen Betreuung. Am 22.06.2026 erfolgte die stationäre Aufnahme in
der Fachklinik MVZ Dr. Lubos Kliniken München-Pasing zur Durchführung einer hochkomplexen geschlechtsangleichenden Operation
(chirurgischer Eingriff am 23.06.2026: Penektomie, beidseitige Orchiektomie, Rekonstruktion von Neovagina und Neovulva). Nach der
Entlassung am 19.07.2026 habe ich in unserer Praxis in Leiferde die lückenlose ambulante Weiterbehandlung und Nachsorge übernommen.
2. Medizinische Differenzierung & Eigenständigkeit des Krankheitsbildes
Es wird hiermit ausdrücklich sozialmedizinisch festgestellt, dass die seit dem 22.06.2026 bestehende Arbeitsunfähigkeit ein
eigenständiges, akut-somatisches Krankheitsgeschehen darstellt. Sie resultiert unmittelbar und primär aus den schweren chirurgischen
Wund- und Gewebefolgen des Großeingriffs.
Abgrenzung zur vorangegangenen Arbeitsunfähigkeit (Aussteuerung Januar 2026): Die bis zum 29.01.2026 dokumentierte
Arbeitsunfähigkeit beruhte im Wesentlichen auf psychischen und psychosozialen Belastungsreaktionen (Geschlechtsdysphorie, ',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf',
                'sha256' => '7ca3217f585facde4d4d4d10c0781e262276f838cae7f8c1047d5b5d2c00323a',
                'filename' => 'Anlage_Klaerung_Versicherungsverhaeltnis_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 91767,
                'title' => 'Anlage & Ergänzende Erläuterungen zum Formular Klärung Versicherungsverhältnis (BKK firmus)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Klärung Versicherungsverhältnis', 'Anlage', 'Erläuterungsblatt', 'BKK firmus', '-6279143758668390890', '§ 192 SGB V', 'Beitragsfreie Mitgliedschaft'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => '1-seitiges offizielles Erläuterungsblatt als Anlage zum Originalformular \'Klärung Versicherungsverhältnis\' der BKK firmus (Az. -6279143758668390890). Erläutert die Punkte 1 bis 6 und begründet die beitragsfreie Mitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum: 01.09.1993 · Versichertennummer: O603571189
 An die BKK firmus · 28192 Bremen
 Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis',
                'full_text' => 'Versicherte: Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
Geburtsdatum: 01.09.1993 · Versichertennummer: O603571189
 An die BKK firmus · 28192 Bremen
 Datum: 01.10.2026
ANLAGE & ERGÄNZENDE ERLÄUTERUNGEN ZUM FORMULAR
»KLÄRUNG VERSICHERUNGSVERHÄLTNIS«
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890
Sehr geehrte Damen und Herren, das nachfolgende Erläuterungsblatt ist verbindlicher Bestandteil des beigefügten Originalformulars
und ergänzt die dortigen Angaben zu den Ziffern 1 bis 6:
Zu Ziffer 1: Familienstand
Die Ehe mit Frau Lena Salewski (geb. 18.11.1997) ist seit dem 18.10.2024 rechtskräftig geschieden. Es besteht kein Eheband und
keine eingetragene Lebenspartnerschaft mehr. Eine Familienversicherung über einen Ehegatten scheidet aus.
Zu Ziffer 2: Beschäftigungsaufnahme
Eine Beschäftigungsaufnahme liegt nicht vor. Seit dem 22.06.2026 besteht fortdauernde somatische Arbeitsunfähigkeit infolge der am
23.06.2026 in den MVZ Dr. Lubos Kliniken München durchgeführten geschlechtsangleichenden Großoperation (stat. Aufenthalt bis
19.07.2026, danach lückenlose Krankschreibung durch Hausärztin D. Jung).
Zu Ziffer 3: Leistungsantrag bei der Agentur für Arbeit / ARGE
Leistungen der Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534) wurden im Rahmen der gesetzlichen 6-wöchigen Leistungsfortzahlung
nach § 146 Abs. 1 Satz 1 SGB III bis einschließlich 02.08.2026 bezogen. Die Zahlung wurde mit Ablauf der 6 Wochen am 02.08.2026
planmäßig beendet. Wegen der rechtswidrigen Leistungsverweigerung des Krankengeldes durch die BKK firmus wurde bei der Agentur
für Arbeit Gifhorn Antrag auf vorläufige Weiterzahlung gem. § 43 SGB I / § 328 SGB III gestellt.
Zu Ziffer 4 & 5: Freiwillige Weiterversicherung & Familienversicherung (nicht zutreffend)
Nicht zutreffend. Eine beitragspflichtige freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V kommt nicht zum Tragen und wird
ausdrücklich abgelehnt. Der gesetzliche Versicherungsschutz besteht kraft Gesetzes als beitragsfreie Pflichtmitgliedschaft fort (siehe
Ziffer 6). Ein Anspruch auf Familienversicherung besteht nicht.
Zu Ziffer 6: Sonstiges, Anmerkungen & Rechtliche Feststellung der beitragsfreien Mitgliedschaft
1. Beitragsfreie Mitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V: Gemäß § 192 Abs. 1 Nr. 2 SGB V bleibt die Mitgliedschaft
Versicherungspflichtiger erhalten, solange Anspruch auf Krankengeld besteht. Da im Anschluss an die 6-wöchige Leistungsfortzahlung
der BA (§ 146 SGB III) seit dem 03.08.2026 A',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Chronologische_Timeline_OP_Krankengeld_Alina_Steinhauer.md',
                'sha256' => 'a9b7c20d7efca84861d9787c461db46d49a18ab06563b09c722838b304efe7c9',
                'filename' => 'Chronologische_Timeline_OP_Krankengeld_Alina_Steinhauer.md',
                'file_type' => 'md',
                'file_size' => 6275,
                'title' => 'Chronologische Gesamttimeline: OP-Verzögerung, Aussteuerung & Krankengeldstreit (Alina Steinhauer)',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Timeline', 'Chronologie', 'BKK firmus', 'OP-Verzögerung', 'Aussteuerung', 'Krankengeld', '§ 242 BGB', 'Dr. Schmidt', '05.10.2026'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Vollständige, lückenlose chronologische Gesamttimeline von der OP-Beantragung Ende 2024 über die 7-monatige Hinhaltetaktik und Erst-Ablehnung der BKK firmus, die verbindliche Kostenzusage bis 30.06.2026, die Aussteuerung zum 29.01.2026, die OP am 23.06.2026 in den Dr. Lubos Kliniken, das Ende der 6-wöchigen Leistungsfortzahlung am 02.08.2026 bis zum aktuellen Ablehnungsbescheid vom 01.10.2026 und dem Eklat mit Dr. Schmidt am 05.10.2026.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => '# Chronologische Gesamttimeline: OP-Verzögerung, Aussteuerung & Krankengeldstreit
**Versicherte:** Alina Steinhauer (geb. 01.09.1993, Vers.-Nr.: O603571189)  
**Krankenkasse:** BKK firmus (Ref: 9650226941682 / Az: -6279143758668390890)  
**Bundesagentur für Arbeit:** Agentur für Arbeit Gifhorn (Kd.-',
                'full_text' => '# Chronologische Gesamttimeline: OP-Verzögerung, Aussteuerung & Krankengeldstreit
**Versicherte:** Alina Steinhauer (geb. 01.09.1993, Vers.-Nr.: O603571189)  
**Krankenkasse:** BKK firmus (Ref: 9650226941682 / Az: -6279143758668390890)  
**Bundesagentur für Arbeit:** Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534)  
**Stand:** 05.10.2026  

---

### Chronologischer Ablauf (von der OP-Beantragung bis zum aktuellen Eklat)

| Datum | Ereignis, Bescheid & rechtliche Bedeutung | Akten-/Dokument-Referenz |
| :--- | :--- | :--- |
| **13.12.2024 / 16.12.2024** | **Antrag auf geschlechtsangleichende Operation (GA-OP):** Alina beantragt die Kostenübernahme bei der BKK firmus. Die Kasse fordert zunächst zahlreiche Nachweise an und verzögert die Vorlage beim Medizinischen Dienst (MD) über ein halbes Jahr. | Dok. `9650078742642.pdf` |
| **19.06.2025** | **Verzögerte Weiterleitung an MD:** Erst über 6 Monate nach Antragstellung leitet die BKK firmus die OP-Unterlagen an den Medizinischen Dienst weiter. | Dok. `9650111610893.pdf` |
| **07.07.2025** | **Rechtswidrige Ablehnung der OP:** Die BKK firmus lehnt die Kostenübernahme für die geschlechtsangleichende Operation ab. | Ablehnungsbescheid vom 07.07.2025 |
| **09.07. / 10.07.2025** | **Sofortiger Widerspruch:** Alina legt energisch Widerspruch gegen die OP-Ablehnung ein und weist die Fehler des MD nach. | Widerspruchsschreiben |
| **15.07.2025** | **Einlenken der Kasse / Abhilfebescheid:** Die BKK firmus hebt die Ablehnung auf und bewilligt die GA-OP (Penisentfernung, Hodenentfernung, Neovagina) befristet bis 15.01.2026. **Kausalität der Verzögerung:** Durch das 7-monatige Zögern und die Erst-Ablehnung der Kasse war an den Dr. Lubos Kliniken München (Wartezeiten ~12 Monate) ein OP-Termin vor Ablauf der Frist nicht mehr zu bekommen. Der frühestmögliche Termin wurde auf den 23.06.2026 datiert. | Abhilfebescheid Dok. `9650114306636.pdf` |
| **10.09.2025** | **Antrag auf Verlängerung der Kostenzusage:** Alina beantragt Fristverlängerung wegen des OP-Termins am 23.06.2026. | Antrag Dok. `9650119701902.pdf` |
| **01.10.2025** | **Verlängerung der Kostenzusage bis 30.06.2026:** Die BKK firmus verlängert die Bewilligung verbindlich bis zum 30.06.2026 – in voller Kenntnis des Termins 23.06.2026! *(Begründet Bindungswirkung nach § 242 BGB).* | Bewilligungsbescheid Dok. `9650122075850.pdf` |
| **03.11.2025** | **Aussteuerungsbescheid der BKK firmus:** Nur einen Monat nach Verlängerung teilt die BKK firmus verbindlich mit, dass de',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Klaerung_Versicherungsverhaeltnis_BKK_firmus_Alina_Steinhauer.pdf',
                'sha256' => '625b50d76ccc864deb9c4128dee224b41ee556290e711f8aab813090d4d0f42e',
                'filename' => 'Klaerung_Versicherungsverhaeltnis_BKK_firmus_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 4605,
                'title' => 'Fragebogen Klärung Versicherungsverhältnis BKK firmus (Ref: -6279143758668390890) – Rechtssicher ausgefüllt',
                'category' => 'Krankengeld & Entgeltersatz',
                'tags' => ['Klärung Versicherungsverhältnis', 'BKK firmus', '-6279143758668390890', '§ 192 SGB V', 'Beitragsfreie Mitgliedschaft', 'Krankengeld'],
                'purpose' => 'Dokumentiertes Arbeits- und Vorgangsdokument im Bereich Krankengeld & Entgeltersatz.',
                'summary' => 'Offizieller Fragebogen \'Klärung Versicherungsverhältnis\' der BKK firmus (Az. -6279143758668390890 zum 02.08.2026), digital und rechtssicher ausgefüllt. Enthält den Nachweis der beitragsfreien Pflichtversicherung nach § 192 Abs. 1 Nr. 2 SGB V aufgrund fortbestehenden Krankengeldanspruchs.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'BKK firmus · Postfach 28192 Bremen · (Ref-Code: 0000 p08e/ DLGSVV0263945602_1_30 // 680823 12749 20406)
Klärung Versicherungsverhältnis
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890
Versicherte: Alina Steinhauer, geb. 01.09.1993 · Vers.-Nr.: O603571189
1. Familienstand
[',
                'full_text' => 'BKK firmus · Postfach 28192 Bremen · (Ref-Code: 0000 p08e/ DLGSVV0263945602_1_30 // 680823 12749 20406)
Klärung Versicherungsverhältnis
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890
Versicherte: Alina Steinhauer, geb. 01.09.1993 · Vers.-Nr.: O603571189
1. Familienstand
[   ] ledig                      [   ] verheiratet / eingetr. Lebenspartnerschaft seit: —
[   ] verwitwet                  [ X ] geschieden seit: 18.10.2024
Name, Vorname des früheren Ehegatten: Salewski, Lena          Geburtsdatum: 18.11.1997
Ehegatte / Lebenspartner versichert bei: entfällt (Ehe rechtskräftig geschieden)
2. Beschäftigungsaufnahme
Arbeitgeber: Keine Beschäftigungsaufnahme (fortdauernde somatische Arbeitsunfähigkeit seit 22.06.2026)
Anschrift: —                                  Beschäftigungsbeginn: —
3. Leistungsantrag bei der Agentur für Arbeit / ARGE
[ X ] Leistungen durch die Agentur für Arbeit
      Zuständige Agentur für Arbeit: Agentur für Arbeit Gifhorn (Kd.-Nr. 241D129534)
      Beginn des Leistungsbezuges: 30.01.2026 bis 02.08.2026 (6 Wochen Leistungsfortzahlung § 146 SGB III beendet)
      Hinweis: Antrag auf vorläufige Weiterzahlung gem. § 43 SGB I / § 328 SGB III gestellt.
[   ] Bezug von Arbeitslosengeld II / Bürgergeld      Träger: —      Beginn: —
4. Freiwillige Weiterversicherung (nicht zutreffend)
[   ] wegen Ausübung einer hauptberuflich selbstständigen Tätigkeit ab: —
[   ] Bitte senden Sie mir Informationen und einen Antrag zur freiwilligen Versicherung zu.
[ X ] Nicht zutreffend: Gesetzliche Mitgliedschaft besteht beitragsfrei nach § 192 Abs. 1 Nr. 2 SGB V fort (siehe Ziffer 6).
5. Anspruch auf Familienversicherung (nicht zutreffend)
[   ] bei der BKK firmus über Ehegatten / Eltern: —
[   ] Bei folgender Krankenkasse: —
[ X ] Nicht zutreffend (kein Anspruch auf Familienversicherung).
6. Sonstiges / Anmerkungen & Rechtliche Feststellung
Fortbestehen der beitragsfreien Mitgliedschaft nach § 192 Abs. 1 Nr. 2 SGB V:
1. Lückenlose Arbeitsunfähigkeit: Seit dem 22.06.2026 besteht infolge der geschlechtsangleichenden Großoperation in den Dr. Lubos
Kliniken München (stat. 22.06. – 19.07.2026, danach lückenlos durch Hausarztpraxis D. Jung) eine schwere somatische Arbeitsunfähigkeit
(ICD-10 Z98.8, T81.8, R52.2).
2. Gesetzlicher Krankengeldanspruch ab 03.08.2026: Die 6-wöchige Leistungsfortzahlung der Agentur für Arbeit gem. § 146 SGB III
endete am 02.08.2026. Ab dem 03.08.2026 besteht gesetzlicher Anspruch auf Krankengeld (§ 44 Abs. 1 i.V',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Lieferschein_BKK_firmus_Alina_Steinhauer.pdf',
                'sha256' => '544d770db8926b08ecba56a51ea647e7edb7200a963e14ddaef05b6884369745',
                'filename' => 'Lieferschein_BKK_firmus_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 3993,
                'title' => 'Dokumenten-Begleitschein / Inhaltsverzeichnis Postsendung BKK firmus Widerspruch Krankengeld',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Lieferschein', 'Begleitschein', 'Post', 'BKK firmus', 'Widerspruch', 'Krankengeld', '9650226941682'],
                'purpose' => 'Nachweis über die fristgerechte und vollständige Einreichung von Antrags- und Widerspruchsunterlagen.',
                'summary' => 'Offizieller 1-seitiger Dokumenten-Begleitschein / Lieferschein für die postalische Übersendung des Widerspruchs per Einschreiben an die BKK firmus Bremen.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Geldleistungen
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer
 Geburtsdatum: 01.09.1993
 Vers.-Nr.: O603571189
Ihr Bescheid: 9650226941682
Versandart: Einschreiben / Vo',
                'full_text' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Geldleistungen
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer
 Geburtsdatum: 01.09.1993
 Vers.-Nr.: O603571189
Ihr Bescheid: 9650226941682
Versandart: Einschreiben / Vorab per App
DOKUMENTEN-BEGLEITSCHEIN / INHALTSVERZEICHNIS
Zugehörig zum Widerspruch vom 01.10.2026 – Bescheid Ref: 9650226941682 (Krankengeld)
Sehr geehrte Damen und Herren,
diesem Briefumschlag liegen die nachfolgend aufgeführten Unterlagen zur rechtlichen Prüfung und
Aktenführung bei. Der Hauptwiderspruch wurde Ihnen vorab zur Fristwahrung am 01.10.2026 digital über die
BKK-firmus-Service-App übermittelt.
Pos.
Dokument / Inhalt
Datum
Umfang / Status
1
Formeller Widerspruch & Begründung
Gegen Ablehnungsbescheid Krankengeld (Ref: 9650226941682)
bezügl. GA-OP Dr. Lubos Kliniken. Mit eigenhändiger
Originalunterschrift.
01.10.2026
2 Seiten
Original
2
Ärztliches Attest & sozialmedizinische Stellungnahme
Hausarztpraxis Dorothea Jung, Leiferde. Bestätigung der rein
somatischen AU-Ursachen (Z98.8, T81.8, R52.2, N99.8).
01.10.2026
1 Seite
Ausfertigung
3
Klinik-Erstbescheinigung Arbeitsunfähigkeit
MVZ Dr. Lubos Kliniken München-Bogenhausen (stat. Aufenthalt
22.06.2026 bis 19.07.2026).
12.07.2026
1 Seite
Kopie
4
Verlängerung Kostenzusage BKK firmus
Verbindliche Genehmigung der geschlechtsangleichenden
Operation bis zum 30.06.2026 (Ref: 9650122075850).
01.10.2025
1 Seite
Kopie
Wichtiger rechtlicher Hinweis:
Die gesetzte Zahlungsfrist bis zum 08.10.2026 bleibt uneingeschränkt bestehen. Sollten die ausstehenden
Krankengeldleistungen bis zu diesem Datum nicht angewiesen sein, wird unverzüglich sozialgerichtlicher
Eilrechtsschutz nach § 86b Abs. 2 SGG beantragt.
Mit freundlichen Grüßen
___________________________________________________
Alina Steinhauer (Carl-Goerdeler-Ring 26, 38518 Gifhorn)',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Lieferschein_Widerspruch_BKK_firmus_Alina_Steinhauer.pdf',
                'sha256' => 'a499fe58161cf8a28ae10137d12632fd8932601035cc91ec0ee2786937689252',
                'filename' => 'Lieferschein_Widerspruch_BKK_firmus_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 4379,
                'title' => 'Dokumenten-Begleitschein / Lieferschein für Postsendung Widerspruch BKK firmus (nur Widerspruch)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Lieferschein', 'Begleitschein', 'Post', 'BKK firmus', 'Widerspruch', 'Krankengeld', '9650226941682'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Dokumenten-Begleitschein / Lieferschein für Postsendung an BKK firmus. Beinhaltet: 1. Widerspruch Krankengeld (2 Seiten) und 2. Neufassung Klärung Versicherungsverhältnis (1 Seite) mit ausdrücklichem Korrekturhinweis zur Ersetzung der fehlerhaften Erstübermittlung.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -62791437',
                'full_text' => 'Alina Steinhauer · Carl-Goerdeler-Ring 26 · 38518 Gifhorn
BKK firmus
Widerspruchsstelle / Klärung Versicherungsverhältnis
Gottlieb-Daimler-Straße 11
28237 Bremen
 Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
 Vers.-Nr.: O603571189
Aktenzeichen: Ref. 9650226941682 &
 Az. -6279143758668390890
Versand: Einschreiben / Vorab per App
DOKUMENTEN-BEGLEITSCHEIN / LIEFERSCHEIN
Zur Postsendung: Widerspruch Krankengeld & Neufassung Klärung Versicherungsverhältnis
Sehr geehrte Damen und Herren,
diesem Briefumschlag liegen die nachfolgend aufgeführten Unterlagen im Original zur rechtswirksamen Bearbeitung und
Aktenführung bei:
Pos.
Enthaltenes Dokument / Bezeichnung
Datum
Umfang & Status
1
Formeller Widerspruch und Widerspruchsbegründung
Gegen Ablehnungsbescheid vom 01.10.2026 (Ref: 9650226941682) – Betreff:
Krankengeld für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026
(GA-OP Dr. Lubos Kliniken).
(Vorab per BKK-firmus-App übermittelt. Zahlungsfrist: 08.10.2026)
01.10.2026
2 Seiten
Original
2
Klärung des Versicherungsverhältnisses (Aktualisierte Neufassung)
Aktenzeichen: Versicherungsverhältnis, 02.08.2026, -6279143758668390890.
Vollständig ausgefüllt mit Feststellung des Fortbestehens der beitragsfreien
gesetzlichen Mitgliedschaft gem. § 192 Abs. 1 Nr. 2 SGB V bei bestehendem
Krankengeldanspruch.
01.10.2026
1 Seite
Original
WICHTIGER HINWEIS ZUR KLÄRUNG DES VERSICHERUNGSVERHÄLTNISSES:
Die beiliegende Neufassung zur Klärung des Versicherungsverhältnisses (Pos. 2) ersetzt die Ihnen zuvor versehentlich fehlerhaft
ausgefüllte und per App übermittelte Variante vollumfänglich. Ich habe den Bogen von Ihnen nochmals postalisch erhalten und
nunmehr vollständig und korrekt ausgefüllt. Ich bitte ausdrücklich darum, ausschließlich die beiliegende Neufassung zur Akte zu
nehmen und die vorangegangene fehlerhafte App-Übermittlung als gegenstandslos zu betrachten.
Rechtliche Hinweise & Fristwahrung:
Es gilt die im beiliegenden Widerspruch gesetzte Zahlungsfrist bis zum 08. Oktober 2026. Nach § 192 Abs. 1 Nr. 2 SGB V bleibt die
Mitgliedschaft beitragsfrei erhalten. Eine beitragspflichtige freiwillige Weiterversicherung nach § 188 Abs. 4 SGB V wird
ausdrücklich abgelehnt.
Mit freundlichen Grüßen
___________________________________________________
Alina Steinhauer',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Widerspruch_BKK_firmus_Krankengeld_Alina_Steinhauer.txt',
                'sha256' => 'feb497e6590283e9e8b9fc98e8293e71c523319d300dda2cda9f5be6a50839d5',
                'filename' => 'Widerspruch_BKK_firmus_Krankengeld_Alina_Steinhauer.txt',
                'file_type' => 'txt',
                'file_size' => 10530,
                'title' => 'Textfassung Widerspruch: Krankengeld für GA-OP ab 22.06.2026',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Widerspruch', 'App-Vorlage'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Reine Textfassung des 2-seitigen Widerspruchs gegen die Krankengeldablehnung ab 22.06.2026 zur unkomplizierten Übernahme in die BKK-firmus-App.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
Geburtsdatum: 01.09.1993
Versichertennummer: O603571189

An die
BKK firmus
Körperschaft des öffentlichen Rechts
- Widerspruchsstelle / Versorgungsmanagement Krankengeld -
Gottlieb-Daimler-Straße 11
28237 Bremen

(Vorab per BKK-firmus-App / Fax an',
                'full_text' => 'Alina Steinhauer
Carl-Goerdeler-Ring 26
38518 Gifhorn
Geburtsdatum: 01.09.1993
Versichertennummer: O603571189

An die
BKK firmus
Körperschaft des öffentlichen Rechts
- Widerspruchsstelle / Versorgungsmanagement Krankengeld -
Gottlieb-Daimler-Straße 11
28237 Bremen

(Vorab per BKK-firmus-App / Fax an: 0421 6434-451 / 0541 33141-250 sowie postalisch per Einschreiben mit Rückschein)

Datum: 01.10.2026


BETREFF:
1. FORMELLER WIDERSPRUCH UND UMFASSENDE WIDERSPRUCHSBEGRÜNDUNG
   gegen die Ablehnung des Krankengeldanspruchs für die geschlechtsangleichende Operation ab dem 22.06.2026 (formlose Bescheide / Mitteilungen über die BKK-firmus-App vom 06.08.2026 und 09.09.2026)
2. DRINGENDER ANTRAG AUF SOFORTIGE FESTSTELLUNG UND AUSZAHLUNG DES KRANKENGELDES
   für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026 (stationäre Aufnahme MVZ Dr. Lubos Kliniken München-Pasing) bzw. nach Ablauf der 6-Wochen-Frist ab August 2026 fortlaufend in gesetzlicher Höhe (Tagessatz: 65,90 EUR brutto gemäß Bewilligungsbescheid vom 23.08.2024, Ref. 9650073846318)
3. EINREDE DES RECHTSMISSBRAUCHS / VERBOT WIDERSPRÜCHLICHEN VERHALTENS (Venire contra factum proprium, § 242 BGB):
   Bindungswirkung der Kassen-Kostenzusage vom 01.10.2025 bis zum 30.06.2026 / Treuwidrige Berufung auf die Aussteuerung
4. NACHWEIS EINES EIGENSTÄNDIGEN SOMATISCHEN VERSICHERUNGSFALLS:
   Erstbescheinigung Dr. Lubos Kliniken vom 12.07.2026 sowie beiliegendes fachärztliches Attest von Frau Dorothea Jung (Gemeinschaftspraxis Leiferde) vom 01.10.2026
5. ANDROHUNG EINES SOZIALGERICHTLICHEN EILVERFAHRENS (Einstweilige Anordnung nach § 86b Abs. 2 SGG) SOWIE FACHAUFSICHTSBESCHWERDE BEIM BUNDESAMT FÜR SOZIALE SICHERUNG (BAS)


Sehr geehrte Damen und Herren,
sehr geehrte Mitglieder des Widerspruchsausschusses,

hiermit lege ich gegen Ihre Verweigerung von Krankengeld für meine am 23.06.2026 durchgeführte geschlechtsangleichende Operation – mitgeteilt in Ihren Nachrichten über die BKK-firmus-App vom 06.08.2026 sowie zuletzt vom 09.09.2026 – form- und fristgerecht 

                       W I D E R S P R U C H

ein und begründe diesen wie folgt.

Ich beantrage:
1. Ihre ablehnenden Entscheidungen vom 06.08.2026 und 09.09.2026 vollumfänglich aufzuheben.
2. Meinen Anspruch auf Krankengeld (§§ 44, 46 SGB V) für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026 und die anschließende postoperative Rekonvaleszenz in gesetzlicher Höhe von kalendertäglich 65,90 EUR brutto verbindlich festzustellen und die aufgelauf',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Krankengeld/Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf',
                'sha256' => '720216961659fbc8f6ad52b18c72cb8ec8e57eade6a16881b5051e14c5a45158',
                'filename' => 'Widerspruch_Krankengeld_OP_Alina_Steinhauer.pdf',
                'file_type' => 'pdf',
                'file_size' => 97538,
                'title' => 'Widerspruch: Krankengeld für geschlechtsangleichende Operation ab 22.06.2026',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Gesundheit & Medizin', 'BKK Firmus', 'Krankengeld', 'Widerspruch', 'GA-OP', 'Dr. Lubos Kliniken', 'SGB V', '§ 242 BGB', 'Eilverfahren'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Fokussierter 2-seitiger Widerspruch gegen die Verweigerung von Krankengeld ab 22.06.2026. Begründung: Bindungswirkung der Kassen-Kostenzusage bis 30.06.2026 (Treu und Glauben, § 242 BGB), neuer somatischer Versicherungsfall (Z98.8, T81.8, R52.2), lückenlose AU und Fristsetzung bis 08.10.2026 unter Androhung eines Eilantrags nach § 86b Abs. 2 SGG.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Widerspruchsstelle / Versorgungsmanagement Krankengeld
Gottlieb-Daimler-Straße 11
28237 Bremen
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rückschein
Datum: 01.10.2026
Versicherte:',
                'full_text' => 'Alina Steinhauer  Carl-Goerdeler-Ring 26  38518 Gifhorn
BKK firmus
Körperschaft des öffentlichen Rechts
Widerspruchsstelle / Versorgungsmanagement Krankengeld
Gottlieb-Daimler-Straße 11
28237 Bremen
Vorab per BKK-App / Fax: 0421 6434-451 / Einschreiben m. Rückschein
Datum: 01.10.2026
Versicherte: Alina Steinhauer, geb. 01.09.1993
Versicherten-Nr.: O603571189
Krankengeld-Tagessatz: 65,90 € brutto
Hausärztin: Dorothea Jung, Leiferde
Dringendes Eilverfahren
WIDERSPRUCH UND UMFASSENDE WIDERSPRUCHSBEGRÜNDUNG
1. Gegen die Ablehnung von Krankengeld für die geschlechtsangleichende Operation ab 22.06.2026 (Mitteilungen der BKK
firmus vom 06.08.2026 und 09.09.2026)
2. Antrag auf sofortige Feststellung und Auszahlung des Krankengeldes für die operationsbedingte Arbeitsunfähigkeit ab
22.06.2026 (Tagessatz: 65,90 € brutto / monatlich 1.977,00 € brutto gemäß Bewilligungsbescheid vom 23.08.2024)
3. Einrede von Treu und Glauben (§ 242 BGB - Venire contra factum proprium): Bindungswirkung der Kassen-Kostenzusage
vom 01.10.2025 bis zum 30.06.2026 / Keine Berufung auf die Aussteuerung
4. Nachweis eines eigenständigen somatischen Versicherungsfalls (Erstbescheinigung Dr. Lubos Kliniken & Attest Hausärztin
Dorothea Jung) / Fristsetzung bis 08.10.2026
Sehr geehrte Damen und Herren, sehr geehrte Mitglieder des Widerspruchsausschusses,
hiermit lege ich gegen Ihre Verweigerung von Krankengeld für meine am 23.06.2026 durchgeführte geschlechtsangleichende
Operation – mitgeteilt in Ihren Nachrichten über die BKK-firmus-App vom 06.08.2026 sowie zuletzt vom 09.09.2026 – form- und
fristgerecht WIDERSPRUCH ein.
Ich beantrage:
1. Ihre ablehnenden Entscheidungen vom 06.08.2026 und 09.09.2026 vollumfänglich aufzuheben.
 
2. Meinen Anspruch auf Krankengeld (§§ 44, 46 SGB V) für die operationsbedingte Arbeitsunfähigkeit ab dem 22.06.2026 und die
 anschließende postoperative Rekonvaleszenz in Höhe von kalendertäglich 65,90 EUR brutto verbindlich anzuerkennen und die
aufgelaufenen Beträge unverzüglich auf mein Girokonto zu überweisen.
3. Mir die notwendigen Aufwendungen gemäß § 63 SGB X zu erstatten sowie fällige Geldleistungen nach § 44 SGB I mit 4 % zu
 verzinsen.
Frist für den Zahlungseingang auf meinem Girokonto: 08. OKTOBER 2026.
I. Streitgegenstand & Ablehnungsbegründung der BKK firmus
Gegenstand dieses Widerspruchs ist ausschließlich die Gewährung und unverzügliche Auszahlung von Krankengeld für
meine am 23.06.2026 in der Fachklinik MVZ Dr. Lubos Kliniken München-Pasing durchgeführte g',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Nachweise/06_Nachweis_Foto_Briefinhalt_1_Lieferschein_Widerspruch.jpeg',
                'sha256' => 'b3d45d3793bdd2eaa27d3719e168eb279f3c96cfeed9530c1497160b9aebfc88',
                'filename' => '06_Nachweis_Foto_Briefinhalt_1_Lieferschein_Widerspruch.jpeg',
                'file_type' => 'jpeg',
                'file_size' => 229931,
                'title' => 'Nachweisfoto Briefinhalt 1: Lieferschein und Widerspruch (unterzeichnet)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Nachweis', 'Foto', 'Einschreiben', 'Briefinhalt', 'Lieferschein', 'Widerspruch', 'Unterschrift', '01.10.2026'],
                'purpose' => 'Formeller Widerspruch gegen die Ablehnung oder Einstellung von Leistungen (Krankengeld / GA-Großoperation).',
                'summary' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig unterzeichneten Dokumente: 1. Lieferschein / Begleitschein und 2. Formeller Widerspruch (Seiten 1 und 2) vor Kuvertierung für den Postversand per Einschreiben.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig unterzeichneten Dokumente: 1. Lieferschein / Begleitschein und 2. Formeller Widerspruch (Seiten 1 und 2) vor Kuvertierung für den Postversand per Einschreiben. [Bildnachweis / Foto der Originalunterschriften und Kuvertierung]',
                'full_text' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig unterzeichneten Dokumente: 1. Lieferschein / Begleitschein und 2. Formeller Widerspruch (Seiten 1 und 2) vor Kuvertierung für den Postversand per Einschreiben. [Bildnachweis / Foto der Originalunterschriften und Kuvertierung]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Nachweise/07_Nachweis_Foto_Briefinhalt_2_Formular_Anlage.jpeg',
                'sha256' => '2a5c79cb3d1a5eb50b7474c22a44be76e7eadc10a570216ef03cd1f2dcd706f7',
                'filename' => '07_Nachweis_Foto_Briefinhalt_2_Formular_Anlage.jpeg',
                'file_type' => 'jpeg',
                'file_size' => 212377,
                'title' => 'Nachweisfoto Briefinhalt 2: BKK-Originalformular und Anlage (unterzeichnet)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Nachweis', 'Foto', 'Einschreiben', 'Briefinhalt', 'Klärung Versicherungsverhältnis', 'BKK Formular', 'Anlage', '01.10.2026'],
                'purpose' => 'Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).',
                'summary' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig ausgefüllten und unterzeichneten Dokumente: 1. Handschriftlich ausgefülltes Original-Formular der BKK firmus \'Klärung Versicherungsverhältnis\' (mit Telefon 01570 1916814) und 2. Offizielles Erläuterungsblatt (Anlage Ziffern 1–6) nebst Widerspruch Seite 2.',
                'key_facts' => [],
                'extracted_date' => '2026-10-01',
                'content_preview' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig ausgefüllten und unterzeichneten Dokumente: 1. Handschriftlich ausgefülltes Original-Formular der BKK firmus \'Klärung Versicherungsverhältnis\' (mit Telefon 01570 1916814) und 2. Offizielles Erläuterungsblatt (Anlage Ziffern 1–6) n',
                'full_text' => 'Fotodokumentation der gedruckten und von Alina Steinhauer eigenhändig ausgefüllten und unterzeichneten Dokumente: 1. Handschriftlich ausgefülltes Original-Formular der BKK firmus \'Klärung Versicherungsverhältnis\' (mit Telefon 01570 1916814) und 2. Offizielles Erläuterungsblatt (Anlage Ziffern 1–6) nebst Widerspruch Seite 2. [Bildnachweis / Foto der Originalunterschriften und Kuvertierung]',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Nachweise/08_Nachweis_Foto_Einlieferungsbeleg_Einschreiben_RT613794858DE.jpeg',
                'sha256' => '34bcf7fae90d620a4dbdd43b07a17a9cf2b74cba170643a9079cc4e58821bb55',
                'filename' => '08_Nachweis_Foto_Einlieferungsbeleg_Einschreiben_RT613794858DE.jpeg',
                'file_type' => 'jpeg',
                'file_size' => 190809,
                'title' => 'Deutsche Post Einlieferungsbeleg Einschreiben Einwurf BKK firmus (Sendungsnummer RT 6137 9485 8DE)',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Einlieferungsbeleg', 'Einschreiben', 'Deutsche Post', 'Tracking', 'RT 6137 9485 8DE', 'Meinersen', 'BKK firmus', 'Widerspruch', 'Krankengeld', '02.10.2026'],
                'purpose' => 'Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).',
                'summary' => 'Offizieller Einlieferungsbeleg der Deutschen Post AG (Filiale 38536 Meinersen) vom 02.10.2026, 10:59 Uhr über den postalischen Versand der Widerspruchsunterlagen und der Neufassung Klärung Versicherungsverhältnis an die BKK firmus Bremen. Versandart: Einschreiben Einwurf, Sendungsnummer: RT 6137 9485 8DE.',
                'key_facts' => [],
                'extracted_date' => '2026-10-02',
                'content_preview' => 'Einlieferungsbeleg
Bitte Beleg gut aufbewahren!
Deutsche Post AG 38536 Meinersen
81047784 6596 02.10.26 10:59
Sendungsnummer: RT 6137 9485 8DE
Einschreiben Einwurf
Information zum Sendungsstatus:
Code bequem mit unserer App scannen
oder Sendungsnummer unter www.deutschepost.de/briefstatus eingeben
K',
                'full_text' => 'Einlieferungsbeleg
Bitte Beleg gut aufbewahren!
Deutsche Post AG 38536 Meinersen
81047784 6596 02.10.26 10:59
Sendungsnummer: RT 6137 9485 8DE
Einschreiben Einwurf
Information zum Sendungsstatus:
Code bequem mit unserer App scannen
oder Sendungsnummer unter www.deutschepost.de/briefstatus eingeben
Kundenservice Brief 0228 4333112
montags bis freitags von 8 bis 18 Uhr
Vielen Dank für Ihren Besuch. Ihre Deutsche Post AG',
            ],
            [
                'file_path' => 'agenten/workspace/Gesundheit/Nachweise/09_Nachweis_Aerztliche_Stellungnahme_Dr_Schmidt_Arztanfrage.png',
                'sha256' => 'd809b586a41e3b6e0c6db9bbea3417a4f5bf0ee309f0b2b09605c7573f88d3c0',
                'filename' => '09_Nachweis_Aerztliche_Stellungnahme_Dr_Schmidt_Arztanfrage.png',
                'file_type' => 'png',
                'file_size' => 1011946,
                'title' => 'Schriftliche Stellungnahme Dr. med. Christian Schmidt: Verweis auf Krankenkassen-Arztanfrage',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['Arztanfrage', 'Dr. Christian Schmidt', 'Hausarzt', 'Leiferde', 'BKK firmus', 'Telefonat 05.10.2026', 'Ungeklärtes Versicherungsverhältnis'],
                'purpose' => 'Rechtssicherer Zustellnachweis (Einlieferungsbeleg Einschreiben / Postbeleg).',
                'summary' => 'Schriftliche Bestätigung auf offiziellem Praxisvordruck von Hausarzt Dr. med. Christian Schmidt (Gemeinschaftspraxis Leiferde, Wilhelm-Busch-Str. 2, 38542 Leiferde) vom 02.10./05.10.2026. Dr. Schmidt stellt klar: \'Im Falle von Unstimmigkeiten bzgl. der Zusammenhänge von Erkrankungen sollte die Krankenkasse regelkonform bitte hier eine Arztanfrage stellen.\' Am 05.10.2026 um 10:30 Uhr rief Dr. Schmidt persönlich bei der BKK firmus an und wurde von der Kasse mit der Begründung eines \'ungeklärten Versicherungsverhältnisses\' abgewiesen.',
                'key_facts' => [],
                'extracted_date' => '2026-10-05',
                'content_preview' => 'Rezeptvordruck / Ärztliche Stellungnahme: Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin, Wilhelm-Busch-Str. 2, 38542 Leiferde, Tel: 05373 1814. Patientin: Alina Steinhauer, geb. 01.09.1993, Carl-Goerdeler-Ring 26, 38518 Gifhorn. Text: Sehr geehrte Frau Steinhauer, vielen Dank für Ihre Ar',
                'full_text' => 'Rezeptvordruck / Ärztliche Stellungnahme: Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin, Wilhelm-Busch-Str. 2, 38542 Leiferde, Tel: 05373 1814. Patientin: Alina Steinhauer, geb. 01.09.1993, Carl-Goerdeler-Ring 26, 38518 Gifhorn. Text: Sehr geehrte Frau Steinhauer, vielen Dank für Ihre Argumentation. Im Falle von Unstimmigkeiten bzgl. der Zusammenhänge von Erkrankungen sollte die Krankenkasse regelkonform bitte hier eine Arztanfrage stellen. PKVH. Unterzeichnet und gestempelt von Dr. med. Christian Schmidt. Telefonat am 05.10.2026 um 10:30 Uhr: BKK firmus lehnt Gespräch mit Hausarzt ab wegen \'ungeklärtem Versicherungsverhältnis\'.',
            ],
        ];

        AiWorkspaceDocument::query()->delete();

        foreach ($documents as $item) {
            AiWorkspaceDocument::updateOrCreate(
                ['file_path' => $item['file_path']],
                $item
            );
        }
    }
}
