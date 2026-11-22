<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use Illuminate\Database\Seeder;

class AiCompanyStructureSeeder extends Seeder
{
    public function run(): void
    {
        $deptCeo = AiDepartment::updateOrCreate(
            ['id' => '019d0000-0000-0000-0000-000000000000'],
            [
                'name' => 'Leitung',
                'description' => 'Zentrale Steuerung unter Funkira, Kalender, Aufgaben, E-Mail, Tagesroutine, Gesundheit und Strategie',
                'icon' => 'bolt',
                'color' => 'primary',
                'order_index' => 1,
            ]
        );

        $deptSupport = AiDepartment::updateOrCreate(
            ['id' => '019d6666-6666-6666-6666-666666666666'],
            [
                'name' => 'Support',
                'description' => 'Air-Gapped Shop-Kundenberatung und Eskalation offener Kundenanliegen',
                'icon' => 'lifebuoy',
                'color' => 'cyan-500',
                'order_index' => 2,
            ]
        );

        $deptProducts = AiDepartment::updateOrCreate(
            ['id' => '019d1111-1111-1111-1111-111111111111'],
            [
                'name' => 'Produkte',
                'description' => 'Produktsortiment, 2D/3D-Konfigurator, Rohling-Maße und xTool F2 Ultra UV Laser-Manufaktur',
                'icon' => 'shopping-bag',
                'color' => 'blue-500',
                'order_index' => 3,
            ]
        );

        $deptMarketing = AiDepartment::updateOrCreate(
            ['id' => '019d2222-2222-2222-2222-222222222222'],
            [
                'name' => 'Marketing',
                'description' => 'SEO/GEO, Magazin, Landingpages, Gutscheine, Newsletter und Creator-Portal',
                'icon' => 'megaphone',
                'color' => 'purple-500',
                'order_index' => 4,
            ]
        );

        $deptOrders = AiDepartment::updateOrCreate(
            ['id' => '019d3333-3333-3333-3333-333333333333'],
            [
                'name' => 'Bestellungen',
                'description' => 'Auftragsabwicklung, Express-Fertigung, DHL-Versand, Angebote und Widerrufe',
                'icon' => 'shopping-cart',
                'color' => 'amber-500',
                'order_index' => 5,
            ]
        );

        $deptFinance = AiDepartment::updateOrCreate(
            ['id' => '019d4444-4444-4444-4444-444444444444'],
            [
                'name' => 'Buchhaltung',
                'description' => 'Buchhaltung, Belege, Fixkosten, Steuern sowie gesicherter Rohling- und Verpackungs-Einkauf',
                'icon' => 'banknotes',
                'color' => 'emerald-500',
                'order_index' => 6,
            ]
        );

        $deptSystem = AiDepartment::updateOrCreate(
            ['id' => '019d5555-5555-5555-5555-555555555555'],
            [
                'name' => 'System',
                'description' => 'Server-Überwachung, 0-Token-Selbstheilung, Backups, Cronjobs und Antigravity-Entwicklung',
                'icon' => 'server',
                'color' => 'red-500',
                'order_index' => 7,
            ]
        );

        // Entferne veraltete Falcone/Falcon/Agenti-Einträge und löse die alte Meta-Abteilung Agenten sauber auf
        AiAgent::whereIn('name', ['Falcone', 'Falcon', 'Agenti'])->delete();

        AiAgent::whereIn('name', ['Funkira', 'Dr. Funki', 'Globi'])->update(['ai_department_id' => $deptCeo->id]);
        AiAgent::whereIn('name', ['Funki'])->update(['ai_department_id' => $deptSupport->id]);
        AiAgent::whereIn('name', ['Produkti', 'Lasi'])->update(['ai_department_id' => $deptProducts->id]);
        AiAgent::whereIn('name', ['Marketi'])->update(['ai_department_id' => $deptMarketing->id]);
        AiAgent::whereIn('name', ['Bestelli'])->update(['ai_department_id' => $deptOrders->id]);
        AiAgent::whereIn('name', ['Buchi', 'Einkaufi'])->update(['ai_department_id' => $deptFinance->id]);
        AiAgent::whereIn('name', ['Systemi', 'Antigravity'])->update(['ai_department_id' => $deptSystem->id]);

        AiDepartment::where('id', '019daaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')->delete();
    }
}
