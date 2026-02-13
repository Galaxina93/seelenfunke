<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\ShopAttribute;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductAttributes extends Component
{
    public Product $product;

    // Die Attribute des Produkts (Key => Value) aus der DB
    public array $productAttributes = [];

    // Verfügbare Attribute aus der Tabelle shop_attributes
    public array $availableAttributes = [];

    // Suche & Modus
    public $search = '';
    public $isManaging = false;

    // CRUD Felder
    public $newAttributeName = '';
    public $editingAttributeId = null;
    public $editingAttributeName = '';

    public function mount(Product $product)
    {
        $this->product = $product;

        // Wir laden die existierenden Attribute aus dem JSON Feld des Produkts
        // WICHTIG: Wir holen sie frisch aus dem Model, falls es gecached war
        $this->productAttributes = $this->product->refresh()->attributes ?? [];

        $this->loadAvailableAttributes();
    }

    public function loadAvailableAttributes()
    {
        $query = ShopAttribute::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        // Wir laden alle, sortieren aber so, dass die zum Typ passenden oben stehen
        // Da SQL Sortierung nach FIELD komplex ist, laden wir einfach alphabetisch
        // Optional: ->where('type', $this->product->type) wenn du strikt trennen willst.
        // Wir zeigen alle an, damit man flexibel ist.

        $this->availableAttributes = $query->orderBy('name')->get()->toArray();
    }

    public function updatedSearch()
    {
        $this->loadAvailableAttributes();
    }

    // --- LOGIK: Attribut aktivieren/deaktivieren ---

    public function toggleAttribute($name)
    {
        if ($this->isManaging) return;

        if (array_key_exists($name, $this->productAttributes)) {
            // Entfernen
            unset($this->productAttributes[$name]);
        } else {
            // Hinzufügen (Standardwert leer)
            $this->productAttributes[$name] = '';
        }

        $this->saveToProduct();
    }

    // --- LOGIK: Wert speichern ---

    public function updatedProductAttributes()
    {
        // Wird gefeuert, wenn in ein Input-Feld getippt wird (wire:model.live)
        $this->saveToProduct();
    }

    private function saveToProduct()
    {
        // Speichere das Array direkt in das Produkt Model
        $this->product->attributes = $this->productAttributes;
        $this->product->save();
    }

    // --- VERWALTUNGS LOGIK (CRUD) ---

    public function toggleManageMode()
    {
        $this->isManaging = !$this->isManaging;
        $this->resetInput();
        $this->loadAvailableAttributes();
    }

    public function createAttribute()
    {
        $this->validate([
            'newAttributeName' => 'required|min:2|unique:shop_attributes,name'
        ]);

        ShopAttribute::create([
            'name' => $this->newAttributeName,
            'slug' => Str::slug($this->newAttributeName),
            'type' => $this->product->type,
        ]);

        $this->newAttributeName = '';
        session()->flash('success', 'Attribut erstellt.');
        $this->loadAvailableAttributes();
    }

    public function startEditing($id, $name)
    {
        $this->editingAttributeId = $id;
        $this->editingAttributeName = $name;
    }

    public function cancelEditing()
    {
        $this->resetInput();
    }

    public function updateAttribute()
    {
        $this->validate([
            'editingAttributeName' => 'required|min:2|unique:shop_attributes,name,' . $this->editingAttributeId
        ]);

        $attr = ShopAttribute::find($this->editingAttributeId);
        if ($attr) {
            // Wenn der Name geändert wird, müssen wir theoretisch auch das JSON im Produkt updaten,
            // wenn wir strikt konsistent sein wollen. Da dies komplex ist (alle Produkte durchsuchen),
            // ändern wir hier nur den "Vorlagen-Namen".
            // Für "Mein-Seelenfunke" Professionalität ändern wir aber den Key im aktuellen Produkt mit, falls vorhanden.

            $oldName = $attr->name;
            $newName = $this->editingAttributeName;

            $attr->update([
                'name' => $newName,
                'slug' => Str::slug($newName)
            ]);

            // Key im aktuellen Produkt migrieren
            if (array_key_exists($oldName, $this->productAttributes)) {
                $value = $this->productAttributes[$oldName];
                unset($this->productAttributes[$oldName]);
                $this->productAttributes[$newName] = $value;
                $this->saveToProduct();
            }
        }

        $this->resetInput();
        $this->loadAvailableAttributes();
    }

    public function deleteAttribute($id)
    {
        $attr = ShopAttribute::find($id);

        if ($attr) {
            $name = $attr->name;

            // Wenn gelöscht, entfernen wir es auch aus der Auswahl des aktuellen Produkts
            if (array_key_exists($name, $this->productAttributes)) {
                unset($this->productAttributes[$name]);
                $this->saveToProduct();
            }

            $attr->delete();
        }

        $this->loadAvailableAttributes();
    }

    private function resetInput()
    {
        $this->newAttributeName = '';
        $this->editingAttributeId = null;
        $this->editingAttributeName = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.shop.product.product-attributes');
    }
}
