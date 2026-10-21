<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingVariableCosts;
use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingVariableCostsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_arbitrary_unwhitelisted_field_cannot_be_updated(): void
    {
        $admin = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();

        $issue = AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Büromaterial',
            'amount' => 50.00,
            'category' => 'Büro',
            'is_business' => true,
            'execution_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AccountingVariableCosts::class)
            ->call('updateSpecialField', $issue->id, 'admin_id', $otherAdmin->id);

        $issue->refresh();
        $this->assertEquals($admin->id, $issue->admin_id, 'admin_id must not be overwritten via updateSpecialField');
    }

    public function test_whitelisted_field_updates_correctly(): void
    {
        $admin = Admin::factory()->create();

        $issue = AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Alte Bezeichnung',
            'amount' => 50.00,
            'category' => 'Büro',
            'is_business' => true,
            'execution_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AccountingVariableCosts::class)
            ->call('updateSpecialField', $issue->id, 'title', 'Neue Bezeichnung')
            ->call('updateSpecialField', $issue->id, 'amount', '99.50');

        $issue->refresh();
        $this->assertEquals('Neue Bezeichnung', $issue->title);
        $this->assertEquals(99.50, (float)$issue->amount);
    }

    public function test_confirm_delete_category_cannot_delete_foreign_admin_category(): void
    {
        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $catB1 = AccountingCategory::create([
            'admin_id' => $adminB->id,
            'name' => 'Kategorie Admin B',
            'usage_count' => 1,
        ]);
        $catB2 = AccountingCategory::create([
            'admin_id' => $adminB->id,
            'name' => 'Ziel Kategorie Admin B',
            'usage_count' => 0,
        ]);

        Livewire::actingAs($adminA, 'admin')
            ->test(AccountingVariableCosts::class)
            ->set('categoryToDeleteId', $catB1->id)
            ->set('targetCategoryId', $catB2->id)
            ->call('confirmDeleteCategory');

        // catB1 must NOT be deleted because adminA does not own it
        $this->assertDatabaseHas('accounting_categories', [
            'id' => $catB1->id,
            'deleted_at' => null,
        ]);
    }
}
