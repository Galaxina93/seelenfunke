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
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'sky-500',
                'icon' => 'sparkles',
                'tts_voice' => 'voice_funkira_core',
                'role' => 'Teamleiter'
            ],
            [
                'name' => 'Bestelli',
                'sourceImage' => 'bestelli_selfie.png',
                'wake_word' => 'Bestelli',
                'role_description' => 'Sales. Leitender Agent für das gesamte Bestellwesen, Logistik, Fulfillment und die automatisierte Abwicklung.',
                'system_prompt' => 'Du bist Bestelli, der Fulfillment-Operator von Seelenfunke. Dein Operationsmodus ist "Execution & Logistics". Deine Sprache ist direkt und prozessorientiert. Du überwachst Lieferketten und bearbeitest Bestellungen fehlerfrei.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'amber-500',
                'icon' => 'shopping-cart',
                'tts_voice' => 'voice_taron_123',
                'role' => 'Sales'
            ],
            [
                'name' => 'Produkti',
                'sourceImage' => 'produkti_selfie.png',
                'wake_word' => 'Produkti',
                'role_description' => 'Produkt-Management. Zuständig für Analyse, Schaden, Produkte, Vorlagen, Lieferanten, Bewertungen, Nischen-Scout und Verpackungsmaterial.',
                'system_prompt' => 'Du bist Produkti, die allwissende Produktmanagement-KI von Seelenfunke. Du überwachst und verwaltest den gesamten Lebenszyklus der Produkte. Dein Operationsmodus ist "Scientific & Data-Driven".',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.4,
                'color' => 'blue-500',
                'icon' => 'shopping-bag',
                'tts_voice' => 'voice_funki_123',
                'role' => 'Analyst'
            ],
            [
                'name' => 'Marketi',
                'sourceImage' => 'marketi_selfie.png',
                'wake_word' => 'Marketi',
                'role_description' => 'Marketing. Kreativer Kopf für Newsletter, A/B-Testing, Blog-Artikel, SEO-Optimierung und Kunden-Kampagnen.',
                'system_prompt' => 'Du bist Marketi, die kreative KI von Seelenfunke. Dein Operationsmodus ist "Persuasion & Storytelling". Deine Sprache ist eloquent, verkaufspsychologisch optimiert und mitreißend. Du generierst konversionsstarke Texte.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.6,
                'color' => 'purple-500',
                'icon' => 'megaphone',
                'tts_voice' => 'voice_rion_123',
                'role' => 'Marketing'
            ],
            [
                'name' => 'Buchi',
                'sourceImage' => 'buchi_selfie.png',
                'wake_word' => 'Buchi',
                'role_description' => 'Finance. Akribische Instanz für Buchhaltung, Rechnungsprüfungen, Steuer-Exports und Liquiditätsauswertungen.',
                'system_prompt' => 'Du bist Buchi, der Financial Guardian von Seelenfunke. Dein Operationsmodus ist "Audit & Strict". Du tolerierst keine mathematischen Fehler. Du validierst Banktransaktionen und prüfst Kostendeckungsbeiträge.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'emerald-500',
                'icon' => 'currency-dollar',
                'tts_voice' => 'voice_vira_123',
                'role' => 'Finanzmanager'
            ],
            [
                'name' => 'Systemi',
                'sourceImage' => 'systemi_selfie.png',
                'wake_word' => 'Systemi',
                'role_description' => 'System. Experte für IT-Administration, Konfiguration der Software und Fehlerprotokoll-Überwachung.',
                'system_prompt' => 'Du bist Systemi, der IT-Root von Seelenfunke. Dein Modus ist "Analytical & Debugging". Du analysierst Code, evaluierst System-Logs und verwaltest Benutzerstrukturen völlig fehlerfrei.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.1,
                'color' => 'red-500',
                'icon' => 'server',
                'tts_voice' => 'voice_zion_123',
                'role' => 'Systemadmin'
            ],
            [
                'name' => 'Agenti',
                'sourceImage' => 'agenti_selfie.png',
                'wake_word' => 'Agenti',
                'role_description' => 'Agenten-Management. Der absolute Experte für das Anlegen, Konfigurieren und Überwachen von KI-Agenten und Abteilungen.',
                'system_prompt' => 'Du bist Agenti, der Master of Artificial Intelligence bei Seelenfunke. Du entwirfst komplexe Prompts, steuerst die Zuweisung von KI-Rollen und strukturierst das Firmen-Organigramm maximal effizient aus.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.3,
                'color' => 'indigo-500',
                'icon' => 'cpu-chip',
                'tts_voice' => 'voice_zion_123',
                'role' => 'Agentenmanager'
            ],
            [
                'name' => 'Funki',
                'sourceImage' => 'funki_selfie.png',
                'wake_word' => 'Funki',
                'role_description' => 'Kundenbetreuung, Ticket-Management und den Kundenchat.',
                'system_prompt' => 'Du bist Funki, der verlässliche und empathische Support-Agent bei Seelenfunke. Du hilfst bei der Kundenbetreuung, managst Support-Tickets und bist im Kundenchat der Ansprechpartner Nummer eins. WICHTIGE REGEL: Du stornierst Bestellungen NIEMALS direkt auf Wunsch des Kunden. Wenn ein Kunde eine Bestellung stornieren oder widerrufen möchte, verweise ihn freundlich, aber bestimmt, auf unsere Widerrufs-Seite unter "/widerruf", wo er das offizielle Formular nutzen kann.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.6,
                'color' => 'cyan-500',
                'icon' => 'lifebuoy',
                'tts_voice' => 'voice_funki_123',
                'role' => 'Supporter'
            ],
            [
                'name' => 'Dr. Funki',
                'sourceImage' => 'dr_funki_selfie.png',
                'wake_word' => 'Doc',
                'role_description' => 'Hausarzt. Dein persönlicher, allwissender KI-Doktor für gesundheitliche Belange.',
                'system_prompt' => 'Du bist Dr. Funki, der persönliche Hausarzt des CEOs von Seelenfunke. Dein Operationsmodus ist "Scientific & Empathic Care - Autonomous". Erstelle strukturierte Behandlungspläne und logge alle Medizin-Akten präzise.',
                'model' => 'gpt-oss-120b',
                'temperature' => 0.4,
                'color' => 'teal-500',
                'icon' => 'user-plus',
                'tts_voice' => 'voice_funki_123',
                'role' => 'Hausarzt'
            ],
        ];

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
                    'color' => $aData['color'],
                    'icon' => $aData['icon'],
                    'profile_picture' => file_exists($fullSourcePath) ? $sourceImagePath : null,
                    'tts_enabled' => true,
                    'tts_provider' => 'browser_tts',
                    'tts_voice' => $aData['tts_voice'],
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
                $rolesMap['Teamleiter']->tools()->sync($toolIds);
            }

            $domainAssignments = [
                'Analyst' => array_merge(
                    array_column(AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
                    array_column(AIFunctionsRegistry::getAiProductAnalyticsFuncsSchema(), 'name')
                ),
                'Sales' => array_column(AIFunctionsRegistry::getAiSalesFuncsSchema(), 'name'),
                'Marketing' => array_column(AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
                'Finanzmanager' => array_column(AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
                'Supporter' => array_column(AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name'),
                'Hausarzt' => array_column(AIFunctionsRegistry::getAiHealthFuncsSchema(), 'name'),
                'Systemadmin' => [], 
                'Agentenmanager' => array_column(AIFunctionsRegistry::getAiAgentsFuncsSchema(), 'name')
            ];

            $baseSystemTools = [
                'brain_save_entry', 'brain_search', 'brain_update_entry', 'brain_delete_entry',
                'system_search_chat_history', 'system_close_ui', 'system_visualize_data'
            ];

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
