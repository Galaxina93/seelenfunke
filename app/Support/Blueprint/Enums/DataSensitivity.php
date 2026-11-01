<?php

namespace App\Support\Blueprint\Enums;

enum DataSensitivity: string
{
    case PublicCatalog = 'public_catalog';
    case InternalErp = 'internal_erp';
    case PersonalPII = 'personal_pii';
    case FinancialConfidential = 'financial_confidential';
    case SpecialCategoryArt9 = 'special_category_art9';

    public function label(): string
    {
        return match ($this) {
            self::PublicCatalog => 'Öffentlich (Shop- & Katalogdaten)',
            self::InternalErp => 'Intern (Manufaktur, Kalkulation & Technik)',
            self::PersonalPII => 'Personenbezogen (Art. 4 Nr. 1 DSGVO)',
            self::FinancialConfidential => 'Streng vertraulich (Finanzen, Steuern & Verträge)',
            self::SpecialCategoryArt9 => 'Besondere Kategorie (Art. 9 DSGVO / Gesundheit)',
        };
    }

    public function legalBasis(): string
    {
        return match ($this) {
            self::PublicCatalog => 'Art. 6 Abs. 1 lit. f DSGVO (Berechtigtes Interesse / Öffentliches Angebot)',
            self::InternalErp => 'Art. 6 Abs. 1 lit. f DSGVO (Betriebssteuerung & IT-Sicherheit)',
            self::PersonalPII => 'Art. 6 Abs. 1 lit. b & a DSGVO (Vertragserfüllung / Einwilligung)',
            self::FinancialConfidential => 'Art. 6 Abs. 1 lit. c DSGVO i.V.m. § 147 AO / § 257 HGB (Gesetzliche Pflicht)',
            self::SpecialCategoryArt9 => 'Art. 9 Abs. 2 lit. a DSGVO (Ausdrückliche Einwilligung im geschützten Raum)',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::PublicCatalog => 'emerald',
            self::InternalErp => 'sky',
            self::PersonalPII => 'amber',
            self::FinancialConfidential => 'rose',
            self::SpecialCategoryArt9 => 'purple',
        };
    }
}
