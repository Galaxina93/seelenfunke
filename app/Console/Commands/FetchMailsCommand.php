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
            return self::SUCCESS;
        }

        $failedAccounts = 0;
        foreach ($accounts as $account) {
            if (trim((string) $account->password) === '') {
                $this->line("Postfach übersprungen (kein Passwort hinterlegt): {$account->email}");
                continue;
            }

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

                        $fromObj = $message->getFrom()[0] ?? null;
                        $fromEmail = $fromObj ? $fromObj->mail : 'unknown';
                        // Decode MIME strings for names without corrupting already-decoded UTF-8 / Emojis
                        $rawFromName = $fromObj ? (string) $fromObj->personal : '';
                        $fromName = self::decodeMimeHeaderSafe($rawFromName);

                        // Decode MIME encoded subjects without corrupting 4-byte UTF-8 emojis (mb_decode_mimeheader turns raw UTF-8 emojis into "????")
                        $rawSubject = (string) $message->getSubject();
                        $decodedSubject = self::repairCorruptedSubjectIcons(self::decodeMimeHeaderSafe($rawSubject));

                        $receivedAt = $message->getDate() ? Carbon::parse($message->getDate()) : null;

                        if ($normalizedMessageId === '') {
                            $fromAddress = $fromEmail;
                            $subject = $decodedSubject;
                            $normalizedMessageId = 'fallback_' . sha1($account->id . '|' . $fromAddress . '|' . $subject . '|' . ($receivedAt ? $receivedAt->toIso8601String() : ''));
                        }

                        // Skip if we already stored it for this account or if it was already deleted as Spam / expired Trash
                        if ($normalizedMessageId !== '') {
                            if (\App\Services\Management\MailAiSortService::isMessageIdDeleted($normalizedMessageId)) {
                                continue;
                            }
                            $existingMsg = MailMessage::where('mail_account_id', $account->id)
                                ->where('message_id', $normalizedMessageId)
                                ->first();
                            if ($existingMsg) {
                                if ($decodedSubject !== '' && $existingMsg->subject !== $decodedSubject && str_contains((string) $existingMsg->subject, '???')) {
                                    $existingMsg->update(['subject' => $decodedSubject]);
                                }
                                continue;
                            }
                        }

                        $bodyHtml = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                        $bodyText = $message->hasTextBody() ? $message->getTextBody() : null;

                        // Sanitize HTML body to strip out tracking pixels and XSS payloads
                        if ($bodyHtml) {
                            $bodyHtml = self::sanitizeEmailHtml($bodyHtml);
                        }
                        $bodyText = self::extractCleanPlainText($bodyText, $bodyHtml);

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
                        $cleanFrom = strtolower(trim((string) $fromEmail));
                        $routingRule = \App\Models\Management\Mail\MailRule::where('mail_account_id', $account->id)
                            ->where('condition_field', 'from_email')
                            ->whereRaw('LOWER(condition_value) = ?', [$cleanFrom])
                            ->first();

                        if (!$routingRule && $cleanFrom !== '') {
                            // Global spam blacklist fallback across accounts
                            $routingRule = \App\Models\Management\Mail\MailRule::where('condition_field', 'from_email')
                                ->whereRaw('LOWER(condition_value) = ?', [$cleanFrom])
                                ->where('action', 'mark_spam')
                                ->first();
                        }

                        if ($routingRule) {
                            if ($routingRule->action === 'mark_spam' || $routingRule->action === 'Junk') {
                                $targetFolder = 'Junk';
                            } elseif ($routingRule->action === 'delete' || $routingRule->action === 'Trash') {
                                $targetFolder = 'Trash';
                            } elseif ($routingRule->action === 'archive' || $routingRule->action === 'Archive') {
                                $targetFolder = 'Archive';
                            } else {
                                $cleanFolder = mb_substr(preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', str_replace('..', '', trim((string) $routingRule->action))), 0, 64);
                                $targetFolder = $cleanFolder !== '' ? $cleanFolder : 'INBOX';
                                if (!in_array($targetFolder, ['INBOX', 'Sent', 'Drafts', 'Junk', 'Trash', 'Archive'], true)) {
                                    \App\Models\Management\Mail\MailFolder::firstOrCreate([
                                        'mail_account_id' => $account->id,
                                        'name' => $targetFolder,
                                    ]);
                                }
                            }
                        }

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
                            'received_at' => $receivedAt ?? now()
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

                            $rawFilename = (string) $attachment->getName();
                            $decodedFilename = self::decodeMimeHeaderSafe($rawFilename);
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

            } catch (\Throwable $e) {
                $failedAccounts++;
                Log::error("IMAP Fetch failed for {$account->email}: " . $e->getMessage(), ['exception' => $e]);
                $this->error("FEHLER beim Abruf von {$account->email}: " . $e->getMessage());

                $account->update(['status' => 'error']);
            }
        }

        try {
            app(\App\Services\Management\MailAiSortService::class)->purgeExpiredTrashMessages();
        } catch (\Throwable $e) {
            // ignore
        }

        return $failedAccounts > 0 ? self::FAILURE : self::SUCCESS;
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

    public static function extractCleanPlainText(?string $plain, ?string $html): ?string
    {
        $fromHtml = '';
        if ($html !== null && trim($html) !== '') {
            $s = $html;
            $s = preg_replace('/<(head|style|script|xml|title|noscript|svg)\b[^>]*>.*?<\/\1>/is', '', $s);
            $s = preg_replace('/<!--.*?-->/s', '', $s);
            $s = preg_replace('/<([a-z0-9]+)\b[^>]*style\s*=\s*["\'][^"\']*(?:display\s*:\s*none|visibility\s*:\s*hidden|font-size\s*:\s*0(?:px|pt|em|rem|%)?|line-height\s*:\s*0(?:px|pt|em|rem|%)?|max-height\s*:\s*0(?:px)?|mso-hide\s*:\s*all)[^"\']*["\'][^>]*>.*?<\/\1>/is', '', $s);
            $s = preg_replace('/<br\s*\/?>/i', "\n", $s);
            $s = preg_replace('/<hr\s*\/?>/i', "\n────────────────────────\n", $s);
            $s = preg_replace('/<li(?:\s[^>]*)?>/i', "\n  • ", $s);
            $s = preg_replace('/<\/li>/i', "\n", $s);
            $s = preg_replace('/<\/?(p|div|tr|h[1-6]|table|blockquote|section|article|header|footer)(?:\s[^>]*)?>/i', "\n\n", $s);
            $s = preg_replace('/<\/(td|th)>/i', '  ', $s);
            $s = strip_tags($s);
            $s = html_entity_decode(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $s = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{034F}\x{2060}]+/u', '', $s);
            $s = str_replace("\xC2\xA0", ' ', $s);
            $lines = array_map(fn($l) => trim(preg_replace('/[ \t]+/', ' ', $l)), explode("\n", str_replace(["\r\n", "\r"], "\n", $s)));
            $fromHtml = trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $lines)));
        }

        $fromPlain = trim((string) $plain);
        if ($fromPlain !== '') {
            if (preg_match('/(?:<!DOCTYPE|<html\b|<head\b|<style\b|@import\s+url|font-family\s*:|text-decoration\s*:|#outlook)/i', $fromPlain)) {
                if ($fromHtml !== '') {
                    return $fromHtml;
                }
            }
            $fromPlain = preg_replace('/@import\s+url\([^)]*\)\s*;?/i', '', $fromPlain);
            $fromPlain = preg_replace('/^\s*(?:[.#@a-zA-Z0-9_:,\-\s\[\]\*>+]+\{[^{}]*\}\s*)+/m', '', $fromPlain);
            $fromPlain = html_entity_decode(html_entity_decode($fromPlain, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $fromPlain = trim($fromPlain);
        }

        if ($fromHtml !== '') {
            return $fromHtml;
        }

        return $fromPlain !== '' ? $fromPlain : null;
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

    /**
     * Safely decodes RFC 2047 MIME encoded-words (=?charset?B/Q?...?=) without passing
     * already-decoded UTF-8 strings or 4-byte emojis through mb_decode_mimeheader(),
     * which otherwise corrupts raw UTF-8 emojis into "????".
     */
    public static function decodeMimeHeaderSafe(?string $raw): string
    {
        $str = trim((string) $raw);
        if ($str === '' || !str_contains($str, '=?')) {
            return $str;
        }

        // Collapse whitespace between adjacent RFC 2047 encoded-words
        $collapsed = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $str) ?? $str;

        $decoded = preg_replace_callback(
            '/=\?([^?]+)\?([BQbq])\?([^?]*)\?=/',
            function (array $m): string {
                $charset = strtoupper(trim($m[1]));
                $enc = strtoupper($m[2]);
                $payload = $m[3];

                if ($enc === 'B') {
                    $bytes = base64_decode($payload, true);
                    if ($bytes === false) {
                        $bytes = base64_decode($payload);
                    }
                } else {
                    $bytes = quoted_printable_decode(str_replace('_', ' ', $payload));
                }

                if ($bytes === false || $bytes === '') {
                    return '';
                }

                if ($charset !== 'UTF-8' && $charset !== 'UTF8' && $charset !== 'US-ASCII' && $charset !== 'ASCII') {
                    try {
                        $converted = @iconv($charset, 'UTF-8//IGNORE', $bytes);
                        if ($converted !== false && $converted !== '') {
                            return $converted;
                        }
                        return mb_convert_encoding($bytes, 'UTF-8', $charset);
                    } catch (\Throwable $e) {
                        return $bytes;
                    }
                }

                return $bytes;
            },
            $collapsed
        );

        return trim((string) ($decoded ?? $str));
    }

    /**
     * Repairs subjects where 3-byte or 4-byte UTF-8 emojis were previously turned into "???" or "????" by mb_decode_mimeheader().
     */
    public static function repairCorruptedSubjectIcons(?string $subject): string
    {
        $s = self::decodeMimeHeaderSafe($subject);
        if ($s === '' || !str_contains($s, '??')) {
            return $s;
        }

        $s = preg_replace('/^(\[EXPRESS\]\s+)?\?{3,4}\s*(\[NEUE BESTELLUNG\])/u', '$1💰 $2', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(\[ANGEBOT ANGENOMMEN\])/u', '✅ $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Deine Bestellung\b)/u', '📦 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Deine Rechnung\b)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Juhu!\s*Ein Freund hat)/u', '🎁 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Dein Mein-Seelenfunke Gutschein\b)/u', '🎁 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Dein Geschenkgutschein von\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Dein exklusives Angebot\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Ein besonderer Seelenfunke\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Gute Nachrichten!\s*Deine Bestellung\b)/u', '🚚 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Vergiss deine Unikate nicht\b)/u', '🛒 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,8}\s*(Wie gefällt dir dein Unikat\b)/u', '📸✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*(Ihr persönliches Angebot von\b)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/^\?{3,4}\s*((\[EXPRESS\]\s+)?Neue Anfrage:)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/(Du hast da etwas vergessen!)\s*\?{3,4}$/u', '$1 🛒', $s) ?? $s;
        $s = preg_replace('/(wurde versendet!)\s*\?{3,4}$/u', '$1 🚚', $s) ?? $s;
        $s = preg_replace('/(Wichtige Rückfrage zu deinem Foto[^\?]+)\s*\?{6,8}$/u', '$1 📸✨', $s) ?? $s;
        // Strip any remaining leading/trailing ??? or ???? emoji corruption artifacts
        $s = preg_replace('/^\?{3,8}\s+/u', '', $s) ?? $s;
        $s = preg_replace('/\s+\?{3,8}$/u', '', $s) ?? $s;

        return trim($s);
    }
}
