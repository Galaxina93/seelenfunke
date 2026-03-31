<?php

namespace Tests\Feature\Livewire\Shop\Master;

use Tests\TestCase;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDashboardLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_working_endpoints_for_all_dashboard_warnings()
    {
        // Session table is now managed tightly via parent RefreshDatabase.

        $adminId = \Illuminate\Support\Str::uuid()->toString();
        \Illuminate\Support\Facades\DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test_' . uniqid() . '@admin.com',
            'password' => bcrypt('password')
        ]);
        $admin = Admin::find($adminId);

        $routesToTest = [
            '/admin/products',
            '/admin/financial-variable-costs',
            '/admin/financial-fix-costs',
            '/admin/orders',
            '/admin/support-tickets',
            '/admin/reviews',
            '/admin/credit-management',
            '/admin/financial-banks',
            '/admin/tasks',
            '/admin/quote-requests',
            '/admin/widerruf',
            '/admin/product-fracture',
        ];

        foreach ($routesToTest as $route) {
            $response = $this->actingAs($admin, 'admin')->get($route);
            $error = $response->exception ? $response->exception->getMessage() : '';
            $this->assertTrue(in_array($response->getStatusCode(), [200, 302]), "Fehlgeschlagen bei URL: {$route} | Status: " . $response->getStatusCode() . " | Error: " . $error);
        }
    }
}
