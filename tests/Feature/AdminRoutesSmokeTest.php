<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRoutesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin()
    {
        $adminId = Str::uuid()->toString();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'General',
            'last_name' => 'Smoke Tester',
            'email' => 'smoke@seelenfunke.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return Admin::find($adminId);
    }

    public function test_all_admin_dashboard_pages_render_without_fatal_errors()
    {
        // Setup initialer KI-Agenten, falls Komponenten diese (wie SystemLogs) im render() hart abfragen
        if (!\App\Models\Ai\AiAgent::where('name', 'Funkira')->exists()) {
            \App\Models\Ai\AiAgent::create([
                'name' => 'Funkira',
                'role_description' => 'CEO Agent',
                'system_prompt' => 'Du bist Funkira',
                'is_active' => true,
                'model' => 'gpt-4o',
                'color' => 'cyan-500',
                'icon' => 'sparkles'
            ]);
        }

        $admin = $this->createAdmin();

        foreach (self::adminRoutesProvider() as $param) {
            $url = $param[0];
            $response = $this->actingAs($admin, 'admin')->get($url);
            
            // Ein erfolgreicher Status (200 OK) bedeutet, dass die gesamte Blade+Livewire-Logik fehlerfrei kompiliert wurde.
            $response->assertSuccessful();
        }
    }

    public static function adminRoutesProvider()
    {
        // Ausschluss aller dynamischen "{id}"-Routen oder Datei-Exports, die spezielle DB-Einträge erfordern würden.
        return [
            ['/admin/dashboard'],
            ['/admin/routine'],
            ['/admin/tasks'],
            ['/admin/calender'],
            ['/admin/company-map'],
            ['/admin/tickets'],
            ['/admin/ai-analytics'],
            ['/admin/ceo/gesundheit'],
            ['/admin/global-logs'],
            ['/admin/ai-genui'],
            ['/admin/person-profiles'],
            ['/admin/rollen'],
            ['/admin/agenten'],
            ['/admin/organigramm'],
            ['/admin/ai-chat'],
            ['/admin/ai-knowledge_base'],
            ['/admin/system-info'],
            ['/admin/user-management'],
            ['/admin/products'],
            ['/admin/product-analytics'],
            ['/admin/product-packaging'],
            ['/admin/product-fracture'],
            ['/admin/product-suppliers'],
            ['/admin/products/nischen-scout'],
            ['/admin/product-templates'],
            ['/admin/reviews'],
            ['/admin/invoices'],
            ['/admin/credit-management'],
            ['/admin/orders'],
            ['/admin/quote-requests'],
            ['/admin/widerruf'],
            ['/admin/financial-evaluation'],
            ['/admin/financial-liquidity-planning'],
            ['/admin/financial-banks'],
            ['/admin/financial-fix-costs'],
            ['/admin/financial-variable-costs'],
            ['/admin/financial-tax'],
            ['/admin/configuration'],
            ['/admin/blog'],
            ['/admin/voucher'],
            ['/admin/newsletter'],
            ['/admin/inbox'],
        ];
    }
}
