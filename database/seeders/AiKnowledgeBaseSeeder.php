<?php

namespace Database\Seeders;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiKnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Generates comprehensive AI knowledge base from Mein-Seelenfunke company policies.
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
        ];

        $catMap = [];
        foreach ($categories as $name => $desc) {
            $catMap[$name] = AiKnowledgeBaseCategory::firstOrCreate([
                'slug' => Str::slug($name)
            ], [
                'name' => $name,
            ])->id;
        }

        // 2. Tags definieren und anlegen
        $tags = ['Kontakt', 'Recht', 'Personalisierung', 'Laser', 'Gamification', 'Datenschutz', 'Widerruf', 'Etsy', 'Zahlung', '3D', 'KI-Sicherheit', 'Support', 'Ticket', 'Reklamation', 'Versand', 'Retoure', 'B2B', 'Produkte', 'Buchhaltung', 'Steuern', 'Sonderausgaben', 'Export'];
        $tagMap = [];
        foreach ($tags as $tagName) {
            $tagMap[$tagName] = AiKnowledgeBaseTag::firstOrCreate([
                'slug' => Str::slug($tagName)
            ], [
                'name' => $tagName,
            ])->id;
        }

        // 3. Artikelstruktur (Knowledge Base Entries)
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
            ]
        ];

        foreach ($articles as $art) {
            $kb = AiKnowledgeBase::updateOrCreate([
                'title' => $art['title']
            ], [
                'slug' => Str::slug($art['title']) . '-' . rand(100, 999),
                'ai_knowledge_base_category_id' => $catMap[$art['category']],
                'content' => trim($art['content']),
                'is_published' => true
            ]);

            $syncTags = [];
            foreach ($art['tags'] as $t) {
                if (isset($tagMap[$t])) {
                    $syncTags[] = $tagMap[$t];
                }
            }
            $kb->tags()->sync($syncTags);
        }
    }
}
