<?php

namespace App\Livewire\Shop\Product;

use Livewire\Attributes\Layout;

use App\Models\Product\ProductSupplier;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ProductSuppliers extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Produkte';

    public $suppliers;

    // Form fields
    public $name;
    public $company_name;
    
    // Address Data
    public $street;
    public $house_number;
    public $zip;
    public $city;
    public $country;
    public $country_code;
    
    // Contact Data
    public $contact_person;
    public $email;
    public $phone;
    public $website;
    public $notes;
    
    // Business Data
    public $tax_id;
    public $vat_id;
    public $bank_name;
    public $iban;
    public $bic;
    public $customer_number;
    
    // Conditions
    public $payment_terms;
    public $minimum_order_value; // in Cent
    public $shipping_costs; // in Cent

    // Lead times & Logistics
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
        'company_name' => 'nullable|string|max:255',
        'street' => 'nullable|string|max:255',
        'house_number' => 'nullable|string|max:255',
        'zip' => 'nullable|string|max:255',
        'city' => 'nullable|string|max:255',
        'country' => 'nullable|string|max:255',
        'country_code' => 'nullable|string|max:2',
        'contact_person' => 'nullable|string|max:255',
        'email' => 'nullable|email|max:255',
        'phone' => 'nullable|string|max:255',
        'website' => 'nullable|url|max:255',
        'notes' => 'nullable|string',
        'tax_id' => 'nullable|string|max:255',
        'vat_id' => 'nullable|string|max:255',
        'bank_name' => 'nullable|string|max:255',
        'iban' => 'nullable|string|max:255',
        'bic' => 'nullable|string|max:255',
        'customer_number' => 'nullable|string|max:255',
        'payment_terms' => 'nullable|string|max:255',
        'minimum_order_value' => 'nullable|integer|min:0',
        'shipping_costs' => 'nullable|integer|min:0',
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
        $this->suppliers = ProductSupplier::with('products')->orderBy('name')->get();
    }

    public function create()
    {
        $this->resetForm();
        $this->isEditing = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $supplier = ProductSupplier::findOrFail($id);
        
        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->company_name = $supplier->company_name;
        $this->street = $supplier->street;
        $this->house_number = $supplier->house_number;
        $this->zip = $supplier->zip;
        $this->city = $supplier->city;
        $this->country = $supplier->country;
        $this->country_code = $supplier->country_code;
        $this->contact_person = $supplier->contact_person;
        $this->email = $supplier->email;
        $this->phone = $supplier->phone;
        $this->website = $supplier->website;
        $this->notes = $supplier->notes;
        $this->tax_id = $supplier->tax_id;
        $this->vat_id = $supplier->vat_id;
        $this->bank_name = $supplier->bank_name;
        $this->iban = $supplier->iban;
        $this->bic = $supplier->bic;
        $this->customer_number = $supplier->customer_number;
        $this->payment_terms = $supplier->payment_terms;
        $this->minimum_order_value = $supplier->minimum_order_value;
        $this->shipping_costs = $supplier->shipping_costs;
        
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
            'company_name' => $this->company_name,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'notes' => $this->notes,
            'tax_id' => $this->tax_id,
            'vat_id' => $this->vat_id,
            'bank_name' => $this->bank_name,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'customer_number' => $this->customer_number,
            'payment_terms' => $this->payment_terms,
            'minimum_order_value' => $this->minimum_order_value,
            'shipping_costs' => $this->shipping_costs,
            'lead_time_land_days' => $this->lead_time_land_days,
            'lead_time_air_days' => $this->lead_time_air_days,
            'lead_time_sea_days' => $this->lead_time_sea_days,
            'lead_time_train_days' => $this->lead_time_train_days,
            'shipping_method' => $this->shipping_method,
            'dynamic_links' => array_values($this->dynamic_links), // Re-index array
        ];

        if ($this->isEditing) {
            ProductSupplier::findOrFail($this->editingId)->update($data);
            $this->dispatch('toast', message: 'Lieferant erfolgreich aktualisiert.', type: 'success');
        } else {
            ProductSupplier::create($data);
            $this->dispatch('toast', message: 'Neuer Lieferant angelegt.', type: 'success');
        }

        $this->showModal = false;
        $this->loadSuppliers();
    }

    public function delete($id)
    {
        ProductSupplier::findOrFail($id)->delete();
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
        $this->reset([
            'name', 'company_name', 'street', 'house_number', 'zip', 'city', 'country', 'country_code',
            'contact_person', 'email', 'phone', 'website', 'notes',
            'tax_id', 'vat_id', 'bank_name', 'iban', 'bic', 'customer_number',
            'payment_terms', 'minimum_order_value', 'shipping_costs',
            'lead_time_land_days', 'lead_time_air_days', 'lead_time_sea_days', 'lead_time_train_days', 
            'shipping_method', 'dynamic_links', 'editingId'
        ]);
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
