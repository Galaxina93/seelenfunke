<?php

namespace Tests\Feature\Livewire\Shop\Management;

use App\Livewire\Shop\Management\ManagementEMails as CrmInbox;
use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\Mail\MailFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementEMailsTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();



        // Ensure we have an authenticated admin
        $this->admin = Admin::forceCreate([
            'first_name' => 'Testing',
            'last_name' => 'Admin',
            'email' => 'admin@test.test',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->admin, 'admin');

        // Create a default mail account
        $this->account = MailAccount::forceCreate([
            'name' => 'Default Account',
            'email' => 'test@example.com',
            'password' => encrypt('secret'),
            'imap_host' => 'imap.example.com',
            'imap_port' => '993',
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '465',
            'smtp_encryption' => 'ssl',
            'status' => 'connected',
            'is_default' => true,
        ]);

        // Create a few folders
        MailFolder::forceCreate(['mail_account_id' => $this->account->id, 'name' => 'CustomFolder']);
    }

    public function test_renders_successfully_and_selects_default_account()
    {
        Livewire::test(CrmInbox::class)
            ->assertStatus(200)
            ->assertSet('selectedAccountId', $this->account->id)
            ->assertSet('selectedFolder', 'INBOX');
    }

    public function test_can_select_an_account_and_folder()
    {
        $secondAccount = MailAccount::forceCreate([
            'name' => 'Second Account',
            'email' => 'second@example.com',
            'password' => encrypt('secret'),
            'imap_host' => 'imap',
            'smtp_host' => 'smtp',
            'status' => 'connected',
            'is_default' => false,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('selectAccountAndFolder', $secondAccount->id, 'Drafts')
            ->assertSet('selectedAccountId', $secondAccount->id)
            ->assertSet('selectedFolder', 'Drafts')
            ->assertSet('selectedMessageId', null)
            ->assertDispatched('folder-selected');
    }

    public function test_triggers_imap_sync_command_on_syncMails()
    {
        Artisan::shouldReceive('call')->with('crm:fetch-mails')->once();

        Livewire::test(CrmInbox::class)
            ->call('syncMails');
    }

    public function test_selects_a_message_and_marks_it_as_read()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'msg123',
            'folder' => 'INBOX',
            'subject' => 'Test',
            'from_email' => 'sender@test.com',
            'to_email' => $this->account->email,
            'is_read' => false,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('selectMessage', $msg->id)
            ->assertSet('selectedMessageId', $msg->id)
            ->assertDispatched('message-selected');

        $this->assertTrue($msg->fresh()->is_read);
    }

    public function test_moves_a_message_to_junk_and_creates_a_blacklist_rule_when_marking_as_spam()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'spam123',
            'folder' => 'INBOX',
            'subject' => 'Spam Test',
            'from_email' => 'spammer@spam.com',
            'to_email' => $this->account->email,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('markAsSpam', $msg->id)
            ->assertSet('selectedMessageId', null)
            ->assertDispatched('folder-selected');

        $msg->refresh();
        $this->assertEquals('Junk', $msg->folder);

        $rule = MailRule::where('condition_value', 'spammer@spam.com')->first();
        $this->assertNotNull($rule);
        $this->assertEquals('blacklist', $rule->type);
        $this->assertEquals('mark_spam', $rule->action);
    }

    public function test_removes_the_junk_folder_and_rule_when_unmarking_spam()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'spam123',
            'folder' => 'Junk',
            'subject' => 'False Positive',
            'from_email' => 'legit@spam.com',
            'to_email' => $this->account->email,
        ]);

        MailRule::forceCreate([
            'mail_account_id' => $this->account->id,
            'type' => 'blacklist',
            'condition_field' => 'from_email',
            'condition_value' => 'legit@spam.com',
            'action' => 'mark_spam',
        ]);

        Livewire::test(CrmInbox::class)
            ->call('unmarkSpam', $msg->id)
            ->assertSet('selectedMessageId', null);

        $msg->refresh();
        $this->assertEquals('INBOX', $msg->folder);
        $this->assertFalse(MailRule::where('condition_value', 'legit@spam.com')->exists());
    }

    public function test_creates_a_custom_folder()
    {
        Livewire::test(CrmInbox::class)
            ->set('newFolderName', 'Invoices')
            ->call('createFolder')
            ->assertSet('showNewFolderModal', false)
            ->assertSet('newFolderName', '');

        $this->assertTrue(MailFolder::where('name', 'Invoices')->where('mail_account_id', $this->account->id)->exists());
    }

    public function test_moves_a_message_to_another_folder()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'move123',
            'folder' => 'INBOX',
            'subject' => 'To Move',
            'from_email' => 'test@test.com',
            'to_email' => $this->account->email,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('moveMessage', $msg->id, 'CustomFolder');

        $this->assertEquals('CustomFolder', $msg->fresh()->folder);
    }

    public function test_deletes_a_message_to_trash_and_then_permanently()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'del123',
            'folder' => 'INBOX',
            'subject' => 'To Delete',
            'from_email' => 'test@test.com',
            'to_email' => $this->account->email,
        ]);

        $component = Livewire::test(CrmInbox::class)
            ->call('deleteMessage', $msg->id);

        $this->assertEquals('Trash', $msg->fresh()->folder);

        $component->call('deleteMessage', $msg->id);
        $this->assertNull(MailMessage::find($msg->id));
    }

    public function test_archives_a_message()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'arch123',
            'folder' => 'INBOX',
            'subject' => 'To Archive',
            'from_email' => 'test@test.com',
            'to_email' => $this->account->email,
            'is_archived' => false,
        ]);

        Livewire::test(CrmInbox::class)
            ->set('selectedMessageId', $msg->id)
            ->call('archiveMessage', $msg->id)
            ->assertSet('selectedMessageId', null);

        $msg->refresh();
        $this->assertTrue($msg->is_archived);
        $this->assertEquals('Archive', $msg->folder);
    }

    public function test_saves_routing_rule_and_applies_to_current_message()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'routing123',
            'folder' => 'INBOX',
            'subject' => 'News',
            'from_email' => 'newsletter@news.com',
            'to_email' => $this->account->email,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('openRoutingModal', $msg->id)
            ->assertSet('showRoutingModal', true)
            ->set('routingTargetFolder', 'CustomFolder')
            ->call('saveRoutingRule')
            ->assertSet('showRoutingModal', false);

        $rule = MailRule::where('condition_value', 'newsletter@news.com')->first();
        $this->assertNotNull($rule);
        $this->assertEquals('routing', $rule->type);
        $this->assertEquals('CustomFolder', $rule->action);

        $this->assertEquals('CustomFolder', $msg->fresh()->folder);
    }

    public function test_can_forward_an_email_and_copy_attachments()
    {
        Mail::fake();

        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'orig123',
            'folder' => 'INBOX',
            'subject' => 'Original Subject',
            'from_email' => 'sender@news.com',
            'from_name' => 'Sender',
            'to_email' => $this->account->email,
            'body_plain' => 'Test body',
            'received_at' => now()
        ]);

        \App\Models\Management\Mail\MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'forwarded.pdf',
            'content_type' => 'application/pdf',
            'size' => 1024,
            'path' => 'path/to/forwarded.pdf'
        ]);

        Livewire::test(CrmInbox::class)
            ->call('openCompose', 'forward', $msg->id)
            ->assertSet('showComposeModal', true)
            ->assertSet('composeSubject', 'Fwd: Original Subject')
            ->set('composeTo', 'forward@test.com')
            ->call('sendMail')
            ->assertSet('showComposeModal', false);

        // Make sure a copy was saved to sent with attachments
        $sentMessage = MailMessage::where('folder', 'Sent')->orderBy('id', 'desc')->first();
        $this->assertNotNull($sentMessage);
        $this->assertTrue((bool)$sentMessage->has_attachments);
        
        $this->assertTrue(\App\Models\Management\Mail\MailAttachment::where('mail_message_id', $sentMessage->id)->exists());
        
        Mail::assertSent(\App\Mail\CrmOutgoingMailToCustomer::class, function ($mail) {
            return $mail->hasTo('forward@test.com');
        });
    }

    public function test_can_compose_new_email_and_send_it()
    {
        Mail::fake();

        Livewire::test(CrmInbox::class)
            ->call('openCompose', 'new')
            ->assertSet('showComposeModal', true)
            ->set('composeTo', 'target@test.com')
            ->set('composeSubject', 'Hello')
            ->set('composeBody', 'This is a test body.')
            ->call('sendMail')
            ->assertSet('showComposeModal', false);

        Mail::assertSent(\App\Mail\CrmOutgoingMailToCustomer::class, function ($mail) {
            return $mail->hasTo('target@test.com');
        });
    }


    public function test_can_open_and_close_account_settings_for_new_account()
    {
        Livewire::test(CrmInbox::class)
            ->call('openAccountSettings', 'new')
            ->assertSet('viewMode', 'account_settings')
            ->assertSet('editAccountId', null)
            ->assertSet('imap_port', '993')
            ->assertSet('is_commercial', true)
            ->call('closeAccountSettings')
            ->assertSet('viewMode', 'inbox');
    }

    public function test_can_open_account_settings_for_existing_account()
    {
        Livewire::test(CrmInbox::class)
            ->call('openAccountSettings', $this->account->id)
            ->assertSet('viewMode', 'account_settings')
            ->assertSet('editAccountId', $this->account->id)
            ->assertSet('account_name', $this->account->name)
            ->assertSet('imap_host', $this->account->imap_host);
    }

    public function test_can_apply_presets_for_imap_and_smtp()
    {
        Livewire::test(CrmInbox::class)
            ->call('applyPreset', 'gmail')
            ->assertSet('imap_host', 'imap.gmail.com')
            ->assertSet('smtp_host', 'smtp.gmail.com')
            ->call('applyPreset', 't-online')
            ->assertSet('imap_host', 'secureimap.t-online.de')
            ->call('applyPreset', 'mittwald')
            ->assertSet('imap_host', 'mail.agenturserver.de');
    }

    public function test_can_save_a_new_account_and_updates_commercial_flag()
    {
        Livewire::test(CrmInbox::class)
            ->call('openAccountSettings', 'new')
            ->set('account_name', 'Test Commercial Account')
            ->set('email', 'commercial@test.com')
            ->set('password', 'password123')
            ->set('imap_host', 'imap.test.com')
            ->set('smtp_host', 'smtp.test.com')
            ->set('is_commercial', true)
            ->call('saveAccount')
            ->assertSet('viewMode', 'inbox');

        $created = MailAccount::where('email', 'commercial@test.com')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->is_commercial);
    }

    public function test_can_delete_an_account()
    {
        $accountToDelete = MailAccount::forceCreate([
            'name' => 'To Delete',
            'email' => 'delete@example.com',
            'password' => encrypt('secret'),
            'imap_host' => 'imap',
            'smtp_host' => 'smtp',
            'status' => 'connected',
            'is_default' => false,
        ]);

        Livewire::test(CrmInbox::class)
            ->call('deleteAccount', $accountToDelete->id);

        $this->assertNull(MailAccount::find($accountToDelete->id));
    }
    
    public function test_download_attachment_returns_file_response()
    {
        $msg = MailMessage::create([
            'mail_account_id' => $this->account->id,
            'message_id' => 'att123',
            'folder' => 'INBOX',
            'subject' => 'With Attachment',
            'from_email' => 'sender@test.com',
            'to_email' => $this->account->email,
        ]);

        $attachment = \App\Models\Management\Mail\MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'test.pdf',
            'path' => 'templates/dummy.pdf', // Exists or not, we might need a mock, wait we can just assert download logic if we mock ExportService
            'content_type' => 'application/pdf',
            'size' => 1024,
        ]);

        // Since it returns a download response, we just test if the method calls the export service
        $mockService = \Mockery::mock(\App\Services\Export\FileDownloadService::class);
        $mockService->shouldReceive('downloadMailAttachment')->once()->andReturn(response('mocked output'));

        Livewire::test(CrmInbox::class)
            ->call('downloadAttachment', $attachment->id, $mockService);
    }
}
