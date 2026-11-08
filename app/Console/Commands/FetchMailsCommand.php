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
                        $fromName = self::repairCorruptedGermanText(self::decodeMimeHeaderSafe($rawFromName));

                        // Decode MIME encoded subjects without corrupting 4-byte UTF-8 emojis (mb_decode_mimeheader turns raw UTF-8 emojis into "????")
                        $rawSubject = (string) $message->getSubject();
                        $decodedSubject = self::repairCorruptedSubjectIcons(self::decodeMimeHeaderSafe($rawSubject));

                        $receivedAt = $message->getDate() ? Carbon::parse($message->getDate()) : null;

                        if ($normalizedMessageId === '') {
                            $fromAddress = $fromEmail;
                            $subject = $decodedSubject;
                            $normalizedMessageId = 'fallback_' . sha1($account->id . '|' . $fromAddress . '|' . $subject . '|' . ($receivedAt ? $receivedAt->toIso8601String() : ''));
                        }

                        // Skip if we already stored it for this account
                        if ($normalizedMessageId !== '') {
                            $existingMsg = MailMessage::where('mail_account_id', $account->id)
                                ->where('message_id', $normalizedMessageId)
                                ->first();
                            if ($existingMsg) {
                                if ($decodedSubject !== '' && $existingMsg->subject !== $decodedSubject && str_contains((string) $existingMsg->subject, '??')) {
                                    $existingMsg->update(['subject' => $decodedSubject]);
                                }
                                continue;
                            }
                        }

                        $bodyHtml = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
                        $bodyText = $message->hasTextBody() ? $message->getTextBody() : null;

                        // Sanitize HTML body to strip out tracking pixels and XSS payloads
                        if ($bodyHtml) {
                            $bodyHtml = self::repairCorruptedGermanText(self::sanitizeEmailHtml($bodyHtml));
                        }
                        $bodyText = self::repairCorruptedGermanText(self::extractCleanPlainText($bodyText, $bodyHtml));

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

                        // Execute Auto-Routing Rules here (Spam/Folders - never delete, max Archive/Junk)
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
                            } elseif ($routingRule->action === 'delete' || $routingRule->action === 'Trash' || $routingRule->action === 'archive' || $routingRule->action === 'Archive') {
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
                            'is_archived' => $targetFolder === 'Archive',
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

            // 1. Collapse all raw HTML source-code newlines & tabs into spaces first (HTML whitespace rule!)
            $s = preg_replace('/[\r\n\t]+/', ' ', $s);

            // 2. Format multi-column data table rows (<tr>...</tr> without nested <table) on single horizontal lines
            $s = preg_replace_callback('/<tr\b[^>]*>((?:(?!<table\b).)*?)<\/tr>/is', function (array $m): string {
                $rowInner = $m[1];
                if (!preg_match_all('/<(?:td|th)\b[^>]*>(.*?)<\/(?:td|th)>/is', $rowInner, $cellMatches) || count($cellMatches[1]) < 2) {
                    return $m[0];
                }
                $cells = [];
                foreach ($cellMatches[1] as $rawCell) {
                    $c = preg_replace('/<br\s*\/?>/i', ' · ', $rawCell);
                    $c = preg_replace('/<\/(?:p|div|li|h[1-6])>/i', ' · ', $c);
                    $c = strip_tags($c);
                    $c = html_entity_decode(html_entity_decode($c, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $c = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00AD}\x{034F}\x{2060}]+/u', '', $c);
                    $c = str_replace("\xC2\xA0", ' ', $c);
                    $c = preg_replace('/(?:\s*·\s*)+/u', ' · ', preg_replace('/\s+/', ' ', $c)) ?? $c;
                    $c = preg_replace('/^(?:[\s·]+)|(?:[\s·]+)$/u', '', $c) ?? trim($c);
                    if ($c !== '') {
                        $cells[] = $c;
                    }
                }
                if (empty($cells)) {
                    return '';
                }
                $line = $cells[0];
                for ($i = 1; $i < count($cells); $i++) {
                    $sep = str_ends_with($line, ':') ? ' ' : '  ·  ';
                    $line .= $sep . $cells[$i];
                }
                return "\n" . $line . "\n";
            }, $s) ?? $s;

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
            $fromHtml = self::cleanPlainTextArtifacts($s);
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
            $fromPlain = self::cleanPlainTextArtifacts($fromPlain);
        }

        if ($fromHtml !== '') {
            return $fromHtml;
        }

        return $fromPlain !== '' ? $fromPlain : null;
    }

    public static function cleanPlainTextArtifacts(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '';
        }
        $s = str_replace(["\r\n", "\r"], "\n", $text);
        // Strip inline parenthesized tracking/login URLs right after text: e.g. "im mStudio einloggen ( https://... )."
        $s = preg_replace('/[ \t]*\(\s*https?:\/\/[^\s)]+\s*\)/i', '', $s) ?? $s;
        // Strip markdown bold *Heading* markers
        $s = preg_replace('/(?<!\w)\*([^*\n]{2,120})\*(?!\w)/u', '$1', $s) ?? $s;

        $rawLines = explode("\n", $s);
        $cleanLines = [];
        foreach ($rawLines as $rawLine) {
            $line = trim(preg_replace('/[ \t]+/', ' ', $rawLine));
            // Skip pure tracking/social link lines like "( https://... )" or "[https://...]"
            if (preg_match('/^[\(\[<]?\s*https?:\/\/\S+\s*[\)\]>]?$/i', $line)) {
                continue;
            }
            // Skip newsletter header boilerplate lines ("Im Browser anzeigen", "mittwald-Logo", etc.)
            if (preg_match('/^(?:Im Browser anzeigen|Webversion|Online-Version|Newsletter im Browser|[\w\-]+-Logo)$/iu', $line)) {
                continue;
            }
            // Skip ASCII dashed separator lines "---..."
            if (preg_match('/^[-=_]{5,}$/', $line)) {
                continue;
            }
            // Clean empty markdown bullet placeholders "* -"
            if ($line === '* -' || $line === '• -' || $line === '*') {
                continue;
            }
            if (str_starts_with($line, '* ')) {
                $line = '• ' . substr($line, 2);
            }
            // Clean leading/trailing orphaned middots safely in UTF-8
            $line = preg_replace('/^(?:[\s·]+)|(?:[\s·]+)$/u', '', $line) ?? trim($line);
            $cleanLines[] = $line;
        }

        $joined = implode("\n", $cleanLines);
        $joined = preg_replace('/\n{3,}/', "\n\n", $joined) ?? $joined;
        return trim(self::repairCorruptedGermanText($joined));
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
     * Repairs subjects where 2/3/4-byte UTF-8 umlauts, dashes, or emojis were previously turned into "??", "???" or "????" by mb_decode_mimeheader().
     */
    public static function repairCorruptedSubjectIcons(?string $subject): string
    {
        $s = self::decodeMimeHeaderSafe($subject);
        if ($s === '') {
            return '';
        }
        $s = self::repairCorruptedGermanText($s);
        if (!str_contains($s, '?')) {
            return $s;
        }

        $s = preg_replace('/^(\[EXPRESS\]\s+)?\?{1,4}\s*(\[NEUE BESTELLUNG\])/u', '$1💰 $2', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(\[ANGEBOT ANGENOMMEN\])/u', '✅ $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Deine Bestellung\b)/u', '📦 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Deine Rechnung\b)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Juhu!\s*Ein Freund hat)/u', '🎁 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Dein Mein-Seelenfunke Gutschein\b)/u', '🎁 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Dein Geschenkgutschein von\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Dein exklusives Angebot\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Ein besonderer Seelenfunke\b)/u', '✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Gute Nachrichten!\s*Deine Bestellung\b)/u', '🚚 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Vergiss deine Unikate nicht\b)/u', '🛒 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,8}\s*(Wie gefällt dir dein Unikat\b)/u', '📸✨ $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*(Ihr persönliches Angebot von\b)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/^\?{1,4}\s*((\[EXPRESS\]\s+)?Neue Anfrage:)/u', '🧾 $1', $s) ?? $s;
        $s = preg_replace('/(Du hast da etwas vergessen!)\s*\?{2,4}$/u', '$1 🛒', $s) ?? $s;
        $s = preg_replace('/(wurde versendet!)\s*\?{2,4}$/u', '$1 🚚', $s) ?? $s;
        $s = preg_replace('/(Wichtige Rückfrage zu deinem Foto[^\?]+)\s*\?{4,8}$/u', '$1 📸✨', $s) ?? $s;
        // Strip any remaining leading/trailing ??? or ???? emoji corruption artifacts
        $s = preg_replace('/^\?{2,8}\s+/u', '', $s) ?? $s;
        $s = preg_replace('/\s+\?{2,8}$/u', '', $s) ?? $s;

        return trim($s);
    }

    /**
     * Repairs German words where 2-byte UTF-8 umlauts (ä, ö, ü, ß) or 3-byte dashes (–) were turned into "??" / "???".
     */
    public static function repairCorruptedGermanText(?string $text): string
    {
        if ($text === null || $text === '' || !str_contains($text, '??')) {
            return (string) $text;
        }

        $s = $text;

        // 1. 3-byte en-dash / em-dash between spaces (" ??? " -> " – ")
        $s = preg_replace('/(?<=\s)\?{3}(?=\s)/u', '–', $s) ?? $s;

        // 2. 4-question-mark double umlaut + eszett combinations (üß / öß / äß)
        $fourMap = [
            'Gr????en' => 'Größen', 'gr????en' => 'größen',
            'Gr????e'  => 'Grüße',  'gr????e'  => 'grüße',
            'Begr????' => 'Begrüß', 'begr????' => 'begrüß',
            'gr????er' => 'größer', 'Gr????er' => 'Größer',
            'gr????te' => 'größte', 'Gr????te' => 'Größte',
            'gem????'  => 'gemäß',  'Gem????'  => 'Gemäß',
            'm????ig'  => 'mäßig',  'M????ig'  => 'Mäßig',
        ];
        $s = strtr($s, $fourMap);

        // 3. Exact German stem & word replacements for "??" -> ä / ö / ü / ß / Ä / Ö / Ü
        $twoMap = [
            // ö / Ö
            'n??tig' => 'nötig', 'N??tig' => 'Nötig',
            'm??glich' => 'möglich', 'M??glich' => 'Möglich',
            'm??cht' => 'möcht', 'M??cht' => 'Möcht',
            'k??nn' => 'könn', 'K??nn' => 'Könn',
            'l??sch' => 'lösch', 'L??sch' => 'Lösch',
            'L??sung' => 'Lösung', 'l??sung' => 'lösung',
            'aufgel??st' => 'aufgelöst', 'eingel??st' => 'eingelöst', 'gel??st' => 'gelöst',
            'pers??nlich' => 'persönlich', 'Pers??nlich' => 'Persönlich',
            'sch??n' => 'schön', 'Sch??n' => 'Schön',
            'h??ch' => 'höch', 'H??ch' => 'Höch',
            'erh??h' => 'erhöh', 'Erh??h' => 'Erhöh', 'H??he' => 'Höhe',
            'geh??r' => 'gehör', 'Geh??r' => 'Gehör', 'Zubeh??r' => 'Zubehör', 'Beh??rde' => 'Behörde', 'h??ren' => 'hören', 'h??rt' => 'hört',
            'Passw??rter' => 'Passwörter', 'W??rter' => 'Wörter', 'w??rtlich' => 'wörtlich',
            'ge??ffnet' => 'geöffnet', 'er??ffn' => 'eröffn', 'Er??ffn' => 'Eröffn', 'ver??ffent' => 'veröffent', 'Ver??ffent' => 'Veröffent',
            '??ffnen' => 'öffnen', '??ffnet' => 'öffnet', '??ffnung' => 'Öffnung', '??ffentlich' => 'öffentlich',
            '??sterreich' => 'Österreich', '??kolog' => 'ökolog', '??konom' => 'ökonom',
            'st??r' => 'stör', 'St??r' => 'Stör',
            'f??rder' => 'förder', 'F??rder' => 'Förder',
            'v??llig' => 'völlig', 'V??llig' => 'Völlig',
            'gew??hn' => 'gewöhn', 'Gew??hn' => 'Gewöhn',
            'zw??lf' => 'zwölf', 'Zw??lf' => 'Zwölf',
            'pl??tzlich' => 'plötzlich', 'Pl??tzlich' => 'Plötzlich',
            'verz??ger' => 'verzöger', 'Verz??ger' => 'Verzöger',

            // ü / Ü
            'f??r' => 'für', 'F??r' => 'Für',
            'pr??f' => 'prüf', 'Pr??f' => 'Prüf',
            'zur??ck' => 'zurück', 'Zur??ck' => 'Zurück',
            'R??ck' => 'Rück', 'r??ck' => 'rück',
            'Gl??ck' => 'Glück', 'gl??ck' => 'glück',
            'St??ck' => 'Stück', 'st??ck' => 'stück',
            'w??nsch' => 'wünsch', 'W??nsch' => 'Wünsch',
            'nat??rlich' => 'natürlich', 'Nat??rlich' => 'Natürlich',
            'k??rz' => 'kürz', 'K??rz' => 'Kürz',
            'k??nftig' => 'künftig', 'K??nftig' => 'Künftig',
            'g??ltig' => 'gültig', 'G??ltig' => 'Gültig',
            'g??nstig' => 'günstig', 'G??nstig' => 'Günstig',
            'f??nf' => 'fünf', 'F??nf' => 'Fünf',
            'f??hr' => 'führ', 'F??hr' => 'Führ',
            'Geb??hr' => 'Gebühr', 'geb??hr' => 'gebühr',
            'f??ll' => 'füll', 'F??ll' => 'Füll',
            'f??hl' => 'fühl', 'F??hl' => 'Fühl', 'Gef??hl' => 'Gefühl',
            'verf??g' => 'verfüg', 'Verf??g' => 'Verfüg', 'hinzuf??g' => 'hinzufüg', 'beigef??g' => 'beigefüg', 'f??gen' => 'fügen', 'f??gt' => 'fügt',
            'm??ss' => 'müss', 'M??ss' => 'Müss',
            'd??rf' => 'dürf', 'D??rf' => 'Dürf', 'Bed??rf' => 'Bedürf',
            'w??rd' => 'würd', 'W??rd' => 'Würd',
            'K??nst' => 'Künst', 'k??nst' => 'künst',
            'K??ndig' => 'Kündig', 'k??ndig' => 'kündig',
            'Schl??ssel' => 'Schlüssel', 'schl??ssel' => 'schlüssel',
            'gesch??tzt' => 'geschützt', 'sch??tzen' => 'schützen', 'Sch??tz' => 'Schütz', 'unterst??tz' => 'unterstütz', 'Unterst??tz' => 'Unterstütz',
            'Gr??nd' => 'Gründ', 'gr??nd' => 'gründ',
            'p??nktlich' => 'pünktlich', 'P??nktlich' => 'Pünktlich',
            'urspr??nglich' => 'ursprünglich', 'Urspr??nglich' => 'Ursprünglich',
            'ausdr??cklich' => 'ausdrücklich', 'Ausdr??cklich' => 'Ausdrücklich',
            'bez??glich' => 'bezüglich', 'Bez??glich' => 'Bezüglich',
            'z??gig' => 'zügig', 'Z??gig' => 'Zügig',
            'fr??h' => 'früh', 'Fr??h' => 'Früh',
            'bem??h' => 'bemüh', 'Bem??h' => 'Bemüh', 'M??he' => 'Mühe',
            'T??r' => 'Tür', 'B??ro' => 'Büro', 'M??ll' => 'Müll', 'H??lle' => 'Hülle', 'F??lle' => 'Fülle',
            'dar??ber' => 'darüber', 'wor??ber' => 'worüber', 'gegen??ber' => 'gegenüber', 'vor??ber' => 'vorüber',
            '??berpr??f' => 'überprüf', '??bersicht' => 'Übersicht', '??berweis' => 'Überweis', '??bermitt' => 'übermitt', '??bernahm' => 'Übernahm', '??berneh' => 'überneh',
            ' ??ber ' => ' über ', ' ??ber' => ' über', '??ber ' => 'Über ',

            // ä / Ä
            'Best??tig' => 'Bestätig', 'best??tig' => 'bestätig',
            'Empf??ng' => 'Empfäng', 'empf??ng' => 'empfäng',
            'Anh??ng' => 'Anhäng', 'anh??ng' => 'anhäng', 'unabh??ng' => 'unabhäng', 'abh??ng' => 'abhäng',
            'Entit??t' => 'Entität', 'Qualit??t' => 'Qualität', 'Kapazit??t' => 'Kapazität', 'Priorit??t' => 'Priorität', 'Aktivit??t' => 'Aktivität', 'Identit??t' => 'Identität', 'Liquidit??t' => 'Liquidität', 'Rarit??t' => 'Rarität', 'Spezialit??t' => 'Spezialität',
            'zus??tzlich' => 'zusätzlich', 'Zus??tzlich' => 'Zusätzlich',
            's??mtlich' => 'sämtlich', 'S??mtlich' => 'Sämtlich',
            'tats??chlich' => 'tatsächlich', 'Tats??chlich' => 'Tatsächlich', 'haupts??chlich' => 'hauptsächlich',
            'vollst??ndig' => 'vollständig', 'Vollst??ndig' => 'Vollständig', 'selbstst??ndig' => 'selbstständig', 'zust??ndig' => 'zuständig', 'Zust??ndig' => 'Zuständig', 'st??ndig' => 'ständig', 'Verst??nd' => 'Verständ', 'verst??nd' => 'verständ', 'Umst??nd' => 'Umständ',
            'ge??ndert' => 'geändert', 'ver??nder' => 'veränder', 'Ver??nder' => 'Veränder', '??nderung' => 'Änderung', '??ndern' => 'ändern', '??ndert' => 'ändert',
            'n??chst' => 'nächst', 'N??chst' => 'Nächst',
            'sp??t' => 'spät', 'Sp??t' => 'Spät',
            'w??hrend' => 'während', 'W??hrend' => 'Während', 'w??hl' => 'wähl', 'W??hl' => 'Wähl', 'erw??hn' => 'erwähn', 'Erw??hn' => 'Erwähn', 'gew??hr' => 'gewähr', 'Gew??hr' => 'Gewähr', 'W??hrung' => 'Währung', 'bew??hr' => 'bewähr',
            'K??ufer' => 'Käufer', 'k??ufer' => 'käufer', 'Eink??uf' => 'Einkäuf', 'L??uf' => 'Läuf', 'l??uf' => 'läuf', 'h??ufig' => 'häufig', 'H??ufig' => 'Häufig',
            't??glich' => 'täglich', 'T??glich' => 'Täglich', 'nachtr??glich' => 'nachträglich', 'ertr??glich' => 'erträglich', 'Vertr??g' => 'Verträg', 'vertr??g' => 'verträg', 'Betr??g' => 'Beträg', 'betr??gt' => 'beträgt', 'Antr??g' => 'Anträg', 'Auftr??g' => 'Aufträg', 'Beitr??g' => 'Beiträg', 'Eintr??g' => 'Einträg',
            'f??hig' => 'fähig', 'F??hig' => 'Fähig', 'auff??llig' => 'auffällig', 'zuf??llig' => 'zufällig', 'sorgf??ltig' => 'sorgfältig', 'vielf??ltig' => 'vielfältig', 'f??lsch' => 'fälsch',
            'z??hl' => 'zähl', 'Z??hl' => 'Zähl',
            'kl??r' => 'klär', 'Kl??r' => 'Klär',
            'l??ng' => 'läng', 'L??ng' => 'Läng', 'zug??ng' => 'zugäng', 'Zug??ng' => 'Zugäng', 'vorg??ng' => 'vorgäng', 'Vorg??ng' => 'Vorgäng',
            'M??ngel' => 'Mängel', 'm??ngel' => 'mängel',
            'einsch??tz' => 'einschätz', 'Einsch??tz' => 'Einschätz', 'sch??tzungs' => 'schätzungs',
            'gef??llt' => 'gefällt', 'enth??lt' => 'enthält', 'erh??lt' => 'erhält', 'h??lt' => 'hält', 'f??llt' => 'fällt',
            'w??re' => 'wäre', 'W??re' => 'Wäre', 'W??rme' => 'Wärme', 'w??rm' => 'wärm',
            'st??rk' => 'stärk', 'St??rk' => 'Stärk', 'M??rz' => 'März', 'Gep??ck' => 'Gepäck', 'Geb??ude' => 'Gebäude', 'geb??ude' => 'gebäude', 'Ger??t' => 'Gerät', 'ger??t' => 'gerät', 'Gespr??ch' => 'Gespräch', 'Gesch??ft' => 'Geschäft', 'gesch??ft' => 'geschäft', 'Pr??sent' => 'Präsent', 'pr??sent' => 'präsent', 'regul??r' => 'regulär', 'tempor??r' => 'temporär', 'popul??r' => 'populär', 'Atmosph??re' => 'Atmosphäre', 'Tr??ger' => 'Träger', 'L??nder' => 'Länder', 'H??ndler' => 'Händler', 'B??nder' => 'Bänder', 'R??nder' => 'Ränder', 'Sch??den' => 'Schäden', 'Pl??tze' => 'Plätze', 'S??tze' => 'Sätze', 's??tze' => 'sätze',

            // ß
            'Gr??e' => 'Grüße', 'gr??e' => 'grüße',
            'Stra??e' => 'Straße', 'stra??e' => 'straße',
            'Gro??' => 'Groß', 'gro??' => 'groß',
            'Au??er' => 'Außer', 'au??er' => 'außer',
            'Wei??' => 'Weiß', 'wei??' => 'weiß',
            'Hei??' => 'Heiß', 'hei??' => 'heiß',
            'Schlie??' => 'Schließ', 'schlie??' => 'schließ', 'anschl??' => 'anschließ',
            'Fu??' => 'Fuß', 'Ma??' => 'Maß', 'ma??' => 'maß', 'Spa??' => 'Spaß',
        ];

        return strtr($s, $twoMap);
    }
}
