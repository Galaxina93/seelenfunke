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

        $rawPdfText = $ext === 'pdf' ? $this->extractPdfTextSafe($fullPath) : '';
        $analysis = null;

        // 0-Token-First: Erst deterministisch über E-Rechnungs-XML / ZUGFeRD / Smalot-PdfParser auslesen (Millisekunden, 0 KI-Kosten)
        if (in_array($ext, ['pdf', 'xml'], true)) {
            $deterministicRaw = $this->tryExtractDeterministicPdfInvoice($fullPath, $attachment, $message, $rawPdfText);
            if ($deterministicRaw !== null && !empty($deterministicRaw['amount_abs'])) {
                $enriched = BuchiReceiptPipelineService::enrichScanResult($deterministicRaw, $fullPath);
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

        // Vision-Fallback nur für gescannte Bild-Belege / Kassenbons ohne auslesbaren PDF-Text
        if ((!$analysis || empty($analysis['amount_abs'])) && $ext !== 'xml' && filesize($fullPath) >= 512) {
            $categories = $adminId
                ? AccountingCategory::where('admin_id', $adminId)->orderByDesc('usage_count')->pluck('name')->all()
                : [];
            $analysis = BuchiReceiptPipelineService::analyzeAndVerify($fullPath, $categories, $adminId);
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

        // 0. Bereits als offen markierte Retouren-/Gewährleistungs-Beilagen sauber auf legal_notice / ignored korrigieren
        $misclassifiedBoilerplate = MailAttachment::with('message')
            ->whereIn('funnel_status', ['open', 'needs_review'])
            ->where(function ($q) {
                $q->where('filename', 'like', '%Retourformular%')
                  ->orWhere('filename', 'like', '%retourformular%')
                  ->orWhere('filename', 'like', '%RETOURENSCHEIN%')
                  ->orWhere('filename', 'like', '%retourenschein%')
                  ->orWhere('filename', 'like', '%Gewaehrleistung%')
                  ->orWhere('filename', 'like', '%Gewährleistung%');
            })
            ->limit(100)
            ->get();

        foreach ($misclassifiedBoilerplate as $bpAtt) {
            $cls = $this->classifyAttachment($bpAtt, $bpAtt->message, $bpAtt->message ? $accounts->get($bpAtt->message->mail_account_id) : null);
            if ($cls['funnel_type'] === 'legal_notice') {
                $bpAtt->update([
                    'funnel_type' => 'legal_notice',
                    'funnel_status' => 'ignored',
                    'funnel_meta' => ['reason' => $cls['reason']],
                ]);
            }
        }

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

            $att->update([
                'funnel_type' => $cls['funnel_type'],
                'funnel_status' => $cls['initial_status'],
                'accounting_issue_id' => null,
                'funnel_meta' => array_filter(['reason' => $cls['reason']]),
            ]);
            $classifiedCount++;
        }

        $bookedCount = 0;
        $reconciledCount = 0;
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
                $st = (string) ($res['status'] ?? '');
                if ($st === 'booked') {
                    $bookedCount++;
                } elseif ($st === 'reconciled_attached') {
                    $reconciledCount++;
                } elseif ($st === 'already_booked') {
                    $alreadyCount++;
                } elseif ($st === 'needs_review') {
                    $reviewCount++;
                }
            }
        }

        return [
            'classified' => $classifiedCount,
            'booked' => $bookedCount,
            'reconciled_attached' => $reconciledCount,
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

    private function stampMessageBookedTag(MailMessage $message, string $bookedId): void
    {
        $tags = is_array($message->tags) ? $message->tags : [];
        $tags['ai_receipt_booked_id'] = $bookedId;
        unset($tags['ai_receipt_draft']);
        $message->update(['tags' => $tags]);
    }

    /**
     * Findet eine bereits erfasste Sonderausgabe (z. B. aus Bestellbestätigung oder Multi-Seller-Bestellung),
     * zu der diese Eingangsrechnung gehört.
     */
    private function findMatchingSpecialIssueForReconciliation(
        $adminId,
        array $analysis,
        string $invoiceNumber,
        MailAttachment $attachment,
        ?MailMessage $message,
        string $rawPdfText,
        array $dupCheck
    ): ?AccountingSpecialIssue {
        $query = AccountingSpecialIssue::query();
        if ($adminId !== null && $adminId !== '') {
            $query->where('admin_id', $adminId);
        }

        if (!empty($dupCheck['is_duplicate']) && !empty($dupCheck['existing_id'])) {
            $found = (clone $query)->find($dupCheck['existing_id']);
            if ($found) {
                return $found;
            }
        }

        // Extrahiere alle eindeutigen Bestell- & Auftragsreferenzen aus PDF-Text, XML-Notizen, Betreff, Dateiname und Mail-Body
        $combinedContext = implode("\n", [
            $invoiceNumber,
            (string) ($analysis['description'] ?? ''),
            (string) ($attachment->filename ?? ''),
            (string) ($message?->subject ?? ''),
            mb_substr((string) ($message?->body_plain ?? ''), 0, 3000),
            mb_substr($rawPdfText, 0, 6000),
        ]);

        $refTokens = [];
        if (mb_strlen($invoiceNumber) >= 4) {
            $refTokens[] = $invoiceNumber;
        }

        // Amazon Order-IDs (303-..., 306-..., 028-...), Temu (PO-076-...), eBay (17-15363-69154), Shop-Bestellnummern
        if (preg_match_all('/\b(\d{3}-\d{7}-\d{7}|PO-\d{3}-\d{10,}|\d{2}-\d{5}-\d{5}|\d{8,}-NV|29000\d{5,}|VPJ\d{4,}|B\d{6,}|A\d{6,}|EN\d{12,}|SO\d{4,}|RE\d{6,})\b/iu', $combinedContext, $m)) {
            foreach ($m[1] as $tok) {
                $refTokens[] = trim($tok);
            }
        }

        if (preg_match_all('/(?:bestellnummer|auftragsnummer|bestellung|order(?:\s*id|\s*#)?)\s*[:#]?\s*([A-Za-z0-9\-]{5,30})/iu', $combinedContext, $m2)) {
            foreach ($m2[1] as $tok) {
                $clean = trim($tok);
                if (!in_array(strtolower($clean), ['datum', 'nummer', 'anzahl', 'status', 'amazon', 'bezahlt'], true)) {
                    $refTokens[] = $clean;
                }
            }
        }

        $refTokens = array_values(array_unique(array_filter($refTokens, fn ($t) => mb_strlen($t) >= 5)));

        foreach ($refTokens as $ref) {
            $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $ref) . '%';
            $boundary = '/(?:^|[^A-Za-z0-9])' . preg_quote($ref, '/') . '(?:$|[^A-Za-z0-9])/iu';
            $candidates = (clone $query)
                ->where(function ($sub) use ($likeVal) {
                    $sub->where('invoice_number', 'like', $likeVal)
                        ->orWhere('note', 'like', $likeVal);
                })
                ->latest('execution_date')
                ->limit(15)
                ->get();

            foreach ($candidates as $cand) {
                $candCents = (int) round(((float) $cand->amount) * 100);
                $invCents = (int) ($analysis['amount_cents'] ?? 0);
                // Gleiches Vorzeichen prüfen (keine Gutschrift auf Ausgabe kleben)
                if ($invCents !== 0 && (($candCents < 0) !== ($invCents < 0))) {
                    continue;
                }
                if (preg_match($boundary, (string) $cand->invoice_number) || preg_match($boundary, (string) $cand->note)) {
                    return $cand;
                }
            }
        }

        return null;
    }

    /**
     * Zieht eine fehlende Rechnungsdatei (.pdf / .xml) sowie eine fehlende Rechnungsnummer
     * sauber auf einer bereits vorhandenen Sonderausgabe nach, ohne Dubletten zu erzeugen.
     */
    private function backfillInvoiceOntoExistingIssue(
        MailAttachment $attachment,
        AccountingSpecialIssue $existingIssue,
        string $fullPath,
        ?string $sha256,
        array $analysis,
        string $invoiceNumber,
        bool $isBusiness,
        ?MailMessage $message
    ): array {
        $disk = Storage::disk('local');
        $currentPaths = is_array($existingIssue->file_paths) ? array_values(array_filter($existingIssue->file_paths)) : [];

        $alreadyHasIdenticalFile = false;
        if ($sha256) {
            foreach ($currentPaths as $relPath) {
                if (is_string($relPath) && $relPath !== '' && !str_contains($relPath, '..') && $disk->exists($relPath)) {
                    $existingFull = $disk->path($relPath);
                    if (is_file($existingFull) && @hash_file('sha256', $existingFull) === $sha256) {
                        $alreadyHasIdenticalFile = true;
                        break;
                    }
                }
            }
        }

        $addedFilePath = null;
        $updatedFields = [];

        if (!$alreadyHasIdenticalFile) {
            $storedPath = BuchiReceiptPipelineService::storeReceiptFile(
                file: $fullPath,
                date: (string) ($analysis['date'] ?? ($existingIssue->execution_date?->format('Y-m-d') ?: date('Y-m-d'))),
                merchant: (string) ($analysis['merchant'] ?: $existingIssue->location ?: 'Lieferant'),
                title: (string) ($existingIssue->title ?: $analysis['title']),
                invoiceNumber: $invoiceNumber
            );

            if ($storedPath && !in_array($storedPath, $currentPaths, true)) {
                $ext = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
                if ($ext === 'pdf') {
                    array_unshift($currentPaths, $storedPath);
                } else {
                    $currentPaths[] = $storedPath;
                }
                $updatedFields['file_paths'] = array_values(array_unique($currentPaths));
                $addedFilePath = $storedPath;
            }
        }

        if ($invoiceNumber !== '' && $existingIssue->is_business) {
            $existingInv = trim((string) ($existingIssue->invoice_number ?? ''));
            if ($existingInv === '') {
                $updatedFields['invoice_number'] = $invoiceNumber;
            } elseif (stripos($existingInv, $invoiceNumber) === false) {
                $existingAbsCents = abs((int) round(((float) $existingIssue->amount) * 100));
                $incomingAbsCents = abs((int) ($analysis['amount_cents'] ?? 0));
                if ($incomingAbsCents > 0 && ($incomingAbsCents + 5) < $existingAbsCents) {
                    $updatedFields['invoice_number'] = Str::limit("{$existingInv} (Teilrechnung {$invoiceNumber})", 190, '');
                } elseif (str_contains($existingInv, '(')) {
                    $updatedFields['invoice_number'] = Str::limit("{$invoiceNumber} / {$existingInv}", 190, '');
                } else {
                    $updatedFields['invoice_number'] = Str::limit("{$invoiceNumber} (Bestellnr. {$existingInv})", 190, '');
                }
            }
        }

        if (!empty($updatedFields)) {
            $existingIssue->update($updatedFields);
        }

        if ($message) {
            $this->stampMessageBookedTag($message, (string) $existingIssue->id);
        }

        $wasBackfilled = !empty($updatedFields);
        $dateStr = $existingIssue->execution_date instanceof Carbon
            ? $existingIssue->execution_date->format('Y-m-d')
            : (string) $existingIssue->execution_date;

        $attachment->update([
            'funnel_type' => 'invoice',
            'funnel_status' => $wasBackfilled ? 'booked' : 'already_booked',
            'accounting_issue_id' => (string) $existingIssue->id,
            'sha256_hash' => $sha256,
            'funnel_meta' => array_filter([
                'title' => (string) $existingIssue->title,
                'amount' => (float) $existingIssue->amount,
                'date' => $dateStr,
                'invoice_number' => $existingIssue->invoice_number,
                'is_business' => (bool) $existingIssue->is_business,
                'receipt_path' => $addedFilePath,
                'reconciled_existing' => $wasBackfilled,
                'note' => $wasBackfilled
                    ? "Fehlender Rechnungsbeleg automatisch zur bestehenden Sonderausgabe '{$existingIssue->title}' nachgezogen."
                    : "Beleg ist bereits vollständig bei '{$existingIssue->title}' hinterlegt.",
                'processed_at' => now()->toIso8601String(),
            ], fn ($v) => $v !== null),
        ]);

        return [
            'success' => true,
            'status' => $wasBackfilled ? 'reconciled_attached' : 'already_booked',
            'accounting_issue_id' => (string) $existingIssue->id,
            'message' => $wasBackfilled
                ? "Rechnung automatisch mit bestehender Sonderausgabe '{$existingIssue->title}' abgeglichen und Beleg hinterlegt."
                : "Rechnung ist bereits vollständig bei '{$existingIssue->title}' hinterlegt.",
        ];
    }

    /**
     * Prüft, ob die Eingangsrechnung zu einer laufenden Fixkostenstelle (AccountingCostItem, z. B. Mittwald Hosting) gehört.
     * Archiviert den Monatsbeleg sauber unter buchhaltung/receipts/, ohne eine doppelte Sonderausgabe anzulegen.
     */
    private function reconcileWithFixCostItem(
        $adminId,
        MailAttachment $attachment,
        string $fullPath,
        ?string $sha256,
        array $analysis,
        string $invoiceNumber,
        ?MailMessage $message
    ): ?array {
        $invCents = (int) ($analysis['amount_cents'] ?? 0);
        if ($invCents >= 0) {
            return null;
        }

        $costItems = AccountingCostItem::query()
            ->when($adminId, fn ($q) => $q->whereHas('group', fn ($g) => $g->where('admin_id', $adminId)))
            ->where('amount', '<', 0)
            ->get();

        if ($costItems->isEmpty()) {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', [
            (string) ($analysis['merchant'] ?? ''),
            (string) ($analysis['title'] ?? ''),
            (string) ($message?->from_email ?? ''),
            (string) ($message?->subject ?? ''),
        ]));

        foreach ($costItems as $item) {
            $itemCents = (int) round(((float) $item->amount) * 100);
            if (abs(abs($itemCents) - abs($invCents)) > 2) {
                continue;
            }

            $provider = mb_strtolower(trim((string) ($item->provider_company ?? '')));
            $itemName = mb_strtolower(trim((string) ($item->name ?? '')));
            $contractNo = mb_strtolower(trim((string) ($item->contract_number ?? '')));

            $providerTokens = array_values(array_filter(
                preg_split('/[^a-z0-9äöüß]+/iu', $provider . ' ' . $itemName) ?: [],
                fn ($t) => mb_strlen($t) >= 4 && !in_array($t, ['gmbh', 'service', 'vertrag', 'lizenz', 'online', 'deutschland', 'limited'], true)
            ));

            $matched = ($contractNo !== '' && mb_strlen($contractNo) >= 5 && str_contains($haystack, $contractNo));
            if (!$matched) {
                foreach ($providerTokens as $pt) {
                    if (str_contains($haystack, $pt)) {
                        $matched = true;
                        break;
                    }
                }
            }

            if (!$matched) {
                continue;
            }

            $storedPath = BuchiReceiptPipelineService::storeReceiptFile(
                file: $fullPath,
                date: (string) ($analysis['date'] ?? date('Y-m-d')),
                merchant: (string) ($item->provider_company ?: $analysis['merchant'] ?: $item->name),
                title: (string) ($item->name . ($invoiceNumber !== '' ? " Rechnung {$invoiceNumber}" : '')),
                invoiceNumber: $invoiceNumber
            );

            if ($message) {
                $this->stampMessageBookedTag($message, 'fixcost:' . $item->id);
            }

            $attachment->update([
                'funnel_type' => 'invoice',
                'funnel_status' => 'already_booked',
                'sha256_hash' => $sha256,
                'funnel_meta' => array_filter([
                    'title' => "Fixkosten: {$item->name}",
                    'amount' => (float) $item->amount,
                    'date' => (string) ($analysis['date'] ?? date('Y-m-d')),
                    'invoice_number' => $invoiceNumber ?: null,
                    'fix_cost_id' => (string) $item->id,
                    'receipt_path' => $storedPath,
                    'note' => "Laufende Fixkostenstelle '{$item->name}' abgeglichen und Monatsbeleg archiviert.",
                    'processed_at' => now()->toIso8601String(),
                ], fn ($v) => $v !== null),
            ]);

            return [
                'success' => true,
                'status' => 'already_booked',
                'fix_cost_id' => (string) $item->id,
                'message' => "Rechnung gehört zur laufenden Fixkostenstelle '{$item->name}' und wurde im Belegarchiv gesichert.",
            ];
        }

        return null;
    }

    private function extractPdfTextSafe(string $fullPath): string
    {
        try {
            if (class_exists(PdfParser::class) && filesize($fullPath) <= 15 * 1024 * 1024) {
                $parser = new PdfParser();
                $pdf = $parser->parseFile($fullPath);
                $txt = trim((string) $pdf->getText());
                if ($txt !== '') {
                    return $txt;
                }
            }
        } catch (\Throwable) {
        }

        try {
            $bin = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));
            if ($bin !== '') {
                $cmd = escapeshellcmd($bin) . ' -layout -nopgbrk ' . escapeshellarg($fullPath) . ' - 2>/dev/null';
                return trim((string) @shell_exec($cmd));
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /**
     * Deterministischer 0-Token-Extractor für E-Rechnungen (.xml / ZUGFeRD-.pdf) und Text-PDF-Rechnungen
     * via AccountingEInvoiceParserService + Smalot\PdfParser (funktioniert komplett ohne Cloud-Vision oder pdftotext-Binary).
     */
    private function tryExtractDeterministicPdfInvoice(
        string $fullPath,
        MailAttachment $attachment,
        ?MailMessage $message,
        string $preExtractedText = ''
    ): ?array {
        try {
            // 1. ZUGFeRD / Factur-X / XRechnung strukturiert prüfen
            try {
                $eParsed = app(AccountingEInvoiceParserService::class)->parseFile($fullPath);
                $totals = is_array($eParsed['totals'] ?? null) ? $eParsed['totals'] : [];
                $gross = (float) ($totals['grand_total_gross'] ?? $totals['due_payable_amount'] ?? $totals['gross_amount'] ?? $totals['payable_amount'] ?? $eParsed['gross_amount'] ?? 0.0);
                if ($gross === 0.0 && !empty($totals['tax_basis_net'])) {
                    $gross = (float) $totals['tax_basis_net'] + (float) ($totals['tax_total'] ?? 0.0);
                }
                $absGross = abs($gross);
                if ($absGross > 0.0 && $absGross < 250000.0) {
                    $isCreditNote = !empty($eParsed['is_credit_note'])
                        || in_array((string) ($eParsed['type_code'] ?? ''), ['381', '384', '81'], true)
                        || $gross < 0.0;
                    $sellerName = trim((string) ($eParsed['seller']['name'] ?? ''));
                    $invNo = trim((string) ($eParsed['invoice_number'] ?? ''));
                    $issueDate = trim((string) ($eParsed['issue_date'] ?? '')) ?: ($message?->received_at ? Carbon::parse($message->received_at)->format('Y-m-d') : date('Y-m-d'));
                    $sellerCountry = strtoupper(trim((string) ($eParsed['seller']['country'] ?? 'DE')));
                    $vatRate = 19;
                    if (isset($totals['tax_rate'])) {
                        $r = (int) round((float) $totals['tax_rate']);
                        $vatRate = in_array($r, [0, 7, 19], true) ? $r : 0;
                    } elseif ($sellerCountry !== 'DE' && $sellerCountry !== '') {
                        $vatRate = 0;
                    }
                    $merchant = $sellerName !== '' ? $sellerName : $this->resolveFallbackMerchant($attachment, $message, '');
                    $xmlNotes = !empty($eParsed['notes']) && is_array($eParsed['notes']) ? implode(' ', $eParsed['notes']) : '';
                    return [
                        'merchant' => $merchant,
                        'title' => $invNo !== '' ? "{$merchant} - {$invNo}" : "{$merchant} - Rechnung",
                        'description' => trim((string) ($message?->subject ?: $attachment->filename) . ' ' . $xmlNotes),
                        'location' => $merchant,
                        'amount' => $absGross,
                        'amount_abs' => $absGross,
                        'is_credit_note' => $isCreditNote,
                        'date' => $issueDate,
                        'category' => 'Sonstiges',
                        'is_business' => true,
                        'tax_rate' => $vatRate,
                        'invoice_number' => $invNo,
                    ];
                }
            } catch (\Throwable) {
                // Kein eingebettetes XML -> weiter mit Text-Extraktion
            }

            $text = $preExtractedText !== '' ? $preExtractedText : $this->extractPdfTextSafe($fullPath);
            if (trim($text) === '') {
                return null;
            }

            $amount = null;
            $isCreditNote = (bool) preg_match('/\b(gutschrift|credit\s*note|rückerstattung|erstattung)\b/iu', $text . ' ' . (string) ($message?->subject ?? ''));

            // Standard-Totalzeilen (inkl. Westpack "Insgesamt EUR inkl. MwSt. 145,95", Amazon "Zahlbetrag 229,99 €", Mittwald "Zu zahlender Betrag")
            if (preg_match_all('/(?:gesamtbetrag|rechnungsbetrag|zu\s+zahlender\s+betrag|zahlbetrag|gesamtpreis|insgesamt\s+eur\s+inkl\.?\s*mwst\.?|endbetrag|summe\s+brutto|bruttobetrag|total(?:\s+eur)?)[^\d\-\n]{0,45}(-?\d{1,6}(?:\.\d{3})*,\d{2}|-?\d{1,6}\.\d{2})\s*(?:€|eur)?/iu', $text, $matches) && !empty($matches[1])) {
                $rawNum = (string) end($matches[1]);
                if (str_starts_with(trim($rawNum), '-')) {
                    $isCreditNote = true;
                }
                $parsed = $this->parseEuroString($rawNum);
                if ($parsed > 0.0 && $parsed < 250000.0) {
                    $amount = $parsed;
                }
            }

            // Mehrzeiliger Tabellenblock (z. B. Glücksfieber: "Warenwert Gesamt\nMwSt. 19%\nRechnungsbetrag\n€\n€\n€\n14,28\n2,71\n16,99")
            if ($amount === null && preg_match('/rechnungsbetrag[\s€\n\r]+(?:\d{1,6},\d{2}[\s\n\r]+){1,3}(\d{1,6},\d{2})/iu', $text, $multiMatch)) {
                $parsed = $this->parseEuroString($multiMatch[1]);
                if ($parsed > 0.0 && $parsed < 250000.0) {
                    $amount = $parsed;
                }
            }

            if ($amount === null) {
                return null;
            }

            $invNumber = '';
            if (preg_match('/(?:rechnungsnummer|rechnungs-nr\.?|rechnungs\s*nr\.?|rechnung\s+nr\.?|invoice\s*(?:no|number|#))\s*[:#]?\s*([A-Za-z0-9\-\/_]{4,32})/iu', $text, $invMatch)) {
                $candInv = trim($invMatch[1]);
                if (!in_array(strtolower($candInv), ['buchungsdatum', 'seite', 'datum', 'kunde', 'rechnung'], true)) {
                    $invNumber = $candInv;
                }
            }
            // Fallback aus Dateiname oder Betreff (z. B. "Westpack Rechnung 821293.pdf", "Rechnung R505615.pdf")
            if ($invNumber === '') {
                $nameAndSubj = (string) ($attachment->filename ?? '') . ' ' . (string) ($message?->subject ?? '');
                if (preg_match('/(?:rechnung|invoice|rg)[\s_\-#:]*([A-Z0-9][A-Z0-9\-]{3,28})/iu', $nameAndSubj, $fnInv)) {
                    $candInv = trim($fnInv[1]);
                    if (!str_starts_with(strtoupper($candInv), 'ORD-')) {
                        $invNumber = $candInv;
                    }
                }
            }

            $issueDate = $message?->received_at
                ? Carbon::parse($message->received_at)->format('Y-m-d')
                : date('Y-m-d');

            if (preg_match('/(?:rechnungsdatum(?:\s*\/?\s*lieferdatum)?|buchungsdatum|belegdatum|lübeck,|datum)[:\s]*(\d{2}\.\d{2}\.\d{4}|\d{4}-\d{2}-\d{2})/iu', $text, $dtMatch)) {
                try {
                    $issueDate = Carbon::parse($dtMatch[1])->format('Y-m-d');
                } catch (\Throwable) {
                }
            } elseif (preg_match('/(\d{1,2})\.\s*(januar|februar|märz|maerz|april|mai|juni|juli|august|september|oktober|november|dezember)\s*(20\d{2})/iu', $text, $gerDate)) {
                $monthMap = [
                    'januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4,
                    'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8,
                    'september' => 9, 'oktober' => 10, 'november' => 11, 'dezember' => 12,
                ];
                $mNum = $monthMap[mb_strtolower($gerDate[2])] ?? null;
                if ($mNum) {
                    $issueDate = sprintf('%04d-%02d-%02d', (int) $gerDate[3], $mNum, (int) $gerDate[1]);
                }
            } elseif (preg_match('/\b(\d{2}\.\d{2}\.20\d{2})\b/', $text, $anyDate)) {
                try {
                    $issueDate = Carbon::parse($anyDate[1])->format('Y-m-d');
                } catch (\Throwable) {
                }
            }

            $merchant = $this->resolveFallbackMerchant($attachment, $message, $text);

            $taxRate = (str_contains($text, '7%') || str_contains($text, '7 %') || str_contains($text, '7,00 %')) ? 7 : 19;
            if (preg_match('/(kleinunternehmer|§\s*19|steuerfrei|0\s*%\s*mwst|reverse[\s\-]*charge|25\s*%\s*mwst|dk\d{8})/iu', $text)) {
                $taxRate = 0;
            }

            return [
                'merchant' => $merchant,
                'title' => $invNumber !== '' ? "{$merchant} - {$invNumber}" : "{$merchant} - Rechnung",
                'description' => (string) ($message?->subject ?: $attachment->filename),
                'location' => $merchant,
                'amount' => $amount,
                'amount_abs' => $amount,
                'is_credit_note' => $isCreditNote,
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

    private function parseEuroString(string $rawNum): float
    {
        $clean = trim(ltrim(trim($rawNum), '-'));
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        return (float) $clean;
    }

    private function resolveFallbackMerchant(MailAttachment $attachment, ?MailMessage $message, string $pdfText): string
    {
        $subj = trim((string) ($message?->subject ?? ''));
        if (preg_match('/rechnung\s+von\s+(.+)$/iu', $subj, $m)) {
            return trim($m[1]);
        }

        $fromName = trim((string) ($message?->from_name ?? ''));
        if ($fromName !== '' && !in_array(strtolower($fromName), ['no-reply', 'noreply', 'kundenservice', 'service', 'rechnungen'], true)) {
            return $fromName;
        }

        $lowerPdf = mb_strtolower(mb_substr($pdfText, 0, 1500));
        foreach (self::BUSINESS_PARTNER_KEYWORDS as $kw) {
            if ($kw !== 'mein-seelenfunke' && $kw !== 'mein seelenfunke' && str_contains($lowerPdf, $kw)) {
                return ucwords($kw);
            }
        }

        if (!empty($message?->from_email)) {
            $domain = substr(strrchr((string) $message->from_email, '@') ?: '', 1);
            $base = ucfirst((string) strtok($domain, '.'));
            if ($base !== '') {
                return $base;
            }
        }

        return pathinfo((string) $attachment->filename, PATHINFO_FILENAME) ?: 'Lieferant';
    }
}
