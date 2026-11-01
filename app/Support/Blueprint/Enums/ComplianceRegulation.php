<?php

namespace App\Support\Blueprint\Enums;

enum ComplianceRegulation: string
{
    case GoBD = 'gobd';
    case DSGVO_Art15_Art30 = 'dsgvo_art15_art30';
    case DSGVO_Art9_Health = 'dsgvo_art9_health';
    case GPSR_EU_2023_988 = 'gpsr_eu_2023_988';
    case BGB_312g_CustomGoods = 'bgb_312g_custom_goods';
    case BGB_356a_ElectronicRevocation = 'bgb_356a_revocation';
    case EU_AI_Act_Art50 = 'eu_ai_act_art50';
    case VerpackG_LUCID = 'verpackg_lucid';
    case PAngV = 'pangv';

    public function label(): string
    {
        return match ($this) {
            self::GoBD => 'GoBD / § 147 AO / § 257 HGB (Unveränderlichkeit & 10J Aufbewahrung)',
            self::DSGVO_Art15_Art30 => 'DSGVO Art. 15 (Auskunft) & Art. 30 (Verarbeitungsverzeichnis)',
            self::DSGVO_Art9_Health => 'DSGVO Art. 9 (Schutz besonderer Kategorien / Gesundheitsdaten)',
            self::GPSR_EU_2023_988 => 'EU-Produktsicherheitsverordnung (GPSR Art. 19)',
            self::BGB_312g_CustomGoods => '§ 312g Abs. 2 Nr. 1 BGB (Widerrufsausschluss bei Personalisierung)',
            self::BGB_356a_ElectronicRevocation => '§ 356a BGB (Elektronischer 2-Stufen-Widerrufsbutton)',
            self::EU_AI_Act_Art50 => 'EU AI Act Art. 50 (KI-Transparenz- & Kennzeichnungspflicht)',
            self::VerpackG_LUCID => 'VerpackG § 9 / § 10 (LUCID Mengenstrom-Nachweis)',
            self::PAngV => 'PAngV (Preisangaben- & Grundpreispflicht)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::GoBD => 'GoBD 10J',
            self::DSGVO_Art15_Art30 => 'DSGVO Art. 15/30',
            self::DSGVO_Art9_Health => 'DSGVO Art. 9',
            self::GPSR_EU_2023_988 => 'GPSR Art. 19',
            self::BGB_312g_CustomGoods => '§ 312g BGB',
            self::BGB_356a_ElectronicRevocation => '§ 356a BGB',
            self::EU_AI_Act_Art50 => 'EU AI Act Art. 50',
            self::VerpackG_LUCID => 'LUCID VerpackG',
            self::PAngV => 'PAngV',
        };
    }
}
