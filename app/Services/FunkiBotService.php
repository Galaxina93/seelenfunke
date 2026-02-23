<?php
namespace App\Services;

use App\Mail\AutomaticNewsletterMail;
use App\Mail\AutomaticVoucherMail;
use App\Models\Blog\BlogPost;
use App\Models\Customer\Customer;
use App\Models\Funki\FunkiLog;
use App\Models\Funki\FunkiNewsletter;
use App\Models\Funki\FunkiVoucher;
use App\Models\Invoice;
use App\Models\LoginAttempt;
use App\Models\NewsletterSubscriber;
use App\Models\Order\Order;
use App\Models\Product\Product;
use App\Models\Quote\QuoteRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
                'action_route' => 'admin.financial-categories-special-editions',
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
                'action_route' => 'admin.funki-todos',
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
