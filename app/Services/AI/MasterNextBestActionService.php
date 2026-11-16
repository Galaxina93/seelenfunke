<?php

namespace App\Services\AI;

use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementTask;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Services\AnalyticsService;
use App\Services\Order\OrderProductionBatchService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class MasterNextBestActionService
{
    /**
     * Berechnet ausschliesslich AUF NACHFRAGE (0 Hintergrund-Tokens) den vernetzten Co-CEO-Kompass:
     * 1. Zeitfenster-, Tagesroutinen- & Kalender-Abgleich (inkl. Zeitfenster-Einwand vor Pausen/Terminen)
     * 2. 5-Stufen-Smart-Fertigungs-Queue (Ruestgruppen-Serien, 60-Sek.-Rohling-Vorlauf & DHL-Versandschluss)
     * 3. 24-Punkte Operativer Status aus AnalyticsService::getHealthChecks() (Schaden-vor-Fleiss-Gewichtung)
     * 4. Reparierte ManagementTask-Priorisierung (hoch -> mittel -> niedrig + Faelligkeit)
     * 5. 14-Tage-Saison- & Sortiments-Weitblick fuer ruhige Tage
     */
    public function evaluate(?string $activeBatchProductId = null, ?CarbonInterface $now = null): array
    {
        $berlinNow = $now
            ? Carbon::instance($now)->setTimezone('Europe/Berlin')
            : Carbon::now('Europe/Berlin');

        $timeCtx = $this->evaluateTimeAndCalendarContext($berlinNow);
        $workshopCtx = $this->evaluateWorkshopAndQueueContext($activeBatchProductId, $berlinNow);
        $healthChecks = app(AnalyticsService::class)->getHealthChecks();
        $topTasks = $this->fetchPrioritizedOpenTasks(8, $berlinNow);
        $foresight = $this->evaluateStrategicForesight($healthChecks, $berlinNow);

        $candidates = [];
        $objectionsAndTips = [];

        // 1. Zeitfenster-Einwand oder Fokus-Tipp
        if (!empty($timeCtx['time_objection'])) {
            $objectionsAndTips[] = $timeCtx['time_objection'];
        }

        // 2. Werkstatt-Einwaende & Ruest-Hebel
        foreach ($workshopCtx['smart_tips'] as $wTip) {
            $objectionsAndTips[] = $wTip;
        }

        // 3. Werkstatt-Kandidaten in Kandidaten-Liste einspeisen
        foreach ($workshopCtx['action_candidates'] as $wCand) {
            $candidates[] = $wCand;
        }

        // 4. Alle 24 Health-Checks gewichten (ohne Dopplung zu open_orders / open_tasks / routine)
        foreach ($this->mapHealthChecksToCandidates($healthChecks) as $hCand) {
            $candidates[] = $hCand;
        }

        // 5. Top-CEO-Aufgaben (ManagementTask: hoch -> mittel -> niedrig) einspeisen
        foreach ($topTasks as $idx => $taskItem) {
            $isHigh = $taskItem['priority'] === 'hoch';
            $isDueToday = !empty($taskItem['is_due_or_active']);
            $tier = ($isHigh && $isDueToday) ? 2 : ($isHigh ? 2 : 3);
            $score = ($isHigh ? 230 : ($taskItem['priority'] === 'mittel' ? 320 : 380))
                - ($isDueToday ? 25 : 0)
                + ($idx * 5);

            $subHint = $taskItem['open_subtasks_count'] > 0
                ? " ({$taskItem['open_subtasks_count']} offene Teilschritte)"
                : '';

            $candidates[] = [
                'key' => 'task_' . $taskItem['id'],
                'tier' => $tier,
                'score' => $score,
                'estimated_minutes' => $taskItem['open_subtasks_count'] >= 3 ? 45 : 20,
                'is_quick_win' => $taskItem['open_subtasks_count'] <= 1 && mb_strlen($taskItem['title']) <= 65,
                'department' => 'Aufgaben (' . $taskItem['list_name'] . ')',
                'route' => '/admin/dashboard',
                'title' => "To-Do [{$taskItem['priority']}]: {$taskItem['title']}{$subHint}",
                'why' => $isDueToday
                    ? 'Fällig bzw. ab heute eingeplant auf deiner zentralen To-Do-Liste.'
                    : 'Wichtigste offene Aufgabe auf deiner zentralen To-Do-Liste.',
            ];
        }

        // 6. Saison- & Wachstums-Kandidaten (Stufe 4 Weitblick, greift besonders wenn operativ Ruhe ist)
        foreach ($foresight['candidates'] as $fCand) {
            $candidates[] = $fCand;
        }
        foreach ($foresight['tips'] as $fTip) {
            $objectionsAndTips[] = $fTip;
        }

        // Nach Score aufsteigend sortieren (kleinster Score = hoechster unternehmerischer Hebel!)
        usort($candidates, static fn (array $a, array $b) => $a['score'] <=> $b['score']);

        $quickWins = array_values(array_filter(
            $candidates,
            static fn (array $c) => ($c['estimated_minutes'] ?? 30) <= 15 || !empty($c['is_quick_win'])
        ));

        // Intelligenter Zeitfenster-Pivot: Wenn <= 25 Min. vor Termin/Pause/Feierabend UND kein Tier-1-Notfall vorliegt,
        // ziehe automatisch einen Quick-Win (<= 15 Min.) auf Platz 1 statt einer 45-Minuten-Baustelle!
        $shortWindow = $timeCtx['minutes_until_hard_stop'] !== null && $timeCtx['minutes_until_hard_stop'] <= 25;
        $focusNow = $candidates[0] ?? null;
        $nextStep = $candidates[1] ?? null;

        if (
            $shortWindow
            && $focusNow !== null
            && ($focusNow['tier'] ?? 3) > 1
            && ($focusNow['estimated_minutes'] ?? 30) > 15
            && !empty($quickWins)
        ) {
            $bestQuick = $quickWins[0];
            $focusNow = array_merge($bestQuick, [
                'pivoted_for_short_window' => true,
            ]);
            $remaining = array_values(array_filter(
                $candidates,
                static fn (array $c) => $c['key'] !== $bestQuick['key']
            ));
            $nextStep = $remaining[0] ?? null;
        }

        $objectionsAndTips = array_values(array_unique(array_filter($objectionsAndTips)));

        if ($focusNow === null) {
            $focusNow = [
                'key' => 'all_clear',
                'tier' => 4,
                'score' => 999,
                'estimated_minutes' => 15,
                'is_quick_win' => true,
                'department' => 'Strategie & Wachstum',
                'route' => '/admin/dashboard',
                'title' => 'Operativ ist alles auf Null: Freie Bahn für neuen Produkt- oder Reel-Content',
                'why' => '0 offene Bestellungen, 0 Tickets und alle Pflichtprüfungen im grünen Bereich.',
            ];
        }

        return [
            'status' => 'success',
            'mode' => 'on_demand_only',
            'timestamp_berlin' => $berlinNow->format('d.m.Y H:i'),
            'time_context' => $timeCtx,
            'recommended_focus_now' => [
                'title' => $focusNow['title'],
                'department' => $focusNow['department'],
                'why' => $focusNow['why'],
                'estimated_minutes' => $focusNow['estimated_minutes'],
                'route' => $focusNow['route'],
            ],
            'smart_objections_and_tips' => array_slice($objectionsAndTips, 0, 3),
            'recommended_next_step' => $nextStep ? [
                'title' => $nextStep['title'],
                'department' => $nextStep['department'],
                'why' => $nextStep['why'],
                'estimated_minutes' => $nextStep['estimated_minutes'],
                'route' => $nextStep['route'],
            ] : null,
            'quick_wins_under_15m' => array_map(static fn (array $q) => [
                'title' => $q['title'],
                'estimated_minutes' => $q['estimated_minutes'],
                'route' => $q['route'],
            ], array_slice($quickWins, 0, 3)),
            'workshop_snapshot' => $workshopCtx['summary'],
            'top_open_tasks' => array_slice($topTasks, 0, 4),
            'response_rule' => 'Antworte herzlich-cool, aber taktisch kurz und knackig in maximal 3 bis 4 kurzen Sätzen: 1) Fokus jetzt, 2) wichtigster Einwand/Effizienz-Tipp, 3) nächster Schritt + kurze Frage ob ihr so startet. Keine langen Aufzählungen!',
        ];
    }

    private function evaluateTimeAndCalendarContext(Carbon $now): array
    {
        $isWeekend = $now->isWeekend();
        $minsOfDay = ((int) $now->hour * 60) + (int) $now->minute;
        $activeRoutineTitle = null;
        $minutesRemainingInRoutine = null;
        $nextRoutineTitle = null;
        $minutesUntilNextRoutine = null;

        if (
            class_exists(ManagementDayRoutine::class)
            && Schema::hasTable('management_day_routines')
            && !$isWeekend
        ) {
            $routines = ManagementDayRoutine::query()
                ->where('is_active', true)
                ->orderBy('start_time', 'asc')
                ->get();

            foreach ($routines as $idx => $r) {
                if (empty($r->start_time)) {
                    continue;
                }
                $startDt = Carbon::parse($r->start_time);
                $rStartMins = ((int) $startDt->hour * 60) + (int) $startDt->minute;
                $dur = max(15, (int) ($r->duration_minutes ?: 60));
                $rEndMins = $rStartMins + $dur;

                if ($minsOfDay >= $rStartMins && $minsOfDay < $rEndMins) {
                    $activeRoutineTitle = (string) $r->title;
                    $minutesRemainingInRoutine = $rEndMins - $minsOfDay;
                } elseif ($rStartMins > $minsOfDay && $nextRoutineTitle === null) {
                    $nextRoutineTitle = (string) $r->title;
                    $minutesUntilNextRoutine = $rStartMins - $minsOfDay;
                }
            }
        }

        if ($activeRoutineTitle === null) {
            if ($isWeekend) {
                $activeRoutineTitle = 'Wochenende (Freiwilliger Manufaktur-Modus)';
            } elseif ($minsOfDay >= 10 * 60 + 30 && $minsOfDay < 12 * 60 + 30) {
                $activeRoutineTitle = 'Deep Work Block I (10:30 bis 12:30)';
                $minutesRemainingInRoutine = (12 * 60 + 30) - $minsOfDay;
            } elseif ($minsOfDay >= 12 * 60 + 30 && $minsOfDay < 13 * 60 + 30) {
                $activeRoutineTitle = 'Mittagspause & Regeneration (12:30 bis 13:30)';
                $minutesRemainingInRoutine = (13 * 60 + 30) - $minsOfDay;
            } elseif ($minsOfDay >= 14 * 60 + 30 && $minsOfDay < 17 * 60) {
                $activeRoutineTitle = 'Work Block II: Operations & Versand (14:30 bis 17:00)';
                $minutesRemainingInRoutine = (17 * 60) - $minsOfDay;
            } elseif ($minsOfDay >= 17 * 60) {
                $activeRoutineTitle = 'Feierabend & Erholung (ab 17:00)';
            } else {
                $activeRoutineTitle = 'Morgen- & Tagesstart';
            }
        }

        $nextEventTitle = null;
        $nextEventTime = null;
        $minutesUntilNextEvent = null;

        if (class_exists(ManagementCalendarEvent::class) && Schema::hasTable('management_calendar_events')) {
            $upcomingEvent = ManagementCalendarEvent::query()
                ->where('is_all_day', false)
                ->whereBetween('start_date', [$now->copy()->subMinutes(5), $now->copy()->endOfDay()])
                ->orderBy('start_date', 'asc')
                ->first();

            if ($upcomingEvent && $upcomingEvent->start_date) {
                $evStart = Carbon::instance($upcomingEvent->start_date)->setTimezone('Europe/Berlin');
                $nextEventTitle = (string) $upcomingEvent->title;
                $nextEventTime = $evStart->format('H:i');
                $minutesUntilNextEvent = max(0, (int) round(($evStart->timestamp - $now->timestamp) / 60));
            }
        }

        // Bestimme den naechsten harten Zeit-Stopp (Termin oder Ende des Arbeitsblocks)
        $minutesUntilHardStop = null;
        $hardStopLabel = null;

        if ($minutesUntilNextEvent !== null && $minutesUntilNextEvent <= 60) {
            $minutesUntilHardStop = $minutesUntilNextEvent;
            $hardStopLabel = "Termin '{$nextEventTitle}' ({$nextEventTime} Uhr)";
        }

        if ($minutesRemainingInRoutine !== null && $minutesRemainingInRoutine <= 30) {
            if ($minutesUntilHardStop === null || $minutesRemainingInRoutine < $minutesUntilHardStop) {
                $minutesUntilHardStop = $minutesRemainingInRoutine;
                $hardStopLabel = $nextRoutineTitle
                    ? "Wechsel zu '{$nextRoutineTitle}'"
                    : "Ende von '{$activeRoutineTitle}'";
            }
        }

        $timeObjection = null;
        $isDeepWork = !$isWeekend && (
            ($minsOfDay >= 10 * 60 + 30 && $minsOfDay < 12 * 60)
            || ($minsOfDay >= 14 * 60 + 30 && $minsOfDay < 16 * 60 + 30)
        );

        if ($minutesUntilHardStop !== null && $minutesUntilHardStop <= 25 && $hardStopLabel !== null) {
            $timeObjection = "Zeitfenster-Einwand: Nur noch {$minutesUntilHardStop} Min. bis {$hardStopLabel}. Jetzt kein großes Thema mehr anfangen, lieber einen schnellen 5-bis-10-Minuten-Punkt abhaken!";
        } elseif (!$isWeekend && $minsOfDay >= 15 * 60 && $minsOfDay <= 16 * 60 + 45) {
            $timeObjection = 'Nachmittags-Versandfenster: Fertig gelaserte Pakete und DHL-Labels jetzt vor Büro-Kleinkram ziehen, damit sie heute noch rausgehen.';
        } elseif ($isDeepWork && ($minutesUntilNextEvent === null || $minutesUntilNextEvent >= 50)) {
            $timeObjection = "Fokus-Vorteil: Freies Zeitfenster in '{$activeRoutineTitle}'. Nicht mit Kleinkram verzetteln, direkt den größten Hebel durchziehen!";
        } elseif ($minsOfDay >= 17 * 60 || $isWeekend) {
            $timeObjection = $isWeekend
                ? 'Wochenend-Modus: Kein Pflichtprogramm, erledige nur das, worauf du selbst gerade Lust hast.'
                : 'Feierabend-Zeit (nach 17:00 Uhr): Keine schweren Baustellen mehr starten, außer es ist Express.';
        }

        return [
            'weekday' => $now->locale('de')->isoFormat('dddd'),
            'time' => $now->format('H:i'),
            'is_weekend' => $isWeekend,
            'active_routine_block' => $activeRoutineTitle,
            'minutes_remaining_in_block' => $minutesRemainingInRoutine,
            'next_calendar_event' => $nextEventTitle ? "{$nextEventTitle} ({$nextEventTime} Uhr, in {$minutesUntilNextEvent} Min.)" : null,
            'minutes_until_hard_stop' => $minutesUntilHardStop,
            'time_objection' => $timeObjection,
        ];
    }

    private function evaluateWorkshopAndQueueContext(?string $activeBatchProductId, Carbon $now): array
    {
        if (!class_exists(OrderOrder::class) || !Schema::hasTable('order_orders')) {
            return [
                'summary' => ['open_orders_count' => 0],
                'smart_tips' => [],
                'action_candidates' => [],
            ];
        }

        $openOrders = OrderOrder::with(['items.product', 'shipments'])
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        if ($openOrders->isEmpty()) {
            return [
                'summary' => [
                    'open_orders_count' => 0,
                    'express_count' => 0,
                    'priority_order_number' => null,
                    'priority_product_label' => null,
                ],
                'smart_tips' => [],
                'action_candidates' => [],
            ];
        }

        $batchService = app(OrderProductionBatchService::class);
        $queue = $batchService->buildSmartQueue($openOrders, $activeBatchProductId);
        $analyses = $queue['analyses'] ?? [];
        $batchGroups = $queue['batch_groups'] ?? [];
        /** @var OrderOrder|null $priorityOrder */
        $priorityOrder = $queue['priority_order'] ?? null;

        $smartTips = [];
        $candidates = [];

        $supplierNeededOrders = [];
        $readyShipOrders = [];
        $expressCount = 0;

        foreach ($openOrders as $ord) {
            $a = $analyses[(string) $ord->id] ?? null;
            if (!$a) {
                continue;
            }
            if (!empty($a['is_express'])) {
                $expressCount++;
            }
            if (($a['stage'] ?? '') === 'action_supplier_order') {
                $supplierNeededOrders[] = $ord->getProductionFocusLabel();
            }
            if (in_array($a['stage'] ?? '', ['ready_ship', 'ready_digital'], true)) {
                $readyShipOrders[] = $ord->getProductionFocusLabel();
            }
        }

        // Einwand A: 60-Sekunden Rohling-Nachbestellung vor langer Arbeit ausloesen!
        if (!empty($supplierNeededOrders)) {
            $ordList = implode(', ', array_slice($supplierNeededOrders, 0, 3));
            $smartTips[] = "Vorlauf-Einwand: Für {$ordList} muss noch der Rohling beim Lieferanten bestellt werden. Klick das zuerst in 60 Sekunden durch, damit die Lieferzeit schon für uns arbeitet!";
            $candidates[] = [
                'key' => 'orders_supplier_order_first',
                'tier' => 1,
                'score' => 55,
                'estimated_minutes' => 3,
                'is_quick_win' => true,
                'department' => 'Bestellungen & Beschaffung',
                'route' => '/admin/orders',
                'title' => 'Rohling sofort nachbestellen für ' . $ordList,
                'why' => 'Dauert nur 1 bis 3 Minuten, startet aber sofort die Lieferanten-Laufzeit.',
            ];
        }

        // Einwand B: Fertig produziert, nur noch Label/Versand offen!
        if (!empty($readyShipOrders)) {
            $shipList = implode(', ', array_slice($readyShipOrders, 0, 3));
            $smartTips[] = "Versand-Hebel: {$shipList} ist fertig produziert und braucht nur noch Label bzw. Versand-Abschluss (2 Min.), damit das Paket heute noch mit DHL rausgeht.";
            $candidates[] = [
                'key' => 'orders_finish_ready_ship',
                'tier' => 1,
                'score' => 45,
                'estimated_minutes' => 5,
                'is_quick_win' => true,
                'department' => 'Versand (DHL)',
                'route' => '/admin/orders',
                'title' => 'Fertige Sendung versandfertig machen (' . $shipList . ')',
                'why' => 'Ware ist bereits fertig, kurzer Abschluss spart einen vollen Tag Lieferzeit beim Kunden.',
            ];
        }

        // Tipp C: Ruestgruppen- & Serien-Buendelung am xTool F2 Ultra UV
        foreach ($batchGroups as $bg) {
            $readyCount = (int) ($bg['ready_orders_count'] ?? 0);
            if ($readyCount >= 2) {
                $pName = (string) ($bg['product_name'] ?? 'Artikel');
                $setupLbl = (string) ($bg['setup_label'] ?? 'Laser-Setup');
                $units = (int) ($bg['ready_units_count'] ?? $readyCount);
                $smartTips[] = "Rüstzeit-Hebel: {$readyCount} offene Aufträge ({$units} Stück '{$pName}') nutzen dieselbe Rüstung ({$setupLbl}). Unbedingt am Stück durchlasern, bevor du umbaust!";
                break;
            }
        }

        // Haupt-Fertigungskandidat aus der 5-Stufen-Smart-Queue
        if ($priorityOrder) {
            $pAnalysis = $analyses[(string) $priorityOrder->id] ?? [];
            $directivePlain = trim((string) ($queue['funki_directive']['action_plain'] ?? ''));
            $focusLabel = $priorityOrder->getProductionFocusLabel();
            $checkinSnap = $batchService->buildWorkshopCheckinSnapshot($priorityOrder, $now);

            if (!empty($checkinSnap['has_discrepancy']) && !empty($checkinSnap['ask_alina_prompt'])) {
                $smartTips[] = 'Status-Abgleich (mit Rückfrage): ' . $checkinSnap['ask_alina_prompt'];
            }

            $isExpressOrSla = !empty($pAnalysis['is_express']) || !empty($pAnalysis['is_aging_sla']);
            $stage = (string) ($pAnalysis['stage'] ?? '');
            $isBlockedWait = in_array($stage, ['blocked_supplier_wait', 'blocked_photo_issue', 'blocked_unpaid'], true);

            if (!$isBlockedWait) {
                $priorityTitle = !empty($pAnalysis['is_express'])
                    ? ($stage === 'ready_laser'
                        ? "EXPRESS-Fertigung: {$focusLabel} sofort lasern"
                        : "EXPRESS-Fertigung: {$focusLabel} bearbeiten")
                    : "Prioritäts-Fertigung: {$focusLabel} bearbeiten";

                $candidates[] = [
                    'key' => 'priority_order_' . $priorityOrder->order_number,
                    'tier' => $isExpressOrSla ? 1 : 2,
                    'score' => !empty($pAnalysis['is_express']) ? 20 : ($isExpressOrSla ? 40 : 110),
                    'estimated_minutes' => in_array($stage, ['ready_ship', 'ready_digital', 'action_supplier_order'], true) ? 5 : 20,
                    'is_quick_win' => in_array($stage, ['ready_ship', 'ready_digital', 'action_supplier_order'], true),
                    'department' => 'Laser-Manufaktur & Bestellungen',
                    'route' => '/admin/orders',
                    'title' => $priorityTitle,
                    'why' => (!empty($pAnalysis['is_express'])
                        ? "Express-Auftrag ({$focusLabel}) hat höchste Priorität in der Manufaktur."
                        : "Platz 1 deiner rüstzeit-optimierten Fertigungs-Queue ({$focusLabel}).")
                        . ($directivePlain !== '' ? ' ' . $directivePlain : ''),
                ];
            }
        }

        return [
            'summary' => [
                'open_orders_count' => $openOrders->count(),
                'express_count' => $expressCount,
                'priority_order_number' => $priorityOrder?->order_number,
                'priority_product_label' => $priorityOrder?->getProductionFocusLabel(),
                'funki_directive' => $queue['funki_directive']['action_plain'] ?? null,
            ],
            'smart_tips' => $smartTips,
            'action_candidates' => $candidates,
        ];
    }

    private function mapHealthChecksToCandidates(array $healthChecks): array
    {
        $weightMatrix = [
            'security_audit' => ['tier' => 1, 'score' => 25, 'mins' => 15, 'route' => '/admin/dashboard', 'why' => 'Sicherheits- bzw. Paket-Warnung zuerst absichern.'],
            'open_chats' => ['tier' => 1, 'score' => 30, 'mins' => 10, 'route' => '/admin/support-chats', 'why' => 'Wartender Kunde im Live-Chat: schnelle Antwort sichert Vertrauen und Umsatz.'],
            'open_revocations' => ['tier' => 1, 'score' => 35, 'mins' => 10, 'route' => '/admin/revocations', 'why' => 'Offener Widerruf erfordert fristgerechte Prüfung.'],
            'product_readiness' => ['tier' => 2, 'score' => 60, 'mins' => 20, 'route' => '/admin/products', 'why' => 'Unvollständige Produkt- oder Laser-Werte verhindern reibungslose Fertigung.'],
            'system_logs' => ['tier' => 2, 'score' => 65, 'mins' => 15, 'route' => '/admin/logs', 'why' => 'Ungelesene Systemfehler der letzten 48h prüfen.'],
            'open_tickets' => ['tier' => 2, 'score' => 95, 'mins' => 12, 'route' => '/admin/support-tickets', 'why' => 'Offene Kunden-Tickets zeitnah beantworten.'],
            'open_quotes' => ['tier' => 2, 'score' => 125, 'mins' => 15, 'route' => '/admin/quote-requests', 'why' => 'Offene B2B-/Individual-Angebote bringen direkten Umsatz.'],
            'open_contact_requests' => ['tier' => 2, 'score' => 135, 'mins' => 10, 'route' => '/admin/support-contacts', 'why' => 'Offene Kontaktanfragen von Interessenten beantworten.'],
            'inventory' => ['tier' => 2, 'score' => 145, 'mins' => 10, 'route' => '/admin/products', 'why' => 'Knappe oder ausverkaufte Rohlinge rechtzeitig nachdisponieren.'],
            'open_mails' => ['tier' => 2, 'score' => 160, 'mins' => 10, 'route' => '/admin/mail', 'why' => 'Ungelesene E-Mails im Postfach sichten.'],
            'influencer_payouts' => ['tier' => 2, 'score' => 175, 'mins' => 10, 'route' => '/admin/marketing-influencers', 'why' => 'Offene Influencer-Auszahlungen oder Muster-Prüfungen klären.'],
            'open_credits' => ['tier' => 2, 'score' => 185, 'mins' => 8, 'route' => '/admin/financial-invoices', 'why' => 'Unversendete Gutschriften buchhalterisch sauber abschließen.'],
            'open_invoices' => ['tier' => 2, 'score' => 195, 'mins' => 10, 'route' => '/admin/financial-invoices', 'why' => 'Offene bzw. überfällige Ausgangsrechnungen im Blick behalten.'],
            'open_abandoned_carts' => ['tier' => 3, 'score' => 260, 'mins' => 8, 'route' => '/admin/carts', 'why' => 'Stehengelassene Warenkörbe mit Opt-in haben schnelles Umsatz-Potenzial.'],
            'product_reviews' => ['tier' => 3, 'score' => 275, 'mins' => 5, 'route' => '/admin/product-reviews', 'why' => 'Wartende Kundenbewertungen freischalten stärkt Social Proof (+15 Funken).'],
            'unassigned_tx' => ['tier' => 3, 'score' => 310, 'mins' => 10, 'route' => '/admin/financial-banks', 'why' => 'Offene Bankumsätze zuordnen hält die Buchhaltung tagesaktuell.'],
            'special_issues' => ['tier' => 3, 'score' => 340, 'mins' => 10, 'route' => '/admin/financial-variable-costs', 'why' => 'Fehlende Ausgabe-Belege ergänzen für 100 % Vorsteuerabzug.'],
            'leitung/contracts' => ['tier' => 3, 'score' => 355, 'mins' => 10, 'route' => '/admin/financial-fix-costs', 'why' => 'Fehlende Vertragsdokumente bei Fixkosten hinterlegen.'],
            'open_losses' => ['tier' => 3, 'score' => 365, 'mins' => 8, 'route' => '/admin/product-fracture', 'why' => 'Offene Bruch-/Schwundmeldungen abschließen.'],
            'storage' => ['tier' => 3, 'score' => 370, 'mins' => 15, 'route' => '/admin/dashboard', 'why' => 'Server-Speicherplatz prüfen.'],
            'shopping_list' => ['tier' => 3, 'score' => 390, 'mins' => 10, 'route' => '/admin/dashboard', 'why' => 'Offene Punkte auf der Einkaufsliste beachten.'],
        ];

        $out = [];
        foreach ($healthChecks as $key => $check) {
            if (in_array($key, ['open_orders', 'open_tasks', 'routine'], true)) {
                continue;
            }
            if (!is_array($check)) {
                continue;
            }
            $status = (string) ($check['status'] ?? 'success');
            $count = (int) ($check['count'] ?? 0);
            if ($status === 'success' || $count <= 0) {
                continue;
            }

            $meta = $weightMatrix[$key] ?? [
                'tier' => 3,
                'score' => 350,
                'mins' => 15,
                'route' => '/admin/dashboard',
                'why' => 'Offener Punkt im Operativen Status.',
            ];

            // Bei product_readiness nur dann Tier 1, wenn wirklich ein AKTIVES Produkt unvollstaendig ist!
            $score = $meta['score'];
            $tier = $meta['tier'];
            if ($key === 'product_readiness' && $status === 'todo') {
                $tier = 3;
                $score = 290;
            }

            $title = trim((string) ($check['title'] ?? $key)) . ': ' . trim((string) ($check['message'] ?? "{$count} offen"));
            $out[] = [
                'key' => 'health_' . $key,
                'tier' => $tier,
                'score' => $score,
                'estimated_minutes' => $meta['mins'],
                'is_quick_win' => $meta['mins'] <= 12,
                'department' => (string) ($check['title'] ?? 'Dashboard'),
                'route' => $meta['route'],
                'title' => $title,
                'why' => $meta['why'],
            ];
        }

        return $out;
    }

    private function fetchPrioritizedOpenTasks(int $limit, Carbon $now): array
    {
        if (!class_exists(ManagementTask::class) || !Schema::hasTable('management_tasks')) {
            return [];
        }

        $todayDate = $now->format('Y-m-d');

        $tasks = ManagementTask::query()
            ->where('is_completed', false)
            ->where('is_archived', false)
            ->whereNull('parent_id')
            ->with(['list', 'subtasks'])
            ->orderByRaw("CASE COALESCE(priority, 'niedrig') WHEN 'hoch' THEN 1 WHEN 'mittel' THEN 2 WHEN 'niedrig' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN relevant_from IS NOT NULL AND relevant_from <= ? THEN 0 WHEN relevant_from IS NULL THEN 1 ELSE 2 END', [$todayDate])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();

        return $tasks->map(static function (ManagementTask $t) use ($todayDate) {
            $relStr = $t->relevant_from ? $t->relevant_from->format('Y-m-d') : null;
            $openSubs = $t->subtasks ? $t->subtasks->where('is_completed', false)->count() : 0;

            return [
                'id' => (string) $t->id,
                'title' => (string) $t->title,
                'priority' => (string) ($t->priority ?: 'mittel'),
                'list_name' => (string) ($t->list?->name ?? 'Allgemein'),
                'relevant_from' => $relStr,
                'is_due_or_active' => $relStr === null || $relStr <= $todayDate,
                'open_subtasks_count' => $openSubs,
            ];
        })->values()->all();
    }

    private function evaluateStrategicForesight(array $healthChecks, Carbon $now): array
    {
        $seasonCtx = AiPromptService::resolveSeasonAndHolidayContext($now);
        $upcomingHint = trim((string) ($seasonCtx['upcoming_hint'] ?? ''));
        $tips = [];
        $candidates = [];

        $activeVoucherCount = 0;
        if (class_exists(MarketingVoucher::class) && Schema::hasTable('marketing_vouchers')) {
            $activeVoucherCount = MarketingVoucher::query()
                ->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
                })
                ->count();
        }

        if ($upcomingHint !== '') {
            if ($activeVoucherCount === 0) {
                $tips[] = "14-Tage-Weitblick: {$upcomingHint} Aktuell läuft noch kein aktiver Saison-Gutschein. Guter Zeitpunkt für eine kleine Aktion oder ein kurzes Laser-Reel!";
                $candidates[] = [
                    'key' => 'foresight_seasonal_campaign',
                    'tier' => 4,
                    'score' => 430,
                    'estimated_minutes' => 20,
                    'is_quick_win' => false,
                    'department' => 'Marketing & Saison-Weitblick',
                    'route' => '/admin/marketing-vouchers',
                    'title' => "Saison-Vorbereitung starten ({$upcomingHint})",
                    'why' => 'Rechtzeitig vor dem Anlass vorbereitet sein bringt entspannten Umsatz ohne Last-Minute-Stress.',
                ];
            } else {
                $tips[] = "Saison-Blick: {$upcomingHint} ({$activeVoucherCount} Gutschein-Aktion(en) bereits aktiv).";
            }
        }

        $invCheck = $healthChecks['inventory'] ?? null;
        if (is_array($invCheck) && (($invCheck['status'] ?? '') === 'error') && $upcomingHint !== '') {
            $tips[] = "Engpass-Warnung vor Saison-Anlass: {$upcomingHint} und mindestens ein aktives Produkt steht auf Bestand 0. Rohlinge lieber heute sichern!";
        }

        return [
            'season' => $seasonCtx['season'] ?? 'standard',
            'occasion' => $seasonCtx['occasion'] ?? 'standard',
            'upcoming_hint' => $upcomingHint,
            'tips' => $tips,
            'candidates' => $candidates,
        ];
    }
}
