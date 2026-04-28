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
        Notification::route('mail', $emailData['to'])
            ->notify(new globalNotification($emailData));
    }

}
