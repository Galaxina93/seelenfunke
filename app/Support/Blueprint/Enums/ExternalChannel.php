<?php

namespace App\Support\Blueprint\Enums;

enum ExternalChannel: string
{
    case StripePayment = 'stripe_payment';
    case DHL_DeutschePost = 'dhl_deutsche_post';
    case LaserMachine_SVG_PDF = 'laser_machine_svg_pdf';
    case LUCID_VerpackG = 'lucid_verpackg';
    case GoogleGemini_AI = 'google_gemini_ai';
    case DATEV_TaxExport = 'datev_tax_export';
    case IMAP_SMTP_Mail = 'imap_smtp_mail';
    case Twilio_Voice = 'twilio_voice';

    public function label(): string
    {
        return match ($this) {
            self::StripePayment => 'Stripe Payment Gateway',
            self::DHL_DeutschePost => 'DHL & Deutsche Post Versand-Schnittstelle',
            self::LaserMachine_SVG_PDF => 'xTool / LightBurn 1:1 Laser-Gravur Export (SVG/PDF)',
            self::LUCID_VerpackG => 'Zentrale Stelle Verpackungsregister (LUCID)',
            self::GoogleGemini_AI => 'Google Gemini Multimodal & Live AI',
            self::DATEV_TaxExport => 'Steuerberater & Finanzamt Export (GoBD/CSV/PDF)',
            self::IMAP_SMTP_Mail => 'IMAP / SMTP E-Mail-Server',
            self::Twilio_Voice => 'Twilio Telefonie & Voice-Stream',
        };
    }
}
