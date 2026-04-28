<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    // Wir erlauben das Schreiben aller Felder (Name, Slug, Type)
    protected $guarded = [];

    // Optional: Falls du später doch wissen willst, welche Produkte diesen Key nutzen (per JSON Search)
    // Das ist aber rein für Analysen, nicht für die Kernlogik nötig.
    /*
    public function findProductsUsingThisAttribute()
    {
        return \App\Models\Product\Product::where('attributes->' . $this->name, '!=', null)->get();
    }
    */
}
