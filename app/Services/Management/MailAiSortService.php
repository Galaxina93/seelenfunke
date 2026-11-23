<?php

namespace App\Services\Management;

use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;
use App\Models\Management\Mail\MailMessage;
use App\Models\Management\Mail\MailRule;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Support\Blueprint\Contracts\HasSystemBlueprint;
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
        $dir = storage_path('app/leitung/mail');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return storage_path('app/leitung/mail/deleted_mail_ids.json');
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
     * Never deletes emails: moves any Trash ("Papierkorb") emails safely into Archive ("Archiv").
     */
    public function purgeExpiredTrashMessages(): int
    {
        $trashed = MailMessage::where('folder', 'Trash')->get();
        $archivedCount = 0;
        foreach ($trashed as $msg) {
            $msg->update([
                'folder' => 'Archive',
                'is_archived' => true,
            ]);
            $archivedCount++;
        }

        return $archivedCount;
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

        // Step 2: Load all accounts (zero email deletion policy - never delete messages)
        $allAccounts = MailAccount::all()->keyBy('id');

        // Step 3: Dissolve all legacy AI subfolders in MailFolder
        MailFolder::whereIn('name', self::LEGACY_AI_FOLDERS)->delete();

        // Step 3b: Intelligenter Rechnungs- & Beleg-Abgleich vor der Ordner-Sortierung:
        // Klassifiziert Anhänge, gleicht Rechnungen gegen bestehende Sonderausgaben & Fixkosten ab
        // und zieht fehlende Rechnungs-PDFs / E-Rechnungs-XMLs automatisch nach.
        $accountingFunnelStats = [
            'classified' => 0,
            'booked' => 0,
            'reconciled_attached' => 0,
            'already_booked' => 0,
            'needs_review' => 0,
        ];
        try {
            $accountingFunnelStats = app(MailAttachmentFunnelService::class)
                ->backfillMetadataAndAutoProcessNew(autoBookInvoices: true, maxInvoiceScans: 40);
        } catch (\Throwable) {
            // Sortierung läuft auch bei einzelnen defekten Anhängen weiter
        }

        // Step 4: Funnel all messages across all accounts
        $allMessages = MailMessage::with('attachments')->orderBy('received_at', 'desc')->get();
        $spamMessageIdsToRemember = [];
        $seenBackupFailure = false;

        $ownEmails = $allAccounts->pluck('email')
            ->map(fn ($e) => strtolower(trim((string) $e)))
            ->filter()
            ->values()
            ->all();

        foreach ($allMessages as $msg) {
            $from = strtolower(trim((string) $msg->from_email));
            $fromName = strtolower(trim((string) $msg->from_name));
            $subj = \App\Console\Commands\FetchMailsCommand::repairCorruptedSubjectIcons((string) $msg->subject);
            $subj = preg_replace('/\s+/u', ' ', trim($subj));
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

            $existingTags = $this->healMessageReceiptTags($msg);
            $isAlreadyResolved = !empty($existingTags['resolved']);

            [$importance, $category, $actionHint] = $this->classifyFunnelMessage(
                $msg,
                $from,
                $fromName,
                $subj,
                $subjLower,
                $bodyLower,
                $seenBackupFailure,
                $ownEmails
            );

            // 1. SPAM -> Create persistent Blacklist Rule (ONLY if sender cannot spoof a trusted/customer address!) + Move to Spam ('Junk') folder
            if ($importance === 'Spam') {
                if (self::canAutoBlacklistSpamSender($from)) {
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
                $msg->update([
                    'subject' => \App\Services\AI\ContextDossierBuilder::sanitizeExternalUntrustedText($subj, 255),
                    'folder' => 'Junk',
                    'is_archived' => false,
                    'category' => 'Spam / Phishing',
                    'priority' => 'low',
                    'ai_status' => 'processed',
                ]);
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
                $baseTags = array_merge($existingTags, [
                    'importance' => 'Wichtig',
                    'category' => $category ?: 'Wichtig',
                    'account_email' => $accEmail,
                    'action_hint' => $actionHint,
                    'resolved' => false,
                ]);
                $enrichedTags = \App\Services\AI\SfInternalOrchestrator::enrichImportantMailTags($msg, $baseTags);

                $msg->update([
                    'subject' => $subj,
                    'folder' => 'Processed',
                    'is_archived' => false,
                    'category' => $category ?: 'Wichtig',
                    'priority' => 'high',
                    'tags' => $enrichedTags,
                    'ai_status' => 'processed',
                ]);
                $importantCount++;
                continue;
            }

            // 3. NORMAL & UNWICHTIG -> Automatically categorize, tag, mark read & move into "Archiv" ('Archive')
            $prioCode = ($importance === 'Unwichtig') ? 'low' : 'normal';
            $cleanCategory = $category ?: ($importance === 'Unwichtig' ? 'Unwichtig / Status-Info' : 'Allgemein');

            $archivedTags = array_merge($existingTags, [
                'importance' => $importance,
                'category' => $cleanCategory,
                'account_email' => $accEmail,
                'action_hint' => null,
                'resolved' => $isAlreadyResolved || !empty($existingTags['ai_receipt_booked_id']),
            ]);

            $msg->update([
                'subject' => $subj,
                'folder' => 'Archive',
                'is_archived' => true,
                'is_read' => true,
                'category' => $cleanCategory,
                'priority' => $prioCode,
                'tags' => $archivedTags,
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
        $orderFile = storage_path('app/leitung/mail/funkenwerk_mail_order.json');
        if (!is_dir(dirname($orderFile))) {
            @mkdir(dirname($orderFile), 0775, true);
        }
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

        $bookedNew = (int) ($accountingFunnelStats['booked'] ?? 0);
        $reconciledExisting = (int) ($accountingFunnelStats['reconciled_attached'] ?? 0);
        $reviewNeeded = (int) ($accountingFunnelStats['needs_review'] ?? 0);

        $accountingLine = '';
        if ($bookedNew > 0 || $reconciledExisting > 0 || $reviewNeeded > 0) {
            $parts = [];
            if ($reconciledExisting > 0) {
                $parts[] = "{$reconciledExisting} Rechnung(en) auf bestehende Sonderausgaben nachgezogen";
            }
            if ($bookedNew > 0) {
                $parts[] = "{$bookedNew} neue Sonderausgabe(n) gebucht";
            }
            if ($reviewNeeded > 0) {
                $parts[] = "{$reviewNeeded} Beleg(e) zur Prüfung markiert";
            }
            $accountingLine = "• 🧾 Buchhaltungs-Abgleich: " . implode(', ', $parts) . "\n";
        }

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
            'accounting_funnel' => $accountingFunnelStats,
            'target_folder' => 'Processed',
            'message' => "KI-Trichter & Auto-Archivierung abgeschlossen!\n\n"
                . $accountingLine
                . "• 🔴 {$importantCount} Wichtige E-Mails mit offenem Handlungsbedarf in 'Verarbeitet'\n"
                . "• 📦 {$archivedTotal} Routine- & Info-Mails ({$normalCount} Normal, {$unimportantCount} Unwichtig) kategorisiert & sicher ins 'Archiv' verschoben\n"
                . "• 🛡️ {$spamDeletedCount} Spam-Mails aussortiert ({$rulesCount} Spam-Regeln aktiv).",
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
        bool &$seenBackupFailure = false,
        array $ownEmails = []
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

        // Invoice / Receipt Airbag: Check if message has PDF/XML invoice attachment or explicit invoice keyword
        $hasPdfAttachment = false;
        $hasInvoiceReviewNeeded = false;
        $hasInvoiceBookedAttachment = false;

        if (!$msg->relationLoaded('attachments')) {
            $msg->load('attachments');
        }
        foreach ($msg->attachments as $att) {
            $fn = strtolower((string) $att->filename);
            if (str_ends_with($fn, '.pdf') && !str_contains($fn, 'agb') && !str_contains($fn, 'widerruf') && !str_contains($fn, 'retour')) {
                $hasPdfAttachment = true;
            }
            if ($att->funnel_type === 'invoice') {
                if ($att->funnel_status === 'needs_review') {
                    $hasInvoiceReviewNeeded = true;
                } elseif (in_array($att->funnel_status, ['booked', 'already_booked'], true)) {
                    $hasInvoiceBookedAttachment = true;
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

        // 2a. Server Backup Mails: Only the latest failure is Wichtig; older duplicates & routine successes are Unwichtig
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

        // 2b. Interne Architektur-Blueprint-Meldungen: Dynamisch prüfen, ob der Verstoß im Code bereits behoben ist
        if (str_contains($subjLower, 'architektur-blueprint verstoss') || str_contains($subjLower, 'architektur-blueprint verstoß')) {
            $rawPlain = (string) ($msg->body_plain ?: strip_tags((string) $msg->body_html));
            if ($this->isBlueprintViolationAlreadyResolved($rawPlain)) {
                return ['Unwichtig', 'System & Architektur', null];
            }
            return [
                'Wichtig',
                'System & Architektur',
                'Architektur-Blueprint-Abweichung prüfen und fehlendes blueprint()-Typenschild im gemeldeten Model ergänzen.'
            ];
        }

        // 2c. Automatische Shop-Bestell- & Angebots-Systemkopien (zentral in /admin/orders & /admin/dashboard gesteuert -> Archiv)
        if (
            str_contains($subjLower, 'deine bestellung bei mein seelenfunke')
            || str_contains($subjLower, '[neue bestellung]')
            || str_contains($subjLower, '[angebot angenommen]')
            || (str_contains($from, 'mein-seelenfunke.de') && (str_contains($subjLower, 'wurde versendet') || str_contains($subjLower, 'neue anfrage:')))
        ) {
            return ['Normal', 'Bestellung', null];
        }

        // 3. Auto-replies, marketing nudges, welcome emails, shipping pings, review nudges -> Unwichtig (Archiv)
        if (!$isInvoiceOrTax) {
            if (
                $subjLower === 'test' ||
                str_starts_with($subjLower, 'automatische antwort:') ||
                str_contains($from, 'db.no-reply@db.com') ||
                str_contains($subjLower, 'noch interesse?') ||
                str_contains($subjLower, 'willkommen bei') ||
                str_contains($from, 'no-reply@doctolib.de') ||
                str_contains($from, 'dhl.de') ||
                str_contains($from, 'dpd.de') ||
                str_contains($from, 'myhermes.de') ||
                str_contains($from, 'tiktok.com') ||
                str_contains($from, 'lieferando.de') ||
                str_contains($from, 'google.com') ||
                str_contains($from, 'versandbestaetigung@amazon.') ||
                str_contains($from, 'shipment-tracking@amazon.') ||
                str_contains($from, 'order-update@amazon.') ||
                str_contains($from, 'judge.me') ||
                str_contains($subjLower, 'paketankündigung') ||
                str_contains($subjLower, 'ihr paket') ||
                str_contains($subjLower, 'sendungsverfolgung') ||
                str_contains($subjLower, 'wurde versandt') ||
                str_contains($subjLower, 'ist unterwegs') ||
                str_contains($subjLower, 'bitte bewerten sie ihren') ||
                str_contains($subjLower, 'wie war ihr erlebnis') ||
                str_contains($subjLower, 'waren sie zufrieden mit ihrem einkauf') ||
                str_contains($subjLower, 'newsletter')
            ) {
                return ['Unwichtig', 'Unwichtig / Status-Info', null];
            }
        }

        // 4. Genuine Actionable WICHTIG Mails (Current open tasks requiring user action)
        // 4.0 Offizielle Stripe-Systemmeldungen (@stripe.com: Webhook-Störung, HTTP 503/500, Deaktivierung, Dispute) -> 0-Token Autopilot + Ticket
        $isOfficialStripeSender = $from === 'stripe.com'
            || str_ends_with($from, '@stripe.com')
            || str_ends_with($from, '.stripe.com');
        if (
            $isRecent
            && $isOfficialStripeSender
            && (
                str_contains($subjLower, 'webhook')
                || str_contains($subjLower, 'übertragungsprobleme')
                || str_contains($bodyLower, 'stripe/webhook')
                || str_contains($subjLower, 'dispute')
                || str_contains($subjLower, 'konfliktfall')
                || str_contains($subjLower, 'rückbuchung')
                || str_contains($subjLower, 'chargeback')
            )
        ) {
            try {
                $autopilot = app(\App\Services\StripeAgentExecutionService::class)->runZeroTokenAlertMailAutopilot($msg);
                $apStatus = (string) ($autopilot['status'] ?? '');
                $apTicketStatus = (string) ($autopilot['ticket_status'] ?? '');
                if ($apStatus === 'auto_resolved' || ($apStatus === 'already_processed' && in_array($apTicketStatus, ['completed', 'archived'], true))) {
                    return ['Normal', 'Stripe & Zahlungsverkehr', null];
                }

                $ticketRef = !empty($autopilot['ticket_number']) ? (' (Ticket #' . $autopilot['ticket_number'] . ')') : '';
                return [
                    'Wichtig',
                    'Stripe & Zahlungsverkehr',
                    'Stripe-Meldung im Ticketsystem prüfen' . $ticketRef . ': Live-Webhook oder Konfliktfall erfordert deine Freigabe.'
                ];
            } catch (\Throwable) {
                if (str_contains($subjLower, 'sandbox') || str_contains($subjLower, 'testmodus') || str_contains($bodyLower, 'enthält keine echten daten')) {
                    return ['Normal', 'Stripe & Zahlungsverkehr', null];
                }
                return [
                    'Wichtig',
                    'Stripe & Zahlungsverkehr',
                    'Stripe-Systemmeldung prüfen: ' . Str::limit($subj, 80)
                ];
            }
        }

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

        // 4c. Westpack Order Confirmation & Logo Cliche Proof: Nur solange die zugehörige Westpack-Rechnung noch nicht gebucht/geliefert ist
        if (str_contains($from, 'afz@westpack.com') && (str_contains($subjLower, 'auftragsbestätigung') || str_contains($subjLower, 'cliche'))) {
            if ($this->isSupplierInvoiceAlreadyReconciled('29000069521 821293')) {
                return ['Normal', 'Lieferant & Bestellung', null];
            }
            return [
                'Wichtig',
                'Lieferant & Bestellung',
                'Auftragsbestätigung & Klischee-Druckfreigabe bei Westpack prüfen sowie Versandtermin überwachen.'
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

        // 4f. Unlesbare / prüfungsbedürftige Rechnungs-Anhänge niemals still im Archiv verstecken
        if ($isRecent && $hasInvoiceReviewNeeded) {
            return [
                'Wichtig',
                'Rechnung & Buchhaltung',
                'Rechnungsanhang konnte nicht eindeutig ausgelesen werden. Bitte mit den Sonderausgaben abgleichen und Beleg prüfen.'
            ];
        }

        // 4g. Dynamische Prüfung für Lieferanten-Rechnungen & Gutschriften ohne bereits gebuchten Anhang
        $existingMsgTags = is_array($msg->tags) ? $msg->tags : [];
        $hasVerifiedReceiptTag = !empty($existingMsgTags['ai_receipt_booked_id']) || $hasInvoiceBookedAttachment;

        if ($isRecent && !$hasVerifiedReceiptTag && $this->isUnreconciledCommercialSupplierInvoiceMail($from, $subjLower, $bodyLower, $hasPdfAttachment)) {
            return [
                'Wichtig',
                'Rechnung & Buchhaltung',
                'Eingangsrechnung / Gutschrift mit den Sonderausgaben abgleichen und fehlenden Beleg hinterlegen.'
            ];
        }

        // 4h. Generic Dynamic Important Triggers for recent incoming emails (Mahnungen, Rückfragen, Aktion erforderlich)
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

        // 4i. Echte externe Shop-Kundenanfragen (ORD-... Referenz oder registrierter Kunde)
        $isOwnSystemSender = str_ends_with($from, '@mein-seelenfunke.de')
            || in_array($from, $ownEmails, true)
            || str_starts_with($from, 'no-reply@')
            || str_starts_with($from, 'noreply@');
        if ($isRecent && !$isOwnSystemSender) {
            $mentionsOrderNumber = preg_match('/\bord-[a-z0-9\-]{4,30}\b/iu', $subj . ' ' . $bodyLower) === 1;
            $isKnownShopCustomer = $from !== ''
                && class_exists(\App\Models\Customer\Customer::class)
                && \App\Models\Customer\Customer::whereRaw('LOWER(email) = ?', [$from])->exists();

            if ($mentionsOrderNumber || ($isKnownShopCustomer && preg_match('/\b(?:bestellung|frage|reklamation|widerruf|gravur|lieferung|rechnung|paket)\b/iu', $subjLower . ' ' . $bodyLower) === 1)) {
                return [
                    'Wichtig',
                    'Kundenanfrage & Bestellung',
                    'Kundenanfrage prüfen und vorbereitete KI-Antwort absenden.'
                ];
            }
        }

        // 5. NORMAL -> Cleanly categorized & archived
        if (str_contains($from, 'fyrst.de') || str_contains($from, 'qonto.com') || str_contains($from, 'paypal.de')) {
            return ['Normal', 'Bank & Finanzen', null];
        }
        if (
            str_contains($from, 'westpack.com') ||
            str_contains($from, 'xtool.com') ||
            str_contains($from, 'karton.eu') ||
            str_contains($from, 'gluecksfieber.de') ||
            str_contains($from, 'natuerlich-verpacken.de') ||
            str_contains($from, 'verpacking.com') ||
            str_contains($from, 'schaumstoffonline.de') ||
            str_contains($from, 'bambulab.com')
        ) {
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
        if (str_contains($from, 'hpsmart.com') || str_contains($subjLower, 'instant ink') || str_contains($from, 'smartsteuer.de') || $isInvoiceOrTax || $hasInvoiceBookedAttachment) {
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

    /**
     * Stellt sicher, dass tags['ai_receipt_booked_id'] auf eine tatsächlich existierende Sonderausgabe oder Fixkostenstelle zeigt
     * bzw. aus einem erfolgreich verknüpften Rechnungsanhang geheilt wird.
     */
    private function healMessageReceiptTags(MailMessage $msg): array
    {
        $tags = is_array($msg->tags) ? $msg->tags : [];
        $bookedId = !empty($tags['ai_receipt_booked_id']) ? (string) $tags['ai_receipt_booked_id'] : '';

        $isValid = false;
        if ($bookedId !== '') {
            if (str_starts_with($bookedId, 'fixcost:')) {
                $fcId = substr($bookedId, 8);
                $isValid = $fcId !== '' && AccountingCostItem::where('id', $fcId)->exists();
            } else {
                $isValid = AccountingSpecialIssue::where('id', $bookedId)->exists();
            }
        }

        if (!$isValid) {
            unset($tags['ai_receipt_booked_id']);
            if ($msg->relationLoaded('attachments')) {
                foreach ($msg->attachments as $att) {
                    if ($att->funnel_type === 'invoice' && in_array($att->funnel_status, ['booked', 'already_booked'], true)) {
                        if (!empty($att->accounting_issue_id) && AccountingSpecialIssue::where('id', $att->accounting_issue_id)->exists()) {
                            $tags['ai_receipt_booked_id'] = (string) $att->accounting_issue_id;
                            break;
                        }
                        $meta = is_array($att->funnel_meta) ? $att->funnel_meta : [];
                        if (!empty($meta['fix_cost_id'])) {
                            $tags['ai_receipt_booked_id'] = 'fixcost:' . $meta['fix_cost_id'];
                            break;
                        }
                    }
                }
            }
        }

        return $tags;
    }

    private function isBlueprintViolationAlreadyResolved(string $rawBody): bool
    {
        if (!preg_match_all('/Model\s*\[([A-Za-z0-9_\\\\]+)\]/u', $rawBody, $matches) || empty($matches[1])) {
            return false;
        }

        foreach ($matches[1] as $className) {
            $cls = ltrim(trim($className), '\\');
            if (!class_exists($cls)) {
                return false;
            }
            if (!is_subclass_of($cls, HasSystemBlueprint::class) || !method_exists($cls, 'blueprint')) {
                return false;
            }
        }

        return true;
    }

    private function isShopOrderInternalOrFulfilled(string $text, array $ownEmails): bool
    {
        if (!preg_match('/\b(ORD-[A-Z0-9\-]{4,30})\b/iu', $text, $m)) {
            return false;
        }
        $orderNo = strtoupper(trim($m[1]));
        $order = OrderOrder::where('order_number', $orderNo)->first();
        if (!$order) {
            return false;
        }

        if (in_array((string) $order->status, ['shipped', 'completed', 'cancelled', 'refunded'], true)) {
            return true;
        }

        $custEmail = strtolower(trim((string) ($order->email ?? '')));
        $internalTestEmails = array_unique(array_merge($ownEmails, [
            'kontakt@mein-seelenfunke.de',
            'alina.stone@t-online.de',
            'kontakt@steinhauer-invest.de',
            'test@mein-seelenfunke.de',
            'sarcastoman@gmail.com',
        ]));

        return $custEmail !== '' && in_array($custEmail, $internalTestEmails, true);
    }

    private function isSupplierInvoiceAlreadyReconciled(string $searchContext): bool
    {
        if (!preg_match_all('/\b(\d{3}-\d{7}-\d{7}|29000\d{5,}|821293|RE\d{6,}|RG\d{5,}|A\d{6,}|B\d{6,}|\d{8,}-NV|42026\d{5,}|VPJ\d{4,}|SO\d{4,}|EN\d{12,})\b/iu', $searchContext, $m)) {
            return false;
        }

        foreach (array_unique($m[1]) as $token) {
            $tok = trim($token);
            if (mb_strlen($tok) < 5) {
                continue;
            }
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $tok) . '%';
            $issue = AccountingSpecialIssue::query()
                ->where(function ($q) use ($like) {
                    $q->where('invoice_number', 'like', $like)
                        ->orWhere('note', 'like', $like);
                })
                ->first();

            if ($issue) {
                // Für Westpack 29000069521 gilt erst die echte Rechnung (821293 / Rechnung-) als vollständiger Abschluss
                if ($tok === '29000069521') {
                    $filesJson = strtolower(json_encode($issue->file_paths ?? []) ?: '');
                    if (!str_contains((string) $issue->invoice_number, '821293') && !str_contains($filesJson, 'rechnung')) {
                        continue;
                    }
                }
                return true;
            }

            if (str_starts_with(strtoupper($tok), 'RG')) {
                $hasMittwaldFix = AccountingCostItem::where('provider_company', 'like', '%Mittwald%')->exists();
                if ($hasMittwaldFix) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isUnreconciledCommercialSupplierInvoiceMail(
        string $from,
        string $subjLower,
        string $bodyLower,
        bool $hasPdfAttachment
    ): bool {
        $isKnownSupplier = str_contains($from, 'westpack.com')
            || str_contains($from, 'gluecksfieber.de')
            || str_contains($from, 'printequipment.')
            || str_contains($from, 'karton.eu')
            || str_contains($from, 'kartonfritze.de')
            || str_contains($from, 'natuerlich-verpacken.de')
            || str_contains($from, 'verpacking.com')
            || str_contains($from, 'rapidpack.')
            || str_contains($from, 'schaumstoffonline.de')
            || str_contains($from, 'bambulab.com')
            || str_contains($from, 'xtool.com')
            || str_contains($from, 'mittwald.de')
            || (str_contains($from, 'amazon.de') && (str_contains($subjLower, 'rechnung von') || str_contains($subjLower, 'gutschriftbestätigung')));

        if (!$isKnownSupplier) {
            return false;
        }

        $isInvoiceSubject = str_contains($subjLower, 'rechnung')
            || str_contains($subjLower, 'invoice')
            || str_contains($subjLower, 'gutschrift');

        if (!$isInvoiceSubject && !$hasPdfAttachment) {
            return false;
        }

        if ($this->isSupplierInvoiceAlreadyReconciled($subjLower . ' ' . mb_substr($bodyLower, 0, 2000))) {
            return false;
        }

        return true;
    }

    /**
     * Verhindert Spoofed-From Auto-Blacklist Poisoning (Denial-of-Service gegen echte Lieferanten,
     * Banken, Behörden, eigene Domains oder bestehende Kunden/Kontakte).
     */
    public static function canAutoBlacklistSpamSender(string $rawEmail): bool
    {
        $email = strtolower(trim($rawEmail));
        if ($email === '' || strlen($email) > 190 || preg_match('/[\r\n\0\*\?]/', $email)) {
            return false;
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return false;
        }
        $domain = substr($email, $atPos + 1);

        $protectedDomains = [
            'mein-seelenfunke.de', 'seelenfunke.de', 'somfy.de', 'somfy.com', 'overkiz.com',
            'paypal.de', 'paypal.com', 'stripe.com', 'dhl.de', 'dhl.com', 'deutschepost.de',
            't-online.de', 'telekom.de', 'westpack.com', 'printequipment.de', 'gluecksfieber.de',
            'karton.eu', 'kartonfritze.de', 'xtool.com', 'fyrst.de', 'postbank.de', 'db.com',
            'elster.de', 'arag.de', 'bkk-firmus.de', 'arbeitsagentur.de', 'bund.de', 'itzbund.de',
            'stadt-gifhorn.de', 'infomaniak.com', 'ik.me', 'mittwald.de', 'amazon.de', 'amazon.com',
            'google.com', 'gmail.com', 'apple.com', 'icloud.com', 'microsoft.com', 'outlook.com',
            'gmx.de', 'gmx.net', 'web.de',
        ];

        foreach ($protectedDomains as $prot) {
            if ($domain === $prot || str_ends_with($domain, '.' . $prot)) {
                return false;
            }
        }

        try {
            if (\App\Models\Customer\Customer::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return false;
            }
            if (\App\Models\Management\ManagementContact::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return false;
            }
            if (\App\Models\Admin\Admin::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return false;
            }
            if (\App\Models\Employee\Employee::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return false;
            }
        } catch (\Throwable) {
        }

        return true;
    }
}
