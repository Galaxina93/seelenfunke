<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Auth;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;

class AiAuthHelper
{
    public static function isAdmin(): bool
    {
        $user = Auth::user();
        return ($user instanceof Admin) || Auth::guard('admin')->check();
    }

    public static function isEmployee(): bool
    {
        $user = Auth::user();
        if ($user instanceof \App\Models\Employee\Employee) {
            return $user->isInternalEmployee();
        }
        $employee = Auth::guard('employee')->user();
        return ($employee instanceof \App\Models\Employee\Employee) && $employee->isInternalEmployee();
    }

    public static function isCustomer(): bool
    {
        $user = Auth::user();
        return ($user instanceof Customer) || Auth::guard('customer')->check();
    }

    public static function getAdminId()
    {
        $user = Auth::user();
        if ($user instanceof Admin) {
            return $user->id;
        }
        return Auth::guard('admin')->id();
    }

    public static function getEmployeeId()
    {
        $user = Auth::user();
        if ($user instanceof \App\Models\Employee\Employee) {
            return $user->id;
        }
        return Auth::guard('employee')->id();
    }

    public static function getCustomerId()
    {
        $user = Auth::user();
        if ($user instanceof Customer) {
            return $user->id;
        }
        return Auth::guard('customer')->id();
    }

    public static function check(): bool
    {
        return self::isAdmin() || self::isEmployee() || self::isCustomer() || Auth::check();
    }

    public static function getUserId()
    {
        if (self::isAdmin()) {
            return self::getAdminId();
        }
        if (self::isEmployee()) {
            return self::getEmployeeId();
        }
        if (self::isCustomer()) {
            return self::getCustomerId();
        }
        return Auth::id();
    }

    public static function getUser()
    {
        if (self::isAdmin()) {
            return (Auth::user() instanceof Admin) ? Auth::user() : Auth::guard('admin')->user();
        }
        if (self::isEmployee()) {
            return (Auth::user() instanceof \App\Models\Employee\Employee) ? Auth::user() : Auth::guard('employee')->user();
        }
        if (self::isCustomer()) {
            return (Auth::user() instanceof Customer) ? Auth::user() : Auth::guard('customer')->user();
        }
        return Auth::user();
    }

    /**
     * Prüft, ob für einen Kunden eine gültige DSGVO-Einwilligung (Art. 6 Abs. 1 lit. a DSGVO)
     * zur KI-gestützten Verarbeitung personenbezogener Daten vorliegt.
     * Gastbestellungen ohne verknüpftes Kundenkonto mit aktivem Haken gelten grundsätzlich als NICHT freigegeben.
     */
    public static function hasCustomerAiConsent(?Customer $customer = null, ?string $customerId = null, ?string $email = null): bool
    {
        if (!$customer && !empty($customerId)) {
            $customer = Customer::with('profile')->find($customerId);
        }

        if (!$customer && !empty($email)) {
            $normalizedEmail = strtolower(trim($email));
            if ($normalizedEmail !== '') {
                $customer = Customer::with('profile')
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->first();
            }
        }

        if (!$customer) {
            return false;
        }

        return $customer->hasAiDataConsent();
    }

    /**
     * Gibt den Klarnamen nur zurück, wenn der Kunde der DSGVO-KI-Verarbeitung zugestimmt hat.
     * Andernfalls wird "Anonyme Person (Keine DSGVO-KI-Freigabe)" zurückgegeben.
     */
    public static function sanitizeCustomerName(?string $realName, bool $hasConsent, string $fallbackLabel = 'Anonyme Person (Keine DSGVO-KI-Freigabe)'): string
    {
        if (!$hasConsent) {
            return $fallbackLabel;
        }

        $trimmed = trim((string) $realName);
        return $trimmed !== '' ? $trimmed : 'Kunde';
    }

    /**
     * Gibt die E-Mail-Adresse nur bei aktiver DSGVO-KI-Freigabe zurück.
     */
    public static function sanitizeCustomerEmail(?string $realEmail, bool $hasConsent): string
    {
        if (!$hasConsent) {
            return '[E-Mail DSGVO-geschützt]';
        }

        $trimmed = trim((string) $realEmail);
        return $trimmed !== '' ? $trimmed : 'Unbekannt';
    }

    /**
     * Maskiert eine Rechnungs- oder Lieferadresse, sofern keine DSGVO-KI-Freigabe vorliegt.
     * Nicht-personenbezogene Strukturinfos (z. B. Zielland für Versandlogik) bleiben erhalten.
     */
    public static function sanitizeCustomerAddress($address, bool $hasConsent): array
    {
        $addr = is_array($address) ? $address : (json_decode((string) $address, true) ?: []);

        if ($hasConsent) {
            return $addr;
        }

        return [
            'first_name' => 'Anonyme',
            'last_name' => 'Person (Keine DSGVO-KI-Freigabe)',
            'company' => !empty($addr['company']) ? '[Firma DSGVO-geschützt]' : null,
            'street' => '[Straße DSGVO-geschützt]',
            'address' => '[Adresse DSGVO-geschützt]',
            'postal_code' => '[PLZ DSGVO-geschützt]',
            'city' => '[Ort DSGVO-geschützt]',
            'country' => $addr['country'] ?? 'DE',
            'gdpr_notice' => 'Personenbezogene Adressdaten wurden ausgeblendet, da dieser Kunde keine DSGVO-KI-Freigabe erteilt hat.',
        ];
    }

    /**
     * Schwärzt personenbezogene Daten (E-Mail, Telefonnummern, bekannte Klarnamen) in Freitexten,
     * falls keine DSGVO-KI-Freigabe vorliegt.
     */
    public static function redactPiiText(?string $text, bool $hasConsent, array $namesToMask = []): string
    {
        $text = (string) $text;
        if ($hasConsent || $text === '') {
            return $text;
        }

        // E-Mail-Adressen maskieren
        $redacted = preg_replace('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', '[E-Mail DSGVO-geschützt]', $text);

        // Telefonnummern maskieren
        $redacted = preg_replace('/(\+?\d[\d\s\-\/\(\)]{6,}\d)/', '[Telefon DSGVO-geschützt]', $redacted);

        foreach ($namesToMask as $name) {
            $clean = trim((string) $name);
            if (mb_strlen($clean) >= 2) {
                $redacted = str_ireplace($clean, 'Anonyme Person', $redacted);
            }
        }

        return $redacted;
    }
}
