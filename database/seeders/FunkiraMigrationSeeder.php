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
     * Run the database seeds for Funkira knowledge, dossier, workspace documents and tools.
     */
    public function run(): void
    {
        $this->command->info('=== STARTE FUNKIRA MIGRATION SEEDER ===');

        // 1. Kategorien anlegen
        $categories = [
            'Gesundheit & Sozialrecht (BKK firmus)' => 'Rechtliche & medizinische Verfahrensakten, Krankengeld und GA-OP Chronologie.',
            'Existenzgründung & Arbeitsagentur'     => 'Gründungszuschuss § 93 SGB III, Businessplan, Fachkundige Stelle und 150-Tage-Regel.',
            'Finanz-Audit & Leistungsansprüche'     => 'Anspruchsvergleiche (ALG 1 vs. KG) und finanzielle Lückenanalyse.',
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
            'Sozialgericht', 'Eilantrag § 86b', 'Gründungszuschuss',
            'Businessplan', 'Liquiditätsplan', 'Tragfähigkeitsbescheinigung',
            'Agentur für Arbeit', 'Finanz-Audit', 'Workspace', 'Dokumente', 'Existenzgründung'
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
                'title' => 'BKK firmus Verfahren & Vollständige 25-Schritte-Chronologie',
                'category' => 'Gesundheit & Sozialrecht (BKK firmus)',
                'tags' => ['BKK firmus', 'Krankengeld', 'GA-OP', 'Widerspruch', 'eAU', 'Sozialgericht', 'Eilantrag § 86b'],
                'content' => <<<MD
# Master-Dossier: BKK firmus Verfahren & 25-Schritte-Chronologie

### 1. Verfahrensstatus & Aktenzeichen
* **Betroffene:** Alina Steinhauer
* **Krankenkasse:** BKK firmus, Gotenstraße 15, 28199 Bremen
* **Kernkonflikt:** Rechtswidrige Einstellung des Krankengeldes zum 30.07.2024 sowie 7-monatige Verzögerung des Antrags auf geschlechtsangleichende Operation (GA-OP).
* **Fristüberwachung:** Fristablauf BKK firmus Widerspruchsbescheid / Klagefrist: **08.10.2026**.

### 2. Rechtliche Kernargumentation
* **§ 44 Abs. 1 SGB V:** Anspruch auf Krankengeld bei lückenlos nachgewiesener Arbeitsunfähigkeit (eAU).
* **§ 47b Abs. 1 Satz 2 SGB V:** Das Krankengeld bemisst sich nach dem bisher bezogenen Arbeitslosengeld I (Regelentgelt 65,90 € kalendertäglich).
* **§ 86b Abs. 2 SGG:** Einstweiliger Rechtsschutz / Eilantrag beim Sozialgericht bei existenzieller Notlage und unverschuldeter Krankengeldlücke.
* **Gutachten nach Aktenlage (MD):** Der Medizinische Dienst hat ohne persönliche Untersuchung eine angebliche "Wiederherstellung der Erwerbsfähigkeit" bescheinigt, obwohl Facharztatteste die durchgehende Verhandlungs- und Arbeitsunfähigkeit belegen.
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
* **Unternehmen:** Mein Seelenfunke (Personalisierte Lasergravuren & Manufaktur)
* **150-Tage-Restanspruch:** Für den Gründungszuschuss nach § 93 Abs. 1 Satz 1 Nr. 1 SGB III muss am Tag der Gründung noch ein Restanspruch auf ALG 1 von **mindestens 150 Tagen** bestehen.
* **Ansprechpartnerin Arbeitsagentur:** Frau Grandke (AfA).

### 2. Erforderliche Antragsdokumente
1. **Businessplan Mein Seelenfunke** (Stand 15.08.2026 / aktualisiert 2026)
2. **Liquiditäts- und Rentabilitätsplan** für die ersten 3 Geschäftsjahre
3. **Tragfähigkeitsbescheinigung der fachkundigen Stelle:** Einreichung des Businessplans bei der Steuerberaterin zur Bestätigung der Tragfähigkeit.
4. **Vordruck Bundesagentur für Arbeit:** "Stellungnahme der fachkundigen Stelle zur Tragfähigkeit der Existenzgründung".
MD
            ],
            [
                'slug' => 'finanz-audit-leistungsansprueche-luecke',
                'title' => 'Finanz-Audit: Offene Krankengeld-Ansprüche & 2.601,86 € Differenz',
                'category' => 'Finanz-Audit & Leistungsansprüche',
                'tags' => ['Finanz-Audit', 'Krankengeld', 'BKK firmus', 'Agentur für Arbeit'],
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
   - `Bank/` (Bankauszüge, Kontoumsätze)
   - `Steuern/` (Finanzamt, Steuererklärung, Gewerbesteuer)
   - `Finanzen/` (Rechnungen, Mahnungen, Verträge)
   - `Berichte/` (Systemanalysen und Berichte)
   - `Allgemein/` (Allgemeine Dokumente, Erinnerungen)

3. `Gesundheit/`
   - `Krankenkasse/` (BKK firmus Briefe, Digitaler Briefkasten, Chats)
   - `Krankengeld/` (Widersprüche, Berechnungen, Bescheide)
   - `Klinik/` (Dr. Lubos Kliniken, Operationsberichte, Liegebescheinigungen)
   - `Atteste/` (Hausarzt-Atteste, Befunde, MDK-Gutachten)
   - `Nachweise/` (Einlieferungsbelege, Einschreiben, Fotodokumentation)

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
        $this->command->info('✓ 4 Master-Dossiers in AiKnowledgeBase angelegt.');

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
                "[OFFIZIELLES EXPERTEN-WISSEN: BKK FIRMUS VERFAHREN & SOZIALRECHT]\n" .
                "- Du bist Alinas führender Spezial-Agent für das BKK firmus Verfahren und medizinisches Sozialrecht.\n" .
                "- Du kennst die vollständige 25-Schritte-Chronologie des BKK firmus Verfahrens (MD-Gutachten nach Aktenlage, GA-Großoperation, lückenlose eAU, § 44 / § 47b SGB V, Eilantrag § 86b SGG).\n" .
                "- Wichtigste Frist: 08.10.2026 für den BKK firmus Krankengeld-Widerspruchsbescheid.\n" .
                "- Akuter Verfahrensstand: BKK firmus hat die Akte an den Medizinischen Dienst (MD) weitergeleitet.\n" .
                "- Alle Gesundheitsdokumente liegen im Workspace geordnet unter `agenten/workspace/Gesundheit/` (Krankenkasse, Krankengeld, Klinik, Atteste, Nachweise).\n" .
                "- Nutze 'workspace_find_documents' und 'health_read_document', um Atteste, Gutachten und Widersprüche im privaten Workspace jederzeit im Volltext zu analysieren.";
            $drFunki->save();
            $this->command->info('✓ Dr. Funki Agenten-Prompt geschärft.');
        }

        $buchi = AiAgent::where('name', 'Buchi')->first();
        if ($buchi) {
            $buchi->system_prompt = 
                "[OFFIZIELLES EXPERTEN-WISSEN: FINANZ-AUDIT & GRÜNDUNGSZUSCHUSS]\n" .
                "- Du bist Alinas Finanz- und Buchhaltungs-Agent für das Finanz-Audit und die Existenzgründung.\n" .
                "- Du kennst die exakte finanzielle Lücke von 2.601,86 € aus der unberechtigten Krankengeldeinstellung August/September 2024.\n" .
                "- Du unterstützt die Liquiditätsplanung für den Gründungszuschuss (§ 93 SGB III) zur Vorlage bei der Steuerberaterin (Tragfähigkeitsbescheinigung) und bei Frau Grandke (Arbeitsamt).\n" .
                "- Alle Berufs- und Finanzdokumente liegen im Workspace geordnet unter `agenten/workspace/Berufsleben/` (Existenzgruendung, Arbeitsamt, Projekte) sowie `agenten/workspace/Dokumente/` (Bank, Steuern, Finanzen).\n" .
                "- Nutze 'workspace_find_documents', um Verträge, BWA und Liquiditätspläne jederzeit abzurufen.";
            $buchi->save();
            $this->command->info('✓ Buchi Agenten-Prompt geschärft.');
        }

        $funkira = AiAgent::where('name', 'Funkira')->first();
        if ($funkira) {
            $funkira->system_prompt = 
                "[OFFIZIELLES EXPERTEN-WISSEN: BKK FIRMUS, ARBEITSAMT & WORKSPACE]\n" .
                "- Du hast vollen Zugriff auf das Gesamtsystem, die Wissensdatenbank und den privaten Workspace (`storage/app/private/agenten/workspace`).\n" .
                "- DER WORKSPACE IST IN 3 KLARE HAUPTORDNER MIT EINZEILIGEN UNTERORDNERN STRUKTURIERT:\n" .
                "  1. `Berufsleben/` (Existenzgruendung, Arbeitsamt, Projekte)\n" .
                "  2. `Dokumente/` (Bank, Steuern, Finanzen, Berichte, Snapshots, Allgemein)\n" .
                "  3. `Gesundheit/` (Krankenkasse, Krankengeld, Klinik, Atteste, Nachweise)\n" .
                "- DATEITRICHTER-FÄHIGKEIT: Du verfügst über das Werkzeug 'workspace_run_dateitrichter'. Sobald Alina oder der Nutzer dich bittet, die Dateistruktur im Workspace aufzuräumen, neue Dateien einzusortieren oder Ordnung zu schaffen, rufst du direkt 'workspace_run_dateitrichter' auf. Anschließend berichtest du präzise, welche Dateien in welchen Hauptordner und einteiligen Unterordner verschoben und in der Knowledge Base verankert wurden.\n" .
                "- BKK FIRMUS & VERFAHREN: Du kennst die 25-Schritte-Chronologie des BKK firmus Verfahrens (lückenlose eAU, § 44 / § 47b SGB V, GA-Großoperation, Eilantrag § 86b SGG beim Sozialgericht). Fristablauf: 08.10.2026. BKK hat Unterlagen an den Medizinischen Dienst (MD) weitergeleitet.\n" .
                "- EXISTENZGRÜNDUNG & ARBEITSAMT: Gründungszuschuss (§ 93 SGB III), 150-Tage-Restanspruch auf ALG 1, Vorlage von Businessplan und Liquiditätsplan bei der Steuerberaterin für die Tragfähigkeitsbescheinigung, Antragstellung bei Frau Grandke (Agentur für Arbeit).\n" .
                "- BLITZSCHNELLE SUCHE: Nutze `brain_search` oder `workspace_find_documents`. Bei Mehrfachfragen liefert `brain_search` sofort alle passenden Dossiers und Workspace-Dokumente.";
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
                'title' => 'Businessplan & Liquiditätsplan an Steuerberaterin übergeben',
                'priority' => 'urgent',
                'plan' => 'Übergabe des fertigen Businessplans und des Liquiditätsplans an die Steuerberaterin zur Ausstellung der fachkundigen Tragfähigkeitsbescheinigung.',
            ],
            [
                'title' => '150 Tage Restanspruch sichern & Gründungsdatum festlegen',
                'priority' => 'urgent',
                'plan' => 'Genaue Prüfung des ALG 1 Restanspruchs vor Gründungsbeginn, damit die gesetzliche 150-Tage-Grenze nach § 93 SGB III eingehalten wird.',
            ],
            [
                'title' => 'Antrag auf Gründungszuschuss bei Frau Grandke (AfA) einreichen',
                'priority' => 'high',
                'plan' => 'Sobald die Tragfähigkeitsbescheinigung der Steuerberaterin vorliegt, den formellen Antrag auf Gründungszuschuss bei Frau Grandke bei der Agentur für Arbeit einreichen.',
            ],
            [
                'title' => 'Fristüberwachung Krankengeld BKK firmus (Fristablauf 08.10.2026)',
                'priority' => 'urgent',
                'plan' => 'Überwachung des Fristablaufs zum 08.10.2026 für den Widerspruchsbescheid der BKK firmus und Vorbereitung des Eilantrags gem. § 86b SGG beim Sozialgericht.',
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
