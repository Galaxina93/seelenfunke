<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiAgent;

class AiCompanyStructureSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Departments with Fixed UUIDs
        $deptCeo = AiDepartment::updateOrCreate(
            ['id' => '019d0000-0000-0000-0000-000000000000'],
            ['name' => 'Leitung', 'description' => 'Unternehmensführung, Vision und zentrale Steuerung', 'icon' => 'bolt', 'color' => 'primary', 'order_index' => 1]
        );

        $deptSupport = AiDepartment::updateOrCreate(
            ['id' => '019d6666-6666-6666-6666-666666666666'],
            ['name' => 'Support', 'description' => 'Kundenbetreuung, Ticket-Management und Kundenchat', 'icon' => 'lifebuoy', 'color' => 'cyan-500', 'order_index' => 2]
        );

        $deptProducts = AiDepartment::updateOrCreate(
            ['id' => '019d1111-1111-1111-1111-111111111111'],
            ['name' => 'Produkte', 'description' => 'Produktmanagement, Kreation und Entwicklung', 'icon' => 'shopping-bag', 'color' => 'blue-500', 'order_index' => 3]
        );

        $deptMarketing = AiDepartment::updateOrCreate(
            ['id' => '019d2222-2222-2222-2222-222222222222'],
            ['name' => 'Marketing', 'description' => 'Kampagnen, Social Media und Promotion', 'icon' => 'megaphone', 'color' => 'purple-500', 'order_index' => 4]
        );

        $deptOrders = AiDepartment::updateOrCreate(
            ['id' => '019d3333-3333-3333-3333-333333333333'],
            ['name' => 'Bestellungen', 'description' => 'Auftragsabwicklung, Support und Vertrieb', 'icon' => 'shopping-cart', 'color' => 'amber-500', 'order_index' => 5]
        );

        $deptFinance = AiDepartment::updateOrCreate(
            ['id' => '019d4444-4444-4444-4444-444444444444'],
            ['name' => 'Buchhaltung', 'description' => 'Finanzen, Rechnungen und internes Controlling', 'icon' => 'banknotes', 'color' => 'emerald-500', 'order_index' => 6]
        );

        $deptAgents = AiDepartment::updateOrCreate(
            ['id' => '019daaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'],
            ['name' => 'Agenten', 'description' => 'KI-Agenten Management, Organigramm, Rollen', 'icon' => 'cpu-chip', 'color' => 'indigo-500', 'order_index' => 7]
        );

        $deptSystem = AiDepartment::updateOrCreate(
            ['id' => '019d5555-5555-5555-5555-555555555555'],
            ['name' => 'System', 'description' => 'Systemkonfiguration, Logs und Technik', 'icon' => 'server', 'color' => 'red-500', 'order_index' => 8]
        );

        // 2. Assign Agents

        AiAgent::whereIn('name', ['Funkira'])->update(['ai_department_id' => $deptCeo->id]);
        AiAgent::whereIn('name', ['Mapi'])->update(['ai_department_id' => $deptCeo->id]);
        AiAgent::whereIn('name', ['Funki'])->update(['ai_department_id' => $deptSupport->id]);
        AiAgent::whereIn('name', ['Produkti'])->update(['ai_department_id' => $deptProducts->id]);
        AiAgent::whereIn('name', ['Marketi'])->update(['ai_department_id' => $deptMarketing->id]);
        AiAgent::whereIn('name', ['Bestelli'])->update(['ai_department_id' => $deptOrders->id]);
        AiAgent::whereIn('name', ['Buchi'])->update(['ai_department_id' => $deptFinance->id]);
        AiAgent::whereIn('name', ['Agenti', 'Dr. Funki'])->update(['ai_department_id' => $deptAgents->id]);
        AiAgent::whereIn('name', ['Systemi'])->update(['ai_department_id' => $deptSystem->id]);
    }
}
