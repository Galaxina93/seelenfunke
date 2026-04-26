<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Models\Management\ManagementCalendarEvent;

class CalendarEventCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $event;
    public $googleCalendarUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(ManagementCalendarEvent $event)
    {
        $this->event = $event;
        $this->googleCalendarUrl = $this->generateGoogleCalendarUrl();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neuer Termin: ' . $this->event->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'global.mails.calendar-event-created',
            with: [
                'event' => $this->event,
                'googleUrl' => $this->googleCalendarUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $icsContent = $this->generateIcsContent();

        return [
            Attachment::fromData(fn () => $icsContent, 'termin.ics')
                ->withMime('text/calendar'),
        ];
    }

    /**
     * Generate the .ics file content dynamically.
     */
    private function generateIcsContent(): string
    {
        $uid = $this->event->ics_uid ?? uniqid('event_') . '@seelenfunke';
        $dtstamp = now()->timezone('UTC')->format('Ymd\THis\Z');
        
        $start = $this->event->start_date ? $this->event->start_date->timezone('UTC')->format('Ymd\THis\Z') : $dtstamp;
        $end = $this->event->end_date ? $this->event->end_date->timezone('UTC')->format('Ymd\THis\Z') : $start;

        // If it's an all day event, format it differently (YYYYMMDD)
        if ($this->event->is_all_day) {
            $start = $this->event->start_date ? $this->event->start_date->format('Ymd') : now()->format('Ymd');
            $end = $this->event->end_date ? $this->event->end_date->copy()->addDay()->format('Ymd') : now()->addDay()->format('Ymd');
            
            $startTag = "DTSTART;VALUE=DATE:{$start}";
            $endTag = "DTEND;VALUE=DATE:{$end}";
        } else {
            $startTag = "DTSTART:{$start}";
            $endTag = "DTEND:{$end}";
        }

        $description = $this->event->description ? str_replace("\n", "\\n", $this->event->description) : '';
        $summary = $this->event->title ?? 'Termin';

        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Seelenfunke//Kalender//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            $startTag,
            $endTag,
            'SUMMARY:' . $summary,
            'DESCRIPTION:' . $description,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR'
        ];

        return implode("\r\n", $ics) . "\r\n";
    }

    /**
     * Generate the Google Calendar Add URL.
     */
    private function generateGoogleCalendarUrl(): string
    {
        $title = urlencode($this->event->title ?? 'Termin');
        $details = urlencode($this->event->description ?? '');
        
        if ($this->event->is_all_day) {
            $start = $this->event->start_date ? $this->event->start_date->format('Ymd') : now()->format('Ymd');
            $end = $this->event->end_date ? $this->event->end_date->copy()->addDay()->format('Ymd') : now()->addDay()->format('Ymd');
            $dates = "{$start}/{$end}";
        } else {
            $start = $this->event->start_date ? $this->event->start_date->timezone('UTC')->format('Ymd\THis\Z') : now()->timezone('UTC')->format('Ymd\THis\Z');
            $end = $this->event->end_date ? $this->event->end_date->timezone('UTC')->format('Ymd\THis\Z') : $start;
            $dates = "{$start}/{$end}";
        }

        return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dates}&details={$details}";
    }
}
