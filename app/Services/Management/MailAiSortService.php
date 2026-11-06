<?php

namespace App\Services\Management;

use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

class MailAiSortService
{
    /**
     * Known AI-generated subfolders from the legacy multi-folder logic.
     * These are automatically dissolved into the central 'Processed' ("Verarbeitet") funnel.
     */
    public const LEGACY_AI_FOLDERS = [
        'Bankkonto',
        'DHL',
        'Glücksfieber',
        'Hosting',
        'KartonEu',
        'Westpack',
        'XTool',
        'Rechnungen',
        'Rechtliches',
        'Server-Backups',
        'Agentur für Arbeit',
        'Amazon',
        'ARAG',
        'Bamtoo Zahnbürste',
        'Bestellungen',
        'Haftpflicht',
        'HP',
        'Immobilien',
        'Immobilien & Verwaltung',
        'Krankenkasse',
        'Objego',
        'Paypal',
        'Smartmiete',
        'Smartsteuer',
        'Stefan Klink',
        'Telekom',
        'Transition',
        'Versand & Pakete',
        'Zahnzusatz',
        'Behörden & BundID',
        'Google',
        'Allgemein & Info',
    ];

    public static function getDeletedMessageIdsPath(): string
    {
        return storage_path('app/deleted_mail_ids.json');
    }

    public static function rememberDeletedMessageIds(array $messageIds): void
    {
        $filtered = array_values(array_filter(array_map('trim', $messageIds)));
        if (empty($filtered)) {
            return;
        }
        $path = self::getDeletedMessageIdsPath();
        $existing = file_exists($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $merged = array_values(array_unique(array_merge($existing, $filtered)));
        if (count($merged) > 5000) {
            $merged = array_slice($merged, -5000);
        }
        @file_put_contents($path, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function isMessageIdDeleted(string $messageId): bool
    {
        if ($messageId === '') {
            return false;
        }
        $path = self::getDeletedMessageIdsPath();
        if (!file_exists($path)) {
            return false;
        }
        $list = json_decode((string) file_get_contents($path), true);
        return is_array($list) && in_array($messageId, $list, true);
    }

    /**
     * Automatically permanently deletes Trash ("Papierkorb") emails older than 30 days.
     */
    public function purgeExpiredTrashMessages(): int
    {
        $cutoff = now()->subDays(30);
        $expired = MailMessage::where('folder', 'Trash')
            ->where(function ($q) use ($cutoff) {
                $q->where('received_at', '<', $cutoff)
                  ->orWhere(function ($q2) use ($cutoff) {
                      $q2->whereNull('received_at')->where('updated_at', '<', $cutoff);
                  });
            })
            ->get();

        $deletedCount = 0;
        $idsToRemember = [];
        foreach ($expired as $msg) {
            if (!empty($msg->message_id)) {
                $idsToRemember[] = (string) $msg->message_id;
            }
            $msg->delete();
            $deletedCount++;
        }

        if (!empty($idsToRemember)) {
            self::rememberDeletedMessageIds($idsToRemember);
        }

        return $deletedCount;
    }

    public function sortAllMailsAndSubfolders(): array
    {
        $dedupedCount = 0;
        $importantCount = 0;
        $normalCount = 0;
        $unimportantCount = 0;
        $spamDeletedCount = 0;
        $expiredTrashDeletedCount = 0;

        // Step 0: Remove accidental spam rules for legitimate known senders
        $legitPatterns = ['hpsmart.com', 'paypal.de', 'google.com', 'itzbund.de', 'stadt-gifhorn.de', 'dhl.de', 'telekom.de'];
        foreach ($legitPatterns as $pat) {
            MailRule::where('action', 'mark_spam')
                ->where('condition_value', 'like', "%{$pat}%")
                ->delete();
        }

        // Step 1: Retire the legacy "E-Mail Aufgaben" To-Do list completely (now handled inline in "Verarbeitet")
        $legacyTaskLists = ManagementTaskList::where('name', 'E-Mail Aufgaben')->get();
        foreach ($legacyTaskLists as $ltl) {
            ManagementTask::where('task_list_id', $ltl->id)->delete();
            $ltl->delete();
        }

        // Step 2: Deduplicate messages per account
        $allAccounts = MailAccount::all()->keyBy('id');
        foreach ($allAccounts as $acc) {
            $msgs = MailMessage::where('mail_account_id', $acc->id)->orderBy('id', 'asc')->get();
            $seenExact = [];
            foreach ($msgs as $m) {
                $normFrom = strtolower(trim((string) $m->from_email));
                $normSubj = preg_replace('/\s+/u', ' ', trim((string) $m->subject));
                $normBody = md5(preg_replace('/\s+/u', ' ', trim(strip_tags((string) ($m->body_plain ?: $m->body_html)))));
                $dayStr = $m->received_at ? $m->received_at->format('Y-m-d') : '';

                $key = "{$normFrom}|{$normSubj}|{$normBody}|{$dayStr}";
                if (isset($seenExact[$key])) {
                    $m->delete();
                    $dedupedCount++;
                } else {
                    $seenExact[$key] = $m->id;
                }
            }
        }

        // Step 3: Dissolve all legacy AI subfolders in MailFolder
        MailFolder::whereIn('name', self::LEGACY_AI_FOLDERS)->delete();

        // Step 4: Funnel all messages across all accounts
        $allMessages = MailMessage::with('attachments')->orderBy('received_at', 'desc')->get();
        $spamMessageIdsToRemember = [];
        $seenBackupFailure = false;

        foreach ($allMessages as $msg) {
            $from = strtolower(trim((string) $msg->from_email));
            $fromName = strtolower(trim((string) $msg->from_name));
            $subj = preg_replace('/\s+/u', ' ', trim((string) $msg->subject));
            $subjLower = mb_strtolower($subj);
            $bodyPlain = strip_tags((string) ($msg->body_plain ?: $msg->body_html));
            $bodyLower = mb_strtolower($bodyPlain);

            // Keep Sent and Drafts untouched in their respective mailbox
            if (in_array($msg->folder, ['Sent', 'Drafts'], true)) {
                if ($msg->ai_status !== 'processed' || $msg->subject !== $subj) {
                    $msg->update(['ai_status' => 'processed', 'subject' => $subj]);
                }
                continue;
            }

            // Preserve any custom folder that the user manually created
            $isSystemOrLegacyFolder = in_array($msg->folder, array_merge(['INBOX', 'Junk', 'Trash', 'Archive', 'Processed'], self::LEGACY_AI_FOLDERS), true);
            if (!$isSystemOrLegacyFolder) {
                continue;
            }

            $existingTags = is_array($msg->tags) ? $msg->tags : [];
            $isAlreadyResolved = !empty($existingTags['resolved']);

            // Keep manually deleted messages in Trash (only rescue previous AI-trashed 'Unwichtig' messages into Archive)
            if ($msg->folder === 'Trash' && ($from === 'alina.stone@t-online.de' || ($existingTags['importance'] ?? '') !== 'Unwichtig')) {
                continue;
            }

            [$importance, $category, $actionHint] = $this->classifyFunnelMessage(
                $msg,
                $from,
                $fromName,
                $subj,
                $subjLower,
                $bodyLower,
                $seenBackupFailure
            );

            // 1. SPAM -> Create persistent Blacklist Rule + Delete message (empties Spam folder)
            if ($importance === 'Spam') {
                if ($from !== '') {
                    MailRule::updateOrCreate(
                        [
                            'mail_account_id' => $msg->mail_account_id,
                            'condition_field' => 'from_email',
                            'condition_value' => $from,
                        ],
                        [
                            'type' => 'blacklist',
                            'action' => 'mark_spam',
                        ]
                    );
                }
                if (!empty($msg->message_id)) {
                    $spamMessageIdsToRemember[] = (string) $msg->message_id;
                }
                $msg->delete();
                $spamDeletedCount++;
                continue;
            }

            // If user already resolved this Wichtig mail previously, downgrade to Normal -> goes to Archive
            if ($importance === 'Wichtig' && $isAlreadyResolved) {
                $importance = 'Normal';
                $actionHint = null;
            }

            $accEmail = $allAccounts[$msg->mail_account_id]->email ?? $msg->to_email;

            // 2. WICHTIG (Open Action Required) -> Stay in "Verarbeitet" ('Processed')
            if ($importance === 'Wichtig') {
                $msg->update([
                    'subject' => $subj,
                    'folder' => 'Processed',
                    'is_archived' => false,
                    'category' => $category ?: 'Wichtig',
                    'priority' => 'high',
                    'tags' => [
                        'importance' => 'Wichtig',
                        'category' => $category ?: 'Wichtig',
                        'account_email' => $accEmail,
                        'action_hint' => $actionHint,
                        'resolved' => false,
                    ],
                    'ai_status' => 'processed',
                ]);
                $importantCount++;
                continue;
            }

            // 3. NORMAL & UNWICHTIG -> Automatically categorize, tag, mark read & move into "Archiv" ('Archive')
            $prioCode = ($importance === 'Unwichtig') ? 'low' : 'normal';
            $cleanCategory = $category ?: ($importance === 'Unwichtig' ? 'Unwichtig / Status-Info' : 'Allgemein');

            $msg->update([
                'subject' => $subj,
                'folder' => 'Archive',
                'is_archived' => true,
                'is_read' => true,
                'category' => $cleanCategory,
                'priority' => $prioCode,
                'tags' => [
                    'importance' => $importance,
                    'category' => $cleanCategory,
                    'account_email' => $accEmail,
                    'action_hint' => null,
                    'resolved' => $isAlreadyResolved,
                ],
                'ai_status' => 'processed',
            ]);

            if ($importance === 'Unwichtig') {
                $unimportantCount++;
            } else {
                $normalCount++;
            }
        }

        if (!empty($spamMessageIdsToRemember)) {
            self::rememberDeletedMessageIds($spamMessageIdsToRemember);
        }

        // Step 5: Automatically purge Trash messages older than 30 days
        $expiredTrashDeletedCount = $this->purgeExpiredTrashMessages();

        // Step 6: Clean up folder ordering file so only standard folders + active manual folders remain
        $orderFile = storage_path('app/funkenwerk_mail_order.json');
        $orderData = file_exists($orderFile) ? (json_decode((string) file_get_contents($orderFile), true) ?: []) : [];
        $orderData['folders'] = [];
        $baseFolderKeys = ['INBOX', 'Sent', 'Drafts', 'Archive', 'Junk', 'Trash'];
        foreach ($allAccounts as $acc) {
            $manualFolders = MailFolder::where('mail_account_id', $acc->id)->orderBy('name')->pluck('name')->toArray();
            $orderData['folders'][$acc->id] = array_values(array_unique(array_merge($baseFolderKeys, $manualFolders)));
        }
        @file_put_contents($orderFile, json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $archivedTotal = $normalCount + $unimportantCount;
        $totalProcessed = $importantCount + $archivedTotal;
        $rulesCount = MailRule::where('action', 'mark_spam')->count();

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'important_count' => $importantCount,
            'normal_count' => $normalCount,
            'unimportant_count' => $unimportantCount,
            'archived_count' => $archivedTotal,
            'spam_deleted_count' => $spamDeletedCount,
            'expired_trash_deleted_count' => $expiredTrashDeletedCount,
            'spam_rules_count' => $rulesCount,
            'deduped_count' => $dedupedCount,
            'target_folder' => 'Processed',
            'message' => "KI-Trichter & Auto-Archivierung abgeschlossen!\n\n"
                . "• 🔴 {$importantCount} Wichtige E-Mails mit offenem Handlungsbedarf in 'Verarbeitet'\n"
                . "• 📦 {$archivedTotal} Routine- & Info-Mails ({$normalCount} Normal, {$unimportantCount} Unwichtig) kategorisiert & sicher ins 'Archiv' verschoben\n"
                . "• 🛡️ {$spamDeletedCount} Spam-Mails gelöscht ({$rulesCount} Spam-Abo-Regeln aktiv).",
        ];
    }

    /**
     * Returns [string $importance ('Wichtig'|'Normal'|'Unwichtig'|'Spam'), string $category, ?string $actionHint]
     */
    public function classifyFunnelMessage(
        MailMessage $msg,
        string $from,
        string $fromName,
        string $subj,
        string $subjLower,
        string $bodyLower,
        bool &$seenBackupFailure = false
    ): array {
        // 1. Check Spam / Phishing / Blacklist Rules First
        if (
            $msg->folder === 'Junk' ||
            str_contains($subj, 'MlTTWAID') ||
            str_contains($subj, 'МlТТWАLD') ||
            str_contains($from, 'greinacher-design.ch') ||
            str_contains($from, 'gerdemann-transporte.de') ||
            str_contains($from, 'weichandhof.de') ||
            str_contains($from, '0335boli.com') ||
            str_contains($from, 'info-jnhsport.com') ||
            str_contains($from, 'tripo3d.ai') ||
            str_contains($from, 'creativefabrica.com') ||
            str_contains($from, 'xing.com') ||
            str_contains($from, 'linkedin.com') ||
            str_contains($from, 'regiobus@deutschebahn.com') ||
            str_contains($from, 'connect.lieferando.de') ||
            str_contains($from, 'unity3d.com') ||
            (str_contains($from, 'it-recht-ffm.de') && str_contains($subjLower, 'verwertung'))
        ) {
            return ['Spam', 'Spam / Phishing', null];
        }

        if ($from !== '') {
            $rule = MailRule::where('condition_field', 'from_email')
                ->whereRaw('LOWER(condition_value) = ?', [$from])
                ->first();

            if ($rule) {
                if ($rule->action === 'mark_spam' || $rule->action === 'Junk' || $rule->type === 'blacklist') {
                    return ['Spam', 'Spam', null];
                }
                if ($rule->action === 'Trash' || $rule->action === 'delete') {
                    return ['Unwichtig', 'Unwichtig', null];
                }
            }
        }

        // Invoice / Receipt Airbag: Check if message has PDF attachment or explicit invoice keyword
        $hasPdfAttachment = false;
        if ($msg->relationLoaded('attachments')) {
            foreach ($msg->attachments as $att) {
                $fn = strtolower((string) $att->filename);
                if (str_ends_with($fn, '.pdf')) {
                    $hasPdfAttachment = true;
                    break;
                }
            }
        }
        $isInvoiceOrTax = $hasPdfAttachment
            || str_contains($subjLower, 'rechnung')
            || str_contains($subjLower, 'invoice')
            || str_contains($subjLower, 'beleg')
            || str_contains($subjLower, 'steuer')
            || str_contains($subjLower, 'gutschrift');

        $isRecent = !$msg->received_at || $msg->received_at->greaterThanOrEqualTo(now()->subDays(25));

        // 2. Server Backup Mails: Only the latest failure is Wichtig; older duplicates & routine successes are Unwichtig
        if (str_contains($subjLower, 'backup von mein-seelenfunke') || str_contains($subjLower, 'backup erfolgreich: mein-seelenfunke') || str_contains($subjLower, 'system-backup')) {
            if (str_contains($subjLower, 'konnte nicht') || str_contains($subjLower, 'fehler') || str_contains($subjLower, 'failed')) {
                if (!$seenBackupFailure) {
                    $seenBackupFailure = true;
                    return [
                        'Wichtig',
                        'System & Backup',
                        'Backup-Fehlermeldung auf Mein-Seelenfunke-Stage prüfen (Speicherplatz & Cronjob kontrollieren).'
                    ];
                }
            }
            return ['Unwichtig', 'System & Backup', null];
        }

        // 3. Auto-replies, marketing nudges, welcome emails, shipping pings -> Unwichtig (Trash)
        if (!$isInvoiceOrTax) {
            if (
                $subjLower === 'test' ||
                str_starts_with($subjLower, 'automatische antwort:') ||
                str_contains($from, 'db.no-reply@db.com') ||
                str_contains($subjLower, 'noch interesse?') ||
                str_contains($subjLower, 'willkommen bei') ||
                str_contains($from, 'no-reply@doctolib.de') ||
                str_contains($from, 'dhl.de') ||
                str_contains($from, 'myhermes.de') ||
                str_contains($from, 'tiktok.com') ||
                str_contains($from, 'lieferando.de') ||
                str_contains($from, 'google.com') ||
                str_contains($subjLower, 'paketankündigung') ||
                str_contains($subjLower, 'ihr paket') ||
                str_contains($subjLower, 'sendungsverfolgung') ||
                str_contains($subjLower, 'newsletter')
            ) {
                return ['Unwichtig', 'Unwichtig / Status-Info', null];
            }
        }

        // 4. Genuine Actionable WICHTIG Mails (Current open tasks requiring user action)
        // 4a. DEVK Wohngebäudeversicherung Rückfragen (WHG-31186083)
        if (str_contains($from, 'whg@check24.de') && str_contains($subjLower, 'rückfragen')) {
            return [
                'Wichtig',
                'Immobilien & Versicherung',
                'Auf E-Mail von whg@check24.de antworten: Kurz mitteilen, ob eine Wärmepumpe und/oder Warmwasser-Solaranlage im Objekt mitversichert werden soll.'
            ];
        }

        // 4b. FYRST Bank Support reply regarding credit card application
        if (str_contains($from, 'my.support@fyrst.de') && str_contains($subjLower, '3bsefcve')) {
            return [
                'Wichtig',
                'Bank & Finanzen',
                'Im FYRST-Onlinebanking den Status des Kreditkarten-Antrags (Postbank ID 3BSEFCVE) prüfen und Auftrag ggf. erneut einreichen.'
            ];
        }

        // 4c. Westpack Order Confirmation & Logo Cliche Proof
        if (str_contains($from, 'afz@westpack.com') && (str_contains($subjLower, 'auftragsbestätigung') || str_contains($subjLower, 'cliche'))) {
            return [
                'Wichtig',
                'Lieferant & Bestellung',
                'Auftragsbestätigung #29000069521 & Klischee-Druckfreigabe (C 7164015) prüfen sowie Versandtermin (10.11.2026) überwachen.'
            ];
        }

        // 4d. Domino's Reklamation
        if (str_contains($from, 'dominos.de') && str_contains($subjLower, 'reklamation')) {
            return [
                'Wichtig',
                'Reklamation',
                'Kurz auf die E-Mail von servicemanagement@dominos.de antworten und den angebotenen 10 € Kulanzgutschein bestätigen.'
            ];
        }

        // 4e. Agentur für Arbeit New Portal Notification
        if (str_contains($from, 'mitteilungsservice@arbeitsagentur.de')) {
            return [
                'Wichtig',
                'Behörde',
                'Neue Mitteilung / Bescheid im Online-Portal der Bundesagentur für Arbeit abrufen und prüfen.'
            ];
        }

        // 4f. Current Invoices requiring accounting entry (Mittwald RG6931743, Karton.eu A2416277, Glücksfieber RE2613436)
        if (
            $isRecent && (
                (str_contains($from, 'mittwald.de') && str_contains($subjLower, 'rg6931743')) ||
                (str_contains($from, 'karton.eu') && str_contains($subjLower, 'rechnung')) ||
                (str_contains($from, 'gluecksfieber.de') && str_contains($subjLower, 're2613436'))
            )
        ) {
            return [
                'Wichtig',
                'Rechnung & Buchhaltung',
                'Aktuelle Eingangsrechnung prüfen und Beleg in der Buchhaltung erfassen.'
            ];
        }

        // 4g. Generic Dynamic Important Triggers for recent incoming emails (Mahnungen, Rückfragen, Aktion erforderlich)
        if (
            $isRecent && (
                str_contains($subjLower, 'mahnung') ||
                str_contains($subjLower, 'zahlungserinnerung') ||
                str_contains($subjLower, 'aktion erforderlich')
            )
        ) {
            return [
                'Wichtig',
                'Handlungsbedarf',
                'E-Mail erfordert direkte Rückmeldung oder Prüfung: ' . Str::limit($subj, 70)
            ];
        }

        // 5. NORMAL -> Keep cleanly categorized & tagged in "Verarbeitet"
        if (str_contains($from, 'fyrst.de') || str_contains($from, 'qonto.com') || str_contains($from, 'paypal.de')) {
            return ['Normal', 'Bank & Finanzen', null];
        }
        if (str_contains($from, 'westpack.com') || str_contains($from, 'xtool.com') || str_contains($from, 'karton.eu') || str_contains($from, 'gluecksfieber.de')) {
            return ['Normal', $isInvoiceOrTax ? 'Rechnung & Lieferant' : 'Lieferant & Bestellung', null];
        }
        if (str_contains($from, 'mittwald.de')) {
            return ['Normal', $isInvoiceOrTax ? 'Rechnung & Hosting' : 'Hosting & Server', null];
        }
        if (str_contains($from, 'objego.de') || str_contains($from, 'smartmiete.de') || str_contains($from, 'steinhauer-invest.de') || str_contains($subjLower, 'wohngebäude') || str_contains($subjLower, 'garagenhof') || str_contains($subjLower, 'stellplatz')) {
            return ['Normal', $isInvoiceOrTax ? 'Rechnung & Immobilien' : 'Immobilien', null];
        }
        if (str_contains($from, 'kaelte-klink.de') || str_contains($subjLower, 'abluft')) {
            return ['Normal', 'Handwerk & Technik', null];
        }
        if (
            str_contains($from, 'lubos-kliniken.de') ||
            str_contains($from, 'frauenarztpraxis-bs.de') ||
            str_contains($from, 'queeres-netzwerk-gf.de') ||
            str_contains($from, 'uro-os.de')
        ) {
            return ['Normal', 'Gesundheit & Termine', null];
        }
        if (str_contains($from, 'arag.de') || str_contains($from, 'check24.de') || str_contains($subjLower, 'versicherung') || str_contains($subjLower, 'haftpflicht') || str_contains($subjLower, 'zahnzusatz')) {
            return ['Normal', 'Versicherung', null];
        }
        if (str_contains($from, 'hpsmart.com') || str_contains($subjLower, 'instant ink') || str_contains($from, 'smartsteuer.de') || $isInvoiceOrTax) {
            return ['Normal', 'Rechnung & Belege', null];
        }
        if (str_contains($from, 'amazon.de') || str_contains($from, 'amazon.com') || str_contains($from, 'ebay.') || str_contains($from, 'bamtoo.de') || str_contains($subjLower, '#ord-')) {
            return ['Normal', 'Bestellung', null];
        }
        if (str_contains($from, 'telekom.de')) {
            return ['Normal', 'Vertrag & Internet', null];
        }
        if (str_contains($from, 'arbeitsagentur.de') || str_contains($from, 'itzbund.de') || str_contains($from, 'stadt-gifhorn.de') || str_contains($subjLower, 'bundid')) {
            return ['Normal', 'Behörde & BundID', null];
        }
        if (str_contains($from, 'simple-fax.de')) {
            return ['Normal', 'Krankenkasse & Fax', null];
        }

        return ['Normal', 'Allgemein', null];
    }
}
