<?php

namespace Tests\Feature\Cart;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\CartFileCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartFileCleanupServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_managed_path_handles_slashes_and_blocks_traversal()
    {
        $service = new CartFileCleanupService();

        // Valid paths
        $this->assertTrue($service->isManagedPath('bestellungen/cart-uploads/upload123.png'));
        $this->assertTrue($service->isManagedPath('system/snapshots/snap123.jpg'));
        $this->assertTrue($service->isManagedPath('snapshots/preview.png'));

        // Windows backslash normalization
        $this->assertTrue($service->isManagedPath('bestellungen\\cart-uploads\\upload123.png'));

        // Traversal attempts blocked
        $this->assertFalse($service->isManagedPath('bestellungen/cart-uploads/../../etc/passwd'));
        $this->assertFalse($service->isManagedPath('../system/snapshots/file.png'));
        $this->assertFalse($service->isManagedPath('unauthorized/folder/file.png'));
    }

    public function test_reference_checks_escape_sql_wildcards()
    {
        $service = new CartFileCleanupService();

        $product = Product::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Personalized Board',
            'slug' => 'personalized-board-' . uniqid(),
            'price' => 2990,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-CLEAN-001',
            'email' => 'test@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        // Create an order item whose configuration contains "file_one.png"
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
            'configuration' => ['files' => ['bestellungen/cart-uploads/file_one.png']],
        ]);

        // A query for "fileXone.png" should NOT match even if "_" is normally a wildcard in SQL LIKE
        $this->assertFalse($service->isFileUsedInOrders('fileXone.png'));

        // A query for "file%one.png" should NOT match
        $this->assertFalse($service->isFileUsedInOrders('file%one.png'));

        // The exact basename should match
        $this->assertTrue($service->isFileUsedInOrders('file_one.png'));
    }

    public function test_cleanup_abandoned_carts_deletes_only_expired_carts()
    {
        Storage::fake('public');
        $service = new CartFileCleanupService();

        $product = Product::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Standard Item',
            'slug' => 'standard-item-' . uniqid(),
            'price' => 1500,
        ]);

        // Expired cart (15 days old)
        $oldCart = Cart::create([
            'session_id' => 'old_session_123',
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(15),
        ]);
        $oldItem = CartItem::create([
            'cart_id' => $oldCart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1500,
            'configuration' => ['files' => ['bestellungen/cart-uploads/old.png']],
        ]);

        // CartItem touch updates cart updated_at; force back to 15 days ago
        \Illuminate\Support\Facades\DB::table('carts')
            ->where('id', $oldCart->id)
            ->update(['updated_at' => now()->subDays(15)]);

        // Active cart (1 day old)
        $activeCart = Cart::create([
            'session_id' => 'active_session_456',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $activeItem = CartItem::create([
            'cart_id' => $activeCart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 2000,
            'configuration' => ['files' => ['bestellungen/cart-uploads/active.png']],
        ]);

        $deletedCount = $service->cleanupAbandonedCarts(14);

        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('carts', ['id' => $oldCart->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $oldItem->id]);
        $this->assertDatabaseHas('carts', ['id' => $activeCart->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $activeItem->id]);
    }
}
