<?php
namespace App\Services;

use App\Mail\AutomaticNewsletterMail;
use App\Mail\AutomaticVoucherMail;
use App\Models\Blog\BlogPost;
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
        // LEVEL 0: BIO-RHYTHMUS & ZEITFENSTER
        // ------------------------------------------------------------------

        // Kernarbeitszeit definieren (09:00 - 22:00)
        $isWorkTime = $now->between(
            Carbon::createFromTime(9, 0, 0),
            Carbon::createFromTime(22, 0, 0)
        );

        // Prüfen, ob gerade eine Routine aktiv ist (z.B. Mittagessen, Zähne putzen)
        // Wir laden alle aktiven Routinen und prüfen, ob JETZT gerade der Zeitraum ist
        $activeRoutine = DayRoutine::with('steps')->where('is_active', true)->get()->filter(function ($routine) use ($now) {
            $start = Carbon::parse($routine->start_time);

            // Sonderfall: Nachtruhe (Ende ist "unendlich" bzw. bis zum Morgen)
            if ($routine->type === 'sleep') {
                $end = $start->copy()->addHours(8); // Annahme: Bis morgens
            } else {
                $end = $start->copy()->addMinutes($routine->duration_minutes);
            }

            return $now->between($start, $end);
        })->first();

        // Wenn eine Routine aktiv ist, bekommt sie einen sehr hohen Score (150),
        // damit sie normale Arbeit unterbricht, aber keine Sicherheitswarnungen.
        if ($activeRoutine) {
            // GENAUE SCHRITT-BERECHNUNG
            $startTime = Carbon::parse($activeRoutine->start_time);
            $minutesPassed = $startTime->diffInMinutes($now);

            $currentStepName = "Allgemein";
            $currentStepIndex = 1;
            $accumulatedMinutes = 0;
            $nextStepName = null;

            foreach ($activeRoutine->steps as $step) {
                $stepDuration = $step->duration_minutes;

                // Wenn wir noch im Zeitfenster dieses Schritts sind
                if ($minutesPassed >= $accumulatedMinutes && $minutesPassed < ($accumulatedMinutes + $stepDuration)) {
                    $currentStepName = $step->title;
                    $currentStepIndex = $step->position;
                    // Nächsten Schritt finden
                    $nextStep = $activeRoutine->steps->where('position', $step->position + 1)->first();
                    $nextStepName = $nextStep ? $nextStep->title : 'Abschluss';
                    break;
                }

                $accumulatedMinutes += $stepDuration;
            }

            // Wenn wir über der kalkulierten Zeit der Steps sind, aber noch in der Routine-Zeit (Puffer)
            if ($minutesPassed >= $accumulatedMinutes && $activeRoutine->steps->count() > 0) {
                $currentStepName = "Letzte Handgriffe / Pufferzeit";
            }

            $baustellen[] = [
                'score' => 150,
                'title' => $activeRoutine->title . " (Step $currentStepIndex)",
                'message' => "Es ist " . $now->format('H:i') . ". Laut Plan solltest du jetzt folgendes tun:\n\n👉 **$currentStepName**\n\n(Danach: " . ($nextStepName ?? 'Fertig') . ")",
                'action_label' => 'Routine anzeigen',
                'action_route' => 'admin.funki', // Tab Routine
                'icon' => $this->getIconForType($activeRoutine->type),
                'instruction' => 'Aktueller Fokus:'
            ];
        }

        // ------------------------------------------------------------------
        // LEVEL 1: KRITISCHE GESCHÄFTSVORFÄLLE (Sicherheit geht immer vor)
        // ------------------------------------------------------------------

        // 1. SICHERHEIT: UNBEFUGTE LOGINS (Score 250)
        $failedLogins = LoginAttempt::where('success', false)
            ->where('attempted_at', '>', now()->subHours(24))
            ->count();

        if ($failedLogins > 5) {
            $baustellen[] = [
                'score' => 250,
                'title' => 'Sicherheits-Alarm!',
                'message' => "Jemand rüttelt an der Tür! Ich habe {$failedLogins} Fehlversuche registriert. Prüfe sofort die IP-Adressen im User-Management.",
                'action_label' => 'Sicherheit prüfen',
                'action_route' => 'admin.user-management',
                'icon' => '🛑'
            ];
        }

        // ------------------------------------------------------------------
        // BREAKPOINT: RUHEZEIT
        // Wenn keine Routine aktiv ist, keine Sicherheitswarnung vorliegt
        // UND wir außerhalb der Arbeitszeit sind -> Feierabend erzwingen.
        // ------------------------------------------------------------------
        if (!$isWorkTime && empty($baustellen)) {
            return [
                'score' => 1000, // Gewinnt gegen alles außer Sicherheit
                'title' => 'Ruhemodus',
                'message' => "Es ist " . $now->format('H:i') . " Uhr. Außerhalb der Kernzeit (09:00 - 22:00) wird nicht gearbeitet. Erhol dich gut!",
                'action_label' => 'Gute Nacht',
                'action_route' => 'admin.dashboard',
                'icon' => '🌙',
                'instruction' => 'System schläft'
            ];
        }

        // ------------------------------------------------------------------
        // LEVEL 2: OPERATIVES GESCHÄFT (Nur während Arbeitszeit)
        // ------------------------------------------------------------------

        if ($isWorkTime) {
            // 2. PRODUKTION: BESTELLUNGEN (Score 200+)
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
                    'message' => "Bestellung #{$prioOrder->order_number} wartet. " . ($prioOrder->is_express ? "ACHTUNG: EXPRESS-PRIO! " : "") . "Kunde: {$prioOrder->billing_address['first_name']} {$prioOrder->billing_address['last_name']}.",
                    'action_label' => 'Jetzt fertigen',
                    'action_route' => 'admin.orders',
                    'icon' => '🚀'
                ];
            }

            // 3. BUCHHALTUNG: SONDERAUSGABEN OHNE BELEG (Score 110)
            $missingReceipt = FinanceSpecialIssue::where(function($q) {
                $q->whereNull('file_paths')->orWhere('file_paths', '[]')->orWhere('file_paths', '');
            })->orderBy('execution_date', 'desc')->first();

            if ($missingReceipt) {
                $baustellen[] = [
                    'score' => 110,
                    'title' => 'Beleg fehlt!',
                    'message' => "Für die Ausgabe '{$missingReceipt->title}' vom {$missingReceipt->execution_date->format('d.m.Y')} ({{ number_format($missingReceipt->amount, 2) }}€) hast du noch keinen Beleg hinterlegt.",
                    'action_label' => 'Beleg hochladen',
                    'action_route' => 'admin.financial-categories-special-editions',
                    'icon' => '📸'
                ];
            }

            // 4. BUCHHALTUNG: ÜBERFÄLLIGE RECHNUNGEN (Score 105)
            $overdueInvoices = Invoice::where('status', 'open')->where('due_date', '<', now())->count();
            if ($overdueInvoices > 0) {
                $baustellen[] = [
                    'score' => 105,
                    'title' => 'Zahlungseingänge prüfen',
                    'message' => "Wir haben {$overdueInvoices} überfällige Rechnungen. Schau nach, ob das Geld da ist oder schick eine freundliche Mahnung.",
                    'action_label' => 'Rechnungen prüfen',
                    'action_route' => 'admin.invoices',
                    'icon' => '💸'
                ];
            }

            // 5. ORDNUNG: FIXKOSTEN OHNE VERTRAG (Score 85)
            $missingContract = FinanceCostItem::whereNull('contract_file_path')->first();
            if ($missingContract) {
                $baustellen[] = [
                    'score' => 85,
                    'title' => 'Vertrag hinterlegen',
                    'message' => "Für '{$missingContract->name}' fehlt der Vertrag im System. Bitte einscannen und hochladen.",
                    'action_label' => 'Vertrag hochladen',
                    'action_route' => 'admin.financial-contracts-groups',
                    'icon' => '📄'
                ];
            }

            // 6. LAGER: BESTANDSPRÜFUNG (Score 80)
            $lowStock = Product::where('track_quantity', true)
                ->where('quantity', '<=', (int)shop_setting('inventory_low_stock_threshold', 5))
                ->where('status', 'active')->first();
            if ($lowStock) {
                $baustellen[] = [
                    'score' => 80,
                    'title' => 'Lager auffüllen',
                    'message' => "Nur noch {$lowStock->quantity}x '{$lowStock->name}' verfügbar. Bestelle rechtzeitig nach!",
                    'action_label' => 'Lager verwalten',
                    'action_route' => 'admin.products',
                    'icon' => '📦'
                ];
            }

            // 7. CONTENT: BLOG (Score 30)
            $lastBlog = BlogPost::where('status', 'published')->latest('published_at')->first();
            if (!$lastBlog || $lastBlog->published_at->diffInDays(now()) > 30) {
                $baustellen[] = [
                    'score' => 30,
                    'title' => 'Inspiration teilen',
                    'message' => "Dein letzter Blogpost ist schon eine Weile her. Lass uns die Kunden mal wieder mit einer schönen Geschichte begeistern.",
                    'action_label' => 'Beitrag schreiben',
                    'action_route' => 'admin.blog',
                    'icon' => '✍️'
                ];
            }
        }

        // ------------------------------------------------------------------
        // ENTSCHEIDUNG LEVEL 1 & 2
        // ------------------------------------------------------------------
        if (!empty($baustellen)) {
            // Sortieren nach Score (höchster zuerst)
            usort($baustellen, fn($a, $b) => $b['score'] <=> $a['score']);

            $winner = $baustellen[0];
            $winner['instruction'] = $winner['instruction'] ?? "Tue jetzt exakt das:";
            return $winner;
        }

        // ------------------------------------------------------------------
        // LEVEL 3: LÜCKENFÜLLER (TODOS)
        // ------------------------------------------------------------------
        // Wenn keine operativen Probleme vorliegen und Arbeitszeit ist:
        if ($isWorkTime) {
            $nextTodo = Todo::where('is_completed', false)
                ->whereNull('parent_id') // Nur Hauptaufgaben
                ->orderBy('position', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if ($nextTodo) {
                $openCount = Todo::where('is_completed', false)->whereNull('parent_id')->count();
                return [
                    'score' => 10,
                    'title' => 'Todo abarbeiten',
                    'message' => "Operativ ist alles sauber. Zeit für die Warteschlange! ({$openCount} offen). Nächste Aufgabe: '{$nextTodo->title}'.",
                    'action_label' => 'Zur ToDo Liste',
                    'action_route' => 'admin.funki', // Tab Todo
                    'icon' => '✅',
                    'instruction' => 'Jetzt erledigen:'
                ];
            }
        }

        // ------------------------------------------------------------------
        // LEVEL 4: SPORT & FREIZEIT (Alles erledigt)
        // ------------------------------------------------------------------

        $user = Auth::user();
        $name = $user ? $user->first_name : 'Du';

        return [
            'score' => 0,
            'title' => 'Mach Sport!',
            'message' => "Unglaublich, {$name}! Das System ist blitzsauber, alle Todos sind erledigt. Geh trainieren oder genieß den Tag. Du hast es dir verdient! 🏋️‍♀️",
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
