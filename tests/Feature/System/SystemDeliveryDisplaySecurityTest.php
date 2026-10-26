<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemDeliveryDisplay;
use App\Models\Customer\Customer;
use App\Models\Delivery\DeliveryFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemDeliveryDisplaySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_submit_delivery_feedback(): void
    {
        Livewire::test(SystemDeliveryDisplay::class)
            ->call('sendFeedback', 'vacation');

        $this->assertEquals(0, DeliveryFeedback::count());
    }

    public function test_customer_cannot_submit_invalid_feedback_type(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(SystemDeliveryDisplay::class)
            ->call('sendFeedback', 'malicious_type_injection');

        $this->assertEquals(0, DeliveryFeedback::count());
    }

    public function test_customer_can_submit_valid_feedback(): void
    {
        \App\Models\Delivery\DeliverySetting::query()->delete();
        \App\Models\Delivery\DeliverySetting::create([
            'is_vacation_mode' => true,
            'is_sick_mode' => false,
        ]);

        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(SystemDeliveryDisplay::class)
            ->call('sendFeedback', 'vacation');

        $this->assertEquals(1, DeliveryFeedback::count());
        $this->assertEquals('vacation', DeliveryFeedback::first()->type);
    }
}
