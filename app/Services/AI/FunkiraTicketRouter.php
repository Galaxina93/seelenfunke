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

        $isAdminActor = AiUniverseTicket::isStrictAdminActor()
            || ($presence['environment_tier'] === FunkiraPresenceContext::ENV_TESTING && !auth()->check());

        $negatesAntigravity = self::mentionsNegatedAntigravity($cleanPrompt);
        $mentionedAgents = self::extractMentionedAgents($cleanPrompt);
        $mentionsAntigravity = !$negatesAntigravity && $mentionedAgents->contains(
            static fn (AiAgent $ag): bool => strcasecmp((string) $ag->name, 'Antigravity') === 0
        );
        $mentionedSpecialists = $mentionedAgents
            ->filter(static fn (AiAgent $ag): bool => strcasecmp((string) $ag->name, 'Antigravity') !== 0)
            ->values();

        // 2. Falls kein explizites Ticket übergeben wurde: Prüfe, ob eine 1-Wort-Bestätigung ("Ja", "Go", "Freigeben") zu einem offenen Rückfrage-Ticket gehört
        $targetTicket = self::resolveExistingOrPendingReplyTicket($existingTicketId, $cleanPrompt);
        if ($targetTicket && !$targetTicket->is_business && !$isAdminActor) {
            return [
                'status' => 'forbidden_private_ticket',
                'mode' => 'noop',
                'reply' => 'Zugriff verweigert: Private Tickets sind ausschließlich für die Geschäftsführung sichtbar.',
                'ticket' => null,
                'presence' => $presence,
            ];
        }
        if ($targetTicket && $targetTicket->status === AiUniverseTicket::STATUS_WAITING_FOR_ALINA) {
            $tp = is_array($targetTicket->action_payload) ? $targetTicket->action_payload : [];
            $isOnlySpecialistMention = $mentionedSpecialists->isNotEmpty() && !$mentionsAntigravity;

            if (!$isOnlySpecialistMention && ($tp['type'] ?? '') === 'ag_inbox_blockade' && !empty($tp['ag_item_id'])) {
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
            if (!$isOnlySpecialistMention && !$mentionsAntigravity && self::isShortConfirmation($cleanPrompt)) {
                return self::handleOneWordConfirmation($targetTicket, $cleanPrompt, $presence);
            }
        }

        // 3. 0-Token Sofortreflex ("Fast-Lane") für reine Gerätesteuerung & Kurzstatus (kein Ticket-Müll!)
        if ($targetTicket === null && empty($attachments) && $mentionedAgents->isEmpty()) {
            $fastLane = self::tryZeroTokenFastLane($cleanPrompt, $presence);
            if ($fastLane !== null) {
                return $fastLane;
            }
        }

        // 4. Deterministische 0-Token Triage (Abteilung, Spezialist & Ticket-Typ) inkl. expliziter @-Mentions
        $triage = self::classifyIntent($cleanPrompt, $targetTicket);
        $primaryMentioned = $mentionedSpecialists->first() ?? ($mentionsAntigravity ? $mentionedAgents->first() : null);

        if ($primaryMentioned !== null) {
            $agent = $primaryMentioned;
            $department = $primaryMentioned->department
                ?? AiDepartment::where('name', $triage['department_name'])->first()
                ?? AiDepartment::where('name', 'Leitung')->first();
        } else {
            $department = AiDepartment::where('name', $triage['department_name'])->first()
                ?? AiDepartment::where('name', 'Leitung')->first();
            $agent = AiAgent::where('name', $triage['agent_name'])->where('is_active', true)->first()
                ?? AiAgent::where('name', 'Funkira')->first();
        }

        $isNewTicket = ($targetTicket === null);
        $hasIsBusinessCol = Schema::hasTable('ai_universe_tickets') && Schema::hasColumn('ai_universe_tickets', 'is_business');
        $resolvedIsBusiness = $isAdminActor ? (bool) ($triage['is_business'] ?? true) : true;

        if ($isNewTicket) {
            $titlePrefix = $presence['environment_tier'] === FunkiraPresenceContext::ENV_STAGE ? '[STAGE] ' : '';
            $assignAgInitially = $resolvedIsBusiness
                && !$negatesAntigravity
                && ($mentionsAntigravity || ($triage['assign_antigravity'] && $mentionedSpecialists->isEmpty()));
            $createData = [
                'ticket_number' => AiUniverseTicket::generateNextTicketNumber(),
                'title' => Str::limit($titlePrefix . self::buildCleanTitle($cleanPrompt, $triage['ticket_type']), 180),
                'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
                'priority' => $triage['priority'],
                'ticket_type' => $assignAgInitially ? 'antigravity_dev' : $triage['ticket_type'],
                'ai_department_id' => $department?->id,
                'assigned_agent_id' => $agent?->id,
                'assigned_to_antigravity' => $assignAgInitially,
                'source_channel' => $presence['channel'],
                'environment_tier' => $presence['environment_tier'],
                'messages_count' => 0,
                'presence_snapshot' => $presence,
                'last_activity_at' => now(),
            ];
            if ($hasIsBusinessCol) {
                $createData['is_business'] = $resolvedIsBusiness;
            }
            $targetTicket = AiUniverseTicket::create($createData);
        } else {
            $updates = [
                'presence_snapshot' => $presence,
                'last_activity_at' => now(),
            ];
            if ($hasIsBusinessCol && $isAdminActor && self::explicitlyMarksAsPrivate($cleanPrompt)) {
                $updates['is_business'] = false;
            }

            if ($primaryMentioned !== null) {
                $updates['assigned_agent_id'] = $primaryMentioned->id;
                if ($primaryMentioned->ai_department_id) {
                    $updates['ai_department_id'] = $primaryMentioned->ai_department_id;
                }
                if (!$mentionsAntigravity) {
                    $updates['assigned_to_antigravity'] = false;
                    $updates['status'] = AiUniverseTicket::STATUS_IN_PROGRESS;
                }
                $agent = $primaryMentioned;
            } elseif ($targetTicket->agent) {
                $agent = $targetTicket->agent;
            }

            if (in_array($targetTicket->status, [AiUniverseTicket::STATUS_COMPLETED, AiUniverseTicket::STATUS_ARCHIVED], true)) {
                $updates['status'] = AiUniverseTicket::STATUS_IN_PROGRESS;
            }

            $targetTicket->update($updates);
            $targetTicket->refresh();
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

        // 5a. Explizite @-Einbindung mehrerer interner Spezialisten + optional @Antigravity
        if ($mentionedAgents->isNotEmpty()) {
            $lastResult = null;

            foreach ($mentionedSpecialists as $idx => $specialist) {
                if ($idx > 0) {
                    $targetTicket->update([
                        'assigned_agent_id' => $specialist->id,
                        'ai_department_id' => $specialist->ai_department_id ?? $targetTicket->ai_department_id,
                        'status' => AiUniverseTicket::STATUS_IN_PROGRESS,
                    ]);
                    $targetTicket->refresh();
                }
                $lastResult = self::executeSpecialistOnTicket($targetTicket, $cleanPrompt, $presence, $specialist);
            }

            if ($mentionsAntigravity) {
                if (!AiAuthHelper::isAdmin() && !($presence['environment_tier'] === FunkiraPresenceContext::ENV_TESTING && !auth()->check())) {
                    if ($lastResult !== null) {
                        return $lastResult;
                    }
                } else {
                    return self::routeTicketToAntigravity(
                        $targetTicket,
                        $presence,
                        $isNewTicket
                            ? ('Direkt via @Antigravity aus ' . ($presence['channel_label'] ?? 'Ticketsystem') . ' eingebunden.')
                            : ('Per @Antigravity im laufenden Ticket ' . $targetTicket->ticket_number . ' hinzugezogen.'),
                        $cleanPrompt
                    );
                }
            }

            if ($lastResult !== null) {
                return $lastResult;
            }
        }

        // 5b. Direkter Entwicklungsauftrag für Antigravity (0 Tokens Triage)
        if ($triage['assign_antigravity'] && ($isNewTicket || $targetTicket->assigned_to_antigravity)) {
            return self::routeTicketToAntigravity(
                $targetTicket,
                $presence,
                $isNewTicket
                    ? ('Vorqualifizierter Entwicklungsauftrag aus ' . $presence['channel_label'])
                    : ('Folgenachricht zu Ticket ' . $targetTicket->ticket_number),
                $isNewTicket ? null : $cleanPrompt
            );
        }

        // 5c. Deterministischer 0-Token Einkaufs-Pfad ("Wir brauchen noch weitere Papierflachbeutel")
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
            'set_status' => self::setManualTicketStatus($ticket, (string) ($params['status'] ?? AiUniverseTicket::STATUS_IN_PROGRESS), $channel),
            'complete' => self::setManualTicketStatus($ticket, AiUniverseTicket::STATUS_COMPLETED, $channel),
            'archive' => self::setManualTicketStatus($ticket, AiUniverseTicket::STATUS_ARCHIVED, $channel),
            'reopen' => self::setManualTicketStatus($ticket, AiUniverseTicket::STATUS_IN_PROGRESS, $channel),
            'toggle_business_scope' => self::setTicketBusinessScope($ticket, !((bool) ($ticket->is_business ?? true)), $channel),
            'set_business_scope' => self::setTicketBusinessScope($ticket, (bool) ($params['is_business'] ?? true), $channel),
            default => [
                'status' => 'error',
                'reply' => "Unbekannte Ticket-Aktion '{$action}'.",
                'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            ],
        };
    }

    /**
     * Lädt in Millisekunden (0 API-Tokens!) gelernte Dauer-Entscheidungen, globale Architektur-Leitplanken
     * sowie thematisch passende Wissenskapitel & frühere Ticket-Abschlussberichte.
     * (Air-Gapped Frontend-Support-Agent Funki ist strikt ausgeschlossen).
     */
    public static function loadFlatDepartmentKnowledge(?string $departmentId, string $prompt = '', ?AiAgent $agent = null): string
    {
        return CentralBrainService::buildZeroTokenInstantContext($departmentId, $prompt, $agent);
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
     * Erkennt explizite Verneinungen wie "nicht an Antigravity melden" oder "ohne Antigravity".
     */
    public static function mentionsNegatedAntigravity(string $prompt): bool
    {
        return (bool) preg_match(
            '/\b(nicht|kein\w*|ohne|niemals|bitte\s+nicht)\b[^.!?\n]{0,45}\bantigravity\b/iu',
            $prompt
        );
    }

    /**
     * Prüft, ob eine Nachricht explizit als privat deklariert wird.
     */
    public static function explicitlyMarksAsPrivate(string $prompt): bool
    {
        return (bool) preg_match(
            '/\b(privat(?:es|er|en)?\s+ticket|als\s+privat|rein\s+privat|nicht\s+im\s+gewerbebereich|nicht\s+gewerblich|privatbereich|privathaushalt)\b/iu',
            $prompt
        );
    }

    /**
     * Prüft in 0 Tokens, ob es sich um ein physisches Haus-/Wohn-/Gesundheits-/Alltagsthema handelt,
     * das KEIN Software-, Code-, Shop-, App-, Server- oder Mini-PC-Entwicklungsauftrag für Antigravity ist.
     */
    public static function isPhysicalOrPrivateNonCodeTopic(string $text): bool
    {
        $lower = mb_strtolower($text);

        // Wenn explizit Code/Software/Systemarchitektur gefordert wird, ist es ein echter Technik-Auftrag
        if (preg_match('/\b(code|livewire|blade|migration|seeder|phpunit|bugfix|komponente|ticketlogik|datenbank|endpoint|controller|css|javascript|three\.js|apk|kotlin|csharp|wpf|cronjob|nginx|ssh)\b/iu', $lower)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(wasserschaden|wasserfleck|durch\s+die\s+decke|dachfenster|dachboden|schimmel|rohrbruch|wasseruhr|heizungsdruck|badezimmer|\bim\s+bad\b|dachrinne|ziegel|dampfbremse|sicherungskasten|wohngebäude\w*|barmenia|hausrat\w*|mieterin|frau\s+keller|heike\s+janny|grundsteuer|schmutzwasser|zahnarzt\w*|zahnzusatz\w*|arzttermin\w*|medikament\w*|krankenkasse|bkk\s+firmus|renteninfo\w*|arbeitslosenversicherung|gründungszuschuss|unterhalt|\bnoah\b)\b/iu',
            $lower
        );
    }

    /**
     * Erkennt in 0 Tokens, ob eine Anfrage in den privaten Lebensbereich (is_business = false)
     * statt in den Gewerbebetrieb Mein-Seelenfunke (is_business = true) gehört.
     */
    public static function detectIsBusinessScope(string $prompt): bool
    {
        if (self::explicitlyMarksAsPrivate($prompt)) {
            return false;
        }

        $lower = mb_strtolower($prompt);

        // Explizit gewerblicher Kontext hat Vorrang (sofern nicht als privat markiert)
        if (preg_match('/\b(gewerblich\w*|betriebsausgabe\w*|mein-seelenfunke|manufaktur|kundenbestellung\w*|shop-bestellung\w*|konfigurator|xtool|lasergravur|produktseite|influencer|steuerberater-export|ustva|fyrst\s+geschäftskonto|hiscox|betriebshaftpflicht|verpackungslizenz)\b/iu', $lower)) {
            return true;
        }

        if (self::isPhysicalOrPrivateNonCodeTopic($lower)) {
            return false;
        }

        if (preg_match('/\b(privat(?:e|er|es|en)?\s+(?:ausgabe|rechnung|versicherung|termin|aufgabe|steuer|auto|haus)|alg\s*1|eltern\s+kommen|familienfeier|kinderarzt|schulferien|urlaubsplanung|reiseplanung)\b/iu', $lower)) {
            return false;
        }

        return true;
    }

    /**
     * Deterministische 0-Token Klassifizierung für Abteilung, zuständigen Agenten & Bereich (Gewerblich vs. Privat).
     *
     * @return array{department_name: string, agent_name: string, ticket_type: string, priority: string, assign_antigravity: bool, is_business: bool}
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
                'is_business' => (bool) ($existingTicket->is_business ?? true),
            ];
        }

        $lower = mb_strtolower($prompt);
        $negatesAg = self::mentionsNegatedAntigravity($prompt);
        $isBusiness = self::detectIsBusinessScope($prompt);

        // 1. Private Vorsorge-, Versicherungs-, Hausfinanz- & Steuer-Themen -> Buchi (Privat)
        if (!$isBusiness && preg_match('/\b(wohngebäude\w*|barmenia|devk|hausrat\w*|privathaftpflicht|axa|zahnzusatz\w*|arbeitslosenversicherung|gründungszuschuss|alg\s*1|renteninfo\w*|rente\w*|mieterin|mieteinnahme\w*|frau\s+keller|heike\s+janny|grundsteuer|schmutzwasser|abfallgebühr\w*|rundfunkbeitrag|volksbank\s+kredit|unterhalt|autoversicherung|adac|beleg\w*|rechnung\w*)\b/iu', $lower)) {
            return [
                'department_name' => 'Buchhaltung',
                'agent_name' => 'Buchi',
                'ticket_type' => 'private_finance',
                'priority' => 'normal',
                'assign_antigravity' => false,
                'is_business' => false,
            ];
        }

        // 2. Private Gesundheit, Ärzte, Medikation & Tagesroutine -> Dr. Funki (Privat)
        if (preg_match('/\b(medikament\w*|dosis|arzt\w*|zahnarzt\w*|praxis|krankenkasse|bkk\s+firmus|krankengeld|ga-op|lubos|eau\b|tagesroutine|pause\w*)\b/iu', $lower)) {
            return [
                'department_name' => 'Leitung',
                'agent_name' => 'Dr. Funki',
                'ticket_type' => $isBusiness ? 'management' : 'private_health',
                'priority' => 'normal',
                'assign_antigravity' => false,
                'is_business' => false,
            ];
        }

        // 3. Privathaus, Bad/Dachfenster/Wasserschaden, Familie & privater Alltag -> Funkira (Privat, KEIN Antigravity!)
        if (!$isBusiness) {
            return [
                'department_name' => 'Leitung',
                'agent_name' => 'Funkira',
                'ticket_type' => 'private_household',
                'priority' => preg_match('/\b(wasserschaden|rohrbruch|stromkabel|notfall|leck)\b/iu', $lower) ? 'high' : 'normal',
                'assign_antigravity' => false,
                'is_business' => false,
            ];
        }

        // 4. Expliziter Antigravity-/Code-/Entwicklungs-Auftrag (nur wenn nicht verneint und gewerblich/technisch)
        if (!$negatesAg && preg_match('/\b(antigravity|programmier\w*|code\s+änder\w*|ticketlogik|livewire|migration|seeder|bugfix|komponente\s+bauen)\b/iu', $lower)) {
            return [
                'department_name' => 'System',
                'agent_name' => 'Antigravity',
                'ticket_type' => 'antigravity_dev',
                'priority' => 'normal',
                'assign_antigravity' => true,
                'is_business' => true,
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
                'is_business' => true,
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
                'is_business' => true,
            ];
        }

        // Laser-Manufaktur & 3D-Laser-Schablonen/Klickplatten (Lasi)
        if (preg_match('/\b(laser\w*|xtool|brennpunkt|fokus|gravurprofil\w*|ra3|conveyor|abluft|tros|pulsdauer|klickplatte\w*|nullpunkt\w*|laserschablone\w*)\b/iu', $lower)) {
            return [
                'department_name' => 'Produkte',
                'agent_name' => 'Lasi',
                'ticket_type' => 'laser',
                'priority' => 'normal',
                'assign_antigravity' => false,
                'is_business' => true,
            ];
        }

        // Produkte, Konfigurator & 3D-Druck-Modelle / Bambu Lab A1 Organizer (Produkti)
        if (preg_match('/\b(produkt\w*|konfigurator|vorlage\w*|edelstahl|anhänger|seelen-pfote|seelen-kristall|bewertung\w*|bruchquote|3d[-\s]*druck\w*|3d[-\s]*modell\w*|bambu\w*|\.3mf|\.stl|filament\w*|organizer|spender)\b/iu', $lower)
            && !str_contains($lower, 'bestellung')) {
            return [
                'department_name' => 'Produkte',
                'agent_name' => 'Produkti',
                'ticket_type' => 'general',
                'priority' => 'normal',
                'assign_antigravity' => false,
                'is_business' => true,
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
                'is_business' => true,
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
                'is_business' => true,
            ];
        }

        // Marktrecherche & Strategie (Globi in Leitung)
        if (preg_match('/\b(marktrecherche|nischen\w*|wettbewerb\w*|konkurrenz)\b/iu', $lower)) {
            return [
                'department_name' => 'Leitung',
                'agent_name' => 'Globi',
                'ticket_type' => 'management',
                'priority' => 'normal',
                'assign_antigravity' => false,
                'is_business' => true,
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
                'is_business' => true,
            ];
        }

        // Standard: Stabschefin Funkira in Leitung (Kalender, Aufgaben, E-Mails, Kontakte, Koordination)
        return [
            'department_name' => 'Leitung',
            'agent_name' => 'Funkira',
            'ticket_type' => 'management',
            'priority' => 'normal',
            'assign_antigravity' => false,
            'is_business' => true,
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

            $isBusinessTicket = (bool) ($ticket->is_business ?? true);

            if ($hasLiveApiCredentials && $activeAgent) {
                $gemini = new GeminiAgent($activeAgent);
                $header = FunkiraPresenceContext::toPromptHeader($presence);
                $scopeHeader = $isBusinessTicket
                    ? "\n[TICKET-KLASSIFIZIERUNG: GEWERBLICH (is_business=true) | Mein-Seelenfunke Betriebsvorgang.]"
                    : "\n[TICKET-KLASSIFIZIERUNG: PRIVAT (is_business=false) | Privates Anliegen von Alina (Haus/Gesundheit/Familie/Vorsorge). NIEMALS ag_send_to_antigravity aufrufen! Antworte herzlich-empathisch und praxisnah anhand ihrer Vertrags- und Wissensdaten. Etwaige Belege/Ausgaben hieraus strikt mit is_business=false buchen!]";
                $flatKnowledge = self::loadFlatDepartmentKnowledge($ticket->ai_department_id, $prompt, $activeAgent);

                $voiceDirective = !empty($presence['is_voice_mode'])
                    ? "\n[SPRACHMODUS AKTIV]: Antworte in 1 bis 2 kurzen, natürlich sprechbaren Sätzen ohne Markdown-Tabellen."
                    : '';

                $gemini->setDynamicSystemPrompt(trim($header . $scopeHeader . $voiceDirective . "\n\n" . $flatKnowledge));

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
                if ($isBusinessTicket) {
                    $rawReply = "Ticket {$ticket->ticket_number} ist in der Abteilung "
                        . ($ticket->department?->name ?? 'Leitung')
                        . " bei " . ($activeAgent?->name ?? 'Funkira')
                        . " erfasst ({$presence['environment_tier']}). Soll ich den Punkt direkt ausführen oder an Antigravity übergeben?";
                    $executedTools[] = 'ticket_set_status_or_ask';
                    $prevPayload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                    $ticket->update([
                        'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                        'action_payload' => array_merge($prevPayload, ['type' => 'antigravity_handoff']),
                    ]);
                } else {
                    $rawReply = "Privates Ticket {$ticket->ticket_number} ist in der Abteilung "
                        . ($ticket->department?->name ?? 'Leitung')
                        . " bei " . ($activeAgent?->name ?? 'Funkira')
                        . " als Privat erfasst ({$presence['environment_tier']}). Sollen wir eine Erinnerung setzen oder den Punkt beobachten?";
                    $executedTools[] = 'ticket_set_status_or_ask';
                    $prevPayload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
                    $ticket->update([
                        'status' => AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
                        'action_payload' => array_merge($prevPayload, [
                            'type' => 'private_followup',
                            'options' => ['Beobachten', 'Erledigt'],
                        ]),
                    ]);
                }
            }
        } finally {
            app()->forgetInstance('active_universe_ticket_id');
        }

        // Harte 0-Token Nachkontrolle: Gedankenstriche, Materialangaben, Bestellreferenzen & Anti-Bluff ("No Tool = No Promise")
        $gate = AiOutputQualityGate::inspectAndClean($rawReply, $executedTools);
        // Keine Romane im Ticket: knackig auf den Punkt halten
        $finalReply = $gate['text'] !== '' ? Str::limit($gate['text'], 720) : 'Status im Ticket aktualisiert.';

        $ticket->refresh();
        $previousStatus = $ticket->status;
        $actionCard = null;
        if (!empty($gate['suggest_antigravity_handoff']) && (bool) ($ticket->is_business ?? true) && !AgentSkillBudgetGuard::isFunkiAgent($activeAgent)) {
            $budget = AgentSkillBudgetGuard::evaluateAgentSkillBudget($activeAgent);
            $dossier = AgentSkillBudgetGuard::formatAntigravitySkillGapDossier(
                $budget,
                Str::limit($prompt, 140),
                'Kein ausführendes Werkzeug im aktuellen Skill-Korb für diese Anforderung vorhanden (0-Token Anti-Bluff-Schutz).',
                $presence['active_route'] ?? 'Ticketsystem'
            );

            if (AiAuthHelper::isAdmin()) {
                return self::routeTicketToAntigravity($ticket, $presence, $dossier);
            }

            $prevPayload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
            $actionCard = array_merge($prevPayload, [
                'type' => 'antigravity_handoff',
                'label' => 'An Antigravity übergeben',
                'skill_gap_dossier' => $budget,
            ]);
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

        // Verbindliches Status-Protokoll bei jedem Statuswechsel (kurz, klar, inklusive finaler Ticket-Aktion)
        if ($ticket->status !== $previousStatus && !str_contains($finalReply, 'Status ->')) {
            $newLabel = AiUniverseTicket::getStatusLabel($ticket->status);
            $statusFooter = match ($ticket->status) {
                AiUniverseTicket::STATUS_COMPLETED => "\n\n**Status -> {$newLabel}:** Alle Schritte ausgeführt, Ticket wird abgeschlossen und im Erledigt-Register abgelegt.",
                AiUniverseTicket::STATUS_WAITING_FOR_ALINA => "\n\n**Status -> {$newLabel}:** Entscheidung oder kurze Rückmeldung von Alina erforderlich.",
                default => "\n\n**Status -> {$newLabel}:** Bearbeitung aktualisiert.",
            };
            $finalReply .= $statusFooter;
        }

        $stickyAttachments = TicketDeliverableService::pullQueuedAttachments($ticket->id);

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'agent',
            'sender_name' => $activeAgent?->name ?: 'Funkira',
            'ai_agent_id' => $activeAgent?->id,
            'channel' => $presence['channel'],
            'content' => $finalReply,
            'executed_tools' => array_values(array_unique($executedTools)),
            'attachments' => !empty($stickyAttachments) ? $stickyAttachments : null,
            'action_card' => $actionCard ?? $ticket->action_payload,
        ]);

        $ticket->messages_count = $ticket->messages()->count();
        $ticket->last_activity_at = now();
        $ticket->save();

        if ($ticket->status === AiUniverseTicket::STATUS_COMPLETED) {
            CentralBrainService::recordTicketCompletionReport($ticket, $finalReply);
        }

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
     * Unterstützt sowohl Erst-Aufträge als auch Folge-Nachrichten via @Antigravity im laufenden Ticket.
     *
     * @param array<string, mixed> $presence
     * @return array<string, mixed>
     */
    public static function routeTicketToAntigravity(
        AiUniverseTicket $ticket,
        array $presence,
        string $reasonNote = '',
        ?string $explicitUserPrompt = null
    ): array {
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
        $previousAgItemId = trim((string) ($existingPayload['ag_item_id'] ?? ''));
        $agHistoryIds = is_array($existingPayload['ag_history_ids'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $existingPayload['ag_history_ids']))))
            : [];

        $shouldEnqueueNewItem = ($previousAgItemId === '' || ($explicitUserPrompt !== null && trim($explicitUserPrompt) !== ''));
        $agItemId = $previousAgItemId;

        if ($shouldEnqueueNewItem) {
            try {
                if ($previousAgItemId !== '') {
                    $agHistoryIds[] = $previousAgItemId;
                }

                $recentMessages = $ticket->messages()
                    ->orderByDesc('created_at')
                    ->limit(6)
                    ->get()
                    ->reverse()
                    ->map(static fn (AiUniverseTicketMessage $m): string => '[' . $m->sender_name . ']: ' . trim((string) $m->content))
                    ->filter(static fn (string $line): bool => $line !== '')
                    ->values()
                    ->all();

                $primaryBody = ($explicitUserPrompt !== null && trim($explicitUserPrompt) !== '')
                    ? trim($explicitUserPrompt)
                    : (string) ($ticket->messages()->where('sender_role', 'alina')->orderBy('created_at')->value('content') ?? $ticket->title);

                $contextTail = count($recentMessages) > 1
                    ? ("\n\nTicket-Verlauf ({$ticket->ticket_number}):\n" . implode("\n", $recentMessages))
                    : '';

                $enqueued = app(\App\Services\System\AntigravityDirectInboxService::class)->enqueueMessage([
                    'channel' => 'leitung',
                    'title' => "[{$ticket->ticket_number}] " . $ticket->title,
                    'body' => $primaryBody . $contextTail . ($reasonNote !== '' ? "\n\nHinweis: {$reasonNote}" : ''),
                    'execution_mode' => 'direct',
                    'source' => $presence['channel'] ?? 'web_portal',
                    'skip_universe_mirror' => true,
                ]);
                $newEnqueuedId = (string) ($enqueued['item']['id'] ?? '');
                if ($newEnqueuedId !== '') {
                    $agItemId = $newEnqueuedId;
                }
            } catch (\Throwable) {
            }
        }

        $payload = array_merge($existingPayload, [
            'type' => 'antigravity_active',
            'ag_item_id' => $agItemId !== '' ? $agItemId : ($existingPayload['ag_item_id'] ?? null),
            'ag_history_ids' => array_values(array_unique($agHistoryIds)),
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

    public static function setManualTicketStatus(AiUniverseTicket $ticket, string $newStatus, string $channel = 'web_portal'): array
    {
        $allowed = [
            AiUniverseTicket::STATUS_NEW,
            AiUniverseTicket::STATUS_IN_PROGRESS,
            AiUniverseTicket::STATUS_WAITING_FOR_ALINA,
            AiUniverseTicket::STATUS_WAITING_FOR_NOTEBOOK,
            AiUniverseTicket::STATUS_WAITING_FOR_TRESOR,
            AiUniverseTicket::STATUS_COMPLETED,
            AiUniverseTicket::STATUS_ARCHIVED,
        ];

        if (!in_array($newStatus, $allowed, true)) {
            $newStatus = AiUniverseTicket::STATUS_IN_PROGRESS;
        }

        $oldStatus = $ticket->status;
        if ($oldStatus === $newStatus) {
            return [
                'status' => 'ok',
                'reply' => 'Status unverändert.',
                'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            ];
        }

        $oldLabel = AiUniverseTicket::getStatusLabel($oldStatus);
        $newLabel = AiUniverseTicket::getStatusLabel($newStatus);
        $note = "**Status manuell geändert -> {$newLabel}** (vorher: {$oldLabel}) · durch Alina.";

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'alina',
            'sender_name' => 'Alina',
            'channel' => $channel,
            'content' => $note,
        ]);

        $ticket->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === AiUniverseTicket::STATUS_COMPLETED ? now() : ($newStatus === AiUniverseTicket::STATUS_IN_PROGRESS ? null : $ticket->completed_at),
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        if ($newStatus === AiUniverseTicket::STATUS_COMPLETED) {
            CentralBrainService::recordTicketCompletionReport($ticket);
        }

        // Bidirektionale Rückspiegelung in AntigravityDirectInboxService, falls verknüpft
        $payload = is_array($ticket->action_payload) ? $ticket->action_payload : [];
        $agId = trim((string) ($payload['ag_item_id'] ?? ''));
        if ($agId !== '') {
            try {
                $inbox = app(\App\Services\System\AntigravityDirectInboxService::class);
                if ($newStatus === AiUniverseTicket::STATUS_COMPLETED) {
                    $inbox->completeItem($agId, 'Manuell durch Alina im Ticketsystem als Erledigt abgeschlossen.', true);
                } elseif ($newStatus === AiUniverseTicket::STATUS_ARCHIVED && ($payload['type'] ?? '') === 'ag_inbox_blockade') {
                    $inbox->resolveBlockadeItem($agId, 'dismissed', 'Manuell im Ticketsystem archiviert', 'Alina');
                }
            } catch (\Throwable) {
            }
        }

        return [
            'status' => 'ok',
            'reply' => $note,
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

        if ($status === AiUniverseTicket::STATUS_COMPLETED) {
            CentralBrainService::recordTicketCompletionReport($ticket, $note);
        }

        return [
            'status' => 'ok',
            'reply' => $note,
            'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
        ];
    }

    public static function setTicketBusinessScope(AiUniverseTicket $ticket, bool $isBusiness, string $channel = 'web_portal'): array
    {
        if (!Schema::hasTable('ai_universe_tickets') || !Schema::hasColumn('ai_universe_tickets', 'is_business')) {
            return [
                'status' => 'noop',
                'reply' => 'Bereichs-Spalte ist noch nicht aktiv.',
                'ticket' => $ticket,
            ];
        }

        $oldScope = (bool) ($ticket->is_business ?? true);
        if ($oldScope === $isBusiness) {
            return [
                'status' => 'ok',
                'reply' => 'Bereich ist bereits auf ' . AiUniverseTicket::getScopeLabel($isBusiness) . ' gesetzt.',
                'ticket' => $ticket->fresh(['department', 'agent', 'messages']),
            ];
        }

        $newLabel = AiUniverseTicket::getScopeLabel($isBusiness);
        $note = "**Bereich -> {$newLabel}:** Ticket-Zuordnung manuell durch Alina umgestellt.";

        AiUniverseTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_role' => 'system',
            'sender_name' => 'Alina',
            'channel' => $channel,
            'content' => $note,
        ]);

        $ticket->update([
            'is_business' => $isBusiness,
            'messages_count' => $ticket->messages()->count(),
            'last_activity_at' => now(),
        ]);

        if ($ticket->status === AiUniverseTicket::STATUS_COMPLETED) {
            CentralBrainService::recordTicketCompletionReport($ticket);
        }

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
                ->where(function ($q) use ($clean) {
                    $q->where('id', $clean)->orWhere('ticket_number', strtoupper($clean));
                })
                ->first();
        }

        if (self::isShortConfirmation($prompt)) {
            return AiUniverseTicket::with(['department', 'agent'])
                ->visibleToCurrentActor()
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
        $clean = preg_replace('/^@[A-Za-zÄÖÜäöüß]+(?:\.\s*[A-Za-zÄÖÜäöüß]+)?[,:\s]+/iu', '', trim($clean)) ?? $clean;
        if (trim($clean) === '') {
            $clean = AiOutputQualityGate::sanitizeText(preg_replace('/\s+/', ' ', trim($prompt)) ?? $prompt);
        }

        return ucfirst(Str::limit(trim($clean), 110));
    }

    /**
     * Erkennt explizite @AgentName-Erwähnungen in Nachrichten (inkl. @Antigravity & @Dr. Funki),
     * sortiert in der Reihenfolge ihres Auftretens.
     *
     * SICHERHEITS-GARANTIE: Der isolierte Frontend-Support-Agent `Funki` ist permanent gesperrt
     * (`AgentSkillBudgetGuard::isFunkiAgent`) und kann NIEMALS per `@` ins interne Ticketsystem gezogen werden.
     *
     * @return \Illuminate\Support\Collection<int, AiAgent>
     */
    public static function extractMentionedAgents(string $prompt): \Illuminate\Support\Collection
    {
        if (!str_contains($prompt, '@') || !Schema::hasTable('ai_agents')) {
            return collect();
        }

        $candidates = AiAgent::with('department')
            ->where('is_active', true)
            ->get()
            ->reject(static fn (AiAgent $ag): bool => AgentSkillBudgetGuard::isFunkiAgent($ag))
            ->sortByDesc(static fn (AiAgent $ag): int => mb_strlen((string) $ag->name))
            ->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $matchedWithPos = [];

        foreach ($candidates as $ag) {
            $name = trim((string) $ag->name);
            if ($name === '') {
                continue;
            }

            $aliases = [$name];
            $compact = preg_replace('/[\s.]+/u', '', $name) ?? '';
            if ($compact !== '' && strcasecmp($compact, $name) !== 0) {
                $aliases[] = $compact;
            }
            $dottedNoSpace = str_replace('. ', '.', $name);
            if ($dottedNoSpace !== '' && !in_array($dottedNoSpace, $aliases, true)) {
                $aliases[] = $dottedNoSpace;
            }

            $earliestOffset = null;
            foreach ($aliases as $alias) {
                $escaped = preg_quote($alias, '/');
                $pattern = '/(?:^|(?<=[\s(\[{"\',;:]))@' . $escaped . '(?=$|[\s.,!?:;)\]}"\'\-])/iu';
                if (preg_match($pattern, $prompt, $m, PREG_OFFSET_CAPTURE)) {
                    $offset = (int) $m[0][1];
                    if ($earliestOffset === null || $offset < $earliestOffset) {
                        $earliestOffset = $offset;
                    }
                }
            }

            if ($earliestOffset !== null) {
                $matchedWithPos[] = [
                    'offset' => $earliestOffset,
                    'agent' => $ag,
                ];
            }
        }

        usort($matchedWithPos, static fn (array $a, array $b): int => $a['offset'] <=> $b['offset']);

        $unique = collect();
        foreach ($matchedWithPos as $entry) {
            /** @var AiAgent $ag */
            $ag = $entry['agent'];
            if (!$unique->contains('id', $ag->id)) {
                $unique->push($ag);
            }
            if ($unique->count() >= 3) {
                break;
            }
        }

        return $unique;
    }
}
