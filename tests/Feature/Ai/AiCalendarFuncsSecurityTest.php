<?php

namespace Tests\Feature\Ai;

use App\Models\Management\ManagementCalendarEvent;
use App\Services\AI\Functions\AiCalendarFuncs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCalendarFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_calendar_event_rejects_wildcard_and_too_short_queries()
    {
        $dummy = new class {
            use AiCalendarFuncs;
        };

        // Create important calendar events
        $event = ManagementCalendarEvent::create([
            'title' => 'Wichtiges Investoren-Meeting',
            'start_date' => now()->addDay(),
            'end_date' => now()->addDay()->addHour(),
            'is_all_day' => false,
            'category' => 'termin',
        ]);

        // Attempt deletion with short query
        $resShort = $dummy::executeDeleteCalendarEvent([
            'title_suche' => 'Wi',
        ]);
        $this->assertEquals('error', $resShort['status']);
        $this->assertDatabaseHas('management_calendar_events', ['id' => $event->id]);

        // Attempt deletion with single wildcard
        $resWildcard = $dummy::executeDeleteCalendarEvent([
            'title_suche' => '%',
        ]);
        $this->assertEquals('error', $resWildcard['status']);
        $this->assertDatabaseHas('management_calendar_events', ['id' => $event->id]);

        // Valid deletion with sufficient title characters
        $resValid = $dummy::executeDeleteCalendarEvent([
            'title_suche' => 'Investoren-Meeting',
        ]);
        $this->assertEquals('success', $resValid['status']);
        $this->assertDatabaseMissing('management_calendar_events', ['id' => $event->id]);
    }

    public function test_create_and_update_event_clamps_invalid_end_date()
    {
        $dummy = new class {
            use AiCalendarFuncs;
        };

        // Create event with end_date before start_date
        $resCreate = $dummy::executeCreateCalendarEvent([
            'title' => 'Projektbesprechung',
            'start_date' => '2026-11-01 14:00:00',
            'end_date' => '2026-11-01 10:00:00', // Invalid: 4 hours earlier
        ]);

        $this->assertEquals('success', $resCreate['status']);
        $createdEvent = ManagementCalendarEvent::find($resCreate['event_id']);
        $this->assertNotNull($createdEvent);
        // end_date should be corrected to start_date + 1 hour
        $this->assertEquals('2026-11-01 15:00:00', $createdEvent->end_date->format('Y-m-d H:i:s'));

        // Update event with invalid end_date
        $resUpdate = $dummy::executeUpdateCalendarEvent([
            'event_id' => $createdEvent->id,
            'end_date' => '2026-11-01 12:00:00', // Invalid: before start_date
        ]);

        $this->assertEquals('success', $resUpdate['status']);
        $createdEvent->refresh();
        $this->assertEquals('2026-11-01 15:00:00', $createdEvent->end_date->format('Y-m-d H:i:s'));
    }
}
