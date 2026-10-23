<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

class ContractDocumentService
{
    /**
     * Erstellt einen sauberen Dateinamen nach Standard:
     * YYYY-MM-DD_Anbieter_Titel.ext
     */
    public function generateLogicalFileName(?string $date, ?string $provider, ?string $itemName, string $extension): string
    {
        $cleanDate = $this->sanitizeDate($date);
        $cleanProvider = $this->sanitizeSlug($provider ?? '', 35);
        $cleanName = $this->sanitizeSlug($itemName ?? '', 45);
        $cleanExt = preg_replace('/[^a-z0-9]/', '', strtolower(ltrim(trim($extension), '.')));
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'txt', 'csv', 'xlsx', 'xls', 'zip'];
        if (empty($cleanExt) || !in_array($cleanExt, $allowedExtensions, true)) {
            $cleanExt = 'pdf';
        }

        if (empty($cleanName)) {
            $cleanName = 'Vertrag';
        }

        // Falls Provider leer ist oder im Namen bereits vorkommt (oder umgekehrt)
        if (!empty($cleanProvider)) {
            if (strcasecmp($cleanProvider, $cleanName) === 0) {
                $baseName = "{$cleanDate}_{$cleanProvider}";
            } elseif (stripos($cleanName, $cleanProvider) !== false) {
                // Name enthält bereits den Provider (z.B. Name "ADAC Autoversicherung", Provider "ADAC")
                $baseName = "{$cleanDate}_{$cleanName}";
            } elseif (stripos($cleanProvider, $cleanName) !== false) {
                // Provider enthält bereits den Namen (z.B. Provider "ADAC Autoversicherung", Name "Autoversicherung")
                $baseName = "{$cleanDate}_{$cleanProvider}";
            } else {
                $baseName = "{$cleanDate}_{$cleanProvider}_{$cleanName}";
            }
        } else {
            $baseName = "{$cleanDate}_{$cleanName}";
        }

        // Sicherstellen, dass keine doppelten Trennzeichen vorliegen
        $baseName = preg_replace('/_+/', '_', $baseName);
        $baseName = trim($baseName, '_-');

        return "{$baseName}.{$cleanExt}";
    }

    /**
     * Erstellt den vollständigen relativen Speicherpfad:
     * buchhaltung/contracts/YYYY/MM/YYYY-MM-DD_Anbieter_Titel.ext
     */
    public function generateLogicalPath(?string $date, ?string $provider, ?string $itemName, string $extension): string
    {
        $cleanDate = $this->sanitizeDate($date);
        $fileName = $this->generateLogicalFileName($cleanDate, $provider, $itemName, $extension);

        $year = substr($cleanDate, 0, 4);
        $month = substr($cleanDate, 5, 2);

        return "buchhaltung/contracts/{$year}/{$month}/{$fileName}";
    }

    /**
     * Speichert eine Vertragsdatei logisch benannt und strukturiert ab.
     * Bereinigt bei Bedarf alte Dateien und vermeidet Namenskollisionen.
     */
    public function storeContractFile(UploadedFile|string $file, array $itemData, ?string $oldPath = null, string $disk = 'local'): string
    {
        $extension = $this->extractExtension($file);
        $date = $itemData['first_payment_date'] ?? $itemData['start_date'] ?? date('Y-m-d');
        $provider = $itemData['provider_company'] ?? '';
        $name = $itemData['name'] ?? 'Vertrag';

        $targetPath = $this->generateLogicalPath($date, $provider, $name, $extension);

        // Dateikollisionen auflösen
        $targetPath = $this->resolveCollision($targetPath, $file, $disk, $oldPath);

        // Datei im Zielpfad speichern
        $contents = $this->getFileContents($file);
        Storage::disk($disk)->put($targetPath, $contents);

        // Alten Pfad löschen, falls geändert
        if (!empty($oldPath) && $oldPath !== $targetPath) {
            $this->cleanupOldFile($oldPath, $disk);
        }

        return $targetPath;
    }

    /**
     * Analysiert ein Vertragsdokument mit KI (oder lokalem Textparser als Fallback).
     * Gibt ein strukturiertes Ergebnis zurück inklusive Vorschau des logischen Pfads.
     */
    public function analyzeDocument(UploadedFile|string $file): ?array
    {
        // 1. Text aus PDF extrahieren (falls möglich)
        $extractedText = $this->extractPdfText($file);

        // 2. Gemini KI Abfrage versuchen (Multimodal oder Text)
        $geminiResult = $this->callGeminiForContract($file, $extractedText);
        if ($geminiResult && !empty($geminiResult['success'])) {
            return $this->formatAnalysisResult($geminiResult, $file);
        }

        // 3. Fallback: Lokale Heuristik / Regex auf extrahiertem Text
        if (!empty($extractedText)) {
            $heuristicResult = $this->extractHeuristically($extractedText);
            if ($heuristicResult) {
                return $this->formatAnalysisResult($heuristicResult, $file);
            }
        }

        // 4. Minimaler Fallback basierend auf Dateinamen
        return $this->fallbackFromFilename($file);
    }

    /**
     * Bereinigt Datumsangaben zu YYYY-MM-DD.
     */
    public function sanitizeDate(?string $date): string
    {
        if (empty($date)) {
            return date('Y-m-d');
        }

        try {
            $parsed = Carbon::parse($date);
            if ($parsed->year >= 2000 && $parsed->year <= 2100) {
                return $parsed->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // Ignorieren und Fallback verwenden
        }

        return date('Y-m-d');
    }

    /**
     * Bereinigt einen String für Dateinamen (Umlaute, Sonderzeichen, Rechtsformen).
     */
    public function sanitizeSlug(string $string, int $maxLength = 40): string
    {
        $trans = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss',
        ];
        $str = strtr($string, $trans);

        // Bekannte Rechtsformen entfernen für sauberere Dateinamen
        $str = preg_replace('/\b(GmbH & Co\.?\s*KG|GmbH|AG|UG|e\.?K\.?|Inc\.?|LLC|GbR|OHG|KGaA)\b/i', '', $str);

        // Alle Leer- und Sonderzeichen durch Unterstrich ersetzen
        $str = preg_replace('/[^A-Za-z0-9]+/', '_', $str);
        $str = preg_replace('/_+/', '_', $str);
        $str = trim($str, '_');

        if (mb_strlen($str) > $maxLength) {
            $str = mb_substr($str, 0, $maxLength);
            $str = trim($str, '_');
        }

        return $str;
    }

    /**
     * Extrahiert die Dateiendung.
     */
    protected function extractExtension(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            $ext = $file->getClientOriginalExtension();
            return !empty($ext) ? strtolower($ext) : 'pdf';
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        return !empty($ext) ? strtolower($ext) : 'pdf';
    }

    /**
     * Ermittelt den Dateiinhalt.
     */
    protected function getFileContents(UploadedFile|string $file): string
    {
        if ($file instanceof UploadedFile) {
            return (string) file_get_contents($file->getRealPath());
        }

        if (str_contains($file, '..') || str_contains($file, "\0")) {
            return '';
        }

        if (Storage::disk('local')->exists($file)) {
            return (string) Storage::disk('local')->get($file);
        }

        $realPath = $this->getRealPath($file);
        if ($realPath && is_file($realPath)) {
            return (string) file_get_contents($realPath);
        }

        return '';
    }

    /**
     * Löst Dateikollisionen auf, wenn am Zielpfad bereits eine andere Datei liegt.
     */
    protected function resolveCollision(string $targetPath, UploadedFile|string $file, string $disk, ?string $oldPath): string
    {
        if (!Storage::disk($disk)->exists($targetPath)) {
            return $targetPath;
        }

        // Falls es die alte Datei des selben Eintrags ist, überschreiben
        if ($oldPath === $targetPath) {
            return $targetPath;
        }

        // Falls der Inhalt identisch ist, ebenfalls überschreiben
        $existingContent = Storage::disk($disk)->get($targetPath);
        $newContent = $this->getFileContents($file);
        if (md5($existingContent) === md5($newContent)) {
            return $targetPath;
        }

        // Ansonsten Zähler anhängen
        $pathInfo = pathinfo($targetPath);
        $dir = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $ext = $pathInfo['extension'];

        $counter = 1;
        do {
            $newPath = "{$dir}/{$filename}_{$counter}.{$ext}";
            $counter++;
        } while (Storage::disk($disk)->exists($newPath));

        return $newPath;
    }

    /**
     * Löscht eine alte Datei aus dem Storage.
     */
    protected function cleanupOldFile(string $oldPath, string $disk): void
    {
        $normalized = str_replace('\\', '/', $oldPath);
        if (str_contains($normalized, '..') || str_contains($normalized, "\0") || !str_starts_with(ltrim($normalized, '/'), 'buchhaltung/')) {
            return;
        }

        try {
            if (Storage::disk($disk)->exists($oldPath)) {
                Storage::disk($disk)->delete($oldPath);
            } elseif (Storage::disk('private')->exists($oldPath)) {
                Storage::disk('private')->delete($oldPath);
            }
        } catch (\Throwable $e) {
            Log::warning("ContractDocumentService: Konnte alte Datei nicht löschen: {$oldPath} - " . $e->getMessage());
        }
    }

    /**
     * Ermittelt den echten Dateipfad auf dem Dateisystem.
     */
    protected function getRealPath(UploadedFile|string $file): ?string
    {
        if ($file instanceof UploadedFile) {
            return $file->getRealPath();
        }

        if (str_contains($file, '..') || str_contains($file, "\0")) {
            return null;
        }

        $storageRoot = realpath(storage_path());
        $tempRoot = realpath(sys_get_temp_dir());

        $checkAllowed = function (?string $candidate) use ($storageRoot, $tempRoot): ?string {
            if (!$candidate) {
                return null;
            }
            if (($storageRoot && str_starts_with($candidate, $storageRoot . DIRECTORY_SEPARATOR))
                || ($tempRoot && str_starts_with($candidate, $tempRoot . DIRECTORY_SEPARATOR))) {
                return $candidate;
            }
            return null;
        };

        if (file_exists($file)) {
            return $checkAllowed(realpath($file) ?: null);
        }

        $storagePath = storage_path('app/' . ltrim($file, '/'));
        if (file_exists($storagePath)) {
            return $checkAllowed(realpath($storagePath) ?: null);
        }

        return null;
    }

    /**
     * Extrahiert Text aus einer PDF-Datei über Smalot\PdfParser.
     */
    protected function extractPdfText(UploadedFile|string $file): string
    {
        $realPath = $this->getRealPath($file);
        if (!$realPath || !file_exists($realPath)) {
            return '';
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return '';
        }

        try {
            if (class_exists(Parser::class)) {
                $parser = new Parser();
                $pdf = $parser->parseFile($realPath);
                return trim($pdf->getText() ?? '');
            }
        } catch (\Throwable $e) {
            Log::info('ContractDocumentService: Lokaler PDF-Parser übersprungen/fehlgeschlagen: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Ruft Gemini API auf zur Vertragsanalyse.
     */
    protected function callGeminiForContract(UploadedFile|string $file, string $extractedText = ''): ?array
    {
        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        if (empty($apiKey)) {
            return null;
        }

        $realPath = $this->getRealPath($file);
        if (!$realPath || !file_exists($realPath)) {
            return null;
        }

        $systemPrompt = <<<PROMPT
Du bist ein hochpräziser Vertragsprüfer und Buchhaltungs-Assistent für deutsche Unternehmen (Firma: Seelenfunke).
Analysiere dieses Vertragsdokument sorgfältig. Extrahiere alle Vertragsdaten und Stammdaten so vollständig wie möglich, sodass ALLE Formularfelder in der Buchhaltung automatisch befüllt werden können.

Antworte AUSSCHLIESSLICH im folgenden JSON-Format ohne Markdown-Fences (kein ```json):
{
  "name": "Prägnante, treffende Bezeichnung der Kostenstelle (z.B. Autoversicherung GF-HS 232, Betriebshaftpflicht, Webhosting Seelenfunke, Büromiete)",
  "amount": 12.34,
  "interval_months": 1,
  "tax_rate": 19,
  "first_payment_date": "YYYY-MM-DD (Vertragsbeginn oder erste Zahlung)",
  "contract_end_date": "YYYY-MM-DD (Ablaufdatum oder nächstes Verlängerungsdatum, null wenn unbefristet)",
  "notice_period": "Kündigungsfrist (z.B. '1 Monat zum Ablauf', '3 Monate zum Jahresende')",
  "is_business": true,
  "provider_company": "Offizieller Firmenname des Vertragspartners / Anbieters (z.B. ADAC Autoversicherung AG, Mittwald CM Service)",
  "provider_clean": "Kurzer Markenname (z.B. ADAC, Mittwald, Canva, Ionos)",
  "contract_number": "Vertragsnummer, Versicherungsschein-Nr, Kundennummer oder Aktenzeichen",
  "provider_street": "Straße des Vertragspartners",
  "provider_house_number": "Hausnummer des Vertragspartners",
  "provider_zip": "PLZ des Vertragspartners",
  "provider_city": "Ort des Vertragspartners",
  "provider_phone": "Telefonnummer des Vertragspartners",
  "provider_email": "E-Mail-Adresse des Vertragspartners",
  "provider_website": "Website URL des Anbieters",
  "tags": ["Array von 3-8 passenden, prägnanten Schlagwörtern / Tags für die deutsche Buchhaltung, z.B. 'Versicherung', 'Auto', 'Kfz', 'ADAC', 'Haftpflicht', 'Kasko', 'Mobilität', 'Absicherung' oder 'Vertrag', 'IT', 'Hosting', 'Mittwald', 'Gewerbe', etc."],
  "summary": "Prägnante Zusammenfassung aller wichtigen Vertragsdetails (z.B. versicherte Gegenstände, Kennzeichen, Tarifleistungen, SF-Klassen, SB, Notizen)"
}

Hinweise:
- amount: Regelmäßiger Zahlbetrag als positive Dezimalzahl (z.B. 387.23 für Jahresbeitrag).
- interval_months: 1 (Monatlich), 3 (Quartalsweise), 6 (Halbjährlich), 12 (Jährlich), 24 (Alle 2 Jahre).
- tax_rate: Steuersatz in Prozent als Zahl: 0, 7 oder 19 (Versicherungssteuer / keine USt = 0, normale Rechnungen = 19).
- is_business: true für betriebliche Ausgaben, false für private.
PROMPT;

        $payload = null;

        // Wenn PDF-Text vorliegt (> 80 Zeichen), verwenden wir Text-Prompt (viel schneller & robuster)
        if (mb_strlen($extractedText) > 80) {
            $truncatedText = mb_substr($extractedText, 0, 15000);
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nDokumententext:\n" . $truncatedText]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                ]
            ];
        } else {
            // Multimodaler Upload (PDF oder Bild als Base64)
            $mime = mime_content_type($realPath) ?: 'application/pdf';
            if (filesize($realPath) > 15 * 1024 * 1024) {
                return null;
            }
            $base64 = base64_encode(file_get_contents($realPath));

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mime,
                                    'data' => $base64
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                ]
            ];
        }

        $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];

        foreach ($models as $model) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                $response = Http::timeout(35)->post($url, $payload);

                if ($response->successful()) {
                    $resData = $response->json();
                    $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;

                    if (!empty($text)) {
                        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
                        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
                        $cleaned = trim($cleaned);

                        $json = json_decode($cleaned, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                            $json['success'] = true;
                            $json['source'] = 'ai';
                            return $json;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ContractDocumentService: Gemini Aufruf mit {$model} fehlgeschlagen: " . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Fallback: Extrahiert Vertragsdaten heuristisch über Regex aus dem PDF-Text.
     */
    protected function extractHeuristically(string $text): ?array
    {
        $result = [
            'success' => true,
            'source' => 'heuristic',
            'provider_company' => '',
            'provider_clean' => '',
            'name' => '',
            'contract_number' => '',
            'first_payment_date' => null,
            'contract_end_date' => null,
            'notice_period' => '',
            'amount' => null,
            'interval_months' => 1,
            'tax_rate' => 19,
            'is_business' => true,
            'provider_street' => '',
            'provider_house_number' => '',
            'provider_zip' => '',
            'provider_city' => '',
            'provider_phone' => '',
            'provider_email' => '',
            'provider_website' => '',
            'summary' => 'Automatisch aus Textstruktur analysiert.'
        ];

        // 1. Vertragsnummer / Versicherungsschein-Nr
        if (preg_match('/(?:Vertrags(?:-|\s*)?(?:nr|nummer)\.?|Versicherungsschein(?:-|\s*)?(?:nr|nummer)\.?|Kunden(?:-|\s*)?(?:nr|nummer)\.?|Policen(?:-|\s*)?(?:nr|nummer)\.?)\s*[:#]?\s*([A-Za-z0-9\-\.\/]{4,30})/iu', $text, $m)) {
            $result['contract_number'] = trim($m[1]);
        }

        // 2. Datum / Ablauf
        if (preg_match('/(?:Ablauf|Vertragsende|Ende der Versicherung|Gültig bis)\s*[:#]?\s*(\d{1,2}\.\d{1,2}\.\d{4})/iu', $text, $m)) {
            $result['contract_end_date'] = $this->sanitizeDate($m[1]);
        }
        if (preg_match('/(?:Beginn|Vertragsbeginn|Gültig ab|Versicherungsbeginn)\s*[:#]?\s*(\d{1,2}\.\d{1,2}\.\d{4})/iu', $text, $m)) {
            $result['first_payment_date'] = $this->sanitizeDate($m[1]);
        }

        // 3. Betrag & Intervall
        if (preg_match('/(?:Gesamtbeitrag|Gesamtbetrag|Beitrag|Summe|Zahlbetrag)\s*[:#]?\s*(\d{1,5}[,\.]\d{2})\s*(?:€|EUR)/iu', $text, $m)) {
            $result['amount'] = (float) str_replace(',', '.', $m[1]);
        }
        if (preg_match('/(?:jährlich|12 Monate|pro Jahr)/iu', $text)) {
            $result['interval_months'] = 12;
        } elseif (preg_match('/(?:vierteljährlich|quartalsweise|3 Monate)/iu', $text)) {
            $result['interval_months'] = 3;
        } elseif (preg_match('/(?:halbjährlich|6 Monate)/iu', $text)) {
            $result['interval_months'] = 6;
        }

        // 4. Kündigungsfrist
        if (preg_match('/(?:Kündigungsfrist|Kuendigungsfrist|Frist)\s*[:#]?\s*([0-9]+\s*(?:Monat[e]?|Woche[n]?|Tag[e]?)[^,\.\n]*)/iu', $text, $m)) {
            $result['notice_period'] = trim($m[1]);
        }

        // 5. Kontakt & Adresse (E-Mail, Telefon, Ort)
        if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $m)) {
            $result['provider_email'] = trim($m[1]);
        }
        if (preg_match('/(?:Telefon|Tel\.?|Hotline)\s*[:#]?\s*([+0-9\s\/\(\)-]{7,25})/iu', $text, $m)) {
            $result['provider_phone'] = trim($m[1]);
        }
        if (preg_match('/(?:^|\s)(\d{5})\s+([A-ZÄÖÜ][a-zäöüß]+(?:\s+[A-ZÄÖÜ][a-zäöüß]+)?)/u', $text, $m)) {
            $result['provider_zip'] = trim($m[1]);
            $result['provider_city'] = trim($m[2]);
        }

        // 6. Bekannte Anbieter prüfen
        $knownProviders = [
            'ADAC' => 'ADAC Autoversicherung AG',
            'Mittwald' => 'Mittwald CM Service GmbH & Co. KG',
            'Allianz' => 'Allianz Versicherungs-AG',
            'HUK' => 'HUK-COBURG',
            'Check24' => 'CHECK24 Vergleichsportal',
            'Canva' => 'Canva Pty Ltd',
            'Google' => 'Google Ireland Limited',
            'Microsoft' => 'Microsoft Corporation',
            'Adobe' => 'Adobe Systems Software',
            'Strato' => 'STRATO AG',
            'IONOS' => 'IONOS SE',
            'Vodafone' => 'Vodafone GmbH',
            'Telekom' => 'Deutsche Telekom AG',
        ];

        foreach ($knownProviders as $key => $fullName) {
            if (stripos($text, $key) !== false) {
                $result['provider_clean'] = $key;
                $result['provider_company'] = $fullName;
                break;
            }
        }

        // 7. Titel schätzen
        if (stripos($text, 'Autoversicherung') !== false || stripos($text, 'Haftpflicht') !== false) {
            $result['name'] = 'Autoversicherung';
        } elseif (stripos($text, 'Hosting') !== false || stripos($text, 'Server') !== false) {
            $result['name'] = 'Webhosting';
        }

        // 8. Tags ermitteln
        $tags = [];
        if (!empty($result['provider_clean'])) {
            $tags[] = $result['provider_clean'];
        }
        if (stripos($text, 'Auto') !== false || stripos($text, 'Kfz') !== false || stripos($text, 'Fahrzeug') !== false || stripos($text, 'Kennzeichen') !== false) {
            $tags = array_merge($tags, ['Auto', 'Versicherung', 'Kfz', 'Mobilität', 'Absicherung']);
            if (stripos($text, 'Haftpflicht') !== false) $tags[] = 'Haftpflicht';
            if (stripos($text, 'Kasko') !== false || stripos($text, 'Teilkasko') !== false || stripos($text, 'Vollkasko') !== false) $tags[] = 'Kasko';
        } elseif (stripos($text, 'Versicherung') !== false) {
            $tags = array_merge($tags, ['Versicherung', 'Absicherung', 'Vorsorge']);
            if (stripos($text, 'Haftpflicht') !== false) $tags[] = 'Haftpflicht';
        } elseif (stripos($text, 'Hosting') !== false || stripos($text, 'Server') !== false || stripos($text, 'Domain') !== false) {
            $tags = array_merge($tags, ['Vertrag', 'IT', 'Hosting', 'Gewerbe']);
        } elseif (stripos($text, 'Mobilfunk') !== false || stripos($text, 'Smartphone') !== false || stripos($text, 'Handy') !== false) {
            $tags = array_merge($tags, ['Vertrag', 'Mobilfunk', 'Kommunikation', 'Smartphone']);
        } elseif (stripos($text, 'Software') !== false || stripos($text, 'Lizenz') !== false) {
            $tags = array_merge($tags, ['Lizenz', 'Software', 'Abo', 'IT']);
        } else {
            $tags[] = 'Vertrag';
        }
        if ($result['is_business']) {
            $tags[] = 'Gewerbe';
        }
        $result['tags'] = array_values(array_unique(array_filter($tags)));

        return $result;
    }

    /**
     * Minimal-Fallback, wenn keine KI oder Text erkannt wurde.
     */
    protected function fallbackFromFilename(UploadedFile|string $file): array
    {
        $origName = $file instanceof UploadedFile ? $file->getClientOriginalName() : basename($file);
        $info = pathinfo($origName);
        $cleanName = $this->sanitizeSlug($info['filename'] ?? 'Vertrag', 40);

        return $this->formatAnalysisResult([
            'success' => true,
            'source' => 'filename_fallback',
            'provider_company' => '',
            'provider_clean' => '',
            'name' => $cleanName,
            'contract_number' => '',
            'first_payment_date' => date('Y-m-d'),
            'contract_end_date' => null,
            'notice_period' => '',
            'amount' => null,
            'interval_months' => 1,
            'tax_rate' => 19,
            'is_business' => true,
            'provider_street' => '',
            'provider_house_number' => '',
            'provider_zip' => '',
            'provider_city' => '',
            'provider_phone' => '',
            'provider_email' => '',
            'provider_website' => '',
            'summary' => 'Standard-Benennung basierend auf Dateinamen.'
        ], $file);
    }

    /**
     * Formatiert das Analyseergebnis und berechnet den logischen Zielpfad.
     */
    protected function formatAnalysisResult(array $data, UploadedFile|string $file): array
    {
        $extension = $this->extractExtension($file);
        $date = $this->sanitizeDate($data['first_payment_date'] ?? date('Y-m-d'));
        $provider = $data['provider_clean'] ?? $data['provider_company'] ?? '';
        $name = $data['name'] ?? 'Vertrag';

        $suggestedFileName = $this->generateLogicalFileName($date, $provider, $name, $extension);
        $suggestedPath = $this->generateLogicalPath($date, $provider, $name, $extension);

        // Tags bereinigen und normieren
        $tags = [];
        if (!empty($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $t) {
                $cleanTag = trim((string)$t);
                if (!empty($cleanTag) && mb_strlen($cleanTag) <= 30) {
                    $tags[] = $cleanTag;
                }
            }
        }
        if (empty($tags)) {
            if (!empty($provider)) {
                $tags[] = $this->sanitizeSlug($provider, 20);
            }
            $tags[] = 'Vertrag';
            if ($data['is_business'] ?? true) {
                $tags[] = 'Gewerbe';
            }
        }
        $tags = array_values(array_unique($tags));

        return [
            'success' => true,
            'source' => $data['source'] ?? 'ai',
            'provider_company' => $data['provider_company'] ?? '',
            'provider_clean' => $data['provider_clean'] ?? '',
            'name' => $data['name'] ?? '',
            'contract_number' => $data['contract_number'] ?? '',
            'tags' => $tags,
            'first_payment_date' => $date,
            'contract_end_date' => !empty($data['contract_end_date']) ? $this->sanitizeDate($data['contract_end_date']) : null,
            'notice_period' => $data['notice_period'] ?? '',
            'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
            'interval_months' => isset($data['interval_months']) ? (int) $data['interval_months'] : 1,
            'tax_rate' => isset($data['tax_rate']) ? (int) $data['tax_rate'] : 19,
            'is_business' => isset($data['is_business']) ? (bool) $data['is_business'] : true,
            'provider_street' => $data['provider_street'] ?? '',
            'provider_house_number' => $data['provider_house_number'] ?? '',
            'provider_zip' => $data['provider_zip'] ?? '',
            'provider_city' => $data['provider_city'] ?? '',
            'provider_phone' => $data['provider_phone'] ?? '',
            'provider_email' => $data['provider_email'] ?? '',
            'provider_website' => $data['provider_website'] ?? '',
            'summary' => $data['summary'] ?? '',
            'suggested_filename' => $suggestedFileName,
            'suggested_path' => $suggestedPath,
        ];
    }
}
