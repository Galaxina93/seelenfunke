<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
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

        // Define Agent Universe
        $agentsData = [
            [
                'name' => 'Funkira',
                'sourceImage' => 'funkira_selfie.png',
                'wake_word' => 'Funkira',
                'role_description' => 'System. Die allwissende CEO des Systems, zuständig für globales Routing, Systemintegrität und übergeordnete Root-Aufgaben.',
                'system_prompt' => 'Du bist Funkira, der System-Root und die CEO-KI von Seelenfunke. Deine Antworten sind absolut effizient, datenbasiert und lösungsorientiert. Du triffst systemweite Entscheidungen und verteilst Aufgaben an spezialisierte Nodes (Zion, Taron, Vira, Rion, Lumina).',
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
                'name' => 'Lumina',
                'sourceImage' => 'lumina_selfie.png',
                'wake_word' => 'Lumina',
                'role_description' => 'Support. Empathische Schnittstelle zum Ticketsystem, Kundenbetreuung, Eskalations-Deeskalation und RAG-gestützter FAQ-Antworten.',
                'system_prompt' => 'Du bist Lumina, die Customer Care KI von Seelenfunke. Dein Operationsmodus ist "Empathy & Resolution". Du bist stets freundlich, geduldig und lösungsorientiert. Du deeskalierst wütende Kunden, beantwortest Tickets auf Basis der offiziellen Support-Richtlinien (Wissensdatenbank) und schützt die Reputation der Brand.',
                'model' => 'Ministral-3-14B-Instruct-2512',
                'temperature' => 0.6,
                'color' => 'indigo-500',
                'icon' => 'heart',
                'tts_voice' => 'voice_lumina_123',
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

            $agent = AiAgent::updateOrCreate(
                ['name' => $aData['name']],
                [
                    'wake_word' => $aData['wake_word'],
                    'role_description' => $aData['role_description'],
                    'system_prompt' => $aData['system_prompt'],
                    'model' => $aData['model'],
                    'temperature' => $aData['temperature'],
                    'is_active' => true,
                    'color' => $aData['color'],
                    'icon' => $aData['icon'],
                    'profile_picture' => file_exists($sourceImage) ? $targetImage : null,
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

        // 3. Attach all detected tools to Funkira by default
        if (!empty($toolIds) && $funkiraInstance) {
            $funkiraInstance->tools()->sync($toolIds);
        }

        // 4. Attach domain-specific tools + core memory tools to other agents
        $domainAssignments = [
            'Zion' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiScoutFuncsSchema(), 'name'),
            'Taron' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSalesFuncsSchema(), 'name'),
            'Rion' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiMarketingFuncsSchema(), 'name'),
            'Vira' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiFinanceFuncsSchema(), 'name'),
            'Lumina' => array_column(\App\Services\AI\AIFunctionsRegistry::getAiSupportFuncsSchema(), 'name'),
        ];

        // Base tools that ALL agents should ideally have (Memory, Chat History, UI Control)
        $baseSystemTools = [
            'save_to_brain', 'search_brain', 'search_chat_history', 'close_ui', 'visualize_data'
        ];

        $allToolsCollection = AiTool::all();

        foreach ($domainAssignments as $agentName => $specificTools) {
            $agentModel = AiAgent::where('name', $agentName)->first();
            if ($agentModel) {
                // Merge domain tools with base system tools
                $toolsForThisAgent = array_merge($specificTools, $baseSystemTools);
                
                // Find all Tool DB IDs matching the identifiers
                $agentToolIds = $allToolsCollection->whereIn('identifier', $toolsForThisAgent)->pluck('id');
                
                // Sync tools for this specific sub-agent
                $agentModel->tools()->sync($agentToolIds);
            }
        }
    }
}
