<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Admin\Admin;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Services\AI\Functions\AiFinanceFuncs;

class AiFinanceFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $mockClass;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');

        $this->mockClass = new class {
            use AiFinanceFuncs;
        };
    }

    public function test_edit_variable_cost_enforces_negative_amount_for_expenses(): void
    {
        $issue = AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Büromaterial Einkauf',
            'category' => 'Bürobedarf',
            'amount' => -45.00,
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        // Attempting to pass positive amount
        $res = $this->mockClass::executeEditVariableCost([
            'id' => $issue->id,
            'amount' => 89.90
        ]);

        $this->assertEquals('success', $res['status']);
        $issue->refresh();
        // Must be converted to negative to prevent turning expense into income
        $this->assertEquals(-89.90, (float)$issue->amount);
    }

    public function test_generate_and_send_report_rejects_invalid_and_crlf_emails(): void
    {
        $resInvalid = $this->mockClass::executeGenerateAndSendReport([
            'email' => 'not-an-email'
        ]);
        $this->assertEquals('error', $resInvalid['status']);

        $resCrlf = $this->mockClass::executeGenerateAndSendReport([
            'email' => "finance@example.com\r\nBcc:spy@evil.com"
        ]);
        $this->assertEquals('error', $resCrlf['status']);
    }

    public function test_read_fixed_cost_contract_blocks_path_traversal(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Server & Hosting',
            'type' => 'expense',
            'position' => 1
        ]);

        $item = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Netcup VPS',
            'amount' => -25.00,
            'interval_months' => 1,
            'first_payment_date' => now()->format('Y-m-d'),
            'contract_file_path' => '../../../../etc/passwd',
            'is_business' => true
        ]);

        $res = $this->mockClass::executeReadFixedCostContract([
            'id' => $item->id
        ]);

        $this->assertEquals('error', $res['status']);
        $this->assertStringContainsString('verdächtiger Dateipfad', $res['message']);
    }

    public function test_search_variable_costs_escapes_like_wildcards(): void
    {
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Test 100% Rabatt Aktion',
            'category' => 'Marketing',
            'amount' => -100.00,
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Andere Buchung Normal',
            'category' => 'Büro',
            'amount' => -50.00,
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        $res = $this->mockClass::executeSearchVariableCosts([
            'query' => '100%'
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['results']);
        $this->assertEquals('Test 100% Rabatt Aktion', $res['results'][0]['title']);
    }
}
