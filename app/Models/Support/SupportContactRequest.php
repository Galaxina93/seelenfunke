<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportContactRequest extends Model
{
    use HasUuids;

    protected $guarded = [];

    // Erzeugt automatisch eine eindeutige REQ- Nummer beim Erstellen
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->ticket_number)) {
                $model->ticket_number = 'REQ-' . date('y') . '-' . strtoupper(\Illuminate\Support\Str::random(6));
            }
        });
    }

    public function messages()
    {
        return $this->hasMany(SupportContactRequestMessage::class)->orderBy('created_at', 'asc');
    }
}
