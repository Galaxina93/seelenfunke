<?php

namespace Tests\Feature\Ai;

use App\Services\AI\Functions\AiHolidayPlannerFuncs;
use App\Services\AI\Functions\AiLaserFuncs;
use App\Services\AI\Mails\AiAgentMessageMail;
use App\Services\AI\Mails\AiHolidayPlanMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiLaserAndHolidaySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_laser_generate_pdf_and_mail_validates_email_and_generates_file()
    {
        Storage::fake('public');
        Storage::fake('workspace');
        Mail::fake();

        $dummyClass = new class {
            use AiLaserFuncs;
        };

        // Call with malformed recipient email
        $res = $dummyClass::executeLaserGeneratePdfAndMail([
            'title' => "Laserschutz-Unterweisung\r\nBcc: evil@attacker.com",
            'content' => '## Sicherheitsregeln Klasse 4 Laser',
            'target_action' => 'email',
            'recipient_email' => 'invalid-email-address@@test',
        ]);

        $this->assertEquals('success', $res['status']);
        // Email sent to fallback safe address, not malformed one
        Mail::assertSent(AiAgentMessageMail::class, function ($mail) {
            return $mail->hasTo(config('mail.from.address') ?: 'kontakt@mein-seelenfunke.de')
                && !str_contains($mail->subject, "\r")
                && !str_contains($mail->subject, "\n");
        });
    }

    public function test_holiday_generate_pdf_plan_sanitizes_title_and_recipient()
    {
        Storage::fake('public');
        Mail::fake();

        $dummyClass = new class {
            use AiHolidayPlannerFuncs;
        };

        $res = $dummyClass::executeHolidayGeneratePdfPlan([
            'title' => '???///!!!',
            'description' => 'Sommerurlaub Test',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-10',
            'travel_logistics' => [],
            'target_action' => 'email',
            'recipient_email' => 'malformed-email',
        ]);

        $this->assertEquals('success', $res['status']);
        Mail::assertSent(AiHolidayPlanMail::class);
    }
}
