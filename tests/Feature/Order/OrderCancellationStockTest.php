<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderCancellationStockTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function standard_product_stock_is_restored_on_cancellation(): void
    {
        $admin = Admin::factory()->create();

        $standardProduct = Product::create([
            'name' => 'Standard Holzkerze',
            'slug' => 'standard-holzkerze',
            'price' => 2000,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => false,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-TEST-STD-01',
            'email' => 'kunde@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'total_price' => 4760,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $standardProduct->id,
            'product_name' => $standardProduct->name,
            'quantity' => 2,
            'unit_price' => 2000,
            'total_price' => 4000,
            'configuration' => null,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->set('status', 'cancelled')
            ->set('cancellationReason', 'Kunde hat sich umentschieden.')
            ->call('saveStatus')
            ->assertHasNoErrors();

        $this->assertEquals(12, $standardProduct->fresh()->quantity);
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function personalized_laser_product_stock_is_never_restored_on_cancellation(): void
    {
        $admin = Admin::factory()->create();

        $laserProduct = Product::create([
            'name' => 'Gelasertes Holzbrett mit Gravur',
            'slug' => 'gelasertes-holzbrett-mit-gravur',
            'price' => 3500,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-TEST-LASER-02',
            'email' => 'kunde2@example.com',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 7000,
            'tax_amount' => 1330,
            'total_price' => 8330,
            'billing_address' => ['first_name' => 'Erika', 'last_name' => 'Musterfrau'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $laserProduct->id,
            'product_name' => $laserProduct->name,
            'quantity' => 2,
            'unit_price' => 3500,
            'total_price' => 7000,
            'configuration' => [
                'engraving_text' => 'Für immer zusammen - Anna & Tom',
                'font' => 'Lobster',
            ],
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->set('status', 'cancelled')
            ->set('cancellationReason', 'Bereits gelasert, Kunde möchte Stornierung.')
            ->call('saveStatus')
            ->assertHasNoErrors();

        // Stock MUST stay at 10, because personalized goods are scrap and must NOT be restored to stock!
        $this->assertEquals(10, $laserProduct->fresh()->quantity);
        $this->assertEquals('cancelled', $order->fresh()->status);
    }
}
