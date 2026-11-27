<?php

namespace App\Livewire\Shop\Management;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;

use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Services\System\SystemSecurityService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementEMails extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    public $selectedAccountId = null;
    public $selectedFolder = 'INBOX';
    public $selectedMessageId = null;

    // View State (inbox, account_settings)
    public $viewMode = 'inbox';

    // Account Settings Form
    #[Locked]
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

    // Mail Dateien View Filters & Lightbox
    public string $mailFileTypeFilter = 'all';
    public string $mailFileScopeFilter = 'all';
    public string $mailFileSearch = '';
    public ?int $previewAttachmentId = null;

    // Folder Creation
    public $newFolderName = '';
    public $showNewFolderModal = false;

    // Routing Rules
    public $showRoutingModal = false;
    #[Locked]
    public $routingMessageId = null;
    public $routingTargetFolder = '';

    // Compose / Reply
    public $showComposeModal = false;
    public $editingDraftId = null;
    public $composeTo = '';
    public $composeCc = '';
    public $composeBcc = '';
    public $composeSubject = '';
    public $composeBody = '';
    public $composeAccountId = null;
    #[Locked]
    public $composeAttachments = [];
    public $uploadedFiles = [];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Zugriff verweigert.');
        }

        $accounts = MailAccount::all();
        if ($accounts->isNotEmpty()) {
            $default = $accounts->firstWhere('is_default', true) ?? $accounts->first();
            $this->selectedAccountId = $default->id;
        }

        if (request()->has('compose_to')) {
            $this->composeTo = request()->get('compose_to');
            $this->showComposeModal = true;
            if ($this->selectedAccountId) {
                $this->composeAccountId = $this->selectedAccountId;
            }
        }

        if (request()->get('folder') === 'MailFiles') {
            $this->openMailFilesView();
        }
    }

    public function selectAccount($id)
    {
        $this->selectedAccountId = $id;
        $this->selectedFolder = 'INBOX';
        $this->selectedMessageId = null;
        $this->viewMode = 'inbox';
    }

    public function selectGlobalFolder(string $folderKey): void
    {
        if ($folderKey === 'MailFiles') {
            $this->openMailFilesView();
            return;
        }
        $this->selectedAccountId = null;
        $this->selectedFolder = in_array($folderKey, ['AllInboxes', 'Processed'], true) ? $folderKey : 'AllInboxes';
        $this->selectedMessageId = null;
        $this->viewMode = 'inbox';
        $this->dispatch('folder-selected');
    }

    public function openMailFilesView(): void
    {
        $this->selectedFolder = 'MailFiles';
        $this->selectedMessageId = null;
        $this->viewMode = 'mail_files';
        $this->dispatch('folder-selected');
    }

    public function openMailFromAttachment(int $messageId): void
    {
        $msg = MailMessage::find($messageId);
        if (!$msg) {
            return;
        }
        $this->selectedAccountId = $msg->mail_account_id;
        $this->selectedFolder = $msg->is_archived ? 'Archive' : $msg->folder;
        $this->selectedMessageId = $msg->id;
        $this->previewAttachmentId = null;
        $this->viewMode = 'inbox';
        $this->dispatch('message-selected');
    }

    public function openAttachmentPreview(int $attachmentId): void
    {
        $this->previewAttachmentId = $attachmentId;
    }

    public function closeAttachmentPreview(): void
    {
        $this->previewAttachmentId = null;
    }

    public function bookMailAttachment(int $attachmentId, \App\Services\Management\MailAttachmentFunnelService $funnelService): void
    {
        $att = \App\Models\Management\Mail\MailAttachment::with('message')->find($attachmentId);
        if (!$att) {
            return;
        }
        $res = $funnelService->processInvoiceToAccounting($att, isManualAction: true);
        if (!empty($res['success'])) {
            session()->flash('success_message', $res['message'] ?? 'Beleg verbucht.');
        } else {
            session()->flash('error_message', $res['message'] ?? 'Beleg konnte nicht gebucht werden.');
        }
    }

    public function sendMailAttachmentToTrichter(int $attachmentId, \App\Services\Management\MailAttachmentFunnelService $funnelService): void
    {
        $att = \App\Models\Management\Mail\MailAttachment::with('message')->find($attachmentId);
        if (!$att) {
            return;
        }
        $res = $funnelService->sendToDateitrichter($att);
        if (!empty($res['success'])) {
            session()->flash('success_message', $res['message'] ?? 'Im Dateitrichter abgelegt.');
        } else {
            session()->flash('error_message', $res['message'] ?? 'Übergabe an Dateitrichter fehlgeschlagen.');
        }
    }

    public function scanOpenMailInvoices(\App\Services\Management\MailAttachmentFunnelService $funnelService): void
    {
        $stats = $funnelService->backfillMetadataAndAutoProcessNew(autoBookInvoices: true, maxInvoiceScans: 15);
        session()->flash(
            'success_message',
            "Rechnungsprüfung: {$stats['booked']} verbucht, {$stats['already_booked']} bereits erfasst, {$stats['needs_review']} zur Prüfung."
        );
    }

    #[On('echo-private:crm-inbox,.MailReceivedEvent')]
    public function refreshInbox()
    {
        // Re-renders the component to display the newly fetched messages from the DB
    }

    public function syncMails()
    {
        if (app()->runningUnitTests()) {
            Artisan::call('crm:fetch-mails');
            return;
        }

        try {
            $artisan = escapeshellarg(base_path('artisan'));
            exec("nohup php {$artisan} crm:fetch-mails > /dev/null 2>&1 &");
            session()->flash('success_message', 'E-Mail Synchronisation im Hintergrund gestartet...');
        } catch (\Throwable $e) {
            Artisan::call('crm:fetch-mails');
            session()->flash('success_message', 'Synchronisation abgeschlossen.');
        }
    }

    public function runAiSort(\App\Services\Management\MailAiSortService $sortService)
    {
        $result = $sortService->sortAllMailsAndSubfolders();
        session()->flash('success_message', str_replace("\n", ' ', $result['message'] ?? 'KI-Sortierung abgeschlossen.'));
    }

    public function createTaskFromMail($messageId)
    {
        $mail = MailMessage::find($messageId);
        if (!$mail) return;

        $firstList = ManagementTaskList::orderBy('position')->first();
        $listId = $firstList ? $firstList->id : null;
        $maxPos = ManagementTask::where('task_list_id', $listId)->max('position') ?? 0;

        $bodySnippet = Str::limit(strip_tags((string) ($mail->body_plain ?: ($mail->body_html ?? ''))), 1000);
        $dateStr = $mail->received_at ? $mail->received_at->format('d.m.Y H:i') : now()->format('d.m.Y H:i');

        ManagementTask::create([
            'task_list_id' => $listId,
            'title' => 'E-Mail: ' . ($mail->subject ?: 'Ohne Betreff'),
            'ai_plan' => "Absender: {$mail->from_name} <{$mail->from_email}>\nDatum: {$dateStr}\n\n{$bodySnippet}",
            'priority' => in_array($mail->priority, ['high', 'hoch'], true) ? 'hoch' : 'mittel',
            'position' => $maxPos + 1,
            'relevant_from' => now(),
        ]);

        session()->flash('success_message', 'Aufgabe aus dieser E-Mail erfolgreich erstellt!');
    }

    public function createContactFromMail($messageId)
    {
        $mail = MailMessage::find($messageId);
        if (!$mail || empty($mail->from_email)) return;

        $existing = ManagementContact::where('email', $mail->from_email)->first();
        if ($existing) {
            session()->flash('info_message', "Kontakt '{$existing->full_name}' existiert bereits ({$existing->email}).");
            return;
        }

        $name = trim($mail->from_name ?: '');
        if ($name) {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        } else {
            $firstName = explode('@', $mail->from_email)[0];
            $lastName = '';
        }

        ManagementContact::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $mail->from_email,
            'relation_type' => 'business',
        ]);

        session()->flash('success_message', "Kontakt '{$firstName} {$lastName}' ({$mail->from_email}) erfolgreich angelegt!");
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
                $this->is_default = (bool) $account->is_default;
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

        if (!SystemSecurityService::isSafeMailHost((string) $this->imap_host) || !SystemSecurityService::isSafeMailHost((string) $this->smtp_host)) {
            SystemSecurityService::logEvent(
                'security:ssrf_mail_host_blocked',
                'ERROR',
                'Unsicherer oder interner Mail-Host (SSRF-Schutz) blockiert.',
                ['imap_host' => $this->imap_host, 'smtp_host' => $this->smtp_host],
                'idor_integrity'
            );
            $this->addError('imap_host', 'Ungültiger oder intern gesperrter Mail-Server Hostname.');
            return;
        }

        if ($this->editAccountId) {
            $existing = MailAccount::find($this->editAccountId);
            if ($existing && ($existing->imap_host !== $this->imap_host || $existing->smtp_host !== $this->smtp_host) && empty($this->password)) {
                $this->addError('password', 'Aus Sicherheitsgründen muss bei einer Änderung des Mail-Servers das Passwort erneut eingegeben werden.');
                return;
            }
        }

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

        $savedAccount = MailAccount::updateOrCreate(['id' => $this->editAccountId], $data);

        if ($this->is_default) {
            MailAccount::where('id', '!=', $savedAccount->id)->update(['is_default' => false]);
        }

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
            // 1. Move to Spam folder
            $msg->update(['folder' => 'Junk', 'is_archived' => false]);

            // 2. Add rule to automatically block this sender in the future & move existing INBOX mails from this sender
            if ($msg->from_email) {
                MailRule::firstOrCreate([
                    'mail_account_id' => $msg->mail_account_id,
                    'type' => 'blacklist',
                    'condition_field' => 'from_email',
                    'condition_value' => strtolower(trim($msg->from_email)),
                    'action' => 'mark_spam'
                ]);

                MailMessage::where('mail_account_id', $msg->mail_account_id)
                    ->whereRaw('LOWER(from_email) = ?', [strtolower(trim($msg->from_email))])
                    ->where('folder', 'INBOX')
                    ->update(['folder' => 'Junk', 'is_archived' => false]);
            }

            $this->selectedMessageId = null;
            session()->flash('success_message', 'E-Mail als Spam markiert und Absender automatisch in den Spam-Ordner geleitet.');
            $this->dispatch('folder-selected');
        }
    }

    public function unmarkSpam($id)
    {
        $msg = MailMessage::find($id);
        if ($msg && $msg->folder === 'Junk') {
            $msg->update(['folder' => 'INBOX', 'is_archived' => false]);

            // Remove the auto-blacklist rule if it exists
            if ($msg->from_email) {
                MailRule::where('mail_account_id', $msg->mail_account_id)
                    ->where('type', 'blacklist')
                    ->whereRaw('LOWER(condition_value) = ?', [strtolower(trim($msg->from_email))])
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
        $this->composeAttachments = [];
        $this->uploadedFiles = [];
        $this->composeCc = '';
        $this->composeBcc = '';
        $this->editingDraftId = null;

        if (($type === 'draft' || $type === 'reply') && $messageId) {
            $msg = MailMessage::find($messageId);
            if ($msg && $msg->folder === 'Drafts') {
                $this->editingDraftId = $msg->id;
                $this->composeAccountId = $msg->mail_account_id ?: $this->selectedAccountId;
                $this->composeTo = (string) $msg->to_email;
                $this->composeSubject = $msg->subject === '(Kein Betreff)' ? '' : (string) $msg->subject;
                $this->composeBody = $msg->body_html ?: nl2br(e((string) $msg->body_plain));
                $this->showComposeModal = true;
                return;
            }
        }

        if ($type === 'reply' && $messageId) {
            $msg = MailMessage::find($messageId);
            if ($msg) {
                $this->composeTo = $msg->from_email;
                $this->composeSubject = str_starts_with(strtolower((string) $msg->subject), 're:') ? $msg->subject : 'Re: ' . $msg->subject;
                $safeFromName = e((string) ($msg->from_name ?: $msg->from_email));
                $receivedFormatted = $msg->received_at ? $msg->received_at->format('d.m.Y H:i') : '';
                $this->composeBody = "<br><br><hr><p>Am " . $receivedFormatted . " schrieb " . $safeFromName . ":<br><i>" . str_replace("\n", "<br>", e(strip_tags((string) ($msg->body_plain ?? $msg->body_html)))) . "</i></p>" . $signature;
            }
        } elseif ($type === 'forward' && $messageId) {
            $msg = MailMessage::find($messageId);
            if ($msg) {
                $this->composeTo = '';
                $this->composeSubject = 'Fwd: ' . $msg->subject;
                $safeFromEmail = e((string) $msg->from_email);
                $receivedFormatted = $msg->received_at ? $msg->received_at->format('d.m.Y H:i') : '';
                $this->composeBody = "<br><br><hr><p>Weitergeleitete Nachricht von: " . $safeFromEmail . "<br>Datum: " . $receivedFormatted . "</p><br>" . ($msg->safe_body_html ?? nl2br(e($msg->body_plain))) . $signature;
                
                // Get all attachments from the original message and store them to be forwarded
                foreach (\App\Models\Management\Mail\MailAttachment::where('mail_message_id', $msg->id)->get() as $att) {
                    $this->composeAttachments[] = [
                        'path' => $att->path,
                        'filename' => $att->filename,
                        'mime' => $att->content_type,
                        'size' => $att->size,
                        'is_uploaded' => false,
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

    public function updatedUploadedFiles()
    {
        $this->validate([
            'uploadedFiles.*' => 'file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx,odt,ods,odp,png,jpg,jpeg,webp,gif,zip,tar,gz,txt,json,xml', // limit of 20MB per file
        ]);

        $forbiddenMimes = [
            'text/x-php',
            'application/x-httpd-php',
            'application/x-php',
            'text/html',
            'image/svg+xml',
            'application/x-dosexec',
            'application/x-executable',
            'application/x-sh',
        ];
        $allowedExts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'zip', 'tar', 'gz', 'txt', 'json', 'xml'];

        foreach ($this->uploadedFiles as $file) {
            $realPath = $file->getRealPath();
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $realPath ? finfo_file($finfo, $realPath) : false;
            finfo_close($finfo);
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (!$detectedMime || in_array($detectedMime, $forbiddenMimes, true) || !in_array($ext, $allowedExts, true)) {
                $this->addError('uploadedFiles', 'Dateityp aus Sicherheitsgründen nicht erlaubt.');
                $this->uploadedFiles = [];
                return;
            }
            $path = $file->storeAs('temp/mail-attachments', Str::uuid()->toString() . '.' . $ext);
            $this->composeAttachments[] = [
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $detectedMime,
                'size' => $file->getSize(),
                'is_uploaded' => true,
            ];
        }

        $this->uploadedFiles = [];
    }

    public function removeAttachment($index)
    {
        if (isset($this->composeAttachments[$index])) {
            $att = $this->composeAttachments[$index];
            if (!empty($att['is_uploaded'])) {
                \Illuminate\Support\Facades\Storage::delete($att['path']);
            }
            unset($this->composeAttachments[$index]);
            $this->composeAttachments = array_values($this->composeAttachments);
        }
    }

    public function closeCompose()
    {
        $to = trim((string) $this->composeTo);
        $subject = trim((string) $this->composeSubject);
        $plainBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", (string) $this->composeBody)));

        $accId = $this->composeAccountId ?: $this->selectedAccountId;
        $account = $accId ? MailAccount::find($accId) : MailAccount::first();

        if ($account && ($to !== '' || $subject !== '' || $plainBody !== '')) {
            $draft = $this->editingDraftId
                ? MailMessage::where('id', $this->editingDraftId)->where('folder', 'Drafts')->first()
                : null;

            $finalSubject = $subject !== '' ? $subject : '(Kein Betreff)';
            if ($draft) {
                $draft->update([
                    'mail_account_id' => $account->id,
                    'subject' => $finalSubject,
                    'from_name' => $account->name,
                    'from_email' => $account->email,
                    'to_email' => $to,
                    'body_plain' => $plainBody,
                    'body_html' => $this->composeBody,
                    'is_read' => true,
                    'is_archived' => false,
                    'received_at' => now(),
                ]);
            } else {
                MailMessage::create([
                    'mail_account_id' => $account->id,
                    'message_id' => 'draft-' . uniqid('', true),
                    'folder' => 'Drafts',
                    'subject' => $finalSubject,
                    'from_name' => $account->name,
                    'from_email' => $account->email,
                    'to_email' => $to,
                    'body_plain' => $plainBody,
                    'body_html' => $this->composeBody,
                    'is_read' => true,
                    'is_archived' => false,
                    'has_attachments' => false,
                    'received_at' => now(),
                ]);
            }
            session()->flash('success_message', 'E-Mail wurde automatisch als Entwurf gespeichert.');
        }

        foreach ($this->composeAttachments as $att) {
            if (!empty($att['is_uploaded'])) {
                \Illuminate\Support\Facades\Storage::delete($att['path']);
            }
        }
        $this->composeAttachments = [];
        $this->uploadedFiles = [];
        $this->composeCc = '';
        $this->composeBcc = '';
        $this->editingDraftId = null;
        $this->showComposeModal = false;
    }

    public function parseEmailList(?string $input): array
    {
        if (empty($input)) {
            return [];
        }

        $emails = preg_split('/[,;]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        $clean = [];

        foreach ($emails as $email) {
            $t = trim($email);
            if (preg_match('/<([^>]+)>/', $t, $matches)) {
                $t = trim($matches[1]);
            }
            if (filter_var($t, FILTER_VALIDATE_EMAIL)) {
                $clean[] = $t;
            }
        }

        return array_values(array_unique($clean));
    }

    public function sendMail()
    {
        $this->validate([
            'composeTo' => 'required|email',
            'composeSubject' => 'required|string',
            'composeBody' => 'required|string',
            'composeAccountId' => 'required'
        ]);

        if (!empty(trim($this->composeCc))) {
            $rawCc = preg_split('/[,;]+/', $this->composeCc, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($rawCc as $item) {
                $trimmed = trim($item);
                if (preg_match('/<([^>]+)>/', $trimmed, $matches)) {
                    $trimmed = trim($matches[1]);
                }
                if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('composeCc', "Ungültige CC-E-Mail-Adresse: {$trimmed}");
                    return;
                }
            }
        }

        if (!empty(trim($this->composeBcc))) {
            $rawBcc = preg_split('/[,;]+/', $this->composeBcc, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($rawBcc as $item) {
                $trimmed = trim($item);
                if (preg_match('/<([^>]+)>/', $trimmed, $matches)) {
                    $trimmed = trim($matches[1]);
                }
                if (!filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    $this->addError('composeBcc', "Ungültige BCC-E-Mail-Adresse: {$trimmed}");
                    return;
                }
            }
        }

        $totalSize = collect($this->composeAttachments)->sum('size');
        if ($totalSize > 20 * 1024 * 1024) {
            session()->flash('error_message', 'Senden fehlgeschlagen: Die Gesamtgröße der Anhänge überschreitet das Limit von 20 MB.');
            $this->addError('uploadedFiles', 'Die Gesamtgröße der Anhänge überschreitet das Limit von 20 MB.');
            return;
        }

        $account = MailAccount::find($this->composeAccountId);

        if (!$account || !$account->smtp_host) {
            session()->flash('error_message', 'SMTP Konfiguration für dieses Konto fehlt!');
            return;
        }

        if (!\App\Services\System\SystemSecurityService::isSafeMailHost((string) $account->smtp_host)) {
            session()->flash('error_message', 'Ungültiger oder interner SMTP-Host ist aus Sicherheitsgründen gesperrt.');
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
            foreach ($this->composeAttachments as $att) {
                $attachmentDataParams[] = $att;
            }

            $ccList = $this->parseEmailList($this->composeCc);
            $bccList = $this->parseEmailList($this->composeBcc);

            $mailable = new \App\Mail\CrmOutgoingMailToCustomer(
                $this->composeSubject,
                $bodyHtml,
                $signatureHtml,
                $account->email,
                $account->name,
                $attachmentDataParams
            );

            // Senden ausführen
            $pendingMail = app()->environment('testing')
                ? Mail::to($this->composeTo)
                : Mail::mailer('dynamic')->to($this->composeTo);

            if (!empty($ccList)) {
                $pendingMail->cc($ccList);
            }
            if (!empty($bccList)) {
                $pendingMail->bcc($bccList);
            }

            $pendingMail->send($mailable);

            // Kopie im lokalen System ablegen (Ordner: Sent)
            $sentMessage = MailMessage::create([
                'mail_account_id' => $account->id,
                'message_id' => 'sent-' . uniqid(),
                'folder' => 'Sent',
                'subject' => $this->composeSubject,
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => $this->composeTo,
                'cc_email' => !empty($ccList) ? implode(', ', $ccList) : null,
                'bcc_email' => !empty($bccList) ? implode(', ', $bccList) : null,
                'body_plain' => $this->composeBody,
                'body_html' => view('global.mails.crm_outgoing_mail_to_customer', compact('bodyHtml', 'signatureHtml'))->render(),
                'is_read' => true,
                'has_attachments' => count($attachmentDataParams) > 0,
                'received_at' => now()
            ]);

            // Save forwarded attachments in the DB for the sent message so they appear in Sent folder
            foreach ($attachmentDataParams as $att) {
                $finalPath = $att['path'];
                if (!empty($att['is_uploaded']) || str_starts_with((string) $finalPath, 'temp/mail-attachments/')) {
                    $newPath = 'leitung/crm/mail-attachments/' . basename((string) $finalPath);
                    if (\Illuminate\Support\Facades\Storage::exists($finalPath)) {
                        \Illuminate\Support\Facades\Storage::move($finalPath, $newPath);
                        $finalPath = $newPath;
                    }
                }

                \App\Models\Management\Mail\MailAttachment::create([
                    'mail_message_id' => $sentMessage->id,
                    'filename' => $att['filename'],
                    'content_type' => $att['mime'],
                    'size' => $att['size'],
                    'path' => $finalPath,
                ]);
            }

            if ($this->editingDraftId) {
                MailMessage::where('id', $this->editingDraftId)->where('folder', 'Drafts')->delete();
            }

            $this->composeAttachments = [];
            $this->uploadedFiles = [];
            $this->composeCc = '';
            $this->composeBcc = '';
            $this->editingDraftId = null;
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
        $selectedMessage = $this->selectedMessageId ? MailMessage::with('attachments')->find($this->selectedMessageId) : null;

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
            // Append Custom Folders (alphabetically sorted)
            $customFolders = \App\Models\Management\Mail\MailFolder::where('mail_account_id', $acc->id)->orderBy('name')->pluck('name')->toArray();
            natcasesort($customFolders);
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
                $escapedQuery = addcslashes((string) $this->searchQuery, '%_\\');
                $search = '%' . $escapedQuery . '%';
                $searchLower = '%' . strtolower($escapedQuery) . '%';
                
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

            $messages = $query->orderByRaw('COALESCE(received_at, created_at) DESC')->orderBy('id', 'desc')->get();
        } elseif ($this->selectedFolder === 'AllInboxes') {
            $query = MailMessage::where('folder', 'INBOX')->where('is_archived', false);
            if ($this->filterMode === 'unread') {
                $query->where('is_read', false);
            } elseif ($this->filterMode === 'attachments') {
                $query->where('has_attachments', true);
            }
            $messages = $query->orderByRaw('COALESCE(received_at, created_at) DESC')->orderBy('id', 'desc')->get();
        } elseif ($this->selectedFolder === 'Processed') {
            $query = MailMessage::where('folder', 'Processed')->where('is_archived', false);
            if ($this->filterMode === 'unread') {
                $query->where('is_read', false);
            } elseif ($this->filterMode === 'attachments') {
                $query->where('has_attachments', true);
            }
            $messages = $query->orderByRaw('COALESCE(received_at, created_at) DESC')->orderBy('id', 'desc')->get();
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

            $messages = $query->orderByRaw('COALESCE(received_at, created_at) DESC')->orderBy('id', 'desc')->get();
        }

        $contactEmails = ManagementContact::whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['first_name', 'last_name', 'email']);

        app(\App\Services\Management\MailAttachmentFunnelService::class)->backfillMetadataAndAutoProcessNew(false);

        $accountsById = $accounts->keyBy('id');
        $filesBase = \App\Models\Management\Mail\MailAttachment::query()
            ->with(['message' => fn($q) => $q->select('id', 'mail_account_id', 'folder', 'subject', 'from_name', 'from_email', 'received_at', 'is_archived')])
            ->whereHas('message', fn($q) => $q->where('folder', '!=', 'Junk'))
            ->where(function ($q) {
                $q->whereNull('funnel_type')->orWhere('funnel_type', '!=', 'inline_signature');
            });

        $mailFilesSummary = [
            'total' => (clone $filesBase)->count(),
            'invoices' => (clone $filesBase)->where('funnel_type', 'invoice')->count(),
            'documents' => (clone $filesBase)->whereIn('funnel_type', ['document', 'legal_notice', 'archive'])->count(),
            'images' => (clone $filesBase)->where('funnel_type', 'image')->count(),
            'needs_review' => (clone $filesBase)->where('funnel_status', 'needs_review')->count(),
            'booked' => (clone $filesBase)->whereIn('funnel_status', ['booked', 'already_booked'])->count(),
            'workspace' => (clone $filesBase)->where('funnel_status', 'workspace')->count(),
        ];

        $globalCounts = [
            'all_inboxes_unread' => MailMessage::where('folder', 'INBOX')->where('is_archived', false)->where('is_read', false)->count(),
            'processed_open' => MailMessage::where('folder', 'Processed')->where('is_archived', false)->count(),
            'mail_files_total' => $mailFilesSummary['total'],
            'mail_files_review' => $mailFilesSummary['needs_review'],
        ];

        $mailFiles = collect();
        if ($this->viewMode === 'mail_files' || $this->selectedFolder === 'MailFiles') {
            $fq = clone $filesBase;
            if ($this->mailFileScopeFilter === 'commercial') {
                $commIds = $accounts->filter(fn($a) => (bool) ($a->is_commercial ?? true))->pluck('id')->all();
                $fq->whereHas('message', fn($q) => $q->whereIn('mail_account_id', $commIds));
            } elseif ($this->mailFileScopeFilter === 'private') {
                $privIds = $accounts->filter(fn($a) => !($a->is_commercial ?? true))->pluck('id')->all();
                $fq->whereHas('message', fn($q) => $q->whereIn('mail_account_id', $privIds));
            }

            if ($this->mailFileTypeFilter === 'invoice') {
                $fq->where('funnel_type', 'invoice');
            } elseif ($this->mailFileTypeFilter === 'document') {
                $fq->whereIn('funnel_type', ['document', 'legal_notice', 'archive']);
            } elseif ($this->mailFileTypeFilter === 'image') {
                $fq->where('funnel_type', 'image');
            } elseif ($this->mailFileTypeFilter === 'needs_review') {
                $fq->where('funnel_status', 'needs_review');
            } elseif ($this->mailFileTypeFilter === 'booked') {
                $fq->whereIn('funnel_status', ['booked', 'already_booked']);
            } elseif ($this->mailFileTypeFilter === 'workspace') {
                $fq->where('funnel_status', 'workspace');
            }

            if (trim($this->mailFileSearch) !== '') {
                $esc = addcslashes(trim($this->mailFileSearch), '%_\\');
                $like = '%' . $esc . '%';
                $fq->where(function ($q) use ($like) {
                    $q->where('filename', 'like', $like)
                        ->orWhereHas('message', function ($mq) use ($like) {
                            $mq->where('subject', 'like', $like)
                                ->orWhere('from_email', 'like', $like)
                                ->orWhere('from_name', 'like', $like);
                        });
                });
            }

            $mailFiles = $fq->orderByDesc('id')->limit(200)->get();
        }

        $previewAttachment = $this->previewAttachmentId
            ? \App\Models\Management\Mail\MailAttachment::with('message')->find($this->previewAttachmentId)
            : null;

        return view('livewire.shop.management.management-e-mails', [
            'accounts' => $accounts,
            'accountsById' => $accountsById,
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'accountTree' => $accountTree,
            'folders' => $this->selectedAccountId && isset($accountTree[$this->selectedAccountId]) ? $accountTree[$this->selectedAccountId]['folders'] : $baseFolders,
            'folderCounts' => $this->selectedAccountId && isset($accountTree[$this->selectedAccountId]) ? $accountTree[$this->selectedAccountId]['counts'] : [],
            'contactEmails' => $contactEmails,
            'globalCounts' => $globalCounts,
            'mailFiles' => $mailFiles,
            'mailFilesSummary' => $mailFilesSummary,
            'previewAttachment' => $previewAttachment,
        ]);
    }

    public function downloadAttachment($attachmentId, \App\Services\Export\FileDownloadService $exportService)
    {
        return $exportService->downloadMailAttachment($attachmentId);
    }

    /**
     * Öffnet das Antwort-Modal mit einem faktengeprüften KI-Antwort-Entwurf (inkl. Bestell- & Tracking-Dossier).
     */
    public function openAiReplyDraft(int $messageId): void
    {
        $msg = MailMessage::with('attachments')->find($messageId);
        if (!$msg) {
            return;
        }

        $this->openCompose('reply', $messageId);

        $tags = is_array($msg->tags) ? $msg->tags : [];
        $draftPlain = (string) ($tags['ai_reply_draft']['draft_text'] ?? '');

        if ($draftPlain === '') {
            $dossier = \App\Services\AI\ContextDossierBuilder::buildMailDossier($msg);
            if ($dossier) {
                $rawDraft = \App\Services\AI\SfInternalOrchestrator::buildGroundedMailReplyDraft($dossier);
                $draftPlain = \App\Services\AI\AiOutputQualityGate::sanitizeText($rawDraft);
            }
        }

        if ($draftPlain !== '') {
            $draftHtml = '<p>' . nl2br(e($draftPlain)) . '</p>';
            $this->composeBody = $draftHtml . $this->composeBody;
        }
    }

    /**
     * Bucht den Rechnungs-/Beleg-Anhang einer E-Mail per 1-Klick über die zentrale Buchi-Beleg-Pipeline.
     */
    public function bookReceiptFromMail(int $messageId): void
    {
        $msg = MailMessage::with('attachments')->find($messageId);
        if (!$msg || $msg->attachments->isEmpty()) {
            session()->flash('error_message', 'Diese E-Mail enthält keinen auslesbaren Beleg-Anhang.');
            return;
        }

        $adminId = \Illuminate\Support\Facades\Auth::guard('admin')->id() ?? \App\Models\Admin\Admin::value('id');
        $scannedFile = null;
        $analysis = null;

        foreach ($msg->attachments as $att) {
            $ext = strtolower(pathinfo((string) ($att->filename ?? $att->path), PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            if (!empty($att->path) && !str_contains((string) $att->path, '..') && \Illuminate\Support\Facades\Storage::disk('local')->exists($att->path)) {
                $candidatePath = \Illuminate\Support\Facades\Storage::disk('local')->path($att->path);
                $analysis = \App\Services\AI\BuchiReceiptPipelineService::analyzeAndVerify($candidatePath, [], $adminId);
                if ($analysis && !empty($analysis['amount_abs'])) {
                    $scannedFile = $candidatePath;
                    break;
                }
            }
        }

        if (!$analysis || !$scannedFile) {
            session()->flash('error_message', 'Kein buchbarer Rechnungsbeleg in den Anhängen erkannt.');
            return;
        }

        if (!empty($analysis['duplicate_check']['is_duplicate'])) {
            session()->flash('error_message', 'Dubletten-Schutz: ' . ($analysis['duplicate_check']['reason'] ?? 'Beleg existiert bereits.'));
            return;
        }

        $storedPath = \App\Services\AI\BuchiReceiptPipelineService::storeReceiptFile(
            file: $scannedFile,
            date: (string) $analysis['date'],
            merchant: (string) ($analysis['merchant'] ?: $analysis['location']),
            title: (string) $analysis['title'],
            invoiceNumber: $analysis['is_business'] ? (string) ($analysis['invoice_number'] ?? '') : ''
        );

        $issue = \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $adminId,
            'title' => $analysis['title'],
            'location' => $analysis['location'] ?: $analysis['merchant'],
            'category' => $analysis['category'],
            'amount' => $analysis['amount'],
            'execution_date' => $analysis['date'],
            'is_business' => $analysis['is_business'],
            'tax_rate' => $analysis['is_business'] ? $analysis['tax_rate'] : null,
            'invoice_number' => $analysis['is_business'] ? ($analysis['invoice_number'] ?: null) : null,
            'file_paths' => $storedPath ? [$storedPath] : [],
            'note' => \App\Services\AI\BuchiReceiptPipelineService::withLiveMarker('Buchi E-Mail-Beleg'),
        ]);

        \App\Services\AI\BuchiReceiptPipelineService::trackCategoryUsage($adminId, (string) $analysis['category'], (bool) $analysis['is_business']);

        $tags = is_array($msg->tags) ? $msg->tags : [];
        $tags['ai_receipt_booked_id'] = (string) $issue->id;
        unset($tags['ai_receipt_draft']);
        $msg->update(['tags' => $tags]);

        session()->flash('success_message', "Beleg '{$issue->title}' ({$analysis['amount_formatted']} €) sauber als Sonderausgabe gebucht!");
    }
}
