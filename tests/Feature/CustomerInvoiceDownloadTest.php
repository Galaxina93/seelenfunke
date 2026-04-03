<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingInvoice;
use Livewire\Livewire;

class CustomerInvoiceDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_payload_for_zip_download_is_generated_correctly()
    {
        $customer = Customer::factory()->create();
        
        $invoice1 = AccountingInvoice::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'invoice',
            'status' => 'open',
            'invoice_number' => 'INV-001'
        ]);

        $invoice2 = AccountingInvoice::factory()->create([
            'customer_id' => $customer->id,
            'type' => 'credit_note',
            'status' => 'open',
            'invoice_number' => 'CRED-002'
        ]);

        // Test component rendering
        Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerInvoicesComponent::class)
            ->assertStatus(200)
            ->assertViewHas('invoicesPayload')
            ->assertSee('Rechnung_INV-001.pdf')
            ->assertSee('Gutschrift_CRED-002.pdf')
            ->assertSee(route('invoice.download', $invoice1->id))
            ->assertSee(route('invoice.download', $invoice2->id));
    }
}
