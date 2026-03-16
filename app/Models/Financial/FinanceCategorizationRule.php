<?php

namespace App\Models\Financial;

use Illuminate\Database\Eloquent\Model;

class FinanceCategorizationRule extends Model
{
    protected $fillable = [
        'admin_id',
        'search_term',
        'finance_category_id',
        'finance_cost_item_id'
    ];

    public function category()
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function costItem()
    {
        return $this->belongsTo(FinanceCostItem::class, 'finance_cost_item_id');
    }
}
