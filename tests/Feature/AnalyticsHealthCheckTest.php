<?php

namespace Tests\Feature\Livewire\Global\Widgets;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Livewire\Shop\Master\MasterAnalytics as Analytics;
use App\Models\Admin\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AnalyticsHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        Schema::disableForeignKeyConstraints();

        $adminId = Str::uuid()->toString();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'test@admin.com',
            'password' => bcrypt('password')
        ]);
        
        $this->admin = Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');
    }

    public function test_it_shows_warning_status_when_no_records_exist()
    {
        Livewire::test(Analytics::class)
            ->assertSee('Keine Aufgaben vorhanden')
            ->assertSet('healthChecks.open_tasks.status', 'warning')
            
            ->assertSee('Keine Gutschriften vorhanden')
            ->assertSet('healthChecks.open_credits.status', 'warning')
            
            ->assertSee('Keine Tickets vorhanden')
            ->assertSet('healthChecks.open_tickets.status', 'warning')
            
            ->assertSee('Noch keine Bewertungen')
            ->assertSet('healthChecks.product_reviews.status', 'warning')
            
            ->assertSee('Keine Transaktionen gefunden')
            ->assertSet('healthChecks.unassigned_tx.status', 'warning')
            
            ->assertSee('Keine Angebote vorhanden')
            ->assertSet('healthChecks.open_quotes.status', 'warning')
            
            ->assertSee('Keine Widerrufe vorhanden')
            ->assertSet('healthChecks.open_revocations.status', 'warning');
    }

    public function test_it_shows_error_status_when_open_records_exist()
    {
        $taskListId = Str::uuid()->toString();
        DB::table('management_task_lists')->insert(['id' => $taskListId, 'name' => 'Test']);
        DB::table('management_tasks')->insert(['id' => Str::uuid()->toString(), 'is_completed' => 0, 'title' => 'Task', 'task_list_id' => $taskListId]);
        DB::table('accounting_invoices')->insert(['id' => Str::uuid()->toString(), 'type' => 'credit_note', 'invoice_number' => '1', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0, 'status' => 'draft', 'invoice_date' => now(), 'billing_address' => '{}']);
        DB::table('support_tickets')->insert(['id' => Str::uuid()->toString(), 'ticket_number' => '1', 'status' => 'open', 'subject' => 'A', 'customer_id' => Str::uuid()->toString(), 'category' => 'support']);
        // ProductReview migration expects title
        DB::table('product_reviews')->insert(['id' => Str::uuid()->toString(), 'status' => 'pending', 'product_id' => Str::uuid()->toString(), 'customer_id' => Str::uuid()->toString(), 'title' => 'A', 'content' => 'B', 'rating' => 5]);
        
        $accountId = 1;
        DB::table('accounting_bank_accounts')->insert([
            'id' => $accountId,
            'admin_id' => $this->admin->id,
            'is_active_for_analysis' => 1,
            'account_name' => 'Test',
            'bank_name' => 'Test Bank',
            'plaid_item_id' => 'item1',
            'plaid_access_token' => 'token1',
            'plaid_account_id' => 'acc1',
            'iban' => '123'
        ]);
        
        DB::table('accounting_bank_transactions')->insert([
            'id' => 1, 
            'accounting_bank_account_id' => $accountId,
            'finapi_transaction_id' => 'trans1',
            'amount' => 10,
            'currency' => 'EUR',
            'assigned_by_type' => null,
            'purpose' => 'Test'
        ]);
        
        DB::table('order_quote_requests')->insert(['id' => Str::uuid()->toString(), 'status' => 'open', 'created_at' => now()->subDays(6), 'quote_number' => '1', 'email' => 'test@test.com', 'first_name' => 'A', 'last_name' => 'B', 'net_total' => 0, 'tax_total' => 0, 'gross_total' => 0]);
        DB::table('order_revocations')->insert(['id' => 1, 'status' => 'pending', 'created_at' => now()->subDays(3), 'name' => 'A', 'email' => 'a@b.com', 'order_number' => '123']);


        Livewire::test(Analytics::class)
            ->assertSet('healthChecks.open_tasks.status', 'warning') 
            ->assertSet('healthChecks.open_credits.status', 'error')
            ->assertSet('healthChecks.open_tickets.status', 'error')
            ->assertSet('healthChecks.product_reviews.status', 'error')
            ->assertSet('healthChecks.unassigned_tx.status', 'error')
            ->assertSet('healthChecks.open_quotes.status', 'error')
            ->assertSet('healthChecks.open_revocations.status', 'error');
    }

    public function test_it_shows_success_status_when_records_exist_but_are_completed()
    {
        $taskListId = Str::uuid()->toString();
        DB::table('management_task_lists')->insert(['id' => $taskListId, 'name' => 'Test']);
        DB::table('management_tasks')->insert(['id' => Str::uuid()->toString(), 'is_completed' => 1, 'title' => 'Task', 'task_list_id' => $taskListId]);
        DB::table('accounting_invoices')->insert(['id' => Str::uuid()->toString(), 'type' => 'credit_note', 'invoice_number' => '2', 'subtotal' => 0, 'tax_amount' => 0, 'total' => 0, 'email_sent_at' => now(), 'status' => 'draft', 'invoice_date' => now(), 'billing_address' => '{}']);
        DB::table('support_tickets')->insert(['id' => Str::uuid()->toString(), 'ticket_number' => '2', 'status' => 'closed', 'subject' => 'A', 'customer_id' => Str::uuid()->toString(), 'category' => 'support']);
        DB::table('product_reviews')->insert(['id' => Str::uuid()->toString(), 'status' => 'approved', 'product_id' => Str::uuid()->toString(), 'customer_id' => Str::uuid()->toString(), 'title' => 'A', 'content' => 'B', 'rating' => 5]);
        
        $accountId = 2;
        DB::table('accounting_bank_accounts')->insert([
            'id' => $accountId,
            'admin_id' => $this->admin->id,
            'is_active_for_analysis' => 1,
            'account_name' => 'Test',
            'bank_name' => 'Test Bank',
            'plaid_item_id' => 'item2',
            'plaid_access_token' => 'token2',
            'plaid_account_id' => 'acc2',
            'iban' => '123'
        ]);
        
        DB::table('accounting_bank_transactions')->insert([
            'id' => 2, 
            'accounting_bank_account_id' => $accountId,
            'finapi_transaction_id' => 'trans2',
            'amount' => 10,
            'currency' => 'EUR',
            'assigned_by_type' => 'App\Models\Order',
            'purpose' => 'Test'
        ]);
        
        DB::table('order_quote_requests')->insert(['id' => Str::uuid()->toString(), 'status' => 'converted', 'created_at' => now()->subDays(6), 'quote_number' => '2', 'email' => 'test@test.com', 'first_name' => 'A', 'last_name' => 'B', 'net_total' => 0, 'tax_total' => 0, 'gross_total' => 0]);
        DB::table('order_revocations')->insert(['id' => 2, 'status' => 'completed', 'created_at' => now()->subDays(3), 'name' => 'A', 'email' => 'a@b.com', 'order_number' => '456']);

        Livewire::test(Analytics::class)
            ->assertSet('healthChecks.open_tasks.status', 'success')
            ->assertSee('Alles erledigt')
            
            ->assertSet('healthChecks.open_credits.status', 'success')
            ->assertSee('Alle versendet')
            
            ->assertSet('healthChecks.open_tickets.status', 'success')
            ->assertSee('Alles beantwortet')
            
            ->assertSet('healthChecks.product_reviews.status', 'success')
            ->assertSee('Alle geprüft')
            
            ->assertSet('healthChecks.unassigned_tx.status', 'success')
            ->assertSee('Alle sortiert')
            
            ->assertSet('healthChecks.open_quotes.status', 'success')
            ->assertSee('Alles aktuell')
            
            ->assertSet('healthChecks.open_revocations.status', 'success')
            ->assertSee('Alles aktuell');
    }
}

