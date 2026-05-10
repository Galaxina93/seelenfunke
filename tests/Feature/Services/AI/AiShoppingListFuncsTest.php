<?php

namespace Tests\Feature\Services\AI;

use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;
use App\Services\AI\Functions\AiShoppingListFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiShoppingListFuncsTest extends TestCase
{
    use RefreshDatabase, AiShoppingListFuncs;

    public function test_get_shopping_list_schema()
    {
        $schema = self::getAiShoppingListFuncsSchema();
        $this->assertIsArray($schema);
        $this->assertGreaterThan(0, count($schema));
        
        $names = array_column($schema, 'name');
        $this->assertContains('shopping_list_get', $names);
        $this->assertContains('shopping_list_add', $names);
        $this->assertContains('shopping_list_toggle', $names);
        $this->assertContains('shopping_list_rename', $names);
        $this->assertContains('shopping_list_delete', $names);
    }

    public function test_execute_get_shopping_list()
    {
        ManagementShoppingItem::create(['name' => 'Äpfel', 'status' => 'needed']);
        ManagementShoppingItem::create(['name' => 'Birnen', 'status' => 'stocked']);

        // Test all
        $resultAll = self::executeGetShoppingList([]);
        $this->assertEquals('success', $resultAll['status']);
        $this->assertCount(2, $resultAll['items']);

        // Test only needed
        $resultNeeded = self::executeGetShoppingList(['only_needed' => true]);
        $this->assertEquals('success', $resultNeeded['status']);
        $this->assertCount(1, $resultNeeded['items']);
        $this->assertEquals('Äpfel', $resultNeeded['items'][0]['name']);
    }

    public function test_execute_add_shopping_item()
    {
        $result = self::executeAddShoppingItem([
            'name' => 'Bananen',
            'category_name' => 'Obst'
        ]);

        $this->assertEquals('success', $result['status']);
        
        $this->assertDatabaseHas('management_shopping_items', [
            'name' => 'Bananen',
            'status' => 'needed'
        ]);

        $this->assertDatabaseHas('management_shopping_categories', [
            'name' => 'Obst'
        ]);
    }

    public function test_execute_add_shopping_item_existing_stocked()
    {
        ManagementShoppingItem::create([
            'name' => 'Käse',
            'status' => 'stocked'
        ]);

        $result = self::executeAddShoppingItem([
            'name' => 'Käse'
        ]);

        $this->assertEquals('success', $result['status']);
        
        $this->assertDatabaseHas('management_shopping_items', [
            'name' => 'Käse',
            'status' => 'needed'
        ]);
        
        $this->assertEquals(1, ManagementShoppingItem::where('name', 'Käse')->count());
    }

    public function test_execute_toggle_shopping_item_by_id()
    {
        $item = ManagementShoppingItem::create([
            'name' => 'Milch',
            'status' => 'needed'
        ]);

        $result = self::executeToggleShoppingItem([
            'item_id' => $item->id,
            'status' => 'stocked'
        ]);

        $this->assertEquals('success', $result['status']);
        
        $this->assertDatabaseHas('management_shopping_items', [
            'id' => $item->id,
            'status' => 'stocked'
        ]);
        
        $item->refresh();
        $this->assertNotNull($item->last_purchased_at);
        $this->assertEquals(1, $item->purchase_count);
    }

    public function test_execute_toggle_shopping_item_by_name()
    {
        ManagementShoppingItem::create([
            'name' => 'Hafermilch',
            'status' => 'needed'
        ]);

        $result = self::executeToggleShoppingItem([
            'name' => 'Hafermilch',
            'status' => 'stocked'
        ]);

        $this->assertEquals('success', $result['status']);
        
        $this->assertDatabaseHas('management_shopping_items', [
            'name' => 'Hafermilch',
            'status' => 'stocked'
        ]);
    }

    public function test_execute_rename_shopping_item()
    {
        $item = ManagementShoppingItem::create([
            'name' => 'Altes Brot',
            'status' => 'needed'
        ]);

        $result = self::executeRenameShoppingItem([
            'item_id' => $item->id,
            'new_name' => 'Frisches Brot',
            'new_category' => 'Backwaren'
        ]);

        $this->assertEquals('success', $result['status']);

        $this->assertDatabaseHas('management_shopping_items', [
            'id' => $item->id,
            'name' => 'Frisches Brot'
        ]);
        
        $this->assertDatabaseHas('management_shopping_categories', [
            'name' => 'Backwaren'
        ]);
    }

    public function test_execute_delete_shopping_item()
    {
        $item = ManagementShoppingItem::create([
            'name' => 'Zu löschendes Item',
            'status' => 'needed'
        ]);

        $result = self::executeDeleteShoppingItem([
            'item_id' => $item->id
        ]);

        $this->assertEquals('success', $result['status']);

        $this->assertDatabaseMissing('management_shopping_items', [
            'id' => $item->id
        ]);
    }
}
