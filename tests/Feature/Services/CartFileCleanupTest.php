<?php

namespace Tests\Feature\Services;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Services\CartFileCleanupService;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartFileCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->product = Product::create([
            'id' => (string) Str::uuid(),
            'sku' => 'TEST-CLEANUP-01',
            'name' => 'Cleanup Test Product',
            'slug' => 'cleanup-test-product',
            'price' => 2500,
            'status' => 'active',
            'type' => 'physical',
            'quantity' => 20,
            'track_quantity' => true,
            'continue_selling_when_out_of_stock' => true,
            'purchase_price' => 500,
            'electricity_wear_factor' => 0,
            'delivery_time_days' => 2,
            'completion_step' => 1,
        ]);
    }

    public function test_remove_item_immediately_deletes_associated_upload_and_snapshot(): void
    {
        $uploadFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_proc.png';
        $snapshotFile = 'system/snapshots/' . Str::uuid() . '_front.jpg';

        Storage::disk('public')->put($uploadFile, 'fake upload content');
        Storage::disk('public')->put($snapshotFile, 'fake snapshot content');

        $this->assertTrue(Storage::disk('public')->exists($uploadFile));
        $this->assertTrue(Storage::disk('public')->exists($snapshotFile));

        $cart = Cart::create(['session_id' => Session::getId()]);
        $item = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => [
                'files' => [$uploadFile],
                'snapshot_path' => $snapshotFile,
            ],
        ]);

        $cartService = app(CartService::class);
        $cartService->removeItem($item->id);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        $this->assertFalse(Storage::disk('public')->exists($uploadFile), 'Upload file should be deleted upon item removal');
        $this->assertFalse(Storage::disk('public')->exists($snapshotFile), 'Snapshot file should be deleted upon item removal');
    }

    public function test_empty_cart_deletes_all_associated_files(): void
    {
        $file1 = 'bestellungen/cart-uploads/' . Str::uuid() . '_proc1.png';
        $file2 = 'bestellungen/cart-uploads/' . Str::uuid() . '_proc2.png';

        Storage::disk('public')->put($file1, 'file 1 content');
        Storage::disk('public')->put($file2, 'file 2 content');

        $cart = Cart::create(['session_id' => Session::getId()]);
        CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$file1]],
        ]);
        CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$file2]],
        ]);

        $cartService = app(CartService::class);
        $cartService->emptyCart();

        $this->assertFalse(Storage::disk('public')->exists($file1));
        $this->assertFalse(Storage::disk('public')->exists($file2));
    }

    public function test_remove_item_does_not_delete_file_if_shared_with_another_cart_item(): void
    {
        $sharedFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_shared.png';
        Storage::disk('public')->put($sharedFile, 'shared content');

        $cart = Cart::create(['session_id' => Session::getId()]);
        $item1 = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$sharedFile]],
        ]);
        $item2 = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$sharedFile]],
        ]);

        $cartService = app(CartService::class);
        $cartService->removeItem($item1->id);

        $this->assertTrue(Storage::disk('public')->exists($sharedFile), 'Shared file must NOT be deleted while item2 still uses it');
    }

    public function test_remove_item_does_not_delete_file_if_referenced_in_order_or_quote(): void
    {
        $orderedFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_ordered.png';
        $quotedFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_quoted.png';

        Storage::disk('public')->put($orderedFile, 'ordered content');
        Storage::disk('public')->put($quotedFile, 'quoted content');

        // Create Order referencing orderedFile
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-001',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'email' => 'test@example.com',
            'subtotal_price' => 2500,
            'tax_amount' => 475,
            'total_price' => 2975,
            'billing_address' => ['first_name' => 'Max'],
            'shipping_address' => ['first_name' => 'Max'],
        ]);
        OrderOrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$orderedFile]],
        ]);

        // Create Quote referencing quotedFile
        $quote = OrderQuoteRequest::create([
            'id' => (string) Str::uuid(),
            'quote_number' => 'QUO-TEST-001',
            'status' => 'open',
            'email' => 'quote@example.com',
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'net_total' => 2500,
            'tax_total' => 475,
            'gross_total' => 2975,
        ]);
        OrderQuoteRequestItem::create([
            'id' => (string) Str::uuid(),
            'quote_request_id' => $quote->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$quotedFile]],
        ]);

        // Create Cart items referencing the same files
        $cart = Cart::create(['session_id' => Session::getId()]);
        $item1 = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$orderedFile]],
        ]);
        $item2 = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$quotedFile]],
        ]);

        $cartService = app(CartService::class);
        $cartService->removeItem($item1->id);
        $cartService->removeItem($item2->id);

        $this->assertTrue(Storage::disk('public')->exists($orderedFile), 'File referenced in placed order must NOT be deleted');
        $this->assertTrue(Storage::disk('public')->exists($quotedFile), 'File referenced in quote must NOT be deleted');
    }

    public function test_cleanup_orphaned_files_service_deletes_only_unreferenced_files_older_than_threshold(): void
    {
        $cleanupService = app(CartFileCleanupService::class);

        $oldOrphan = 'bestellungen/cart-uploads/' . Str::uuid() . '_old_orphan.png';
        $newOrphan = 'bestellungen/cart-uploads/' . Str::uuid() . '_new_orphan.png';
        $activeCartFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_active_cart.png';

        Storage::disk('public')->put($oldOrphan, 'old orphan content');
        Storage::disk('public')->put($newOrphan, 'new orphan content');
        Storage::disk('public')->put($activeCartFile, 'active cart content');

        // Put activeCartFile in an active cart
        $cart = Cart::create(['session_id' => 'active_session_123']);
        CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$activeCartFile]],
        ]);

        // Mock file modification times
        // Note: Storage::fake doesn't easily allow setting mtime directly via API,
        // so cleanupOrphanedFiles accepts $olderThanHours = 0 to simulate age threshold
        $result = $cleanupService->cleanupOrphanedFiles(olderThanHours: 0, dryRun: false);

        $this->assertFalse(Storage::disk('public')->exists($oldOrphan), 'Old orphan should be deleted');
        $this->assertFalse(Storage::disk('public')->exists($newOrphan), 'New orphan should be deleted when threshold is 0');
        $this->assertTrue(Storage::disk('public')->exists($activeCartFile), 'File in active cart must NOT be deleted');
        $this->assertGreaterThanOrEqual(2, $result['deleted']);
        $this->assertGreaterThanOrEqual(1, $result['skipped_active']);
    }

    public function test_cleanup_orphaned_uploads_command_runs_successfully(): void
    {
        $orphan = 'system/snapshots/' . Str::uuid() . '_snap.jpg';
        Storage::disk('public')->put($orphan, 'snap content');

        // Run dry-run
        Artisan::call('shop:cleanup-orphaned-uploads', [
            '--hours' => 0,
            '--dry-run' => true,
        ]);

        $this->assertTrue(Storage::disk('public')->exists($orphan), 'Dry-run must not delete file');

        // Run live command
        Artisan::call('shop:cleanup-orphaned-uploads', [
            '--hours' => 0,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($orphan), 'Live run must delete orphaned snapshot');
    }

    public function test_cleanup_abandoned_carts_deletes_old_carts_and_preserves_new_ones(): void
    {
        $cleanupService = app(CartFileCleanupService::class);

        $oldCartFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_abandoned.png';
        $freshCartFile = 'bestellungen/cart-uploads/' . Str::uuid() . '_fresh.png';

        Storage::disk('public')->put($oldCartFile, 'abandoned content');
        Storage::disk('public')->put($freshCartFile, 'fresh content');

        // Abandoned cart: 20 days old
        $oldCart = Cart::create(['session_id' => 'abandoned_session']);
        $oldItem = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $oldCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$oldCartFile]],
        ]);
        Cart::where('id', $oldCart->id)->update(['updated_at' => now()->subDays(20)]);

        // Fresh cart: 2 days old
        $freshCart = Cart::create(['session_id' => 'fresh_session']);
        $freshItem = CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $freshCart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 2500,
            'total_price' => 2500,
            'configuration' => ['files' => [$freshCartFile]],
        ]);
        Cart::where('id', $freshCart->id)->update(['updated_at' => now()->subDays(2)]);

        $deletedCarts = $cleanupService->cleanupAbandonedCarts(olderThanDays: 14);

        $this->assertEquals(1, $deletedCarts);
        $this->assertDatabaseMissing('carts', ['id' => $oldCart->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $oldItem->id]);
        $this->assertFalse(Storage::disk('public')->exists($oldCartFile));

        $this->assertDatabaseHas('carts', ['id' => $freshCart->id]);
        $this->assertDatabaseHas('cart_items', ['id' => $freshItem->id]);
        $this->assertTrue(Storage::disk('public')->exists($freshCartFile));
    }

    public function test_is_managed_path_blocks_directory_traversal_and_unmanaged_folders(): void
    {
        $cleanupService = app(CartFileCleanupService::class);

        $this->assertTrue($cleanupService->isManagedPath('bestellungen/cart-uploads/abc.png'));
        $this->assertTrue($cleanupService->isManagedPath('system/snapshots/front.jpg'));
        $this->assertTrue($cleanupService->isManagedPath('snapshots/front.jpg'));

        $this->assertFalse($cleanupService->isManagedPath('../../etc/passwd'));
        $this->assertFalse($cleanupService->isManagedPath('bestellungen/cart-uploads/../secret.txt'));
        $this->assertFalse($cleanupService->isManagedPath('invoices/rechnung.pdf'));
        $this->assertFalse($cleanupService->isManagedPath('system/users/avatar.jpg'));
    }
}
