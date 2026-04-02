<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingInvoice;
use App\Models\System\SystemUser;
use Illuminate\Support\Facades\Route;
use App\Services\InvoiceService;
use Mockery;

class CustomerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_download_their_own_invoice()
    {
        // Prevent actual PDF generation overhead during tests
        $mock = Mockery::mock(InvoiceService::class);
        $mock->shouldReceive('generatePdf')
             ->andReturnSelf();
        
        $mock->shouldReceive('download')
             ->andReturn(response('PDF_CONTENT', 200, ['content-type' => 'application/pdf']));

        $this->app->instance(InvoiceService::class, $mock);

        $customer = Customer::factory()->create();
        
        $invoice = AccountingInvoice::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'invoice',
            'invoice_number' => 'INV-TEST-123',
            'status' => 'open',
        ]);

        $response = $this->actingAs($customer, 'customer')
             ->get("/invoice/{$invoice->id}/download");

        $response->assertStatus(200);
    }

    public function test_customer_cannot_download_other_customer_invoice()
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        
        $invoice = AccountingInvoice::factory()->create([
            'customer_id' => $owner->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($intruder, 'customer')
             ->get("/invoice/{$invoice->id}/download");

        $response->assertStatus(403);
    }

    public function test_admin_can_download_any_invoice()
    {
        $mock = Mockery::mock(InvoiceService::class);
        $mock->shouldReceive('generatePdf')->andReturnSelf();
        $mock->shouldReceive('download')->andReturn(response('PDF_CONTENT', 200, ['content-type' => 'application/pdf']));
        $this->app->instance(InvoiceService::class, $mock);

        $customer = Customer::factory()->create();
        $admin = SystemUser::factory()->create();
        
        $invoice = AccountingInvoice::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'open'
        ]);

        $response = $this->actingAs($admin, 'admin') // admin auth
             ->get("/invoice/{$invoice->id}/download");

        $response->assertStatus(200);
    }
}
