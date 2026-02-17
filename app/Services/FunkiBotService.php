<?php

namespace App\Services;

use App\Mail\AutomaticNewsletterMail;
use App\Mail\AutomaticVoucherMail;
use App\Models\Coupon;
use App\Models\FunkiVoucher;
use App\Models\Customer\Customer;
use App\Models\FunkiLog;
use App\Models\NewsletterSubscriber;
use App\Models\FunkiNewsletter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FunkiBotService
{
    // =========================================================================
    // 1. BASIS & KALENDER LOGIK
    // =========================================================================

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

    // =========================================================================
    // 2. NEWSLETTER MARKETING LOGIK
    // =========================================================================

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

    // =========================================================================
    // 3. GUTSCHEIN KAMPAGNEN LOGIK
    // =========================================================================

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
        // Available Events laden um den Namen zu mappen
        $availableEvents = $this->getAvailableEvents();

        foreach ($automations as $auto) {
            if ($auto->isPersonalEvent()) continue;

            $targetDate = $this->getHolidayDate($auto->trigger_event, $year);
            $sendDate = $targetDate->copy()->subDays($auto->days_offset);

            if ($sendDate->isPast() && !$sendDate->isToday()) {
                $targetDate = $this->getHolidayDate($auto->trigger_event, $year + 1);
                $sendDate = $targetDate->copy()->subDays($auto->days_offset);
            }

            // Event Name ermitteln
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

    /**
     * Sendet eine Test-Gutschein-Mail (Vorschau) an den Admin.
     */
    public function sendVoucherPreviewMail($subject, $content, $codePattern, $value, $type): string
    {
        $adminEmail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
        $adminName = 'Admin';

        // Dummy Coupon generieren
        $dummyCode = $this->generateCouponCode($codePattern, $adminName);
        $tempCoupon = new Coupon();
        $tempCoupon->forceFill([
            'code' => $dummyCode,
            'type' => $type,
            'value' => ($type === 'fixed') ? (int)($value * 100) : (int)$value, // Umrechnung für Anzeige
        ]);

        Mail::to($adminEmail)->send(new AutomaticVoucherMail($tempCoupon, $adminName, '[FUNKI TEST] ' . $subject, $content));

        return $adminEmail;
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
