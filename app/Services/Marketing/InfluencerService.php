<?php

namespace App\Services\Marketing;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingInfluencerCommission;
use App\Models\Marketing\MarketingInfluencerPayout;
use App\Models\Marketing\MarketingTrackingLink;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemLog;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class InfluencerService
{
    /**
     * Erzeugt mit 1 Klick einen neuen Einladungs-Slot inklusive Einladungslink (/partner/einladung/{token})
     * und 6-stelligem Sicherheits-Verifizierungscode für die Schritt-für-Schritt Selbstregistrierung.
     */
    public function createInvitationSlot(array $options = []): MarketingInfluencer
    {
        $rawToken = Str::random(48);
        $rawCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $rawCommissionType = $options['commission_type'] ?? 'percent';
        $commissionType = in_array($rawCommissionType, ['percent', 'fixed_cents'], true) ? $rawCommissionType : 'percent';
        $commissionValue = max(0, (int) ($options['commission_value'] ?? 15));
        $followerDiscount = max(0, min(50, (int) ($options['follower_discount_percent'] ?? 10)));
        $cookieDays = max(1, min(90, (int) ($options['cookie_days'] ?? 30)));
        $label = !empty($options['invitation_label'])
            ? trim((string) $options['invitation_label'])
            : ('Creator-Einladung #' . strtoupper(Str::random(4)));

        return MarketingInfluencer::create([
            'employee_id' => null,
            'invitation_label' => $label,
            'handle' => null,
            'slug' => null,
            'primary_channel' => strtolower(trim((string) ($options['primary_channel'] ?? 'instagram'))),
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
            'follower_discount_percent' => $followerDiscount,
            'cookie_days' => $cookieDays,
            'invitation_token_hash' => hash('sha256', $rawToken),
            'invitation_token_encrypted' => $rawToken,
            'invitation_code_hash' => hash('sha256', $rawCode),
            'invitation_code_encrypted' => $rawCode,
            'invitation_expires_at' => now()->addDays(14),
            'is_active' => true,
            'is_archived' => false,
            'internal_notes' => $options['internal_notes'] ?? null,
        ]);
    }

    /**
     * Normalisiert den 6-stelligen Verifizierungscode (entfernt Leerzeichen, Bindestriche und optionales SF-Präfix).
     */
    public function normalizeVerificationCode(string $inputCode): string
    {
        $clean = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', trim($inputCode)));
        if (str_starts_with($clean, 'SF') && strlen($clean) > 6) {
            $clean = substr($clean, 2);
        }

        return $clean;
    }

    /**
     * Prüft Timing-safe, ob Einladungs-Token und 6-stelliger Verifizierungscode gültig sind.
     */
    public function verifyInvitationCode(string $rawToken, string $inputCode): ?MarketingInfluencer
    {
        $cleanToken = trim($rawToken);
        $cleanCode = $this->normalizeVerificationCode($inputCode);

        if (strlen($cleanToken) < 20 || strlen($cleanCode) < 4) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $influencer = MarketingInfluencer::with('employee.profile')
            ->where('invitation_token_hash', $tokenHash)
            ->first();

        if (!$influencer || !$influencer->is_active || $influencer->invitation_accepted_at !== null) {
            return null;
        }

        if ($influencer->invitation_expires_at && $influencer->invitation_expires_at->isPast()) {
            return null;
        }

        if (empty($influencer->invitation_code_hash)) {
            return null;
        }

        $inputHash = hash('sha256', $cleanCode);
        if (!hash_equals((string) $influencer->invitation_code_hash, $inputHash)) {
            return null;
        }

        return $influencer;
    }

    /**
     * Schließt die Schritt-für-Schritt Selbstregistrierung eines Influencers ab:
     * - Verifiziert Token & 6-stelligen Sicherheitscode
     * - Erstellt den Employee-Account (role = 'influencer') + EmployeeProfile (Anschrift & Steuernummer)
     * - Speichert Kanal, @Handle, Wunsch-Slug, Steuerstatus, Auszahlungsmethode & Selbstständigkeits-Bestätigung
     * - Generiert vollautomatisch den Kurz-Tracking-Link (/t/{slug}) und den Follower-Rabattcode ({SLUG}10)
     */
    public function completeSelfRegistration(string $rawToken, string $inputCode, array $data): ?MarketingInfluencer
    {
        $cleanToken = trim($rawToken);
        $cleanCode = $this->normalizeVerificationCode($inputCode);

        if (strlen($cleanToken) < 20 || strlen($cleanCode) < 4) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $codeHash = hash('sha256', $cleanCode);

        return DB::transaction(function () use ($tokenHash, $codeHash, $data) {
            $influencer = MarketingInfluencer::with('employee.profile')
                ->where('invitation_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$influencer || !$influencer->is_active || $influencer->invitation_accepted_at !== null) {
                return null;
            }

            if ($influencer->invitation_expires_at && $influencer->invitation_expires_at->isPast()) {
                return null;
            }

            if (!empty($influencer->invitation_code_hash) && !hash_equals((string) $influencer->invitation_code_hash, $codeHash)) {
                return null;
            }

            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');

            $rawHandle = trim((string) ($data['handle'] ?? ''));
            $handle = str_starts_with($rawHandle, '@') ? $rawHandle : '@' . ltrim($rawHandle, '@');

            $baseSlug = Str::slug((string) ($data['slug'] ?? ltrim($handle, '@')));
            if ($baseSlug === '') {
                $baseSlug = Str::slug($firstName ?: 'creator');
            }
            $cleanSlug = $baseSlug;
            if (MarketingInfluencer::where('slug', $cleanSlug)->where('id', '!=', $influencer->id)->exists()) {
                $cleanSlug = $baseSlug . '-' . strtolower(Str::random(3));
            }

            $channel = strtolower(trim((string) ($data['primary_channel'] ?? $influencer->primary_channel ?: 'instagram')));
            $profileUrl = !empty($data['profile_url']) ? trim((string) $data['profile_url']) : null;

            $employee = $influencer->employee;
            if ($employee) {
                $employee->update([
                    'first_name' => $firstName ?: $employee->first_name,
                    'last_name' => $lastName ?: $employee->last_name,
                    'email' => $email ?: $employee->email,
                    'role' => 'influencer',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
            } else {
                $employee = Employee::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'role' => 'influencer',
                    'password' => Hash::make($password),
                    'email_verified_at' => now(),
                ]);
            }

            $employee->load('profile');
            if ($employee->profile) {
                $employee->profile->update([
                    'street' => trim((string) ($data['street'] ?? '')),
                    'house_number' => trim((string) ($data['house_number'] ?? '')),
                    'postal' => trim((string) ($data['postal'] ?? '')),
                    'city' => trim((string) ($data['city'] ?? '')),
                    'country' => strtoupper(trim((string) ($data['country'] ?? 'DE'))),
                    'tax_number' => trim((string) ($data['tax_number'] ?? '')),
                    'email_verified_at' => now(),
                ]);
            }

            $taxStatus = in_array($data['tax_status'] ?? 'small_business', ['small_business', 'standard_tax'], true)
                ? $data['tax_status']
                : 'small_business';

            $payoutMethod = in_array($data['payout_method'] ?? 'bank_transfer', ['bank_transfer', 'paypal', 'shop_voucher'], true)
                ? $data['payout_method']
                : 'bank_transfer';

            $influencer->update([
                'employee_id' => $employee->id,
                'handle' => $handle,
                'slug' => $cleanSlug,
                'primary_channel' => $channel,
                'profile_url' => $profileUrl,
                'tax_status' => $taxStatus,
                'tax_number' => trim((string) ($data['tax_number'] ?? '')),
                'self_employed_confirmed' => (bool) ($data['self_employed_confirmed'] ?? true),
                'self_employed_confirmed_at' => now(),
                'payout_method' => $payoutMethod,
                'account_holder' => !empty($data['account_holder']) ? trim((string) $data['account_holder']) : ($firstName . ' ' . $lastName),
                'iban' => !empty($data['iban']) ? strtoupper(preg_replace('/\s+/', '', (string) $data['iban'])) : null,
                'bic' => !empty($data['bic']) ? strtoupper(preg_replace('/\s+/', '', (string) $data['bic'])) : null,
                'paypal_email' => !empty($data['paypal_email']) ? strtolower(trim((string) $data['paypal_email'])) : null,
                'invitation_token_hash' => null,
                'invitation_token_encrypted' => null,
                'invitation_code_hash' => null,
                'invitation_code_encrypted' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
            ]);

            $this->ensureTrackingLinkAndVoucher($influencer, $data['custom_voucher_code'] ?? null);

            if (class_exists(SystemLog::class)) {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'influencer:onboarding_completed',
                    'title' => 'Influencer Selbst-Registrierung abgeschlossen',
                    'message' => "Creator {$influencer->handle} ({$employee->email}) hat sich über den Einladungslink registriert.",
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }

            return $influencer->fresh(['employee.profile', 'defaultVoucher', 'defaultTrackingLink', 'trackingLinks']);
        });
    }

    /**
     * Stellt sicher, dass der Influencer seinen persönlichen Kurz-Tracking-Link (/t/{slug})
     * und seinen Follower-Rabattcode besitzt.
     */
    public function ensureTrackingLinkAndVoucher(MarketingInfluencer $influencer, ?string $customVoucherCode = null): void
    {
        $cleanSlug = $influencer->slug ?: ('creator-' . strtolower(Str::random(4)));
        $handle = $influencer->handle ?: ('@' . $cleanSlug);
        $channel = $influencer->primary_channel ?: 'instagram';
        $followerDiscount = (int) $influencer->follower_discount_percent;
        $isActive = (bool) $influencer->is_active && !$influencer->is_archived;

        if (empty($influencer->default_tracking_link_id) && Schema::hasTable('marketing_tracking_links')) {
            $linkSlug = $cleanSlug;
            if (MarketingTrackingLink::where('slug', $linkSlug)->exists()) {
                $linkSlug = $cleanSlug . '-' . strtolower(Str::random(3));
            }

            $trackingLink = MarketingTrackingLink::create([
                'influencer_id' => $influencer->id,
                'name' => 'Creator Bio-Link ' . $handle,
                'slug' => $linkSlug,
                'target_url' => '/shop',
                'channel' => 'referral',
                'utm_source' => 'influencer',
                'utm_medium' => $channel,
                'utm_campaign' => $cleanSlug,
                'is_active' => $isActive,
            ]);

            $influencer->update(['default_tracking_link_id' => $trackingLink->id]);
        } elseif ($influencer->defaultTrackingLink) {
            $influencer->defaultTrackingLink->update([
                'is_active' => $isActive,
            ]);
        }

        if ($followerDiscount > 0 && empty($influencer->default_voucher_id) && Schema::hasTable('marketing_vouchers')) {
            $customCode = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($customVoucherCode ?? '')));
            $basePrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $cleanSlug));
            if ($basePrefix === '') {
                $basePrefix = 'CREATOR';
            }

            $candidateCode = $customCode !== '' ? $customCode : ($basePrefix . $followerDiscount);
            if (MarketingVoucher::where('code', $candidateCode)->exists()) {
                $candidateCode = $basePrefix . $followerDiscount . '-' . strtoupper(Str::random(2));
            }

            $voucher = MarketingVoucher::create([
                'influencer_id' => $influencer->id,
                'title' => 'Creator-Rabatt von ' . $handle,
                'code' => $candidateCode,
                'type' => 'percent',
                'value' => $followerDiscount,
                'used_count' => 0,
                'usage_limit' => null,
                'min_order_value' => 0,
                'is_active' => $isActive,
                'mode' => 'manual',
                'valid_from' => now()->subDay(),
                'valid_until' => now()->addYears(5),
            ]);

            $influencer->update(['default_voucher_id' => $voucher->id]);
        } elseif ($influencer->defaultVoucher && $followerDiscount > 0) {
            $updateData = [
                'value' => $followerDiscount,
                'is_active' => $isActive,
            ];
            $cleanCustom = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) ($customVoucherCode ?? '')));
            if ($cleanCustom !== '' && $cleanCustom !== $influencer->defaultVoucher->code) {
                if (!MarketingVoucher::where('code', $cleanCustom)->where('id', '!=', $influencer->defaultVoucher->id)->exists()) {
                    $updateData['code'] = $cleanCustom;
                }
            }
            $influencer->defaultVoucher->update($updateData);
        }
    }

    /**
     * Legt einen Influencer inklusive Employee-Account, Einladungs-Token,
     * Kurz-Tracking-Link (/t/{slug}) und Follower-Rabattcode vollautomatisch an oder aktualisiert ihn.
     */
    public function createOrUpdateInfluencer(array $data, ?string $influencerId = null): MarketingInfluencer
    {
        return DB::transaction(function () use ($data, $influencerId) {
            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $rawHandle = trim((string) ($data['handle'] ?? ''));
            $handle = $rawHandle !== ''
                ? (str_starts_with($rawHandle, '@') ? $rawHandle : '@' . ltrim($rawHandle, '@'))
                : null;

            $cleanSlug = $handle ? Str::slug((string) ($data['slug'] ?? ltrim($handle, '@'))) : Str::slug((string) ($data['slug'] ?? ''));
            if ($cleanSlug === '' && $firstName !== '') {
                $cleanSlug = Str::slug($firstName) . '-' . strtolower(Str::random(4));
            }

            $channel = strtolower(trim((string) ($data['primary_channel'] ?? 'instagram')));
            $rawCommissionType = $data['commission_type'] ?? 'percent';
            $commissionType = in_array($rawCommissionType, ['percent', 'fixed_cents'], true)
                ? $rawCommissionType
                : 'percent';
            $commissionValue = max(0, (int) ($data['commission_value'] ?? 15));
            $followerDiscount = max(0, min(50, (int) ($data['follower_discount_percent'] ?? 10)));
            $cookieDays = max(1, min(90, (int) ($data['cookie_days'] ?? 30)));
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($influencerId) {
                $influencer = MarketingInfluencer::with(['employee', 'defaultVoucher', 'defaultTrackingLink'])->findOrFail($influencerId);
                $employee = $influencer->employee;

                if ($employee && ($firstName !== '' || $email !== '')) {
                    $employee->update([
                        'first_name' => $firstName ?: $employee->first_name,
                        'last_name' => $lastName ?: $employee->last_name,
                        'email' => $email ?: $employee->email,
                        'role' => 'influencer',
                    ]);
                }

                $influencer->update([
                    'invitation_label' => $data['invitation_label'] ?? $influencer->invitation_label,
                    'handle' => $handle ?: $influencer->handle,
                    'slug' => $cleanSlug ?: $influencer->slug,
                    'primary_channel' => $channel,
                    'profile_url' => array_key_exists('profile_url', $data) ? (!empty($data['profile_url']) ? trim((string) $data['profile_url']) : null) : $influencer->profile_url,
                    'commission_type' => $commissionType,
                    'commission_value' => $commissionValue,
                    'follower_discount_percent' => $followerDiscount,
                    'cookie_days' => $cookieDays,
                    'is_active' => $isActive,
                    'internal_notes' => $data['internal_notes'] ?? $influencer->internal_notes,
                ]);
            } else {
                $rawToken = Str::random(48);
                $rawCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

                $employee = Employee::create([
                    'first_name' => $firstName ?: 'Creator',
                    'last_name' => $lastName ?: 'Partner',
                    'email' => $email,
                    'role' => 'influencer',
                    'password' => Hash::make(Str::random(40)),
                ]);

                $influencer = MarketingInfluencer::create([
                    'employee_id' => $employee->id,
                    'invitation_label' => $data['invitation_label'] ?? ($handle ?: $email),
                    'handle' => $handle,
                    'slug' => $cleanSlug ?: null,
                    'primary_channel' => $channel,
                    'profile_url' => !empty($data['profile_url']) ? trim((string) $data['profile_url']) : null,
                    'commission_type' => $commissionType,
                    'commission_value' => $commissionValue,
                    'follower_discount_percent' => $followerDiscount,
                    'cookie_days' => $cookieDays,
                    'invitation_token_hash' => hash('sha256', $rawToken),
                    'invitation_token_encrypted' => $rawToken,
                    'invitation_code_hash' => hash('sha256', $rawCode),
                    'invitation_code_encrypted' => $rawCode,
                    'invitation_expires_at' => now()->addDays(14),
                    'is_active' => $isActive,
                    'is_archived' => false,
                    'internal_notes' => $data['internal_notes'] ?? null,
                ]);
            }

            if (!empty($influencer->slug)) {
                $this->ensureTrackingLinkAndVoucher($influencer, $data['custom_voucher_code'] ?? null);
            }

            return $influencer->fresh(['employee.profile', 'defaultVoucher', 'defaultTrackingLink', 'trackingLinks']);
        });
    }

    /**
     * Erzeugt einen neuen, 14 Tage gültigen Einladungs-Token + neuen 6-stelligen Verifizierungscode für das Creator-Onboarding.
     */
    public function regenerateInvitationToken(MarketingInfluencer $influencer): string
    {
        $rawToken = Str::random(48);
        $rawCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $influencer->update([
            'invitation_token_hash' => hash('sha256', $rawToken),
            'invitation_token_encrypted' => $rawToken,
            'invitation_code_hash' => hash('sha256', $rawCode),
            'invitation_code_encrypted' => $rawCode,
            'invitation_expires_at' => now()->addDays(14),
            'invitation_accepted_at' => null,
        ]);

        return route('influencer.invitation', ['token' => $rawToken]);
    }

    /**
     * Prüft den Einladungs-Token (Timing-safe via SHA-256 Hash), setzt das gewählte Passwort
     * und schaltet den Influencer-Account frei.
     */
    public function acceptInvitation(string $rawToken, string $newPassword): ?MarketingInfluencer
    {
        $cleanToken = trim($rawToken);
        if (strlen($cleanToken) < 20) {
            return null;
        }

        $hash = hash('sha256', $cleanToken);

        return DB::transaction(function () use ($hash, $newPassword) {
            $influencer = MarketingInfluencer::with('employee.profile')
                ->where('invitation_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (!$influencer || !$influencer->is_active || !$influencer->employee) {
                return null;
            }

            if ($influencer->invitation_expires_at && $influencer->invitation_expires_at->isPast()) {
                return null;
            }

            $influencer->employee->update([
                'password' => Hash::make($newPassword),
                'email_verified_at' => now(),
                'role' => 'influencer',
            ]);

            if ($influencer->employee->profile) {
                $influencer->employee->profile->update([
                    'email_verified_at' => now(),
                ]);
            }

            $influencer->update([
                'invitation_token_hash' => null,
                'invitation_token_encrypted' => null,
                'invitation_expires_at' => null,
                'invitation_accepted_at' => now(),
            ]);

            if (class_exists(SystemLog::class)) {
                SystemLog::create([
                    'type' => 'system',
                    'action_id' => 'influencer:onboarding_completed',
                    'title' => 'Influencer-Onboarding abgeschlossen',
                    'message' => "Creator {$influencer->handle} ({$influencer->employee->email}) hat die Einladung angenommen.",
                    'status' => 'success',
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }

            return $influencer;
        });
    }

    /**
     * Dual-Attribution Engine:
     * Prüft bei einer bezahlten Bestellung sowohl den eingelösten Gutscheincode als auch
     * das Tracking-Link-/UTM-Cookie und schreibt dem Influencer die Provision gut.
     */
    public function processOrderCommission(OrderOrder $order): ?MarketingInfluencerCommission
    {
        if (!Schema::hasTable('marketing_influencer_commissions') || !Schema::hasTable('marketing_influencers')) {
            return null;
        }

        if ((int) ($order->total_price ?? 0) <= 0) {
            return null;
        }

        // Idempotenz-Schutz: Jede Bestellung kann maximal 1x verprovisioniert werden
        $existing = MarketingInfluencerCommission::where('order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($order) {
            if (MarketingInfluencerCommission::where('order_id', $order->id)->lockForUpdate()->exists()) {
                return MarketingInfluencerCommission::where('order_id', $order->id)->first();
            }

            $couponInfluencer = null;
            $matchedVoucherCode = null;
            $linkInfluencer = null;
            $matchedTrackingLinkId = null;

            // WEG A: Zuordnung über eingelösten Gutscheincode (z.B. LARA10)
            if (!empty($order->coupon_code) && Schema::hasTable('marketing_vouchers')) {
                $cleanCoupon = strtoupper(trim((string) $order->coupon_code));
                $voucher = MarketingVoucher::where('code', $cleanCoupon)
                    ->whereNotNull('influencer_id')
                    ->first();

                if ($voucher && $voucher->influencer && $voucher->influencer->is_active) {
                    $couponInfluencer = $voucher->influencer;
                    $matchedVoucherCode = $voucher->code;
                }
            }

            // WEG B: Zuordnung über Tracking-Link Cookie / Session / UTM-Kampagne
            $cookieInfluencerId = session('seelenfunke_influencer_id') ?: request()?->cookie('seelenfunke_influencer_id');
            $cookieTrackingLinkId = session('seelenfunke_tracking_link_id') ?: request()?->cookie('seelenfunke_tracking_link_id');

            if ($cookieInfluencerId) {
                $candidate = MarketingInfluencer::where('id', $cookieInfluencerId)->where('is_active', true)->first();
                if ($candidate) {
                    $linkInfluencer = $candidate;
                    $matchedTrackingLinkId = $cookieTrackingLinkId ?: $candidate->default_tracking_link_id;
                }
            }

            if (!$linkInfluencer && (!empty($order->utm_campaign_last) || !empty($order->utm_campaign_first))) {
                $campaignSlug = trim((string) ($order->utm_campaign_last ?: $order->utm_campaign_first));
                if (str_starts_with($campaignSlug, 'aff:')) {
                    $campaignSlug = substr($campaignSlug, 4);
                }

                if ($campaignSlug !== '') {
                    $trk = MarketingTrackingLink::where('slug', $campaignSlug)
                        ->whereNotNull('influencer_id')
                        ->first();

                    if ($trk && $trk->influencer && $trk->influencer->is_active) {
                        $linkInfluencer = $trk->influencer;
                        $matchedTrackingLinkId = $trk->id;
                    } else {
                        $bySlug = MarketingInfluencer::where('slug', $campaignSlug)->where('is_active', true)->first();
                        if ($bySlug) {
                            $linkInfluencer = $bySlug;
                            $matchedTrackingLinkId = $bySlug->default_tracking_link_id;
                        }
                    }
                }
            }

            $finalInfluencer = $couponInfluencer ?: $linkInfluencer;
            if (!$finalInfluencer || !$finalInfluencer->is_active) {
                return null;
            }

            // Eigenkauf-Schutz: Influencer darf keine Provision für eigene Bestellungen erhalten
            $influencerEmail = strtolower(trim((string) ($finalInfluencer->employee?->email ?? '')));
            $buyerEmail = strtolower(trim((string) ($order->email ?? '')));
            if ($influencerEmail !== '' && $buyerEmail !== '' && $influencerEmail === $buyerEmail) {
                Log::warning("Influencer-Provision abgelehnt (Eigenkauf): {$buyerEmail} für Order {$order->order_number}.");
                return null;
            }

            $attributionSource = 'link';
            if ($couponInfluencer && $linkInfluencer && $couponInfluencer->id === $linkInfluencer->id) {
                $attributionSource = 'both';
            } elseif ($couponInfluencer) {
                $attributionSource = 'coupon';
            }

            // Netto-Warenwert (ohne Versand- und Expresskosten, nach Abzug von Rabatten) berechnen
            $grossMerchandiseCents = max(
                0,
                (int) $order->subtotal_price - (int) $order->discount_amount - (int) $order->volume_discount
            );

            if ((bool) $order->is_small_business || (int) $order->tax_amount === 0) {
                $netMerchandiseCents = $grossMerchandiseCents;
            } else {
                $defaultTaxRate = (float) shop_setting('default_tax_rate', 19.0);
                $netMerchandiseCents = (int) round($grossMerchandiseCents / (1 + ($defaultTaxRate / 100)));
            }

            if ($netMerchandiseCents <= 0) {
                return null;
            }

            if ($finalInfluencer->commission_type === 'fixed_cents') {
                $commissionCents = min($netMerchandiseCents, (int) $finalInfluencer->commission_value);
            } else {
                $commissionCents = (int) round($netMerchandiseCents * ((int) $finalInfluencer->commission_value / 100));
            }

            if ($commissionCents <= 0) {
                return null;
            }

            $initialStatus = in_array($order->status, ['completed', 'shipped'], true) ? 'approved' : 'pending';

            return MarketingInfluencerCommission::create([
                'influencer_id' => $finalInfluencer->id,
                'order_id' => $order->id,
                'tracking_link_id' => $matchedTrackingLinkId,
                'voucher_code' => $matchedVoucherCode,
                'attribution_source' => $attributionSource,
                'order_number' => (string) $order->order_number,
                'order_net_amount_cents' => $netMerchandiseCents,
                'commission_rate_snapshot' => (int) $finalInfluencer->commission_value,
                'commission_amount_cents' => $commissionCents,
                'status' => $initialStatus,
                'approved_at' => $initialStatus === 'approved' ? now() : null,
            ]);
        });
    }

    /**
     * Storniert eine vorgemerkte oder freigegebene Provision automatisch bei Bestell-Storno oder Widerruf.
     */
    public function reverseOrderCommission(OrderOrder $order, string $reason = 'Stornierung/Widerruf'): bool
    {
        if (!Schema::hasTable('marketing_influencer_commissions')) {
            return false;
        }

        $commission = MarketingInfluencerCommission::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereNull('payout_id')
            ->first();

        if (!$commission) {
            return false;
        }

        $commission->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => mb_substr($reason, 0, 250),
        ]);

        return true;
    }

    /**
     * Gibt alle offenen (pending) Provisionen eines Influencers frei, deren Bestellung bezahlt und nicht storniert ist.
     */
    public function approveEligibleCommissions(MarketingInfluencer $influencer): int
    {
        $commissions = MarketingInfluencerCommission::with('order')
            ->where('influencer_id', $influencer->id)
            ->where('status', 'pending')
            ->whereNull('payout_id')
            ->get();

        $count = 0;
        foreach ($commissions as $comm) {
            if ($comm->order && $comm->order->payment_status === 'paid' && !in_array($comm->order->status, ['cancelled', 'refunded'], true)) {
                $comm->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Erstellt eine Auszahlungsanforderung inkl. rechtssicherer Gutschriftsnummer (PROV-YYYY-XXXX).
     */
    public function requestPayout(MarketingInfluencer $influencer, ?string $chosenMethod = null): MarketingInfluencerPayout
    {
        $influencer->loadMissing('employee.profile');

        if (!$influencer->isReadyForPayout()) {
            throw new InvalidArgumentException('Bitte vervollständige zuerst deine Adresse, Steuernummer, Selbstständigkeits-Bestätigung und Auszahlungsdaten.');
        }

        return DB::transaction(function () use ($influencer, $chosenMethod) {
            $commissions = MarketingInfluencerCommission::where('influencer_id', $influencer->id)
                ->where('status', 'approved')
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            $netCents = (int) $commissions->sum('commission_amount_cents');
            if ($netCents <= 0) {
                throw new InvalidArgumentException('Aktuell sind keine freigegebenen Provisionen zur Auszahlung verfügbar.');
            }

            $method = in_array($chosenMethod, ['bank_transfer', 'paypal', 'shop_voucher'], true)
                ? $chosenMethod
                : $influencer->payout_method;

            $bonusCents = 0;
            $taxRate = 0;
            $taxCents = 0;

            if ($method === 'shop_voucher') {
                // +20 % Sachprämien-Boost bei Auszahlung als Seelenfunke Shop-Gutschein
                $bonusCents = (int) round($netCents * 0.20);
                $grossCents = $netCents + $bonusCents;
            } else {
                $taxRate = $influencer->tax_status === 'standard_tax' ? 19 : 0;
                $taxCents = $taxRate > 0 ? (int) round($netCents * ($taxRate / 100)) : 0;
                $grossCents = $netCents + $taxCents;
            }

            $creditNoteNumber = $this->generateNextCreditNoteNumber();
            $profile = $influencer->employee?->profile;

            $snapshot = [
                'full_name' => trim(($influencer->employee?->first_name ?? '') . ' ' . ($influencer->employee?->last_name ?? '')),
                'email' => $influencer->employee?->email,
                'handle' => $influencer->handle,
                'company_name' => $profile?->company_name,
                'street' => $profile?->street,
                'house_number' => $profile?->house_number,
                'postal' => $profile?->postal,
                'city' => $profile?->city,
                'country' => $profile?->country ?: 'DE',
                'tax_status' => $influencer->tax_status,
                'tax_number' => $influencer->tax_number,
                'payout_method' => $method,
                'account_holder' => $influencer->account_holder,
                'masked_iban' => $influencer->masked_iban,
                'paypal_email' => $influencer->paypal_email,
            ];

            $payout = MarketingInfluencerPayout::create([
                'influencer_id' => $influencer->id,
                'credit_note_number' => $creditNoteNumber,
                'payout_method' => $method,
                'net_amount_cents' => $netCents,
                'tax_rate' => $taxRate,
                'tax_amount_cents' => $taxCents,
                'bonus_amount_cents' => $bonusCents,
                'gross_amount_cents' => $grossCents,
                'status' => 'requested',
                'requested_at' => now(),
                'recipient_snapshot' => $snapshot,
            ]);

            MarketingInfluencerCommission::whereIn('id', $commissions->pluck('id'))
                ->update(['payout_id' => $payout->id]);

            $this->generateAndStoreCreditNotePdf($payout);

            return $payout->fresh(['commissions', 'influencer.employee.profile']);
        });
    }

    /**
     * Verbucht eine Auszahlung endgültig als bezahlt:
     * - Setzt alle enthaltenen Provisionen auf 'paid'
     * - Generiert bei 'shop_voucher' automatisch den +20% Wertgutschein
     * - Bucht bei 'bank_transfer' / 'paypal' automatisch die Ausgabe inkl. PDF-Beleg in die Variablen Kosten (AccountingSpecialIssue)
     */
    public function markPayoutAsPaid(MarketingInfluencerPayout $payout, ?string $adminId = null): MarketingInfluencerPayout
    {
        return DB::transaction(function () use ($payout, $adminId) {
            $payout->loadMissing(['influencer.employee.profile', 'commissions']);

            if ($payout->status === 'paid') {
                return $payout;
            }

            $effectiveAdminId = $adminId ?: (auth('admin')->id() ?: Admin::value('id'));
            $influencer = $payout->influencer;

            // 1. Falls Auszahlung als Shop-Wertgutschein (+20% Bonus): Gutschein erzeugen
            if ($payout->payout_method === 'shop_voucher' && empty($payout->gift_voucher_id) && Schema::hasTable('marketing_gift_vouchers')) {
                $voucherCode = 'CREATOR-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
                $giftVoucher = MarketingGiftVoucher::create([
                    'code' => $voucherCode,
                    'initial_value' => $payout->gross_amount_cents,
                    'current_balance' => $payout->gross_amount_cents,
                    'recipient_name' => trim(($influencer->employee?->first_name ?? '') . ' ' . ($influencer->employee?->last_name ?? '')) ?: $influencer->handle,
                    'recipient_email' => $influencer->employee?->email,
                    'personal_message' => "Deine Creator-Provision inkl. +20 % Seelenfunke Bonus ({$payout->credit_note_number})",
                    'delivery_method' => 'digital',
                    'is_active' => true,
                    'valid_until' => now()->addYears(3),
                ]);

                $payout->gift_voucher_id = $giftVoucher->id;
            }

            $payout->status = 'paid';
            $payout->paid_at = now();
            $payout->paid_by_admin_id = $effectiveAdminId;
            $payout->save();

            // 2. Einzel-Provisionen auf 'paid' setzen
            MarketingInfluencerCommission::where('payout_id', $payout->id)
                ->update(['status' => 'paid']);

            // 3. PDF-Gutschrift aktualisieren (mit Bezahlt-Datum / ggf. Gutscheincode)
            $pdfPath = $this->generateAndStoreCreditNotePdf($payout->fresh(['influencer.employee.profile', 'commissions', 'giftVoucher']));

            // 4. Automatischer Eintrag in die Buchhaltung (Variable Kosten -> "Marketing & Affiliate")
            if (in_array($payout->payout_method, ['bank_transfer', 'paypal'], true) && empty($payout->accounting_special_issue_id) && Schema::hasTable('accounting_special_issues') && $effectiveAdminId) {
                $categoryName = 'Marketing & Affiliate';

                if (Schema::hasTable('accounting_categories')) {
                    $cat = AccountingCategory::withTrashed()
                        ->where('admin_id', $effectiveAdminId)
                        ->where('name', $categoryName)
                        ->first();

                    if ($cat) {
                        if ($cat->trashed()) {
                            $cat->restore();
                        }
                        $cat->increment('usage_count');
                    } else {
                        AccountingCategory::create([
                            'admin_id' => $effectiveAdminId,
                            'name' => $categoryName,
                            'usage_count' => 1,
                        ]);
                    }
                }

                $specialIssue = AccountingSpecialIssue::create([
                    'admin_id' => $effectiveAdminId,
                    'title' => "Influencer-Provision {$influencer->handle} ({$payout->credit_note_number})",
                    'category' => $categoryName,
                    'amount' => -round($payout->gross_amount_cents / 100, 2),
                    'execution_date' => now()->format('Y-m-d'),
                    'location' => $payout->payout_method === 'paypal' ? 'PayPal' : 'SEPA-Banküberweisung',
                    'is_business' => true,
                    'tax_rate' => (int) $payout->tax_rate,
                    'invoice_number' => $payout->credit_note_number,
                    'file_paths' => $pdfPath ? [$pdfPath] : [],
                ]);

                $payout->update(['accounting_special_issue_id' => $specialIssue->id]);
            }

            return $payout->fresh(['commissions', 'giftVoucher', 'accountingSpecialIssue']);
        });
    }

    /**
     * Erzeugt das PDF der Provisions-Gutschrift (§ 14 Abs. 2 S. 2 UStG) und legt es revisionssicher
     * im Buchhaltungs-Verzeichnis `buchhaltung/receipts/` ab.
     */
    public function generateAndStoreCreditNotePdf(MarketingInfluencerPayout $payout): ?string
    {
        try {
            $payout->loadMissing(['influencer.employee.profile', 'commissions', 'giftVoucher']);

            $pdf = Pdf::loadView('global.pdf.influencer_credit_note', [
                'payout' => $payout,
                'influencer' => $payout->influencer,
                'commissions' => $payout->commissions,
            ])->setPaper('a4', 'portrait');

            $safeNumber = preg_replace('/[^A-Za-z0-9_\-]/', '_', $payout->credit_note_number);
            $relativePath = "buchhaltung/receipts/gutschrift_{$safeNumber}.pdf";

            Storage::disk('local')->put($relativePath, $pdf->output());

            if ($payout->pdf_path !== $relativePath) {
                $payout->update(['pdf_path' => $relativePath]);
            }

            return $relativePath;
        } catch (\Throwable $e) {
            Log::error("Fehler beim Erstellen der Influencer-Gutschrift {$payout->credit_note_number}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fortlaufende Gutschriftsnummer generieren (z.B. PROV-2026-0001).
     */
    public function generateNextCreditNoteNumber(): string
    {
        $year = date('Y');
        $prefix = "PROV-{$year}-";

        $lastNumber = MarketingInfluencerPayout::where('credit_note_number', 'like', $prefix . '%')
            ->orderByDesc('credit_note_number')
            ->value('credit_note_number');

        $nextSeq = 1;
        if ($lastNumber && preg_match('/^PROV-\d{4}-(\d+)$/', $lastNumber, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return $prefix . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generiert ein sauberes SVG für QR-Codes (Tracking-Links & EPC-GiroCode für SEPA-Überweisungen).
     */
    public function generateQrCodeSvg(string $payload, int $size = 180): string
    {
        $safeSize = max(100, min(400, $size));
        $renderer = new ImageRenderer(
            new RendererStyle($safeSize, 1),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        return $writer->writeString($payload);
    }
}
