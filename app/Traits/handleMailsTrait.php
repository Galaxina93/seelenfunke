<?php

namespace App\Traits;

use App\Notifications\globalNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\WithFileUploads;

trait handleMailsTrait
{
    use WithFileUploads;

    public function sendMail(array $emailData): void
    {
        $to = $emailData['to'] ?? shop_setting('company_email') ?: shop_setting('owner_email') ?: config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
        
        if (empty($to)) {
            \Illuminate\Support\Facades\Log::warning('SendMail failed: No recipient address provided and no default fallback available.');
            return;
        }

        Notification::route('mail', $to)
            ->notify(new globalNotification($emailData));
    }

}
