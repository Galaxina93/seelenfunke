<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketingHerzensprojektAction extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'allocated_amount_cents' => 'integer',
        'completed_at' => 'datetime',
        'photos' => 'array',
        'is_public' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(MarketingHerzensprojektTransaction::class, 'action_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->allocated_amount_cents / 100, 2, ',', '.') . ' €';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'in_progress' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            default => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Erfolgreich übergeben',
            'in_progress' => 'In Umsetzung',
            default => 'Geplant',
        };
    }
}
