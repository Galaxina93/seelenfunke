<?php

namespace App\Services\Management;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Ai\AiWorkspaceDocument;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailAttachment;
use App\Models\Management\Mail\MailMessage;
use App\Services\AccountingEInvoiceParserService;
use App\Services\AI\BuchiReceiptPipelineService;
use App\Services\AI\Workspace\DateitrichterService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;

class MailAttachmentFunnelService
{
    private const BUSINESS_PARTNER_KEYWORDS = [
        'mein-seelenfunke',
        'mein seelenfunke',
        'westpack',
        'glücksfieber',
        'gluecksfieber',
        'printequipment',
        'print equipment',
        'karton.eu',
        'kartonfritze',
        'frau wundervoll',
        'frauwundervoll',
        'natürlich verpacken',
        'natuerlich-verpacken',
        'parterremedia',
        'rapidpack',
        'verpacking',
        'schaumstoffonline',
        'bambulab',
        'bambu lab',
        'munbyn',
        'xtool',
        'makeblock',
        'mittwald',
        'google cloud',
        'hiscox',
        'interzero',
        'stripe',
        'fyrst',
        'smartsteuer',
        'lucid',
        'lizenzero',
    ];

    private const BLOCKED_EXTENSIONS = [
        'exe', 'dll', 'bat', 'cmd', 'ps1', 'psm1', 'vbs', 'vbe', 'js', 'jse',
        'wsf', 'wsh', 'msi', 'msp', 'com', 'scr', 'hta', 'cpl', 'jar', 'reg',
        'lnk', 'pif', 'url', 'scf', 'php', 'phtml', 'phar', 'sh', 'py', 'docm', 'xlsm', 'pptm',
    ];

    /**
     * Wird direkt beim E-Mail-Abruf (FetchMailsCommand) für jeden neuen Anhang ausgeführt.
     */
    public function handleIncomingAttachment(
        MailAttachment $attachment,
        ?MailMessage $message = null,
        ?MailAccount $account = null
    ): array {
        $message ??= $attachment->message;
        $account ??= $message ? MailAccount::find($message->mail_account_id) : null;

        if ($message && $message->folder === 'Junk') {
            $attachment->update([
                'funnel_type' => 'ignored_spam',
                'funnel_status' => 'ignored',
            ]);
            return ['status' => 'ignored', 'reason' => 'Spam-Ordner'];
        }

        $classification = $this->classifyAttachment($attachment, $message, $account);
        $fullPath = $this->resolveSafeLocalAttachmentPath($attachment);
        $sha256 = ($fullPath && is_file($fullPath)) ? (@hash_file('sha256', $fullPath) ?: null) : null;

        $attachment->update([
            'funnel_type' => $classification['funnel_type'],
            'funnel_status' => $classification['initial_status'],
            'sha256_hash' => $sha256,
            'funnel_meta' => array_filter([
                'reason' => $classification['reason'] ?? null,
                'classified_at' => now()->toIso8601String(),
            ]),
        ]);

        // Rechnungen automatisch dublettensicher in die Buchhaltung verarbeiten
        if ($classification['funnel_type'] === 'invoice' && $classification['initial_status'] === 'open') {
            return $this->processInvoiceToAccounting($attachment, isManualAction: false);
        }

        return [
            'status' => $classification['initial_status'],
            'funnel_type' => $classification['funnel_type'],
        ];
    }

    /**
     * Klassifiziert einen E-Mail-Anhang sauber in:
     * - inline_signature (Mini-Logos / Signatur-Icons -> ignored)
     * - legal_notice (AGB / Widerruf / Datenschutz -> ignored)
     * - invoice (Eingangsrechnung / Quittung / Gutschrift / E-Rechnung)
     * - document / image / archive
     *
     * @return array{funnel_type: string, initial_status: string, reason: ?string}
     */
    public function classifyAttachment(
        MailAttachment $attachment,
        ?MailMessage $message = null,
        ?MailAccount $account = null
    ): array {
        $message ??= $attachment->message;
        $rawName = trim((string) ($attachment->filename ?: basename((string) $attachment->path)));
        $lowerName = mb_strtolower($rawName);
        $ext = strtolower((string) pathinfo($rawName, PATHINFO_EXTENSION));
        $size = (int) ($attachment->size ?? 0);
        $hasContentId = !empty($attachment->content_id);

        $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'bmp', 'heic', 'heif'];
        $archiveExts = ['zip', 'rar', '7z', 'tar', 'gz'];

        // 1. Winzige Inline-Signatur-Bilder / Tracking-Icons ausfiltern
        if (in_array($ext, $imageExts, true)) {
            if ($hasContentId && $size > 0 && $size < 20480) {
                return [
                    'funnel_type' => 'inline_signature',
                    'initial_status' => 'ignored',
                    'reason' => 'Inline-Signaturgrafik',
                ];
            }
            if (
                $size > 0
                && $size < 14336
                && preg_match('/^(image00\d+|logo|icon|banner|footer|spacer|pixel|sig|facebook|instagram|linkedin|twitter|youtube|xing)/iu', $lowerName)
            ) {
                return [
                    'funnel_type' => 'inline_signature',
                    'initial_status' => 'ignored',
                    'reason' => 'Signatur-Icon',
                ];
            }
        }

        $hasInvoiceWordInFilename = (bool) preg_match(
            '/(rechnung|invoice|quittung|beleg|kassenbon|gutschrift|abrechnung|steuerbescheid|beitragsrechnung|zahlungsbeleg|receipt|e-rechnung|xrechnung|zugferd|\brg[\s_\-]*\d+|\binv[\s_\-]*\d+|\bre[\s_\-]*\d{4,})/iu',
            $lowerName
        );

        // 2. AGB / Widerruf / Retouren- & Gewährleistungs-Beilagen niemals als Rechnung oder Dateitrichter-Ballast werten
        if (
            !$hasInvoiceWordInFilename
            && preg_match('/(agb|allgemeine[\s_\-]*geschaeftsbedingungen|allgemeine[\s_\-]*geschäftsbedingungen|widerruf|widerrufsbelehrung|widerrufsformular|muster-widerruf|retourformular|retourenformular|retourenschein|ruecksendeformular|rücksendeformular|gewaehrleistung|gewährleistung|garantiebedingungen|datenschutz|privacy[\s_\-]*policy|terms[\s_\-]*and[\s_\-]*conditions|terms[\s_\-]*of[\s_\-]*service|impressum)/iu', $lowerName)
        ) {
            return [
                'funnel_type' => 'legal_notice',
                'initial_status' => 'ignored',
                'reason' => 'Rechtstext / AGB / Retourenbeilage',
            ];
        }

        // 3. Eigene Ausgangs-Mails / Entwürfe nicht als Eingangsrechnung verbuchen
        $subjLower = mb_strtolower((string) ($message?->subject ?? ''));
        $isForwarded = (bool) preg_match('/^(wg:|fwd:|weiterleitung:)/iu', trim($subjLower));
        $fromEmail = strtolower(trim((string) ($message?->from_email ?? '')));
        $ownEmails = MailAccount::pluck('email')->map(fn ($e) => strtolower(trim((string) $e)))->all();

        $isOwnOutgoing = ($message && in_array($message->folder, ['Sent', 'Drafts'], true))
            || ($fromEmail !== '' && in_array($fromEmail, $ownEmails, true) && !$isForwarded);

        if ($isOwnOutgoing) {
            $baseType = in_array($ext, $imageExts, true) ? 'image' : (in_array($ext, $archiveExts, true) ? 'archive' : 'document');
            return [
                'funnel_type' => $baseType,
                'initial_status' => 'open',
                'reason' => 'Gesendete Nachricht',
            ];
        }

        // 4. Eingangsrechnungs- & E-Rechnungs-Erkennung
        $receiptExts = ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $receiptExts, true)) {
            $isShippingLabelOnly = !$hasInvoiceWordInFilename && (bool) preg_match(
                '/(lieferschein|versandetikett|versandmarke|retourenlabel|retourenschein|retourformular|paketschein|dhl[\s_\-]*label|anleitung|handbuch|manual)/iu',
                $lowerName
            );

            if (!$isShippingLabelOnly) {
                $subjectHasInvoiceSignal = (bool) preg_match(
                    '/(rechnung|invoice|zahlungsbeleg|quittung|gutschrift|kaufbeleg|steuerbescheid|abrechnung|beitragsrechnung|e-rechnung)/iu',
                    $subjLower
                );

                $isXmlEInvoice = false;
                if ($ext === 'xml') {
                    $fullPath = $this->resolveSafeLocalAttachmentPath($attachment);
                    if ($fullPath && is_file($fullPath) && filesize($fullPath) <= 5 * 1024 * 1024) {
                        $head = (string) @file_get_contents($fullPath, false, null, 0, 4096);
                        $isXmlEInvoice = str_contains($head, 'CrossIndustryInvoice')
                            || str_contains($head, '<Invoice')
                            || str_contains($head, ':Invoice')
                            || str_contains($head, 'CreditNote');
                    }
                }

                if ($hasInvoiceWordInFilename || ($subjectHasInvoiceSignal && in_array($ext, ['pdf', 'xml'], true)) || $isXmlEInvoice) {
                    return [
                        'funnel_type' => 'invoice',
                        'initial_status' => 'open',
                        'reason' => null,
                    ];
                }
            }
        }

        if (in_array($ext, $imageExts, true)) {
            return ['funnel_type' => 'image', 'initial_status' => 'open', 'reason' => null];
        }
        if (in_array($ext, $archiveExts, true)) {
            return ['funnel_type' => 'archive', 'initial_status' => 'open', 'reason' => null];
        }

        return ['funnel_type' => 'document', 'initial_status' => 'open', 'reason' => null];
    }

    /**
     * Verarbeitet einen Rechnungs-/Beleg-Anhang dublettensicher in die Buchhaltung (AccountingSpecialIssue)
     * und zieht fehlende Rechnungsdateien / Rechnungsnummern auf bereits angelegten Sonderausgaben automatisch nach.
     */
    public function processInvoiceToAccounting(MailAttachment $attachment, bool $isManualAction = false): array
    {
        $fullPath = $this->resolveSafeLocalAttachmentPath($attachment);
        if (!$fullPath || !is_file($fullPath)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Anhang-Datei nicht auf dem Speicher gefunden.'];
        }

        $ext = strtolower((string) pathinfo((string) ($attachment->filename ?: $fullPath), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'xml', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Dateiformat wird für die Beleg-Verarbeitung nicht unterstützt.'];
        }

        $message = $attachment->message;
        $account = $message ? MailAccount::find($message->mail_account_id) : null;
        $adminId = Auth::guard('admin')->id() ?? Admin::value('id');
        $sha256 = @hash_file('sha256', $fullPath) ?: null;

        // Vorab-Check über SHA-256 auf bereits gebuchte Belegdateien (spart KI-Tokens bei Weiterleitungen/Dubletten)
        $preDup = BuchiReceiptPipelineService::checkDuplicate(
            adminId: $adminId,
            title: '',
            merchant: '',
            date: '',
            amountCents: 0,
            invoiceNumber: '',
            file: $fullPath
        );

        if (!empty($preDup['is_duplicate'])) {
            $attachment->update([
                'funnel_type' => 'invoice',
                'funnel_status' => 'already_booked',
                'accounting_issue_id' => $preDup['existing_id'],
                'sha256_hash' => $sha256,
                'funnel_meta' => [
                    'title' => $preDup['existing_title'],
                    'date' => $preDup['existing_date'],
                    'note' => $preDup['reason'],
                    'processed_at' => now()->toIso8601String(),
                ],
            ]);

            if ($message && !empty($preDup['existing_id'])) {
                $this->stampMessageBookedTag($message, (string) $preDup['existing_id']);
            }

            return [
                'success' => true,
                'status' => 'already_booked',
                'message' => (string) ($preDup['reason'] ?? 'Beleg ist bereits in der Buchhaltung erfasst.'),
                'accounting_issue_id' => $preDup['existing_id'],
            ];
        }

        $categories = $adminId
            ? AccountingCategory::where('admin_id', $adminId)->orderByDesc('usage_count')->pluck('name')->all()
            : [];

        $rawPdfText = $ext === 'pdf' ? $this->extractPdfTextSafe($fullPath) : '';
        $analysis = BuchiReceiptPipelineService::analyzeAndVerify($fullPath, $categories, $adminId);

        if ((!$analysis || empty($analysis['amount_abs'])) && in_array($ext, ['pdf', 'xml'], true)) {
            $fallbackRaw = $this->tryExtractDeterministicPdfInvoice($fullPath, $attachment, $message, $rawPdfText);
            if ($fallbackRaw !== null) {
                $enriched = BuchiReceiptPipelineService::enrichScanResult($fallbackRaw, $fullPath);
                $enriched['duplicate_check'] = BuchiReceiptPipelineService::checkDuplicate(
                    adminId: $adminId,
                    title: (string) $enriched['title'],
                    merchant: (string) ($enriched['merchant'] ?? $enriched['location'] ?? ''),
                    date: (string) $enriched['date'],
                    amountCents: (int) $enriched['amount_cents'],
                    invoiceNumber: (string) ($enriched['invoice_number'] ?? ''),
                    file: $fullPath
                );
                $analysis = $enriched;
            }
        }

        // Sicherheitsnetz: Kein Blindbuchen von 0,00 € oder unlesbaren Dokumenten -> "Prüfung nötig"
        if (!$analysis || empty($analysis['amount_abs']) || (float) $analysis['amount_abs'] <= 0.0) {
            $attachment->update([
                'funnel_type' => 'invoice',
                'funnel_status' => 'needs_review',
                'sha256_hash' => $sha256,
                'funnel_meta' => [
                    'reason' => 'Betrag oder Rechnungsdaten konnten nicht eindeutig ausgelesen werden.',
                    'checked_at' => now()->toIso8601String(),
                ],
            ]);

            return [
                'success' => false,
                'status' => 'needs_review',
                'message' => 'Beleg konnte nicht eindeutig ausgelesen werden und wurde als Prüfung nötig markiert.',
            ];
        }

        // Gewerblich vs. Privat sauber anhand Postfach + Seelenfunke-Kontext entscheiden
        $isBusiness = $this->resolveBusinessFlag($analysis, $attachment, $message, $account);
        $taxRate = $isBusiness ? (int) ($analysis['tax_rate'] ?? 19) : null;
        $extractedInvoiceNumber = trim((string) ($analysis['invoice_number'] ?? ''));
        $invoiceNumber = $isBusiness ? ($extractedInvoiceNumber !== '' ? $extractedInvoiceNumber : null) : null;

        // Vollständiger 3-Stufen-Dublettenschutz + intelligenter Abgleich gegen bestehende Sonderausgaben (z. B. Vorab-Bestellung ohne PDF)
        $dupCheck = $analysis['duplicate_check'] ?? BuchiReceiptPipelineService::checkDuplicate(
            adminId: $adminId,
            title: (string) $analysis['title'],
            merchant: (string) ($analysis['merchant'] ?? $analysis['location'] ?? ''),
            date: (string) $analysis['date'],
            amountCents: (int) $analysis['amount_cents'],
            invoiceNumber: $extractedInvoiceNumber,
            file: $fullPath
        );

        $matchedExistingIssue = $this->findMatchingSpecialIssueForReconciliation(
            adminId: $adminId,
            analysis: $analysis,
            invoiceNumber: $extractedInvoiceNumber,
            attachment: $attachment,
            message: $message,
            rawPdfText: $rawPdfText,
            dupCheck: is_array($dupCheck) ? $dupCheck : []
        );

        if ($matchedExistingIssue) {
            return $this->backfillInvoiceOntoExistingIssue(
                attachment: $attachment,
                existingIssue: $matchedExistingIssue,
                fullPath: $fullPath,
                sha256: $sha256,
                analysis: $analysis,
                invoiceNumber: $extractedInvoiceNumber,
                isBusiness: $isBusiness,
                message: $message
            );
        }

        // Abgleich gegen laufende Fixkosten / Verträge (z. B. Mittwald Hosting, Google AI Pro), damit keine doppelte Sonderausgabe entsteht
        $fixCostMatch = $this->reconcileWithFixCostItem(
            adminId: $adminId,
            attachment: $attachment,
            fullPath: $fullPath,
            sha256: $sha256,
            analysis: $analysis,
            invoiceNumber: $extractedInvoiceNumber,
            message: $message
        );
        if ($fixCostMatch !== null) {
            return $fixCostMatch;
        }

        $storedReceiptPath = BuchiReceiptPipelineService::storeReceiptFile(
            file: $fullPath,
            date: (string) $analysis['date'],
            merchant: (string) ($analysis['merchant'] ?: $analysis['location']),
            title: (string) $analysis['title'],
            invoiceNumber: (string) ($invoiceNumber ?? '')
        );

        $issue = AccountingSpecialIssue::create([
            'admin_id' => $adminId,
            'title' => str_replace([' – ', ' — '], ' - ', (string) $analysis['title']),
            'location' => $analysis['location'] ?: $analysis['merchant'],
            'category' => $analysis['category'] ?: 'Sonstiges',
            'amount' => $analysis['amount'],
            'execution_date' => $analysis['date'],
            'is_business' => $isBusiness,
            'tax_rate' => $taxRate,
            'invoice_number' => $invoiceNumber,
            'file_paths' => $storedReceiptPath ? [$storedReceiptPath] : [],
            'note' => BuchiReceiptPipelineService::withLiveMarker(
                $isManualAction ? 'Mail-Datei Buchung' : 'Auto-Buchung aus E-Mail-Eingang'
            ),
        ]);

        BuchiReceiptPipelineService::trackCategoryUsage($adminId, (string) ($analysis['category'] ?: 'Sonstiges'), $isBusiness);

        $attachment->update([
            'funnel_type' => 'invoice',
            'funnel_status' => 'booked',
            'accounting_issue_id' => (string) $issue->id,
            'sha256_hash' => $sha256,
            'funnel_meta' => [
                'title' => $issue->title,
                'merchant' => $analysis['merchant'] ?: $analysis['location'],
                'amount' => (float) $issue->amount,
                'amount_formatted' => number_format(abs((float) $issue->amount), 2, ',', '.') . ' €',
                'date' => (string) $analysis['date'],
                'invoice_number' => $invoiceNumber,
                'is_business' => $isBusiness,
                'receipt_path' => $storedReceiptPath,
                'processed_at' => now()->toIso8601String(),
            ],
        ]);

        if ($message) {
            $this->stampMessageBookedTag($message, (string) $issue->id);
        }

        $formattedEuro = number_format(abs((float) $issue->amount), 2, ',', '.');
        $scopeLabel = $isBusiness ? 'Gewerblich' : 'Privat';

        return [
            'success' => true,
            'status' => 'booked',
            'accounting_issue_id' => (string) $issue->id,
            'message' => "Rechnung '{$issue->title}' ({$formattedEuro} €, {$scopeLabel}) erfolgreich in der Buchhaltung erfasst.",
        ];
    }

    /**
     * Übergibt eine E-Mail-Datei gezielt an den Dateitrichter (Berufsleben / Dokumente / Gesundheit).
     */
    public function sendToDateitrichter(MailAttachment $attachment): array
    {
        $fullPath = $this->resolveSafeLocalAttachmentPath($attachment);
        if (!$fullPath || !is_file($fullPath)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Anhang-Datei nicht auf dem Speicher gefunden.'];
        }

        $rawName = str_replace(["\xE2\x80\xAE", "\xE2\x80\xAD", "\xE2\x80\xAA", "\xE2\x80\xAB", "\xE2\x80\xAC"], '', (string) ($attachment->filename ?: basename($fullPath)));
        $ext = strtolower((string) pathinfo($rawName, PATHINFO_EXTENSION));
        if ($ext === '' || in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            return ['success' => false, 'status' => 'error', 'message' => 'Dieser Dateityp ist für den Dateitrichter gesperrt.'];
        }

        $sha256 = @hash_file('sha256', $fullPath) ?: null;
        if ($sha256) {
            $existingDoc = AiWorkspaceDocument::where('sha256', $sha256)->first();
            if ($existingDoc) {
                $attachment->update([
                    'funnel_status' => 'workspace',
                    'workspace_document_id' => $existingDoc->id,
                    'sha256_hash' => $sha256,
                    'funnel_meta' => array_merge(is_array($attachment->funnel_meta) ? $attachment->funnel_meta : [], [
                        'workspace_path' => $existingDoc->file_path,
                        'processed_at' => now()->toIso8601String(),
                    ]),
                ]);

                return [
                    'success' => true,
                    'status' => 'workspace',
                    'workspace_document_id' => $existingDoc->id,
                    'message' => "Datei liegt bereits im Dateitrichter ({$existingDoc->file_path}).",
                ];
            }
        }

        $cleanBase = preg_replace('/[^a-zA-Z0-9_\-\.äöüÄÖÜß ]/u', '_', (string) pathinfo($rawName, PATHINFO_FILENAME)) ?? 'Dokument';
        $cleanBase = trim(preg_replace('/\s+/', ' ', $cleanBase) ?? 'Dokument') ?: 'Dokument';
        $datePrefix = $attachment->message?->received_at
            ? Carbon::parse($attachment->message->received_at)->format('Y-m-d')
            : date('Y-m-d');

        $workspace = Storage::disk('workspace');
        if (!$workspace->exists('agenten/workspace')) {
            $workspace->makeDirectory('agenten/workspace');
        }

        $inboxRelPath = 'agenten/workspace/' . $datePrefix . '_' . $cleanBase . '.' . $ext;
        if ($workspace->exists($inboxRelPath)) {
            $inboxRelPath = 'agenten/workspace/' . $datePrefix . '_' . uniqid() . '_' . $cleanBase . '.' . $ext;
        }

        $rawBytes = @file_get_contents($fullPath);
        if ($rawBytes === false) {
            return ['success' => false, 'status' => 'error', 'message' => 'Datei konnte nicht gelesen werden.'];
        }

        $workspace->put($inboxRelPath, $rawBytes);

        $result = app(DateitrichterService::class)->processSingleFile($inboxRelPath);
        if (!$result) {
            $workspace->delete($inboxRelPath);
            return ['success' => false, 'status' => 'error', 'message' => 'Dateitrichter konnte die Datei nicht verarbeiten.'];
        }

        $targetPath = (string) ($result['target_path'] ?? $result['file_path'] ?? $inboxRelPath);
        $doc = AiWorkspaceDocument::where('file_path', $targetPath)->latest('id')->first();

        $attachment->update([
            'funnel_status' => 'workspace',
            'workspace_document_id' => $doc?->id,
            'sha256_hash' => $sha256,
            'funnel_meta' => array_merge(is_array($attachment->funnel_meta) ? $attachment->funnel_meta : [], [
                'workspace_path' => $targetPath,
                'main_folder' => $result['main_folder'] ?? null,
                'sub_folder' => $result['sub_folder'] ?? null,
                'processed_at' => now()->toIso8601String(),
            ]),
        ]);

        $folderLabel = trim(($result['main_folder'] ?? 'Dokumente') . ' / ' . ($result['sub_folder'] ?? 'Allgemein'), ' /');

        return [
            'success' => true,
            'status' => 'workspace',
            'workspace_document_id' => $doc?->id,
            'message' => "Datei im Dateitrichter unter '{$folderLabel}' einsortiert.",
        ];
    }

    /**
     * Klassifiziert bestehende Anhänge ohne Status nach (schneller Metadaten-Lauf)
     * und verarbeitet auf Wunsch offene Eingangsrechnungen.
     */
    public function backfillMetadataAndAutoProcessNew(bool $autoBookInvoices = false, int $maxInvoiceScans = 12): array
    {
        $accounts = MailAccount::all()->keyBy('id');
        $unclassified = MailAttachment::with('message')
            ->whereNull('funnel_type')
            ->orderByDesc('id')
            ->limit(400)
            ->get();

        $classifiedCount = 0;
        foreach ($unclassified as $att) {
            $msg = $att->message;
            if (!$msg || $msg->folder === 'Junk') {
                $att->update(['funnel_type' => 'ignored_spam', 'funnel_status' => 'ignored']);
                continue;
            }

            $acc = $accounts->get($msg->mail_account_id);
            $cls = $this->classifyAttachment($att, $msg, $acc);

            $tags = is_array($msg->tags) ? $msg->tags : [];
            $existingBookedId = !empty($tags['ai_receipt_booked_id']) ? (string) $tags['ai_receipt_booked_id'] : null;

            $status = $cls['initial_status'];
            $accountingId = null;
            if ($cls['funnel_type'] === 'invoice' && $existingBookedId) {
                $status = 'booked';
                $accountingId = $existingBookedId;
            }

            $att->update([
                'funnel_type' => $cls['funnel_type'],
                'funnel_status' => $status,
                'accounting_issue_id' => $accountingId,
                'funnel_meta' => array_filter(['reason' => $cls['reason']]),
            ]);
            $classifiedCount++;
        }

        $bookedCount = 0;
        $alreadyCount = 0;
        $reviewCount = 0;

        if ($autoBookInvoices) {
            $openInvoices = MailAttachment::with('message')
                ->where('funnel_type', 'invoice')
                ->whereIn('funnel_status', ['open', 'needs_review'])
                ->orderByDesc('id')
                ->limit($maxInvoiceScans)
                ->get();

            foreach ($openInvoices as $invAtt) {
                $res = $this->processInvoiceToAccounting($invAtt, isManualAction: false);
                if (($res['status'] ?? '') === 'booked') {
                    $bookedCount++;
                } elseif (($res['status'] ?? '') === 'already_booked') {
                    $alreadyCount++;
                } elseif (($res['status'] ?? '') === 'needs_review') {
                    $reviewCount++;
                }
            }
        }

        return [
            'classified' => $classifiedCount,
            'booked' => $bookedCount,
            'already_booked' => $alreadyCount,
            'needs_review' => $reviewCount,
        ];
    }

    public function resolveSafeLocalAttachmentPath(MailAttachment $attachment): ?string
    {
        $rel = ltrim(str_replace('\\', '/', (string) $attachment->path), '/');
        if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }
        if (!Storage::disk('local')->exists($rel)) {
            return null;
        }
        $full = Storage::disk('local')->path($rel);
        if (!is_file($full) || is_link($full)) {
            return null;
        }
        $realFull = realpath($full);
        $realStorageRoot = realpath(storage_path('app'));
        if (!$realFull || !$realStorageRoot || !str_starts_with($realFull, $realStorageRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realFull;
    }

    private function resolveBusinessFlag(
        array $analysis,
        MailAttachment $attachment,
        ?MailMessage $message,
        ?MailAccount $account
    ): bool {
        $isCommercialMailbox = (bool) ($account?->is_commercial ?? true);
        $haystack = mb_strtolower(implode(' ', [
            (string) ($analysis['merchant'] ?? ''),
            (string) ($analysis['title'] ?? ''),
            (string) ($analysis['description'] ?? ''),
            (string) ($attachment->filename ?? ''),
            (string) ($message?->subject ?? ''),
            (string) ($message?->from_email ?? ''),
            mb_substr((string) ($message?->body_plain ?? ''), 0, 2500),
        ]));

        $hasExplicitSeelenfunkeContext = false;
        foreach (self::BUSINESS_PARTNER_KEYWORDS as $kw) {
            if (str_contains($haystack, $kw)) {
                $hasExplicitSeelenfunkeContext = true;
                break;
            }
        }

        if (!$isCommercialMailbox) {
            return $hasExplicitSeelenfunkeContext;
        }

        return (bool) ($analysis['is_business'] ?? true);
    }

    /**
     * Deterministischer Fallback für Text-PDF-Rechnungen (z. B. wenn lokal kein Cloud-Vision-Key aktiv ist).
     * Greift ausschließlich, wenn im PDF ein eindeutiges Total-Label mit gültigem Euro-Betrag > 0 steht.
     */
    private function tryExtractDeterministicPdfInvoice(
        string $fullPath,
        MailAttachment $attachment,
        ?MailMessage $message
    ): ?array {
        try {
            $bin = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
            if ($bin === '') {
                return null;
            }
            $cmd = escapeshellcmd($bin) . ' -layout -nopgbrk ' . escapeshellarg($fullPath) . ' - 2>/dev/null';
            $text = (string) @shell_exec($cmd);
            if (trim($text) === '') {
                return null;
            }

            $amount = null;
            if (preg_match_all('/(?:gesamtbetrag|rechnungsbetrag|zu\s+zahlender\s+betrag|endbetrag|summe\s+brutto|bruttobetrag|total(?:\s+eur)?)[^\d\n]{0,40}(\d{1,6}(?:\.\d{3})*,\d{2}|\d{1,6}\.\d{2})\s*(?:€|eur)?/iu', $text, $matches) && !empty($matches[1])) {
                $rawNum = (string) end($matches[1]);
                if (str_contains($rawNum, ',')) {
                    $rawNum = str_replace('.', '', $rawNum);
                    $rawNum = str_replace(',', '.', $rawNum);
                }
                $parsed = (float) $rawNum;
                if ($parsed > 0.0 && $parsed < 250000.0) {
                    $amount = $parsed;
                }
            }

            if ($amount === null) {
                return null;
            }

            $invNumber = '';
            if (preg_match('/(?:rechnungsnummer|rechnungs-nr\.?|rechnung\s+nr\.?|invoice\s*(?:no|number|#))[:\s#]*([A-Za-z0-9\-\/_]{3,32})/iu', $text, $invMatch)) {
                $invNumber = trim($invMatch[1]);
            }

            $issueDate = $message?->received_at
                ? Carbon::parse($message->received_at)->format('Y-m-d')
                : date('Y-m-d');
            if (preg_match('/(?:rechnungsdatum|datum|belegdatum|date)[:\s]*(\d{2}\.\d{2}\.\d{4}|\d{4}-\d{2}-\d{2})/iu', $text, $dtMatch)) {
                try {
                    $issueDate = Carbon::parse($dtMatch[1])->format('Y-m-d');
                } catch (\Throwable) {
                }
            }

            $merchant = trim((string) ($message?->from_name ?: ''));
            if ($merchant === '' && !empty($message?->from_email)) {
                $domain = substr(strrchr((string) $message->from_email, '@') ?: '', 1);
                $merchant = ucfirst((string) strtok($domain, '.'));
            }
            if ($merchant === '') {
                $merchant = pathinfo((string) $attachment->filename, PATHINFO_FILENAME) ?: 'Lieferant';
            }

            $taxRate = str_contains($text, '7%') || str_contains($text, '7 %') || str_contains($text, '7,00 %') ? 7 : 19;
            if (preg_match('/(kleinunternehmer|§\s*19|steuerfrei|0\s*%\s*mwst|reverse[\s\-]*charge)/iu', $text)) {
                $taxRate = 0;
            }

            return [
                'merchant' => $merchant,
                'title' => $invNumber !== '' ? "{$merchant} - {$invNumber}" : "{$merchant} - Rechnung",
                'description' => (string) ($message?->subject ?: $attachment->filename),
                'location' => $merchant,
                'amount' => $amount,
                'amount_abs' => $amount,
                'date' => $issueDate,
                'category' => 'Sonstiges',
                'is_business' => true,
                'tax_rate' => $taxRate,
                'invoice_number' => $invNumber,
            ];
        } catch (\Throwable $e) {
            Log::warning('MailAttachmentFunnelService PDF fallback error: ' . $e->getMessage());
            return null;
        }
    }
}
