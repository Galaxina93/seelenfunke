<?php

namespace App\Mail;

use App\Models\Customer\Customer;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketUpdateMailToCustomer extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public Ticket $ticket;

    public function __construct(Customer $customer, Ticket $ticket)
    {
        $this->customer = $customer;
        $this->ticket = $ticket;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neuigkeiten zu deinem Ticket: ' . $this->ticket->ticket_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'global.mails.ticket_update_mail_to_customer',
        );
    }
}
