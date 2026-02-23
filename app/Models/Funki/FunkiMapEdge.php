<?php

namespace App\Models\Funki;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FunkiMapEdge extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'source_id', 'target_id', 'label', 'status'
    ];

    public function sourceNode()
    {
        return $this->belongsTo(FunkiMapNode::class, 'source_id');
    }

    public function targetNode()
    {
        return $this->belongsTo(FunkiMapNode::class, 'target_id');
    }
}
