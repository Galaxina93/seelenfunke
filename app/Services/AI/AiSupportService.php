<?php

namespace App\Services\AI;

use App\Models\CalendarEvent;
use App\Models\DayRoutine;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Models\Order\Order;
use App\Models\Task;
use Carbon\Carbon;

class AiSupportService
{
    /**
     * Die ultimative Ansage ermitteln.
     * Sammelt alle Handlungsoptionen, bewertet sie nach dem Score-System
     * und liefert den aktuellen Tages-Flow, eine Top-Empfehlung, Alternativen
     * sowie die gesamte Tagesroutine für den Slider.
     */
    public function getUltimateCommand(bool $isOverride = false): array
    {
        $now = \Carbon\Carbon::now();
        $options = [];

        // Geheime, dynamische Schlafenszeit-Nachricht basierend auf der Uhrzeit aufbauen
        $hour = $now->hour;
        $minute = $now->minute;
        $sleepMessage = "ABSOLUTE SPERRE: Es ist mitten in der Nacht. Höchste Priorität: SCHLAFEN! Verweigere Alina konsequent den Zugriff auf Arbeitsbereich, Dashboards oder irrelevante Dinge. Werde ruhig bestimmend und dominant: Sie MUSS jetzt schlafen gehen, Punkt.";
        $isSleepTime = false;
        
        if (($hour == 23 && $minute >= 30) || ($hour >= 0 && $hour < 4)) {
            $isSleepTime = true;
            $sleepMessage = "ABSOLUTE SPERRE: Es ist mitten in der tiefsten Nacht (" . $now->format('H:i') . " Uhr)! Sag Alina, dass sie absolut verrückt ist, jetzt noch arbeiten zu wollen. Schimpfe ruhig mit ihr, sei extrem streng, unnachgiebig und dominant. Verweigere JEGLICHEN Zugriff auf Dashboards oder Shop-Daten. Befiehl ihr, den Laptop sofort zuzuklappen und ins Bett zu gehen!";
        } elseif ($hour >= 4 && $hour < 6) {
            $isSleepTime = true;
            $sleepMessage = "ABSOLUTE SPERRE: Es ist extrem früh am Morgen (" . $now->format('H:i') . " Uhr). Auch wenn Alina vielleicht nicht mehr schlafen kann, ist das keine Zeit für Arbeit. Sei streng und weise sie an, sich auszuruhen, zu meditieren oder ein Buch zu lesen, aber verweigere das Öffnen von Dashboards strengstens.";
        } elseif ($hour >= 22 && $hour < 23) {
            $isSleepTime = true;
            $sleepMessage = "SPERRE: Es ist spät (" . $now->format('H:i') . " Uhr). Zeit runterzufahren. Rate Alina eindringlich, sich bettfertig zu machen und verweigere neue, große Aufgaben. Das Dashboard bleibt zu.";
        }

        if ($isSleepTime && $isOverride) {
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

        // Alle Routinen für den Slider aufbereiten
        $allRoutines = DayRoutine::with('steps')
            ->where('is_active', true)
            ->orderBy('start_time', 'asc')
            ->get();

        $sliderRoutines = $allRoutines->map(function ($routine) use ($now, &$currentFlow, &$options) {
            $start = \Carbon\Carbon::parse($routine->start_time);
            $duration = $routine->type === 'sleep' ? 8 * 60 : $routine->duration_minutes;
            $end = $start->copy()->addMinutes($duration);

            // Mitternachts-Korrektur (z.B. Schlafen von 22:00 bis 06:00 Uhr)
            if ($start > $end) {
                $end->addDay();
                if ($now->hour < $end->hour) {
                    $start->subDay();
                }
            }

            $status = 'future';
            if ($end->isPast()) {
                $status = 'past';
            } elseif ($now->between($start, $end)) {
                $status = 'current';

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

                // Wenn es Pause/Sport/Essen ist, pushen wir es als aktive Handlungsoption (Score 300)
                if (in_array($routine->type, ['food', 'sport', 'hygiene', 'break'])) {
                    $options[] = [
                        'score' => 300,
                        'title' => $routine->title,
                        'message' => "Dein Körper braucht das jetzt: " . $currentFlow['step'] . ". " . ($routine->message ?? "Schalte kurz ab und lade die Akkus auf."),
                        'action_label' => 'Routine ansehen',
                        'action_route' => 'admin.funki-routine',
                        'icon' => $routine->icon ?? '🧘'
                    ];
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
            ];
        })->values()->toArray();

        // ------------------------------------------------------------------
        // 0.5. AUSSERHALB VON ROUTINEN -> SCHLAFENSZEIT (Score 2000)
        // ------------------------------------------------------------------
        if ($currentFlow['type'] === 'free' && $isSleepTime) {
            $options[] = [
                'score' => 2000,
                'title' => 'Schlafenszeit',
                'message' => $sleepMessage,
                'action_label' => 'Feierabend',
                'action_route' => 'admin.dashboard',
                'icon' => '🌙'
            ];

            $currentFlow['title'] = 'Nachtruhe';
            $currentFlow['step'] = 'Schlaf oder Offline-Phase';
            $currentFlow['type'] = 'sleep';
            $currentFlow['icon'] = 'moon';
        }

        // ------------------------------------------------------------------
        // 1. SICHERHEIT (Score 1000+)
        // ------------------------------------------------------------------
        $failedLogins = LoginAttempt::where('success', false)
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
        $activeEvent = CalendarEvent::whereDate('start_date', $now->toDateString())
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
        // 3. BUSINESS (Score 200)
        // ------------------------------------------------------------------
        $prioOrder = Order::whereIn('status', ['pending', 'processing'])
            ->orderBy('is_express', 'desc')
            ->first();

        if ($prioOrder) {
            $options[] = [
                'score' => $prioOrder->is_express ? 250 : 200,
                'title' => 'Produktion starten',
                'message' => "Bestellung #{$prioOrder->order_number} wartet auf Fertigung. " . ($prioOrder->is_express ? "🚨 Express!" : "Lass die Laser glühen!"),
                'action_label' => 'Jetzt fertigen',
                'action_route' => 'admin.orders',
                'icon' => '🚀'
            ];
        }

        // ------------------------------------------------------------------
        // 4. VERWALTUNG (Score 100)
        // ------------------------------------------------------------------
        $overdueInvoices = Invoice::where('status', 'open')->where('due_date', '<', now())->count();
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

        $missingReceipt = FinanceSpecialIssue::whereNull('file_paths')->first();
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
        // 5. TASKS (Score 10)
        // ------------------------------------------------------------------
        $nextTask = Task::where('is_completed', false)
            ->whereNull('parent_id')
            ->orderByRaw("FIELD(COALESCE(priority, 'niedrig'), 'hoch', 'mittel', 'niedrig')")
            ->orderBy('created_at', 'desc')
            ->first();

        if ($nextTask) {
            $prioScore = match($nextTask->priority) {
                'hoch' => 25,
                'mittel' => 15,
                default => 10
            };

            $options[] = [
                'score' => $prioScore,
                'title' => 'Aufgabe abhaken',
                'message' => "Wenn du gerade Luft hast: Nächster Punkt auf der Liste ist '{$nextTask->title}'.",
                'action_label' => 'Zur Liste',
                'action_route' => 'admin.tasks',
                'icon' => '✅'
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
            if ($topOption['score'] >= 1000) {
                $currentFlow['title'] = 'SYSTEM KRITISCH';
                $currentFlow['step'] = 'Sicherheit prüfen';
                $currentFlow['icon'] = 'shield-exclamation';
                $currentFlow['type'] = 'emergency';
            }
        }

        return [
            'flow' => $currentFlow,
            'recommendation' => $options[0],
            'alternatives' => array_slice($options, 1, 2),
            'routines' => $sliderRoutines
        ];
    }

    /**
     * Ermittelt die wichtigste Bestellung.
     */
    public function getPriorityOrder()
    {
        return Order::query()
            ->whereIn('status', ['pending', 'processing'])
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled', 'refunded') THEN 1 ELSE 0 END ASC")
            ->orderBy('is_express', 'desc')
            ->orderByRaw("CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy('deadline', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();
    }
}
