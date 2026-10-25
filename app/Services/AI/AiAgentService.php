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
        $query = AiAgent::where('is_active', true)->with('role.tools');

        if (is_numeric($identifier) || \Illuminate\Support\Str::isUuid((string) $identifier)) {
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

        $isEmployeeRestricted = AiAuthHelper::check() && !AiAuthHelper::isAdmin();
        if (!$isEmployeeRestricted) {
            $allowedToolIdentifiers[] = 'system_dispatch_background_task';
            $allowedToolIdentifiers[] = 'system_get_task_status';
        }

        if (in_array('calendar_create_event', $allowedToolIdentifiers, true)) {
            $allowedToolIdentifiers[] = 'calendar_send_ics_email';
            $allowedToolIdentifiers[] = 'calendar_update_event';
            $allowedToolIdentifiers[] = 'calendar_delete_event';
        }

        if (in_array('routine_get_day_routines', $allowedToolIdentifiers, true) && class_exists('\App\Services\AI\AIFunctionsRegistry')) {
            $allowedToolIdentifiers = array_values(array_unique(array_merge(
                $allowedToolIdentifiers,
                array_column(\App\Services\AI\AIFunctionsRegistry::getAiRoutineFuncsSchema(), 'name')
            )));
        }

        // Extrahiere das Schema aus der globalen Registry
        $globalSchema = class_exists('\App\Services\AI\AIFunctionsRegistry')
            ? \App\Services\AI\AIFunctionsRegistry::getSchema()
            : [];

        // Filtere das Schema: Nur Tools, die dem Agenten zugewiesen sind (und für Nicht-Admins zulässig sind)
        $filteredSchema = array_filter($globalSchema, function ($toolData) use ($allowedToolIdentifiers, $isEmployeeRestricted) {
            $functionName = $toolData['function']['name'] ?? null;
            if (!$functionName || !in_array($functionName, $allowedToolIdentifiers, true)) {
                return false;
            }
            if ($isEmployeeRestricted && \App\Services\AI\AIFunctionsRegistry::isToolRestrictedForEmployee($functionName)) {
                return false;
            }
            return true;
        });

        // Key-Reset nach array_filter
        $filteredSchema = array_values($filteredSchema);

        $timeInfo = "\n\n[SYSTEM-ZEIT & AKTUELLE MISSION]\n" .
                    "Die aktuelle Systemzeit beim Verbindungsaufbau ist: " . now()->format('d.m.Y H:i:s') . " Uhr.\n" .
                    "HINWEIS: Da diese Verbindung länger offen bleiben kann, nutze das Tool `system_get_current_time` für die exakte, minutengenaue Uhrzeit, um zu prüfen, ob sich deine Anweisungen (wie Schlafenszeit) geändert haben.\n";
        
        if (AiAuthHelper::isAdmin()) {
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

                if (class_exists('\App\Services\AI\AIFunctionsRegistry')) {
                    $routineSnapshot = \App\Services\AI\AIFunctionsRegistry::executeGetDayRoutines([]);
                    if (($routineSnapshot['status'] ?? '') === 'success') {
                        $funkiEval = $routineSnapshot['funki_evaluation'] ?? [];
                        $currR = $routineSnapshot['current_active_routine'] ?? null;
                        $nextR = $routineSnapshot['next_upcoming_routine'] ?? null;
                        $timeInfo .= "\n[TAGESROUTINE & HABIT-TRACKER HEUTE ({$routineSnapshot['weekday']}, {$routineSnapshot['date']})]\n";
                        $timeInfo .= "- Modus: {$routineSnapshot['weekend_note']}" . (!empty($routineSnapshot['is_sick_day']) ? " | 🤒 KRANKHEITSTAG AKTIV (Medizin-Funki: Serien geschützt!)" : "") . "\n";
                        $timeInfo .= "- Fortschritt heute: {$routineSnapshot['completed_count']}/{$routineSnapshot['active_routines_count']} Routinen abgehakt (Fair-Score bis jetzt: {$routineSnapshot['progress_pct_fair']}% · Funki-Status: " . ($funkiEval['speech'] ?? 'Bereit') . ")\n";
                        if ($currR) {
                            $timeInfo .= "- Aktiver Routine-Block: {$currR['title']} ({$currR['time_window']} · Schritt: {$currR['current_step']} · " . ($currR['is_completed'] ? 'Bereits erledigt ✓' : 'Noch offen') . ")\n";
                        }
                        if ($nextR) {
                            $timeInfo .= "- Nächster Routine-Block: {$nextR['start_time']} Uhr {$nextR['title']} ({$nextR['duration_minutes']} Min.)\n";
                        }
                        $timeInfo .= "- Deine Routine-Werkzeuge: Nutze `routine_get_day_routines` (Tagesübersicht), `routine_get_evaluation` (Wochen-/Monats-/Jahresauswertung & Coaching-Tipps), `routine_log_progress` (Routine/Tag abhaken), `routine_log_step` (Unterschritt abhaken), `routine_set_sick_day` (Krankheits-/Genesungsmodus), `routine_create`, `routine_update`, `routine_delete`, `routine_manage_steps`.\n";
                    }
                }
            } catch (\Exception $e) {
                // Fallback falls der Service nicht erreichbar ist
            }
        }

        return [
            'model' => $agent->model ?? 'gemini-2.5-flash',
            'temperature' => (float) ($agent->temperature ?? 0.4),
            'system_prompt' => \App\Services\AI\AiPromptService::getRichPrompt($agent) . $timeInfo,
            'tools' => empty($filteredSchema) ? null : $filteredSchema,
        ];
    }
}
