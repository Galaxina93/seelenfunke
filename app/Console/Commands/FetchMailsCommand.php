<?php

namespace App\Console\Commands;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchMailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:fetch-mails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ruft per IMAP neue E-Mails von allen angelegten Postfächern ab und speichert sie in der Datenbank.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = MailAccount::where('status', 'connected')->get();
        if ($accounts->isEmpty()) {
            $this->info("Keine aktiven Mail-Konten gefunden.");
            return;
        }

        foreach ($accounts as $account) {
            $this->info("Postfach wird verarbeitet: {$account->email}...");

            try {
                // Dynamically build IMAP config array
                $client = \Webklex\IMAP\Facades\Client::make([
                    'host'          => $account->imap_host,
                    'port'          => $account->imap_port,
                    'encryption'    => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                    'validate_cert' => true,
                    'username'      => $account->imap_username ?: $account->email,
                    'password'      => $account->password,
                    'protocol'      => 'imap',
                ]);

                $client->connect();

                // Get Posteingang/INBOX
                $folder = $client->getFolder('INBOX');

                // Get All Messages from the last 7 days (ignoring Seen status to avoid race conditions with Mail Clients)
                $messages = $folder->query()->since(now()->subDays(7))->get();
                $count = 0;

                foreach ($messages as $message) {
                    $uid = $message->getUid();
                    $messageId = $message->getMessageId();

                    // Skip if we already stored it
                    if (MailMessage::where('message_id', $messageId)->exists()) {
                        continue;
                    }

                    $bodyHtml = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                    $bodyText = $message->hasTextBody() ? $message->getTextBody() : null;

                    $fromObj = $message->getFrom()[0] ?? null;
                    $fromEmail = $fromObj ? $fromObj->mail : 'unknown';
                    $fromName = $fromObj ? $fromObj->personal : '';

                    $toObj = $message->getTo()[0] ?? null;
                    $toEmail = $toObj ? $toObj->mail : $account->email;

                    // Execute Auto-Routing Rules here (Spam/Folders)
                    $targetFolder = 'INBOX';
                    $routingRule = \App\Models\Mail\MailRule::where('mail_account_id', $account->id)
                        ->where('condition_field', 'from_email')
                        ->where('condition_value', $fromEmail)
                        ->first();

                    if ($routingRule) {
                        if ($routingRule->action === 'mark_spam') {
                            $targetFolder = 'Junk';
                        } else {
                            $targetFolder = $routingRule->action;
                        }
                    }

                    MailMessage::create([
                        'mail_account_id' => $account->id,
                        'message_id' => $messageId[0] ?? uniqid('msg_'),
                        'folder' => $targetFolder,
                        'subject' => $message->getSubject() ?: 'Kein Betreff',
                        'from_name' => str_replace('"', '', $fromName),
                        'from_email' => $fromEmail,
                        'to_email' => $toEmail,
                        'body_plain' => $bodyText,
                        'body_html' => $bodyHtml,
                        'is_read' => false,
                        'is_archived' => false,
                        'has_attachments' => $message->hasAttachments(),
                        'received_at' => Carbon::parse($message->getDate())
                    ]);

                    // Set message as SEEN on remote server so we don't fetch it again next poll loop
                    $message->setFlag('Seen');
                    $count++;
                }

                $this->info("   -> {$count} neue Mails in Datenbank importiert.");
                $client->disconnect();

            } catch (\Exception $e) {
                Log::error("IMAP Fetch failed for {$account->email}: " . $e->getMessage());
                $this->error("FEHLER beim Abruf von {$account->email} - siehe Laravel Logs.");
            }
        }
    }
}
