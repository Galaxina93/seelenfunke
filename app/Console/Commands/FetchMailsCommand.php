<?php

namespace App\Console\Commands;

use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailAttachment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $accounts = MailAccount::all();
        if ($accounts->isEmpty()) {
            $this->info("Keine aktiven Mail-Konten gefunden.");
            return;
        }

        foreach ($accounts as $account) {
            $this->info("Postfach wird verarbeitet: {$account->email}...");

            try {
                // Dynamically build IMAP config array with connection timeouts to prevent scheduler hangs
                $client = \Webklex\IMAP\Facades\Client::make([
                    'host'          => $account->imap_host,
                    'port'          => $account->imap_port,
                    'encryption'    => $account->imap_encryption === 'none' ? false : $account->imap_encryption,
                    'validate_cert' => true,
                    'username'      => $account->imap_username ?: $account->email,
                    'password'      => $account->password,
                    'protocol'      => 'imap',
                    'timeout'       => 15,
                    'options'       => [
                        'timeout' => 15,
                    ]
                ]);

                $client->connect();

                // Get Posteingang/INBOX
                $folder = $client->getFolder('INBOX');

                // Get All Messages from the last 7 days (ignoring Seen status to avoid race conditions with Mail Clients)
                $messages = $folder->query()->since(now()->subDays(7))->get();
                $count = 0;

                foreach ($messages as $message) {
                    try {
                        $uid = $message->getUid();
                        $messageId = $message->getMessageId();

                    // Skip if we already stored it
                    if (MailMessage::where('message_id', $messageId)->exists()) {
                        continue;
                    }

                    $bodyHtml = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                    $bodyText = $message->hasTextBody() ? $message->getTextBody() : null;

                    // Sanitize HTML body to strip out tracking pixels (like stat.alibaba.com)
                    if ($bodyHtml) {
                        // 1. Remove img tags containing known tracking domain patterns
                        $bodyHtml = preg_replace('/<img[^>]+src=["\'][^"\']*(?:stat\.alibaba|mail_callback|tracelog|open\.php|pixel|track|google-analytics)[^"\']*["\'][^>]*>/i', '', $bodyHtml);
                        // 2. Remove any img tags trying to render as 1x1 or 0x0
                        $bodyHtml = preg_replace('/<img[^>]+(?:width=["\']?[01]["\']?[^>]*height=["\']?[01]["\']?|height=["\']?[01]["\']?[^>]*width=["\']?[01]["\']?)[^>]*>/i', '', $bodyHtml);
                    }

                    $fromObj = $message->getFrom()[0] ?? null;
                    $fromEmail = $fromObj ? $fromObj->mail : 'unknown';
                    // Decode MIME strings for names (like "=?utf-8?Q?..." to readable names)
                    $rawFromName = $fromObj ? $fromObj->personal : '';
                    $fromName = mb_decode_mimeheader((string)$rawFromName) ?: $rawFromName;

                    $toObj = $message->getTo()[0] ?? null;
                    $toEmail = $toObj ? $toObj->mail : $account->email;

                    // Execute Auto-Routing Rules here (Spam/Folders)
                    $targetFolder = 'INBOX';
                    $routingRule = \App\Models\Management\Mail\MailRule::where('mail_account_id', $account->id)
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

                    // Decode MIME encoded subjects
                    $rawSubject = (string)$message->getSubject();
                    $decodedSubject = mb_decode_mimeheader($rawSubject) ?: $rawSubject;

                    $mailMessage = MailMessage::create([
                        'mail_account_id' => $account->id,
                        'message_id' => is_array($messageId) ? ($messageId[0] ?? uniqid('msg_')) : ($messageId ?: uniqid('msg_')),
                        'folder' => $targetFolder,
                        'subject' => $decodedSubject ?: 'Kein Betreff',
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

                    if ($message->hasAttachments()) {
                        $attachments = $message->getAttachments();
                        foreach ($attachments as $attachment) {
                            $rawFilename = (string)$attachment->getName();
                            $decodedFilename = mb_decode_mimeheader($rawFilename) ?: $rawFilename;
                            $filename = $decodedFilename ?: 'unknown_file_' . Str::random(5);
                            
                            $extension = $attachment->getExtension() ?: 'dat';
                            $safeFilename = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.' . $extension;

                            // Save to a secure local folder
                            $filePath = 'leitung/crm/mail-attachments/' . $mailMessage->id . '/' . uniqid() . '_' . $safeFilename;
                            Storage::put($filePath, $attachment->getContent());

                            MailAttachment::create([
                                'mail_message_id' => $mailMessage->id,
                                'filename' => $filename,
                                'content_type' => $attachment->getMimeType(),
                                'size' => $attachment->getSize() ?? 0,
                                'path' => $filePath,
                                'content_id' => $attachment->getId()
                            ]);
                        }
                    }

                    // Set message as SEEN on remote server so we don't fetch it again next poll loop
                    $message->setFlag('Seen');
                    $count++;
                    } catch (\Exception $e) {
                        Log::error("Failed to import single message for {$account->email}: " . $e->getMessage());
                    }
                }

                $this->info("   -> {$count} neue Mails in Datenbank importiert.");
                
                if ($count > 0) {
                    event(new \App\Events\Management\MailReceivedEvent());
                }
                $client->disconnect();

                $account->update(['status' => 'connected', 'last_sync_at' => now()]);

            } catch (\Exception $e) {
                Log::error("IMAP Fetch failed for {$account->email}: " . $e->getMessage());
                $this->error("FEHLER beim Abruf von {$account->email} - siehe Laravel Logs.");
                
                $account->update(['status' => 'error']);
            }
        }
    }
}
