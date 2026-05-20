<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\ManagementContact;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class ManagementEMails extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public $selectedAccountId = null;
    public $selectedFolder = 'INBOX';
    public $selectedMessageId = null;

    // View State (inbox, account_settings)
    public $viewMode = 'inbox';

    // Account Settings Form
    public $editAccountId = null;
    public $account_name = '';
    public $email = '';
    public $password = '';
    public $imap_username = '';
    public $imap_host = '';
    public $imap_port = '993';
    public $imap_encryption = 'ssl';
    public $smtp_username = '';
    public $smtp_host = '';
    public $smtp_port = '465';
    public $smtp_encryption = 'ssl';
    public $signature = '';
    public $is_default = false;
    public $is_commercial = true;

    // Search and Filter
    public $searchQuery = '';
    public $filterMode = 'all'; // 'all', 'unread', 'attachments'

    // Folder Creation
    public $newFolderName = '';
    public $showNewFolderModal = false;

    // Routing Rules
    public $showRoutingModal = false;
    public $routingMessageId = null;
    public $routingTargetFolder = '';

    // Compose / Reply
    public $showComposeModal = false;
    public $composeTo = '';
    public $composeSubject = '';
    public $composeBody = '';
    public $composeAccountId = null;
    public $forwardedAttachments = [];

    public function mount()
    {
        $accounts = MailAccount::all();
        if ($accounts->isNotEmpty()) {
            $default = $accounts->firstWhere('is_default', true) ?? $accounts->first();
            $this->selectedAccountId = $default->id;
        }
    }

    public function selectAccount($id)
    {
        $this->selectedAccountId = $id;
        $this->selectedFolder = 'INBOX';
        $this->selectedMessageId = null;
    }

    #[On('echo:crm-inbox,.MailReceivedEvent')]
    public function refreshInbox()
    {
        // Re-renders the component to display the newly fetched messages from the DB
        // Can optionally flash a localized success message
        // session()->flash('success_message', 'Neue Mail(s) empfangen!');
    }

    public function syncMails()
    {
        Artisan::call('crm:fetch-mails');

        $errorAccounts = \App\Models\Management\Mail\MailAccount::where('status', 'error')->count();
        if ($errorAccounts > 0) {
            session()->flash('error_message', 'Synchronisation abgeschlossen, aber bei ' . $errorAccounts . ' Postfächern gab es Verbindungsfehler! Prüfe die Passwörter.');
        } else {
            session()->flash('success_message', 'Synchronisation erfolgreich abgeschlossen.');
        }
    }

    public function selectFolder($folder)
    {
        $this->selectedFolder = $folder;
        $this->selectedMessageId = null;
        $this->dispatch('folder-selected');
    }

    public function selectAccountAndFolder($accountId, $folder)
    {
        $this->selectedAccountId = $accountId;
        $this->selectedFolder = $folder;
        $this->selectedMessageId = null;
        $this->viewMode = 'inbox';
        $this->dispatch('folder-selected');
    }

    public function openAccountSettings($id = null)
    {
        $this->resetAccountForm();
        if ($id && $id !== 'new') {
            $account = MailAccount::find($id);
            if ($account) {
                $this->editAccountId = $account->id;
                $this->account_name = $account->name;
                $this->email = $account->email;
                $this->imap_username = $account->imap_username;
                $this->imap_host = $account->imap_host;
                $this->imap_port = $account->imap_port;
                $this->imap_encryption = $account->imap_encryption;
                $this->smtp_username = $account->smtp_username;
                $this->smtp_host = $account->smtp_host;
                $this->smtp_port = $account->smtp_port;
                $this->smtp_encryption = $account->smtp_encryption;
                $this->signature = $account->signature;
                $this->is_commercial = $account->is_commercial ?? true;
            }
        }
        $this->viewMode = 'account_settings';
        $this->dispatch('settings-opened');
    }

    public function closeAccountSettings()
    {
        $this->viewMode = 'inbox';
        $this->editAccountId = null;
        $this->dispatch('folder-selected');
    }

    public function updatedEmail($value)
    {
        $email = strtolower(trim($value));
        if (str_ends_with($email, '@t-online.de')) {
            $this->applyPreset('t-online');
        } elseif (str_ends_with($email, '@gmail.com') || str_ends_with($email, '@googlemail.com')) {
            $this->applyPreset('gmail');
        } elseif (str_ends_with($email, '@gmx.de') || str_ends_with($email, '@gmx.net') || str_ends_with($email, '@gmx.at') || str_ends_with($email, '@gmx.ch')) {
            $this->applyPreset('gmx');
        }
    }

    public function applyPreset($provider)
    {
        switch ($provider) {
            case 't-online':
                $this->imap_host = 'secureimap.t-online.de';
                $this->imap_port = '993';
                $this->imap_encryption = 'ssl';
                $this->smtp_host = 'securesmtp.t-online.de';
                $this->smtp_port = '465';
                $this->smtp_encryption = 'ssl';
                break;
            case 'gmail':
                $this->imap_host = 'imap.gmail.com';
                $this->imap_port = '993';
                $this->imap_encryption = 'ssl';
                $this->smtp_host = 'smtp.gmail.com';
                $this->smtp_port = '587';
                $this->smtp_encryption = 'tls';
                break;
            case 'mittwald':
                $this->imap_host = 'mail.agenturserver.de';
                $this->imap_port = '993';
                $this->imap_encryption = 'ssl';
                $this->smtp_host = 'mail.agenturserver.de';
                $this->smtp_port = '465';
                $this->smtp_encryption = 'ssl';
                break;
            case 'gmx':
                $this->imap_host = 'imap.gmx.net';
                $this->imap_port = '993';
                $this->imap_encryption = 'ssl';
                $this->smtp_host = 'mail.gmx.net';
                $this->smtp_port = '587';
                $this->smtp_encryption = 'tls';
                break;
        }
    }

    public function saveAccount()
    {
        $this->validate([
            'account_name' => 'required',
            'email' => 'required|email',
            'password' => $this->editAccountId ? 'nullable' : 'required',
            'imap_host' => 'required',
            'smtp_host' => 'required',
        ]);

        $data = [
            'name' => $this->account_name,
            'email' => $this->email,
            'imap_username' => $this->imap_username,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_encryption' => $this->imap_encryption,
            'smtp_username' => $this->smtp_username,
            'smtp_host' => $this->smtp_host,
            'smtp_port' => $this->smtp_port,
            'smtp_encryption' => $this->smtp_encryption,
            'signature' => $this->signature,
            'is_default' => $this->is_default,
            'is_commercial' => $this->is_commercial,
            'status' => 'connected',
        ];

        if (!empty($this->password)) {
            $data['password'] = $this->password;
        }

        MailAccount::updateOrCreate(['id' => $this->editAccountId], $data);

        $this->closeAccountSettings();
        session()->flash('success_message', 'E-Mail Konto wurde erfolgreich gespeichert.');
    }

    private function resetAccountForm()
    {
        $this->reset([
            'email', 'password', 'imap_host', 'account_name',
            'smtp_host', 'editAccountId', 'signature',
            'imap_username', 'smtp_username'
        ]);
        $this->imap_port = '993';
        $this->imap_encryption = 'ssl';
        $this->smtp_port = '465';
        $this->smtp_encryption = 'ssl';
        $this->is_default = false;
        $this->is_commercial = true;

        if (view()->exists('global.mails.partials.mail_footer')) {
            $this->signature = view('global.mails.partials.mail_footer')->render();
        }
    }

    public function deleteAccount($id)
    {
        MailAccount::destroy($id);
        $this->closeAccountSettings();
        session()->flash('success_message', 'Postfach wurde gelöscht.');
        $this->mount();
    }

    public function selectMessage($id)
    {
        $this->selectedMessageId = $id;

        $msg = MailMessage::find($id);
        if ($msg && !$msg->is_read) {
            $msg->update(['is_read' => true]);
        }
        $this->dispatch('message-selected');
    }

    public function markAsSpam($id)
    {
        $msg = MailMessage::find($id);
        if ($msg) {
            // 1. Move to Spam folder (or delete)
            $msg->update(['folder' => 'Junk']);

            // 2. Add rule to automatically block this sender in the future
            if ($msg->from_email) {
                MailRule::firstOrCreate([
                    'mail_account_id' => $msg->mail_account_id,
                    'type' => 'blacklist',
                    'condition_field' => 'from_email',
                    'condition_value' => $msg->from_email,
                    'action' => 'mark_spam'
                ]);
            }

            $this->selectedMessageId = null;
            session()->flash('success_message', 'E-Mail als Spam markiert und Absender dauerhaft blockiert.');
            $this->dispatch('folder-selected');
        }
    }

    public function unmarkSpam($id)
    {
        $msg = MailMessage::find($id);
        if ($msg && $msg->folder === 'Junk') {
            $msg->update(['folder' => 'INBOX']);

            // Remove the auto-blacklist rule if it exists
            if ($msg->from_email) {
                MailRule::where('mail_account_id', $msg->mail_account_id)
                    ->where('type', 'blacklist')
                    ->where('condition_value', $msg->from_email)
                    ->delete();
            }
            $this->selectedMessageId = null;
            session()->flash('success_message', 'Markierung aufgehoben. E-Mail ist wieder im Posteingang.');
            $this->dispatch('folder-selected');
        }
    }

    public function createFolder()
    {
        $this->validate(['newFolderName' => 'required|string|max:50']);

        if ($this->selectedAccountId) {
            \App\Models\Management\Mail\MailFolder::firstOrCreate([
                'mail_account_id' => $this->selectedAccountId,
                'name' => trim($this->newFolderName)
            ]);

            $this->showNewFolderModal = false;
            $this->newFolderName = '';
            session()->flash('success_message', 'Ordner erfolgreich erstellt.');
        }
    }

    public function moveMessage($messageId, $targetFolder)
    {
        $msg = MailMessage::find($messageId);
        if ($msg) {
            $isArchived = ($targetFolder === 'Archive');
            if ($msg->folder !== $targetFolder || $msg->is_archived !== $isArchived) {
                $msg->update([
                    'folder' => $targetFolder,
                    'is_archived' => $isArchived
                ]);

                if ($this->selectedMessageId === $messageId && $this->selectedFolder !== $targetFolder) {
                    // If the user moved the currently viewed message out of the current folder, close the reading pane
                    $this->selectedMessageId = null;
                    $this->dispatch('folder-selected');
                }
            }
        }
    }

    public function deleteMessage($id)
    {
        $msg = MailMessage::find($id);
        if ($msg) {
            if ($msg->folder === 'Trash') {
                $msg->delete();
            } else {
                $msg->update(['folder' => 'Trash']);
            }
            $this->selectedMessageId = null;
            $this->dispatch('folder-selected');
        }
    }

    public function archiveMessage($id)
    {
        $msg = MailMessage::find($id);
        if ($msg && !$msg->is_archived) {
            $msg->update(['is_archived' => true, 'folder' => 'Archive']);
            if ($this->selectedMessageId === $id) {
                $this->selectedMessageId = null;
                $this->dispatch('folder-selected');
            }
            session()->flash('success_message', 'E-Mail archiviert.');
        }
    }

    public function unarchiveMessage($id)
    {
        $msg = MailMessage::find($id);
        if ($msg && $msg->is_archived) {
            $msg->update(['is_archived' => false, 'folder' => 'INBOX']);
            if ($this->selectedMessageId === $id) {
                $this->selectedMessageId = null;
                $this->dispatch('folder-selected');
            }
            session()->flash('success_message', 'E-Mail aus dem Archiv wiederhergestellt.');
        }
    }

    public function openRoutingModal($messageId)
    {
        $this->routingMessageId = $messageId;
        $this->routingTargetFolder = '';
        $this->showRoutingModal = true;
    }

    public function saveRoutingRule()
    {
        $this->validate(['routingTargetFolder' => 'required|string']);

        $msg = MailMessage::find($this->routingMessageId);
        if ($msg && $msg->from_email) {
            MailRule::firstOrCreate([
                'mail_account_id' => $msg->mail_account_id,
                'type' => 'routing',
                'condition_field' => 'from_email',
                'condition_value' => $msg->from_email,
                'action' => $this->routingTargetFolder
            ]);

            // Move the current one too
            $msg->update(['folder' => $this->routingTargetFolder]);
            if ($this->selectedMessageId === $msg->id && $this->selectedFolder !== $this->routingTargetFolder) {
                $this->selectedMessageId = null;
                $this->dispatch('folder-selected');
            }

            $this->showRoutingModal = false;
            session()->flash('success_message', 'Regel aktiv! Zukünftige Mails von '.$msg->from_email.' landen automatisch in '.$this->routingTargetFolder.'.');
        }
    }



    public function openCompose($type = 'new', $messageId = null)
    {
        $this->composeAccountId = $this->selectedAccountId;
        $account = MailAccount::find($this->composeAccountId);
        $signature = $account ? "<br><br>" . $account->signature : '';
        $this->forwardedAttachments = [];

        if ($type === 'reply' && $messageId) {
            $msg = MailMessage::find($messageId);
            if ($msg) {
                $this->composeTo = $msg->from_email;
                $this->composeSubject = 'Re: ' . $msg->subject;
                $this->composeBody = "<br><br><hr><p>Am " . $msg->received_at->format('d.m.Y H:i') . " schrieb " . $msg->from_name . ":<br><i>" . str_replace("\n", "<br>", strip_tags($msg->body_plain ?? $msg->body_html)) . "</i></p>" . $signature;
            }
        } elseif ($type === 'forward' && $messageId) {
            $msg = MailMessage::find($messageId);
            if ($msg) {
                $this->composeTo = '';
                $this->composeSubject = 'Fwd: ' . $msg->subject;
                $this->composeBody = "<br><br><hr><p>Weitergeleitete Nachricht von: " . $msg->from_email . "<br>Datum: " . $msg->received_at->format('d.m.Y H:i') . "</p><br>" . ($msg->safe_body_html ?? nl2br(e($msg->body_plain))) . $signature;
                
                // Get all attachments from the original message and store them to be forwarded
                foreach (\App\Models\Management\Mail\MailAttachment::where('mail_message_id', $msg->id)->get() as $att) {
                    $this->forwardedAttachments[] = [
                        'path' => $att->path,
                        'filename' => $att->filename,
                        'mime' => $att->content_type,
                        'size' => $att->size,
                    ];
                }
            }
        } else {
            $this->composeTo = '';
            $this->composeSubject = '';
            $this->composeBody = "<br><br>" . $signature;
        }
        $this->showComposeModal = true;
    }

    public function sendMail()
    {
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string',
            'composeBody' => 'required|string',
            'composeAccountId' => 'required'
        ]);

        $account = MailAccount::find($this->composeAccountId);

        if (!$account || !$account->smtp_host) {
            session()->flash('error_message', 'SMTP Konfiguration für dieses Konto fehlt!');
            return;
        }

        try {
            // Dynamische Mail-Verbindung für genau dieses Postfach aufbauen
            Config::set('mail.mailers.dynamic', [
                'transport' => 'smtp',
                'host' => trim($account->smtp_host),
                'port' => trim($account->smtp_port),
                'encryption' => trim($account->smtp_encryption),
                'username' => trim($account->smtp_username ?: $account->email),
                'password' => trim($account->password),
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ]);

            // Clear any cached mailer instance so Laravel rebuilds it with the new config
            app('mail.manager')->purge('dynamic');

            // Formatierten Body mit echter Signatur als HTML aufbauen
            $bodyHtml = $this->composeBody;
            // Die Signatur ist in createComposeBody bereits im $this->composeBody enthalten,
            // daher übergeben wir null an den Mailable.
            $signatureHtml = null;

            // Prepare Attachments
            $attachmentDataParams = [];
            foreach ($this->forwardedAttachments as $att) {
                $attachmentDataParams[] = $att;
            }

            // Senden ausführen
            if (app()->environment('testing')) {
                Mail::to($this->composeTo)->send(
                    new \App\Mail\CrmOutgoingMailToCustomer(
                        $this->composeSubject,
                        $bodyHtml,
                        $signatureHtml,
                        $account->email,
                        $account->name,
                        $attachmentDataParams
                    )
                );
            } else {
                Mail::mailer('dynamic')->to($this->composeTo)->send(
                    new \App\Mail\CrmOutgoingMailToCustomer(
                        $this->composeSubject,
                        $bodyHtml,
                        $signatureHtml,
                        $account->email,
                        $account->name,
                        $attachmentDataParams
                    )
                );
            }

            // Kopie im lokalen System ablegen (Ordner: Sent)
            $sentMessage = MailMessage::create([
                'mail_account_id' => $account->id,
                'message_id' => 'sent-' . uniqid(),
                'folder' => 'Sent',
                'subject' => $this->composeSubject,
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => $this->composeTo,
                'body_plain' => $this->composeBody,
                'body_html' => view('global.mails.crm_outgoing_mail_to_customer', compact('bodyHtml', 'signatureHtml'))->render(),
                'is_read' => true,
                'has_attachments' => count($attachmentDataParams) > 0,
                'received_at' => now()
            ]);

            // Save forwarded attachments in the DB for the sent message so they appear in Sent folder
            foreach ($attachmentDataParams as $att) {
                \App\Models\Management\Mail\MailAttachment::create([
                    'mail_message_id' => $sentMessage->id,
                    'filename' => $att['filename'],
                    'content_type' => $att['mime'],
                    'size' => $att['size'],
                    'path' => $att['path'],
                ]);
            }

            $this->showComposeModal = false;
            session()->flash('success_message', 'E-Mail wurde via SMTP erfolgreich gesendet!');

        } catch (\Exception $e) {
            Log::error('SMTP Sende-Fehler: ' . $e->getMessage());
            session()->flash('error_message', 'Fehler beim Senden: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $accounts = MailAccount::all();
        $messages = [];
        $selectedMessage = $this->selectedMessageId ? MailMessage::find($this->selectedMessageId) : null;

        $baseFolders = [
            'INBOX' => 'Posteingang',
            'Sent' => 'Gesendet',
            'Drafts' => 'Entwürfe',
            'Junk' => 'Spam',
            'Trash' => 'Papierkorb',
            'Archive' => 'Archiv',
        ];

        // We build a comprehensive tree for the Sidebar: Accounts -> Folders -> Counts
        $accountTree = [];

        foreach ($accounts as $acc) {
            $accFolders = $baseFolders;
            // Append Custom Folders
            $customFolders = \App\Models\Management\Mail\MailFolder::where('mail_account_id', $acc->id)->pluck('name')->toArray();
            foreach ($customFolders as $cf) {
                $accFolders[$cf] = $cf;
            }

            $query = MailMessage::where('mail_account_id', $acc->id);
            $folderCounts = [];
            $totalUnreadForAccount = 0;

            foreach ($accFolders as $key => $label) {
                $qc = clone $query;
                if ($key === 'Archive') {
                    $count = $qc->where('is_archived', true)->where('is_read', false)->count();
                } else {
                    $count = $qc->where('folder', $key)->where('is_archived', false)->where('is_read', false)->count();
                }
                $folderCounts[$key] = $count;

                // Usually we only sum up INBOX (or all explicit folders depending on choice) for the account-level badge
                // Let's just sum all unread for the account badge:
                $totalUnreadForAccount += $count;
            }

            $accountTree[$acc->id] = [
                'model' => $acc,
                'folders' => $accFolders,
                'counts' => $folderCounts,
                'total_unread' => $totalUnreadForAccount
            ];
        }

        // Fetch actual messages
        if (!empty($this->searchQuery)) {
            // Global Search Mode across all accounts and folders
            $query = MailMessage::query();

            $query->where(function ($q) {
                $search = '%' . $this->searchQuery . '%';
                $searchLower = '%' . strtolower($this->searchQuery) . '%';
                
                $q->where('subject', 'like', $search)
                  ->orWhere('from_email', 'like', $search)
                  ->orWhere('from_name', 'like', $search)
                  ->orWhere('category', 'like', $search)
                  ->orWhereRaw('LOWER(CAST(tags AS CHAR)) LIKE ?', [$searchLower]);
                  
                $exactMatch = strtolower(trim($this->searchQuery));
                if ($exactMatch === 'wichtig' || $exactMatch === 'high') {
                    $q->orWhere('priority', 'high');
                } elseif ($exactMatch === 'niedrig' || $exactMatch === 'low') {
                    $q->orWhere('priority', 'low');
                } elseif ($exactMatch === 'normal') {
                    $q->orWhere('priority', 'normal');
                }
            });

            // Apply Filters
            if ($this->filterMode === 'unread') {
                $query->where('is_read', false);
            } elseif ($this->filterMode === 'attachments') {
                $query->where('has_attachments', true);
            }

            $messages = $query->orderBy('received_at', 'desc')->get();
        } elseif ($this->selectedAccountId) {
            // Normal Folder View Mode
            $query = MailMessage::where('mail_account_id', $this->selectedAccountId);

            if ($this->selectedFolder === 'Archive') {
                $query->where('is_archived', true);
            } else {
                $query->where('folder', $this->selectedFolder)->where('is_archived', false);
            }

            // Apply Filters
            if ($this->filterMode === 'unread') {
                $query->where('is_read', false);
            } elseif ($this->filterMode === 'attachments') {
                $query->where('has_attachments', true);
            }

            $messages = $query->orderBy('received_at', 'desc')->get();
        }

        $contactEmails = ManagementContact::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['first_name', 'last_name', 'email']);

        return view('livewire.shop.management.management-e-mails', [
            'accounts' => $accounts,
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'accountTree' => $accountTree,
            'folders' => $this->selectedAccountId && isset($accountTree[$this->selectedAccountId]) ? $accountTree[$this->selectedAccountId]['folders'] : $baseFolders,
            'folderCounts' => $this->selectedAccountId && isset($accountTree[$this->selectedAccountId]) ? $accountTree[$this->selectedAccountId]['counts'] : [],
            'contactEmails' => $contactEmails
        ]);
    }

    public function downloadAttachment($attachmentId, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadMailAttachment($attachmentId);
    }
}
