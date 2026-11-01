<?php

namespace App\Support\Blueprint\Enums;

enum ActorScope: string
{
    case PublicVisitor = 'public_visitor';
    case Customer = 'customer';
    case PartnerInfluencer = 'partner_influencer';
    case EmployeeProduction = 'employee_production';
    case Admin = 'admin';
    case SystemCron = 'system_cron';

    public function label(): string
    {
        return match ($this) {
            self::PublicVisitor => 'Shop-Besucher (Gast / Öffentlich)',
            self::Customer => 'Kunde (Kundenportal)',
            self::PartnerInfluencer => 'Partner & Influencer (Affiliate-Portal)',
            self::EmployeeProduction => 'Manufaktur & Versand (Mitarbeiter)',
            self::Admin => 'Geschäftsführung (Admin)',
            self::SystemCron => 'System-Scheduler / Automatischer Prozess',
        };
    }
}
