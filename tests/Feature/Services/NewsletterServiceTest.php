<?php

namespace Tests\Feature\Services;

use App\Mail\AutomaticNewsletterMail;
use App\Models\NewsletterSubscriber;
use App\Models\FunkiNewsletter;
use App\Services\NewsletterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test; // <--- WICHTIG: Neuer Import
use Tests\TestCase;

class NewsletterServiceTest extends TestCase
{
    use RefreshDatabase;

    private NewsletterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NewsletterService::class);
    }

    #[Test] // <--- Neues Attribut statt /** @test */
    public function es_liefert_alle_definierten_events_zurueck()
    {
        $events = $this->service->getAvailableEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey('christmas', $events);
        $this->assertArrayHasKey('easter', $events);
        $this->assertArrayHasKey('valentines', $events);
    }

    #[Test]
    public function es_berechnet_feste_feiertage_korrekt()
    {
        $year = 2024;

        $christmas = $this->service->getHolidayDate('christmas', $year);
        $this->assertEquals('2024-12-24', $christmas->format('Y-m-d'));

        $valentines = $this->service->getHolidayDate('valentines', $year);
        $this->assertEquals('2024-02-14', $valentines->format('Y-m-d'));

        $newYear = $this->service->getHolidayDate('new_year', $year);
        $this->assertEquals('2024-01-01', $newYear->format('Y-m-d'));
    }

    #[Test]
    public function es_berechnet_variable_feiertage_korrekt()
    {
        // Ostern 2024
        $easter24 = $this->service->getHolidayDate('easter', 2024);
        $this->assertEquals('2024-03-31', $easter24->format('Y-m-d'));

        // Ostern 2025
        $easter25 = $this->service->getHolidayDate('easter', 2025);
        $this->assertEquals('2025-04-20', $easter25->format('Y-m-d'));

        // Muttertag 2024
        $mothersDay24 = $this->service->getHolidayDate('mothers_day', 2024);
        $this->assertEquals('2024-05-12', $mothersDay24->format('Y-m-d'));

        // Vatertag 2024
        $fathersDay24 = $this->service->getHolidayDate('fathers_day', 2024);
        $this->assertEquals('2024-05-09', $fathersDay24->format('Y-m-d'));

        // 1. Advent 2024
        $advent24 = $this->service->getHolidayDate('advent_1', 2024);
        $this->assertEquals('2024-12-01', $advent24->format('Y-m-d'));
    }

    #[Test]
    public function es_versendet_mails_nur_an_verifizierte_abonnenten()
    {
        Mail::fake();

        NewsletterSubscriber::factory()->create(['email' => 'user1@test.de', 'is_verified' => true]);
        NewsletterSubscriber::factory()->create(['email' => 'user2@test.de', 'is_verified' => true]);
        NewsletterSubscriber::factory()->create(['email' => 'spam@test.de', 'is_verified' => false]);

        $template = FunkiNewsletter::create([
            'target_event_key' => 'test',
            'title' => 'Test Titel',
            'subject' => 'Test Betreff',
            'content' => 'Hallo Welt',
            'days_offset' => 0,
            'is_active' => true
        ]);

        $sentCount = $this->service->sendBatch($template);

        $this->assertEquals(2, $sentCount);

        Mail::assertQueued(AutomaticNewsletterMail::class, function ($mail) use ($template) {
            return $mail->hasTo('user1@test.de') && $mail->template->id === $template->id;
        });

        Mail::assertNotQueued(AutomaticNewsletterMail::class, function ($mail) {
            return $mail->hasTo('spam@test.de');
        });
    }
}
