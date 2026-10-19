<?php

namespace App\Services\AI;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptAiScannerService
{
    /**
     * Analysiert einen Beleg (Foto, Scan, Bild oder PDF) mit Gemini Multimodal Vision
     * und gibt strukturierte Buchhaltungsdaten zurück.
     *
     * @param UploadedFile|string $file
     * @param array $availableCategories
     * @return array|null
     */
    public static function analyze($file, array $availableCategories = []): ?array
    {
        try {
            $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
            if (empty($apiKey) && !app()->runningUnitTests()) {
                Log::warning('ReceiptAiScannerService: GEMINI_API_KEY ist nicht konfiguriert.');
                return null;
            }

            // In Unit Tests: Fake-Response zurückgeben, falls kein Key oder Mock
            if (app()->runningUnitTests() && empty($apiKey)) {
                return self::mockResponseForTests();
            }

            // Dateipfad und MimeType ermitteln
            if ($file instanceof UploadedFile) {
                $path = $file->getRealPath();
                $mime = $file->getMimeType();
                $originalName = $file->getClientOriginalName();
            } elseif (is_string($file) && file_exists($file)) {
                $path = $file;
                $mime = mime_content_type($file) ?: 'image/jpeg';
                $originalName = basename($file);
            } else {
                return null;
            }

            if (!file_exists($path)) {
                return null;
            }

            // Maximale Dateigröße 15 MB
            if (filesize($path) > 15 * 1024 * 1024) {
                Log::warning("ReceiptAiScannerService: Datei zu groß (>15MB) für Base64-OCR: {$originalName}");
                return null;
            }

            $content = file_get_contents($path);
            $base64 = base64_encode($content);

            // Kategorien für Prompt vorbereiten
            $categoriesList = !empty($availableCategories)
                ? implode(', ', array_slice($availableCategories, 0, 40))
                : 'Bürobedarf, Tanken, Bewirtung, Hardware, Software, Reisekosten, Werkzeug, Porto & Versand, Telefon & Internet, Werbekosten, Einkauf';

            $prompt = <<<PROMPT
Du bist ein hochpräziser Beleg- und Buchhaltungs-Scanner für deutsche Unternehmen (Firma: Seelenfunke).
Analysiere dieses Foto / Bild / PDF eines Kassenbons, einer Quittung, einer Rechnung oder eines Belegs sorgfältig.

Vorhandene Buchungskategorien:
{$categoriesList}

Extrahiere alle Daten und antworte AUSSCHLIESSLICH im folgenden JSON-Format ohne Markdown-Fences, ohne Erklärung:
{
  "merchant": "Name des Geschäfts / Händlers / Ausstellers (z.B. Bauhaus, REWE, Shell, Amazon)",
  "description": "Prägnante Kurzbeschreibung der gekauften Produkte/Leistungen (z.B. Schrauben & Werkzeug, Tanken Diesel, Büromaterial)",
  "title": "Aussagekräftiger Buchungstitel aus Händler und Produkten (z.B. Bauhaus - Schrauben & Dübel, Shell - Tanken)",
  "amount": 29.99,
  "date": "YYYY-MM-DD",
  "location": "Ort/Stadt der Filiale falls auf dem Beleg lesbar (z.B. Gifhorn, Wolfsburg, Berlin), sonst leerer String",
  "category": "Die am besten passende Kategorie aus der obigen Liste oder eine treffende neue Standard-Kategorie",
  "is_business": true,
  "tax_rate": 19,
  "invoice_number": "Rechnungsnummer, Bon-Nr., Beleg-Nr. oder Kassenbon-ID falls lesbar, sonst leerer String"
}

Wichtige Regeln:
1. "amount": Gib den Bruttobetrag (Gesamtsumme inkl. MwSt.) als positive Dezimalzahl mit Punkt an (z.B. 49.99).
2. "date": Im Format YYYY-MM-DD. Wenn kein Jahr auf dem Beleg steht, verwende das aktuelle Kalenderjahr. Wenn unleserlich, verwende das heutige Datum.
3. "is_business": Setze auf true, wenn es sich um typische Betriebsausgaben (Bürobedarf, Elektronik, Software, KFZ/Tanken, Werkzeuge, Geschäftsessen, Porto, Fachliteratur etc.) handelt. Setze auf false nur bei rein privatem Konsum (z.B. Wocheneinkauf Lebensmittel für Haushalt).
4. "tax_rate": Erkannter Hauptsteuersatz in Prozent (meist 19, 7 oder 0).
5. "title": Halte den Titel kompakt und informativ (z.B. "REWE - Bürokaffee & Milch" oder "Hornbach - Holzlack & Pinsel").
PROMPT;

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mime,
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

            $models = ['gemini-2.5-flash', 'gemini-1.5-flash'];

            foreach ($models as $model) {
                try {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                    $response = Http::timeout(40)->post($url, $payload);

                    if ($response->successful()) {
                        $resData = $response->json();
                        $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? null;

                        if (!empty($text)) {
                            // Bereinige Markdown Code-Blöcke
                            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
                            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
                            $cleaned = trim($cleaned);

                            $json = json_decode($cleaned, true);

                            if (json_last_error() === JSON_ERROR_NONE && is_array($json) && isset($json['amount'])) {
                                return self::normalizeResult($json);
                            }
                        }
                    } else {
                        Log::warning("ReceiptAiScannerService Gemini HTTP {$response->status()} with {$model}: " . substr($response->body(), 0, 300));
                    }
                } catch (\Throwable $e) {
                    Log::warning("ReceiptAiScannerService Exception with {$model}: " . $e->getMessage());
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::error("ReceiptAiScannerService unexpected error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Normalisiert das JSON-Ergebnis für die Buchhaltung.
     */
    protected static function normalizeResult(array $raw): array
    {
        $amount = abs((float) ($raw['amount'] ?? 0.0));

        // Datum validieren oder Fallback auf heute
        $date = date('Y-m-d');
        if (!empty($raw['date'])) {
            try {
                $date = Carbon::parse($raw['date'])->format('Y-m-d');
            } catch (\Throwable $e) {
                $date = date('Y-m-d');
            }
        }

        // Steuersatz normieren
        $taxRate = isset($raw['tax_rate']) ? (float) $raw['tax_rate'] : 19.0;
        if (!in_array($taxRate, [19.0, 7.0, 0.0])) {
            $taxRate = 19.0;
        }

        $title = trim($raw['title'] ?? '');
        if (empty($title)) {
            $merchant = trim($raw['merchant'] ?? 'Beleg');
            $desc = trim($raw['description'] ?? '');
            $title = $desc ? "{$merchant} - {$desc}" : $merchant;
        }

        return [
            'success' => true,
            'title' => $title,
            'merchant' => $raw['merchant'] ?? '',
            'description' => $raw['description'] ?? '',
            'amount_abs' => $amount,
            'amount_formatted' => '-' . number_format($amount, 2, '.', ''), // Sonderausgaben sind negativ in der DB
            'date' => $date,
            'location' => trim($raw['location'] ?? ''),
            'category' => trim($raw['category'] ?? 'Einkauf'),
            'is_business' => (bool) ($raw['is_business'] ?? true),
            'tax_rate' => $taxRate,
            'invoice_number' => trim((string) ($raw['invoice_number'] ?? '')),
            'raw' => $raw,
        ];
    }

    /**
     * Fallback für automatisierte Tests
     */
    protected static function mockResponseForTests(): array
    {
        return [
            'success' => true,
            'title' => 'Test-Händler - Bürobedarf',
            'merchant' => 'Test-Händler',
            'description' => 'Bürobedarf',
            'amount_abs' => 19.99,
            'amount_formatted' => '-19.99',
            'date' => date('Y-m-d'),
            'location' => 'Gifhorn',
            'category' => 'Bürobedarf',
            'is_business' => true,
            'tax_rate' => 19.0,
            'invoice_number' => 'RE-2026-TEST',
            'raw' => []
        ];
    }
}
