<?php

namespace App\Models\Financial;

use App\Models\Admin\Admin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceCategory extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
