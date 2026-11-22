<?php

namespace App\Services\AI;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementTask;
use App\Models\Order\OrderOrder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FunkiraTicketRouter
{
    /**
     * Zentraler Universum-Eingang für alle 6 Kanäle (Web-Portal, Seiten-Widget, Android-App,
     * Android 3D Live-Call, Funkenwerk Desktop und Werkstatt Mini-PC).
     *
     * @param array<string, mixed> $rawPresence
     * @param array<int, array<string, mixed>> $attachments
     * @return array<string, mixed>
     */
    public static function dispatchMessage(
        string $prompt,
        array $rawPresence = [],
        ?string $existingTicketId = null,
        array $attachments = []
    ): array {
        $cleanPrompt = trim($prompt);
        $presence = FunkiraPresenceContext::build($rawPresence);

        if ($cleanPrompt === '' && empty($attachments)) {
            return [
                'status' => 'ignored_empty',
                'mode' => 'noop',
                'reply' => 'Sag mir kurz, wobei ich dir helfen soll, Alina.',
                'ticket' => null,
                'presence' => $presence,
            ];
        }

        // 1. Harte Zero-PII Besucher-Sperre am Werkstatt Mini-PC (0 Tokens!)
        if (!empty($presence['visitor_sandbox_locked'])) {
            return [
                'status' => 'visitor_locked',
                'mode' => 'visitor_sandbox_0_token',
                'reply' => 'Hallo im Seelenfunke-Atelier! Da Alina gerade nicht persönlich im Kamerabild ist, ist mein geschützter Besuchermodus aktiv. Interne Firmendaten und Aktionen sind gesperrt, aber ich richte ihr gerne aus, dass jemand da war.',
                'ticket' => null,
                'presence' => $presence,
            ];
        }

        // 2. Falls kein explizites Ticket übergeben wurde: Prüfe, ob eine 1-Wort-Bestätigung ("Ja", "Go", "Freigeben") zu einem offenen Rückfrage-Ticket gehört
        $targetTicket = self::resolveExistingOrPendingReplyTicket($existingTicketId, $cleanPrompt);
        if ($targetTicket && $targetTicket->status === AiUniverseTicket::STATUS_WAITING_FOR_ALINA) {
            $tp = is_array($targetTicket->action_payload) ? $targetTicket->action_payload : [];
            if (($tp['type'] ?? '') === 'ag_inbox_blockade' && !empty($tp['ag_item_id'])) {
                $normReply = mb_strtolower(trim(preg_replace('/[!?.]+$/u', '', $cleanPrompt) ?? $cleanPrompt));
                $decision = in_array($normReply, ['nein', 'verwerfen', 'ablehnen', 'abbrechen', 'stornieren'], true)
                    ? 'dismissed'
                    : (self::isShortConfirmation($cleanPrompt) ? 'approved' : 'info_provided');
                app(\App\Services\System\AntigravityDirectInboxService::class)->resolveBlockadeItem(
                    (string) $tp['ag_item_id'],
                    $decision,
                    $cleanPrompt,
                    'Alina'
                );
                return [
                    'status' => 'ok',
                    'mode' => 'ag_blockade_resolved',
                    'reply' => $decision === 'dismissed'
                        ? "Konzept **{$targetTicket->ticket_number}** wurde verworfen und im Archiv protokolliert."
                        : "Freigabe & Antwort zu **{$targetTicket->ticket_number}** wurde direkt an **Antigravity** übermittelt.",
                    'ticket' => $targetTicket->fresh(['department', 'agent', 'messages']),
                    'presence' => $presence,
                ];
            }
            if (self::isShortConfirmation($cleanPrompt)) {
                return self::handleOneWordConfirmation($targetTicket, $cleanPrompt, $presence);
            }
        }

        // 3. 0-Token Sofortreflex ("Fast-Lane") für reine Gerätesteuerung & Kurzstatus (kein Ticket-Müll!)
        if ($targetTicket === null && empty($attachments)) {
            $fastLane = self::tryZeroTokenFastLane($cleanPrompt, $presence);
            if ($fastLane !== null) {
                return $fastLane;
            }
        }

        // 4. Deterministische 0-Token Triage (Abteilung, Spezialist & Ticket-Typ)
        $triage = self::classifyIntent($cleanPrompt, $targetTicket);
        $department = AiDepartment::where('name', $triage['department_name'])->first()
            ?? AiDepartment::where('name', 'Leitung')->first();
        $agent = AiAgent::where('name', $triage['agent_name'])->where('is_active', true)->first()
            ?? AiAgent::where('name', 'Funkira')->first();

        $isNewTicket = ($targetTicket === null);
        if ($isNewTicket) {
            $titlePrefix = $presence['environment_tier'] === FunkiraPresenceContext::ENV_STAGE ? '[STAGE] ' : '';
            $targetTicket = AiUniverseTicket::create([
                'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                'title' => Str::limit($titlePrefix . self::buildCleanTitle($cleanPrompt, $triage['ticket_type']), 180),
                'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
                'priority' => $triage['priority'],
                'ticket_type' => $triage['ticket_type'],
                'ai_department_id' => $department?->id,
                'assigned_agent_id' => $agent?->id,
                'assigned_to_antigravity' => $triage['assign_antigravity'],
                'source_channel' => $presence['channel'],
                'environment_tier' => $presence['environment_tier'],
                'messages_count' => 0,
                'presence_snapshot' => $presence,
                'last_activity_at' => now(),
            ]);
        } else {
            $targetTicket->update([
                'presence_snapshot' => $presence,
                'last_activity_at' => now(),
            ]);
            if ($targetTicket->agent) {
                $agent = $targetTicket->agent;
            }
        }

        // Protokolliere Alinas Nachricht chronologisch im Ticket
        AiUniverseTicketMessage::create([
            'ticket_id' => $targetTicket->id,
            'sender_role' => 'alina',
            'sender_name' => 'Alina',
            'channel' => $presence['channel'],
            'content' => $cleanPrompt !== '' ? $cleanPrompt : '(Anhang hochgeladen)',
            'attachments' => !empty($attachments) ? $attachments : null,
        ]);
        $targetTicket->increment('messages_count');

        // 5a. Direkter Entwicklungsauftrag für Antigravity (0 Tokens Triage)
        if ($triage['assign_antigravity'] && $isNewTicket) {
            return self::routeTicketToAntigravity($targetTicket, $presence, 'Vorqualifizierter Entwicklungsauftrag aus ' . $presence['channel_label']);
        }

        // 5b. Deterministischer 0-Token Einkaufs-Pfad ("Wir brauchen noch weitere Papierflachbeutel")
        if ($triage['ticket_type'] === 'procurement' && $isNewTicket) {
            return self::handleZeroTokenProcurementFlow($targetTicket, $cleanPrompt, $presence, $agent);
        }

        // 6. Fokussierter Spezialisten-Lauf (mit Orts-/Umgebungs-Header, flachem SQL-Wissen, Auto-Compact & Anti-Bluff-Gate)
        return self::executeSpecialistOnTicket($targetTicket, $cleanPrompt, $presence, $agent);
    }

    /**
     * Führt 1-Klick-Aktionen direkt aus dem Ticket-UI oder der App/Funkenwerk-API in 0 Tokens aus.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function performTicketAction(AiUniverseTicket $ticket, string $action, array $params = []): array
    {
        $channel = (string) ($params['channel'] ?? 'web_portal');

        return match ($action) {
            'handoff_antigravity' => self::routeTicketToAntigravity(
                $ticket,
                FunkiraPresenceContext::build(['channel' => $channel]),
                (string) ($params['note'] ?? 'Per 1-Klick an Antigravity übergeben.')
            ),
            'approve_procurement' => self::approveProcurementTicket($ticket, $channel),
            'approve_ag_blockade' => self::resolveAgBlockadeFromTicketUi($ticket, 'approved', (string) ($params['note'] ?? 'Go im Ticketsystem erteilt')),
            'dismiss_ag_blockade' => self::resolveAgBlockadeFromTicketUi($ticket, 'dismissed', (string) ($params['note'] ?? 'Im Ticketsystem verworfen')),
            'complete' => self::markTicketStatus($ticket, AiUniverseTicket::STATUS_COMPLETED, 'Ticket abgeschlossen.', $channel),
            'archive' => self::markTicketStatus($ticket, AiUniverseTicket::STATUS_ARCHIVED, 'Ticket archiviert.', $channel),
            'reopen' => self::markTicketStatus($ticket, AiUniverseTicket::STATUS_IN_PROGRESS, 'Ticket wieder geöffnet.', $channel),
            default => [
                'status' => 'error',
                'reply' => "Unbekannte Ticket-Aktion '{$action}'.",
                'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            ],
        };
    }

    /**
     * Lädt flaches Abteilungs-Wissen & gelernte Präferenzen per schnellem SQL-Index-Select (< 5 ms).
     */
    public static function loadFlatDepartmentKnowledge(?string $departmentId): string
    {
        $blocks = [];

        if (Schema::hasTable('ai_learned_preferences')) {
            $prefs = AiLearnedPreference::query()
                ->where('is_active', true)
                ->when($departmentId, function ($q) use ($departmentId) {
                    $q->where(function ($sq) use ($departmentId) {
                        $sq->where('ai_department_id', $departmentId)
                            ->orWhereNull('ai_department_id');
                    });
                })
                ->latest('updated_at')
                ->limit(8)
                ->get(['subject_label', 'rule_summary']);

            if ($prefs->isNotEmpty()) {
                $lines = ['[GELERNTE DAUER-ENTSCHEIDUNGEN VON ALINA (NICHT ERNEUT FRAGEN!)]:'];
                foreach ($prefs as $p) {
                    $lines[] = "- {$p->subject_label}: {$p->rule_summary}";
                }
                $blocks[] = implode("\n", $lines);
            }
        }

        if ($departmentId && Schema::hasTable('ai_knowledge_bases') && Schema::hasColumn('ai_knowledge_bases', 'title')) {
            try {
                $kbQuery = AiKnowledgeBase::query()->latest('updated_at')->limit(5);
                if (Schema::hasColumn('ai_knowledge_bases', 'is_active')) {
                    $kbQuery->where('is_active', true);
                }
                $entries = $kbQuery->get(['title', 'content']);
                if ($entries->isNotEmpty()) {
                    $kbLines = ['[KOMPAKTES FIRMENWISSEN]:'];
                    foreach ($entries as $entry) {
                        $kbLines[] = '- ' . $entry->title . ': ' . Str::limit(trim(strip_tags((string) $entry->content)), 180);
                    }
                    $blocks[] = implode("\n", $kbLines);
                }
            } catch (\Throwable) {
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * 0-Token Sofortreflex für Licht, Musik & Kurzstatus ohne Ticket-Erzeugung.
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>|null
     */
    protected static function tryZeroTokenFastLane(string $prompt, array $presence): ?array
    {
        $lower = mb_strtolower(trim($prompt));

        // 1. Licht / Smart-Home Kurzkommandos
        if (preg_match('/^(?:bitte\s+)?(?:alle\s+)?(?:licht(?:er)?|lichterkette(?:n)?|packtisch(?:\s+warm\s+licht)?|deckenfluter)\s+(an|ein|aus|ausschalten|einschalten)$/iu', $lower, $m)) {
            $turnOn = in_array(mb_strtolower($m[1]), ['an', 'ein', 'einschalten'], true);
            $action = str_contains($lower, 'alle')
                ? ($turnOn ? 'turn_all_on' : 'turn_all_off')
                : ($turnOn ? 'turn_on' : 'turn_off');

            try {
                $funkira = AiAgent::where('name', 'Funkira')->first();
                AIFunctionsRegistry::execute('smarthome_control_plug', [
                    'action' => $action,
                    'device_name' => $prompt,
                ], $funkira);
            } catch (\Throwable) {
            }

            return [
                'status' => 'ok',
                'mode' => 'fast_lane_0_token',
                'reply' => $turnOn ? 'Erledigt, Alina: Licht ist eingeschaltet.' : 'Erledigt, Alina: Licht ist ausgeschaltet.',
                'ticket' => null,
                'presence' => $presence,
            ];
        }

        // 2. Kurzes Status-Ping ("Funkira?", "Kurzstatus", "Tagesbriefing")
        if (in_array($lower, ['funkira', 'funkira?', 'bist du da?', 'status', 'kurzstatus', 'briefing'], true)) {
            $openTasks = Schema::hasTable('management_tasks')
                ? ManagementTask::where('is_completed', false)->count()
                : 0;
            $todayEvents = Schema::hasTable('management_calendar_events')
                ? ManagementCalendarEvent::whereDate('start_date', today())->count()
                : 0;
            $waitingTickets = Schema::hasTable('ai_universe_tickets')
                ? AiUniverseTicket::where('status', AiUniverseTicket::STATUS_WAITING_FOR_ALINA)->count()
                : 0;
            $openOrders = Schema::hasTable('order_orders')
                ? OrderOrder::whereIn('status', ['paid', 'processing', 'in_production'])->count()
                : 0;

            $envBadge = $presence['environment_tier'];
            $locBadge = $presence['channel_label'];

            return [
                'status' => 'ok',
                'mode' => 'fast_lane_0_token',
                'reply' => "Ja, Alina! Ich bin hier im {$locBadge} ({$envBadge}). Aktuell: {$waitingTickets} Rückfrage-Tickets, {$openOrders} offene Manufaktur-Aufträge, {$todayEvents} Termine heute und {$openTasks} offene To-Dos.",
                'ticket' => null,
                'presence' => $presence,
            ];
        }

        return null;
    }

    /**
     * Deterministische 0-Token Klassifizierung für Abteilung & zuständigen Agenten.
     *
     * @return array{department_name: string, agent_name: string, ticket_type: string, priority: string, assign_antigravity: bool}
     */
    public static function classifyIntent(string $prompt, ?AiUniverseTicket $existingTicket = null): array
    {
        if ($existingTicket && $existingTicket->department && $existingTicket->agent) {
            return [
                'department_name' => $existingTicket->department->name,
                'agent_name' => $existingTicket->agent->name,
                'ticket_type' => $existingTicket->ticket_type,
                'priority' => $existingTicket->priority,
                'assign_antigravity' => (bool) $existingTicket->assigned_to_antigravity,
            ];
        }

        $lower = mb_strtolower($prompt);

        // Expliziter Antigravity-/Code-/Entwicklungs-Auftrag
        if (preg_match('/\b(antigravity|programmier\w*|code\s+änder\w*|livewire|migration|seeder|bugfix|komponente\s+bauen)\b/iu', $lower)) {
            return [
                'department_name' => 'System',
                'agent_name' => 'Antigravity',
                'ticket_type' => 'antigravity_dev',
                'priority' => 'normal',
                'assign_antigravity' => true,
            ];
        }

        // Einkauf / Nachbestellung ("Wir brauchen noch weitere Papierflachbeutel", Kartons, Rohlinge, Schmuckboxen)
        if (preg_match('/\b(nachbestell\w*|papierflachbeutel|flachbeutel|versandkarton\w*|schmuckbox\w*|geschenkbox\w*|luftpolster\w*|rohling\w*\s+bestell\w*|wir\s+brauchen\s+(?:noch\s+)?(?:weitere\s+|neue\s+)?[a-zäöüß]*beutel|verpackung\w*\s+kaufen|westpack|karton\.eu|natürlich-verpacken)\b/iu', $lower)) {
            return [
                'department_name' => 'Buchhaltung',
                'agent_name' => 'Einkaufi',
                'ticket_type' => 'procurement',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Buchhaltung / Finanzen / Belege / Steuern
        if (preg_match('/\b(beleg\w*|quittung|sonderausgabe\w*|fixkosten|umsatzsteuer|steuerberater|datev|buchhaltung|fyrst|stripe\s+auszahlung|gutschrift\w*|rechnung\s+buchen)\b/iu', $lower)) {
            return [
                'department_name' => 'Buchhaltung',
                'agent_name' => 'Buchi',
                'ticket_type' => 'finance',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Laser-Manufaktur (Lasi)
        if (preg_match('/\b(laser\w*|xtool|brennpunkt|fokus|gravurprofil\w*|ra3|conveyor|abluft|tros|pulsdauer)\b/iu', $lower)) {
            return [
                'department_name' => 'Produkte',
                'agent_name' => 'Lasi',
                'ticket_type' => 'laser',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Produkte & Konfigurator (Produkti)
        if (preg_match('/\b(produkt\w*|konfigurator|vorlage\w*|edelstahl|anhänger|seelen-pfote|seelen-kristall|bewertung\w*|bruchquote)\b/iu', $lower)
            && !str_contains($lower, 'bestellung')) {
            return [
                'department_name' => 'Produkte',
                'agent_name' => 'Produkti',
                'ticket_type' => 'general',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Bestellungen, Versand & Widerruf (Bestelli)
        if (preg_match('/\b(bestellung\w*|versand|dhl|sendungsverfolgung|tracking|widerruf\w*|retoure\w*|express-auftrag|ord-)\b/iu', $lower)) {
            return [
                'department_name' => 'Bestellungen',
                'agent_name' => 'Bestelli',
                'ticket_type' => 'order',
                'priority' => str_contains($lower, 'express') ? 'express' : 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Marketing, Blog, SEO, Gutscheine (Marketi)
        if (preg_match('/\b(blog\w*|magazin|seo|landingpage\w*|newsletter|gutschein\w*|rabattcode|influencer|herzensprojekt\w*|linktree)\b/iu', $lower)) {
            return [
                'department_name' => 'Marketing',
                'agent_name' => 'Marketi',
                'ticket_type' => 'marketing',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Gesundheit & Tagesroutine (Dr. Funki in Leitung)
        if (preg_match('/\b(medikament\w*|dosis|arzt\w*|praxis|krankenkasse|bkk\s+firmus|krankengeld|tagesroutine|pause\w*)\b/iu', $lower)) {
            return [
                'department_name' => 'Leitung',
                'agent_name' => 'Dr. Funki',
                'ticket_type' => 'management',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Marktrecherche & Strategie (Globi in Leitung)
        if (preg_match('/\b(marktrecherche|nischen\w*|wettbewerb\w*|konkurrenz|reiseplanung|urlaub)\b/iu', $lower)) {
            return [
                'department_name' => 'Leitung',
                'agent_name' => 'Globi',
                'ticket_type' => 'management',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // System, Server, Backups, Cronjobs (Systemi)
        if (preg_match('/\b(server-status|cronjob\w*|backup\w*|sicherung\w*|fehler-log\w*|mittwald|ssl-zertifikat)\b/iu', $lower)) {
            return [
                'department_name' => 'System',
                'agent_name' => 'Systemi',
                'ticket_type' => 'system_error',
                'priority' => 'normal',
                'assign_antigravity' => false,
            ];
        }

        // Standard: Stabschefin Funkira in Leitung (Kalender, Aufgaben, E-Mails, Kontakte, Koordination)
        return [
            'department_name' => 'Leitung',
            'agent_name' => 'Funkira',
            'ticket_type' => 'management',
            'priority' => 'normal',
            'assign_antigravity' => false,
        ];
    }

    /**
     * Verarbeitet Nachbestellungen ("Wir brauchen noch weitere Papierflachbeutel") deterministisch
     * über das 6-stufige 0-Token-Schutznetz (`ProcurementSafetyGuard`).
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>
     */
    protected static function handleZeroTokenProcurementFlow(
        AiUniverseTicket $ticket,
        string $prompt,
        array $presence,
        ?AiAgent $agent
    ): array {
        $itemQuery = self::extractProcurementItemQuery($prompt);
        $requestedPacks = null;
        if (preg_match('/\b(\d{1,2})\s*(?:pack(?:ung(?:en)?|s)?|karton(?:s)?|mal|x)\b/iu', $prompt, $pm)) {
            $requestedPacks = max(1, (int) $pm[1]);
        }

        $eval = ProcurementSafetyGuard::evaluateReorder(
            $itemQuery,
            $requestedPacks,
            $presence['environment_tier'] ?? null
        );

        if (empty($eval['found'])) {
            $reply = "Für **{$itemQuery}** ist aktuell noch kein Stamm-Artikel mit verifizierter Händler-URL hinterlegt. Sag mir einmalig deinen gewünschten Händler-Link und die Packungsgröße, dann speichere ich das dauerhaft für alle künftigen Nachbestellungen ab.";
            $actionPayload = [
                'type' => 'clarify_new_procurement_item',
                'query' => $itemQuery,
            ];
            $targetStatus = AiUniverseTicket::STATUS_WAITING_FOR_ALINA;
        } elseif (!empty($eval['duplicate_lock_triggered'])) {
            $reply = "**Sicherheits-Stopp (Kein Doppelkauf):** {$eval['name']} ({$eval['supplier_name']}) wurde erst kürzlich bestellt ({$eval['last_purchase_date']}). Seitdem wurden {$eval['consumed_since_last_purchase']} Stück über Shop-Bestellungen verbraucht (aktueller Bestand: {$eval['current_stock']} Stk.). Falls du trotzdem außerplanmäßig nachbestellen möchtest, bestätige kurz per Klick.";
            $actionPayload = [
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
                'auto_executed_under_25' => false,
                'approved_by_alina' => false,
                'duplicate_override_required' => true,
                'blocking_reasons' => $eval['blocking_reasons'],
            ];
            $targetStatus = AiUniverseTicket::STATUS_WAITING_FOR_ALINA;
        } else {
            $autoAllowed = !empty($eval['auto_execute_allowed']);
            $targetStatus = $autoAllowed
                ? AiUniverseTicket::STATUS_WAITING_FOR_TRESOR
                : AiUniverseTicket::STATUS_WAITING_FOR_ALINA;

            $actionPayload = [
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
                'auto_executed_under_25' => $autoAllowed,
                'approved_by_alina' => $autoAllowed,
                'blocking_reasons' => $eval['blocking_reasons'],
            ];

            $consumptionInfo = "Bestand: {$eval['current_stock']} Stk., Verbrauch seit letztem Kauf: {$eval['consumed_since_last_purchase']} Stk.";
            if ($autoAllowed) {
                $reply = "**Auto-Nachkauf (<= 25,00 € Schutznetz grün):** {$eval['packs']}x {$eval['name']} ({$eval['total_units']} Stk. bei {$eval['supplier_name']}) für **{$eval['gross_total_formatted']} Brutto inkl. Versand** ({$consumptionInfo}) wurde direkt für den isolierten Funkenwerk Tresor-Browser eingereiht.";
            } else {
                $reasonHint = !empty($eval['blocking_reasons']) ? ' (' . implode(' ', $eval['blocking_reasons']) . ')' : '';
                $reply = "**Nachbestellung geprüft ({$ticket->ticket_number}):** {$eval['packs']}x {$eval['name']} ({$eval['total_units']} Stk. bei {$eval['supplier_name']}) für **{$eval['gross_total_formatted']} Brutto inkl. Versand** ({$consumptionInfo}). Bitte mit 1 Klick freigeben{$reasonHint}.";
            }
        }

        $cleanReply = AiOutputQualityGate::sanitizeText($reply);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => $agent?->name ?: 'Einkaufi',
            'ai_agent_id' => $agent?->id,
            'channel' => $presence['channel'],
            'content' => $cleanReply,
            'executed_tools' => ['procurement_evaluate_reorder'],
            'action_card' => $actionPayload,
        ]);

        $ticket->update([
            'status' => $targetStatus,
            'action_payload' => $actionPayload,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        TicketAutoCompactService::compactIfNeeded($ticket);

        return [
            'status' => 'ok',
            'mode' => 'ticket_procurement_0_token',
            'reply' => $cleanReply,
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            'presence' => $presence,
        ];
    }

    /**
     * Führt den zuständigen Abteilungs-Agenten auf dem Ticket aus.
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>
     */
    protected static function executeSpecialistOnTicket(
        AiUniverseTicket $ticket,
        string $prompt,
        array $presence,
        ?AiAgent $agent
    ): array {
        $activeAgent = $agent ?? AiAgent::where('name', 'Funkira')->first();
        $executedTools = [];
        $rawReply = '';

        app()->instance('active_universe_ticket_id', $ticket->id);

        try {
            $openAi = VertexAiService::resolveOpenAiEndpoint();
            $hasLiveApiCredentials = !empty($openAi['token']) && ($presence['environment_tier'] !== FunkiraPresenceContext::ENV_TESTING);

            if ($hasLiveApiCredentials && $activeAgent) {
                $gemini = new GeminiAgent($activeAgent);
                $header = FunkiraPresenceContext::toPromptHeader($presence);
                $flatKnowledge = self::loadFlatDepartmentKnowledge($ticket->ai_department_id);

                $voiceDirective = !empty($presence['is_voice_mode'])
                    ? "\n[SPRACHMODUS AKTIV]: Antworte in 1 bis 2 kurzen, natürlich sprechbaren Sätzen ohne Markdown-Tabellen."
                    : '';

                $gemini->setDynamicSystemPrompt(trim($header . $voiceDirective . "\n\n" . $flatKnowledge));

                $history = TicketAutoCompactService::buildPromptHistory($ticket);
                if (empty($history)) {
                    $history = [['role' => 'user', 'content' => $prompt]];
                }

                $askResult = $gemini->ask($history, function (array $evt) use (&$executedTools) {
                    if (($evt['type'] ?? '') === 'tool_call' && !empty($evt['tool'])) {
                        $executedTools[] = (string) $evt['tool'];
                    }
                });

                $rawReply = is_array($askResult)
                    ? (string) ($askResult['response'] ?? '')
                    : (string) $askResult;
            } else {
                // Deterministischer Offline-/Test-Fallback ohne unkontrollierte Token-Kosten
                $rawReply = "Ticket {$ticket->ticket_number} ist in der Abteilung "
                    . ($ticket->department?->name ?? 'Leitung')
                    . " bei " . ($activeAgent?->name ?? 'Funkira')
                    . " erfasst ({$presence['environment_tier']}). Soll ich den Punkt direkt ausführen oder an Antigravity übergeben?";
                $executedTools[] = 'ticket_set_status_or_ask';
                $ticket->update([
                    'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                    'action_payload' => ['type' => 'antigravity_handoff'],
                ]);
            }
        } finally {
            app()->forgetInstance('active_universe_ticket_id');
        }

        // Harte 0-Token Nachkontrolle: Gedankenstriche, Materialangaben, Bestellreferenzen & Anti-Bluff ("No Tool = No Promise")
        $gate = AiOutputQualityGate::inspectAndClean($rawReply, $executedTools);
        $finalReply = $gate['text'] !== '' ? $gate['text'] : 'Status im Ticket aktualisiert.';

        $ticket->refresh();
        $actionCard = null;
        if (!empty($gate['suggest_antigravity_handoff'])) {
            $actionCard = [
                'type' => 'antigravity_handoff',
                'label' => 'An Antigravity übergeben',
            ];
            $ticket->status = AiUniverseTicket::STATUS_WAITING_FOR_ALINA;
            $ticket->ticket_type = 'capability_gap';
            $ticket->action_payload = $actionCard;
        } elseif ($ticket->status === AiUniverseTicket::STATUS_IN_PROGRESS) {
            // Kein Ticket bleibt unklar in der Luft hängen: abgeschlossene Antworten ohne offene Frage gehen sauber auf Erledigt
            $hasQuestion = str_ends_with(trim($finalReply), '?');
            $ticket->status = $hasQuestion
                ? AiUniverseTicket::STATUS_WAITING_FOR_ALINA
                : AiUniverseTicket::STATUS_COMPLETED;
            if ($ticket->status === AiUniverseTicket::STATUS_COMPLETED) {
                $ticket->completed_at = now();
            }
        }

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => $activeAgent?->name ?: 'Funkira',
            'ai_agent_id' => $activeAgent?->id,
            'channel' => $presence['channel'],
            'content' => $finalReply,
            'executed_tools' => array_values(array_unique($executedTools)),
            'action_card' => $actionCard ?? $ticket->action_payload,
        ]);

        $ticket->messages_count = $ticket->messages()->count();
        $ticket->last_activity_at = now();
        $ticket->save();

        TicketAutoCompactService::compactIfNeeded($ticket);

        return [
            'status' => 'ok',
            'mode' => 'ticket_specialist',
            'reply' => $finalReply,
            'bluff_blocked' => $gate['bluff_detected'],
            'executed_tools' => array_values(array_unique($executedTools)),
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            'presence' => $presence,
        ];
    }

    /**
     * Übergibt ein Ticket sauber an Antigravity (Online -> In Arbeit, Notebook aus -> Wartet auf Notebook).
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>
     */
    public static function routeTicketToAntigravity(AiUniverseTicket $ticket, array $presence, string $reasonNote = ''): array
    {
        $agAgent = AiAgent::where('name', 'Antigravity')->first();
        $sysDeptId = AiDepartment::where('name', 'System')->value('id') ?? $ticket->ai_department_id;
        $notebookOnline = FunkiraPresenceContext::isAntigravityNotebookOnline();

        $newStatus = $notebookOnline
            ? AiUniverseTicket::STATUS_IN_PROGRESS
            : AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK;

        $reply = $notebookOnline
            ? "Ticket **{$ticket->ticket_number}** wurde direkt an **Antigravity** übergeben (Notebook ist online). Ergebnisse und Rückfragen laufen chronologisch hier ins Ticket ein."
            : "Ticket **{$ticket->ticket_number}** ist vorqualifiziert in der Warteschlange **Wartet auf Notebook** geparkt. Sobald dein Notebook eingeschaltet ist, übernimmt Antigravity nahtlos.";

        $existingPayload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $agItemId = (string) ($existingPayload['ag_item_id'] ?? '');
        if ($agItemId === '') {
            try {
                $firstUserBody = (string) ($ticket->messages()->where('sender_role', 'alina')->orderBy('created_at')->value('content') ?? $ticket->title);
                $enqueued = app(\App\Services\System\AntigravityDirectInboxService::class)->enqueueMessage([
                    'channel' => 'leitung',
                    'title' => "[{$ticket->ticket_number}] " . $ticket->title,
                    'body' => $firstUserBody . ($reasonNote !== '' ? "\n\nHinweis: {$reasonNote}" : ''),
                    'execution_mode' => 'direct',
                    'source' => $presence['channel'] ?? 'web_portal',
                    'skip_universe_mirror' => true,
                ]);
                $agItemId = (string) ($enqueued['item']['id'] ?? '');
            } catch (\Throwable) {
            }
        }

        $payload = array_merge($existingPayload, [
            'type' => 'antigravity_active',
            'ag_item_id' => $agItemId !== '' ? $agItemId : ($existingPayload['ag_item_id'] ?? null),
            'handoff_at' => now()->toIso8601String(),
            'notebook_online' => $notebookOnline,
            'note' => $reasonNote,
        ]);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Funkira',
            'channel' => $presence['channel'] ?? 'web_portal',
            'content' => $reply,
            'executed_tools' => ['ag_send_to_antigravity'],
        ]);

        $ticket->update([
            'ai_department_id' => $sysDeptId,
            'assigned_agent_id' => $agAgent?->id ?? $ticket->assigned_agent_id,
            'assigned_to_antigravity' => true,
            'status' => $newStatus,
            'action_payload' => $payload,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        TicketAutoCompactService::compactIfNeeded($ticket);

        return [
            'status' => 'ok',
            'mode' => 'antigravity_handoff',
            'reply' => $reply,
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            'presence' => $presence,
        ];
    }

    protected static function resolveAgBlockadeFromTicketUi(AiUniverseTicket $ticket, string $decision, string $note): array
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $agId = trim((string) ($payload['ag_item_id'] ?? ''));
        if ($agId !== '') {
            try {
                app(\App\Services\System\AntigravityDirectInboxService::class)->resolveBlockadeItem($agId, $decision, $note, 'Alina');
            } catch (\Throwable) {
            }
        }

        return [
            'status' => 'ok',
            'mode' => 'ag_blockade_resolved',
            'reply' => $decision === 'dismissed'
                ? "Konzept {$ticket->ticket_number} wurde verworfen."
                : "Go für {$ticket->ticket_number} wurde direkt an Antigravity übermittelt.",
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
        ];
    }

    protected static function approveProcurementTicket(AiUniverseTicket $ticket, string $channel): array
    {
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $payload['approved_by_alina'] = true;
        $payload['approved_at'] = now()->toIso8601String();

        $itemLabel = (string) ($payload['item_name'] ?? $ticket->title);
        $priceLabel = (string) ($payload['gross_total_formatted'] ?? '');
        $reply = "Freigabe bestätigt: **{$itemLabel}**" . ($priceLabel !== '' ? " ({$priceLabel})" : '') . " steht jetzt im Status **Tresor bereit** für den isolierten Funkenwerk Einkaufs-Browser bereit.";

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => 'Einkaufi',
            'channel' => $channel,
            'content' => $reply,
            'executed_tools' => ['procurement_queue_tresor_order'],
        ]);

        $ticket->update([
            'status' => AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
            'action_payload' => $payload,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        TicketAutoCompactService::compactIfNeeded($ticket);

        return [
            'status' => 'ok',
            'mode' => 'procurement_approved',
            'reply' => $reply,
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
        ];
    }

    protected static function markTicketStatus(AiUniverseTicket $ticket, string $status, string $note, string $channel): array
    {
        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'system',
            'sender_name' => 'Funkira',
            'channel' => $channel,
            'content' => $note,
            'executed_tools' => ['ticket_set_status_or_ask'],
        ]);

        $ticket->update([
            'status' => $status,
            'completed_at' => $status === AiUniverseTicket::STATUS_COMPLETED ? now() : $ticket->completed_at,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        return [
            'status' => 'ok',
            'reply' => $note,
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
        ];
    }

    protected static function resolveExistingOrPendingReplyTicket(?string $existingTicketId, string $prompt): ?AiUniverseTicket
    {
        if (!Schema::hasTable('ai_universe_tickets')) {
            return null;
        }

        if ($existingTicketId !== null && trim($existingTicketId) !== '') {
            $clean = trim($existingTicketId);
            return AiUniverseTicket::with(['department', 'agent'])
                ->where('id', $clean)
                ->orWhere('ticket_number', strtoupper($clean))
                ->first();
        }

        if (self::isShortConfirmation($prompt)) {
            return AiUniverseTicket::with(['department', 'agent'])
                ->where('status', AiUniverseTicket::STATUS_WAITING_FOR_ALINA)
                ->orderByDesc('last_activity_at')
                ->first();
        }

        return null;
    }

    protected static function isShortConfirmation(string $prompt): bool
    {
        $normalized = mb_strtolower(trim(preg_replace('/[!?.]+$/u', '', $prompt) ?? $prompt));

        return in_array($normalized, [
            'ja',
            'ja bitte',
            'go',
            'ok',
            'okay',
            'machen',
            'freigeben',
            'jetzt kaufen',
            'kaufen',
            'bestellen',
            'übergeben',
            'an antigravity',
            'an antigravity übergeben',
        ], true);
    }

    /**
     * Verarbeitet Alinas 1-Wort-Bestätigung ("Ja", "Go", "Übergeben") auf ein offenes Rückfrage-Ticket in 0 Tokens.
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>
     */
    protected static function handleOneWordConfirmation(AiUniverseTicket $ticket, string $prompt, array $presence): array
    {
        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'alina',
            'sender_name' => 'Alina',
            'channel' => $presence['channel'],
            'content' => $prompt,
        ]);
        $ticket->increment('messages_count');

        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $type = (string) ($payload['type'] ?? '');

        if ($type === 'tresor_procurement') {
            return self::approveProcurementTicket($ticket, $presence['channel']);
        }

        return self::routeTicketToAntigravity($ticket, $presence, 'Durch Alina per 1-Wort-Bestätigung freigegeben.');
    }

    protected static function extractProcurementItemQuery(string $prompt): string
    {
        $clean = preg_replace('/^(?:funkira[,:\s]+|einkaufi[,:\s]+)?(?:wir\s+brauchen|bitte\s+bestell(?:e|en)?|bestell(?:e|en)?|kaufe?)\s+(?:bitte\s+)?(?:noch\s+)?(?:weitere\s+|neue\s+)?(?:\d+\s*(?:pack(?:ung(?:en)?|s)?|mal|x)\s+)?/iu', '', trim($prompt)) ?? $prompt;
        $clean = preg_replace('/\s+nach[.!?]*$/iu', '', trim($clean)) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B\"'.,!?");

        return $clean !== '' ? $clean : trim($prompt);
    }

    protected static function buildCleanTitle(string $prompt, string $ticketType): string
    {
        $clean = AiOutputQualityGate::sanitizeText(preg_replace('/\s+/', ' ', trim($prompt)) ?? $prompt);
        $clean = preg_replace('/^(?:hey\s+)?funkira[,:\s]+/iu', '', $clean) ?? $clean;

        return ucfirst(Str::limit(trim($clean), 110));
    }
}
