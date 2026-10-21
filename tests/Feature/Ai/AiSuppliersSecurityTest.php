<?php

namespace Tests\Feature\Ai;

use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use App\Services\AI\Functions\AiSuppliersFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiSuppliersSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_create_and_update_clamp_lead_times_and_whitelist_shipping()
    {
        $dummyClass = new class {
            use AiSuppliersFuncs;
        };

        // Create with negative lead time and invalid shipping method
        $resCreate = $dummyClass::executeSupplierCreate([
            'name' => 'Holzlieferant Nord',
            'lead_time_land_days' => -10,
            'shipping_method' => 'teleportation',
        ]);

        $this->assertEquals('success', $resCreate['status']);

        $supplier = ProductSupplier::first();
        $this->assertNotNull($supplier);
        $this->assertEquals(0, $supplier->lead_time_land_days);
        $this->assertEquals('land', $supplier->shipping_method); // Fallback to 'land'

        // Update with valid values
        $resUpdate = $dummyClass::executeSupplierUpdate([
            'product_supplier_id' => $supplier->id,
            'lead_time_sea_days' => 45,
            'shipping_method' => 'sea',
        ]);

        $this->assertEquals('success', $resUpdate['status']);
        $supplier->refresh();
        $this->assertEquals(45, $supplier->lead_time_sea_days);
        $this->assertEquals('sea', $supplier->shipping_method);
    }

    public function test_supplier_delete_blocks_deletion_when_linked_to_products()
    {
        $dummyClass = new class {
            use AiSuppliersFuncs;
        };

        $supplier = ProductSupplier::create([
            'name' => 'Wachs-Manufaktur Bayern',
            'shipping_method' => 'land',
        ]);

        // Link product to supplier
        Product::create([
            'name' => 'Bienenwachskerze Premium',
            'slug' => 'wachs-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
            'quantity' => 15,
            'track_quantity' => true,
            'product_supplier_id' => $supplier->id,
        ]);

        // Deletion must be blocked due to integrity constraints
        $resDelete = $dummyClass::executeSupplierDelete([
            'product_supplier_id' => $supplier->id,
        ]);

        $this->assertEquals('error', $resDelete['status']);
        $this->assertDatabaseHas('product_suppliers', ['id' => $supplier->id]);

        // Disassociate product and retry
        Product::where('product_supplier_id', $supplier->id)->update(['product_supplier_id' => null]);

        $resDeleteRetry = $dummyClass::executeSupplierDelete([
            'product_supplier_id' => $supplier->id,
        ]);

        $this->assertEquals('success', $resDeleteRetry['status']);
        $this->assertDatabaseMissing('product_suppliers', ['id' => $supplier->id]);
    }
}
