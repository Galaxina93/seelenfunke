<?php

namespace Tests\Feature\Api;

use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_cannot_update_another_admins_variable_expense(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $issueB = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $adminB->id,
            'title' => 'Admin B Geheimkosten',
            'amount' => 500.00,
            'category' => 'Technik',
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        Sanctum::actingAs($adminA);

        $response = $this->putJson("/api/funki/financials/variable/{$issueB->id}", [
            'title' => 'Gehackt von A',
            'amount' => 999.00,
            'category' => 'Technik',
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        $response->assertStatus(404);
        $this->assertEquals('Admin B Geheimkosten', $issueB->fresh()->title);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_cannot_delete_another_admins_variable_expense(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $issueB = AccountingSpecialIssue::create([
            'id' => Str::uuid(),
            'admin_id' => $adminB->id,
            'title' => 'Admin B Geheimkosten',
            'amount' => 500.00,
            'category' => 'Technik',
            'execution_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        Sanctum::actingAs($adminA);

        $response = $this->deleteJson("/api/funki/financials/variable/{$issueB->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('accounting_special_issues', ['id' => $issueB->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_cannot_insert_or_modify_cost_items_in_another_admins_group(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $groupB = AccountingGroup::create([
            'id' => Str::uuid(),
            'admin_id' => $adminB->id,
            'name' => 'Admin B Fixkosten',
            'type' => 'expense',
            'position' => 1,
        ]);

        $itemB = AccountingCostItem::create([
            'id' => Str::uuid(),
            'accounting_group_id' => $groupB->id,
            'name' => 'Admin B Server',
            'amount' => 150.00,
            'interval_months' => 1,
            'first_payment_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);

        Sanctum::actingAs($adminA);

        // A tries to insert item into B's group
        $insertResponse = $this->postJson('/api/funki/financials/fixed-item', [
            'finance_group_id' => $groupB->id,
            'name' => 'Admin A Injektion',
            'amount' => 20.00,
            'interval_months' => 1,
            'first_payment_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);
        $insertResponse->assertStatus(404);

        // A tries to update B's item
        $updateResponse = $this->putJson("/api/funki/financials/fixed-item/{$itemB->id}", [
            'name' => 'Admin A Manipuliert',
            'amount' => 999.00,
            'interval_months' => 1,
            'first_payment_date' => now()->format('Y-m-d'),
            'is_business' => true,
        ]);
        $updateResponse->assertStatus(404);

        // A tries to delete B's item
        $deleteResponse = $this->deleteJson("/api/funki/financials/fixed-item/{$itemB->id}");
        $deleteResponse->assertStatus(404);

        $this->assertDatabaseHas('accounting_cost_items', ['id' => $itemB->id]);
    }
}
