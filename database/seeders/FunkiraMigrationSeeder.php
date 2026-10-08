<?php

namespace Database\Seeders;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiTool;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiWorkspaceDocument;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FunkiraMigrationSeeder extends Seeder
{
    /**
     * Run the database seeds for Funkira knowledge, dossiers, workspace documents, agent prompts and tasks.
     */
    public function run(): void
    {
        $this->command->info('=== STARTE FUNKIRA MIGRATION SEEDER ===');

        // 1. Kategorien anlegen
        $categories = [
            'Gesundheit & Sozialrecht (BKK firmus)' => 'Rechtliche & medizinische Verfahrensakten, Krankengeld, GA-OP und Sozialgerichtsverfahren.',
            'Existenzgründung & Arbeitsagentur'     => 'Gründungszuschuss § 93 SGB III, Businessplan, Fachkundige Stelle und 150-Tage-Regel.',
            'Finanz-Audit & Leistungsansprüche'     => 'Anspruchsvergleiche (ALG 1 vs. KG), Kontostände und Liquiditätsanalysen.',
            'Workspace-Dokumentenkatalog'           => 'Master-Inventar aller vertraulichen Dokumente im privaten Workspace.',
        ];

        $catMap = [];
        foreach ($categories as $name => $desc) {
            $catMap[$name] = AiKnowledgeBaseCategory::firstOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            )->id;
        }

        // 2. Tags anlegen
        $tagNames = [
            'BKK firmus', 'Krankengeld', 'GA-OP', 'Widerspruch', 'eAU',
            'Sozialgericht', 'Eilantrag § 86b', 'Gründungszuschuss', 'Mein Justizpostfach', 'BundID',
            'Businessplan', 'Liquiditätsplan', 'Tragfähigkeitsbescheinigung',
            'Agentur für Arbeit', 'Finanz-Audit', 'Volksbank eG', 'Workspace', 'Dokumente', 'Existenzgründung'
        ];
        $tagMap = [];
        foreach ($tagNames as $t) {
            $tagMap[$t] = AiKnowledgeBaseTag::firstOrCreate(
                ['name' => $t],
                ['slug' => Str::slug($t)]
            )->id;
        }

        // 3. Master-Dossiers anlegen / aktualisieren
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
* **Kernkonflikt:** Rechtswidrige Ablehnung von Krankengeld nach schwerer stationärer geschlechtsangleichender Operation (GA-OP) per Bescheid vom 01.10.2026.
* **Widerspruch:** Fristgerecht eingereicht am 01.10.2026 per Einwurf-Einschreiben (Sendungsnummer: RT613794858DE, Zustellung am 02.10.2026) mit Fristsetzung zur Zahlung bis 08.10.2026.
* **Aktuelle Reaktion der Kasse:** Am 07.10.2026 teilte die BKK firmus mit, die Unterlagen erst jetzt an den Medizinischen Dienst (MD) zur Begutachtung weitergeleitet zu haben.

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
                'title' => 'Finanz-Audit: Offene Krankengeld-Ansprüche & 2.601,86 € Differenz',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanz-Audit', 'Krankengeld', 'BKK firmus', 'Agentur für Arbeit', 'Volksbank eG'],
                'content' => <<<MD
# Master-Dossier: Finanz-Audit & Leistungsansprüche

### 1. Tagessätze & Anspruchskalkulation
* **Arbeitslosengeld I (AfA):** Kalendertäglicher Leistungsbetrag: **60,21 €** (brutto = netto).
* **Krankengeld (BKK firmus):** Maßgebliches Regelentgelt gem. § 47b SGB V: **65,90 €** kalendertäglich.

### 2. Nachforderung & Finanzlücke August / September 2024
Durch die abrupte Einstellung des Krankengeldes entstand eine existenzbedrohende Lücke von **2.601,86 €**.
MD
            ],
            [
                'slug' => 'master-dokumenten-katalog-workspace',
                'title' => 'Master-Dokumentenkatalog: Wo liegen Dokumente & Wofür sind sie da',
                'category' => 'Workspace-Dokumentenkatalog',
                'tags' => ['Workspace', 'Dokumente', 'BKK firmus', 'Existenzgründung'],
                'content' => <<<MD
# Master-Dokumentenkatalog des privaten Workspaces

Alle Dokumente sind revisionssicher unter `storage/app/private/agenten/workspace/` in einer strengen 3-Säulen-Struktur mit rein einteiligen Ordnernamen strukturiert:

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

        foreach ($dossiers as $d) {
            $kb = AiKnowledgeBase::updateOrCreate(
                ['slug' => $d['slug']],
                [
                    'title' => $d['title'],
                    'ai_knowledge_base_category_id' => $catMap[$d['category']],
                    'content' => $d['content'],
                    'is_published' => true,
                ]
            );

            $syncIds = array_values(array_filter(array_map(fn($t) => $tagMap[$t] ?? null, $d['tags'])));
            $kb->tags()->sync($syncIds);
        }
        $this->command->info('✓ 5 Master-Dossiers in AiKnowledgeBase angelegt.');

        // 4. Dokumentenkatalog aus JSON einpflegen (ai_workspace_documents)
        $jsonPath = __DIR__ . '/data/workspace_documents.json';
        if (!file_exists($jsonPath)) {
            $jsonPath = storage_path('app/workspace_documents.json');
        }

        if (file_exists($jsonPath)) {
            $docData = json_decode(file_get_contents($jsonPath), true);
            if (is_array($docData)) {
                AiWorkspaceDocument::query()->delete();
                $imported = 0;
                foreach ($docData as $item) {
                    $extractedDate = !empty($item['extracted_date']) ? substr($item['extracted_date'], 0, 10) : null;
                    if ($extractedDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $extractedDate)) {
                        $extractedDate = null;
                    }

                    AiWorkspaceDocument::updateOrCreate(
                        ['file_path' => $item['file_path']],
                        [
                            'sha256' => $item['sha256'] ?? null,
                            'filename' => $item['filename'],
                            'file_type' => $item['file_type'] ?? pathinfo($item['filename'], PATHINFO_EXTENSION),
                            'file_size' => (int)($item['file_size'] ?? 0),
                            'title' => $item['title'] ?? pathinfo($item['filename'], PATHINFO_FILENAME),
                            'category' => $item['category'] ?? 'Allgemein',
                            'tags' => is_array($item['tags'] ?? null) ? $item['tags'] : [],
                            'purpose' => $item['purpose'] ?? 'Dokumentiertes Arbeits- und Vorgangsdokument.',
                            'summary' => $item['summary'] ?? '',
                            'key_facts' => is_array($item['key_facts'] ?? null) ? $item['key_facts'] : [],
                            'extracted_date' => $extractedDate,
                            'content_preview' => $item['content_preview'] ?? null,
                            'full_text' => $item['full_text'] ?? null,
                        ]
                    );
                    $imported++;
                }
                $this->command->info("✓ {$imported} Dokumente in `ai_workspace_documents` registriert.");
            }
        }

        // 5. AI Tools registrieren & verknüpfen
        $toolFind = AiTool::firstOrCreate(
            ['identifier' => 'workspace_find_documents'],
            [
                'name' => 'Workspace Find Documents',
                'description' => 'Sucht in der Datenbank nach registrierten Dokumenten im privaten Workspace anhand von Suchbegriff, Kategorie oder Zweck (wofür sie da sind). Gibt Metadaten, Pfad, Zweck und Relevanz zurück.'
            ]
        );

        $toolInfo = AiTool::firstOrCreate(
            ['identifier' => 'workspace_get_document_info'],
            [
                'name' => 'Workspace Get Document Info',
                'description' => 'Liefert detaillierte Informationen zu einem bestimmten Dokument im Workspace: Wo es genau liegt, wofür es da ist (Zweck), Zusammenfassung, Eckdaten und Auszug.'
            ]
        );

        $toolDateitrichter = AiTool::firstOrCreate(
            ['identifier' => 'workspace_run_dateitrichter'],
            [
                'name' => 'Workspace Dateitrichter',
                'description' => 'Aktiviert den intelligenten Dateitrichter im KI-Workspace. Analysiert alle lose im Workspace abgelegten oder unsortierten Dateien, klassifiziert sie logisch in die 3 Hauptordner (Berufsleben, Dokumente, Gesundheit) mit einteiligen Unterordnern, verschiebt sie dorthin, extrahiert Metadaten und verankert das Wissen sofort in der Knowledge Base.'
            ]
        );

        foreach (AiRole::all() as $role) {
            if (!$role->tools()->where('ai_tool_id', $toolFind->id)->exists()) {
                $role->tools()->attach($toolFind->id);
            }
            if (!$role->tools()->where('ai_tool_id', $toolInfo->id)->exists()) {
                $role->tools()->attach($toolInfo->id);
            }
            if (!$role->tools()->where('ai_tool_id', $toolDateitrichter->id)->exists()) {
                $role->tools()->attach($toolDateitrichter->id);
            }
        }
        $this->command->info('✓ AI-Tools registriert und Rollen zugewiesen.');

        // 6. Agenten-Prompts schärfen (Dr. Funki, Buchi, Funkira)
        $drFunki = AiAgent::where('name', 'Dr. Funki')->first();
        if ($drFunki) {
            $drFunki->system_prompt = 
                "[OFFIZIELLES EXPERTEN-WISSEN: BKK FIRMUS VERFAHREN, GA-OP & MEDIZINISCHES SOZIALRECHT]\n" .
                "- Du bist Alinas führender Spezial-Agent für das BKK firmus Verfahren und medizinisches Sozialrecht.\n" .
                "- MEDIZINISCHER VERLAUF: Stationäre geschlechtsangleichende OP vom 22.06.–12.07.2026 bei Dr. Lubos Kliniken Bogenhausen München. Poststationäre AU bis 19.07.2026.\n" .
                "- LÜCKENLOSE eAU: Nahtlose Folgekrankschreibungen ab 13.07., 17.07., 07.08. und 03.09.2026 durch die Hausarztpraxis Leiferde (Frau Dorothea Jung / Dr. Schmidt). Alle eAUs liegen der BKK firmus elektronisch vor und wurden in der App quittiert.\n" .
                "- ZWICKMÜHLE DER ÄRZTE:\n" .
                "  1. Praxis Jung (Leiferde): Schreibt Alina per eAU weiter krank, unterschreibt jedoch aus Angst vor rechtlichen Konsequenzen und Kassenregress keine gesonderte Kausalitätsbescheinigung.\n" .
                "  2. Dr. Lubos Kliniken (München): Schreiben von Sören Kopmann (08.10.2026) stellt klar: Keine nachträgliche Codierungsänderung, aber volle Freigabe aller Befunde und Akten für das Sozialgericht und den MD.\n" .
                "- EILANTRAG SOZIALGERICHT: Eilantrag nach § 86b Abs. 2 SGG beim Sozialgericht Braunschweig (Wilhelmstraße 55, Fax 05141 5937-31600) vorbereitet. Einreichung erfolgt via BundID über 'Mein Justizpostfach' (MJP).\n" .
                "- Alle Gesundheitsakten liegen unter `storage/app/private/agenten/workspace/Gesundheit/` (Krankenkasse, Krankengeld, Klinik, Atteste, Nachweise, Krankschreibungen).";
            $drFunki->save();
            $this->command->info('✓ Dr. Funki Agenten-Prompt geschärft.');
        }

        $buchi = AiAgent::where('name', 'Buchi')->first();
        if ($buchi) {
            $buchi->system_prompt = 
                "[OFFIZIELLES EXPERTEN-WISSEN: FINANZ-AUDIT, VOLKSBANK eG & EXISTENZGRÜNDUNG]\n" .
                "- Du bist Alinas Finanz- und Buchhaltungs-Agent für das Finanz-Audit, die Existenzgründung und die Budgetüberwachung.\n" .
                "- KONTOSTAND & BANK: Girokonto bei der **Volksbank eG** (IBAN DE85 2699 1066 8583 1960 00). Guthaben per 08.10.2026: nur noch **1.519,09 €**.\n" .
                "- FIXKOSTEN-BEDARF: Monatlich ca. **1.600 €** unabweisbare Ausgaben:\n" .
                "  * **546,00 € Hauskredit** (Volksbank eG – für das selbstbewohnte Eigenheim zur Abwendung von Kündigung und Zwangsversteigerung; keine Mietwohnung!).\n" .
                "  * **355,00 € gesetzlicher Kindesunterhalt** für den minderjährigen Sohn Noah (Sohn lebt bei Alinas Ex-Partnerin).\n" .
                "  * Lebensunterhalt, Energie, Grundversorgung.\n" .
                "- EXISTENZGRÜNDUNG 'MEIN SEELENFUNKE': Start geplant zum 01.11.2026. Gründungszuschuss (§ 93 SGB III) erfordert mindestens 150 Tage Restanspruch auf ALG 1. Eine Zwangsmeldung beim Arbeitsamt statt Krankengeld würde diesen Restanspruch zerstören!\n" .
                "- Nutze 'workspace_find_documents', um Verträge, BWA und Liquiditätspläne jederzeit abzurufen.";
            $buchi->save();
            $this->command->info('✓ Buchi Agenten-Prompt geschärft.');
        }

        $funkira = AiAgent::where('name', 'Funkira')->first();
        if ($funkira) {
            $funkira->system_prompt = 
                "[OFFIZIELLES EXPERTEN-WISSEN: BKK FIRMUS, SOZIALGERICHT, BUNDID & WORKSPACE]\n" .
                "- Du bist Funkira, die System-Root- und CEO-KI von Seelenfunke. Du hast vollen Zugriff auf das Gesamtsystem, die Wissensdatenbank und den privaten Workspace (`storage/app/private/agenten/workspace`).\n" .
                "- WORKSPACE-STRUKTUR (3 Hauptordner mit einteiligen Unterordnern):\n" .
                "  1. `Berufsleben/` (Existenzgruendung, Arbeitsamt, Projekte)\n" .
                "  2. `Dokumente/` (Bank, Steuern, Finanzen, Berichte, Allgemein)\n" .
                "  3. `Gesundheit/` (Krankenkasse, Krankengeld, Klinik, Atteste, Nachweise, Krankschreibungen)\n" .
                "- SOZIALGERICHTSVERFAHREN & BUNDID-STATUS (Stand 08.10.2026):\n" .
                "  * Der Eilantrag nach § 86b Abs. 2 SGG (`00_EILANTRAG_Sozialgericht_Braunschweig_Alina_Steinhauer.pdf`) gegen die BKK firmus (Gottlieb-Daimler-Str. 11, Bremen) ist komplett fertig vorbereitet.\n" .
                "  * Gericht: Sozialgericht Braunschweig, Wilhelmstraße 55, 38100 Braunschweig (Rechtssachen-Fax: 05141 / 5937-31600, EGVP-ID: `govello-1272982110140-000216760`).\n" .
                "  * WICHTIGER STATUS: Alina wartet aktuell auf ihren **PIN-Rücksetzbrief für den Online-Ausweis (eID / BundID)**. Sobald der Brief eintrifft, reicht sie den Antrag kostenlos und papierlos über **'Mein Justizpostfach' (MJP)** unter `mein-justizpostfach.bund.de` direkt beim Sozialgericht ein. Das spart teure Druckkosten und wahrt die Schriftform nach § 65a Abs. 3 SGG.\n" .
                "  * Finanzen: Konto bei **Volksbank eG** (1.519,09 € Rest), monatliche Fixkosten 1.600 € (546 € Hauskredit Eigenheim, 355 € Unterhalt für Sohn Noah bei Ex-Partnerin).\n" .
                "  * Ärzte-Zwickmühle: Frau Jung (Leiferde) schreibt per eAU weiter krank, unterschreibt aber kein Gutachten aus Regressangst. Dr. Lubos Kliniken (Schreiben Kopmann 08.10.2026) stellt keine nachträglichen Codierungen aus, gibt aber Befunde für das Sozialgericht frei.\n" .
                "- DATEITRICHTER-FÄHIGKEIT: Du verfügst über das Werkzeug 'workspace_run_dateitrichter'. Sobald Alina oder der Nutzer dich bittet, die Dateistruktur aufzuräumen oder neue Dokumente einzusortieren, rufst du direkt 'workspace_run_dateitrichter' auf und verankerst die Infos in der Knowledge Base.\n" .
                "- SUCHE: Nutze `brain_search` oder `workspace_find_documents` für schnellen Zugriff auf alle Master-Dossiers.";
            $funkira->save();
            $this->command->info('✓ Funkira Agenten-Prompt geschärft.');
        }

        // 7. Management-Tasks anlegen
        $taskList = ManagementTaskList::firstOrCreate(
            ['name' => 'Existenzgründung & Behörden'],
            ['color' => '#10b981', 'position' => 1]
        );

        $tasks = [
            [
                'title' => 'PIN-Rücksetzbrief für Online-Ausweis abwarten & BundID aktivieren',
                'priority' => 'urgent',
                'plan' => 'Sobald der PIN-Rücksetzbrief der Bundesdruckerei / Bürgeramt per Post eintrifft, den Online-Ausweis über die AusweisApp freischalten, um den kostenlosen Zugang zu "Mein Justizpostfach" (MJP) freizuschalten.',
            ],
            [
                'title' => 'Eilantrag nach § 86b SGG über Mein Justizpostfach (MJP) beim SG Braunschweig einreichen',
                'priority' => 'urgent',
                'plan' => 'Nach BundID-Aktivierung über mein-justizpostfach.bund.de den Eilantrag (00_EILANTRAG...) und die Anlagen K01 bis K08 digital und papierlos an das Sozialgericht Braunschweig (EGVP-ID: govello-1272982110140-000216760) senden.',
            ],
            [
                'title' => 'Businessplan & Liquiditätsplan an Steuerberaterin übergeben',
                'priority' => 'high',
                'plan' => 'Übergabe des fertigen Businessplans und des Liquiditätsplans an die Steuerberaterin zur Ausstellung der fachkundigen Tragfähigkeitsbescheinigung.',
            ],
            [
                'title' => '150 Tage Restanspruch sichern & Gründungszuschuss bei Frau Grandke (AfA) beantragen',
                'priority' => 'high',
                'plan' => 'Genaue Überwachung der 150-Tage-Grenze beim ALG 1 vor Gründungsbeginn (01.11.2026) und formelle Antragstellung bei Frau Grandke bei der Agentur für Arbeit Gifhorn.',
            ]
        ];

        foreach ($tasks as $t) {
            ManagementTask::updateOrCreate(
                ['title' => $t['title'], 'task_list_id' => $taskList->id],
                [
                    'priority' => $t['priority'],
                    'ai_plan' => $t['plan'],
                    'is_completed' => false,
                ]
            );
        }
        $this->command->info('✓ Management-Tasks angelegt.');

        $this->command->info('=== FUNKIRA MIGRATION SEEDER ERFOLGREICH DURCHGELAUFEN ===');
    }
}
