<?php
namespace App\Services;

use App\Mail\AutomaticNewsletterMail;
use App\Mail\AutomaticVoucherMail;
use App\Models\Blog\BlogPost;
use App\Models\CalendarEvent;
use App\Models\Coupon;
use App\Models\DayRoutine;
use App\Models\Financial\FinanceCostItem;
use App\Models\FunkiVoucher;
use App\Models\Customer\Customer;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\FunkiLog;
use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Models\NewsletterSubscriber;
use App\Models\FunkiNewsletter;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Quote\QuoteRequest;
use App\Models\ShopSetting;
use App\Models\Todo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FunkiBotService
{
    // --- MASTER METHODEN (ARBEITSANWEISUNGEN) ---

    /**
     * Die ultimative Ansage ermitteln.
     * Logik: Jede Baustelle bekommt ein "Dringlichkeits-Score".
     * Der höchste Score gewinnt und wird als "Tue jetzt das" ausgegeben.
     */
    public function getUltimateCommand(): array
    {
        $baustellen = [];
        $now = Carbon::now();

        // ------------------------------------------------------------------
        // LEVEL 1: KRITISCHE SICHERHEIT (Geht über ALLES)
        // ------------------------------------------------------------------
        $failedLogins = LoginAttempt::where('success', false)
            ->where('attempted_at', '>', now()->subHours(24))
            ->count();

        if ($failedLogins > 5) {
            return [
                'score' => 1000,
                'title' => 'Sicherheits-Alarm!',
                'message' => "ALARM! {$failedLogins} Fehlversuche registriert. Lass alles stehen und liegen. Prüfe die IP-Adressen!",
                'action_label' => 'Sicherheit prüfen',
                'action_route' => 'admin.user-management',
                'icon' => '🛑',
                'instruction' => 'SOFORT HANDELN:'
            ];
        }

        // ------------------------------------------------------------------
        // LEVEL 2: KALENDER & TERMINE (Geht vor Routine)
        // ------------------------------------------------------------------

        // Termine laden (Einmalig & Wiederkehrend)
        $calendarEvents = CalendarEvent::whereNull('recurrence')
            ->whereDate('start_date', $now->toDateString())
            ->get();

        $recurringEvents = CalendarEvent::whereNotNull('recurrence')->get();
        $allRelevantEvents = $calendarEvents->merge($recurringEvents);

        $activeAppointment = null;
        $appointmentState = ''; // 'active', 'upcoming_urgent', 'upcoming_reminder'

        foreach ($allRelevantEvents as $event) {
            $effectiveStart = $event->start_date->copy();
            $effectiveEnd = $event->end_date ? $event->end_date->copy() : $effectiveStart->copy()->addHour();

            // Wiederholung berechnen
            if ($event->recurrence) {
                $matchesToday = false;
                $startOfToday = $now->copy()->startOfDay();

                if ($event->recurrence_end_date && $event->recurrence_end_date < $startOfToday) continue;

                switch ($event->recurrence) {
                    case 'daily': $matchesToday = true; break;
                    case 'weekly': $matchesToday = $event->start_date->isSameDayOfWeek($now); break;
                    case 'monthly': $matchesToday = $event->start_date->day === $now->day; break;
                    case 'yearly': $matchesToday = $event->start_date->format('m-d') === $now->format('m-d'); break;
                }

                if (!$matchesToday) continue;

                $effectiveStart->setDate($now->year, $now->month, $now->day);
                $effectiveEnd->setDate($now->year, $now->month, $now->day);
            }

            // CHECK 1: Läuft der Termin gerade?
            if ($now->between($effectiveStart, $effectiveEnd)) {
                $activeAppointment = $event;
                $appointmentState = 'active';
                break; // Top Prio, Schleife abbrechen
            }

            // CHECK 2: Baldiger Start (Erinnerung)
            if ($effectiveStart->isFuture()) {
                $minutesToStart = $now->diffInMinutes($effectiveStart);

                // Hat der Termin eine eigene Erinnerung? Wenn nein, Standard 45 Min.
                $reminderThreshold = $event->reminder_minutes ?? 45;

                // Logik:
                // 1. Sehr dringend (< 15 min): Immer warnen (höchste Prio bei Upcoming)
                // 2. Erinnerung (< Threshold): Warnen

                if ($minutesToStart <= 15) {
                    $activeAppointment = $event;
                    $appointmentState = 'upcoming_urgent'; // Rot
                } elseif ($minutesToStart <= $reminderThreshold) {
                    // Nur überschreiben, wenn wir nicht schon was Dringenderes (Urgent) haben
                    if ($appointmentState !== 'upcoming_urgent') {
                        $activeAppointment = $event;
                        $appointmentState = 'upcoming_reminder'; // Gelb
                    }
                }
            }
        }

        if ($activeAppointment) {
            $effectiveStart = $activeAppointment->start_date->copy();
            if ($activeAppointment->recurrence) {
                $effectiveStart->setDate($now->year, $now->month, $now->day);
            }

            $message = "";
            $score = 500;
            $instruction = "Termin wahrnehmen:";

            if ($appointmentState === 'active') {
                $message = "Der Termin '{$activeAppointment->title}' läuft seit " . $effectiveStart->format('H:i') . " Uhr. Fokus!";
                $score = 600;
            } elseif ($appointmentState === 'upcoming_urgent') {
                $message = "Achtung! In weniger als 15 Minuten startet '{$activeAppointment->title}' ({$effectiveStart->format('H:i')}). Mach dich bereit!";
                $score = 550;
                $instruction = "Gleich geht's los:";
            } else {
                // Reminder Phase (nach Config oder 45 min Standard)
                $diff = $now->diffInMinutes($effectiveStart);
                $message = "Erinnerung: In {$diff} Minuten ({$effectiveStart->format('H:i')}) ist '{$activeAppointment->title}'. Bereite dich vor.";
                $score = 500;
            }

            $baustellen[] = [
                'score' => $score,
                'title' => ($appointmentState === 'active' ? 'Termin läuft' : 'Termin steht an'),
                'message' => $message,
                'action_label' => 'Kalender öffnen',
                'action_route' => 'admin.funki-kalender', // <-- ANGEPASSTE ROUTE
                'icon' => '📅',
                'instruction' => $instruction
            ];
        }

        // Wenn ein Termin aktiv/warnend ist, geben wir ihn sofort zurück (unterbricht Routine)
        if (!empty($baustellen)) {
            return $baustellen[0];
        }

        // ------------------------------------------------------------------
        // LEVEL 3: BIO-RHYTHMUS (ROUTINE)
        // ------------------------------------------------------------------

        // Kernarbeitszeit definieren (09:00 - 22:00)
        $isWorkTime = $now->between(
            Carbon::createFromTime(9, 0, 0),
            Carbon::createFromTime(22, 0, 0)
        );

        $activeRoutine = DayRoutine::with('steps')->where('is_active', true)->get()->filter(function ($routine) use ($now) {
            $start = Carbon::parse($routine->start_time);
            if ($routine->type === 'sleep') {
                $end = $start->copy()->addHours(8);
            } else {
                $end = $start->copy()->addMinutes($routine->duration_minutes);
            }
            return $now->between($start, $end);
        })->first();

        if ($activeRoutine) {
            // Schritt-Berechnung der Routine
            $startTime = Carbon::parse($activeRoutine->start_time);
            $minutesPassed = $startTime->diffInMinutes($now);

            $currentStepName = "Fokus halten";
            $currentStepIndex = 1;
            $accumulatedMinutes = 0;
            $nextStepName = null;

            foreach ($activeRoutine->steps as $step) {
                $stepDuration = $step->duration_minutes;
                if ($minutesPassed >= $accumulatedMinutes && $minutesPassed < ($accumulatedMinutes + $stepDuration)) {
                    $currentStepName = $step->title;
                    $currentStepIndex = $step->position;
                    $nextStep = $activeRoutine->steps->where('position', $step->position + 1)->first();
                    $nextStepName = $nextStep ? $nextStep->title : 'Abschluss';
                    break;
                }
                $accumulatedMinutes += $stepDuration;
            }

            if ($minutesPassed >= $accumulatedMinutes && $activeRoutine->steps->count() > 0) {
                $currentStepName = "Pufferzeit / Abschluss";
            }

            $baustellen[] = [
                'score' => 300,
                'title' => $activeRoutine->title,
                'message' => "Es ist " . $now->format('H:i') . ". Kein Termin liegt an, also folge deiner Routine:\n\n👉 **$currentStepName**\n\n(Danach: " . ($nextStepName ?? 'Fertig') . ")",
                'action_label' => 'Routine ansehen',
                'action_route' => 'admin.funki-routine', // <-- ANGEPASSTE ROUTE
                'icon' => $this->getIconForType($activeRoutine->type),
                'instruction' => 'Bio-Rhythmus:'
            ];
        }

        // Wenn Routine aktiv ist -> Routine ausgeben
        if (!empty($baustellen)) {
            return $baustellen[0];
        }

        // ------------------------------------------------------------------
        // BREAKPOINT: RUHEZEIT
        // ------------------------------------------------------------------
        if (!$isWorkTime) {
            return [
                'score' => 1000,
                'title' => 'Ruhemodus',
                'message' => "Es ist " . $now->format('H:i') . " Uhr. Keine Termine, keine Routine. Ruh dich aus!",
                'action_label' => 'Gute Nacht',
                'action_route' => 'admin.dashboard',
                'icon' => '🌙',
                'instruction' => 'System schläft'
            ];
        }

        // ------------------------------------------------------------------
        // LEVEL 4: OPERATIVES GESCHÄFT (Lückenfüller)
        // ------------------------------------------------------------------

        if ($isWorkTime) {
            // 1. BESTELLUNGEN
            $prioOrder = Order::whereIn('status', ['pending', 'processing'])
                ->orderBy('is_express', 'desc')
                ->orderByRaw("CASE WHEN deadline IS NULL THEN 1 ELSE 0 END ASC")
                ->orderBy('deadline', 'asc')
                ->first();

            if ($prioOrder) {
                $score = $prioOrder->is_express ? 220 : 200;
                if ($prioOrder->deadline && $prioOrder->deadline->isPast()) $score += 100;

                $baustellen[] = [
                    'score' => $score,
                    'title' => 'Produktion starten',
                    'message' => "Freie Zeit nutzen! Bestellung #{$prioOrder->order_number} wartet. " . ($prioOrder->is_express ? "EXPRESS! " : "") . "Kunde: {$prioOrder->billing_address['first_name']} {$prioOrder->billing_address['last_name']}.",
                    'action_label' => 'Jetzt fertigen',
                    'action_route' => 'admin.orders',
                    'icon' => '🚀'
                ];
            }

            // 2. BELEGE
            $missingReceipt = FinanceSpecialIssue::where(function($q) {
                $q->whereNull('file_paths')->orWhere('file_paths', '[]')->orWhere('file_paths', '');
            })->orderBy('execution_date', 'desc')->first();

            if ($missingReceipt) {
                $baustellen[] = [
                    'score' => 110,
                    'title' => 'Beleg fehlt!',
                    'message' => "Buchhaltung machen: Für '{$missingReceipt->title}' ({{ number_format($missingReceipt->amount, 2) }}€) fehlt der Beleg.",
                    'action_label' => 'Beleg hochladen',
                    'action_route' => 'admin.financial-categories-special-editions',
                    'icon' => '📸'
                ];
            }

            // 3. RECHNUNGEN
            $overdueInvoices = Invoice::where('status', 'open')->where('due_date', '<', now())->count();
            if ($overdueInvoices > 0) {
                $baustellen[] = [
                    'score' => 105,
                    'title' => 'Zahlungseingänge prüfen',
                    'message' => "Wir haben {$overdueInvoices} überfällige Rechnungen. Schau nach dem Geld.",
                    'action_label' => 'Rechnungen prüfen',
                    'action_route' => 'admin.invoices',
                    'icon' => '💸'
                ];
            }

            // 4. VERTRAG
            $missingContract = FinanceCostItem::whereNull('contract_file_path')->first();
            if ($missingContract) {
                $baustellen[] = [
                    'score' => 85,
                    'title' => 'Vertrag hinterlegen',
                    'message' => "Für '{$missingContract->name}' fehlt der Vertrag im System.",
                    'action_label' => 'Vertrag hochladen',
                    'action_route' => 'admin.financial-contracts-groups',
                    'icon' => '📄'
                ];
            }

            // 5. LAGER
            $lowStock = Product::where('track_quantity', true)
                ->where('quantity', '<=', (int)shop_setting('inventory_low_stock_threshold', 5))
                ->where('status', 'active')->first();
            if ($lowStock) {
                $baustellen[] = [
                    'score' => 80,
                    'title' => 'Lager auffüllen',
                    'message' => "Bestand niedrig: Nur noch {$lowStock->quantity}x '{$lowStock->name}'.",
                    'action_label' => 'Lager verwalten',
                    'action_route' => 'admin.products',
                    'icon' => '📦'
                ];
            }

            // 6. BLOG
            $lastBlog = BlogPost::where('status', 'published')->latest('published_at')->first();
            if (!$lastBlog || $lastBlog->published_at->diffInDays(now()) > 30) {
                $baustellen[] = [
                    'score' => 30,
                    'title' => 'Content erstellen',
                    'message' => "Dein letzter Blogpost ist lange her. Zeit für neue Inspiration!",
                    'action_label' => 'Beitrag schreiben',
                    'action_route' => 'admin.blog',
                    'icon' => '✍️'
                ];
            }
        }

        // ENTSCHEIDUNG LEVEL 4
        if (!empty($baustellen)) {
            usort($baustellen, fn($a, $b) => $b['score'] <=> $a['score']);
            $winner = $baustellen[0];
            $winner['instruction'] = $winner['instruction'] ?? "Freie Zeit nutzen:";
            return $winner;
        }

        // ------------------------------------------------------------------
        // LEVEL 5: TODOS
        // ------------------------------------------------------------------
        if ($isWorkTime) {
            $nextTodo = Todo::where('is_completed', false)
                ->whereNull('parent_id')
                ->orderBy('position', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($nextTodo) {
                $openCount = Todo::where('is_completed', false)->whereNull('parent_id')->count();
                return [
                    'score' => 10,
                    'title' => 'Todo abarbeiten',
                    'message' => "Alles sauber im Shop! Zeit für die Warteschlange ({$openCount} offen). Nächste Aufgabe: '{$nextTodo->title}'.",
                    'action_label' => 'Zur ToDo Liste',
                    'action_route' => 'admin.funki-todos', // <-- ANGEPASSTE ROUTE
                    'icon' => '✅',
                    'instruction' => 'Jetzt erledigen:'
                ];
            }
        }

        // ------------------------------------------------------------------
        // LEVEL 6: FREIZEIT
        // ------------------------------------------------------------------

        // Versuche den User zu ermitteln (Fallback für CLI/Bot Context)
        $name = 'Du';

        // Prüfen, ob wir im Web-Context sind
        $guard = (new \App\Models\User)->getGuard();
        if ($guard) {
            $user = Auth::guard($guard)->user();
            if ($user) {
                $name = $user->first_name;
            }
        }

        return [
            'score' => 0,
            'title' => 'Mach Sport!',
            'message' => "Unglaublich, {$name}! Keine Termine, Routine durch, Shop leer, Todos erledigt. Geh raus und mach Sport! 🏋️‍♀️",
            'action_label' => 'Dashboard öffnen',
            'action_route' => 'admin.dashboard',
            'icon' => '🏆',
            'instruction' => 'Status: Perfekt'
        ];
    }

    /**
     * Kleiner Helper für Routine-Icons
     */
    private function getIconForType($type)
    {
        return match($type) {
            'food'    => '🍔',
            'hygiene' => '🪥',
            'sport'   => '🏋️',
            'work'    => '💼',
            'sleep'   => '🛌',
            default   => '⏰'
        };
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

    // --- AUTOMATION METHODEN (Bestehend) ---

    public function getAvailableEvents(): array
    {
        return [
            'valentines' => 'Valentinstag (14.02.)',
            'womens_day' => 'Weltfrauentag (08.03.)',
            'easter' => 'Ostern (Variabel)',
            'mothers_day' => 'Muttertag (2. So im Mai)',
            'fathers_day' => 'Vatertag (Christi Himmelfahrt)',
            'halloween' => 'Halloween (31.10.)',
            'advent_1' => '1. Advent (Variabel)',
            'christmas' => 'Weihnachten (24.12.)',
            'new_year' => 'Neujahr (01.01.)',
            'sale_summer' => 'Sommer Sale (01.07.)',
            'sale_winter' => 'Winter Sale (01.01.)',
        ];
    }

    public function getHolidayDate($key, $year): Carbon
    {
        switch ($key) {
            case 'valentines': return Carbon::create($year, 2, 14);
            case 'womens_day': return Carbon::create($year, 3, 8);
            case 'halloween': return Carbon::create($year, 10, 31);
            case 'christmas': return Carbon::create($year, 12, 24);
            case 'new_year': return Carbon::create($year, 1, 1);
            case 'sale_summer': return Carbon::create($year, 7, 1);
            case 'sale_winter': return Carbon::create($year, 1, 1);
            case 'easter': return Carbon::createFromDate($year, 3, 21)->addDays(easter_days($year));
            case 'mothers_day': return Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY);
            case 'fathers_day': return Carbon::createFromDate($year, 3, 21)->addDays(easter_days($year))->addDays(39);
            case 'advent_1': return Carbon::create($year, 11, 26)->next(Carbon::SUNDAY);
            default: return Carbon::now();
        }
    }

    public function sendNewsletterBatch(FunkiNewsletter $template)
    {
        Log::info("FunkiBot: Starte Newsletter-Versand für '{$template->subject}'");
        $subscribers = NewsletterSubscriber::where('is_verified', true)->get();
        $count = 0;
        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->queue(new AutomaticNewsletterMail($template, $subscriber));
            $count++;
        }
        return $count;
    }

    public function sendPreviewMail(string $subject, string $content): string
    {
        $adminEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
        $tempTemplate = new FunkiNewsletter();
        $tempTemplate->forceFill([
            'subject' => '[FUNKI TEST] ' . $subject,
            'content' => $content,
            'days_offset' => 0,
            'target_event_key' => 'test_preview'
        ]);

        $dummySubscriber = new NewsletterSubscriber();
        $dummySubscriber->email = $adminEmail;
        $dummySubscriber->first_name = 'Admin';

        Mail::to($adminEmail)->send(new AutomaticNewsletterMail($tempTemplate, $dummySubscriber));

        return $adminEmail;
    }

    public function getNewsletterTimeline($year)
    {
        $templates = FunkiNewsletter::where('is_active', true)->get();
        $events = [];

        foreach ($this->getAvailableEvents() as $key => $label) {
            $date = $this->getHolidayDate($key, $year);
            $tmpl = $templates->where('target_event_key', $key)->first();

            if ($tmpl) {
                $sendDate = $date->copy()->subDays($tmpl->days_offset);
                if ($sendDate->isPast() && !$sendDate->isToday()) {
                    $date = $this->getHolidayDate($key, $year + 1);
                    $sendDate = $date->copy()->subDays($tmpl->days_offset);
                }

                $events[] = [
                    'date' => $sendDate,
                    'title' => $tmpl->subject,
                    'template_id' => $tmpl->id,
                    'type' => 'mail',
                    'event_key' => $key,
                    'event_name' => $label
                ];
            }
        }
        return collect($events)->sortBy('date');
    }

    public function archiveNewsletter($id): void
    {
        FunkiNewsletter::where('id', $id)->update(['is_active' => false]);
    }

    public function restoreNewsletter($id): void
    {
        FunkiNewsletter::where('id', $id)->update(['is_active' => true]);
    }

    public function getVoucherTriggerEvents(): array
    {
        $global = $this->getAvailableEvents();
        $personal = ['registered_date' => 'Jahrestag der Registrierung'];
        return array_merge($personal, $global);
    }

    public function getVoucherTimeline($year)
    {
        $automations = FunkiVoucher::where('is_active', true)->get();
        $events = [];
        $availableEvents = $this->getAvailableEvents();

        foreach ($automations as $auto) {
            if ($auto->isPersonalEvent()) continue;

            $targetDate = $this->getHolidayDate($auto->trigger_event, $year);
            $sendDate = $targetDate->copy()->subDays($auto->days_offset);

            if ($sendDate->isPast() && !$sendDate->isToday()) {
                $targetDate = $this->getHolidayDate($auto->trigger_event, $year + 1);
                $sendDate = $targetDate->copy()->subDays($auto->days_offset);
            }

            $eventName = $availableEvents[$auto->trigger_event] ?? 'Event';

            $events[] = [
                'id' => $auto->id,
                'title' => $auto->title,
                'date' => $sendDate,
                'code' => $auto->code_pattern,
                'value' => $auto->coupon_type === 'percent' ? $auto->coupon_value . '%' : ($auto->coupon_value / 100) . '€',
                'type' => 'voucher',
                'event_key' => $auto->trigger_event,
                'event_name' => $eventName
            ];
        }
        return collect($events)->sortBy('date');
    }

    public function runVoucherAutomation(FunkiVoucher $automation)
    {
        if ($automation->isPersonalEvent()) {
            $this->processPersonalVoucherEvent($automation);
        } else {
            $this->processGlobalVoucherEvent($automation);
        }
    }

    protected function processGlobalVoucherEvent(FunkiVoucher $automation)
    {
        $year = date('Y');
        $targetDate = $this->getHolidayDate($automation->trigger_event, $year);
        $sendDate = $targetDate->copy()->subDays($automation->days_offset);

        if (!$sendDate->isSameDay(now())) return;

        $log = FunkiLog::start('voucher:global', "Globale Kampagne: {$automation->title}", 'system');

        try {
            $subscribers = NewsletterSubscriber::where('is_verified', true)->get();
            $count = 0;
            foreach ($subscribers as $sub) {
                $this->createAndSendCoupon($automation, $sub->email, $sub->first_name ?? 'Kunde');
                $count++;
            }
            $log->finish('success', "Globale Gutscheine ($count Stk.) erfolgreich versendet.", ['automation_id' => $automation->id, 'count' => $count]);
        } catch (\Exception $e) {
            $log->finish('error', 'Fehler: ' . $e->getMessage());
        }
    }

    protected function processPersonalVoucherEvent(FunkiVoucher $automation)
    {
        if ($automation->trigger_event === 'registered_date') {
            $customers = Customer::whereRaw("DATE_FORMAT(created_at, '%m-%d') = ?", [date('m-d')])
                ->whereYear('created_at', '<', date('Y'))->get();

            if ($customers->count() > 0) {
                $log = FunkiLog::start('voucher:personal', "Jubiläums-Gutscheine: {$automation->title}", 'system');
                try {
                    $count = 0;
                    foreach ($customers as $customer) {
                        $this->createAndSendCoupon($automation, $customer->email, $customer->first_name);
                        $count++;
                    }
                    $log->finish('success', "An $count Kunden zum Jahrestag versendet.", ['automation_id' => $automation->id, 'count' => $count]);
                } catch (\Exception $e) {
                    $log->finish('error', $e->getMessage());
                }
            }
        }
    }

    public function generateCouponCode($pattern, $name = 'Gast')
    {
        $code = strtoupper($pattern);
        $code = str_replace('{YEAR}', date('Y'), $code);
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', Str::slug($name));
        $code = str_replace('{NAME}', strtoupper(substr($cleanName, 0, 5)), $code);
        return $code;
    }
}
