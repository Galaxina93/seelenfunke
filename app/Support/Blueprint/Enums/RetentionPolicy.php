<?php

namespace App\Support\Blueprint\Enums;

enum RetentionPolicy: string
{
    case GoBD_10Years = 'gobd_10_years';
    case TaxBusinessLetters_6Years = 'tax_letters_6_years';
    case StatutoryWarranty_3Years = 'warranty_3_years';
    case ManufacturingAssets_90Days = 'manufacturing_assets_90_days';
    case ShortLived_30Days = 'short_lived_30_days';
    case UntilConsentRevoked = 'until_consent_revoked';
    case PermanentMasterData = 'permanent_master_data';

    public function label(): string
    {
        return match ($this) {
            self::GoBD_10Years => '10 Jahre GoBD (§ 147 AO / § 257 HGB – Sperrung statt Löschung)',
            self::TaxBusinessLetters_6Years => '6 Jahre Handels-/Geschäftsbriefe (§ 257 HGB)',
            self::StatutoryWarranty_3Years => '3 Jahre Regelverjährung (§ 195 BGB)',
            self::ManufacturingAssets_90Days => '90 Tage nach Versand (Temporäre Druck-/Laser-Assets)',
            self::ShortLived_30Days => '30 Tage Kurzzeitspeicher (Automatisches Pruning)',
            self::UntilConsentRevoked => 'Bis zum Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO)',
            self::PermanentMasterData => 'Dauerhafte System- & Katalog-Stammdaten',
        };
    }

    public function days(): ?int
    {
        return match ($this) {
            self::GoBD_10Years => 3650,
            self::TaxBusinessLetters_6Years => 2190,
            self::StatutoryWarranty_3Years => 1095,
            self::ManufacturingAssets_90Days => 90,
            self::ShortLived_30Days => 30,
            self::UntilConsentRevoked, self::PermanentMasterData => null,
        };
    }

    public function isGoBdLocked(): bool
    {
        return $this === self::GoBD_10Years;
    }

    public function requiresAutoCleanup(): bool
    {
        return in_array($this, [self::ShortLived_30Days, self::ManufacturingAssets_90Days], true);
    }
}
