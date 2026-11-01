<?php

namespace App\Support\Blueprint\Enums;

enum SystemDepartment: string
{
    case Accounting = 'accounting';
    case Admin = 'admin';
    case Ai = 'ai';
    case Cart = 'cart';
    case Customer = 'customer';
    case Delivery = 'delivery';
    case Employee = 'employee';
    case Logistics = 'logistics';
    case Management = 'management';
    case Marketing = 'marketing';
    case Order = 'order';
    case Product = 'product';
    case Support = 'support';
    case System = 'system';
    case Tracking = 'tracking';

    public function label(): string
    {
        return match ($this) {
            self::Accounting => 'Buchhaltung & Finanzen',
            self::Admin => 'Leitung & Administration',
            self::Ai => 'KI & Agenten (Funki)',
            self::Cart => 'Warenkorb & Checkout',
            self::Customer => 'Kunden & Gamification',
            self::Delivery => 'Lieferzeiten & Versandstatus',
            self::Employee => 'Mitarbeiter & Personal',
            self::Logistics => 'Logistik & Versandtarife',
            self::Management => 'Management, Planung & Postfach',
            self::Marketing => 'Marketing, Gutscheine & Partner',
            self::Order => 'Bestellungen & Manufaktur-Fertigung',
            self::Product => 'Produkte, Rohlinge & 2D/3D-Konfigurator',
            self::Support => 'Kundensupport & Tickets',
            self::System => 'System, Sicherheit & Projekt-Gehirn',
            self::Tracking => 'Analyse & Besucher-Tracking',
        };
    }

    public function themingName(): string
    {
        return match ($this) {
            self::Accounting => 'Buchhaltung',
            self::Admin => 'Leitung',
            self::Ai => 'Agenten',
            self::Cart, self::Order => 'Bestellungen',
            self::Customer => 'Kunden',
            self::Delivery, self::Logistics => 'Logistik',
            self::Employee => 'Personal',
            self::Management => 'Management',
            self::Marketing => 'Marketing',
            self::Product => 'Produkte',
            self::Support => 'Support',
            self::System => 'System',
            self::Tracking => 'Analyse',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::Accounting => '#10b981', // emerald
            self::Admin => '#c5a059',      // gold
            self::Ai => '#06b6d4',         // cyan
            self::Cart => '#f59e0b',       // amber
            self::Customer => '#8b5cf6',   // violet
            self::Delivery => '#0ea5e9',   // sky
            self::Employee => '#3b82f6',   // blue
            self::Logistics => '#14b8a6',  // teal
            self::Management => '#6366f1', // indigo
            self::Marketing => '#ec4899',  // pink
            self::Order => '#f97316',      // orange
            self::Product => '#c5a059',    // gold
            self::Support => '#22c55e',    // green
            self::System => '#ef4444',     // red
            self::Tracking => '#a855f7',   // purple
        };
    }
}
