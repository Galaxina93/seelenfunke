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
                'description' => 'Aktualisiert Status und Bereich (Gewerblich vs. Privat) des aktiven Universum-Tickets, legt bei fehlendem aktivem Ticket optional ein neues Ticket an oder stellt Alina exakt 1 präzise Rückfrage.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'UUID oder Ticket-Nummer (z. B. SF-0001) des Tickets.'],
                        'title' => ['type' => 'string', 'description' => 'Optionaler Titel, falls im Live-Call ein neues Ticket angelegt werden soll.'],
                        'is_business' => ['type' => 'boolean', 'description' => 'true = Gewerblich (Mein-Seelenfunke Betrieb), false = Privat (Haus, Bad, Gesundheit, Rente, Familie, private Versicherungen).'],
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
            [
                'name' => 'ticket_attach_deliverable',
                'description' => 'Heftet ein erzeugtes Konzept (.md), eine Datei (.pdf, .docx, .csv, .xcs, .json) oder ein 3D-Druckmodell (.3mf, .stl, .glb) dauerhaft als Sticky-Anhang an die aktuelle Nachricht im Universum-Ticket an (inkl. Funkenwerk-3D-Vorschau & Download).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => ['type' => 'string', 'description' => 'Optionale UUID oder Ticket-Nummer (z. B. SF-0001).'],
                        'filename' => ['type' => 'string', 'description' => 'Dateiname mit Endung (z. B. Konzept_Spender.md, Klickplatte_BambuA1.3mf, Kalkulation.csv).'],
                        'content_text' => ['type' => 'string', 'description' => 'Text-/Markdown-Inhalt, wenn eine neue Konzept- oder Textdatei direkt erstellt werden soll.'],
                        'source_path' => ['type' => 'string', 'description' => 'Optionaler relativer Pfad innerhalb von storage/app/ einer bereits erzeugten Binär-/3D-/PDF-Datei.'],
                        'pinned' => ['type' => 'boolean', 'description' => 'Auf true setzen bei dauerhaften Referenz-Vorlagen (schützt vor der automatischen 4-Wochen-Archivierung).'],
                        'dimensions_mm' => [
                            'type' => 'object',
                            'description' => 'Optionale Außenmaße in Millimetern bei 3D-Modellen ({width, depth, height}).',
                            'properties' => [
                                'width' => ['type' => 'number'],
                                'depth' => ['type' => 'number'],
                                'height' => ['type' => 'number'],
                            ],
                        ],
                        'filament_grams' => ['type' => 'integer', 'description' => 'Optionaler geschätzter Filamentverbrauch in Gramm bei 3D-Druckvorlagen.'],
                    ],
                    'required' => ['filename'],
                ],
                'callable' => [self::class, 'executeTicketAttachDeliverable'],
            ],
            [
                'name' => 'print3d_evaluate_design_parameters',
                'description' => 'Pflicht-Prüfwerkzeug für 3D-Druck-Anfragen (Bambu Lab A1 256x256x256 mm): Prüft Bauraum, 0-%-Support-Geometrie (>=45 Grad), Materialwahl (PLA Matte vs. PETG/ASA), Wandlinien, Lightning-Infill-Einsparung, xTool-Strahlengang-Freiheit und deckt fehlende Pflicht-Parameter auf, die vor dem Modellieren hinterfragt werden müssen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'purpose' => ['type' => 'string', 'description' => 'Wofür soll das 3D-Bauteil eingesetzt werden (z. B. xTool Laser-Schablone, Regal-Organizer, Produkt-Aufsteller)?'],
                        'width_mm' => ['type' => 'number', 'description' => 'Geplante Breite in mm (0 falls noch unbekannt).'],
                        'depth_mm' => ['type' => 'number', 'description' => 'Geplante Tiefe in mm (0 falls noch unbekannt).'],
                        'height_mm' => ['type' => 'number', 'description' => 'Geplante Höhe in mm (0 falls noch unbekannt).'],
                        'clearance_above_mm' => ['type' => 'number', 'description' => 'Freie Eingreif-Luft nach oben im Fach/Regal in mm (falls in einem Fach stehend).'],
                        'exposed_to_heat_or_uv_laser' => ['type' => 'boolean', 'description' => 'Ist das Bauteil Wärme >50 Grad, Dauerspannung oder dem xTool 355nm UV-Laserstrahl ausgesetzt?'],
                        'has_spring_clips' => ['type' => 'boolean', 'description' => 'Besitzt das Bauteil federnde Zentrierlippen / Klickfedern für Rohling-Toleranzen?'],
                    ],
                    'required' => ['purpose'],
                ],
                'callable' => [self::class, 'executePrint3dEvaluateDesignParameters'],
            ],
        ];
    }

    public static function executeTicketSetStatusOrAsk(array $args): array
    {
        $isAdmin = AiUniverseTicket::isStrictAdminActor();
        if (!$isAdmin && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        if ($ticket && !$ticket->is_business && !$isAdmin) {
            return ['error' => true, 'message' => 'Zugriff verweigert: Private Tickets sind ausschließlich Administratoren vorbehalten.'];
        }

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
        $cleanTitle = \App\Services\AI\AiOutputQualityGate::sanitizeText(Str::limit(trim((string) ($args['title'] ?? '')), 180));
        $hasBizCol = \Illuminate\Support\Facades\Schema::hasTable('ai_universe_tickets')
            && \Illuminate\Support\Facades\Schema::hasColumn('ai_universe_tickets', 'is_business');

        // Optionales Neu-Anlegen eines Tickets (z. B. privates Nachverfolgungs-Ticket direkt aus dem Live-Call)
        if (!$ticket && $cleanTitle !== '' && \Illuminate\Support\Facades\Schema::hasTable('ai_universe_tickets')) {
            $triage = \App\Services\AI\FunkiraTicketRouter::classifyIntent($cleanTitle . ' ' . $cleanReason);
            $resolvedBiz = $isAdmin
                ? (array_key_exists('is_business', $args) && $args['is_business'] !== null ? (bool) $args['is_business'] : $triage['is_business'])
                : true;
            $dept = AiDepartment::where('name', $triage['department_name'])->first()
                ?? AiDepartment::where('name', 'Leitung')->first();
            $agent = \App\Models\Ai\AiAgent::where('name', $triage['agent_name'])->where('is_active', true)->first()
                ?? \App\Models\Ai\AiAgent::where('name', 'Funkira')->first();

            $createPayload = [
                'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                'title' => $cleanTitle,
                'status' => $status,
                'priority' => $triage['priority'],
                'ticket_type' => $resolvedBiz ? $triage['ticket_type'] : ($triage['ticket_type'] === 'management' ? 'private_household' : $triage['ticket_type']),
                'ai_department_id' => $dept?->id,
                'assigned_agent_id' => $agent?->id,
                'assigned_to_antigravity' => false,
                'source_channel' => 'android_live_call',
                'environment_tier' => FunkiraPresenceContext::resolveEnvironmentTier(),
                'messages_count' => 0,
                'last_activity_at' => now(),
                'completed_at' => $status === AiUniverseTicket::STATUS_COMPLETED ? now() : null,
            ];
            if ($hasBizCol) {
                $createPayload['is_business'] = $resolvedBiz;
            }
            $ticket = AiUniverseTicket::create($createPayload);
        }

        if ($ticket) {
            $oldStatusLabel = AiUniverseTicket::getStatusLabel($ticket->status);
            $newStatusLabel = AiUniverseTicket::getStatusLabel($status);
            $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            if (!empty($options)) {
                $payload['type'] = 'quick_choice';
                $payload['options'] = array_slice($options, 0, 5);
            }
            $updates = [
                'status' => $status,
                'action_payload' => $payload ?: null,
                'last_activity_at' => now(),
                'completed_at' => $status === AiUniverseTicket::STATUS_COMPLETED ? now() : $ticket->completed_at,
            ];
            if ($hasBizCol && $isAdmin && array_key_exists('is_business', $args) && $args['is_business'] !== null) {
                $updates['is_business'] = (bool) $args['is_business'];
            }
            $ticket->update($updates);

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

            if ($status === AiUniverseTicket::STATUS_COMPLETED) {
                \App\Services\AI\CentralBrainService::recordTicketCompletionReport($ticket, $cleanReason);
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

    public static function executeTicketEscalateMissingCapability(array $args, $agent = null): array
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        $reportingAgent = $agent ?? $ticket?->agent ?? \App\Models\Ai\AiAgent::where('name', 'Funkira')->first();

        // Harter Unveränderlichkeits-Schutz für Funki (Frontend-Support): niemals neue Fähigkeiten über Antigravity!
        if (\App\Services\AI\AgentSkillBudgetGuard::isFunkiAgent($reportingAgent)) {
            return [
                'error' => true,
                'status' => 'blocked_funki_immutable',
                'message' => "GESETZ-SPERRE: Der externe Frontend-Support-Agent 'Funki' ist fest eingefroren und darf niemals durch Antigravity neue Fähigkeiten oder Rollenrechte erhalten.",
            ];
        }

        $missing = trim((string) ($args['missing_capability'] ?? 'Fehlende technische Funktion'));
        $whyBlocked = trim((string) ($args['why_blocked'] ?? "Kein passendes Ausführungswerkzeug im aktuellen Skill-Korb für '{$missing}' vorhanden."));
        $area = trim((string) ($args['affected_area'] ?? 'System'));
        $autoHandoff = !array_key_exists('auto_handoff_to_antigravity', $args) || (bool) $args['auto_handoff_to_antigravity'];

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

    public static function executeTicketAttachDeliverable(array $args): array
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $ticket = self::resolveActiveUniverseTicket($args['ticket_id'] ?? null);
        $filename = trim((string) ($args['filename'] ?? ''));
        if ($filename === '') {
            return ['error' => true, 'message' => 'Dateiname fehlt.'];
        }

        $extraMeta = [
            'pinned' => !empty($args['pinned']),
        ];
        if (!empty($args['dimensions_mm']) && is_array($args['dimensions_mm'])) {
            $extraMeta['dimensions_mm'] = $args['dimensions_mm'];
        }
        if (!empty($args['filament_grams'])) {
            $extraMeta['filament_grams'] = (int) $args['filament_grams'];
        }

        $meta = null;
        $sourceRel = trim(str_replace('\\', '/', (string) ($args['source_path'] ?? '')), '/');

        if ($sourceRel !== '' && !str_contains($sourceRel, '..')) {
            $absCandidate = storage_path('app/' . $sourceRel);
            if (!is_file($absCandidate)) {
                $absCandidate = storage_path('app/public/' . ltrim(preg_replace('/^storage\//', '', $sourceRel) ?? $sourceRel, '/'));
            }
            if (is_file($absCandidate) && !is_link($absCandidate)) {
                $meta = \App\Services\AI\TicketDeliverableService::registerLocalFileForActiveTicket(
                    $absCandidate,
                    $filename,
                    $extraMeta,
                    $ticket?->id
                );
            }
        } elseif (isset($args['content_text']) && trim((string) $args['content_text']) !== '') {
            $cleanText = \App\Services\AI\AiOutputQualityGate::sanitizeText((string) $args['content_text']);
            $meta = \App\Services\AI\TicketDeliverableService::storeBytesForTicket(
                $cleanText,
                $filename,
                $ticket?->ticket_number,
                $extraMeta
            );
            if ($meta !== null && $ticket) {
                \App\Services\AI\TicketDeliverableService::queueAttachmentForTicket($ticket->id, $meta);
            }
        }

        if ($meta === null) {
            return [
                'error' => true,
                'message' => 'Datei konnte nicht gespeichert werden (ungültige Dateiendung, leerer Inhalt oder Quelldatei nicht gefunden).',
            ];
        }

        // Falls außerhalb eines laufenden Router-Turns aufgerufen -> direkt als eigene Sticky-Nachricht ans Ticket hängen
        if ($ticket && !app()->bound('active_universe_ticket_id') && \Illuminate\Support\Facades\Schema::hasTable('ai_universe_ticket_messages')) {
            \App\Services\AI\TicketDeliverableService::pullQueuedAttachments($ticket->id);
            \App\Models\Ai\AiUniverseTicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_role' => 'agent',
                'sender_name' => $ticket->agent?->name ?: 'Antigravity',
                'ai_agent_id' => $ticket->assigned_agent_id,
                'channel' => $ticket->source_channel ?: 'web_portal',
                'content' => "Datei **{$meta['name']}** ({$meta['size_human']}) wurde bereitgestellt und direkt angeheftet.",
                'executed_tools' => ['ticket_attach_deliverable'],
                'attachments' => [$meta],
            ]);
            $ticket->update([
                'messages_count' => $ticket->messages()->count(),
                'last_activity_at' => now(),
            ]);
        }

        return [
            'status' => 'success',
            'ticket_number' => $ticket?->ticket_number,
            'attachment' => $meta,
        ];
    }

    public static function executePrint3dEvaluateDesignParameters(array $args): array
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            return ['error' => true, 'message' => 'Zugriff verweigert.'];
        }

        $purpose = trim((string) ($args['purpose'] ?? '3D-Bauteil'));
        $w = (float) ($args['width_mm'] ?? 0);
        $d = (float) ($args['depth_mm'] ?? 0);
        $h = (float) ($args['height_mm'] ?? 0);
        $clearance = isset($args['clearance_above_mm']) ? (float) $args['clearance_above_mm'] : null;
        $heatOrLaser = !empty($args['exposed_to_heat_or_uv_laser']);
        $hasSprings = !empty($args['has_spring_clips']);

        $bambuMax = 256.0;
        $questionsToAskAlina = [];
        $engineeringWarnings = [];

        if ($w <= 0 || $d <= 0 || $h <= 0) {
            $questionsToAskAlina[] = 'Exakte Außen-/Fachmaße (Breite x Tiefe x Höhe in mm per Messschieber/Zollstock inkl. Scharnier- oder Leisten-Überständen)';
        } else {
            if ($w > $bambuMax || $d > $bambuMax || $h > $bambuMax) {
                $engineeringWarnings[] = sprintf(
                    'Maße (%.1f x %.1f x %.1f mm) überschreiten den Monoblock-Bauraum des Bambu Lab A1 (256 x 256 x 256 mm): Kompaktere Hybrid-Geometrie vorschlagen statt instabilem Klebe-/Stecksystem.',
                    $w,
                    $d,
                    $h
                );
            }
        }

        if ($clearance !== null && $clearance > 0 && $clearance < 100.0) {
            $engineeringWarnings[] = sprintf(
                'Nur %.1f mm freie Eingreif-Luft nach oben (< 100 mm): Unbedingt gestufte Vorder-Tribüne planen, damit man nicht blind über hohe Rückwände greifen muss.',
                $clearance
            );
        }

        $lowerPurpose = mb_strtolower($purpose);
        $isLaserJig = $heatOrLaser || str_contains($lowerPurpose, 'xtool') || str_contains($lowerPurpose, 'laser') || str_contains($lowerPurpose, 'schablone') || str_contains($lowerPurpose, 'klickplatte');

        if ($isLaserJig) {
            $engineeringWarnings[] = 'xTool F2 Ultra UV Pflicht: Offenes Durchschussfenster (0 mm Kunststoff unter der Gravurfläche gegen 355nm-Schmauch), 140 x 140 mm M4-Lochraster am Boden und freischwebende Aussparung für angeklebte Glasfüße.';
            if (!$hasSprings) {
                $questionsToAskAlina[] = 'Schwanken die Lieferanten-Rohlinge im Außenmaß (±1,5 mm bei K9-Glas/Schiefer), sodass wir 4 symmetrische 1,6 mm Zentrier-Blattfedern einplanen sollen?';
            }
        }

        $recommendedMaterial = ($heatOrLaser && !$isLaserJig)
            ? 'Bambu PETG HF / ASA (Wärme- & Dauerlaststabil > 50 Grad)'
            : 'Bambu PLA Matte Charcoal 11101 (höchste Maßhaltigkeit, edles Matt-Finish, kein Warping)';

        $wallLoops = ($hasSprings || $isLaserJig) ? 3 : 2;
        $infill = $isLaserJig ? '15 % Gyroid' : '10 % Lightning (Blitz-Füllung, spart ~35 % Filament & Druckzeit)';

        return [
            'status' => 'evaluated',
            'printer_target' => 'Bambu Lab A1 (N2S · 256 x 256 x 256 mm · 0,4 mm Düse · Textured PEI Plate)',
            'fits_monoblock_256mm' => ($w > 0 && $d > 0 && $h > 0) ? ($w <= $bambuMax && $d <= $bambuMax && $h <= $bambuMax) : null,
            'recommended_material' => $recommendedMaterial,
            'recommended_slicer_profile' => [
                'layer_height_mm' => 0.20,
                'wall_loops' => $wallLoops,
                'sparse_infill' => $infill,
                'support_structures' => 'AUS (0 % Support durch >= 45 Grad Schrägen/Spitzbögen & Rückseiten-Stützgewölbe)',
                'triangle_budget' => '< 900.000 Dreiecke (Manifold 0 offene Kanten, verhindert Bambu-Vereinfachen-Reset)',
                'elephants_foot_chamfer_mm' => 0.5,
            ],
            'engineering_warnings' => $engineeringWarnings,
            'missing_parameters_to_ask_alina' => $questionsToAskAlina,
            'next_step_directive' => !empty($questionsToAskAlina)
                ? 'NICHT blind modellieren! Stelle Alina im Ticket kurz & knackig die offenen Parameter-Fragen via ticket_set_status_or_ask (status=waiting_for_alina) und schlage direkt die sinnvollste Geometrie vor.'
                : 'Alle Kernmaße plausibel: Erstelle eine kompakte 3D-Konzeptkarte und übergib die CAD/.3mf-Generierung bei Go an @Antigravity.',
        ];
    }
}
