<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Prüft, ob der Gutschein generell gültig ist (unabhängig vom Warenkorb).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;

        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;

        return true;
    }
}
