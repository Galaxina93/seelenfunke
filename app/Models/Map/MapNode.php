<?php

namespace App\Models\Map;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MapNode extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'label', 'description', 'icon', 'type', 'status', 'pos_x', 'pos_y'
    ];

    public function sourceEdges()
    {
        return $this->hasMany(MapEdge::class, 'source_id');
    }

    public function targetEdges()
    {
        return $this->hasMany(MapEdge::class, 'target_id');
    }
}
