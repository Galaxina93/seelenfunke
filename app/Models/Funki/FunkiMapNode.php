<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FunkiMapNode extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'label', 'description', 'icon', 'type', 'status', 'pos_x', 'pos_y'
    ];

    public function sourceEdges()
    {
        return $this->hasMany(FunkiMapEdge::class, 'source_id');
    }

    public function targetEdges()
    {
        return $this->hasMany(FunkiMapEdge::class, 'target_id');
    }
}
