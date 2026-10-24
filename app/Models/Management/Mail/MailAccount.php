<?php

namespace App\Models\Management\Mail;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'signature',
        'is_default',
        'is_commercial',
        'status',
        'last_sync_at',
    ];

    protected $hidden = [
        'password',
        'imap_password',
        'smtp_password',
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'is_default' => 'boolean',
        'is_commercial' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(function (MailAccount $account) {
            foreach ($account->messages()->get() as $message) {
                $message->delete();
            }
        });
    }

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
