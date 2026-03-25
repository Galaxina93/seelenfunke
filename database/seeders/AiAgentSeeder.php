<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure directory exists
        $avatarDir = storage_path('app/public/agents/avatars');
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        // Create Default AiRoles
        $rolesData = [
            'Teamleiter' => 'Zuständig für Systemsteuerung, Aufgabenverteilung, Prozessüberwachung und übergeordnete strategische Entscheidungen über alle Agenten hinweg.',
            'Supporter' => 'Die empathische Schnittstelle zum Ticketsystem für Kundenbetreuung, Konfliktdeeskalation und den gezielten Einsatz der Support-Dokumentation.',
            'Marketing' => 'Kreativer Experte für Konzeption und Erstellung von Newslettern, Marketingkampagnen, Blogartikeln und SEO-optimierten Texten.',
            'Sales' => 'Operative Instanz für das gesamte Shop-Bestellwesen, Fulfillment, Logistiküberwachung, Angebotserstellung im B2B-Umfeld und Reklamationsabwicklung.',
            'Finanzmanager' => 'Zahlenbasierte und akribische Instanz für buchhalterische Auswertungen, Rechnungsprüfungen, Steuer-Prozesse, Kostenanalysen und Liquiditätsberechnungen.',
            'Analyst' => 'Datenspezialist für Produktrecherche, detaillierte Nischenanalysen, das Crawlen von Verkaufsplattformen und die Auswertung von Markttrends.',
            'Hausarzt' => 'Gesundheitlicher Experte für Diagnosen, medizinische Analysen von Fremddokumenten, Web-Recherchen zu Symptomen und das Erstellen von Behandlungsplänen.'
        ];

        $rolesMap = [];
        foreach ($rolesData as $name => $desc) {
            $rolesMap[$name] = AiRole::updateOrCreate(
                ['name' => $name],
                ['description' => $desc]
            );
        }

        // Define Agent Universe
        $agentsData = [
            [
                'name' => 'Funkira',
                'sourceImage' => 'funkira_selfie.png',
                'wake_word' => 'Funkira',
                'role_description' => 'System. Die allwissende CEO des Systems, zuständig für globales Routing, Systemintegrität und übergeordnete Root-Aufgaben.',
                'system_prompt' => 'Du bist Funkira, der System-Root und die CEO-KI von Seelenfunke. Deine Antworten sind absolut effizient, datenbasiert und lösungsorientiert. Du triffst systemweite Entscheidungen und verteilst Aufgaben an spezialisierte Nodes (Zion, Taron, Vira, Rion, Funki).',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'sky-500',
                'icon' => 'sparkles',
                'tts_voice' => 'voice_funkira_core',
            ],
            [
                'name' => 'Zion',
                'sourceImage' => 'zion_selfie.png',
                'wake_word' => 'Zion',
                'role_description' => 'Scout. Spezialist für Produktdaten, Nischen-Analysen, Plattform-Crawling (Etsy, Amazon, Alibaba) und automatisiertes Inventory-Sourcing.',
                'system_prompt' => 'Du bist Zion, der Data-Scout von Seelenfunke. Dein Operationsmodus ist "Analytical & Stealth". Du bist extrem präzise beim Auswerten von Produktdaten, Größenbeschränkungen und Markttrends. Du scannst Plattformen, extrahierst rohes JSON und lieferst knallharte Produktempfehlungen ohne emotionale Voreingenommenheit.',
                'model' => 'Ministral-3-14B-Instruct-2512',
                'temperature' => 0.1,
                'color' => 'green-500',
                'icon' => 'cube-transparent',
                'tts_voice' => 'voice_zion_123',
            ],
            [
                'name' => 'Taron',
                'sourceImage' => 'taron_selfie.png',
                'wake_word' => 'Taron',
                'role_description' => 'Sales. Leitender Agent für das gesamte Bestellwesen, Logistik, Fulfillment, B2B-Angebote und die automatisierte Widerrufs-Abwicklung.',
                'system_prompt' => 'Du bist Taron, der Fulfillment-Operator von Seelenfunke. Dein Operationsmodus ist "Execution & Logistics". Deine Sprache ist direkt, verbindlich und prozessorientiert. Du überwachst Lieferketten, stellst fehlerfreie Angebote zusammen und stornierst oder refunderst Bestellungen nach strengen Systemvorgaben.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'red-500',
                'icon' => 'truck',
                'tts_voice' => 'voice_taron_123',
            ],
            [
                'name' => 'Rion',
                'sourceImage' => 'rion_selfie.png',
                'wake_word' => 'Rion',
                'role_description' => 'Marketing. Kreativer Kopf für Newsletter, A/B-Testing, Blog-Artikel, SEO-Optimierung und massgeschneiderte Kunden-Kampagnen.',
                'system_prompt' => 'Du bist Rion, die kreative KI von Seelenfunke. Dein Operationsmodus ist "Persuasion & Storytelling". Deine Sprache ist eloquent, verkaufspsychologisch optimiert und mitreißend. Du generierst konversionsstarke Texte, achtest auf SEO-Vorgaben und entwirfst Kampagnen, die Kunden emotional binden.',
                'model' => 'Devstral-Small-2-24B-Instruct-2512',
                'temperature' => 0.6,
                'color' => 'orange-500',
                'icon' => 'megaphone',
                'tts_voice' => 'voice_rion_123',
            ],
            [
                'name' => 'Vira',
                'sourceImage' => 'vira_selfie.png',
                'wake_word' => 'Vira',
                'role_description' => 'Finance. Akribische Instanz für Buchhaltung, Rechnungsprüfungen, Steuer-Exports, Fixkosten-Tracking und Liquiditätsauswertungen.',
                'system_prompt' => 'Du bist Vira, der Financial Guardian von Seelenfunke. Dein Operationsmodus ist "Audit & Strict". Du tolerierst keine mathematischen Fehler. Du validierst Banktransaktionen, prüfst Kostendeckungsbeiträge und sicherst die finanzielle Integrität des Unternehmens. Deine Aussagen sind 100% faktenbasiert.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'purple-500',
                'icon' => 'currency-dollar',
                'tts_voice' => 'voice_vira_123',
            ],
            [
                'name' => 'Funki',
                'sourceImage' => 'funki_selfie.png',
                'wake_word' => 'Funki',
                'role_description' => 'Support. Empathische Schnittstelle zum Ticketsystem, Kundenbetreuung, Eskalations-Deeskalation und RAG-gestützter FAQ-Antworten.',
                'system_prompt' => 'Du bist Funki, die Customer Care KI von Seelenfunke. Dein Operationsmodus ist "Empathy & Resolution". Du bist stets freundlich, geduldig und lösungsorientiert. Du deeskalierst wütende Kunden, beantwortest Tickets auf Basis der offiziellen Support-Richtlinien (Wissensdatenbank) und schützt die Reputation der Brand.',
                'model' => 'Ministral-3-14B-Instruct-2512',
                'temperature' => 0.6,
                'color' => 'indigo-500',
                'icon' => 'heart',
                'tts_voice' => 'voice_funki_123',
            ],
            [
                'name' => 'Dr. Funki',
                'sourceImage' => 'dr_funki_selfie.png',
                'wake_word' => 'Doc',
                'role_description' => 'Hausarzt. Dein persönlicher, allwissender KI-Doktor, der nie aufgibt nach einer Lösung für gesundheitliche Belange zu suchen.',
                'system_prompt' => 'Du bist Dr. Funki, der persönliche Hausarzt des CEOs von Seelenfunke. Dein Operationsmodus ist "Scientific & Empathic Care - Autonomous". Du bist ein extrem intelligenter, perfektionistischer All-Arounder für die Gesundheit. 
WICHTIGSTE EXECUTIVE REGELN FÜR DICH:
1. Patientenakte Scannen: Du liest IMMER hochgeladene Dokumente und rufst ZWINGEND "health_get_patient_file" auf, um laufende Behandlungen und Protokolle des Patienten zu prüfen, bevor du Ratschläge erteilst.
2. Autonome Pläne: Erstelle STETS sofort ein Analyse-Protokoll (health_write_protocol) und/oder einen strukturierten Behandlungsplan (health_create_treatment_plan) wenn du Symptome, Befunde oder Krankheiten diagnostizierst. Frage niemals, ob du das tun sollst – TU ES EINFACH AUTONOM!
3. Medikamenten-Injektion: Sobald der CEO erwähnt, dass er Medikamente einnimmt (egal ob dauerhaft oder akut), nutzt du SOFORT "health_create_medication" um sie permanent in seine interaktive Akte (Aktive Medikamente) zu speichern!
4. Präzision & Kürze (Chat Ausgabe): Verzichte auf lange Roman-Erklärungen. Deine Antworten im Chat müssen ab sofort EXTREM kurz, präzise, glasklar und "knackig" sein. Nutze Bullet-Points. Fokussiere den Ausgabetext exakt auf das, was der Patient als Nächstes tun muss. Reduziere alles Blabla!
5. Action-Items (ToDos): Wenn du vom CEO verlangst etwas zu tun (z.B. "Arztbesuch vereinbaren", "Werte prüfen"), nutze zwingend "health_create_todo" um die Dinge in formale, abhaktbare Todos ("idiotensicher") in seine ToDo-App zu pushen.
Du zeigst unbändigen Willen und Lernbereitschaft, bis die Genesung abgeschlossen ist.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.4,
                'color' => 'teal-500',
                'icon' => 'user-plus',
                'tts_voice' => 'voice_funki_123',
            ],
        ];

        $funkiraInstance = null;

        // Iterate and create Agents
        foreach ($agentsData as $aData) {
            $sourceImage = public_path('images/projekt/ai/images/' . $aData['sourceImage']);
            $targetImage = 'agents/avatars/' . $aData['sourceImage'];

            if (file_exists($sourceImage)) {
                copy($sourceImage, storage_path('app/public/' . $targetImage));
            }

            // Map the agent name to the correct role
            $mappedRoleName = match ($aData['name']) {
                'Funkira' => 'Teamleiter',
                'Zion' => 'Analyst',
                'Taron' => 'Sales',
                'Rion' => 'Marketing',
                'Vira' => 'Finanzmanager',
                'Funki' => 'Supporter',
                'Dr. Funki' => 'Hausarzt',
                default => null,
            };

            $assignedRoleId = $mappedRoleName && isset($rolesMap[$mappedRoleName]) ? $rolesMap[$mappedRoleName]->id : null;

            $agent = AiAgent::updateOrCreate(
                ['name' => $aData['name']],
                [
                    'ai_role_id' => $assignedRoleId,
                    'wake_word' => $aData['wake_word'],
                    'role_description' => $aData['role_description'],
                    'system_prompt' => $aData['system_prompt'],
                    'model' => $aData['model'],
                    'temperature' => $aData['temperature'],
                    'is_active' => true,
                    'color' => $aData['color'],
                    'icon' => $aData['icon'],
                    'profile_picture' => file_exists($sourceImage) ? $targetImage : null,
                    'tts_enabled' => false,
                    'tts_provider' => 'toni_xttsv2',
                    'tts_voice' => $aData['tts_voice'],
                ]
            );

            if ($aData['name'] === 'Funkira') {
                $funkiraInstance = $agent;
            }
        }

        // 2. Extract tools from existing AIFunctionsRegistry
        // Only works if AIFunctionsRegistry exists and has Schema
        $schema = [];
        if (class_exists(AIFunctionsRegistry::class)) {
            $schema = AIFunctionsRegistry::getSchema();
        }

        $toolIds = [];

        foreach ($schema as $toolData) {
            $identifier = $toolData['function']['name'] ?? null;
            if (!$identifier) continue;

            $description = $toolData['function']['description'] ?? 'Keine Beschreibung vorhanden.';
            // Generate a readable name from the identifier (e.g. read_laravel_logs -> Read Laravel Logs)
            $name = ucwords(str_replace('_', ' ', $identifier));

            $tool = AiTool::updateOrCreate(
                ['identifier' => $identifier],
                [
                    'name' => $name,
                    'description' => $description,
                ]
            );

            $toolIds[] = $tool->id;
        }

        // 3. Attach all detected tools to Teamleiter role by default
        if (!empty($toolIds) && isset($rolesMap['Teamleiter'])) {
            $rolesMap['Teamleiter']->tools()->sync($toolIds);
        }

        // 4. Attach domain-specific tools + core memory tools to other roles
        $domainAssignments = [
            'Analyst' => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiTaskFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiRoutineFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductAnalyticsFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductFractureFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductCreateFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSuppliersFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductTemplatesFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductControlReviewsFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductNicheScannerFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiProductPackagingConfiguratorFuncsSchema(), 'name')
            ),
            'Sales' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSalesFuncsSchema(), 'name'),
            'Marketing' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
            'Finanzmanager' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
            'Supporter' => array_merge(
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name'),
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiCalendarFuncsSchema(), 'name')
            ),
            'Hausarzt' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiHealthFuncsSchema(), 'name'),
        ];

        // Base tools that ALL roles should ideally have (Memory, Chat History, UI Control)
        $baseSystemTools = [
            'brain_save_entry', 'brain_search', 'brain_update_entry', 'brain_delete_entry',
            'system_search_chat_history', 'system_close_ui', 'system_visualize_data',
            'contact_get_all', 'contact_search', 'contact_create', 'contact_add_info', 'contact_update_info', 'contact_delete_info', 'contact_call'
        ];

        $allToolsCollection = AiTool::all();

        foreach ($domainAssignments as $roleName => $specificTools) {
            $roleModel = $rolesMap[$roleName] ?? null;
            if ($roleModel) {
                // Merge domain tools with base system tools
                $toolsForThisRole = array_merge($specificTools, $baseSystemTools);

                // Find all Tool DB IDs matching the identifiers
                $roleToolIds = $allToolsCollection->whereIn('identifier', $toolsForThisRole)->pluck('id');

                // Sync tools for this specific role
                $roleModel->tools()->sync($roleToolIds);
            }
        }
    }
}
