<?php

namespace App\Models\Management\Mail;

use Illuminate\Database\Eloquent\Model;

class MailAttachment extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (MailAttachment $attachment) {
            if (!empty($attachment->path) && !str_contains((string) $attachment->path, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($attachment->path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
            }
        });
    }

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
