<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cart\Cart;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\AbandonedCartReminder;

class SendAbandonedCartReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:send-abandoned-cart-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Versendet rechtssichere E-Mail-Erinnerungen für verlassene Warenkörbe unter Beachtung von Werbeeinwilligungen (§ 7 UWG / DSGVO).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Konfiguration abrufen
        $redLimit = (int) shop_setting('cart_abandoned_red_hours', 24);
        $maxDays = (int) shop_setting('cart_abandoned_max_days', 7);
        $requireConsent = (bool) shop_setting('cart_abandoned_require_consent', true);

        $this->info("Prüfe verlassene Warenkörbe (Älter als {$redLimit}h, jünger als {$maxDays} Tage, Rechtssicherheits-Modus: " . ($requireConsent ? 'AKTIV' : 'INAKTIV') . ")...");

        // 2. Relevante Warenkörbe abfragen (nur registrierte Kunden mit Artikeln, innerhalb des Zeitfensters)
        $carts = Cart::with(['customer.profile', 'items.product'])
            ->where('updated_at', '<=', now()->subHours($redLimit))
            ->where('updated_at', '>=', now()->subDays($maxDays))
            ->whereNull('reminder_email_sent_at')
            ->whereNotNull('customer_id')
            ->has('items')
            ->get();

        $sentCount = 0;
        $skippedNoConsentCount = 0;
        $skippedUnsubscribedCount = 0;

        foreach ($carts as $cart) {
            $customer = $cart->customer;

            if (!$customer || empty($customer->email)) {
                continue;
            }

            // A. Gesetzlicher Werbewiderspruch (§ 7 Abs. 3 Nr. 4 UWG / Art. 21 DSGVO)
            if ($customer->profile?->cart_reminder_unsubscribed_at !== null) {
                $this->line("Überspringe Warenkorb #{$cart->id}: Kunde {$customer->email} hat Warenkorb-Erinnerungen abbestellt (Opt-Out).");
                $skippedUnsubscribedCount++;
                continue;
            }

            // B. Rechtssicherheitsprüfung: Liegt ein verifiziertes Werbe-Opt-In (Double-Opt-In) vor?
            if ($requireConsent && !$customer->hasMarketingConsent()) {
                $this->line("Überspringe Warenkorb #{$cart->id}: Kunde {$customer->email} hat keine verifizierte Werbeeinwilligung (§ 7 Abs. 2 UWG).");
                $skippedNoConsentCount++;
                continue;
            }

            // C. Erinnerungs-Mail versenden
            try {
                Mail::to($customer->email)->send(new AbandonedCartReminder($cart));
                $cart->update(['reminder_email_sent_at' => now()]);
                $sentCount++;
                $this->info("Erinnerung erfolgreich an {$customer->email} für Warenkorb #{$cart->id} gesendet.");
            } catch (\Exception $e) {
                Log::error("Fehler beim automatischen Versand der Warenkorberinnerung an {$customer->email}: " . $e->getMessage());
                $this->error("Fehler beim Versand an {$customer->email}: " . $e->getMessage());
            }
        }

        $this->info("Abgeschlossen: {$sentCount} Mails versendet | {$skippedNoConsentCount} wegen fehlender Einwilligung übersprungen | {$skippedUnsubscribedCount} wegen Opt-Out übersprungen.");

        return Command::SUCCESS;
    }
}
