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

            if (!\App\Services\System\SystemSecurityService::isSafeMailHost((string) $account->imap_host)) {
                Log::warning("IMAP Fetch blocked for {$account->email}: Unsafe IMAP host '{$account->imap_host}' (SSRF guard).");
                $this->error("FEHLER: Unsicherer IMAP-Host für {$account->email} blockiert.");
                $account->update(['status' => 'error']);
                continue;
            }

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
                        $rawMessageId = $message->getMessageId();
                        $normalizedMessageId = is_array($rawMessageId)
                            ? (string) ($rawMessageId[0] ?? '')
                            : (is_object($rawMessageId) && method_exists($rawMessageId, '__toString') ? (string) $rawMessageId : (string) $rawMessageId);
                        $normalizedMessageId = trim($normalizedMessageId);

                    // Skip if we already stored it for this account
                    if ($normalizedMessageId !== '' && MailMessage::where('mail_account_id', $account->id)->where('message_id', $normalizedMessageId)->exists()) {
                        continue;
                    }

                    $bodyHtml = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                    $bodyText = $message->hasTextBody() ? $message->getTextBody() : null;

                    // Sanitize HTML body to strip out tracking pixels and XSS payloads
                    if ($bodyHtml) {
                        $bodyHtml = self::sanitizeEmailHtml($bodyHtml);
                    }

                    $fromObj = $message->getFrom()[0] ?? null;
                    $fromEmail = $fromObj ? $fromObj->mail : 'unknown';
                    // Decode MIME strings for names (like "=?utf-8?Q?..." to readable names)
                    $rawFromName = $fromObj ? $fromObj->personal : '';
                    $fromName = mb_decode_mimeheader((string)$rawFromName) ?: $rawFromName;

                    $toObj = $message->getTo()[0] ?? null;
                    $toEmail = $toObj ? $toObj->mail : $account->email;

                    $ccEmails = [];
                    if (method_exists($message, 'getCc')) {
                        $ccObjs = $message->getCc();
                        if ($ccObjs) {
                            foreach ($ccObjs as $ccObj) {
                                if (!empty($ccObj->mail)) {
                                    $ccEmails[] = $ccObj->mail;
                                }
                            }
                        }
                    }
                    $ccEmail = !empty($ccEmails) ? implode(', ', array_unique($ccEmails)) : null;

                    // Execute Auto-Routing Rules here (Spam/Folders)
                    $targetFolder = 'INBOX';
                    $routingRule = \App\Models\Management\Mail\MailRule::where('mail_account_id', $account->id)
                        ->where('condition_field', 'from_email')
                        ->where('condition_value', $fromEmail)
                        ->first();

                    if ($routingRule) {
                        if ($routingRule->action === 'mark_spam') {
                            $targetFolder = 'Junk';
                        } elseif ($routingRule->action === 'delete') {
                            $targetFolder = 'Trash';
                        } elseif ($routingRule->action === 'archive') {
                            $targetFolder = 'Archive';
                        } else {
                            $cleanFolder = mb_substr(preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', str_replace('..', '', trim((string) $routingRule->action))), 0, 64);
                            $targetFolder = $cleanFolder !== '' ? $cleanFolder : 'INBOX';
                        }
                    }

                    // Decode MIME encoded subjects
                    $rawSubject = (string)$message->getSubject();
                    $decodedSubject = mb_decode_mimeheader($rawSubject) ?: $rawSubject;

                    $mailMessage = MailMessage::create([
                        'mail_account_id' => $account->id,
                        'message_id' => $normalizedMessageId !== '' ? $normalizedMessageId : uniqid('msg_'),
                        'folder' => $targetFolder,
                        'subject' => $decodedSubject ?: 'Kein Betreff',
                        'from_name' => str_replace('"', '', $fromName),
                        'from_email' => $fromEmail,
                        'to_email' => $toEmail,
                        'cc_email' => $ccEmail,
                        'body_plain' => $bodyText,
                        'body_html' => $bodyHtml,
                        'is_read' => false,
                        'is_archived' => false,
                        'has_attachments' => $message->hasAttachments(),
                        'received_at' => Carbon::parse($message->getDate())
                    ]);

                    if ($message->hasAttachments()) {
                        $attachments = $message->getAttachments();
                        $maxAttachmentBytes = 25 * 1024 * 1024;
                        foreach ($attachments as $attachment) {
                            $reportedSize = (int) ($attachment->getSize() ?? 0);
                            if ($reportedSize > $maxAttachmentBytes) {
                                Log::warning("Skipping oversized attachment ({$reportedSize} bytes) for mail {$mailMessage->id}");
                                continue;
                            }

                            $content = $attachment->getContent();
                            $actualSize = strlen((string) $content);
                            if ($actualSize > $maxAttachmentBytes) {
                                Log::warning("Skipping oversized attachment payload ({$actualSize} bytes) for mail {$mailMessage->id}");
                                continue;
                            }

                            $rawFilename = (string)$attachment->getName();
                            $decodedFilename = mb_decode_mimeheader($rawFilename) ?: $rawFilename;
                            $filename = $decodedFilename ?: 'unknown_file_' . Str::random(5);
                            
                            $safeFilename = self::sanitizeAttachmentFilename($filename, (string) ($attachment->getExtension() ?: 'dat'));

                            // Save to a secure local folder
                            $filePath = 'leitung/crm/mail-attachments/' . $mailMessage->id . '/' . uniqid() . '_' . $safeFilename;
                            Storage::disk('local')->put($filePath, $content);

                            MailAttachment::create([
                                'mail_message_id' => $mailMessage->id,
                                'filename' => $filename,
                                'content_type' => $attachment->getMimeType(),
                                'size' => $reportedSize > 0 ? $reportedSize : $actualSize,
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

    public static function sanitizeEmailHtml(?string $bodyHtml): ?string
    {
        if ($bodyHtml === null || $bodyHtml === '') {
            return $bodyHtml;
        }

        // 1. Remove img tags containing known tracking domain patterns
        $bodyHtml = preg_replace('/<img[^>]+src=["\'][^"\']*(?:stat\.alibaba|mail_callback|tracelog|open\.php|pixel|track|google-analytics)[^"\']*["\'][^>]*>/i', '', $bodyHtml);
        // 2. Remove any img tags trying to render as 1x1 or 0x0
        $bodyHtml = preg_replace('/<img[^>]+(?:width=["\']?[01]["\']?[^>]*height=["\']?[01]["\']?|height=["\']?[01]["\']?[^>]*width=["\']?[01]["\']?)[^>]*>/i', '', $bodyHtml);
        // 3. Remove dangerous tags (<script>, <iframe>, <object>, <embed>, <applet>, <form>, <meta>, <base>, <svg>)
        $bodyHtml = preg_replace('/<(script|iframe|object|embed|applet|form|meta|base|svg)\b[^>]*>.*?<\/\1>/is', '', $bodyHtml);
        $bodyHtml = preg_replace('/<(script|iframe|object|embed|applet|form|meta|base|svg)\b[^>]*\/?>/is', '', $bodyHtml);
        // 4. Remove inline event handlers (onload, onerror, onclick, etc.)
        $bodyHtml = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $bodyHtml);
        // 5. Neutralize javascript:, vbscript:, data:text/html, data:image/svg+xml, data:application/xhtml+xml URIs in href/src/action/formaction/xlink:href
        $bodyHtml = preg_replace('/\b(href|src|action|formaction|xlink:href)\s*=\s*(["\']?)\s*(?:javascript|vbscript|data\s*:\s*(?:text\/html|image\/svg\+xml|application\/xhtml\+xml))[^"\'>\s]*/i', '$1=$2#', $bodyHtml);

        return $bodyHtml;
    }

    public static function sanitizeAttachmentFilename(string $filename, ?string $rawExtension = null): string
    {
        $base = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'attachment';
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) ($rawExtension ?: pathinfo($filename, PATHINFO_EXTENSION))));
        if ($ext === '') {
            $ext = 'dat';
        }

        $dangerousExtensions = [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
            'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'scr', 'msi', 'vbs',
            'js', 'mjs', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'rb',
            'htaccess', 'html', 'htm', 'xhtml', 'svg', 'swf',
        ];

        if (in_array($ext, $dangerousExtensions, true)) {
            $ext = 'dat';
        }

        return $base . '.' . $ext;
    }
}
