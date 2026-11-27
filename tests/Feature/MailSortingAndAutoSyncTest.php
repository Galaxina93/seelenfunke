<?php

namespace Tests\Feature;

use App\Console\Commands\FetchMailsCommand;
use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Webklex\PHPIMAP\Attribute;

class MailSortingAndAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateAdmin(): Admin
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-mail-test@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    public function test_normalize_imap_date_converts_webklex_attribute_and_mixed_timezones_to_berlin(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);
        $refNow = Carbon::parse('2026-11-25 19:30:00', 'Europe/Berlin');

        // Microsoft US Pacific (-0800): 08:24:56 -0800 == 17:24:56 +0100 (Europe/Berlin)
        $pstCarbon = Carbon::parse('Wed, 25 Nov 2026 08:24:56 -0800');
        $webklexAttr = new Attribute('date', $pstCarbon);

        $normalizedAttr = FetchMailsCommand::normalizeImapDate($webklexAttr, $refNow);
        $this->assertNotNull($normalizedAttr);
        $this->assertSame('Europe/Berlin', $normalizedAttr->timezoneName);
        $this->assertSame('2026-11-25 17:24:56', $normalizedAttr->format('Y-m-d H:i:s'));

        // UTC (+0000): 17:59:24 +0000 == 18:59:24 +0100
        $utcNormalized = FetchMailsCommand::normalizeImapDate('Wed, 25 Nov 2026 17:59:24 +0000', $refNow);
        $this->assertSame('2026-11-25 18:59:24', $utcNormalized->format('Y-m-d H:i:s'));

        // Bogus future date (> +10 min) gets clamped to reference now
        $futureNormalized = FetchMailsCommand::normalizeImapDate('Fri, 25 Nov 2033 12:00:00 +0000', $refNow);
        $this->assertSame('2026-11-25 19:30:00', $futureNormalized->format('Y-m-d H:i:s'));
    }

    public function test_mail_message_mutator_normalizes_timezone_and_all_inboxes_sorts_chronologically(): void
    {
        config(['app.timezone' => 'Europe/Berlin']);
        $this->authenticateAdmin();

        $tOnline = MailAccount::create([
            'name' => 'T-Online',
            'email' => 'alina.stone@t-online.de',
            'imap_host' => 'secureimap.t-online.de',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'securesmtp.t-online.de',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'password' => '',
            'status' => 'connected',
        ]);

        $elocrest = MailAccount::create([
            'name' => 'EloCrest',
            'email' => 'support@elocrest.gg',
            'imap_host' => 'mail.agenturserver.de',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'mail.agenturserver.de',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'password' => '',
            'status' => 'connected',
        ]);

        // 12:30 CET (+0100) German mail
        $simpleFax = MailMessage::create([
            'mail_account_id' => $tOnline->id,
            'message_id' => 'msg-simplefax',
            'folder' => 'INBOX',
            'subject' => 'Ihr Guthaben ist fast aufgebraucht',
            'from_email' => 'noanswer@simple-fax.de',
            'to_email' => $tOnline->email,
            'received_at' => Carbon::parse('Wed, 25 Nov 2026 12:30:45 +0100'),
        ]);

        // 17:10 CET (sent as 13:10 -0300)
        $elocrestMail = MailMessage::create([
            'mail_account_id' => $elocrest->id,
            'message_id' => 'msg-elocrest',
            'folder' => 'INBOX',
            'subject' => 'Passwort zurücksetzen',
            'from_email' => 'noreply@elocrest.gg',
            'to_email' => $elocrest->email,
            'received_at' => Carbon::parse('Wed, 25 Nov 2026 13:10:37 -0300'),
        ]);

        // 17:24 CET (sent as 08:24 -0800 Microsoft PST -> previously buried below 12:30!)
        $microsoftCode = MailMessage::create([
            'mail_account_id' => $tOnline->id,
            'message_id' => 'msg-ms-pst',
            'folder' => 'INBOX',
            'subject' => 'Sicherheitscode Microsoft',
            'from_email' => 'account-security-noreply@accountprotection.microsoft.com',
            'to_email' => $tOnline->email,
            'received_at' => Carbon::parse('Wed, 25 Nov 2026 08:24:56 -0800'),
        ]);

        // 18:59 CET (sent as 17:59 +0000 UTC)
        $microsoftStore = MailMessage::create([
            'mail_account_id' => $tOnline->id,
            'message_id' => 'msg-ms-utc',
            'folder' => 'INBOX',
            'subject' => 'Action needed Microsoft Store',
            'from_email' => 'noreply@microsoft.com',
            'to_email' => $tOnline->email,
            'received_at' => Carbon::parse('Wed, 25 Nov 2026 17:59:24 +0000'),
        ]);

        // Verify raw MySQL DATETIME values are all normalized to Europe/Berlin
        $this->assertSame('2026-11-25 18:59:24', $microsoftStore->fresh()->getRawOriginal('received_at'));
        $this->assertSame('2026-11-25 17:24:56', $microsoftCode->fresh()->getRawOriginal('received_at'));
        $this->assertSame('2026-11-25 17:10:37', $elocrestMail->fresh()->getRawOriginal('received_at'));
        $this->assertSame('2026-11-25 12:30:45', $simpleFax->fresh()->getRawOriginal('received_at'));

        // Verify AllInboxes API returns exact newest-first chronological order across accounts
        $res = $this->getJson('/api/funki/emails/messages?folder=AllInboxes&global=1');
        $res->assertOk();
        $ids = collect($res->json('data.data'))->pluck('id')->all();
        $this->assertSame([
            $microsoftStore->id,
            $microsoftCode->id,
            $elocrestMail->id,
            $simpleFax->id,
        ], $ids);

        // Verify accounts endpoint exposes sync_state catching custom folders & latest_message_id
        $customFolderMail = MailMessage::create([
            'mail_account_id' => $elocrest->id,
            'message_id' => 'msg-riot-rule',
            'folder' => 'Riot',
            'subject' => 'Riot Rule Mail',
            'from_email' => 'noreply@riotgames.com',
            'to_email' => $elocrest->email,
            'is_read' => false,
            'received_at' => Carbon::parse('Wed, 25 Nov 2026 19:01:00 +0100'),
        ]);

        $accRes = $this->getJson('/api/funki/emails/accounts');
        $accRes->assertOk();
        $this->assertSame($customFolderMail->id, $accRes->json('sync_state.latest_message_id'));
        $this->assertSame(5, $accRes->json('sync_state.total_messages'));
        $this->assertSame(5, $accRes->json('sync_state.unread_total'));
    }
}
