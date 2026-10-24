<?php

namespace App\Livewire\Shop\Management;

use Livewire\Component;
use App\Models\Management\ManagementShoppingCategory;
use App\Models\Management\ManagementShoppingItem;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.backend_layout')]
class ManagementShoppingList extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public $activeTab = 'needed'; // 'needed', 'all', 'categories'
    public $newItemName = '';
    public $newItemQuantity = '';
    public $selectedCategoryId = ''; // Selected category for quick add
    
    public $newCategoryName = '';
    public $newCategoryIcon = 'shopping-cart';
    
    public $availableIcons = [
        'shopping-cart', 'shopping-bag', 'home', 'sparkles', 'beaker', 
        'heart', 'cake', 'fire', 'gift', 'star', 'scissors', 'cube', 'sun', 'moon'
    ];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\Illuminate\Support\Facades\Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function render()
    {
        $categories = ManagementShoppingCategory::where('is_archived', false)
            ->orderBy('sort_order')
            ->get();

        $itemsQuery = ManagementShoppingItem::with('category');
        $oldestItems = collect();

        if ($this->activeTab === 'needed') {
            $itemsQuery->where('status', 'needed');
            $items = $itemsQuery->get()->sortBy(function($item) {
                return ($item->category ? $item->category->sort_order : 999) . '-' . $item->name;
            });
            
            $oldestItems = ManagementShoppingItem::where('status', 'stocked')
                ->whereNotNull('last_purchased_at')
                ->orderBy('last_purchased_at', 'asc')
                ->take(10)
                ->get();
        } else {
            $items = $itemsQuery->orderByRaw('last_purchased_at IS NOT NULL, last_purchased_at ASC')
                ->get();
        }

        $groupedItems = $items->groupBy(function($item) {
            return $item->category ? $item->category->name : 'Ohne Kategorie';
        });

        return view('livewire.shop.management.management-shopping-list', [
            'categories' => $categories,
            'groupedItems' => $groupedItems,
            'oldestItems' => $oldestItems
        ]);
    }

    public function toggleItemStatus($itemId)
    {
        $item = ManagementShoppingItem::find($itemId);
        if ($item) {
            if ($item->status === 'needed') {
                $item->status = 'stocked';
                $item->last_purchased_at = now();
                $item->purchase_count++;
            } else {
                $item->status = 'needed';
            }
            $item->save();
        }
    }

    public function addItem()
    {
        $this->validate([
            'newItemName' => 'required|string|max:255',
            'newItemQuantity' => 'nullable|string|max:50'
        ]);
        
        $cleanName = trim($this->newItemName);
        $cleanQty = !empty($this->newItemQuantity) ? trim($this->newItemQuantity) : null;

        $existing = ManagementShoppingItem::where('name', 'like', addcslashes($cleanName, '%_\\'))->first();

        $catId = (!empty($this->selectedCategoryId) && ManagementShoppingCategory::where('id', $this->selectedCategoryId)->exists())
            ? $this->selectedCategoryId
            : null;

        if ($existing) {
            $existing->status = 'needed';
            if ($catId) {
                $existing->category_id = $catId;
            }
            if ($cleanQty) {
                $existing->quantity = $cleanQty;
            }
            $existing->save();
        } else {
            ManagementShoppingItem::create([
                'name' => $cleanName,
                'quantity' => $cleanQty,
                'category_id' => $catId,
                'status' => 'needed',
            ]);
        }

        $this->newItemName = '';
        $this->newItemQuantity = '';
    }

    public function updateItemQuantity($itemId, $quantity)
    {
        $item = ManagementShoppingItem::find($itemId);
        if ($item) {
            $item->update(['quantity' => !empty(trim($quantity)) ? trim($quantity) : null]);
        }
    }

    public function addCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|string|max:255',
            'newCategoryIcon' => 'required|string|in:' . implode(',', $this->availableIcons)
        ]);
        
        ManagementShoppingCategory::create([
            'name' => $this->newCategoryName,
            'icon' => $this->newCategoryIcon,
            'sort_order' => ManagementShoppingCategory::max('sort_order') + 1,
        ]);

        $this->newCategoryName = '';
        $this->newCategoryIcon = 'shopping-cart';
    }

    public function deleteCategory($id)
    {
        $category = ManagementShoppingCategory::find($id);
        if ($category) {
            // Optional: Move items to "Ohne Kategorie"
            ManagementShoppingItem::where('category_id', $category->id)->update(['category_id' => null]);
            $category->delete();
        }
    }

    public function deleteItem($id)
    {
        $item = ManagementShoppingItem::find($id);
        if ($item) {
            $item->delete();
        }
    }
}
