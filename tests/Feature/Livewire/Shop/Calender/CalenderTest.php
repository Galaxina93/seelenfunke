<?php

namespace Tests\Feature\Livewire\Shop\Calender;

use App\Livewire\Shop\Calender\Calender;
use App\Models\CalendarEvent;
use App\Services\AI\Functions\AiSupportFuncs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalenderTest extends TestCase
{
    use RefreshDatabase;

    // Helper class implementation to test the trait statically
    private $aiFunctionsDummy;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->aiFunctionsDummy = new class {
            use AiSupportFuncs;
        };
    }

    // ============================================
    // LIVEWIRE UI TESTS (Calender.php)
    // ============================================

    #[Test]
    public function it_can_navigate_between_dates_and_views()
    {
        $now = Carbon::now();
        $component = Livewire::test(Calender::class);
        
        // Default is month view
        $component->assertSet('view', 'month');

        // Test next() in month view
        $component->call('next');
        $this->assertEquals($now->copy()->addMonth()->format('Y-m'), Carbon::parse($component->get('currentDate'))->format('Y-m'));

        // Test prev() in month view
        $component->call('prev');
        $component->call('prev');
        $this->assertEquals($now->copy()->subMonth()->format('Y-m'), Carbon::parse($component->get('currentDate'))->format('Y-m'));

        // Test today()
        $component->call('today');
        $this->assertEquals($now->format('Y-m-d'), Carbon::parse($component->get('currentDate'))->format('Y-m-d'));

        // Test view change
        $component->call('setView', 'week')
                  ->assertSet('view', 'week');
                  
        // Test goToDay
        $component->call('goToDay', '2026-10-15')
                  ->assertSet('view', 'day')
                  ->assertSet('currentDate', Carbon::parse('2026-10-15'));
    }

    #[Test]
    public function it_can_create_a_new_event_via_livewire()
    {
        Livewire::test(Calender::class)
            ->call('createEvent')
            ->set('editTitle', 'My Custom Event')
            ->set('editStartDate', '2026-05-10')
            ->set('editStartTime', '14:00')
            ->set('editEndDate', '2026-05-10')
            ->set('editEndTime', '15:00')
            ->set('editIsAllDay', false)
            ->set('editCategory', 'meeting')
            ->call('saveEvent')
            ->assertSet('showEditModal', false);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'My Custom Event',
            'category' => 'meeting',
            'is_all_day' => false,
            'start_date' => '2026-05-10 14:00:00',
            'end_date' => '2026-05-10 15:00:00'
        ]);
    }

    #[Test]
    public function it_can_edit_and_delete_an_existing_event()
    {
        $event = CalendarEvent::create([
            'title' => 'Old Event',
            'start_date' => '2026-01-01 10:00:00',
            'end_date' => '2026-01-01 11:00:00',
            'category' => 'general',
            'is_all_day' => false
        ]);

        Livewire::test(Calender::class)
            ->call('editEvent', $event->id)
            ->assertSet('editTitle', 'Old Event')
            ->set('editTitle', 'New Updated Event')
            ->call('saveEvent');

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'New Updated Event'
        ]);

        // Now Delete
        Livewire::test(Calender::class)
            ->call('editEvent', $event->id)
            ->call('deleteEvent');

        $this->assertDatabaseMissing('calendar_events', [
            'id' => $event->id
        ]);
    }

    #[Test]
    public function it_expands_recurring_events_properly()
    {
        // Freeze time to a guaranteed Monday so 'startOfWeek' math is absolutely predictable
        Carbon::setTestNow('2026-05-11 12:00:00'); // May 11, 2026 is a Monday

        CalendarEvent::create([
            'title' => 'Daily Standup',
            'start_date' => Carbon::now()->startOfWeek()->setTime(9, 0), // Mon 09:00
            'end_date' => Carbon::now()->startOfWeek()->setTime(10, 0), // Mon 10:00
            'is_all_day' => false,
            'category' => 'meeting',
            'recurrence' => 'daily', // Repeated every day
            'recurrence_end_date' => Carbon::parse('2099-01-01') // Set to far future
        ]);

        $component = Livewire::test(Calender::class)
            ->call('today')
            ->call('setView', 'week');

        // The property 'events' merges standard events with calculated recurring copies
        $events = $component->instance()->events;

        $recurringCount = collect($events)
            ->filter(fn($e) => $e->title === 'Daily Standup')
            ->filter(fn($e) => $e->start_date->between(
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek()
            ))
            ->count();
        
        // Endless daily recurrence inside the current 'week' view should yield exactly 7 instances
        $this->assertEquals(7, $recurringCount);

        // Reset the clock to current time
        Carbon::setTestNow();
    }

    #[Test]
    public function it_can_import_ics_files()
    {
        // By default, Livewire restricts temporary uploads strictly to images/pdfs. We temporarily disable this rule so the .ics payload isn't blocked by the HTTP pipeline
        config()->set('livewire.temporary_file_upload.rules', 'file|max:12288');

        // Use the physical test file provided by the user in public/testing but wrap it into a Livewire compatible fake upload
        $content = file_get_contents(public_path('testing/feiertage-deutschland.ics'));
        $file = UploadedFile::fake()->createWithContent('feiertage.ics', $content);

        $initialCount = CalendarEvent::count();

        // Testing session()->flash interactions in Livewire can be unpredictable. Instead, we rigidly assert Database mutations.
        Livewire::test(Calender::class)
            ->set('importFile', $file)
            ->call('importEvents')
            ->assertHasNoErrors();
            
        $this->assertGreaterThan($initialCount, CalendarEvent::count());
    }

    // ============================================
    // AI FUNCTIONS TRAIT TESTS (CalendarFunctions.php)
    // ============================================

    #[Test]
    public function ai_can_get_calendar_events_schema()
    {
        $schema = $this->aiFunctionsDummy::getAiSupportFuncsSchema();
        $this->assertIsArray($schema);
        $this->assertCount(5, $schema); // get, create, update, delete, get_tickets
        $this->assertEquals('get_calendar_events', $schema[0]['name']);
    }

    #[Test]
    public function ai_can_query_upcoming_calendar_events()
    {
        CalendarEvent::create([
            'title' => 'Zahnarzt',
            'start_date' => Carbon::now()->addDays(2)->setHour(10)->setMinute(0),
            'end_date' => Carbon::now()->addDays(2)->setHour(11)->setMinute(0),
            'is_all_day' => false,
            'category' => 'termin'
        ]);

        $result = $this->aiFunctionsDummy::executeGetCalendarEvents([
            'keyword' => 'Zahnarzt',
            'limit' => 1
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['events_count']);
        $this->assertEquals('Zahnarzt', $result['upcoming_events'][0]['title']);
    }

    #[Test]
    public function ai_can_create_a_calendar_event()
    {
        $result = $this->aiFunctionsDummy::executeCreateCalendarEvent([
            'title' => 'Steuerberater',
            'start_date' => '2026-06-15 14:00:00',
            'is_all_day' => false,
            'description' => 'Wichtig'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('event_id', $result);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'Steuerberater',
            'start_date' => '2026-06-15 14:00:00',
            'description' => 'Wichtig'
        ]);
    }

    #[Test]
    public function ai_can_update_an_existing_event()
    {
        $event = CalendarEvent::create([
            'title' => 'Altes Meeting',
            'start_date' => '2026-01-01 10:00:00',
            'end_date' => '2026-01-01 11:00:00',
            'category' => 'general',
            'is_all_day' => false
        ]);

        $result = $this->aiFunctionsDummy::executeUpdateCalendarEvent([
            'event_id' => (string) $event->id,
            'title' => 'Neues Meeting verschoben',
            'start_date' => '2026-01-02 10:00:00'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'Neues Meeting verschoben',
            'start_date' => '2026-01-02 10:00:00'
        ]);
    }

    #[Test]
    public function ai_can_delete_events()
    {
        $event = CalendarEvent::create([
            'title' => 'Bitte löschen AI',
            'start_date' => '2026-01-01 10:00:00',
            'end_date' => '2026-01-01 11:00:00',
            'category' => 'general',
            'is_all_day' => false
        ]);

        // Attempt delete by title
        $result = $this->aiFunctionsDummy::executeDeleteCalendarEvent([
            'title_suche' => 'Bitte löschen'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertDatabaseMissing('calendar_events', [
            'id' => $event->id
        ]);
    }
}
