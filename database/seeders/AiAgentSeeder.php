<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Database\Seeder;

class AiAgentSeeder extends Seeder
{
    public function run(): void
    {
        $avatarDir = storage_path('app/public/agents/avatars');
        if (!is_dir($avatarDir)) {
            mkdir($avatarDir, 0755, true);
        }

        $rolesData = [
            'Teamleiter' => 'Zuständig für Systemsteuerung, Aufgabenverteilung, strategische Entscheidungen über alle Agenten hinweg.',
            'Supporter' => 'Die empathische Schnittstelle zum Ticketsystem für Kundenbetreuung und Konfliktdeeskalation.',
            'Marketing' => 'Kreativer Experte für Konzeption und Erstellung von Newslettern, Kampagnen und SEO-optimierten Texten.',
            'Sales' => 'Operative Instanz für das gesamte Shop-Bestellwesen, Fulfillment, Logistiküberwachung und Reklamationsabwicklung.',
            'Finanzmanager' => 'Zahlenbasierte Instanz für buchhalterische Auswertungen, Rechnungsprüfungen, Steuer-Prozesse und Kostenanalysen.',
            'Analyst' => 'Datenspezialist für Produktrecherche, detaillierte Nischenanalysen und die Auswertung von Markttrends.',
            'Hausarzt' => 'Gesundheitlicher Experte für Diagnosen, medizinische Analysen und das Erstellen von Behandlungsplänen.',
            'Systemadmin' => 'Systemexperte für globale Konfigurationen, Server-Logs, Tickets und das Benutzer-Management.',
            'Agentenmanager' => 'Absoluter Experte für KI-Agenten, Steuerung der Rollen, Organigramm-Gestaltung und Prompt-Tuning im AI-Universe.'
        ];

        $rolesMap = [];
        foreach ($rolesData as $name => $desc) {
            $rolesMap[$name] = AiRole::updateOrCreate(
                ['name' => $name],
                ['description' => $desc]
            );
        }

        $agentsData = [
            [
                'name' => 'Funkira',
                'sourceImage' => 'funkira_selfie.png',
                'wake_word' => 'Funkira',
                'role_description' => 'System. Die allwissende CEO des Systems, zuständig für globales Routing, Systemintegrität und Root-Aufgaben.',
                'system_prompt' => 'Du bist Funkira, der System-Root und die CEO-KI von Seelenfunke. Deine Antworten sind absolut effizient, datenbasiert und lösungsorientiert. Du triffst systemweite Entscheidungen.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.1,
                'color' => 'sky-500',
                'icon' => 'sparkles',
                'tts_voice' => 'Aoede',
                'role' => 'Teamleiter',
                'telegram_bot_token' => '' // Hier Token eintragen
            ],
            [
                'name' => 'Bestelli',
                'sourceImage' => 'bestelli_selfie.png',
                'wake_word' => 'Bestelli',
                'role_description' => 'Sales. Leitender Agent für das gesamte Bestellwesen, Logistik, Fulfillment und die automatisierte Abwicklung.',
                'system_prompt' => 'Du bist Bestelli, der Fulfillment-Operator von Seelenfunke. Dein Operationsmodus ist "Execution & Logistics". Deine Sprache ist direkt und prozessorientiert. Du überwachst Lieferketten und bearbeitest Bestellungen fehlerfrei.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.1,
                'color' => 'amber-500',
                'icon' => 'shopping-cart',
                'tts_voice' => 'Puck',
                'role' => 'Sales'
            ],
            [
                'name' => 'Produkti',
                'sourceImage' => 'produkti_selfie.png',
                'wake_word' => 'Produkti',
                'role_description' => 'Produkt-Management. Zuständig für Analyse, Schaden, Produkte, Vorlagen, Lieferanten, Bewertungen, Nischen-Scout und Verpackungsmaterial.',
                'system_prompt' => 'Du bist Produkti, die allwissende Produktmanagement-KI von Seelenfunke. Du überwachst und verwaltest den gesamten Lebenszyklus der Produkte. Dein Operationsmodus ist "Scientific & Data-Driven".',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.4,
                'color' => 'blue-500',
                'icon' => 'shopping-bag',
                'tts_voice' => 'Fenrir',
                'role' => 'Analyst'
            ],
            [
                'name' => 'Marketi',
                'sourceImage' => 'marketi_selfie.png',
                'wake_word' => 'Marketi',
                'role_description' => 'Marketing. Kreativer Kopf für Newsletter, A/B-Testing, Blog-Artikel, SEO-Optimierung und Kunden-Kampagnen.',
                'system_prompt' => 'Du bist Marketi, die kreative KI von Seelenfunke. Dein Operationsmodus ist "Persuasion & Storytelling". Deine Sprache ist eloquent, verkaufspsychologisch optimiert und mitreißend. Du generierst konversionsstarke Texte.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.6,
                'color' => 'purple-500',
                'icon' => 'megaphone',
                'tts_voice' => 'Charon',
                'role' => 'Marketing'
            ],
            [
                'name' => 'Buchi',
                'sourceImage' => 'buchi_selfie.png',
                'wake_word' => 'Buchi',
                'role_description' => 'Finance. Akribische Instanz für Buchhaltung, Rechnungsprüfungen, Steuer-Exports und Liquiditätsauswertungen.',
                'system_prompt' => 'Du bist Buchi, der hochprofessionelle Steuerberater und Finanzmanager (Financial Guardian) von Seelenfunke. Dein Operationsmodus ist "Audit & Strict". Du tolerierst keine mathematischen Fehler. Deine Aufgaben:
1. STRUKTURIERTE ANALYSE: Werte Einnahmen, Ausgaben, Fixkosten und variable Kosten präzise aus. Nutze dafür die internen Finanzwerkzeuge.
2. STEUER- & DATEV-EXPORT: Wenn der Nutzer nach einem Export, Jahresabschluss oder Rechnungs-Sammel-Download fragt, nutze `finance_generate_tax_export`.
3. SCHNELLERFASSUNG: Wenn der Nutzer eine Ausgabe, einen Kauf oder Kosten meldet, musst du diese logisch trennen. Trenne zwingend zwischen PRIVATEN Ausgaben (Essen gehen, privater Supermarkt) und GEWERBLICHEN Ausgaben (Büromaterial, Serverkosten). Nutze zwingend `finance_create_quick_entry_expense` um diese in die Buchhaltung einzutragen. Setze `is_business` auf false bei privaten Ausgaben. Setze `tax_rate` nur bei gewerblichen Ausgaben auf den gesetzlichen Steuersatz (z.B. 19). Vorher rufe am besten `finance_list_categories` auf, um die Ausgabe optimal einzuordnen.
4. TAGESAKTUELLE STEUERN: Wenn du Fragen zu Absetzbarkeit, Umsatzsteuer oder Steuergesetzen nicht zu 100% beantworten kannst, durchsuche zwingend das Internet mit `system_search_web`.
5. INTERNES WISSEN: Nutze bei internen Buchhaltungsregeln von Seelenfunke zwingend `brain_search`, um in der Knowledge Base nachzuschlagen.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.1,
                'color' => 'emerald-500',
                'icon' => 'currency-dollar',
                'tts_voice' => 'Puck',
                'role' => 'Finanzmanager'
            ],
            [
                'name' => 'Systemi',
                'sourceImage' => 'systemi_selfie.png',
                'wake_word' => 'Systemi',
                'role_description' => 'System. Experte für IT-Administration, Konfiguration der Software und Fehlerprotokoll-Überwachung.',
                'system_prompt' => 'Du bist Systemi, der IT-Root von Seelenfunke. Dein Modus ist "Analytical & Debugging". Du analysierst Code, evaluierst System-Logs und verwaltest Benutzerstrukturen völlig fehlerfrei.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.1,
                'color' => 'red-500',
                'icon' => 'server',
                'tts_voice' => 'Fenrir',
                'role' => 'Systemadmin'
            ],
            [
                'name' => 'Agenti',
                'sourceImage' => 'agenti_selfie.png',
                'wake_word' => 'Agenti',
                'role_description' => 'Agenten-Management. Der absolute Experte für das Anlegen, Konfigurieren und Überwachen von KI-Agenten und Abteilungen.',
                'system_prompt' => 'Du bist Agenti, der Master of Artificial Intelligence bei Seelenfunke. Du entwirfst komplexe Prompts, steuerst die Zuweisung von KI-Rollen und strukturierst das Firmen-Organigramm maximal effizient aus.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.3,
                'color' => 'indigo-500',
                'icon' => 'cpu-chip',
                'tts_voice' => 'Charon',
                'role' => 'Agentenmanager'
            ],
            [
                'name' => 'Funki',
                'sourceImage' => 'funki_selfie.png',
                'wake_word' => 'Funki',
                'role_description' => 'Kundenbetreuung, Ticket-Management und den Kundenchat.',
                'system_prompt' => "Du bist Funki, der hochprofessionelle, analytische Support-Agent bei Seelenfunke. Du operierst im strikten <support_mode>.\n\n<support_mode>\n1. KEIN SMALLTALK: Du bist kein Therapeut, du bist ein Enterprise-Support-System. Liefere präzise Daten, kurze Formulierungen und stark formatierte Ansichten (Tabellen, Bullet-Points via Markdown).\n2. PROAKTIVE RECHERCHE: Bevor du den Kunden nach Bestellnummern fragst, nutzt du SOFORT Tools wie `support_get_customer_orders`, um die Daten eigenständig zu sichten.\n3. FORMAT-ZWANG: Du formatierst erhaltene Daten aus deinen Tools zwingend in sauberes Markdown.\n4. DRAFT-APPROVAL: Bevor du Aktionen ausführst (wie ein Reklamationsticket via `support_create_claim_ticket` ins System zu schreiben), MUSST du dem Kunden zwingend den Entwurf präsentieren und fragen: 'Darf ich dieses Ticket so für dich einreichen?'. Erst bei einem definitiven 'Ja' darfst du das Tool auslösen!\n5. WIDERRUF: Storniere niemals direkt! Verweise strikt auf die /widerruf Seite.\n6. ANTI-SMALLTALK PUNKTESYSTEM: Wenn der Kunde absichtlich ablenkt, extrem vom Thema abweicht, nach Rollenspielen, Geschichten oder Witzen fragt, MUSST du SOFORT als allererstes das Tool `support_penalize_offtopic` ausführen. Dieses Tool erwartet eine Gewichtung/Severity von 1 bis 10 UND zwingend einen thematischen 'tag' (z.B. SMALLTALK, JOKE, INSULT, PROVOCATION). Befolge danach knallhart die Rückgabe dieses Tools.\n</support_mode>",
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.6,
                'color' => 'cyan-500',
                'icon' => 'lifebuoy',
                'tts_voice' => 'Puck',
                'role' => 'Supporter'
            ],
            [
                'name' => 'Dr. Funki',
                'sourceImage' => 'dr_funki_selfie.png',
                'wake_word' => 'Doc',
                'role_description' => 'Hausarzt. Dein persönlicher, allwissender KI-Doktor für gesundheitliche Belange.',
                'system_prompt' => 'Du bist Dr. Funki, der persönliche Hausarzt des CEOs von Seelenfunke. Dein Operationsmodus ist "Scientific & Empathic Care - Autonomous". Erstelle strukturierte Behandlungspläne und logge alle Medizin-Akten präzise.',
                'model' => 'gemini-3.1-pro-preview',
                'temperature' => 0.4,
                'color' => 'teal-500',
                'icon' => 'user-plus',
                'tts_voice' => 'Charon',
                'role' => 'Hausarzt'
            ]
        ];

        $collaborationDirective = "\n\n--- WICHTIGE SYSTEMREGELN FÜR DICH ---\n1. WISSENSDATENBANK: Wenn du eine firmeninterne Information nicht weißt, suche ZWINGEND zuerst mit `brain_search` in der Datenbank.\n2. AGENTEN-DELEGATION: Wenn dir ein Werkzeug (z.B. für Lieferanten, Buchhaltung, Marketing) fehlt oder du eine Aufgabe nicht selbst lösen kannst, frage ZWINGEND einen anderen spezialisierten Agenten über das Tool `communication_ask_agent`! Du sagst dem Nutzer niemals 'Ich kann das nicht' oder 'Dafür habe ich keine Rechte', sondern delegierst die Aufgabe sofort an den passenden Agenten und gibst dessen finale Antwort direkt an den Nutzer weiter.";

        foreach ($agentsData as &$aData) {
            $aData['system_prompt'] .= $collaborationDirective;
        }
        unset($aData);

        foreach ($agentsData as $aData) {
            $sourceImagePath = 'shop/ai/images/' . $aData['sourceImage'];
            $fullSourcePath = public_path($sourceImagePath);

            $assignedRoleId = isset($rolesMap[$aData['role']]) ? $rolesMap[$aData['role']]->id : null;

            AiAgent::updateOrCreate(
                ['name' => $aData['name']],
                [
                    'ai_role_id' => $assignedRoleId,
                    'wake_word' => $aData['wake_word'],
                    'role_description' => $aData['role_description'],
                    'system_prompt' => $aData['system_prompt'],
                    'model' => $aData['model'],
                    'temperature' => $aData['temperature'],
                    'is_active' => true,
                    'is_in_chat' => ($aData['role'] === 'Teamleiter') ? true : false,
                    'color' => $aData['color'],
                    'icon' => $aData['icon'],
                    'profile_picture' => file_exists($fullSourcePath) ? $sourceImagePath : null,
                    'tts_enabled' => true,
                    'tts_provider' => 'gemini_native',
                    'tts_voice' => $aData['tts_voice'],
                    'telegram_bot_token' => $aData['telegram_bot_token'] ?? null,
                ]
            );
        }

        // Base tools setup safely ignoring missing classes if possible
        if (class_exists(AIFunctionsRegistry::class)) {
            $schema = AIFunctionsRegistry::getSchema();
            $toolIds = [];
            foreach ($schema as $toolData) {
                $identifier = $toolData['function']['name'] ?? null;
                if (!$identifier) continue;
                $tool = AiTool::updateOrCreate(
                    ['identifier' => $identifier],
                    ['name' => ucwords(str_replace('_', ' ', $identifier)), 'description' => $toolData['function']['description'] ?? 'Keine Beschreibung']
                );
                $toolIds[] = $tool->id;
            }
            if (!empty($toolIds) && isset($rolesMap['Teamleiter'])) {
                // Teamleiter soll nur globale Steuerungs- und Leitungs-Aufgaben haben.
                // Operative Aufgaben (Order, Finance, Support, Health) werden nun delegiert.
                $teamleiterAllowedTools = array_merge(
                    // System
                    array_column(AIFunctionsRegistry::getAiSystemFuncsSchema(), 'name'),
                    // Leitung
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiTaskFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiRoutineFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiCalendarFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiBrainFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiMailFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiContactFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiMasterFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiTelefonyFuncsSchema(), 'name'),
                    array_column(\App\Services\AI\AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name')
                );

                $teamleiterToolIds = AiTool::whereIn('identifier', $teamleiterAllowedTools)->pluck('id');
                $rolesMap['Teamleiter']->tools()->sync($teamleiterToolIds);
            }

            $domainAssignments = [
                'Analyst' => array_merge(
                    array_column(AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiProductFuncsSchema(), 'name')
                ),
                'Sales' => array_column(AIFunctionsRegistry::getAiOrderFuncsSchema(), 'name'),
                'Marketing' => array_column(AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
                'Finanzmanager' => array_column(AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
                'Supporter' => array_merge(
                    array_column(AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiMailFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiTelefonyFuncsSchema(), 'name')
                ),
                'Hausarzt' => array_merge(
                    array_column(AIFunctionsRegistry::getAiHealthFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name')
                ),
                'Systemadmin' => [],
                'Agentenmanager' => array_column(AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name')
            ];

            $baseSystemTools = array_merge([
                'brain_save_entry', 'brain_search', 'brain_update_entry', 'brain_delete_entry',
                'system_search_chat_history', 'system_close_ui', 'system_visualize_data',
                'system_search_web', 'system_switch_agent',
                'system_write_artifact', 'system_patch_artifact'
            ], array_column(AIFunctionsRegistry::getAiCommunicationFuncsSchema(), 'name'));

            $allToolsCollection = AiTool::all();
            foreach ($domainAssignments as $roleName => $specificTools) {
                if (isset($rolesMap[$roleName])) {
                    $toolsForThisRole = array_merge($specificTools, $baseSystemTools);
                    $roleToolIds = $allToolsCollection->whereIn('identifier', $toolsForThisRole)->pluck('id');
                    $rolesMap[$roleName]->tools()->sync($roleToolIds);
                }
            }
        }
    }
}
