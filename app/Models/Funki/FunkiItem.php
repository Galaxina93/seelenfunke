<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Model;

class FunkiItem extends Model
{
    protected $guarded = [];

    public function getRarityColorAttribute(): string
    {
        return match ($this->rarity) {
            'common' => 'bg-slate-100 text-slate-600 border-slate-200',
            'rare' => 'bg-blue-100 text-blue-700 border-blue-200 shadow-[0_0_10px_rgba(59,130,246,0.3)]',
            'epic' => 'bg-purple-100 text-purple-700 border-purple-200 shadow-[0_0_15px_rgba(168,85,247,0.4)]',
            'legendary' => 'bg-gradient-to-r from-amber-200 to-yellow-400 text-amber-900 border-amber-300 shadow-[0_0_20px_rgba(251,191,36,0.6)]',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    public function getRarityNameAttribute(): string
    {
        return match ($this->rarity) {
            'common' => 'Gewöhnlich',
            'rare' => 'Selten',
            'epic' => 'Episch',
            'legendary' => 'Legendär',
            default => 'Unbekannt',
        };
    }
}
