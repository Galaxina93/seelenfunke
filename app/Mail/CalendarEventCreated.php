<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use App\Models\Management\ManagementCalendarEvent;

class CalendarEventCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $event;
    public array $eventsList = [];
    public $googleCalendarUrl;
    public ?string $customMessage;
    public ?string $agentName;
    public ?string $location;
    public ?string $attendeeEmail;
    public ?string $icsFilename;

    /**
     * Create a new message instance.
     */
    public function __construct(
        ManagementCalendarEvent $event,
        array $additionalEvents = [],
        ?string $customMessage = null,
        ?string $agentName = null,
        ?string $location = null,
        ?string $attendeeEmail = null,
        ?string $icsFilename = null
    ) {
        $this->event = $event;
        $this->eventsList = array_values(array_merge([$event], $additionalEvents));
        $this->customMessage = $customMessage;
        $this->agentName = $agentName;
        $this->location = $location;
        $this->attendeeEmail = $attendeeEmail;
        $this->icsFilename = $icsFilename;
        $this->googleCalendarUrl = $this->generateGoogleCalendarUrl();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $count = count($this->eventsList);
        $subject = $count > 1
            ? "Kalender-Export ({$count} Termine): " . ($this->event->title ?? 'Termine')
            : 'Termin-Einladung (ICS): ' . ($this->event->title ?? 'Termin');

        return new Envelope(
            subject: $subject,
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
                'eventsList' => $this->eventsList,
                'googleUrl' => $this->googleCalendarUrl,
                'customMessage' => $this->customMessage,
                'agentName' => $this->agentName,
                'location' => $this->location,
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
        $filename = $this->icsFilename;
        if (!$filename) {
            if (count($this->eventsList) > 1) {
                $filename = 'termine_' . now()->format('Y-m-d') . '.ics';
            } else {
                $slug = Str::slug($this->event->title ?? 'termin') ?: 'termin';
                $datePart = $this->event->start_date ? $this->event->start_date->format('Y-m-d') : now()->format('Y-m-d');
                $filename = "termin_{$slug}_{$datePart}.ics";
            }
        }

        return [
            Attachment::fromData(fn () => $icsContent, $filename)
                ->withMime('text/calendar; charset=UTF-8; method=REQUEST'),
        ];
    }

    /**
     * Generate the .ics file content dynamically (supports single and multiple events).
     */
    public function generateIcsContent(): string
    {
        return self::buildIcsForEvents($this->eventsList, [
            'location' => $this->location,
            'attendee_email' => $this->attendeeEmail,
            'agent_name' => $this->agentName,
        ]);
    }

    /**
     * Builds an RFC 5545 compliant VCALENDAR (.ics) string for one or more events.
     */
    public static function buildIcsForEvents(iterable $events, array $options = []): string
    {
        $organizerEmail = config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de';
        $organizerName = $options['agent_name'] ?? 'Mein-Seelenfunke Kalender';
        $defaultLocation = $options['location'] ?? null;
        $attendeeEmail = $options['attendee_email'] ?? null;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mein-Seelenfunke//KI-Kalender v2.0//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'X-WR-CALNAME:Mein-Seelenfunke Termine',
            'X-WR-TIMEZONE:Europe/Berlin',
        ];

        $dtstamp = now()->timezone('UTC')->format('Ymd\THis\Z');

        foreach ($events as $ev) {
            $uid = !empty($ev->ics_uid) ? $ev->ics_uid : (($ev->id ?? uniqid('event_')) . '@mein-seelenfunke.de');
            $startCarbon = $ev->start_date ? \Carbon\Carbon::parse($ev->start_date) : now();
            $endCarbon = $ev->end_date ? \Carbon\Carbon::parse($ev->end_date) : $startCarbon->copy()->addHour();

            if (!empty($ev->is_all_day)) {
                $startStr = $startCarbon->format('Ymd');
                $endStr = $endCarbon->copy()->addDay()->format('Ymd');
                $startTag = "DTSTART;VALUE=DATE:{$startStr}";
                $endTag = "DTEND;VALUE=DATE:{$endStr}";
            } else {
                $startStr = $startCarbon->copy()->timezone('UTC')->format('Ymd\THis\Z');
                $endStr = $endCarbon->copy()->timezone('UTC')->format('Ymd\THis\Z');
                $startTag = "DTSTART:{$startStr}";
                $endTag = "DTEND:{$endStr}";
            }

            $summary = self::escapeIcsText($ev->title ?? 'Termin');
            $description = self::escapeIcsText($ev->description ?? '');
            $evLocation = $ev->location ?? $defaultLocation ?? '';
            $category = strtoupper(self::escapeIcsText($ev->category ?? 'TERMIN'));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . $dtstamp;
            $lines[] = $startTag;
            $lines[] = $endTag;
            $lines[] = 'SUMMARY:' . $summary;

            if ($description !== '') {
                $lines[] = 'DESCRIPTION:' . $description;
            }
            if (!empty($evLocation)) {
                $lines[] = 'LOCATION:' . self::escapeIcsText((string) $evLocation);
            }
            if ($category !== '') {
                $lines[] = 'CATEGORIES:' . $category;
            }

            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'SEQUENCE:0';
            $lines[] = 'ORGANIZER;CN="' . self::escapeIcsText($organizerName) . '":mailto:' . $organizerEmail;

            if (!empty($attendeeEmail) && filter_var($attendeeEmail, FILTER_VALIDATE_EMAIL)) {
                $lines[] = 'ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE:mailto:' . $attendeeEmail;
            }

            // Recurrence Rule (RRULE)
            if (!empty($ev->recurrence)) {
                $freqMap = [
                    'daily' => 'DAILY',
                    'taeglich' => 'DAILY',
                    'weekly' => 'WEEKLY',
                    'woechentlich' => 'WEEKLY',
                    'monthly' => 'MONTHLY',
                    'monatlich' => 'MONTHLY',
                    'yearly' => 'YEARLY',
                    'jaehrlich' => 'YEARLY',
                ];
                $recKey = strtolower(trim((string) $ev->recurrence));
                $freq = $freqMap[$recKey] ?? (in_array(strtoupper($recKey), ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true) ? strtoupper($recKey) : null);
                if ($freq) {
                    $rrule = "RRULE:FREQ={$freq}";
                    if (!empty($ev->recurrence_end_date)) {
                        $until = \Carbon\Carbon::parse($ev->recurrence_end_date)->format('Ymd') . 'T235959Z';
                        $rrule .= ";UNTIL={$until}";
                    }
                    $lines[] = $rrule;
                }
            }

            // Reminder / Alarm (VALARM)
            $reminderMin = isset($ev->reminder_minutes) && is_numeric($ev->reminder_minutes)
                ? max(1, (int) $ev->reminder_minutes)
                : 30;
            $lines[] = 'BEGIN:VALARM';
            $lines[] = "TRIGGER:-PT{$reminderMin}M";
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Erinnerung: ' . $summary;
            $lines[] = 'END:VALARM';

            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    private static function escapeIcsText(string $text): string
    {
        $clean = str_replace(["\r\n", "\r"], "\n", $text);
        $clean = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $clean);
        return str_replace("\n", '\\n', $clean);
    }

    /**
     * Generate the Google Calendar Add URL.
     */
    private function generateGoogleCalendarUrl(): string
    {
        $title = urlencode($this->event->title ?? 'Termin');
        $details = urlencode($this->event->description ?? '');
        $loc = urlencode($this->location ?? ($this->event->location ?? ''));

        if ($this->event->is_all_day) {
            $start = $this->event->start_date ? $this->event->start_date->format('Ymd') : now()->format('Ymd');
            $end = $this->event->end_date ? $this->event->end_date->copy()->addDay()->format('Ymd') : now()->addDay()->format('Ymd');
            $dates = "{$start}/{$end}";
        } else {
            $start = $this->event->start_date ? $this->event->start_date->copy()->timezone('UTC')->format('Ymd\THis\Z') : now()->timezone('UTC')->format('Ymd\THis\Z');
            $end = $this->event->end_date ? $this->event->end_date->copy()->timezone('UTC')->format('Ymd\THis\Z') : $start;
            $dates = "{$start}/{$end}";
        }

        $url = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dates}&details={$details}";
        if ($loc !== '') {
            $url .= "&location={$loc}";
        }
        return $url;
    }
}
