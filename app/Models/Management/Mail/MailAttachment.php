<?php

namespace App\Models\Management\Mail;

use Illuminate\Database\Eloquent\Model;

class MailAttachment extends Model
{
    protected $guarded = [];

    public function message()
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }
    
    /**
     * Get the URL to stream the attachment.
     */
    public function getStreamUrlAttribute()
    {
        return route('crm.mail-attachment', ['id' => $this->id]);
    }
}
