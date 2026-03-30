<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SupportCustomerChat extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $keyType = 'string';
    public $incrementing = false;

    public function messages()
    {
        return $this->hasMany(SupportCustomerChatMessage::class)->orderBy('created_at', 'asc');
    }
    
    // Status helpers
    public function getIsOpenAttribute()
    {
        return $this->status === 'open';
    }
    
    public function getIsResolvedAttribute()
    {
        return $this->status === 'resolved';
    }
    
    public function getNeedsEmployeeAttribute()
    {
        return $this->status === 'needs_employee';
    }
}
