<?php

namespace Tests\Feature\Ai;

use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;
use App\Services\AI\Functions\AiShoppingListFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiShoppingListFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_shopping_item_uses_exact_match_preventing_wildcard_hijacking()
    {
        $dummyClass = new class {
            use AiShoppingListFuncs;
        };

        // Existing item in the shopping database
        $cat = ManagementShoppingCategory::create(['name' => 'Küche', 'icon' => 'shopping-cart', 'sort_order' => 1]);
        $existingItem = ManagementShoppingItem::create([
            'name' => 'Olivenöl Extra Vergine',
            'category_id' => $cat->id,
            'status' => 'stocked',
        ]);

        // Attacker attempts wildcard matching using "%" to hijack existing items
        $resWildcard = $dummyClass::executeAddShoppingItem([
            'name' => '%',
        ]);

        // A new item named "%" should be created rather than mutating "Olivenöl Extra Vergine"
        $existingItem->refresh();
        $this->assertEquals('stocked', $existingItem->status);

        // Standard item addition
        $resNormal = $dummyClass::executeAddShoppingItem([
            'name' => 'Bienenwachs Pellets',
            'category_name' => 'Werkstatt',
        ]);

        $this->assertEquals('success', $resNormal['status']);
        $this->assertDatabaseHas('management_shopping_items', [
            'name' => 'Bienenwachs Pellets',
            'status' => 'needed',
        ]);
    }
}
