<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductPackaging extends Model
{
    protected $guarded = [];

    protected $casts = [
        'weight_grams' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function getMaterialTypes()
    {
        return [
            'paper' => 'Pappe, Papier & Karton',
            'plastic' => 'Kunststoffe',
            'glass' => 'Glas',
            'wood' => 'Holz',
            'tin' => 'Weißblech',
            'alu' => 'Aluminium',
            'composite' => 'Verbundmaterialien',
            'other' => 'Sonstige (Naturmaterialien)',
        ];
    }
}
