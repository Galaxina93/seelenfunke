<?php

namespace App\Services\AI;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuchiReceiptPipelineService
{
    public const LIVE_MARKER = '[BUCHI-LIVE]';

    /**
     * Seelenfunke Lieferanten- & Partner-Intelligenz:
     * Ordnet bekannte Händler deterministisch Kategorie, Gewerblich-Flag und Steuersatz zu.
     */
    private const SUPPLIER_RULES = [
        'westpack' => ['merchant' => 'Westpack', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 0],
        'glücksfieber' => ['merchant' => 'Glücksfieber', 'category' => 'Rohmaterial', 'is_business' => true, 'tax_rate' => 19],
        'gluecksfieber' => ['merchant' => 'Glücksfieber', 'category' => 'Rohmaterial', 'is_business' => true, 'tax_rate' => 19],
        'printequipment' => ['merchant' => 'PrintEquipment', 'category' => 'Rohmaterial', 'is_business' => true, 'tax_rate' => 19],
        'print equipment' => ['merchant' => 'PrintEquipment', 'category' => 'Rohmaterial', 'is_business' => true, 'tax_rate' => 19],
        'karton.eu' => ['merchant' => 'Karton.eu', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'kartonfritze' => ['merchant' => 'Kartonfritze', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'frau wundervoll' => ['merchant' => 'Frau Wundervoll GmbH', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'frauwundervoll' => ['merchant' => 'Frau Wundervoll GmbH', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'natürlich verpacken' => ['merchant' => 'Natürlich verpacken', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'natuerlich-verpacken' => ['merchant' => 'Natürlich verpacken', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'parterremedia' => ['merchant' => 'Natürlich verpacken', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'rapidpack' => ['merchant' => 'RAPIDPACK GmbH', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'verpacking' => ['merchant' => 'RAPIDPACK GmbH', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'schaumstoffonline' => ['merchant' => 'Schaumstoffonline DE', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'bambulab' => ['merchant' => 'Bambu Lab EU', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 19],
        'bambu lab' => ['merchant' => 'Bambu Lab EU', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 19],
        'munbyn' => ['merchant' => 'MUNBYN', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 19],
        'xtool' => ['merchant' => 'xTool', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 19],
        'makeblock' => ['merchant' => 'xTool', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 19],
        'mittwald' => ['merchant' => 'Mittwald', 'category' => 'Software & Lizenzen', 'is_business' => true, 'tax_rate' => 19],
        'google cloud' => ['merchant' => 'Google Cloud', 'category' => 'Software & Lizenzen', 'is_business' => true, 'tax_rate' => 19],
        'hiscox' => ['merchant' => 'Hiscox', 'category' => 'Beratung & Gründung', 'is_business' => true, 'tax_rate' => 0],
        'interzero' => ['merchant' => 'Interzero / Lizenzero', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'stripe' => ['merchant' => 'Stripe', 'category' => 'Software & Lizenzen', 'is_business' => true, 'tax_rate' => 0],
        'fyrst' => ['merchant' => 'FYRST', 'category' => 'Beratung & Gründung', 'is_business' => true, 'tax_rate' => 0],
        'smartsteuer' => ['merchant' => 'Smartsteuer', 'category' => 'Steuer', 'is_business' => true, 'tax_rate' => 19],
        'lucid' => ['merchant' => 'LUCID / Lizenzero', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'lizenzero' => ['merchant' => 'Lizenzero', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'zulos' => ['merchant' => 'Zentek / Verpackungslizenz', 'category' => 'Verpackungen', 'is_business' => true, 'tax_rate' => 19],
        'kleinanzeigen' => ['merchant' => 'eBay Kleinanzeigen', 'category' => 'Arbeitsmaterial', 'is_business' => true, 'tax_rate' => 0],
    ];

    private const PRIVATE_MERCHANT_HINTS = [
        'rewe', 'edeka', 'lidl', 'aldi', 'kaufland', 'netto', 'penny', 'rossmann', 'dm-drogerie', 'apotheke', 'lieferando',
        'badeland', 'einmalig events', 'anny.co',
    ];

    /**
     * Führt den vorhandenen ReceiptAiScannerService aus und veredelt das Ergebnis
     * mit Seelenfunke-Lieferantenwissen, Cent-Genauigkeit und 3-Stufen-Dublettenprüfung.
     *
     * @param UploadedFile|string $file
     * @param array<int, string> $availableCategories
     * @param string|int|null $adminId
     * @return array<string, mixed>|null
     */
    public static function analyzeAndVerify($file, array $availableCategories = [], $adminId = null): ?array
    {
        $raw = ReceiptAiScannerService::analyze($file, $availableCategories);
        if (!$raw || empty($raw['amount_abs'])) {
            return null;
        }

        $resolvedAdminId = $adminId ?? Auth::guard('admin')->id() ?? Admin::value('id');
        $enriched = self::enrichScanResult($raw, $file);
        $duplicate = self::checkDuplicate(
            adminId: $resolvedAdminId,
            title: (string) $enriched['title'],
            merchant: (string) ($enriched['merchant'] ?? $enriched['location'] ?? ''),
            date: (string) $enriched['date'],
            amountCents: (int) $enriched['amount_cents'],
            invoiceNumber: (string) ($enriched['invoice_number'] ?? ''),
            file: $file
        );

        $enriched['duplicate_check'] = $duplicate;

        return $enriched;
    }

    /**
     * Veredelt beliebige Roh-Ergebnisse (Vision oder ZUGFeRD/XML) mit Lieferantenregeln,
     * KI-Gedankenstrich-Bereinigung und exakten negativen Cent-Integern.
     *
     * @param array<string, mixed> $raw
     * @param UploadedFile|string|null $file
     * @return array<string, mixed>
     */
    public static function enrichScanResult(array $raw, $file = null): array
    {
        $merchant = self::cleanLabel((string) ($raw['merchant'] ?? ''));
        $title = self::cleanLabel((string) ($raw['title'] ?? ''));
        $description = self::cleanLabel((string) ($raw['description'] ?? ''));
        $location = self::cleanLabel((string) ($raw['location'] ?? ''));
        $category = self::cleanLabel((string) ($raw['category'] ?? 'Sonstiges')) ?: 'Sonstiges';
        $invoiceNumber = trim((string) ($raw['invoice_number'] ?? ''));

        $amountFloat = isset($raw['amount_abs'])
            ? abs((float) $raw['amount_abs'])
            : abs((float) str_replace(',', '.', (string) ($raw['amount'] ?? 0)));

        $isCreditNote = !empty($raw['is_credit_note']);
        $amountCents = $isCreditNote
            ? abs((int) round($amountFloat * 100))
            : -abs((int) round($amountFloat * 100));
        $amountDecimal = round($amountCents / 100, 2);
        $amountFormatted = number_format($amountDecimal, 2, '.', '');

        $date = (string) ($raw['date'] ?? date('Y-m-d'));
        try {
            $date = Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            $date = date('Y-m-d');
        }

        $isBusiness = (bool) ($raw['is_business'] ?? true);
        $isSmallBusiness = app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness();
        $defaultTax = $isSmallBusiness ? 0 : (int) round((float) shop_setting('default_tax_rate', 19));
        $hasExplicitTaxRate = array_key_exists('tax_rate', $raw) && $raw['tax_rate'] !== null && $raw['tax_rate'] !== '';
        $explicitTaxRate = $hasExplicitTaxRate ? (int) round((float) $raw['tax_rate']) : null;
        $taxRate = $explicitTaxRate ?? $defaultTax;

        $origFilename = '';
        if ($file instanceof UploadedFile) {
            $origFilename = $file->getClientOriginalName();
        } elseif (is_string($file)) {
            $origFilename = basename($file);
        }

        $haystack = mb_strtolower(trim("{$merchant} {$title} {$description} {$location} {$origFilename}"));

        $matchedSupplierRule = false;
        foreach (self::SUPPLIER_RULES as $needle => $rule) {
            if (str_contains($haystack, $needle)) {
                $merchant = $merchant !== '' ? $merchant : $rule['merchant'];
                if ($location === '') {
                    $location = $rule['merchant'];
                }
                $category = $rule['category'];
                $isBusiness = $rule['is_business'];
                $taxRate = $isSmallBusiness ? 0 : ($explicitTaxRate ?? $rule['tax_rate']);
                $matchedSupplierRule = true;
                break;
            }
        }

        if (!$matchedSupplierRule) {
            $officeKeywords = ['büro', 'buero', 'drucker', 'papier', 'paket', 'klebeband', 'werkstatt', 'laser', 'reinigung', 'isopropanol'];
            $hasOfficeKeyword = false;
            foreach ($officeKeywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    $hasOfficeKeyword = true;
                    break;
                }
            }

            if (!$hasOfficeKeyword) {
                foreach (self::PRIVATE_MERCHANT_HINTS as $privHint) {
                    if (str_contains($haystack, $privHint)) {
                        $isBusiness = false;
                        break;
                    }
                }
            }
        }

        if ($location === '' && $merchant !== '') {
            $location = $merchant;
        }

        if ($title === '') {
            $title = $merchant !== '' ? "{$merchant}: Beleg" : 'Sonderausgabe';
        }

        $suggestedPath = self::buildCleanStoragePath(
            date: $date,
            merchant: $merchant ?: $location,
            title: $title,
            invoiceNumber: $invoiceNumber,
            extension: self::detectExtension($file)
        );

        return array_merge($raw, [
            'merchant' => $merchant,
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'category' => $category,
            'date' => $date,
            'amount_abs' => abs($amountDecimal),
            'amount_cents' => $amountCents,
            'amount' => $amountDecimal,
            'amount_formatted' => $amountFormatted,
            'is_business' => $isBusiness,
            'tax_rate' => $isBusiness ? max(0, min(100, $taxRate)) : 0,
            'invoice_number' => $invoiceNumber,
            'suggested_storage_path' => $suggestedPath,
        ]);
    }

    /**
     * 3-Stufen-Dublettenprüfung:
     * 1) Exakter SHA-256-Datei-Hash gegen bereits gespeicherte Belege
     * 2) Gleiche Rechnungsnummer (mind. 3 Zeichen)
     * 3) Gleiches Kaufdatum + exakter Cent-Betrag + ähnlicher Händler/Titel
     *
     * @param string|int|null $adminId
     * @param UploadedFile|string|null $file
     * @return array{is_duplicate: bool, reason: ?string, existing_id: ?string, existing_title: ?string, existing_date: ?string}
     */
    public static function checkDuplicate(
        $adminId,
        string $title,
        string $merchant,
        string $date,
        int $amountCents,
        string $invoiceNumber = '',
        $file = null
    ): array {
        $empty = [
            'is_duplicate' => false,
            'reason' => null,
            'existing_id' => null,
            'existing_title' => null,
            'existing_date' => null,
        ];

        $query = AccountingSpecialIssue::query();
        if ($adminId !== null && $adminId !== '') {
            $query->where('admin_id', $adminId);
        }

        // 1. SHA-256 Datei-Hash-Abgleich
        $incomingHash = self::computeFileSha256($file);
        if ($incomingHash !== null) {
            $candidatesWithFiles = (clone $query)->whereNotNull('file_paths')->latest('execution_date')->limit(300)->get();
            foreach ($candidatesWithFiles as $issue) {
                $paths = is_array($issue->file_paths) ? $issue->file_paths : [];
                foreach ($paths as $relPath) {
                    if (!is_string($relPath) || $relPath === '' || str_contains($relPath, '..')) {
                        continue;
                    }
                    if (Storage::disk('local')->exists($relPath)) {
                        $existingFull = Storage::disk('local')->path($relPath);
                        if (is_file($existingFull) && @hash_file('sha256', $existingFull) === $incomingHash) {
                            $dateStr = $issue->execution_date instanceof Carbon
                                ? $issue->execution_date->format('Y-m-d')
                                : (string) $issue->execution_date;
                            return [
                                'is_duplicate' => true,
                                'reason' => "Identische Belegdatei bereits am {$dateStr} unter '{$issue->title}' erfasst.",
                                'existing_id' => (string) $issue->id,
                                'existing_title' => (string) $issue->title,
                                'existing_date' => $dateStr,
                            ];
                        }
                    }
                }
            }
        }

        // 2. Exakte Rechnungsnummer (inkl. zusammengesetzter Einträge wie "R505615 (Bestellnr. 306-...)")
        $cleanInv = trim($invoiceNumber);
        if (mb_strlen($cleanInv) >= 3) {
            $invMatch = (clone $query)
                ->where('invoice_number', $cleanInv)
                ->first();

            if (!$invMatch && mb_strlen($cleanInv) >= 4) {
                $likeVal = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $cleanInv) . '%';
                $tokenRegex = '/(?:^|[^A-Za-z0-9\-])' . preg_quote($cleanInv, '/') . '(?:$|[^A-Za-z0-9\-])/iu';
                $tokenCandidates = (clone $query)
                    ->where(function ($sub) use ($likeVal) {
                        $sub->where('invoice_number', 'like', $likeVal)
                            ->orWhere('note', 'like', $likeVal);
                    })
                    ->latest('execution_date')
                    ->limit(25)
                    ->get();

                foreach ($tokenCandidates as $tc) {
                    $invField = (string) ($tc->invoice_number ?? '');
                    $noteField = (string) ($tc->note ?? '');
                    if (preg_match($tokenRegex, $invField) || preg_match($tokenRegex, $noteField)) {
                        $invMatch = $tc;
                        break;
                    }
                }
            }

            if ($invMatch) {
                $dateStr = $invMatch->execution_date instanceof Carbon
                    ? $invMatch->execution_date->format('Y-m-d')
                    : (string) $invMatch->execution_date;
                return [
                    'is_duplicate' => true,
                    'reason' => "Rechnungsnummer '{$cleanInv}' wurde bereits am {$dateStr} gebucht ('{$invMatch->title}').",
                    'existing_id' => (string) $invMatch->id,
                    'existing_title' => (string) $invMatch->title,
                    'existing_date' => $dateStr,
                ];
            }
        }

        // 3. Bestell-zu-Rechnungs-Zeitfenster (-21 bis +5 Tage) + Cent-Betrag (±2 Cent Shop-Rundung) + Händler-/Titel-Übereinstimmung
        if ($date !== '' && $amountCents !== 0) {
            try {
                $parsedDate = Carbon::parse($date);
                $winStart = $parsedDate->copy()->subDays(21)->format('Y-m-d');
                $winEnd = $parsedDate->copy()->addDays(5)->format('Y-m-d');
            } catch (\Throwable) {
                $winStart = $date;
                $winEnd = $date;
            }

            $windowCandidates = (clone $query)
                ->whereBetween('execution_date', [$winStart, $winEnd])
                ->get()
                ->sortBy(function ($cand) use ($date) {
                    $cDate = $cand->execution_date instanceof Carbon
                        ? $cand->execution_date->format('Y-m-d')
                        : substr((string) $cand->execution_date, 0, 10);
                    return abs(strtotime($cDate) - strtotime($date));
                });

            $needleMerchant = mb_strtolower(trim($merchant));
            $needleTitle = mb_strtolower(trim($title));
            $stopWords = ['gmbh', 'rechnung', 'invoice', 'bestellung', 'order', 'shop', 'store', 'deutschland', 'online', 'service', 'noreply', 'no-reply', 'verkauf', 'sales'];
            $merchantTokens = array_values(array_filter(
                preg_split('/[^a-z0-9äöüß]+/iu', $needleMerchant . ' ' . $needleTitle) ?: [],
                fn ($tok) => mb_strlen($tok) >= 4 && !in_array($tok, $stopWords, true) && !ctype_digit($tok)
            ));

            foreach ($windowCandidates as $cand) {
                $candCents = (int) round(((float) $cand->amount) * 100);
                if (($candCents < 0) !== ($amountCents < 0)) {
                    continue;
                }
                if (abs(abs($candCents) - abs($amountCents)) > 2) {
                    continue;
                }

                $candTitle = mb_strtolower(trim((string) $cand->title));
                $candLoc = mb_strtolower(trim((string) $cand->location));
                $candNote = mb_strtolower(mb_substr((string) ($cand->note ?? ''), 0, 1200));
                $candHaystack = "{$candLoc} {$candTitle} {$candNote}";

                $sameMerchant = $needleMerchant !== '' && (
                    str_contains($candLoc, $needleMerchant)
                    || ($candLoc !== '' && str_contains($needleMerchant, $candLoc))
                    || str_contains($candTitle, $needleMerchant)
                );
                $sameTitle = $needleTitle !== '' && (
                    $candTitle === $needleTitle
                    || str_contains($candTitle, $needleTitle)
                    || ($candTitle !== '' && str_contains($needleTitle, $candTitle))
                );

                $tokenHit = false;
                if (!$sameMerchant && !$sameTitle && !empty($merchantTokens)) {
                    foreach ($merchantTokens as $tok) {
                        if ($tok === 'amazon' || $tok === 'paypal' || $tok === 'ebay') {
                            continue;
                        }
                        if (str_contains($candHaystack, $tok)) {
                            $tokenHit = true;
                            break;
                        }
                    }
                }

                if ($sameMerchant || $sameTitle || $tokenHit) {
                    $dateStr = $cand->execution_date instanceof Carbon
                        ? $cand->execution_date->format('Y-m-d')
                        : (string) $cand->execution_date;
                    $euro = number_format($candCents / 100, 2, ',', '.');
                    return [
                        'is_duplicate' => true,
                        'reason' => "Zugehöriger Eintrag gefunden: Am {$dateStr} existiert bereits '{$cand->title}' über {$euro} €.",
                        'existing_id' => (string) $cand->id,
                        'existing_title' => (string) $cand->title,
                        'existing_date' => $dateStr,
                    ];
                }
            }
        }

        return $empty;
    }

    /**
     * Speichert einen Beleg mit logischer Seelenfunke-Dateibenennung in
     * storage/app/buchhaltung/receipts/YYYY/MM/YYYY-MM-DD_Haendler_Kurzbeschreibung.ext
     *
     * @param UploadedFile|string $file
     */
    public static function storeReceiptFile(
        $file,
        string $date,
        string $merchant,
        string $title,
        string $invoiceNumber = '',
        int $fileIndex = 0
    ): ?string {
        $ext = self::detectExtension($file);
        $baseRelPath = self::buildCleanStoragePath($date, $merchant, $title, $invoiceNumber, $ext, $fileIndex);

        $rawBytes = null;
        if ($file instanceof UploadedFile) {
            $realPath = $file->getRealPath();
            if (!$realPath || !is_file($realPath)) {
                return null;
            }
            $rawBytes = file_get_contents($realPath);
        } elseif (is_string($file) && is_file($file) && !is_link($file)) {
            $rawBytes = file_get_contents($file);
        }

        if ($rawBytes === false || $rawBytes === null) {
            return null;
        }

        $disk = Storage::disk('local');
        $incomingHash = hash('sha256', $rawBytes);

        $finalPath = $baseRelPath;
        $counter = 2;
        while ($disk->exists($finalPath)) {
            $existingPath = $disk->path($finalPath);
            if (is_file($existingPath) && @hash_file('sha256', $existingPath) === $incomingHash) {
                return $finalPath;
            }
            $dir = dirname($baseRelPath);
            $nameOnly = pathinfo($baseRelPath, PATHINFO_FILENAME);
            $finalPath = "{$dir}/{$nameOnly}_{$counter}.{$ext}";
            $counter++;
        }

        $disk->put($finalPath, $rawBytes);

        return $finalPath;
    }

    /**
     * Erzeugt den sauberen Ablagepfad analog zu AccountingSeeder.php:
     * buchhaltung/receipts/2026/11/2026-11-20_Westpack_Schmuckboxen.pdf
     */
    public static function buildCleanStoragePath(
        string $date,
        string $merchant,
        string $title,
        string $invoiceNumber = '',
        string $extension = 'pdf',
        int $fileIndex = 0
    ): string {
        try {
            $dt = Carbon::parse($date);
        } catch (\Throwable) {
            $dt = now();
        }

        $year = $dt->format('Y');
        $month = $dt->format('m');
        $dayPrefix = $dt->format('Y-m-d');

        $cleanTitle = $title;
        if ($merchant !== '' && stripos($cleanTitle, $merchant) === 0) {
            $cleanTitle = ltrim(substr($cleanTitle, strlen($merchant)), " -:|·");
        }

        $merchantSlug = self::slugifySegment($merchant ?: 'Beleg', 28);
        $titleSlug = self::slugifySegment($cleanTitle ?: ($invoiceNumber ?: 'Ausgabe'), 42);
        $invSlug = $invoiceNumber !== '' ? self::slugifySegment($invoiceNumber, 20) : '';

        $parts = array_values(array_filter([$dayPrefix, $merchantSlug, $titleSlug, $invSlug]));
        $filenameBase = implode('_', array_unique($parts));
        if ($fileIndex > 0) {
            $filenameBase .= '_' . ($fileIndex + 1);
        }

        $safeExt = preg_replace('/[^a-z0-9]/', '', strtolower($extension)) ?: 'pdf';

        return "buchhaltung/receipts/{$year}/{$month}/{$filenameBase}.{$safeExt}";
    }

    /**
     * Ergänzt den Seeder-Schutz-Marker [BUCHI-LIVE], damit db:seed Live-Scans niemals löscht.
     */
    public static function withLiveMarker(?string $note): string
    {
        $trimmed = trim((string) $note);
        if (str_contains($trimmed, self::LIVE_MARKER)) {
            return $trimmed;
        }

        return $trimmed === '' ? self::LIVE_MARKER : ($trimmed . ' ' . self::LIVE_MARKER);
    }

    /**
     * Erzeugt ein 1:1 kopierfertiges PHP-Array-Snippet für database/seeders/AccountingSeeder.php.
     */
    public static function formatIssueForSeeder(AccountingSpecialIssue $issue): string
    {
        $dateStr = $issue->execution_date instanceof Carbon
            ? $issue->execution_date->format('Y-m-d')
            : (string) $issue->execution_date;
        $amountStr = number_format((float) $issue->amount, 2, '.', '');
        $title = addslashes((string) $issue->title);
        $location = addslashes((string) ($issue->location ?? ''));
        $category = addslashes((string) ($issue->category ?? 'Sonstiges'));
        $taxRate = (int) ($issue->tax_rate ?? 0);
        $isBusiness = $issue->is_business ? 'true' : 'false';
        $cleanNote = trim(str_replace(self::LIVE_MARKER, '', (string) ($issue->note ?? '')));
        $paths = is_array($issue->file_paths) ? $issue->file_paths : [];
        $pathsPhp = '[' . implode(', ', array_map(fn ($p) => "'" . addslashes((string) $p) . "'", $paths)) . ']';

        $lines = [
            "            [",
            "                'title'      => '{$title}',",
            "                'location'   => '{$location}',",
            "                'amount'     => {$amountStr},",
            "                'date'       => '{$dateStr}',",
            "                'category'   => '{$category}',",
            "                'is_business'=> {$isBusiness},",
            "                'tax_rate'   => {$taxRate},",
        ];
        if (!empty($issue->invoice_number)) {
            $inv = addslashes((string) $issue->invoice_number);
            $lines[] = "                'invoice_number' => '{$inv}',";
        }
        if ($cleanNote !== '') {
            $noteEsc = addslashes($cleanNote);
            $lines[] = "                'note'       => '{$noteEsc}',";
        }
        if (!empty($paths)) {
            $lines[] = "                'file_paths' => {$pathsPhp},";
        }
        $lines[] = "            ],";

        return implode("\n", $lines);
    }

    /**
     * Trackt die genutzte Buchhaltungskategorie sauber inkl. is_business Flag.
     */
    public static function trackCategoryUsage($adminId, string $categoryName, bool $isBusiness = false): void
    {
        $clean = trim($categoryName);
        if ($clean === '' || !$adminId) {
            return;
        }

        $cat = AccountingCategory::withTrashed()
            ->where('admin_id', $adminId)
            ->where('name', $clean)
            ->first();

        if ($cat) {
            if ($cat->trashed()) {
                $cat->restore();
            }
            $cat->increment('usage_count');
        } else {
            AccountingCategory::create([
                'admin_id' => $adminId,
                'name' => $clean,
                'is_business' => $isBusiness,
                'usage_count' => 1,
            ]);
        }
    }

    private static function cleanLabel(string $value): string
    {
        $cleaned = str_replace([' – ', ' — '], ' - ', $value);
        $cleaned = preg_replace('/\s+/u', ' ', trim($cleaned)) ?? '';

        return $cleaned;
    }

    private static function slugifySegment(string $value, int $maxLength = 36): string
    {
        $translit = strtr($value, [
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        ]);
        $ascii = Str::ascii($translit);
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $ascii) ?? '';
        $slug = trim($slug, '-');

        return mb_substr($slug !== '' ? $slug : 'Beleg', 0, $maxLength);
    }

    /**
     * @param UploadedFile|string|null $file
     */
    private static function detectExtension($file): string
    {
        if ($file instanceof UploadedFile) {
            return strtolower((string) ($file->getClientOriginalExtension() ?: 'pdf'));
        }
        if (is_string($file) && $file !== '') {
            return strtolower((string) (pathinfo($file, PATHINFO_EXTENSION) ?: 'pdf'));
        }

        return 'pdf';
    }

    /**
     * @param UploadedFile|string|null $file
     */
    private static function computeFileSha256($file): ?string
    {
        if ($file instanceof UploadedFile) {
            $real = $file->getRealPath();
            return ($real && is_file($real)) ? (@hash_file('sha256', $real) ?: null) : null;
        }
        if (is_string($file) && $file !== '' && is_file($file) && !is_link($file)) {
            return @hash_file('sha256', $file) ?: null;
        }

        return null;
    }
}
