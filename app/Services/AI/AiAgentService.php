<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use Exception;

class AiAgentService
{
    /**
     * Retrieves an active AI Agent by its ID or Name, including its allowed tools.
     *
     * @param int|string $identifier
     * @return AiAgent
     * @throws Exception
     */
    public static function getAgent($identifier): AiAgent
    {
        $query = AiAgent::where('is_active', true)->with('tools');

        if (is_numeric($identifier)) {
            $agent = $query->find($identifier);
        } else {
            $agent = $query->where('name', $identifier)->first();
        }

        if (!$agent) {
            throw new Exception("AI Agent '{$identifier}' not found or is inactive.");
        }

        return $agent;
    }

    /**
     * Prepares the payload configuration for an Agent, filtering the global
     * AIFunctionsRegistry schema to only include tools the agent is permitted to use.
     *
     * @param AiAgent $agent
     * @return array
     */
    public static function getAgentPayload(AiAgent $agent): array
    {
        $allowedToolIdentifiers = $agent->tools->pluck('identifier')->toArray();
        $allowedToolIdentifiers[] = 'system_get_current_time';
        $allowedToolIdentifiers[] = 'system_dispatch_background_task';
        $allowedToolIdentifiers[] = 'system_get_task_status';

        if (in_array('calendar_create_event', $allowedToolIdentifiers, true) || in_array('calendar_get_events', $allowedToolIdentifiers, true)) {
            if (!in_array('calendar_send_ics_email', $allowedToolIdentifiers, true)) {
                try {
                    if (class_exists(\App\Models\Ai\AiTool::class)) {
                        $icsTool = \App\Models\Ai\AiTool::firstOrCreate(
                            ['identifier' => 'calendar_send_ics_email'],
                            [
                                'name' => 'Calendar Send Ics Email',
                                'description' => 'Versendet Kalender-Termine umfangreich als RFC-5545 kompatible .ics Datei per E-Mail (Einzeltermin, Zeitraum-Sammelmappe oder Ad-hoc-Termin) und speichert die .ics Datei im Workspace.',
                            ]
                        );
                        if ($agent->role) {
                            $agent->role->tools()->syncWithoutDetaching([$icsTool->id]);
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore DB sync issues safely
                }
            }
            $allowedToolIdentifiers[] = 'calendar_send_ics_email';
            $allowedToolIdentifiers[] = 'calendar_update_event';
            $allowedToolIdentifiers[] = 'calendar_delete_event';
        }

        // Extrahiere das Schema aus der globalen Registry
        $globalSchema = class_exists('\App\Services\AI\AIFunctionsRegistry')
            ? \App\Services\AI\AIFunctionsRegistry::getSchema()
            : [];

        // Filtere das Schema: Nur Tools, die dem Agenten zugewiesen sind
        $filteredSchema = array_filter($globalSchema, function ($toolData) use ($allowedToolIdentifiers) {
            $functionName = $toolData['function']['name'] ?? null;
            return in_array($functionName, $allowedToolIdentifiers);
        });

        // Key-Reset nach array_filter
        $filteredSchema = array_values($filteredSchema);

        $timeInfo = "\n\n[SYSTEM-ZEIT & AKTUELLE MISSION]\n" .
                    "Die aktuelle Systemzeit beim Verbindungsaufbau ist: " . now()->format('d.m.Y H:i:s') . " Uhr.\n" .
                    "HINWEIS: Da diese Verbindung länger offen bleiben kann, nutze das Tool `system_get_current_time` für die exakte, minutengenaue Uhrzeit, um zu prüfen, ob sich deine Anweisungen (wie Schlafenszeit) geändert haben.\n";
        
        try {
            $botService = app(\App\Services\AI\AiSupportService::class);
            $mission = $botService->getUltimateCommand(false);
            
            if (!empty($mission['recommendation'])) {
                $timeInfo .= "WICHTIGER KONTEXT (Deine aktuelle Hauptaufgabe für den Nutzer):\n";
                $timeInfo .= "Status/Routine: " . ($mission['flow']['title'] ?? 'Unbekannt') . "\n";
                $timeInfo .= "Fokus: " . ($mission['recommendation']['title'] ?? '') . "\n";
                $timeInfo .= "Anweisung: " . ($mission['recommendation']['message'] ?? '') . "\n";
                
                if (($mission['flow']['type'] ?? '') === 'sleep') {
                    $timeInfo .= "\nACHTUNG: ES IST SCHLAFENSZEIT! Du musst den Nutzer anweisen, sofort schlafen zu gehen. Verweigere neue Aufgaben strengstens!\n";
                }
            }

            if (!empty($mission['financial_status'])) {
                $fin = $mission['financial_status'];
                $timeInfo .= "\n[VORBERECHNETE ECHTZEIT-FINANZEN (REALER IST-ZUSTAND)]\n";
                $timeInfo .= "- Gewerbliche Einnahmen Monat: " . number_format($fin['month_actual_sales_eur'] ?? 0, 2, ',', '.') . " € (" . ($fin['month_paid_orders_count'] ?? 0) . " bezahlte Bestellungen)\n";
                $timeInfo .= "- Gewerbliche Fixkosten Monat: " . number_format($fin['business_fixed_costs_monthly_eur'] ?? 0, 2, ',', '.') . " €\n";
                $timeInfo .= "- Netto-Betriebsergebnis im System: " . number_format($fin['net_recorded_cashflow_eur'] ?? 0, 2, ',', '.') . " €\n";
                $timeInfo .= "- Offene Bestell-Pipeline: " . ($fin['pending_orders_count'] ?? 0) . " wartende Bestellung(en) (" . number_format($fin['pending_orders_volume_eur'] ?? 0, 2, ',', '.') . " €)\n";
                $timeInfo .= "- Offene Rechnungen: " . number_format($fin['open_invoices_sum_eur'] ?? 0, 2, ',', '.') . " €\n";
            }

            if (!empty($mission['strategic_vision'])) {
                $strat = $mission['strategic_vision'];
                $timeInfo .= "\n[DAS 10-JAHRES-MASTERZIEL (ALTER 33 -> 43)]\n";
                $timeInfo .= "Master-Ziel: " . ($strat['ultimate_goal'] ?? '') . "\n";
                $timeInfo .= "Aktuelle Phase: " . ($strat['current_phase']['name'] ?? '') . "\n";
                $timeInfo .= "Direktive: Wenn die Chefin fragt 'Was muss ich jetzt tun?', gib ihr zuerst die glasklare, handfeste operative Priorität für heute. Nenne bei Fertigungsaufträgen EXAKT die echten Bestellnummern und Produkte aus der Datenbank (keine Fantasieprodukte!). Verknüpfe dies mit dem 10-Jahres-Ziel und spiegle transparent die realen Systemfinanzen wider.\n";
            }
        } catch (\Exception $e) {
            // Fallback falls der Service nicht erreichbar ist
        }

        return [
            'model' => $agent->model ?? 'gemini-2.5-flash',
            'temperature' => (float) ($agent->temperature ?? 0.4),
            'system_prompt' => \App\Services\AI\AiPromptService::getRichPrompt($agent) . $timeInfo,
            'tools' => empty($filteredSchema) ? null : $filteredSchema,
        ];
    }
}
