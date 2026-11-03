<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeeInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        $company = function_exists('shop_setting') ? shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')) : 'Mein Seelenfunke';

        return new Envelope(
            subject: 'Deine Einladung ins Team von ' . $company,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.employee_invitation_mail',
        );
    }
}
