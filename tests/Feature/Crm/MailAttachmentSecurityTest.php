<?php

namespace Tests\Feature\Crm;

use Tests\TestCase;
use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MailAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_download_forces_attachment_disposition_for_dangerous_mimes()
    {
        Storage::fake('local');

        $admin = Admin::create([
            'id' => Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $account = MailAccount::create([
            'name' => 'Test Account',
            'email' => 'mail@example.com',
            'password' => 'secret123',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
        ]);

        $message = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => 'msg-001',
            'folder' => 'INBOX',
            'subject' => 'Malicious HTML Test',
            'from_name' => 'Attacker',
            'from_email' => 'attacker@example.com',
            'to_email' => 'admin@example.com',
            'received_at' => now(),
        ]);

        $filePath = 'leitung/crm/mail-attachments/' . $message->id . '/test.html';
        Storage::put($filePath, '<html><script>alert(1)</script></html>');

        $attachment = MailAttachment::create([
            'mail_message_id' => $message->id,
            'filename' => 'payload.html',
            'content_type' => 'text/html',
            'size' => 30,
            'path' => $filePath,
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('crm.mail-attachment', $attachment->id));

        $response->assertStatus(200);
        // text/html must NEVER be served inline to prevent Stored XSS
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_attachment_download_rejects_path_traversal()
    {
        $admin = Admin::create([
            'id' => Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $account = MailAccount::create([
            'name' => 'Test Account',
            'email' => 'mail@example.com',
            'password' => 'secret123',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
        ]);

        $message = MailMessage::create([
            'mail_account_id' => $account->id,
            'message_id' => 'msg-002',
            'folder' => 'INBOX',
            'subject' => 'Traversal Test',
            'from_name' => 'Attacker',
            'from_email' => 'attacker@example.com',
            'to_email' => 'admin@example.com',
            'received_at' => now(),
        ]);

        $attachment = MailAttachment::create([
            'mail_message_id' => $message->id,
            'filename' => 'passwd',
            'content_type' => 'text/plain',
            'size' => 100,
            'path' => '../../../../etc/passwd',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('crm.mail-attachment', $attachment->id));

        $response->assertStatus(403);
    }
}
