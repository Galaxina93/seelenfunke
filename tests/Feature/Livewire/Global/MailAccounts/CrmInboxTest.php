<?php

namespace Tests\Feature\Livewire\Global\MailAccounts;

use App\Livewire\Global\MailAccounts\CrmInbox;
use App\Models\Admin\Admin;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailRule;
use App\Models\Mail\MailFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CrmInboxTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected MailAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the necessary role to bypass the Admin model observer bug in an empty database
        \App\Models\Role::firstOrCreate(['name' => 'admin']);

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

        Mail::assertSent(\App\Mail\CrmOutgoingMail::class);
    }
}
