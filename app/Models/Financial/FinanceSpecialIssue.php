<?php

namespace App\Models\Financial;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceSpecialIssue extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = [
        'execution_date' => 'date',
        'amount' => 'decimal:2',
        'is_business' => 'boolean'
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
