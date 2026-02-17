<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FunkiVoucher extends Model
{
    use HasFactory;

    protected $table = 'funki_vouchers';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'title', 'code', 'type', 'value', 'used_count', 'usage_limit',
        'min_order_value', 'valid_from', 'valid_until',
        'usage_limit', 'used_count', 'is_active',
        'mode', 'trigger_event', 'days_offset', 'validity_days'
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'integer',
        'min_order_value' => 'integer',
        'used_count' => 'integer',
        'usage_limit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(fn ($model) => $model->id = $model->id ?? (string) Str::uuid());
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now());
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) return false;

        $now = now();
        if ($this->valid_from && $now->lt($this->valid_from)) return false;
        if ($this->valid_until && $now->gt($this->valid_until)) return false;

        return true;
    }

    public function isPersonalEvent()
    {
        return in_array($this->trigger_event, ['birthday', 'registered_date']);
    }
}
