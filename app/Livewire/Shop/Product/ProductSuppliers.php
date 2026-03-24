<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Supplier;
use Livewire\Component;

class ProductSuppliers extends Component
{
    public $suppliers;

    // Form fields
    public $name;
    public $contact_person;
    public $email;
    public $phone;
    public $website;
    public $address;
    public $notes;
    public $lead_time_land_days;
    public $lead_time_air_days;
    public $lead_time_sea_days;
    public $lead_time_train_days;
    public $shipping_method = 'land';
    public $dynamic_links = [];

    // State
    public $isEditing = false;
    public $editingId = null;
    public $showModal = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'contact_person' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:255',
        'website' => 'nullable|url|max:255',
        'address' => 'nullable|string',
        'notes' => 'nullable|string',
        'lead_time_land_days' => 'nullable|integer|min:0',
        'lead_time_air_days' => 'nullable|integer|min:0',
        'lead_time_sea_days' => 'nullable|integer|min:0',
        'lead_time_train_days' => 'nullable|integer|min:0',
        'shipping_method' => 'nullable|string|in:land,air,sea,train',
        'dynamic_links' => 'nullable|array',
        'dynamic_links.*.title' => 'required|string|max:255',
        'dynamic_links.*.url' => 'required|url|max:500',
    ];

    public function mount()
    {
        $this->loadSuppliers();
    }

    public function loadSuppliers()
    {
        $this->suppliers = Supplier::orderBy('name')->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $supplier = Supplier::findOrFail($id);
        
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->contact_person = $supplier->contact_person;
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->website = $supplier->website;
        $this->address = $supplier->address;
        $this->notes = $supplier->notes;
        $this->lead_time_land_days = $supplier->lead_time_land_days;
        $this->lead_time_air_days = $supplier->lead_time_air_days;
        $this->lead_time_sea_days = $supplier->lead_time_sea_days;
        $this->lead_time_train_days = $supplier->lead_time_train_days;
        $this->shipping_method = $supplier->shipping_method ?? 'land';
        
        $this->dynamic_links = is_array($supplier->dynamic_links) ? $supplier->dynamic_links : [];

        $this->isEditing = true;
        $this->showModal = true;
    }

    public function save()
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('validation-failed');
            throw $e;
        }

        $data = [
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'address' => $this->address,
            'notes' => $this->notes,
            'lead_time_land_days' => $this->lead_time_land_days,
            'lead_time_air_days' => $this->lead_time_air_days,
            'lead_time_sea_days' => $this->lead_time_sea_days,
            'lead_time_train_days' => $this->lead_time_train_days,
            'shipping_method' => $this->shipping_method,
            'dynamic_links' => array_values($this->dynamic_links), // Re-index array
        ];

        if ($this->isEditing) {
            Supplier::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: 'Lieferant erfolgreich aktualisiert.', type: 'success');
        } else {
            Supplier::create($data);
            $this->dispatch('toast', message: 'Neuer Lieferant angelegt.', type: 'success');
        }

        $this->showModal = false;
        $this->loadSuppliers();
    }

    public function delete($id)
    {
        Supplier::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Lieferant gelöscht.', type: 'info');
        $this->loadSuppliers();
    }

    public function addLink()
    {
        $this->dynamic_links[] = ['title' => '', 'url' => ''];
    }

    public function removeLink($index)
    {
        unset($this->dynamic_links[$index]);
        $this->dynamic_links = array_values($this->dynamic_links);
    }

    public function resetForm()
    {
        $this->reset(['name', 'contact_person', 'email', 'phone', 'website', 'address', 'notes', 'lead_time_land_days', 'lead_time_air_days', 'lead_time_sea_days', 'lead_time_train_days', 'shipping_method', 'dynamic_links', 'editingId']);
        $this->shipping_method = 'land';
        $this->dynamic_links = [];
        $this->showModal = false;
        $this->isEditing = false;
    }

    public function render()
    {
        return view('livewire.shop.product.product-suppliers');
    }
}
