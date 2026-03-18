<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'password' => 'encrypted',
        'is_default' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(MailMessage::class);
    }

    public function rules()
    {
        return $this->hasMany(MailRule::class);
    }

    public function folders()
    {
        return $this->hasMany(MailFolder::class);
    }
}
