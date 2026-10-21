<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingCredit;
use App\Livewire\Shop\Accounting\AccountingInvoicePreview;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingCreditAndInvoicePreviewSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_credit_and_invoice_preview(): void
    {
        Livewire::test(AccountingInvoicePreview::class)->assertStatus(403);
        Livewire::test(AccountingCredit::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_credit_and_invoice_preview(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AccountingInvoicePreview::class)
            ->assertStatus(403);

        Livewire::actingAs($customer, 'customer')
            ->test(AccountingCredit::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_credit_and_invoice_preview(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Finanz',
            'last_name' => 'Mitarbeiter',
            'email' => 'finanz@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AccountingInvoicePreview::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(AccountingCredit::class)
            ->assertStatus(200);
    }
}
