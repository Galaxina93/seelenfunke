<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailAttachment;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementTask;
use App\Services\System\SystemSecurityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class FunkenwerkOrganizerAndMailApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('organizer-admin', ['*'])->plainTextToken;

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_tasks_crud_portable_priority_sort_subtasks_and_delete(): void
    {
        $headers = $this->adminHeaders();

        $listRes = $this->withHeaders($headers)->postJson('/api/funki/tasks/lists', [
            'name' => 'Manufaktur Sprint',
            'icon' => 'bolt',
            'color' => '#C5A059',
        ])->assertStatus(200);

        $listId = $listRes->json('data.id');
        $this->assertNotEmpty($listId);

        $lowRes = $this->withHeaders($headers)->postJson("/api/funki/tasks/lists/{$listId}/tasks", [
            'title' => 'Niedrige Aufgabe',
            'priority' => 'niedrig',
        ])->assertStatus(200);

        $highRes = $this->withHeaders($headers)->postJson("/api/funki/tasks/lists/{$listId}/tasks", [
            'title' => 'Kritische Laser-Wartung',
            'priority' => 'hoch',
        ])->assertStatus(200);

        $medRes = $this->withHeaders($headers)->postJson("/api/funki/tasks/lists/{$listId}/tasks", [
            'title' => 'Verpackung prüfen',
            'priority' => 'mittel',
        ])->assertStatus(200);

        $highId = $highRes->json('data.id');
        $lowId = $lowRes->json('data.id');
        $medId = $medRes->json('data.id');

        // Subtask creation
        $subRes = $this->withHeaders($headers)->postJson("/api/funki/tasks/{$highId}/subtask", [
            'title' => 'Linse reinigen',
        ])->assertStatus(200);
        $subId = $subRes->json('data.id');

        // Portable CASE priority ordering: hoch -> mittel -> niedrig + eager-loaded parent_title
        $tasks = $this->withHeaders($headers)
            ->getJson('/api/funki/tasks')
            ->assertStatus(200)
            ->json('data');

        $orderedRootIds = collect($tasks)
            ->whereNull('parent_id')
            ->pluck('id')
            ->values()
            ->all();

        $this->assertSame([$highId, $medId, $lowId], $orderedRootIds);

        $fetchedSub = collect($tasks)->firstWhere('id', $subId);
        $this->assertNotNull($fetchedSub);
        $this->assertSame('Kritische Laser-Wartung', $fetchedSub['parent_title']);

        // Toggle & update
        $this->withHeaders($headers)->putJson("/api/funki/tasks/{$medId}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('data.is_completed', true);

        $this->withHeaders($headers)->putJson("/api/funki/tasks/{$lowId}", [
            'title' => 'Niedrige Aufgabe aktualisiert',
        ])->assertStatus(200)->assertJsonPath('data.title', 'Niedrige Aufgabe aktualisiert');

        // Delete parent cascades to subtasks
        $this->withHeaders($headers)->deleteJson("/api/funki/tasks/{$highId}")->assertStatus(200);
        $this->assertDatabaseMissing('management_tasks', ['id' => $highId]);
        $this->assertDatabaseMissing('management_tasks', ['id' => $subId]);
    }

    public function test_calendar_crud_unique_recurring_instance_ids_and_task_deletion_via_calendar(): void
    {
        $headers = $this->adminHeaders();

        $startDay = Carbon::parse('2026-11-16 09:00:00');
        $createRes = $this->withHeaders($headers)->postJson('/api/funki/calendar/events', [
            'title' => 'Tägliches Standup',
            'start' => $startDay->toIso8601String(),
            'end' => $startDay->copy()->addMinutes(30)->toIso8601String(),
            'recurrence' => 'daily',
            'recurrence_end_date' => '2026-11-18',
            'priority' => 'high',
        ])->assertStatus(200);

        $seriesId = (string) $createRes->json('data.id');
        $this->assertNotEmpty($seriesId);

        // Create a scheduled task with subtask so it appears in calendar as task_{uuid}
        $taskRes = $this->withHeaders($headers)->postJson('/api/funki/tasks/lists/planned/tasks', [
            'title' => 'Kalender-Aufgabe löschen testen',
            'relevant_from' => '2026-11-17',
            'priority' => 'hoch',
        ])->assertStatus(200);
        $taskId = (string) $taskRes->json('data.id');

        $subRes = $this->withHeaders($headers)->postJson("/api/funki/tasks/{$taskId}/subtask", [
            'title' => 'Unterschritt für Kalender-Aufgabe',
        ])->assertStatus(200);
        $subId = (string) $subRes->json('data.id');

        $events = $this->withHeaders($headers)
            ->getJson('/api/funki/calendar/events?start=2026-11-16&end=2026-11-18')
            ->assertStatus(200)
            ->json('data');

        $seriesInstances = collect($events)->where('series_id', $seriesId)->values();
        $this->assertCount(3, $seriesInstances);

        $instanceIds = $seriesInstances->pluck('id')->all();
        $this->assertCount(3, array_unique($instanceIds));
        $this->assertSame([
            "{$seriesId}_20261116",
            "{$seriesId}_20261117",
            "{$seriesId}_20261118",
        ], $instanceIds);

        // Verify task entry is projected into calendar and deleting /events/task_{uuid} deletes the actual ManagementTask
        $calendarTaskEntry = collect($events)->firstWhere('id', "task_{$taskId}");
        $this->assertNotNull($calendarTaskEntry);

        $this->withHeaders($headers)
            ->deleteJson("/api/funki/calendar/events/task_{$taskId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('management_tasks', ['id' => $taskId]);
        $this->assertDatabaseMissing('management_tasks', ['id' => $subId]);
        // Recurring calendar series itself must still exist
        $this->assertNotNull(ManagementCalendarEvent::find($seriesId));

        // Deleting a recurring instance ID removes the calendar event cleanly
        $this->withHeaders($headers)
            ->deleteJson("/api/funki/calendar/events/{$seriesId}_20261116")
            ->assertStatus(200);
        $this->assertNull(ManagementCalendarEvent::find($seriesId));
    }

    public function test_contacts_crud_and_call_whitelist_and_shopping_crud_toggle(): void
    {
        $headers = $this->adminHeaders();

        // Contacts CRUD + call-whitelist
        $contactRes = $this->withHeaders($headers)->postJson('/api/funki/contacts', [
            'first_name' => 'Julia',
            'last_name' => 'Lieferantin',
            'email' => 'julia@westpack.example',
            'phone' => '+491511234567',
            'funkira_call_whitelist' => false,
        ])->assertStatus(200);

        $contactId = $contactRes->json('data.id');
        $this->assertNotEmpty($contactId);

        $this->withHeaders($headers)
            ->putJson("/api/funki/contacts/{$contactId}/call-whitelist", [
                'funkira_call_whitelist' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.funkira_call_whitelist', true);

        $this->withHeaders($headers)
            ->getJson('/api/funki/contacts?search=Julia')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withHeaders($headers)->deleteJson("/api/funki/contacts/{$contactId}")->assertStatus(200);

        // Shopping CRUD + toggle
        $catRes = $this->withHeaders($headers)->postJson('/api/funki/shopping/categories', [
            'name' => 'Versandlager',
            'icon' => 'box',
        ])->assertStatus(200);
        $catId = $catRes->json('data.id');

        $itemRes = $this->withHeaders($headers)->postJson('/api/funki/shopping/items', [
            'name' => 'Seidenpapier Schwarz',
            'category_id' => $catId,
            'quantity' => 3,
        ])->assertStatus(200);
        $itemId = $itemRes->json('data.id');
        $this->assertSame('needed', $itemRes->json('data.status'));

        $this->withHeaders($headers)
            ->putJson("/api/funki/shopping/items/{$itemId}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'stocked')
            ->assertJsonPath('data.purchase_count', 1);

        $this->withHeaders($headers)
            ->putJson("/api/funki/shopping/items/{$itemId}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'needed');

        $this->withHeaders($headers)->deleteJson("/api/funki/shopping/items/{$itemId}")->assertStatus(200);
        $this->assertDatabaseMissing('management_shopping_items', ['id' => $itemId]);
    }

    public function test_emails_ssrf_dns_rebinding_encryption_ports_trash_move_send_scope_and_attachment_realpath(): void
    {
        Mail::fake();
        Storage::fake('local');
        $headers = $this->adminHeaders();

        try {
            // 1. DNS-rebinding & SSRF checks on SystemSecurityService::isSafeMailHost
            $this->assertFalse(SystemSecurityService::isSafeMailHost('127.0.0.1'));
            $this->assertFalse(SystemSecurityService::isSafeMailHost('169.254.169.254'));
            $this->assertFalse(SystemSecurityService::isSafeMailHost('172.18.0.5'));
            $this->assertFalse(SystemSecurityService::isSafeMailHost('127.0.0.1.nip.io'));

            SystemSecurityService::$dnsResolver = static function (string $host): array {
                return match ($host) {
                    'rebind.attacker.example' => ['127.0.0.1'],
                    'docker.attacker.example' => ['172.20.0.3'],
                    'imap.trusted-provider.example', 'smtp.trusted-provider.example' => ['93.184.215.14'],
                    default => ['93.184.215.14'],
                };
            };

            $this->assertFalse(SystemSecurityService::isSafeMailHost('rebind.attacker.example'));
            $this->assertFalse(SystemSecurityService::isSafeMailHost('docker.attacker.example'));
            $this->assertTrue(SystemSecurityService::isSafeMailHost('imap.trusted-provider.example'));

            // API account creation rejects DNS-rebinding host (422)
            $this->withHeaders($headers)->postJson('/api/funki/emails/accounts', [
                'name' => 'Rebind Account',
                'email' => 'test@mein-seelenfunke.de',
                'password' => 'Secret123!',
                'imap_host' => 'rebind.attacker.example',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.trusted-provider.example',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ])->assertStatus(422);

            // API account creation rejects non-standard port (e.g. Redis 6379)
            $this->withHeaders($headers)->postJson('/api/funki/emails/accounts', [
                'name' => 'Bad Port Account',
                'email' => 'test@mein-seelenfunke.de',
                'password' => 'Secret123!',
                'imap_host' => 'imap.trusted-provider.example',
                'imap_port' => 6379,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.trusted-provider.example',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ])->assertStatus(422);

            // API account creation rejects 'none' cleartext encryption on external host
            $this->withHeaders($headers)->postJson('/api/funki/emails/accounts', [
                'name' => 'Cleartext Account',
                'email' => 'test@mein-seelenfunke.de',
                'password' => 'Secret123!',
                'imap_host' => 'imap.trusted-provider.example',
                'imap_port' => 993,
                'imap_encryption' => 'none',
                'smtp_host' => 'smtp.trusted-provider.example',
                'smtp_port' => 587,
                'smtp_encryption' => 'none',
            ])->assertStatus(422);

            // Valid encrypted mail account succeeds
            $accRes = $this->withHeaders($headers)->postJson('/api/funki/emails/accounts', [
                'name' => 'Seelenfunke Hauptpostfach',
                'email' => 'kontakt@mein-seelenfunke.de',
                'password' => 'AppPassword2026!',
                'imap_host' => 'imap.trusted-provider.example',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.trusted-provider.example',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
            ])->assertStatus(200);

            $accountAId = (int) $accRes->json('data.id');
            $accountB = MailAccount::create([
                'name' => 'Zweitpostfach',
                'email' => 'buchhaltung@mein-seelenfunke.de',
                'imap_host' => 'imap.trusted-provider.example',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.trusted-provider.example',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'password' => 'AppPassword2026!',
            ]);

            // 2. Move to 'Trash' vs 'Archive'
            $msgTrash = MailMessage::create([
                'mail_account_id' => $accountAId,
                'message_id' => 'msg-trash-1',
                'folder' => 'INBOX',
                'subject' => 'Test Papierkorb',
                'from_email' => 'sender@example.com',
                'to_email' => 'kontakt@mein-seelenfunke.de',
                'is_archived' => false,
                'is_read' => false,
                'received_at' => now(),
            ]);

            $this->withHeaders($headers)
                ->putJson("/api/funki/emails/messages/{$msgTrash->id}/move", ['folder' => 'Trash'])
                ->assertStatus(200);

            $msgTrash->refresh();
            $this->assertSame('Trash', $msgTrash->folder);
            $this->assertFalse((bool) $msgTrash->is_archived);

            $this->withHeaders($headers)
                ->putJson("/api/funki/emails/messages/{$msgTrash->id}/move", ['folder' => 'Archive'])
                ->assertStatus(200);

            $msgTrash->refresh();
            $this->assertSame('Archive', $msgTrash->folder);
            $this->assertTrue((bool) $msgTrash->is_archived);

            // 3. Send auto-archive scoping: only archives matching account & thread subject
            $matchingImportant = MailMessage::create([
                'mail_account_id' => $accountAId,
                'message_id' => 'msg-imp-a',
                'folder' => 'Processed',
                'subject' => 'Anfrage Großbestellung #77',
                'from_email' => 'vip@kunde.de',
                'to_email' => 'kontakt@mein-seelenfunke.de',
                'priority' => 'high',
                'is_archived' => false,
                'received_at' => now()->subHour(),
            ]);

            $otherAccountImportant = MailMessage::create([
                'mail_account_id' => $accountB->id,
                'message_id' => 'msg-imp-b',
                'folder' => 'Processed',
                'subject' => 'Anfrage Großbestellung #77',
                'from_email' => 'vip@kunde.de',
                'to_email' => 'buchhaltung@mein-seelenfunke.de',
                'priority' => 'high',
                'is_archived' => false,
                'received_at' => now()->subHour(),
            ]);

            $this->withHeaders($headers)->postJson('/api/funki/emails/send', [
                'account_id' => $accountAId,
                'to' => 'vip@kunde.de',
                'subject' => 'Re: Anfrage Großbestellung #77',
                'body' => '<p>Vielen Dank für Ihre Anfrage!</p>',
            ])->assertStatus(200)->assertJsonPath('success', true);

            $this->assertSame('Archive', $matchingImportant->fresh()->folder);
            $this->assertSame('Processed', $otherAccountImportant->fresh()->folder);

            // 4. Attachment realpath containment & streaming
            Storage::disk('local')->put('leitung/crm/mail-attachments/angebot.pdf', '%PDF-1.4 valid content');
            $validAtt = MailAttachment::create([
                'mail_message_id' => $msgTrash->id,
                'filename' => 'angebot.pdf',
                'content_type' => 'application/pdf',
                'size' => 22,
                'path' => 'leitung/crm/mail-attachments/angebot.pdf',
            ]);

            $dlResponse = $this->withHeaders($headers)
                ->get("/api/funki/emails/attachments/{$validAtt->id}")
                ->assertStatus(200);
            $this->assertSame('%PDF-1.4 valid content', $dlResponse->streamedContent());

            // Symlink escaping storage root must be rejected with 403
            $outsideTarget = sys_get_temp_dir() . '/sf_secret_' . Str::random(8) . '.txt';
            file_put_contents($outsideTarget, 'SECRET_HOST_FILE');
            $symlinkDir = Storage::disk('local')->path('leitung/crm/mail-attachments');
            if (!is_dir($symlinkDir)) {
                mkdir($symlinkDir, 0775, true);
            }
            $symlinkPath = $symlinkDir . '/escaped_link.txt';
            @symlink($outsideTarget, $symlinkPath);

            try {
                if (is_link($symlinkPath)) {
                    $symlinkAtt = MailAttachment::create([
                        'mail_message_id' => $msgTrash->id,
                        'filename' => 'escaped_link.txt',
                        'content_type' => 'text/plain',
                        'size' => 16,
                        'path' => 'leitung/crm/mail-attachments/escaped_link.txt',
                    ]);

                    $this->withHeaders($headers)
                        ->get("/api/funki/emails/attachments/{$symlinkAtt->id}")
                        ->assertStatus(403);
                }
            } finally {
                @unlink($symlinkPath);
                @unlink($outsideTarget);
            }
        } finally {
            SystemSecurityService::$dnsResolver = null;
        }
    }
}
