<?php

namespace Tests\Feature\Product;

use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReviewSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createCustomerWithPaidOrder(Product $product): Customer
    {
        $customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => bcrypt('password123'),
        ]);
        $customer->profile()->create([
            'email_verified_at' => now(),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-REV-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'total_price' => 5000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'product_name' => $product->name,
        ]);

        return $customer;
    }

    public function test_new_review_ignores_client_injected_existing_media(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('system/important_file.txt', 'secret');

        $product = Product::create([
            'name' => 'Test Produkt',
            'slug' => 'test-produkt',
            'price' => 2000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $customer = $this->createCustomerWithPaidOrder($product);
        Auth::guard('customer')->login($customer);

        $component = Livewire::test(\App\Livewire\Shop\Product\ProductReviews::class, ['product' => $product])
            ->set('rating', 5)
            ->set('title', 'Toller Artikel')
            ->set('content', 'Dies ist eine sehr gute Bewertung für das Produkt!');

        try {
            $component->set('existingMedia', ['system/important_file.txt']);
        } catch (\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException $e) {
            // Expected when existingMedia is #[Locked]
        }

        $component->call('submitReview')
            ->assertHasNoErrors();

        $review = ProductReview::where('product_id', $product->id)->first();
        $this->assertNotNull($review);
        // The injected existingMedia must NOT be saved in the review media array!
        $this->assertEmpty($review->media);
    }

    public function test_delete_review_only_removes_files_within_reviews_directory(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('system/critical.jpg', 'system_content');
        Storage::disk('public')->put('produkte/reviews/safe_review_photo.jpg', 'review_content');

        $product = Product::create([
            'name' => 'Test Produkt 2',
            'slug' => 'test-produkt-2',
            'price' => 2000,
            'type' => 'physical',
            'status' => 'active',
        ]);

        $customer = $this->createCustomerWithPaidOrder($product);
        Auth::guard('customer')->login($customer);

        // Create review with one safe review photo and one unauthorized system path
        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'title' => 'Super',
            'content' => 'Sehr zufrieden mit der Lieferung!',
            'media' => [
                'produkte/reviews/safe_review_photo.jpg',
                'system/critical.jpg',
            ],
            'status' => 'approved',
        ]);

        Livewire::test(\App\Livewire\Shop\Product\ProductReviews::class, ['product' => $product])
            ->call('deleteReview')
            ->assertHasNoErrors();

        // The safe review photo should be deleted
        Storage::disk('public')->assertMissing('produkte/reviews/safe_review_photo.jpg');
        // The unauthorized system file must NOT be deleted!
        Storage::disk('public')->assertExists('system/critical.jpg');
    }
}
