<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'is_archived' => 'boolean',
        'has_attachments' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    public function attachments()
    {
        return $this->hasMany(MailAttachment::class, 'mail_message_id');
    }
}
