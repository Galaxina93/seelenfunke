<?php

namespace App\Models\Accounting;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'admin_id',
        'name',
        'is_business',
        'usage_count'
    ];

    protected $casts = [
        'is_business' => 'boolean',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
