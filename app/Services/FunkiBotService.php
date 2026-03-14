<?php

namespace App\Services;

use App\Models\Blog\BlogPost;
use App\Models\Customer\Customer;
use App\Models\Funki\FunkiLog;
use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Quote\QuoteRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FunkiBotService
{
    // --- MASTER METHODEN (ARBEITSANWEISUNGEN) ---

    /**
     * Die ultimative Ansage ermitteln.
     * Sammelt alle Handlungsoptionen, bewertet sie nach dem Score-System
     * und liefert den aktuellen Tages-Flow, eine Top-Empfehlung, Alternativen
     * sowie die gesamte Tagesroutine für den Slider.
     */
    public function getUltimateCommand(): array
    {
        $now = \Carbon\Carbon::now();
        $options = [];

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
        $allRoutines = \App\Models\Funki\FunkiDayRoutine::with('steps')
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
                        'message' => "Deine Nachtruhe ist aktiv: " . $currentFlow['step'] . ". " . ($routine->message ?? "Zeit für Regeneration. Klapp den Laptop zu und gute Nacht!"),
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
        if ($currentFlow['type'] === 'free') {
            $options[] = [
                'score' => 2000,
                'title' => 'Schlafenszeit',
                'message' => "Du befindest dich außerhalb deiner regulären Routinen. Das bedeutet höchste Priorität für Schlaf und Erholung! Klapp den Laptop zu.",
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
        $failedLogins = \App\Models\LoginAttempt::where('success', false)
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
        // Erkennt Termine, die in den nächsten 45 Min starten ODER gerade laufen
        $activeEvent = \App\Models\CalendarEvent::whereDate('start_date', $now->toDateString())
            ->where(function($query) use ($now) {
                $query->where('start_date', '>', $now) // Zukünftig
                ->where('start_date', '<=', $now->copy()->addMinutes(45))
                    ->orWhere(function($q) use ($now) {
                        $q->where('start_date', '<=', $now) // Läuft bereits
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
                    ? "Du solltest jetzt bei '{$activeEvent->title}' sein (bis " . \Carbon\Carbon::parse($activeEvent->end_date)->format('H:i') . " Uhr)."
                    : "Um " . \Carbon\Carbon::parse($activeEvent->start_date)->format('H:i') . " Uhr steht '{$activeEvent->title}' an. Zeit, sich vorzubereiten.",
                'action_label' => 'Kalender öffnen',
                'action_route' => 'admin.funki-kalender',
                'icon' => '📅'
            ];
        }

        // ------------------------------------------------------------------
        // 3. BUSINESS (Score 200)
        // ------------------------------------------------------------------
        $prioOrder = \App\Models\Order\Order::whereIn('status', ['pending', 'processing'])
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
        $overdueInvoices = \App\Models\Invoice::where('status', 'open')->where('due_date', '<', now())->count();
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

        $missingReceipt = \App\Models\Financial\FinanceSpecialIssue::whereNull('file_paths')->first();
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
        // 5. TODOS (Score 10)
        // ------------------------------------------------------------------
        $nextTodo = \App\Models\Todo::where('is_completed', false)
            ->whereNull('parent_id')
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderBy('position', 'asc')
            ->first();

        if ($nextTodo) {
            $prioScore = match($nextTodo->priority) {
                'high' => 25,
                'medium' => 15,
                default => 10
            };

            $options[] = [
                'score' => $prioScore,
                'title' => 'ToDo abhaken',
                'message' => "Wenn du gerade Luft hast: Nächster Punkt auf der Liste ist '{$nextTodo->title}'.",
                'action_label' => 'Zur Liste',
                'action_route' => 'admin.todos',
                'icon' => '✅'
            ];
        }

        // ------------------------------------------------------------------
        // AUSWERTUNG & SORTIERUNG
        // ------------------------------------------------------------------
        usort($options, fn($a, $b) => $b['score'] <=> $a['score']);

        // FALLBACK bei leeren Optionen
        if (empty($options)) {
            $options[] = [
                'score' => 0,
                'title' => 'Freie Bahn!',
                'message' => "Der Shop schnurrt, die ToDos sind leer. Klapp den Laptop zu oder gönn dir was Schönes!",
                'action_label' => 'Dashboard öffnen',
                'action_route' => 'admin.dashboard',
                'icon' => '🏆'
            ];
        }

        // --- DYNAMISCHE FLOW-ANPASSUNG ---
        // Wenn die Top-Empfehlung eine hohe Priorität hat (z.B. Termin oder Sicherheit),
        // überschreiben wir den Header-Flow, damit Anzeige und Empfehlung zusammenpassen.
        if (!empty($options)) {
            $topOption = $options[0];

            // Bei Terminen (Score 500) passen wir den Header an
            if ($topOption['score'] === 500) {
                $currentFlow['title'] = 'Termin-Fokus';
                $currentFlow['step'] = $topOption['title'];
                $currentFlow['icon'] = 'calendar';
                $currentFlow['type'] = 'event';
            }

            // Bei Sicherheitsalarmen überschreiben wir ebenfalls
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
     * Ermittelt die wichtigste Bestellung (Logik aus Orders.php übertragen).
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

    /**
     * Prüft den Produkt-Katalog auf Probleme (Low Stock, Drafts).
     */
    public function getProductStatus()
    {
        $lowStockCount = Product::where('track_quantity', true)
            ->where('quantity', '<=', (int)shop_setting('inventory_low_stock_threshold', 5))
            ->count();

        $draftCount = Product::where('status', 'draft')->count();

        if ($lowStockCount > 0) {
            return [
                'status' => 'warning',
                'message' => "Achtung! {$lowStockCount} Artikel haben einen kritischen Lagerbestand.",
                'action_route' => 'admin.products',
                'action_label' => 'Lager prüfen'
            ];
        }

        if ($draftCount > 0) {
            return [
                'status' => 'info',
                'message' => "Du hast {$draftCount} Produkte im Entwurf. Zeit für den Release?",
                'action_route' => 'admin.products',
                'action_label' => 'Entwürfe ansehen'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Das Sortiment ist glänzend aufgestellt.',
            'action_route' => 'admin.products',
            'action_label' => 'Sortiment'
        ];
    }

    /**
     * Prüft offene Angebote.
     */
    public function getQuoteStatus()
    {
        $openQuotes = QuoteRequest::where('status', 'open')->count();
        $expiredQuotes = QuoteRequest::where('status', 'open')->where('expires_at', '<', now())->count();

        if ($expiredQuotes > 0) {
            return [
                'status' => 'warning',
                'message' => "{$expiredQuotes} Angebote sind abgelaufen. Nachfassen?",
                'action_route' => 'admin.quote-requests',
                'action_label' => 'Angebote prüfen'
            ];
        }

        if ($openQuotes > 0) {
            return [
                'status' => 'info',
                'message' => "{$openQuotes} Angebote warten auf Bestätigung durch den Kunden.",
                'action_route' => 'admin.quote-requests',
                'action_label' => 'Angebote ansehen'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Alle Anfragen sind bearbeitet.',
            'action_route' => 'admin.quote-requests',
            'action_label' => 'Angebote'
        ];
    }

    /**
     * Prüft offene Rechnungen.
     */
    public function getInvoiceStatus()
    {
        $overdueCount = Invoice::where('status', 'open')
            ->where('due_date', '<', now())
            ->count();

        $openCount = Invoice::where('status', 'open')->count();

        if ($overdueCount > 0) {
            return [
                'status' => 'danger',
                'message' => "Alarm! {$overdueCount} Rechnungen sind überfällig. Mahnung erforderlich.",
                'action_route' => 'admin.invoices',
                'action_label' => 'Mahnwesen'
            ];
        }

        if ($openCount > 0) {
            return [
                'status' => 'info',
                'message' => "{$openCount} Rechnungen sind noch offen (aber in der Frist).",
                'action_route' => 'admin.invoices',
                'action_label' => 'Buchhaltung'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Kasse stimmt. Alle Rechnungen bezahlt.',
            'action_route' => 'admin.invoices',
            'action_label' => 'Rechnungen'
        ];
    }

    /**
     * Prüft Blog-Aktivität.
     */
    public function getBlogStatus()
    {
        $lastPost = BlogPost::where('status', 'published')->latest('published_at')->first();

        if (!$lastPost) {
            return [
                'status' => 'info',
                'message' => 'Der Blog ist noch leer. Schreib deine erste Geschichte!',
                'action_route' => 'admin.blog',
                'action_label' => 'Schreiben'
            ];
        }

        $daysAgo = $lastPost->published_at->diffInDays(now());

        if ($daysAgo > 30) {
            return [
                'status' => 'warning',
                'message' => "Der letzte Beitrag ist {$daysAgo} Tage her. Die Leser warten!",
                'action_route' => 'admin.blog',
                'action_label' => 'Neuen Beitrag'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Der Blog ist aktuell. Letzter Post vor ' . $daysAgo . ' Tagen.',
            'action_route' => 'admin.blog',
            'action_label' => 'Blog'
        ];
    }

    /**
     * Prüft Versandstatus (Processing Orders).
     */
    public function getShippingStatus()
    {
        $toShip = Order::where('status', 'processing')->count();

        if ($toShip > 0) {
            return [
                'status' => 'warning',
                'message' => "{$toShip} Pakete warten auf den Versand. Packstation bereit?",
                'action_route' => 'admin.orders',
                'action_label' => 'Versenden'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Versandlager ist leer. Alles verschickt!',
            'action_route' => 'admin.orders',
            'action_label' => 'Bestellungen'
        ];
    }

    /**
     * Prüft Systemgesundheit (Fehlgeschlagene Logins, Wartungsmodus).
     */
    public function getSystemStatus()
    {
        $maintenance = filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN);

        if ($maintenance) {
            return [
                'status' => 'warning',
                'message' => 'Der Wartungsmodus ist AKTIV. Der Shop ist für Kunden nicht erreichbar.',
                'action_route' => 'admin.configuration',
                'action_label' => 'Ausschalten'
            ];
        }

        $failedLogins = LoginAttempt::where('success', false)
            ->where('attempted_at', '>', now()->subHours(24))
            ->count();

        if ($failedLogins > 5) {
            return [
                'status' => 'danger',
                'message' => "Sicherheits-Warnung: {$failedLogins} fehlgeschlagene Logins in 24h.",
                'action_route' => 'admin.user-management',
                'action_label' => 'Logs prüfen'
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Alle Systeme laufen stabil.',
            'action_route' => 'admin.dashboard',
            'action_label' => 'Systemcheck'
        ];
    }
}
