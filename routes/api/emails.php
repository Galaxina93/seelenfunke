<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailFolder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

Route::prefix('funki/emails')->group(function () {

    // 1. Accounts & Folders
    Route::get('/accounts', function () {
        try {
            app(\App\Services\Management\MailAiSortService::class)->purgeExpiredTrashMessages();
        } catch (\Throwable $e) {
            // ignore
        }

        $orderFile = storage_path('app/leitung/mail/funkenwerk_mail_order.json');
        if (!is_dir(dirname($orderFile))) {
            @mkdir(dirname($orderFile), 0775, true);
        }
        $orderData = file_exists($orderFile) ? (json_decode(file_get_contents($orderFile), true) ?: []) : [];
        $accountOrder = $orderData['accounts'] ?? [];
        $folderOrders = $orderData['folders'] ?? [];

        $accounts = MailAccount::all()->sortBy(function ($acc) use ($accountOrder) {
            $idx = array_search($acc->id, $accountOrder);
            return $idx !== false ? $idx : (1000 + $acc->id);
        })->values();

        $result = [];

        $baseFolders = [
            'INBOX' => 'Posteingang',
            'Sent' => 'Gesendet',
            'Drafts' => 'Entwürfe',
            'Archive' => 'Archiv',
            'Junk' => 'Spam',
            'Trash' => 'Papierkorb',
        ];

        foreach ($accounts as $acc) {
            $accFolders = $baseFolders;
            $customFolders = MailFolder::where('mail_account_id', $acc->id)
                ->whereNotIn('name', \App\Services\Management\MailAiSortService::LEGACY_AI_FOLDERS)
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            foreach ($customFolders as $cf) {
                $accFolders[$cf] = $cf;
            }

            if (!empty($folderOrders[$acc->id]) && is_array($folderOrders[$acc->id])) {
                $ordered = [];
                foreach ($folderOrders[$acc->id] as $fKey) {
                    if (isset($accFolders[$fKey])) {
                        $ordered[$fKey] = $accFolders[$fKey];
                    }
                }
                foreach ($accFolders as $k => $v) {
                    if (!isset($ordered[$k])) {
                        $ordered[$k] = $v;
                    }
                }
                $accFolders = $ordered;
            }

            $folderCounts = [];
            $folderTotals = [];
            $totalUnread = 0;
            $query = MailMessage::where('mail_account_id', $acc->id);

            foreach ($accFolders as $key => $label) {
                $qc = clone $query;
                $qt = clone $query;
                if ($key === 'Archive') {
                    $count = 0;
                    $tot = $qt->where(fn($q) => $q->where('folder', 'Archive')->orWhere('is_archived', true))->count();
                } elseif ($key === 'Sent' || $key === 'Trash') {
                    $count = 0;
                    $tot = $qt->where('folder', $key)->where('is_archived', false)->count();
                } else {
                    $count = $qc->where('folder', $key)->where('is_archived', false)->where('is_read', false)->count();
                    $tot = $qt->where('folder', $key)->where('is_archived', false)->count();
                }
                $folderCounts[$key] = $count;
                $folderTotals[$key] = $tot;
                if ($key === 'INBOX') {
                    $totalUnread += $count;
                }
            }

            $hasPassword = trim((string) $acc->password) !== '';

            $result[] = [
                'id' => $acc->id,
                'name' => $acc->name,
                'email' => $acc->email,
                'imap_username' => $acc->imap_username,
                'smtp_username' => $acc->smtp_username,
                'imap_host' => $acc->imap_host,
                'imap_port' => $acc->imap_port,
                'imap_encryption' => $acc->imap_encryption,
                'signature' => $acc->signature,
                'smtp_host' => $acc->smtp_host,
                'smtp_port' => $acc->smtp_port,
                'smtp_encryption' => $acc->smtp_encryption,
                'is_default' => $acc->is_default,
                'is_commercial' => $acc->is_commercial,
                'has_password' => $hasPassword,
                'status' => $hasPassword ? ($acc->status ?: 'connected') : 'needs_password',
                'folders' => $accFolders,
                'counts' => $folderCounts,
                'total_counts' => $folderTotals,
                'total_unread' => $totalUnread
            ];
        }

        $processedBase = MailMessage::where('folder', 'Processed')->where('is_archived', false);
        $archivedBase = MailMessage::where(fn($q) => $q->where('folder', 'Archive')->orWhere('is_archived', true));
        $processedSummary = [
            'total_count' => (clone $processedBase)->count(),
            'important_count' => (clone $processedBase)->where('priority', 'high')->count(),
            'normal_count' => (clone $processedBase)->where('priority', '!=', 'high')->count(),
            'unread_count' => (clone $processedBase)->where('is_read', false)->count(),
            'archived_count' => (clone $archivedBase)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $result,
            'processed_summary' => $processedSummary,
        ]);
    });

    // 1b. All Ever-Received & Sent Email Addresses Sorted by Frequency (for Recipient Autocomplete)
    Route::get('/recipients', function () {
        $rows = MailMessage::query()
            ->select(['from_email', 'from_name', 'to_email', 'cc_email', 'bcc_email', 'received_at'])
            ->distinct()
            ->orderBy('received_at', 'desc')
            ->limit(1000)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $rawFrom = trim((string) ($r->from_email ?? ''));
            $rawName = trim((string) ($r->from_name ?? ''));
            if ($rawFrom !== '' && str_contains($rawFrom, '@')) {
                if (preg_match('/<([^>]+@[^>]+)>/', $rawFrom, $m)) {
                    if ($rawName === '') {
                        $rawName = trim(str_replace($m[0], '', $rawFrom), " \"'\t\n\r");
                    }
                    $rawFrom = trim($m[1]);
                }
                $key = strtolower($rawFrom);
                if (!isset($map[$key])) {
                    $map[$key] = [
                        'email' => $rawFrom,
                        'name' => $rawName,
                        'count' => 0,
                        'last_seen' => (string) ($r->received_at ?? ''),
                    ];
                }
                $map[$key]['count']++;
                if ($map[$key]['name'] === '' && $rawName !== '') {
                    $map[$key]['name'] = $rawName;
                }
            }

            foreach ([$r->to_email ?? '', $r->cc_email ?? '', $r->bcc_email ?? ''] as $rawList) {
                $rawList = trim((string) $rawList);
                if ($rawList !== '' && str_contains($rawList, '@')) {
                    foreach (preg_split('/[,;]+/', $rawList) as $part) {
                        $part = trim($part);
                        $toName = '';
                        if (preg_match('/<([^>]+@[^>]+)>/', $part, $m)) {
                            $toName = trim(str_replace($m[0], '', $part), " \"'\t\n\r");
                            $part = trim($m[1]);
                        }
                        if ($part === '' || !str_contains($part, '@')) {
                            continue;
                        }
                        $key = strtolower($part);
                        if (!isset($map[$key])) {
                            $map[$key] = [
                                'email' => $part,
                                'name' => $toName,
                                'count' => 0,
                                'last_seen' => (string) ($r->received_at ?? ''),
                            ];
                        }
                        $map[$key]['count']++;
                        if ($map[$key]['name'] === '' && $toName !== '') {
                            $map[$key]['name'] = $toName;
                        }
                    }
                }
            }
        }

        $list = array_values($map);
        usort($list, function ($a, $b) {
            if ($b['count'] !== $a['count']) {
                return $b['count'] <=> $a['count'];
            }
            return strcmp((string) $b['last_seen'], (string) $a['last_seen']);
        });

        return response()->json([
            'success' => true,
            'data' => array_slice($list, 0, 500),
        ]);
    });

    // 2. Mails List (with Funnel "Processed" & Global Trash-inclusive Search)
    Route::get('/messages', function (Request $request) {
        $query = MailMessage::with('attachments');
        $hasSearch = $request->has('search') && trim((string) $request->search) !== '';
        $folder = (string) $request->query('folder', '');

        if ($hasSearch) {
            // Search includes all folders (including Trash / Papierkorb, Archive & Processed / Verarbeitet)
            if (!in_array($folder, ['Processed', 'Archive'], true) && $request->has('account_id') && (int) $request->account_id > 0 && !$request->boolean('global')) {
                $query->where('mail_account_id', (int) $request->account_id);
            }
            $search = '%' . addcslashes(trim((string) $request->search), '%_\\') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                  ->orWhere('from_email', 'like', $search)
                  ->orWhere('from_name', 'like', $search)
                  ->orWhere('to_email', 'like', $search)
                  ->orWhere('category', 'like', $search)
                  ->orWhere('body_plain', 'like', $search);
            });
        } else {
            if ($folder === 'Processed') {
                // Global funnel folder across all accounts (only open actionable items)
                $query->where('folder', 'Processed')->where('is_archived', false);
                if ($request->filled('priority_filter') && $request->priority_filter !== 'all') {
                    $query->where('priority', $request->priority_filter);
                }
                if ($request->filled('category_filter') && $request->category_filter !== 'all') {
                    $query->where('category', $request->category_filter);
                }
            } elseif ($folder === 'Archive') {
                // Global Archive (if account_id <= 0) or per-account Archive (if account_id > 0)
                if ($request->has('account_id') && (int) $request->account_id > 0) {
                    $query->where('mail_account_id', (int) $request->account_id);
                }
                $query->where(fn($q) => $q->where('folder', 'Archive')->orWhere('is_archived', true));
                if ($request->filled('priority_filter') && $request->priority_filter !== 'all') {
                    $query->where('priority', $request->priority_filter);
                }
                if ($request->filled('category_filter') && $request->category_filter !== 'all') {
                    $query->where('category', $request->category_filter);
                }
            } else {
                if ($request->has('account_id') && (int) $request->account_id > 0) {
                    $query->where('mail_account_id', (int) $request->account_id);
                }
                if ($folder !== '') {
                    $query->where('folder', $folder)->where('is_archived', false);
                }
            }
        }

        if ($request->has('unread') && $request->unread == '1') {
            $query->where('is_read', false);
        }

        if ($folder === 'Processed' || $hasSearch) {
            $query->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END ASC")
                  ->orderBy('received_at', 'desc');
        } else {
            $query->orderBy('received_at', 'desc');
        }

        $messages = $query->paginate(150);
        foreach ($messages->items() as $m) {
            $dirty = false;
            if (is_string($m->subject) && (str_contains($m->subject, '?') || str_contains($m->subject, '=?'))) {
                $fixed = \App\Console\Commands\FetchMailsCommand::repairCorruptedSubjectIcons($m->subject);
                if ($fixed !== $m->subject) {
                    $m->subject = $fixed;
                    $dirty = true;
                }
            }
            if (is_string($m->from_name) && (str_contains($m->from_name, '??') || str_contains($m->from_name, '=?'))) {
                $fixedFrom = \App\Console\Commands\FetchMailsCommand::repairCorruptedGermanText(\App\Console\Commands\FetchMailsCommand::decodeMimeHeaderSafe($m->from_name));
                if ($fixedFrom !== $m->from_name) {
                    $m->from_name = $fixedFrom;
                    $dirty = true;
                }
            }
            if (is_string($m->body_plain) && (str_contains($m->body_plain, '??') || str_contains($m->body_plain, '( http') || preg_match('/(?:<!DOCTYPE|<html\b|<head\b|<style\b|@import\s+url|font-family\s*:|text-decoration\s*:|#outlook)/i', $m->body_plain))) {
                $cleanBody = \App\Console\Commands\FetchMailsCommand::extractCleanPlainText($m->body_plain, $m->body_html);
                if ($cleanBody !== null && $cleanBody !== $m->body_plain) {
                    $m->body_plain = $cleanBody;
                    $dirty = true;
                }
            }
            if ($dirty) {
                $m->saveQuietly();
            }
        }

        return response()->json(['success' => true, 'data' => $messages]);
    });

    // 3. Single Mail Details
    Route::get('/messages/{id}', function ($id) {
        $msg = MailMessage::with('attachments')->findOrFail($id);
        $dirty = false;
        if (is_string($msg->subject) && (str_contains($msg->subject, '?') || str_contains($msg->subject, '=?'))) {
            $fixed = \App\Console\Commands\FetchMailsCommand::repairCorruptedSubjectIcons($msg->subject);
            if ($fixed !== $msg->subject) {
                $msg->subject = $fixed;
                $dirty = true;
            }
        }
        if (is_string($msg->from_name) && (str_contains($msg->from_name, '??') || str_contains($msg->from_name, '=?'))) {
            $fixedFrom = \App\Console\Commands\FetchMailsCommand::repairCorruptedGermanText(\App\Console\Commands\FetchMailsCommand::decodeMimeHeaderSafe($msg->from_name));
            if ($fixedFrom !== $msg->from_name) {
                $msg->from_name = $fixedFrom;
                $dirty = true;
            }
        }
        if (is_string($msg->body_plain) && (str_contains($msg->body_plain, '??') || str_contains($msg->body_plain, '( http') || preg_match('/(?:<!DOCTYPE|<html\b|<head\b|<style\b|@import\s+url|font-family\s*:|text-decoration\s*:|#outlook)/i', $msg->body_plain))) {
            $cleanBody = \App\Console\Commands\FetchMailsCommand::extractCleanPlainText($msg->body_plain, $msg->body_html);
            if ($cleanBody !== null && $cleanBody !== $msg->body_plain) {
                $msg->body_plain = $cleanBody;
                $dirty = true;
            }
        }
        if ($dirty) {
            $msg->saveQuietly();
        }
        
        // Auto mark as read when fetching details
        if (!$msg->is_read) {
            $msg->update(['is_read' => true]);
        }

        return response()->json(['success' => true, 'data' => $msg]);
    });

    // 4. Send Mail
    Route::post('/send', function (Request $request) {
        $data = $request->validate([
            'draft_id' => 'nullable|integer',
            'account_id' => 'nullable',
            'from' => 'nullable|string|max:255',
            'to' => 'required|string|max:1000',
            'cc' => 'nullable|string|max:1000',
            'bcc' => 'nullable|string|max:1000',
            'subject' => 'required|string|max:255',
            'body' => 'required|string|max:200000',
            'attachments' => 'nullable|array|max:20',
        ]);

        $parseEmails = function (?string $raw): array {
            if (empty($raw)) return [];
            $parts = preg_split('/[,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $clean = [];
            foreach ($parts as $p) {
                $t = trim($p);
                if (preg_match('/<([^>]+)>/', $t, $m)) {
                    $t = trim($m[1]);
                }
                if (filter_var($t, FILTER_VALIDATE_EMAIL)) {
                    $clean[] = $t;
                }
            }
            return array_values(array_unique($clean));
        };

        $toList = $parseEmails($data['to'] ?? '');
        if (empty($toList)) {
            return response()->json(['error' => 'Bitte mindestens eine gültige Empfänger-E-Mail-Adresse (An) angeben.'], 422);
        }
        $ccList = $parseEmails($data['cc'] ?? '');
        $bccList = $parseEmails($data['bcc'] ?? '');

        $account = null;
        if (!empty($data['account_id'])) {
            $account = MailAccount::find($data['account_id']);
            if (!$account && filter_var($data['account_id'], FILTER_VALIDATE_EMAIL)) {
                $account = MailAccount::where('email', $data['account_id'])->first();
            }
        }
        if (!$account && !empty($data['from'])) {
            $account = MailAccount::where('email', $data['from'])->first();
        }

        if (!$account) {
            $account = MailAccount::where('is_default', true)->first() ?: MailAccount::first();
        }

        if (!$account) {
            return response()->json(['error' => 'Kein E-Mail-Konto konfiguriert'], 400);
        }

        if (!$account->smtp_host) {
            return response()->json([
                'error' => "Für {$account->email} ist noch kein Postausgangsserver (SMTP) hinterlegt.",
                'action_code' => 'MISSING_SMTP_CONFIG',
                'needs_password' => true,
                'account_id' => $account->id,
                'guidance_title' => 'SMTP-Server für dieses Postfach ergänzen',
                'guidance_steps' => [
                    "Klicke neben {$account->email} auf das Zahnrad-Symbol (⚙).",
                    'Trage den Postausgangsserver (z. B. securesmtp.t-online.de, Port 465, SSL) und dein E-Mail-Passwort ein.',
                    'Klicke auf Speichern & Verbindung prüfen und sende die E-Mail danach ab.',
                ],
            ], 422);
        }

        if (!\App\Services\System\SystemSecurityService::isSafeMailHost((string) $account->smtp_host)) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:ssrf_mail_host_blocked',
                'ERROR',
                'Unsicherer SMTP-Server Host beim Versand über API blockiert (SSRF-Schutz).',
                ['smtp_host' => $account->smtp_host],
                'idor_integrity'
            );
            return response()->json([
                'error' => 'Ungültiger oder intern gesperrter SMTP-Host.',
                'action_code' => 'MISSING_SMTP_CONFIG',
                'guidance_title' => 'Bitte gültigen öffentlichen SMTP-Server eintragen',
                'guidance_steps' => [
                    'Öffne die Postfach-Einstellungen (⚙) und trage den offiziellen SMTP-Server deines Anbieters ein.',
                ],
            ], 422);
        }

        $promoteAttachmentPath = function (string $relPath, string $filename, string $bucket = 'drafts', ?int $expectedSize = null): string {
            $cleanRel = ltrim($relPath, '/');
            $abs = \App\Mail\CrmOutgoingMailToCustomer::resolveVerifiedAttachmentDiskPath($cleanRel, $expectedSize, $filename);
            if ($abs === null) {
                return $cleanRel;
            }
            if (\Illuminate\Support\Str::startsWith($cleanRel, 'leitung/crm/mail-attachments/') && \Illuminate\Support\Facades\Storage::disk('local')->exists($cleanRel)) {
                return $cleanRel;
            }
            $safeName = preg_replace('/[^A-Za-z0-9._äöüÄÖÜß\- ]+/u', '_', basename($filename ?: 'anhang.pdf'));
            $permRel = 'leitung/crm/mail-attachments/' . $bucket . '/' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '_' . $safeName;
            try {
                \Illuminate\Support\Facades\Storage::disk('local')->put($permRel, file_get_contents($abs));
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($permRel)) {
                    return $permRel;
                }
            } catch (\Throwable) {
                // fallback to original verified path
            }
            return $cleanRel;
        };

        $attachmentDataParams = [];
        $missingAttachmentNames = [];
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $att) {
                if (!empty($att['id']) && empty($att['path'])) {
                    $existingAtt = \App\Models\Management\Mail\MailAttachment::find((int) $att['id']);
                    if ($existingAtt && !empty($existingAtt->path)) {
                        $att['path'] = $existingAtt->path;
                        $att['filename'] = $att['filename'] ?? $existingAtt->filename;
                        $att['mime'] = $att['mime'] ?? $existingAtt->content_type;
                        $att['size'] = $att['size'] ?? $existingAtt->size;
                    }
                }
                $attPath = ltrim((string) ($att['path'] ?? ''), '/');
                if (
                    empty($attPath)
                    || str_contains($attPath, '..')
                    || !\Illuminate\Support\Str::startsWith($attPath, ['temp/mail-attachments/', 'mail-attachments/', 'crm/', 'leitung/crm/'])
                ) {
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:lfi_mail_attachment_blocked',
                        'ERROR',
                        'Unzulässiger Dateipfad beim E-Mail-Anhang-Versand blockiert.',
                        ['path' => $att['path'] ?? null],
                        'idor_integrity'
                    );
                    return response()->json([
                        'error' => 'Ungültiger Anhang-Pfad.',
                        'action_code' => 'MISSING_ATTACHMENT',
                        'guidance_title' => 'Anhang bitte neu hinzufügen',
                        'guidance_steps' => [
                            'Entferne den Anhang kurz über das ✕-Symbol und ziehe die Datei erneut per Drag & Drop in das Fenster.',
                        ],
                    ], 403);
                }

                $fname = basename((string) ($att['filename'] ?? 'attachment'));
                $fsize = (int) ($att['size'] ?? 0);
                $verifiedAbs = \App\Mail\CrmOutgoingMailToCustomer::resolveVerifiedAttachmentDiskPath($attPath, $fsize > 0 ? $fsize : null, $fname);
                if ($verifiedAbs === null) {
                    $missingAttachmentNames[] = $fname;
                } else {
                    $attPath = $promoteAttachmentPath($attPath, $fname, 'drafts', $fsize > 0 ? $fsize : null);
                    $fsize = filesize($verifiedAbs) ?: $fsize;
                }

                $attachmentDataParams[] = [
                    'path' => $attPath,
                    'filename' => $fname,
                    'mime' => $att['mime'] ?? $att['content_type'] ?? 'application/octet-stream',
                    'size' => $fsize,
                ];
            }
        }

        $saveDraftWithAttachments = function () use ($data, $account, $toList, $ccList, $bccList, $attachmentDataParams) {
            $plainBody = html_entity_decode(
                strip_tags(str_replace(['<br>', '<br/>', '<br />', "\r\n", "\r"], "\n", (string) $data['body'])),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $draft = null;
            if (!empty($data['draft_id'])) {
                $draft = MailMessage::where('id', (int) $data['draft_id'])->where('folder', 'Drafts')->first();
            }
            if ($draft) {
                $draft->update([
                    'mail_account_id' => $account->id,
                    'subject' => $data['subject'] ?: '(Kein Betreff)',
                    'from_name' => $account->name,
                    'from_email' => $account->email,
                    'to_email' => implode(', ', $toList),
                    'cc_email' => !empty($ccList) ? implode(', ', $ccList) : null,
                    'bcc_email' => !empty($bccList) ? implode(', ', $bccList) : null,
                    'body_plain' => $plainBody,
                    'body_html' => nl2br(e($plainBody)),
                    'is_read' => true,
                    'is_archived' => false,
                    'has_attachments' => count($attachmentDataParams) > 0,
                    'received_at' => now(),
                ]);
                \App\Models\Management\Mail\MailAttachment::where('mail_message_id', $draft->id)->delete();
            } else {
                $draft = MailMessage::create([
                    'mail_account_id' => $account->id,
                    'message_id' => 'draft-' . uniqid('', true),
                    'folder' => 'Drafts',
                    'subject' => $data['subject'] ?: '(Kein Betreff)',
                    'from_name' => $account->name,
                    'from_email' => $account->email,
                    'to_email' => implode(', ', $toList),
                    'cc_email' => !empty($ccList) ? implode(', ', $ccList) : null,
                    'bcc_email' => !empty($bccList) ? implode(', ', $bccList) : null,
                    'body_plain' => $plainBody,
                    'body_html' => nl2br(e($plainBody)),
                    'is_read' => true,
                    'is_archived' => false,
                    'has_attachments' => count($attachmentDataParams) > 0,
                    'received_at' => now(),
                ]);
            }

            foreach ($attachmentDataParams as $att) {
                \App\Models\Management\Mail\MailAttachment::create([
                    'mail_message_id' => $draft->id,
                    'filename' => $att['filename'],
                    'content_type' => $att['mime'],
                    'size' => $att['size'],
                    'path' => $att['path'],
                ]);
            }

            return $draft;
        };

        $buildPasswordGuidanceSteps = function (MailAccount $acc): array {
            $lower = strtolower((string) $acc->email);
            if (str_ends_with($lower, '@t-online.de')) {
                return [
                    "Klicke direkt auf 'Postfach-Passwort eintragen (⚙)'.",
                    'Gib dort dein separates T-Online Passwort für E-Mail-Programme ein (nicht das normale Telekom-Kundencenter-Login).',
                    'Falls noch keines eingerichtet ist: Telekom Kundencenter -> E-Mail-Center -> Einstellungen -> Passwörter -> Passwort für E-Mail-Programme.',
                    'Klicke auf Speichern & Verbindung prüfen -> danach wird dein Entwurf sofort versendet.',
                ];
            }
            if (str_ends_with($lower, '@gmail.com') || str_ends_with($lower, '@googlemail.com')) {
                return [
                    "Klicke direkt auf 'Postfach-Passwort eintragen (⚙)'.",
                    'Trage dein 16-stelliges Google App-Passwort ein (Google-Konto -> Sicherheit -> 2-Faktor -> App-Passwörter).',
                    'Klicke auf Speichern & Verbindung prüfen und sende deinen Entwurf ab.',
                ];
            }
            return [
                "Klicke direkt auf 'Postfach-Passwort eintragen (⚙)'.",
                "Trage das aktuelle E-Mail-Passwort für {$acc->email} ({$acc->smtp_host}) ein.",
                'Klicke auf Speichern & Verbindung prüfen und sende deinen Entwurf ab.',
            ];
        };

        // Pre-Flight Check A: Missing attachment file on current server (never crash or blame SMTP!)
        if (!empty($missingAttachmentNames)) {
            $draft = $saveDraftWithAttachments();
            $namesStr = implode(', ', $missingAttachmentNames);
            return response()->json([
                'error' => "Die Anhang-Datei ({$namesStr}) liegt auf diesem Server nicht mehr im temporären Zwischenspeicher vor.",
                'action_code' => 'MISSING_ATTACHMENT',
                'missing_attachments' => $missingAttachmentNames,
                'needs_password' => false,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => "Anhang '{$namesStr}' bitte kurz neu anfügen",
                'guidance_steps' => [
                    'Dein kompletter Nachrichtentext, Empfänger und Betreff sind sicher als Entwurf gespeichert.',
                    "Ziehe die Datei '{$namesStr}' einfach per Drag & Drop von deinem PC erneut in dieses Fenster (oder klicke unten auf Datei anfügen).",
                    'Klicke anschließend auf Jetzt senden -> der Anhang wird ab sofort dauerhaft gesichert.',
                ],
            ], 422);
        }

        // Pre-Flight Check B: Missing or undecryptable password
        $decryptedPassword = trim((string) $account->password);
        if ($decryptedPassword === '') {
            $draft = $saveDraftWithAttachments();
            return response()->json([
                'error' => "Für {$account->email} ist in dieser Umgebung noch kein E-Mail-Passwort gespeichert.",
                'action_code' => 'NEEDS_PASSWORD',
                'needs_password' => true,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => "Passwort für {$account->email} einmalig hinterlegen",
                'guidance_steps' => $buildPasswordGuidanceSteps($account),
            ], 422);
        }

        // Promote verified attachments into permanent 'sent' bucket before sending
        foreach ($attachmentDataParams as &$attRef) {
            $attRef['path'] = $promoteAttachmentPath($attRef['path'], $attRef['filename'], 'sent', (int) ($attRef['size'] ?? 0) ?: null);
        }
        unset($attRef);

        $signature = !empty($account->signature) ? ("<br><br>" . $account->signature) : "";
        $bodyHtml = $data['body'] . $signature;
        $signatureHtml = null;

        $mailable = new \App\Mail\CrmOutgoingMailToCustomer(
            $data['subject'], $bodyHtml, $signatureHtml, $account->email, $account->name, $attachmentDataParams
        );

        // Build primary + auto-healing fallback SMTP transport candidates (465 SSL <-> 587 STARTTLS)
        $smtpHostClean = trim((string) $account->smtp_host);
        $primaryPort = (int) trim((string) $account->smtp_port ?: '465');
        $primaryEnc = strtolower(trim((string) $account->smtp_encryption ?: ($primaryPort === 465 ? 'ssl' : 'tls')));

        if (
            !\App\Services\System\SystemSecurityService::isSafeMailHost($smtpHostClean)
            || !\App\Services\System\SystemSecurityService::isAllowedMailPort($primaryPort)
            || !\App\Services\System\SystemSecurityService::isSafeMailEncryption($primaryEnc, $smtpHostClean)
        ) {
            return response()->json([
                'error' => 'Ungültige oder unverschlüsselte SMTP-Server-Konfiguration blockiert.',
            ], 422);
        }

        $smtpCandidates = [
            ['host' => $smtpHostClean, 'port' => $primaryPort, 'enc' => $primaryEnc],
        ];
        if ($primaryPort === 465) {
            $smtpCandidates[] = ['host' => $smtpHostClean, 'port' => 587, 'enc' => 'tls'];
        } elseif ($primaryPort === 587) {
            $smtpCandidates[] = ['host' => $smtpHostClean, 'port' => 465, 'enc' => 'ssl'];
        }

        $sentOk = false;
        $lastException = null;

        foreach ($smtpCandidates as $idx => $cand) {
            try {
                $candPort = (int) $cand['port'];
                $candEnc = $cand['enc'];
                $candScheme = ($candPort === 465 || $candEnc === 'ssl') ? 'smtps' : 'smtp';

                Config::set('mail.mailers.dynamic', [
                    'transport' => 'smtp',
                    'scheme' => $candScheme,
                    'host' => $cand['host'],
                    'port' => $candPort,
                    'encryption' => $candEnc === 'none' ? null : $candEnc,
                    'username' => trim($account->smtp_username ?: $account->email),
                    'password' => $decryptedPassword,
                    'timeout' => 15,
                    'local_domain' => env('MAIL_EHLO_DOMAIN'),
                ]);

                app('mail.manager')->purge('dynamic');

                $pendingMail = app()->environment('testing')
                    ? Mail::to($toList)
                    : Mail::mailer('dynamic')->to($toList);

                if (!empty($ccList)) {
                    $pendingMail->cc($ccList);
                }
                if (!empty($bccList)) {
                    $pendingMail->bcc($bccList);
                }

                $pendingMail->send($mailable);
                $sentOk = true;

                // If fallback port/encryption healed the connection, persist it automatically!
                if ($idx > 0) {
                    $account->update([
                        'smtp_port' => $candPort,
                        'smtp_encryption' => $candEnc,
                        'status' => 'connected',
                    ]);
                }
                break;
            } catch (\Throwable $e) {
                $lastException = $e;
                $errMsg = $e->getMessage();
                // Do NOT retry fallback port on credential/auth errors (avoid locking account with double 535 attempts)
                if (
                    str_contains($errMsg, '535')
                    || stripos($errMsg, 'Authentication') !== false
                    || stripos($errMsg, 'Invalid login') !== false
                    || str_starts_with($errMsg, 'MISSING_ATTACHMENT:')
                ) {
                    break;
                }
            }
        }

        if ($sentOk) {
            // Save in Sent
            $cleanSentPlain = html_entity_decode(
                strip_tags(str_replace(['<br>', '<br/>', '<br />', "\r\n", "\r"], "\n", (string) $data['body'])),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $sentMessage = MailMessage::create([
                'mail_account_id' => $account->id,
                'message_id' => 'sent-' . uniqid(),
                'folder' => 'Sent',
                'subject' => $data['subject'],
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => implode(', ', $toList),
                'cc_email' => !empty($ccList) ? implode(', ', $ccList) : null,
                'bcc_email' => !empty($bccList) ? implode(', ', $bccList) : null,
                'body_plain' => $cleanSentPlain,
                'body_html' => $bodyHtml,
                'is_read' => true,
                'has_attachments' => count($attachmentDataParams) > 0,
                'received_at' => now()
            ]);

            foreach ($attachmentDataParams as $att) {
                \App\Models\Management\Mail\MailAttachment::create([
                    'mail_message_id' => $sentMessage->id,
                    'filename' => $att['filename'],
                    'content_type' => $att['mime'],
                    'size' => $att['size'],
                    'path' => $att['path'],
                ]);
            }

            if (!empty($data['draft_id'])) {
                MailMessage::where('id', (int) $data['draft_id'])
                    ->where('folder', 'Drafts')
                    ->delete();
            }

            $normalizedThreadSubject = trim((string) preg_replace('/^(?:(?:re|aw|wg|fwd|fw)\s*:\s*)+/iu', '', (string) ($data['subject'] ?? '')));

            foreach ($toList as $singleTo) {
                $cleanTo = strtolower(trim((string) $singleTo));
                if ($cleanTo !== '') {
                    $importantQuery = MailMessage::where('mail_account_id', $account->id)
                        ->whereRaw('LOWER(from_email) = ?', [$cleanTo])
                        ->where('priority', 'high');

                    if ($normalizedThreadSubject !== '') {
                        $escapedSubj = '%' . addcslashes($normalizedThreadSubject, '%_\\') . '%';
                        $importantQuery->where('subject', 'like', $escapedSubj);
                    }

                    $importantFromRecipient = $importantQuery->get();
                    foreach ($importantFromRecipient as $imp) {
                        $tags = is_array($imp->tags) ? $imp->tags : [];
                        $tags['importance'] = 'Normal';
                        $tags['resolved'] = true;
                        $tags['action_hint'] = null;
                        $imp->update([
                            'folder' => 'Archive',
                            'is_archived' => true,
                            'priority' => 'normal',
                            'is_read' => true,
                            'tags' => $tags,
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'sent_id' => $sentMessage->id,
                'from_email' => $account->email,
                'to' => $toList,
            ]);
        }

        // Intelligent Structured Failure Classification (Zero Generic Crashes!)
        $rawMsg = $lastException ? $lastException->getMessage() : 'Unbekannte Rückmeldung';
        Log::warning("Mail-Versand Hinweis ({$account->email}): {$rawMsg}");

        $draft = $saveDraftWithAttachments();

        if (str_starts_with($rawMsg, 'MISSING_ATTACHMENT:')) {
            $missingFile = substr($rawMsg, strlen('MISSING_ATTACHMENT:'));
            return response()->json([
                'error' => "Die Anhang-Datei '{$missingFile}' muss kurz neu angefügt werden.",
                'action_code' => 'MISSING_ATTACHMENT',
                'missing_attachments' => [$missingFile],
                'needs_password' => false,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => "Anhang '{$missingFile}' bitte neu hineinziehen",
                'guidance_steps' => [
                    'Dein Entwurf ist vollständig gesichert.',
                    "Ziehe '{$missingFile}' per Drag & Drop erneut in dieses Fenster und klicke auf Jetzt senden.",
                ],
            ], 422);
        }

        $isAuthErr = str_contains($rawMsg, '535')
            || stripos($rawMsg, 'Authentication') !== false
            || stripos($rawMsg, 'Invalid login') !== false
            || stripos($rawMsg, 'Username and Password not accepted') !== false;

        if ($isAuthErr) {
            return response()->json([
                'error' => "Der Mailserver ({$account->smtp_host}) hat das hinterlegte Passwort für {$account->email} abgelehnt (Code 535).",
                'action_code' => 'AUTH_REJECTED',
                'needs_password' => true,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => "E-Mail-Passwort für {$account->email} aktualisieren",
                'guidance_steps' => $buildPasswordGuidanceSteps($account),
            ], 422);
        }

        if (str_contains($rawMsg, '552') || stripos($rawMsg, 'size') !== false || stripos($rawMsg, 'too large') !== false) {
            return response()->json([
                'error' => 'Die E-Mail überschreitet das Größenlimit des Empfänger-Mailservers.',
                'action_code' => 'ATTACHMENT_TOO_LARGE',
                'needs_password' => false,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => 'Anhänge für den Mailserver zu groß',
                'guidance_steps' => [
                    'Dein Entwurf bleibt vollständig gespeichert.',
                    'Verkleinere große PDF-/Bilddateien auf unter 15 MB oder teile sie auf zwei E-Mails auf.',
                ],
            ], 422);
        }

        if (preg_match('/\b(550|553|554|501)\b/', $rawMsg)) {
            return response()->json([
                'error' => 'Der Mailserver hat die Empfänger- oder Absenderadresse abgewiesen.',
                'action_code' => 'RECIPIENT_REJECTED',
                'needs_password' => false,
                'account_id' => $account->id,
                'draft_saved' => true,
                'draft_id' => $draft->id ?? null,
                'guidance_title' => 'Empfänger-Adresse kurz prüfen',
                'guidance_steps' => [
                    'Prüfe die Schreibweise der Empfänger-Adresse im Feld AN / CC / BCC auf Tippfehler.',
                    "Prüfe unter ⚙, ob als SMTP-Benutzername exakt {$account->email} eingetragen ist.",
                ],
            ], 422);
        }

        return response()->json([
            'error' => "Der Postausgangsserver {$account->smtp_host} ist momentan nicht erreichbar.",
            'action_code' => 'NETWORK_UNREACHABLE',
            'needs_password' => false,
            'account_id' => $account->id,
            'draft_saved' => true,
            'draft_id' => $draft->id ?? null,
            'guidance_title' => "Keine Netzwerkverbindung zu {$account->smtp_host}",
            'guidance_steps' => [
                'Deine E-Mail inkl. aller Anhänge wurde sicher unter Entwürfe gespeichert.',
                'Prüfe kurz deine Internetverbindung bzw. ob ein VPN/Firewall Port 465 oder 587 blockiert.',
                "Klicke ansonsten auf ⚙ neben {$account->email} -> Speichern & Verbindung prüfen.",
            ],
        ], 422);
    });

    // 4b. Save or Update Draft ("Entwürfe")
    Route::post('/drafts', function (Request $request) {
        $data = $request->validate([
            'draft_id' => 'nullable|integer',
            'account_id' => 'nullable',
            'to' => 'nullable|string|max:1000',
            'cc' => 'nullable|string|max:1000',
            'bcc' => 'nullable|string|max:1000',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:200000',
            'attachments' => 'nullable|array|max:20',
        ]);

        $account = null;
        if (!empty($data['account_id'])) {
            $account = MailAccount::find($data['account_id']);
        }
        if (!$account) {
            $account = MailAccount::where('is_default', true)->first() ?: MailAccount::first();
        }
        if (!$account) {
            return response()->json(['error' => 'Kein E-Mail-Konto konfiguriert'], 400);
        }

        $draftAttachmentParams = [];
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $att) {
                if (!empty($att['id']) && empty($att['path'])) {
                    $existingAtt = \App\Models\Management\Mail\MailAttachment::find((int) $att['id']);
                    if ($existingAtt && !empty($existingAtt->path)) {
                        $att['path'] = $existingAtt->path;
                        $att['filename'] = $att['filename'] ?? $existingAtt->filename;
                        $att['mime'] = $att['mime'] ?? $existingAtt->content_type;
                        $att['size'] = $att['size'] ?? $existingAtt->size;
                    }
                }
                $attPath = ltrim((string) ($att['path'] ?? ''), '/');
                if (
                    !empty($attPath)
                    && !str_contains($attPath, '..')
                    && \Illuminate\Support\Str::startsWith($attPath, ['temp/mail-attachments/', 'mail-attachments/', 'crm/', 'leitung/crm/'])
                ) {
                    $fname = basename((string) ($att['filename'] ?? 'attachment'));
                    $fsize = (int) ($att['size'] ?? 0);
                    $abs = \App\Mail\CrmOutgoingMailToCustomer::resolveVerifiedAttachmentDiskPath($attPath, $fsize > 0 ? $fsize : null, $fname);
                    if ($abs !== null && !\Illuminate\Support\Str::startsWith($attPath, 'leitung/crm/mail-attachments/')) {
                        $safeName = preg_replace('/[^A-Za-z0-9._äöüÄÖÜß\- ]+/u', '_', $fname);
                        $permRel = 'leitung/crm/mail-attachments/drafts/' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '_' . $safeName;
                        try {
                            \Illuminate\Support\Facades\Storage::disk('local')->put($permRel, file_get_contents($abs));
                            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($permRel)) {
                                $attPath = $permRel;
                            }
                        } catch (\Throwable) {
                            // keep verified path
                        }
                    }
                    $draftAttachmentParams[] = [
                        'path' => $attPath,
                        'filename' => $fname,
                        'mime' => $att['mime'] ?? $att['content_type'] ?? 'application/octet-stream',
                        'size' => $abs !== null ? (filesize($abs) ?: $fsize) : $fsize,
                    ];
                }
            }
        }

        $to = trim((string) ($data['to'] ?? ''));
        $cc = trim((string) ($data['cc'] ?? ''));
        $bcc = trim((string) ($data['bcc'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $rawBody = (string) ($data['body'] ?? '');
        $bodyPlain = html_entity_decode(
            str_replace(["\r\n", "\r"], "\n", preg_replace('/<br\s*\/?>/i', "\n", $rawBody)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        if ($to === '' && $cc === '' && $bcc === '' && $subject === '' && trim($bodyPlain) === '' && empty($draftAttachmentParams)) {
            return response()->json(['success' => false, 'message' => 'Leerer Entwurf ignoriert']);
        }

        $finalSubject = $subject !== '' ? $subject : '(Kein Betreff)';
        $bodyHtml = nl2br(htmlspecialchars($bodyPlain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

        $draft = null;
        if (!empty($data['draft_id'])) {
            $draft = MailMessage::where('id', (int) $data['draft_id'])
                ->where('folder', 'Drafts')
                ->first();
        }

        if ($draft) {
            $draft->update([
                'mail_account_id' => $account->id,
                'subject' => $finalSubject,
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => $to,
                'cc_email' => $cc !== '' ? $cc : null,
                'bcc_email' => $bcc !== '' ? $bcc : null,
                'body_plain' => $bodyPlain,
                'body_html' => $bodyHtml,
                'is_read' => true,
                'is_archived' => false,
                'has_attachments' => !empty($draftAttachmentParams) ? true : $draft->has_attachments,
                'received_at' => now(),
            ]);
            if (!empty($draftAttachmentParams)) {
                \App\Models\Management\Mail\MailAttachment::where('mail_message_id', $draft->id)->delete();
            }
        } else {
            $draft = MailMessage::create([
                'mail_account_id' => $account->id,
                'message_id' => 'draft-' . uniqid('', true),
                'folder' => 'Drafts',
                'subject' => $finalSubject,
                'from_name' => $account->name,
                'from_email' => $account->email,
                'to_email' => $to,
                'cc_email' => $cc !== '' ? $cc : null,
                'bcc_email' => $bcc !== '' ? $bcc : null,
                'body_plain' => $bodyPlain,
                'body_html' => $bodyHtml,
                'is_read' => true,
                'is_archived' => false,
                'has_attachments' => count($draftAttachmentParams) > 0,
                'received_at' => now(),
            ]);
        }

        foreach ($draftAttachmentParams as $att) {
            \App\Models\Management\Mail\MailAttachment::create([
                'mail_message_id' => $draft->id,
                'filename' => $att['filename'],
                'content_type' => $att['mime'],
                'size' => $att['size'],
                'path' => $att['path'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $draft,
        ]);
    });

    // 5. Move Message (including Spam Rule logic + Processed Funnel)
    Route::put('/messages/{id}/move', function (Request $request, $id) {
        $data = $request->validate(['folder' => 'required|string|max:50']);
        $folder = trim($data['folder']);
        if ($folder === '' || preg_match('/(\.\.|[\/\\\\\x00-\x1F])/', $folder)) {
            return response()->json(['error' => 'Ungültiger Ordnername.'], 422);
        }
        $msg = MailMessage::findOrFail($id);
        $wasJunk = ($msg->folder === 'Junk');

        $cleanEmail = strtolower(trim((string) $msg->from_email));
        if ($folder === 'Junk') {
            if ($cleanEmail !== '') {
                \App\Models\Management\Mail\MailRule::updateOrCreate(
                    [
                        'mail_account_id' => $msg->mail_account_id,
                        'condition_field' => 'from_email',
                        'condition_value' => $cleanEmail,
                    ],
                    [
                        'type' => 'blacklist',
                        'action' => 'mark_spam',
                    ]
                );
                MailMessage::where('mail_account_id', $msg->mail_account_id)
                    ->whereRaw('LOWER(from_email) = ?', [$cleanEmail])
                    ->update(['folder' => 'Junk', 'is_archived' => false]);
            } else {
                $msg->update(['folder' => 'Junk', 'is_archived' => false]);
            }
            return response()->json(['success' => true, 'spam_deleted' => false]);
        }

        if (!in_array($folder, ['INBOX', 'Sent', 'Drafts', 'Junk', 'Trash', 'Archive', 'Processed'], true)) {
            \App\Models\Management\Mail\MailFolder::firstOrCreate([
                'mail_account_id' => $msg->mail_account_id,
                'name' => $folder,
            ]);
        }

        if ($folder === 'Archive') {
            $msg->update(['folder' => 'Archive', 'is_archived' => true, 'is_read' => true]);
        } elseif ($folder === 'Trash') {
            $msg->update(['folder' => 'Trash', 'is_archived' => false, 'is_read' => true]);
        } else {
            $msg->update(['folder' => $folder, 'is_archived' => false]);
        }

        if ($wasJunk && in_array($folder, ['INBOX', 'Processed', 'Archive'], true) && $cleanEmail !== '') {
            \App\Models\Management\Mail\MailRule::where('mail_account_id', $msg->mail_account_id)
                ->where('type', 'blacklist')
                ->whereRaw('LOWER(condition_value) = ?', [$cleanEmail])
                ->delete();
        }

        return response()->json(['success' => true]);
    });

    // 5b. Resolve / Toggle Importance of a Processed Mail ("Wichtig" -> "Normal" erledigt & ins Archiv)
    Route::put('/messages/{id}/resolve', function (Request $request, $id) {
        $msg = MailMessage::findOrFail($id);
        $tags = is_array($msg->tags) ? $msg->tags : [];
        $makeImportant = $request->boolean('important', false);

        if ($makeImportant) {
            $tags['importance'] = 'Wichtig';
            $tags['resolved'] = false;
            if (empty($tags['action_hint'])) {
                $tags['action_hint'] = $request->input('action_hint') ?: ('Rückmeldung / Prüfung zu: ' . \Illuminate\Support\Str::limit($msg->subject, 70));
            }
            $msg->update([
                'folder' => 'Processed',
                'is_archived' => false,
                'priority' => 'high',
                'tags' => $tags,
            ]);
        } else {
            $tags['importance'] = 'Normal';
            $tags['resolved'] = true;
            $tags['action_hint'] = null;
            $msg->update([
                'folder' => 'Archive',
                'is_archived' => true,
                'priority' => 'normal',
                'is_read' => true,
                'tags' => $tags,
            ]);
        }

        return response()->json(['success' => true, 'data' => $msg]);
    });

    // 6. Toggle Read Status
    Route::put('/messages/{id}/read', function (Request $request, $id) {
        $data = $request->validate(['is_read' => 'required|boolean']);
        $msg = MailMessage::findOrFail($id);
        $msg->update(['is_read' => $data['is_read']]);
        return response()->json(['success' => true]);
    });

    // 7. Archive instead of Delete (Zero Email Deletion Policy)
    Route::delete('/messages/{id}', function ($id) {
        $msg = MailMessage::findOrFail($id);
        $msg->update(['folder' => 'Archive', 'is_archived' => true, 'is_read' => true]);
        return response()->json(['success' => true, 'archived' => true]);
    });

    // 8. Create or Update Account
    Route::post('/accounts', function (Request $request) {
        $data = $request->validate([
            'id' => 'nullable',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'nullable|string|max:500',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|in:143,465,587,993,2525',
            'imap_encryption' => 'nullable|string|in:ssl,tls,starttls,none',
            'imap_username' => 'nullable|string|max:255',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|in:143,465,587,993,2525',
            'smtp_encryption' => 'nullable|string|in:ssl,tls,starttls,none',
            'smtp_username' => 'nullable|string|max:255',
            'signature' => 'nullable|string|max:20000',
            'is_default' => 'boolean',
            'is_commercial' => 'boolean',
        ]);

        if (!\App\Services\System\SystemSecurityService::isSafeMailHost($data['imap_host']) || !\App\Services\System\SystemSecurityService::isSafeMailHost($data['smtp_host'])) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:ssrf_mail_host_blocked',
                'ERROR',
                'Unsicherer Mail-Server Host über API blockiert (SSRF-Schutz).',
                ['imap_host' => $data['imap_host'], 'smtp_host' => $data['smtp_host']],
                'idor_integrity'
            );
            return response()->json(['error' => 'Ungültiger oder intern gesperrter Mail-Server Hostname.'], 422);
        }

        $imapEnc = $data['imap_encryption'] ?? 'ssl';
        $smtpEnc = $data['smtp_encryption'] ?? 'ssl';
        if (
            !\App\Services\System\SystemSecurityService::isSafeMailEncryption($imapEnc, $data['imap_host'])
            || !\App\Services\System\SystemSecurityService::isSafeMailEncryption($smtpEnc, $data['smtp_host'])
        ) {
            return response()->json([
                'error' => 'Unverschlüsselte Klartext-Mailverbindungen (none) sind für externe Mail-Server unzulässig.',
            ], 422);
        }

        if (!empty($data['id'])) {
            $existing = MailAccount::find($data['id']);
            if ($existing && ($existing->imap_host !== $data['imap_host'] || $existing->smtp_host !== $data['smtp_host']) && empty($data['password'])) {
                return response()->json(['error' => 'Bei Änderung des Mail-Servers muss das Passwort erneut angegeben werden.'], 422);
            }
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'imap_host' => $data['imap_host'],
            'imap_port' => $data['imap_port'],
            'imap_encryption' => $data['imap_encryption'] ?? 'ssl',
            'imap_username' => $data['imap_username'] ?? $data['email'],
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => $data['smtp_port'],
            'smtp_encryption' => $data['smtp_encryption'] ?? 'ssl',
            'smtp_username' => $data['smtp_username'] ?? $data['email'],
            'signature' => $data['signature'] ?? null,
            'is_default' => $data['is_default'] ?? false,
            'is_commercial' => $data['is_commercial'] ?? true,
            'status' => 'connected',
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = $data['password'];
        }

        $account = MailAccount::updateOrCreate(
            ['id' => $data['id'] ?? null],
            $updateData
        );

        $hasPw = trim((string) $account->password) !== '';
        $smtpVerified = null;
        $smtpMessage = null;

        if ($request->boolean('verify_smtp')) {
            if (!$hasPw) {
                $smtpVerified = false;
                $smtpMessage = 'Kein Passwort gespeichert.';
            } else {
                try {
                    $smtpPort = (int) trim((string) $account->smtp_port ?: '465');
                    $smtpEnc = strtolower(trim((string) $account->smtp_encryption ?: 'ssl'));
                    $smtpScheme = ($smtpPort === 465 || $smtpEnc === 'ssl') ? 'smtps' : 'smtp';

                    Config::set('mail.mailers.dynamic_test', [
                        'transport' => 'smtp',
                        'scheme' => $smtpScheme,
                        'host' => trim($account->smtp_host),
                        'port' => $smtpPort,
                        'encryption' => $smtpEnc === 'none' ? null : $smtpEnc,
                        'username' => trim($account->smtp_username ?: $account->email),
                        'password' => trim((string) $account->password),
                        'timeout' => 12,
                        'local_domain' => env('MAIL_EHLO_DOMAIN'),
                    ]);
                    app('mail.manager')->purge('dynamic_test');
                    $transport = app('mail.manager')->mailer('dynamic_test')->getSymfonyTransport();
                    if (method_exists($transport, 'start')) {
                        $transport->start();
                    }
                    if (method_exists($transport, 'stop')) {
                        $transport->stop();
                    }
                    $smtpVerified = true;
                    $smtpMessage = 'SMTP-Verbindung & Anmeldung erfolgreich geprüft.';
                } catch (\Throwable $e) {
                    $smtpVerified = false;
                    $raw = $e->getMessage();
                    if (str_contains($raw, '535') || stripos($raw, 'Authentication') !== false) {
                        $lowerEmail = strtolower((string) $account->email);
                        if (str_ends_with($lowerEmail, '@t-online.de')) {
                            $smtpMessage = 'Anmeldung abgelehnt (535). Bitte verwende bei T-Online das separate „Passwort für E-Mail-Programme" aus dem Telekom Kundencenter.';
                        } elseif (str_ends_with($lowerEmail, '@gmail.com') || str_ends_with($lowerEmail, '@googlemail.com')) {
                            $smtpMessage = 'Anmeldung abgelehnt (535). Bitte verwende bei Gmail ein 16-stelliges Google App-Passwort.';
                        } else {
                            $smtpMessage = 'SMTP-Anmeldung abgelehnt (Code 535): Bitte prüfe Benutzername und Passwort.';
                        }
                    } else {
                        $smtpMessage = 'SMTP-Verbindung fehlgeschlagen: Bitte prüfe Server (' . $account->smtp_host . ':' . $account->smtp_port . ') und Verschlüsselung.';
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $account,
            'has_password' => $hasPw,
            'smtp_verified' => $smtpVerified,
            'smtp_message' => $smtpMessage,
        ]);
    });

    // 9. Delete Account
    Route::delete('/accounts/{id}', function ($id) {
        $account = MailAccount::findOrFail($id);
        $account->delete();
        return response()->json(['success' => true]);
    });

    // 10. Upload Attachment
    Route::post('/attachments', function (Request $request) {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,ppt,pptx,odt,ods,odp,png,jpg,jpeg,webp,gif,zip,tar,gz,txt,json,xml', // limit of 20MB per file
        ]);

        $file = $request->file('file');
        $path = $file->store('temp/mail-attachments', 'local');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]
        ]);
    });

    // 11. Download Attachment
    Route::get('/attachments/{id}', function ($id, \App\Services\Export\FileDownloadService $downloadService) {
        return $downloadService->downloadMailAttachment($id);
    });

    // 12. Create Custom Folder
    Route::post('/folders', function (Request $request) {
        $data = $request->validate([
            'account_id' => 'required|exists:mail_accounts,id',
            'name' => 'required|string|max:50'
        ]);

        $folderName = trim($data['name']);
        if ($folderName === '' || preg_match('/(\.\.|[\/\\\\\x00-\x1F])/', $folderName)) {
            return response()->json(['error' => 'Ungültiger Ordnername.'], 422);
        }

        $folder = \App\Models\Management\Mail\MailFolder::firstOrCreate([
            'mail_account_id' => $data['account_id'],
            'name' => $folderName
        ]);

        return response()->json(['success' => true, 'data' => $folder]);
    });

    // 13. Delete Custom Folder
    Route::delete('/folders', function (Request $request) {
        $data = $request->validate([
            'account_id' => 'required|exists:mail_accounts,id',
            'name' => 'required|string|max:50'
        ]);

        $baseFolders = ['INBOX', 'Sent', 'Trash', 'Archive', 'Drafts', 'Junk'];
        if (in_array($data['name'], $baseFolders)) {
            return response()->json(['success' => false, 'message' => 'Standardordner können nicht gelöscht werden.'], 400);
        }

        \App\Models\Management\Mail\MailFolder::where('mail_account_id', $data['account_id'])
            ->where('name', $data['name'])
            ->delete();

        // Move messages back to INBOX
        \App\Models\Management\Mail\MailMessage::where('mail_account_id', $data['account_id'])
            ->where('folder', $data['name'])
            ->update(['folder' => 'INBOX']);

        return response()->json(['success' => true]);
    });

    // 14. Trigger IMAP Sync (supports synchronous `wait: true` with live metrics + fast background fallback)
    Route::post('/sync', function (Request $request) {
        try {
            if ($request->boolean('wait', false)) {
                @set_time_limit(60);
                $startedAt = microtime(true);
                $beforeCount = MailMessage::count();

                \Illuminate\Support\Facades\Artisan::call('crm:fetch-mails');

                $afterCount = MailMessage::count();
                $newCount = max(0, $afterCount - $beforeCount);
                $accounts = MailAccount::all();
                $syncedAccounts = $accounts->filter(fn ($a) => trim((string) $a->password) !== '' && $a->status === 'connected')->count();
                $failedAccounts = $accounts->filter(fn ($a) => trim((string) $a->password) !== '' && $a->status === 'error')
                    ->pluck('email')
                    ->values()
                    ->all();

                return response()->json([
                    'success' => true,
                    'mode' => 'sync',
                    'new_mails_count' => $newCount,
                    'synced_accounts' => $syncedAccounts,
                    'failed_accounts' => $failedAccounts,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'message' => $newCount > 0
                        ? ($newCount === 1 ? '1 neue E-Mail abgerufen' : "{$newCount} neue E-Mails abgerufen")
                        : 'Postfächer aktuell',
                ]);
            }

            $artisan = escapeshellarg(base_path('artisan'));
            exec("nohup php {$artisan} crm:fetch-mails > /dev/null 2>&1 &");
            return response()->json(['success' => true, 'mode' => 'async', 'message' => 'Synchronisation gestartet']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    });

    // 15. Create To-Do Task from Mail
    Route::post('/messages/{id}/create-task', function (Request $request, $id) {
        $mail = MailMessage::findOrFail($id);
        $listName = $request->input('list_name', 'E-Mail Aufgaben');
        $targetList = \App\Models\Management\ManagementTaskList::firstOrCreate(
            ['name' => $listName],
            ['icon' => 'envelope', 'color' => '#C5A059', 'position' => (\App\Models\Management\ManagementTaskList::max('position') ?? 0) + 1]
        );
        $listId = $targetList->id;
        $maxPos = \App\Models\Management\ManagementTask::where('task_list_id', $listId)->max('position') ?? 0;

        $bodySnippet = \Illuminate\Support\Str::limit(strip_tags((string) ($mail->body_plain ?: $mail->body_html ?: '')), 1000);
        $dateStr = $mail->received_at ? $mail->received_at->format('d.m.Y H:i') : now()->format('d.m.Y H:i');

        $task = \App\Models\Management\ManagementTask::create([
            'task_list_id' => $listId,
            'title' => $request->input('title') ?: ('E-Mail: ' . ($mail->subject ?: 'Ohne Betreff')),
            'ai_plan' => "Absender: {$mail->from_name} <{$mail->from_email}>\nDatum: {$dateStr}\nMail-ID: #{$mail->id}\n\n{$bodySnippet}",
            'priority' => in_array($mail->priority, ['high', 'hoch'], true) ? 'hoch' : 'mittel',
            'position' => $maxPos + 1,
            'relevant_from' => now(),
        ]);

        return response()->json(['success' => true, 'data' => $task]);
    });

    // 16. Create Contact from Mail Sender
    Route::post('/messages/{id}/create-contact', function ($id) {
        $mail = MailMessage::findOrFail($id);
        if (empty($mail->from_email)) {
            return response()->json(['success' => false, 'message' => 'Keine Absender-Adresse vorhanden.'], 422);
        }

        $existing = \App\Models\Management\ManagementContact::whereRaw('LOWER(email) = ?', [strtolower(trim($mail->from_email))])->first();
        if ($existing) {
            return response()->json(['success' => true, 'already_exists' => true, 'data' => $existing, 'message' => "Kontakt '{$existing->first_name} {$existing->last_name}' existiert bereits."]);
        }

        $name = trim($mail->from_name ?: '');
        if ($name !== '') {
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        } else {
            $firstName = explode('@', $mail->from_email)[0];
            $lastName = '';
        }

        $contact = \App\Models\Management\ManagementContact::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => trim($mail->from_email),
            'relation_type' => 'business',
        ]);

        return response()->json(['success' => true, 'already_exists' => false, 'data' => $contact, 'message' => "Kontakt '{$firstName} {$lastName}' wurde im Adressbuch angelegt."]);
    });

    // 17. Create Auto-Routing Rule for Sender (Abonnieren / Immer in Ordner)
    Route::post('/messages/{id}/rule', function (Request $request, $id) {
        $data = $request->validate(['target_folder' => 'required|string|max:50']);
        $targetFolder = trim($data['target_folder']);
        if ($targetFolder === '' || preg_match('/(\.\.|[\/\\\\\x00-\x1F])/', $targetFolder)) {
            return response()->json(['error' => 'Ungültiger Ordnername.'], 422);
        }

        $msg = MailMessage::findOrFail($id);
        $cleanEmail = strtolower(trim((string) $msg->from_email));
        if ($cleanEmail === '') {
            return response()->json(['error' => 'Kein Absender vorhanden.'], 422);
        }

        if (!in_array($targetFolder, ['INBOX', 'Sent', 'Drafts', 'Junk', 'Trash', 'Archive'], true)) {
            \App\Models\Management\Mail\MailFolder::firstOrCreate([
                'mail_account_id' => $msg->mail_account_id,
                'name' => $targetFolder,
            ]);
        }

        \App\Models\Management\Mail\MailRule::updateOrCreate(
            [
                'mail_account_id' => $msg->mail_account_id,
                'condition_field' => 'from_email',
                'condition_value' => $cleanEmail,
            ],
            [
                'type' => $targetFolder === 'Junk' ? 'blacklist' : 'routing',
                'action' => $targetFolder === 'Junk' ? 'mark_spam' : $targetFolder,
            ]
        );

        // Move current message and all INBOX messages from this sender
        $isArchived = ($targetFolder === 'Archive');
        MailMessage::where('mail_account_id', $msg->mail_account_id)
            ->whereRaw('LOWER(from_email) = ?', [$cleanEmail])
            ->whereIn('folder', ['INBOX', $msg->folder])
            ->update(['folder' => $targetFolder, 'is_archived' => $isArchived]);

        return response()->json([
            'success' => true,
            'message' => "Regel aktiv: Alle E-Mails von {$cleanEmail} landen automatisch in '{$targetFolder}'."
        ]);
    });

    // 18. Save Drag & Drop Order for Accounts and Folders
    Route::post('/reorder', function (Request $request) {
        $orderFile = storage_path('app/leitung/mail/funkenwerk_mail_order.json');
        if (!is_dir(dirname($orderFile))) {
            @mkdir(dirname($orderFile), 0775, true);
        }
        $orderData = file_exists($orderFile) ? (json_decode(file_get_contents($orderFile), true) ?: []) : [];

        if ($request->has('accounts') && is_array($request->accounts)) {
            $orderData['accounts'] = array_map('intval', $request->accounts);
        }
        if ($request->has('account_id') && $request->has('folders') && is_array($request->folders)) {
            $accId = (int) $request->account_id;
            if (!isset($orderData['folders'])) {
                $orderData['folders'] = [];
            }
            $orderData['folders'][$accId] = array_values(array_map('strval', $request->folders));
        }

        file_put_contents($orderFile, json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return response()->json(['success' => true, 'data' => $orderData]);
    });

    // 19. AI Sort & Actionable Task Extraction ("KI-Sortierung")
    Route::post('/ai-sort', function (\App\Services\Management\MailAiSortService $sortService) {
        return response()->json($sortService->sortAllMailsAndSubfolders());
    });
});


