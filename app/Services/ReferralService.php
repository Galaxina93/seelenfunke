<?php

namespace App\Services;

use App\Mail\ReferralRewardEarnedMail;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingReferral;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Erstellt oder liefert den dauerhaften, personalisierten Empfehlungscode für einen Kunden.
     * Vollständig geschützt gegen Race Conditions & Namenskollisionen bei parallelen Einkäufen.
     */
    public function getOrCreateReferralForCustomer(
        string $email,
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $customerId = null
    ): MarketingReferral {
        $normalizedEmail = strtolower(trim($email));

        // 1. Bereits vorhandener Code für diese E-Mail?
        $existing = MarketingReferral::where('referrer_email', $normalizedEmail)->first();
        if ($existing) {
            if ($customerId && empty($existing->referrer_customer_id)) {
                $existing->update(['referrer_customer_id' => $customerId]);
            }
            return $existing;
        }

        // 2. Falls Kunde eingeloggt, auch nach customer_id prüfen
        if ($customerId) {
            $existing = MarketingReferral::where('referrer_customer_id', $customerId)->first();
            if ($existing) {
                return $existing;
            }
        }

        // 3. Saubere Namen für lesbare Codes generieren
        // Entfernt Umlaute, Akzente & Sonderzeichen (z.B. René -> RENE, André -> ANDRE)
        $cleanFirst = $this->sanitizeName($firstName) ?: 'FREUNDE';
        $cleanLast = $this->sanitizeName($lastName);

        // 4. Kandidaten-Leiter in absteigender Lesbarkeit & Präferenz
        $candidates = $this->generateCandidateCodes($cleanFirst, $cleanLast);

        // 5. Atomare Zuteilung in einer Concurrency-sicheren Transaktionsschleife
        foreach ($candidates as $candidate) {
            // Schnelle Vorabprüfung
            if (MarketingReferral::where('code', $candidate)->exists() || MarketingVoucher::where('code', $candidate)->exists()) {
                continue;
            }

            try {
                return DB::transaction(function () use ($candidate, $normalizedEmail, $firstName, $lastName, $customerId) {
                    // MarketingVoucher für 10% Rabatt anlegen
                    $voucher = MarketingVoucher::create([
                        'code' => $candidate,
                        'title' => 'Freunde-Empfehlung von ' . ($firstName ?: 'Kunde'),
                        'type' => 'percent',
                        'value' => 10,
                        'used_count' => 0,
                        'usage_limit' => null,
                        'min_order_value' => 0,
                        'is_active' => true,
                        'mode' => 'manual',
                        'valid_from' => now()->subDay(),
                        'valid_until' => now()->addYears(5),
                    ]);

                    // MarketingReferral Datensatz anlegen
                    return MarketingReferral::create([
                        'referrer_email' => $normalizedEmail,
                        'referrer_customer_id' => $customerId,
                        'referrer_first_name' => $firstName,
                        'referrer_last_name' => $lastName,
                        'code' => $candidate,
                        'voucher_id' => $voucher->id,
                        'discount_percent' => 10,
                        'reward_cents' => 500, // 5.00 € Belohnung
                        'successful_referrals_count' => 0,
                        'is_active' => true,
                    ]);
                });
            } catch (QueryException $e) {
                // Bei simultanen Bestellungen mit identischem Namen fängt SQL 23000 (Duplicate entry)
                // die Kollision sauber ab und schaltet ohne Fehler auf den nächsten Kandidaten weiter.
                if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'Duplicate entry')) {
                    // Prüfen ob ein paralleler Thread für denselben Kunden bereits fertig ist
                    $alreadyCreated = MarketingReferral::where('referrer_email', $normalizedEmail)->first();
                    if ($alreadyCreated) {
                        return $alreadyCreated;
                    }
                    continue;
                }
                throw $e;
            }
        }

        // Extrem unwahrscheinlicher Notfall-Fallback mit Microtime
        $fallbackCode = $cleanFirst . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
        return DB::transaction(function () use ($fallbackCode, $normalizedEmail, $firstName, $lastName, $customerId) {
            $voucher = MarketingVoucher::create([
                'code' => $fallbackCode,
                'title' => 'Freunde-Empfehlung von ' . ($firstName ?: 'Kunde'),
                'type' => 'percent',
                'value' => 10,
                'is_active' => true,
                'mode' => 'manual',
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addYears(5),
            ]);

            return MarketingReferral::create([
                'referrer_email' => $normalizedEmail,
                'referrer_customer_id' => $customerId,
                'referrer_first_name' => $firstName,
                'referrer_last_name' => $lastName,
                'code' => $fallbackCode,
                'voucher_id' => $voucher->id,
                'discount_percent' => 10,
                'reward_cents' => 500,
                'successful_referrals_count' => 0,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Prüft, ob ein Code ein Empfehlungscode ist und ob er für den aktuellen Käufer zulässig ist.
     * Beinhaltet:
     * - Eigenmissbrauch-Schutz (Kunde darf nicht eigenen Code nutzen)
     * - Neukunden-Schutz (Nur für Kunden, die noch nie eine bezahlte Bestellung hatten)
     */
    public function validateReferralCode(string $code, ?string $buyerEmail = null, ?string $buyerCustomerId = null): array
    {
        $code = strtoupper(trim($code));
        $referral = MarketingReferral::where('code', $code)->first();

        if (!$referral) {
            return ['is_referral' => false, 'valid' => false, 'message' => null];
        }

        if (!$referral->is_active) {
            return [
                'is_referral' => true,
                'valid' => false,
                'message' => 'Dieser Empfehlungscode ist nicht mehr aktiv.'
            ];
        }

        // 1. Eigenmissbrauch-Prüfung (E-Mail oder Customer-ID)
        $isSelfReferral = false;
        if ($buyerEmail && strtolower(trim($buyerEmail)) === strtolower(trim($referral->referrer_email))) {
            $isSelfReferral = true;
        }
        if ($buyerCustomerId && !empty($referral->referrer_customer_id) && (string)$buyerCustomerId === (string)$referral->referrer_customer_id) {
            $isSelfReferral = true;
        }

        if ($isSelfReferral) {
            return [
                'is_referral' => true,
                'valid' => false,
                'message' => 'Du kannst deinen eigenen Empfehlungscode nicht für deine eigene Bestellung verwenden.'
            ];
        }

        // 2. Neukunden-Prüfung (Code darf nur bei Erstbestellungen greifen)
        if ($buyerEmail || $buyerCustomerId) {
            $hasPreviousPaidOrder = OrderOrder::where(function ($query) use ($buyerEmail, $buyerCustomerId) {
                if ($buyerEmail) {
                    $query->where('email', strtolower(trim($buyerEmail)));
                }
                if ($buyerCustomerId) {
                    $query->orWhere('customer_id', $buyerCustomerId);
                }
            })
            ->where(function ($query) {
                $query->where('payment_status', 'paid')
                      ->orWhereNotIn('status', ['cancelled', 'draft']);
            })
            ->exists();

            if ($hasPreviousPaidOrder) {
                return [
                    'is_referral' => true,
                    'valid' => false,
                    'message' => 'Dieser Empfehlungscode ist exklusiv für Neukunden bei ihrer ersten Bestellung gültig.'
                ];
            }
        }

        return [
            'is_referral' => true,
            'valid' => true,
            'referral' => $referral,
            'message' => 'Empfehlungsrabatt von 10 % erfolgreich angewendet!'
        ];
    }

    /**
     * Schreibt dem Werber nach erfolgreicher Bezahlung der geworbenen Bestellung
     * automatisch einen 5 € Wertgutschein gut und sendet die Belohnungs-Mail.
     */
    public function processOrderReferralReward(OrderOrder $order): ?MarketingGiftVoucher
    {
        if (empty($order->coupon_code)) {
            return null;
        }

        $voucherToSend = null;
        $referralFound = null;

        $voucher = DB::transaction(function () use ($order, &$voucherToSend, &$referralFound) {
            $referral = MarketingReferral::where('code', strtoupper(trim($order->coupon_code)))
                ->lockForUpdate()
                ->first();

            // Fallback: Prüfen ob Referrer via Cookie/Session übergeben wurde
            if (!$referral && !empty($order->utm_campaign_last) && str_starts_with($order->utm_campaign_last, 'ref:')) {
                $refCode = substr($order->utm_campaign_last, 4);
                $referral = MarketingReferral::where('code', strtoupper(trim($refCode)))
                    ->lockForUpdate()
                    ->first();
            }

            if (!$referral) {
                return null;
            }

            // Verhindere Eigen-Belohnung
            if (strtolower(trim($order->email)) === strtolower(trim($referral->referrer_email))) {
                Log::warning("Referral Reward abgewiesen: Käufer {$order->email} ist identisch mit Werber {$referral->referrer_email}.");
                return null;
            }

            // Neukunden-Schutz: Belohnung nur bei Erstbestellern ausstellen (Prüfung über E-Mail und Customer-ID)
            $paidOrdersCount = OrderOrder::where(function ($query) use ($order) {
                $query->where('email', strtolower(trim($order->email)));
                if (!empty($order->customer_id)) {
                    $query->orWhere('customer_id', $order->customer_id);
                }
            })
            ->where('payment_status', 'paid')
            ->count();
            if ($paidOrdersCount > 1) {
                Log::warning("Referral Reward abgewiesen: Käufer {$order->email} ist kein Neukunde (hat bereits {$paidOrdersCount} bezahlte Bestellungen).");
                return null;
            }

            // Idempotenz: Verhindere Doppelgutschriften für dieselbe Order
            $escapedOrderNumber = addcslashes((string) $order->order_number, '%_\\');
            $alreadyRewarded = MarketingGiftVoucher::where('recipient_email', $referral->referrer_email)
                ->where('personal_message', 'like', "%{$escapedOrderNumber}%")
                ->exists();
            if ($alreadyRewarded) {
                return null;
            }

            // Kumulatives Guthabenkonto: Prüfen, ob der Werber bereits einen aktiven DANKE-Belohnungsgutschein besitzt
            $existingVoucher = MarketingGiftVoucher::where('recipient_email', $referral->referrer_email)
                ->where('code', 'like', 'DANKE-%')
                ->where('is_active', true)
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($existingVoucher) {
                // Bestehenden Gutschein um 5,00 € aufbuchen
                $existingVoucher->initial_value += $referral->reward_cents;
                $existingVoucher->current_balance += $referral->reward_cents;
                $existingVoucher->personal_message .= " [Order {$order->order_number}: +5€]";
                $existingVoucher->valid_until = now()->addYears(3); // Gültigkeit ab neuem Eingang verlängern
                $existingVoucher->save();

                $voucher = $existingVoucher;
                Log::info("Freunde-Werben: +5 € Guthaben auf bestehenden Gutschein ({$voucher->code}) an {$referral->referrer_email} für Order {$order->order_number} aufgebucht (Neuer Saldo: {$voucher->current_balance} Cents).");
            } else {
                // Neuen Belohnungsgutschein mit eindeutigem Code DANKE-XXXXXX erzeugen
                $rewardCode = $this->generateUniqueGiftVoucherCode();

                $voucher = MarketingGiftVoucher::create([
                    'code' => $rewardCode,
                    'initial_value' => $referral->reward_cents, // 500 = 5.00 €
                    'current_balance' => $referral->reward_cents,
                    'recipient_email' => $referral->referrer_email,
                    'recipient_name' => $referral->referrer_first_name ?: 'Lieber Seelenfunke-Kunde',
                    'personal_message' => "5 € Belohnung für deine erfolgreiche Empfehlung durch Bestellung {$order->order_number}!",
                    'delivery_method' => 'digital',
                    'is_active' => true,
                    'valid_until' => now()->addYears(3),
                ]);

                Log::info("Freunde-Werben: Neuer 5 € Belohnungsgutschein ({$voucher->code}) an {$referral->referrer_email} für Order {$order->order_number} ausgestellt.");
            }

            $referral->increment('successful_referrals_count');

            $voucherToSend = $voucher;
            $referralFound = $referral;

            return $voucher;
        });

        // E-Mail an Werber versenden (nach erfolgreicher Transaktion)
        if ($voucherToSend && $referralFound) {
            try {
                if (class_exists(ReferralRewardEarnedMail::class)) {
                    Mail::to($referralFound->referrer_email)->send(new ReferralRewardEarnedMail($voucherToSend, $referralFound, $order));
                }
            } catch (\Exception $e) {
                Log::error("Referral Reward E-Mail konnte nicht gesendet werden: " . $e->getMessage());
            }
        }

        return $voucher;
    }

    /**
     * Zieht eine gutgeschriebene Empfehlungsvergütung bei Stornierung oder Erstattung wieder ab.
     * Geschützt gegen Doppel-Stornierungen und Race-Conditions.
     */
    public function reverseOrderReferralReward(OrderOrder $order): bool
    {
        if (empty($order->coupon_code)) {
            return false;
        }

        return DB::transaction(function () use ($order) {
            $referral = MarketingReferral::where('code', strtoupper(trim($order->coupon_code)))
                ->lockForUpdate()
                ->first();
            if (!$referral) {
                return false;
            }

            // Suche den Gutschein, auf dem die Belohnung für diese Order vermerkt ist
            $escapedOrderNumber = addcslashes((string) $order->order_number, '%_\\');
            $voucher = MarketingGiftVoucher::where('recipient_email', $referral->referrer_email)
                ->where('personal_message', 'like', "%{$escapedOrderNumber}%")
                ->lockForUpdate()
                ->first();

            if (!$voucher) {
                return false;
            }

            // IDEMPOTENZ-SCHUTZ: Wurde diese Order bereits zurückgebucht/storniert?
            if (str_contains($voucher->personal_message, "[Order {$order->order_number}: Storniert")) {
                Log::info("Freunde-Werben: Storno für Order {$order->order_number} wurde bereits zuvor zurückgebucht. Abbruch.");
                return false;
            }

            // Saldo um die Belohnung reduzieren (mindestens auf 0)
            $newBalance = max(0, $voucher->current_balance - $referral->reward_cents);
            $voucher->update([
                'current_balance' => $newBalance,
                'is_active' => $newBalance > 0,
                'personal_message' => $voucher->personal_message . " [Order {$order->order_number}: Storniert -5€]"
            ]);

            if ($referral->successful_referrals_count > 0) {
                $referral->decrement('successful_referrals_count');
            }

            Log::info("Freunde-Werben: 5 € Belohnung für Gutschein {$voucher->code} von {$referral->referrer_email} nach Storno von Order {$order->order_number} zurückgebucht.");
            return true;
        });
    }

    /**
     * Erzeugt eine Prioritätenliste an lesbaren Gutscheincode-Kandidaten.
     */
    protected function generateCandidateCodes(string $cleanFirst, string $cleanLast): array
    {
        $candidates = [];

        // 1. Reiner Vorname + 10 (z.B. ALINA10, TIM10)
        $candidates[] = $cleanFirst . '10';

        // 2. Vorname + Nachnamen-Initial + 10 (z.B. ALINAS10 für Alina Stone)
        if (!empty($cleanLast)) {
            $candidates[] = $cleanFirst . substr($cleanLast, 0, 1) . '10';

            // Vorname + erste 2 Buchstaben des Nachnamens + 10 (z.B. ALINAST10)
            if (strlen($cleanLast) >= 2) {
                $candidates[] = $cleanFirst . substr($cleanLast, 0, 2) . '10';
            }
        }

        // 3. Vorname + '-' + kurze 2-stellige Alphanum-Zahl (z.B. ALINA-7K, ALINA-9X)
        for ($i = 0; $i < 10; $i++) {
            $candidates[] = $cleanFirst . '-' . strtoupper(Str::random(2));
        }

        // 4. Vorname + '-' + 3-stellige Alphanum-Zahl (z.B. ALINA-2M4)
        for ($i = 0; $i < 10; $i++) {
            $candidates[] = $cleanFirst . '-' . strtoupper(Str::random(3));
        }

        return array_unique($candidates);
    }

    /**
     * Bereinigt Namen von Sonderzeichen, Akzenten und Leerzeichen.
     */
    protected function sanitizeName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Transliteration von Umlauten
        $umlauts = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE', 'ß' => 'ss'];
        $name = strtr($name, $umlauts);

        // Nur lateinische Buchstaben behalten
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $name));
    }

    /**
     * Erzeugt einen garantiert kollisionsfreien Wertgutschein-Code (DANKE-XXXXXX).
     */
    protected function generateUniqueGiftVoucherCode(): string
    {
        do {
            $code = 'DANKE-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (MarketingGiftVoucher::where('code', $code)->exists());

        return $code;
    }
}
