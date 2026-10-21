<?php

namespace Tests\Feature\Ai;

use App\Models\Management\ManagementContact;
use App\Services\AI\Functions\AiContactFuncs;
use App\Services\AI\Functions\AiMailFuncs;
use App\Services\AI\Mails\AiAgentMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AiMailAndContactSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_mail_rejects_invalid_email_format_and_cleans_subject_headers()
    {
        Mail::fake();

        $dummyClass = new class {
            use AiMailFuncs;
        };

        // Rejection of invalid email format
        $resInvalid = $dummyClass::executeSendEmail([
            'to_address' => 'not-an-email-address',
            'subject' => 'Test Subject',
            'body' => 'Test Body',
            'agent_name' => 'MailBot',
        ]);
        $this->assertEquals('error', $resInvalid['status']);

        // Success with valid email and CRLF stripped
        $resValid = $dummyClass::executeSendEmail([
            'to_address' => 'empfaenger@seelenfunke-partner.de',
            'subject' => "Statusbericht\r\nBcc: evil@attacker.com",
            'body' => 'Guten Tag, anbei die Rückmeldung.',
            'agent_name' => 'MailBot',
        ]);
        $this->assertEquals('success', $resValid['status']);

        Mail::assertSent(AiAgentMessageMail::class, function ($mail) {
            return $mail->hasTo('empfaenger@seelenfunke-partner.de')
                && !str_contains($mail->subject, "\r")
                && !str_contains($mail->subject, "\n");
        });
    }

    public function test_ai_contact_create_and_add_info_enforce_character_bounds()
    {
        $dummyClass = new class {
            use AiContactFuncs;
        };

        $hugeFirstName = str_repeat('A', 250);
        $hugeLastName = str_repeat('B', 250);

        $resCreate = $dummyClass::executeContactCreate([
            'first_name' => $hugeFirstName,
            'last_name' => $hugeLastName,
            'relation_type' => 'Kooperationspartner',
        ]);

        $this->assertEquals('success', $resCreate['status']);

        $contact = ManagementContact::first();
        $this->assertNotNull($contact);
        $this->assertEquals(100, strlen($contact->first_name));
        $this->assertEquals(100, strlen($contact->last_name));

        $hugeContent = str_repeat('Info ', 2000); // 10,000 chars
        $resInfo = $dummyClass::executeContactAddInfo([
            'first_name' => $contact->first_name,
            'content' => $hugeContent,
            'title' => 'Wichtiges Detail',
        ]);
        $this->assertEquals('success', $resInfo['status']);

        $contact->refresh();
        $this->assertLessThanOrEqual(5200, strlen($contact->ai_learned_facts));
    }
}
