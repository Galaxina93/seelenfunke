<?php

namespace Database\Seeders;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiCompanyStructureSeeder extends Seeder
{
    public function run(): void
    {
        $canonicalDepartments = [
            [
                'id' => '019d0000-0000-0000-0000-000000000000',
                'name' => 'Leitung',
                'description' => 'Zentrale Steuerung unter Funkira, Kalender, Aufgaben, E-Mail, Tagesroutine, Gesundheit und Strategie',
                'icon' => 'bolt',
                'color' => 'primary',
                'order_index' => 1,
            ],
            [
                'id' => '019d6666-6666-6666-6666-666666666666',
                'name' => 'Support',
                'description' => 'Air-Gapped Shop-Kundenberatung und Eskalation offener Kundenanliegen',
                'icon' => 'lifebuoy',
                'color' => 'cyan-500',
                'order_index' => 2,
            ],
            [
                'id' => '019d1111-1111-1111-1111-111111111111',
                'name' => 'Produkte',
                'description' => 'Produktsortiment, 2D/3D-Konfigurator, Rohling-Maße und xTool F2 Ultra UV Laser-Manufaktur',
                'icon' => 'shopping-bag',
                'color' => 'blue-500',
                'order_index' => 3,
            ],
            [
                'id' => '019d2222-2222-2222-2222-222222222222',
                'name' => 'Marketing',
                'description' => 'SEO/GEO, Magazin, Landingpages, Gutscheine, Newsletter und Creator-Portal',
                'icon' => 'megaphone',
                'color' => 'purple-500',
                'order_index' => 4,
            ],
            [
                'id' => '019d3333-3333-3333-3333-333333333333',
                'name' => 'Bestellungen',
                'description' => 'Auftragsabwicklung, Express-Fertigung, DHL-Versand, Angebote und Widerrufe',
                'icon' => 'shopping-cart',
                'color' => 'amber-500',
                'order_index' => 5,
            ],
            [
                'id' => '019d4444-4444-4444-4444-444444444444',
                'name' => 'Buchhaltung',
                'description' => 'Buchhaltung, Belege, Fixkosten, Steuern sowie gesicherter Rohling- und Verpackungs-Einkauf',
                'icon' => 'banknotes',
                'color' => 'emerald-500',
                'order_index' => 6,
            ],
            [
                'id' => '019d5555-5555-5555-5555-555555555555',
                'name' => 'System',
                'description' => 'Server-Überwachung, 0-Token-Selbstheilung, Backups, Cronjobs und Antigravity-Entwicklung',
                'icon' => 'server',
                'color' => 'red-500',
                'order_index' => 7,
            ],
        ];

        $canonicalByName = [];
        foreach ($canonicalDepartments as $deptData) {
            $dept = AiDepartment::updateOrCreate(
                ['id' => $deptData['id']],
                [
                    'name' => $deptData['name'],
                    'description' => $deptData['description'],
                    'icon' => $deptData['icon'],
                    'color' => $deptData['color'],
                    'order_index' => $deptData['order_index'],
                ]
            );
            $canonicalByName[$deptData['name']] = $dept;

            // Bereinige etwaige Dubletten mit gleichem Abteilungsnamen aus älteren UUID-Läufen
            $duplicateIds = AiDepartment::where('name', $deptData['name'])
                ->where('id', '!=', $dept->id)
                ->pluck('id')
                ->all();

            if (!empty($duplicateIds)) {
                foreach (['ai_agents', 'ai_roles', 'ai_universe_tickets', 'ai_learned_preferences'] as $fkTable) {
                    if (Schema::hasTable($fkTable) && Schema::hasColumn($fkTable, 'ai_department_id')) {
                        DB::table($fkTable)->whereIn('ai_department_id', $duplicateIds)->update([
                            'ai_department_id' => $dept->id,
                        ]);
                    }
                }
                AiDepartment::whereIn('id', $duplicateIds)->delete();
            }
        }

        // Entferne veraltete Falcone/Falcon/Agenti-Einträge und löse die alte Meta-Abteilung Agenten sauber auf
        AiAgent::whereIn('name', ['Falcone', 'Falcon', 'Agenti'])->delete();

        $oldAgentDepts = AiDepartment::where('name', 'Agenten')->pluck('id')->all();
        if (!empty($oldAgentDepts)) {
            AiAgent::whereIn('ai_department_id', $oldAgentDepts)->update([
                'ai_department_id' => $canonicalByName['Leitung']->id,
            ]);
            if (Schema::hasTable('ai_universe_tickets')) {
                DB::table('ai_universe_tickets')->whereIn('ai_department_id', $oldAgentDepts)->update([
                    'ai_department_id' => $canonicalByName['System']->id,
                ]);
            }
            AiDepartment::whereIn('id', $oldAgentDepts)->delete();
        }

        // Ordne alle 12 aktiven Einheiten den 7 sauberen Fachabteilungen zu
        $assignments = [
            'Funkira' => $canonicalByName['Leitung']->id,
            'Dr. Funki' => $canonicalByName['Leitung']->id,
            'Globi' => $canonicalByName['Leitung']->id,
            'Funki' => $canonicalByName['Support']->id,
            'Produkti' => $canonicalByName['Produkte']->id,
            'Lasi' => $canonicalByName['Produkte']->id,
            'Marketi' => $canonicalByName['Marketing']->id,
            'Bestelli' => $canonicalByName['Bestellungen']->id,
            'Buchi' => $canonicalByName['Buchhaltung']->id,
            'Einkaufi' => $canonicalByName['Buchhaltung']->id,
            'Systemi' => $canonicalByName['System']->id,
            'Antigravity' => $canonicalByName['System']->id,
        ];

        foreach ($assignments as $agentName => $departmentId) {
            AiAgent::where('name', $agentName)->update(['ai_department_id' => $departmentId]);
        }
    }
}
