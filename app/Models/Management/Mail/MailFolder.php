<?php

namespace App\Models\Management\Mail;

use Illuminate\Database\Eloquent\Model;

class MailFolder extends Model
{
    protected $guarded = [];

    public function account()
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
