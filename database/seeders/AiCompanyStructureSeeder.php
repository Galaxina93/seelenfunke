<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiCompanyStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Departments with Fixed UUIDs to Map into Navigation 
        $deptCeo = \App\Models\Ai\AiDepartment::updateOrCreate(
            ['id' => '019d0000-0000-0000-0000-000000000000'],
            ['name' => 'Firmenleitung', 'description' => 'Unternehmensführung, Vision und zentrale Steuerung', 'icon' => 'bolt', 'color' => 'primary', 'order_index' => 0]
        );

        $deptProducts = \App\Models\Ai\AiDepartment::updateOrCreate(
            ['id' => '019d1111-1111-1111-1111-111111111111'],
            ['name' => 'Produkte', 'description' => 'Produktmanagement, Kreation und Entwicklung', 'icon' => 'shopping-bag', 'color' => 'blue-500', 'order_index' => 1]
        );

        $deptMarketing = \App\Models\Ai\AiDepartment::updateOrCreate(
            ['id' => '019d2222-2222-2222-2222-222222222222'],
            ['name' => 'Marketing', 'description' => 'Kampagnen, Social Media und Promotion', 'icon' => 'megaphone', 'color' => 'purple-500', 'order_index' => 2]
        );

        $deptOrders = \App\Models\Ai\AiDepartment::updateOrCreate(
            ['id' => '019d3333-3333-3333-3333-333333333333'],
            ['name' => 'Bestellungen', 'description' => 'Auftragsabwicklung, Support und Vertrieb', 'icon' => 'shopping-cart', 'color' => 'amber-500', 'order_index' => 3]
        );

        $deptFinance = \App\Models\Ai\AiDepartment::updateOrCreate(
            ['id' => '019d4444-4444-4444-4444-444444444444'],
            ['name' => 'Buchhaltung', 'description' => 'Finanzen, Rechnungen und internes Controlling', 'icon' => 'banknotes', 'color' => 'emerald-500', 'order_index' => 4]
        );

        // 2. Assign Agents
        // "Funkira", "Zion", "Taron", "Rion", "Vira", "Funki", "Dr. Funki", "Marketi"
        
        \App\Models\Ai\AiAgent::whereIn('name', ['Funkira', 'Dr. Funki'])
            ->update(['ai_department_id' => $deptCeo->id]);

        \App\Models\Ai\AiAgent::whereIn('name', ['Funki'])
            ->update(['ai_department_id' => $deptProducts->id]);
        
        \App\Models\Ai\AiAgent::whereIn('name', ['Marketi', 'Vira'])
            ->update(['ai_department_id' => $deptMarketing->id]);
        
        \App\Models\Ai\AiAgent::whereIn('name', ['Zion'])
            ->update(['ai_department_id' => $deptOrders->id]);
        
        \App\Models\Ai\AiAgent::whereIn('name', ['Taron', 'Rion'])
            ->update(['ai_department_id' => $deptFinance->id]);
    }
}
