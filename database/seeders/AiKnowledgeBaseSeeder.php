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
            'Rechtliches & AGB' => 'Widerrufsrecht, AGB, Zahlungs- und Lieferbedingungen.',
            'Datenschutz & Infrastruktur' => 'Informationen zur Datenverarbeitung, Server-Hosting und KI.',
            'Bestell- & Produktionsprozess' => 'Konfigurator, Laser-Personalisierung und Toleranzen.',
            'Gamification & 3D-Welt' => '3D-Dashboard, Mini-Spiele, Level-System und Rabattcodes.',
        ];

        $catMap = [];
        foreach ($categories as $name => $desc) {
            $catMap[$name] = AiKnowledgeBaseCategory::firstOrCreate([
                'slug' => Str::slug($name)
            ], [
                'name' => $name,
                // Assuming 'description' exists, if not we ignore it or add it if needed.
                // Mostly name and slug are standard.
            ])->id;
        }

        // 2. Tags definieren und anlegen
        $tags = ['Kontakt', 'Recht', 'Personalisierung', 'Laser', 'Gamification', 'Datenschutz', 'Widerruf', 'Etsy', 'Zahlung', '3D', 'KI-Sicherheit'];
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
            // Artikel 1
            [
                'title' => 'Unternehmensidentität & Kontakt',
                'category' => 'Unternehmen & Kontakt',
                'tags' => ['Kontakt'],
                'content' => "
# Mein-Seelenfunke Identität
**Inhaberin:** Alina Steinhauer
**Anschrift:** Carl-Goerdeler-Ring 26, 38518 Gifhorn
**Gerichtsstand:** Gifhorn

**Offizielle Kontaktmöglichkeiten:**
- E-Mail: kontakt@mein-seelenfunke.de
- Telefon: +49 159 019 668 64
- Webseite: www.mein-seelenfunke.de

**Unternehmensfokus & Brand:**
Mein-Seelenfunke ist eine Manufaktur für hochindividualisierte, personalisierte Produkte. Der Schwerpunkt liegt auf präzisen Lasergravuren auf hochwertigen Naturmaterialien wie Holz und Schiefer. Der Verkauf erfolgt primär über den eigenen Online-Shop (Dashboard) sowie den externen Marktplatz Etsy. Darüber hinaus bedienen wir auch individuelle B2B-Großaufträge.
Der Kundenservice und das System-Management werden durch ein fortschrittliches Netzwerk an KI-Agenten (Funkira, Lumina, Zion, Taron, Rion, Vira) verwaltet, welches eng mit der Inhaberin Alina Steinhauer ('Alina') zusammenarbeitet.
                "
            ],
            // Artikel 2
            [
                'title' => 'Widerrufsrecht bei personalisierten Waren',
                'category' => 'Rechtliches & AGB',
                'tags' => ['Widerruf', 'Recht', 'Personalisierung'],
                'content' => "
# Ausschluss des Widerrufsrechts
Das Widerrufsrecht besteht gesetzlich **grundsätzlich nicht** bei Verträgen zur Lieferung von Waren, die nicht vorgefertigt sind und für deren Herstellung eine individuelle Auswahl oder Bestimmung durch den Verbraucher maßgeblich ist, oder die eindeutig auf die persönlichen Bedürfnisse des Verbrauchers zugeschnitten sind (§ 312g Abs. 2 Nr. 1 BGB).

Da Mein-Seelenfunke Produkte (z. B. auf Basis von Design-Vorlagen mittels Lasergravur) individuell erst nach Bestelleingang fertigt, **erlischt das Widerrufsrecht für diese Bestellungen vollständig**.
Das gilt ausdrücklich auch dann, wenn der Kunde an einer angebotenen Basis-Vorlage keine weiteren Text- oder Bildänderungen vorgenommen hat (da das Produkt dennoch erst auf Auftrag gefertigt wird).

**Zusatz für Standardwaren (Nicht-Personalisiert):**
Ausschließlich für rein nicht-personalisierte Standardwaren gilt das reguläre gesetzliche 14-tägige Widerrufsrecht. Dem Kunden stehen hierfür die Rücksendekosten zu Lasten. Zur leichteren Abwicklung existiert präventiv ein 'Vertrag widerrufen'-Button in der Shop-Navigation.
                "
            ],
            // Artikel 3
            [
                'title' => 'Produktionsprozess, Konfigurator & Toleranzen',
                'category' => 'Bestell- & Produktionsprozess',
                'tags' => ['Personalisierung', 'Laser'],
                'content' => "
# Online-Konfigurator & Laserverfahren

**Vorschau vs. Realität:**
Der auf der Website bereitgestellte Produktkonfigurator dient ausschließlich als visuelle Hilfe zur Positionierung von Texten und Designs. Die Vorschau ist **nicht millimetergenau maßstabsgetreu**. Kleine produktionsbedingte Abweichungen in Ausrichtung und Größe sind normal und stellen keinen Mangel dar.

**Laser-Gravur & Farbdarstellung:**
Im Laserverfahren wird das Material durch Hitze dauerhaft abgetragen und verdunkelt (Gravur). Farbige Kundenbilder oder farbige Logos werden im Produktionsprozess zwingend in materialabhängige **Graustufen bzw. monochrome Kontraststufen** umgewandelt. Farbechte RGB-Wiedergaben (wie am Monitor) sind technisch unmöglich.

**Naturmaterialien (Holz & Schiefer):**
Holz und Schiefer unterliegen natürlichen Schwankungen. Abweichungen in Farbe, Holzmaserung oder typische Asteinschlüsse sind Merkmale eines echten Naturproduktes und berechtigen nicht zur Reklamation.
                "
            ],
            // Artikel 4
            [
                'title' => 'Zahlungsmethoden, Etsy & Versand',
                'category' => 'Bestell- & Produktionsprozess',
                'tags' => ['Zahlung', 'Etsy'],
                'content' => "
# Zahlungen & Abwicklung

**Zahlungsarten im eigenen Shop:**
- Vorkasse (klassische Überweisung)
- Kreditkarte (Visa, Mastercard, American Express)
- Apple Pay / Google Pay
- Amazon Pay / Paypal
- Sofortüberweisung / Klarna
Die Abwicklung digitaler Zahlungen erfolgt DSGVO-konform über **Stripe Payments Europe, Ltd.** Wir selbst speichern keine sensiblen Kreditkartendaten ab.

**Bestellungen über Etsy:**
Wird über unseren angeschlossenen Etsy-Shop bestellt, greifen vorrangig die AGB von Etsy. Die Bezahlung erfolgt dort zwingend über *Etsy Payments*.

**Versandlogistik:**
Die Auslieferung der Bestellungen in Deutschland erfolgt durch die **Deutsche Post AG** sowie die **DHL Paket GmbH**.
                "
            ],
            // Artikel 5
            [
                'title' => 'Gamification, 3D Dashboard & Seelenfunken',
                'category' => 'Gamification & 3D-Welt',
                'tags' => ['Gamification', '3D'],
                'content' => "
# Interaktiver Kundenbereich & Spiele

Mein-Seelenfunke bietet ein innovatives, 3D-beschleunigtes Kunden-Dashboard (Three.js basiert). Nach dem Login haben Nutzer die völlig freiwillige Möglichkeit, mit 3D-Begleitern zu interagieren und plattforminterne Mini-Spiele (wie *Kristall-Kollaps 3D*) zu absolvieren.

**Das Bonusprogramm (Seelenfunken):**
- **Punkte-System:** Kunden sammeln durch Interaktion virtuelle Erfahrungspunkte ('Seelenfunken').
- **Belohnungen:** Beim Erreichen von Level-Meilensteinen vergibt das System echte **Rabattcodes** für Käufe im Laser-Shop. Diese Rabattcodes sind streng Account-gebunden, nur einmalig pro User nutzbar und nicht übertragbar.
- **Wirtschaftliche Richtlinie:** Virtuelle Punkte stellen keine reale Währung dar und können unter keinen Umständen in Bargeld ausgezahlt werden.
- **Anti-Cheat:** Die Nutzung von automatisierten Skripten, Bots oder das Ausnutzen von Bugs führt zum permanenten Sperren des Programms und dem Verlust aller Prämien für den betroffenen Nutzer. Der Rechtsweg ist bei Prämien ausgeschlossen.
                "
            ],
            // Artikel 6
            [
                'title' => 'Datenschutz, Server-Hosting & souveräne KI',
                'category' => 'Datenschutz & Infrastruktur',
                'tags' => ['Datenschutz', 'Recht', 'KI-Sicherheit'],
                'content' => "
# Strikter Datenschutz & Mittwald-Hosting

Ein absolutes Alleinstellungsmerkmal von Mein-Seelenfunke ist der souveräne Umgang mit Nutzerdaten.
Sowohl die Kern-Website, die Datenbanken als auch **alle Künstlichen Intelligenz (KI)-Sprachmodelle laufen autark auf dedizierten Servern in Deutschland** bei der *Mittwald CM Service GmbH & Co. KG*.

**KI-Datenschutz (Garantiert):**
- **Kein Datenabfluss in die USA:** Es existieren **keine** API-Verbindungen zu OpenAI (ChatGPT), Anthropic oder Google.
- Die KI verarbeitet Support-Tickets und Anfragen rein lokal und flüchtig auf den deutschen Systemen und garantiert so 100% DSGVO-Konformität.
- Nutzerdaten werden niemals dazu verwendet, große externe, öffentliche KI-Modelle zu trainieren.

**Cookies & Drittanbieter:**
Optionale Cookies werden nur nach aktivem Consent gesetzt. Soziale Medien wie Meta (Instagram) und TikTok sammeln Daten auf ihren eigenen Portalen, hierauf hat Mein-Seelenfunke keinen tieferen technischen Einfluss, wertet dort aber statistische Insights aus.
                "
            ]
        ];

        foreach ($articles as $art) {
            $kb = AiKnowledgeBase::updateOrCreate([
                'title' => $art['title']
            ], [
                'slug' => Str::slug($art['title']) . '-' . rand(100, 999), // Prevent collisions
                'ai_knowledge_base_category_id' => $catMap[$art['category']],
                'content' => trim($art['content']),
                'is_published' => true
            ]);

            // Ensure Tags are assigned
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
