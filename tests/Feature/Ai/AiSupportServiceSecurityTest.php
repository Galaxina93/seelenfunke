<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\OrderOrder;
use App\Models\Customer\Customer;
use App\Models\Admin\Admin;
use App\Services\AI\AiSupportService;

class AiSupportServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private AiSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AiSupportService();
    }

    public function test_missing_receipt_detects_empty_array_and_only_business_issues(): void
    {
        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'email' => 'admin_test@example.com',
            'password' => bcrypt('password')
        ]);

        // Private issue without receipt -> should NOT trigger business receipt missing warning
        AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Zahnarztbesuch privat',
            'amount' => -150.00,
            'is_business' => false,
            'execution_date' => now()->toDateString(),
            'file_paths' => []
        ]);

        $cmd1 = $this->service->getUltimateCommand();
        $rec1 = $cmd1['recommendation'] ?? null;
        $this->assertNotEquals('Beleg fehlt', $rec1['title'] ?? '');

        // Business issue with empty array receipt -> MUST trigger missing receipt warning
        AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Lasermaterial Acryl',
            'amount' => -320.00,
            'is_business' => true,
            'execution_date' => now()->toDateString(),
            'file_paths' => []
        ]);

        $cmd2 = $this->service->getUltimateCommand();
        $hasMissingOption = collect(array_merge([$cmd2['recommendation']], $cmd2['alternatives']))->contains('title', 'Beleg fehlt');
        $this->assertTrue($hasMissingOption);
    }

    public function test_financial_status_excludes_cancelled_and_refunded_orders(): void
    {
        $customer = Customer::factory()->create();

        // Valid paid order
        OrderOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-PAID-1',
            'email' => 'customer@example.com',
            'customer_email' => 'customer@example.com',
            'status' => 'processing',
            'subtotal_price' => 10000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 10000, // 100.00 EUR
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => [],
            'created_at' => now()
        ]);

        // Cancelled order that had payment_status = paid
        OrderOrder::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CANCELLED-1',
            'email' => 'customer@example.com',
            'customer_email' => 'customer@example.com',
            'status' => 'cancelled',
            'subtotal_price' => 20000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'total_price' => 20000, // 200.00 EUR
            'payment_status' => 'paid',
            'billing_address' => [],
            'shipping_address' => [],
            'created_at' => now()
        ]);

        $result = $this->service->getUltimateCommand();
        $fin = $result['financial_status'];

        // Only the valid processing order (100.00 EUR) should be included, not the cancelled one
        $this->assertEquals(100.00, $fin['month_actual_sales_eur']);
        $this->assertEquals(1, $fin['month_paid_orders_count']);
        $this->assertEquals(100.00, $fin['total_recorded_sales_eur']);
    }
}
