<?php

namespace App\Services\AI\Functions;

use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Services\AI\AiAuthHelper;
use App\Services\AI\FunkiraPresenceContext;
use App\Services\AI\ProcurementSafetyGuard;
use Illuminate\Support\Str;

trait AiUniverseTicketFuncs
{
    public static function getAiUniverseTicketFuncsSchema(): array
    {
        return [
            [
                'name' => 'ticket_set_status_or_ask',
                'description' => 'Aktualisiert den Status des aktiven Universum-Tickets oder stellt Alina exakt 1 präzise Rückfrage bzw. 1-Klick-Freigabekarte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'UUID oder Ticket-Nummer (z. B. SF-0001) des Tickets.'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['in_progress', 'waiting_for_alina', 'completed'],
                            'description' => 'Zielstatus des Tickets.',
                        ],
                        'question_or_summary' => ['type' => 'string', 'description' => 'Kurze klare Frage oder Ergebnis-Zusammenfassung ohne KI-Gedankenstriche.'],
                        'options' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Optionale 1-Klick-Antwortoptionen für Alina.',
                        ],
                    ],
                    'required' => ['status', 'question_or_summary'],
                ],
                'callable' => [self::class, 'executeTicketSetStatusOrAsk'],
            ],
            [
                'name' => 'ticket_save_learned_preference',
                'description' => 'Speichert eine einmalig mit Alina geklärte Entscheidung dauerhaft in der Abteilungs-Datenbank (Einmal geklärt = Für immer gespeichert, nie wieder doppelt fragen).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'scope_key' => ['type' => 'string', 'description' => 'Eindeutiger Schlüssel, z. B. procurement:papierflachbeutel oder leitung:zahnarzt_dauer.'],
                        'department' => ['type' => 'string', 'description' => 'Abteilungsname (Leitung, Produkte, Marketing, Bestellungen, Buchhaltung, Support, System).'],
                        'subject_label' => ['type' => 'string', 'description' => 'Kurzer Titel der gelernten Regel.'],
                        'rule_summary' => ['type' => 'string', 'description' => 'Klare Regel in 1 bis 2 Sätzen ohne KI-Gedankenstriche.'],
                        'packaging_item_id' => ['type' => 'integer', 'description' => 'Optionale ID aus product_packaging_items bei Einkaufs-Präferenzen.'],
                        'preferred_packs' => ['type' => 'integer', 'description' => 'Optionale Standard-Gebindeanzahl bei Nachbestellungen.'],
                    ],
                    'required' => ['scope_key', 'subject_label', 'rule_summary'],
                ],
                'callable' => [self::class, 'executeTicketSaveLearnedPreference'],
            ],
            [
                'name' => 'ticket_escalate_missing_capability',
                'description' => 'Selbstständige Weiterleitung an Antigravity bei fehlendem Skill oder notwendiger Code-Erweiterung: Dokumentiert exakt, warum der Agent die Aufgabe nicht selbst lösen konnte, prüft in 0 Tokens das Skill-/Kontext-Budget und übergibt das Ticket sauber an Antigravity.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'Optionale UUID oder Ticket-Nummer des aktiven Tickets.'],
                        'missing_capability' => ['type' => 'string', 'description' => 'Welcher konkrete Skill oder welche Systemfunktion fehlt (1 kurzer Satz).'],
                        'why_blocked' => ['type' => 'string', 'description' => 'Warum der Agent die Aufgabe technisch nicht selbst abschließen konnte (1 kurzer Satz).'],
                        'affected_area' => ['type' => 'string', 'description' => 'Betroffener Systembereich oder Route.'],
                        'auto_handoff_to_antigravity' => ['type' => 'boolean', 'description' => 'Standard true: Leitet das Ticket direkt selbstständig inkl. Skill-Budget-Prüfung an Antigravity weiter.'],
                    ],
                    'required' => ['missing_capability'],
                ],
                'callable' => [self::class, 'executeTicketEscalateMissingCapability'],
            ],
            [
                'name' => 'procurement_evaluate_reorder',
                'description' => 'Führt den deterministischen 0-Token Einkaufs-Sicherheitscheck (Stamm-Artikel, 14-Tage-Doppelkaufsperre, Bestellverbrauch, 25,00 € Brutto-Grenze, 50,00 € Wochendeckel) vor jeder Nachbestellung aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_query' => ['type' => 'string', 'description' => 'Gesuchtes Material / Produkt, z. B. Papierflachbeutel.'],
                        'packs' => ['type' => 'integer', 'description' => 'Gewünschte Anzahl Gebinde/Packs (Standard: 1).'],
                    ],
                    'required' => ['item_query'],
                ],
                'callable' => [self::class, 'executeProcurementEvaluateReorder'],
            ],
            [
                'name' => 'procurement_queue_tresor_order',
                'description' => 'Reiht einen geprüften Einkauf in den isolierten Funkenwerk Tresor-Browser ein (unter 25,00 € Brutto auf LIVE für Stamm-Artikel direkt ausführbar, darüber als 1-Klick-Freigabe für Alina).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'Optionale UUID oder Ticket-Nummer des aktiven Einkaufs-Tickets.'],
                        'item_query' => ['type' => 'string', 'description' => 'Name oder SKU des Stamm-Artikels.'],
                        'packs' => ['type' => 'integer', 'description' => 'Anzahl Gebinde (Standard: 1).'],
                    ],
                    'required' => ['item_query'],
                ],
                'callable' => [self::class, 'executeProcurementQueueTresorOrder'],
            ],
        ];
    }

    public static function executeTicketSetStatusOrAsk(array $args): array
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        $status = (string) ($args['status'] ?? AiUniverseTicket::STATUS_WAITING_FOR_ALINA);
        if (!in_array($status, [
            AiUniverseTicket::STATUS_IN_PROGRESS,
            AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            AiUniverseTicket::STATUS_COMPLETED,
        ], true)) {
            $status = AiUniverseTicket::STATUS_WAITING_FOR_ALINA;
        }

        $options = array_values(array_filter(array_map('strval', (array) ($args['options'] ?? []))));
        $cleanReason = \App\Services\AI\AiOutputQualityGate::sanitizeText(Str::limit(trim((string) ($args['question_or_summary'] ?? '')), 360));

        if ($ticket) {
            $oldStatusLabel = AiUniverseTicket::getStatusLabel($ticket->status);
            $newStatusLabel = AiUniverseTicket::getStatusLabel($status);
            $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            if (!empty($options)) {
                $payload['type'] = 'quick_choice';
                $payload['options'] = array_slice($options, 0, 5);
            }
            $ticket->update([
                'status' => $status,
                'action_payload' => $payload ?: null,
                'last_activity_at' => now(),
                'completed_at' => $status === AiUniverseTicket::STATUS_COMPLETED ? now() : $ticket->completed_at,
            ]);

            if ($cleanReason !== '' && \Illuminate\Support\Facades\Schema::hasTable('ai_universe_ticket_messages')) {
                $closingInfo = $status === AiUniverseTicket::STATUS_COMPLETED
                    ? ' · Finale Aktion: Aufgabe abgeschlossen, Ticket wird ins Erledigt-Register gelegt.'
                    : '';
                \App\Models\Ai\AiUniverseTicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_role' => 'agent',
                    'sender_name' => $ticket->agent?->name ?: 'Funkira',
                    'ai_agent_id' => $ticket->assigned_agent_id,
                    'channel' => $ticket->source_channel ?: 'web_portal',
                    'content' => "**Status -> {$newStatusLabel}** (vorher: {$oldStatusLabel})\nGrund: {$cleanReason}{$closingInfo}",
                    'executed_tools' => ['ticket_set_status_or_ask'],
                ]);
                $ticket->update(['messages_count' => $ticket->messages()->count()]);
            }
        }

        return [
            'status' => 'success',
            'ticket_number' => $ticket?->ticket_number,
            'new_status' => $status,
            'summary' => $cleanReason,
            'options' => $options,
        ];
    }

    public static function executeTicketSaveLearnedPreference(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return ['error' => true, 'message' => 'Nur Administratoren dürfen dauerhafte Abteilungsregeln speichern.'];
        }

        $rawKey = strtolower(trim((string) ($args['scope_key'] ?? '')));
        $scopeKey = preg_replace('/[^a-z0-9:_\-äöüß]/iu', '_', $rawKey) ?: ('pref:' . Str::random(8));

        $deptName = trim((string) ($args['department'] ?? 'Leitung'));
        $deptId = AiDepartment::where('name', $deptName)->value('id');

        $structured = [];
        if (!empty($args['packaging_item_id'])) {
            $structured['packaging_item_id'] = (int) $args['packaging_item_id'];
        }
        if (!empty($args['preferred_packs'])) {
            $structured['preferred_packs'] = max(1, (int) $args['preferred_packs']);
        }

        $pref = AiLearnedPreference::updateOrCreate(
            ['scope_key' => Str::limit($scopeKey, 120, '')],
            [
                'ai_department_id' => $deptId,
                'subject_label' => Str::limit(trim((string) ($args['subject_label'] ?? $scopeKey)), 190, ''),
                'rule_summary' => trim((string) ($args['rule_summary'] ?? '')),
                'structured_value' => $structured ?: null,
                'is_active' => true,
            ]
        );

        return [
            'status' => 'success',
            'scope_key' => $pref->scope_key,
            'message' => 'Entscheidung dauerhaft gespeichert. Wird künftig ohne erneute Rückfrage angewendet.',
        ];
    }

    public static function executeTicketEscalateMissingCapability(array $args): array
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $missing = trim((string) ($args['missing_capability'] ?? 'Fehlende technische Funktion'));
        $whyBlocked = trim((string) ($args['why_blocked'] ?? "Kein passendes Ausführungswerkzeug im aktuellen Skill-Korb für '{$missing}' vorhanden."));
        $area = trim((string) ($args['affected_area'] ?? 'System'));
        $autoHandoff = !array_key_exists('auto_handoff_to_antigravity', $args) || (bool) $args['auto_handoff_to_antigravity'];

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        $reportingAgent = $ticket?->agent ?? \App\Models\Ai\AiAgent::where('name', 'Funkira')->first();
        $budget = \App\Services\AI\AgentSkillBudgetGuard::evaluateAgentSkillBudget($reportingAgent);
        $dossier = \App\Services\AI\AgentSkillBudgetGuard::formatAntigravitySkillGapDossier($budget, $missing, $whyBlocked, $area);

        if ($ticket) {
            if ($autoHandoff && AiAuthHelper::isAdmin()) {
                \App\Services\AI\FunkiraTicketRouter::routeTicketToAntigravity(
                    $ticket,
                    FunkiraPresenceContext::build(['channel' => $ticket->source_channel ?: 'web_portal']),
                    $dossier
                );
                $ticket->refresh();
                $p = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                $p['skill_gap_dossier'] = $budget;
                $p['missing_capability'] = $missing;
                $p['why_blocked'] = $whyBlocked;
                $ticket->update(['ticket_type' => 'capability_gap', 'action_payload' => $p]);

                return [
                    'status' => 'escalated_to_antigravity',
                    'ticket_number' => $ticket->ticket_number,
                    'missing_capability' => $missing,
                    'why_blocked' => $whyBlocked,
                    'skill_budget_check' => $budget,
                    'message' => "Ticket {$ticket->ticket_number} wurde selbstständig inkl. Skill-Erweiterungs-Dossier und Kontext-Prüfung ({$budget['current_tool_count']}/{$budget['max_tool_cap']} Tools) direkt an Antigravity übergeben.",
                ];
            }

            $ticket->update([
                'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                'ticket_type' => 'capability_gap',
                'action_payload' => [
                    'type' => 'antigravity_handoff',
                    'missing_capability' => $missing,
                    'why_blocked' => $whyBlocked,
                    'affected_area' => $area,
                    'skill_gap_dossier' => $budget,
                ],
                'last_activity_at' => now(),
            ]);
        }

        return [
            'status' => 'awaiting_antigravity_confirmation',
            'ticket_number' => $ticket?->ticket_number,
            'missing_capability' => $missing,
            'why_blocked' => $whyBlocked,
            'skill_budget_check' => $budget,
            'prompt_for_alina' => "Dafür fehlt aktuell der Skill '{$missing}' ({$budget['current_tool_count']}/{$budget['max_tool_cap']} Tools belegt). Soll ich das inkl. Skill-Anforderung an Antigravity übergeben?",
        ];
    }

    public static function executeProcurementEvaluateReorder(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return ['error' => true, 'message' => 'Einkaufs-Prüfungen sind ausschließlich der Geschäftsführung (Admin) vorbehalten.'];
        }

        $query = trim((string) ($args['item_query'] ?? ''));
        $packs = isset($args['packs']) ? (int) $args['packs'] : null;

        return ProcurementSafetyGuard::evaluateReorder($query, $packs);
    }

    public static function executeProcurementQueueTresorOrder(array $args): array
    {
        if (!AiAuthHelper::isAdmin()) {
            return ['error' => true, 'message' => 'Tresor-Einkäufe dürfen ausschließlich durch Admin ausgelöst werden.'];
        }

        $query = trim((string) ($args['item_query'] ?? ''));
        $packs = isset($args['packs']) ? (int) $args['packs'] : null;
        $eval = ProcurementSafetyGuard::evaluateReorder($query, $packs);

        if (empty($eval['found'])) {
            return $eval;
        }

        if (!empty($eval['duplicate_lock_triggered'])) {
            return [
                'status' => 'blocked_duplicate',
                'evaluation' => $eval,
                'message' => implode(' ', $eval['blocking_reasons']),
            ];
        }

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        $autoExec = !empty($eval['auto_execute_allowed']);
        $targetStatus = $autoExec
            ? AiUniverseTicket::STATUS_WAITING_FOR_TRESOR
            : AiUniverseTicket::STATUS_WAITING_FOR_ALINA;

        $payload = [
            'type' => 'tresor_procurement',
            'item_id' => $eval['item_id'],
            'item_name' => $eval['name'],
            'sku' => $eval['sku'],
            'supplier_name' => $eval['supplier_name'],
            'reorder_url' => $eval['reorder_url'],
            'reorder_domain' => $eval['reorder_domain'],
            'packs' => $eval['packs'],
            'total_units' => $eval['total_units'],
            'gross_total_cents' => $eval['gross_total_cents'],
            'gross_total_formatted' => $eval['gross_total_formatted'],
            'max_allowed_checkout_cents' => $eval['max_allowed_checkout_cents'],
            'delivery_address_guard' => $eval['delivery_address_guard'],
            'auto_executed_under_25' => $autoExec,
            'approved_by_alina' => $autoExec,
            'blocking_reasons' => $eval['blocking_reasons'],
        ];

        if ($ticket) {
            $ticket->update([
                'status' => $targetStatus,
                'ticket_type' => 'procurement',
                'action_payload' => $payload,
                'last_activity_at' => now(),
            ]);
        }

        return [
            'status' => $autoExec ? 'queued_auto_under_25_euro' : 'awaiting_alina_1click_approval',
            'ticket_number' => $ticket?->ticket_number,
            'target_status' => $targetStatus,
            'evaluation' => $eval,
        ];
    }

    protected static function resolveActiveUniverseTicket(?string $idOrNumber): ?AiUniverseTicket
    {
        if ($idOrNumber !== null && trim($idOrNumber) !== '') {
            $clean = trim($idOrNumber);
            $found = AiUniverseTicket::where('id', $clean)
                ->orWhere('ticket_number', strtoupper($clean))
                ->first();
            if ($found) {
                return $found;
            }
        }

        if (app()->bound('active_universe_ticket_id')) {
            $activeId = app('active_universe_ticket_id');
            if (is_string($activeId) && $activeId !== '') {
                return AiUniverseTicket::find($activeId);
            }
        }

        return null;
    }
}
