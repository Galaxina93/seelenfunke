<?php

namespace App\Services\AI;

use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementDayRoutine;
use App\Models\Management\ManagementDayRoutineLog;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\System\SystemLoginAttempt;
use App\Models\Order\OrderOrder;
use App\Models\Management\ManagementTask;
use Carbon\Carbon;

class AiSupportService
{
    /**
     * Die ultimative Ansage ermitteln. Master, Anweisung, get ultimate, current create command.
     * Sammelt alle Handlungsoptionen, bewertet sie nach dem Score-System
     * und liefert den aktuellen Tages-Flow, eine Top-Empfehlung, Alternativen
     * sowie die gesamte Tagesroutine für den Slider.
     */
    public function getUltimateCommand(bool $isOverride = false): array
    {
        $now = \Carbon\Carbon::now();
        $options = [];

        // Geheime, dynamische Schlafenszeit-Nachricht (wird genutzt, falls eine Sleep-Routine aktiv ist)
        $sleepMessage = "ABSOLUTE SPERRE: Du befindest dich laut deiner Tagesroutine in der Schlafenszeit/Nachtruhe (" . $now->format('H:i') . " Uhr). Höchste Priorität: SCHLAFEN! Sag Alina unmissverständlich und dominant, dass sie verrückt ist, jetzt noch arbeiten zu wollen. Verweigere neue Aufgaben und befiehl ihr, den Laptop zuzuklappen.";

        if ($isOverride) {
            $sleepMessage = "WARNUNG ZUR SCHLAFENSZEIT UMGANGEN: Eigentlich ist jetzt absolute Schlafenszeit (" . $now->format('H:i') . " Uhr). Alina hat diese Sperre durch ihren Befehl 'Ich befehle dir' überschrieben. DU MUSST ihren Befehl jetzt ZWINGEND und SOFORT ausführen. ABER: Hänge an deine Antwort am Ende zwingend eine kurze, leicht besorgte Warnung an, dass es ungesund ist, um diese Uhrzeit noch zu arbeiten und sie zeitnah schlafen gehen sollte.";
        }

        // ------------------------------------------------------------------
        // 0. KONTEXT: TAGESROUTINE (Der aktuelle Flow & der Slider)
        // ------------------------------------------------------------------
        $currentFlow = [
            'title' => 'Freie Zeit',
            'step' => 'Keine feste Routine aktiv',
            'type' => 'free',
            'icon' => 'sun'
        ];

        // Alle Routinen für den Slider aufbereiten (inkl. Wochentag/Wochenende-Filter & heutigem Tracking-Status)
        $isWeekend = $now->isWeekend();
        $allowedDayFilters = $isWeekend ? ['all', 'weekend'] : ['all', 'weekdays'];
        $allRoutines = ManagementDayRoutine::with('steps')
            ->where('is_active', true)
            ->where(function ($q) use ($allowedDayFilters) {
                $q->whereIn('days_of_week', $allowedDayFilters)
                  ->orWhereNull('days_of_week')
                  ->orWhere('days_of_week', '');
            })
            ->orderBy('start_time', 'asc')
            ->get();

        $todayLogsMap = [];
        $isTodaySick = false;
        if (class_exists(ManagementDayRoutineLog::class) && \Illuminate\Support\Facades\Schema::hasTable('management_day_routine_logs')) {
            $todayLogs = ManagementDayRoutineLog::whereDate('date', $now->toDateString())->get();
            foreach ($todayLogs as $tLog) {
                $todayLogsMap[$tLog->day_routine_id] = $tLog;
                if ($tLog->is_sick) {
                    $isTodaySick = true;
                }
            }
        }

        if ($isTodaySick) {
            $currentFlow = [
                'title' => 'Genesungs-Modus (Medizin-Funki)',
                'step' => 'Schonen & Auskurieren',
                'type' => 'sick',
                'icon' => 'heart'
            ];
            $options[] = [
                'score' => 450,
                'title' => 'Medizin-Funki: Gute Besserung!',
                'message' => 'Du bist heute im Krankheits-/Genesungs-Modus eingetragen. Deine 🔥 Routine-Serien sind eingefroren und geschützt. Gönn dir Ruhe, trink viel Tee und kurier dich aus!',
                'action_label' => 'Routine öffnen',
                'action_route' => 'admin.funki-routine',
                'icon' => '❤️‍🩹'
            ];
        }

        $sliderRoutines = $allRoutines->map(function ($routine) use ($now, &$currentFlow, &$options, $sleepMessage, $todayLogsMap, $isTodaySick) {
            $start = \Carbon\Carbon::parse($routine->start_time);
            $duration = $routine->duration_minutes ?: ($routine->type === 'sleep' ? 8 * 60 : 0);
            $end = $start->copy()->addMinutes($duration);

            // Mitternachts-Korrektur (z.B. Schlafen von 22:00 bis 06:00 Uhr)
            $crossesMidnight = $start->format('H:i:s') > $end->format('H:i:s');
            if ($crossesMidnight) {
                if ($now->format('H:i:s') < $end->format('H:i:s')) {
                    $start->subDay();
                    $end->subDay();
                }
            }

            $rLog = $todayLogsMap[$routine->id] ?? null;
            $isCompletedToday = $rLog ? (bool) $rLog->is_completed : false;

            $status = 'future';
            if ($end->isPast()) {
                $status = 'past';
            } elseif ($now->between($start, $end)) {
                $status = 'current';

                if (!$isTodaySick) {
                    // --- Aktuellen Flow für die Hauptanzeige setzen ---
                    $currentFlow['title'] = $routine->title;
                    $currentFlow['type'] = $routine->type;
                    $currentFlow['icon'] = $routine->icon ?? 'clock';

                    $minutesPassed = $start->diffInMinutes($now);
                    $accumulated = 0;
                    $stepFound = false;

                    foreach ($routine->steps->sortBy('position') as $step) {
                        if ($minutesPassed >= $accumulated && $minutesPassed < ($accumulated + $step->duration_minutes)) {
                            $currentFlow['step'] = $step->title;
                            $stepFound = true;
                            break;
                        }
                        $accumulated += $step->duration_minutes;
                    }

                    if (!$stepFound) {
                        $currentFlow['step'] = 'Fokus-Phase';
                    }

                    // Wenn es Pause/Sport/Essen ist (und heute noch nicht abgehakt), pushen wir es als aktive Handlungsoption (Score 300)
                    if (!$isCompletedToday && in_array($routine->type, ['food', 'sport', 'hygiene', 'break'])) {
                        $options[] = [
                            'score' => 300,
                            'title' => $routine->title,
                            'message' => "Dein Körper braucht das jetzt: " . $currentFlow['step'] . ". " . ($routine->message ?? "Schalte kurz ab und lade die Akkus auf."),
                            'action_label' => 'Routine ansehen',
                            'action_route' => 'admin.funki-routine',
                            'icon' => $routine->icon ?? '🧘'
                        ];
                    }
                }

                // Wenn es ein aktiver Schlafenszeit-Block ist, pushen wir es mit absoluter Priorität (Score 2500)
                if ($routine->type === 'sleep') {
                    $options[] = [
                        'score' => 2500,
                        'title' => $routine->title,
                        'message' => $sleepMessage,
                        'action_label' => 'Feierabend',
                        'action_route' => 'admin.dashboard',
                        'icon' => $routine->icon ?? '🌙'
                    ];
                }
            }

            return [
                'id' => $routine->id,
                'title' => $routine->title,
                'time_formatted' => $start->format('H:i') . ' - ' . $end->format('H:i'),
                'icon' => $routine->icon ?? 'clock',
                'status' => $status,
                'is_completed' => $isCompletedToday,
                'is_sick' => $isTodaySick,
            ];
        })->values()->toArray();

        // ------------------------------------------------------------------
        // 1. SICHERHEIT (Score 1000+)
        // ------------------------------------------------------------------
        $failedLogins = SystemLoginAttempt::where('success', false)
            ->where('attempted_at', '>', now()->subHours(24))
            ->count();

        if ($failedLogins > 5) {
            $options[] = [
                'score' => 1000 + $failedLogins,
                'title' => 'Sicherheits-Alarm!',
                'message' => "Ich habe {$failedLogins} verdächtige Login-Versuche blockiert. Bitte prüfe sofort die IP-Adressen!",
                'action_label' => 'Sicherheit prüfen',
                'action_route' => 'admin.user-management',
                'icon' => '🛑'
            ];
        }

        // ------------------------------------------------------------------
        // 2. TERMINE (Score 500)
        // ------------------------------------------------------------------
        $activeEvent = ManagementCalendarEvent::whereDate('start_date', $now->toDateString())
            ->where(function($query) use ($now) {
                $query->where('start_date', '>', $now)
                ->where('start_date', '<=', $now->copy()->addMinutes(45))
                    ->orWhere(function($q) use ($now) {
                        $q->where('start_date', '<=', $now)
                        ->where('end_date', '>=', $now);
                    });
            })
            ->orderBy('start_date', 'asc')
            ->first();

        if ($activeEvent) {
            $isRunning = $now->greaterThanOrEqualTo($activeEvent->start_date);
            $options[] = [
                'score' => 500,
                'title' => $isRunning ? 'Termin läuft gerade' : 'Termin rückt näher',
                'message' => $isRunning
                    ? "Du solltest jetzt bei '{$activeEvent->title}' sein (bis " . Carbon::parse($activeEvent->end_date)->format('H:i') . " Uhr)."
                    : "Um " . Carbon::parse($activeEvent->start_date)->format('H:i') . " Uhr steht '{$activeEvent->title}' an. Zeit, sich vorzubereiten.",
                'action_label' => 'Calender öffnen',
                'action_route' => 'admin.calender',
                'icon' => '📅'
            ];
        }

        // ------------------------------------------------------------------
        // 2.5. CEO MISSION (Score 400)
        // ------------------------------------------------------------------
        if (class_exists(\App\Models\Management\ManagementMission::class)) {
            $latestMission = \App\Models\Management\ManagementMission::latest()->first();
            if ($latestMission) {
                $options[] = [
                    'score' => 400,
                    'title' => 'CEO Mission',
                    'message' => "Die aktuelle, vom KI-Agenten festgelegte Hauptmission lautet:\n" . $latestMission->mission_text,
                    'action_label' => 'Verstanden',
                    'action_route' => 'admin.dashboard',
                    'icon' => '🎯'
                ];
            }
        }

        // ------------------------------------------------------------------
        // 3. BUSINESS: PRODUKTION & ECHTE BESTELLUNGEN (Score 200-250)
        // ------------------------------------------------------------------
        $pendingOrders = OrderOrder::whereIn('status', ['pending', 'processing'])
            ->with(['items.product'])
            ->orderBy('is_express', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingOrders->isNotEmpty()) {
            $hasExpress = $pendingOrders->contains('is_express', true);
            $orderCount = $pendingOrders->count();

            // Reale Produktliste zusammenstellen
            $ordersSummary = [];
            foreach ($pendingOrders as $order) {
                $itemNames = $order->items->map(function($it) {
                    $name = $it->product ? $it->product->name : ($it->name ?? 'Produkt');
                    return "{$it->quantity}x {$name}";
                })->implode(', ');

                $ordersSummary[] = "#{$order->order_number} (" . ($itemNames ?: '1x Artikel') . ")";
            }

            $orderListText = implode(', ', $ordersSummary);
            $msg = $orderCount === 1
                ? "Bestellung {$ordersSummary[0]} wartet auf deine Fertigung. Ran an den Laser!"
                : "{$orderCount} Bestellungen warten auf deine Fertigung: {$orderListText}. Ran an den Laser!";

            if ($hasExpress) {
                $msg .= " 🚨 Achtung: Mindestens ein Express-Auftrag dabei!";
            }

            $options[] = [
                'score' => $hasExpress ? 250 : 200,
                'title' => 'Produktion: ' . ($orderCount > 1 ? "{$orderCount} Aufträge offen" : "Bestellung {$pendingOrders->first()->order_number}"),
                'message' => $msg,
                'action_label' => 'Zu den Bestellungen',
                'action_route' => 'admin.orders',
                'icon' => '🚀',
                'real_orders' => $pendingOrders->map(function($o) {
                    return [
                        'order_number' => $o->order_number,
                        'status' => $o->status,
                        'is_express' => (bool)$o->is_express,
                        'total_eur' => round($o->total_price / 100, 2),
                        'items' => $o->items->map(fn($it) => [
                            'name' => $it->product ? $it->product->name : ($it->name ?? 'Produkt'),
                            'quantity' => $it->quantity,
                        ])->toArray()
                    ];
                })->toArray()
            ];
        }

        // ------------------------------------------------------------------
        // 4. VERWALTUNG (Score 100)
        // ------------------------------------------------------------------
        $overdueInvoices = AccountingInvoice::where('status', 'open')->where('due_date', '<', now())->count();
        if ($overdueInvoices > 0) {
            $options[] = [
                'score' => 110,
                'title' => 'Zahlungseingänge',
                'message' => "Da lässt sich jemand Zeit: {$overdueInvoices} Rechnung(en) sind überfällig. Ein Reminder wäre gut.",
                'action_label' => 'Rechnungen prüfen',
                'action_route' => 'admin.invoices',
                'icon' => '💸'
            ];
        }

        $missingReceipt = AccountingSpecialIssue::where('is_business', true)
            ->where(function ($query) {
                $query->whereNull('file_paths')
                    ->orWhere('file_paths', '[]')
                    ->orWhere('file_paths', '')
                    ->orWhere('file_paths', 'null')
                    ->orWhereJsonLength('file_paths', 0);
            })->first();
        if ($missingReceipt) {
            $options[] = [
                'score' => 100,
                'title' => 'Beleg fehlt',
                'message' => "Uns fehlt noch der Beleg für '{$missingReceipt->title}'. Gleich hochladen, dann ist es erledigt.",
                'action_label' => 'Beleg hochladen',
                'action_route' => 'admin.financial-variable-costs',
                'icon' => '📸'
            ];
        }

        // ------------------------------------------------------------------
        // 5. TASKS (Score 20 - 300)
        // ------------------------------------------------------------------
        $nextTask = ManagementTask::where('is_completed', false)
            ->whereNull('parent_id')
            ->where(function($query) {
                $query->whereNull('relevant_from')
                      ->orWhere('relevant_from', '<=', now());
            })
            ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'urgent', 'hoch', 'high', 'mittel', 'medium', 'niedrig')")
            ->orderBy('created_at', 'desc')
            ->first();

        if ($nextTask) {
            $prioScore = match(strtolower($nextTask->priority ?? '')) {
                'urgent' => 300,
                'hoch', 'high' => 150,
                'mittel', 'medium' => 50,
                default => 20
            };

            $options[] = [
                'score' => $prioScore,
                'title' => ($nextTask->priority === 'urgent' ? 'Dringende Aufgabe: ' : 'Aufgabe: ') . $nextTask->title,
                'message' => "Nächster wichtiger Punkt auf deiner Aufgabenliste: '{$nextTask->title}'.",
                'action_label' => 'Zur Aufgabenliste',
                'action_route' => 'admin.tasks',
                'icon' => $nextTask->priority === 'urgent' ? '⚠️' : '✅'
            ];
        }

        // ------------------------------------------------------------------
        // AUSWERTUNG & SORTIERUNG
        // ------------------------------------------------------------------
        usort($options, fn($a, $b) => $b['score'] <=> $a['score']);

        if (empty($options)) {
            $options[] = [
                'score' => 0,
                'title' => 'Freie Bahn!',
                'message' => "Der Shop schnurrt, die Aufgaben sind leer. Klapp den Laptop zu oder gönn dir was Schönes!",
                'action_label' => 'Dashboard öffnen',
                'action_route' => 'admin.dashboard',
                'icon' => '🏆'
            ];
        }

        if (!empty($options)) {
            $topOption = $options[0];
            if ($topOption['score'] === 500) {
                $currentFlow['title'] = 'Termin-Fokus';
                $currentFlow['step'] = $topOption['title'];
                $currentFlow['icon'] = 'calendar';
                $currentFlow['type'] = 'event';
            }
            if ($topOption['score'] >= 1000 && ($topOption['title'] ?? '') === 'Sicherheits-Alarm!') {
                $currentFlow['title'] = 'SYSTEM KRITISCH';
                $currentFlow['step'] = 'Sicherheit prüfen';
                $currentFlow['icon'] = 'shield-exclamation';
                $currentFlow['type'] = 'emergency';
            }
        }

        $financialStatus = $this->calculateFinancialStatus($now);
        $strategicVision = $this->getStrategicVision($now);

        return [
            'flow' => $currentFlow,
            'recommendation' => $options[0],
            'alternatives' => array_slice($options, 1, 2),
            'routines' => $sliderRoutines,
            'financial_status' => $financialStatus,
            'strategic_vision' => $strategicVision,
        ];
    }

    /**
     * Berechnet den aktuellen Finanz- und Kostenstatus vorab aus den realen Daten des Systems
     * (Einnahmen, gewerbliche Fixkosten, Sonderausgaben, offene Pipeline).
     * Das System fängt ohne angenommene Phantasie-Polster bei 0 an.
     */
    private function calculateFinancialStatus(Carbon $now): array
    {
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // 1. Bankkonten (reale Kontostände, falls angebunden, sonst 0.00)
        $businessBankBalance = 0.0;
        $privateBankBalance = 0.0;
        $accountsCount = 0;
        if (class_exists(AccountingBankAccount::class)) {
            $businessBankBalance = (float) AccountingBankAccount::where('is_business', true)->sum('balance');
            $privateBankBalance = (float) AccountingBankAccount::where('is_business', false)->sum('balance');
            $accountsCount = AccountingBankAccount::count();
        }

        // 2. Reale gewerbliche Fixkosten (monatlich berechnet aus AccountingCostItem, exkl. abgelaufener Verträge & Einnahmen)
        $monthlyBizFixedCosts = 0.0;
        $bizCostItemsList = [];
        if (class_exists(AccountingCostItem::class)) {
            $bizCostItems = AccountingCostItem::with('group')
                ->where('is_business', true)
                ->where(function ($q) use ($endOfMonth) {
                    $q->whereNull('first_payment_date')
                      ->orWhere('first_payment_date', '<=', $endOfMonth->toDateString());
                })
                ->where(function ($q) use ($startOfMonth) {
                    $q->whereNull('last_payment_date')
                      ->orWhere('last_payment_date', '>=', $startOfMonth->toDateString());
                })
                ->get();
            foreach ($bizCostItems as $item) {
                if ($item->group && $item->group->type === 'income') {
                    continue;
                }
                $interval = max(1, (int)$item->interval_months);
                $monthlyAmount = abs((float)$item->amount) / $interval;
                $monthlyBizFixedCosts += $monthlyAmount;
                if ($monthlyAmount > 0) {
                    $bizCostItemsList[] = $item->name . ' (' . number_format($monthlyAmount, 2, ',', '.') . ' €/Monat)';
                }
            }
        }
        $monthlyBizFixedCosts = round($monthlyBizFixedCosts, 2);

        // 3. Reale betriebliche Sonderausgaben / Material- & Vorbereitungskosten (nur gewerbliche Ausgaben!)
        $specialIssuesTotal = 0.0;
        $specialIssuesMonth = 0.0;
        if (class_exists(AccountingSpecialIssue::class)) {
            $specialIssuesTotal = round((float) AccountingSpecialIssue::where('is_business', true)->sum('amount'), 2);
            $specialIssuesMonth = round((float) AccountingSpecialIssue::where('is_business', true)
                ->where(function($q) use ($startOfMonth, $endOfMonth, $now) {
                    $q->whereBetween('execution_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                      ->orWhere(function($sub) use ($now) {
                          $sub->whereNull('execution_date')
                              ->whereYear('created_at', $now->year)
                              ->whereMonth('created_at', $now->month);
                      });
                })->sum('amount'), 2);
        }

        // 4. Reale gewerbliche Einnahmen (bezahlte Shop- & Auftragsbestellungen, stornierte/erstattete ausgeschlossen)
        $monthPaidOrders = OrderOrder::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();
        $monthActualSales = round((float)($monthPaidOrders->sum('total_price') / 100), 2);
        $monthPaidOrdersCount = $monthPaidOrders->count();

        $totalRecordedSales = round((float)(OrderOrder::where('payment_status', 'paid')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('total_price') / 100), 2);

        // 5. Reales Netto-Betriebsergebnis des Systems (Einnahmen abzgl. Sonderausgaben)
        $netRecordedCashflow = round($totalRecordedSales + $specialIssuesTotal, 2);

        // 6. Soll-Umsatz laut geprüftem Businessplan (Mein Seelenfunke 2026–2029)
        $businessPlanTargets = [
            2026 => [11 => 2000.0, 12 => 2500.0],
            2027 => [1 => 2200.0, 2 => 3800.0, 3 => 2800.0, 4 => 3200.0, 5 => 4200.0, 6 => 2500.0, 7 => 2400.0, 8 => 2500.0, 9 => 3000.0, 10 => 3500.0, 11 => 5200.0, 12 => 5800.0],
            2028 => [1 => 2800.0, 2 => 4500.0, 3 => 3200.0, 4 => 3800.0, 5 => 5000.0, 6 => 3000.0, 7 => 2800.0, 8 => 3000.0, 9 => 3500.0, 10 => 4200.0, 11 => 6000.0, 12 => 6500.0],
            2029 => [1 => 3200.0, 2 => 4900.0, 3 => 3600.0, 4 => 4100.0, 5 => 5400.0, 6 => 3500.0, 7 => 3100.0, 8 => 3200.0, 9 => 3900.0, 10 => 4800.0, 11 => 6800.0, 12 => 7100.0],
        ];
        $targetSalesCurrentMonth = (float)($businessPlanTargets[$now->year][$now->month] ?? 0.0);
        $salesDelta = round($monthActualSales - $targetSalesCurrentMonth, 2);
        $salesTargetAchievedPercent = $targetSalesCurrentMonth > 0 ? round(($monthActualSales / $targetSalesCurrentMonth) * 100, 1) : 0.0;

        // 7. Produktions-Pipeline & Auftragslage
        $pendingOrders = OrderOrder::whereIn('status', ['pending', 'processing'])->get();
        $pendingOrdersCount = $pendingOrders->count();
        $pendingOrdersVolume = round((float)($pendingOrders->sum('total_price') / 100), 2);

        // 8. Offene Rechnungen & Forderungen
        $openInvoicesSum = 0.0;
        $overdueInvoicesSum = 0.0;
        if (class_exists(AccountingInvoice::class)) {
            $openInvoicesSum = round((float) (AccountingInvoice::where('status', 'open')->sum('total') / 100), 2);
            $overdueInvoicesSum = round((float) (AccountingInvoice::where('status', 'open')->where('due_date', '<', $now)->sum('total') / 100), 2);
        }

        // 9. Gründungszuschuss & Förderphase
        $isGründungszuschussActive = ($now->year == 2026 && $now->month >= 11) || ($now->year == 2027 && $now->month <= 4);
        $gzStatus = $isGründungszuschussActive
            ? "Phase 1 aktiv (2.106,30 €/Monat gesichert – Lebensunterhalt 100% gedeckt)"
            : ($now->lt(Carbon::create(2026, 11, 1)) ? "In Startvorbereitung (Stichtag 01.11.2026 mit 2.106,30 €/Monat Gründungszuschuss)" : "Regulärer Geschäftsbetrieb nach Förderphase 1");

        return [
            'business_bank_balance' => $businessBankBalance,
            'private_bank_balance' => $privateBankBalance,
            'bank_accounts_synced' => $accountsCount > 0,
            'business_fixed_costs_monthly_eur' => $monthlyBizFixedCosts,
            'business_fixed_costs_summary' => !empty($bizCostItemsList) ? implode(', ', $bizCostItemsList) : 'Keine aktiven Fixkosten',
            'variable_costs_special_issues_total_eur' => $specialIssuesTotal,
            'variable_costs_special_issues_month_eur' => $specialIssuesMonth,
            'total_recorded_sales_eur' => $totalRecordedSales,
            'month_actual_sales_eur' => $monthActualSales,
            'month_paid_orders_count' => $monthPaidOrdersCount,
            'net_recorded_cashflow_eur' => $netRecordedCashflow,
            'target_sales_current_month_eur' => $targetSalesCurrentMonth,
            'sales_delta_eur' => $salesDelta,
            'sales_achieved_percent' => $salesTargetAchievedPercent,
            'pending_orders_count' => $pendingOrdersCount,
            'pending_orders_volume_eur' => $pendingOrdersVolume,
            'open_invoices_sum_eur' => $openInvoicesSum,
            'overdue_invoices_sum_eur' => $overdueInvoicesSum,
            'funding_status' => $gzStatus,
            'garagenhof_project_budget' => 20000.0, // 20k Modernisierung Tore & Türen
        ];
    }

    /**
     * Liefert die strategische 10-Jahres-Roadmap der Chefin Alina Steinhauer (Alter 33 -> 43).
     */
    private function getStrategicVision(Carbon $now): array
    {
        $currentAge = 33;
        $targetAge = 43;
        $targetYear = 2036;

        $phaseNum = 1;
        $phaseName = "Phase 1: Das Fundament (Dachboden & Hands-on Chefin)";
        $phaseDesc = "Standort Dachboden mit 1 xTool F2 Ultra Laser. Chefin Alina packt operativ voll an (Fertigung, Gravuren, Etsy, Unboxing, 5-Sterne-Bewertungen). Ziel: 41.100 € Jahresumsatz, 100% Gründungszuschuss-Ausschöpfung.";
        $phaseNextStep = "Garagenhof für 20k modernisieren (Tore & Türen einbauen) als Sprungbrett für den Manufaktur-Umzug.";

        if ($now->year >= 2028 && $now->year <= 2030) {
            $phaseNum = 2;
            $phaseName = "Phase 2: Der Garagenhof-Boost (Skalierung ohne Fremdmiete)";
            $phaseDesc = "Standort eigener Garagenhof. 3-6 Industriemaschinen, 2-5 Festangestellte für Packstraße & Versand, B2B-Start für Firmenkunden. Umsatz: 200k - 1,5 Mio. €. Maximale Gewinnthesaurierung als Eigenkapital für den Bau.";
            $phaseNextStep = "Volle Web-to-Laser Softwareautomatisierung und Vorbereitung Grundstückskauf.";
        } elseif ($now->year >= 2031 && $now->year <= 2033) {
            $phaseNum = 3;
            $phaseName = "Phase 3: Tech-Automatisierung & B2B-Konzern";
            $phaseDesc = "Web-to-Laser Pipeline (KI-Vektorisierung, Direktansteuerung), Corporate Gifts Großkunden, White-Label. Umsatz: 3 - 8 Mio. €, 15-20 Mitarbeiter. Garagenhof platzt aus allen Nähten.";
            $phaseNextStep = "Bauantrag und Baubeginn für das 15-Mio.-Firmengebäude.";
        } elseif ($now->year >= 2034) {
            $phaseNum = 4;
            $phaseName = "Phase 4: Der 15-Mio.-Firmensitz & 'Mit 43 durch sein'";
            $phaseDesc = "Neues 15-Mio.-Firmengebäude bezogen (Showroom, Logistik, Büro). Garagenhof agiert als Vertriebsstätte/Außenlager. 12-20 Mio. € Jahresumsatz. Operative Geschäftsführung entlastet Alina. Ziel erreicht: Mit 43 finanziell frei / durch sein!";
            $phaseNextStep = "Ziel erreicht: Durch sein!";
        }

        return [
            'founder_name' => 'Alina Steinhauer',
            'current_age' => $currentAge,
            'target_age' => $targetAge,
            'target_year' => $targetYear,
            'ultimate_goal' => "Neues 15-Millionen-Euro-Firmengebäude (Showroom, Logistik, Büro), Garagenhof agiert als Vertriebsstätte / Außenlager. Mit 43 Jahren finanziell 'durch sein'.",
            'current_phase' => [
                'number' => $phaseNum,
                'name' => $phaseName,
                'description' => $phaseDesc,
                'next_step' => $phaseNextStep,
            ],
            'intermediate_hub' => "Eigener Garagenhof: Wird demnächst für 20k modernisiert (neue Tore + Türen). Dient ab Phase 2 als mietfreie Manufaktur & später als Vertriebsstätte.",
            'guidelines_for_ai' => [
                'chefin_hands_on' => "Alina packt als Chefin in Phase 1 operativ voll an (Laser bedienen, Bestellungen fertigen, Belege pflegen, Prozesse aufsetzen).",
                'strategic_framing' => "Jede Tagesaufgabe und jede gefertigte Bestellung ist ein direkter Baustein für den Umzug in den Garagenhof und den 15-Mio.-Firmensitz bis 43.",
                'financial_awareness' => "Nutze immer die vorberechneten Finanz- und Soll-Zahlen aus 'financial_status', ohne den Nutzer nach Zahlen zu fragen oder separate Tools aufzurufen."
            ]
        ];
    }

    /**
     * Ermittelt die wichtigste Bestellung.
     */
    public function getPriorityOrder()
    {
        return OrderOrder::query()
            ->whereIn('status', ['pending', 'processing'])
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc')
            ->orderBy('created_at', 'asc')
            ->first();
    }
}
