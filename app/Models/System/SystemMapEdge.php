<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SystemMapEdge extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'map_id', 'source_id', 'target_id', 'label', 'status'
    ];

    public function sourceNode()
    {
        return $this->belongsTo(SystemMapNode::class, 'source_id');
    }

    public function targetNode()
    {
        return $this->belongsTo(SystemMapNode::class, 'target_id');
    }
}
