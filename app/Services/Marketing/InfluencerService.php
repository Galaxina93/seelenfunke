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
     * Gesetzlicher & buchhalterischer Mindestauszahlungsbetrag für Creator-Selbstabrufe: 20,00 € Netto (2000 Cents).
     */
    public const MIN_PAYOUT_CENTS = 2000;

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
        $commissionValue = $commissionType === 'fixed_cents'
            ? max(0, min(50000, (int) ($options['commission_value'] ?? 1500)))
            : max(1, min(80, (int) ($options['commission_value'] ?? 15)));
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

        if (strlen($cleanToken) < 20 || strlen($cleanCode) !== 6) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $throttleKey = 'invite_pin_inf_' . $tokenHash;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:invitation_pin_lockout',
                'Creator-Einladungs-PIN wegen Brute-Force gesperrt',
                'Zu viele Fehlversuche für Creator-Einladungs-Token von IP ' . request()->ip(),
                'error',
                ['token_hash_prefix' => substr($tokenHash, 0, 12)]
            );
            return null;
        }

        $influencer = MarketingInfluencer::with('employee.profile')
            ->where('invitation_token_hash', $tokenHash)
            ->first();

        if (!$influencer || !$influencer->is_active || $influencer->is_archived || $influencer->invitation_accepted_at !== null) {
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
            \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 900);
            return null;
        }

        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

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

        if (strlen($cleanToken) < 20 || strlen($cleanCode) !== 6) {
            return null;
        }

        $tokenHash = hash('sha256', $cleanToken);
        $codeHash = hash('sha256', $cleanCode);
        $throttleKey = 'invite_pin_inf_' . $tokenHash;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return null;
        }

        return DB::transaction(function () use ($tokenHash, $codeHash, $throttleKey, $data) {
            $influencer = MarketingInfluencer::with('employee.profile')
                ->where('invitation_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$influencer || !$influencer->is_active || $influencer->is_archived || $influencer->invitation_accepted_at !== null) {
                return null;
            }

            if ($influencer->invitation_expires_at && $influencer->invitation_expires_at->isPast()) {
                return null;
            }

            if (empty($influencer->invitation_code_hash) || !hash_equals((string) $influencer->invitation_code_hash, $codeHash)) {
                \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 900);
                return null;
            }

            \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

            $firstName = trim((string) ($data['first_name'] ?? ''));
            $lastName = trim((string) ($data['last_name'] ?? ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $password = (string) ($data['password'] ?? '');

            $employee = $influencer->employee;
            if ($email !== '' && \App\Models\System\SystemUser::isEmailTakenAcrossGuards($email, 'employee', $employee?->id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => 'Diese E-Mail-Adresse ist bereits einem bestehenden Konto (Kunde, Mitarbeiter oder Admin) zugeordnet und kann nicht erneut verwendet werden.',
                ]);
            }

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

            $employee->profile()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'street' => trim((string) ($data['street'] ?? '')),
                    'house_number' => trim((string) ($data['house_number'] ?? '')),
                    'postal' => trim((string) ($data['postal'] ?? '')),
                    'city' => trim((string) ($data['city'] ?? '')),
                    'country' => strtoupper(trim((string) ($data['country'] ?? 'DE'))),
                    'vat_id' => trim((string) ($data['tax_number'] ?? '')) ?: null,
                    'email_verified_at' => now(),
                ]
            );

            $taxStatus = in_array($data['tax_status'] ?? 'small_business', ['small_business', 'standard_tax', 'reverse_charge'], true)
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

        $this->syncInfluencerActiveState($influencer);
    }

    /**
     * Synchronisiert den Aktiv-Status aller Tracking-Links und Rabattcodes eines Influencers
     * (z.B. beim Pausieren, Reaktivieren oder Archivieren).
     */
    public function syncInfluencerActiveState(MarketingInfluencer $influencer): void
    {
        $effectiveActive = (bool) $influencer->is_active && !(bool) $influencer->is_archived;

        if (Schema::hasTable('marketing_tracking_links')) {
            MarketingTrackingLink::where('influencer_id', $influencer->id)
                ->update(['is_active' => $effectiveActive]);
        }

        if (Schema::hasTable('marketing_vouchers')) {
            MarketingVoucher::where('influencer_id', $influencer->id)
                ->update(['is_active' => $effectiveActive]);
        }
    }

    /**
     * Löscht oder archiviert einen Influencer GoBD-konform (§ 147 AO):
     * - Besitzt der Creator bereits Provisionen, Gutschriften oder eingelöste Codes, wird er NIEMALS
     *   hart gelöscht, sondern revisionssicher archiviert und alle Codes/Links deaktiviert.
     * - Besitzt der Creator keinerlei Buchungen/Einlösungen (z.B. offene Einladung oder ungenutzter Test-Slot),
     *   werden alle verknüpften Links, Codes, Profile und der Account rückstandsfrei entfernt.
     */
    public function deleteOrArchiveInfluencer(MarketingInfluencer $influencer): array
    {
        return DB::transaction(function () use ($influencer) {
            $hasCommissions = Schema::hasTable('marketing_influencer_commissions')
                && $influencer->commissions()->exists();
            $hasPayouts = Schema::hasTable('marketing_influencer_payouts')
                && $influencer->payouts()->exists();
            $hasUsedVouchers = Schema::hasTable('marketing_vouchers')
                && MarketingVoucher::where('influencer_id', $influencer->id)->where('used_count', '>', 0)->exists();

            if ($hasCommissions || $hasPayouts || $hasUsedVouchers) {
                $influencer->update([
                    'is_active' => false,
                    'is_archived' => true,
                    'invitation_token_hash' => null,
                    'invitation_token_encrypted' => null,
                    'invitation_code_hash' => null,
                    'invitation_code_encrypted' => null,
                    'invitation_expires_at' => null,
                ]);

                $this->syncInfluencerActiveState($influencer);

                $label = $influencer->handle ?: ($influencer->invitation_label ?: 'Creator');
                return [
                    'action' => 'archived',
                    'message' => "Creator {$label} besitzt bereits Buchungen oder eingelöste Gutscheine und wurde zur Wahrung der GoBD-Aufbewahrungspflicht archiviert statt gelöscht. Alle Links und Codes wurden sofort deaktiviert.",
                ];
            }

            $wasInvited = $influencer->is_pending_invitation;
            $label = $influencer->handle ?: ($influencer->invitation_label ?: 'Creator');
            $employee = $influencer->employee;

            $influencer->update([
                'default_tracking_link_id' => null,
                'default_voucher_id' => null,
            ]);

            if (Schema::hasTable('marketing_tracking_links')) {
                MarketingTrackingLink::where('influencer_id', $influencer->id)->delete();
            }

            if (Schema::hasTable('marketing_vouchers')) {
                MarketingVoucher::where('influencer_id', $influencer->id)->delete();
            }

            $influencer->delete();

            if ($employee) {
                $employee->profile()?->delete();
                $employee->delete();
            }

            return [
                'action' => 'deleted',
                'message' => $wasInvited
                    ? 'Offene Einladung rückstandsfrei gelöscht.'
                    : "Creator {$label} inklusive ungenutzter Links und Codes rückstandsfrei gelöscht.",
            ];
        });
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
            $commissionValue = $commissionType === 'fixed_cents'
                ? max(0, min(50000, (int) ($data['commission_value'] ?? 1500)))
                : max(1, min(80, (int) ($data['commission_value'] ?? 15)));
            $followerDiscount = max(0, min(50, (int) ($data['follower_discount_percent'] ?? 10)));
            $cookieDays = max(1, min(90, (int) ($data['cookie_days'] ?? 30)));
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($influencerId) {
                $influencer = MarketingInfluencer::with(['employee', 'defaultVoucher', 'defaultTrackingLink'])->findOrFail($influencerId);
                $employee = $influencer->employee;

                if ($email !== '' && \App\Models\System\SystemUser::isEmailTakenAcrossGuards($email, 'employee', $employee?->id)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => 'Diese E-Mail-Adresse ist bereits einem bestehenden Konto (Kunde, Mitarbeiter oder Admin) zugeordnet.',
                    ]);
                }

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
                if ($email !== '' && \App\Models\System\SystemUser::isEmailTakenAcrossGuards($email, 'employee', null)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => 'Diese E-Mail-Adresse ist bereits einem bestehenden Konto (Kunde, Mitarbeiter oder Admin) zugeordnet.',
                    ]);
                }

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
                'invitation_code_hash' => null,
                'invitation_code_encrypted' => null,
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

        if (in_array($order->status, ['cancelled', 'refunded'], true) || $order->payment_status === 'refunded') {
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

                if ($voucher && $voucher->influencer && $voucher->influencer->is_active && !$voucher->influencer->is_archived) {
                    $couponInfluencer = $voucher->influencer;
                    $matchedVoucherCode = $voucher->code;
                }
            }

            // WEG B: Zuordnung über Tracking-Link Cookie / Session / UTM-Kampagne
            // Wichtig: Falls ein Admin im Backend eine Kundenbestellung manuell als bezahlt markiert,
            // darf ein eventuell im Admin-Browser gesetztes Test-Cookie die Kundenbestellung nicht kontaminieren.
            $isAdminBackendAction = \Illuminate\Support\Facades\Auth::guard('admin')->check()
                && session('checkout_order_id') !== $order->id
                && !str_contains((string) request()?->path(), 'checkout');

            $cookieInfluencerId = !$isAdminBackendAction
                ? (session('seelenfunke_influencer_id') ?: request()?->cookie('seelenfunke_influencer_id'))
                : null;
            $cookieTrackingLinkId = !$isAdminBackendAction
                ? (session('seelenfunke_tracking_link_id') ?: request()?->cookie('seelenfunke_tracking_link_id'))
                : null;

            if ($cookieInfluencerId) {
                $candidate = MarketingInfluencer::where('id', $cookieInfluencerId)->where('is_active', true)->where('is_archived', false)->first();
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

                    if ($trk && $trk->influencer && $trk->influencer->is_active && !$trk->influencer->is_archived) {
                        $linkInfluencer = $trk->influencer;
                        $matchedTrackingLinkId = $trk->id;
                    } else {
                        $bySlug = MarketingInfluencer::where('slug', $campaignSlug)->where('is_active', true)->where('is_archived', false)->first();
                        if ($bySlug) {
                            $linkInfluencer = $bySlug;
                            $matchedTrackingLinkId = $bySlug->default_tracking_link_id;
                        }
                    }
                }
            }

            $finalInfluencer = $couponInfluencer ?: $linkInfluencer;
            if (!$finalInfluencer || !$finalInfluencer->is_active || $finalInfluencer->is_archived) {
                return null;
            }

            // Eigenkauf-Schutz: Influencer darf keine Provision für eigene Bestellungen erhalten
            $influencerEmail = strtolower(trim((string) ($finalInfluencer->employee?->email ?? '')));
            $influencerPaypalEmail = strtolower(trim((string) ($finalInfluencer->paypal_email ?? '')));
            $buyerEmails = array_filter(array_unique([
                strtolower(trim((string) ($order->email ?? ''))),
                strtolower(trim((string) ($order->customer?->email ?? ''))),
            ]));

            foreach ($buyerEmails as $buyerEmail) {
                if ($buyerEmail === $influencerEmail || ($influencerPaypalEmail !== '' && $buyerEmail === $influencerPaypalEmail)) {
                    Log::warning("Influencer-Provision abgelehnt (Eigenkauf): {$buyerEmail} für Order {$order->order_number}.");
                    return null;
                }
            }

            $attributionSource = 'link';
            if ($couponInfluencer && $linkInfluencer && $couponInfluencer->id === $linkInfluencer->id) {
                $attributionSource = 'both';
            } elseif ($couponInfluencer) {
                $attributionSource = 'coupon';
            }

            // Netto-Warenwert (ohne Versand- und Expresskosten, nach Abzug von Rabatten) über den zentralen PriceCalcService berechnen
            $grossMerchandiseCents = max(
                0,
                (int) $order->subtotal_price - (int) $order->discount_amount - (int) $order->volume_discount
            );

            $effectiveTaxRate = ((bool) $order->is_small_business || (int) $order->tax_amount === 0)
                ? 0.0
                : (float) shop_setting('default_tax_rate', 19.0);

            $netMerchandiseCents = app(\App\Services\PriceCalcService::class)->getNetFromGross($grossMerchandiseCents, $effectiveTaxRate);

            if ($netMerchandiseCents <= 0) {
                return null;
            }

            $commissionCents = $this->calculateCommissionFromNetCents($finalInfluencer, $netMerchandiseCents);

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
     * Bei Selbstabruf durch den Creator ($enforceMinimum = true) gilt der Mindestauszahlungsbetrag von 20,00 € Netto.
     */
    public function requestPayout(MarketingInfluencer $influencer, ?string $chosenMethod = null, bool $enforceMinimum = false): MarketingInfluencerPayout
    {
        $influencer->loadMissing('employee.profile');

        $method = in_array($chosenMethod, ['bank_transfer', 'paypal', 'shop_voucher'], true)
            ? $chosenMethod
            : $influencer->payout_method;

        if (!$influencer->isReadyForPayout($method)) {
            throw new InvalidArgumentException('Bitte vervollständige zuerst deine Adresse, Steuernummer, Selbstständigkeits-Bestätigung und die Zahlungsdaten für den gewählten Auszahlungsweg.');
        }

        return DB::transaction(function () use ($influencer, $method, $enforceMinimum) {
            $commissions = MarketingInfluencerCommission::where('influencer_id', $influencer->id)
                ->where('status', 'approved')
                ->whereNull('payout_id')
                ->lockForUpdate()
                ->get();

            $netCents = (int) $commissions->sum('commission_amount_cents');
            if ($netCents <= 0) {
                throw new InvalidArgumentException('Aktuell sind keine freigegebenen Provisionen zur Auszahlung verfügbar.');
            }

            if ($enforceMinimum && $netCents < self::MIN_PAYOUT_CENTS) {
                $minFormatted = number_format(self::MIN_PAYOUT_CENTS / 100, 2, ',', '.');
                $currentFormatted = number_format($netCents / 100, 2, ',', '.');
                throw new InvalidArgumentException("Der Mindestauszahlungsbetrag von {$minFormatted} € Netto ist noch nicht erreicht (aktuell freigegeben: {$currentFormatted} €).");
            }

            $bonusCents = 0;
            $taxRate = 0;
            $taxCents = 0;

            if ($method === 'shop_voucher') {
                // +20 % Sachprämien-Boost bei Auszahlung als Seelenfunke Shop-Gutschein
                $bonusCents = (int) round($netCents * 0.20);
                $grossCents = $netCents + $bonusCents;
            } else {
                $defaultShopTaxRate = (float) shop_setting('default_tax_rate', 19.0);
                $taxRate = $influencer->tax_status === 'standard_tax' ? $defaultShopTaxRate : 0.0;
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

    public function generateCreditNotePdf(MarketingInfluencerPayout $payout): ?string
    {
        return $this->generateAndStoreCreditNotePdf($payout);
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
            ->lockForUpdate()
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

    /**
     * Zentrale Provisionsberechnung auf Basis eines Netto-Warenwerts in Cent.
     * Wird sowohl bei echten Bestellungen (processOrderCommission) als auch für die Live-Produktvorschau im Creator-Cockpit genutzt.
     */
    public function calculateCommissionFromNetCents(MarketingInfluencer $influencer, int $netMerchandiseCents): int
    {
        if ($netMerchandiseCents <= 0) {
            return 0;
        }

        if ($influencer->commission_type === 'fixed_cents') {
            return min($netMerchandiseCents, max(0, (int) $influencer->commission_value));
        }

        return (int) round($netMerchandiseCents * (max(0, (int) $influencer->commission_value) / 100));
    }

    /**
     * Berechnet für ein einzelnes Produkt zentral und dynamisch alle relevanten Creator-Kennzahlen
     * unter Berücksichtigung von Produktsteuer, Kleinunternehmer-Status, Follower-Rabatt, Autopilot-Sale-Sperre
     * und individuellem Provisionssatz.
     */
    public function calculateProductPromotionPreview(\App\Models\Product\Product $product, MarketingInfluencer $influencer): array
    {
        $priceCalc = app(\App\Services\PriceCalcService::class);
        $grossPriceCents = (int) $product->gross_price;
        $compareAtGrossCents = (int) ($product->compare_at_price ?? 0);
        $isAutopilotSale = $product->isInAutopilotSale();
        $isCouponCompatible = $product->isDiscountableWithCoupon();

        $followerDiscountPercent = ($isCouponCompatible && (int) $influencer->follower_discount_percent > 0)
            ? (int) $influencer->follower_discount_percent
            : 0;

        // Exakt identische Rundungsformel wie CartService::calculateTotals()
        $followerDiscountCents = $followerDiscountPercent > 0
            ? (int) round($grossPriceCents * ($followerDiscountPercent / 100))
            : 0;

        $followerFinalGrossCents = max(0, $grossPriceCents - $followerDiscountCents);
        $effectiveTaxRate = (float) $product->tax_rate;
        $netBasisCents = $priceCalc->getNetFromGross($followerFinalGrossCents, $effectiveTaxRate);
        $estimatedCommissionCents = $this->calculateCommissionFromNetCents($influencer, $netBasisCents);

        $salesForMinPayout = $estimatedCommissionCents > 0
            ? (int) ceil(self::MIN_PAYOUT_CENTS / $estimatedCommissionCents)
            : null;

        $voucherCode = $influencer->defaultVoucher?->code ?: ('CODE' . $influencer->follower_discount_percent);
        $cookieDays = max(1, (int) ($influencer->cookie_days ?: 30));

        $highlights = [];
        if ($product->isPersonalizable() || $product->three_d_active) {
            $highlights[] = 'Live-Personalisierung / 3D (Ideal für Story-Video)';
        }
        if ($salesForMinPayout !== null && $salesForMinPayout <= 4) {
            $highlights[] = "Nur {$salesForMinPayout}x für 20 € Auszahlung";
        }
        if ((int) ($product->paid_sales_count ?? 0) >= 3) {
            $highlights[] = 'Shop-Bestseller';
        }
        if ($product->track_quantity && !$product->continue_selling_when_out_of_stock && $product->quantity > 0 && $product->quantity <= 5) {
            $highlights[] = "Nur noch {$product->quantity} auf Lager";
        }

        if ($isCouponCompatible) {
            $finalEuro = number_format($followerFinalGrossCents / 100, 2, ',', '.');
            $commEuro = number_format($estimatedCommissionCents / 100, 2, ',', '.');
            $reasonText = $followerDiscountPercent > 0
                ? "Voller Doppel-Vorteil: Dein Code {$voucherCode} (-{$followerDiscountPercent} % → {$finalEuro} €) + dein Kurzlink sind hier voll kombinierbar. Du erhältst +{$commEuro} € Netto-Provision pro Verkauf."
                : "Sofort über deinen Kurzlink bewerbbar: Du erhältst +{$commEuro} € Netto-Provision pro Verkauf.";
        } else {
            $origEuro = number_format($compareAtGrossCents / 100, 2, ',', '.');
            $saleEuro = number_format($grossPriceCents / 100, 2, ',', '.');
            $commEuro = number_format($estimatedCommissionCents / 100, 2, ',', '.');
            $reasonText = "Aktions-Unikat (im Shop bereits von {$origEuro} € auf {$saleEuro} € reduziert): Nicht mit zusätzlichem Rabattcode kombinierbar – bewirb dieses Produkt direkt über deinen Kurzlink ({$cookieDays} Tage Cookie-Tracking) für +{$commEuro} € Provision.";
        }

        $targetPath = '/produkt/' . $product->slug;
        $existingLink = $influencer->relationLoaded('trackingLinks')
            ? $influencer->trackingLinks->firstWhere('target_url', $targetPath)
            : $influencer->trackingLinks()->where('target_url', $targetPath)->first();

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'category_name' => $product->relationLoaded('categories') ? ($product->categories->first()?->name ?? 'Manufaktur-Unikat') : 'Manufaktur-Unikat',
            'image_url' => $product->resolveMainImageUrl() ?: asset('shop/projekt/logo/mein-seelenfunke-logo.png'),
            'shop_url' => url($targetPath),
            'gross_price_cents' => $grossPriceCents,
            'compare_at_gross_cents' => $compareAtGrossCents,
            'is_autopilot_sale' => $isAutopilotSale,
            'is_coupon_compatible' => $isCouponCompatible,
            'follower_discount_percent' => $followerDiscountPercent,
            'follower_discount_cents' => $followerDiscountCents,
            'follower_final_gross_cents' => $followerFinalGrossCents,
            'effective_tax_rate' => $effectiveTaxRate,
            'net_basis_cents' => $netBasisCents,
            'estimated_commission_cents' => $estimatedCommissionCents,
            'sales_for_min_payout' => $salesForMinPayout,
            'mode' => $isCouponCompatible ? 'code_and_link' : 'link_only',
            'mode_badge' => $isCouponCompatible ? 'Code + Kurzlink aktiv' : 'Aktions-Deal • Nur Kurzlink',
            'highlights' => $highlights,
            'reason_text' => $reasonText,
            'existing_short_url' => $existingLink?->short_url,
            'existing_link_clicks' => (int) ($existingLink?->clicks_count ?? 0),
        ];
    }

    /**
     * Liefert alle aktuell vom Influencer bewerbbaren Produkte (aktiv, auf Lager, keine Geschenkgutscheine),
     * angereichert um die zentrale Provisions- und Rabatt-Vorschau, sortiert nach Relevanz.
     */
    public function getPromotableProductsForInfluencer(MarketingInfluencer $influencer): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable('products')) {
            return collect();
        }

        $influencer->loadMissing(['defaultVoucher', 'trackingLinks']);

        $products = \App\Models\Product\Product::where('status', 'active')
            ->with([
                'categories',
                'templates' => fn ($q) => $q->where('is_active', true),
            ])
            ->withCount([
                'orderItems as paid_sales_count' => function ($q) {
                    $q->whereHas('order', function ($oq) {
                        $oq->where('payment_status', 'paid')
                            ->whereNotIn('status', ['cancelled', 'refunded']);
                    });
                },
            ])
            ->orderBy('name')
            ->get();

        return $products
            ->filter(fn (\App\Models\Product\Product $product) => $product->isPromotableByInfluencer())
            ->map(fn (\App\Models\Product\Product $product) => $this->calculateProductPromotionPreview($product, $influencer))
            ->sortByDesc(function (array $item) {
                // Priorisiere Produkte, bei denen Code + Link gleichzeitig greifen, danach Provisionshöhe
                $compatibilityScore = $item['is_coupon_compatible'] ? 1000000 : 0;
                return $compatibilityScore + (int) $item['estimated_commission_cents'];
            })
            ->values();
    }

    /**
     * Erstellt oder liefert einen produktbezogenen Kurz-Tracking-Link (/t/{slug}-{product}) für den Creator.
     */
    public function createOrGetProductTrackingLink(
        MarketingInfluencer $influencer,
        ?\App\Models\Product\Product $product = null,
        ?string $customTitle = null
    ): MarketingTrackingLink {
        $targetUrl = '/shop';
        $slugSuffix = $customTitle ? Str::slug($customTitle) : '';
        $linkName = trim((string) $customTitle);

        if ($product && $product->isPromotableByInfluencer()) {
            $targetUrl = '/produkt/' . $product->slug;

            if ($customTitle === null || trim($customTitle) === '') {
                $existing = MarketingTrackingLink::where('influencer_id', $influencer->id)
                    ->where('target_url', $targetUrl)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            if ($slugSuffix === '') {
                $slugSuffix = $product->slug;
            }
            if ($linkName === '') {
                $linkName = 'Produkt-Link: ' . Str::limit($product->name, 55, '');
            }
        }

        if ($linkName === '') {
            $linkName = 'Story-Link ' . $influencer->handle;
        }

        $candidateSlug = Str::slug(($influencer->slug ?: 'creator') . '-' . ($slugSuffix ?: strtolower(Str::random(4))));
        if (MarketingTrackingLink::where('slug', $candidateSlug)->exists()) {
            $candidateSlug .= '-' . strtolower(Str::random(3));
        }

        return MarketingTrackingLink::create([
            'influencer_id' => $influencer->id,
            'name' => $linkName,
            'slug' => $candidateSlug,
            'target_url' => $targetUrl,
            'channel' => 'referral',
            'utm_source' => 'influencer',
            'utm_medium' => $influencer->primary_channel ?: 'social',
            'utm_campaign' => $candidateSlug,
            'is_active' => (bool) $influencer->is_active && !(bool) $influencer->is_archived,
        ]);
    }
}
