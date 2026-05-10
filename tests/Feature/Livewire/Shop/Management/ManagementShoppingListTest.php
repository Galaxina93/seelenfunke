<?php

namespace Tests\Feature\Livewire\Shop\Management;

use App\Livewire\Shop\Management\ManagementShoppingList;
use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;
use App\Models\System\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Admin erstellen und authentifizieren
        $this->admin = Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');
    }

    public function test_renders_successfully()
    {
        Livewire::test(ManagementShoppingList::class)
            ->assertStatus(200);
    }

    public function test_can_add_item()
    {
        Livewire::test(ManagementShoppingList::class)
            ->set('newItemName', 'Milch')
            ->call('addItem')
            ->assertSet('newItemName', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('management_shopping_items', [
            'name' => 'Milch',
            'status' => 'needed',
        ]);
    }

    public function test_can_toggle_item_status()
    {
        $item = ManagementShoppingItem::create([
            'name' => 'Brot',
            'status' => 'needed'
        ]);

        Livewire::test(ManagementShoppingList::class)
            ->call('toggleItemStatus', $item->id);

        $this->assertDatabaseHas('management_shopping_items', [
            'id' => $item->id,
            'status' => 'stocked',
        ]);
        
        $item->refresh();
        $this->assertNotNull($item->last_purchased_at);
        $this->assertEquals(1, $item->purchase_count);
        
        // Zurücksetzen auf needed
        Livewire::test(ManagementShoppingList::class)
            ->call('toggleItemStatus', $item->id);
            
        $this->assertDatabaseHas('management_shopping_items', [
            'id' => $item->id,
            'status' => 'needed',
        ]);
    }

    public function test_can_add_category()
    {
        Livewire::test(ManagementShoppingList::class)
            ->set('newCategoryName', 'Getränke')
            ->call('addCategory')
            ->assertSet('newCategoryName', '');

        $this->assertDatabaseHas('management_shopping_categories', [
            'name' => 'Getränke',
        ]);
    }

    public function test_readding_existing_item_marks_as_needed()
    {
        $item = ManagementShoppingItem::create([
            'name' => 'Kaffee',
            'status' => 'stocked'
        ]);

        Livewire::test(ManagementShoppingList::class)
            ->set('newItemName', 'Kaffee')
            ->call('addItem');

        $this->assertDatabaseHas('management_shopping_items', [
            'id' => $item->id,
            'status' => 'needed',
        ]);
        
        // Es sollte kein neues Item erstellt werden
        $this->assertEquals(1, ManagementShoppingItem::count());
    }

    public function test_view_switches_between_tabs()
    {
        Livewire::test(ManagementShoppingList::class)
            ->assertSet('activeTab', 'needed')
            ->set('activeTab', 'all')
            ->assertSet('activeTab', 'all');
    }
}
