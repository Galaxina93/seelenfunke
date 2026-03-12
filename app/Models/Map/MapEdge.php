<?php

namespace App\Models\Map;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class MapEdge extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'map_id', 'source_id', 'target_id', 'label', 'status'
    ];

    public function sourceNode()
    {
        return $this->belongsTo(MapNode::class, 'source_id');
    }

    public function targetNode()
    {
        return $this->belongsTo(MapNode::class, 'target_id');
    }
}
