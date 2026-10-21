<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Product\Product;
use App\Models\Product\ProductPackaging;
use App\Models\Product\ProductLoss;
use App\Services\AI\Functions\AiProductFuncs;

class AiProductFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $mockClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClass = new class {
            use AiProductFuncs;
        };
    }

    public function test_report_loss_rejects_negative_or_zero_quantity_preventing_stock_inflation(): void
    {
        $product = Product::create([
            'name' => 'Kerzenhalter Eiche',
            'slug' => 'kerzenhalter-eiche',
            'price' => 2500,
            'quantity' => 10,
            'track_quantity' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        // Attempting to report negative loss (which without validation would increment stock)
        $resNegative = $this->mockClass::executeReportLoss([
            'product_id' => (string)$product->id,
            'quantity' => -5,
            'reason' => 'Bruch'
        ]);

        $this->assertEquals('error', $resNegative['status']);
        $product->refresh();
        $this->assertEquals(10, $product->quantity);

        // Attempting zero quantity
        $resZero = $this->mockClass::executeReportLoss([
            'product_id' => (string)$product->id,
            'quantity' => 0,
            'reason' => 'Bruch'
        ]);

        $this->assertEquals('error', $resZero['status']);
        $product->refresh();
        $this->assertEquals(10, $product->quantity);
    }

    public function test_product_draft_update_rejects_negative_prices_and_stock(): void
    {
        $product = Product::create([
            'name' => 'Wandbild Lebensbaum',
            'slug' => 'wandbild-lebensbaum',
            'price' => 4500,
            'quantity' => 5,
            'track_quantity' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $resNegativePrice = $this->mockClass::executeProductDraftUpdate([
            'product_id' => (string)$product->id,
            'price_eur' => -10.50
        ]);
        $this->assertEquals('error', $resNegativePrice['status']);

        $resNegativeQty = $this->mockClass::executeProductDraftUpdate([
            'product_id' => (string)$product->id,
            'quantity' => -1
        ]);
        $this->assertEquals('error', $resNegativeQty['status']);

        $product->refresh();
        $this->assertEquals(4500, $product->price);
        $this->assertEquals(5, $product->quantity);
    }

    public function test_crawler_whitelists_allowed_platforms(): void
    {
        $resInvalid = $this->mockClass::executeRunCrawler([
            'keyword' => 'laser holz',
            'platforms' => ['MaliciousSite', 'DarknetMarket']
        ]);

        $this->assertEquals('error', $resInvalid['status']);
        $this->assertStringContainsString('Keine gültigen Plattformen', $resInvalid['message']);
    }

    public function test_packaging_add_material_rejects_negative_or_zero_weight(): void
    {
        $product = Product::create([
            'name' => 'Gravierte Schatulle',
            'slug' => 'gravierte-schatulle',
            'price' => 3000,
            'quantity' => 20,
            'track_quantity' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $res = $this->mockClass::executeAddMaterial([
            'product_id' => $product->id,
            'material_type' => 'Kartonage',
            'weight_grams' => -50
        ]);

        $this->assertEquals('error', $res['status']);
        $this->assertDatabaseMissing('product_packagings', [
            'product_id' => $product->id
        ]);
    }
}
