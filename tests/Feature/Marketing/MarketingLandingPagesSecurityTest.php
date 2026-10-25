<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingLandingPages;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingLandingPagesSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_marketing_landing_pages(): void
    {
        Livewire::test(MarketingLandingPages::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_marketing_landing_pages(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(MarketingLandingPages::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_marketing_landing_pages(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Landing',
            'last_name' => 'Manager',
            'email' => 'employee.landing@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingLandingPages::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(MarketingLandingPages::class)
            ->assertStatus(200);
    }

    public function test_auto_syncs_new_products_and_supports_copy_regeneration(): void
    {
        $admin = Admin::factory()->create();

        $product = \App\Models\Product\Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Neues Kristall Glas',
            'slug' => 'neues-kristall-glas',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 3990,
            'status' => 'active',
            'description' => 'Edles K9-Kristallglas aus der Manufaktur.',
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingLandingPages::class)
            ->assertStatus(200)
            ->assertSee('Neues Kristall Glas')
            ->call('setLayoutMode', 'list')
            ->assertSet('layoutMode', 'list');

        $lp = \App\Models\Marketing\MarketingLandingPage::where('product_id', $product->id)->first();
        $this->assertNotNull($lp);
        $this->assertSame('active', $lp->status);
        $this->assertStringContainsString('Neues Kristall Glas', (string) $lp->headline);
    }

    public function test_landing_page_dynamically_reflects_shop_settings_and_delivery_logic(): void
    {
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'company_city'], ['value' => 'Braunschweig']);
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Seelenfunke Atelier']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        \App\Models\Delivery\DeliveryTime::query()->update(['is_active' => false]);
        \App\Models\Delivery\DeliveryTime::create([
            'name' => 'Hochsaison',
            'min_days' => 6,
            'max_days' => 9,
            'description' => 'Individuelle Fertigung in der Hochsaison.',
            'color' => 'yellow',
            'is_active' => true,
        ]);

        $product = \App\Models\Product\Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Der Feierabend-Funke',
            'slug' => 'weizenglas-personalisiert',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'track_quantity' => true,
            'quantity' => 25,
            'price' => 1990,
            'status' => 'active',
            'attributes' => [
                'Material' => 'Kristallglas',
                'Lieferzeit' => '1-2 Werktage',
            ],
        ]);

        \App\Models\Marketing\MarketingLandingPage::syncForProduct($product, true);

        $response = $this->get('/l/weizenglas-personalisiert');
        $response->assertStatus(200);
        $response->assertSee('Manufaktur Braunschweig');
        $response->assertSee('Handveredelt in Braunschweig');
        $response->assertSee('Seelenfunke Atelier');
        $response->assertSee('6-9 Werktage');
        $response->assertSee('cta_wertschaetzung.webp');
    }
}
