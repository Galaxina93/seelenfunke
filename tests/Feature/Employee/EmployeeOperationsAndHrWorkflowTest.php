<?php

namespace Tests\Feature\Employee;

use App\Livewire\Employee\EmployeeDashboard;
use App\Livewire\Shop\Management\ManagementEmployees;
use App\Mail\EmployeeInvitationMail;
use App\Mail\NewOrderShippedToCustomer;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeeTimeEntry;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\Employee\EmployeeHrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeOperationsAndHrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Chef',
            'last_name' => 'Leitung',
            'email' => 'chef-' . uniqid() . '@seelenfunke.test',
            'password' => Hash::make('Secret123!'),
        ]);
    }

    protected function createSignedEmployee(array $permissions = []): array
    {
        $employee = Employee::create([
            'first_name' => 'Lisa',
            'last_name' => 'Manufaktur',
            'email' => 'lisa-' . uniqid() . '@seelenfunke.test',
            'role' => 'internal',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $contract = EmployeeContract::create(array_merge([
            'employee_id' => $employee->id,
            'invitation_label' => 'Lisa Manufaktur',
            'job_title' => 'Manufaktur-Assistentin',
            'employment_type' => 'minijob',
            'compensation_type' => 'hourly',
            'hourly_rate_cents' => 1500,
            'weekly_hours' => 9.0,
            'vacation_days_per_year' => 20,
            'vacation_days_taken' => 0,
            'contract_number' => 'AV-2026-0099',
            'signed_at' => now(),
            'invitation_accepted_at' => now(),
            'is_active' => true,
            'is_archived' => false,
            'can_access_production' => true,
            'can_access_support' => false,
            'can_access_inventory' => false,
        ], $permissions));

        return [$employee, $contract];
    }

    #[Test]
    public function admin_can_invite_employee_with_direct_email_and_toggle_work_areas(): void
    {
        Mail::fake();
        $admin = $this->createAdmin();

        Livewire::actingAs($admin, 'admin')
            ->test(ManagementEmployees::class)
            ->call('openInviteModal')
            ->set('inviteLabel', 'Max Packtisch')
            ->set('inviteEmail', 'max@packtisch.test')
            ->set('inviteCanAccessProduction', true)
            ->set('inviteCanAccessSupport', true)
            ->set('inviteCanAccessInventory', false)
            ->call('createInvitation')
            ->assertHasNoErrors();

        Mail::assertSent(EmployeeInvitationMail::class, function (EmployeeInvitationMail $mail) {
            return $mail->hasTo('max@packtisch.test')
                && !empty($mail->data['url'])
                && strlen((string) $mail->data['code']) === 6;
        });

        $contract = EmployeeContract::where('invitation_label', 'Max Packtisch')->firstOrFail();
        $this->assertTrue($contract->can_access_production);
        $this->assertTrue($contract->can_access_support);
        $this->assertFalse($contract->can_access_inventory);

        // 1-Klick Berechtigungs-Schalter testen
        Livewire::actingAs($admin, 'admin')
            ->test(ManagementEmployees::class)
            ->call('togglePermission', $contract->id, 'can_access_inventory');

        $this->assertTrue($contract->fresh()->can_access_inventory);
    }

    #[Test]
    public function employee_can_process_laser_and_packing_orders_step_by_step(): void
    {
        Mail::fake();
        [$employee] = $this->createSignedEmployee([
            'can_access_production' => true,
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-PACK1',
            'email' => 'kunde@beispiel.test',
            'status' => 'pending',
            'payment_status' => 'paid',
            'billing_address' => [
                'first_name' => 'Anna',
                'last_name' => 'Kundin',
                'street' => 'Blumenweg',
                'house_number' => '12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Anna',
                'last_name' => 'Kundin',
                'street' => 'Blumenweg',
                'house_number' => '12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
        ]);

        $product = Product::create([
            'name' => 'Seelenkristall Herz',
            'slug' => 'seelenkristall-herz-' . uniqid(),
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'quantity' => 50,
            'track_quantity' => true,
        ]);

        $item = OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Seelenkristall Herz',
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
            'is_completed' => false,
            'configuration' => ['text' => 'Für immer im Herzen'],
        ]);

        Livewire::actingAs($employee, 'employee')
            ->test(EmployeeDashboard::class)
            ->assertSee('ORD-2026-PACK1')
            ->call('toggleOrderItemCompleted', $item->id)
            ->set("trackingNumbers.{$order->id}", '00340434161094015902')
            ->call('markOrderShipped', $order->id);

        $this->assertTrue((bool) $item->fresh()->is_completed);
        $this->assertSame('shipped', $order->fresh()->status);
        $this->assertDatabaseHas('order_shipments', [
            'order_id' => $order->id,
            'tracking_number' => '00340434161094015902',
        ]);

        Mail::assertSent(NewOrderShippedToCustomer::class);
    }

    #[Test]
    public function employee_can_answer_support_tickets_and_report_inventory_fracture_when_unlocked(): void
    {
        [$employee] = $this->createSignedEmployee([
            'can_access_production' => false,
            'can_access_support' => true,
            'can_access_inventory' => true,
        ]);

        $customer = Customer::create([
            'first_name' => 'Tom',
            'last_name' => 'Support',
            'email' => 'tom-' . uniqid() . '@kunde.test',
            'password' => Hash::make('Secret123!'),
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'TIC-9001',
            'customer_id' => $customer->id,
            'subject' => 'Frage zur Gravur',
            'category' => 'order',
            'status' => 'open',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => 'Kann ich noch ein Herz ergänzen?',
        ]);

        $product = Product::create([
            'name' => 'Glasblock XL',
            'slug' => 'glasblock-xl-' . uniqid(),
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'purchase_price' => 800,
            'quantity' => 10,
            'track_quantity' => true,
        ]);

        Livewire::actingAs($employee, 'employee')
            ->test(EmployeeDashboard::class)
            ->call('selectSupportTicket', $ticket->id)
            ->set('ticketReplyMessage', 'Ja, wir haben das Herz gerne für dich ergänzt!')
            ->call('sendSupportReply')
            ->call('closeSupportTicket', $ticket->id)
            ->call('switchTab', 'inventory')
            ->set('lossProductId', $product->id)
            ->set('lossQuantity', 2)
            ->set('lossReason', 'Laser-Glasbruch')
            ->call('saveFracture')
            ->assertHasNoErrors();

        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => 'Ja, wir haben das Herz gerne für dich ergänzt!',
        ]);

        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertSame(1, ProductLoss::where('product_id', $product->id)->where('quantity', 2)->count());
    }

    #[Test]
    public function employee_can_request_vacation_and_admin_can_approve_with_one_click(): void
    {
        $admin = $this->createAdmin();
        [$employee, $contract] = $this->createSignedEmployee();

        $date = now()->addDays(3)->format('Y-m-d');

        Livewire::actingAs($employee, 'employee')
            ->test(EmployeeDashboard::class)
            ->call('switchTab', 'time')
            ->set('leaveType', 'vacation')
            ->set('leaveStartDate', $date)
            ->set('leaveEndDate', $date)
            ->set('leaveNote', 'Erholung')
            ->call('saveLeaveEntry')
            ->assertHasNoErrors();

        $entry = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('entry_type', 'vacation')
            ->firstOrFail();

        $this->assertSame('pending_approval', $entry->status);
        $this->assertSame(0, (int) $contract->fresh()->vacation_days_taken);

        // Admin genehmigt mit 1 Klick
        Livewire::actingAs($admin, 'admin')
            ->test(ManagementEmployees::class)
            ->call('approveLeave', $entry->id);

        $this->assertSame('approved', $entry->fresh()->status);
        $this->assertSame(1, (int) $contract->fresh()->vacation_days_taken);
    }

    #[Test]
    public function tax_advisor_can_download_employee_contract_and_questionnaire_pdfs(): void
    {
        [, $contract] = $this->createSignedEmployee();
        app(EmployeeHrService::class)->generateAndStoreContractPdf($contract);
        app(EmployeeHrService::class)->generateAndStoreQuestionnairePdf($contract);
        $contract->refresh();

        $taxAdvisor = Employee::create([
            'first_name' => 'Steuer',
            'last_name' => 'Kanzlei',
            'email' => 'kanzlei-' . uniqid() . '@steuer.test',
            'role' => 'tax_advisor',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $this->actingAs($taxAdvisor, 'employee')
            ->get(route('employee.tax-advisor.employee-contract.pdf', ['contract' => $contract->id]))
            ->assertOk();

        $this->actingAs($taxAdvisor, 'employee')
            ->get(route('employee.tax-advisor.employee-questionnaire.pdf', ['contract' => $contract->id]))
            ->assertOk();

        $this->actingAs($taxAdvisor, 'employee')
            ->get(route('employee.tax-advisor.employee-timesheet.pdf', [
                'contract' => $contract->id,
                'year' => (int) now()->year,
                'month' => (int) now()->month,
            ]))
            ->assertOk();
    }
}
